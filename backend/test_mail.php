<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Mail\TouristWelcomeMail;
use App\Models\User;

$u = new User();
$u->id     = 999;
$u->name   = 'James Vergel Garcia';
$u->email  = '[EMAIL_ADDRESS]';
$u->role   = 'tourist';

try {
    Illuminate\Support\Facades\Mail::to($u->email)->send(new TouristWelcomeMail($u));
    echo "SUCCESS: Welcome email sent to {$u->email}\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
