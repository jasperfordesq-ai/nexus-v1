<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * HelpService - FAQ management for Help Center
 *
 * Provides public FAQ retrieval with tenant-specific overrides and
 * global default fallback, plus admin CRUD for managing FAQs per tenant.
 *
 * Tenant resolution order:
 * 1. FAQs belonging to the current tenant (tenant_id = current)
 * 2. Global defaults (tenant_id = 0) if no tenant FAQs exist
 */
class HelpService
{
    /**
     * Get published FAQs for the current tenant, grouped by category.
     * Falls back to global defaults (tenant_id = 0) if tenant has no FAQs.
     *
     * @return array Array of { category: string, faqs: { id, question, answer }[] }
     */
    public static function getFaqs(): array
    {
        $tenantId = TenantContext::getId();

        // Attempt to load tenant-specific FAQs first
        $faqs = Database::query(
            "SELECT id, category, question, answer, sort_order
             FROM help_faqs
             WHERE tenant_id = ? AND is_published = 1
             ORDER BY category ASC, sort_order ASC, id ASC",
            [$tenantId]
        )->fetchAll();

        // Fall back to global defaults if tenant has no FAQs configured
        if (empty($faqs)) {
            $faqs = Database::query(
                "SELECT id, category, question, answer, sort_order
                 FROM help_faqs
                 WHERE tenant_id = 0 AND is_published = 1
                 ORDER BY category ASC, sort_order ASC, id ASC",
                []
            )->fetchAll();
        }

        // Group rows by category, preserving category order
        $grouped = [];
        foreach ($faqs as $faq) {
            $cat = $faq['category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [
                    'category' => $cat,
                    'faqs'     => [],
                ];
            }
            $grouped[$cat]['faqs'][] = [
                'id'       => (int) $faq['id'],
                'question' => $faq['question'],
                'answer'   => $faq['answer'],
            ];
        }

        return array_values($grouped);
    }

    /**
     * Admin: Get all FAQs for the current tenant (published and unpublished).
     *
     * @return array Raw FAQ rows
     */
    public static function adminGetFaqs(): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT id, tenant_id, category, question, answer, sort_order, is_published, created_at, updated_at
             FROM help_faqs
             WHERE tenant_id = ?
             ORDER BY category ASC, sort_order ASC, id ASC",
            [$tenantId]
        )->fetchAll();
    }

    /**
     * Admin: Create a new FAQ for the current tenant.
     *
     * @param array $data Required: question, answer. Optional: category, sort_order, is_published.
     * @return int The new FAQ id
     */
    public static function createFaq(array $data): int
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "INSERT INTO help_faqs (tenant_id, category, question, answer, sort_order, is_published)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $tenantId,
                trim($data['category'] ?? 'General'),
                trim($data['question']),
                trim($data['answer']),
                (int) ($data['sort_order'] ?? 0),
                isset($data['is_published']) ? (int) (bool) $data['is_published'] : 1,
            ]
        );

        return Database::lastInsertId();
    }

    /**
     * Admin: Update an existing FAQ belonging to the current tenant.
     *
     * @param int   $id   FAQ id
     * @param array $data Fields to update (any subset of: category, question, answer, sort_order, is_published)
     * @return bool False if no fields were provided
     */
    public static function updateFaq(int $id, array $data): bool
    {
        $tenantId = TenantContext::getId();

        $fields = [];
        $params = [];
        $allowed = ['category', 'question', 'answer', 'sort_order', 'is_published'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $params[] = $tenantId;

        Database::query(
            "UPDATE help_faqs SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?",
            $params
        );

        return true;
    }

    /**
     * Admin: Delete an FAQ belonging to the current tenant.
     *
     * @param int $id FAQ id
     */
    public static function deleteFaq(int $id): void
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "DELETE FROM help_faqs WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
    }
}
