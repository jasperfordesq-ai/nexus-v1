<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class Attribute
{
    public static function create($name, $categoryId = null, $inputType = 'checkbox')
    {
        $tenantId = TenantContext::getId();
        $sql = "INSERT INTO attributes (tenant_id, name, category_id, input_type) VALUES (?, ?, ?, ?)";
        Database::query($sql, [$tenantId, $name, $categoryId ?: null, $inputType]);
        return Database::lastInsertId();
    }

    public static function update($id, $data)
    {
        $tenantId = TenantContext::getId();
        $sql = "UPDATE attributes SET name = ?, category_id = ?, input_type = ?, is_active = ? WHERE id = ? AND tenant_id = ?";
        Database::query($sql, [
            $data['name'],
            $data['category_id'] ?: null,
            $data['input_type'],
            $data['is_active'],
            $id,
            $tenantId
        ]);
    }

    public static function delete($id)
    {
        $tenantId = TenantContext::getId();
        Database::query("DELETE FROM attributes WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
    }

    public static function find($id)
    {
        $tenantId = TenantContext::getId();
        return Database::query("SELECT * FROM attributes WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();
    }

    public static function all()
    {
        $tenantId = TenantContext::getId();
        // Join category name for display
        $sql = "SELECT a.*, c.name as category_name 
                FROM attributes a 
                LEFT JOIN categories c ON a.category_id = c.id 
                WHERE a.tenant_id = ? 
                ORDER BY a.category_id ASC, a.name ASC";
        return Database::query($sql, [$tenantId])->fetchAll();
    }

    /**
     * Get attributes available for a specific category context.
     * Returns Global Attributes (category_id IS NULL) + Category Specific Attributes
     */
    public static function getForCategory($categoryId)
    {
        $tenantId = TenantContext::getId();
        if ($categoryId) {
            $sql = "SELECT * FROM attributes WHERE tenant_id = ? AND is_active = 1 AND (category_id IS NULL OR category_id = ?) ORDER BY category_id ASC, name ASC";
            return Database::query($sql, [$tenantId, $categoryId])->fetchAll();
        } else {
            // If no category selected, maybe just return globals? 
            // Or typically in a "Create Listing" flow, we might change attributes dynamically. 
            // For now, return globals.
            $sql = "SELECT * FROM attributes WHERE tenant_id = ? AND is_active = 1 AND category_id IS NULL ORDER BY name ASC";
            return Database::query($sql, [$tenantId])->fetchAll();
        }
    }

    /**
     * Get attributes by target type (e.g. 'offer' or 'request')
     * Returns attributes that match the type OR are 'any'
     */
    public static function getByType($type)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT * FROM attributes WHERE tenant_id = ? AND is_active = 1 AND (target_type = 'any' OR target_type = ?) ORDER BY name ASC";
        return Database::query($sql, [$tenantId, $type])->fetchAll();
    }
    /**
     * Get attributes for a specific listing
     */
    public static function getForListing($listingId)
    {
        $sql = "SELECT a.id, a.name, a.input_type, la.value 
                FROM listing_attributes la 
                JOIN attributes a ON la.attribute_id = a.id 
                WHERE la.listing_id = ?";
        return Database::query($sql, [$listingId])->fetchAll();
    }

    /**
     * Seeds default attributes for a new tenant.
     * Hardwired standards that can be edited/deleted later by the admin.
     */
    public static function seedDefaults($tenantId)
    {
        $defaults = [
            ['name' => 'Garda Vetted',         'type' => 'offer',   'input' => 'checkbox'],
            ['name' => 'Tools Provided',       'type' => 'offer',   'input' => 'checkbox'],
            ['name' => 'Materials Provided',   'type' => 'offer',   'input' => 'checkbox'],
            ['name' => 'References Available', 'type' => 'offer',   'input' => 'checkbox'],

            ['name' => 'Tools Required',       'type' => 'request', 'input' => 'checkbox'],
            ['name' => 'Materials Required',   'type' => 'request', 'input' => 'checkbox'],

            ['name' => 'Wheelchair Accessible', 'type' => 'any',    'input' => 'checkbox'],
            ['name' => 'Pet Friendly',          'type' => 'any',    'input' => 'checkbox'],
            ['name' => 'Online Only',           'type' => 'any',    'input' => 'checkbox'],
        ];

        $sql = "INSERT INTO attributes (tenant_id, name, target_type, input_type) VALUES (?, ?, ?, ?)";

        foreach ($defaults as $attr) {
            Database::query($sql, [
                $tenantId,
                $attr['name'],
                $attr['type'],
                $attr['input']
            ]);
        }
    }
}
