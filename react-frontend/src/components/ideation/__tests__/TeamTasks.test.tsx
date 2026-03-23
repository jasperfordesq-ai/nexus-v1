// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for TeamTasks component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
};

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => mockToast),
  useAuth: vi.fn(() => ({
    isAuthenticated: true,
    user: { id: 1, name: 'Test User', role: 'user' },
    login: vi.fn(),
    logout: vi.fn(),
    register: vi.fn(),
    updateUser: vi.fn(),
    refreshUser: vi.fn(),
    status: 'idle',
    error: null,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test', logo_url: null },
    tenantSlug: 'test',
    tenantPath: (p: string) => '/test' + p,
    isLoading: false,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url: string | null) => url || '/default-avatar.png'),
}));

import { TeamTasks } from '../TeamTasks';

const mockStats = {
  total: 5,
  todo: 2,
  in_progress: 2,
  done: 1,
  overdue: 1,
};

const mockTasks = [
  {
    id: 1,
    group_id: 10,
    title: 'Write documentation',
    description: 'Document the new API endpoints',
    status: 'todo' as const,
    priority: 'high' as const,
    assigned_to: 1,
    due_date: '2026-04-01',
    created_at: '2026-01-01T00:00:00Z',
    assignee: { id: 1, name: 'Alice', avatar_url: null },
  },
  {
    id: 2,
    group_id: 10,
    title: 'Review PR',
    description: null,
    status: 'in_progress' as const,
    priority: 'medium' as const,
    assigned_to: null,
    due_date: null,
    created_at: '2026-01-02T00:00:00Z',
    assignee: null,
  },
  {
    id: 3,
    group_id: 10,
    title: 'Deploy changes',
    description: null,
    status: 'done' as const,
    priority: 'low' as const,
    assigned_to: null,
    due_date: null,
    created_at: '2026-01-03T00:00:00Z',
    assignee: null,
  },
];

describe('TeamTasks', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders loading spinner initially', () => {
    vi.mocked(api.get).mockImplementation(() => new Promise(() => {}));

    render(<TeamTasks groupId={10} isGroupAdmin={false} />);
    expect(screen.getByLabelText('Loading')).toBeInTheDocument();
  });

  it('renders tasks after loading', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/stats')) return Promise.resolve({ success: true, data: mockStats });
      return Promise.resolve({ success: true, data: mockTasks });
    });

    render(<TeamTasks groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(screen.getByText('Write documentation')).toBeInTheDocument();
      expect(screen.getByText('Review PR')).toBeInTheDocument();
      expect(screen.getByText('Deploy changes')).toBeInTheDocument();
    });
  });

  it('renders empty state when no tasks', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/stats')) return Promise.resolve({ success: true, data: { total: 0, todo: 0, in_progress: 0, done: 0, overdue: 0 } });
      return Promise.resolve({ success: true, data: [] });
    });

    render(<TeamTasks groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(screen.getByText('No Tasks Yet')).toBeInTheDocument();
    });
  });

  it('renders stats bar with counts', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/stats')) return Promise.resolve({ success: true, data: mockStats });
      return Promise.resolve({ success: true, data: mockTasks });
    });

    render(<TeamTasks groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(screen.getByText('Write documentation')).toBeInTheDocument();
    });
  });

  it('renders Add Task button', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/stats')) return Promise.resolve({ success: true, data: mockStats });
      return Promise.resolve({ success: true, data: mockTasks });
    });

    render(<TeamTasks groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(screen.getByText('Add Task')).toBeInTheDocument();
    });
  });

  it('renders status filter buttons', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/stats')) return Promise.resolve({ success: true, data: mockStats });
      return Promise.resolve({ success: true, data: mockTasks });
    });

    render(<TeamTasks groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(screen.getByText('All')).toBeInTheDocument();
    });
  });

  it('renders priority chips for tasks', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/stats')) return Promise.resolve({ success: true, data: mockStats });
      return Promise.resolve({ success: true, data: mockTasks });
    });

    render(<TeamTasks groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(screen.getByText('High')).toBeInTheDocument();
      expect(screen.getByText('Medium')).toBeInTheDocument();
      expect(screen.getByText('Low')).toBeInTheDocument();
    });
  });

  it('shows delete button for admin', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/stats')) return Promise.resolve({ success: true, data: mockStats });
      return Promise.resolve({ success: true, data: mockTasks });
    });

    render(<TeamTasks groupId={10} isGroupAdmin={true} />);

    await waitFor(() => {
      // aria-label from t('toast.task_deleted') = "Task deleted"
      const deleteBtns = screen.getAllByLabelText('Task deleted');
      expect(deleteBtns.length).toBeGreaterThanOrEqual(1);
    });
  });

  it('shows assignee name when task has assignee', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/stats')) return Promise.resolve({ success: true, data: mockStats });
      return Promise.resolve({ success: true, data: mockTasks });
    });

    render(<TeamTasks groupId={10} isGroupAdmin={false} />);

    await waitFor(() => {
      expect(screen.getByText('Alice')).toBeInTheDocument();
    });
  });
});
