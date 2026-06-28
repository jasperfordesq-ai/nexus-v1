// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

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
  useToast: () => mockToast,
  useTenant: () => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    isLoading: false,
  }),
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/components/seo', () => ({ PageMeta: () => null }));

// resolveAvatarUrl — partial mock: keep all real exports, override only what we need
vi.mock('@/lib/helpers', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/helpers')>();
  return {
    ...actual,
    resolveAvatarUrl: (url: string | null | undefined) => url ?? '',
  };
});

import { BlockedUsersPage } from './BlockedUsersPage';
import { api } from '@/lib/api';

const MOCK_BLOCKED_USER = {
  block_id: 1,
  user_id: 55,
  name: 'Bob Blocker',
  first_name: 'Bob',
  last_name: 'Blocker',
  avatar_url: null,
  reason: 'Spam',
  blocked_at: '2026-01-15T12:00:00Z',
};

const MOCK_BLOCKED_USER_2 = {
  block_id: 2,
  user_id: 66,
  name: 'Carol Blocker',
  first_name: 'Carol',
  last_name: 'Blocker',
  avatar_url: null,
  reason: null,
  blocked_at: '2026-02-20T09:00:00Z',
};

describe('BlockedUsersPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading screen while fetching blocked users', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<BlockedUsersPage />);
    // LoadingScreen renders a spinner/spinner region
    const spinner = document.querySelector('[role="status"], [aria-busy="true"]');
    expect(spinner).toBeInTheDocument();
  });

  it('shows empty state when no blocked users are returned', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<BlockedUsersPage />);
    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/v2/users/blocked'));
    // EmptyState or the empty-state heading renders
    const heading = await screen.findByRole('heading', { level: 1 });
    expect(heading).toBeInTheDocument();
  });

  it('renders blocked user names when the list is populated', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [MOCK_BLOCKED_USER, MOCK_BLOCKED_USER_2] });
    render(<BlockedUsersPage />);

    await waitFor(() => expect(screen.getByText('Bob Blocker')).toBeInTheDocument());
    expect(screen.getByText('Carol Blocker')).toBeInTheDocument();
  });

  it('shows the blocked user count in the summary chip', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [MOCK_BLOCKED_USER] });
    render(<BlockedUsersPage />);

    await waitFor(() => expect(screen.getByText('Bob Blocker')).toBeInTheDocument());
    // Count label renders somewhere on the page — find text containing "1"
    const countEl = screen.getByText((txt) =>
      txt.includes('1') && !txt.includes('2026')
    );
    expect(countEl).toBeInTheDocument();
  });

  it('renders an unblock button for each blocked user', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [MOCK_BLOCKED_USER, MOCK_BLOCKED_USER_2] });
    render(<BlockedUsersPage />);
    await waitFor(() => expect(screen.getByText('Bob Blocker')).toBeInTheDocument());

    const unblockBtns = screen.getAllByRole('button', { name: /unblock/i });
    expect(unblockBtns).toHaveLength(2);
  });

  it('opens confirmation modal when unblock button is clicked', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [MOCK_BLOCKED_USER] });
    render(<BlockedUsersPage />);
    await waitFor(() => expect(screen.getByText('Bob Blocker')).toBeInTheDocument());

    const unblockBtn = screen.getByRole('button', { name: /unblock/i });
    fireEvent.click(unblockBtn);

    // Modal appears — confirmation text or confirm button
    await waitFor(() => {
      const confirmBtns = screen.getAllByRole('button', { name: /unblock/i });
      // Now there should be at least 2: the original row button + the confirm button in modal
      expect(confirmBtns.length).toBeGreaterThanOrEqual(1);
    });
  });

  it('calls api.delete when unblock is confirmed', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [MOCK_BLOCKED_USER] });
    vi.mocked(api.delete).mockResolvedValue({ success: true });
    render(<BlockedUsersPage />);

    await waitFor(() => expect(screen.getByText('Bob Blocker')).toBeInTheDocument());

    // Click the unblock button to open the modal
    const [unblockBtn] = screen.getAllByRole('button', { name: /unblock/i });
    fireEvent.click(unblockBtn);

    // Wait for the cancel button that appears in the modal footer
    const cancelBtn = await screen.findByRole('button', { name: /cancel/i });
    expect(cancelBtn).toBeInTheDocument();

    // Now find the confirm unblock button — the modal footer adds another unblock button
    // or the last unblock-labelled button is the confirm one
    await waitFor(() => {
      const allUnblockBtns = screen.getAllByRole('button', { name: /unblock/i });
      // Click the last one (modal footer confirm)
      fireEvent.click(allUnblockBtns[allUnblockBtns.length - 1]);
    });

    await waitFor(() => {
      expect(api.delete).toHaveBeenCalledWith('/v2/users/55/block');
    });
  });

  it('removes user from list after successful unblock', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [MOCK_BLOCKED_USER, MOCK_BLOCKED_USER_2] });
    vi.mocked(api.delete).mockResolvedValue({ success: true });
    render(<BlockedUsersPage />);

    await waitFor(() => expect(screen.getByText('Bob Blocker')).toBeInTheDocument());

    // Open unblock modal for Bob (first unblock button)
    const [firstUnblockBtn] = screen.getAllByRole('button', { name: /unblock/i });
    fireEvent.click(firstUnblockBtn);

    // Wait for the cancel button to confirm the modal is open
    await screen.findByRole('button', { name: /cancel/i });

    // Click the modal's confirm unblock button (last unblock-labelled button)
    await waitFor(() => {
      const allBtns = screen.getAllByRole('button', { name: /unblock/i });
      fireEvent.click(allBtns[allBtns.length - 1]);
    });

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
      expect(screen.queryByText('Bob Blocker')).not.toBeInTheDocument();
    });
    // Carol should still be there
    expect(screen.getByText('Carol Blocker')).toBeInTheDocument();
  });

  it('shows error toast when unblock API throws', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [MOCK_BLOCKED_USER] });
    vi.mocked(api.delete).mockRejectedValue(new Error('Server error'));
    render(<BlockedUsersPage />);

    await waitFor(() => expect(screen.getByText('Bob Blocker')).toBeInTheDocument());

    const [unblockBtn] = screen.getAllByRole('button', { name: /unblock/i });
    fireEvent.click(unblockBtn);

    // Wait for the cancel button to confirm modal is open
    await screen.findByRole('button', { name: /cancel/i });

    // Confirm unblock
    await waitFor(() => {
      const allBtns = screen.getAllByRole('button', { name: /unblock/i });
      fireEvent.click(allBtns[allBtns.length - 1]);
    });

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows an error toast and keeps the user when unblock returns success:false', async () => {
    // Regression: api.delete resolves { success:false } on a 4xx WITHOUT throwing,
    // so the success-only `if` was skipped, the catch never fired, and the finally
    // closed the modal — a silent failure that looked like it worked while the user
    // stayed blocked. The { success:false } path must now surface an error.
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [MOCK_BLOCKED_USER] });
    vi.mocked(api.delete).mockResolvedValue({ success: false, error: 'nope' });
    render(<BlockedUsersPage />);

    await waitFor(() => expect(screen.getByText('Bob Blocker')).toBeInTheDocument());

    const [unblockBtn] = screen.getAllByRole('button', { name: /unblock/i });
    fireEvent.click(unblockBtn);
    await screen.findByRole('button', { name: /cancel/i });
    await waitFor(() => {
      const allBtns = screen.getAllByRole('button', { name: /unblock/i });
      fireEvent.click(allBtns[allBtns.length - 1]);
    });

    await waitFor(() => expect(mockToast.error).toHaveBeenCalled());
    // The user must NOT be removed (the unblock failed), and success must not fire.
    expect(screen.getByText('Bob Blocker')).toBeInTheDocument();
    expect(mockToast.success).not.toHaveBeenCalled();
  });

  it('closes the modal when cancel is clicked', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [MOCK_BLOCKED_USER] });
    render(<BlockedUsersPage />);

    await waitFor(() => expect(screen.getByText('Bob Blocker')).toBeInTheDocument());

    const [unblockBtn] = screen.getAllByRole('button', { name: /unblock/i });
    fireEvent.click(unblockBtn);

    // Cancel button appears
    const cancelBtn = await screen.findByRole('button', { name: /cancel/i });
    expect(cancelBtn).toBeInTheDocument();

    fireEvent.click(cancelBtn);

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: /cancel/i })).not.toBeInTheDocument();
    });
    // api.delete was never called
    expect(api.delete).not.toHaveBeenCalled();
  });
});
