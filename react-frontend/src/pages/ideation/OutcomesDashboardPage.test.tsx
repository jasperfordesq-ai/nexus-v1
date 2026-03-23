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
    t: (key: string, opts?: string | Record<string, unknown>) =>
      typeof opts === 'string'
        ? opts
        : (opts?.defaultValue as string | undefined) ?? key,
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

import { OutcomesDashboardPage } from './OutcomesDashboardPage';

const mockDashboard = {
  total: 12,
  implemented: 5,
  in_progress: 3,
  not_started: 3,
  abandoned: 1,
  outcomes: [
    {
      challenge_id: 1,
      challenge_title: 'Reduce Carbon Footprint',
      winning_idea_title: 'Community Bike Share Scheme',
      implementation_status: 'implemented' as const,
      impact_description: 'Reduced carbon emissions by 15% in the area.',
      updated_at: '2026-03-01T10:00:00Z',
    },
    {
      challenge_id: 2,
      challenge_title: 'Improve Local Parks',
      winning_idea_title: null,
      implementation_status: 'in_progress' as const,
      impact_description: null,
      updated_at: null,
    },
  ],
};

describe('OutcomesDashboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders dashboard title on success', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockDashboard });
    render(<OutcomesDashboardPage />);
    await waitFor(() => {
      expect(screen.getByText('outcomes.dashboard')).toBeInTheDocument();
    });
  });

  it('renders summary stats on success', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockDashboard });
    render(<OutcomesDashboardPage />);
    await waitFor(() => {
      // Total count
      expect(screen.getByText('12')).toBeInTheDocument();
    });
    // Implemented count
    expect(screen.getByText('5')).toBeInTheDocument();
    // In progress count (3 appears in both in_progress and not_started)
    expect(screen.getAllByText('3').length).toBeGreaterThanOrEqual(1);
  });

  it('renders outcome entries with challenge titles', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockDashboard });
    render(<OutcomesDashboardPage />);
    await waitFor(() => {
      expect(screen.getByText('Reduce Carbon Footprint')).toBeInTheDocument();
    });
    expect(screen.getByText('Improve Local Parks')).toBeInTheDocument();
  });

  it('shows winning idea title when present', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockDashboard });
    render(<OutcomesDashboardPage />);
    await waitFor(() => {
      expect(screen.getByText('Community Bike Share Scheme')).toBeInTheDocument();
    });
  });

  it('shows error state when API fails', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));
    render(<OutcomesDashboardPage />);
    await waitFor(() => {
      expect(screen.getByText('challenges.load_error')).toBeInTheDocument();
    });
    expect(screen.getByText('Retry')).toBeInTheDocument();
  });

  it('retry button refetches data after error', async () => {
    vi.mocked(api.get)
      .mockRejectedValueOnce(new Error('Network error'))
      .mockResolvedValueOnce({ success: true, data: mockDashboard });
    render(<OutcomesDashboardPage />);
    await waitFor(() => {
      expect(screen.getByText('Retry')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('Retry'));
    await waitFor(() => {
      expect(screen.getByText('Reduce Carbon Footprint')).toBeInTheDocument();
    });
  });

  it('calls the correct API endpoint on mount', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockDashboard });
    render(<OutcomesDashboardPage />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/ideation-outcomes/dashboard');
    });
  });
});
