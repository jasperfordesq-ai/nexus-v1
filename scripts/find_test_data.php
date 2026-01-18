<?php
if (php_sapi_name() !== 'cli') die;
require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;

$tenants = Database::query("SELECT id, name FROM tenants LIMIT 5")->fetchAll();
foreach ($tenants as $t) {
    echo "Tenant: {$t['id']} - {$t['name']}\n";
    $users = Database::query("SELECT COUNT(*) as c FROM users WHERE tenant_id = ?", [$t['id']])->fetch()['c'];
    $groups = Database::query("SELECT COUNT(*) as c FROM groups WHERE tenant_id = ?", [$t['id']])->fetch()['c'];
    echo "  Users: $users\n";
    echo "  Groups: $groups\n";
}
