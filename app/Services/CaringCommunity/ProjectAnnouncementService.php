<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

/**
 * AG69 — Multi-stage project announcement tracking.
 */
class ProjectAnnouncementService
{
    private const TABLE_PROJECTS = 'caring_project_announcements';
    private const TABLE_UPDATES = 'caring_project_updates';
    private const TABLE_SUBSCRIPTIONS = 'caring_project_subscriptions';

    /**
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return Schema::hasTable(self::TABLE_PROJECTS)
            && Schema::hasTable(self::TABLE_UPDATES)
            && Schema::hasTable(self::TABLE_SUBSCRIPTIONS);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listPublished(int $tenantId): array
    {
        self::ensureAvailable();

        return DB::table(self::TABLE_PROJECTS)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'paused', 'completed'])
            ->orderByRaw('CASE status WHEN "active" THEN 0 WHEN "paused" THEN 1 WHEN "completed" THEN 2 ELSE 3 END')
            ->orderByDesc('last_update_at')
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (object $row): array => self::projectRowToArray($row))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listAdmin(int $tenantId, ?string $status = null): array
    {
        self::ensureAvailable();

        $query = DB::table(self::TABLE_PROJECTS)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get()
            ->map(fn (object $row): array => self::projectRowToArray($row))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getProject(int $id, int $tenantId, bool $includeDrafts = false, ?int $viewerId = null): ?array
    {
        self::ensureAvailable();

        $query = DB::table(self::TABLE_PROJECTS)
            ->where('id', $id)
            ->where('tenant_id', $tenantId);

        if (! $includeDrafts) {
            $query->whereIn('status', ['active', 'paused', 'completed']);
        }

        $row = $query->first();
        if ($row === null) {
            return null;
        }

        $project = self::projectRowToArray($row);
        $project['updates'] = self::updatesForProject($id, $tenantId, $includeDrafts);
        $project['is_subscribed'] = $viewerId !== null
            ? self::isSubscribed($id, $tenantId, $viewerId)
            : false;

        return $project;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function createProject(int $tenantId, int $userId, array $data): array
    {
        self::ensureAvailable();
        self::assertTitle($data['title'] ?? null);

        $now = Carbon::now();
        $status = (string) ($data['status'] ?? 'draft');
        if (! in_array($status, ['draft', 'active', 'paused', 'completed', 'cancelled'], true)) {
            $status = 'draft';
        }

        $projectId = DB::table(self::TABLE_PROJECTS)->insertGetId([
            'tenant_id' => $tenantId,
            'created_by' => $userId,
            'title' => mb_substr((string) $data['title'], 0, 255),
            'summary' => self::nullableString($data['summary'] ?? null),
            'location' => self::nullableMaxString($data['location'] ?? null, 255),
            'status' => $status,
            'current_stage' => self::nullableMaxString($data['current_stage'] ?? null, 120),
            'progress_percent' => self::normaliseProgress($data['progress_percent'] ?? 0),
            'starts_at' => self::dateOrNull($data['starts_at'] ?? null),
            'ends_at' => self::dateOrNull($data['ends_at'] ?? null),
            'published_at' => $status === 'draft' ? null : $now,
            'last_update_at' => null,
            'subscriber_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return self::getProject((int) $projectId, $tenantId, true) ?? [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function updateProject(int $id, int $tenantId, array $data): array
    {
        self::ensureAvailable();
        self::assertProjectExists($id, $tenantId);

        $update = ['updated_at' => Carbon::now()];

        if (array_key_exists('title', $data)) {
            self::assertTitle($data['title']);
            $update['title'] = mb_substr((string) $data['title'], 0, 255);
        }
        if (array_key_exists('summary', $data)) {
            $update['summary'] = self::nullableString($data['summary']);
        }
        if (array_key_exists('location', $data)) {
            $update['location'] = self::nullableMaxString($data['location'], 255);
        }
        if (array_key_exists('current_stage', $data)) {
            $update['current_stage'] = self::nullableMaxString($data['current_stage'], 120);
        }
        if (array_key_exists('progress_percent', $data)) {
            $update['progress_percent'] = self::normaliseProgress($data['progress_percent']);
        }
        if (array_key_exists('starts_at', $data)) {
            $update['starts_at'] = self::dateOrNull($data['starts_at']);
        }
        if (array_key_exists('ends_at', $data)) {
            $update['ends_at'] = self::dateOrNull($data['ends_at']);
        }
        if (array_key_exists('status', $data)) {
            $status = (string) $data['status'];
            if (! in_array($status, ['draft', 'active', 'paused', 'completed', 'cancelled'], true)) {
                throw new InvalidArgumentException(__('api.caring_project_invalid_status'));
            }
            $update['status'] = $status;
            if ($status !== 'draft') {
                $update['published_at'] = DB::raw('COALESCE(published_at, NOW())');
            }
        }

        DB::table(self::TABLE_PROJECTS)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update($update);

        return self::getProject($id, $tenantId, true) ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function publishProject(int $id, int $tenantId): array
    {
        self::ensureAvailable();
        self::assertProjectExists($id, $tenantId);

        DB::table(self::TABLE_PROJECTS)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update([
                'status' => 'active',
                'published_at' => DB::raw('COALESCE(published_at, NOW())'),
                'updated_at' => Carbon::now(),
            ]);

        return self::getProject($id, $tenantId, true) ?? [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function createUpdate(int $projectId, int $tenantId, int $userId, array $data): array
    {
        self::ensureAvailable();
        self::assertProjectExists($projectId, $tenantId);
        self::assertTitle($data['title'] ?? null);

        $status = (string) ($data['status'] ?? 'draft');
        if (! in_array($status, ['draft', 'published'], true)) {
            $status = 'draft';
        }

        $now = Carbon::now();
        $updateId = DB::table(self::TABLE_UPDATES)->insertGetId([
            'tenant_id' => $tenantId,
            'project_id' => $projectId,
            'created_by' => $userId,
            'stage_label' => self::nullableMaxString($data['stage_label'] ?? null, 120),
            'title' => mb_substr((string) $data['title'], 0, 255),
            'body' => self::nullableString($data['body'] ?? null),
            'progress_percent' => array_key_exists('progress_percent', $data)
                ? self::normaliseProgress($data['progress_percent'])
                : null,
            'is_milestone' => ! empty($data['is_milestone']),
            'status' => $status,
            'published_at' => $status === 'published' ? $now : null,
            'notification_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($status === 'published') {
            self::applyPublishedUpdate((int) $updateId, $projectId, $tenantId);
        }

        return self::getUpdate((int) $updateId, $tenantId) ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function publishUpdate(int $updateId, int $tenantId): array
    {
        self::ensureAvailable();

        $update = DB::table(self::TABLE_UPDATES)
            ->where('id', $updateId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($update === null) {
            throw new RuntimeException(__('api.caring_project_update_not_found'));
        }

        if ($update->status === 'published') {
            return self::getUpdate($updateId, $tenantId) ?? [];
        }

        DB::table(self::TABLE_UPDATES)
            ->where('id', $updateId)
            ->where('tenant_id', $tenantId)
            ->update([
                'status' => 'published',
                'published_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

        self::applyPublishedUpdate($updateId, (int) $update->project_id, $tenantId);

        return self::getUpdate($updateId, $tenantId) ?? [];
    }

    public static function subscribe(int $projectId, int $tenantId, int $userId): void
    {
        self::ensureAvailable();
        self::assertProjectExists($projectId, $tenantId, false);

        DB::table(self::TABLE_SUBSCRIPTIONS)->updateOrInsert(
            [
                'project_id' => $projectId,
                'user_id' => $userId,
            ],
            [
                'tenant_id' => $tenantId,
                'subscribed_at' => Carbon::now(),
                'unsubscribed_at' => null,
                'updated_at' => Carbon::now(),
                'created_at' => Carbon::now(),
            ],
        );

        self::refreshSubscriberCount($projectId, $tenantId);
    }

    public static function unsubscribe(int $projectId, int $tenantId, int $userId): void
    {
        self::ensureAvailable();

        DB::table(self::TABLE_SUBSCRIPTIONS)
            ->where('project_id', $projectId)
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->update([
                'unsubscribed_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

        self::refreshSubscriberCount($projectId, $tenantId);
    }

    private static function ensureAvailable(): void
    {
        if (! self::isAvailable()) {
            throw new RuntimeException(__('api.caring_project_tables_unavailable'));
        }
    }

    private static function assertProjectExists(int $id, int $tenantId, bool $includeDrafts = true): void
    {
        $query = DB::table(self::TABLE_PROJECTS)
            ->where('id', $id)
            ->where('tenant_id', $tenantId);

        if (! $includeDrafts) {
            $query->whereIn('status', ['active', 'paused', 'completed']);
        }

        if (! $query->exists()) {
            throw new RuntimeException(__('api.caring_project_not_found'));
        }
    }

    private static function assertTitle(mixed $title): void
    {
        if (! is_string($title) || trim($title) === '') {
            throw new InvalidArgumentException(__('api.caring_project_title_required'));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function updatesForProject(int $projectId, int $tenantId, bool $includeDrafts): array
    {
        $query = DB::table(self::TABLE_UPDATES)
            ->where('project_id', $projectId)
            ->where('tenant_id', $tenantId);

        if (! $includeDrafts) {
            $query->where('status', 'published');
        }

        return $query
            ->orderByDesc('is_milestone')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (object $row): array => self::updateRowToArray($row))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function getUpdate(int $id, int $tenantId): ?array
    {
        $row = DB::table(self::TABLE_UPDATES)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        return $row ? self::updateRowToArray($row) : null;
    }

    private static function applyPublishedUpdate(int $updateId, int $projectId, int $tenantId): void
    {
        $update = DB::table(self::TABLE_UPDATES)
            ->where('id', $updateId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($update === null || $update->status !== 'published') {
            return;
        }

        $projectUpdate = [
            'last_update_at' => $update->published_at ?: Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        if ($update->progress_percent !== null) {
            $projectUpdate['progress_percent'] = (int) $update->progress_percent;
        }
        if ($update->stage_label !== null && trim((string) $update->stage_label) !== '') {
            $projectUpdate['current_stage'] = mb_substr((string) $update->stage_label, 0, 120);
        }

        DB::table(self::TABLE_PROJECTS)
            ->where('id', $projectId)
            ->where('tenant_id', $tenantId)
            ->update($projectUpdate);

        $notifications = self::notifySubscribers($projectId, $tenantId, (string) $update->title);

        DB::table(self::TABLE_UPDATES)
            ->where('id', $updateId)
            ->where('tenant_id', $tenantId)
            ->update([
                'notification_count' => $notifications,
                'updated_at' => Carbon::now(),
            ]);
    }

    private static function notifySubscribers(int $projectId, int $tenantId, string $updateTitle): int
    {
        $projectTitle = (string) DB::table(self::TABLE_PROJECTS)
            ->where('id', $projectId)
            ->where('tenant_id', $tenantId)
            ->value('title');

        $userIds = DB::table(self::TABLE_SUBSCRIPTIONS)
            ->where('project_id', $projectId)
            ->where('tenant_id', $tenantId)
            ->whereNull('unsubscribed_at')
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        foreach ($userIds as $userId) {
            Notification::createNotification(
                $userId,
                __('api.caring_project_update_notification', [
                    'project' => $projectTitle,
                    'update' => $updateTitle,
                ]),
                '/caring-community/projects/' . $projectId,
                'caring_project_update',
                false,
                $tenantId,
            );
        }

        return count($userIds);
    }

    private static function refreshSubscriberCount(int $projectId, int $tenantId): void
    {
        $count = DB::table(self::TABLE_SUBSCRIPTIONS)
            ->where('project_id', $projectId)
            ->where('tenant_id', $tenantId)
            ->whereNull('unsubscribed_at')
            ->count();

        DB::table(self::TABLE_PROJECTS)
            ->where('id', $projectId)
            ->where('tenant_id', $tenantId)
            ->update([
                'subscriber_count' => (int) $count,
                'updated_at' => Carbon::now(),
            ]);
    }

    private static function isSubscribed(int $projectId, int $tenantId, int $userId): bool
    {
        return DB::table(self::TABLE_SUBSCRIPTIONS)
            ->where('project_id', $projectId)
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('unsubscribed_at')
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private static function projectRowToArray(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'tenant_id' => (int) $row->tenant_id,
            'created_by' => $row->created_by !== null ? (int) $row->created_by : null,
            'title' => (string) $row->title,
            'summary' => $row->summary !== null ? (string) $row->summary : null,
            'location' => $row->location !== null ? (string) $row->location : null,
            'status' => (string) $row->status,
            'current_stage' => $row->current_stage !== null ? (string) $row->current_stage : null,
            'progress_percent' => (int) $row->progress_percent,
            'starts_at' => $row->starts_at !== null ? (string) $row->starts_at : null,
            'ends_at' => $row->ends_at !== null ? (string) $row->ends_at : null,
            'published_at' => $row->published_at !== null ? (string) $row->published_at : null,
            'last_update_at' => $row->last_update_at !== null ? (string) $row->last_update_at : null,
            'subscriber_count' => (int) $row->subscriber_count,
            'created_at' => $row->created_at !== null ? (string) $row->created_at : null,
            'updated_at' => $row->updated_at !== null ? (string) $row->updated_at : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function updateRowToArray(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'tenant_id' => (int) $row->tenant_id,
            'project_id' => (int) $row->project_id,
            'created_by' => $row->created_by !== null ? (int) $row->created_by : null,
            'stage_label' => $row->stage_label !== null ? (string) $row->stage_label : null,
            'title' => (string) $row->title,
            'body' => $row->body !== null ? (string) $row->body : null,
            'progress_percent' => $row->progress_percent !== null ? (int) $row->progress_percent : null,
            'is_milestone' => (bool) $row->is_milestone,
            'status' => (string) $row->status,
            'published_at' => $row->published_at !== null ? (string) $row->published_at : null,
            'notification_count' => (int) $row->notification_count,
            'created_at' => $row->created_at !== null ? (string) $row->created_at : null,
            'updated_at' => $row->updated_at !== null ? (string) $row->updated_at : null,
        ];
    }

    private static function normaliseProgress(mixed $value): int
    {
        return max(0, min(100, (int) $value));
    }

    private static function dateOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->toDateTimeString();
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function nullableMaxString(mixed $value, int $max): ?string
    {
        $value = self::nullableString($value);
        return $value === null ? null : mb_substr($value, 0, $max);
    }
}
