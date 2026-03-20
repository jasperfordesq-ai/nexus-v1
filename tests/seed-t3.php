<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
// Seed missing category types for T3 (Public Sector Demo)
// Only inserts types that have zero categories for this tenant.
require '/var/www/html/vendor/autoload.php';
$db = \App\Core\Database::getInstance();
$tenantId = 3;

// Check existing types
$existing = $db->prepare("SELECT type, COUNT(*) as n FROM categories WHERE tenant_id = ? GROUP BY type");
$existing->execute([$tenantId]);
$counts = [];
foreach ($existing->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $counts[$row['type']] = (int) $row['n'];
}

echo "T3 current categories: " . json_encode($counts) . "\n";

$types = [
    'listing' => [
        ['name' => 'Arts & Crafts',          'slug' => 'arts-crafts',          'color' => 'pink'],
        ['name' => 'Business & Admin',       'slug' => 'business-admin',       'color' => 'gray'],
        ['name' => 'Care & Companionship',   'slug' => 'care-companionship',   'color' => 'red'],
        ['name' => 'Computers & Tech',       'slug' => 'computers-tech',       'color' => 'indigo'],
        ['name' => 'DIY & Home',             'slug' => 'diy-home',             'color' => 'orange'],
        ['name' => 'Education & Tuition',    'slug' => 'education-tuition',    'color' => 'yellow'],
        ['name' => 'Events & Entertainment', 'slug' => 'events-entertainment', 'color' => 'purple'],
        ['name' => 'Food & Cooking',         'slug' => 'food-cooking',         'color' => 'green'],
        ['name' => 'Health & Wellbeing',     'slug' => 'health-wellbeing',     'color' => 'teal'],
        ['name' => 'Legal & Financial',      'slug' => 'legal-financial',      'color' => 'blue'],
        ['name' => 'Music & Performance',    'slug' => 'music-performance',    'color' => 'fuchsia'],
        ['name' => 'Sports & Recreation',    'slug' => 'sports-recreation',    'color' => 'cyan'],
        ['name' => 'Transportation',         'slug' => 'transportation',       'color' => 'slate'],
        ['name' => 'Miscellaneous',          'slug' => 'miscellaneous',        'color' => 'gray'],
    ],
    'vol_opportunity' => [
        ['name' => 'Community Service', 'slug' => 'community-service', 'color' => 'blue'],
        ['name' => 'Environmental',     'slug' => 'environmental',     'color' => 'green'],
        ['name' => 'Event Support',     'slug' => 'event-support',     'color' => 'purple'],
        ['name' => 'Fundraising',       'slug' => 'fundraising',       'color' => 'red'],
        ['name' => 'Mentoring',         'slug' => 'mentoring',         'color' => 'yellow'],
        ['name' => 'Office / Admin',    'slug' => 'office-admin',      'color' => 'gray'],
    ],
    'event' => [
        ['name' => 'Social Gathering', 'slug' => 'social-gathering', 'color' => 'pink'],
        ['name' => 'Workshop / Class', 'slug' => 'workshop-class',   'color' => 'indigo'],
        ['name' => 'Outdoor Activity', 'slug' => 'outdoor-activity', 'color' => 'green'],
        ['name' => 'Fundraiser',       'slug' => 'fundraiser',       'color' => 'red'],
        ['name' => 'Market / Fair',    'slug' => 'market-fair',      'color' => 'orange'],
    ],
    'blog' => [
        ['name' => 'Community Stories', 'slug' => 'community-stories', 'color' => 'blue'],
        ['name' => 'Platform Updates',  'slug' => 'platform-updates',  'color' => 'gray'],
        ['name' => 'Member Spotlight',  'slug' => 'member-spotlight',  'color' => 'fuchsia'],
        ['name' => 'Events',            'slug' => 'events',            'color' => 'purple'],
    ],
];

$added = 0;
foreach ($types as $type => $cats) {
    $existing_count = $counts[$type] ?? 0;
    if ($existing_count > 0 && $type === 'listing') {
        echo "listing: already has {$existing_count}, inserting missing ones with INSERT IGNORE...\n";
    }
    foreach ($cats as $cat) {
        // Use INSERT IGNORE to skip existing slugs
        $stmt = $db->prepare(
            "INSERT IGNORE INTO categories (tenant_id, name, slug, color, type) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$tenantId, $cat['name'], $cat['slug'], $cat['color'], $type]);
        if ($stmt->rowCount() > 0) $added++;
    }
}

// Final count
$final = $db->prepare("SELECT type, COUNT(*) as n FROM categories WHERE tenant_id = ? GROUP BY type");
$final->execute([$tenantId]);
echo "\nT3 after seeding:\n";
$total = 0;
foreach ($final->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "  {$row['type']}: {$row['n']}\n";
    $total += $row['n'];
}
echo "Total: {$total} categories, {$added} new\n";
