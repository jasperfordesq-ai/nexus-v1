<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\EventTemplateException;
use App\Models\EventTemplate;
use App\Models\EventTemplateAudit;
use App\Models\EventTemplateVersion;
use App\Models\User;
use App\Policies\EventTemplatePolicy;
use App\Support\Events\EventTemplateFoundationSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/** Tenant-safe, policy-filtered query boundary for the event-template library. */
final class EventTemplateQueryService
{
    public function __construct(
        private readonly EventTemplateFoundationSupport $support = new EventTemplateFoundationSupport(),
        private readonly EventTemplatePolicy $policy = new EventTemplatePolicy(),
    ) {}

    /**
     * @return array{
     *   records:list<array{template:EventTemplate,version:EventTemplateVersion,source:\App\Models\Event,capabilities:array<string,bool>}>,
     *   meta:array{per_page:int,next_cursor:?string,has_more:bool,scanned:int}
     * }
     */
    public function index(
        User|int $actor,
        string $status = 'active',
        ?int $sourceEventId = null,
        ?string $search = null,
        ?int $cursor = null,
        int $perPage = 20,
    ): array {
        $this->assertSchema();
        $tenantId = $this->support->tenantId();
        $persistedActor = $this->support->actor($tenantId, $actor);
        $perPage = max(1, min(50, $perPage));
        $scanLimit = min(200, max(100, $perPage * 4));

        $query = EventTemplate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['sourceEvent' => static function ($query): void {
                $query->withoutGlobalScopes()->select([
                    'id',
                    'tenant_id',
                    'user_id',
                    'group_id',
                    'title',
                    'status',
                    'publication_status',
                    'operational_status',
                    'lifecycle_version',
                    'updated_at',
                ]);
            }])
            ->withCount(['audits', 'materializations'])
            ->orderByDesc('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($sourceEventId !== null) {
            $query->where('source_event_id', $sourceEventId);
        }
        if ($cursor !== null) {
            $query->where('id', '<', $cursor);
        }
        $search = trim((string) $search);
        if ($search !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->whereHas('sourceEvent', static function ($eventQuery) use ($escaped): void {
                $eventQuery->withoutGlobalScopes()
                    ->where('title', 'like', '%' . $escaped . '%');
            });
        }

        /** @var Collection<int,EventTemplate> $candidates */
        $candidates = $query->limit($scanLimit + 1)->get();
        $databaseHasMore = $candidates->count() > $scanLimit;
        $scan = $candidates->take($scanLimit)->values();
        $decisions = $this->policy->decisions($persistedActor, $scan);
        $versions = $this->currentVersions($tenantId, $scan);

        $records = [];
        $lastExaminedId = null;
        $stoppedBeforeEnd = false;
        $scanned = 0;
        foreach ($scan as $index => $template) {
            $scanned++;
            $lastExaminedId = (int) $template->id;
            if (! ($decisions[(int) $template->id] ?? false)) {
                continue;
            }
            $version = $versions[(int) $template->id] ?? null;
            if (! $version instanceof EventTemplateVersion || $template->sourceEvent === null) {
                throw new EventTemplateException('event_template_snapshot_integrity_invalid');
            }
            $records[] = $this->record($template, $version);
            if (count($records) === $perPage) {
                $stoppedBeforeEnd = $index < $scan->count() - 1;
                break;
            }
        }

        $hasMore = $stoppedBeforeEnd || $databaseHasMore;

        return [
            'records' => $records,
            'meta' => [
                'per_page' => $perPage,
                'next_cursor' => $hasMore && $lastExaminedId !== null
                    ? (string) $lastExaminedId
                    : null,
                'has_more' => $hasMore,
                'scanned' => $scanned,
            ],
        ];
    }

    /**
     * @return array{template:EventTemplate,version:EventTemplateVersion,source:\App\Models\Event,capabilities:array<string,bool>}
     */
    public function show(int $templateId, User|int $actor): array
    {
        $this->assertSchema();
        $tenantId = $this->support->tenantId();
        $persistedActor = $this->support->actor($tenantId, $actor);
        $template = $this->template($tenantId, $templateId);
        if (! $this->policy->view($persistedActor, $template)) {
            throw new EventTemplateException('event_template_authorization_denied');
        }
        $version = EventTemplateVersion::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('template_id', $templateId)
            ->where('version_number', (int) $template->current_version)
            ->first();
        if (! $version instanceof EventTemplateVersion || $template->sourceEvent === null) {
            throw new EventTemplateException('event_template_snapshot_integrity_invalid');
        }

        return $this->record($template, $version);
    }

    /**
     * @return array{
     *   audits:list<EventTemplateAudit>,
     *   meta:array{per_page:int,next_cursor:?string,has_more:bool}
     * }
     */
    public function history(
        int $templateId,
        User|int $actor,
        ?int $cursor = null,
        int $perPage = 50,
    ): array {
        $this->assertSchema();
        $tenantId = $this->support->tenantId();
        $persistedActor = $this->support->actor($tenantId, $actor);
        $template = $this->template($tenantId, $templateId);
        if (! $this->policy->viewAudit($persistedActor, $template)) {
            throw new EventTemplateException('event_template_authorization_denied');
        }

        $perPage = max(1, min(100, $perPage));
        $query = EventTemplateAudit::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('template_id', $templateId)
            ->orderByDesc('id');
        if ($cursor !== null) {
            $query->where('id', '<', $cursor);
        }
        $audits = $query->limit($perPage + 1)->get();
        $hasMore = $audits->count() > $perPage;
        $items = $audits->take($perPage)->values();

        return [
            'audits' => $items->all(),
            'meta' => [
                'per_page' => $perPage,
                'next_cursor' => $hasMore && $items->isNotEmpty()
                    ? (string) $items->last()->id
                    : null,
                'has_more' => $hasMore,
            ],
        ];
    }

    /**
     * @param Collection<int,EventTemplate> $templates
     * @return array<int,EventTemplateVersion>
     */
    private function currentVersions(int $tenantId, Collection $templates): array
    {
        $templateIds = $templates->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        if ($templateIds === []) {
            return [];
        }
        $current = $templates->mapWithKeys(
            static fn (EventTemplate $template): array => [
                (int) $template->id => (int) $template->current_version,
            ],
        )->all();

        return EventTemplateVersion::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('template_id', $templateIds)
            ->get()
            ->filter(static fn (EventTemplateVersion $version): bool =>
                (int) $version->version_number === ($current[(int) $version->template_id] ?? 0))
            ->mapWithKeys(static fn (EventTemplateVersion $version): array => [
                (int) $version->template_id => $version,
            ])
            ->all();
    }

    /**
     * @return array{template:EventTemplate,version:EventTemplateVersion,source:\App\Models\Event,capabilities:array<string,bool>}
     */
    private function record(
        EventTemplate $template,
        EventTemplateVersion $version,
    ): array {
        // `index` and `show` have already resolved EventTemplatePolicy against
        // this exact persisted aggregate. Derive state capabilities without
        // repeating the complete EventPolicy query matrix for every button.
        // Mutation services still re-authorize inside their transactions.
        $active = (string) $template->getRawOriginal('status') === 'active';

        return [
            'template' => $template,
            'version' => $version,
            'source' => $template->sourceEvent,
            'capabilities' => [
                'view' => true,
                'revise' => $active,
                'archive' => $active,
                'materialize' => $active,
                'view_audit' => true,
            ],
        ];
    }

    private function template(int $tenantId, int $templateId): EventTemplate
    {
        $template = EventTemplate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['sourceEvent' => static fn ($query) => $query->withoutGlobalScopes()])
            ->withCount(['audits', 'materializations'])
            ->find($templateId);
        if (! $template instanceof EventTemplate) {
            throw new EventTemplateException('event_template_not_found');
        }

        return $template;
    }

    private function assertSchema(): void
    {
        foreach ([
            'event_templates',
            'event_template_versions',
            'event_template_materializations',
            'event_template_audit',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new EventTemplateException('event_template_schema_unavailable');
            }
        }
    }
}
