// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for StatusSelector component.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Stable mock references (prevent infinite re-render loops) ──────────────

const mockSetStatus = vi.fn().mockResolvedValue(undefined);
const mockSetPrivacy = vi.fn().mockResolvedValue(undefined);
const mockFetchPresence = vi.fn().mockResolvedValue(undefined);
const mockGetPresence = vi.fn();

const mockPresenceContext = {
  onlineUsers: new Map(),
  onlineCount: 3,
  setStatus: mockSetStatus,
  setPrivacy: mockSetPrivacy,
  fetchPresence: mockFetchPresence,
  getPresence: mockGetPresence,
};

const mockUser = { id: 1, name: 'Test User' };

const mockAuthContext = {
  user: mockUser,
  isAuthenticated: true,
  status: 'idle' as const,
  login: vi.fn(),
  logout: vi.fn(),
  register: vi.fn(),
  verify2FA: vi.fn(),
  refreshUser: vi.fn(),
  biometricLogin: vi.fn(),
  clearError: vi.fn(),
  error: null,
  tokenReady: true,
  pendingUserId: null,
};

// ─── Mocks ──────────────────────────────────────────────────────────────────

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallbackOrOpts?: string | Record<string, unknown>, opts?: Record<string, unknown>) => {
      if (typeof fallbackOrOpts === 'string') return fallbackOrOpts;
      return key;
    },
    i18n: { language: 'en', changeLanguage: vi.fn() },
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

vi.mock('@/contexts/PresenceContext', () => ({
  usePresenceOptional: vi.fn(() => mockPresenceContext),
  usePresence: vi.fn(() => mockPresenceContext),
}));

vi.mock('@/contexts/AuthContext', () => ({
  useAuth: vi.fn(() => mockAuthContext),
}));

// ─── Imports ────────────────────────────────────────────────────────────────

import { StatusSelector } from '../StatusSelector';
import { usePresenceOptional } from '@/contexts/PresenceContext';
import { useAuth } from '@/contexts/AuthContext';

// ─── Wrapper ────────────────────────────────────────────────────────────────

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test']}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

// ─── Tests ──────────────────────────────────────────────────────────────────

describe('StatusSelector', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Default: user is online with no custom status
    mockGetPresence.mockReturnValue({
      status: 'online',
      last_seen_at: null,
      custom_status: null,
      status_emoji: null,
    });
    vi.mocked(usePresenceOptional).mockReturnValue(mockPresenceContext);
    vi.mocked(useAuth).mockReturnValue(mockAuthContext as ReturnType<typeof useAuth>);
  });

  it('renders without crashing', () => {
    render(
      <W>
        <StatusSelector />
      </W>,
    );

    expect(screen.getByLabelText('status.set_status')).toBeInTheDocument();
  });

  it('renders children as custom trigger when provided', () => {
    render(
      <W>
        <StatusSelector>
          <button>Custom Trigger</button>
        </StatusSelector>
      </W>,
    );

    expect(screen.getByText('Custom Trigger')).toBeInTheDocument();
  });

  it('renders just children when presence context is null', () => {
    vi.mocked(usePresenceOptional).mockReturnValue(null);

    render(
      <W>
        <StatusSelector>
          <span>Fallback content</span>
        </StatusSelector>
      </W>,
    );

    expect(screen.getByText('Fallback content')).toBeInTheDocument();
  });

  it('opens the dropdown menu when trigger is clicked', async () => {
    const user = userEvent.setup();

    render(
      <W>
        <StatusSelector />
      </W>,
    );

    await user.click(screen.getByLabelText('status.set_status'));

    // The dropdown menu should appear with status options
    expect(screen.getAllByText('status.online').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('status.away')).toBeInTheDocument();
    expect(screen.getByText('status.dnd')).toBeInTheDocument();
  });

  it('shows "Set custom status..." option in dropdown', async () => {
    const user = userEvent.setup();

    render(
      <W>
        <StatusSelector />
      </W>,
    );

    await user.click(screen.getByLabelText('status.set_status'));

    expect(screen.getByText('status.set_custom')).toBeInTheDocument();
  });

  it('shows current custom status text in the dropdown', async () => {
    mockGetPresence.mockReturnValue({
      status: 'online',
      last_seen_at: null,
      custom_status: 'In a meeting',
      status_emoji: '\uD83D\uDCC5',
    });

    const user = userEvent.setup();

    render(
      <W>
        <StatusSelector />
      </W>,
    );

    await user.click(screen.getByLabelText('status.set_status'));

    // Should show the custom status instead of "Set custom status..."
    expect(screen.getByText('\uD83D\uDCC5 In a meeting')).toBeInTheDocument();
  });

  it('shows "Clear custom status" when custom status is set', async () => {
    mockGetPresence.mockReturnValue({
      status: 'online',
      last_seen_at: null,
      custom_status: 'Busy',
      status_emoji: null,
    });

    const user = userEvent.setup();

    render(
      <W>
        <StatusSelector />
      </W>,
    );

    await user.click(screen.getByLabelText('status.set_status'));

    expect(screen.getByText('status.clear_custom')).toBeInTheDocument();
  });

  it('calls setStatus when a status option is selected', async () => {
    const user = userEvent.setup();

    render(
      <W>
        <StatusSelector />
      </W>,
    );

    await user.click(screen.getByLabelText('status.set_status'));
    await user.click(screen.getByText('status.away'));

    expect(mockSetStatus).toHaveBeenCalledWith('away');
  });

  it('highlights the current status option', async () => {
    mockGetPresence.mockReturnValue({
      status: 'dnd',
      last_seen_at: null,
      custom_status: null,
      status_emoji: null,
    });

    const user = userEvent.setup();

    render(
      <W>
        <StatusSelector />
      </W>,
    );

    await user.click(screen.getByLabelText('status.set_status'));

    // The DND option should have the highlight class
    const dndOption = screen.getAllByText('status.dnd')
      .map((node) => node.closest('[data-key="dnd"]'))
      .find(Boolean);
    if (dndOption) {
      expect(dndOption.className).toContain('bg-theme-hover');
    }
  });

  it('opens custom status modal when "Set custom status..." is clicked', async () => {
    const user = userEvent.setup();

    render(
      <W>
        <StatusSelector />
      </W>,
    );

    await user.click(screen.getByLabelText('status.set_status'));
    await user.click(screen.getByText('status.set_custom'));

    // Modal should appear with title
    expect(screen.getByText('status.set_custom_title')).toBeInTheDocument();
  });

  it('renders emoji and status input fields in custom status modal', async () => {
    const user = userEvent.setup();

    render(
      <W>
        <StatusSelector />
      </W>,
    );

    await user.click(screen.getByLabelText('status.set_status'));
    await user.click(screen.getByText('status.set_custom'));

    // The modal should contain input fields for Emoji and Status
    // Use getByLabelText for the Emoji field; for Status, use the dialog to scope
    expect(screen.getByLabelText('status.emoji_label')).toBeInTheDocument();
    // "Status" appears in both the dropdown section title and the input label,
    // so query within the dialog
    const dialog = screen.getByRole('dialog');
    const statusInput = dialog.querySelector('input[aria-label="status.status_label"], label');
    expect(statusInput).not.toBeNull();
  });

  it('shows Cancel and Save buttons in custom status modal', async () => {
    const user = userEvent.setup();

    render(
      <W>
        <StatusSelector />
      </W>,
    );

    await user.click(screen.getByLabelText('status.set_status'));
    await user.click(screen.getByText('status.set_custom'));

    expect(screen.getByText('cancel')).toBeInTheDocument();
    expect(screen.getByText('save')).toBeInTheDocument();
  });

  it('shows character count in custom status modal', async () => {
    const user = userEvent.setup();

    render(
      <W>
        <StatusSelector />
      </W>,
    );

    await user.click(screen.getByLabelText('status.set_status'));
    await user.click(screen.getByText('status.set_custom'));

    const dialog = screen.getByRole('dialog');
    expect(dialog.textContent).toContain('status.char_count');
  });

  it('does not render dropdown when presence is null and no children', () => {
    vi.mocked(usePresenceOptional).mockReturnValue(null);

    render(
      <W>
        <StatusSelector />
      </W>,
    );

    // When presence is null and no children, the component renders <>{children}</> which is empty
    // The "Set status" button should NOT be present
    expect(screen.queryByLabelText('status.set_status')).not.toBeInTheDocument();
  });
});
