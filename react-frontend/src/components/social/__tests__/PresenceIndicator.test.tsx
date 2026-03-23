// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for PresenceIndicator component.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { HeroUIProvider } from '@heroui/react';

// ─── Stable mock references (prevent infinite re-render loops) ──────────────

const mockGetPresence = vi.fn();
const mockSetStatus = vi.fn();
const mockSetPrivacy = vi.fn();
const mockFetchPresence = vi.fn();

const mockPresenceContext = {
  onlineUsers: new Map(),
  onlineCount: 3,
  setStatus: mockSetStatus,
  setPrivacy: mockSetPrivacy,
  fetchPresence: mockFetchPresence,
  getPresence: mockGetPresence,
};

// ─── Mocks ──────────────────────────────────────────────────────────────────

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallbackOrOpts?: string | Record<string, unknown>, opts?: Record<string, unknown>) => {
      // If second arg is a string, it's the fallback
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

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, className, ...rest }: Record<string, unknown>) => {
      // Filter out framer-motion props and render a plain div
      const { animate, transition, ...htmlProps } = rest;
      return <div className={className as string} {...htmlProps}>{children as React.ReactNode}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// ─── Imports ────────────────────────────────────────────────────────────────

import { PresenceIndicator } from '../PresenceIndicator';
import { usePresenceOptional } from '@/contexts/PresenceContext';

// ─── Wrapper ────────────────────────────────────────────────────────────────

function W({ children }: { children: React.ReactNode }) {
  return <HeroUIProvider>{children}</HeroUIProvider>;
}

// ─── Tests ──────────────────────────────────────────────────────────────────

describe('PresenceIndicator', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Restore the default mock implementations after each test
    vi.mocked(usePresenceOptional).mockReturnValue(mockPresenceContext);
    // Default: user is online
    mockGetPresence.mockReturnValue({
      status: 'online',
      last_seen_at: null,
      custom_status: null,
      status_emoji: null,
    });
  });

  it('renders without crashing for an online user', () => {
    render(
      <W>
        <PresenceIndicator userId={1} />
      </W>,
    );

    expect(screen.getByRole('status')).toBeInTheDocument();
  });

  it('returns null when presence context is unavailable', () => {
    vi.mocked(usePresenceOptional).mockReturnValue(null);

    render(
      <W>
        <PresenceIndicator userId={1} />
      </W>,
    );

    expect(screen.queryByRole('status')).not.toBeInTheDocument();
  });

  it('returns null when user is offline', () => {
    mockGetPresence.mockReturnValue({
      status: 'offline',
      last_seen_at: null,
      custom_status: null,
      status_emoji: null,
    });

    render(
      <W>
        <PresenceIndicator userId={1} />
      </W>,
    );

    expect(screen.queryByRole('status')).not.toBeInTheDocument();
  });

  it('renders a green dot for online status', () => {
    render(
      <W>
        <PresenceIndicator userId={1} />
      </W>,
    );

    const statusEl = screen.getByRole('status');
    // The inner div should have the green class
    const dot = statusEl.querySelector('div');
    expect(dot?.className).toContain('bg-green-500');
  });

  it('renders a yellow dot for away status', () => {
    mockGetPresence.mockReturnValue({
      status: 'away',
      last_seen_at: null,
      custom_status: null,
      status_emoji: null,
    });

    render(
      <W>
        <PresenceIndicator userId={1} />
      </W>,
    );

    const statusEl = screen.getByRole('status');
    const dot = statusEl.querySelector('div');
    expect(dot?.className).toContain('bg-yellow-500');
  });

  it('renders a red dot for dnd status', () => {
    mockGetPresence.mockReturnValue({
      status: 'dnd',
      last_seen_at: null,
      custom_status: null,
      status_emoji: null,
    });

    render(
      <W>
        <PresenceIndicator userId={1} />
      </W>,
    );

    const statusEl = screen.getByRole('status');
    const dot = statusEl.querySelector('div');
    expect(dot?.className).toContain('bg-red-500');
  });

  it('sets aria-label to the translated status label', () => {
    render(
      <W>
        <PresenceIndicator userId={1} />
      </W>,
    );

    const statusEl = screen.getByRole('status');
    expect(statusEl).toHaveAttribute('aria-label', 'presence.online');
  });

  it('applies custom className for positioning', () => {
    render(
      <W>
        <PresenceIndicator userId={1} className="top-0 left-0" />
      </W>,
    );

    const statusEl = screen.getByRole('status');
    expect(statusEl.className).toContain('top-0 left-0');
  });

  it('applies sm size classes', () => {
    render(
      <W>
        <PresenceIndicator userId={1} size="sm" />
      </W>,
    );

    const statusEl = screen.getByRole('status');
    const dot = statusEl.querySelector('div');
    expect(dot?.className).toContain('w-2');
    expect(dot?.className).toContain('h-2');
  });

  it('applies lg size classes', () => {
    render(
      <W>
        <PresenceIndicator userId={1} size="lg" />
      </W>,
    );

    const statusEl = screen.getByRole('status');
    const dot = statusEl.querySelector('div');
    expect(dot?.className).toContain('w-3');
    expect(dot?.className).toContain('h-3');
  });

  it('shows custom status in tooltip content', () => {
    mockGetPresence.mockReturnValue({
      status: 'online',
      last_seen_at: null,
      custom_status: 'In a meeting',
      status_emoji: null,
    });

    render(
      <W>
        <PresenceIndicator userId={1} />
      </W>,
    );

    // The component renders, tooltip content is set but only visible on hover.
    // We verify it renders without error.
    expect(screen.getByRole('status')).toBeInTheDocument();
  });

  it('shows emoji + custom status in tooltip', () => {
    mockGetPresence.mockReturnValue({
      status: 'online',
      last_seen_at: null,
      custom_status: 'Busy coding',
      status_emoji: '\uD83D\uDCBB',
    });

    render(
      <W>
        <PresenceIndicator userId={1} />
      </W>,
    );

    expect(screen.getByRole('status')).toBeInTheDocument();
  });

  it('calls getPresence with the correct userId', () => {
    render(
      <W>
        <PresenceIndicator userId={42} />
      </W>,
    );

    expect(mockGetPresence).toHaveBeenCalledWith(42);
  });

  it('defaults size to md', () => {
    render(
      <W>
        <PresenceIndicator userId={1} />
      </W>,
    );

    const statusEl = screen.getByRole('status');
    const dot = statusEl.querySelector('div');
    expect(dot?.className).toContain('w-2.5');
    expect(dot?.className).toContain('h-2.5');
  });
});
