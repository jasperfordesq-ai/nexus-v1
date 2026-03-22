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

vi.mock('react-router-dom', async () => {
  const actual = await import('react-router-dom');
  const React = await import('react');
  return {
    ...actual,
    Link: ({ children, to, ...rest }: { children: ReactNode; to: string; [k: string]: unknown }) =>
      React.createElement('a', { href: String(to), ...rest }, children),
    useNavigate: () => vi.fn(),
    useParams: () => ({ id: '3' }),
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
  resolveAvatarUrl: (url: string | null) => url ?? '',
  resolveAssetUrl: (url: string | null) => url ?? '',
  formatRelativeTime: (d: string) => d,
}));

import { ChallengeDetailPage } from './ChallengeDetailPage';

const mockChallenge = {
  id: 3,
  tenant_id: 2,
  user_id: 5,
  title: 'Zero-Waste Living Challenge',
  description: 'Share your best zero-waste living tips with the community.',
  category: 'Environment',
  status: 'open' as const,
  ideas_count: 7,
  submission_deadline: '2026-12-01T00:00:00Z',
  voting_deadline: null,
  prize_description: '€200 reward',
  max_ideas_per_user: 2,
  created_at: '2026-01-10T10:00:00Z',
  user_idea_count: 0,
  tags: ['waste', 'sustainability'],
  cover_image: null,
  is_favorited: false,
  favorites_count: 5,
  views_count: 73,
  is_featured: false,
  campaign_id: null,
  campaign_name: null,
  creator: { id: 5, name: 'Admin User', avatar_url: null },
};

const mockIdeas = [
  {
    id: 201,
    challenge_id: 3,
    user_id: 10,
    title: 'Reusable Shopping Bags',
    description: 'Bring your own bags to every shop.',
    votes_count: 14,
    comments_count: 3,
    status: 'submitted' as const,
    has_voted: false,
    created_at: '2026-02-01T10:00:00Z',
    image_url: null,
    media: [],
    creator: { id: 10, name: 'Alice Member', avatar_url: null },
  },
];

const mockIdeasResponse = {
  success: true,
  data: mockIdeas,
  meta: { cursor: null, has_more: false },
};

function setupMocks() {
  vi.mocked(api.get).mockImplementation((url: string) => {
    if (url.includes('/ideas')) {
      return Promise.resolve(mockIdeasResponse);
    }
    if (url.includes('/drafts')) {
      return Promise.resolve({ success: true, data: [] });
    }
    return Promise.resolve({ success: true, data: mockChallenge });
  });
}

describe('ChallengeDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders challenge title and description on success', async () => {
    setupMocks();
    render(<ChallengeDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Zero-Waste Living Challenge')).toBeInTheDocument();
    });
    expect(screen.getByText('Share your best zero-waste living tips with the community.')).toBeInTheDocument();
  });

  it('renders submitted ideas on success', async () => {
    setupMocks();
    render(<ChallengeDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Reusable Shopping Bags')).toBeInTheDocument();
    });
  });

  it('shows error state when challenge fetch fails', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));
    render(<ChallengeDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('challenges.load_error')).toBeInTheDocument();
    });
  });

  it('shows Submit Idea button for open challenges when authenticated', async () => {
    setupMocks();
    render(<ChallengeDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Zero-Waste Living Challenge')).toBeInTheDocument();
    });
    expect(screen.getByText('ideas.submit')).toBeInTheDocument();
  });

  it('shows admin controls for admin users', async () => {
    const { useAuth } = await import('@/contexts');
    vi.mocked(useAuth).mockReturnValue({
      user: { id: 1, first_name: 'Admin', name: 'Admin User', role: 'admin' },
      isAuthenticated: true,
    } as ReturnType<typeof useAuth>);

    setupMocks();
    render(<ChallengeDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Zero-Waste Living Challenge')).toBeInTheDocument();
    });
    // Admin dropdown menu trigger should be present
    expect(screen.getByRole('button', { name: /Challenge actions/i })).toBeInTheDocument();
  });

  it('shows prize description when set', async () => {
    setupMocks();
    render(<ChallengeDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('€200 reward')).toBeInTheDocument();
    });
  });

  it('calls the challenge API with correct endpoint', async () => {
    setupMocks();
    render(<ChallengeDetailPage />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('/v2/ideation-challenges/3'),
      );
    });
  });

  it('shows empty ideas state when no ideas submitted', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/ideas')) {
        return Promise.resolve({ success: true, data: [], meta: { cursor: null, has_more: false } });
      }
      if (url.includes('/drafts')) {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: true, data: mockChallenge });
    });
    render(<ChallengeDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Zero-Waste Living Challenge')).toBeInTheDocument();
    });
    expect(screen.queryByText('Reusable Shopping Bags')).not.toBeInTheDocument();
  });
});
