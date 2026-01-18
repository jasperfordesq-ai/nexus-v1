<?php
require __DIR__ . '/../vendor/autoload.php';

$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && substr(trim($line), 0, 1) !== '#') {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

try {
    $pdo = new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME'),
        getenv('DB_USER'),
        getenv('DB_PASS')
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== SEO Redirects involving 'groups' ===\n";
    $stmt = $pdo->query("SELECT * FROM seo_redirects WHERE source_url LIKE '%groups%' OR destination_url LIKE '%groups%' LIMIT 20");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($results)) {
        echo "No redirects found.\n";
    } else {
        foreach ($results as $row) {
            echo "ID: {$row['id']} | {$row['source_url']} -> {$row['destination_url']} | Tenant: {$row['tenant_id']}\n";
        }
    }

    echo "\n=== Checking tenant features for hour-timebank.ie ===\n";
    $stmt = $pdo->query("SELECT id, name, slug, domain, features FROM tenants WHERE domain LIKE '%hour-timebank%' OR slug = 'hour-timebank' LIMIT 5");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tenants as $tenant) {
        echo "Tenant: {$tenant['name']} (ID: {$tenant['id']})\n";
        echo "Domain: {$tenant['domain']}\n";
        echo "Features: {$tenant['features']}\n\n";
    }

    echo "\n=== Checking specific redirect for /groups/479 ===\n";
    $stmt = $pdo->query("SELECT * FROM seo_redirects WHERE source_url LIKE '%/groups/479%' OR source_url = '/groups/479'");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($results)) {
        echo "No specific redirect for /groups/479\n";
    } else {
        print_r($results);
    }

    echo "\n=== Checking if group 479 exists ===\n";
    $stmt = $pdo->query("SELECT id, name, tenant_id FROM groups WHERE id = 479");
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($group) {
        echo "Group EXISTS: {$group['name']} (Tenant: {$group['tenant_id']})\n";
        echo "The redirect for /groups/479 is WRONG and should be deleted!\n";
    } else {
        echo "Group 479 does NOT exist - redirect is valid.\n";
    }

    echo "\n=== Deleting bad redirect for /groups/479 ===\n";
    if ($group) {
        $stmt = $pdo->prepare("DELETE FROM seo_redirects WHERE id = 1253");
        $stmt->execute();
        echo "Deleted redirect ID 1253\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
