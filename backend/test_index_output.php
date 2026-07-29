<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing GET /api/tourist-spots Output ===" . PHP_EOL;

$controller = new App\Http\Controllers\TouristSpotController();
$request = Illuminate\Http\Request::create('/api/tourist-spots', 'GET');
$request->setLaravelSession($app['session']->driver());
$request->session()->put('user_role', 'lupto');
$request->session()->put('user_municipality_id', 0);

$response = $controller->index($request);
$data = json_decode($response->getContent(), true);

echo "Returned Spot Count: " . count($data) . PHP_EOL;
foreach ($data as $s) {
    echo "ID: " . ($s['id'] ?? 'N/A') . " | Name: " . ($s['name'] ?? 'N/A') . " | Status: " . ($s['status'] ?? 'N/A') . PHP_EOL;
}
