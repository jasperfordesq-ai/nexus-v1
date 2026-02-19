<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class Category
{
    public static function create($data)
    {
        $tenantId = TenantContext::getId();
        $sql = "INSERT INTO categories (tenant_id, name, slug, color, type) VALUES (?, ?, ?, ?, ?)";
        Database::query($sql, [
            $tenantId,
            $data['name'],
            $data['slug'],
            $data['color'] ?? 'blue',
            $data['type'] ?? 'listing'
        ]);
        return Database::lastInsertId();
    }

    public static function all()
    {
        $tenantId = TenantContext::getId();
        return Database::query("SELECT * FROM categories WHERE tenant_id = ? ORDER BY type, name ASC", [$tenantId])->fetchAll();
    }

    public static function getByType($type)
    {
        $tenantId = TenantContext::getId();
        return Database::query("SELECT * FROM categories WHERE tenant_id = ? AND type = ? ORDER BY name ASC", [$tenantId, $type])->fetchAll();
    }

    public static function find($id)
    {
        $tenantId = TenantContext::getId();
        return Database::query("SELECT * FROM categories WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();
    }

    public static function delete($id)
    {
        $tenantId = TenantContext::getId();
        Database::query("DELETE FROM categories WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
    }

    public static function update($id, $data)
    {
        $tenantId = TenantContext::getId();

        // Data Loss Prevention: Fetch existing category to preserve values not being updated
        $existing = self::find($id);
        if (!$existing) {
            return false;
        }

        // Only update fields that are explicitly provided with non-empty values
        $sql = "UPDATE categories SET name = ?, slug = ?, color = ?, type = ? WHERE id = ? AND tenant_id = ?";
        Database::query($sql, [
            isset($data['name']) && $data['name'] !== '' ? $data['name'] : $existing['name'],
            isset($data['slug']) && $data['slug'] !== '' ? $data['slug'] : $existing['slug'],
            isset($data['color']) && $data['color'] !== '' ? $data['color'] : $existing['color'],
            isset($data['type']) && $data['type'] !== '' ? $data['type'] : $existing['type'],
            $id,
            $tenantId
        ]);
    }

    /**
     * Seeds standard default categories for a new tenant.
     */
    public static function seedDefaults($tenantId)
    {
        // 1. Listings (Offers/Requests)
        $listings = [
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
            ['name' => 'Miscellaneous',          'slug' => 'miscellaneous',        'color' => 'gray']
        ];

        // 2. Volunteering
        $volunteering = [
            ['name' => 'Community Service', 'slug' => 'community-service', 'color' => 'blue'],
            ['name' => 'Environmental',     'slug' => 'environmental',     'color' => 'green'],
            ['name' => 'Event Support',     'slug' => 'event-support',     'color' => 'purple'],
            ['name' => 'Fundraising',       'slug' => 'fundraising',       'color' => 'red'],
            ['name' => 'Mentoring',         'slug' => 'mentoring',         'color' => 'yellow'],
            ['name' => 'Office / Admin',    'slug' => 'office-admin',      'color' => 'gray']
        ];

        // 3. Events
        $events = [
            ['name' => 'Social Gathering',  'slug' => 'social-gathering',  'color' => 'pink'],
            ['name' => 'Workshop / Class',  'slug' => 'workshop-class',    'color' => 'indigo'],
            ['name' => 'Outdoor Activity',  'slug' => 'outdoor-activity',  'color' => 'green'],
            ['name' => 'Fundraiser',        'slug' => 'fundraiser',        'color' => 'red'],
            ['name' => 'Market / Fair',     'slug' => 'market-fair',       'color' => 'orange']
        ];

        // 4. Blog / News
        $blog = [
            ['name' => 'Community Stories', 'slug' => 'community-stories', 'color' => 'blue'],
            ['name' => 'Platform Updates',  'slug' => 'platform-updates',  'color' => 'gray'],
            ['name' => 'Member Spotlight',  'slug' => 'member-spotlight',  'color' => 'fuchsia'],
            ['name' => 'Events',            'slug' => 'events',            'color' => 'purple']
        ];

        $sql = "INSERT INTO categories (tenant_id, name, slug, color, type) VALUES (?, ?, ?, ?, ?)";

        // Helper to insert batch
        $insertBatch = function ($items, $type) use ($tenantId, $sql) {
            foreach ($items as $cat) {
                Database::query($sql, [
                    $tenantId,
                    $cat['name'],
                    $cat['slug'],
                    $cat['color'],
                    $type
                ]);
            }
        };

        $insertBatch($listings, 'listing');
        $insertBatch($volunteering, 'vol_opportunity');
        $insertBatch($events, 'event');
        $insertBatch($blog, 'blog');
    }
}
