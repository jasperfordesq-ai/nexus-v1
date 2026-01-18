#!/usr/bin/env php
<?php
require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;

$stmt = Database::query("SELECT id, title, status, author_id, created_at FROM posts LIMIT 20");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\n→ First 20 blog posts:\n\n";
foreach ($posts as $post) {
    echo "  [{$post['id']}] {$post['title']}\n";
    echo "       Status: {$post['status']} | Author: {$post['author_id']} | Date: {$post['created_at']}\n\n";
}

$countStmt = Database::query("SELECT status, COUNT(*) as count FROM posts GROUP BY status");
$counts = $countStmt->fetchAll(PDO::FETCH_ASSOC);

echo "\n→ Posts by status:\n";
foreach ($counts as $count) {
    echo "  {$count['status']}: {$count['count']} posts\n";
}
echo "\n";
