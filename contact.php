<?php
// Contact form handler for www.redoceanconsult.com (Namecheap shared hosting)
header('Content-Type: application/json');

$to   = 'saadsr@redoceanconsult.com';
$from = 'website@redoceanconsult.com'; // any address on your domain works as sender

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

// Honeypot: real visitors never fill this hidden field
if (!empty($_POST['website'])) {
  echo json_encode(['ok' => true]);
  exit;
}

function clean($v) { return trim(str_replace(["\r", "\n"], ' ', strip_tags($v ?? ''))); }

$name    = clean($_POST['name'] ?? '');
$email   = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$phone   = clean($_POST['phone'] ?? '');
$company = clean($_POST['company'] ?? '');
$service = clean($_POST['service'] ?? '');
$message = trim(strip_tags($_POST['message'] ?? ''));

if ($name === '' || !$email || $message === '') {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Please fill in your name, a valid email and a message.']);
  exit;
}

$subject = "New enquiry from $name" . ($service ? " - $service" : '');
$body  = "New enquiry from the Red Ocean Consult website\n\n";
$body .= "Name:    $name\nEmail:   $email\nPhone:   $phone\nCompany: $company\nService: $service\n\n";
$body .= "Message:\n$message\n\n---\nSent " . date('Y-m-d H:i') . " from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

$headers  = "From: Red Ocean Consult Website <$from>\r\n";
$headers .= "Reply-To: $name <$email>\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, "-f$from");

if ($sent) {
  echo json_encode(['ok' => true]);
} else {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'The message could not be sent. Please email us directly.']);
}
