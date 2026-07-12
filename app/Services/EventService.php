<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Enums\EventCapacityRegistrationState;
use App\Enums\EventNotificationDeliveryMode;
use App\Enums\EventOperationalState;
use App\Enums\EventPublicationState;
use App\Enums\EventWaitlistQueueState;
use App\Enums\GroupStatus;
use App\Exceptions\EventAttendanceException;
use App\Exceptions\EventLifecycleTransitionException;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\EventSeries;
use App\Models\User;
use App\Policies\EventPolicy;
use App\Support\Events\EventContractMapper;
use App\Support\Events\EventDiscoveryCursor;
use App\Support\Events\EventAttendanceResult;
use App\Support\Events\EventLifecycleCompatibility;
use App\Support\Events\EventLifecycleTransitionGuard;
use App\Support\Events\EventLifecycleTransitionResult;
use App\Support\Events\EventRegistrationAvailability;
use App\Support\Events\EventRegistrationCompatibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
/**
 * EventService — Laravel DI-based service for event operations.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class EventService
{
    public const MAX_BULK_ATTENDANCE = 100;

    private const MAX_EVENT_DESCRIPTION_LENGTH = 10000;
    private const MAX_EVENT_CAPACITY = 100000;
    private const RECURRENCE_ENGINE = 'legacy';
    private const RECURRENCE_ENGINE_VERSION = '1';
    private const VENUE_ACCESSIBILITY_BOOLEAN_FIELDS = [
        'step_free_access' => 'accessibility_step_free',
        'accessible_toilet' => 'accessibility_toilet',
        'hearing_loop' => 'accessibility_hearing_loop',
        'quiet_space' => 'accessibility_quiet_space',
        'seating_available' => 'accessibility_seating',
        'accessible_parking' => 'accessibility_parking',
    ];
    private const VENUE_ACCESSIBILITY_TEXT_FIELDS = [
        'parking_details' => ['column' => 'accessibility_parking_details', 'max' => 1000],
        'transit_details' => ['column' => 'accessibility_transit_details', 'max' => 1000],
        'assistance_contact' => ['column' => 'accessibility_assistance_contact', 'max' => 500],
        'notes' => ['column' => 'accessibility_notes', 'max' => 4000],
    ];

    private static bool $lastRsvpChanged = false;

    private static ?EventAttendanceResult $lastAttendanceResult = null;

    private static ?EventLifecycleTransitionResult $lastLifecycleResult = null;

    /** @var array<string,mixed>|null */
    private static ?array $lastLifecycleResponse = null;

    /** @var array<string,mixed> */
    private static array $lastMeaningfulUpdateChanges = [];

    /** @var array<int> */
    private static array $lastCancellationRecipientIds = [];

    public function __construct(
        private readonly Event $event,
        private readonly EventRsvp $rsvp,
    ) {}

    /**
     * Get events with cursor-based pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getAll(array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 20), 100));
        $whenInput = $filters['when'] ?? 'upcoming';
        if (!is_string($whenInput) || !in_array($whenInput, ['upcoming', 'past', 'all'], true)) {
            throw ValidationException::withMessages([
                'when' => [__('validation.in', ['attribute' => 'when'])],
            ]);
        }

        $when = $whenInput;
        $viewerId = !empty($filters['viewer_id']) ? (int) $filters['viewer_id'] : null;
        $tenantId = (int) TenantContext::getId();
        $categoryId = self::resolveDiscoveryCategoryId($filters);
        $seriesId = self::resolveDiscoverySeriesId($filters['series_id'] ?? null);
        $groupId = self::resolveDiscoveryPositiveId($filters['group_id'] ?? null, 'group_id');
        $organizerId = self::resolveDiscoveryPositiveId($filters['user_id'] ?? null, 'user_id');
        $search = self::resolveDiscoverySearch($filters['search'] ?? null);
        $stepFree = self::resolveDiscoveryStepFree($filters['step_free'] ?? null);

        $coordinateInputs = [
            'near_lat' => $filters['near_lat'] ?? null,
            'near_lng' => $filters['near_lng'] ?? null,
            'radius_km' => $filters['radius_km'] ?? null,
        ];
        $providedCoordinates = array_filter(
            $coordinateInputs,
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );
        if ($providedCoordinates !== [] && count($providedCoordinates) !== 3) {
            throw ValidationException::withMessages([
                'near_lat' => [__('api.event_lat_lon_required')],
            ]);
        }

        $hasProximity = count($providedCoordinates) === 3;
        $nearLat = $hasProximity
            ? self::resolveDiscoveryFloat($coordinateInputs['near_lat'], 'near_lat', -90.0, 90.0)
            : null;
        $nearLng = $hasProximity
            ? self::resolveDiscoveryFloat($coordinateInputs['near_lng'], 'near_lng', -180.0, 180.0)
            : null;
        $radiusKm = $hasProximity
            ? self::resolveDiscoveryFloat($coordinateInputs['radius_km'], 'radius_km', 0.1, 500.0)
            : null;

        $sort = $hasProximity
            ? 'distance_asc_start_asc_id_asc'
            : ($when === 'upcoming' ? 'start_asc_id_asc' : 'start_desc_id_desc');
        $cursorKind = $hasProximity ? 'events.proximity' : 'events.list';
        $queryIdentity = self::discoveryQueryIdentity([
            'tenant_id' => $tenantId,
            'viewer_id' => $viewerId,
            'when' => $when,
            'category_id' => $categoryId,
            'series_id' => $seriesId,
            'group_id' => $groupId,
            'user_id' => $organizerId,
            'search' => $search,
            'step_free' => $stepFree,
            'near_lat' => $nearLat !== null ? sprintf('%.8F', $nearLat) : null,
            'near_lng' => $nearLng !== null ? sprintf('%.8F', $nearLng) : null,
            'radius_km' => $radiusKm !== null ? sprintf('%.3F', $radiusKm) : null,
            'sort' => $sort,
        ]);

        $cursorPosition = null;
        $snapshotAt = now()->format('Y-m-d H:i:s');
        if (array_key_exists('cursor', $filters)) {
            $cursor = $filters['cursor'];
            if (!is_string($cursor) || $cursor === '') {
                self::rejectDiscoveryCursor();
            }

            $decodedCursor = EventDiscoveryCursor::decode($cursor, $cursorKind, $queryIdentity);
            $snapshotAt = self::validateDiscoveryCursorDate($decodedCursor['at']);
            $cursorPosition = $decodedCursor['p'];
        }

        $query = Event::query()
            ->with([
                'user:id,first_name,last_name,avatar_url,organization_name,profile_type',
                'category:id,name,slug,color,type',
                'group:id,name',
            ])
            ->where(function (Builder $q) {
                $q->whereNull('status')->orWhere('status', 'active');
            });

        $isTenantAdmin = self::isTenantAdmin($viewerId, $tenantId);
        $query->where(function (Builder $visibility) use ($viewerId, $tenantId, $isTenantAdmin) {
            $visibility->whereNull('events.group_id')
                ->orWhereExists(function ($group) use ($viewerId, $tenantId, $isTenantAdmin) {
                    $group->selectRaw('1')
                        ->from('groups as visible_groups')
                        ->whereColumn('visible_groups.id', 'events.group_id')
                        ->where('visible_groups.tenant_id', $tenantId)
                        ->where('visible_groups.status', GroupStatus::Active->value);

                    if ($isTenantAdmin) {
                        return;
                    }

                    $group->where(function ($audience) use ($viewerId, $tenantId) {
                        $audience->whereNull('visible_groups.visibility')
                            ->orWhere('visible_groups.visibility', 'public');

                        if ($viewerId !== null) {
                            $audience->orWhere('visible_groups.owner_id', $viewerId)
                                ->orWhereExists(function ($membership) use ($tenantId, $viewerId) {
                                    $membership->selectRaw('1')
                                        ->from('group_members as event_group_members')
                                        ->whereColumn('event_group_members.group_id', 'visible_groups.id')
                                        ->where('event_group_members.tenant_id', $tenantId)
                                        ->where('event_group_members.user_id', $viewerId)
                                        ->where('event_group_members.status', 'active');
                                });
                        }
                    });
                });
        });

        if ($when === 'upcoming') {
            $query->where('start_time', '>=', $snapshotAt);
        } elseif ($when === 'past') {
            $query->where('start_time', '<', $snapshotAt);
        }

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        if ($seriesId !== null) {
            $query->where('series_id', $seriesId);
        }

        if ($groupId !== null) {
            $query->where('group_id', $groupId);
        }

        if ($organizerId !== null) {
            $query->where('user_id', $organizerId);
        }

        if ($search !== null) {
            $term = '%' . $search . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'LIKE', $term)
                  ->orWhere('description', 'LIKE', $term)
                  ->orWhere('location', 'LIKE', $term);
            });
        }

        if ($stepFree === 'unknown') {
            $query->whereNull('events.accessibility_step_free');
        } elseif ($stepFree !== null) {
            $query->where('events.accessibility_step_free', $stepFree === 'yes');
        }

        // Collapse recurring series so the list shows ONE card per series — the
        // next upcoming occurrence — instead of flooding the page with every
        // occurrence. A row is kept only when no *preferred* sibling exists in
        // the same series within the current time window. Standalone events have
        // no siblings (their series key is their own id), so they always pass.
        // Proximity applies the same collapse boundary to eligible in-radius siblings.
        [$siblingVisibilitySql, $siblingVisibilityBindings] = self::eventGroupVisibilitySql(
            'e2',
            $viewerId,
            $tenantId
        );
        $siblingVisibilityPredicate = self::visibilityPredicate($siblingVisibilitySql);
        $query->whereNotExists(function ($sub) use (
            $when,
            $snapshotAt,
            $siblingVisibilityPredicate,
            $siblingVisibilityBindings,
            $categoryId,
            $seriesId,
            $groupId,
            $organizerId,
            $search,
            $stepFree,
            $hasProximity,
            $nearLat,
            $nearLng,
            $radiusKm
        ) {
            $sub->selectRaw('1')
                ->from('events as e2')
                ->whereColumn('e2.tenant_id', 'events.tenant_id')
                ->whereRaw('COALESCE(e2.parent_event_id, e2.id) = COALESCE(events.parent_event_id, events.id)')
                ->whereColumn('e2.id', '!=', 'events.id')
                ->where(function ($status) {
                    $status->whereNull('e2.status')->orWhere('e2.status', 'active');
                })
                ->whereRaw($siblingVisibilityPredicate, $siblingVisibilityBindings);

            if ($categoryId !== null) {
                $sub->where('e2.category_id', $categoryId);
            }
            if ($seriesId !== null) {
                $sub->where('e2.series_id', $seriesId);
            }
            if ($groupId !== null) {
                $sub->where('e2.group_id', $groupId);
            }
            if ($organizerId !== null) {
                $sub->where('e2.user_id', $organizerId);
            }
            if ($search !== null) {
                $term = '%' . $search . '%';
                $sub->where(function ($text) use ($term) {
                    $text->where('e2.title', 'LIKE', $term)
                        ->orWhere('e2.description', 'LIKE', $term)
                        ->orWhere('e2.location', 'LIKE', $term);
                });
            }
            if ($stepFree === 'unknown') {
                $sub->whereNull('e2.accessibility_step_free');
            } elseif ($stepFree !== null) {
                $sub->where('e2.accessibility_step_free', $stepFree === 'yes');
            }
            if ($hasProximity) {
                $siblingHaversine = '(6371 * acos(LEAST(1.0, GREATEST(-1.0, '
                    . 'cos(radians(?)) * cos(radians(e2.latitude)) * cos(radians(e2.longitude) - radians(?)) + '
                    . 'sin(radians(?)) * sin(radians(e2.latitude))'
                    . '))))';
                $sub->whereNotNull('e2.latitude')
                    ->whereNotNull('e2.longitude')
                    ->whereRaw("{$siblingHaversine} <= ?", [$nearLat, $nearLng, $nearLat, $radiusKm]);
            }

            if ($when === 'past') {
                $sub->where('e2.start_time', '<', $snapshotAt)
                    ->whereRaw('(e2.start_time > events.start_time OR (e2.start_time = events.start_time AND e2.id > events.id))');
            } elseif ($when === 'all') {
                $sub->whereRaw(
                    '((e2.start_time >= ? AND (events.start_time < ? OR e2.start_time < events.start_time OR (e2.start_time = events.start_time AND e2.id < events.id)))'
                    . ' OR (e2.start_time < ? AND events.start_time < ? AND (e2.start_time > events.start_time OR (e2.start_time = events.start_time AND e2.id > events.id))))',
                    [$snapshotAt, $snapshotAt, $snapshotAt, $snapshotAt]
                );
            } else {
                $sub->where('e2.start_time', '>=', $snapshotAt)
                    ->whereRaw('(e2.start_time < events.start_time OR (e2.start_time = events.start_time AND e2.id < events.id))');
            }
        });

        if (!$hasProximity && $cursorPosition !== null) {
            $cursorStart = self::validateDiscoveryCursorDate($cursorPosition['start'] ?? null);
            $cursorId = self::validateDiscoveryCursorId($cursorPosition['id'] ?? null);
            $ascending = $when === 'upcoming';
            $query->where(function (Builder $position) use ($cursorStart, $cursorId, $ascending) {
                $operator = $ascending ? '>' : '<';
                $position->where('start_time', $operator, $cursorStart)
                    ->orWhere(function (Builder $tie) use ($cursorStart, $cursorId, $operator) {
                        $tie->where('start_time', $cursorStart)
                            ->where('id', $operator, $cursorId);
                    });
            });
        }

        if ($hasProximity) {
            $haversine = 'ROUND((6371 * acos(LEAST(1.0, GREATEST(-1.0, '
                . 'cos(radians(?)) * cos(radians(events.latitude)) * cos(radians(events.longitude) - radians(?)) + '
                . 'sin(radians(?)) * sin(radians(events.latitude))'
                . ')))), 6)';
            $query->selectRaw("events.*, {$haversine} AS distance_km", [$nearLat, $nearLng, $nearLat])
                  ->whereNotNull('events.latitude')
                  ->whereNotNull('events.longitude')
                  ->having('distance_km', '<=', $radiusKm);

            if ($cursorPosition !== null) {
                $cursorDistance = self::validateDiscoveryCursorDistance($cursorPosition['distance'] ?? null);
                $cursorStart = self::validateDiscoveryCursorDate($cursorPosition['start'] ?? null);
                $cursorId = self::validateDiscoveryCursorId($cursorPosition['id'] ?? null);
                $query->havingRaw(
                    '(distance_km > ? OR (distance_km = ? AND (events.start_time > ? OR (events.start_time = ? AND events.id > ?))))',
                    [$cursorDistance, $cursorDistance, $cursorStart, $cursorStart, $cursorId]
                );
            }

            $query->orderBy('distance_km')->orderBy('start_time')->orderBy('id');
            $items = $query->limit($limit + 1)->get();
        } else {
            if ($when === 'upcoming') {
                $query->orderBy('start_time')->orderBy('id');
            } else {
                $query->orderByDesc('start_time')->orderByDesc('id');
            }
            $items = $query->limit($limit + 1)->get();
        }

        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        $eventIds = $items->pluck('id');
        $rsvpCounts = EventRsvp::query()
            ->selectRaw('event_id, COUNT(*) as count')
            ->whereIn('event_id', $eventIds)
            ->where('status', 'going')
            ->groupBy('event_id')
            ->pluck('count', 'event_id');

        // Also get interested counts for the same events
        $interestedCounts = EventRsvp::query()
            ->selectRaw('event_id, COUNT(*) as count')
            ->whereIn('event_id', $eventIds)
            ->where('status', 'interested')
            ->groupBy('event_id')
            ->pluck('count', 'event_id');

        // Series metadata for the collapsed representatives, so the card can show
        // "Repeats weekly · N dates". Keyed by series root (parent_event_id ?? id).
        $tenantIdForSeries = $tenantId;
        $seriesRoots = [];
        foreach ($items as $it) {
            if (! empty($it->is_recurring_template) || ! empty($it->parent_event_id)) {
                $seriesRoots[(int) ($it->parent_event_id ?? $it->id)] = true;
            }
        }
        $seriesRoots = array_keys($seriesRoots);
        $seriesCounts = [];
        $seriesFreq = [];
        if (! empty($seriesRoots)) {
            [$seriesVisibilitySql, $seriesVisibilityBindings] = self::eventGroupVisibilitySql(
                'series_events',
                $viewerId,
                $tenantIdForSeries
            );
            $countQuery = DB::table('events as series_events')
                ->selectRaw('COALESCE(series_events.parent_event_id, series_events.id) AS skey, COUNT(*) AS c')
                ->where('series_events.tenant_id', $tenantIdForSeries)
                ->where(function ($status) {
                    $status->whereNull('series_events.status')->orWhere('series_events.status', 'active');
                })
                ->where(function ($roots) use ($seriesRoots) {
                    $roots->whereIn('series_events.id', $seriesRoots)
                        ->orWhereIn('series_events.parent_event_id', $seriesRoots);
                })
                ->whereRaw(self::visibilityPredicate($seriesVisibilitySql), $seriesVisibilityBindings);
            if ($when === 'past') {
                $countQuery->where('series_events.start_time', '<', $snapshotAt);
            } elseif ($when === 'upcoming') {
                $countQuery->where('series_events.start_time', '>=', $snapshotAt);
            }
            $countRows = $countQuery
                ->groupByRaw('COALESCE(series_events.parent_event_id, series_events.id)')
                ->get();
            foreach ($countRows as $row) {
                $seriesCounts[(int) $row->skey] = (int) $row->c;
            }
            $placeholders = implode(',', array_fill(0, count($seriesRoots), '?'));
            $freqRows = DB::select(
                "SELECT event_id, frequency FROM event_recurrence_rules
                 WHERE tenant_id = ? AND event_id IN ($placeholders)",
                array_merge([$tenantIdForSeries], $seriesRoots)
            );
            foreach ($freqRows as $row) {
                $seriesFreq[(int) $row->event_id] = $row->frequency;
            }
        }

        $result = $items->map(function (Event $event) use ($rsvpCounts, $interestedCounts, $seriesCounts, $seriesFreq) {
            $data = $event->toArray();

            if (! empty($event->is_recurring_template) || ! empty($event->parent_event_id)) {
                $root = (int) ($event->parent_event_id ?? $event->id);
                $data['is_series'] = true;
                $data['series_count'] = $seriesCounts[$root] ?? 1;
                $data['recurrence_frequency'] = $seriesFreq[$root] ?? null;
            }
            $goingCount = (int) ($rsvpCounts[$event->id] ?? 0);
            $interestedCount = (int) ($interestedCounts[$event->id] ?? 0);
            $maxAttendees = $event->max_attendees;

            // Frontend field names (with legacy aliases)
            $data['attendee_count'] = $goingCount;
            $data['attendees_count'] = $goingCount;
            $data['interested_count'] = $interestedCount;
            $data['rsvp_counts'] = ['going' => $goingCount, 'interested' => $interestedCount];
            $data['spots_left'] = $maxAttendees ? max(0, $maxAttendees - $goingCount) : null;
            $data['is_full'] = $maxAttendees ? ($goingCount >= $maxAttendees) : false;
            if (isset($event->distance_km)) {
                $data['distance_km'] = round((float) $event->distance_km, 2);
            }

            return $data;
        })->all();

        $nextCursor = null;
        if ($hasMore && $items->isNotEmpty()) {
            /** @var Event $lastEvent */
            $lastEvent = $items->last();
            $position = [
                'start' => self::eventCursorStart($lastEvent),
                'id' => (int) $lastEvent->id,
            ];
            if ($hasProximity) {
                $position['distance'] = sprintf('%.6F', (float) $lastEvent->distance_km);
            }
            $nextCursor = EventDiscoveryCursor::encode(
                $cursorKind,
                $queryIdentity,
                $snapshotAt,
                $position
            );
        }

        $result = self::redactOnlineAccessForEvents(array_values($result), $viewerId);

        return [
            'items'    => $result,
            'cursor'   => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /** @param array<string, mixed> $filters */
    private static function resolveDiscoveryCategoryId(array $filters): ?int
    {
        $field = 'category_id';
        $raw = $filters['category_id'] ?? null;
        if ($raw === null || $raw === '') {
            $field = 'category';
            $raw = $filters['category'] ?? null;
        }
        if ($raw === null || $raw === '') {
            return null;
        }
        if (!is_scalar($raw)) {
            throw ValidationException::withMessages([
                $field => [__('validation.string', ['attribute' => $field])],
            ]);
        }

        $query = DB::table('categories')
            ->where('tenant_id', (int) TenantContext::getId())
            ->whereIn('type', ['event', 'events'])
            ->where('is_active', 1);
        $value = trim((string) $raw);
        if (preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            $query->where('id', (int) $value);
        } else {
            $query->where(function ($category) use ($value) {
                $category->where('slug', $value)->orWhere('name', $value);
            });
        }

        $categoryId = $query->value('id');
        if ($categoryId === null) {
            throw ValidationException::withMessages([
                $field => [__('validation.exists', ['attribute' => $field])],
            ]);
        }

        return (int) $categoryId;
    }

    private static function resolveDiscoverySeriesId(mixed $value): ?int
    {
        $seriesId = self::resolveDiscoveryPositiveId($value, 'series_id');
        if ($seriesId === null) {
            return null;
        }

        if (!DB::table('event_series')
            ->where('id', $seriesId)
            ->where('tenant_id', (int) TenantContext::getId())
            ->exists()) {
            throw ValidationException::withMessages([
                'series_id' => [__('validation.exists', ['attribute' => 'series_id'])],
            ]);
        }

        return $seriesId;
    }

    private static function resolveDiscoveryPositiveId(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_int($value) && (!is_string($value) || preg_match('/^[1-9][0-9]*$/', $value) !== 1)) {
            throw ValidationException::withMessages([
                $field => [__('validation.integer', ['attribute' => $field])],
            ]);
        }

        $integer = (int) $value;
        if ($integer < 1) {
            throw ValidationException::withMessages([
                $field => [__('validation.min.numeric', ['attribute' => $field, 'min' => 1])],
            ]);
        }

        return $integer;
    }

    private static function resolveDiscoverySearch(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw ValidationException::withMessages([
                'q' => [__('validation.string', ['attribute' => 'q'])],
            ]);
        }

        $search = trim($value);
        if ($search === '') {
            return null;
        }
        if (mb_strlen($search) > 200) {
            throw ValidationException::withMessages([
                'q' => [__('validation.max.string', ['attribute' => 'q', 'max' => 200])],
            ]);
        }

        return $search;
    }

    /** @return 'yes'|'no'|'unknown'|null */
    private static function resolveDiscoveryStepFree(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw ValidationException::withMessages([
                'step_free' => [__('validation.string', ['attribute' => 'step_free'])],
            ]);
        }

        $stepFree = trim($value);
        if ($stepFree === '' || $stepFree === 'any') {
            return null;
        }
        if (!in_array($stepFree, ['yes', 'no', 'unknown'], true)) {
            throw ValidationException::withMessages([
                'step_free' => [__('validation.in', ['attribute' => 'step_free'])],
            ]);
        }

        return $stepFree;
    }

    private static function resolveDiscoveryFloat(mixed $value, string $field, float $min, float $max): float
    {
        if (!is_scalar($value) || !is_numeric((string) $value)) {
            throw ValidationException::withMessages([
                $field => [__('validation.numeric', ['attribute' => $field])],
            ]);
        }

        $number = (float) $value;
        if (!is_finite($number) || $number < $min || $number > $max) {
            throw ValidationException::withMessages([
                $field => [__('validation.between.numeric', [
                    'attribute' => $field,
                    'min' => $min,
                    'max' => $max,
                ])],
            ]);
        }

        return $number;
    }

    /** @param array<string, int|string|null> $scope */
    private static function discoveryQueryIdentity(array $scope): string
    {
        return hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private static function validateDiscoveryCursorDate(mixed $value): string
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) !== 1) {
            self::rejectDiscoveryCursor();
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        if ($date === false || $date->format('Y-m-d H:i:s') !== $value) {
            self::rejectDiscoveryCursor();
        }

        return $value;
    }

    private static function validateDiscoveryCursorId(mixed $value): int
    {
        if ((!is_int($value) && (!is_string($value) || preg_match('/^[1-9][0-9]*$/', $value) !== 1)) || (int) $value < 1) {
            self::rejectDiscoveryCursor();
        }

        return (int) $value;
    }

    private static function validateDiscoveryCursorDistance(mixed $value): float
    {
        if (!is_scalar($value) || !is_numeric((string) $value)) {
            self::rejectDiscoveryCursor();
        }

        $distance = (float) $value;
        if (!is_finite($distance) || $distance < 0) {
            self::rejectDiscoveryCursor();
        }

        return $distance;
    }

    private static function eventCursorStart(Event $event): string
    {
        $raw = $event->getRawOriginal('start_time');

        return self::validateDiscoveryCursorDate(
            $raw instanceof \DateTimeInterface ? $raw->format('Y-m-d H:i:s') : (string) $raw
        );
    }

    private static function visibilityPredicate(string $visibilitySql): string
    {
        return preg_replace('/^\s*AND\s+/i', '', $visibilitySql) ?? $visibilitySql;
    }

    /** @throws ValidationException */
    private static function rejectDiscoveryCursor(): never
    {
        throw ValidationException::withMessages([
            'cursor' => [__('api.invalid_cursor')],
        ]);
    }

    /**
     * Get a single event by ID without embedding attendee identities.
     */
    public static function getById(int $id, ?int $currentUserId = null): ?array
    {
        /** @var Event|null $event */
        $event = Event::query()
            ->with([
                'user:id,first_name,last_name,organization_name,profile_type,avatar_url',
                'category',
                'group',
            ])
            ->find($id);

        $tenantId = (int) TenantContext::getId();
        $viewer = self::policyUser($currentUserId, $tenantId);
        $policy = app(EventPolicy::class);
        if (! $event || $viewer === null || ! $policy->view($viewer, $event)) {
            return null;
        }

        $data = $event->toArray();

        // Replace eager-loaded user relation with safe public fields only
        $eventUser = $event->user;
        if ($eventUser) {
            $data['user'] = [
                'id'         => $eventUser->id,
                'name'       => ($eventUser->profile_type === 'organisation' && $eventUser->organization_name)
                                    ? $eventUser->organization_name
                                    : trim($eventUser->first_name . ' ' . $eventUser->last_name),
                'avatar'     => $eventUser->avatar_url,
                'avatar_url' => $eventUser->avatar_url,
            ];
        }
        // Counts remain useful on event detail, but RSVP rows and user
        // relationships are deliberately never serialized into the event DTO.
        unset($data['rsvps']);
        $rsvpCounts = DB::table('event_rsvps')
            ->where('event_id', $id)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['going', 'interested'])
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $goingCount = (int) ($rsvpCounts['going'] ?? 0);
        $interestedCount = (int) ($rsvpCounts['interested'] ?? 0);
        $maxAttendees = $event->max_attendees;

        // Frontend field names (with legacy aliases)
        $data['attendee_count'] = $goingCount;
        $data['attendees_count'] = $goingCount;
        $data['interested_count'] = $interestedCount;
        $data['rsvp_counts'] = ['going' => $goingCount, 'interested' => $interestedCount];
        $data['spots_left'] = $maxAttendees ? max(0, $maxAttendees - $goingCount) : null;
        $data['is_full'] = $maxAttendees ? ($goingCount >= $maxAttendees) : false;

        if ($currentUserId) {
            $data['my_rsvp'] = DB::table('event_rsvps')
                ->where('event_id', $id)
                ->where('tenant_id', $tenantId)
                ->where('user_id', $currentUserId)
                ->value('status');
        }

        // Recurring-series metadata + upcoming dates so the detail page can show
        // the full schedule that the collapsed list card links through to.
        if (! empty($event->is_recurring_template) || ! empty($event->parent_event_id)) {
            $rootId = (int) ($event->parent_event_id ?? $event->id);
            $data['is_series'] = true;

            $freq = DB::selectOne(
                "SELECT frequency FROM event_recurrence_rules WHERE tenant_id = ? AND event_id = ?",
                [$tenantId, $rootId]
            );
            $data['recurrence_frequency'] = $freq->frequency ?? null;

            // A recurrence can contain independently moderated occurrences.
            // Never let one visible occurrence reveal draft/private sibling IDs
            // or timestamps: evaluate the complete candidate set through the
            // same policy used for direct event reads before applying the UI cap.
            $occurrenceModels = Event::query()
                ->where('tenant_id', $tenantId)
                ->where(static function (Builder $series) use ($rootId): void {
                    $series->whereKey($rootId)->orWhere('parent_event_id', $rootId);
                })
                ->where('start_time', '>=', now())
                ->orderBy('start_time')
                ->orderBy('id')
                ->get([
                    'id',
                    'tenant_id',
                    'user_id',
                    'group_id',
                    'status',
                    'publication_status',
                    'operational_status',
                    'is_recurring_template',
                    'start_time',
                    'occurrence_date',
                ]);
            $visibleOccurrenceIds = self::policyVisibleEventIdMap(
                $occurrenceModels,
                $viewer,
                $policy,
            );
            $visibleOccurrences = $occurrenceModels
                ->filter(static fn (Event $occurrence): bool => isset(
                    $visibleOccurrenceIds[(int) $occurrence->getKey()],
                ))
                ->take(50)
                ->values();
            $data['series_occurrences'] = $visibleOccurrences
                ->map(static fn (Event $occurrence): array => [
                    'id' => (int) $occurrence->getKey(),
                    'start_time' => $occurrence->getRawOriginal('start_time'),
                    'date' => $occurrence->getRawOriginal('occurrence_date'),
                ])
                ->all();
            $data['series_count'] = $visibleOccurrences->count();
        }

        return self::redactOnlineAccessForEvents([$data], $currentUserId)[0];
    }

    /**
     * Create a new event.
     *
     * @throws ValidationException
     */
    public static function create(int $userId, array $data): Event
    {
        $tenantId = (int) TenantContext::getId();
        $isRecurrenceTemplate = filter_var(
            $data['_is_recurring_template'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
        $normalized = self::validateAndNormalizeWriterData($data, null);

        $event = DB::transaction(function () use (
            $userId,
            $data,
            $tenantId,
            $normalized,
            $isRecurrenceTemplate,
        ): Event {
            $event = new Event([
                'user_id'              => $userId,
                'title'                => $normalized['title'],
                'description'          => $normalized['description'],
                'start_time'           => $normalized['start_time'],
                'end_time'             => $normalized['end_time'],
                'location'             => $normalized['location'],
                'latitude'             => $normalized['latitude'],
                'longitude'            => $normalized['longitude'],
                'category_id'          => self::resolveCategoryId($data),
                'group_id'             => self::resolveGroupId($normalized['group_id'], $userId),
                'max_attendees'        => $normalized['max_attendees'],
                'is_online'            => $normalized['is_online'],
                'online_link'          => $normalized['online_link'],
                'allow_remote_attendance' => $normalized['allow_remote_attendance'],
                'video_url'            => $normalized['video_url'],
                'image_url'            => $normalized['image_url'],
                'cover_image'          => $normalized['cover_image'],
                'series_id'            => self::resolveSeriesId($normalized['series_id'], $userId),
                'federated_visibility' => $normalized['federated_visibility'],
                'accessibility_step_free' => $normalized['accessibility_step_free'],
                'accessibility_toilet' => $normalized['accessibility_toilet'],
                'accessibility_hearing_loop' => $normalized['accessibility_hearing_loop'],
                'accessibility_quiet_space' => $normalized['accessibility_quiet_space'],
                'accessibility_seating' => $normalized['accessibility_seating'],
                'accessibility_parking' => $normalized['accessibility_parking'],
                'accessibility_parking_details' => $normalized['accessibility_parking_details'],
                'accessibility_transit_details' => $normalized['accessibility_transit_details'],
                'accessibility_assistance_contact' => $normalized['accessibility_assistance_contact'],
                'accessibility_notes' => $normalized['accessibility_notes'],
            ]);

            $event->forceFill([
                'status' => 'draft',
                'publication_status' => EventPublicationState::Draft->value,
                'operational_status' => EventOperationalState::Scheduled->value,
                'lifecycle_version' => 0,
                'calendar_sequence' => 0,
                'publication_status_changed_at' => now(),
                'publication_status_changed_by' => $userId,
                'operational_status_changed_at' => now(),
                'operational_status_changed_by' => $userId,
                'timezone' => $normalized['timezone'],
                'timezone_source' => $normalized['timezone_source'],
                'all_day' => $normalized['all_day'],
                'is_recurring_template' => $isRecurrenceTemplate ? 1 : 0,
                'occurrence_key' => null,
                'recurrence_engine' => $isRecurrenceTemplate ? self::RECURRENCE_ENGINE : null,
                'recurrence_engine_version' => $isRecurrenceTemplate
                    ? self::RECURRENCE_ENGINE_VERSION
                    : null,
            ]);

            $event->save();

            if (! $isRecurrenceTemplate) {
                $event->forceFill([
                    'occurrence_key' => self::newOccurrenceKey($tenantId, (int) $event->id),
                ])->save();
            }

            return $event->fresh(['user', 'category', 'series']) ?? $event;
        });
        TenantContext::setById($tenantId);

        return $event;
    }

    /**
     * Update an existing event.
     *
     * @param int   $id     Event ID
     * @param int   $userId Authenticated user requesting the update
     * @param array $data   Fields to update
     * @return bool True on success, false on error (check getErrors())
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        self::$errors = [];
        self::$lastMeaningfulUpdateChanges = [];
        $tenantId = (int) TenantContext::getId();

        /** @var Event|null $event */
        $event = Event::query()->find($id);

        if (! $event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.event_not_found')];
            return false;
        }

        if (! self::policyAllows($event, $userId, 'manage')) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.event_edit_forbidden')];
            return false;
        }

        $normalized = self::validateAndNormalizeWriterData($data, $event);

        // Resolve category_name to category_id, and tenant-validate any raw
        // caller-supplied category_id before it reaches fill() below.
        if (array_key_exists('category_name', $data) || array_key_exists('category_id', $data)) {
            $normalized['category_id'] = self::resolveCategoryId($data);
        }
        if (array_key_exists('series_id', $data)) {
            $normalized['series_id'] = self::resolveSeriesId($normalized['series_id'], $userId);
        }
        if (array_key_exists('group_id', $data)) {
            $normalized['group_id'] = self::resolveGroupId($normalized['group_id'], $userId);
        }
        $accessibilityColumns = array_merge(
            array_values(self::VENUE_ACCESSIBILITY_BOOLEAN_FIELDS),
            array_column(self::VENUE_ACCESSIBILITY_TEXT_FIELDS, 'column'),
        );
        $accessibilityTouched = array_key_exists('venue_accessibility', $data);

        $meaningfulKeys = [
            'title',
            'start_time',
            'end_time',
            'timezone',
            'all_day',
            'location',
            'is_online',
            'online_link',
            'allow_remote_attendance',
            'max_attendees',
        ];
        $calendarKeys = [
            'title',
            'description',
            'start_time',
            'end_time',
            'timezone',
            'all_day',
            'location',
            'group_id',
            'is_online',
            'online_link',
            'allow_remote_attendance',
            'max_attendees',
        ];
        $original = [];
        foreach ($meaningfulKeys as $key) {
            $original[$key] = $event->getAttribute($key);
        }

        $event->forceFill($normalized);
        $federationVisibleFields = [
            'title',
            'start_time',
            'end_time',
            'timezone',
            'all_day',
            'location',
            'latitude',
            'longitude',
            'is_online',
            'federated_visibility',
        ];
        // validateAndNormalizeWriterData() returns only caller-touched fields
        // (plus their coupled time fields). Restrict the pre-save dirty check to
        // that exact set so legacy/cast representation differences on untouched
        // columns cannot advance federation versions or enqueue duplicate facts.
        $federationTouchedFields = array_values(array_intersect(
            $federationVisibleFields,
            array_keys($normalized),
        ));
        $federationVisibleMutation = Schema::hasColumn('events', 'federation_version')
            && $federationTouchedFields !== []
            && $event->isDirty($federationTouchedFields);
        if ($event->isDirty($calendarKeys)) {
            $event->forceFill([
                'calendar_sequence' => max(
                    0,
                    (int) $event->getRawOriginal('calendar_sequence'),
                ) + 1,
            ]);
        }
        foreach ($meaningfulKeys as $key) {
            if (!array_key_exists($key, $normalized)) {
                continue;
            }

            $before = self::normalizeEventChangeValue($key, $original[$key] ?? null);
            $after = self::normalizeEventChangeValue($key, $event->getAttribute($key));
            if ($before !== $after) {
                // The online URL is deliberately represented only by a change
                // marker. Notification payloads must never become a side door
                // around the event-detail reveal window and entitlement policy.
                self::$lastMeaningfulUpdateChanges[$key] = $key === 'online_link'
                    ? true
                    : $event->getAttribute($key);
            }
        }
        if ($accessibilityTouched && $event->isDirty($accessibilityColumns)) {
            self::$lastMeaningfulUpdateChanges['venue_accessibility'] = true;
        }

        DB::transaction(function () use (
            $event,
            $tenantId,
            $id,
            $userId,
            $data,
            $federationVisibleMutation,
        ): void {
            if ($federationVisibleMutation) {
                $currentFederationVersion = DB::table('events')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->value('federation_version');
                $event->forceFill([
                    'federation_version' => max(1, (int) ($currentFederationVersion ?? 1)) + 1,
                ]);
            }
            $event->save();

            if ($federationVisibleMutation
                && Schema::hasTable('event_federation_deliveries')
                && Schema::hasTable('federation_external_partners')) {
                app(EventFederationPublisher::class)->publish($event);
            }
            if (self::$lastMeaningfulUpdateChanges === [] || ! self::isPublishedEvent($event)) {
                return;
            }

            $changedFields = array_values(array_keys(self::$lastMeaningfulUpdateChanges));
            sort($changedFields);
            $sequence = max(1, (int) $event->getAttribute('calendar_sequence'));
            $scope = (string) ($data['recurrence_scope'] ?? $data['scope'] ?? 'single');
            if (! in_array($scope, ['single', 'all'], true)) {
                $scope = 'single';
            }
            app(EventDomainOutboxService::class)->record(
                $tenantId,
                $id,
                $sequence,
                'event.updated',
                "event-update:{$tenantId}:{$id}:calendar:{$sequence}",
                [
                    'schema_version' => 1,
                    'tenant_id' => $tenantId,
                    'event_id' => $id,
                    'actor_user_id' => $userId,
                    'organizer_user_id' => (int) $event->getAttribute('user_id'),
                    'calendar_sequence' => $sequence,
                    'changed_fields' => $changedFields,
                    'recurrence_scope' => $scope,
                    'occurred_at' => now()->toIso8601String(),
                ],
            );
        }, 3);

        if (! self::isPublishedEvent($event)) {
            self::$lastMeaningfulUpdateChanges = [];
            TenantContext::setById($tenantId);
            return true;
        }

        TenantContext::setById($tenantId);

        return true;
    }

    /**
     * Validate the complete writer contract and return only mutable event
     * attributes. Server-owned lifecycle and occurrence identity fields are
     * never copied from the request.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function validateAndNormalizeWriterData(array $data, ?Event $event): array
    {
        $tenantId = (int) TenantContext::getId();
        $isCreate = $event === null;
        $input = $data;

        foreach ([
            'title', 'description', 'location', 'timezone', 'online_link',
            'video_url', 'image_url', 'cover_image',
        ] as $field) {
            if (array_key_exists($field, $input) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        foreach (['location', 'online_link', 'video_url', 'image_url', 'cover_image'] as $field) {
            if (($input[$field] ?? null) === '') {
                $input[$field] = null;
            }
        }

        [$defaultTimezone, $defaultTimezoneSource] = self::defaultEventTimezone($tenantId);
        $storedTimezone = $event !== null
            ? trim((string) ($event->getRawOriginal('timezone') ?? ''))
            : '';
        $storedTimezoneSource = $event !== null
            ? trim((string) ($event->getRawOriginal('timezone_source') ?? ''))
            : '';
        $explicitTimezone = array_key_exists('timezone', $input);
        $timezone = $explicitTimezone
            ? (is_string($input['timezone']) ? $input['timezone'] : '')
            : (self::isIanaTimezone($storedTimezone) ? $storedTimezone : $defaultTimezone);
        $timezoneSource = $explicitTimezone
            ? 'event_input'
            : (self::isIanaTimezone($storedTimezone)
                ? ($storedTimezoneSource !== '' ? $storedTimezoneSource : 'preexisting_unverified')
                : $defaultTimezoneSource);

        $existing = static fn (Event $model, string $field): mixed => $model->getRawOriginal($field);
        $effective = [
            'title' => array_key_exists('title', $input)
                ? $input['title']
                : ($event !== null ? $existing($event, 'title') : null),
            'description' => array_key_exists('description', $input)
                ? $input['description']
                : ($event !== null ? $existing($event, 'description') : null),
            'start_time' => array_key_exists('start_time', $input)
                ? $input['start_time']
                : ($event !== null ? $existing($event, 'start_time') : null),
            'end_time' => array_key_exists('end_time', $input)
                ? $input['end_time']
                : ($event !== null ? $existing($event, 'end_time') : null),
            'location' => array_key_exists('location', $input)
                ? $input['location']
                : ($event !== null ? $existing($event, 'location') : null),
            'latitude' => array_key_exists('latitude', $input)
                ? $input['latitude']
                : ($event !== null ? $existing($event, 'latitude') : null),
            'longitude' => array_key_exists('longitude', $input)
                ? $input['longitude']
                : ($event !== null ? $existing($event, 'longitude') : null),
            'category_id' => array_key_exists('category_id', $input)
                ? $input['category_id']
                : ($event !== null ? $existing($event, 'category_id') : null),
            'series_id' => array_key_exists('series_id', $input)
                ? $input['series_id']
                : ($event !== null ? $existing($event, 'series_id') : null),
            'group_id' => array_key_exists('group_id', $input)
                ? $input['group_id']
                : ($event !== null ? $existing($event, 'group_id') : null),
            'max_attendees' => array_key_exists('max_attendees', $input)
                ? $input['max_attendees']
                : ($event !== null ? $existing($event, 'max_attendees') : null),
            'is_online' => array_key_exists('is_online', $input)
                ? $input['is_online']
                : ($event !== null ? $existing($event, 'is_online') : false),
            'allow_remote_attendance' => array_key_exists('allow_remote_attendance', $input)
                ? $input['allow_remote_attendance']
                : ($event !== null ? $existing($event, 'allow_remote_attendance') : false),
            'online_link' => array_key_exists('online_link', $input)
                ? $input['online_link']
                : ($event !== null ? $existing($event, 'online_link') : null),
            'video_url' => array_key_exists('video_url', $input)
                ? $input['video_url']
                : ($event !== null ? $existing($event, 'video_url') : null),
            'image_url' => array_key_exists('image_url', $input)
                ? $input['image_url']
                : ($event !== null ? $existing($event, 'image_url') : null),
            'cover_image' => array_key_exists('cover_image', $input)
                ? $input['cover_image']
                : ($event !== null ? $existing($event, 'cover_image') : null),
            'federated_visibility' => array_key_exists('federated_visibility', $input)
                ? $input['federated_visibility']
                : ($event !== null ? $existing($event, 'federated_visibility') : 'none'),
            'timezone' => $timezone,
            'all_day' => array_key_exists('all_day', $input)
                ? $input['all_day']
                : ($event !== null ? ($existing($event, 'all_day') ?? false) : false),
        ];

        $occupiedCapacity = 0;
        if ($event !== null && array_key_exists('max_attendees', $input) && $input['max_attendees'] !== null) {
            $occupiedCapacity = app(EventPeopleService::class)->capacityOccupiedCount($event);
        }

        $rules = [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string', 'min:1', 'max:' . self::MAX_EVENT_DESCRIPTION_LENGTH],
            'start_time' => ['required', 'date'],
            'end_time' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'max_attendees' => [
                'nullable', 'integer', 'min:' . max(1, $occupiedCapacity),
                'max:' . self::MAX_EVENT_CAPACITY,
            ],
            'is_online' => ['required', 'boolean'],
            'allow_remote_attendance' => ['required', 'boolean'],
            'all_day' => ['required', 'boolean'],
            'timezone' => [
                'required', 'string', 'max:64',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! self::isIanaTimezone($value)) {
                        $fail(__('validation.timezone', ['attribute' => $attribute]));
                    }
                },
            ],
        ];

        if ($isCreate || array_key_exists('category_id', $input)) {
            $rules['category_id'] = [
                'nullable', 'integer', 'min:1',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->whereIn('type', ['event', 'events'])
                        ->where('is_active', 1)
                ),
            ];
        }
        if ($isCreate || array_key_exists('series_id', $input)) {
            $rules['series_id'] = [
                'nullable', 'integer', 'min:1',
                Rule::exists('event_series', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ];
        }
        if ($isCreate || array_key_exists('group_id', $input)) {
            $rules['group_id'] = [
                'nullable', 'integer', 'min:1',
                Rule::exists('groups', 'id')->where(
                    fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('status', GroupStatus::Active->value)
                ),
            ];
        }
        if ($isCreate || array_key_exists('federated_visibility', $input)) {
            $rules['federated_visibility'] = ['required', Rule::in(['none', 'listed', 'joinable'])];
        }
        foreach ([
            'online_link' => 512,
            'video_url' => 512,
            'image_url' => 512,
            'cover_image' => 255,
        ] as $field => $maxLength) {
            if (! $isCreate && ! array_key_exists($field, $input)) {
                continue;
            }
            $rules[$field] = [
                'nullable', 'string', 'max:' . $maxLength,
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    $valid = is_string($value) && (
                        in_array($attribute, ['image_url', 'cover_image'], true)
                            ? self::isAcceptedImageReference($value)
                            : self::isHttpUrl($value)
                    );
                    if ($value !== null && ! $valid) {
                        $fail(__('validation.url', ['attribute' => $attribute]));
                    }
                },
            ];
        }

        $validated = validator($effective, $rules)->validate();

        $latitudeMissing = $effective['latitude'] === null || $effective['latitude'] === '';
        $longitudeMissing = $effective['longitude'] === null || $effective['longitude'] === '';
        if ($latitudeMissing !== $longitudeMissing) {
            $missingField = $latitudeMissing ? 'latitude' : 'longitude';
            $otherField = $latitudeMissing ? 'longitude' : 'latitude';
            throw ValidationException::withMessages([
                $missingField => [__('validation.required_with', [
                    'attribute' => $missingField,
                    'values' => $otherField,
                ])],
            ]);
        }

        $allDay = (bool) $validated['all_day'];
        $startWasProvided = $isCreate || array_key_exists('start_time', $input);
        $endWasProvided = $isCreate || array_key_exists('end_time', $input);
        $startUtc = $startWasProvided
            ? self::normalizeEventDateInput($effective['start_time'], $timezone, 'start_time')
            : self::normalizeStoredUtcDate($event?->getRawOriginal('start_time'), 'start_time');
        $endUtc = $effective['end_time'] === null
            ? null
            : ($endWasProvided
                ? self::normalizeEventDateInput($effective['end_time'], $timezone, 'end_time')
                : self::normalizeStoredUtcDate($event?->getRawOriginal('end_time'), 'end_time'));

        if ($allDay) {
            if ($endUtc === null) {
                throw ValidationException::withMessages([
                    'end_time' => [__('validation.required', ['attribute' => 'end_time'])],
                ]);
            }
            self::assertAllDayBoundary($startUtc, $timezone, 'start_time');
            self::assertAllDayBoundary($endUtc, $timezone, 'end_time');
        }

        $startInstant = new \DateTimeImmutable($startUtc, new \DateTimeZone('UTC'));
        if ($endUtc !== null) {
            $endInstant = new \DateTimeImmutable($endUtc, new \DateTimeZone('UTC'));
            if ($endInstant <= $startInstant) {
                throw ValidationException::withMessages([
                    'end_time' => [__('api.event_end_after_start')],
                ]);
            }
        }
        if ($startWasProvided && $startInstant <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
            throw ValidationException::withMessages([
                'start_time' => [__('api.event_invalid_start_time')],
            ]);
        }

        $normalized = [];
        foreach ([
            'title', 'description', 'location', 'latitude', 'longitude',
            'category_id', 'group_id', 'series_id', 'max_attendees',
            'is_online', 'allow_remote_attendance', 'online_link', 'video_url',
            'image_url', 'cover_image', 'federated_visibility',
        ] as $field) {
            if ($isCreate || array_key_exists($field, $input)) {
                $normalized[$field] = $effective[$field];
            }
        }

        foreach (['is_online', 'allow_remote_attendance'] as $field) {
            if (array_key_exists($field, $normalized)) {
                $normalized[$field] = (bool) $normalized[$field];
            }
        }
        foreach (['latitude', 'longitude'] as $field) {
            if (array_key_exists($field, $normalized) && $normalized[$field] !== null) {
                $normalized[$field] = (float) $normalized[$field];
            }
        }
        foreach (['category_id', 'group_id', 'series_id', 'max_attendees'] as $field) {
            if (array_key_exists($field, $normalized) && $normalized[$field] !== null) {
                $normalized[$field] = (int) $normalized[$field];
            }
        }

        if ($isCreate || array_key_exists('venue_accessibility', $input)) {
            $accessibility = self::normalizeVenueAccessibilityProfile(
                $input['venue_accessibility'] ?? null,
            );
            $hasPublicVenueFacts = array_filter(
                $accessibility,
                static fn (mixed $value): bool => $value !== null,
            ) !== [];
            if ($hasPublicVenueFacts && ($effective['location'] === null || $effective['location'] === '')) {
                throw ValidationException::withMessages([
                    'location' => [__('validation.required_with', [
                        'attribute' => 'location',
                        'values' => 'venue_accessibility',
                    ])],
                ]);
            }
            $normalized += $accessibility;
        }

        $timeContextChanged = $isCreate
            || array_key_exists('start_time', $input)
            || array_key_exists('end_time', $input)
            || array_key_exists('timezone', $input)
            || array_key_exists('all_day', $input);
        if ($timeContextChanged) {
            $normalized['start_time'] = $startUtc;
            $normalized['end_time'] = $endUtc;
            $normalized['timezone'] = $timezone;
            $normalized['timezone_source'] = $timezoneSource;
            $normalized['all_day'] = $allDay;
        }

        return $normalized;
    }

    /**
     * Replace the complete public venue profile when the nested writer field is
     * supplied. Missing keys become unknown/null; attendee accommodation data is
     * never accepted at this boundary.
     *
     * @return array<string,bool|string|null>
     */
    private static function normalizeVenueAccessibilityProfile(mixed $profile): array
    {
        if (is_array($profile)) {
            foreach (self::VENUE_ACCESSIBILITY_TEXT_FIELDS as $field => $_metadata) {
                if (array_key_exists($field, $profile) && is_string($profile[$field])) {
                    $profile[$field] = trim($profile[$field]);
                    if ($profile[$field] === '') {
                        $profile[$field] = null;
                    }
                }
            }
        }

        $allowed = array_merge(
            array_keys(self::VENUE_ACCESSIBILITY_BOOLEAN_FIELDS),
            array_keys(self::VENUE_ACCESSIBILITY_TEXT_FIELDS),
        );
        $rules = [
            'venue_accessibility' => ['nullable', 'array:' . implode(',', $allowed)],
        ];
        foreach (self::VENUE_ACCESSIBILITY_BOOLEAN_FIELDS as $field => $_column) {
            $rules["venue_accessibility.{$field}"] = ['nullable', 'boolean'];
        }
        foreach (self::VENUE_ACCESSIBILITY_TEXT_FIELDS as $field => $metadata) {
            $rules["venue_accessibility.{$field}"] = [
                'nullable',
                'string',
                'max:' . $metadata['max'],
            ];
        }

        /** @var array{venue_accessibility?:array<string,mixed>|null} $validated */
        $validated = validator(['venue_accessibility' => $profile], $rules)->validate();
        $values = $validated['venue_accessibility'] ?? [];
        $normalized = [];
        foreach (self::VENUE_ACCESSIBILITY_BOOLEAN_FIELDS as $field => $column) {
            $normalized[$column] = array_key_exists($field, $values) && $values[$field] !== null
                ? (bool) $values[$field]
                : null;
        }
        foreach (self::VENUE_ACCESSIBILITY_TEXT_FIELDS as $field => $metadata) {
            $value = $values[$field] ?? null;
            $normalized[$metadata['column']] = is_string($value) && trim($value) !== ''
                ? trim($value)
                : null;
        }

        return $normalized;
    }

    /** @return array{0:string,1:string} */
    private static function defaultEventTimezone(int $tenantId): array
    {
        $setting = app(TenantSettingsService::class)->get($tenantId, 'general.timezone');
        $settingExists = $setting !== null;
        $settingValue = trim((string) $setting);
        if ($settingExists && self::isIanaTimezone($settingValue)) {
            return [$settingValue, 'tenant_setting'];
        }

        $appTimezone = trim((string) config('app.timezone', 'UTC'));
        if (self::isIanaTimezone($appTimezone)) {
            return [
                $appTimezone,
                $settingExists
                    ? 'app_config_invalid_tenant_setting'
                    : 'app_config_missing_tenant_setting',
            ];
        }

        return [
            'UTC',
            $settingExists
                ? 'utc_fallback_invalid_tenant_setting'
                : 'utc_fallback_missing_tenant_setting',
        ];
    }

    private static function isIanaTimezone(string $timezone): bool
    {
        if ($timezone === 'UTC') {
            return true;
        }

        static $identifiers = null;
        $identifiers ??= array_fill_keys(
            \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC),
            true,
        );

        return isset($identifiers[$timezone]);
    }

    private static function normalizeEventDateInput(mixed $value, string $timezone, string $field): string
    {
        $utc = new \DateTimeZone('UTC');
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)->setTimezone($utc)->format('Y-m-d H:i:s');
        }
        if (! is_string($value) || trim($value) === '') {
            self::throwInvalidEventDate($field);
        }

        $text = trim($value);
        $zone = new \DateTimeZone($timezone);
        $expectedLocal = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1) {
            $expectedLocal = $text . ' 00:00:00';
        } elseif (preg_match(
            '/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})(?::(\d{2})(?:\.\d{1,6})?)?$/',
            $text,
            $matches,
        ) === 1) {
            $expectedLocal = $matches[1] . ' ' . $matches[2] . ':' . ($matches[3] ?? '00');
        }

        try {
            $date = new \DateTimeImmutable($text, $expectedLocal !== null ? $zone : null);
            $errors = \DateTimeImmutable::getLastErrors();
            if (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
                self::throwInvalidEventDate($field);
            }
        } catch (\Throwable) {
            self::throwInvalidEventDate($field);
        }

        // PHP silently advances nonexistent wall times across a DST gap. A
        // strict round trip makes that data loss visible to the caller.
        if ($expectedLocal !== null && $date->format('Y-m-d H:i:s') !== $expectedLocal) {
            self::throwInvalidEventDate($field);
        }
        if ($expectedLocal !== null) {
            self::assertUnambiguousLocalEventDate($expectedLocal, $zone, $field);
        }

        return $date->setTimezone($utc)->format('Y-m-d H:i:s');
    }

    private static function assertUnambiguousLocalEventDate(
        string $expectedLocal,
        \DateTimeZone $zone,
        string $field,
    ): void {
        $utc = new \DateTimeZone('UTC');
        $wallClock = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $expectedLocal, $utc);
        if (! $wallClock instanceof \DateTimeImmutable) {
            self::throwInvalidEventDate($field);
        }

        $wallTimestamp = $wallClock->getTimestamp();
        $transitions = $zone->getTransitions($wallTimestamp - 259200, $wallTimestamp + 259200);
        if (! is_array($transitions)) {
            self::throwInvalidEventDate($field);
        }

        $offsets = [];
        foreach ($transitions as $transition) {
            $offsets[(int) ($transition['offset'] ?? 0)] = true;
        }

        $candidateInstants = [];
        foreach (array_keys($offsets) as $offset) {
            $candidateTimestamp = $wallTimestamp - $offset;
            $candidate = (new \DateTimeImmutable('@' . $candidateTimestamp))->setTimezone($zone);
            if ($candidate->format('Y-m-d H:i:s') === $expectedLocal) {
                $candidateInstants[$candidateTimestamp] = true;
            }
        }

        // Zero candidates is a DST gap; two candidates is a fall-back overlap.
        // Neither can safely preserve an organizer's wall-clock intent.
        if (count($candidateInstants) !== 1) {
            self::throwInvalidEventDate($field);
        }
    }

    private static function normalizeStoredUtcDate(mixed $value, string $field): string
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        }
        if (! is_string($value) || trim($value) === '') {
            self::throwInvalidEventDate($field);
        }

        try {
            return (new \DateTimeImmutable(trim($value), new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            self::throwInvalidEventDate($field);
        }
    }

    private static function throwInvalidEventDate(string $field): never
    {
        throw ValidationException::withMessages([
            $field => [__('validation.date', ['attribute' => $field])],
        ]);
    }

    private static function assertAllDayBoundary(string $utcDate, string $timezone, string $field): void
    {
        $local = (new \DateTimeImmutable($utcDate, new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone($timezone));
        if ($local->format('H:i:s') !== '00:00:00') {
            throw ValidationException::withMessages([
                $field => [__('validation.date_format', [
                    'attribute' => $field,
                    'format' => 'YYYY-MM-DD',
                ])],
            ]);
        }
    }

    private static function isHttpUrl(string $value): bool
    {
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private static function isAcceptedImageReference(string $value): bool
    {
        if (self::isHttpUrl($value)) {
            return true;
        }

        // Preserve the two server-owned upload path contracts used by the
        // existing upload endpoints. Arbitrary relative paths and cross-tenant
        // upload namespaces remain rejected.
        if (preg_match('#^/uploads/events/[A-Za-z0-9][A-Za-z0-9._-]*$#', $value) === 1) {
            return true;
        }

        $tenant = TenantContext::get();
        $slug = is_array($tenant) ? trim((string) ($tenant['slug'] ?? '')) : '';
        if ($slug === '') {
            return false;
        }

        return preg_match(
            '#^/uploads/tenants/' . preg_quote($slug, '#') . '/events/[A-Za-z0-9][A-Za-z0-9._-]*$#',
            $value,
        ) === 1;
    }

    private static function newOccurrenceKey(int $tenantId, int $eventId): string
    {
        return "event:{$tenantId}:{$eventId}";
    }

    private static function isPublishedEvent(Event $event): bool
    {
        $publication = strtolower(trim((string) ($event->getRawOriginal('publication_status') ?? '')));
        if ($publication !== '') {
            return $publication === EventPublicationState::Published->value;
        }

        $legacy = strtolower(trim((string) ($event->getRawOriginal('status') ?? 'active')));
        return $legacy === '' || $legacy === 'active';
    }

    private static function isConcretePublishedRegistrationTarget(Event $event): bool
    {
        return empty($event->getRawOriginal('is_recurring_template'))
            && self::isPublishedEvent($event);
    }

    /**
     * Resolve category_id from data — supports both numeric ID and string slug.
     *
     * Both branches are tenant-scoped. A supplied invalid/cross-tenant id fails
     * validation; a legacy name/slug that cannot be resolved remains nullable.
     */
    private static function resolveCategoryId(array $data): ?int
    {
        $tenantId = (int) TenantContext::getId();

        if (!empty($data['category_id'])) {
            $ownedId = DB::table('categories')
                ->where('id', (int) $data['category_id'])
                ->where('tenant_id', $tenantId)
                ->whereIn('type', ['event', 'events'])
                ->where('is_active', 1)
                ->value('id');
            if (!$ownedId) {
                throw ValidationException::withMessages([
                    'category_id' => [__('validation.exists', ['attribute' => 'category_id'])],
                ]);
            }

            return (int) $ownedId;
        }

        if (!empty($data['category_name'])) {
            $categoryName = trim((string) $data['category_name']);
            $category = DB::table('categories')
                ->where('tenant_id', $tenantId)
                ->whereIn('type', ['event', 'events'])
                ->where('is_active', 1)
                ->where(function ($query) use ($categoryName) {
                    $query->where('slug', $categoryName)
                        ->orWhere('name', $categoryName);
                })
                ->value('id');
            return $category ? (int) $category : null;
        }

        return null;
    }

    /** Tenant- and actor-validate an optional named-series association. */
    private static function resolveSeriesId(mixed $seriesId, ?int $actorId = null): ?int
    {
        if ($seriesId === null || $seriesId === '') {
            return null;
        }

        $series = EventSeries::query()
            ->where('id', (int) $seriesId)
            ->where('tenant_id', (int) TenantContext::getId())
            ->first(['id', 'created_by']);

        if ($series === null) {
            throw ValidationException::withMessages([
                'series_id' => [__('validation.exists', ['attribute' => 'series_id'])],
            ]);
        }

        if ($actorId !== null
            && (int) $series->created_by !== $actorId
            && !self::isTenantAdmin($actorId, (int) TenantContext::getId())) {
            throw new AuthorizationException(__('api.event_modify_forbidden'));
        }

        return (int) $series->id;
    }

    /**
     * Tenant- and role-validate an optional group association.
     *
     * Only active group owners/admins and tenant administrators may publish an
     * event into a group. Ordinary membership is intentionally insufficient.
     */
    private static function resolveGroupId(mixed $groupId, int $actorId): ?int
    {
        if ($groupId === null || $groupId === '') {
            return null;
        }

        $tenantId = (int) TenantContext::getId();
        $group = DB::table('groups')
            ->where('id', (int) $groupId)
            ->where('tenant_id', $tenantId)
            ->where('status', GroupStatus::Active->value)
            ->first(['id']);

        if ($group === null) {
            throw ValidationException::withMessages([
                'group_id' => [__('validation.exists', ['attribute' => 'group_id'])],
            ]);
        }

        if (!GroupAccessService::canIntegrate((int) $group->id, $actorId)) {
            throw new AuthorizationException(__('api.group_modify_forbidden'));
        }

        return (int) $group->id;
    }

    /**
     * Archive an event through the canonical lifecycle boundary.
     *
     * The legacy method name is retained for API and accessible-frontend
     * compatibility. No event row or operational evidence is physically deleted.
     */
    public static function delete(
        int $id,
        int $userId,
        ?string $reason = null,
        ?string $idempotencyKey = null,
    ): bool {
        self::resetLifecycleCompatibilityState();

        if (! self::validLifecycleIdempotencyKey($idempotencyKey)) {
            return false;
        }

        $tenantId = (int) TenantContext::getId();
        /** @var Event|null $snapshot */
        $snapshot = Event::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();
        if ($snapshot === null) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.event_not_found')];
            return false;
        }

        try {
            $actor = self::lifecycleActor($userId);
            if (! app(EventPolicy::class)->manage($actor, $snapshot)) {
                throw new EventLifecycleTransitionException('event_lifecycle_authorization_denied');
            }
            $operation = self::transitionLifecycleTargets(
                $snapshot,
                $actor,
                'archive',
                $reason,
            );
            $result = $operation['result'];
            self::captureLifecycleResult(
                'archive',
                $result,
                $idempotencyKey,
                $operation['series'],
            );
            if ($result->changed) {
                self::notifyLifecycleCancellation($result, $reason);
            }

            return true;
        } catch (EventLifecycleTransitionException $exception) {
            self::$errors[] = self::lifecycleCompatibilityError($exception, 'archive');
        } catch (\UnexpectedValueException $exception) {
            self::$errors[] = [
                'code' => 'EVENT_LIFECYCLE_CONFLICT',
                'message' => __('api.invalid_status'),
            ];
        } catch (\Throwable $exception) {
            Log::error('EventService::delete lifecycle error', [
                'event_id' => $id,
                'actor_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
            self::$errors[] = [
                'code' => 'SERVER_ERROR',
                'message' => __('api.delete_failed', ['resource' => 'event']),
            ];
        }

        return false;
    }

    /** @var array Validation error messages */
    private static array $errors = [];

    /**
     * Validate event data and return boolean.
     *
     * @return bool True if valid, false if errors (check getErrors()).
     */
    public static function validate(array $data): bool
    {
        self::$errors = [];

        $title = $data['title'] ?? null;
        $startTime = $data['start_time'] ?? null;
        $endTime = $data['end_time'] ?? null;

        // title is required and max 255
        if ($title === null || $title === '') {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.title_required'), 'field' => 'title'];
        } elseif (mb_strlen($title) > 255) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.event_title_max_255'), 'field' => 'title'];
        }

        // start_time: validate format if provided
        if ($startTime !== null) {
            $parsed = strtotime($startTime);
            if ($parsed === false) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.event_invalid_start_time'), 'field' => 'start_time'];
            }
        }

        // end_time must be after start_time if both provided
        if ($startTime !== null && $endTime !== null) {
            $startParsed = strtotime($startTime);
            $endParsed = strtotime($endTime);
            if ($startParsed !== false && $endParsed !== false && $endParsed <= $startParsed) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.event_end_after_start'), 'field' => 'end_time'];
            }
        }

        return empty(self::$errors);
    }

    // ================================================================
    // CONVERTED FROM LEGACY — Direct DB facade calls
    // ================================================================

    /**
     * Get validation errors from the last operation.
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * @return array<int>
     */
    public static function getLastCancellationRecipientIds(): array
    {
        return self::$lastCancellationRecipientIds;
    }

    public static function getLastLifecycleResult(): ?EventLifecycleTransitionResult
    {
        return self::$lastLifecycleResult;
    }

    /** @return array<string,mixed>|null */
    public static function getLastLifecycleResponse(): ?array
    {
        return self::$lastLifecycleResponse;
    }

    /**
     * Get user's RSVP status for an event.
     */
    public static function getUserRsvp(int $eventId, int $userId): ?string
    {
        $tenantId = \App\Core\TenantContext::getId();
        $row = DB::selectOne(
            "SELECT status FROM event_rsvps WHERE event_id = ? AND user_id = ? AND tenant_id = ?",
            [$eventId, $userId, $tenantId]
        );
        return $row ? $row->status : null;
    }

    /**
     * Batch-load user RSVP statuses for multiple events (avoids N+1).
     *
     * @param  int[] $eventIds
     * @return array<int, string> Map of event_id => status
     */
    public static function getUserRsvpsBatch(array $eventIds, int $userId): array
    {
        if (empty($eventIds)) {
            return [];
        }
        $tenantId = \App\Core\TenantContext::getId();
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $params = array_merge($eventIds, [$userId, $tenantId]);
        $rows = DB::select(
            "SELECT event_id, status FROM event_rsvps WHERE event_id IN ({$placeholders}) AND user_id = ? AND tenant_id = ?",
            $params
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->event_id] = $row->status;
        }
        return $map;
    }

    /**
     * Batch-hydrate the durable facts consumed by negotiated Events resources.
     *
     * @param array<int, array<string, mixed>> $events
     * @return array<int, array<string, mixed>> Map of event id to facts
     */
    public static function getContractFacts(array $events, ?int $viewerId): array
    {
        $tenantId = (int) TenantContext::getId();
        $eventsById = [];
        foreach ($events as $event) {
            $id = isset($event['id']) ? (int) $event['id'] : 0;
            if ($id > 0) {
                $eventsById[$id] = $event;
            }
        }

        if ($eventsById === []) {
            return [];
        }

        $eventIds = array_keys($eventsById);
        $defaultPool = trim((string) config(
            'events.registration.default_capacity_pool_key',
            EventRegistrationCompatibility::DEFAULT_CAPACITY_POOL,
        ));
        if ($defaultPool === '') {
            $defaultPool = EventRegistrationCompatibility::DEFAULT_CAPACITY_POOL;
        }

        $eventModels = Event::query()
            ->whereIn('id', $eventIds)
            ->get([
                'id',
                'tenant_id',
                'user_id',
                'group_id',
                'status',
                'publication_status',
                'operational_status',
                'is_recurring_template',
                'start_time',
            ])
            ->keyBy('id');
        $viewer = self::policyUser($viewerId, $tenantId);
        $eventPolicy = app(EventPolicy::class);
        $policyAbilities = $viewer === null
            ? []
            : $eventPolicy->abilitiesForEvents($viewer, $eventModels->values());
        $rsvpMap = $viewerId !== null ? self::getUserRsvpsBatch($eventIds, $viewerId) : [];

        $canonicalRegistrations = collect();
        if ($viewerId !== null && Schema::hasTable('event_registrations')) {
            $canonicalRegistrations = DB::table('event_registrations')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $viewerId)
                ->where('capacity_pool_key', $defaultPool)
                ->whereIn('event_id', $eventIds)
                ->get(['event_id', 'registration_state'])
                ->keyBy('event_id');
        }

        $canonicalWaitlist = collect();
        if ($viewerId !== null && Schema::hasTable('event_waitlist_entries')) {
            $activeRank = DB::table('event_waitlist_entries as ranked')
                ->selectRaw('COUNT(*)')
                ->whereColumn('ranked.tenant_id', 'viewer_queue.tenant_id')
                ->whereColumn('ranked.event_id', 'viewer_queue.event_id')
                ->whereColumn('ranked.capacity_pool_key', 'viewer_queue.capacity_pool_key')
                ->whereIn('ranked.queue_state', [
                    EventWaitlistQueueState::Waiting->value,
                    EventWaitlistQueueState::Offered->value,
                ])
                ->whereColumn('ranked.queue_sequence', '<=', 'viewer_queue.queue_sequence');
            $canonicalWaitlist = DB::table('event_waitlist_entries as viewer_queue')
                ->where('viewer_queue.tenant_id', $tenantId)
                ->where('viewer_queue.user_id', $viewerId)
                ->where('viewer_queue.capacity_pool_key', $defaultPool)
                ->whereIn('viewer_queue.event_id', $eventIds)
                ->select([
                    'viewer_queue.event_id',
                    'viewer_queue.queue_state',
                    'viewer_queue.offer_expires_at',
                ])
                ->selectSub($activeRank, 'active_position')
                ->get()
                ->keyBy('event_id');
        }

        $legacyWaitlist = collect();
        if ($viewerId !== null && Schema::hasTable('event_waitlist')) {
            $legacyWaitlist = DB::table('event_waitlist')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $viewerId)
                ->whereIn('event_id', $eventIds)
                ->get(['event_id', 'status', 'position'])
                ->keyBy('event_id');
        }

        $canonicalConfirmedCounts = [];
        if (Schema::hasTable('event_registrations')) {
            $canonicalConfirmedCounts = DB::table('event_registrations')
                ->where('tenant_id', $tenantId)
                ->where('capacity_pool_key', $defaultPool)
                ->where('registration_state', EventCapacityRegistrationState::Confirmed->value)
                ->whereIn('event_id', $eventIds)
                ->selectRaw('event_id, COUNT(*) AS aggregate')
                ->groupBy('event_id')
                ->pluck('aggregate', 'event_id')
                ->mapWithKeys(static fn ($count, $eventId): array => [(int) $eventId => (int) $count])
                ->all();
        }
        $legacyConfirmed = DB::table('event_rsvps as legacy')
            ->where('legacy.tenant_id', $tenantId)
            ->whereIn('legacy.event_id', $eventIds)
            ->whereIn('legacy.status', ['going', 'attended']);
        if (Schema::hasTable('event_registrations')) {
            $legacyConfirmed->whereNotExists(static function ($query) use ($defaultPool): void {
                $query->selectRaw('1')
                    ->from('event_registrations as canonical')
                    ->whereColumn('canonical.tenant_id', 'legacy.tenant_id')
                    ->whereColumn('canonical.event_id', 'legacy.event_id')
                    ->whereColumn('canonical.user_id', 'legacy.user_id')
                    ->where('canonical.capacity_pool_key', $defaultPool);
            });
        }
        $legacyConfirmedCounts = $legacyConfirmed
            ->selectRaw('legacy.event_id, COUNT(*) AS aggregate')
            ->groupBy('legacy.event_id')
            ->pluck('aggregate', 'event_id')
            ->mapWithKeys(static fn ($count, $eventId): array => [(int) $eventId => (int) $count])
            ->all();
        $confirmedCounts = $canonicalConfirmedCounts;
        foreach ($legacyConfirmedCounts as $eventId => $count) {
            $confirmedCounts[$eventId] = (int) ($confirmedCounts[$eventId] ?? 0) + $count;
        }

        $activeOfferCounts = [];
        if (Schema::hasTable('event_waitlist_entries')) {
            $activeOfferCounts = DB::table('event_waitlist_entries')
                ->where('tenant_id', $tenantId)
                ->where('capacity_pool_key', $defaultPool)
                ->where('queue_state', EventWaitlistQueueState::Offered->value)
                ->where('offer_expires_at', '>', now())
                ->whereIn('event_id', $eventIds)
                ->selectRaw('event_id, COUNT(*) AS aggregate')
                ->groupBy('event_id')
                ->pluck('aggregate', 'event_id')
                ->mapWithKeys(static fn ($count, $eventId): array => [(int) $eventId => (int) $count])
                ->all();
        }

        // Interest remains an independent engagement signal during the
        // canonical-registration compatibility window.
        $interestedCounts = DB::table('event_rsvps')
            ->where('tenant_id', $tenantId)
            ->whereIn('event_id', $eventIds)
            ->whereIn('status', ['interested', 'maybe'])
            ->selectRaw('event_id, COUNT(*) AS aggregate')
            ->groupBy('event_id')
            ->pluck('aggregate', 'event_id')
            ->mapWithKeys(static fn ($count, $eventId): array => [(int) $eventId => (int) $count])
            ->all();

        $canonicalWaitlistCounts = [];
        if (Schema::hasTable('event_waitlist_entries')) {
            $canonicalWaitlistCounts = DB::table('event_waitlist_entries')
                ->where('tenant_id', $tenantId)
                ->where('capacity_pool_key', $defaultPool)
                ->whereIn('queue_state', [
                    EventWaitlistQueueState::Waiting->value,
                    EventWaitlistQueueState::Offered->value,
                ])
                ->whereIn('event_id', $eventIds)
                ->selectRaw('event_id, COUNT(*) AS aggregate')
                ->groupBy('event_id')
                ->pluck('aggregate', 'event_id')
                ->mapWithKeys(static fn ($count, $eventId): array => [(int) $eventId => (int) $count])
                ->all();
        }
        $legacyWaitlistCountsQuery = DB::table('event_waitlist as legacy')
            ->where('legacy.tenant_id', $tenantId)
            ->where('legacy.status', 'waiting')
            ->whereIn('legacy.event_id', $eventIds);
        if (Schema::hasTable('event_waitlist_entries')) {
            $legacyWaitlistCountsQuery->whereNotExists(static function ($query) use ($defaultPool): void {
                $query->selectRaw('1')
                    ->from('event_waitlist_entries as canonical')
                    ->whereColumn('canonical.tenant_id', 'legacy.tenant_id')
                    ->whereColumn('canonical.event_id', 'legacy.event_id')
                    ->whereColumn('canonical.user_id', 'legacy.user_id')
                    ->where('canonical.capacity_pool_key', $defaultPool);
            });
        }
        $legacyWaitlistCounts = $legacyWaitlistCountsQuery
            ->selectRaw('legacy.event_id, COUNT(*) AS aggregate')
            ->groupBy('legacy.event_id')
            ->pluck('aggregate', 'event_id')
            ->mapWithKeys(static fn ($count, $eventId): array => [(int) $eventId => (int) $count])
            ->all();
        $waitlistCounts = $canonicalWaitlistCounts;
        foreach ($legacyWaitlistCounts as $eventId => $count) {
            $waitlistCounts[$eventId] = (int) ($waitlistCounts[$eventId] ?? 0) + $count;
        }

        $timezone = (string) (app(TenantSettingsService::class)->get(
            $tenantId,
            'general.timezone',
            'UTC'
        ) ?: 'UTC');
        try {
            new \DateTimeZone($timezone);
        } catch (\Throwable) {
            $timezone = 'UTC';
        }

        $attendanceMap = [];
        if ($viewerId !== null) {
            $attendanceColumns = ['event_id', 'checked_in_at', 'checked_out_at'];
            if (Schema::hasColumn('event_attendance', 'attendance_status')) {
                $attendanceColumns[] = 'attendance_status';
            }
            $rows = DB::table('event_attendance')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $viewerId)
                ->whereIn('event_id', $eventIds)
                ->get($attendanceColumns);
            foreach ($rows as $row) {
                $attendanceMap[(int) $row->event_id] = [
                    'state' => isset($row->attendance_status)
                        ? (string) $row->attendance_status
                        : null,
                    'checked_in_at' => $row->checked_in_at,
                    'checked_out_at' => $row->checked_out_at,
                ];
            }
        }

        $categoryIds = [];
        $seriesIds = [];
        $recurrenceRootIds = [];
        foreach ($eventsById as $event) {
            if (!empty($event['category_id'])) {
                $categoryIds[] = (int) $event['category_id'];
            }
            if (!empty($event['series_id'])) {
                $seriesIds[] = (int) $event['series_id'];
            }
            if (!empty($event['is_recurring_template']) || !empty($event['parent_event_id']) || !empty($event['is_series'])) {
                $recurrenceRootIds[] = (int) ($event['parent_event_id'] ?? $event['id']);
            }
        }
        $categoryIds = array_values(array_unique(array_filter($categoryIds)));
        $seriesIds = array_values(array_unique(array_filter($seriesIds)));
        $recurrenceRootIds = array_values(array_unique(array_filter($recurrenceRootIds)));

        $categoryMap = [];
        if ($categoryIds !== []) {
            $categories = DB::table('categories')
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $categoryIds)
                ->get(['id', 'name', 'slug', 'color']);
            foreach ($categories as $category) {
                $categoryMap[(int) $category->id] = [
                    'id' => (int) $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'color' => $category->color,
                ];
            }
        }

        $namedSeriesMap = [];
        if ($seriesIds !== []) {
            $seriesEventModels = Event::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('series_id', $seriesIds)
                ->get([
                    'id',
                    'tenant_id',
                    'user_id',
                    'group_id',
                    'series_id',
                    'status',
                    'publication_status',
                    'operational_status',
                    'is_recurring_template',
                    'start_time',
                ]);
            $visibleSeriesEventIds = self::policyVisibleEventIdMap(
                $seriesEventModels,
                $viewer,
                $eventPolicy,
            );
            $visibleSeriesCounts = [];
            foreach ($seriesEventModels as $seriesEvent) {
                if (! isset($visibleSeriesEventIds[(int) $seriesEvent->getKey()])) {
                    continue;
                }
                $seriesId = (int) $seriesEvent->getAttribute('series_id');
                if ($seriesId > 0) {
                    $visibleSeriesCounts[$seriesId] = ($visibleSeriesCounts[$seriesId] ?? 0) + 1;
                }
            }

            $seriesRows = EventSeries::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $seriesIds)
                ->get(['id', 'title', 'description']);
            foreach ($seriesRows as $series) {
                $namedSeriesMap[(int) $series->id] = [
                    'id' => (int) $series->id,
                    'title' => $series->title,
                    'description' => $series->description,
                    'event_count' => (int) ($visibleSeriesCounts[(int) $series->id] ?? 0),
                ];
            }
        }

        $recurrenceMap = [];
        if ($recurrenceRootIds !== []) {
            $rules = DB::table('event_recurrence_rules')
                ->where('tenant_id', $tenantId)
                ->whereIn('event_id', $recurrenceRootIds)
                ->get([
                    'event_id',
                    'frequency',
                    'interval_value',
                    'days_of_week',
                    'day_of_month',
                    'month_of_year',
                    'rrule',
                    'ends_type',
                    'ends_after_count',
                    'ends_on_date',
                ]);
            foreach ($rules as $rule) {
                $recurrenceMap[(int) $rule->event_id] = (array) $rule;
            }
        }

        $facts = [];
        foreach ($eventsById as $eventId => $event) {
            $rawLegacyStatus = $rsvpMap[$eventId]
                ?? $event['user_rsvp']
                ?? $event['my_rsvp']
                ?? null;
            $engagementState = in_array($rawLegacyStatus, ['interested', 'maybe'], true)
                ? 'interested'
                : 'none';
            $canonicalRegistration = $canonicalRegistrations->get($eventId);
            $registrationState = is_object($canonicalRegistration)
                ? (string) $canonicalRegistration->registration_state
                : EventRegistrationCompatibility::registrationFromLegacy(
                    is_string($rawLegacyStatus) ? $rawLegacyStatus : null,
                )?->value;
            $legacyStatus = $rawLegacyStatus;
            $registrationEnum = is_string($registrationState)
                ? EventCapacityRegistrationState::tryFrom($registrationState)
                : null;
            if ($canonicalRegistration !== null && $registrationEnum !== null) {
                // Compatibility aliases follow the canonical capacity fact;
                // engagement remains independently projected above.
                $legacyStatus = EventRegistrationCompatibility::registrationToLegacy(
                    $registrationEnum,
                );
            }

            $canonicalQueue = $canonicalWaitlist->get($eventId);
            $legacyQueue = $legacyWaitlist->get($eventId);
            $waitlistState = is_object($canonicalQueue)
                ? (string) $canonicalQueue->queue_state
                : EventRegistrationCompatibility::waitlistFromLegacy(
                    is_object($legacyQueue) && is_string($legacyQueue->status)
                        ? $legacyQueue->status
                        : null,
                )?->value;
            $activeWaitlist = in_array($waitlistState, [
                EventWaitlistQueueState::Waiting->value,
                EventWaitlistQueueState::Offered->value,
            ], true);
            $waitlistPosition = match (true) {
                is_object($canonicalQueue) && $activeWaitlist
                    => max(1, (int) $canonicalQueue->active_position),
                $canonicalQueue === null
                    && $waitlistState === EventWaitlistQueueState::Waiting->value
                    && is_object($legacyQueue)
                    => max(1, (int) $legacyQueue->position),
                default => null,
            };
            $offerExpiresAt = is_object($canonicalQueue)
                ? $canonicalQueue->offer_expires_at
                : null;
            $offerTimestamp = is_string($offerExpiresAt)
                ? strtotime($offerExpiresAt)
                : ($offerExpiresAt instanceof \DateTimeInterface
                    ? $offerExpiresAt->getTimestamp()
                    : false);
            $offerActive = $waitlistState === EventWaitlistQueueState::Offered->value
                && $offerTimestamp !== false
                && $offerTimestamp > time();
            $isOrganizer = $viewerId !== null && (int) ($event['user_id'] ?? 0) === $viewerId;
            $abilities = $policyAbilities[$eventId] ?? [
                'view' => false,
                'viewMeetingLink' => false,
                'viewRoster' => false,
                'viewWaitlist' => false,
                'manage' => false,
                'manageAttendance' => false,
                'messagePeople' => false,
                'exportPeople' => false,
                'linkSeries' => false,
            ];
            $confirmedCount = (int) ($confirmedCounts[$eventId] ?? 0);
            // A live timed offer reserves the released place until acceptance
            // or expiry. Counting a corrupt confirmed+offered overlap twice is
            // intentionally fail-closed; the integrity audit identifies it.
            $capacityOccupiedCount = $confirmedCount
                + (int) ($activeOfferCounts[$eventId] ?? 0);
            $maxAttendees = isset($event['max_attendees']) && $event['max_attendees'] !== null
                ? (int) $event['max_attendees']
                : null;
            $isFull = $maxAttendees !== null && $capacityOccupiedCount >= $maxAttendees;
            $eventModel = $eventModels->get($eventId);
            $registrable = $eventModel instanceof Event
                && EventRegistrationAvailability::isRegistrable($eventModel);
            $canParticipate = $viewerId !== null
                && $abilities['view']
                && ! $abilities['manage']
                && $registrable;
            $hasRegistration = in_array($registrationState, [
                EventCapacityRegistrationState::Invited->value,
                EventCapacityRegistrationState::Pending->value,
                EventCapacityRegistrationState::Confirmed->value,
            ], true) || $waitlistState === EventWaitlistQueueState::Accepted->value;
            $canWithdraw = in_array($registrationState, [
                EventCapacityRegistrationState::Invited->value,
                EventCapacityRegistrationState::Pending->value,
                EventCapacityRegistrationState::Confirmed->value,
            ], true);

            $rootId = (int) ($event['parent_event_id'] ?? $eventId);
            $facts[$eventId] = [
                'viewer_id' => $viewerId,
                'timezone' => $timezone,
                'category' => !empty($event['category_id'])
                    ? ($categoryMap[(int) $event['category_id']] ?? null)
                    : null,
                'legacy_status' => $legacyStatus,
                'engagement_state' => $engagementState,
                'registration_state' => $registrationState,
                'waitlist_state' => $waitlistState,
                'waitlist_position' => $waitlistPosition,
                'confirmed_count' => $confirmedCount,
                'capacity_occupied_count' => $capacityOccupiedCount,
                'interested_count' => (int) ($interestedCounts[$eventId] ?? 0),
                'waitlist_count' => (int) ($waitlistCounts[$eventId] ?? 0),
                'attendance' => $attendanceMap[$eventId] ?? [],
                'is_organizer' => $isOrganizer,
                'policy_abilities' => $abilities,
                'can_message_organizer' => false,
                'allowed_actions' => [
                    'set_interest' => $canParticipate,
                    'register' => $canParticipate && ! $hasRegistration && ! $isFull && ! $activeWaitlist,
                    'withdraw' => $canWithdraw,
                    'join_waitlist' => $canParticipate && $isFull && ! $hasRegistration && ! $activeWaitlist,
                    // Exit-only actions remain available if the event later
                    // stops accepting registrations.
                    'leave_waitlist' => $activeWaitlist,
                    'accept_offer' => $registrable && $offerActive,
                ],
                'named_series' => !empty($event['series_id'])
                    ? ($namedSeriesMap[(int) $event['series_id']] ?? null)
                    : null,
                'recurrence' => $recurrenceMap[$rootId] ?? null,
                'online_access' => is_array($event['online_access'] ?? null)
                    ? $event['online_access']
                    : EventContractMapper::onlineAccess(
                        $event,
                        $abilities['viewMeetingLink'],
                        $abilities['manage']
                    ),
            ];
        }

        return $facts;
    }

    /**
     * Redact raw meeting aliases before EventService data can reach any reader.
     *
     * @param array<int, array<string, mixed>> $events
     * @return array<int, array<string, mixed>>
     */
    private static function redactOnlineAccessForEvents(array $events, ?int $viewerId): array
    {
        if ($events === []) {
            return [];
        }

        $tenantId = (int) TenantContext::getId();
        $eventIds = array_values(array_filter(array_map(
            static fn (array $event): int => (int) ($event['id'] ?? 0),
            $events
        )));
        $abilities = [];
        $viewer = self::policyUser($viewerId, $tenantId);
        if ($viewer !== null && $eventIds !== []) {
            $policyEvents = Event::query()
                ->whereIn('id', $eventIds)
                ->get(['id', 'tenant_id', 'user_id', 'group_id', 'status']);
            $abilities = app(EventPolicy::class)->abilitiesForEvents($viewer, $policyEvents);
        }

        foreach ($events as &$event) {
            $eventId = (int) ($event['id'] ?? 0);
            $eventAbilities = $abilities[$eventId] ?? [];
            $onlineAccess = EventContractMapper::onlineAccess(
                $event,
                (bool) ($eventAbilities['viewMeetingLink'] ?? false),
                (bool) ($eventAbilities['manage'] ?? false)
            );
            $event = EventContractMapper::redactLegacyOnlineFields($event, $onlineAccess);
        }
        unset($event);

        return $events;
    }

    /**
     * RSVP to an event with capacity enforcement.
     */
    public static function rsvp(int $eventId, int $userId, string $status): bool
    {
        self::$errors = [];
        self::$lastRsvpChanged = false;

        $validStatuses = ['going', 'interested', 'not_going', 'declined'];
        if (!in_array($status, $validStatuses)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.event_invalid_rsvp_status'), 'field' => 'status'];
            return false;
        }

        $tenantId = (int) \App\Core\TenantContext::getId();
        $event = Event::query()->find($eventId);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.event_not_found')];
            return false;
        }

        if (! self::policyAllows($event, $userId, 'view')) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.event_not_found')];
            return false;
        }

        if (! self::isConcretePublishedRegistrationTarget($event)) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.event_not_found')];
            return false;
        }

        if (($event->status ?? 'active') === 'cancelled') {
            self::$errors[] = ['code' => 'EVENT_CANCELLED', 'message' => __('svc_notifications_2.event.rsvp_cancelled')];
            return false;
        }

        if (!self::allowsKissTreffenRsvp($eventId, $userId, $tenantId)) {
            self::$errors[] = ['code' => 'KISS_TREFFEN_MEMBERS_ONLY', 'message' => __('api.caring_kiss_treffen_members_only_rsvp')];
            return false;
        }

        if (in_array($status, ['going', 'interested'], true)) {
            self::assertEventParticipationAllowed(
                $userId,
                (int) ($event->user_id ?? 0),
                $tenantId,
                'event_rsvp',
            );
        }

        // Block RSVP to past events (event has already ended, or started with no end time)
        if ($status === 'going' || $status === 'interested') {
            $eventEnd = $event->end_time ?? $event->start_time ?? null;
            if ($eventEnd && strtotime($eventEnd) < time()) {
                self::$errors[] = ['code' => 'EVENT_ENDED', 'message' => __('svc_notifications_2.event.rsvp_ended')];
                return false;
            }
        }

        // Capacity enforcement for 'going' status — use SELECT ... FOR UPDATE to prevent race conditions
        if ($status === 'going' && !empty($event->max_attendees)) {
            $maxAttendees = (int) $event->max_attendees;

            return DB::transaction(function () use ($eventId, $userId, $tenantId, $status, $maxAttendees) {
                // Lock the RSVP rows for this event to prevent concurrent over-booking
                $currentGoing = (int) DB::selectOne(
                    "SELECT COUNT(*) as cnt FROM event_rsvps WHERE event_id = ? AND tenant_id = ? AND status = 'going' FOR UPDATE",
                    [$eventId, $tenantId]
                )->cnt;

                $currentUserStatus = self::getUserRsvp($eventId, $userId);
                $isAlreadyGoing = ($currentUserStatus === 'going');
                if ($currentUserStatus === $status) {
                    self::removeFromWaitlist($eventId, $userId);
                    return true;
                }

                if (!$isAlreadyGoing && $currentGoing >= $maxAttendees) {
                    self::addToWaitlist($eventId, $userId);
                    self::$errors[] = [
                        'code' => 'EVENT_FULL',
                        'message' => __('svc_notifications_2.event.added_to_waitlist'),
                        'waitlisted' => true,
                    ];
                    return false;
                }

                // Upsert RSVP inside the transaction
                DB::statement(
                    "INSERT INTO event_rsvps (event_id, user_id, tenant_id, status, created_at)
                     VALUES (?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = NOW()",
                    [$eventId, $userId, $tenantId, $status]
                );

                if (!in_array($status, ['going', 'interested'], true)) {
                    self::cancelPendingRemindersForRsvp($eventId, $userId, $tenantId);
                }

                self::removeFromWaitlist($eventId, $userId);
                self::$lastRsvpChanged = true;

                return true;
            });
        }

        try {
            $currentUserStatus = self::getUserRsvp($eventId, $userId);
            if ($currentUserStatus === $status) {
                if (!in_array($status, ['going', 'interested'], true)) {
                    self::cancelPendingRemindersForRsvp($eventId, $userId, $tenantId);
                }
                self::removeFromWaitlist($eventId, $userId);
                return true;
            }

            // Upsert RSVP (no capacity limit)
            DB::statement(
                "INSERT INTO event_rsvps (event_id, user_id, tenant_id, status, created_at)
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = NOW()",
                [$eventId, $userId, $tenantId, $status]
            );

            if (!in_array($status, ['going', 'interested'], true)) {
                self::cancelPendingRemindersForRsvp($eventId, $userId, $tenantId);
            }

            self::removeFromWaitlist($eventId, $userId);
            self::$lastRsvpChanged = true;

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EventService::rsvp error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => __('api.event_rsvp_update_failed')];
            return false;
        }
    }

    private static function cancelPendingRemindersForRsvp(int $eventId, int $userId, int $tenantId): void
    {
        DB::table('event_reminders')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);
    }

    public static function wasLastRsvpChanged(): bool
    {
        return self::$lastRsvpChanged;
    }

    /**
     * @return array<string,mixed>
     */
    public static function getLastMeaningfulUpdateChanges(): array
    {
        return self::$lastMeaningfulUpdateChanges;
    }

    private static function normalizeEventChangeValue(string $key, mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (in_array($key, ['start_time', 'end_time'], true)) {
            if ($value === null || $value === '') {
                return null;
            }

            $timestamp = strtotime((string) $value);
            return $timestamp === false ? (string) $value : $timestamp;
        }

        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }

    private static function allowsKissTreffenRsvp(int $eventId, int $userId, int $tenantId): bool
    {
        if (!Schema::hasTable('caring_kiss_treffen')) {
            return true;
        }

        $treffen = DB::table('caring_kiss_treffen')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first(['members_only']);

        if (!$treffen || !(bool) $treffen->members_only) {
            return true;
        }

        return DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->where('status', 'active')
            ->where('is_approved', true)
            ->exists();
    }

    /**
     * Remove RSVP from an event (with waitlist promotion).
     */
    public static function removeRsvp(int $eventId, int $userId): bool
    {
        self::$errors = [];
        $tenantId = \App\Core\TenantContext::getId();

        $event = DB::selectOne("SELECT id FROM events WHERE id = ? AND tenant_id = ?", [$eventId, $tenantId]);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.event_not_found')];
            return false;
        }

        try {
            $currentStatus = self::getUserRsvp($eventId, $userId);

            DB::delete("DELETE FROM event_rsvps WHERE event_id = ? AND user_id = ? AND tenant_id = ?", [$eventId, $userId, $tenantId]);

            // Cancel reminders
            DB::update(
                "UPDATE event_reminders SET status = 'cancelled' WHERE event_id = ? AND user_id = ? AND status = 'pending' AND tenant_id = ?",
                [$eventId, $userId, $tenantId]
            );

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EventService::removeRsvp error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => __('api.event_rsvp_remove_failed')];
            return false;
        }
    }

    /**
     * Get attendees for an event with cursor-based pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getAttendees(int $eventId, array $filters = [], ?int $viewerId = null): array
    {
        $limit = min($filters['limit'] ?? 20, 100);
        $status = $filters['status'] ?? 'going';
        $cursor = $filters['cursor'] ?? null;

        $validStatuses = ['going', 'interested', 'invited', 'attended', 'all'];
        if (!in_array($status, $validStatuses)) {
            $status = 'going';
        }

        $cursorId = null;
        if (is_string($cursor) && $cursor !== '') {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int) $decoded;
            }
        }

        $tenantId = (int) \App\Core\TenantContext::getId();
        $event = Event::query()->find($eventId);
        if ($event === null || ! self::policyAllows($event, $viewerId, 'viewRoster')) {
            return [
                'items' => [],
                'cursor' => null,
                'has_more' => false,
            ];
        }

        // The operational roster must retain checked-in/legacy-attended people
        // after refresh; registration and attendance are independent facts.
        if ($status === 'all') {
            $params = [$eventId, 'going', 'interested', 'invited', 'attended', $tenantId];
            $statusSql = '(r.status IN (?, ?, ?, ?) OR a.checked_in_at IS NOT NULL)';
        } else {
            $params = [$eventId, $status, $tenantId];
            $statusSql = 'r.status = ?';
        }

        $cursorSql = '';
        if ($cursorId) {
            $cursorSql = ' AND r.id > ?';
            $params[] = $cursorId;
        }
        $params[] = $limit + 1;

        $rows = DB::select(
            "SELECT r.id as rsvp_id, r.user_id, r.status, r.created_at as rsvp_at,
                   u.name, u.first_name, u.last_name, u.avatar_url,
                   a.checked_in_at, a.checked_out_at
            FROM event_rsvps r
             JOIN users u ON r.user_id = u.id AND u.tenant_id = r.tenant_id AND u.status = 'active'
             LEFT JOIN event_attendance a
               ON a.event_id = r.event_id
              AND a.user_id = r.user_id
              AND a.tenant_id = r.tenant_id
            WHERE r.event_id = ? AND {$statusSql} AND r.tenant_id = ?{$cursorSql}
            ORDER BY r.id ASC LIMIT ?",
            $params
        );

        $attendees = array_map(fn($r) => (array) $r, $rows);
        $hasMore = count($attendees) > $limit;
        if ($hasMore) {
            array_pop($attendees);
        }

        $items = [];
        $lastId = null;

        foreach ($attendees as $att) {
            $lastId = $att['rsvp_id'];
            $items[] = [
                'id' => (int) $att['user_id'],
                'name' => $att['name'] ?? trim(($att['first_name'] ?? '') . ' ' . ($att['last_name'] ?? '')),
                'first_name' => $att['first_name'] ?? null,
                'last_name' => $att['last_name'] ?? null,
                'avatar' => $att['avatar_url'],
                'avatar_url' => $att['avatar_url'],
                'rsvp_status' => $att['status'],
                'status' => $att['status'],
                'rsvp_at' => $att['rsvp_at'],
                'checked_in_at' => $att['checked_in_at'] ?? null,
                'checked_out_at' => $att['checked_out_at'] ?? null,
            ];
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string) $lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get nearby upcoming events using Haversine distance.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getNearby(
        float $lat,
        float $lon,
        array $filters = [],
        ?int $viewerId = null
    ): array
    {
        $result = self::getAll(array_merge($filters, [
            'when' => 'upcoming',
            'near_lat' => $lat,
            'near_lng' => $lon,
            'radius_km' => $filters['radius_km'] ?? 25,
            'viewer_id' => $viewerId,
        ]));

        foreach ($result['items'] as &$item) {
            if (!isset($item['organizer']) && is_array($item['user'] ?? null)) {
                $item['organizer'] = $item['user'];
            }
        }
        unset($item);

        return $result;
    }

    /**
     * Update event cover image.
     */
    /**
     * Authorize an event image mutation before any file is written.
     */
    public static function canUpdateImage(int $eventId, int $userId): bool
    {
        self::$errors = [];
        $event = Event::query()->find($eventId);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.event_not_found')];
            return false;
        }

        if (! self::policyAllows($event, $userId, 'manage')) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.event_modify_forbidden')];
            return false;
        }

        return true;
    }

    public static function updateImage(int $eventId, int $userId, string $imageUrl): bool
    {
        if (!self::canUpdateImage($eventId, $userId)) {
            return false;
        }

        $tenantId = \App\Core\TenantContext::getId();
        $event = DB::selectOne(
            "SELECT id, is_recurring_template, parent_event_id FROM events WHERE id = ? AND tenant_id = ?",
            [$eventId, $tenantId]
        );
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.event_not_found')];
            return false;
        }

        try {
            DB::update("UPDATE events SET cover_image = ? WHERE id = ? AND tenant_id = ?", [$imageUrl, $eventId, $tenantId]);

            // Recurring series: the image is uploaded to the template AFTER its
            // occurrences are generated, so without this the cover only lands on
            // the one row the upload targeted (which sorts last in the DESC list).
            // Propagate it to the whole series so every occurrence shows it.
            if (!empty($event->is_recurring_template) || !empty($event->parent_event_id)) {
                $rootId = !empty($event->parent_event_id) ? (int) $event->parent_event_id : (int) $event->id;
                DB::update(
                    "UPDATE events SET cover_image = ? WHERE tenant_id = ? AND (id = ? OR parent_event_id = ?)",
                    [$imageUrl, $tenantId, $rootId, $rootId]
                );
            }
            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EventService::updateImage error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => __('api.event_image_update_failed')];
            return false;
        }
    }

    /**
     * Cancel an event through the serialized lifecycle boundary.
     *
     * This compatibility method is shared by the JSON and accessible frontends.
     */
    public static function cancelEvent(
        int $eventId,
        int $userId,
        string $reason = '',
        ?string $idempotencyKey = null,
    ): bool {
        self::resetLifecycleCompatibilityState();

        $reason = trim($reason);
        if ($reason === '') {
            self::$errors[] = [
                'code' => 'VALIDATION_REQUIRED_FIELD',
                'message' => __('api.missing_required_field', ['field' => 'reason']),
                'field' => 'reason',
            ];
            return false;
        }
        if (! self::validLifecycleIdempotencyKey($idempotencyKey)) {
            return false;
        }

        try {
            $tenantId = (int) TenantContext::getId();
            /** @var Event|null $snapshot */
            $snapshot = Event::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereKey($eventId)
                ->first();
            if ($snapshot === null) {
                throw new EventLifecycleTransitionException('event_lifecycle_event_not_found');
            }
            $actor = self::lifecycleActor($userId);
            if (! app(EventPolicy::class)->manage($actor, $snapshot)) {
                throw new EventLifecycleTransitionException('event_lifecycle_authorization_denied');
            }
            $operation = self::transitionLifecycleTargets(
                $snapshot,
                $actor,
                'cancel',
                $reason,
            );
            $result = $operation['result'];
            self::captureLifecycleResult(
                'cancel',
                $result,
                $idempotencyKey,
                $operation['series'],
            );
            if ($result->changed) {
                self::notifyLifecycleCancellation($result, $reason);
            }

            return true;
        } catch (EventLifecycleTransitionException $exception) {
            self::$errors[] = self::lifecycleCompatibilityError($exception, 'cancel');
        } catch (\Throwable $exception) {
            Log::error('EventService::cancelEvent lifecycle error', [
                'event_id' => $eventId,
                'actor_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
            self::$errors[] = [
                'code' => 'SERVER_ERROR',
                'message' => __('api.event_cancel_failed'),
            ];
        }

        return false;
    }

    private static function resetLifecycleCompatibilityState(): void
    {
        self::$errors = [];
        self::$lastCancellationRecipientIds = [];
        self::$lastLifecycleResult = null;
        self::$lastLifecycleResponse = null;
    }

    private static function validLifecycleIdempotencyKey(?string $idempotencyKey): bool
    {
        if ($idempotencyKey === null || trim($idempotencyKey) === '') {
            return true;
        }
        if (mb_strlen(trim($idempotencyKey)) <= 191) {
            return true;
        }

        self::$errors[] = [
            'code' => 'VALIDATION_ERROR',
            'message' => __('api.invalid_input'),
            'field' => 'idempotency_key',
        ];

        return false;
    }

    private static function lifecycleActor(int $actorId): User
    {
        $tenantId = (int) TenantContext::getId();
        if ($tenantId <= 0 || $actorId <= 0) {
            throw new EventLifecycleTransitionException('event_lifecycle_subject_invalid');
        }

        /** @var User|null $actor */
        $actor = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($actorId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();
        if ($actor === null) {
            throw new EventLifecycleTransitionException('event_lifecycle_authorization_denied');
        }

        return $actor;
    }

    private static function nullableLifecycleValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new \UnexpectedValueException('event_lifecycle_storage_type_invalid');
        }

        return $value;
    }

    /**
     * Apply a lifecycle intent to one event or the remaining future occurrences
     * of a recurring template in one outer transaction.
     *
     * @return array{
     *   result:EventLifecycleTransitionResult,
     *   series:array{
     *     is_series:bool,
     *     target_count:int,
     *     changed_count:int,
     *     replayed_count:int,
     *     history_count:int,
     *     outbox_count:int,
     *     event_ids:list<int>,
     *     changed_event_ids:list<int>
     *   }
     * }
     */
    private static function transitionLifecycleTargets(
        Event $root,
        User $actor,
        string $action,
        ?string $reason,
    ): array {
        $tenantId = (int) $root->getAttribute('tenant_id');
        $rootId = (int) $root->getKey();
        $isSeries = (bool) $root->getAttribute('is_recurring_template');

        return DB::transaction(function () use (
            $tenantId,
            $rootId,
            $isSeries,
            $actor,
            $action,
            $reason,
        ): array {
            $targetIds = [$rootId];
            if ($isSeries) {
                $occurrenceIds = Event::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('parent_event_id', $rootId)
                    ->where('start_time', '>=', now())
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->filter(static fn (int $id): bool => $id > 0)
                    ->all();
                $targetIds = array_values(array_unique(array_merge($targetIds, $occurrenceIds)));
            }

            $lifecycle = app(EventLifecycleService::class);
            /** @var array<int,EventLifecycleTransitionResult> $results */
            $results = [];
            foreach ($targetIds as $targetId) {
                if ($action === 'cancel') {
                    $results[$targetId] = $lifecycle->transition(
                        $targetId,
                        $actor,
                        null,
                        EventOperationalState::Cancelled,
                        $reason,
                        new EventLifecycleTransitionGuard(null, [
                            EventOperationalState::Scheduled,
                            EventOperationalState::Postponed,
                            EventOperationalState::Cancelled,
                        ]),
                    );
                    continue;
                }
                if ($action !== 'archive') {
                    throw new \LogicException('Unsupported event lifecycle compatibility action.');
                }

                /** @var Event|null $target */
                $target = Event::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($targetId)
                    ->first();
                if ($target === null) {
                    throw new EventLifecycleTransitionException('event_lifecycle_event_not_found');
                }
                $current = EventLifecycleCompatibility::resolve(
                    self::nullableLifecycleValue($target->getRawOriginal('publication_status')),
                    self::nullableLifecycleValue($target->getRawOriginal('operational_status')),
                    self::nullableLifecycleValue($target->getRawOriginal('status')),
                );
                $cancelOperationally = in_array($current['operational'], [
                    EventOperationalState::Scheduled,
                    EventOperationalState::Postponed,
                ], true);
                $results[$targetId] = $lifecycle->transition(
                    $targetId,
                    $actor,
                    EventPublicationState::Archived,
                    $cancelOperationally ? EventOperationalState::Cancelled : null,
                    $reason,
                );
            }

            return self::aggregateLifecycleResults($rootId, $isSeries, $results);
        }, 3);
    }

    /**
     * @param array<int,EventLifecycleTransitionResult> $results
     * @return array{
     *   result:EventLifecycleTransitionResult,
     *   series:array{
     *     is_series:bool,
     *     target_count:int,
     *     changed_count:int,
     *     replayed_count:int,
     *     history_count:int,
     *     outbox_count:int,
     *     event_ids:list<int>,
     *     changed_event_ids:list<int>
     *   }
     * }
     */
    private static function aggregateLifecycleResults(
        int $rootId,
        bool $isSeries,
        array $results,
    ): array {
        $rootResult = $results[$rootId] ?? null;
        if (! $rootResult instanceof EventLifecycleTransitionResult) {
            throw new \LogicException('Root event lifecycle result is missing.');
        }

        $changedEventIds = [];
        $recipientIds = [];
        /** @var array{reminders_cancelled:int,waitlist_cancelled:int,registrations_cancelled:int} $cascade */
        $cascade = [
            'reminders_cancelled' => 0,
            'waitlist_cancelled' => 0,
            'registrations_cancelled' => 0,
        ];
        $deliveryMode = null;
        $publicationBecamePublished = false;
        foreach ($results as $eventId => $result) {
            if ($result->changed) {
                $changedEventIds[] = (int) $eventId;
            }
            $recipientIds = array_merge($recipientIds, $result->affectedRecipientUserIds);
            foreach (array_keys($cascade) as $key) {
                $cascade[$key] += (int) ($result->cascade[$key] ?? 0);
            }
            $deliveryMode ??= $result->deliveryMode;
            $publicationBecamePublished = $publicationBecamePublished
                || $result->publicationBecamePublished;
        }
        $recipientIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $recipientIds),
            static fn (int $id): bool => $id > 0,
        )));
        $eventIds = array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            array_keys($results),
        ));
        $changedCount = count($changedEventIds);

        return [
            'result' => new EventLifecycleTransitionResult(
                $rootResult->event,
                $changedCount > 0,
                $rootResult->historyId,
                $rootResult->outboxId,
                $recipientIds,
                $cascade,
                $publicationBecamePublished,
                $deliveryMode,
            ),
            'series' => [
                'is_series' => $isSeries,
                'target_count' => count($eventIds),
                'changed_count' => $changedCount,
                'replayed_count' => count($eventIds) - $changedCount,
                'history_count' => $changedCount,
                'outbox_count' => $changedCount,
                'event_ids' => $eventIds,
                'changed_event_ids' => $changedEventIds,
            ],
        ];
    }

    /** @param array<string,mixed> $series */
    private static function captureLifecycleResult(
        string $action,
        EventLifecycleTransitionResult $result,
        ?string $idempotencyKey,
        array $series,
    ): void {
        $event = $result->event;
        $publication = (string) $event->getRawOriginal('publication_status');
        $operational = (string) $event->getRawOriginal('operational_status');
        $legacyStatus = (string) $event->getRawOriginal('status');
        $replayed = ! $result->changed;
        $archived = $publication === EventPublicationState::Archived->value;
        $cancelled = $operational === EventOperationalState::Cancelled->value;
        $outcome = match ($action) {
            'cancel' => $replayed ? 'already_cancelled' : 'cancelled',
            'archive' => $replayed ? 'already_archived' : 'archived',
            default => $replayed ? 'already_transitioned' : 'transitioned',
        };
        $storedReason = $action === 'cancel'
            ? $event->getRawOriginal('cancellation_reason')
            : $event->getRawOriginal('lifecycle_reason');

        self::$lastLifecycleResult = $result;
        self::$lastCancellationRecipientIds = $result->affectedRecipientUserIds;
        self::$lastLifecycleResponse = [
            'action' => $action,
            'requested_action' => $action === 'archive' ? 'delete' : $action,
            'outcome' => $outcome,
            'event_id' => (int) $event->getKey(),
            'changed' => $result->changed,
            'replayed' => $replayed,
            'idempotent_replay' => $replayed,
            'idempotency_key_supplied' => $idempotencyKey !== null
                && trim($idempotencyKey) !== '',
            'cancelled' => $cancelled,
            'already_cancelled' => $action === 'cancel' && $replayed,
            'archived' => $archived,
            'already_archived' => $action === 'archive' && $replayed,
            'deleted' => false,
            'publication_status' => $publication,
            'operational_status' => $operational,
            'status' => $legacyStatus,
            'lifecycle_version' => (int) $event->getRawOriginal('lifecycle_version'),
            'reason' => is_string($storedReason) && $storedReason !== '' ? $storedReason : null,
            'history_id' => $result->historyId,
            'outbox_id' => $result->outboxId,
            'cascade' => $result->cascade,
            'series' => $series,
        ];
    }

    private static function notifyLifecycleCancellation(
        EventLifecycleTransitionResult $result,
        ?string $reason,
    ): void {
        if ($result->affectedRecipientUserIds === []
            || ! in_array($result->deliveryMode, [
                EventNotificationDeliveryMode::Direct->value,
                EventNotificationDeliveryMode::ShadowOutbox->value,
            ], true)) {
            return;
        }

        try {
            app(EventNotificationService::class)->notifyCancellation(
                (int) $result->event->getAttribute('tenant_id'),
                (int) $result->event->getKey(),
                $reason,
                $result->affectedRecipientUserIds,
            );
        } catch (\Throwable $exception) {
            Log::warning('Event lifecycle cancellation notification failed', [
                'event_id' => (int) $result->event->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /** @return array{code:string,message:string,field?:string} */
    private static function lifecycleCompatibilityError(
        EventLifecycleTransitionException $exception,
        string $action,
    ): array {
        $forbiddenMessage = $action === 'cancel'
            ? __('api.event_cancel_forbidden')
            : __('api.event_delete_forbidden');
        $failureMessage = $action === 'cancel'
            ? __('api.event_cancel_failed')
            : __('api.delete_failed', ['resource' => 'event']);

        return match ($exception->reasonCode) {
            'event_lifecycle_event_not_found' => [
                'code' => 'NOT_FOUND',
                'message' => __('api.event_not_found'),
            ],
            'event_lifecycle_authorization_denied',
            'event_lifecycle_subject_invalid' => [
                'code' => 'FORBIDDEN',
                'message' => $forbiddenMessage,
            ],
            'event_lifecycle_reason_too_long' => [
                'code' => 'VALIDATION_ERROR',
                'message' => __('api.invalid_input'),
                'field' => 'reason',
            ],
            'event_lifecycle_tenant_context_missing' => [
                'code' => 'SERVER_ERROR',
                'message' => $failureMessage,
            ],
            default => [
                'code' => 'EVENT_LIFECYCLE_CONFLICT',
                'message' => __('api.invalid_status'),
            ],
        };
    }

    /**
     * Add user to event waitlist.
     */
    public static function addToWaitlist(int $eventId, int $userId): bool
    {
        self::$errors = [];
        $tenantId = (int) \App\Core\TenantContext::getId();
        $event = Event::query()->find($eventId);
        if ($event === null) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.event_not_found')];
            return false;
        }
        if (! self::policyAllows($event, $userId, 'view')) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.event_not_found')];
            return false;
        }
        if (! self::isConcretePublishedRegistrationTarget($event)) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.event_not_found')];
            return false;
        }
        self::assertEventParticipationAllowed(
            $userId,
            (int) $event->user_id,
            $tenantId,
            'event_waitlist',
        );

        try {
            $exists = DB::selectOne("SELECT id FROM event_waitlist WHERE event_id = ? AND user_id = ? AND tenant_id = ? AND status = 'waiting'", [$eventId, $userId, $tenantId]);
            if ($exists) {
                return true;
            }

            $posRow = DB::selectOne("SELECT COALESCE(MAX(position), 0) + 1 as next_pos FROM event_waitlist WHERE event_id = ? AND tenant_id = ? AND status = 'waiting'", [$eventId, $tenantId]);
            $nextPos = (int) $posRow->next_pos;

            DB::statement(
                "INSERT INTO event_waitlist (event_id, user_id, tenant_id, position, status) VALUES (?, ?, ?, ?, 'waiting')
                 ON DUPLICATE KEY UPDATE status = 'waiting', position = ?, updated_at = NOW()",
                [$eventId, $userId, $tenantId, $nextPos, $nextPos]
            );

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EventService::addToWaitlist error: " . $e->getMessage());
            return false;
        }
    }

    private static function assertEventParticipationAllowed(
        int $memberId,
        int $organizerId,
        int $tenantId,
        string $channel,
    ): void {
        if ($memberId <= 0 || $organizerId <= 0 || $memberId === $organizerId) {
            return;
        }

        $policy = app(SafeguardingInteractionPolicy::class);
        $policy->assertLocalContactAllowed($memberId, $organizerId, $tenantId, $channel);
        $policy->assertLocalContactAllowed($organizerId, $memberId, $tenantId, $channel);
    }

    /**
     * Remove user from event waitlist.
     */
    public static function removeFromWaitlist(int $eventId, int $userId): void
    {
        $tenantId = \App\Core\TenantContext::getId();
        try {
            DB::update(
                "UPDATE event_waitlist SET status = 'cancelled', cancelled_at = NOW() WHERE event_id = ? AND user_id = ? AND status = 'waiting' AND tenant_id = ?",
                [$eventId, $userId, $tenantId]
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EventService::removeFromWaitlist error: " . $e->getMessage());
        }
    }

    /**
     * Get event waitlist.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getWaitlist(
        int $eventId,
        array $filters = [],
        ?int $viewerId = null
    ): array
    {
        self::$errors = [];
        $limit = min($filters['limit'] ?? 20, 100);

        $tenantId = (int) \App\Core\TenantContext::getId();
        $event = Event::query()->find($eventId);
        if ($event === null || ! self::policyAllows($event, $viewerId, 'viewWaitlist')) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.event_not_found')];
            return ['items' => [], 'has_more' => false];
        }

        $rows = DB::select(
            "SELECT w.id, w.user_id, w.position, w.status, w.created_at,
                   u.name, u.first_name, u.last_name, u.avatar_url
            FROM event_waitlist w
            JOIN users u ON w.user_id = u.id
            WHERE w.event_id = ? AND w.tenant_id = ? AND w.status = 'waiting'
            ORDER BY w.position ASC
            LIMIT ?",
            [$eventId, $tenantId, $limit + 1]
        );

        $items = array_map(fn($r) => (array) $r, $rows);
        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        $formatted = [];
        foreach ($items as $item) {
            $formatted[] = [
                'id' => (int) $item['user_id'],
                'name' => $item['name'] ?? trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')),
                'first_name' => $item['first_name'] ?? null,
                'last_name' => $item['last_name'] ?? null,
                'avatar_url' => $item['avatar_url'],
                'position' => (int) $item['position'],
                'joined_at' => $item['created_at'],
            ];
        }

        return ['items' => $formatted, 'has_more' => $hasMore];
    }

    /**
     * Get user's waitlist position.
     */
    public static function getUserWaitlistPosition(int $eventId, int $userId): ?int
    {
        try {
            $tenantId = \App\Core\TenantContext::getId();
            $row = DB::selectOne(
                "SELECT position FROM event_waitlist WHERE event_id = ? AND user_id = ? AND tenant_id = ? AND status = 'waiting'",
                [$eventId, $userId, $tenantId]
            );
            return $row ? (int) $row->position : null;
        } catch (\Exception $e) {
            Log::debug('[EventService] getUserWaitlistPosition failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get user's reminders for an event.
     */
    public static function getUserReminders(int $eventId, int $userId): array
    {
        try {
            $tenantId = (int) \App\Core\TenantContext::getId();
            $event = Event::query()->find($eventId);
            if ($event === null || ! self::policyAllows($event, $userId, 'view')) {
                return [];
            }

            $rows = DB::select(
                "SELECT remind_before_minutes, reminder_type, status, scheduled_for
                 FROM event_reminders
                 WHERE event_id = ? AND user_id = ? AND tenant_id = ? AND status = 'pending'
                 ORDER BY remind_before_minutes ASC",
                [$eventId, $userId, $tenantId]
            );
            return array_map(fn($r) => (array) $r, $rows);
        } catch (\Exception $e) {
            Log::debug('[EventService] getUserReminders failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Update reminders for an event.
     */
    public static function updateReminders(int $eventId, int $userId, array $reminders): bool
    {
        $tenantId = (int) \App\Core\TenantContext::getId();

        try {
            $event = Event::query()->find($eventId);
            if ($event === null || ! self::policyAllows($event, $userId, 'view')) {
                return false;
            }

            // Cancel existing reminders
            DB::update(
                "UPDATE event_reminders SET status = 'cancelled' WHERE event_id = ? AND user_id = ? AND status = 'pending' AND tenant_id = ?",
                [$eventId, $userId, $tenantId]
            );

            $validTypes = ['platform', 'email', 'both'];
            $validMinutes = [60, 1440, 10080];

            if (!$event->start_time) {
                return false;
            }

            foreach ($reminders as $reminder) {
                $minutes = (int) ($reminder['minutes'] ?? 0);
                $type = $reminder['type'] ?? 'both';

                if (!in_array($minutes, $validMinutes) || !in_array($type, $validTypes)) {
                    continue;
                }

                $startTimestamp = strtotime($event->start_time);
                $scheduledFor = date('Y-m-d H:i:s', $startTimestamp - ($minutes * 60));

                if (strtotime($scheduledFor) < time()) {
                    continue;
                }

                DB::statement(
                    "INSERT INTO event_reminders (event_id, user_id, tenant_id, remind_before_minutes, reminder_type, scheduled_for, status)
                     VALUES (?, ?, ?, ?, ?, ?, 'pending')
                     ON DUPLICATE KEY UPDATE status = 'pending', scheduled_for = ?, updated_at = NOW()",
                    [$eventId, $userId, $tenantId, $minutes, $type, $scheduledFor, $scheduledFor]
                );
            }

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("EventService::updateReminders error: " . $e->getMessage());
            return false;
        }
    }

    private static function policyUser(?int $userId, int $tenantId): ?User
    {
        if ($userId === null || $userId <= 0) {
            return null;
        }

        return User::query()
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->first([
                'id',
                'tenant_id',
                'status',
                'deleted_at',
                'role',
                'is_admin',
                'is_super_admin',
                'is_tenant_super_admin',
                'is_god',
            ]);
    }

    /**
     * Resolve visible event IDs through the canonical policy in bounded queries.
     *
     * @param  iterable<Event>  $events
     * @return array<int, true>
     */
    private static function policyVisibleEventIdMap(
        iterable $events,
        ?User $viewer,
        EventPolicy $policy,
    ): array {
        if ($viewer === null) {
            return [];
        }

        $eventModels = [];
        foreach ($events as $event) {
            if ($event instanceof Event && (int) $event->getKey() > 0) {
                $eventModels[] = $event;
            }
        }

        if ($eventModels === []) {
            return [];
        }

        $visibleEventIds = [];
        foreach ($policy->abilitiesForEvents($viewer, $eventModels) as $eventId => $abilities) {
            if (($abilities['view'] ?? false) === true) {
                $visibleEventIds[(int) $eventId] = true;
            }
        }

        return $visibleEventIds;
    }

    private static function policyAllows(
        Event $event,
        ?int $userId,
        string $ability,
        ?User $user = null,
        ?EventPolicy $policy = null,
    ): bool {
        $tenantId = (int) TenantContext::getId();
        $user ??= self::policyUser($userId, $tenantId);
        if ($user === null) {
            return false;
        }

        $policy ??= app(EventPolicy::class);

        return match ($ability) {
            'view' => $policy->view($user, $event),
            'viewMeetingLink' => $policy->viewMeetingLink($user, $event),
            'viewRoster' => $policy->viewRoster($user, $event),
            'viewWaitlist' => $policy->viewWaitlist($user, $event),
            'manage' => $policy->manage($user, $event),
            'manageAttendance' => $policy->manageAttendance($user, $event),
            'messagePeople' => $policy->messagePeople($user, $event),
            'exportPeople' => $policy->exportPeople($user, $event),
            'linkSeries' => $policy->linkSeries($user, $event),
            default => false,
        };
    }

    /**
     * Build a tenant-scoped private-group audience clause for raw event queries.
     *
     * @return array{0: string, 1: array<int, int|string>}
     */
    private static function eventGroupVisibilitySql(
        string $eventAlias,
        ?int $viewerId,
        int $tenantId
    ): array {
        $sql = "AND (
            {$eventAlias}.group_id IS NULL
            OR EXISTS (
                SELECT 1 FROM groups visible_event_groups
                WHERE visible_event_groups.id = {$eventAlias}.group_id
                  AND visible_event_groups.tenant_id = ?
                  AND visible_event_groups.status = ?";
        $bindings = [$tenantId, GroupStatus::Active->value];

        if (!self::isTenantAdmin($viewerId, $tenantId)) {
            $sql .= "
                  AND (
                      visible_event_groups.visibility IS NULL
                      OR visible_event_groups.visibility = 'public'";

            if ($viewerId !== null) {
                $sql .= "
                      OR visible_event_groups.owner_id = ?
                      OR EXISTS (
                          SELECT 1 FROM group_members visible_event_memberships
                          WHERE visible_event_memberships.group_id = visible_event_groups.id
                            AND visible_event_memberships.tenant_id = ?
                            AND visible_event_memberships.user_id = ?
                            AND visible_event_memberships.status = 'active'
                      )";
                array_push($bindings, $viewerId, $tenantId, $viewerId);
            }

            $sql .= "
                  )";
        }

        $sql .= "
            )
        )";

        return [$sql, $bindings];
    }

    private static function isTenantAdmin(?int $userId, int $tenantId): bool
    {
        if ($userId === null) {
            return false;
        }

        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->select([
                'role',
                'is_admin',
                'is_super_admin',
                'is_tenant_super_admin',
                'is_god',
            ])
            ->first();

        return $user !== null && (
            in_array($user->role ?? '', ['admin', 'tenant_admin', 'super_admin', 'god'], true)
            || !empty($user->is_admin)
            || !empty($user->is_super_admin)
            || !empty($user->is_tenant_super_admin)
            || !empty($user->is_god)
        );
    }

    /**
     * Get attendance records for an event.
     */
    public static function getAttendanceRecords(int $eventId, ?int $viewerId = null): ?array
    {
        $tenantId = (int) \App\Core\TenantContext::getId();
        $event = Event::query()->find($eventId);

        if ($event === null || ! self::policyAllows($event, $viewerId, 'manageAttendance')) {
            return null;
        }

        try {
            $rows = DB::select(
                "SELECT a.*, u.name, u.first_name, u.last_name, u.avatar_url,
                        cb.name as checked_in_by_name
                 FROM event_attendance a
                 JOIN users u ON a.user_id = u.id
                 LEFT JOIN users cb ON a.checked_in_by = cb.id
                 WHERE a.event_id = ? AND a.tenant_id = ?
                 ORDER BY a.checked_in_at ASC",
                [$eventId, $tenantId]
            );

            $items = [];
            foreach ($rows as $r) {
                $items[] = [
                    'user_id'        => (int) $r->user_id,
                    'name'           => $r->name ?? trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')),
                    'first_name'     => $r->first_name ?? null,
                    'last_name'      => $r->last_name ?? null,
                    'avatar_url'     => $r->avatar_url,
                    'checked_in_at'  => $r->checked_in_at,
                    'checked_in_by'  => $r->checked_in_by_name ?? null,
                    'hours_credited' => $r->hours_credited ? (float) $r->hours_credited : null,
                    'notes'          => $r->notes,
                ];
            }

            return $items;
        } catch (\Exception $e) {
            Log::error("EventService::getAttendanceRecords error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark a user as attended at an event.
     */
    public static function markAttended(
        int $eventId,
        int $attendeeId,
        int $markedById,
        ?float $hoursOverride = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
    ): bool {
        self::$errors = [];
        self::$lastAttendanceResult = null;

        try {
            self::$lastAttendanceResult = app(EventAttendanceService::class)->record(
                $eventId,
                $attendeeId,
                self::attendanceActor($markedById),
                $hoursOverride,
                $notes,
                $idempotencyKey,
            );

            return true;
        } catch (EventAttendanceException $exception) {
            self::$errors[] = self::attendanceCompatibilityError($exception);
            return false;
        } catch (\Throwable $exception) {
            Log::error('EventService::markAttended error', [
                'event_id' => $eventId,
                'attendee_id' => $attendeeId,
                'actor_id' => $markedById,
                'error' => $exception->getMessage(),
            ]);
            self::$errors[] = [
                'code' => 'SERVER_ERROR',
                'message' => __('api.event_mark_attendance_failed'),
            ];
            return false;
        }
    }

    public static function getLastAttendanceResult(): ?EventAttendanceResult
    {
        return self::$lastAttendanceResult;
    }

    private static function attendanceActor(int $actorId): User
    {
        $tenantId = TenantContext::currentId();
        if ($tenantId === null || $tenantId <= 0) {
            throw new EventAttendanceException('event_attendance_tenant_context_missing');
        }

        $actor = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($actorId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();
        if ($actor === null) {
            throw new EventAttendanceException('event_attendance_authorization_denied');
        }

        return $actor;
    }

    /** @return array{code:string,message:string,field?:string} */
    private static function attendanceCompatibilityError(EventAttendanceException $exception): array
    {
        return match ($exception->reasonCode) {
            'event_attendance_event_not_found',
            'event_attendance_concrete_occurrence_required' => [
                'code' => 'NOT_FOUND',
                'message' => __('api.event_not_found'),
            ],
            'event_attendance_authorization_denied' => [
                'code' => 'FORBIDDEN',
                'message' => __('api.event_attendance_forbidden'),
            ],
            'event_attendance_attendee_not_found' => [
                'code' => 'NOT_FOUND',
                'message' => __('api.user_not_found'),
                'field' => 'user_id',
            ],
            'event_attendance_registration_required' => [
                'code' => 'VALIDATION_ERROR',
                'message' => __('api.event_not_rsvped'),
                'field' => 'user_id',
            ],
            'event_attendance_too_early' => [
                'code' => 'VALIDATION_ERROR',
                'message' => __('api.event_too_early_checkin'),
            ],
            'event_attendance_window_closed' => [
                'code' => 'VALIDATION_ERROR',
                'message' => __('api.event_ended_checkin'),
            ],
            'event_attendance_event_unavailable' => [
                'code' => 'VALIDATION_ERROR',
                'message' => __('api.event_checkin_failed'),
            ],
            'event_attendance_idempotency_conflict' => [
                'code' => 'IDEMPOTENCY_CONFLICT',
                'message' => __('api.event_checkin_failed'),
                'field' => 'idempotency_key',
            ],
            'event_attendance_idempotency_key_invalid' => [
                'code' => 'VALIDATION_ERROR',
                'message' => __('api.invalid_input'),
                'field' => 'idempotency_key',
            ],
            'event_attendance_hours_invalid' => [
                'code' => 'VALIDATION_ERROR',
                'message' => __('api.invalid_amount'),
                'field' => 'hours',
            ],
            'event_attendance_notes_too_long' => [
                'code' => 'VALIDATION_ERROR',
                'message' => __('api.invalid_input'),
                'field' => 'notes',
            ],
            'event_attendance_subject_invalid',
            'event_attendance_schedule_invalid' => [
                'code' => 'VALIDATION_ERROR',
                'message' => __('api.invalid_input'),
            ],
            default => [
                'code' => 'SERVER_ERROR',
                'message' => __('api.event_mark_attendance_failed'),
            ],
        };
    }

    /** @return array<string,mixed> */
    private static function attendanceSuccessPayload(EventAttendanceResult $result): array
    {
        $data = $result->toArray();
        $alreadyCheckedIn = $result->outcome === 'already_checked_in';

        return [
            'attendance_id' => $data['attendance_id'],
            'event_id' => $data['event_id'],
            'user_id' => $data['user_id'],
            'attendee_id' => $data['user_id'],
            'success' => true,
            'outcome' => $result->outcome,
            'checked_in' => (bool) $data['checked_in'],
            'marked' => $result->outcome === 'checked_in',
            'already_checked_in' => $alreadyCheckedIn,
            'replayed' => $alreadyCheckedIn,
            'checked_in_at' => $data['checked_in_at'],
            'credit_status' => $data['credit_status'],
            'hours_credited' => $data['hours_credited'],
            'attendance_version' => $data['attendance_version'],
        ];
    }

    /**
     * @param array{code:string,message:string,field?:string} $error
     * @return array<string,mixed>
     */
    private static function attendanceFailurePayload(int $attendeeId, array $error): array
    {
        return [
            'user_id' => $attendeeId,
            'attendee_id' => $attendeeId,
            'success' => false,
            'outcome' => 'failed',
            'checked_in' => false,
            'marked' => false,
            'already_checked_in' => false,
            'replayed' => false,
            'error' => [
                'code' => $error['code'],
                'message' => $error['message'],
                'field' => $error['field'] ?? null,
            ],
        ];
    }

    /**
     * Fail-closed compatibility boundary for the retired automatic credit path.
     *
     * The former implementation charged the acting checker (including a tenant
     * administrator) and used mutable RSVP status as its idempotency claim.
     *
     * @return string 'credit_disabled'
     */
    public static function recordCheckInCredit(int $eventId, int $attendeeId, int $checkedInBy, float $duration, string $eventTitle): string
    {
        $mode = strtolower(trim((string) config('events.attendance_credit_mode', 'off')));

        // No legacy/on escape hatch is intentionally provided. Unknown values
        // fail closed until immutable credit claims and an explicit funder exist.
        if ($mode !== 'off') {
            Log::critical('Unsupported event attendance credit mode failed closed', [
                'mode' => $mode,
                'tenant_id' => \App\Core\TenantContext::getId(),
                'event_id' => $eventId,
                'attendee_id' => $attendeeId,
                'actor_id' => $checkedInBy,
            ]);
        }

        return 'credit_disabled';
    }

    /**
     * Bulk mark users as attended.
     *
     * @return array<string,mixed>
     */
    public static function bulkMarkAttended(
        int $eventId,
        array $attendeeIds,
        int $markedById,
        ?float $hoursOverride = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
    ): array {
        self::$errors = [];
        self::$lastAttendanceResult = null;
        if (count($attendeeIds) > self::MAX_BULK_ATTENDANCE) {
            self::$errors[] = [
                'code' => 'VALIDATION_ERROR',
                'message' => __('api.bulk_too_many', ['max' => self::MAX_BULK_ATTENDANCE]),
            ];
            return [
                'total' => count($attendeeIds),
                'processed' => 0,
                'successful' => 0,
                'marked' => 0,
                'already_checked_in' => 0,
                'failed' => count($attendeeIds),
                'complete' => false,
                'partial_success' => false,
                'outcomes' => [],
            ];
        }

        $attendeeIds = array_values(array_unique(array_map('intval', $attendeeIds)));
        try {
            $actor = self::attendanceActor($markedById);
        } catch (EventAttendanceException $exception) {
            $error = self::attendanceCompatibilityError($exception);
            self::$errors[] = $error;
            $outcomes = array_map(
                static fn (int $attendeeId): array => self::attendanceFailurePayload($attendeeId, $error),
                $attendeeIds,
            );

            return [
                'total' => count($attendeeIds),
                'processed' => count($outcomes),
                'successful' => 0,
                'marked' => 0,
                'already_checked_in' => 0,
                'failed' => count($outcomes),
                'complete' => false,
                'partial_success' => false,
                'outcomes' => $outcomes,
            ];
        }

        $service = app(EventAttendanceService::class);
        $outcomes = [];
        $marked = 0;
        $alreadyCheckedIn = 0;
        $failed = 0;
        foreach ($attendeeIds as $attendeeId) {
            try {
                $result = $service->record(
                    $eventId,
                    $attendeeId,
                    $actor,
                    $hoursOverride,
                    $notes,
                    $idempotencyKey,
                );
                self::$lastAttendanceResult = $result;
                $outcomes[] = self::attendanceSuccessPayload($result);
                if ($result->outcome === 'already_checked_in') {
                    $alreadyCheckedIn++;
                } else {
                    $marked++;
                }
            } catch (EventAttendanceException $exception) {
                $error = self::attendanceCompatibilityError($exception);
                self::$errors[] = array_merge($error, ['user_id' => $attendeeId]);
                $outcomes[] = self::attendanceFailurePayload($attendeeId, $error);
                $failed++;
            } catch (\Throwable $exception) {
                Log::error('EventService::bulkMarkAttended item error', [
                    'event_id' => $eventId,
                    'attendee_id' => $attendeeId,
                    'actor_id' => $markedById,
                    'error' => $exception->getMessage(),
                ]);
                $error = [
                    'code' => 'SERVER_ERROR',
                    'message' => __('api.event_mark_attendance_failed'),
                ];
                self::$errors[] = array_merge($error, ['user_id' => $attendeeId]);
                $outcomes[] = self::attendanceFailurePayload($attendeeId, $error);
                $failed++;
            }
        }

        $successful = $marked + $alreadyCheckedIn;

        return [
            'total' => count($attendeeIds),
            'processed' => count($outcomes),
            'successful' => $successful,
            'marked' => $marked,
            'already_checked_in' => $alreadyCheckedIn,
            'failed' => $failed,
            'complete' => $failed === 0,
            'partial_success' => $successful > 0 && $failed > 0,
            'outcomes' => $outcomes,
        ];
    }

    /**
     * Get all event series for the current tenant.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getAllSeries(array $filters = [], ?int $viewerId = null): array
    {
        $tenantId = (int) TenantContext::getId();
        $limit = max(1, min((int) ($filters['limit'] ?? 20), 100));
        $cursorKind = 'events.series';
        $queryIdentity = self::discoveryQueryIdentity([
            'tenant_id' => $tenantId,
            'viewer_id' => $viewerId,
            'sort' => 'created_desc_id_desc',
        ]);
        $snapshotAt = now()->format('Y-m-d H:i:s');
        $cursorPosition = null;
        if (array_key_exists('cursor', $filters)) {
            $cursor = $filters['cursor'];
            if (!is_string($cursor) || $cursor === '') {
                self::rejectDiscoveryCursor();
            }
            $decodedCursor = EventDiscoveryCursor::decode($cursor, $cursorKind, $queryIdentity);
            $snapshotAt = self::validateDiscoveryCursorDate($decodedCursor['at']);
            $cursorPosition = $decodedCursor['p'];
        }

        [$eventVisibilitySql, $eventVisibilityBindings] = self::eventGroupVisibilitySql(
            'series_events',
            $viewerId,
            $tenantId
        );
        $cursorSql = '';
        $cursorBindings = [];
        if ($cursorPosition !== null) {
            $cursorCreatedAt = self::validateDiscoveryCursorDate($cursorPosition['created_at'] ?? null);
            $cursorId = self::validateDiscoveryCursorId($cursorPosition['id'] ?? null);
            $cursorSql = " AND (COALESCE(s.created_at, '1970-01-01 00:00:00') < ?"
                . " OR (COALESCE(s.created_at, '1970-01-01 00:00:00') = ? AND s.id < ?))";
            $cursorBindings = [$cursorCreatedAt, $cursorCreatedAt, $cursorId];
        }

        try {
            $rows = DB::select(
                "SELECT s.*, u.name as creator_name,
                        COALESCE(s.created_at, '1970-01-01 00:00:00') as sort_created_at,
                        (SELECT COUNT(*) FROM events series_events
                         WHERE series_events.series_id = s.id AND series_events.tenant_id = ?
                           AND (series_events.status IS NULL OR series_events.status = 'active')
                           {$eventVisibilitySql}) as event_count,
                        (SELECT MIN(series_events.start_time) FROM events series_events
                         WHERE series_events.series_id = s.id AND series_events.tenant_id = ?
                           AND series_events.start_time >= ?
                           AND (series_events.status IS NULL OR series_events.status = 'active')
                           {$eventVisibilitySql}) as next_event
                 FROM event_series s
                 JOIN users u ON s.created_by = u.id AND u.tenant_id = s.tenant_id
                 WHERE s.tenant_id = ?{$cursorSql}
                 ORDER BY COALESCE(s.created_at, '1970-01-01 00:00:00') DESC, s.id DESC
                 LIMIT ?",
                array_merge(
                    [$tenantId],
                    $eventVisibilityBindings,
                    [$tenantId, $snapshotAt],
                    $eventVisibilityBindings,
                    [$tenantId],
                    $cursorBindings,
                    [$limit + 1]
                )
            );

            $items = array_map(fn($r) => (array) $r, $rows);
            $hasMore = count($items) > $limit;
            if ($hasMore) {
                array_pop($items);
            }

            $nextCursor = null;
            if ($hasMore && $items !== []) {
                $last = $items[array_key_last($items)];
                $nextCursor = EventDiscoveryCursor::encode(
                    $cursorKind,
                    $queryIdentity,
                    $snapshotAt,
                    [
                        'created_at' => self::validateDiscoveryCursorDate($last['sort_created_at']),
                        'id' => (int) $last['id'],
                    ]
                );
            }

            $formatted = [];
            foreach ($items as $s) {
                $formatted[] = [
                    'id'          => (int) $s['id'],
                    'title'       => $s['title'],
                    'description' => $s['description'],
                    'event_count' => (int) $s['event_count'],
                    'next_event'  => $s['next_event'],
                    'creator'     => $s['creator_name'],
                    'created_at'  => $s['created_at'],
                ];
            }

            return ['items' => $formatted, 'cursor' => $nextCursor, 'has_more' => $hasMore];
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("EventService::getAllSeries error: " . $e->getMessage());
            return ['items' => [], 'cursor' => null, 'has_more' => false];
        }
    }

    /**
     * Create an event series.
     */
    public static function createSeries(int $userId, string $title, ?string $description = null): ?int
    {
        self::$errors = [];
        $tenantId = \App\Core\TenantContext::getId();

        if (empty(trim($title))) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.event_series_title_required'), 'field' => 'title'];
            return null;
        }

        try {
            DB::statement(
                "INSERT INTO event_series (tenant_id, title, description, created_by) VALUES (?, ?, ?, ?)",
                [$tenantId, trim($title), $description, $userId]
            );
            return (int) DB::getPdo()->lastInsertId();
        } catch (\Exception $e) {
            Log::error("EventService::createSeries error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => __('api.event_series_create_failed')];
            return null;
        }
    }

    /**
     * Get series info.
     */
    public static function getSeriesInfo(int $seriesId, ?int $viewerId = null): ?array
    {
        $tenantId = \App\Core\TenantContext::getId();
        [$eventVisibilitySql, $eventVisibilityBindings] = self::eventGroupVisibilitySql(
            'series_events',
            $viewerId,
            (int) $tenantId
        );

        try {
            $series = DB::selectOne(
                "SELECT s.*, u.name as creator_name,
                        (SELECT COUNT(*) FROM events series_events
                         WHERE series_events.series_id = s.id AND series_events.tenant_id = ?
                           AND (series_events.status IS NULL OR series_events.status = 'active')
                           {$eventVisibilitySql}) as event_count
                 FROM event_series s
                 JOIN users u ON s.created_by = u.id AND u.tenant_id = s.tenant_id
                 WHERE s.id = ? AND s.tenant_id = ?",
                array_merge([$tenantId], $eventVisibilityBindings, [$seriesId, $tenantId])
            );

            if (!$series) {
                return null;
            }

            return [
                'id'          => (int) $series->id,
                'title'       => $series->title,
                'description' => $series->description,
                'event_count' => (int) $series->event_count,
                'creator'     => $series->creator_name,
            ];
        } catch (\Exception $e) {
            Log::debug('[EventService] getSeriesInfo failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get events in a series.
     */
    public static function getSeriesEvents(int $seriesId, ?int $viewerId = null): array
    {
        $tenantId = \App\Core\TenantContext::getId();
        [$eventVisibilitySql, $eventVisibilityBindings] = self::eventGroupVisibilitySql(
            'e',
            $viewerId,
            (int) $tenantId
        );

        try {
            $rows = DB::select(
                "SELECT e.id, e.title, e.start_time, e.end_time, e.status, e.location,
                        (SELECT COUNT(*) FROM event_rsvps
                         WHERE event_id = e.id AND tenant_id = e.tenant_id AND status = 'going') as going_count
                 FROM events e
                 WHERE e.series_id = ? AND e.tenant_id = ?
                   AND (e.status IS NULL OR e.status = 'active')
                 {$eventVisibilitySql}
                 ORDER BY e.start_time ASC, e.id ASC",
                array_merge([$seriesId, $tenantId], $eventVisibilityBindings)
            );

            $items = [];
            foreach ($rows as $e) {
                $items[] = [
                    'id'          => (int) $e->id,
                    'title'       => $e->title,
                    'start_time'  => $e->start_time,
                    'end_time'    => $e->end_time,
                    'status'      => $e->status ?? 'active',
                    'location'    => $e->location,
                    'going_count' => (int) ($e->going_count ?? 0),
                ];
            }

            return $items;
        } catch (\Exception $e) {
            Log::error("EventService::getSeriesEvents error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Link an event to a series.
     */
    public static function linkToSeries(int $eventId, int $seriesId, int $userId): bool
    {
        self::$errors = [];
        $tenantId = \App\Core\TenantContext::getId();

        $event = Event::query()->find($eventId);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.event_not_found')];
            return false;
        }

        if (! self::policyAllows($event, $userId, 'linkSeries')) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.event_modify_forbidden')];
            return false;
        }

        $ownedSeriesId = self::resolveSeriesId($seriesId, $userId);
        if ($ownedSeriesId === null) {
            self::$errors[] = [
                'code' => 'VALIDATION_ERROR',
                'message' => __('api.event_series_not_found'),
                'field' => 'series_id',
            ];
            return false;
        }

        try {
            DB::update("UPDATE events SET series_id = ? WHERE id = ? AND tenant_id = ?", [$ownedSeriesId, $eventId, $tenantId]);
            return true;
        } catch (\Exception $e) {
            Log::error("EventService::linkToSeries error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => __('api.event_series_link_failed')];
            return false;
        }
    }

    /**
     * Create a recurring event with recurrence rule.
     *
     * @return array{template_id: int, occurrences: int}|null
     */
    public static function createRecurring(int $userId, array $data): ?array
    {
        self::$errors = [];

        if ((bool) config('events.recurrence.engine_v2_enabled', false)) {
            return self::createRecurringV2($userId, $data);
        }

        // Validate recurrence frequency
        $frequency = $data['recurrence_frequency'] ?? null;
        $validFrequencies = ['daily', 'weekly', 'monthly', 'yearly', 'custom'];
        if (!$frequency || !in_array($frequency, $validFrequencies)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.event_recurrence_frequency_required'), 'field' => 'recurrence_frequency'];
            return null;
        }

        validator($data, [
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'recurrence_days' => ['nullable', 'string', 'max:50'],
            'recurrence_day_of_month' => ['nullable', 'integer', 'between:1,31'],
            'recurrence_ends_type' => ['nullable', Rule::in(['never', 'after_count', 'on_date'])],
            'recurrence_ends_after_count' => ['nullable', 'integer', 'between:1,52'],
            'recurrence_ends_on_date' => ['nullable', 'date'],
            'recurrence_rrule' => ['nullable', 'string', 'max:2048'],
        ])->validate();

        $tenantId = (int) TenantContext::getId();

        try {
            return DB::transaction(function () use ($userId, $data, $tenantId, $frequency): array {
                // A template is an abstract schedule definition, never a
                // concrete registration target, so it receives no key.
                $template = self::create($userId, array_merge($data, [
                    '_is_recurring_template' => true,
                ]));
                $templateId = (int) $template->id;

                DB::table('events')
                    ->where('id', $templateId)
                    ->where('tenant_id', $tenantId)
                    ->update([
                        'is_recurring_template' => 1,
                        'occurrence_key' => null,
                        'recurrence_engine' => self::RECURRENCE_ENGINE,
                        'recurrence_engine_version' => self::RECURRENCE_ENGINE_VERSION,
                    ]);

                $interval = (int) ($data['recurrence_interval'] ?? 1);
                $daysOfWeek = $data['recurrence_days'] ?? null;
                $dayOfMonth = $data['recurrence_day_of_month'] ?? null;
                $endsType = $data['recurrence_ends_type'] ?? 'after_count';
                $endsAfterCount = $data['recurrence_ends_after_count'] ?? 10;
                $endsOnDate = $data['recurrence_ends_on_date'] ?? null;
                $rrule = $data['recurrence_rrule'] ?? null;

                DB::statement(
                    "INSERT INTO event_recurrence_rules
                     (event_id, tenant_id, frequency, interval_value, days_of_week, day_of_month, rrule, ends_type, ends_after_count, ends_on_date)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $templateId,
                        $tenantId,
                        $frequency,
                        $interval,
                        $daysOfWeek,
                        $dayOfMonth,
                        $rrule,
                        $endsType,
                        $endsAfterCount,
                        $endsOnDate,
                    ]
                );

                return [
                    'template_id' => $templateId,
                    'occurrences' => self::generateOccurrences($templateId, $data),
                ];
            });
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error("EventService::createRecurring error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => __('api.event_recurring_create_failed')];
            return null;
        }
    }

    /**
     * Generate occurrence events from a recurrence template.
     */
    private static function generateOccurrences(int $templateId, array $data): int
    {
        $tenantId = \App\Core\TenantContext::getId();

        $rule = DB::selectOne("SELECT * FROM event_recurrence_rules WHERE event_id = ? AND tenant_id = ?", [$templateId, $tenantId]);
        if (!$rule) {
            return 0;
        }

        $template = DB::selectOne("SELECT * FROM events WHERE id = ? AND tenant_id = ?", [$templateId, $tenantId]);
        if (!$template) {
            return 0;
        }

        $eventTimezone = self::isIanaTimezone((string) ($template->timezone ?? ''))
            ? (string) $template->timezone
            : 'UTC';
        $eventZone = new \DateTimeZone($eventTimezone);
        $utcZone = new \DateTimeZone('UTC');
        $startTime = (new \DateTime((string) $template->start_time, $utcZone))->setTimezone($eventZone);
        $endTime = $template->end_time
            ? (new \DateTime((string) $template->end_time, $utcZone))->setTimezone($eventZone)
            : null;
        $duration = $endTime ? $startTime->diff($endTime) : null;

        $frequency = $rule->frequency;
        $interval = max(1, (int) $rule->interval_value);
        $endsType = $rule->ends_type;
        $maxOccurrences = $endsType === 'after_count' ? min((int) ($rule->ends_after_count ?? 10), 52) : 52;
        $endsOnDate = $rule->ends_on_date
            ? new \DateTime($rule->ends_on_date . ' 23:59:59', $eventZone)
            : null;

        $occurrences = [];
        $current = clone $startTime;
        $monthsAdded = 0;

        for ($i = 0; $i < $maxOccurrences; $i++) {
            switch ($frequency) {
                case 'daily':   $current->modify("+{$interval} days"); break;
                case 'weekly':  $current->modify("+{$interval} weeks"); break;
                case 'monthly':
                    // Re-anchor each occurrence to the template's day-of-month,
                    // clamped to the target month's length. Naive mutable
                    // "+1 month" overflows (May 31 → Jul 1) and the series then
                    // permanently drifts to the 1st, skipping months entirely.
                    $monthsAdded += $interval;
                    $anchor = clone $startTime;
                    $anchor->modify('first day of this month');
                    $anchor->modify("+{$monthsAdded} months");
                    $dayOfMonth = min((int) $startTime->format('j'), (int) $anchor->format('t'));
                    $current = $anchor->setDate((int) $anchor->format('Y'), (int) $anchor->format('n'), $dayOfMonth);
                    break;
                case 'yearly':  $current->modify("+{$interval} years"); break;
                default:        $current->modify("+{$interval} weeks"); break;
            }

            if ($endsOnDate && $current > $endsOnDate) {
                break;
            }
            $oneYearOut = new \DateTime('+1 year', $eventZone);
            if ($current > $oneYearOut) {
                break;
            }

            $occStart = clone $current;
            $occEnd = null;
            if ($duration) {
                $occEnd = clone $occStart;
                $occEnd->add($duration);
            }

            $occurrences[] = [
                'start' => (clone $occStart)->setTimezone($utcZone)->format('Y-m-d H:i:s'),
                'end'   => $occEnd
                    ? (clone $occEnd)->setTimezone($utcZone)->format('Y-m-d H:i:s')
                    : null,
                'date'  => $occStart->format('Y-m-d'),
            ];
        }

        $count = 0;
        foreach ($occurrences as $occ) {
            try {
                DB::transaction(function () use ($tenantId, $template, $templateId, $occ): void {
                    $occurrenceId = (int) DB::table('events')->insertGetId([
                        'tenant_id' => $tenantId,
                        'user_id' => (int) $template->user_id,
                        'title' => $template->title,
                        'description' => $template->description ?? '',
                        'location' => $template->location,
                        'start_time' => $occ['start'],
                        'end_time' => $occ['end'],
                        'timezone' => $template->timezone ?? 'UTC',
                        'timezone_source' => $template->timezone_source ?? 'preexisting_unverified',
                        'all_day' => (int) ($template->all_day ?? 0),
                        'group_id' => $template->group_id,
                        'category_id' => $template->category_id,
                        'latitude' => $template->latitude,
                        'longitude' => $template->longitude,
                        'accessibility_step_free' => $template->accessibility_step_free,
                        'accessibility_toilet' => $template->accessibility_toilet,
                        'accessibility_hearing_loop' => $template->accessibility_hearing_loop,
                        'accessibility_quiet_space' => $template->accessibility_quiet_space,
                        'accessibility_seating' => $template->accessibility_seating,
                        'accessibility_parking' => $template->accessibility_parking,
                        'accessibility_parking_details' => $template->accessibility_parking_details,
                        'accessibility_transit_details' => $template->accessibility_transit_details,
                        'accessibility_assistance_contact' => $template->accessibility_assistance_contact,
                        'accessibility_notes' => $template->accessibility_notes,
                        'federated_visibility' => $template->federated_visibility ?? 'none',
                        'parent_event_id' => $templateId,
                        'occurrence_date' => $occ['date'],
                        'occurrence_key' => null,
                        'recurrence_engine' => self::RECURRENCE_ENGINE,
                        'recurrence_engine_version' => self::RECURRENCE_ENGINE_VERSION,
                        'is_recurring_template' => 0,
                        'max_attendees' => $template->max_attendees,
                        'series_id' => $template->series_id,
                        'cover_image' => $template->cover_image,
                        'image_url' => $template->image_url,
                        'is_online' => (int) ($template->is_online ?? 0),
                        'online_link' => $template->online_link,
                        'allow_remote_attendance' => (int) ($template->allow_remote_attendance ?? 0),
                        'video_url' => $template->video_url,
                        'status' => 'draft',
                        'publication_status' => EventPublicationState::Draft->value,
                        'operational_status' => EventOperationalState::Scheduled->value,
                        'lifecycle_version' => 0,
                        'calendar_sequence' => 0,
                        'publication_status_changed_at' => now(),
                        'publication_status_changed_by' => (int) $template->user_id,
                        'operational_status_changed_at' => now(),
                        'operational_status_changed_by' => (int) $template->user_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('events')
                        ->where('tenant_id', $tenantId)
                        ->where('id', $occurrenceId)
                        ->update([
                            'occurrence_key' => self::newOccurrenceKey($tenantId, $occurrenceId),
                        ]);
                });
                $count++;
            } catch (\Throwable $e) {
                Log::error("Failed to generate occurrence: " . $e->getMessage());
                throw $e;
            }
        }

        return $count;
    }

    /**
     * Re-run deterministic materialisation for a v2 template.
     *
     * No controller is exposed during the rollout foundation. This service
     * boundary exists so a future admin repair command or series editor can
     * regenerate safely without duplicating concrete registration targets.
     */
    public static function regenerateRecurring(int $templateId, int $userId): ?int
    {
        self::$errors = [];
        $tenantId = (int) TenantContext::getId();

        /** @var Event|null $template */
        $template = Event::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $templateId)
            ->where('is_recurring_template', 1)
            ->first();

        if ($template === null) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.event_not_found')];
            return null;
        }
        if (! self::policyAllows($template, $userId, 'manage')) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.event_edit_forbidden')];
            return null;
        }
        if ((string) $template->getRawOriginal('recurrence_engine') !== EventRecurrenceService::ENGINE
            || (string) $template->getRawOriginal('recurrence_engine_version') !== EventRecurrenceService::ENGINE_VERSION) {
            self::$errors[] = [
                'code' => 'VALIDATION_ERROR',
                'message' => __('api.invalid_input'),
                'field' => 'recurrence_engine',
            ];
            return null;
        }

        try {
            return DB::transaction(
                static fn (): int => self::generateOccurrencesV2($templateId, $tenantId),
            );
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('EventService::regenerateRecurring error: ' . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => __('api.event_recurring_create_failed')];
            return null;
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array{template_id:int,occurrences:int}
     */
    private static function createRecurringV2(int $userId, array $data): array
    {
        $tenantId = (int) TenantContext::getId();

        return DB::transaction(function () use ($userId, $data, $tenantId): array {
            // The writer first persists its normalised UTC/timezone contract.
            // Recurrence normalisation happens inside the same transaction, so
            // an invalid or unsupported RRULE rolls the template back as well.
            $template = self::create($userId, array_merge($data, [
                '_is_recurring_template' => true,
            ]));
            $templateId = (int) $template->id;
            $startUtc = new \DateTimeImmutable(
                (string) $template->getRawOriginal('start_time'),
                new \DateTimeZone('UTC'),
            );
            $recurrence = app(EventRecurrenceService::class);
            $definition = $recurrence->normalize(
                $data,
                $startUtc,
                (string) ($template->getRawOriginal('timezone') ?: 'UTC'),
            );

            DB::table('events')
                ->where('tenant_id', $tenantId)
                ->where('id', $templateId)
                ->update([
                    'is_recurring_template' => 1,
                    'occurrence_key' => null,
                    'recurrence_engine' => EventRecurrenceService::ENGINE,
                    'recurrence_engine_version' => EventRecurrenceService::ENGINE_VERSION,
                ]);

            DB::table('event_recurrence_rules')->insert([
                'event_id' => $templateId,
                'tenant_id' => $tenantId,
                'frequency' => $definition['frequency'],
                'interval_value' => $definition['interval'],
                'days_of_week' => $definition['days_of_week'],
                // The legacy compatibility column is unsigned. Negative values
                // such as BYMONTHDAY=-1 remain losslessly represented in RRULE.
                'day_of_month' => ($definition['day_of_month'] ?? 0) > 0
                    ? $definition['day_of_month']
                    : null,
                'month_of_year' => $definition['month_of_year'],
                'rrule' => $definition['rrule'],
                'exdates' => json_encode($definition['exdates'], JSON_THROW_ON_ERROR),
                'rdates' => json_encode($definition['rdates'], JSON_THROW_ON_ERROR),
                'recurrence_engine' => EventRecurrenceService::ENGINE,
                'recurrence_engine_version' => EventRecurrenceService::ENGINE_VERSION,
                'rule_hash' => $definition['rule_hash'],
                'ends_type' => $definition['ends_type'],
                'ends_after_count' => $definition['ends_after_count'],
                'ends_on_date' => $definition['ends_on_date'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'template_id' => $templateId,
                'occurrences' => self::generateOccurrencesV2($templateId, $tenantId),
            ];
        });
    }

    private static function generateOccurrencesV2(int $templateId, int $tenantId): int
    {
        $template = DB::table('events')
            ->where('tenant_id', $tenantId)
            ->where('id', $templateId)
            ->where('is_recurring_template', 1)
            ->lockForUpdate()
            ->first();
        $rule = DB::table('event_recurrence_rules')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $templateId)
            ->lockForUpdate()
            ->first();

        if ($template === null || $rule === null
            || (string) ($rule->recurrence_engine ?? '') !== EventRecurrenceService::ENGINE
            || (string) ($rule->recurrence_engine_version ?? '') !== EventRecurrenceService::ENGINE_VERSION) {
            throw ValidationException::withMessages([
                'recurrence_engine' => [__('api.invalid_input')],
            ]);
        }

        $utc = new \DateTimeZone('UTC');
        $startUtc = new \DateTimeImmutable((string) $template->start_time, $utc);
        $endUtc = $template->end_time !== null
            ? new \DateTimeImmutable((string) $template->end_time, $utc)
            : null;
        $recurrence = app(EventRecurrenceService::class);
        $occurrences = $recurrence->expand(
            $startUtc,
            $endUtc,
            (string) ($template->timezone ?: 'UTC'),
            (string) $rule->rrule,
            self::decodeRecurrenceDates($rule->exdates ?? null),
            self::decodeRecurrenceDates($rule->rdates ?? null),
        );

        $inserted = 0;
        foreach ($occurrences as $occurrence) {
            $occurrenceKey = $recurrence->occurrenceKey(
                $tenantId,
                $templateId,
                $occurrence['recurrence_id'],
            );
            $now = now();
            $didInsert = DB::table('events')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'user_id' => (int) $template->user_id,
                'title' => $template->title,
                'description' => $template->description ?? '',
                'location' => $template->location,
                'start_time' => $occurrence['start_utc'],
                'end_time' => $occurrence['end_utc'],
                'timezone' => $template->timezone ?? 'UTC',
                'timezone_source' => $template->timezone_source ?? 'preexisting_unverified',
                'all_day' => (int) ($template->all_day ?? 0),
                'group_id' => $template->group_id,
                'category_id' => $template->category_id,
                'latitude' => $template->latitude,
                'longitude' => $template->longitude,
                'accessibility_step_free' => $template->accessibility_step_free,
                'accessibility_toilet' => $template->accessibility_toilet,
                'accessibility_hearing_loop' => $template->accessibility_hearing_loop,
                'accessibility_quiet_space' => $template->accessibility_quiet_space,
                'accessibility_seating' => $template->accessibility_seating,
                'accessibility_parking' => $template->accessibility_parking,
                'accessibility_parking_details' => $template->accessibility_parking_details,
                'accessibility_transit_details' => $template->accessibility_transit_details,
                'accessibility_assistance_contact' => $template->accessibility_assistance_contact,
                'accessibility_notes' => $template->accessibility_notes,
                'federated_visibility' => $template->federated_visibility ?? 'none',
                'parent_event_id' => $templateId,
                'occurrence_date' => $occurrence['occurrence_date'],
                'occurrence_key' => $occurrenceKey,
                'recurrence_engine' => EventRecurrenceService::ENGINE,
                'recurrence_engine_version' => EventRecurrenceService::ENGINE_VERSION,
                'is_recurring_template' => 0,
                'max_attendees' => $template->max_attendees,
                'series_id' => $template->series_id,
                'cover_image' => $template->cover_image,
                'image_url' => $template->image_url,
                'is_online' => (int) ($template->is_online ?? 0),
                'online_link' => $template->online_link,
                'allow_remote_attendance' => (int) ($template->allow_remote_attendance ?? 0),
                'video_url' => $template->video_url,
                'status' => 'draft',
                'publication_status' => EventPublicationState::Draft->value,
                'operational_status' => EventOperationalState::Scheduled->value,
                'lifecycle_version' => 0,
                'calendar_sequence' => 0,
                'publication_status_changed_at' => $now,
                'publication_status_changed_by' => (int) $template->user_id,
                'operational_status_changed_at' => $now,
                'operational_status_changed_by' => (int) $template->user_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($didInsert === 1) {
                $inserted++;
                continue;
            }

            $existing = DB::table('events')
                ->where('tenant_id', $tenantId)
                ->where('occurrence_key', $occurrenceKey)
                ->first(['parent_event_id', 'start_time', 'recurrence_engine', 'recurrence_engine_version']);
            if ($existing === null
                || (int) $existing->parent_event_id !== $templateId
                || (string) $existing->start_time !== $occurrence['start_utc']
                || (string) $existing->recurrence_engine !== EventRecurrenceService::ENGINE
                || (string) $existing->recurrence_engine_version !== EventRecurrenceService::ENGINE_VERSION) {
                throw new \LogicException('Deterministic event occurrence identity collision');
            }
        }

        return $inserted;
    }

    /** @return list<string> */
    private static function decodeRecurrenceDates(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw ValidationException::withMessages([
                    'recurrence_rrule' => [__('api.invalid_input')],
                ]);
            }
        }
        if (! is_array($value)) {
            throw ValidationException::withMessages([
                'recurrence_rrule' => [__('api.invalid_input')],
            ]);
        }

        $dates = [];
        foreach ($value as $date) {
            if (! is_string($date)) {
                throw ValidationException::withMessages([
                    'recurrence_rrule' => [__('api.invalid_input')],
                ]);
            }
            $dates[] = $date;
        }

        return $dates;
    }

    /**
     * Update recurring event(s).
     *
     * @param string $scope 'single' to update only this occurrence, 'all' for all future occurrences
     */
    public static function updateRecurring(int $eventId, int $userId, array $data, string $scope = 'single'): bool
    {
        self::$errors = [];
        $tenantId = \App\Core\TenantContext::getId();

        if ($scope === 'single') {
            $event = Event::query()->find($eventId);
            if (!$event) {
                self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.event_not_found')];
                return false;
            }
            if (! self::policyAllows($event, $userId, 'manage')) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.event_edit_forbidden')];
                return false;
            }

            // Detach from parent (make independent) and update
            DB::update("UPDATE events SET parent_event_id = NULL WHERE id = ? AND tenant_id = ?", [$eventId, $tenantId]);
            return self::update($eventId, $userId, $data);
        }

        // scope === 'all': update all future occurrences
        $event = Event::query()->find($eventId);
        if (!$event) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.event_not_found')];
            return false;
        }
        if (! self::policyAllows($event, $userId, 'manage')) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.event_edit_forbidden')];
            return false;
        }

        // Time fields are per-occurrence — applying one start_time to every
        // future occurrence would collapse the whole series onto a single
        // timestamp. Series-wide edits cover content fields only.
        unset($data['start_time'], $data['end_time']);

        $parentId = $event->parent_event_id ?? $eventId;

        $ids = DB::select(
            "SELECT id FROM events WHERE (parent_event_id = ? OR id = ?) AND tenant_id = ? AND start_time >= NOW()",
            [$parentId, $parentId, $tenantId]
        );

        $updated = 0;
        foreach ($ids as $row) {
            try {
                if (self::update((int) $row->id, $userId, $data)) {
                    $updated++;
                }
            } catch (\Exception $e) {
                Log::error("EventService::updateRecurring failed for event {$row->id}: " . $e->getMessage());
            }
        }

        return $updated > 0;
    }
}
