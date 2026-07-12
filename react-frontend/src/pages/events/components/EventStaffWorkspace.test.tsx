// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { eventsApi, type EventStaffAssignment } from '@/lib/events-api';
import { renderEventComponent } from '@/test/events-test-harness';
import { EventStaffWorkspace } from './EventStaffWorkspace';

const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));

vi.mock('@/contexts/ToastContext', () => ({ useToast: () => mockToast }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

function staffAssignment(overrides: Partial<EventStaffAssignment> = {}): EventStaffAssignment {
  return {
    id: 501,
    event_id: 1,
    member: {
      id: 44,
      name: 'Sam Rivera',
      first_name: 'Sam',
      last_name: 'Rivera',
      avatar_url: null,
    },
    role: 'check_in_staff',
    capabilities: ['view', 'viewRoster', 'manageAttendance'],
    status: 'active',
    effective: true,
    version: 1,
    granted_at: '2030-04-01T08:00:00+00:00',
    granted_by_user_id: 7,
    revoked_at: null,
    revoked_by_user_id: null,
    expires_at: null,
    history_metadata: {
      immutable: true,
      entry_count: 1,
      latest_entry_id: 900,
      latest_version: 1,
    },
    history: [{
      id: 900,
      version: 1,
      action: 'granted',
      from_status: null,
      to_status: 'active',
      previous_expires_at: null,
      new_expires_at: null,
      actor_user_id: 7,
      idempotency_key: 'grant-501',
      metadata: { schema_version: 1 },
      created_at: '2030-04-01T08:00:00+00:00',
      immutable: true,
    }],
    created_at: '2030-04-01T08:00:00+00:00',
    updated_at: '2030-04-01T08:00:00+00:00',
    ...overrides,
  };
}

function renderWorkspace(overrides: Partial<ComponentProps<typeof EventStaffWorkspace>> = {}) {
  const props: ComponentProps<typeof EventStaffWorkspace> = {
    eventId: 1,
    organizerId: 5,
    canGrantPrivilegedRoles: true,
    assignments: [],
    isLoading: false,
    error: null,
    onRetry: vi.fn(),
    onChanged: vi.fn().mockResolvedValue(undefined),
    ...overrides,
  };

  return { ...renderEventComponent(<EventStaffWorkspace {...props} />), props };
}

describe('EventStaffWorkspace', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('searches the maintained member directory and assigns an exact non-owner role', async () => {
    const user = userEvent.setup();
    vi.spyOn(eventsApi, 'searchMembers').mockResolvedValue({
      success: true,
      data: [{ id: 44, name: 'Sam Rivera', first_name: 'Sam', last_name: 'Rivera', avatar: null }],
    });
    const assignSpy = vi.spyOn(eventsApi, 'assignStaff').mockResolvedValue({
      success: true,
      data: {
        assignment: staffAssignment(),
        changed: true,
        idempotent_replay: false,
        history_entry_id: 900,
      },
    });
    const onChanged = vi.fn().mockResolvedValue(undefined);
    renderWorkspace({ onChanged });

    await user.type(screen.getByRole('searchbox', { name: 'Member' }), 'Sam');
    await user.click(await screen.findByRole('button', { name: 'Select Sam Rivera' }, { timeout: 2500 }));
    await user.click(screen.getByRole('button', { name: 'Assign role' }));

    await waitFor(() => {
      expect(assignSpy).toHaveBeenCalledWith(
        1,
        { user_id: 44, role: 'co_organizer', expires_at: null },
        expect.any(String),
      );
    });
    expect(onChanged).toHaveBeenCalledTimes(1);
    expect(mockToast.success).toHaveBeenCalledWith('Sam Rivera was added to the event team.');
  });

  it('does not offer privileged co-organiser or finance roles to a delegated staff manager', async () => {
    const user = userEvent.setup();
    renderWorkspace({
      canGrantPrivilegedRoles: false,
      assignments: [staffAssignment({ role: 'finance_manager', capabilities: ['view', 'manageFinance'] })],
    });

    expect(screen.queryByRole('button', { name: 'Revoke access' })).not.toBeInTheDocument();

    const roleLabel = screen.getByText('Event role');
    const selectRoot = roleLabel.closest('[data-slot="select"]');
    expect(selectRoot).not.toBeNull();
    await user.click(within(selectRoot as HTMLElement).getByRole('button'));

    await screen.findByRole('option', { name: 'Registration manager' });
    expect(screen.getByRole('option', { name: 'Communications manager' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Check-in staff' })).toBeInTheDocument();
    expect(screen.queryByRole('option', { name: 'Co-organiser' })).not.toBeInTheDocument();
    expect(screen.queryByRole('option', { name: 'Finance manager' })).not.toBeInTheDocument();
  });

  it('shows effective state, exact capabilities, audit metadata, and revokes with confirmation', async () => {
    const user = userEvent.setup();
    const assignment = staffAssignment();
    const revokeSpy = vi.spyOn(eventsApi, 'revokeStaff').mockResolvedValue({
      success: true,
      data: {
        assignment: staffAssignment({ status: 'revoked', effective: false, version: 2 }),
        changed: true,
        idempotent_replay: false,
        history_entry_id: 901,
      },
    });
    const onChanged = vi.fn().mockResolvedValue(undefined);
    renderWorkspace({ assignments: [assignment], onChanged });

    expect(screen.getByText('Active')).toBeInTheDocument();
    expect(screen.getByText('Manage attendance')).toBeInTheDocument();
    expect(screen.getByText('Version')).toBeInTheDocument();
    expect(screen.getByText('Audit trail (1)')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Revoke access' }));
    const dialog = await screen.findByRole('alertdialog');
    expect(within(dialog).getByText(/audit history will be retained/i)).toBeInTheDocument();
    await user.click(within(dialog).getByRole('button', { name: 'Revoke role' }));

    await waitFor(() => {
      expect(revokeSpy).toHaveBeenCalledWith(1, 501, expect.any(String));
    });
    expect(onChanged).toHaveBeenCalledTimes(1);
    expect(mockToast.success).toHaveBeenCalledWith('Access for Sam Rivera was revoked.');
  });
});
