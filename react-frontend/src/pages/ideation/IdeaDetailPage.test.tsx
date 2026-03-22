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
    useParams: () => ({ challengeId: '3', id: '201' }),
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
    user: { id: 10, first_name: 'Alice', name: 'Alice Member', role: 'member' },
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
  formatRelativeTime: (d: string) => d,
}));

import { IdeaDetailPage } from './IdeaDetailPage';

const mockIdea = {
  id: 201,
  challenge_id: 3,
  user_id: 10,
  title: 'Reusable Shopping Bags',
  description: 'Bring reusable bags to every grocery trip to reduce plastic waste.',
  votes_count: 14,
  comments_count: 1,
  status: 'submitted' as const,
  has_voted: false,
  created_at: '2026-02-01T10:00:00Z',
  creator: { id: 10, name: 'Alice Member', avatar_url: null },
};

const mockComments = [
  {
    id: 501,
    idea_id: 201,
    user_id: 20,
    body: 'Great idea! I already do this.',
    created_at: '2026-02-05T08:00:00Z',
    author: { id: 20, name: 'Bob Commenter', avatar_url: null },
  },
];

const mockCommentsResponse = {
  success: true,
  data: { items: mockComments, cursor: null, has_more: false },
};

function setupMocks() {
  vi.mocked(api.get).mockImplementation((url: string) => {
    if (url.includes('/comments')) {
      return Promise.resolve(mockCommentsResponse);
    }
    return Promise.resolve({ success: true, data: mockIdea });
  });
}

describe('IdeaDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders idea title and description on success', async () => {
    setupMocks();
    render(<IdeaDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Reusable Shopping Bags')).toBeInTheDocument();
    });
    expect(screen.getByText('Bring reusable bags to every grocery trip to reduce plastic waste.')).toBeInTheDocument();
  });

  it('renders comments on success', async () => {
    setupMocks();
    render(<IdeaDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Great idea! I already do this.')).toBeInTheDocument();
    });
  });

  it('shows error state when API fails', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));
    render(<IdeaDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('ideas.load_error')).toBeInTheDocument();
    });
  });

  it('shows vote button with count', async () => {
    setupMocks();
    render(<IdeaDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Reusable Shopping Bags')).toBeInTheDocument();
    });
    expect(screen.getByText('14')).toBeInTheDocument();
  });

  it('shows comment form for authenticated users', async () => {
    setupMocks();
    render(<IdeaDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Reusable Shopping Bags')).toBeInTheDocument();
    });
    expect(screen.getByText('comments.add_placeholder')).toBeInTheDocument();
  });

  it('shows creator name', async () => {
    setupMocks();
    render(<IdeaDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Alice Member')).toBeInTheDocument();
    });
  });

  it('shows admin controls for admin users', async () => {
    const { useAuth } = await import('@/contexts');
    vi.mocked(useAuth).mockReturnValue({
      user: { id: 1, first_name: 'Admin', name: 'Admin User', role: 'admin' },
      isAuthenticated: true,
    } as ReturnType<typeof useAuth>);

    setupMocks();
    render(<IdeaDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Reusable Shopping Bags')).toBeInTheDocument();
    });
    // Admin dropdown with status controls
    expect(screen.getByRole('button', { name: /idea_detail\.admin_actions/i })).toBeInTheDocument();
  });

  it('calls the correct API endpoints on mount', async () => {
    setupMocks();
    render(<IdeaDetailPage />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('/v2/ideation-ideas/201'),
      );
    });
    expect(api.get).toHaveBeenCalledWith(
      expect.stringContaining('/v2/ideation-ideas/201/comments'),
    );
  });
});
