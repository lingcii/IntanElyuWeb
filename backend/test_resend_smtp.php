<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Mail;

echo "Testing Brevo SMTP...\n";
echo "MAIL_HOST: " . env('MAIL_HOST') . "\n";
echo "MAIL_PORT: " . env('MAIL_PORT') . "\n";
echo "MAIL_USERNAME: " . env('MAIL_USERNAME') . "\n";
echo "MAIL_FROM_ADDRESS: " . env('MAIL_FROM_ADDRESS') . "\n";

try {
    Mail::raw('Test email from Resend SMTP - ' . date('Y-m-d H:i:s'), function ($m) {
        $m->to('side24250@gmail.com')
          ->subject('Resend SMTP Test - ' . date('H:i:s'));
    });
    echo "SUCCESS: Email sent via Resend SMTP!\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
