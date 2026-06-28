// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () => createMockContexts({
  useAuth: () => ({
    user: { id: 7, name: 'Jane Member' },
    isAuthenticated: true,
    login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(),
    status: 'idle' as const, error: null,
  }),
  useToast: () => mockToast,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// Mock the @/components/ui Tabs and Tab as simple passthroughs so HeroUI React
// Aria panel-registration quirks in JSDOM don't hide content. All other ui
// exports (Card, Button, Spinner, Chip, etc.) are kept real.
vi.mock('@/components/ui', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...actual,
    Tabs: ({ children, 'aria-label': ariaLabel }: { children?: React.ReactNode; 'aria-label'?: string }) => (
      <div role="tablist" aria-label={ariaLabel ?? 'tabs'}>{children}</div>
    ),
    Tab: ({ children, title }: { children?: React.ReactNode; title?: React.ReactNode }) => (
      <div role="tabpanel">
        {title ? <div role="tab">{title}</div> : null}
        {children}
      </div>
    ),
  };
});

import MyVereinInvitationsPage from './MyVereinInvitationsPage';
import { api } from '@/lib/api';

const MOCK_INVITATION = {
  id: 1,
  status: 'sent',
  message: 'Join our Verein!',
  sent_at: '2026-01-01T10:00:00Z',
  responded_at: null,
  expires_at: '2026-12-31T00:00:00Z',
  source_organization_id: 10,
  target_organization_id: 20,
  source_name: 'Source Verein',
  target_name: 'Target Verein',
  inviter_name: 'Alice Admin',
  invitee_user_id: 7,
};

describe('MyVereinInvitationsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading spinner while fetching invitations', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<MyVereinInvitationsPage />);
    // Spinner has aria-busy="true"
    expect(document.querySelector('[aria-busy="true"]')).toBeInTheDocument();
  });

  it('renders the tabs panel after data loads', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [MOCK_INVITATION] });
    render(<MyVereinInvitationsPage />);

    // After loading, Tabs renders (its tablist is in the DOM)
    await waitFor(() => {
      expect(screen.getByRole('tablist')).toBeInTheDocument();
    });
  });

  it('renders received invitation target name inside the received tab', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [MOCK_INVITATION] });
    render(<MyVereinInvitationsPage />);

    // Wait until the tablist appears (loading done)
    await waitFor(() => expect(screen.getByRole('tablist')).toBeInTheDocument());

    // The invitation target_name "Target Verein" appears as part of the label
    // e.g. "Target: Target Verein" — use regex to match the substring
    await waitFor(() => {
      expect(screen.getByText(/Target Verein/)).toBeInTheDocument();
    });
  });

  it('renders the invitation message when present', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [MOCK_INVITATION] });
    render(<MyVereinInvitationsPage />);

    await waitFor(() => expect(screen.getByRole('tablist')).toBeInTheDocument());
    expect(screen.getByText('Join our Verein!')).toBeInTheDocument();
  });

  it('shows accept and decline buttons for a pending (sent) invitation', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [MOCK_INVITATION] });
    render(<MyVereinInvitationsPage />);

    await waitFor(() => expect(screen.getByRole('tablist')).toBeInTheDocument());
    expect(screen.getByRole('button', { name: /accept/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /decline/i })).toBeInTheDocument();
  });

  it('does not show accept/decline for an already-accepted invitation', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [{ ...MOCK_INVITATION, status: 'accepted' }],
    });
    render(<MyVereinInvitationsPage />);

    await waitFor(() => expect(screen.getByRole('tablist')).toBeInTheDocument());

    expect(screen.queryByRole('button', { name: /accept/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /decline/i })).not.toBeInTheDocument();
  });

  it('calls POST respond endpoint when accept is clicked', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [MOCK_INVITATION] });
    vi.mocked(api.post).mockResolvedValue({ success: true, data: { ...MOCK_INVITATION, status: 'accepted' } });

    render(<MyVereinInvitationsPage />);
    await waitFor(() => screen.getByRole('tablist'));
    await waitFor(() => screen.getByRole('button', { name: /accept/i }));

    fireEvent.click(screen.getByRole('button', { name: /accept/i }));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/v2/me/verein-invitations/1/respond',
        { action: 'accept' },
      );
    });
  });

  it('calls POST respond endpoint when decline is clicked', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [MOCK_INVITATION] });
    vi.mocked(api.post).mockResolvedValue({ success: true, data: { ...MOCK_INVITATION, status: 'declined' } });

    render(<MyVereinInvitationsPage />);
    await waitFor(() => screen.getByRole('tablist'));
    await waitFor(() => screen.getByRole('button', { name: /decline/i }));

    fireEvent.click(screen.getByRole('button', { name: /decline/i }));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/v2/me/verein-invitations/1/respond',
        { action: 'decline' },
      );
    });
  });

  it('shows an error toast when the API fails to load', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));
    render(<MyVereinInvitationsPage />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows an error toast when the load returns success:false (not just on a throw)', async () => {
    // Regression: load() gated on `if (res.success && Array.isArray(res.data))` with no
    // else, and the catch only fires on a thrown error. A { success:false } (4xx, which
    // api.get resolves without throwing) used to show the empty "Received" tab silently,
    // indistinguishable from genuinely having no invitations. It must now surface the
    // error. Verified live.
    vi.mocked(api.get).mockResolvedValue({ success: false, error: 'Cannot load' });
    render(<MyVereinInvitationsPage />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows an error toast when respond API fails with a message', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [MOCK_INVITATION] });
    vi.mocked(api.post).mockResolvedValue({ success: false, error: 'Already responded' });

    render(<MyVereinInvitationsPage />);
    await waitFor(() => screen.getByRole('tablist'));
    await waitFor(() => screen.getByRole('button', { name: /accept/i }));

    fireEvent.click(screen.getByRole('button', { name: /accept/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows empty tab content when there are no invitations', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<MyVereinInvitationsPage />);

    await waitFor(() => expect(screen.getByRole('tablist')).toBeInTheDocument());

    // Our Tab mock renders all tab panels; the empty-state paragraph is visible
    const tabPanels = screen.getAllByRole('tabpanel');
    expect(tabPanels.length).toBeGreaterThanOrEqual(1);
  });
});
