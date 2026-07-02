// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

const { mockQueue } = vi.hoisted(() => ({
  mockQueue: vi.fn(() => <div data-testid="admin-content-queue" />),
}));

vi.mock('@/admin/modules/reports/ModerationQueuePage', () => ({
  __esModule: true,
  default: mockQueue,
}));

const mockUser = vi.hoisted(() => ({ current: { id: 1, role: 'broker' } as Record<string, unknown> }));

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({ user: mockUser.current }),
  }),
);

vi.mock('@/lib/access', () => ({
  hasAdminPanelAccess: (u: { role?: string } | null) =>
    u?.role === 'admin' || u?.role === 'super_admin',
}));

describe('ContentQueuePage (broker)', () => {
  it('frames the admin content queue in the broker shell', async () => {
    mockUser.current = { id: 1, role: 'broker' };
    const Component = (await import('./ContentQueuePage')).default;
    render(<Component />);

    expect(screen.getByRole('heading', { level: 1, name: 'Content Queue' })).toBeInTheDocument();
    expect(screen.getByTestId('admin-content-queue')).toBeInTheDocument();
  });

  it('hides the admin-only Settings button for broker-role users', async () => {
    mockUser.current = { id: 1, role: 'broker' };
    const Component = (await import('./ContentQueuePage')).default;
    render(<Component />);

    const wrapper = screen.getByTestId('admin-content-queue').parentElement;
    expect(wrapper?.className).toContain('button:first-of-type]:hidden');
  });

  it('keeps the Settings button for admins', async () => {
    mockUser.current = { id: 2, role: 'admin' };
    const Component = (await import('./ContentQueuePage')).default;
    render(<Component />);

    const wrapper = screen.getByTestId('admin-content-queue').parentElement;
    expect(wrapper?.className).not.toContain('button:first-of-type]:hidden');
  });
});
