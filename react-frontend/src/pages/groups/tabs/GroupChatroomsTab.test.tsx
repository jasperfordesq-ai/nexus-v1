// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api ────────────────────────────────────────────────────────────────
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

// ─── Contexts ────────────────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Group Member', role: 'member' },
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
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
    // Pusher is optional in TeamChatrooms — return null to avoid real Pusher
    usePusherOptional: () => null,
  })
);

// ─── Stub TeamChatrooms — capture props forwarded to it ──────────────────────
// GroupChatroomsTab passes groupId and isGroupAdmin down to TeamChatrooms.
// We stub the component and record the received props so we can assert them.
const capturedProps: { groupId?: number; isGroupAdmin?: boolean } = {};

vi.mock('@/components/ideation', () => ({
  TeamChatrooms: (props: { groupId: number; isGroupAdmin: boolean }) => {
    capturedProps.groupId = props.groupId;
    capturedProps.isGroupAdmin = props.isGroupAdmin;
    return (
      <div
        data-testid="team-chatrooms-stub"
        data-group-id={String(props.groupId)}
        data-is-admin={String(props.isGroupAdmin)}
      />
    );
  },
  // Export the sibling task surface so barrel imports remain stable.
  TeamTasks: () => null,
}));

// ─── Stub GlassCard (it imports @/lib/motion which may not be jsdom-safe) ────
vi.mock('@/components/ui/GlassCard', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>
      {children}
    </div>
  ),
}));

// ─────────────────────────────────────────────────────────────────────────────
describe('GroupChatroomsTab', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    // Reset captured props
    delete capturedProps.groupId;
    delete capturedProps.isGroupAdmin;
    // Default: chatrooms API returns empty list (not called by the stub, but
    // guards against any accidental real import paths)
    mockApi.get.mockResolvedValue({ success: true, data: [] });
  });

  it('renders without crashing', async () => {
    const { GroupChatroomsTab } = await import('./GroupChatroomsTab');
    const { container } = render(<GroupChatroomsTab groupId={7} isGroupAdmin={false} />);
    expect(container).toBeTruthy();
  });

  it('renders the TeamChatrooms child component', async () => {
    const { GroupChatroomsTab } = await import('./GroupChatroomsTab');
    render(<GroupChatroomsTab groupId={7} isGroupAdmin={false} />);

    expect(screen.getByTestId('team-chatrooms-stub')).toBeInTheDocument();
  });

  it('forwards groupId prop to TeamChatrooms', async () => {
    const { GroupChatroomsTab } = await import('./GroupChatroomsTab');
    render(<GroupChatroomsTab groupId={42} isGroupAdmin={false} />);

    const stub = screen.getByTestId('team-chatrooms-stub');
    expect(stub).toHaveAttribute('data-group-id', '42');
    expect(capturedProps.groupId).toBe(42);
  });

  it('forwards isGroupAdmin=true prop to TeamChatrooms', async () => {
    const { GroupChatroomsTab } = await import('./GroupChatroomsTab');
    render(<GroupChatroomsTab groupId={7} isGroupAdmin={true} />);

    const stub = screen.getByTestId('team-chatrooms-stub');
    expect(stub).toHaveAttribute('data-is-admin', 'true');
    expect(capturedProps.isGroupAdmin).toBe(true);
  });

  it('forwards isGroupAdmin=false prop to TeamChatrooms', async () => {
    const { GroupChatroomsTab } = await import('./GroupChatroomsTab');
    render(<GroupChatroomsTab groupId={7} isGroupAdmin={false} />);

    const stub = screen.getByTestId('team-chatrooms-stub');
    expect(stub).toHaveAttribute('data-is-admin', 'false');
    expect(capturedProps.isGroupAdmin).toBe(false);
  });

  it('wraps content in a GlassCard container', async () => {
    const { GroupChatroomsTab } = await import('./GroupChatroomsTab');
    render(<GroupChatroomsTab groupId={5} isGroupAdmin={false} />);

    // GlassCard is stubbed to render data-testid="glass-card"
    expect(screen.getByTestId('glass-card')).toBeInTheDocument();
  });

  it('TeamChatrooms is rendered inside the GlassCard', async () => {
    const { GroupChatroomsTab } = await import('./GroupChatroomsTab');
    render(<GroupChatroomsTab groupId={5} isGroupAdmin={false} />);

    const glassCard = screen.getByTestId('glass-card');
    const teamChatrooms = screen.getByTestId('team-chatrooms-stub');

    // TeamChatrooms must be a descendant of GlassCard
    expect(glassCard).toContainElement(teamChatrooms);
  });

  it('GlassCard has padding class applied', async () => {
    const { GroupChatroomsTab } = await import('./GroupChatroomsTab');
    render(<GroupChatroomsTab groupId={5} isGroupAdmin={false} />);

    const glassCard = screen.getByTestId('glass-card');
    // The component applies className="p-6" to GlassCard
    expect(glassCard.className).toContain('p-6');
  });

  it('re-renders with updated groupId and forwards the new value', async () => {
    const { GroupChatroomsTab } = await import('./GroupChatroomsTab');
    const { rerender } = render(<GroupChatroomsTab groupId={10} isGroupAdmin={false} />);

    expect(screen.getByTestId('team-chatrooms-stub')).toHaveAttribute('data-group-id', '10');

    rerender(<GroupChatroomsTab groupId={20} isGroupAdmin={false} />);

    expect(screen.getByTestId('team-chatrooms-stub')).toHaveAttribute('data-group-id', '20');
    expect(capturedProps.groupId).toBe(20);
  });

  it('re-renders with updated isGroupAdmin and forwards the new value', async () => {
    const { GroupChatroomsTab } = await import('./GroupChatroomsTab');
    const { rerender } = render(<GroupChatroomsTab groupId={7} isGroupAdmin={false} />);

    expect(capturedProps.isGroupAdmin).toBe(false);

    rerender(<GroupChatroomsTab groupId={7} isGroupAdmin={true} />);

    expect(capturedProps.isGroupAdmin).toBe(true);
    expect(screen.getByTestId('team-chatrooms-stub')).toHaveAttribute('data-is-admin', 'true');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Smoke tests for the real TeamChatrooms behaviour that GroupChatroomsTab
// relies on, rendered via the stub layer above but with realistic props.
// ─────────────────────────────────────────────────────────────────────────────
describe('GroupChatroomsTab — prop contract validation', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    delete capturedProps.groupId;
    delete capturedProps.isGroupAdmin;
    mockApi.get.mockResolvedValue({ success: true, data: [] });
  });

  it('accepts groupId as a number (not a string)', async () => {
    const { GroupChatroomsTab } = await import('./GroupChatroomsTab');
    // TypeScript enforces number; confirm the value arrives correctly typed
    render(<GroupChatroomsTab groupId={99} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(capturedProps.groupId).toStrictEqual(99);
      expect(typeof capturedProps.groupId).toBe('number');
    });
  });

  it('accepts isGroupAdmin as a boolean', async () => {
    const { GroupChatroomsTab } = await import('./GroupChatroomsTab');
    render(<GroupChatroomsTab groupId={1} isGroupAdmin={true} />);

    await waitFor(() => {
      expect(capturedProps.isGroupAdmin).toStrictEqual(true);
      expect(typeof capturedProps.isGroupAdmin).toBe('boolean');
    });
  });
});
