<?php
// Shared helpers for order.php and paypal.php. Outputs nothing when loaded directly.
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'common.php') { http_response_code(404); exit; }

// Private settings live ONE LEVEL ABOVE public_html (never in the website folder or on GitHub):
//   /home/<cpanel-user>/redocean-config.php   (template: config.sample.php)
function settings() {
  static $s = null;
  if ($s !== null) return $s;
  $defaults = [
    'notify_to'        => 'saadsr@redoceanconsult.com',
    'mail_from'        => 'website@redoceanconsult.com',
    'paypal_mode'      => 'sandbox',
    'paypal_client_id' => '',
    'paypal_secret'    => '',
    'sar_per_usd'      => 3.75,
    'bank'             => [],
  ];
  $file = dirname(__DIR__) . '/redocean-config.php';
  $s = is_file($file) ? array_merge($defaults, (array)require $file) : $defaults;
  return $s;
}

function paypal_enabled() { $s = settings(); return $s['paypal_client_id'] !== '' && $s['paypal_secret'] !== ''; }
function paypal_api() {
  $s = settings();
  if (!empty($s['paypal_api'])) return rtrim($s['paypal_api'], '/'); // testing override
  return $s['paypal_mode'] === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
}
function bank_details($ref) {
  $b = settings()['bank'];
  if (empty($b['IBAN'])) return [];
  $b['Reference'] = $ref;
  return $b;
}
function usd_amount($sar) { return number_format($sar / settings()['sar_per_usd'], 2, '.', ''); }

function fail($code, $msg) { http_response_code($code); echo json_encode(['ok' => false, 'error' => $msg]); exit; }
function clean($v) { return trim(str_replace(["\r", "\n"], ' ', strip_tags((string)($v ?? '')))); }
function sar($n) { return 'SAR ' . number_format($n); }

function orders_dir() {
  $dir = __DIR__ . '/orders';
  if (!is_dir($dir)) { mkdir($dir, 0750, true); }
  if (!is_file("$dir/.htaccess")) file_put_contents("$dir/.htaccess", "Require all denied\nDeny from all\n");
  return $dir;
}
function valid_ref($ref) { return is_string($ref) && preg_match('/^RO-[A-Z]+-\d{6}-[A-F0-9]{8}$/', $ref); }
function load_order($ref) {
  if (!valid_ref($ref)) return null;
  $f = orders_dir() . "/$ref.json";
  return is_file($f) ? json_decode(file_get_contents($f), true) : null;
}
function save_order($o) { file_put_contents(orders_dir() . "/{$o['ref']}.json", json_encode($o, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX); }
function log_csv($row) {
  $f = orders_dir() . '/orders.csv';
  $new = !is_file($f) || filesize($f) === 0;
  $fh = fopen($f, 'a');
  if (!$fh) return;
  if ($new) fputcsv($fh, ['date','ref','event','service','client','company','email','phone','project','value band','items','subtotal SAR','vat SAR','total SAR','total USD','working days','payment id']);
  fputcsv($fh, $row);
  fclose($fh);
}
function order_row($o, $event, $paymentId = '') {
  return [date('Y-m-d H:i'), $o['ref'], $event, $o['service'], $o['client']['name'], $o['client']['company'], $o['client']['email'],
          $o['client']['phone'], $o['client']['project'], $o['band'], implode(' | ', array_column($o['lines'], 0)),
          $o['subtotal'], $o['vat'], $o['total'], $o['usd'], $o['days'], $paymentId];
}

function send_mail($to, $subject, $body, $replyTo) {
  $from = settings()['mail_from'];
  $hdr = "From: Red Ocean Consult <$from>\r\nReply-To: $replyTo\r\nContent-Type: text/plain; charset=UTF-8\r\n";
  return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $hdr, "-f$from");
}
function quote_text($o) {
  $t = "Quotation {$o['ref']}\n{$o['service']} — {$o['client']['project']}\nProject value: {$o['band']}\n\n";
  foreach ($o['lines'] as $l) $t .= str_pad($l[0], 52) . sar($l[1]) . "\n";
  $t .= str_repeat('-', 64) . "\n" . str_pad('Subtotal', 52) . sar($o['subtotal']) . "\n" . str_pad('VAT ' . $o['vat_pct'] . '%', 52) . sar($o['vat']) . "\n" . str_pad('TOTAL', 52) . sar($o['total']) . "\n";
  $t .= "(PayPal charges the equivalent USD {$o['usd']} at the fixed rate of " . settings()['sar_per_usd'] . " SAR per USD.)\n";
  $t .= "\nEstimated delivery: {$o['days']} working days from receipt of documents.\nQuotation valid for {$o['valid_days']} days.\n";
  return $t;
}
