<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a MEMBER initiates a GDPR data-rights action on themselves:
 * a data-subject request (access/erasure/rectification/restriction/objection/
 * portability), an immediate self-service account deletion, a personal-data
 * export, or a consent change.
 *
 * Consumed by {@see \App\Listeners\NotifyAdminOfGdprAction} so tenant admins
 * are alerted (bell + push + email) the moment the action happens — closing the
 * long-standing gap where a data-subject request became a silent `pending` row
 * that only surfaced once it was already ~25 days old (near the Art.12(3)
 * deadline) via the overdue-request cron.
 *
 * NOT dispatched for admin-initiated actions (e.g. an admin creating a request
 * on a member's behalf) — those bypass the member entry points on purpose.
 */
class GdprActionOccurred
{
    use Dispatchable;

    public const ACTION_REQUEST          = 'request';
    public const ACTION_ACCOUNT_DELETION = 'account_deletion';
    public const ACTION_DATA_EXPORT      = 'data_export';
    public const ACTION_CONSENT          = 'consent';

    /**
     * Stable per-event key so a queue re-delivery can't double-notify, while two
     * genuinely distinct actions (e.g. two exports) still each notify.
     */
    public readonly string $dedupeKey;

    /**
     * @param int         $userId      Member who performed the action. For
     *                                 'account_deletion' this row is anonymised
     *                                 by the time a queued listener runs, so the
     *                                 display name is carried in $subjectName.
     * @param int         $tenantId    Tenant the action occurred in.
     * @param string      $action      One of the ACTION_* constants.
     * @param string|null $detail      request_type | export format | consent slug.
     * @param bool|null   $granted     Consent grant state ('consent' action only).
     * @param int|null    $requestId   gdpr_requests row id ('request' action only).
     * @param string|null $subjectName Member display name captured BEFORE
     *                                 anonymisation ('account_deletion' only).
     */
    public function __construct(
        public readonly int $userId,
        public readonly int $tenantId,
        public readonly string $action,
        public readonly ?string $detail = null,
        public readonly ?bool $granted = null,
        public readonly ?int $requestId = null,
        public readonly ?string $subjectName = null,
    ) {
        // requestId uniquely identifies a request; other actions can legitimately
        // repeat (5 exports/day, many consent toggles) so a random suffix keeps
        // each distinct action notifiable while a serialized re-delivery of the
        // SAME event keeps the SAME key.
        $this->dedupeKey = $action . ':' . ($requestId !== null
            ? 'r' . $requestId
            : $userId . ':' . ($detail ?? '') . ':' . bin2hex(random_bytes(6)));
    }
}
