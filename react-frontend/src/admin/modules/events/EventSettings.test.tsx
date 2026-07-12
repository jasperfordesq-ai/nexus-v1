// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('../../api/adminApi', () => ({
  adminConfig: {
    getEventConfig: vi.fn().mockResolvedValue({
      success: true,
      data: {
        config: {
          creation_role: 'members', moderation_required: false, registration_enabled: true,
          default_capacity: 0, guest_registration_enabled: true, waitlist_enabled: true,
          timed_waitlist_offers_enabled: false, recurrence_enabled: true, reminders_enabled: true,
          organizer_broadcasts_enabled: true, offline_checkin_enabled: true,
          calendar_feeds_enabled: true, federation_sharing_enabled: true,
          safety_enforcement_mode: null, notification_delivery_mode: null,
        },
        defaults: {}, version: 0,
        capabilities: {
          recurrence_v2: false, rolling_recurrence: false,
          recurrence_definition_blueprints: false, timed_waitlist_offers: false,
          attendance_credits: false, optional_analytics_capture: false,
          registration_forms: true, invitation_campaigns: true, ticketing: true,
          agenda: true, offline_sync: true, broadcast_delivery: true,
          safety_evidence: true, federation_delivery: true, notification_consumer: true,
          notification_delivery: { resolved_mode: 'outbox_authoritative', source: 'global' },
          safety: { resolved_mode: 'off', source: 'global', configuration_valid: true },
        },
        impact: {
          active_registrations: 0, active_waitlist_entries: 0, pending_reminders: 0,
          active_calendar_tokens: 0, shared_events: 0, scheduled_broadcasts: 0,
        },
      },
    }),
    getEventConfigAuditLog: vi.fn().mockResolvedValue({ success: true, data: [] }),
    updateEventConfig: vi.fn(),
    restoreEventConfigDefaults: vi.fn(),
  },
}));

const mockToast = { success: vi.fn(), error: vi.fn() };
vi.mock('@/contexts', () => createMockContexts({
  useToast: () => mockToast,
}));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/ui/ConfirmDialog', () => ({ useConfirm: () => vi.fn() }));

import EventSettings from './EventSettings';

describe('EventSettings', () => {
  it('renders the enterprise configuration surface without invalid compound components', async () => {
    render(<EventSettings />);

    await waitFor(() => expect(screen.getByRole('heading', { name: 'Event settings' })).toBeInTheDocument());
    expect(screen.getByRole('heading', { name: 'Platform readiness' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Current operational footprint' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Configuration history' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Save Event settings' })).toBeDisabled();
  });
});
