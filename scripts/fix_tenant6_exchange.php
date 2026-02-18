<?php
/**
 * One-time fix: Enable exchange_workflow in tenant 6's broker_controls config.
 * Run via: docker exec nexus-php-app php /var/www/html/scripts/fix_tenant6_exchange.php
 */

$host = getenv('DB_HOST');
$name = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

$pdo = new PDO("mysql:host=$host;dbname=$name", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Read current configuration
$stmt = $pdo->query('SELECT configuration FROM tenants WHERE id = 6');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$config = json_decode($row['configuration'] ?? '{}', true);
$brokerControls = $config['broker_controls'] ?? [];
$exchangeWorkflow = $brokerControls['exchange_workflow'] ?? [];

echo 'Current broker_controls.exchange_workflow: ' . json_encode($exchangeWorkflow) . PHP_EOL;

// Enable exchange_workflow
$exchangeWorkflow['enabled'] = true;
$brokerControls['exchange_workflow'] = $exchangeWorkflow;
$config['broker_controls'] = $brokerControls;

$stmt = $pdo->prepare('UPDATE tenants SET configuration = ? WHERE id = 6');
$stmt->execute([json_encode($config)]);

// Verify
$stmt = $pdo->query('SELECT configuration FROM tenants WHERE id = 6');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$config2 = json_decode($row['configuration'], true);
echo 'Updated broker_controls.exchange_workflow: ' . json_encode($config2['broker_controls']['exchange_workflow']) . PHP_EOL;
echo 'Done.' . PHP_EOL;
