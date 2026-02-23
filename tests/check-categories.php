<?php
require '/var/www/html/vendor/autoload.php';
$db = \Nexus\Core\Database::getInstance();
$r = $db->prepare('SELECT tenant_id, type, COUNT(*) as n FROM categories GROUP BY tenant_id, type ORDER BY tenant_id, type');
$r->execute();
echo "tenant_id | type | count\n";
foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "{$row['tenant_id']} | {$row['type']} | {$row['n']}\n";
}
