<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\ApiErrorCodes;
use Nexus\Services\HelpService;

/**
 * HelpApiController - V2 API for Help Center FAQs
 *
 * Public endpoint returns grouped FAQs for the current tenant with fallback
 * to global defaults. Admin endpoints provide full CRUD.
 *
 * Endpoints:
 * - GET    /api/v2/help/faqs              - Public: grouped FAQs (no auth)
 * - GET    /api/v2/admin/help/faqs        - Admin: all FAQs for tenant
 * - POST   /api/v2/admin/help/faqs        - Admin: create FAQ
 * - PUT    /api/v2/admin/help/faqs/{id}   - Admin: update FAQ
 * - DELETE /api/v2/admin/help/faqs/{id}   - Admin: delete FAQ
 */
class HelpApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    // ============================================
    // PUBLIC ENDPOINTS
    // ============================================

    /**
     * GET /api/v2/help/faqs
     *
     * Returns published FAQs grouped by category for the current tenant.
     * Falls back to global defaults (tenant_id = 0) when the tenant has
     * no custom FAQs configured.
     *
     * No authentication required.
     */
    public function getFaqs(): void
    {
        $groups = HelpService::getFaqs();
        $this->respondWithData($groups);
    }

    // ============================================
    // ADMIN ENDPOINTS
    // ============================================

    /**
     * GET /api/v2/admin/help/faqs
     *
     * Returns all FAQs for the current tenant (including unpublished).
     * Requires admin role.
     */
    public function adminGetFaqs(): void
    {
        $this->requireAdmin();

        $faqs = HelpService::adminGetFaqs();

        $formatted = array_map(function (array $row) {
            return [
                'id'           => (int) $row['id'],
                'category'     => $row['category'],
                'question'     => $row['question'],
                'answer'       => $row['answer'],
                'sort_order'   => (int) $row['sort_order'],
                'is_published' => (bool) $row['is_published'],
                'created_at'   => $row['created_at'],
                'updated_at'   => $row['updated_at'] ?? null,
            ];
        }, $faqs);

        $this->respondWithData($formatted);
    }

    /**
     * POST /api/v2/admin/help/faqs
     *
     * Creates a new FAQ for the current tenant.
     * Requires admin role.
     *
     * Body: { category?, question, answer, sort_order?, is_published? }
     */
    public function adminCreateFaq(): void
    {
        $this->requireAdmin();

        $data = $this->getAllInput();

        $question = trim($data['question'] ?? '');
        $answer   = trim($data['answer'] ?? '');

        if ($question === '') {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Question is required',
                'question',
                400
            );
            return;
        }

        if ($answer === '') {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Answer is required',
                'answer',
                400
            );
            return;
        }

        $newId = HelpService::createFaq($data);

        $this->respondWithData(['id' => $newId, 'created' => true], null, 201);
    }

    /**
     * PUT /api/v2/admin/help/faqs/{id}
     *
     * Updates an existing FAQ belonging to the current tenant.
     * Requires admin role.
     *
     * Body: Any subset of { category, question, answer, sort_order, is_published }
     */
    public function adminUpdateFaq(int $id): void
    {
        $this->requireAdmin();

        $data = $this->getAllInput();

        if (empty($data)) {
            $this->respondWithError(
                'VALIDATION_NO_FIELDS',
                'No fields provided to update',
                null,
                400
            );
            return;
        }

        $updated = HelpService::updateFaq($id, $data);

        if (!$updated) {
            $this->respondWithError(
                'VALIDATION_NO_FIELDS',
                'No valid fields provided to update',
                null,
                400
            );
            return;
        }

        $this->respondWithData(['id' => $id, 'updated' => true]);
    }

    /**
     * DELETE /api/v2/admin/help/faqs/{id}
     *
     * Deletes an FAQ belonging to the current tenant.
     * Requires admin role.
     */
    public function adminDeleteFaq(int $id): void
    {
        $this->requireAdmin();

        HelpService::deleteFaq($id);

        $this->respondWithData(['id' => $id, 'deleted' => true]);
    }
}
