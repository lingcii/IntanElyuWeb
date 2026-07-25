<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    $dbName = DB::getDatabaseName();
    echo "Connected to database: " . $dbName . "\n\n";

    $tables = DB::select('SHOW TABLES');
    $tableKey = 'Tables_in_' . $dbName;
    
    // If table key is dynamic:
    if (empty($tables)) {
        echo "No tables found!\n";
        exit;
    }
    
    $tableList = [];
    foreach ($tables as $t) {
        $arr = (array)$t;
        $tableName = reset($arr);
        $count = DB::table($tableName)->count();
        $tableList[] = [
            'name' => $tableName,
            'rows' => $count
        ];
    }
    
    echo sprintf("%-35s | %-10s\n", "Table Name", "Row Count");
    echo str_repeat("-", 50) . "\n";
    foreach ($tableList as $row) {
        echo sprintf("%-35s | %-10d\n", $row['name'], $row['rows']);
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
