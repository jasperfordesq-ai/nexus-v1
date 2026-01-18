<?php
/**
 * Check Missing Content - Database Query Script
 * Checks if the missing URLs exist in database with different slugs
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;

$db = Database::getInstance();

echo "=== Checking for Missing Content ===\n\n";

// Check Help Articles
echo "1. Help Articles:\n";
echo "   Looking for: 'volunteering-guide'\n";
try {
    $helpQuery = "SELECT id, title, slug FROM help_articles WHERE slug LIKE '%volunteer%' OR title LIKE '%volunteer%'";
    $stmt = $db->query($helpQuery);
    $results = $stmt->fetchAll();
    if (count($results) > 0) {
        foreach ($results as $row) {
            echo "   Found: ID={$row['id']}, Slug={$row['slug']}, Title={$row['title']}\n";
        }
    } else {
        echo "   No matching help articles found.\n";
    }
} catch (\Exception $e) {
    echo "   Table 'help_articles' may not exist or query failed: " . $e->getMessage() . "\n";
}

// Check Blog Posts
echo "\n2. Blog Posts:\n";
echo "   Looking for: 'timebanks-org-a-comprehensive-guide-to-the-community-exchange-platform'\n";
try {
    $blogQuery = "SELECT id, title, slug FROM posts WHERE slug LIKE '%timebank%' OR title LIKE '%timebank%' ORDER BY created_at DESC LIMIT 10";
    $stmt = $db->query($blogQuery);
    $results = $stmt->fetchAll();
    if (count($results) > 0) {
        foreach ($results as $row) {
            echo "   Found: ID={$row['id']}, Slug={$row['slug']}, Title={$row['title']}\n";
        }
    } else {
        echo "   No matching blog posts found.\n";
    }
} catch (\Exception $e) {
    echo "   Table 'posts' may not exist or query failed: " . $e->getMessage() . "\n";
}

// Check Listings
echo "\n3. Listings:\n";
echo "   Looking for: 'chess-play' or similar\n";
try {
    $listingQuery = "SELECT id, title, description FROM listings WHERE title LIKE '%chess%' OR description LIKE '%chess%'";
    $stmt = $db->query($listingQuery);
    $results = $stmt->fetchAll();
    if (count($results) > 0) {
        foreach ($results as $row) {
            echo "   Found: ID={$row['id']}, Title={$row['title']}\n";
            echo "   Correct URL: /listings/{$row['id']}\n";
        }
    } else {
        echo "   No matching listings found.\n";
    }
} catch (\Exception $e) {
    echo "   Table 'listings' may not exist or query failed: " . $e->getMessage() . "\n";
}

// Check Groups
echo "\n4. Groups:\n";
echo "   Looking for: 'gardening' groups\n";
try {
    $groupQuery = "SELECT id, name FROM groups WHERE name LIKE '%garden%'";
    $stmt = $db->query($groupQuery);
    $results = $stmt->fetchAll();
    if (count($results) > 0) {
        foreach ($results as $row) {
            echo "   Found: ID={$row['id']}, Name={$row['name']}\n";
            echo "   Correct URL: /groups/{$row['id']}\n";
        }
    } else {
        echo "   No matching groups found.\n";
    }
} catch (\Exception $e) {
    echo "   Table 'groups' may not exist or query failed: " . $e->getMessage() . "\n";
}

echo "\n=== Check Complete ===\n";
