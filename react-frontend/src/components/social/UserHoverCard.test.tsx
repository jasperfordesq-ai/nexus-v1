// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── stable mock data ──────────────────────────────────────────────────────────

const MOCK_USER_DATA = vi.hoisted(() => ({
  id: 99,
  name: 'Alice Smith',
  tagline: 'Community builder',
  bio: 'Loves timebanking.',
  is_verified: false,
  skills: ['Gardening', 'Cooking'],
  interests: ['Events'],
  connection_status: 'none' as const,
  stats: { total_hours_given: 10, connections_count: 5, listings_count: 2 },
}));

// ── mock @/lib/api ────────────────────────────────────────────────────────────

const mockApiObj = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  default: mockApiObj,
  api: mockApiObj,
}));

// ── mock @/lib/logger ─────────────────────────────────────────────────────────

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ── mock @/lib/helpers ────────────────────────────────────────────────────────

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: (url: string | null | undefined) => url ?? '',
}));

// ── mock PresenceIndicator (child, irrelevant to these tests) ─────────────────

vi.mock('./PresenceIndicator', () => ({
  PresenceIndicator: () => null,
}));

// ── mock @/contexts ───────────────────────────────────────────────────────────

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

// ── component import (after mocks) ────────────────────────────────────────────

import { UserHoverCard } from './UserHoverCard';

// ── helper ────────────────────────────────────────────────────────────────────

/**
 * UserHoverCard is touch-device-aware. In jsdom, window.matchMedia is
 * not implemented, so isTouchDevice() may return true and bypass the
 * popover entirely. We force a non-touch environment by stubbing matchMedia.
 */
function stubNonTouchDevice() {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: (query: string) => ({
      matches: query === '(hover: hover)', // returns true for hover media
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    }),
  });
  // ontouchstart must be absent for non-touch
  if ('ontouchstart' in window) {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    delete (window as any).ontouchstart;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Tests
// ─────────────────────────────────────────────────────────────────────────────

describe('UserHoverCard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    stubNonTouchDevice();
    mockApiObj.get.mockResolvedValue({ success: true, data: MOCK_USER_DATA });
    // Flush the module-level cache so each test starts fresh
    // (The component uses a module-level Map for caching)
  });

  it('renders children (trigger) without fetching on mount', () => {
    render(
      <UserHoverCard userId={99}>
        <span data-testid="trigger">Alice</span>
      </UserHoverCard>,
    );
    expect(screen.getByTestId('trigger')).toBeInTheDocument();
    expect(mockApiObj.get).not.toHaveBeenCalled();
  });

  it('fetches user data after hover (mouseenter on trigger span)', async () => {
    render(
      <UserHoverCard userId={99}>
        <span data-testid="trigger">Alice</span>
      </UserHoverCard>,
    );

    const trigger = screen.getByTestId('trigger').parentElement!; // the <span> wrapper
    await userEvent.hover(trigger);

    await waitFor(() => {
      expect(mockApiObj.get).toHaveBeenCalledWith('/v2/users/99');
    });
  });

  it('shows user name in popover after successful fetch', async () => {
    render(
      <UserHoverCard userId={99}>
        <span data-testid="trigger">Alice</span>
      </UserHoverCard>,
    );

    const trigger = screen.getByTestId('trigger').parentElement!;
    await userEvent.hover(trigger);

    await waitFor(() => {
      // Name should appear inside the popover content
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    });
  });

  it('shows tagline in popover', async () => {
    render(
      <UserHoverCard userId={99}>
        <span data-testid="trigger">Alice</span>
      </UserHoverCard>,
    );

    const trigger = screen.getByTestId('trigger').parentElement!;
    await userEvent.hover(trigger);

    await waitFor(() => {
      expect(screen.getByText('Community builder')).toBeInTheDocument();
    });
  });

  it('shows skills chips in popover', async () => {
    render(
      <UserHoverCard userId={99}>
        <span data-testid="trigger">Alice</span>
      </UserHoverCard>,
    );

    const trigger = screen.getByTestId('trigger').parentElement!;
    await userEvent.hover(trigger);

    await waitFor(() => {
      expect(screen.getByText('Gardening')).toBeInTheDocument();
      expect(screen.getByText('Cooking')).toBeInTheDocument();
    });
  });

  it('shows Connect button when connection_status is none', async () => {
    render(
      <UserHoverCard userId={99}>
        <span data-testid="trigger">Alice</span>
      </UserHoverCard>,
    );

    const trigger = screen.getByTestId('trigger').parentElement!;
    await userEvent.hover(trigger);

    await waitFor(() => {
      const connectBtn = screen.getAllByRole('button').find(
        (b) => /connect/i.test(b.textContent ?? ''),
      );
      expect(connectBtn).toBeInTheDocument();
    });
  });

  it('calls POST /v2/connections/request when Connect is clicked', async () => {
    mockApiObj.post.mockResolvedValue({ success: true });

    render(
      <UserHoverCard userId={99}>
        <span data-testid="trigger">Alice</span>
      </UserHoverCard>,
    );

    const trigger = screen.getByTestId('trigger').parentElement!;
    await userEvent.hover(trigger);

    await waitFor(() => {
      const connectBtn = screen.getAllByRole('button').find(
        (b) => /connect/i.test(b.textContent ?? ''),
      );
      expect(connectBtn).toBeInTheDocument();
    });

    const connectBtn = screen.getAllByRole('button').find(
      (b) => /connect/i.test(b.textContent ?? ''),
    )!;
    await userEvent.click(connectBtn);

    await waitFor(() => {
      expect(mockApiObj.post).toHaveBeenCalledWith(
        '/v2/connections/request',
        expect.objectContaining({ user_id: 99 }),
      );
    });
  });

  it('does not flip to (or cache) a pending state when the request returns success:false', async () => {
    // Regression: handleConnect did an UNCHECKED `await api.post(...)` then set — and
    // CACHED — connection_status:'pending'. api.post resolves { success:false } on a 4xx
    // (already requested / blocked / rate-limited) WITHOUT throwing, so a rejected
    // request used to show (and persist to the module cache) a fake 'pending' state.
    // The card must stay "Connect" so the user can retry. Unique userId 777 avoids the
    // module-level cache from other tests.
    mockApiObj.get.mockResolvedValue({ success: true, data: { ...MOCK_USER_DATA, id: 777, connection_status: 'none' } });
    mockApiObj.post.mockResolvedValue({ success: false, error: 'You have already sent a request' });

    render(
      <UserHoverCard userId={777}>
        <span data-testid="trigger-fail">Carol</span>
      </UserHoverCard>,
    );
    const trigger = screen.getByTestId('trigger-fail').parentElement!;
    await userEvent.hover(trigger);

    await waitFor(() => {
      const connectBtn = screen.getAllByRole('button').find((b) => /connect/i.test(b.textContent ?? ''));
      expect(connectBtn).toBeInTheDocument();
    });
    const connectBtn = screen.getAllByRole('button').find((b) => /connect/i.test(b.textContent ?? ''))!;
    await userEvent.click(connectBtn);

    await waitFor(() => {
      expect(mockApiObj.post).toHaveBeenCalledWith(
        '/v2/connections/request',
        expect.objectContaining({ user_id: 777 }),
      );
    });
    // The optimistic 'pending' was NOT applied on the rejected request: the Connect
    // button remains and no "pending" state is shown.
    expect(screen.getAllByRole('button').find((b) => /connect/i.test(b.textContent ?? ''))).toBeDefined();
    expect(screen.queryByText(/pending/i)).not.toBeInTheDocument();
  });

  it('shows Connected button (disabled) when connection_status is connected', async () => {
    // Use a different userId to avoid the module-level cache returning 'none' status
    mockApiObj.get.mockResolvedValue({
      success: true,
      data: { ...MOCK_USER_DATA, id: 200, connection_status: 'connected' },
    });

    render(
      <UserHoverCard userId={200}>
        <span data-testid="trigger-connected">Bob</span>
      </UserHoverCard>,
    );

    const trigger = screen.getByTestId('trigger-connected').parentElement!;
    await userEvent.hover(trigger);

    await waitFor(() => {
      // "Connected" text should be in the popover — check by text content since
      // HeroUI isDisabled may render as aria-disabled rather than native disabled
      expect(screen.getByText(/connected/i)).toBeInTheDocument();
    });

    // The connected button should be aria-disabled or disabled
    const connectedEl = screen.getByText(/connected/i).closest('button');
    // If it rendered as a button, it should be disabled in some form
    if (connectedEl) {
      const isDisabled =
        connectedEl.disabled ||
        connectedEl.getAttribute('aria-disabled') === 'true' ||
        connectedEl.getAttribute('data-disabled') === 'true';
      expect(isDisabled).toBe(true);
    }
  });

  it('shows Pending button (disabled) when connection_status is pending', async () => {
    // Use yet another userId to avoid cache collisions
    mockApiObj.get.mockResolvedValue({
      success: true,
      data: { ...MOCK_USER_DATA, id: 201, connection_status: 'pending' },
    });

    render(
      <UserHoverCard userId={201}>
        <span data-testid="trigger-pending">Carol</span>
      </UserHoverCard>,
    );

    const trigger = screen.getByTestId('trigger-pending').parentElement!;
    await userEvent.hover(trigger);

    await waitFor(() => {
      expect(screen.getByText(/pending/i)).toBeInTheDocument();
    });

    const pendingEl = screen.getByText(/pending/i).closest('button');
    if (pendingEl) {
      const isDisabled =
        pendingEl.disabled ||
        pendingEl.getAttribute('aria-disabled') === 'true' ||
        pendingEl.getAttribute('data-disabled') === 'true';
      expect(isDisabled).toBe(true);
    }
  });

  it('on touch device: renders children only (no popover wrapper)', () => {
    // Force touch environment
    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: () => ({ matches: false, addListener: vi.fn(), removeListener: vi.fn(), addEventListener: vi.fn(), removeEventListener: vi.fn(), dispatchEvent: vi.fn() }),
    });
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (window as any).ontouchstart = () => {};

    render(
      <UserHoverCard userId={99}>
        <span data-testid="trigger">Alice</span>
      </UserHoverCard>,
    );

    expect(screen.getByTestId('trigger')).toBeInTheDocument();
    // No popover: api.get should not be called
    expect(mockApiObj.get).not.toHaveBeenCalled();

    // cleanup
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    delete (window as any).ontouchstart;
  });
});
