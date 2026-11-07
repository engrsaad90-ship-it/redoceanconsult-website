<?php
// PayPal Checkout for online orders.
//   action=create  → creates a PayPal order for the amount saved on the server for this quotation
//   action=capture → captures the approved payment, checks it with PayPal, marks the order paid
header('Content-Type: application/json');
require __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Method not allowed');
if (!paypal_enabled()) fail(503, 'Online payment is not available. Please contact us.');
$in = json_decode(file_get_contents('php://input'), true);
$order = load_order($in['ref'] ?? '');
if (!$order) fail(404, 'Order not found.');
if ($order['status'] === 'paid') fail(409, 'This order has already been paid.');

function pp($method, $path, $body = null) {
  static $token = null;
  $s = settings();
  if ($token === null) {
    $ch = curl_init(paypal_api() . '/v1/oauth2/token');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
      CURLOPT_USERPWD => $s['paypal_client_id'] . ':' . $s['paypal_secret'], CURLOPT_POSTFIELDS => 'grant_type=client_credentials']);
    $r = json_decode(curl_exec($ch), true);
    curl_close($ch);
    if (empty($r['access_token'])) fail(502, 'Could not connect to PayPal. Please try again.');
    $token = $r['access_token'];
  }
  $ch = curl_init(paypal_api() . $path);
  curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", 'Content-Type: application/json', 'PayPal-Request-Id: ' . bin2hex(random_bytes(12))],
    CURLOPT_POSTFIELDS => $body === null ? '' : json_encode($body)]);
  $raw = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$code, json_decode($raw, true) ?: []];
}

$action = $in['action'] ?? '';

if ($action === 'create') {
  [$code, $r] = pp('POST', '/v2/checkout/orders', [
    'intent' => 'CAPTURE',
    'purchase_units' => [[
      'reference_id' => $order['ref'],
      'custom_id'    => $order['ref'],
      'invoice_id'   => $order['ref'],
      'description'  => substr("{$order['service']} – {$order['client']['project']}", 0, 127),
      'amount'       => ['currency_code' => 'USD', 'value' => $order['usd']],
    ]],
    'application_context' => ['brand_name' => 'Red Ocean Consult', 'shipping_preference' => 'NO_SHIPPING', 'user_action' => 'PAY_NOW'],
  ]);
  if ($code >= 300 || empty($r['id'])) fail(502, 'PayPal could not start the payment. Please try again.');
  $order['paypal_order_id'] = $r['id'];
  save_order($order);
  echo json_encode(['ok' => true, 'id' => $r['id']]);
  exit;
}

if ($action === 'capture') {
  $id = $in['orderID'] ?? '';
  if ($id === '' || $id !== ($order['paypal_order_id'] ?? '')) fail(400, 'Payment does not match this order.');
  [$code, $r] = pp('POST', '/v2/checkout/orders/' . rawurlencode($id) . '/capture');
  $cap = $r['purchase_units'][0]['payments']['captures'][0] ?? null;
  $ok = $code < 300 && ($r['status'] ?? '') === 'COMPLETED' && $cap && $cap['status'] === 'COMPLETED'
     && $cap['amount']['currency_code'] === 'USD' && $cap['amount']['value'] === $order['usd']
     && ($cap['custom_id'] ?? $r['purchase_units'][0]['custom_id'] ?? $order['ref']) === $order['ref'];
  if (!$ok) {
    $issue = $r['details'][0]['issue'] ?? '';
    if ($issue === 'INSTRUMENT_DECLINED') fail(402, 'Your payment method was declined. Please try another card or PayPal account.');
    fail(402, 'We could not confirm this payment. Please contact us with your order reference ' . $order['ref'] . ' before trying again.');
  }

  $order['status'] = 'paid';
  $order['paid_at'] = date('c');
  $order['payment'] = ['method' => 'PayPal', 'capture_id' => $cap['id'], 'amount_usd' => $cap['amount']['value'], 'payer' => $r['payer']['email_address'] ?? ''];
  save_order($order);
  log_csv(order_row($order, 'paid (PayPal)', $cap['id']));

  $s = settings(); $c = $order['client'];
  send_mail($s['notify_to'], "PAID: {$order['ref']} – {$order['service']} – USD {$order['usd']}",
    "PayPal payment received.\n\nCapture ID: {$cap['id']}\nPayer: {$order['payment']['payer']}\nClient: {$c['name']} ({$c['email']}, {$c['phone']})\nCompany: {$c['company']}\n\n" . quote_text($order),
    "{$c['name']} <{$c['email']}>");
  send_mail($c['email'], "Payment received – {$order['ref']}",
    "Dear {$c['name']},\n\nWe have received your payment of USD {$order['usd']} (" . sar($order['total']) . ") for order {$order['ref']}.\nPayPal transaction: {$cap['id']}\n\nOur team will contact you within one business day to collect the contract, programme and project records.\n\n" . quote_text($order) . "\nRed Ocean Consult\n+966 58 290 7870 · {$s['notify_to']}\n",
    "Red Ocean Consult <{$s['notify_to']}>");

  echo json_encode(['ok' => true, 'captureId' => $cap['id']]);
  exit;
}

fail(400, 'Unknown action');
