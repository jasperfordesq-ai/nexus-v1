<?php
require '/var/www/html/vendor/autoload.php';
$db = \Nexus\Core\Database::getInstance();
$r = $db->prepare('SELECT id, name, slug, domain, is_active FROM tenants ORDER BY id');
$r->execute();
echo "ID | Name | Slug | Domain | Active\n";
echo str_repeat('-', 60) . "\n";
foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $t) {
    echo "{$t['id']} | {$t['name']} | {$t['slug']} | {$t['domain']} | {$t['is_active']}\n";
}
