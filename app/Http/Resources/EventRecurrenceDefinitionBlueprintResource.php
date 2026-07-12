<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Resources;

/** Manager-only projections that never expose manifest values or staff IDs. */
final class EventRecurrenceDefinitionBlueprintResource
{
    /** @param array<string,mixed> $history @return array<string,mixed> */
    public static function history(array $history): array
    {
        $items = is_array($history['items'] ?? null) ? $history['items'] : [];

        return [
            'items' => array_values(array_map(static fn (array $item): array => [
                'blueprint_id' => (int) $item['blueprint_id'],
                'blueprint_version' => (int) $item['blueprint_version'],
                'schema_version' => (int) $item['schema_version'],
                'effective_from_recurrence_id' => (string) $item['effective_from_recurrence_id'],
                'source_event_id' => (int) $item['source_event_id'],
                'source_recurrence_id' => (string) $item['source_recurrence_id'],
                'selected_sections' => is_array($item['selected_sections'] ?? null)
                    ? $item['selected_sections']
                    : [],
                'counts' => is_array($item['counts'] ?? null) ? $item['counts'] : [],
                'manifest_hash' => (string) $item['manifest_hash'],
                'captured_by_user_id' => isset($item['captured_by_user_id'])
                    ? (int) $item['captured_by_user_id']
                    : null,
                'created_at' => (string) $item['created_at'],
            ], $items)),
            'next_before_version' => isset($history['next_before_version'])
                ? (int) $history['next_before_version']
                : null,
        ];
    }

    /** @param array<string,mixed> $preview @return array<string,mixed> */
    public static function preview(array $preview): array
    {
        return [
            'preview_token' => (string) $preview['preview_token'],
            'preview_expires_at' => (string) $preview['preview_expires_at'],
            'schema_version' => (int) $preview['schema_version'],
            'root_event_id' => (int) $preview['root_event_id'],
            'source_event_id' => (int) $preview['source_event_id'],
            'source_recurrence_id' => (string) $preview['source_recurrence_id'],
            'effective_from_recurrence_id' => (string) $preview['effective_from_recurrence_id'],
            'selected_sections' => is_array($preview['selected_sections'] ?? null)
                ? $preview['selected_sections']
                : [],
            'manifest_hash' => (string) $preview['manifest_hash'],
            'blueprint_set_version' => (int) $preview['blueprint_set_version'],
            'counts' => is_array($preview['counts'] ?? null) ? $preview['counts'] : [],
            'conflicts' => is_array($preview['conflicts'] ?? null) ? $preview['conflicts'] : [],
            'can_commit' => (bool) $preview['can_commit'],
        ];
    }

    /** @param array<string,mixed> $commit @return array<string,mixed> */
    public static function commit(array $commit): array
    {
        return [
            'blueprint_id' => (int) $commit['blueprint_id'],
            'blueprint_version' => (int) $commit['blueprint_version'],
            'schema_version' => (int) $commit['schema_version'],
            'root_event_id' => (int) $commit['root_event_id'],
            'source_event_id' => (int) $commit['source_event_id'],
            'source_recurrence_id' => (string) $commit['source_recurrence_id'],
            'effective_from_recurrence_id' => (string) $commit['effective_from_recurrence_id'],
            'selected_sections' => is_array($commit['selected_sections'] ?? null)
                ? $commit['selected_sections']
                : [],
            'manifest_hash' => (string) $commit['manifest_hash'],
            'counts' => is_array($commit['counts'] ?? null) ? $commit['counts'] : [],
            'idempotent_replay' => (bool) $commit['idempotent_replay'],
            'created_at' => (string) $commit['created_at'],
        ];
    }
}
