<?php
/**
 * Migration: Create SEO Redirects Table
 *
 * Creates the seo_redirects table for managing 301 redirects.
 *
 * Run: php scripts/migrations/create_seo_redirects.php
 */

require_once __DIR__ . '/../../httpdocs/bootstrap.php';

use Nexus\Core\Database;

echo "Creating seo_redirects table...\n";

$db = Database::getConnection();

$sql = "
CREATE TABLE IF NOT EXISTS seo_redirects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    source_url VARCHAR(500) NOT NULL,
    destination_url VARCHAR(500) NOT NULL,
    hits INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_redirect (tenant_id, source_url(191)),
    INDEX idx_source (source_url(191)),
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sql);
    echo "SUCCESS: seo_redirects table created.\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Also ensure tenants table has the social/organization columns
echo "\nChecking tenants table for organization schema columns...\n";

$columns = [
    'description' => 'TEXT',
    'contact_email' => 'VARCHAR(255)',
    'contact_phone' => 'VARCHAR(50)',
    'address' => 'VARCHAR(500)',
    'social_facebook' => 'VARCHAR(255)',
    'social_twitter' => 'VARCHAR(255)',
    'social_instagram' => 'VARCHAR(255)',
    'social_linkedin' => 'VARCHAR(255)',
    'social_youtube' => 'VARCHAR(255)'
];

foreach ($columns as $column => $type) {
    try {
        $db->exec("ALTER TABLE tenants ADD COLUMN $column $type DEFAULT NULL");
        echo "  Added: $column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "  Exists: $column\n";
        } else {
            echo "  Error ($column): " . $e->getMessage() . "\n";
        }
    }
}

echo "\nMigration complete!\n";
