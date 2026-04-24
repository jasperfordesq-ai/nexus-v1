// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GroupsPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@/test/test-utils';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [], meta: {} }),
    post: vi.fn().mockResolvedValue({ success: true }),
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
    branding: { name: 'Test Tenant' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
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
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  resolveAssetUrl: vi.fn((url) => url || ''),
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
}));
vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title }: { title: string }) => <div data-testid="empty-state">{title}</div>,
}));
vi.mock('framer-motion', () => {  const motionProps = new Set(['variants', 'initial', 'animate', 'layout', 'transition', 'exit', 'whileHover', 'whileTap', 'whileInView', 'viewport']);  const filterMotion = (props: Record<string, unknown>) => {    const filtered: Record<string, unknown> = {};    for (const [k, v] of Object.entries(props)) {      if (!motionProps.has(k)) filtered[k] = v;    }    return filtered;  };  return {    motion: {      div: ({ children, ...props }: Record<string, unknown>) => <div {...filterMotion(props)}>{children}</div>,    },    AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,  };});

import { GroupsPage } from './GroupsPage';

describe('GroupsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [], meta: {} });
  });

  it('renders without crashing', () => {
    render(<GroupsPage />);
    expect(screen.getByText('Groups')).toBeInTheDocument();
  });

  it('shows Create Group button for authenticated users', () => {
    render(<GroupsPage />);
    expect(screen.getByText('Create Group')).toBeInTheDocument();
  });

  it('shows search input', () => {
    render(<GroupsPage />);
    expect(screen.getByPlaceholderText(/Search groups/i)).toBeInTheDocument();
  });

  it('loads public groups when the public filter is selected', async () => {
    render(<GroupsPage />);

    fireEvent.click(screen.getByRole('button', { name: /Public/i }));

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('visibility=public'),
        expect.objectContaining({ signal: expect.any(AbortSignal) })
      );
    });
    expect(screen.getByText('Showing')).toBeInTheDocument();
    expect(screen.getAllByText('Public').length).toBeGreaterThan(0);
  });

  it('renders polished group cards with imagery and accessible stats', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [
        {
          id: 42,
          name: 'Garden Crew',
          description: 'A group for growing food together.',
          image_url: '/uploads/groups/garden.jpg',
          member_count: 12,
          members_count: 12,
          posts_count: 5,
          visibility: 'public',
          is_featured: true,
          tags: [{ id: 1, name: 'Outdoors' }],
          recent_members: [],
          created_at: '2026-01-01T00:00:00Z',
        },
      ],
      meta: { has_more: false, total_items: 1 },
    });

    const { container } = render(<GroupsPage />);

    expect(await screen.findByText('Garden Crew')).toBeInTheDocument();
    expect(container.querySelector('img')).toHaveAttribute('src', '/uploads/groups/garden.jpg');
    expect(screen.getByLabelText('12 members')).toBeInTheDocument();
    expect(screen.getByLabelText('5 posts')).toBeInTheDocument();
    expect(screen.getByText('Featured')).toBeInTheDocument();
  });
});
