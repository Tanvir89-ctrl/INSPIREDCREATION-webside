<?php
// Copy this file to config.php and fill in your hosting details.
// config.php is kept out of Git so your passwords never reach GitHub.
return [
    'db' => [
        'driver' => 'mysql',               // 'mysql' on your hosting, 'sqlite' for testing on your own PC
        'host'   => 'localhost',
        'name'   => 'your_database_name',  // cPanel → MySQL Databases
        'user'   => 'your_database_user',
        'pass'   => 'your_database_password',
        'sqlite' => __DIR__ . '/../data/inspired.sqlite',
    ],

    // Where new-order notifications are emailed (needs PHP mail(), available on most cPanel hosting)
    'order_email' => 'inspiredcreationbd@gmail.com',
    'whatsapp'    => '8801321744476',

    // Pricing rules (must match the website)
    'customization_fee' => 350,   // per item for Custom Order Studio orders
    'advance_rate'      => 0.3,   // 30% advance
    'delivery_fee'      => ['dhaka' => 80, 'outside' => 140],
    'free_delivery_min' => 2500,  // cart orders at or above this subtotal ship free

    // Steadfast courier (portal.steadfast.com.bd → API settings). Leave the keys empty to add tracking codes by hand.
    'courier' => [
        'provider'   => 'steadfast',
        'api_key'    => '',
        'secret_key' => '',
        'base_url'   => 'https://portal.packzy.com/api/v1',
    ],

    // Secret for running api/cron.php from a URL (any long random text, 20+ letters and numbers)
    'cron_key' => 'change-this-to-a-long-random-text',

    // Keep false on the live site: errors then go to data/php-errors.log instead of being shown to visitors
    'debug' => false,

    // Important orders: remind this many days before the customer's "needed by" date
    'reminder_days_before' => 2,
    // Parcels waiting longer than this many days are flagged on the dashboard
    'waiting_alert_days' => 5,

    // Fake order check: orders scoring below this % chance of accepting the parcel go to "Fake order"
    'fake_threshold' => 40,
    'fake_high_cod'  => 10000,  // cash-on-delivery above this (৳) with no advance lowers the score

    // Customer uploads
    'max_upload_mb' => 10,
    'allowed_ext'   => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'pdf', 'ai', 'eps', 'psd', 'cdr', 'dxf', 'zip', 'rar', 'txt', 'doc', 'docx'],
];
