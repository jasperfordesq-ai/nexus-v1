// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for NotificationsPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    post: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test' },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  })),
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
  formatRelativeTime: vi.fn(() => '5 minutes ago'),
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
}));

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <div>{title}</div>
      {description && <div>{description}</div>}
    </div>
  ),
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const { variants, initial, animate, layout, ...rest } = props;
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

import { NotificationsPage } from './NotificationsPage';
import { api } from '@/lib/api';

describe('NotificationsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the page heading and description', () => {
    render(<NotificationsPage />);
    expect(screen.getByText('Notifications')).toBeInTheDocument();
    expect(screen.getByText('Stay updated with your activity')).toBeInTheDocument();
  });

  it('shows filter buttons for All and Unread', () => {
    render(<NotificationsPage />);
    expect(screen.getByText('All')).toBeInTheDocument();
    // Unread count starts at 0
    expect(screen.getByText('Unread (0)')).toBeInTheDocument();
  });

  it('shows empty state when no notifications exist', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<NotificationsPage />);
    await waitFor(() => {
      expect(screen.getByText('No notifications')).toBeInTheDocument();
    });
  });

  it('renders notification cards with message and timestamp', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [
        {
          id: 1,
          type: 'message',
          title: 'New Message',
          body: 'You received a new message from Alice',
          message: 'You received a new message from Alice',
          read_at: null,
          created_at: '2026-02-19T10:00:00Z',
        },
        {
          id: 2,
          type: 'listing',
          title: 'Listing Update',
          body: 'Your listing got a response',
          message: 'Your listing got a response',
          read_at: '2026-02-19T09:00:00Z',
          created_at: '2026-02-19T08:00:00Z',
        },
      ],
    });
    render(<NotificationsPage />);
    await waitFor(() => {
      expect(screen.getByText('You received a new message from Alice')).toBeInTheDocument();
    });
    expect(screen.getByText('Your listing got a response')).toBeInTheDocument();
    // Check timestamps rendered
    expect(screen.getAllByText('5 minutes ago').length).toBeGreaterThanOrEqual(2);
  });

  it('shows unread count badge when there are unread notifications', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [
        {
          id: 1,
          type: 'message',
          title: 'New',
          body: 'Unread notification',
          read_at: null,
          created_at: '2026-02-19T10:00:00Z',
        },
        {
          id: 2,
          type: 'listing',
          title: 'Old',
          body: 'Read notification',
          read_at: '2026-02-19T09:00:00Z',
          created_at: '2026-02-19T08:00:00Z',
        },
      ],
    });
    render(<NotificationsPage />);
    await waitFor(() => {
      expect(screen.getByText('1 new')).toBeInTheDocument();
    });
    expect(screen.getByText('Unread (1)')).toBeInTheDocument();
  });

  it('shows Mark all read button when there are unread notifications', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [
        {
          id: 1,
          type: 'message',
          title: 'New',
          body: 'Unread notification',
          read_at: null,
          created_at: '2026-02-19T10:00:00Z',
        },
      ],
    });
    render(<NotificationsPage />);
    await waitFor(() => {
      expect(screen.getByText('Mark all read')).toBeInTheDocument();
    });
  });

  it('shows mark-as-read and delete buttons on notification cards', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [
        {
          id: 1,
          type: 'message',
          title: 'New',
          body: 'Unread notification',
          read_at: null,
          created_at: '2026-02-19T10:00:00Z',
        },
      ],
    });
    render(<NotificationsPage />);
    await waitFor(() => {
      expect(screen.getByLabelText('Mark as read')).toBeInTheDocument();
    });
    expect(screen.getByLabelText('Delete notification')).toBeInTheDocument();
  });

  it('shows notification settings button', () => {
    render(<NotificationsPage />);
    expect(screen.getByLabelText('Notification settings')).toBeInTheDocument();
  });
});
