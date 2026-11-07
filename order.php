<?php
// Online order handler for www.redoceanconsult.com
// Recalculates the quotation from pricing.json (never trusts prices sent by the browser),
// saves the order, emails Red Ocean Consult and the client, and returns the payment link.
header('Content-Type: application/json');

// ── Settings ──────────────────────────────────────────────────────────────
$NOTIFY_TO  = 'saadsr@redoceanconsult.com';
$FROM       = 'website@redoceanconsult.com';

// Payment: pick ONE option.
// 1) A payment link that accepts the amount in the URL, e.g. PayPal.me:
//      'https://www.paypal.me/YOURNAME/{amount}SAR'
//    Placeholders: {amount} {ref} {email} {name}
// 2) A fixed payment page link (client enters the amount shown on the quotation).
// 3) Leave empty '' to show bank transfer details instead (below).
$PAYMENT_URL = '';
$BANK = [
  'Bank'            => '[YOUR BANK NAME]',
  'Account name'    => 'Red Ocean Consult',
  'IBAN'            => '[SA00 0000 0000 0000 0000 0000]',
  'Reference'       => '{ref}',
];
// ──────────────────────────────────────────────────────────────────────────

function fail($code, $msg) { http_response_code($code); echo json_encode(['ok' => false, 'error' => $msg]); exit; }
function clean($v) { return trim(str_replace(["\r", "\n"], ' ', strip_tags((string)($v ?? '')))); }

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
  $ids = array_column($svc['items'], 'id');
  $picked = array_values(array_intersect($picked, $ids));
  if (count($picked) !== 1) fail(422, 'Please choose one option.');
}

$c = $in['client'] ?? [];
$name    = clean($c['name'] ?? '');
$email   = filter_var(trim($c['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$phone   = clean($c['phone'] ?? '');
$company = clean($c['company'] ?? '');
$project = clean($c['project'] ?? '');
$notes   = trim(strip_tags((string)($c['notes'] ?? '')));
if ($name === '' || !$email || $project === '') fail(422, 'Please add your name, email and project name.');

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

$ref = 'RO-' . strtoupper($sid === 'baseline' ? 'BL' : ($sid === 'eot' ? 'EOT' : 'QC')) . '-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));

// Save order log (folder is protected by its own .htaccess)
$dir = __DIR__ . '/orders';
if (!is_dir($dir)) { mkdir($dir, 0750, true); file_put_contents("$dir/.htaccess", "Require all denied\nDeny from all\n"); }
$fh = fopen("$dir/orders.csv", 'a');
if ($fh) {
  if (filesize("$dir/orders.csv") === 0) fputcsv($fh, ['date','ref','service','client','company','email','phone','project','value band','units','express','items','subtotal','vat','total','working days']);
  fputcsv($fh, [date('Y-m-d H:i'), $ref, $svc['title'], $name, $company, $email, $phone, $project, $band['label'], $units, $express ? 'yes' : 'no', implode(' | ', array_column($lines, 0)), $subtotal, $vat, $total, $days]);
  fclose($fh);
}

// Payment instructions
$fill = function ($s) use ($total, $ref, $email, $name) {
  return str_replace(['{amount}','{ref}','{email}','{name}'], [$total, rawurlencode($ref), rawurlencode($email), rawurlencode($name)], $s);
};
$payUrl = $PAYMENT_URL ? $fill($PAYMENT_URL) : '';
$bank = [];
if (!$payUrl) foreach ($BANK as $k => $v) $bank[$k] = str_replace('{ref}', $ref, $v);

// Emails
$fmt = function ($n) { return 'SAR ' . number_format($n); };
$quote = "Quotation $ref\n" . $svc['title'] . " — $project\nProject value: {$band['label']}\n\n";
foreach ($lines as $l) $quote .= str_pad($l[0], 52) . $fmt($l[1]) . "\n";
$quote .= str_repeat('-', 64) . "\n" . str_pad('Subtotal', 52) . $fmt($subtotal) . "\n" . str_pad('VAT ' . ($cfg['vatRate'] * 100) . '%', 52) . $fmt($vat) . "\n" . str_pad('TOTAL', 52) . $fmt($total) . "\n\nEstimated delivery: $days working days from receipt of documents.\nQuotation valid for {$cfg['validityDays']} days.\n";

$hdr = "From: Red Ocean Consult <$FROM>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
$sub = function ($s) { return '=?UTF-8?B?' . base64_encode($s) . '?='; };

$internal = "New online order — awaiting payment\n\nClient:  $name\nCompany: $company\nEmail:   $email\nPhone:   $phone\nNotes:   $notes\n\n$quote";
@mail($NOTIFY_TO, $sub("New order $ref – {$svc['title']} – " . $fmt($total)), $internal, $hdr . "Reply-To: $name <$email>\r\n", "-f$FROM");

$client = "Dear $name,\n\nThank you for your order with Red Ocean Consult. Your quotation is below.\n\n$quote\n";
$client .= $payUrl ? "Pay securely here: $payUrl\n" : "Please pay by bank transfer:\n" . implode("\n", array_map(fn($k, $v) => "$k: $v", array_keys($bank), $bank)) . "\n";
$client .= "\nOnce payment is received we will contact you to collect the project documents.\n\nRed Ocean Consult\n+966 58 290 7870 · $NOTIFY_TO\nwww.redoceanconsult.com\n";
@mail($email, $sub("Your Red Ocean Consult quotation $ref"), $client, $hdr . "Reply-To: Red Ocean Consult <$NOTIFY_TO>\r\n", "-f$FROM");

echo json_encode(['ok' => true, 'ref' => $ref, 'total' => $total, 'paymentUrl' => $payUrl, 'bank' => $bank]);
