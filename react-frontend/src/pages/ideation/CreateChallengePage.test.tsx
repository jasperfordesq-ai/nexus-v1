// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
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

const mockNavigate = vi.fn();

vi.mock('react-router-dom', async () => {
  const actual = await import('react-router-dom');
  const React = await import('react');
  return {
    ...actual,
    Link: ({ children, to, ...rest }: { children: ReactNode; to: string; [k: string]: unknown }) =>
      React.createElement('a', { href: String(to), ...rest }, children),
    useNavigate: () => mockNavigate,
    useParams: () => ({}),
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
    user: { id: 1, first_name: 'Admin', name: 'Admin User', role: 'admin' },
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

import { CreateChallengePage } from './CreateChallengePage';

describe('CreateChallengePage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
  });

  it('renders the create challenge form for admins', async () => {
    render(<CreateChallengePage />);
    await waitFor(() => {
      expect(screen.getByText('create_page.page_title')).toBeInTheDocument();
    });
  });

  it('renders title and description inputs', async () => {
    render(<CreateChallengePage />);
    await waitFor(() => {
      expect(screen.getByText('create_page.field_title')).toBeInTheDocument();
    });
    expect(screen.getByText('create_page.field_description')).toBeInTheDocument();
  });

  it('redirects non-admin users away from the page', async () => {
    const { useAuth } = await import('@/contexts');
    vi.mocked(useAuth).mockReturnValue({
      user: { id: 2, first_name: 'Member', name: 'Regular Member', role: 'member' },
      isAuthenticated: true,
    } as ReturnType<typeof useAuth>);

    render(<CreateChallengePage />);
    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('/test/ideation', { replace: true });
    });
  });

  it('shows template picker button', async () => {
    render(<CreateChallengePage />);
    await waitFor(() => {
      expect(screen.getByText('create_page.use_template')).toBeInTheDocument();
    });
  });

  it('shows validation error when submitting empty title', async () => {
    render(<CreateChallengePage />);
    await waitFor(() => {
      expect(screen.getByText('create_page.page_title')).toBeInTheDocument();
    });
    // Submit without filling required fields
    const submitBtn = screen.getByText('create_page.save_button');
    submitBtn.click();
    await waitFor(() => {
      expect(screen.getByText('create_page.title_required')).toBeInTheDocument();
    });
  });

  it('calls categories API on mount', async () => {
    render(<CreateChallengePage />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/ideation-categories');
    });
  });

  it('shows edit page title when editing an existing challenge', async () => {
    const { useParams } = await import('react-router-dom');
    vi.mocked(useParams).mockReturnValue({ id: '10' });

    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/v2/ideation-challenges/10')) {
        return Promise.resolve({
          success: true,
          data: {
            id: 10,
            title: 'Existing Challenge',
            description: 'Already created.',
            category: null,
            prize_description: null,
            submission_deadline: null,
            voting_deadline: null,
            max_ideas_per_user: null,
            status: 'draft',
            cover_image: null,
            tags: [],
          },
        });
      }
      return Promise.resolve({ success: true, data: [] });
    });

    render(<CreateChallengePage />);
    await waitFor(() => {
      expect(screen.getByText('edit_page.page_title')).toBeInTheDocument();
    });
  });
});
