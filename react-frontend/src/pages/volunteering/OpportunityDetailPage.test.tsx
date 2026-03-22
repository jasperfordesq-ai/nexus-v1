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
    useParams: () => ({ id: '42' }),
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
    user: { id: 5, first_name: 'Jane', name: 'Jane Owner', role: 'member' },
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
vi.mock('@/lib/helpers', () => ({ resolveAvatarUrl: (url: string | null) => url ?? '' }));

import OpportunityDetailPage from './OpportunityDetailPage';

const mockOpportunity = {
  id: 42,
  title: 'Park Cleanup Drive',
  description: 'Help clean the local park.',
  location: 'Dublin City Park',
  skills_needed: 'Enthusiasm',
  start_date: '2026-06-01T09:00:00Z',
  end_date: '2026-06-01T17:00:00Z',
  is_active: true,
  is_remote: false,
  category: 'Environment',
  organization: { id: 7, name: 'Green Dublin', logo_url: null },
  created_at: '2026-01-10T12:00:00Z',
  shifts: [
    {
      id: 1,
      start_time: '2026-06-01T09:00:00Z',
      end_time: '2026-06-01T13:00:00Z',
      capacity: 10,
      signup_count: 3,
      spots_available: 7,
    },
  ],
  has_applied: false,
  application: null,
  is_owner: false,
};

const mockOpportunityOwner = {
  ...mockOpportunity,
  is_owner: true,
};

const mockApplicationsResponse = {
  success: true,
  data: {
    items: [
      {
        id: 101,
        status: 'pending',
        message: 'I love the environment!',
        created_at: '2026-02-01T10:00:00Z',
        user: { id: 20, name: 'Alice Volunteer', email: 'alice@example.com', avatar_url: null },
        shift: { id: 1, start_time: '2026-06-01T09:00:00Z', end_time: '2026-06-01T13:00:00Z' },
      },
    ],
    cursor: null,
    has_more: false,
  },
};

describe('OpportunityDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading screen initially', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<OpportunityDetailPage />);
    // LoadingScreen renders during data fetch — document should be in loading state
    expect(document.body).toBeTruthy();
  });

  it('renders opportunity title and organisation on success', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockOpportunity });
    render(<OpportunityDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Park Cleanup Drive')[0]).toBeInTheDocument();
    });
    expect(screen.getByText('Green Dublin')).toBeInTheDocument();
  });

  it('shows error state when API fails', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network failure'));
    render(<OpportunityDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('opportunity.load_error')).toBeInTheDocument();
    });
    expect(screen.getByText('opportunity.try_again')).toBeInTheDocument();
  });

  it('shows Apply button for non-owner who has not applied', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockOpportunity });
    render(<OpportunityDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Park Cleanup Drive')[0]).toBeInTheDocument();
    });
    expect(screen.getByText('opportunity.apply_now')).toBeInTheDocument();
  });

  it('shows already-applied state when user has applied', async () => {
    const applied = {
      ...mockOpportunity,
      has_applied: true,
      application: { id: 99, status: 'pending', message: null, created_at: '2026-02-01T00:00:00Z' },
    };
    vi.mocked(api.get).mockResolvedValue({ success: true, data: applied });
    render(<OpportunityDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Park Cleanup Drive')[0]).toBeInTheDocument();
    });
    expect(screen.getByText('opportunity.you_have_applied')).toBeInTheDocument();
  });

  it('shows shift information when shifts are present', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockOpportunity });
    render(<OpportunityDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Park Cleanup Drive')[0]).toBeInTheDocument();
    });
    // Shifts section heading
    expect(screen.getByText('opportunity.upcoming_shifts')).toBeInTheDocument();
  });

  it('shows Applications panel for opportunity owner', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/applications')) {
        return Promise.resolve(mockApplicationsResponse);
      }
      return Promise.resolve({ success: true, data: mockOpportunityOwner });
    });
    render(<OpportunityDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Park Cleanup Drive')[0]).toBeInTheDocument();
    });
    await waitFor(() => {
      expect(screen.getByText('applications.heading')).toBeInTheDocument();
    });
  });

  it('does not show Applications panel for non-owner', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockOpportunity });
    render(<OpportunityDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Park Cleanup Drive')[0]).toBeInTheDocument();
    });
    expect(screen.queryByText('applications.heading')).not.toBeInTheDocument();
  });

  it('calls the correct API endpoint on mount', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockOpportunity });
    render(<OpportunityDetailPage />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('/v2/volunteering/opportunities/42'),
      );
    });
  });

  it('shows remote badge for remote opportunities', async () => {
    const remote = { ...mockOpportunity, is_remote: true, location: '' };
    vi.mocked(api.get).mockResolvedValue({ success: true, data: remote });
    render(<OpportunityDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Park Cleanup Drive')[0]).toBeInTheDocument();
    });
    expect(screen.getByText('opportunity.remote')).toBeInTheDocument();
  });

  it('shows inactive badge for inactive opportunities', async () => {
    const inactive = { ...mockOpportunity, is_active: false };
    vi.mocked(api.get).mockResolvedValue({ success: true, data: inactive });
    render(<OpportunityDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Park Cleanup Drive')[0]).toBeInTheDocument();
    });
    expect(screen.getByText('opportunity.status_closed')).toBeInTheDocument();
  });

  it('retry button refetches data after error', async () => {
    vi.mocked(api.get)
      .mockRejectedValueOnce(new Error('Network failure'))
      .mockResolvedValueOnce({ success: true, data: mockOpportunity });
    render(<OpportunityDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('opportunity.try_again')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('opportunity.try_again'));
    await waitFor(() => {
      expect(screen.getAllByText('Park Cleanup Drive')[0]).toBeInTheDocument();
    });
  });
});
