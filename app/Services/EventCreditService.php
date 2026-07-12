<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Fail-closed boundary for optional attendance-related time-credit effects.
 *
 * The durable claim schema exists so a reviewed funding model can be added
 * without reusing mutable RSVP state. No financial writer is active: the only
 * supported mode is off until payer, consent, amount and reversal semantics are
 * explicitly configured and tested.
 */
final class EventCreditService
{
    /** @return array{status:string,claim_id:null,transaction_id:null} */
    public function settleAttendance(
        Event $event,
        EventAttendance $attendance,
        User $attendee,
        User $actor,
    ): array {
        $mode = strtolower(trim((string) config('events.attendance_credit_mode', 'off')));
        if ($mode !== 'off') {
            Log::critical('Unsupported event attendance credit mode failed closed', [
                'mode' => $mode,
                'tenant_id' => (int) $event->tenant_id,
                'event_id' => (int) $event->getKey(),
                'attendance_id' => (int) $attendance->getKey(),
                'attendee_id' => (int) $attendee->getKey(),
                'actor_id' => (int) $actor->getKey(),
            ]);
        }

        return [
            'status' => 'disabled',
            'claim_id' => null,
            'transaction_id' => null,
        ];
    }
}
