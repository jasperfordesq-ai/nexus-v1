<?php
require '/var/www/html/vendor/autoload.php';
require '/var/www/html/src/helpers.php';

// Load env
$envFile = '/var/www/html/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($k, $v) = explode('=', $line, 2);
            putenv(trim($k) . '=' . trim($v));
            $_ENV[trim($k)] = trim($v);
        }
    }
}

use Nexus\Core\Database;
Database::init();

$stmt = Database::query("SHOW TABLES LIKE 'broker_review_archives'");
echo "Table broker_review_archives => " . (count($stmt->fetchAll()) > 0 ? 'OK' : 'MISSING') . "\n";

$stmt = Database::query("SHOW COLUMNS FROM broker_message_copies LIKE 'archived_at'");
echo "Column archived_at => " . (count($stmt->fetchAll()) > 0 ? 'OK' : 'MISSING') . "\n";

$stmt = Database::query("SHOW COLUMNS FROM broker_message_copies LIKE 'archive_id'");
echo "Column archive_id => " . (count($stmt->fetchAll()) > 0 ? 'OK' : 'MISSING') . "\n";

$stmt = Database::query("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_NAME = 'broker_review_archives'");
$row = $stmt->fetch();
echo "Archive table columns => " . $row['cnt'] . "\n";
