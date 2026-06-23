// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── API mock (required by module graph) ─────────────────────────────────────
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

// ─── Stable context state controlled via module-level variables ───────────────
// We keep the actual values in mutable refs that the mock factory reads
// on each call (not closed over at hoist time).
const authState = { isAuthenticated: true };
const featureState = { allEnabled: true };

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: authState.isAuthenticated ? { id: 1, name: 'Alice' } : null,
      isAuthenticated: authState.isAuthenticated,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: (_feat: string) => featureState.allEnabled,
      hasModule: (_mod: string) => true,
    }),
  })
);

// ─── Stub heavy UI children ───────────────────────────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <div data-testid="glass-card" className={className}>{children}</div>
    ),
    Button: ({
      children,
      to,
      as: _as,
      startContent,
      ...rest
    }: {
      children: React.ReactNode;
      to?: string;
      as?: React.ElementType;
      startContent?: React.ReactNode;
      [key: string]: unknown;
    }) => (
      <a href={to as string | undefined} role="link" {...rest as object}>
        {startContent}
        {children}
      </a>
    ),
  };
});

// ─────────────────────────────────────────────────────────────────────────────
describe('QuickActionsWidget', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    // Restore defaults after resetAllMocks
    authState.isAuthenticated = true;
    featureState.allEnabled = true;
  });

  it('renders nothing when user is not authenticated', async () => {
    authState.isAuthenticated = false;
    vi.resetModules();
    const { QuickActionsWidget } = await import('./QuickActionsWidget');
    render(<QuickActionsWidget />);
    expect(screen.queryByTestId('glass-card')).not.toBeInTheDocument();
    expect(screen.queryByText('Create New Listing')).not.toBeInTheDocument();
  });

  it('renders the "Create New Listing" primary CTA when authenticated', async () => {
    const { QuickActionsWidget } = await import('./QuickActionsWidget');
    render(<QuickActionsWidget />);
    expect(screen.getByText('Create New Listing')).toBeInTheDocument();
  });

  it('Create New Listing link points to /listings/create', async () => {
    const { QuickActionsWidget } = await import('./QuickActionsWidget');
    render(<QuickActionsWidget />);
    const cta = screen.getByText('Create New Listing').closest('[role="link"]') as HTMLElement | null;
    expect(cta).not.toBeNull();
    expect(cta!.getAttribute('href')).toBe('/test/listings/create');
  });

  it('renders the Host Event action when events feature is enabled', async () => {
    const { QuickActionsWidget } = await import('./QuickActionsWidget');
    render(<QuickActionsWidget />);
    expect(screen.getByText('Host Event')).toBeInTheDocument();
  });

  it('renders the Create Poll action when polls feature is enabled', async () => {
    const { QuickActionsWidget } = await import('./QuickActionsWidget');
    render(<QuickActionsWidget />);
    expect(screen.getByText('Create Poll')).toBeInTheDocument();
  });

  it('renders the Set Goal action when goals feature is enabled', async () => {
    const { QuickActionsWidget } = await import('./QuickActionsWidget');
    render(<QuickActionsWidget />);
    expect(screen.getByText('Set Goal')).toBeInTheDocument();
  });

  it('renders the Groups action when groups feature is enabled', async () => {
    const { QuickActionsWidget } = await import('./QuickActionsWidget');
    render(<QuickActionsWidget />);
    expect(screen.getByText('Groups')).toBeInTheDocument();
  });

  it('does not render secondary actions when all features are disabled', async () => {
    featureState.allEnabled = false;
    vi.resetModules();
    const { QuickActionsWidget } = await import('./QuickActionsWidget');
    render(<QuickActionsWidget />);
    // Primary CTA always shows; secondary actions should be absent
    expect(screen.getByText('Create New Listing')).toBeInTheDocument();
    expect(screen.queryByText('Host Event')).not.toBeInTheDocument();
    expect(screen.queryByText('Create Poll')).not.toBeInTheDocument();
    expect(screen.queryByText('Set Goal')).not.toBeInTheDocument();
    expect(screen.queryByText('Groups')).not.toBeInTheDocument();
  });

  it('Host Event link navigates to /events/create', async () => {
    const { QuickActionsWidget } = await import('./QuickActionsWidget');
    render(<QuickActionsWidget />);
    // secondary actions render as <Link to="..."> from react-router-dom
    // which renders as <a href="..."> in the DOM
    const hostEventEl = screen.getByText('Host Event');
    const link = hostEventEl.closest('a');
    expect(link).not.toBeNull();
    expect(link!.getAttribute('href')).toBe('/test/events/create');
  });

  it('Goals link navigates to /goals', async () => {
    const { QuickActionsWidget } = await import('./QuickActionsWidget');
    render(<QuickActionsWidget />);
    const goalEl = screen.getByText('Set Goal');
    const link = goalEl.closest('a');
    expect(link).not.toBeNull();
    expect(link!.getAttribute('href')).toBe('/test/goals');
  });

  it('Groups link navigates to /groups', async () => {
    const { QuickActionsWidget } = await import('./QuickActionsWidget');
    render(<QuickActionsWidget />);
    const groupEl = screen.getByText('Groups');
    const link = groupEl.closest('a');
    expect(link).not.toBeNull();
    expect(link!.getAttribute('href')).toBe('/test/groups');
  });

  it('wraps content in the GlassCard', async () => {
    const { QuickActionsWidget } = await import('./QuickActionsWidget');
    render(<QuickActionsWidget />);
    expect(screen.getByTestId('glass-card')).toBeInTheDocument();
  });
});
