<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AG91 — Success-Story Proof Cards.
 *
 * Demo-ready proof cards municipalities can show to procurement officers and
 * funders. Each card combines a before/after metric (manually entered or
 * pulled live from AG83 PilotScoreboardService / AG76 MunicipalRoiService),
 * a short narrative, an audience tag, a method caveat, an evidence source
 * label, a Demo/Real flag and a published flag.
 *
 * Cards are stored as a single JSON envelope under the
 * `caring.success_stories` setting key per tenant — no schema change.
 *
 * Demo seeding only succeeds when no stories exist yet, so the admin's manual
 * curation is never overwritten. Promotion from `is_demo: true` to false is
 * always a manual decision — never automatic.
 */
class SuccessStoryService
{
    public const SETTING_KEY = 'caring.success_stories';

    public const METRIC_SOURCES = ['pilot_scoreboard', 'municipal_roi', 'manual'];

    /**
     * Pilot scoreboard service is optional — if Laravel cannot resolve it the
     * service still functions; only `refreshLiveMetrics()` for
     * metric_source=pilot_scoreboard is unavailable.
     */
    public function __construct(
        private readonly ?PilotScoreboardService $scoreboard = null,
    ) {
    }

    // ---------------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------------

    /**
     * Return all stored stories for the tenant.
     *
     * @return list<array<string, mixed>>
     */
    public function listStories(int $tenantId, bool $publishedOnly = false): array
    {
        $envelope = $this->loadEnvelope($tenantId);
        $items = $envelope['items'] ?? [];

        if ($publishedOnly) {
            $items = array_values(array_filter(
                $items,
                static fn ($item) => (bool) ($item['is_published'] ?? false),
            ));
        }

        return $items;
    }

    /**
     * Get a single story by stable ID.
     *
     * @return array<string, mixed>|null
     */
    public function getStory(int $tenantId, string $storyId): ?array
    {
        $envelope = $this->loadEnvelope($tenantId);
        $items = $envelope['items'] ?? [];

        $idx = $this->findIndex($items, $storyId);
        return $idx === null ? null : $items[$idx];
    }

    /**
     * Create a new story.
     *
     * @param array<string, mixed> $payload
     * @return array{story?: array<string, mixed>, errors?: list<array{code: string, message: string, field: string}>}
     */
    public function createStory(int $tenantId, array $payload): array
    {
        $errors = $this->validate($payload, false);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $envelope = $this->loadEnvelope($tenantId);
        $items = $envelope['items'] ?? [];

        $now = now()->toIso8601String();
        $story = $this->makeStory($payload, $now);
        $items[] = $story;

        $this->save($tenantId, $items);

        return ['story' => $story];
    }

    /**
     * Update an existing story by stable ID. Partial: only provided fields
     * are merged.
     *
     * @param array<string, mixed> $payload
     * @return array{story?: array<string, mixed>, error?: string, errors?: list<array{code: string, message: string, field: string}>}
     */
    public function updateStory(int $tenantId, string $storyId, array $payload): array
    {
        $envelope = $this->loadEnvelope($tenantId);
        $items = $envelope['items'] ?? [];

        $idx = $this->findIndex($items, $storyId);
        if ($idx === null) {
            return ['error' => 'not_found'];
        }

        $errors = $this->validate($payload, true);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $existing = $items[$idx];
        $now = now()->toIso8601String();

        $merged = array_merge($existing, [
            'title' => isset($payload['title'])
                ? trim((string) $payload['title'])
                : ($existing['title'] ?? ''),
            'narrative' => isset($payload['narrative'])
                ? trim((string) $payload['narrative'])
                : ($existing['narrative'] ?? ''),
            'metric_source' => isset($payload['metric_source'])
                ? (string) $payload['metric_source']
                : ($existing['metric_source'] ?? 'manual'),
            'metric_key' => array_key_exists('metric_key', $payload)
                ? (is_string($payload['metric_key']) && $payload['metric_key'] !== '' ? (string) $payload['metric_key'] : null)
                : ($existing['metric_key'] ?? null),
            'before_value' => array_key_exists('before_value', $payload)
                ? $this->floatOrNull($payload['before_value'])
                : ($existing['before_value'] ?? null),
            'after_value' => array_key_exists('after_value', $payload)
                ? $this->floatOrNull($payload['after_value'])
                : ($existing['after_value'] ?? null),
            'unit' => isset($payload['unit'])
                ? trim((string) $payload['unit'])
                : ($existing['unit'] ?? ''),
            'audience' => isset($payload['audience'])
                ? trim((string) $payload['audience'])
                : ($existing['audience'] ?? 'all_residents'),
            'sub_region_id' => array_key_exists('sub_region_id', $payload)
                ? (is_string($payload['sub_region_id']) && $payload['sub_region_id'] !== '' ? (string) $payload['sub_region_id'] : null)
                : ($existing['sub_region_id'] ?? null),
            'method_caveat' => isset($payload['method_caveat'])
                ? trim((string) $payload['method_caveat'])
                : ($existing['method_caveat'] ?? ''),
            'evidence_source' => isset($payload['evidence_source'])
                ? trim((string) $payload['evidence_source'])
                : ($existing['evidence_source'] ?? ''),
            'is_demo' => array_key_exists('is_demo', $payload)
                ? (bool) $payload['is_demo']
                : (bool) ($existing['is_demo'] ?? true),
            'is_published' => array_key_exists('is_published', $payload)
                ? (bool) $payload['is_published']
                : (bool) ($existing['is_published'] ?? false),
            'updated_at' => $now,
        ]);

        $items[$idx] = $merged;
        $this->save($tenantId, $items);

        return ['story' => $merged];
    }

    /**
     * Delete a story by stable ID.
     *
     * @return array{ok?: true, error?: string}
     */
    public function deleteStory(int $tenantId, string $storyId): array
    {
        $envelope = $this->loadEnvelope($tenantId);
        $items = $envelope['items'] ?? [];

        $idx = $this->findIndex($items, $storyId);
        if ($idx === null) {
            return ['error' => 'not_found'];
        }

        array_splice($items, $idx, 1);
        $this->save($tenantId, $items);

        return ['ok' => true];
    }

    /**
     * Seed three illustrative demo stories. Only succeeds when no stories
     * exist yet, so the admin's manual curation is never overwritten. All
     * seeded stories are flagged `is_demo: true` and `is_published: true` so
     * they appear immediately on the member-facing gallery, clearly labelled.
     *
     * @return array{items?: list<array<string, mixed>>, error?: string}
     */
    public function seedDemoStories(int $tenantId): array
    {
        $envelope = $this->loadEnvelope($tenantId);
        $items = $envelope['items'] ?? [];

        if (count($items) > 0) {
            return ['error' => 'already_seeded'];
        }

        $now = now()->toIso8601String();

        $seeds = [
            [
                'title' => '30% lower information distribution effort',
                'narrative' => 'Coordinators spend roughly a third less time disseminating updates after switching to NEXUS. The platform consolidates announcements, member-segment targeting, and confirmation tracking into one place.',
                'metric_source' => 'manual',
                'metric_key' => null,
                'before_value' => 100.0,
                'after_value' => 70.0,
                'unit' => '%',
                'audience' => 'municipality',
                'sub_region_id' => null,
                'method_caveat' => 'Illustrative example based on Agoris claim; not measured on this tenant.',
                'evidence_source' => 'Agoris municipality page (illustrative)',
            ],
            [
                'title' => '25% more volunteer engagement',
                'narrative' => 'Pilot communities report a quarter more active volunteer participation after adopting NEXUS, driven by clearer matching, low-friction sign-up, and visible Warmth Pass recognition.',
                'metric_source' => 'manual',
                'metric_key' => null,
                'before_value' => 20.0,
                'after_value' => 25.0,
                'unit' => '%',
                'audience' => 'verein_members',
                'sub_region_id' => null,
                'method_caveat' => 'Illustrative example based on Agoris claim; not measured on this tenant.',
                'evidence_source' => 'Agoris municipality page (illustrative)',
            ],
            [
                'title' => 'CHF 12,250 in formal care costs offset',
                'narrative' => 'Volunteer hours coordinated through NEXUS represent a CHF 12,250 offset against formal home-care costs in the pilot window, valued at the Swiss formal-care assistant rate.',
                'metric_source' => 'municipal_roi',
                'metric_key' => 'formal_care_offset_chf',
                'before_value' => 0.0,
                'after_value' => 12250.0,
                'unit' => 'CHF',
                'audience' => 'municipality',
                'sub_region_id' => null,
                'method_caveat' => 'Estimate using CHF 35/hr × 350 hours; pre-pilot baseline only.',
                'evidence_source' => 'AG76 MunicipalRoi (illustrative)',
            ],
        ];

        $newItems = [];
        foreach ($seeds as $seed) {
            $newItems[] = $this->makeStory(array_merge($seed, [
                'is_demo' => true,
                'is_published' => true,
            ]), $now);
        }

        $this->save($tenantId, $newItems);

        return ['items' => $newItems];
    }

    /**
     * Refresh a story's `after_value` from the linked live metric source.
     * Only operates when metric_source ∈ {pilot_scoreboard, municipal_roi}
     * and metric_key is non-null.
     *
     * @return array{story?: array<string, mixed>, error?: string}
     */
    public function refreshLiveMetrics(int $tenantId, string $storyId): array
    {
        $envelope = $this->loadEnvelope($tenantId);
        $items = $envelope['items'] ?? [];

        $idx = $this->findIndex($items, $storyId);
        if ($idx === null) {
            return ['error' => 'not_found'];
        }

        $story = $items[$idx];
        $source = (string) ($story['metric_source'] ?? 'manual');
        $key = $story['metric_key'] ?? null;

        if ($source === 'manual' || !is_string($key) || $key === '') {
            return ['error' => 'manual_metric'];
        }

        $newValue = null;

        if ($source === 'pilot_scoreboard' && $this->scoreboard !== null) {
            $metrics = $this->scoreboard->captureCurrentMetrics($tenantId);
            $newValue = $this->floatOrNull($metrics[$key] ?? null);
        } elseif ($source === 'municipal_roi') {
            $newValue = $this->fetchMunicipalRoiMetric($tenantId, $key);
        }

        if ($newValue === null) {
            return ['error' => 'metric_unavailable'];
        }

        $now = now()->toIso8601String();
        $items[$idx] = array_merge($story, [
            'after_value' => $newValue,
            'updated_at' => $now,
        ]);

        $this->save($tenantId, $items);

        return ['story' => $items[$idx]];
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * Lightweight inline ROI metric fetch — mirrors the calculation used by
     * AdminCaringCommunityController::municipalRoi() so the story can quote a
     * live CHF figure. Tenant-scoped via Schema::hasTable guards.
     */
    private function fetchMunicipalRoiMetric(int $tenantId, string $key): ?float
    {
        if (!Schema::hasTable('vol_logs')) {
            return null;
        }

        $totalHours = (float) DB::table('vol_logs')
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->sum('hours');

        $hourlyRateChf = 35.0;
        $formalCareOffsetChf = round($totalHours * $hourlyRateChf, 2);
        $preventionValueChf = round($formalCareOffsetChf * 2, 2);

        $recipientCount = 0;
        if (Schema::hasTable('caring_support_relationships')) {
            $recipientCount = (int) DB::table('caring_support_relationships')
                ->where('tenant_id', $tenantId)
                ->distinct('recipient_user_id')
                ->count('recipient_user_id');
        }

        return match ($key) {
            'hourly_rate_chf' => $hourlyRateChf,
            'formal_care_offset_chf' => $formalCareOffsetChf,
            'prevention_value_chf' => $preventionValueChf,
            'social_isolation_prevented' => (float) $recipientCount,
            'total_hours' => $totalHours,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array{code: string, message: string, field: string}>
     */
    private function validate(array $payload, bool $isPartial): array
    {
        $errors = [];

        // title — required, ≤200
        if (!$isPartial || array_key_exists('title', $payload)) {
            $title = isset($payload['title']) ? trim((string) $payload['title']) : '';
            if ($title === '') {
                $errors[] = ['code' => 'VALIDATION_REQUIRED', 'message' => __('caring_community.success_stories.validation.title_required'), 'field' => 'title'];
            } elseif (mb_strlen($title) > 200) {
                $errors[] = ['code' => 'VALIDATION_LENGTH', 'message' => __('caring_community.success_stories.validation.title_too_long'), 'field' => 'title'];
            }
        }

        // narrative — required, ≤1500
        if (!$isPartial || array_key_exists('narrative', $payload)) {
            $narrative = isset($payload['narrative']) ? trim((string) $payload['narrative']) : '';
            if ($narrative === '') {
                $errors[] = ['code' => 'VALIDATION_REQUIRED', 'message' => __('caring_community.success_stories.validation.narrative_required'), 'field' => 'narrative'];
            } elseif (mb_strlen($narrative) > 1500) {
                $errors[] = ['code' => 'VALIDATION_LENGTH', 'message' => __('caring_community.success_stories.validation.narrative_too_long'), 'field' => 'narrative'];
            }
        }

        // method_caveat — required, ≤500
        if (!$isPartial || array_key_exists('method_caveat', $payload)) {
            $caveat = isset($payload['method_caveat']) ? trim((string) $payload['method_caveat']) : '';
            if ($caveat === '') {
                $errors[] = ['code' => 'VALIDATION_REQUIRED', 'message' => __('caring_community.success_stories.validation.method_caveat_required'), 'field' => 'method_caveat'];
            } elseif (mb_strlen($caveat) > 500) {
                $errors[] = ['code' => 'VALIDATION_LENGTH', 'message' => __('caring_community.success_stories.validation.method_caveat_too_long'), 'field' => 'method_caveat'];
            }
        }

        // evidence_source — required, ≤300
        if (!$isPartial || array_key_exists('evidence_source', $payload)) {
            $evidence = isset($payload['evidence_source']) ? trim((string) $payload['evidence_source']) : '';
            if ($evidence === '') {
                $errors[] = ['code' => 'VALIDATION_REQUIRED', 'message' => __('caring_community.success_stories.validation.evidence_source_required'), 'field' => 'evidence_source'];
            } elseif (mb_strlen($evidence) > 300) {
                $errors[] = ['code' => 'VALIDATION_LENGTH', 'message' => __('caring_community.success_stories.validation.evidence_source_too_long'), 'field' => 'evidence_source'];
            }
        }

        // metric_source — must be in enum if provided
        if (array_key_exists('metric_source', $payload)) {
            $source = (string) $payload['metric_source'];
            if (!in_array($source, self::METRIC_SOURCES, true)) {
                $errors[] = ['code' => 'VALIDATION_ENUM', 'message' => __('caring_community.success_stories.validation.metric_source_invalid'), 'field' => 'metric_source'];
            }
        }

        // unit — ≤30
        if (array_key_exists('unit', $payload)) {
            $unit = trim((string) ($payload['unit'] ?? ''));
            if (mb_strlen($unit) > 30) {
                $errors[] = ['code' => 'VALIDATION_LENGTH', 'message' => __('caring_community.success_stories.validation.unit_too_long'), 'field' => 'unit'];
            }
        }

        // audience — ≤50
        if (array_key_exists('audience', $payload)) {
            $audience = trim((string) ($payload['audience'] ?? ''));
            if (mb_strlen($audience) > 50) {
                $errors[] = ['code' => 'VALIDATION_LENGTH', 'message' => __('caring_community.success_stories.validation.audience_too_long'), 'field' => 'audience'];
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function makeStory(array $payload, string $now): array
    {
        $metricSource = (string) ($payload['metric_source'] ?? 'manual');
        if (!in_array($metricSource, self::METRIC_SOURCES, true)) {
            $metricSource = 'manual';
        }

        $metricKeyRaw = $payload['metric_key'] ?? null;
        $metricKey = is_string($metricKeyRaw) && $metricKeyRaw !== ''
            ? (string) $metricKeyRaw
            : null;

        $subRegionRaw = $payload['sub_region_id'] ?? null;
        $subRegionId = is_string($subRegionRaw) && $subRegionRaw !== ''
            ? (string) $subRegionRaw
            : null;

        return [
            'id' => $this->generateId(),
            'title' => trim((string) ($payload['title'] ?? '')),
            'narrative' => trim((string) ($payload['narrative'] ?? '')),
            'metric_source' => $metricSource,
            'metric_key' => $metricKey,
            'before_value' => $this->floatOrNull($payload['before_value'] ?? null),
            'after_value' => $this->floatOrNull($payload['after_value'] ?? null),
            'unit' => trim((string) ($payload['unit'] ?? '')),
            'audience' => trim((string) ($payload['audience'] ?? 'all_residents')),
            'sub_region_id' => $subRegionId,
            'method_caveat' => trim((string) ($payload['method_caveat'] ?? '')),
            'evidence_source' => trim((string) ($payload['evidence_source'] ?? '')),
            'is_demo' => array_key_exists('is_demo', $payload) ? (bool) $payload['is_demo'] : true,
            'is_published' => array_key_exists('is_published', $payload) ? (bool) $payload['is_published'] : false,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function generateId(): string
    {
        return 'story_' . substr(bin2hex(random_bytes(8)), 0, 16);
    }

    private function floatOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_scalar($v) || (is_string($v) && !is_numeric($v))) {
            return null;
        }
        return (float) $v;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function findIndex(array $items, string $storyId): ?int
    {
        foreach ($items as $i => $item) {
            if (($item['id'] ?? null) === $storyId) {
                return $i;
            }
        }
        return null;
    }

    /**
     * @return array{items: list<array<string, mixed>>, updated_at: ?string}
     */
    private function loadEnvelope(int $tenantId): array
    {
        if (!Schema::hasTable('tenant_settings')) {
            return ['items' => [], 'updated_at' => null];
        }

        $row = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', self::SETTING_KEY)
            ->first();

        if (!$row || !$row->setting_value) {
            return ['items' => [], 'updated_at' => null];
        }

        $decoded = json_decode((string) $row->setting_value, true);
        if (!is_array($decoded)) {
            return ['items' => [], 'updated_at' => null];
        }

        $items = [];
        if (isset($decoded['items']) && is_array($decoded['items'])) {
            foreach ($decoded['items'] as $item) {
                if (is_array($item) && isset($item['id'])) {
                    $items[] = $item;
                }
            }
        }

        $updatedAt = isset($decoded['updated_at']) && is_string($decoded['updated_at'])
            ? $decoded['updated_at']
            : (isset($row->updated_at) ? (string) $row->updated_at : null);

        return ['items' => $items, 'updated_at' => $updatedAt];
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function save(int $tenantId, array $items): void
    {
        if (!Schema::hasTable('tenant_settings')) {
            return;
        }

        $now = now();
        $envelope = [
            'items' => array_values($items),
            'updated_at' => $now->toIso8601String(),
        ];

        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'setting_key' => self::SETTING_KEY],
            [
                'setting_value' => json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'setting_type' => 'json',
                'category' => 'caring_community',
                'description' => 'AG91 success story proof cards',
                'updated_at' => $now,
            ],
        );
    }
}
