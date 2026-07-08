<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AuditHotPathPerformance extends Command
{
    protected $signature = 'performance:audit-hot-paths
        {--tenant=2 : Tenant id to use in representative EXPLAIN bindings}
        {--user=1 : User id to use in representative user-scoped EXPLAIN bindings}
        {--strict : Return a non-zero exit code when strict hot paths full-scan base tables}';

    protected $description = 'Run read-only EXPLAIN checks for high-traffic feed, listings, marketplace, search, messages, events, and notifications paths';

    public function handle(): int
    {
        $tenantId = max(1, (int) $this->option('tenant'));
        $userId = max(1, (int) $this->option('user'));
        $strict = (bool) $this->option('strict');
        $failed = false;

        $this->info(sprintf('Running hot-path EXPLAIN audit for tenant=%d user=%d', $tenantId, $userId));

        foreach ($this->cases($tenantId, $userId) as $case) {
            if (! $this->requirementsMet($case['requires'])) {
                $this->warn("SKIP {$case['name']} - required table/columns are absent");
                continue;
            }

            try {
                $rows = DB::select('EXPLAIN ' . $case['sql'], $case['bindings']);
            } catch (\Throwable $e) {
                $this->error("FAIL {$case['name']} - EXPLAIN error: {$e->getMessage()}");
                $failed = true;
                continue;
            }

            $summary = $this->summariseExplain($rows);
            $status = $summary['risky'] ? 'WARN' : 'OK';
            $this->line(sprintf(
                '%s %-34s keys=%s rows=%s extra=%s',
                $status,
                $case['name'],
                $summary['keys'],
                $summary['rows'],
                $summary['extra']
            ));

            if ($strict && $case['strict'] && $summary['risky']) {
                $failed = true;
            }
        }

        if ($failed) {
            $this->error('Hot-path performance audit found strict EXPLAIN risks.');

            return Command::FAILURE;
        }

        $this->info('Hot-path performance audit finished.');

        return Command::SUCCESS;
    }

    /**
     * @return list<array{
     *   name:string,
     *   sql:string,
     *   bindings:list<mixed>,
     *   strict:bool,
     *   requires:array<string,list<string>>
     * }>
     */
    private function cases(int $tenantId, int $userId): array
    {
        return [
            [
                'name' => 'feed chronological',
                'sql' => 'SELECT feed_activity.id FROM feed_activity INNER JOIN users u ON feed_activity.user_id = u.id AND u.tenant_id = ? WHERE feed_activity.tenant_id = ? AND feed_activity.is_visible = 1 ORDER BY feed_activity.created_at DESC, feed_activity.id DESC LIMIT 21',
                'bindings' => [$tenantId, $tenantId],
                'strict' => true,
                'requires' => [
                    'feed_activity' => ['tenant_id', 'user_id', 'is_visible', 'created_at', 'id'],
                    'users' => ['tenant_id', 'id'],
                ],
            ],
            [
                'name' => 'listings public newest',
                'sql' => "SELECT id FROM listings WHERE tenant_id = ? AND (status IS NULL OR status = 'active') AND (moderation_status IS NULL OR moderation_status = 'approved') ORDER BY id DESC LIMIT 21",
                'bindings' => [$tenantId],
                'strict' => true,
                'requires' => ['listings' => ['tenant_id', 'status', 'moderation_status', 'id']],
            ],
            [
                'name' => 'listings SQL search fallback',
                'sql' => "SELECT id FROM listings WHERE tenant_id = ? AND (status IS NULL OR status = 'active') AND (moderation_status IS NULL OR moderation_status = 'approved') AND (title LIKE ? OR description LIKE ?) ORDER BY id DESC LIMIT 21",
                'bindings' => [$tenantId, '%garden%', '%garden%'],
                'strict' => false,
                'requires' => ['listings' => ['tenant_id', 'status', 'moderation_status', 'title', 'description', 'id']],
            ],
            [
                'name' => 'search users SQL fallback',
                'sql' => "SELECT id FROM users WHERE tenant_id = ? AND (first_name LIKE ? OR last_name LIKE ? OR organization_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?) AND status NOT IN ('banned', 'suspended') ORDER BY id DESC LIMIT 21",
                'bindings' => [$tenantId, '%garden%', '%garden%', '%garden%', '%garden%'],
                'strict' => false,
                'requires' => ['users' => ['tenant_id', 'first_name', 'last_name', 'organization_name', 'status', 'id']],
            ],
            [
                'name' => 'search events SQL fallback',
                'sql' => "SELECT id FROM events WHERE tenant_id = ? AND (title LIKE ? OR description LIKE ? OR location LIKE ?) AND (status IS NULL OR status = 'active') AND start_time >= NOW() ORDER BY start_time ASC, id ASC LIMIT 21",
                'bindings' => [$tenantId, '%garden%', '%garden%', '%garden%'],
                'strict' => false,
                'requires' => ['events' => ['tenant_id', 'title', 'description', 'location', 'status', 'start_time', 'id']],
            ],
            [
                'name' => 'search groups SQL fallback',
                'sql' => 'SELECT id FROM `groups` WHERE tenant_id = ? AND (name LIKE ? OR description LIKE ?) ORDER BY id DESC LIMIT 21',
                'bindings' => [$tenantId, '%garden%', '%garden%'],
                'strict' => false,
                'requires' => ['groups' => ['tenant_id', 'name', 'description', 'id']],
            ],
            [
                'name' => 'search suggestions listings',
                'sql' => "SELECT id, title, type FROM listings WHERE tenant_id = ? AND title LIKE ? AND (status IS NULL OR status = 'active') ORDER BY id DESC LIMIT 10",
                'bindings' => [$tenantId, '%ga%'],
                'strict' => false,
                'requires' => ['listings' => ['tenant_id', 'title', 'type', 'status', 'id']],
            ],
            [
                'name' => 'search suggestions users',
                'sql' => "SELECT id, first_name, last_name, avatar_url, organization_name, profile_type FROM users WHERE tenant_id = ? AND (first_name LIKE ? OR last_name LIKE ? OR organization_name LIKE ?) AND status NOT IN ('banned', 'suspended') LIMIT 10",
                'bindings' => [$tenantId, '%ga%', '%ga%', '%ga%'],
                'strict' => false,
                'requires' => ['users' => ['tenant_id', 'first_name', 'last_name', 'avatar_url', 'organization_name', 'profile_type', 'status', 'id']],
            ],
            [
                'name' => 'search suggestions events',
                'sql' => "SELECT id, title, start_time FROM events WHERE tenant_id = ? AND title LIKE ? AND (status IS NULL OR status = 'active') AND start_time >= NOW() ORDER BY start_time ASC LIMIT 10",
                'bindings' => [$tenantId, '%ga%'],
                'strict' => false,
                'requires' => ['events' => ['tenant_id', 'title', 'status', 'start_time', 'id']],
            ],
            [
                'name' => 'search suggestions groups',
                'sql' => 'SELECT id, name FROM `groups` WHERE tenant_id = ? AND name LIKE ? ORDER BY id DESC LIMIT 10',
                'bindings' => [$tenantId, '%ga%'],
                'strict' => false,
                'requires' => ['groups' => ['tenant_id', 'name', 'id']],
            ],
            [
                'name' => 'search trending terms',
                'sql' => 'SELECT query AS term, COUNT(*) AS search_count FROM search_logs WHERE tenant_id = ? AND created_at >= ? GROUP BY query ORDER BY search_count DESC LIMIT 50',
                'bindings' => [$tenantId, now()->subDays(7)],
                'strict' => true,
                'requires' => ['search_logs' => ['tenant_id', 'query', 'created_at']],
            ],
            [
                'name' => 'saved searches list',
                'sql' => 'SELECT id FROM saved_searches WHERE tenant_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 50',
                'bindings' => [$tenantId, $userId],
                'strict' => true,
                'requires' => ['saved_searches' => ['tenant_id', 'user_id', 'created_at', 'id']],
            ],
            [
                'name' => 'members directory SQL fallback',
                'sql' => "SELECT id FROM users WHERE tenant_id = ? AND (first_name LIKE ? OR last_name LIKE ? OR organization_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?) AND status NOT IN ('banned', 'suspended') AND (privacy_search = 1 OR privacy_search IS NULL) LIMIT 200",
                'bindings' => [$tenantId, '%garden%', '%garden%', '%garden%', '%garden%'],
                'strict' => false,
                'requires' => ['users' => ['tenant_id', 'first_name', 'last_name', 'organization_name', 'status', 'privacy_search', 'id']],
            ],
            [
                'name' => 'marketplace newest',
                'sql' => "SELECT id FROM marketplace_listings WHERE tenant_id = ? AND status = 'active' AND moderation_status = 'approved' ORDER BY id DESC LIMIT 21",
                'bindings' => [$tenantId],
                'strict' => true,
                'requires' => ['marketplace_listings' => ['tenant_id', 'status', 'moderation_status', 'id']],
            ],
            [
                'name' => 'marketplace price sort',
                'sql' => "SELECT id FROM marketplace_listings WHERE tenant_id = ? AND status = 'active' AND moderation_status = 'approved' ORDER BY price ASC, id ASC LIMIT 21",
                'bindings' => [$tenantId],
                'strict' => true,
                'requires' => ['marketplace_listings' => ['tenant_id', 'status', 'moderation_status', 'price', 'id']],
            ],
            [
                'name' => 'marketplace popular sort',
                'sql' => "SELECT id FROM marketplace_listings WHERE tenant_id = ? AND status = 'active' AND moderation_status = 'approved' ORDER BY views_count DESC, id DESC LIMIT 21",
                'bindings' => [$tenantId],
                'strict' => true,
                'requires' => ['marketplace_listings' => ['tenant_id', 'status', 'moderation_status', 'views_count', 'id']],
            ],
            [
                'name' => 'messages conversations',
                'sql' => 'SELECT MAX(id) AS latest_id, partner_id FROM ((SELECT id, receiver_id AS partner_id FROM messages WHERE tenant_id = ? AND is_federated = 0 AND sender_id = ? AND archived_by_sender IS NULL) UNION ALL (SELECT id, sender_id AS partner_id FROM messages WHERE tenant_id = ? AND is_federated = 0 AND receiver_id = ? AND archived_by_receiver IS NULL)) conversation_messages GROUP BY partner_id ORDER BY latest_id DESC LIMIT 21',
                'bindings' => [$tenantId, $userId, $tenantId, $userId],
                'strict' => true,
                'requires' => ['messages' => ['tenant_id', 'is_federated', 'sender_id', 'receiver_id', 'archived_by_sender', 'archived_by_receiver', 'id']],
            ],
            [
                'name' => 'messages unread counts',
                'sql' => 'SELECT sender_id, COUNT(*) FROM messages WHERE tenant_id = ? AND is_federated = 0 AND receiver_id = ? AND is_read = 0 AND sender_id IN (?, ?, ?) GROUP BY sender_id',
                'bindings' => [$tenantId, $userId, max(1, $userId - 1), $userId + 1, $userId + 2],
                'strict' => true,
                'requires' => ['messages' => ['tenant_id', 'is_federated', 'receiver_id', 'is_read', 'sender_id']],
            ],
            [
                'name' => 'events upcoming public',
                'sql' => "SELECT id FROM events WHERE tenant_id = ? AND (status IS NULL OR status = 'active') AND start_time >= NOW() ORDER BY start_time DESC, id DESC LIMIT 21",
                'bindings' => [$tenantId],
                'strict' => true,
                'requires' => ['events' => ['tenant_id', 'status', 'start_time', 'id']],
            ],
            [
                'name' => 'notifications unread',
                'sql' => 'SELECT id FROM notifications WHERE tenant_id = ? AND user_id = ? AND is_read = 0 ORDER BY id DESC LIMIT 21',
                'bindings' => [$tenantId, $userId],
                'strict' => true,
                'requires' => ['notifications' => ['tenant_id', 'user_id', 'is_read', 'id']],
            ],
            [
                'name' => 'notifications type filter',
                'sql' => "SELECT id FROM notifications WHERE tenant_id = ? AND user_id = ? AND type IN ('message', 'new_message') ORDER BY id DESC LIMIT 21",
                'bindings' => [$tenantId, $userId],
                'strict' => true,
                'requires' => ['notifications' => ['tenant_id', 'user_id', 'type', 'id']],
            ],
        ];
    }

    /**
     * @param array<string,list<string>> $requirements
     */
    private function requirementsMet(array $requirements): bool
    {
        foreach ($requirements as $table => $columns) {
            if (! Schema::hasTable($table)) {
                return false;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param list<object> $rows
     * @return array{keys:string,rows:string,extra:string,risky:bool}
     */
    private function summariseExplain(array $rows): array
    {
        $keys = [];
        $possibleKeys = [];
        $rowCounts = [];
        $extras = [];
        $risky = false;

        foreach ($rows as $row) {
            $data = (array) $row;
            $table = (string) ($data['table'] ?? '');
            $type = strtoupper((string) ($data['type'] ?? ''));
            $key = (string) ($data['key'] ?? '');
            $possibleKey = (string) ($data['possible_keys'] ?? '');
            $rowCount = (string) ($data['rows'] ?? '');
            $extra = (string) ($data['Extra'] ?? '');

            if ($key !== '') {
                $keys[$key] = true;
            }
            if ($possibleKey !== '') {
                foreach (explode(',', $possibleKey) as $candidateKey) {
                    $candidateKey = trim($candidateKey);
                    if ($candidateKey !== '') {
                        $possibleKeys[$candidateKey] = true;
                    }
                }
            }
            if ($rowCount !== '') {
                $rowCounts[] = $rowCount;
            }
            if ($extra !== '') {
                $extras[$extra] = true;
            }

            $isBaseTable = $table !== '' && ! str_starts_with($table, '<');
            $estimatedRows = is_numeric($rowCount) ? (int) $rowCount : null;
            $tinyCandidateScan = $key === ''
                && $possibleKey !== ''
                && $estimatedRows !== null
                && $estimatedRows <= 100;

            if ($isBaseTable && ($key === '' || $type === 'ALL') && ! $tinyCandidateScan) {
                $risky = true;
            }
        }

        $keySummary = array_keys($keys);
        if (empty($keySummary) && ! empty($possibleKeys)) {
            $candidateKeys = array_keys($possibleKeys);
            $visibleCandidates = array_slice($candidateKeys, 0, 4);
            $keySummary = array_map(
                static fn (string $key): string => "candidate:{$key}",
                $visibleCandidates
            );
            if (count($candidateKeys) > count($visibleCandidates)) {
                $keySummary[] = sprintf('+%d more candidate(s)', count($candidateKeys) - count($visibleCandidates));
            }
        }

        return [
            'keys' => empty($keySummary) ? '(none)' : implode(',', $keySummary),
            'rows' => empty($rowCounts) ? '(unknown)' : implode('/', $rowCounts),
            'extra' => empty($extras) ? '(none)' : implode(' | ', array_keys($extras)),
            'risky' => $risky,
        ];
    }
}
