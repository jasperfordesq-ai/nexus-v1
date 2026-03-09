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

        $subject = $this->input('subject', '');
        $name = $this->input('name') ?: $subject; // Fall back to subject if name not provided
        $previewText = $this->input('preview_text', '');
        $content = $this->input('content', '');
        $status = $this->input('status', 'draft');
        $targetAudience = $this->input('target_audience', 'all_members');
        $segmentId = $this->inputInt('segment_id') ?: null;
        $scheduledAt = $this->input('scheduled_at') ?: null;
        $abTestEnabled = $this->input('ab_test_enabled') ? 1 : 0;
        $subjectB = $this->input('subject_b') ?: null;
        $templateId = $this->inputInt('template_id') ?: null;
        $abSplitPercentage = $this->inputInt('ab_split_percentage') ?: 50;
        $abWinnerMetric = $this->input('ab_winner_metric', 'opens');
        $abAutoSelectWinner = $this->input('ab_auto_select_winner') ? 1 : 0;
        $abAutoSelectAfterHours = $this->inputInt('ab_auto_select_after_hours') ?: null;
        $targetCounties = $this->input('target_counties') ?: null;
        $targetTowns = $this->input('target_towns') ?: null;
        $targetGroups = $this->input('target_groups') ?: null;
        $isRecurring = $this->input('is_recurring') ? 1 : 0;
        $recurringFrequency = $this->input('recurring_frequency') ?: null;
        $recurringDay = $this->inputInt('recurring_day') ?: null;
        $recurringDayOfMonth = $this->inputInt('recurring_day_of_month') ?: null;
        $recurringTime = $this->input('recurring_time') ?: null;
        $recurringEndDate = $this->input('recurring_end_date') ?: null;

        if (!$name && !$subject) {
            $this->respondWithError('VALIDATION_ERROR', 'Subject is required', 'subject');
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
                    ab_split_percentage, ab_winner_metric, ab_auto_select_winner, ab_auto_select_after_hours,
                    target_counties, target_towns, target_groups,
                    is_recurring, recurring_frequency, recurring_day, recurring_day_of_month,
                    recurring_time, recurring_end_date,
                    created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$tenantId, $name, $subject, $previewText, $content, $status,
                 $targetAudience, $segmentId, $scheduledAt, $abTestEnabled, $subjectB, $templateId,
                 $abSplitPercentage, $abWinnerMetric, $abAutoSelectWinner, $abAutoSelectAfterHours,
                 $targetCounties, $targetTowns, $targetGroups,
                 $isRecurring, $recurringFrequency, $recurringDay, $recurringDayOfMonth,
                 $recurringTime, $recurringEndDate,
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
                'target_audience', 'scheduled_at', 'subject_b',
                'target_counties', 'target_towns', 'target_groups',
                'ab_winner_metric',
                'recurring_frequency', 'recurring_time', 'recurring_end_date',
            ];

            foreach ($allowedFields as $field) {
                $val = $this->input($field);
                if ($val !== null) {
                    $fields[] = "{$field} = ?";
                    $params[] = $val;
                }
            }

            // Handle nullable int fields
            foreach (['segment_id', 'template_id', 'ab_split_percentage', 'recurring_day', 'recurring_day_of_month', 'ab_auto_select_after_hours'] as $intField) {
                $val = $this->input($intField);
                if ($val !== null) {
                    $fields[] = "{$intField} = ?";
                    $params[] = $val ? (int)$val : null;
                }
            }

            // Handle boolean fields
            foreach (['ab_test_enabled', 'is_recurring', 'ab_auto_select_winner'] as $boolField) {
                $val = $this->input($boolField);
                if ($val !== null) {
                    $fields[] = "{$boolField} = ?";
                    $params[] = $val ? 1 : 0;
                }
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
            'totals' => [
                'newsletters_sent' => 0,
                'total_sent' => 0,
                'total_failed' => 0,
                'total_opens' => 0,
                'unique_opens' => 0,
                'total_clicks' => 0,
                'unique_clicks' => 0,
            ],
            'monthly_breakdown' => [],
            'top_performers' => [],
        ];

        if (!$this->tableExists('newsletters')) {
            $this->respondWithData($data);
            return;
        }

        try {
            // Summary counts
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

            // Full analytics from sent newsletters (mirrors legacy PHP admin)
            $newsletters = \Nexus\Models\Newsletter::getAllSent();

            $totals = &$data['totals'];
            $monthlyStats = [];
            $topPerformers = [];

            foreach ($newsletters as $newsletter) {
                $totals['newsletters_sent']++;
                $totals['total_sent'] += (int) ($newsletter['total_sent'] ?? 0);
                $totals['total_failed'] += (int) ($newsletter['total_failed'] ?? 0);
                $totals['total_opens'] += (int) ($newsletter['total_opens'] ?? 0);
                $totals['unique_opens'] += (int) ($newsletter['unique_opens'] ?? 0);
                $totals['total_clicks'] += (int) ($newsletter['total_clicks'] ?? 0);
                $totals['unique_clicks'] += (int) ($newsletter['unique_clicks'] ?? 0);

                // Group by month
                $sentAt = $newsletter['sent_at'] ?? null;
                if ($sentAt) {
                    $month = date('Y-m', strtotime($sentAt));
                    if (!isset($monthlyStats[$month])) {
                        $monthlyStats[$month] = [
                            'month' => $month,
                            'newsletters' => 0,
                            'sent' => 0,
                            'opens' => 0,
                            'clicks' => 0,
                        ];
                    }
                    $monthlyStats[$month]['newsletters']++;
                    $monthlyStats[$month]['sent'] += (int) ($newsletter['total_sent'] ?? 0);
                    $monthlyStats[$month]['opens'] += (int) ($newsletter['unique_opens'] ?? 0);
                    $monthlyStats[$month]['clicks'] += (int) ($newsletter['unique_clicks'] ?? 0);
                }

                // Top performers (min 10 recipients)
                $totalSent = (int) ($newsletter['total_sent'] ?? 0);
                if ($totalSent >= 10) {
                    $openRate = round(((int) ($newsletter['unique_opens'] ?? 0) / $totalSent) * 100, 1);
                    $clickRate = round(((int) ($newsletter['unique_clicks'] ?? 0) / $totalSent) * 100, 1);
                    $topPerformers[] = [
                        'id' => (int) $newsletter['id'],
                        'subject' => $newsletter['subject'] ?? '',
                        'sent_at' => $newsletter['sent_at'] ?? '',
                        'total_sent' => $totalSent,
                        'open_rate' => $openRate,
                        'click_rate' => $clickRate,
                    ];
                }
            }

            // Sort monthly by month ascending
            ksort($monthlyStats);
            $data['monthly_breakdown'] = array_values($monthlyStats);

            // Sort top performers by open rate descending, take top 10
            usort($topPerformers, function ($a, $b) {
                return $b['open_rate'] <=> $a['open_rate'];
            });
            $data['top_performers'] = array_slice($topPerformers, 0, 10);
        } catch (\Exception $e) {
            // Return defaults on error
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

            // ── Device breakdown (from user agents) ──
            $deviceStats = ['desktop' => 0, 'mobile' => 0, 'tablet' => 0, 'unknown' => 0];
            if ($this->tableExists('newsletter_opens')) {
                try {
                    $uaRows = Database::query(
                        "SELECT user_agent FROM newsletter_opens
                         WHERE tenant_id = ? AND newsletter_id = ? AND user_agent IS NOT NULL",
                        [$tenantId, $id]
                    )->fetchAll() ?: [];
                    foreach ($uaRows as $uaRow) {
                        $ua = strtolower($uaRow['user_agent'] ?? '');
                        if (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false) {
                            $deviceStats['mobile']++;
                        } elseif (strpos($ua, 'tablet') !== false || strpos($ua, 'ipad') !== false) {
                            $deviceStats['tablet']++;
                        } elseif (strpos($ua, 'windows') !== false || strpos($ua, 'macintosh') !== false || strpos($ua, 'linux') !== false) {
                            $deviceStats['desktop']++;
                        } else {
                            $deviceStats['unknown']++;
                        }
                    }
                } catch (\Exception $e) {
                    // Keep defaults
                }
            }

            // ── Recent activity (last 20 opens+clicks) ──
            $recentActivity = [];
            try {
                $activityParts = [];
                if ($this->tableExists('newsletter_opens')) {
                    $activityParts[] = "(SELECT 'open' as action_type, email, opened_at as action_at, NULL as url
                                         FROM newsletter_opens WHERE tenant_id = ? AND newsletter_id = ?)";
                }
                if ($this->tableExists('newsletter_clicks')) {
                    $activityParts[] = "(SELECT 'click' as action_type, email, clicked_at as action_at, url
                                         FROM newsletter_clicks WHERE tenant_id = ? AND newsletter_id = ?)";
                }
                if (!empty($activityParts)) {
                    $params = [];
                    foreach ($activityParts as $p) {
                        $params[] = $tenantId;
                        $params[] = $id;
                    }
                    $activitySql = implode(' UNION ALL ', $activityParts) . ' ORDER BY action_at DESC LIMIT 20';
                    $recentActivity = Database::query($activitySql, $params)->fetchAll() ?: [];
                    $recentActivity = array_map(function ($row) {
                        return [
                            'action_type' => $row['action_type'] ?? 'open',
                            'email' => $row['email'] ?? '',
                            'action_at' => $row['action_at'] ?? '',
                            'url' => $row['url'] ?? null,
                        ];
                    }, $recentActivity);
                }
            } catch (\Exception $e) {
                // No activity data
            }

            // ── Peak engagement (max opens in one hour) ──
            $peakOpens = 0;
            $peakHour = null;
            if (!empty($timeline)) {
                foreach ($timeline as $point) {
                    if ($point['opens'] > $peakOpens) {
                        $peakOpens = $point['opens'];
                        $peakHour = $point['hour'];
                    }
                }
            }

            // ── Add A/B rates + split info ──
            if ($abTest !== null) {
                // Count sent per variant
                $aSent = 0;
                $bSent = 0;
                if ($this->tableExists('newsletter_queue')) {
                    try {
                        $variantCounts = Database::query(
                            "SELECT subject_variant, COUNT(*) as cnt
                             FROM newsletter_queue
                             WHERE newsletter_id = ? AND tenant_id = ? AND status IN ('sent', 'failed')
                             GROUP BY subject_variant",
                            [$id, $tenantId]
                        )->fetchAll() ?: [];
                        foreach ($variantCounts as $vc) {
                            $v = $vc['subject_variant'] ?? 'a';
                            $c = (int) ($vc['cnt'] ?? 0);
                            if ($v === 'b') {
                                $bSent = $c;
                            } else {
                                $aSent += $c;
                            }
                        }
                    } catch (\Exception $e) {
                        // Fallback: split total evenly
                        $splitPct = (int) ($newsletter['ab_split_percentage'] ?? 50);
                        $aSent = (int) round($delivery['total_sent'] * $splitPct / 100);
                        $bSent = $delivery['total_sent'] - $aSent;
                    }
                }
                $abTest['subject_a_sent'] = $aSent;
                $abTest['subject_b_sent'] = $bSent;
                $abTest['subject_a_open_rate'] = $aSent > 0 ? round(($abTest['subject_a_opens'] / $aSent) * 100, 1) : 0;
                $abTest['subject_b_open_rate'] = $bSent > 0 ? round(($abTest['subject_b_opens'] / $bSent) * 100, 1) : 0;
                $abTest['subject_a_click_rate'] = $aSent > 0 ? round(($abTest['subject_a_clicks'] / $aSent) * 100, 1) : 0;
                $abTest['subject_b_click_rate'] = $bSent > 0 ? round(($abTest['subject_b_clicks'] / $bSent) * 100, 1) : 0;
                $abTest['split_percentage'] = (int) ($newsletter['ab_split_percentage'] ?? 50);
                $abTest['winner_metric'] = $newsletter['ab_winner_metric'] ?? 'opens';
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
                'device_stats' => $deviceStats,
                'recent_activity' => $recentActivity,
                'peak_engagement' => [
                    'max_opens_per_hour' => $peakOpens,
                    'peak_hour' => $peakHour,
                ],
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
                 LIMIT ? OFFSET ?",
                array_merge($params, [$limit, $offset])
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
                 INNER JOIN newsletters n ON n.id = q.newsletter_id AND n.tenant_id = ?
                 WHERE q.newsletter_id = ? AND q.status = 'sent'
                 AND q.email NOT IN (
                     SELECT DISTINCT email FROM newsletter_opens
                     WHERE newsletter_id = ?
                 )",
                [$tenantId, $newsletterId, $newsletterId]
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
                     INNER JOIN newsletters n ON n.id = q.newsletter_id AND n.tenant_id = ?
                     WHERE q.newsletter_id = ? AND q.status = 'sent'
                     AND q.email NOT IN (
                         SELECT DISTINCT email FROM newsletter_opens
                         WHERE newsletter_id = ?
                     )",
                    [$tenantId, $newsletterId, $newsletterId]
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
                    "INSERT INTO newsletter_queue (tenant_id, newsletter_id, email, status, tracking_token, created_at)
                     VALUES (?, ?, ?, 'pending', ?, NOW())",
                    [$tenantId, $newsletterId, $email, $trackingToken]
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

            // Also get click data for the same period
            $clicksBySlot = [];
            if ($this->tableExists('newsletter_clicks')) {
                try {
                    $clickStmt = Database::query(
                        "SELECT DAYOFWEEK(clicked_at) as day_of_week, HOUR(clicked_at) as hour,
                                COUNT(*) as clicks
                         FROM newsletter_clicks
                         WHERE tenant_id = ? AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                         GROUP BY DAYOFWEEK(clicked_at), HOUR(clicked_at)",
                        [$tenantId, $days]
                    );
                    foreach ($clickStmt->fetchAll() ?: [] as $cRow) {
                        $clicksBySlot[(int)$cRow['day_of_week'] . '_' . (int)$cRow['hour']] = (int)$cRow['clicks'];
                    }
                } catch (\Exception $e) {
                    // No click data
                }
            }

            $heatmap = [];
            foreach ($heatmapRaw as $row) {
                $key = (int)$row['day_of_week'] . '_' . (int)$row['hour'];
                $clicks = $clicksBySlot[$key] ?? 0;
                $heatmap[] = [
                    'day_of_week' => (int)$row['day_of_week'],
                    'hour' => (int)$row['hour'],
                    'engagement_score' => (int)$row['opens'] + ($clicks * 2),
                    'opens' => (int)$row['opens'],
                    'clicks' => $clicks,
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
    // Send / Test / Duplicate / Activity
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Send a newsletter immediately
     */
    public function sendNewsletter(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletters')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter tables not available', null, 503);
            return;
        }

        try {
            $newsletter = Database::query(
                "SELECT id, status, target_audience, segment_id FROM newsletters WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$newsletter) {
                $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
                return;
            }

            if ($newsletter['status'] === 'sent') {
                $this->respondWithError('ALREADY_SENT', 'Newsletter has already been sent');
                return;
            }

            if ($newsletter['status'] === 'sending') {
                $this->respondWithError('ALREADY_SENDING', 'Newsletter is currently being sent');
                return;
            }

            $targetAudience = $newsletter['target_audience'] ?? 'all_members';
            $segmentId = $newsletter['segment_id'] ?? null;

            $queued = \Nexus\Services\NewsletterService::sendNow($id, $targetAudience, $segmentId);

            $this->respondWithData([
                'queued' => $queued,
                'status' => 'sending',
                'message' => "Newsletter queued for {$queued} recipients",
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('SEND_FAILED', $e->getMessage());
        }
    }

    /**
     * Send a test email to the current admin
     */
    public function sendTest(int $id): void
    {
        $userId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletters')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter tables not available', null, 503);
            return;
        }

        try {
            $newsletter = Database::query(
                "SELECT * FROM newsletters WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$newsletter) {
                $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
                return;
            }

            $admin = Database::query(
                "SELECT email, first_name, last_name FROM users WHERE id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            )->fetch();

            if (!$admin || empty($admin['email'])) {
                $this->respondWithError('NO_EMAIL', 'Admin user has no email address');
                return;
            }

            $tenantName = TenantContext::get()['name'] ?? 'Community';

            $sampleRecipient = [
                'email' => $admin['email'],
                'first_name' => $admin['first_name'] ?? 'Admin',
                'last_name' => $admin['last_name'] ?? 'User',
                'name' => trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '')),
            ];

            $html = \Nexus\Services\NewsletterService::renderEmail(
                $newsletter,
                $tenantName,
                'test-unsubscribe-token',
                $sampleRecipient
            );

            $subject = '[TEST] ' . ($newsletter['subject'] ?? 'No Subject');

            $sent = (new \Nexus\Core\Mailer())->send($admin['email'], $subject, $html);

            if ($sent) {
                $this->respondWithData([
                    'sent_to' => $admin['email'],
                    'message' => "Test email sent to {$admin['email']}",
                ]);
            } else {
                $this->respondWithError('SEND_FAILED', 'Failed to send test email. Check email configuration.');
            }
        } catch (\Exception $e) {
            $this->respondWithError('SEND_FAILED', 'Failed to send test email: ' . $e->getMessage());
        }
    }

    /**
     * Get estimated recipient count for targeting preview
     */
    public function recipientCount(): void
    {
        $this->requireAdmin();

        try {
            $targetAudience = $this->input('target_audience', 'all_members');
            $segmentId = $this->inputInt('segment_id');

            if ($segmentId) {
                $count = \Nexus\Services\NewsletterService::getSegmentRecipientCount($segmentId);
            } else {
                $count = \Nexus\Services\NewsletterService::getRecipientCount($targetAudience);
            }

            $this->respondWithData(['count' => $count]);
        } catch (\Exception $e) {
            $this->respondWithData(['count' => 0]);
        }
    }

    /**
     * Duplicate a newsletter as a new draft
     */
    public function duplicateNewsletter(int $id): void
    {
        $userId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletters')) {
            $this->respondWithError('TABLE_MISSING', 'Newsletter tables not available', null, 503);
            return;
        }

        try {
            $newsletter = Database::query(
                "SELECT name, subject, preview_text, content, target_audience, segment_id,
                        ab_test_enabled, subject_b, ab_split_percentage, ab_winner_metric,
                        ab_auto_select_winner, ab_auto_select_after_hours,
                        target_counties, target_towns, target_groups, template_id,
                        is_recurring, recurring_frequency, recurring_day, recurring_day_of_month,
                        recurring_time, recurring_end_date
                 FROM newsletters WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$newsletter) {
                $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
                return;
            }

            $newSubject = ($newsletter['subject'] ?? 'Newsletter') . ' (Copy)';
            $newName = ($newsletter['name'] ?? $newSubject) . ' (Copy)';

            Database::query(
                "INSERT INTO newsletters (tenant_id, name, subject, preview_text, content, status,
                    target_audience, segment_id, ab_test_enabled, subject_b, ab_split_percentage,
                    ab_winner_metric, ab_auto_select_winner, ab_auto_select_after_hours,
                    target_counties, target_towns, target_groups, template_id,
                    is_recurring, recurring_frequency, recurring_day, recurring_day_of_month,
                    recurring_time, recurring_end_date, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $tenantId, $newName, $newSubject, $newsletter['preview_text'], $newsletter['content'],
                    $newsletter['target_audience'], $newsletter['segment_id'],
                    $newsletter['ab_test_enabled'], $newsletter['subject_b'],
                    $newsletter['ab_split_percentage'], $newsletter['ab_winner_metric'],
                    $newsletter['ab_auto_select_winner'], $newsletter['ab_auto_select_after_hours'],
                    $newsletter['target_counties'], $newsletter['target_towns'],
                    $newsletter['target_groups'], $newsletter['template_id'],
                    $newsletter['is_recurring'], $newsletter['recurring_frequency'],
                    $newsletter['recurring_day'], $newsletter['recurring_day_of_month'],
                    $newsletter['recurring_time'], $newsletter['recurring_end_date'],
                    $userId,
                ]
            );

            $newId = Database::lastInsertId();

            $this->respondWithData([
                'id' => (int) $newId,
                'message' => 'Newsletter duplicated successfully',
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('DUPLICATE_FAILED', 'Failed to duplicate newsletter: ' . $e->getMessage());
        }
    }

    /**
     * Get open/click activity log for a newsletter
     */
    public function activity(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 50, 10, 200);
        $offset = ($page - 1) * $perPage;
        $type = $this->query('type', 'all');

        if ($this->tableExists('newsletters')) {
            $newsletter = Database::query(
                "SELECT id FROM newsletters WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$newsletter) {
                $this->respondWithError('NOT_FOUND', 'Newsletter not found', null, 404);
                return;
            }
        }

        $activities = [];

        try {
            if ($type === 'all' || $type === 'open') {
                if ($this->tableExists('newsletter_opens')) {
                    $opens = Database::query(
                        "SELECT o.id, o.email, 'open' as action_type, o.opened_at as action_at, o.user_agent, o.ip_address, NULL as url
                         FROM newsletter_opens o
                         INNER JOIN newsletters n ON n.id = o.newsletter_id AND n.tenant_id = ?
                         WHERE o.newsletter_id = ?
                         ORDER BY o.opened_at DESC",
                        [$tenantId, $id]
                    )->fetchAll();
                    $activities = array_merge($activities, $opens ?: []);
                }
            }

            if ($type === 'all' || $type === 'click') {
                if ($this->tableExists('newsletter_clicks')) {
                    $clicks = Database::query(
                        "SELECT c.id, c.email, 'click' as action_type, c.clicked_at as action_at, c.user_agent, c.ip_address, c.url
                         FROM newsletter_clicks c
                         INNER JOIN newsletters n ON n.id = c.newsletter_id AND n.tenant_id = ?
                         WHERE c.newsletter_id = ?
                         ORDER BY c.clicked_at DESC",
                        [$tenantId, $id]
                    )->fetchAll();
                    $activities = array_merge($activities, $clicks ?: []);
                }
            }

            usort($activities, function ($a, $b) {
                return strtotime($b['action_at'] ?? '0') - strtotime($a['action_at'] ?? '0');
            });

            $totalCount = count($activities);
            $activities = array_slice($activities, $offset, $perPage);

            // Ensure each activity has a unique id (fallback if table lacks id column)
            foreach ($activities as $i => &$activity) {
                if (empty($activity['id'])) {
                    $activity['id'] = $offset + $i + 1;
                }
            }
            unset($activity);

            $this->respondWithPaginatedCollection($activities, $totalCount, $page, $perPage);
        } catch (\Exception $e) {
            $this->respondWithPaginatedCollection([], 0, $page, $perPage);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Per-Subscriber Engagement Lists
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Get list of subscribers who opened a newsletter (deduplicated by email)
     */
    public function openers(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 50, 10, 200);
        $offset = ($page - 1) * $perPage;

        try {
            $total = 0;
            $items = [];

            if ($this->tableExists('newsletter_opens')) {
                $countRow = Database::query(
                    "SELECT COUNT(DISTINCT email) as cnt FROM newsletter_opens WHERE newsletter_id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
                $total = (int) ($countRow['cnt'] ?? 0);

                $items = Database::query(
                    "SELECT email, MIN(opened_at) as first_opened, COUNT(*) as open_count
                     FROM newsletter_opens
                     WHERE newsletter_id = ? AND tenant_id = ?
                     GROUP BY email
                     ORDER BY first_opened DESC
                     LIMIT ? OFFSET ?",
                    [$id, $tenantId, $perPage, $offset]
                )->fetchAll() ?: [];

                $items = array_map(function ($row) {
                    return [
                        'email' => $row['email'] ?? '',
                        'first_opened' => $row['first_opened'] ?? '',
                        'open_count' => (int) ($row['open_count'] ?? 0),
                    ];
                }, $items);
            }

            $this->respondWithPaginatedCollection($items, $total, $page, $perPage);
        } catch (\Exception $e) {
            $this->respondWithPaginatedCollection([], 0, $page, $perPage);
        }
    }

    /**
     * Get list of subscribers who clicked a link in a newsletter (deduplicated by email)
     */
    public function clickers(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 50, 10, 200);
        $offset = ($page - 1) * $perPage;

        try {
            $total = 0;
            $items = [];

            if ($this->tableExists('newsletter_clicks')) {
                $countRow = Database::query(
                    "SELECT COUNT(DISTINCT email) as cnt FROM newsletter_clicks WHERE newsletter_id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
                $total = (int) ($countRow['cnt'] ?? 0);

                $items = Database::query(
                    "SELECT email, MIN(clicked_at) as first_clicked, COUNT(*) as click_count, COUNT(DISTINCT url) as unique_links
                     FROM newsletter_clicks
                     WHERE newsletter_id = ? AND tenant_id = ?
                     GROUP BY email
                     ORDER BY first_clicked DESC
                     LIMIT ? OFFSET ?",
                    [$id, $tenantId, $perPage, $offset]
                )->fetchAll() ?: [];

                $items = array_map(function ($row) {
                    return [
                        'email' => $row['email'] ?? '',
                        'first_clicked' => $row['first_clicked'] ?? '',
                        'click_count' => (int) ($row['click_count'] ?? 0),
                        'unique_links' => (int) ($row['unique_links'] ?? 0),
                    ];
                }, $items);
            }

            $this->respondWithPaginatedCollection($items, $total, $page, $perPage);
        } catch (\Exception $e) {
            $this->respondWithPaginatedCollection([], 0, $page, $perPage);
        }
    }

    /**
     * Get list of recipients who did NOT open a newsletter
     */
    public function nonOpeners(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 50, 10, 200);
        $offset = ($page - 1) * $perPage;

        try {
            $total = 0;
            $items = [];

            if ($this->tableExists('newsletter_queue') && $this->tableExists('newsletter_opens')) {
                $countRow = Database::query(
                    "SELECT COUNT(*) as cnt
                     FROM newsletter_queue q
                     WHERE q.newsletter_id = ? AND q.tenant_id = ? AND q.status = 'sent'
                     AND q.email NOT IN (
                         SELECT DISTINCT email FROM newsletter_opens
                         WHERE newsletter_id = ? AND tenant_id = ?
                     )",
                    [$id, $tenantId, $id, $tenantId]
                )->fetch();
                $total = (int) ($countRow['cnt'] ?? 0);

                $items = Database::query(
                    "SELECT q.email, q.sent_at, u.first_name, u.last_name
                     FROM newsletter_queue q
                     LEFT JOIN users u ON q.user_id = u.id
                     WHERE q.newsletter_id = ? AND q.tenant_id = ? AND q.status = 'sent'
                     AND q.email NOT IN (
                         SELECT DISTINCT email FROM newsletter_opens
                         WHERE newsletter_id = ? AND tenant_id = ?
                     )
                     ORDER BY q.sent_at DESC
                     LIMIT ? OFFSET ?",
                    [$id, $tenantId, $id, $tenantId, $perPage, $offset]
                )->fetchAll() ?: [];

                $items = array_map(function ($row) {
                    $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                    return [
                        'email' => $row['email'] ?? '',
                        'name' => $name ?: null,
                        'sent_at' => $row['sent_at'] ?? '',
                    ];
                }, $items);
            }

            $this->respondWithPaginatedCollection($items, $total, $page, $perPage);
        } catch (\Exception $e) {
            $this->respondWithPaginatedCollection([], 0, $page, $perPage);
        }
    }

    /**
     * Get list of subscribers who opened but didn't click
     */
    public function openersNoClick(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 50, 10, 200);
        $offset = ($page - 1) * $perPage;

        try {
            $total = 0;
            $items = [];

            if ($this->tableExists('newsletter_opens') && $this->tableExists('newsletter_clicks')) {
                $countRow = Database::query(
                    "SELECT COUNT(DISTINCT o.email) as cnt
                     FROM newsletter_opens o
                     WHERE o.newsletter_id = ? AND o.tenant_id = ?
                     AND o.email NOT IN (
                         SELECT DISTINCT email FROM newsletter_clicks
                         WHERE newsletter_id = ? AND tenant_id = ?
                     )",
                    [$id, $tenantId, $id, $tenantId]
                )->fetch();
                $total = (int) ($countRow['cnt'] ?? 0);

                $items = Database::query(
                    "SELECT o.email, MIN(o.opened_at) as first_opened, COUNT(*) as open_count,
                            u.first_name, u.last_name
                     FROM newsletter_opens o
                     LEFT JOIN newsletter_queue q ON o.email = q.email AND q.newsletter_id = ?
                     LEFT JOIN users u ON q.user_id = u.id
                     WHERE o.newsletter_id = ? AND o.tenant_id = ?
                     AND o.email NOT IN (
                         SELECT DISTINCT email FROM newsletter_clicks
                         WHERE newsletter_id = ? AND tenant_id = ?
                     )
                     GROUP BY o.email, u.first_name, u.last_name
                     ORDER BY first_opened DESC
                     LIMIT ? OFFSET ?",
                    [$id, $id, $tenantId, $id, $tenantId, $perPage, $offset]
                )->fetchAll() ?: [];

                $items = array_map(function ($row) {
                    $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                    return [
                        'email' => $row['email'] ?? '',
                        'name' => $name ?: null,
                        'first_opened' => $row['first_opened'] ?? '',
                        'open_count' => (int) ($row['open_count'] ?? 0),
                    ];
                }, $items);
            }

            $this->respondWithPaginatedCollection($items, $total, $page, $perPage);
        } catch (\Exception $e) {
            $this->respondWithPaginatedCollection([], 0, $page, $perPage);
        }
    }

    /**
     * Get email client breakdown for a newsletter
     */
    public function emailClients(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $clients = [];
        if ($this->tableExists('newsletter_opens')) {
            try {
                $opens = Database::query(
                    "SELECT user_agent FROM newsletter_opens
                     WHERE tenant_id = ? AND newsletter_id = ? AND user_agent IS NOT NULL",
                    [$tenantId, $id]
                )->fetchAll() ?: [];

                $counts = [];
                foreach ($opens as $open) {
                    $ua = strtolower($open['user_agent'] ?? '');
                    $client = 'Other';
                    if (strpos($ua, 'outlook') !== false) {
                        $client = 'Outlook';
                    } elseif (strpos($ua, 'gmail') !== false || strpos($ua, 'googleimageproxy') !== false) {
                        $client = 'Gmail';
                    } elseif ((strpos($ua, 'apple mail') !== false || strpos($ua, 'applewebkit') !== false) && strpos($ua, 'mobile') === false) {
                        $client = 'Apple Mail';
                    } elseif (strpos($ua, 'yahoo') !== false) {
                        $client = 'Yahoo Mail';
                    } elseif (strpos($ua, 'thunderbird') !== false) {
                        $client = 'Thunderbird';
                    }
                    $counts[$client] = ($counts[$client] ?? 0) + 1;
                }
                arsort($counts);
                foreach ($counts as $name => $count) {
                    $clients[] = ['client' => $name, 'count' => $count];
                }
            } catch (\Exception $e) {
                // No data
            }
        }

        $this->respondWithData(['email_clients' => $clients]);
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
            'sender_score_breakdown' => [
                'bounce_penalty' => 0,
                'complaint_penalty' => 0,
                'failure_penalty' => 0,
                'suppression_penalty' => 0,
                'volume_bonus' => 0,
            ],
            'configuration' => [
                'smtp_configured' => !empty(getenv('SMTP_HOST')),
                'api_configured' => !empty(getenv('USE_GMAIL_API')) && getenv('USE_GMAIL_API') !== 'false',
                'tracking_enabled' => $this->tableExists('newsletter_opens'),
            ],
            'health_status' => 'healthy',
        ];

        $totalSent = 0;
        $totalBounces = 0;
        $totalComplaints = 0;
        $totalSuppressed = 0;

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

                $totalSent = $diagnostics['queue_status']['sent'];
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Bounce rate + complaint count
        if ($this->tableExists('newsletter_bounces')) {
            try {
                $bounceStats = Database::query(
                    "SELECT bounce_type, COUNT(*) as cnt FROM newsletter_bounces WHERE tenant_id = ? GROUP BY bounce_type",
                    [$tenantId]
                )->fetchAll();

                foreach ($bounceStats as $row) {
                    $count = (int)$row['cnt'];
                    $totalBounces += $count;
                    if (($row['bounce_type'] ?? '') === 'complaint') {
                        $totalComplaints += $count;
                    }
                }

                if ($totalSent > 0) {
                    $diagnostics['bounce_rate'] = round(($totalBounces / $totalSent) * 100, 2);
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Suppression list size
        if ($this->tableExists('newsletter_suppression_list')) {
            try {
                $totalSuppressed = (int)(Database::query(
                    "SELECT COUNT(*) as cnt FROM newsletter_suppression_list WHERE tenant_id = ?",
                    [$tenantId]
                )->fetch()['cnt'] ?? 0);
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Dynamic sender score calculation (0-100)
        // Start at 100, subtract penalties, add small bonuses
        $score = 100;
        $breakdown = &$diagnostics['sender_score_breakdown'];

        // Bounce rate penalty: -3 per percentage point (e.g. 5% bounce = -15)
        $bouncePenalty = round($diagnostics['bounce_rate'] * 3, 1);
        $bouncePenalty = min($bouncePenalty, 40); // cap at -40
        $breakdown['bounce_penalty'] = $bouncePenalty;
        $score -= $bouncePenalty;

        // Complaint penalty: -10 per percentage point of complaints (severe)
        if ($totalSent > 0) {
            $complaintRate = ($totalComplaints / $totalSent) * 100;
            $complaintPenalty = round($complaintRate * 10, 1);
            $complaintPenalty = min($complaintPenalty, 30); // cap at -30
            $breakdown['complaint_penalty'] = $complaintPenalty;
            $score -= $complaintPenalty;
        }

        // Failure rate penalty: -2 per percentage point of failed sends
        $totalAttempted = $diagnostics['queue_status']['total'];
        if ($totalAttempted > 0) {
            $failureRate = ($diagnostics['queue_status']['failed'] / $totalAttempted) * 100;
            $failurePenalty = round($failureRate * 2, 1);
            $failurePenalty = min($failurePenalty, 20); // cap at -20
            $breakdown['failure_penalty'] = $failurePenalty;
            $score -= $failurePenalty;
        }

        // Suppression ratio penalty: high suppression = poor list hygiene
        if ($totalSent > 0) {
            $suppressionRatio = ($totalSuppressed / $totalSent) * 100;
            $suppressionPenalty = round($suppressionRatio * 1.5, 1);
            $suppressionPenalty = min($suppressionPenalty, 15); // cap at -15
            $breakdown['suppression_penalty'] = $suppressionPenalty;
            $score -= $suppressionPenalty;
        }

        // Volume bonus: reward consistent sending (up to +5)
        if ($totalSent >= 1000) {
            $breakdown['volume_bonus'] = 5;
            $score += 5;
        } elseif ($totalSent >= 100) {
            $breakdown['volume_bonus'] = 2;
            $score += 2;
        }

        $diagnostics['sender_score'] = max(0, min(100, round($score)));

        // Determine health status based on sender score + bounce rate
        if ($diagnostics['bounce_rate'] > 10 || $diagnostics['sender_score'] < 50) {
            $diagnostics['health_status'] = 'critical';
        } elseif ($diagnostics['bounce_rate'] > 5 || $diagnostics['queue_status']['failed'] > 10 || $diagnostics['sender_score'] < 70) {
            $diagnostics['health_status'] = 'warning';
        }

        $this->respondWithData($diagnostics);
    }

    /**
     * Bounce reason trend analysis — weekly breakdown by bounce type
     */
    public function getBounceTrends(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('newsletter_bounces')) {
            $this->respondWithData(['trends' => [], 'summary' => []]);
            return;
        }

        $weeks = $this->queryInt('weeks', 12, 1, 52);

        try {
            // Weekly breakdown by bounce type (last N weeks)
            $trends = Database::query(
                "SELECT
                    DATE_FORMAT(bounced_at, '%x-W%v') as week_label,
                    MIN(DATE(bounced_at)) as week_start,
                    bounce_type,
                    COUNT(*) as count
                 FROM newsletter_bounces
                 WHERE tenant_id = ?
                   AND bounced_at >= DATE_SUB(NOW(), INTERVAL ? WEEK)
                 GROUP BY week_label, bounce_type
                 ORDER BY week_label ASC, bounce_type ASC",
                [$tenantId, $weeks]
            )->fetchAll() ?: [];

            // Top bounce reasons with counts
            $summary = Database::query(
                "SELECT
                    COALESCE(bounce_reason, 'Unknown') as reason,
                    bounce_type,
                    COUNT(*) as count
                 FROM newsletter_bounces
                 WHERE tenant_id = ?
                   AND bounced_at >= DATE_SUB(NOW(), INTERVAL ? WEEK)
                 GROUP BY bounce_reason, bounce_type
                 ORDER BY count DESC
                 LIMIT ?",
                [$tenantId, $weeks, 10]
            )->fetchAll() ?: [];

            $this->respondWithData([
                'trends' => $trends,
                'summary' => $summary,
            ]);
        } catch (\Exception $e) {
            $this->respondWithData(['trends' => [], 'summary' => []]);
        }
    }
}
