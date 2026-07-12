// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type {
  CanonicalEvent,
  EventRecurrenceCapabilities,
  EventRecurrenceDefinitionSections,
} from '@/lib/api/events';

export function recurrenceDefinitionPermissions(event: CanonicalEvent): EventRecurrenceDefinitionSections {
  return {
    agenda: event.permissions.manage_agenda,
    ticket_types: event.permissions.manage_finance,
    registration: event.permissions.manage_registration,
    safety: event.permissions.edit,
    staff: event.permissions.manage_staff,
  };
}

export function canUseRecurrenceDefinitionBlueprints(
  event: CanonicalEvent,
  capabilities: EventRecurrenceCapabilities,
): boolean {
  return isRecurrenceDefinitionBlueprintCandidate(event)
    && capabilities.engine === 'v2'
    && capabilities.schema_ready
    && capabilities.supports_definition_blueprints
    && capabilities.rollout_state === 'v2_rolling';
}

export function isRecurrenceDefinitionBlueprintCandidate(event: CanonicalEvent): boolean {
  const recurrence = event.series.recurrence;
  if (!recurrence
    || recurrence.is_template
    || recurrence.parent_event_id === null
    || recurrence.recurrence_id === null
    || !/^\d{8}T\d{6}Z$/.test(recurrence.recurrence_id)
    || recurrence.engine !== 'sabre-vobject'
    || recurrence.engine_version !== '2') {
    return false;
  }
  return Object.values(recurrenceDefinitionPermissions(event)).some(Boolean);
}
