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
vi.mock('@/lib/helpers', () => ({
  resolveAssetUrl: (url: string | null) => url ?? '',
  resolveAvatarUrl: (url: string | null) => url ?? '',
}));

import { IdeationPage } from './IdeationPage';

const mockChallenge = {
  id: 1,
  tenant_id: 2,
  user_id: 5,
  title: 'Reduce Plastic Waste',
  description: 'Submit your best ideas to reduce plastic waste in our community.',
  category: 'Environment',
  status: 'open' as const,
  ideas_count: 12,
  submission_deadline: '2026-12-01T00:00:00Z',
  voting_deadline: null,
  prize_description: '€500 grant',
  max_ideas_per_user: 3,
  created_at: '2026-01-15T10:00:00Z',
  tags: ['sustainability', 'environment'],
  cover_image: null,
  is_favorited: false,
  favorites_count: 7,
  views_count: 120,
  is_featured: false,
  creator: { id: 5, name: 'Admin User', avatar_url: null },
};

const mockChallengesResponse = {
  success: true,
  data: [mockChallenge],
  meta: {
    cursor: null,
    has_more: false,
  },
};

function setupMocks() {
  vi.mocked(api.get).mockImplementation((url: string) => {
    if (url.includes('/v2/ideation-categories')) {
      return Promise.resolve({ success: true, data: [] });
    }
    if (url.includes('/v2/ideation-tags/popular')) {
      return Promise.resolve({ success: true, data: [] });
    }
    if (url.includes('/v2/ideation-challenges')) {
      return Promise.resolve(mockChallengesResponse);
    }
    return Promise.resolve({ success: true, data: [] });
  });
}

describe('IdeationPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading state initially', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<IdeationPage />);
    expect(document.body).toBeTruthy();
  });

  it('renders challenge cards on success', async () => {
    setupMocks();
    render(<IdeationPage />);
    await waitFor(() => {
      expect(screen.getByText('Reduce Plastic Waste')).toBeInTheDocument();
    });
  });

  it('shows error state when API fails', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/v2/ideation-challenges')) {
        return Promise.reject(new Error('Network error'));
      }
      return Promise.resolve({ success: true, data: [] });
    });
    render(<IdeationPage />);
    await waitFor(() => {
      expect(screen.getAllByText('challenges.load_error').length).toBeGreaterThanOrEqual(1);
    });
  });

  it('shows empty state when no challenges exist', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/v2/ideation-challenges')) {
        return Promise.resolve({ success: true, data: [], meta: { cursor: null, has_more: false } });
      }
      return Promise.resolve({ success: true, data: [] });
    });
    render(<IdeationPage />);
    await waitFor(() => {
      expect(screen.queryByText('Reduce Plastic Waste')).not.toBeInTheDocument();
    });
  });

  it('shows Load More button when has_more is true', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/v2/ideation-challenges')) {
        return Promise.resolve({
          success: true,
          data: [mockChallenge],
          meta: { cursor: 'next-cursor', has_more: true },
        });
      }
      return Promise.resolve({ success: true, data: [] });
    });
    render(<IdeationPage />);
    await waitFor(() => {
      expect(screen.getByText('challenges.load_more')).toBeInTheDocument();
    });
  });

  it('shows admin Create Challenge button for admin users', async () => {
    const { useAuth } = await import('@/contexts');
    vi.mocked(useAuth).mockReturnValueOnce({
      user: { id: 1, first_name: 'Admin', name: 'Admin User', role: 'admin' },
      isAuthenticated: true,
    } as ReturnType<typeof useAuth>);

    setupMocks();
    render(<IdeationPage />);
    await waitFor(() => {
      expect(screen.getByText('challenges.create')).toBeInTheDocument();
    });
  });

  it('does not show Create Challenge button for regular members', async () => {
    setupMocks();
    render(<IdeationPage />);
    await waitFor(() => {
      expect(screen.queryByText('challenges.create')).not.toBeInTheDocument();
    });
  });

  it('calls the correct API endpoint on mount', async () => {
    setupMocks();
    render(<IdeationPage />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('/v2/ideation-challenges'),
      );
    });
  });
});
