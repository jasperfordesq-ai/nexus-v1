#!/usr/bin/env php
<?php
/**
 * Nexus Database Seeder
 *
 * Generates realistic test data for development and demonstration environments.
 *
 * Usage:
 *   php scripts/seed_database.php [options]
 *
 * Options:
 *   --env=<env>        Environment (dev, demo, test) [default: dev]
 *   --users=<n>        Number of users to create [default: 50]
 *   --groups=<n>       Number of groups to create [default: 10]
 *   --posts=<n>        Number of posts to create [default: 100]
 *   --events=<n>       Number of events to create [default: 20]
 *   --listings=<n>     Number of listings to create [default: 30]
 *   --transactions=<n> Number of transactions to create [default: 50]
 *   --clear            Clear existing data before seeding
 *   --tenant=<id>      Tenant ID to seed for [default: 1]
 *   --help             Show this help message
 *
 * Examples:
 *   php scripts/seed_database.php --env=demo --users=100
 *   php scripts/seed_database.php --clear --users=20 --groups=5
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;

// Parse command line arguments
$options = getopt('', [
    'env:',
    'users:',
    'groups:',
    'posts:',
    'events:',
    'listings:',
    'transactions:',
    'clear',
    'tenant:',
    'help'
]);

if (isset($options['help'])) {
    echo file_get_contents(__FILE__);
    exit(0);
}

// Configuration
$config = [
    'env' => $options['env'] ?? 'dev',
    'users' => (int)($options['users'] ?? 50),
    'groups' => (int)($options['groups'] ?? 10),
    'posts' => (int)($options['posts'] ?? 100),
    'events' => (int)($options['events'] ?? 20),
    'listings' => (int)($options['listings'] ?? 30),
    'transactions' => (int)($options['transactions'] ?? 50),
    'clear' => isset($options['clear']),
    'tenant_id' => (int)($options['tenant'] ?? 1),
];

// Color output helpers
function color($text, $color = 'green') {
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

function success($msg) {
    echo color("✓ ", "green") . $msg . "\n";
}

function error($msg) {
    echo color("✗ ", "red") . $msg . "\n";
}

function info($msg) {
    echo color("→ ", "blue") . $msg . "\n";
}

function warn($msg) {
    echo color("⚠ ", "yellow") . $msg . "\n";
}

// Banner
echo "\n";
echo color("╔════════════════════════════════════════════════════════════╗\n", "blue");
echo color("║         NEXUS DATABASE SEEDER v1.0                         ║\n", "blue");
echo color("╚════════════════════════════════════════════════════════════╝\n", "blue");
echo "\n";

// Display configuration
info("Configuration:");
echo "  Environment: " . color($config['env'], 'yellow') . "\n";
echo "  Tenant ID: " . color($config['tenant_id'], 'yellow') . "\n";
echo "  Users: " . color($config['users'], 'yellow') . "\n";
echo "  Groups: " . color($config['groups'], 'yellow') . "\n";
echo "  Posts: " . color($config['posts'], 'yellow') . "\n";
echo "  Events: " . color($config['events'], 'yellow') . "\n";
echo "  Listings: " . color($config['listings'], 'yellow') . "\n";
echo "  Transactions: " . color($config['transactions'], 'yellow') . "\n";
if ($config['clear']) {
    warn("  Clear existing data: YES");
}
echo "\n";

// Safety check
if ($config['env'] === 'production') {
    error("ERROR: Cannot seed production database!");
    echo "Use --env=dev or --env=demo instead.\n";
    exit(1);
}

// Confirm
echo "This will add test data to your database.\n";
echo "Continue? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim($line) !== 'y') {
    info("Aborted.");
    exit(0);
}
echo "\n";

// Initialize database
try {
    $pdo = Database::getInstance();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    error("Database connection failed: " . $e->getMessage());
    exit(1);
}

// Start seeding
$startTime = microtime(true);
info("Starting database seeding...\n");

// Include seeder classes
require_once __DIR__ . '/seeders/UserSeeder.php';
require_once __DIR__ . '/seeders/GroupSeeder.php';
require_once __DIR__ . '/seeders/PostSeeder.php';
require_once __DIR__ . '/seeders/EventSeeder.php';
require_once __DIR__ . '/seeders/ListingSeeder.php';
require_once __DIR__ . '/seeders/TransactionSeeder.php';
require_once __DIR__ . '/seeders/BadgeSeeder.php';
require_once __DIR__ . '/seeders/NotificationSeeder.php';

try {
    // Clear data if requested
    if ($config['clear']) {
        info("Clearing existing data...");

        $tables = [
            'feed_posts',
            'posts',
            'messages',
            'notifications',
            'post_likes',
            'user_badges',
            'transactions',
            'listings',
            'events',
            'event_rsvp',
            'group_members',
            'group_posts',
            'reviews',
        ];

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            try {
                $pdo->exec("DELETE FROM {$table} WHERE tenant_id = {$config['tenant_id']}");
                success("  Cleared {$table}");
            } catch (Exception $e) {
                warn("  Could not clear {$table}: " . $e->getMessage());
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        echo "\n";
    }

    // Seed users
    info("Seeding users...");
    $userSeeder = new UserSeeder($pdo, $config['tenant_id']);
    $userIds = $userSeeder->seed($config['users']);
    success("Created {$config['users']} users");
    echo "\n";

    // Seed groups
    info("Seeding groups...");
    $groupSeeder = new GroupSeeder($pdo, $config['tenant_id'], $userIds);
    $groupIds = $groupSeeder->seed($config['groups']);
    success("Created {$config['groups']} groups");
    echo "\n";

    // Seed posts
    info("Seeding posts...");
    $postSeeder = new PostSeeder($pdo, $config['tenant_id'], $userIds, $groupIds);
    $postIds = $postSeeder->seed($config['posts']);
    success("Created {$config['posts']} posts");
    echo "\n";

    // Seed events
    info("Seeding events...");
    $eventSeeder = new EventSeeder($pdo, $config['tenant_id'], $userIds, $groupIds);
    $eventIds = $eventSeeder->seed($config['events']);
    success("Created {$config['events']} events");
    echo "\n";

    // Seed listings
    info("Seeding listings...");
    $listingSeeder = new ListingSeeder($pdo, $config['tenant_id'], $userIds);
    $listingIds = $listingSeeder->seed($config['listings']);
    success("Created {$config['listings']} listings");
    echo "\n";

    // Seed transactions
    info("Seeding transactions...");
    $transactionSeeder = new TransactionSeeder($pdo, $config['tenant_id'], $userIds);
    $transactionIds = $transactionSeeder->seed($config['transactions']);
    success("Created {$config['transactions']} transactions");
    echo "\n";

    // Seed badges
    info("Seeding badges...");
    $badgeSeeder = new BadgeSeeder($pdo, $config['tenant_id'], $userIds);
    $badgeSeeder->seed();
    success("Awarded badges to users");
    echo "\n";

    // Seed notifications
    info("Seeding notifications...");
    $notificationSeeder = new NotificationSeeder($pdo, $config['tenant_id'], $userIds);
    $notificationSeeder->seed(50);
    success("Created notifications");
    echo "\n";

    // Calculate time taken
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    // Success summary
    echo "\n";
    echo color("╔════════════════════════════════════════════════════════════╗\n", "green");
    echo color("║                  SEEDING COMPLETED!                        ║\n", "green");
    echo color("╚════════════════════════════════════════════════════════════╝\n", "green");
    echo "\n";
    success("Database seeded successfully in {$duration} seconds");
    echo "\n";
    info("Summary:");
    echo "  Users: " . color(count($userIds), 'green') . "\n";
    echo "  Groups: " . color(count($groupIds), 'green') . "\n";
    echo "  Posts: " . color(count($postIds), 'green') . "\n";
    echo "  Events: " . color(count($eventIds), 'green') . "\n";
    echo "  Listings: " . color(count($listingIds), 'green') . "\n";
    echo "  Transactions: " . color(count($transactionIds), 'green') . "\n";
    echo "\n";
    info("Test Users Created:");
    echo "  Email: admin@nexus.test | Password: password\n";
    echo "  Email: user1@nexus.test | Password: password\n";
    echo "  Email: user2@nexus.test | Password: password\n";
    echo "\n";

} catch (Exception $e) {
    error("\nSeeding failed: " . $e->getMessage());
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}
