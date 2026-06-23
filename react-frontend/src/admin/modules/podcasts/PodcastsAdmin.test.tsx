// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ─── Hoist mock data ──────────────────────────────────────────────────────────
const { mockApi, mockPodcastsApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
  mockPodcastsApi: {
    validateFeed: vi.fn(),
    resolveReport: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

vi.mock('@/lib/api/podcasts', () => ({
  podcastsApi: mockPodcastsApi,
}));

// ─── Admin components ─────────────────────────────────────────────────────────
vi.mock('../../components', () => ({
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div data-testid="page-header">
      <h1>{title}</h1>
      {actions}
    </div>
  ),
}));

// ─── Contexts ─────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeStats = (overrides = {}) => ({
  total_shows: 3,
  published_shows: 2,
  pending_shows: 1,
  total_episodes: 10,
  published_episodes: 8,
  pending_episodes: 2,
  total_listens: 500,
  completed_listens: 300,
  completion_rate: 60,
  unique_listeners: 120,
  open_reports: 0,
  subscribers: 45,
  pending_media_scans: 0,
  media_scan_unavailable: 0,
  pending_media_processing: 0,
  ...overrides,
});

const makeShow = (overrides = {}) => ({
  id: 1,
  owner_user_id: 5,
  title: 'Tech Talks',
  slug: 'tech-talks',
  summary: 'A tech podcast',
  language: 'en',
  visibility: 'public' as const,
  status: 'published' as const,
  moderation_status: 'approved' as const,
  episode_count: 5,
  subscriber_count: 10,
  updated_at: '2024-06-01T00:00:00Z',
  owner: { id: 5, name: 'Bob' },
  ...overrides,
});

const makeEpisode = (overrides = {}) => ({
  id: 10,
  title: 'Episode 1',
  slug: 'episode-1',
  summary: 'First episode',
  status: 'published' as const,
  moderation_status: 'approved' as const,
  listen_count: 100,
  show: { title: 'Tech Talks' },
  author: { name: 'Bob' },
  ...overrides,
});

const makeReport = (overrides = {}) => ({
  id: 99,
  episode_id: 10,
  reporter_user_id: 7,
  episode_title: 'Episode 1',
  show_title: 'Tech Talks',
  reporter_name: 'Charlie',
  reason: 'spam',
  details: 'This is spam content',
  status: 'open',
  created_at: '2024-06-01T00:00:00Z',
  ...overrides,
});

const makeAdminData = (overrides = {}) => ({
  shows: [makeShow()],
  episodes: [makeEpisode()],
  stats: makeStats(),
  top_episodes: [makeEpisode()],
  reports: [],
  client_breakdown: [{ client: 'Chrome', listens: 200 }],
  retention: [{ bucket: '0-25%', listens: 50 }],
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('PodcastsAdmin', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue({ success: true, data: makeAdminData() });
  });

  it('shows a loading spinner initially', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const { default: PodcastsAdmin } = await import('./PodcastsAdmin');
    render(<PodcastsAdmin />);

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders stats cards after loading', async () => {
    const { default: PodcastsAdmin } = await import('./PodcastsAdmin');
    render(<PodcastsAdmin />);

    await waitFor(() => {
      // total_shows = 3 — appears at least once (stat card + possibly elsewhere)
      const threes = screen.getAllByText('3');
      expect(threes.length).toBeGreaterThan(0);
    });
  });

  it('renders show title in the shows table', async () => {
    const { default: PodcastsAdmin } = await import('./PodcastsAdmin');
    render(<PodcastsAdmin />);

    await waitFor(() => {
      // Tech Talks appears in shows table and top_episodes table
      const titles = screen.getAllByText('Tech Talks');
      expect(titles.length).toBeGreaterThan(0);
    });
  });

  it('renders episode title in the episodes table', async () => {
    const { default: PodcastsAdmin } = await import('./PodcastsAdmin');
    render(<PodcastsAdmin />);

    await waitFor(() => {
      // Episode 1 appears in episodes table and top_episodes table
      const titles = screen.getAllByText('Episode 1');
      expect(titles.length).toBeGreaterThan(0);
    });
  });

  it('shows empty message when no shows', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: makeAdminData({ shows: [] }) });
    const { default: PodcastsAdmin } = await import('./PodcastsAdmin');
    render(<PodcastsAdmin />);

    await waitFor(() => {
      // t('podcasts_admin.empty.shows') key fallback
      const empties = screen.getAllByText(/podcasts_admin\.empty\.shows|no shows/i);
      expect(empties.length).toBeGreaterThan(0);
    });
  });

  it('shows empty message when no episodes', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: makeAdminData({ episodes: [] }) });
    const { default: PodcastsAdmin } = await import('./PodcastsAdmin');
    render(<PodcastsAdmin />);

    await waitFor(() => {
      const empties = screen.getAllByText(/podcasts_admin\.empty\.episodes|no episodes/i);
      expect(empties.length).toBeGreaterThan(0);
    });
  });

  it('shows report row when report data is present', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: makeAdminData({ reports: [makeReport()] }),
    });
    const { default: PodcastsAdmin } = await import('./PodcastsAdmin');
    render(<PodcastsAdmin />);

    await waitFor(() => {
      // reporter name is inside a nested element — use getAllByText with substring matcher
      const charlies = screen.getAllByText(/charlie/i);
      expect(charlies.length).toBeGreaterThan(0);
    });
  });

  it('calls moderate endpoint when approve button is clicked on a show', async () => {
    mockApi.post.mockResolvedValue({ success: true });
    const { default: PodcastsAdmin } = await import('./PodcastsAdmin');
    render(<PodcastsAdmin />);

    // Wait until data loads — Tech Talks appears in both shows + top_episodes
    await waitFor(() => {
      const titles = screen.getAllByText('Tech Talks');
      expect(titles.length).toBeGreaterThan(0);
    });

    const approveBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('approve')
    );
    expect(approveBtn).toBeDefined();
    fireEvent.click(approveBtn!);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/admin/podcasts/shows/1/moderate',
        { action: 'approve' }
      );
    });
  });

  it('calls moderate endpoint when reject button is clicked on an episode', async () => {
    mockApi.post.mockResolvedValue({ success: true });
    const { default: PodcastsAdmin } = await import('./PodcastsAdmin');
    render(<PodcastsAdmin />);

    await waitFor(() => {
      const titles = screen.getAllByText('Episode 1');
      expect(titles.length).toBeGreaterThan(0);
    });

    const rejectBtns = screen.getAllByRole('button').filter((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('reject')
    );
    // There are reject buttons for the show and episode rows
    expect(rejectBtns.length).toBeGreaterThan(0);
    fireEvent.click(rejectBtns[rejectBtns.length - 1]!);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/admin/podcasts/episodes/10/moderate',
        { action: 'reject' }
      );
    });
  });

  it('calls podcastsApi.validateFeed when validate feed button is clicked', async () => {
    mockPodcastsApi.validateFeed.mockResolvedValue({
      success: true,
      data: { valid: true, errors: [], warnings: [] },
    });
    const { default: PodcastsAdmin } = await import('./PodcastsAdmin');
    render(<PodcastsAdmin />);

    await waitFor(() => {
      const titles = screen.getAllByText('Tech Talks');
      expect(titles.length).toBeGreaterThan(0);
    });

    const validateBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('validate') ||
      b.getAttribute('aria-label')?.toLowerCase().includes('feed')
    );
    expect(validateBtn).toBeDefined();
    fireEvent.click(validateBtn!);

    await waitFor(() => {
      expect(mockPodcastsApi.validateFeed).toHaveBeenCalledWith(1);
    });
  });

  it('shows feed validation result card after valid feed check', async () => {
    mockPodcastsApi.validateFeed.mockResolvedValue({
      success: true,
      data: { valid: true, errors: [], warnings: [] },
    });
    const { default: PodcastsAdmin } = await import('./PodcastsAdmin');
    render(<PodcastsAdmin />);

    await waitFor(() => {
      const titles = screen.getAllByText('Tech Talks');
      expect(titles.length).toBeGreaterThan(0);
    });

    const validateBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('validate') ||
      b.getAttribute('aria-label')?.toLowerCase().includes('feed')
    );
    fireEvent.click(validateBtn!);

    await waitFor(() => {
      // Feed validation card should appear — t() returns the key as value in tests
      const validText = screen.getAllByText(
        /podcasts_admin\.feed_validation\.(valid|title)|valid/i
      );
      expect(validText.length).toBeGreaterThan(0);
    });
  });

  it('shows error toast when API load fails', async () => {
    mockApi.get.mockResolvedValue({ success: false });
    const { default: PodcastsAdmin } = await import('./PodcastsAdmin');
    render(<PodcastsAdmin />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('calls resolveReport when resolve button is clicked on a report', async () => {
    mockPodcastsApi.resolveReport.mockResolvedValue({ success: true });
    mockApi.get.mockResolvedValue({
      success: true,
      data: makeAdminData({ reports: [makeReport()] }),
    });

    const { default: PodcastsAdmin } = await import('./PodcastsAdmin');
    render(<PodcastsAdmin />);

    await waitFor(() => {
      const charlies = screen.getAllByText(/charlie/i);
      expect(charlies.length).toBeGreaterThan(0);
    });

    const resolveBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('resolve')
    );
    expect(resolveBtn).toBeDefined();
    fireEvent.click(resolveBtn!);

    await waitFor(() => {
      expect(mockPodcastsApi.resolveReport).toHaveBeenCalledWith(10, 'resolved');
    });
  });
});
