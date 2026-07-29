<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Database Tourist Spots ===" . PHP_EOL;

$spots = App\Models\TouristSpot::all();
echo "Total DB Tourist Spots Count: " . $spots->count() . PHP_EOL . PHP_EOL;

foreach ($spots as $s) {
    echo "ID: {$s->id} | Name: {$s->name} | Status: {$s->status} | Classification: {$s->classification_status} | MuniID: {$s->municipality_id}" . PHP_EOL;
}
