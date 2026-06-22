// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { api } from '@/lib/api';

// ---------------------------------------------------------------------------
// Module mocks
// ---------------------------------------------------------------------------

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// Stable mock values — defined once, never recreated per call
const mockNavigate = vi.fn();
const mockTenantValue = {
  tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
  tenantPath: (p: string) => `/test${p}`,
  hasFeature: vi.fn(() => true),
  hasModule: vi.fn(() => true),
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => mockTenantValue,
  }),
);

vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

import { SuccessStoriesPage } from './SuccessStoriesPage';

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

const story1 = {
  id: 'story-1',
  title: 'Time Bank Reduces Isolation',
  narrative: 'After joining the time bank, 45 members reported reduced loneliness.',
  metric_source: 'municipal_roi' as const,
  metric_key: 'isolation_score',
  before_value: 80,
  after_value: 42,
  unit: 'points',
  audience: 'Elderly residents',
  sub_region_id: null,
  method_caveat: 'Self-reported survey, n=45.',
  evidence_source: 'Community Research 2025',
  is_demo: false,
  is_published: true,
  created_at: '2025-01-01T00:00:00Z',
  updated_at: '2025-01-01T00:00:00Z',
};

const demoStory = {
  ...story1,
  id: 'story-demo',
  title: 'Demo Story',
  is_demo: true,
};

function makeListResponse(items: typeof story1[]) {
  return { success: true, data: { items } };
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('SuccessStoriesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Default: caring_community feature is on
    mockTenantValue.hasFeature.mockReturnValue(true);
  });

  it('redirects to home when the caring_community feature is disabled', async () => {
    mockTenantValue.hasFeature.mockImplementation((f: string) => f !== 'caring_community');
    vi.mocked(api.get).mockResolvedValue(makeListResponse([]));
    render(<SuccessStoriesPage />);

    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('/test/', { replace: true });
    });
  });

  it('shows loading skeletons while data is being fetched', async () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<SuccessStoriesPage />);

    await waitFor(() => {
      // Loading state renders aria-busy wrapper
      expect(document.querySelector('[aria-busy="true"]')).not.toBeNull();
    });
  });

  it('renders story cards after successful load', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([story1]));
    render(<SuccessStoriesPage />);

    await waitFor(() => {
      expect(screen.getByText('Time Bank Reduces Isolation')).toBeInTheDocument();
    });
  });

  it('renders story narrative text', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([story1]));
    render(<SuccessStoriesPage />);

    await waitFor(() => {
      expect(screen.getByText(/45 members reported reduced loneliness/)).toBeInTheDocument();
    });
  });

  it('renders before/after metric values', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([story1]));
    render(<SuccessStoriesPage />);

    await waitFor(() => {
      // before_value 80 points and after_value 42 points
      expect(screen.getByText(/80/)).toBeInTheDocument();
      expect(screen.getByText(/42/)).toBeInTheDocument();
    });
  });

  it('renders the audience chip', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([story1]));
    render(<SuccessStoriesPage />);

    await waitFor(() => {
      expect(screen.getByText('Elderly residents')).toBeInTheDocument();
    });
  });

  it('renders the method caveat text', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([story1]));
    render(<SuccessStoriesPage />);

    await waitFor(() => {
      expect(screen.getByText(/Self-reported survey/)).toBeInTheDocument();
    });
  });

  it('renders the evidence source', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([story1]));
    render(<SuccessStoriesPage />);

    await waitFor(() => {
      expect(screen.getByText(/Community Research 2025/)).toBeInTheDocument();
    });
  });

  it('renders a demo chip for demo stories', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([demoStory]));
    render(<SuccessStoriesPage />);

    await waitFor(() => {
      expect(screen.getByText('Demo Story')).toBeInTheDocument();
    });
    // The is_demo flag renders a Chip component with the demo_label translation key.
    // Both the card title "Demo Story" and the chip text match /demo/i, so use
    // getAllByText and confirm at least 2 matches (title + chip).
    expect(screen.getAllByText(/demo/i).length).toBeGreaterThanOrEqual(2);
  });

  it('shows the error alert when the API returns an error response', async () => {
    // Resolve with success:false so useApi sets error state without throwing unhandled rejection
    vi.mocked(api.get).mockResolvedValueOnce({ success: false, error: 'Server error' });
    render(<SuccessStoriesPage />);

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
    });
  });

  it('shows empty state when no stories are returned', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([]));
    render(<SuccessStoriesPage />);

    await waitFor(() => {
      // Spinner / loading state should be gone
      expect(document.querySelector('[aria-busy="true"]')).toBeNull();
    });
    // No story titles should appear
    expect(screen.queryByText('Time Bank Reduces Isolation')).not.toBeInTheDocument();
  });

  it('renders multiple stories in a grid', async () => {
    const story2 = { ...story1, id: 'story-2', title: 'Green Spaces Initiative' };
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([story1, story2]));
    render(<SuccessStoriesPage />);

    await waitFor(() => {
      expect(screen.getByText('Time Bank Reduces Isolation')).toBeInTheDocument();
      expect(screen.getByText('Green Spaces Initiative')).toBeInTheDocument();
    });
  });

  it('formats null metric values as em-dash', async () => {
    const storyNullMetrics = { ...story1, before_value: null, after_value: null };
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([storyNullMetrics]));
    render(<SuccessStoriesPage />);

    await waitFor(() => {
      expect(screen.getByText('Time Bank Reduces Isolation')).toBeInTheDocument();
    });
    // Null values render as '—'
    const dashes = screen.getAllByText('—');
    expect(dashes.length).toBeGreaterThanOrEqual(2);
  });

  it('calls the correct API endpoint', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(makeListResponse([]));
    render(<SuccessStoriesPage />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/caring-community/success-stories');
    });
  });
});
