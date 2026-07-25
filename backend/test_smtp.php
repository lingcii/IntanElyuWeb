<?php
// Quick SMTP connectivity test for INTAN ELYU system
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Mail;

try {
    Mail::raw(
        'INTAN ELYU Tourism Management System - SMTP test email. If you received this, Gmail SMTP is working correctly!',
        function ($message) {
            $message->to('lotlance319@gmail.com')
                    ->subject('✅ SMTP Test - INTAN ELYU System');
        }
    );
    echo "✅ Mail sent successfully! Check your Gmail inbox.\n";
} catch (\Exception $e) {
    echo "❌ Mail failed: " . $e->getMessage() . "\n";
}
