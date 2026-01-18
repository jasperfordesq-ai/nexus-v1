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

    echo "=== Finding ALL bad group redirects ===\n\n";

    // Get all redirects that start with /groups/ and redirect to homepage
    $stmt = $pdo->query("SELECT * FROM seo_redirects WHERE source_url LIKE '/groups/%' ORDER BY id");
    $redirects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $badRedirects = [];

    foreach ($redirects as $redirect) {
        $source = $redirect['source_url'];

        // Extract group ID or slug from URL
        // Patterns: /groups/123, /groups/123/anything, /groups/slug, /groups/slug/anything

        if (preg_match('/^\/groups\/(\d+)(\/|$)/', $source, $matches)) {
            // Numeric group ID
            $groupId = $matches[1];
            $checkStmt = $pdo->prepare("SELECT id, name FROM groups WHERE id = ?");
            $checkStmt->execute([$groupId]);
            $group = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($group) {
                $badRedirects[] = [
                    'redirect_id' => $redirect['id'],
                    'source_url' => $source,
                    'destination_url' => $redirect['destination_url'],
                    'group_id' => $groupId,
                    'group_name' => $group['name'],
                    'hits' => $redirect['hits']
                ];
            }
        }
    }

    echo "Found " . count($badRedirects) . " redirects for EXISTING groups\n\n";

    if (!empty($badRedirects)) {
        echo "=== BAD REDIRECTS TO DELETE ===\n";
        foreach ($badRedirects as $bad) {
            echo "ID {$bad['redirect_id']}: {$bad['source_url']} -> {$bad['destination_url']}\n";
            echo "   Group: {$bad['group_name']} (ID: {$bad['group_id']}) - {$bad['hits']} hits\n";
        }

        echo "\n=== DELETING BAD REDIRECTS ===\n";
        $deleteIds = array_column($badRedirects, 'redirect_id');
        $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
        $deleteStmt = $pdo->prepare("DELETE FROM seo_redirects WHERE id IN ($placeholders)");
        $deleteStmt->execute($deleteIds);
        echo "Deleted " . count($deleteIds) . " bad redirects.\n";
    } else {
        echo "No bad redirects found.\n";
    }

    echo "\n=== DONE ===\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
