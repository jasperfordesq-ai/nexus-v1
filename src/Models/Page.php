<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;

class Page
{
    public static function all()
    {
        $tenantId = \Nexus\Core\TenantContext::getId();
        $db = Database::getConnection();

        // Ensure table exists or handle error gracefull if migration not run?
        // Assuming table 'pages' exists from previous migration context
        try {
            $stmt = $db->prepare("SELECT title, slug FROM pages WHERE tenant_id = ? ORDER BY title ASC");
            $stmt->execute([$tenantId]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return []; // Table might not exist yet
        }
    }

    public static function findById($id)
    {
        $tenantId = \Nexus\Core\TenantContext::getId();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM pages WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        return $stmt->fetch();
    }

    /**
     * Find a published page by slug
     * @param string $slug
     * @param int $tenantId
     * @return array|false Page data or false if not found
     */
    public static function findBySlug(string $slug, int $tenantId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ? AND tenant_id = ? AND is_published = 1");
        $stmt->execute([$slug, $tenantId]);
        return $stmt->fetch();
    }

    /**
     * Find ANY page by slug (including unpublished - for admin preview)
     * @param string $slug
     * @param int $tenantId
     * @return array|false
     */
    public static function findBySlugAny(string $slug, int $tenantId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ? AND tenant_id = ?");
        $stmt->execute([$slug, $tenantId]);
        return $stmt->fetch();
    }

    /**
     * Seed placeholder pages for a new tenant (About, Privacy, Terms).
     * Idempotent: skips pages whose slug already exists for the tenant.
     */
    public static function seedDefaults(int $tenantId): void
    {
        $defaults = [
            [
                'title'         => 'About',
                'slug'          => 'about',
                'content'       => '<p>Welcome to our community. This page can be customised from the admin panel.</p>',
                'is_published'  => 1,
                'show_in_menu'  => 1,
                'menu_location' => 'about',
            ],
            [
                'title'         => 'Privacy Policy',
                'slug'          => 'privacy',
                'content'       => '<p>Your privacy is important to us. Please update this page with your community\'s privacy policy.</p>',
                'is_published'  => 1,
                'show_in_menu'  => 1,
                'menu_location' => 'footer',
            ],
            [
                'title'         => 'Terms of Service',
                'slug'          => 'terms',
                'content'       => '<p>Please update this page with your community\'s terms of service.</p>',
                'is_published'  => 1,
                'show_in_menu'  => 1,
                'menu_location' => 'footer',
            ],
        ];

        $db = Database::getConnection();

        foreach ($defaults as $page) {
            // Skip if slug already exists for this tenant
            $exists = $db->prepare("SELECT id FROM pages WHERE tenant_id = ? AND slug = ?");
            $exists->execute([$tenantId, $page['slug']]);
            if ($exists->fetch()) {
                continue;
            }

            $stmt = $db->prepare(
                "INSERT INTO pages (tenant_id, title, slug, content, is_published, show_in_menu, menu_location, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())"
            );
            $stmt->execute([
                $tenantId,
                $page['title'],
                $page['slug'],
                $page['content'],
                $page['is_published'],
                $page['show_in_menu'],
                $page['menu_location'],
            ]);
        }
    }

    /**
     * Update page settings (slug, publish status, menu settings)
     * @param int $id
     * @param int $tenantId
     * @param array $data
     * @return bool
     */
    public static function updateSettings(int $id, int $tenantId, array $data): bool
    {
        $db = Database::getConnection();

        $allowedFields = ['title', 'slug', 'is_published', 'publish_at', 'show_in_menu', 'menu_location'];
        $updates = [];
        $values = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $updates[] = "updated_at = NOW()";
        $values[] = $id;
        $values[] = $tenantId;

        $sql = "UPDATE pages SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute($values);
    }
}
