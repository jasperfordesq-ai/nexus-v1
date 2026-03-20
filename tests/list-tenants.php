<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
require '/var/www/html/vendor/autoload.php';
$db = \App\Core\Database::getInstance();
$r = $db->prepare('SELECT id, name, slug, domain, is_active FROM tenants ORDER BY id');
$r->execute();
echo "ID | Name | Slug | Domain | Active\n";
echo str_repeat('-', 60) . "\n";
foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $t) {
    echo "{$t['id']} | {$t['name']} | {$t['slug']} | {$t['domain']} | {$t['is_active']}\n";
}
