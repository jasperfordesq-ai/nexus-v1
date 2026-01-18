<?php
require_once __DIR__ . '/../bootstrap.php';

$db = Nexus\Core\Database::getInstance();
$stmt = $db->prepare("DELETE FROM error_404_log WHERE url LIKE '/test-404-%'");
$stmt->execute();
echo "Cleaned up test 404 entries\n";
