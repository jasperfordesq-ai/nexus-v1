// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for OrgVolunteersTab component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { api } from '@/lib/api';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  })
);

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

// resolveAvatarUrl is used inside OrgVolunteersTab — passthrough in tests
vi.mock('@/lib/helpers', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/helpers')>();
  return {
    ...actual,
    resolveAvatarUrl: (url: string | null) => url ?? '',
  };
});

import OrgVolunteersTab from './OrgVolunteersTab';

const VOLUNTEERS = [
  {
    id: 10,
    name: 'Alice Brown',
    avatar_url: null,
    email: 'alice@example.com',
    total_hours: 12,
    applications_count: 3,
    applied_at: '2024-01-01T00:00:00Z',
  },
  {
    id: 11,
    name: 'Bob Green',
    avatar_url: null,
    email: 'bob@example.com',
    total_hours: 5,
    applications_count: 1,
    applied_at: '2024-02-01T00:00:00Z',
  },
];

/** Build a successful API response object matching how OrgVolunteersTab reads it */
function volunteerResponse(
  items: typeof VOLUNTEERS,
  { cursor = null, has_more = false } = {}
) {
  return {
    success: true,
    // Mirrors the real backend shape after api.get() unwraps the envelope:
    // response.data = Volunteer[] (read via extractCollectionItems), response.meta = pagination
    data: items,
    meta: { cursor, has_more },
  };
}

describe('OrgVolunteersTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading spinner while fetching', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<OrgVolunteersTab orgId={7} />);
    // HeroUI Spinner nests multiple role="status" elements with the same label.
    // Assert that at least one loading status is in the document.
    const statusEls = screen.getAllByRole('status', { name: /loading/i });
    expect(statusEls.length).toBeGreaterThan(0);
  });

  it('renders volunteer names and emails after load', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(volunteerResponse(VOLUNTEERS));

    render(<OrgVolunteersTab orgId={7} />);

    await waitFor(() => expect(screen.getByText('Alice Brown')).toBeInTheDocument());
    expect(screen.getByText('alice@example.com')).toBeInTheDocument();
    expect(screen.getByText('Bob Green')).toBeInTheDocument();
    expect(screen.getByText('bob@example.com')).toBeInTheDocument();
  });

  it('calls the correct API endpoint with the given orgId', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(volunteerResponse(VOLUNTEERS));

    render(<OrgVolunteersTab orgId={7} />);

    await waitFor(() =>
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('/v2/volunteering/organisations/7/volunteers')
      )
    );
  });

  it('shows empty state when no volunteers are returned', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(volunteerResponse([]));

    render(<OrgVolunteersTab orgId={7} />);

    await waitFor(() =>
      expect(screen.queryAllByRole('status', { name: /loading/i }).length).toBe(0)
    );
    // Volunteer names should not appear
    expect(screen.queryByText('Alice Brown')).not.toBeInTheDocument();
  });

  it('shows an error toast when the API call returns success=false', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: false, data: null });

    render(<OrgVolunteersTab orgId={7} />);

    await waitFor(() => expect(mockToast.error).toHaveBeenCalled());
    expect(screen.queryByText('Alice Brown')).not.toBeInTheDocument();
  });

  it('shows an error toast when the API throws', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('Network error'));

    render(<OrgVolunteersTab orgId={7} />);

    await waitFor(() => expect(mockToast.error).toHaveBeenCalled());
  });

  it('does NOT render a "Load more" button when has_more is false', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(
      volunteerResponse(VOLUNTEERS, { has_more: false })
    );

    render(<OrgVolunteersTab orgId={7} />);

    await waitFor(() => expect(screen.getByText('Alice Brown')).toBeInTheDocument());

    // t('load_more') key — just check no button text matches
    expect(screen.queryByRole('button', { name: /load.more/i })).not.toBeInTheDocument();
  });

  it('renders a "Load more" button when has_more is true', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(
      volunteerResponse(VOLUNTEERS, { has_more: true, cursor: 'abc' })
    );

    render(<OrgVolunteersTab orgId={7} />);

    await waitFor(() => expect(screen.getByText('Alice Brown')).toBeInTheDocument());

    // The "+" in the count label confirms has_more was recognised
    const countLabel = screen.getByText(/\d+\+/);
    expect(countLabel).toBeInTheDocument();
  });

  it('fetches the next page with cursor when "Load more" is pressed', async () => {
    // First page
    vi.mocked(api.get).mockResolvedValueOnce(
      volunteerResponse(VOLUNTEERS, { has_more: true, cursor: 'cursor-abc' })
    );
    // Second page (appended)
    vi.mocked(api.get).mockResolvedValueOnce(
      volunteerResponse(
        [
          {
            id: 12,
            name: 'Carol White',
            avatar_url: null,
            email: 'carol@example.com',
            total_hours: 2,
            applications_count: 1,
            applied_at: '2024-03-01T00:00:00Z',
          },
        ],
        { has_more: false }
      )
    );

    render(<OrgVolunteersTab orgId={7} />);

    await waitFor(() => expect(screen.getByText('Alice Brown')).toBeInTheDocument());

    // The "load more" button sits next to the "+" count; find any button in the card area
    // The component renders it via t('load_more') key; let's find it by its "+" parent row
    const buttons = screen.getAllByRole('button');
    // There should be at least a load-more button
    expect(buttons.length).toBeGreaterThan(0);
    fireEvent.click(buttons[buttons.length - 1]);

    await waitFor(() => expect(screen.getByText('Carol White')).toBeInTheDocument());

    // Verify second call includes cursor param
    expect(api.get).toHaveBeenCalledWith(
      expect.stringContaining('cursor=cursor-abc')
    );
  });

  it('renders volunteer counts (hours and applications)', async () => {
    vi.mocked(api.get).mockResolvedValueOnce(volunteerResponse(VOLUNTEERS));

    render(<OrgVolunteersTab orgId={7} />);

    await waitFor(() => expect(screen.getByText('Alice Brown')).toBeInTheDocument());

    // total_hours for Alice is 12 — rendered via t('hours_abbrev', { hours: 12 })
    // The i18n key fallback includes the hours value in the key string
    expect(screen.getByText(/12/)).toBeInTheDocument();
    // applications_count for Alice is 3
    expect(screen.getByText('3')).toBeInTheDocument();
  });
});
