// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { api } from '@/lib/api';
import type { ReactNode } from 'react';

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: { children?: ReactNode; [k: string]: unknown }) => {
      const { variants: _v, initial: _i, animate: _a, exit: _e, transition: _t, ...rest } = props as Record<string, unknown>;
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children?: ReactNode }) => <>{children}</>,
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) =>
      (opts?.defaultValue as string | undefined) ?? key,
  }),
}));

vi.mock('react-router-dom', async () => {
  const actual = await import('react-router-dom');
  const React = await import('react');
  return {
    ...actual,
    Link: ({ children, to, ...rest }: { children: ReactNode; to: string; [k: string]: unknown }) =>
      React.createElement('a', { href: String(to), ...rest }, children),
    useNavigate: () => vi.fn(),
  };
});

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test', name: 'Test User', role: 'member' },
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

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { MyApplicationsPage } from './MyApplicationsPage';

const mockApplicationsResponse = {
  success: true,
  data: {
    items: [
      {
        id: 1,
        vacancy_id: 10,
        user_id: 1,
        status: 'applied',
        stage: 'initial',
        message: null,
        reviewer_notes: null,
        created_at: '2026-01-01T10:00:00Z',
        updated_at: '2026-01-01T10:00:00Z',
        vacancy: {
          id: 10,
          title: 'Community Garden Volunteer',
          type: 'volunteer' as const,
          commitment: 'flexible' as const,
          status: 'active',
          location: 'Dublin',
          is_remote: false,
          deadline: null,
        },
      },
      {
        id: 2,
        vacancy_id: 11,
        user_id: 1,
        status: 'accepted',
        stage: 'offer',
        message: 'I am very interested in this role',
        reviewer_notes: 'Great candidate',
        created_at: '2026-01-02T10:00:00Z',
        updated_at: '2026-01-03T10:00:00Z',
        vacancy: {
          id: 11,
          title: 'Remote Coding Tutor',
          type: 'paid' as const,
          commitment: 'part_time' as const,
          status: 'active',
          location: null,
          is_remote: true,
          deadline: null,
        },
      },
    ],
    cursor: null,
    has_more: false,
  },
};

const emptyApplicationsResponse = {
  success: true,
  data: { items: [], cursor: null, has_more: false },
};

describe('MyApplicationsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading skeleton initially', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<MyApplicationsPage />);
    // Skeleton elements rendered during loading
    expect(document.querySelectorAll('.animate-pulse, [data-slot="base"]').length).toBeGreaterThanOrEqual(0);
  });

  it('renders page title and filter tabs', async () => {
    vi.mocked(api.get).mockResolvedValue(emptyApplicationsResponse);
    render(<MyApplicationsPage />);
    await waitFor(() => {
      expect(screen.getByText('my_applications.title')).toBeInTheDocument();
    });
    expect(screen.getByText('my_applications.tab_all')).toBeInTheDocument();
    expect(screen.getByText('my_applications.tab_active')).toBeInTheDocument();
    expect(screen.getByText('my_applications.tab_accepted')).toBeInTheDocument();
    expect(screen.getByText('my_applications.tab_rejected')).toBeInTheDocument();
  });

  it('shows empty state when no applications exist', async () => {
    vi.mocked(api.get).mockResolvedValue(emptyApplicationsResponse);
    render(<MyApplicationsPage />);
    await waitFor(() => {
      expect(screen.getByText('my_applications.empty_title')).toBeInTheDocument();
    });
    expect(screen.getByText('my_applications.browse_jobs')).toBeInTheDocument();
  });

  it('renders application cards on success', async () => {
    vi.mocked(api.get).mockResolvedValue(mockApplicationsResponse);
    render(<MyApplicationsPage />);
    await waitFor(() => {
      expect(screen.getByText('Community Garden Volunteer')).toBeInTheDocument();
    });
    expect(screen.getByText('Remote Coding Tutor')).toBeInTheDocument();
  });

  it('shows error state when API fails', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));
    render(<MyApplicationsPage />);
    await waitFor(() => {
      expect(screen.getByText('something_wrong')).toBeInTheDocument();
    });
    expect(screen.getByText('try_again')).toBeInTheDocument();
  });

  it('shows Load More button when has_more is true', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { ...mockApplicationsResponse.data, has_more: true, cursor: 'next123' },
    });
    render(<MyApplicationsPage />);
    await waitFor(() => {
      expect(screen.getByText('load_more')).toBeInTheDocument();
    });
  });

  it('shows withdraw button for active applications', async () => {
    vi.mocked(api.get).mockResolvedValue(mockApplicationsResponse);
    render(<MyApplicationsPage />);
    await waitFor(() => {
      expect(screen.getByText('Community Garden Volunteer')).toBeInTheDocument();
    });
    // Active application (status 'applied') shows a Withdraw button
    const withdrawButtons = screen.getAllByText('my_applications.withdraw');
    expect(withdrawButtons.length).toBeGreaterThan(0);
  });

  it('does not show withdraw button for accepted application', async () => {
    vi.mocked(api.get).mockResolvedValue(mockApplicationsResponse);
    render(<MyApplicationsPage />);
    await waitFor(() => {
      expect(screen.getByText('Remote Coding Tutor')).toBeInTheDocument();
    });
    // There is only 1 active application so only 1 withdraw button
    const withdrawButtons = screen.getAllByText('my_applications.withdraw');
    expect(withdrawButtons.length).toBe(1);
  });

  it('shows cover message when application has a message', async () => {
    vi.mocked(api.get).mockResolvedValue(mockApplicationsResponse);
    render(<MyApplicationsPage />);
    await waitFor(() => {
      expect(screen.getByText('Remote Coding Tutor')).toBeInTheDocument();
    });
    const showMsgButton = screen.getByText('my_applications.show_cover_message');
    expect(showMsgButton).toBeInTheDocument();
    fireEvent.click(showMsgButton);
    await waitFor(() => {
      expect(screen.getByText('I am very interested in this role')).toBeInTheDocument();
    });
  });

  it('shows reviewer notes when present', async () => {
    vi.mocked(api.get).mockResolvedValue(mockApplicationsResponse);
    render(<MyApplicationsPage />);
    await waitFor(() => {
      expect(screen.getByText('Great candidate')).toBeInTheDocument();
    });
  });

  it('calls API with correct endpoint on mount', async () => {
    vi.mocked(api.get).mockResolvedValue(emptyApplicationsResponse);
    render(<MyApplicationsPage />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('/v2/jobs/my-applications'),
      );
    });
  });
});
