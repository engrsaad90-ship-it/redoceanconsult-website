<?php
// PRIVATE SETTINGS — template.
// Copy this file to /home/redohmgq/redocean-config.php  (one level ABOVE public_html,
// so it can never be downloaded) and fill in your details. Never commit the real file.
return [
  // PayPal: developer.paypal.com → Apps & Credentials → your app.
  // Use 'sandbox' keys to test, then switch to 'live' with your live keys.
  'paypal_mode'      => 'sandbox',
  'paypal_client_id' => '',
  'paypal_secret'    => '',

  // SAR is fixed at 3.75 per USD. PayPal charges total ÷ this rate in USD.
  'sar_per_usd'      => 3.75,

  // Optional bank transfer alternative (leave IBAN empty to hide it).
  'bank' => [
    'Bank'         => '',
    'Account name' => 'Red Ocean Consult',
    'IBAN'         => '',
  ],

  'notify_to' => 'saadsr@redoceanconsult.com',
  'mail_from' => 'website@redoceanconsult.com',
];
