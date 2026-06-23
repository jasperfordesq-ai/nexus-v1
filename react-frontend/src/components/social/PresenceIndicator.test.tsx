// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import type { PresenceState } from '@/contexts/PresenceContext';

// ─── Mock api ─────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Motion shim (no React.forwardRef to avoid hoisting TDZ issues) ───────────
vi.mock('@/lib/motion', () => ({
  motion: {
    div: ({
      children,
      animate: _a,
      initial: _i,
      exit: _e,
      transition: _t,
      ...rest
    }: Record<string, unknown>) => (
      <div {...(rest as React.HTMLAttributes<HTMLDivElement>)}>{children as React.ReactNode}</div>
    ),
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// ─── Tooltip stub ─────────────────────────────────────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    Tooltip: ({ children, content }: { children: React.ReactNode; content: string }) => (
      <span data-tooltip={content}>{children}</span>
    ),
  };
});

// ─── PresenceContext mock ─────────────────────────────────────────────────────
const mockGetPresence = vi.fn();

const makePresence = (status: PresenceState['status'], overrides: Partial<PresenceState> = {}): PresenceState => ({
  status,
  last_seen_at: null,
  custom_status: null,
  status_emoji: null,
  ...overrides,
});

vi.mock('@/contexts/PresenceContext', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/contexts/PresenceContext')>();
  return {
    ...orig,
    usePresenceOptional: () => ({ getPresence: mockGetPresence }),
  };
});

// ─── Auth / Tenant contexts ───────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Alice' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─────────────────────────────────────────────────────────────────────────────
describe('PresenceIndicator', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    // By default getPresence returns offline
    mockGetPresence.mockReturnValue(makePresence('offline'));
  });

  it('renders nothing (no presence dot) for offline user when showOffline=false', async () => {
    mockGetPresence.mockReturnValue(makePresence('offline'));
    const { PresenceIndicator } = await import('./PresenceIndicator');
    render(<PresenceIndicator userId={5} />);
    // The presence dot would have aria-label that is NOT "Notifications" or "Urgent notifications"
    const statusEls = screen.queryAllByRole('status');
    const presenceDot = statusEls.find((el) => {
      const label = el.getAttribute('aria-label') ?? '';
      return label.length > 0 && label !== 'Notifications' && label !== 'Urgent notifications';
    });
    expect(presenceDot).toBeUndefined();
  });

  it('renders presence dot (role=status with aria-label) for offline user when showOffline=true', async () => {
    mockGetPresence.mockReturnValue(makePresence('offline'));
    const { PresenceIndicator } = await import('./PresenceIndicator');
    render(<PresenceIndicator userId={5} showOffline={true} />);
    const statusEls = screen.getAllByRole('status');
    const presenceDot = statusEls.find((el) => {
      const label = el.getAttribute('aria-label') ?? '';
      return label.length > 0 && label !== 'Notifications' && label !== 'Urgent notifications';
    });
    expect(presenceDot).toBeDefined();
  });

  it('renders presence dot for online user', async () => {
    mockGetPresence.mockReturnValue(makePresence('online'));
    const { PresenceIndicator } = await import('./PresenceIndicator');
    render(<PresenceIndicator userId={10} />);
    const statusEls = screen.getAllByRole('status');
    const presenceDot = statusEls.find((el) => {
      const label = el.getAttribute('aria-label') ?? '';
      return label.length > 0 && label !== 'Notifications' && label !== 'Urgent notifications';
    });
    expect(presenceDot).toBeDefined();
  });

  it('renders presence dot for away user', async () => {
    mockGetPresence.mockReturnValue(
      makePresence('away', { last_seen_at: new Date(Date.now() - 5 * 60_000).toISOString() })
    );
    const { PresenceIndicator } = await import('./PresenceIndicator');
    render(<PresenceIndicator userId={11} />);
    const statusEls = screen.getAllByRole('status');
    const presenceDot = statusEls.find((el) => {
      const label = el.getAttribute('aria-label') ?? '';
      return label.length > 0 && label !== 'Notifications' && label !== 'Urgent notifications';
    });
    expect(presenceDot).toBeDefined();
  });

  it('renders presence dot for dnd user', async () => {
    mockGetPresence.mockReturnValue(makePresence('dnd'));
    const { PresenceIndicator } = await import('./PresenceIndicator');
    render(<PresenceIndicator userId={12} />);
    const statusEls = screen.getAllByRole('status');
    const presenceDot = statusEls.find((el) => {
      const label = el.getAttribute('aria-label') ?? '';
      return label.length > 0 && label !== 'Notifications' && label !== 'Urgent notifications';
    });
    expect(presenceDot).toBeDefined();
  });

  it('online dot has aria-label containing online status', async () => {
    mockGetPresence.mockReturnValue(makePresence('online'));
    const { PresenceIndicator } = await import('./PresenceIndicator');
    render(<PresenceIndicator userId={10} />);
    const statusEls = screen.getAllByRole('status');
    const dot = statusEls.find((el) => {
      const label = el.getAttribute('aria-label') ?? '';
      return label.length > 0 && label !== 'Notifications' && label !== 'Urgent notifications';
    });
    expect(dot?.getAttribute('aria-label')).toBeTruthy();
  });

  it('presence dot has data-tooltip attribute from Tooltip wrapper', async () => {
    mockGetPresence.mockReturnValue(makePresence('online'));
    const { PresenceIndicator } = await import('./PresenceIndicator');
    render(<PresenceIndicator userId={10} />);
    // Our Tooltip stub renders a span with data-tooltip={content}
    const tooltipWrapper = document.querySelector('[data-tooltip]');
    expect(tooltipWrapper).toBeTruthy();
    expect(tooltipWrapper?.getAttribute('data-tooltip')).toBeTruthy();
  });

  it('applies custom className for positioning override', async () => {
    mockGetPresence.mockReturnValue(makePresence('online'));
    const { PresenceIndicator } = await import('./PresenceIndicator');
    render(<PresenceIndicator userId={10} className="custom-pos" />);
    const statusEls = screen.getAllByRole('status');
    const dot = statusEls.find((el) => {
      const label = el.getAttribute('aria-label') ?? '';
      return label.length > 0 && label !== 'Notifications' && label !== 'Urgent notifications';
    });
    expect(dot?.className).toContain('custom-pos');
  });

  it('shows custom_status in tooltip content when set', async () => {
    mockGetPresence.mockReturnValue(
      makePresence('online', { custom_status: 'In a meeting', status_emoji: null })
    );
    const { PresenceIndicator } = await import('./PresenceIndicator');
    render(<PresenceIndicator userId={10} />);
    const tooltipWrapper = document.querySelector('[data-tooltip]');
    expect(tooltipWrapper?.getAttribute('data-tooltip')).toContain('In a meeting');
  });

  it('shows emoji + custom_status in tooltip content when both set', async () => {
    mockGetPresence.mockReturnValue(
      makePresence('online', { custom_status: 'Coding', status_emoji: '💻' })
    );
    const { PresenceIndicator } = await import('./PresenceIndicator');
    render(<PresenceIndicator userId={10} />);
    const tooltipWrapper = document.querySelector('[data-tooltip]');
    const tip = tooltipWrapper?.getAttribute('data-tooltip') ?? '';
    expect(tip).toContain('💻');
    expect(tip).toContain('Coding');
  });

  it('online dot inner element has green bg class', async () => {
    mockGetPresence.mockReturnValue(makePresence('online'));
    const { PresenceIndicator } = await import('./PresenceIndicator');
    render(<PresenceIndicator userId={10} />);
    const statusEls = screen.getAllByRole('status');
    const dot = statusEls.find((el) => {
      const label = el.getAttribute('aria-label') ?? '';
      return label.length > 0 && label !== 'Notifications' && label !== 'Urgent notifications';
    });
    const inner = dot?.firstChild as HTMLElement | null;
    expect(inner?.className).toContain('bg-green-500');
  });

  it('dnd dot inner element has red bg class', async () => {
    mockGetPresence.mockReturnValue(makePresence('dnd'));
    const { PresenceIndicator } = await import('./PresenceIndicator');
    render(<PresenceIndicator userId={12} />);
    const statusEls = screen.getAllByRole('status');
    const dot = statusEls.find((el) => {
      const label = el.getAttribute('aria-label') ?? '';
      return label.length > 0 && label !== 'Notifications' && label !== 'Urgent notifications';
    });
    const inner = dot?.firstChild as HTMLElement | null;
    expect(inner?.className).toContain('bg-red-500');
  });

  it('applies sm size class to inner element', async () => {
    mockGetPresence.mockReturnValue(makePresence('online'));
    const { PresenceIndicator } = await import('./PresenceIndicator');
    render(<PresenceIndicator userId={10} size="sm" />);
    const statusEls = screen.getAllByRole('status');
    const dot = statusEls.find((el) => {
      const label = el.getAttribute('aria-label') ?? '';
      return label.length > 0 && label !== 'Notifications' && label !== 'Urgent notifications';
    });
    const inner = dot?.firstChild as HTMLElement | null;
    expect(inner?.className).toContain('w-2');
    expect(inner?.className).toContain('h-2');
  });

  it('applies lg size class to inner element', async () => {
    mockGetPresence.mockReturnValue(makePresence('online'));
    const { PresenceIndicator } = await import('./PresenceIndicator');
    render(<PresenceIndicator userId={10} size="lg" />);
    const statusEls = screen.getAllByRole('status');
    const dot = statusEls.find((el) => {
      const label = el.getAttribute('aria-label') ?? '';
      return label.length > 0 && label !== 'Notifications' && label !== 'Urgent notifications';
    });
    const inner = dot?.firstChild as HTMLElement | null;
    expect(inner?.className).toContain('w-3');
    expect(inner?.className).toContain('h-3');
  });
});
