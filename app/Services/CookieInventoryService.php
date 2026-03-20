<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\CookieInventoryItem;
use Illuminate\Support\Facades\DB;

/**
 * CookieInventoryService
 *
 * Manages the inventory of cookies used by the platform.
 * Provides information for cookie banners, policy pages, and documentation.
 */
class CookieInventoryService
{
    /**
     * Get all cookies for a specific category.
     *
     * @param string $category Category (essential, functional, analytics, marketing)
     * @param int|null $tenantId Tenant ID (null = global cookies only)
     * @return array
     */
    public function getCookiesByCategory(string $category, ?int $tenantId = null): array
    {
        $query = CookieInventoryItem::where('category', $category)
            ->where('is_active', true)
            ->orderBy('cookie_name');

        if ($tenantId === null) {
            $query->whereNull('tenant_id');
        } else {
            $query->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            });
        }

        return $query->get()->toArray();
    }

    /**
     * Get all cookies grouped by category (for policy page).
     *
     * @param int|null $tenantId Tenant ID (null = global only)
     * @return array Cookies grouped by category
     */
    public function getAllCookies(?int $tenantId = null): array
    {
        $query = CookieInventoryItem::where('is_active', true)
            ->orderBy('category')
            ->orderBy('cookie_name');

        if ($tenantId === null) {
            $query->whereNull('tenant_id');
        } else {
            $query->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            });
        }

        $cookies = $query->get();

        $grouped = [
            'essential' => [],
            'functional' => [],
            'analytics' => [],
            'marketing' => [],
        ];

        foreach ($cookies as $cookie) {
            $grouped[$cookie->category][] = $cookie->toArray();
        }

        return $grouped;
    }

    /**
     * Get cookies formatted for banner display.
     *
     * @param int|null $tenantId Tenant ID
     * @return array Cookies grouped by category
     */
    public function getBannerCookieList(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        return $this->getAllCookies($tenantId);
    }

    /**
     * Add new cookie to inventory (admin only).
     *
     * @param array $data Cookie data
     * @return int Cookie ID
     * @throws \InvalidArgumentException
     */
    public function addCookie(array $data): int
    {
        $requiredFields = ['cookie_name', 'category', 'purpose', 'duration'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $validCategories = ['essential', 'functional', 'analytics', 'marketing'];
        if (!in_array($data['category'], $validCategories)) {
            throw new \InvalidArgumentException("Invalid category: {$data['category']}");
        }

        $cookie = CookieInventoryItem::create([
            'cookie_name' => $data['cookie_name'],
            'category' => $data['category'],
            'purpose' => $data['purpose'],
            'duration' => $data['duration'],
            'third_party' => $data['third_party'] ?? 'First-party',
            'tenant_id' => $data['tenant_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $cookie->id;
    }

    /**
     * Update cookie details.
     *
     * @param int $id Cookie ID
     * @param array $data Fields to update
     * @return bool
     */
    public function updateCookie(int $id, array $data): bool
    {
        $cookie = CookieInventoryItem::find($id);
        if (!$cookie) {
            return false;
        }

        $allowedFields = ['cookie_name', 'category', 'purpose', 'duration', 'third_party', 'is_active'];
        $updateData = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (empty($updateData)) {
            return false;
        }

        $cookie->update($updateData);

        return true;
    }
}
