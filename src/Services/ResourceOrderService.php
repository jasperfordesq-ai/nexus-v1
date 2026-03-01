<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;

/**
 * ResourceOrderService - Manual ordering for resources
 *
 * Allows admins to reorder resources via drag-and-drop or explicit
 * sort_order values. Default sort falls back to sort_order then created_at.
 *
 * @package Nexus\Services
 */
class ResourceOrderService
{
    /** @var array Collected errors */
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    private static function clearErrors(): void
    {
        self::$errors = [];
    }

    private static function addError(string $code, string $message, ?string $field = null): void
    {
        $error = ['code' => $code, 'message' => $message];
        if ($field !== null) {
            $error['field'] = $field;
        }
        self::$errors[] = $error;
    }

    /**
     * Reorder resources by providing an array of {id, sort_order}
     *
     * @param array $items Array of ['id' => int, 'sort_order' => int]
     * @return bool
     */
    public static function reorder(array $items): bool
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        if (empty($items)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Items array is required', 'items');
            return false;
        }

        try {
            Database::beginTransaction();

            foreach ($items as $i => $item) {
                $id = (int)($item['id'] ?? 0);
                $sortOrder = (int)($item['sort_order'] ?? 0);

                if ($id <= 0) {
                    self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, "Invalid resource ID at index {$i}", "items.{$i}.id");
                    Database::rollback();
                    return false;
                }

                Database::query(
                    "UPDATE resources SET sort_order = ? WHERE id = ? AND tenant_id = ?",
                    [$sortOrder, $id, $tenantId]
                );
            }

            Database::commit();
            return true;
        } catch (\Throwable $e) {
            Database::rollback();
            error_log("Resource reorder failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to reorder resources');
            return false;
        }
    }
}
