<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use App\Enums\EventSafetyEnforcementMode;
use App\Exceptions\EventParticipationException;
use App\Models\Event;
use App\Models\User;
use App\Policies\EventPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/** Audience, Caring Community, and safeguarding boundary for every writer. */
final class EventParticipationEligibilityService
{
    public function __construct(
        private readonly EventPolicy $eventPolicy,
        private readonly SafeguardingInteractionPolicy $safeguarding,
        private readonly ?EventSafetyEligibilityService $eventSafety = null,
    ) {}

    public function assertCanParticipate(
        Event $event,
        User $subject,
        string $channel,
    ): void {
        $tenantId = TenantContext::currentId();
        if ($tenantId === null
            || $tenantId <= 0
            || DB::transactionLevel() <= 0
            || (int) $event->tenant_id !== $tenantId
            || (int) $subject->tenant_id !== $tenantId
            || (int) $event->getKey() <= 0
            || (int) $subject->getKey() <= 0) {
            throw new EventParticipationException('event_participation_scope_invalid');
        }
        if (! $this->eventPolicy->view($subject, $event)) {
            throw new EventParticipationException('event_participation_audience_denied');
        }
        if (Schema::hasTable('caring_kiss_treffen')) {
            $membersOnly = DB::table('caring_kiss_treffen')
                ->where('tenant_id', $tenantId)
                ->where('event_id', (int) $event->getKey())
                ->where('members_only', 1)
                ->lockForUpdate()
                ->first(['event_id']) !== null;
            if ($membersOnly && ! (bool) $subject->getAttribute('is_approved')) {
                throw new EventParticipationException(
                    'event_participation_kiss_treffen_members_only',
                );
            }
        }

        $organizerId = (int) $event->getAttribute('user_id');
        $subjectId = (int) $subject->getKey();
        if ($organizerId <= 0) {
            throw new EventParticipationException('event_participation_organizer_invalid');
        }
        if ($subjectId === $organizerId) {
            $this->assertSafety($event, $subject, $channel, $tenantId);
            return;
        }
        $organizer = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($organizerId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();
        if ($organizer === null) {
            throw new EventParticipationException('event_participation_organizer_invalid');
        }
        $this->safeguarding->assertLocalContactAllowed(
            $subjectId,
            $organizerId,
            $tenantId,
            $channel,
        );
        $this->safeguarding->assertLocalContactAllowed(
            $organizerId,
            $subjectId,
            $tenantId,
            $channel,
        );
        $this->assertSafety($event, $subject, $channel, $tenantId);
    }

    private function assertSafety(
        Event $event,
        User $subject,
        string $channel,
        int $tenantId,
    ): void {
        $rollout = EventSafetyEnforcementModeResolver::inspect($tenantId);
        if (! $rollout['configuration_valid']) {
            Log::critical('Event Safety participation rollout is invalid', [
                'tenant_id' => $tenantId,
                'event_id' => (int) $event->getKey(),
                'channel' => $channel,
                'reason_code' => 'event_safety_rollout_configuration_invalid',
            ]);
            throw new EventParticipationException('event_participation_safety_unavailable');
        }
        $mode = EventSafetyEnforcementMode::from($rollout['resolved_mode']);
        if (! $mode->evaluatesParticipation()) {
            return;
        }
        $decision = ($this->eventSafety ?? new EventSafetyEligibilityService())->evaluate(
            (int) $event->getKey(),
            $subject,
        );
        Log::info('Event Safety participation decision evaluated', [
            'tenant_id' => $tenantId,
            'event_id' => (int) $event->getKey(),
            'subject_reference_fingerprint' => hash(
                'sha256',
                $tenantId . ':' . (int) $subject->getKey(),
            ),
            'channel' => $channel,
            'mode' => $mode->value,
            'status' => $decision->status,
            'reason_codes' => $decision->reasonCodes,
            'requirements_version' => $decision->requirementsVersion,
        ]);
        if (! $mode->blocksParticipation() || $decision->isAllowed()) {
            return;
        }

        throw new EventParticipationException(
            $decision->isUnavailable()
                ? 'event_participation_safety_unavailable'
                : 'event_participation_safety_denied',
        );
    }
}
