// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for FeedPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';

const mockGet = vi.fn().mockResolvedValue({ success: true, data: [], meta: {} });
const mockPost = vi.fn().mockResolvedValue({ success: true });

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockGet(...args),
    post: (...args: unknown[]) => mockPost(...args),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test', avatar: null },
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
  resolveAssetUrl: vi.fn((url) => url || ''),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
}));

vi.mock('@/components/seo', () => ({
  PageMeta: () => null,
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className, ...props }: Record<string, unknown>) => (
    <div className={`glass-card ${className || ''}`} {...props}>{children as React.ReactNode}</div>
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

import { FeedPage } from './FeedPage';

describe('FeedPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGet.mockResolvedValue({ success: true, data: [], meta: {} });
    mockPost.mockResolvedValue({ success: true });
  });

  it('renders without crashing', () => {
    render(<FeedPage />);
    expect(screen.getByText('Community Feed')).toBeInTheDocument();
  });

  it('shows the page description', () => {
    render(<FeedPage />);
    expect(screen.getByText(/what's happening in your community/i)).toBeInTheDocument();
  });

  it('shows New Post button for authenticated users', () => {
    render(<FeedPage />);
    expect(screen.getByText('New Post')).toBeInTheDocument();
  });

  it('shows filter options', () => {
    render(<FeedPage />);
    expect(screen.getByText('All')).toBeInTheDocument();
    expect(screen.getByText('Posts')).toBeInTheDocument();
    expect(screen.getByText('Listings')).toBeInTheDocument();
    expect(screen.getByText('Events')).toBeInTheDocument();
    expect(screen.getByText('Polls')).toBeInTheDocument();
  });

  it('shows quick post box for authenticated users', () => {
    render(<FeedPage />);
    expect(screen.getByText(/What's on your mind/i)).toBeInTheDocument();
  });

  it('shows empty state when no items returned', async () => {
    mockGet.mockResolvedValue({ success: true, data: [], meta: {} });
    render(<FeedPage />);
    await waitFor(() => {
      expect(screen.getByText('No posts yet')).toBeInTheDocument();
    });
  });

  it('renders feed items when data is returned', async () => {
    mockGet.mockResolvedValue({
      success: true,
      data: [
        {
          id: 1,
          content: 'First post content',
          author_name: 'Alice',
          author_id: 10,
          created_at: '2026-02-21T12:00:00Z',
          type: 'post',
          likes_count: 2,
          comments_count: 0,
          is_liked: false,
        },
      ],
      meta: { has_more: false },
    });
    render(<FeedPage />);
    await waitFor(() => {
      expect(screen.getByText('First post content')).toBeInTheDocument();
    });
    expect(screen.getByText('Alice')).toBeInTheDocument();
  });

  it('shows Load More when has_more is true', async () => {
    mockGet.mockResolvedValue({
      success: true,
      data: [
        {
          id: 1,
          content: 'A post',
          author_name: 'User',
          author_id: 10,
          created_at: '2026-02-21T12:00:00Z',
          type: 'post',
          likes_count: 0,
          comments_count: 0,
          is_liked: false,
        },
      ],
      meta: { has_more: true, cursor: 'abc123' },
    });
    render(<FeedPage />);
    await waitFor(() => {
      expect(screen.getByText('Load More')).toBeInTheDocument();
    });
  });

  it('shows error state when API fails', async () => {
    mockGet.mockRejectedValue(new Error('Network error'));
    render(<FeedPage />);
    await waitFor(() => {
      expect(screen.getByText('Unable to Load Feed')).toBeInTheDocument();
    });
    expect(screen.getByText('Try Again')).toBeInTheDocument();
  });

  it('calls API with filter type when a filter is selected', async () => {
    const user = userEvent.setup();
    render(<FeedPage />);

    // Wait for initial load
    await waitFor(() => {
      expect(mockGet).toHaveBeenCalled();
    });

    mockGet.mockClear();

    // Click the "Events" filter
    const eventsBtn = screen.getByText('Events');
    await user.click(eventsBtn);

    await waitFor(() => {
      expect(mockGet).toHaveBeenCalledWith(
        expect.stringContaining('type=events')
      );
    });
  });

  it('calls loadFeed without type param for "all" filter', async () => {
    render(<FeedPage />);
    await waitFor(() => {
      expect(mockGet).toHaveBeenCalledWith(
        expect.stringContaining('per_page=20')
      );
    });
    // "all" filter should not include type=
    expect(mockGet).not.toHaveBeenCalledWith(
      expect.stringContaining('type=')
    );
  });

  it('shows Goals filter option', () => {
    render(<FeedPage />);
    expect(screen.getByText('Goals')).toBeInTheDocument();
  });

  it('shows loading skeletons while loading', async () => {
    // Use a controllable promise instead of never-resolving one (prevents Vitest hang on CI)
    let resolveApi: (value: unknown) => void;
    mockGet.mockReturnValue(new Promise((resolve) => { resolveApi = resolve; }));
    render(<FeedPage />);
    // Should show skeleton containers (GlassCard mocked as div.glass-card)
    const skeletonCards = document.querySelectorAll('.glass-card');
    // At least 3 skeleton cards + possible quick-post box
    expect(skeletonCards.length).toBeGreaterThanOrEqual(3);
    // Clean up: resolve the promise so Vitest can exit cleanly
    resolveApi!({ success: true, data: [], meta: {} });
  });
});
