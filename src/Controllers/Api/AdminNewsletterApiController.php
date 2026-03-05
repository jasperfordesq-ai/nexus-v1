<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Admin Newsletter API Controller
 * Provides CRUD for newsletters, subscribers, segments, templates, and analytics.
 * Gracefully returns empty data if tables don't exist.
 */
class AdminNewsletterApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    private function tableExists(string $table): bool
    {
        try {
            Database::query("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function index(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $status = $this->query('status');

        if (!$this->tableExists('newsletters')) {
            $this->respondWithPaginatedCollection([], 0, $page, $perPage);
            return;
        }

        try {
            $where = 'WHERE tenant_id = ?';
            $params = [$tenantId];
            if ($status) {
                $where .= ' AND status = ?';
                $params[] = $status;
            }

            $countStmt = Database::query("SELECT COUNT(*) as cnt FROM newsletters {$where}", $params);
            $total = (int) $countStmt->fetch()['cnt'];

            $params[] = $perPage;
            $params[] = $offset;
            $stmt = Database::query(
                "SELECT * FROM newsletters {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?",
                $params
            );
            $items = $stmt->fetchAll() ?: [];

            $this->respondWithPaginatedCollection($items, $total, $page, $perPage);
        } catch (\Exception $e) {
            $this->respondWithPaginatedCollection([], 0, $page, $perPage);
        }
    }

    public function show(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletters')) {
            $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT * FROM newsletters WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
            $item = $stmt->fetch();
            if (!$item) {
                $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
                return;
            }
            $this->respondWithData($item);
        } catch (\Exception $e) {
            $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
        }
    }

    public function store(): void
    {
        $userId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $name = $this->input('name');
        $subject = $this->input('subject', '');
        $previewText = $this->input('preview_text', '');
        $content = $this->input('content', '');
        $status = $this->input('status', 'draft');
        $targetAudience = $this->input('target_audience', 'all_members');
        $segmentId = $this->inputInt('segment_id') ?: null;
        $scheduledAt = $this->input('scheduled_at') ?: null;
        $abTestEnabled = $this->input('ab_test_enabled') ? 1 : 0;
        $subjectB = $this->input('subject_b') ?: null;
        $templateId = $this->inputInt('template_id') ?: null;

        if (!$name) {
            $this->respondWithError('VALIDATION_ERROR', 'Name is required', 'name');
            return;
        }

        if (!$this->tableExists('newsletters')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter functionality is not yet configured', null, 503);
            return;
        }

        try {
            Database::query(
                "INSERT INTO newsletters (tenant_id, name, subject, preview_text, content, status,
                    target_audience, segment_id, scheduled_at, ab_test_enabled, subject_b, template_id,
                    created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$tenantId, $name, $subject, $previewText, $content, $status,
                 $targetAudience, $segmentId, $scheduledAt, $abTestEnabled, $subjectB, $templateId,
                 $userId]
            );
            $id = Database::lastInsertId();
            $this->respondWithData(['id' => $id, 'name' => $name, 'status' => $status], null, 201);
        } catch (\Exception $e) {
            $this->respondWithError('CREATE_FAILED', 'Failed to create newsletter: ' . $e->getMessage());
        }
    }

    public function update(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletters')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter functionality is not yet configured', null, 503);
            return;
        }

        try {
            $fields = [];
            $params = [];

            $allowedFields = [
                'name', 'subject', 'preview_text', 'content', 'status',
                'target_audience', 'scheduled_at', 'subject_b', 'template_id',
            ];

            foreach ($allowedFields as $field) {
                $val = $this->input($field);
                if ($val !== null) {
                    $fields[] = "{$field} = ?";
                    $params[] = $val;
                }
            }

            // Handle nullable int fields
            foreach (['segment_id'] as $intField) {
                $val = $this->input($intField);
                if ($val !== null) {
                    $fields[] = "{$intField} = ?";
                    $params[] = $val ? (int)$val : null;
                }
            }

            // Handle boolean fields
            $abVal = $this->input('ab_test_enabled');
            if ($abVal !== null) {
                $fields[] = "ab_test_enabled = ?";
                $params[] = $abVal ? 1 : 0;
            }

            if (empty($fields)) {
                $this->respondWithError('VALIDATION_ERROR', 'No fields to update');
                return;
            }
            $params[] = $id;
            $params[] = $tenantId;
            Database::query(
                "UPDATE newsletters SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?",
                $params
            );
            $this->respondWithData(['id' => $id, 'updated' => true]);
        } catch (\Exception $e) {
            $this->respondWithError('UPDATE_FAILED', 'Failed to update newsletter');
        }
    }

    public function destroy(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletters')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter functionality is not yet configured', null, 503);
            return;
        }

        try {
            Database::query("DELETE FROM newsletters WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            $this->noContent();
        } catch (\Exception $e) {
            $this->respondWithError('DELETE_FAILED', 'Failed to delete newsletter');
        }
    }

    public function subscribers(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $offset = ($page - 1) * $perPage;
        $status = $this->query('status');
        $search = $this->query('search');

        if (!$this->tableExists('newsletter_subscribers')) {
            // Fallback to users table if newsletter_subscribers doesn't exist
            try {
                $stmt = Database::query(
                    "SELECT u.id, u.first_name, u.last_name, u.email, u.status, u.created_at,
                            'member' as source
                     FROM users u
                     WHERE u.tenant_id = ? AND u.newsletter_opt_in = 1
                     ORDER BY u.created_at DESC
                     LIMIT 100",
                    [$tenantId]
                );
                $subscribers = $stmt->fetchAll() ?: [];
                $this->respondWithPaginatedCollection($subscribers, count($subscribers), 1, 100);
            } catch (\Exception $e) {
                $this->respondWithPaginatedCollection([], 0, 1, 20);
            }
            return;
        }

        try {
            // Get stats
            $statsStmt = Database::query(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed
                 FROM newsletter_subscribers
                 WHERE tenant_id = ?",
                [$tenantId]
            );
            $stats = $statsStmt->fetch();

            // Build filtered query
            $where = 'WHERE ns.tenant_id = ?';
            $params = [$tenantId];

            if ($status && in_array($status, ['active', 'pending', 'unsubscribed'])) {
                $where .= ' AND ns.status = ?';
                $params[] = $status;
            }

            if ($search) {
                $where .= ' AND (ns.email LIKE ? OR ns.first_name LIKE ? OR ns.last_name LIKE ?)';
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            // Count filtered
            $countStmt = Database::query(
                "SELECT COUNT(*) as cnt FROM newsletter_subscribers ns {$where}",
                $params
            );
            $total = (int) $countStmt->fetch()['cnt'];

            // Fetch page
            $params[] = $perPage;
            $params[] = $offset;
            $stmt = Database::query(
                "SELECT ns.id, ns.email, ns.first_name, ns.last_name, ns.status,
                        ns.source, ns.created_at, ns.confirmed_at, ns.user_id
                 FROM newsletter_subscribers ns
                 {$where}
                 ORDER BY ns.created_at DESC
                 LIMIT ? OFFSET ?",
                $params
            );
            $subscribers = $stmt->fetchAll() ?: [];

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;

            $this->jsonResponse([
                'data' => $subscribers,
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $totalPages,
                    'has_more' => $page < $totalPages,
                ],
                'stats' => [
                    'total' => (int) ($stats['total'] ?? 0),
                    'active' => (int) ($stats['active'] ?? 0),
                    'pending' => (int) ($stats['pending'] ?? 0),
                    'unsubscribed' => (int) ($stats['unsubscribed'] ?? 0),
                ],
            ]);
        } catch (\Exception $e) {
            $this->respondWithPaginatedCollection([], 0, $page, $perPage);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Subscriber Management
    // ─────────────────────────────────────────────────────────────────────────────

    public function addSubscriber(): void
    {
        $this->requireAdmin();

        $email = $this->input('email');
        $firstName = $this->input('first_name');
        $lastName = $this->input('last_name');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respondWithError('VALIDATION_ERROR', 'A valid email address is required', 'email');
            return;
        }

        if (!$this->tableExists('newsletter_subscribers')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter subscriber functionality is not yet configured', null, 503);
            return;
        }

        try {
            $existing = \Nexus\Models\NewsletterSubscriber::findByEmail($email);
            if ($existing) {
                $this->respondWithError('DUPLICATE', 'A subscriber with this email already exists', 'email', 409);
                return;
            }

            $id = \Nexus\Models\NewsletterSubscriber::createConfirmed($email, $firstName, $lastName, 'manual');
            $this->respondWithData([
                'id' => $id,
                'email' => strtolower(trim($email)),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'status' => 'active',
            ], null, 201);
        } catch (\Exception $e) {
            $this->respondWithError('CREATE_FAILED', 'Failed to add subscriber: ' . $e->getMessage());
        }
    }

    public function removeSubscriber(int $id): void
    {
        $this->requireAdmin();

        if (!$this->tableExists('newsletter_subscribers')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter subscriber functionality is not yet configured', null, 503);
            return;
        }

        try {
            $subscriber = \Nexus\Models\NewsletterSubscriber::findById($id);
            if (!$subscriber) {
                $this->respondWithError('NOT_FOUND', 'Subscriber not found', null, 404);
                return;
            }

            \Nexus\Models\NewsletterSubscriber::delete($id);
            $this->noContent();
        } catch (\Exception $e) {
            $this->respondWithError('DELETE_FAILED', 'Failed to remove subscriber');
        }
    }

    public function importSubscribers(): void
    {
        $this->requireAdmin();

        $rows = $this->input('rows');
        if (!is_array($rows) || empty($rows)) {
            $this->respondWithError('VALIDATION_ERROR', 'An array of subscriber rows is required', 'rows');
            return;
        }

        if (!$this->tableExists('newsletter_subscribers')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter subscriber functionality is not yet configured', null, 503);
            return;
        }

        try {
            $result = \Nexus\Models\NewsletterSubscriber::import($rows);
            $this->respondWithData([
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'total_rows' => count($rows),
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('IMPORT_FAILED', 'Failed to import subscribers: ' . $e->getMessage());
        }
    }

    public function exportSubscribers(): void
    {
        $this->requireAdmin();

        if (!$this->tableExists('newsletter_subscribers')) {
            $this->respondWithData([]);
            return;
        }

        try {
            $subscribers = \Nexus\Models\NewsletterSubscriber::export();
            $this->respondWithData($subscribers ?: []);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    public function syncPlatformMembers(): void
    {
        $this->requireAdmin();

        if (!$this->tableExists('newsletter_subscribers')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter subscriber functionality is not yet configured', null, 503);
            return;
        }

        try {
            $result = \Nexus\Models\NewsletterSubscriber::syncMembersWithStats();
            $this->respondWithData([
                'synced' => $result['synced'],
                'total_users' => $result['total_users'],
                'already_subscribed' => $result['already_subscribed'],
                'eligible' => $result['eligible'],
                'pending_approval' => $result['pending_approval'] ?? 0,
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('SYNC_FAILED', 'Failed to sync members: ' . $e->getMessage());
        }
    }

    public function segments(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_segments')) {
            $this->respondWithData([]);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT * FROM newsletter_segments WHERE tenant_id = ? ORDER BY name ASC",
                [$tenantId]
            );
            $this->respondWithData($stmt->fetchAll() ?: []);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    public function templates(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_templates')) {
            $this->respondWithData([]);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT * FROM newsletter_templates WHERE tenant_id = ? ORDER BY name ASC",
                [$tenantId]
            );
            $this->respondWithData($stmt->fetchAll() ?: []);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    public function analytics(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $data = [
            'total_newsletters' => 0,
            'total_sent' => 0,
            'avg_open_rate' => 0,
            'avg_click_rate' => 0,
            'total_subscribers' => 0,
        ];

        if (!$this->tableExists('newsletters')) {
            $this->respondWithData($data);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT COUNT(*) as total, SUM(CASE WHEN status='sent' THEN 1 ELSE 0 END) as sent,
                        AVG(open_rate) as avg_open, AVG(click_rate) as avg_click
                 FROM newsletters WHERE tenant_id = ?",
                [$tenantId]
            );
            $row = $stmt->fetch();
            if ($row) {
                $data['total_newsletters'] = (int) ($row['total'] ?? 0);
                $data['total_sent'] = (int) ($row['sent'] ?? 0);
                $data['avg_open_rate'] = round((float) ($row['avg_open'] ?? 0), 1);
                $data['avg_click_rate'] = round((float) ($row['avg_click'] ?? 0), 1);
            }

            $subStmt = Database::query(
                "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND status = 'active'",
                [$tenantId]
            );
            $subRow = $subStmt->fetch();
            $data['total_subscribers'] = (int) ($subRow['cnt'] ?? 0);
        } catch (\Exception $e) {
            // Return defaults
        }

        $this->respondWithData($data);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Segment CRUD
    // ─────────────────────────────────────────────────────────────────────────────

    public function showSegment(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_segments')) {
            $this->respondWithError('NOT_FOUND', 'Segment not found', null, 404);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT * FROM newsletter_segments WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
            $segment = $stmt->fetch();
            if (!$segment) {
                $this->respondWithError('NOT_FOUND', 'Segment not found', null, 404);
                return;
            }

            // Decode rules JSON if stored as string
            if (!empty($segment['rules']) && is_string($segment['rules'])) {
                $segment['rules'] = json_decode($segment['rules'], true) ?: [];
            }

            $this->respondWithData($segment);
        } catch (\Exception $e) {
            $this->respondWithError('FETCH_FAILED', 'Failed to fetch segment');
        }
    }

    public function storeSegment(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $name = $this->input('name');
        $description = $this->input('description', '');
        $isActive = $this->inputBool('is_active', true);
        $matchType = $this->input('match_type', 'all');
        $rules = $this->input('rules');

        if (!$name) {
            $this->respondWithError('VALIDATION_ERROR', 'Name is required', 'name');
            return;
        }

        if (!in_array($matchType, ['all', 'any'], true)) {
            $this->respondWithError('VALIDATION_ERROR', 'match_type must be "all" or "any"', 'match_type');
            return;
        }

        if (!$this->tableExists('newsletter_segments')) {
            $this->respondWithError('TABLE_MISSING', 'Segment functionality is not yet configured', null, 503);
            return;
        }

        try {
            $rulesJson = is_array($rules) ? json_encode($rules) : ($rules ?: '[]');

            // Count matching subscribers
            $subscriberCount = $this->countMatchingSubscribers($tenantId, $matchType, is_array($rules) ? $rules : (json_decode($rulesJson, true) ?: []));

            Database::query(
                "INSERT INTO newsletter_segments (tenant_id, name, description, is_active, match_type, rules, subscriber_count, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [$tenantId, $name, $description, $isActive ? 1 : 0, $matchType, $rulesJson, $subscriberCount]
            );

            $id = Database::lastInsertId();
            $this->respondWithData([
                'id' => $id,
                'name' => $name,
                'is_active' => $isActive,
                'subscriber_count' => $subscriberCount,
            ], null, 201);
        } catch (\Exception $e) {
            $this->respondWithError('CREATE_FAILED', 'Failed to create segment: ' . $e->getMessage());
        }
    }

    public function updateSegment(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_segments')) {
            $this->respondWithError('TABLE_MISSING', 'Segment functionality is not yet configured', null, 503);
            return;
        }

        try {
            // Check segment exists
            $existing = Database::query(
                "SELECT id FROM newsletter_segments WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$existing) {
                $this->respondWithError('NOT_FOUND', 'Segment not found', null, 404);
                return;
            }

            $fields = [];
            $params = [];

            $name = $this->input('name');
            if ($name !== null) {
                $fields[] = "name = ?";
                $params[] = $name;
            }

            $description = $this->input('description');
            if ($description !== null) {
                $fields[] = "description = ?";
                $params[] = $description;
            }

            $isActive = $this->input('is_active');
            if ($isActive !== null) {
                $fields[] = "is_active = ?";
                $params[] = $isActive ? 1 : 0;
            }

            $matchType = $this->input('match_type');
            if ($matchType !== null) {
                if (!in_array($matchType, ['all', 'any'], true)) {
                    $this->respondWithError('VALIDATION_ERROR', 'match_type must be "all" or "any"', 'match_type');
                    return;
                }
                $fields[] = "match_type = ?";
                $params[] = $matchType;
            }

            $rules = $this->input('rules');
            if ($rules !== null) {
                $rulesJson = is_array($rules) ? json_encode($rules) : $rules;
                $fields[] = "rules = ?";
                $params[] = $rulesJson;
            }

            if (empty($fields)) {
                $this->respondWithError('VALIDATION_ERROR', 'No fields to update');
                return;
            }

            // Recalculate subscriber count if rules or match_type changed
            if ($rules !== null || $matchType !== null) {
                $finalMatchType = $matchType ?? 'all';
                $finalRules = is_array($rules) ? $rules : (json_decode($rules ?? '[]', true) ?: []);
                $subscriberCount = $this->countMatchingSubscribers($tenantId, $finalMatchType, $finalRules);
                $fields[] = "subscriber_count = ?";
                $params[] = $subscriberCount;
            }

            $fields[] = "updated_at = NOW()";
            $params[] = $id;
            $params[] = $tenantId;

            Database::query(
                "UPDATE newsletter_segments SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?",
                $params
            );

            $this->respondWithData(['id' => $id, 'updated' => true]);
        } catch (\Exception $e) {
            $this->respondWithError('UPDATE_FAILED', 'Failed to update segment: ' . $e->getMessage());
        }
    }

    public function destroySegment(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_segments')) {
            $this->respondWithError('TABLE_MISSING', 'Segment functionality is not yet configured', null, 503);
            return;
        }

        try {
            $existing = Database::query(
                "SELECT id FROM newsletter_segments WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$existing) {
                $this->respondWithError('NOT_FOUND', 'Segment not found', null, 404);
                return;
            }

            Database::query(
                "DELETE FROM newsletter_segments WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
            $this->noContent();
        } catch (\Exception $e) {
            $this->respondWithError('DELETE_FAILED', 'Failed to delete segment');
        }
    }

    public function previewSegment(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $matchType = $this->input('match_type', 'all');
        $rules = $this->input('rules');

        if (!in_array($matchType, ['all', 'any'], true)) {
            $this->respondWithError('VALIDATION_ERROR', 'match_type must be "all" or "any"', 'match_type');
            return;
        }

        $rulesArray = is_array($rules) ? $rules : (json_decode($rules ?? '[]', true) ?: []);
        $count = $this->countMatchingSubscribers($tenantId, $matchType, $rulesArray);

        $this->respondWithData([
            'matching_count' => $count,
            'match_type' => $matchType,
            'rules_count' => count($rulesArray),
        ]);
    }

    public function getSegmentSuggestions(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $suggestions = [];

        try {
            // Suggestion 1: Active users (logged in within 30 days)
            $activeCount = 0;
            try {
                $stmt = Database::query(
                    "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND status = 'active' AND last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                    [$tenantId]
                );
                $activeCount = (int) ($stmt->fetch()['cnt'] ?? 0);
            } catch (\Exception $e) {
                // Column may not exist
            }

            if ($activeCount > 0) {
                $suggestions[] = [
                    'name' => 'Active Members (30 days)',
                    'description' => "Members who logged in within the last 30 days ({$activeCount} members)",
                    'match_type' => 'all',
                    'rules' => [
                        ['field' => 'login_recency', 'operator' => 'less_than', 'value' => '30'],
                    ],
                    'estimated_count' => $activeCount,
                ];
            }

            // Suggestion 2: Inactive users (not logged in for 60+ days)
            $inactiveCount = 0;
            try {
                $stmt = Database::query(
                    "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND status = 'active' AND (last_login IS NULL OR last_login < DATE_SUB(NOW(), INTERVAL 60 DAY))",
                    [$tenantId]
                );
                $inactiveCount = (int) ($stmt->fetch()['cnt'] ?? 0);
            } catch (\Exception $e) {
                // Column may not exist
            }

            if ($inactiveCount > 0) {
                $suggestions[] = [
                    'name' => 'Inactive Members (60+ days)',
                    'description' => "Members who haven't logged in for 60+ days ({$inactiveCount} members)",
                    'match_type' => 'all',
                    'rules' => [
                        ['field' => 'login_recency', 'operator' => 'greater_than', 'value' => '60'],
                    ],
                    'estimated_count' => $inactiveCount,
                ];
            }

            // Suggestion 3: Members with listings
            $withListingsCount = 0;
            try {
                $stmt = Database::query(
                    "SELECT COUNT(DISTINCT u.id) as cnt FROM users u INNER JOIN listings l ON u.id = l.user_id AND l.tenant_id = ? WHERE u.tenant_id = ? AND u.status = 'active'",
                    [$tenantId, $tenantId]
                );
                $withListingsCount = (int) ($stmt->fetch()['cnt'] ?? 0);
            } catch (\Exception $e) {
                // Table may not exist
            }

            if ($withListingsCount > 0) {
                $suggestions[] = [
                    'name' => 'Members with Listings',
                    'description' => "Members who have created at least one listing ({$withListingsCount} members)",
                    'match_type' => 'all',
                    'rules' => [
                        ['field' => 'has_listings', 'operator' => 'equals', 'value' => '1'],
                    ],
                    'estimated_count' => $withListingsCount,
                ];
            }

            // Suggestion 4: New members (joined in last 30 days)
            $newCount = 0;
            try {
                $stmt = Database::query(
                    "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND status = 'active' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                    [$tenantId]
                );
                $newCount = (int) ($stmt->fetch()['cnt'] ?? 0);
            } catch (\Exception $e) {
                // Ignore
            }

            if ($newCount > 0) {
                $suggestions[] = [
                    'name' => 'New Members (Last 30 Days)',
                    'description' => "Members who joined in the last 30 days ({$newCount} members)",
                    'match_type' => 'all',
                    'rules' => [
                        ['field' => 'member_since', 'operator' => 'after', 'value' => date('Y-m-d', strtotime('-30 days'))],
                    ],
                    'estimated_count' => $newCount,
                ];
            }

            // Suggestion 5: Admin users
            $adminCount = 0;
            try {
                $stmt = Database::query(
                    "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND status = 'active' AND role IN ('admin', 'broker')",
                    [$tenantId]
                );
                $adminCount = (int) ($stmt->fetch()['cnt'] ?? 0);
            } catch (\Exception $e) {
                // Ignore
            }

            if ($adminCount > 0) {
                $suggestions[] = [
                    'name' => 'Admins & Brokers',
                    'description' => "Admin and broker users ({$adminCount} members)",
                    'match_type' => 'any',
                    'rules' => [
                        ['field' => 'user_role', 'operator' => 'equals', 'value' => 'admin'],
                        ['field' => 'user_role', 'operator' => 'equals', 'value' => 'broker'],
                    ],
                    'estimated_count' => $adminCount,
                ];
            }
        } catch (\Exception $e) {
            // Return whatever suggestions we have
        }

        $this->respondWithData($suggestions);
    }

    /**
     * Count subscribers matching the given segment rules.
     */
    private function countMatchingSubscribers(int $tenantId, string $matchType, array $rules): int
    {
        if (empty($rules)) {
            // No rules = match all active users
            try {
                $stmt = Database::query(
                    "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND status = 'active'",
                    [$tenantId]
                );
                return (int) ($stmt->fetch()['cnt'] ?? 0);
            } catch (\Exception $e) {
                return 0;
            }
        }

        // Build dynamic WHERE clauses from rules
        $conditions = [];
        $params = [$tenantId];
        $needsListingJoin = false;

        foreach ($rules as $rule) {
            if (!is_array($rule) || empty($rule['field']) || empty($rule['operator'])) {
                continue;
            }

            $field = $rule['field'];
            $operator = $rule['operator'];
            $value = $rule['value'] ?? '';

            switch ($field) {
                case 'login_recency':
                    $days = (int) $value;
                    if ($operator === 'less_than') {
                        $conditions[] = "u.last_login >= DATE_SUB(NOW(), INTERVAL ? DAY)";
                    } else {
                        $conditions[] = "(u.last_login IS NULL OR u.last_login < DATE_SUB(NOW(), INTERVAL ? DAY))";
                    }
                    $params[] = $days;
                    break;

                case 'member_since':
                    if ($operator === 'after') {
                        $conditions[] = "u.created_at >= ?";
                    } else {
                        $conditions[] = "u.created_at <= ?";
                    }
                    $params[] = $value;
                    break;

                case 'user_role':
                    if ($operator === 'equals') {
                        $conditions[] = "u.role = ?";
                    } else {
                        $conditions[] = "u.role != ?";
                    }
                    $params[] = $value;
                    break;

                case 'profile_type':
                    if ($operator === 'equals') {
                        $conditions[] = "u.profile_type = ?";
                    } else {
                        $conditions[] = "u.profile_type != ?";
                    }
                    $params[] = $value;
                    break;

                case 'county':
                    if ($operator === 'equals') {
                        $conditions[] = "u.county = ?";
                        $params[] = $value;
                    } elseif ($operator === 'contains') {
                        $conditions[] = "u.county LIKE ?";
                        $params[] = '%' . $value . '%';
                    }
                    break;

                case 'town':
                    if ($operator === 'equals') {
                        $conditions[] = "u.town = ?";
                        $params[] = $value;
                    } elseif ($operator === 'contains') {
                        $conditions[] = "u.town LIKE ?";
                        $params[] = '%' . $value . '%';
                    }
                    break;

                case 'has_listings':
                    $needsListingJoin = true;
                    if ($value === '1' || $value === 'true') {
                        $conditions[] = "listing_count.cnt > 0";
                    } else {
                        $conditions[] = "(listing_count.cnt IS NULL OR listing_count.cnt = 0)";
                    }
                    break;

                case 'listing_count':
                    $needsListingJoin = true;
                    $intVal = (int) $value;
                    if ($operator === 'greater_than') {
                        $conditions[] = "COALESCE(listing_count.cnt, 0) > ?";
                    } elseif ($operator === 'less_than') {
                        $conditions[] = "COALESCE(listing_count.cnt, 0) < ?";
                    } else {
                        $conditions[] = "COALESCE(listing_count.cnt, 0) = ?";
                    }
                    $params[] = $intVal;
                    break;

                case 'activity_score':
                case 'community_rank':
                    $col = $field === 'activity_score' ? 'u.activity_score' : 'u.community_rank';
                    $numVal = (float) $value;
                    if ($operator === 'greater_than') {
                        $conditions[] = "{$col} > ?";
                    } elseif ($operator === 'less_than') {
                        $conditions[] = "{$col} < ?";
                    } else {
                        $conditions[] = "{$col} = ?";
                    }
                    $params[] = $numVal;
                    break;

                case 'transaction_count':
                    $numVal = (int) $value;
                    if ($operator === 'greater_than') {
                        $conditions[] = "COALESCE(u.transaction_count, 0) > ?";
                    } elseif ($operator === 'less_than') {
                        $conditions[] = "COALESCE(u.transaction_count, 0) < ?";
                    } else {
                        $conditions[] = "COALESCE(u.transaction_count, 0) = ?";
                    }
                    $params[] = $numVal;
                    break;

                // email_open_rate and email_click_rate would need newsletter tracking join
                // For now, skip complex joins — these are best-effort
                default:
                    break;
            }
        }

        if (empty($conditions)) {
            try {
                $stmt = Database::query(
                    "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND status = 'active'",
                    [$tenantId]
                );
                return (int) ($stmt->fetch()['cnt'] ?? 0);
            } catch (\Exception $e) {
                return 0;
            }
        }

        $joinClause = '';
        if ($needsListingJoin) {
            $joinClause = "LEFT JOIN (SELECT user_id, COUNT(*) as cnt FROM listings WHERE tenant_id = ? GROUP BY user_id) listing_count ON u.id = listing_count.user_id";
            // Insert tenant_id for the listing subquery after the main tenant_id
            array_splice($params, 1, 0, [$tenantId]);
        }

        $connector = $matchType === 'any' ? ' OR ' : ' AND ';
        $whereClause = '(' . implode($connector, $conditions) . ')';

        try {
            $sql = "SELECT COUNT(DISTINCT u.id) as cnt FROM users u {$joinClause} WHERE u.tenant_id = ? AND u.status = 'active' AND {$whereClause}";
            $stmt = Database::query($sql, $params);
            return (int) ($stmt->fetch()['cnt'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Template CRUD
    // ─────────────────────────────────────────────────────────────────────────────

    public function showTemplate(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_templates')) {
            $this->respondWithError('NOT_FOUND', 'Template not found', null, 404);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT t.*,
                        (SELECT COUNT(*) FROM newsletters n WHERE n.template_id = t.id AND n.tenant_id = ?) as usage_count
                 FROM newsletter_templates t
                 WHERE t.id = ? AND t.tenant_id = ?",
                [$tenantId, $id, $tenantId]
            );
            $item = $stmt->fetch();
            if (!$item) {
                $this->respondWithError('NOT_FOUND', 'Template not found', null, 404);
                return;
            }
            $this->respondWithData($item);
        } catch (\Exception $e) {
            $this->respondWithError('NOT_FOUND', 'Template not found', null, 404);
        }
    }

    public function storeTemplate(): void
    {
        $userId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $name = $this->input('name');
        if (!$name) {
            $this->respondWithError('VALIDATION_ERROR', 'Name is required', 'name');
            return;
        }

        if (!$this->tableExists('newsletter_templates')) {
            $this->respondWithError('TABLE_MISSING', 'Template functionality is not yet configured', null, 503);
            return;
        }

        $description = $this->input('description', '');
        $category = $this->input('category', 'custom');
        $isActive = $this->input('is_active') !== null ? ($this->input('is_active') ? 1 : 0) : 1;
        $subject = $this->input('subject', '');
        $previewText = $this->input('preview_text', '');
        $content = $this->input('content', '');

        try {
            Database::query(
                "INSERT INTO newsletter_templates
                    (tenant_id, name, description, category, is_active, subject, preview_text, content, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [$tenantId, $name, $description, $category, $isActive, $subject, $previewText, $content, $userId]
            );
            $id = Database::lastInsertId();
            $this->respondWithData(['id' => $id, 'name' => $name], null, 201);
        } catch (\Exception $e) {
            $this->respondWithError('CREATE_FAILED', 'Failed to create template: ' . $e->getMessage());
        }
    }

    public function updateTemplate(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_templates')) {
            $this->respondWithError('TABLE_MISSING', 'Template functionality is not yet configured', null, 503);
            return;
        }

        try {
            $fields = [];
            $params = [];

            $allowedFields = ['name', 'description', 'category', 'subject', 'preview_text', 'content'];

            foreach ($allowedFields as $field) {
                $val = $this->input($field);
                if ($val !== null) {
                    $fields[] = "{$field} = ?";
                    $params[] = $val;
                }
            }

            // Handle boolean field
            $isActive = $this->input('is_active');
            if ($isActive !== null) {
                $fields[] = "is_active = ?";
                $params[] = $isActive ? 1 : 0;
            }

            if (empty($fields)) {
                $this->respondWithError('VALIDATION_ERROR', 'No fields to update');
                return;
            }

            $fields[] = "updated_at = NOW()";
            $params[] = $id;
            $params[] = $tenantId;

            Database::query(
                "UPDATE newsletter_templates SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?",
                $params
            );
            $this->respondWithData(['id' => $id, 'updated' => true]);
        } catch (\Exception $e) {
            $this->respondWithError('UPDATE_FAILED', 'Failed to update template');
        }
    }

    public function destroyTemplate(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_templates')) {
            $this->respondWithError('TABLE_MISSING', 'Template functionality is not yet configured', null, 503);
            return;
        }

        try {
            Database::query(
                "DELETE FROM newsletter_templates WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
            $this->noContent();
        } catch (\Exception $e) {
            $this->respondWithError('DELETE_FAILED', 'Failed to delete template');
        }
    }

    public function duplicateTemplate(int $id): void
    {
        $userId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_templates')) {
            $this->respondWithError('TABLE_MISSING', 'Template functionality is not yet configured', null, 503);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT * FROM newsletter_templates WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
            $original = $stmt->fetch();
            if (!$original) {
                $this->respondWithError('NOT_FOUND', 'Template not found', null, 404);
                return;
            }

            $newName = $original['name'] . ' (Copy)';

            Database::query(
                "INSERT INTO newsletter_templates
                    (tenant_id, name, description, category, is_active, subject, preview_text, content, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [
                    $tenantId,
                    $newName,
                    $original['description'] ?? '',
                    $original['category'] ?? 'custom',
                    $original['is_active'] ?? 1,
                    $original['subject'] ?? '',
                    $original['preview_text'] ?? '',
                    $original['content'] ?? '',
                    $userId,
                ]
            );

            $newId = Database::lastInsertId();
            $this->respondWithData(['id' => $newId, 'name' => $newName], null, 201);
        } catch (\Exception $e) {
            $this->respondWithError('DUPLICATE_FAILED', 'Failed to duplicate template: ' . $e->getMessage());
        }
    }

    public function previewTemplate(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_templates')) {
            $this->respondWithError('NOT_FOUND', 'Template not found', null, 404);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT content, subject, name FROM newsletter_templates WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
            $item = $stmt->fetch();
            if (!$item) {
                $this->respondWithError('NOT_FOUND', 'Template not found', null, 404);
                return;
            }
            $this->respondWithData([
                'html' => $item['content'] ?? '',
                'subject' => $item['subject'] ?? '',
                'name' => $item['name'] ?? '',
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('NOT_FOUND', 'Template not found', null, 404);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Per-Campaign Stats
    // ─────────────────────────────────────────────────────────────────────────────

    public function stats(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletters')) {
            $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
            return;
        }

        try {
            // Get newsletter with author info
            $stmt = Database::query(
                "SELECT n.*, u.first_name as author_first_name, u.last_name as author_last_name
                 FROM newsletters n
                 LEFT JOIN users u ON n.created_by = u.id
                 WHERE n.id = ? AND n.tenant_id = ?",
                [$id, $tenantId]
            );
            $newsletter = $stmt->fetch();

            if (!$newsletter) {
                $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
                return;
            }

            $authorName = trim(($newsletter['author_first_name'] ?? '') . ' ' . ($newsletter['author_last_name'] ?? ''));

            // ── Delivery stats ──
            $delivery = [
                'total_sent' => 0,
                'delivered' => 0,
                'failed' => 0,
                'bounced' => 0,
                'pending' => 0,
            ];

            if ($this->tableExists('newsletter_queue')) {
                try {
                    $qStmt = Database::query(
                        "SELECT status, COUNT(*) as cnt
                         FROM newsletter_queue
                         WHERE newsletter_id = ? AND tenant_id = ?
                         GROUP BY status",
                        [$id, $tenantId]
                    );
                    foreach ($qStmt->fetchAll() ?: [] as $row) {
                        $s = $row['status'] ?? '';
                        $c = (int) ($row['cnt'] ?? 0);
                        if ($s === 'sent') {
                            $delivery['delivered'] += $c;
                            $delivery['total_sent'] += $c;
                        } elseif ($s === 'failed') {
                            $delivery['failed'] += $c;
                            $delivery['total_sent'] += $c;
                        } elseif ($s === 'bounced') {
                            $delivery['bounced'] += $c;
                            $delivery['total_sent'] += $c;
                        } elseif ($s === 'pending' || $s === 'sending') {
                            $delivery['pending'] += $c;
                        }
                    }
                } catch (\Exception $e) {
                    $delivery['total_sent'] = (int) ($newsletter['total_sent'] ?? 0);
                    $delivery['delivered'] = $delivery['total_sent'] - (int) ($newsletter['total_failed'] ?? 0);
                    $delivery['failed'] = (int) ($newsletter['total_failed'] ?? 0);
                }
            } else {
                $delivery['total_sent'] = (int) ($newsletter['total_sent'] ?? 0);
                $delivery['delivered'] = $delivery['total_sent'] - (int) ($newsletter['total_failed'] ?? 0);
                $delivery['failed'] = (int) ($newsletter['total_failed'] ?? 0);
            }

            if ($this->tableExists('newsletter_bounces')) {
                try {
                    $bStmt = Database::query(
                        "SELECT COUNT(*) as cnt FROM newsletter_bounces
                         WHERE newsletter_id = ? AND tenant_id = ?",
                        [$id, $tenantId]
                    );
                    $delivery['bounced'] = (int) ($bStmt->fetch()['cnt'] ?? 0);
                } catch (\Exception $e) {
                    // Keep default
                }
            }

            // ── Engagement stats ──
            $uniqueOpens = (int) ($newsletter['unique_opens'] ?? 0);
            $totalOpens = (int) ($newsletter['total_opens'] ?? 0);
            $uniqueClicks = (int) ($newsletter['unique_clicks'] ?? 0);
            $totalClicks = (int) ($newsletter['total_clicks'] ?? 0);

            if ($this->tableExists('newsletter_opens')) {
                try {
                    $oRow = Database::query(
                        "SELECT COUNT(*) as total, COUNT(DISTINCT email) as uniq
                         FROM newsletter_opens
                         WHERE newsletter_id = ? AND tenant_id = ?",
                        [$id, $tenantId]
                    )->fetch();
                    if ($oRow) {
                        $totalOpens = max($totalOpens, (int) $oRow['total']);
                        $uniqueOpens = max($uniqueOpens, (int) $oRow['uniq']);
                    }
                } catch (\Exception $e) {
                    // Keep newsletter-level data
                }
            }

            if ($this->tableExists('newsletter_clicks')) {
                try {
                    $cRow = Database::query(
                        "SELECT COUNT(*) as total, COUNT(DISTINCT email) as uniq
                         FROM newsletter_clicks
                         WHERE newsletter_id = ? AND tenant_id = ?",
                        [$id, $tenantId]
                    )->fetch();
                    if ($cRow) {
                        $totalClicks = max($totalClicks, (int) $cRow['total']);
                        $uniqueClicks = max($uniqueClicks, (int) $cRow['uniq']);
                    }
                } catch (\Exception $e) {
                    // Keep newsletter-level data
                }
            }

            $totalForRate = $delivery['delivered'] > 0 ? $delivery['delivered'] : $delivery['total_sent'];
            $totalAll = $delivery['total_sent'] + $delivery['failed'];
            $engagement = [
                'unique_opens' => $uniqueOpens,
                'total_opens' => $totalOpens,
                'unique_clicks' => $uniqueClicks,
                'total_clicks' => $totalClicks,
                'open_rate' => $totalForRate > 0 ? round(($uniqueOpens / $totalForRate) * 100, 1) : 0,
                'click_rate' => $totalForRate > 0 ? round(($uniqueClicks / $totalForRate) * 100, 1) : 0,
                'click_to_open_rate' => $uniqueOpens > 0 ? round(($uniqueClicks / $uniqueOpens) * 100, 1) : 0,
                'success_rate' => $totalAll > 0 ? round(($delivery['delivered'] / $totalAll) * 100, 1) : 0,
            ];

            // ── A/B test data ──
            $abTest = null;
            if (!empty($newsletter['ab_test_enabled']) && !empty($newsletter['subject_b'])) {
                $abTest = [
                    'subject_a' => $newsletter['subject'] ?? '',
                    'subject_b' => $newsletter['subject_b'] ?? '',
                    'subject_a_opens' => 0,
                    'subject_a_clicks' => 0,
                    'subject_b_opens' => 0,
                    'subject_b_clicks' => 0,
                    'winner' => $newsletter['ab_winner'] ?? null,
                    'winning_margin' => 0,
                ];

                if ($this->tableExists('newsletter_queue') && $this->tableExists('newsletter_opens')) {
                    try {
                        $aOpens = Database::query(
                            "SELECT COUNT(DISTINCT o.email) as cnt
                             FROM newsletter_opens o
                             JOIN newsletter_queue q ON o.email = q.email AND o.newsletter_id = q.newsletter_id
                             WHERE o.newsletter_id = ? AND o.tenant_id = ?
                             AND (q.subject_variant = 'a' OR q.subject_variant IS NULL)",
                            [$id, $tenantId]
                        )->fetch();
                        $abTest['subject_a_opens'] = (int) ($aOpens['cnt'] ?? 0);

                        $bOpens = Database::query(
                            "SELECT COUNT(DISTINCT o.email) as cnt
                             FROM newsletter_opens o
                             JOIN newsletter_queue q ON o.email = q.email AND o.newsletter_id = q.newsletter_id
                             WHERE o.newsletter_id = ? AND o.tenant_id = ?
                             AND q.subject_variant = 'b'",
                            [$id, $tenantId]
                        )->fetch();
                        $abTest['subject_b_opens'] = (int) ($bOpens['cnt'] ?? 0);
                    } catch (\Exception $e) {
                        $abTest['subject_a_opens'] = (int) ceil($uniqueOpens / 2);
                        $abTest['subject_b_opens'] = (int) floor($uniqueOpens / 2);
                    }
                }

                if ($this->tableExists('newsletter_queue') && $this->tableExists('newsletter_clicks')) {
                    try {
                        $aClicks = Database::query(
                            "SELECT COUNT(DISTINCT c.email) as cnt
                             FROM newsletter_clicks c
                             JOIN newsletter_queue q ON c.email = q.email AND c.newsletter_id = q.newsletter_id
                             WHERE c.newsletter_id = ? AND c.tenant_id = ?
                             AND (q.subject_variant = 'a' OR q.subject_variant IS NULL)",
                            [$id, $tenantId]
                        )->fetch();
                        $abTest['subject_a_clicks'] = (int) ($aClicks['cnt'] ?? 0);

                        $bClicks = Database::query(
                            "SELECT COUNT(DISTINCT c.email) as cnt
                             FROM newsletter_clicks c
                             JOIN newsletter_queue q ON c.email = q.email AND c.newsletter_id = q.newsletter_id
                             WHERE c.newsletter_id = ? AND c.tenant_id = ?
                             AND q.subject_variant = 'b'",
                            [$id, $tenantId]
                        )->fetch();
                        $abTest['subject_b_clicks'] = (int) ($bClicks['cnt'] ?? 0);
                    } catch (\Exception $e) {
                        $abTest['subject_a_clicks'] = (int) ceil($uniqueClicks / 2);
                        $abTest['subject_b_clicks'] = (int) floor($uniqueClicks / 2);
                    }
                }

                // Calculate winning margin
                $aRate = $abTest['subject_a_opens'];
                $bRate = $abTest['subject_b_opens'];
                if ($aRate > 0 || $bRate > 0) {
                    $maxRate = max($aRate, $bRate);
                    $minRate = min($aRate, $bRate);
                    $abTest['winning_margin'] = $minRate > 0
                        ? round((($maxRate - $minRate) / $minRate) * 100, 1)
                        : 100;
                }
            }

            // ── Timeline data (opens/clicks grouped by hour, first 48h) ──
            $timeline = [];
            $sentAt = $newsletter['sent_at'] ?? $newsletter['created_at'];
            if ($sentAt && $this->tableExists('newsletter_opens')) {
                try {
                    $tStmt = Database::query(
                        "SELECT TIMESTAMPDIFF(HOUR, ?, opened_at) as hour_offset, COUNT(*) as opens
                         FROM newsletter_opens
                         WHERE newsletter_id = ? AND tenant_id = ?
                           AND opened_at >= ? AND opened_at <= DATE_ADD(?, INTERVAL 48 HOUR)
                         GROUP BY hour_offset
                         ORDER BY hour_offset",
                        [$sentAt, $id, $tenantId, $sentAt, $sentAt]
                    );
                    $opensMap = [];
                    foreach ($tStmt->fetchAll() ?: [] as $row) {
                        $h = (int) $row['hour_offset'];
                        if ($h >= 0 && $h <= 48) {
                            $opensMap[$h] = (int) $row['opens'];
                        }
                    }

                    $clicksMap = [];
                    if ($this->tableExists('newsletter_clicks')) {
                        try {
                            $tcStmt = Database::query(
                                "SELECT TIMESTAMPDIFF(HOUR, ?, clicked_at) as hour_offset, COUNT(*) as clicks
                                 FROM newsletter_clicks
                                 WHERE newsletter_id = ? AND tenant_id = ?
                                   AND clicked_at >= ? AND clicked_at <= DATE_ADD(?, INTERVAL 48 HOUR)
                                 GROUP BY hour_offset
                                 ORDER BY hour_offset",
                                [$sentAt, $id, $tenantId, $sentAt, $sentAt]
                            );
                            foreach ($tcStmt->fetchAll() ?: [] as $row) {
                                $h = (int) $row['hour_offset'];
                                if ($h >= 0 && $h <= 48) {
                                    $clicksMap[$h] = (int) $row['clicks'];
                                }
                            }
                        } catch (\Exception $e) {
                            // No click timeline data
                        }
                    }

                    for ($h = 0; $h <= 48; $h++) {
                        if (isset($opensMap[$h]) || isset($clicksMap[$h])) {
                            $timeline[] = [
                                'hour' => $h,
                                'opens' => $opensMap[$h] ?? 0,
                                'clicks' => $clicksMap[$h] ?? 0,
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    // No timeline data
                }
            }

            // ── Top links clicked ──
            $topLinks = [];
            if ($this->tableExists('newsletter_clicks')) {
                try {
                    $lStmt = Database::query(
                        "SELECT url, COUNT(*) as clicks, COUNT(DISTINCT email) as unique_clicks
                         FROM newsletter_clicks
                         WHERE newsletter_id = ? AND tenant_id = ?
                         GROUP BY url
                         ORDER BY clicks DESC
                         LIMIT 10",
                        [$id, $tenantId]
                    );
                    $topLinks = array_map(function ($link) {
                        return [
                            'url' => $link['url'] ?? '',
                            'clicks' => (int) ($link['clicks'] ?? 0),
                            'unique_clicks' => (int) ($link['unique_clicks'] ?? 0),
                        ];
                    }, $lStmt->fetchAll() ?: []);
                } catch (\Exception $e) {
                    // No link data
                }
            }

            $this->respondWithData([
                'newsletter' => [
                    'id' => (int) $newsletter['id'],
                    'name' => $newsletter['name'] ?? '',
                    'subject' => $newsletter['subject'] ?? '',
                    'subject_b' => $newsletter['subject_b'] ?? null,
                    'status' => $newsletter['status'] ?? 'draft',
                    'ab_test_enabled' => !empty($newsletter['ab_test_enabled']),
                    'ab_winner' => $newsletter['ab_winner'] ?? null,
                    'ab_winner_metric' => $newsletter['ab_winner_metric'] ?? 'opens',
                    'created_by' => (int) ($newsletter['created_by'] ?? 0),
                    'author_name' => $authorName ?: null,
                    'sent_at' => $newsletter['sent_at'] ?? null,
                    'created_at' => $newsletter['created_at'] ?? null,
                ],
                'delivery' => $delivery,
                'engagement' => $engagement,
                'ab_test' => $abTest,
                'timeline' => $timeline,
                'top_links' => $topLinks,
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('FETCH_FAILED', 'Failed to fetch newsletter stats');
        }
    }

    public function selectAbWinner(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $winner = $this->input('winner');
        if (!in_array($winner, ['a', 'b'], true)) {
            $this->respondWithError('VALIDATION_ERROR', 'Winner must be "a" or "b"', 'winner');
            return;
        }

        if (!$this->tableExists('newsletters')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter functionality is not yet configured', null, 503);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT id, ab_test_enabled FROM newsletters WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
            $newsletter = $stmt->fetch();

            if (!$newsletter) {
                $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
                return;
            }

            if (empty($newsletter['ab_test_enabled'])) {
                $this->respondWithError('VALIDATION_ERROR', 'Newsletter does not have A/B testing enabled');
                return;
            }

            Database::query(
                "UPDATE newsletters SET ab_winner = ? WHERE id = ? AND tenant_id = ?",
                [$winner, $id, $tenantId]
            );

            $this->respondWithData(['success' => true, 'winner' => $winner]);
        } catch (\Exception $e) {
            $this->respondWithError('UPDATE_FAILED', 'Failed to select A/B test winner');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Bounce Management
    // ─────────────────────────────────────────────────────────────────────────────

    public function getBounces(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $limit = $this->queryInt('limit', 50, 1, 500);
        $offset = $this->queryInt('offset', 0, 0);
        $type = $this->query('type');
        $startDate = $this->query('startDate');
        $endDate = $this->query('endDate');

        if (!$this->tableExists('newsletter_bounces')) {
            $this->respondWithData([]);
            return;
        }

        try {
            $where = 'WHERE b.tenant_id = ?';
            $params = [$tenantId];

            if ($type) {
                $where .= ' AND b.bounce_type = ?';
                $params[] = $type;
            }

            if ($startDate) {
                $where .= ' AND b.bounced_at >= ?';
                $params[] = $startDate;
            }

            if ($endDate) {
                $where .= ' AND b.bounced_at <= ?';
                $params[] = $endDate;
            }

            $stmt = Database::query(
                "SELECT b.*, n.subject as newsletter_subject
                 FROM newsletter_bounces b
                 LEFT JOIN newsletters n ON b.newsletter_id = n.id
                 {$where}
                 ORDER BY b.bounced_at DESC
                 LIMIT {$limit} OFFSET {$offset}",
                $params
            );

            $bounces = $stmt->fetchAll() ?: [];
            $this->respondWithData($bounces);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    public function getSuppressionList(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_suppression_list')) {
            $this->respondWithData([]);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT email, reason, suppressed_at, bounce_count
                 FROM newsletter_suppression_list
                 WHERE tenant_id = ? AND (expires_at IS NULL OR expires_at > NOW())
                 ORDER BY suppressed_at DESC",
                [$tenantId]
            );

            $list = $stmt->fetchAll() ?: [];
            $this->respondWithData($list);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    public function unsuppress(string $email): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_suppression_list')) {
            $this->respondWithError('TABLE_MISSING', 'Suppression list not available', null, 503);
            return;
        }

        try {
            Database::query(
                "DELETE FROM newsletter_suppression_list WHERE tenant_id = ? AND email = ?",
                [$tenantId, $email]
            );
            $this->respondWithData(['success' => true, 'email' => $email]);
        } catch (\Exception $e) {
            $this->respondWithError('UNSUPPRESS_FAILED', 'Failed to remove email from suppression list');
        }
    }

    public function suppress(string $email): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_suppression_list')) {
            $this->respondWithError('TABLE_MISSING', 'Suppression list not available', null, 503);
            return;
        }

        try {
            Database::query(
                "INSERT INTO newsletter_suppression_list (tenant_id, email, reason, bounce_count)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE suppressed_at = NOW()",
                [$tenantId, $email, 'manual', 0]
            );
            $this->respondWithData(['success' => true, 'email' => $email]);
        } catch (\Exception $e) {
            $this->respondWithError('SUPPRESS_FAILED', 'Failed to add email to suppression list');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Resend Workflow
    // ─────────────────────────────────────────────────────────────────────────────

    public function getResendInfo(int $newsletterId): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletters') || !$this->tableExists('newsletter_queue')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter functionality not available', null, 503);
            return;
        }

        try {
            // Get newsletter stats
            $newsletter = Database::query(
                "SELECT id, total_sent, unique_opens, unique_clicks FROM newsletters WHERE id = ? AND tenant_id = ?",
                [$newsletterId, $tenantId]
            )->fetch();

            if (!$newsletter) {
                $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
                return;
            }

            // Count non-openers
            $nonOpeners = Database::query(
                "SELECT COUNT(*) as cnt
                 FROM newsletter_queue q
                 WHERE q.newsletter_id = ? AND q.status = 'sent'
                 AND q.email NOT IN (
                     SELECT DISTINCT email FROM newsletter_opens
                     WHERE newsletter_id = ? AND tenant_id = ?
                 )",
                [$newsletterId, $newsletterId, $tenantId]
            )->fetch()['cnt'] ?? 0;

            // Count non-clickers (opened but didn't click)
            $nonClickers = Database::query(
                "SELECT COUNT(DISTINCT o.email) as cnt
                 FROM newsletter_opens o
                 WHERE o.newsletter_id = ? AND o.tenant_id = ?
                 AND o.email NOT IN (
                     SELECT DISTINCT email FROM newsletter_clicks
                     WHERE newsletter_id = ? AND tenant_id = ?
                 )",
                [$newsletterId, $tenantId, $newsletterId, $tenantId]
            )->fetch()['cnt'] ?? 0;

            $this->respondWithData([
                'newsletter_id' => (int)$newsletter['id'],
                'total_sent' => (int)($newsletter['total_sent'] ?? 0),
                'total_opened' => (int)($newsletter['unique_opens'] ?? 0),
                'total_clicked' => (int)($newsletter['unique_clicks'] ?? 0),
                'non_openers_count' => (int)$nonOpeners,
                'non_clickers_count' => (int)$nonClickers,
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('FETCH_FAILED', 'Failed to fetch resend info');
        }
    }

    public function resend(int $newsletterId): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $target = $this->input('target');
        $segmentId = $this->inputInt('segment_id');
        $subjectOverride = $this->input('subject_override');

        if (!in_array($target, ['non_openers', 'non_clickers', 'segment'])) {
            $this->respondWithError('VALIDATION_ERROR', 'Invalid target', 'target');
            return;
        }

        if (!$this->tableExists('newsletters') || !$this->tableExists('newsletter_queue')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter functionality not available', null, 503);
            return;
        }

        try {
            // Get newsletter
            $newsletter = Database::query(
                "SELECT * FROM newsletters WHERE id = ? AND tenant_id = ?",
                [$newsletterId, $tenantId]
            )->fetch();

            if (!$newsletter) {
                $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
                return;
            }

            // Get recipient list based on target
            $recipients = [];
            if ($target === 'non_openers') {
                $stmt = Database::query(
                    "SELECT q.email
                     FROM newsletter_queue q
                     WHERE q.newsletter_id = ? AND q.status = 'sent'
                     AND q.email NOT IN (
                         SELECT DISTINCT email FROM newsletter_opens
                         WHERE newsletter_id = ? AND tenant_id = ?
                     )",
                    [$newsletterId, $newsletterId, $tenantId]
                );
                $recipients = array_column($stmt->fetchAll(), 'email');
            } elseif ($target === 'non_clickers') {
                $stmt = Database::query(
                    "SELECT DISTINCT o.email
                     FROM newsletter_opens o
                     WHERE o.newsletter_id = ? AND o.tenant_id = ?
                     AND o.email NOT IN (
                         SELECT DISTINCT email FROM newsletter_clicks
                         WHERE newsletter_id = ? AND tenant_id = ?
                     )",
                    [$newsletterId, $tenantId, $newsletterId, $tenantId]
                );
                $recipients = array_column($stmt->fetchAll(), 'email');
            } elseif ($target === 'segment' && $segmentId) {
                // TODO: Implement segment-based resend
                $recipients = [];
            }

            if (empty($recipients)) {
                $this->respondWithError('NO_RECIPIENTS', 'No recipients found for resend target');
                return;
            }

            // Queue the resend
            $subject = $subjectOverride ?: $newsletter['subject'];
            $queuedCount = 0;

            foreach ($recipients as $email) {
                $trackingToken = bin2hex(random_bytes(16));
                Database::query(
                    "INSERT INTO newsletter_queue (newsletter_id, email, status, tracking_token, created_at)
                     VALUES (?, ?, 'pending', ?, NOW())",
                    [$newsletterId, $email, $trackingToken]
                );
                $queuedCount++;
            }

            $this->respondWithData([
                'success' => true,
                'queued_count' => $queuedCount,
                'subject' => $subject,
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('RESEND_FAILED', 'Failed to queue resend: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Send-Time Optimizer
    // ─────────────────────────────────────────────────────────────────────────────

    public function getSendTimeData(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $days = $this->queryInt('days', 30, 7, 365);

        if (!$this->tableExists('newsletter_opens')) {
            $this->respondWithData([
                'heatmap' => [],
                'recommendations' => [],
                'insights' => 'Not enough data available yet.',
            ]);
            return;
        }

        try {
            // Get heatmap data (day of week × hour)
            $stmt = Database::query(
                "SELECT DAYOFWEEK(opened_at) as day_of_week, HOUR(opened_at) as hour,
                        COUNT(*) as opens, COUNT(DISTINCT email) as unique_opens
                 FROM newsletter_opens
                 WHERE tenant_id = ? AND opened_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY DAYOFWEEK(opened_at), HOUR(opened_at)
                 ORDER BY day_of_week, hour",
                [$tenantId, $days]
            );

            $heatmapRaw = $stmt->fetchAll() ?: [];
            $heatmap = [];

            foreach ($heatmapRaw as $row) {
                $heatmap[] = [
                    'day_of_week' => (int)$row['day_of_week'],
                    'hour' => (int)$row['hour'],
                    'engagement_score' => (int)$row['opens'],
                    'opens' => (int)$row['opens'],
                    'clicks' => 0, // TODO: Join with clicks if needed
                ];
            }

            // Generate recommendations (top 3 times)
            $scores = [];
            foreach ($heatmap as $cell) {
                $key = "{$cell['day_of_week']}_{$cell['hour']}";
                $scores[$key] = $cell['engagement_score'];
            }
            arsort($scores);
            $topTimes = array_slice($scores, 0, 3, true);

            $recommendations = [];
            $dayNames = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            foreach ($topTimes as $key => $score) {
                [$day, $hour] = explode('_', $key);
                $recommendations[] = [
                    'day_of_week' => (int)$day,
                    'hour' => (int)$hour,
                    'score' => $score,
                    'description' => $dayNames[(int)$day] . ' at ' . date('g:i A', strtotime("{$hour}:00")),
                ];
            }

            $insights = count($heatmap) > 0
                ? "Based on {$days} days of engagement data, these are your community's most active times."
                : "Not enough data available yet. Send a few newsletters to see engagement patterns.";

            $this->respondWithData([
                'heatmap' => $heatmap,
                'recommendations' => $recommendations,
                'insights' => $insights,
            ]);
        } catch (\Exception $e) {
            $this->respondWithData([
                'heatmap' => [],
                'recommendations' => [],
                'insights' => 'Error loading send-time data.',
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Diagnostics
    // ─────────────────────────────────────────────────────────────────────────────

    public function getDiagnostics(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $diagnostics = [
            'queue_status' => [
                'total' => 0,
                'pending' => 0,
                'sending' => 0,
                'sent' => 0,
                'failed' => 0,
            ],
            'bounce_rate' => 0,
            'sender_score' => 100,
            'configuration' => [
                'smtp_configured' => !empty(getenv('SMTP_HOST')),
                'api_configured' => !empty(getenv('USE_GMAIL_API')) && getenv('USE_GMAIL_API') !== 'false',
                'tracking_enabled' => true,
            ],
            'health_status' => 'healthy',
        ];

        // Queue status
        if ($this->tableExists('newsletter_queue')) {
            try {
                $queueStats = Database::query(
                    "SELECT status, COUNT(*) as cnt FROM newsletter_queue WHERE tenant_id = ? GROUP BY status",
                    [$tenantId]
                )->fetchAll();

                foreach ($queueStats as $row) {
                    $status = $row['status'] ?? 'unknown';
                    $count = (int)$row['cnt'];
                    $diagnostics['queue_status']['total'] += $count;
                    if (isset($diagnostics['queue_status'][$status])) {
                        $diagnostics['queue_status'][$status] = $count;
                    }
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Bounce rate
        if ($this->tableExists('newsletter_bounces') && $this->tableExists('newsletter_queue')) {
            try {
                $totalSent = Database::query(
                    "SELECT COUNT(*) as cnt FROM newsletter_queue WHERE tenant_id = ? AND status = 'sent'",
                    [$tenantId]
                )->fetch()['cnt'] ?? 0;

                $totalBounces = Database::query(
                    "SELECT COUNT(*) as cnt FROM newsletter_bounces WHERE tenant_id = ?",
                    [$tenantId]
                )->fetch()['cnt'] ?? 0;

                if ($totalSent > 0) {
                    $diagnostics['bounce_rate'] = round(($totalBounces / $totalSent) * 100, 2);
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Determine health status
        if ($diagnostics['bounce_rate'] > 10) {
            $diagnostics['health_status'] = 'critical';
        } elseif ($diagnostics['bounce_rate'] > 5 || $diagnostics['queue_status']['failed'] > 10) {
            $diagnostics['health_status'] = 'warning';
        }

        $this->respondWithData($diagnostics);
    }
}
