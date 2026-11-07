<?php
// Online order handler for www.redoceanconsult.com
// Recalculates the quotation from pricing.json (never trusts prices sent by the browser),
// saves the order, emails Red Ocean Consult and the client, and returns the payment options.
header('Content-Type: application/json');
require __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Method not allowed');
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) fail(400, 'Invalid request');
if (!empty($in['website'])) { echo json_encode(['ok' => true, 'ref' => 'RO-0']); exit; } // honeypot

$cfg = json_decode(file_get_contents(__DIR__ . '/pricing.json'), true);
$sid = $in['service'] ?? '';
if (!isset($cfg['services'][$sid])) fail(422, 'Unknown service');
$svc = $cfg['services'][$sid];

$band = null;
foreach ($cfg['sizeBands'] as $b) if ($b['id'] === ($in['band'] ?? '')) $band = $b;
if (!$band) fail(422, 'Please choose the project value.');

$units = 1;
if (isset($svc['unit'])) {
  $units = (int)($in['units'] ?? 0);
  if ($units < $svc['unit']['min'] || $units > $svc['unit']['max']) fail(422, 'Please check the ' . strtolower($svc['unit']['label']) . '.');
}
$express = !empty($in['express']);
$picked  = is_array($in['items'] ?? null) ? $in['items'] : [];
if (($svc['choice'] ?? '') === 'single') {
  $picked = array_values(array_intersect($picked, array_column($svc['items'], 'id')));
  if (count($picked) !== 1) fail(422, 'Please choose one option.');
}

$c = $in['client'] ?? [];
$client = [
  'name'    => clean($c['name'] ?? ''),
  'email'   => filter_var(trim($c['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '',
  'phone'   => clean($c['phone'] ?? ''),
  'company' => clean($c['company'] ?? ''),
  'project' => clean($c['project'] ?? ''),
  'notes'   => trim(strip_tags((string)($c['notes'] ?? ''))),
];
if ($client['name'] === '' || $client['email'] === '' || $client['project'] === '') fail(422, 'Please add your name, email and project name.');

// Same calculation as the website
$lines = []; $subtotal = 0; $days = 0;
foreach ($svc['items'] as $it) {
  if (empty($it['required']) && !in_array($it['id'], $picked, true)) continue;
  $qty = !empty($it['perUnit']) ? $units : 1;
  $amt = (int)round($it['price'] * $qty * $band['multiplier']);
  $lines[] = [$it['label'] . ($qty > 1 ? " × $qty" : ''), $amt];
  $subtotal += $amt;
  $days += $it['days'] * $qty;
}
if ($express) {
  $sur = (int)round($subtotal * ($cfg['express']['multiplier'] - 1));
  $lines[] = [$cfg['express']['label'], $sur];
  $subtotal += $sur;
}
$days  = max(1, (int)ceil(max($days, $svc['baseDays']) * ($express ? $cfg['express']['daysFactor'] : 1)));
$vat   = (int)round($subtotal * $cfg['vatRate']);
$total = $subtotal + $vat;

$code = $sid === 'baseline' ? 'BL' : ($sid === 'eot' ? 'EOT' : 'QC');
$ref = "RO-$code-" . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

$order = [
  'ref' => $ref, 'created' => date('c'), 'status' => 'awaiting_payment',
  'service' => $svc['title'], 'band' => $band['label'], 'units' => $units, 'express' => $express,
  'lines' => $lines, 'subtotal' => $subtotal, 'vat' => $vat, 'vat_pct' => $cfg['vatRate'] * 100,
  'total' => $total, 'usd' => usd_amount($total), 'days' => $days, 'valid_days' => $cfg['validityDays'],
  'client' => $client,
];
save_order($order);
log_csv(order_row($order, 'quotation approved'));

$bank = bank_details($ref);
$s = settings();

// Emails
$internal = "New online order — awaiting payment\n\nClient:  {$client['name']}\nCompany: {$client['company']}\nEmail:   {$client['email']}\nPhone:   {$client['phone']}\nNotes:   {$client['notes']}\n\n" . quote_text($order);
send_mail($s['notify_to'], "New order $ref – {$svc['title']} – " . sar($total), $internal, "{$client['name']} <{$client['email']}>");

$msg = "Dear {$client['name']},\n\nThank you for your order with Red Ocean Consult. Your quotation is below.\n\n" . quote_text($order) . "\n";
if (paypal_enabled()) $msg .= "If you did not complete the PayPal payment on our website, reply to this email and we will send you a PayPal invoice.\n";
if ($bank) $msg .= "\nYou can also pay by bank transfer:\n" . implode("\n", array_map(fn($k, $v) => "$k: $v", array_keys($bank), $bank)) . "\n";
$msg .= "\nOnce payment is received we will contact you to collect the project documents.\n\nRed Ocean Consult\n+966 58 290 7870 · {$s['notify_to']}\nwww.redoceanconsult.com\n";
send_mail($client['email'], "Your Red Ocean Consult quotation $ref", $msg, "Red Ocean Consult <{$s['notify_to']}>");

echo json_encode([
  'ok' => true, 'ref' => $ref, 'total' => $total,
  'paypal' => paypal_enabled() ? ['clientId' => $s['paypal_client_id'], 'usd' => $order['usd'], 'rate' => $s['sar_per_usd']] : null,
  'bank' => $bank,
]);
