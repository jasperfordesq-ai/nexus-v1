// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// Stable hoisted refs
const mockShowToast = vi.hoisted(() => vi.fn());
const mockHasFeature = vi.hoisted(() => vi.fn(() => true));
const mockRefetch = vi.hoisted(() => vi.fn());

const mockUseApiState = vi.hoisted(() => ({
  data: null as unknown,
  isLoading: false,
  error: null as string | null,
  refetch: mockRefetch,
  execute: vi.fn(),
  reset: vi.fn(),
  setData: vi.fn(),
  loading: false,
  meta: null,
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: mockHasFeature,
      hasModule: vi.fn(() => true),
    }),
    useToast: () => ({
      showToast: mockShowToast,
      success: vi.fn(),
      error: vi.fn(),
      info: vi.fn(),
      warning: vi.fn(),
    }),
  }),
);

vi.mock('@/contexts/ToastContext', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/contexts/ToastContext')>();
  return {
    ...actual,
    useToast: () => ({
      showToast: mockShowToast,
      success: vi.fn(),
      error: vi.fn(),
      info: vi.fn(),
      warning: vi.fn(),
    }),
  };
});

vi.mock('@/hooks/useApi', () => ({
  useApi: () => mockUseApiState,
}));

vi.mock('@/hooks', () => ({
  useApi: () => mockUseApiState,
  usePageTitle: vi.fn(),
}));

vi.mock('@/lib/api', () => {
  const m = {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  };
  return { default: m, api: m };
});

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/components/caring-community/SubRegionFilter', () => ({
  SubRegionFilter: ({ label }: { label: string }) => (
    <div data-testid="sub-region-filter">{label}</div>
  ),
}));

vi.mock('@/components/seo', () => ({ PageMeta: () => null }));

import { api } from '@/lib/api';
import { CivicDigestPage } from './CivicDigestPage';

const makeDigestItem = (overrides = {}) => ({
  id: 'item-1',
  source: 'announcement' as const,
  title: 'Community Announcement',
  summary: 'This is an important announcement',
  occurred_at: '2024-01-01T10:00:00.000Z',
  sub_region_id: null,
  audience_match_score: 15,
  link_path: '/announcements/1',
  score_reasons: [],
  ...overrides,
});

const makeResponse = (overrides = {}) => ({
  items: [],
  prefs: {
    enabled: true,
    cadence: 'monthly' as const,
    preferred_sub_region_id: null,
    opt_out_sources: [],
    updated_at: null,
  },
  tenant_default_cadence: 'weekly',
  ...overrides,
});

describe('CivicDigestPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
    mockUseApiState.data = null;
    mockUseApiState.isLoading = false;
    mockUseApiState.error = null;
    mockUseApiState.refetch = mockRefetch;
  });

  it('renders loading skeletons while fetching', () => {
    mockUseApiState.isLoading = true;
    render(<CivicDigestPage />);
    const statusEls = screen.queryAllByRole('status');
    const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('shows error card when the API returns an error', () => {
    mockUseApiState.error = 'Failed to load';
    render(<CivicDigestPage />);
    const alerts = screen.queryAllByRole('alert');
    expect(alerts.length).toBeGreaterThan(0);
  });

  it('shows empty state when items array is empty', () => {
    mockUseApiState.data = makeResponse({ items: [] });
    render(<CivicDigestPage />);
    // The real translation is "Nothing new in the last 30 days..."
    // The component also renders the preferences section — verify no items list
    expect(screen.queryAllByRole('listitem').length).toBe(0);
    // And the preferences panel is still present (save button)
    expect(screen.getByRole('button', { name: /save|prefs_save/i })).toBeInTheDocument();
  });

  it('renders digest item titles when data has items', () => {
    mockUseApiState.data = makeResponse({ items: [makeDigestItem()] });
    render(<CivicDigestPage />);
    expect(screen.getByText('Community Announcement')).toBeInTheDocument();
    expect(screen.getByText('This is an important announcement')).toBeInTheDocument();
  });

  it('renders match score chip when score > 0', () => {
    mockUseApiState.data = makeResponse({
      items: [makeDigestItem({ audience_match_score: 15 })],
    });
    render(<CivicDigestPage />);
    expect(screen.getByText(/15\/20/)).toBeInTheDocument();
  });

  it('does not render match score chip when score is 0', () => {
    mockUseApiState.data = makeResponse({
      items: [makeDigestItem({ audience_match_score: 0 })],
    });
    render(<CivicDigestPage />);
    expect(screen.queryByText(/\/20/)).not.toBeInTheDocument();
  });

  it('renders a link when item has link_path', () => {
    mockUseApiState.data = makeResponse({
      items: [makeDigestItem({ link_path: '/announcements/1' })],
    });
    render(<CivicDigestPage />);
    const links = screen.getAllByRole('link');
    expect(links.length).toBeGreaterThan(0);
  });

  it('calls api.put when Save preferences button is clicked', async () => {
    vi.mocked(api.put).mockResolvedValue({ data: { prefs: makeResponse().prefs } });
    mockUseApiState.data = makeResponse({ items: [] });

    const user = userEvent.setup();
    render(<CivicDigestPage />);

    const saveBtn = screen.getByRole('button', { name: /save|prefs_save/i });
    await user.click(saveBtn);

    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith(
        '/v2/caring-community/digest/prefs',
        expect.objectContaining({ cadence: expect.any(String) }),
      );
    });
  });

  it('shows success toast after saving preferences', async () => {
    vi.mocked(api.put).mockResolvedValue({ data: { prefs: makeResponse().prefs } });
    mockUseApiState.data = makeResponse({ items: [] });

    const user = userEvent.setup();
    render(<CivicDigestPage />);

    await user.click(screen.getByRole('button', { name: /save|prefs_save/i }));

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'success');
    });
  });

  it('shows error toast when saving preferences fails', async () => {
    vi.mocked(api.put).mockRejectedValue(new Error('Network error'));
    mockUseApiState.data = makeResponse({ items: [] });

    const user = userEvent.setup();
    render(<CivicDigestPage />);

    await user.click(screen.getByRole('button', { name: /save|prefs_save/i }));

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'error');
    });
  });

  it('renders multiple digest items from different sources', () => {
    mockUseApiState.data = makeResponse({
      items: [
        makeDigestItem({ id: '1', source: 'event', title: 'Community Event' }),
        makeDigestItem({ id: '2', source: 'safety_alert', title: 'Safety Alert' }),
      ],
    });
    render(<CivicDigestPage />);
    expect(screen.getByText('Community Event')).toBeInTheDocument();
    expect(screen.getByText('Safety Alert')).toBeInTheDocument();
  });

  it('renders the preferences section with sub-region filter', () => {
    mockUseApiState.data = makeResponse({ items: [] });
    render(<CivicDigestPage />);
    expect(screen.getByTestId('sub-region-filter')).toBeInTheDocument();
  });
});
