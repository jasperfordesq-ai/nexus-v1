<?php
putenv('APP_ENV=testing');
putenv('DB_DATABASE=nexus_test');

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "DB name: " . config('database.connections.mysql.database') . PHP_EOL;

// Check what tables exist
$tables = Illuminate\Support\Facades\DB::select('SHOW TABLES');
echo "Tables in DB: " . count($tables) . PHP_EOL;
foreach ($tables as $t) {
    $name = array_values((array)$t)[0];
    echo "  - $name\n";
}
