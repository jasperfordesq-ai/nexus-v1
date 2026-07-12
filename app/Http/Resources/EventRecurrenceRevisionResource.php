<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Resources;

/** Privacy-safe API projections for recurrence revision preview and commit. */
final class EventRecurrenceRevisionResource
{
    /** @param array<string,mixed> $preview @return array<string,mixed> */
    public static function preview(array $preview): array
    {
        return [
            'preview_token' => (string) $preview['preview_token'],
            'preview_expires_at' => (string) $preview['preview_expires_at'],
            'scope' => 'this_and_future',
            'selected_event_id' => (int) $preview['selected_event_id'],
            'root_event_id' => (int) $preview['root_event_id'],
            'effective_from_utc' => (string) $preview['effective_from_utc'],
            'can_commit' => (bool) $preview['can_commit'],
            'impact' => is_array($preview['impact'] ?? null) ? $preview['impact'] : [],
        ];
    }

    /** @param array<string,mixed> $commit @return array<string,mixed> */
    public static function commit(array $commit): array
    {
        return [
            'revision_id' => (int) $commit['revision_id'],
            'root_event_id' => (int) $commit['root_event_id'],
            'revision_version' => (int) $commit['revision_version'],
            'effective_from_utc' => (string) $commit['effective_from_utc'],
            'changed_event_ids' => array_values(array_map(
                'intval',
                is_array($commit['changed_event_ids'] ?? null)
                    ? $commit['changed_event_ids']
                    : [],
            )),
            'changed_count' => (int) $commit['changed_count'],
            'notification_recipient_count' => (int) $commit['notification_recipient_count'],
            'notification_outbox_id' => isset($commit['notification_outbox_id'])
                ? (int) $commit['notification_outbox_id']
                : null,
            'idempotent_replay' => (bool) $commit['idempotent_replay'],
            'created_at' => (string) $commit['created_at'],
        ];
    }
}
