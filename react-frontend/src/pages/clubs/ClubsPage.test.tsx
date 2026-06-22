// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/lib/api', () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/contexts', () => createMockContexts());

import { ClubsPage } from './ClubsPage';
import { api } from '@/lib/api';

const mockedGet = api.get as ReturnType<typeof vi.fn>;

const CLUBS = [
  {
    id: 1,
    name: 'Knitting Circle',
    description: 'Weekly knitting meetup',
    logo_url: null,
    contact_email: 'knit@example.com',
    website: 'https://knit.example.com',
    meeting_schedule: 'Tuesdays 7pm',
    member_count: 12,
    created_at: '2024-01-01T00:00:00Z',
  },
  {
    id: 2,
    name: 'Book Club',
    description: null,
    logo_url: null,
    contact_email: null,
    website: null,
    meeting_schedule: null,
    member_count: 0,
    created_at: '2024-02-01T00:00:00Z',
  },
];

/** The component's loading skeleton is the role=status element carrying aria-busy="true". */
function busyStatus() {
  return screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true');
}

describe('ClubsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading skeletons while clubs are fetching', () => {
    mockedGet.mockReturnValue(new Promise(() => {})); // never resolves
    render(<ClubsPage />);
    expect(busyStatus()).toBeInTheDocument();
  });

  it('renders club cards after a successful API response', async () => {
    mockedGet.mockResolvedValue({ success: true, data: CLUBS, meta: { total_pages: 1, current_page: 1, total: 2 } });
    render(<ClubsPage />);
    expect(await screen.findByText('Knitting Circle')).toBeInTheDocument();
    expect(screen.getByText('Book Club')).toBeInTheDocument();
  });

  it('shows the club description when present', async () => {
    mockedGet.mockResolvedValue({ success: true, data: CLUBS, meta: { total_pages: 1 } });
    render(<ClubsPage />);
    expect(await screen.findByText('Weekly knitting meetup')).toBeInTheDocument();
  });

  it('shows the meeting schedule when present', async () => {
    mockedGet.mockResolvedValue({ success: true, data: CLUBS, meta: { total_pages: 1 } });
    render(<ClubsPage />);
    expect(await screen.findByText(/Tuesdays 7pm/)).toBeInTheDocument();
  });

  it('renders a website link when the club has a website', async () => {
    mockedGet.mockResolvedValue({ success: true, data: CLUBS, meta: { total_pages: 1 } });
    render(<ClubsPage />);
    const link = await screen.findByRole('link', { name: /knit\.example\.com/i });
    expect(link).toHaveAttribute('href', 'https://knit.example.com');
  });

  it('shows the empty state when no clubs are returned', async () => {
    mockedGet.mockResolvedValue({ success: true, data: [], meta: { total_pages: 0 } });
    render(<ClubsPage />);
    await waitFor(() => expect(busyStatus()).toBeUndefined());
    expect(screen.queryByText('Knitting Circle')).not.toBeInTheDocument();
  });

  it('shows an error alert when the API call fails', async () => {
    mockedGet.mockResolvedValue({ success: false, error: 'Server error' });
    render(<ClubsPage />);
    expect(await screen.findByRole('alert')).toBeInTheDocument();
  });

  it('does not show the club grid when there is an error', async () => {
    mockedGet.mockResolvedValue({ success: false, error: 'Server error' });
    render(<ClubsPage />);
    await screen.findByRole('alert');
    expect(screen.queryByText('Knitting Circle')).not.toBeInTheDocument();
  });

  it('debounces the search query and calls the API with the search param', async () => {
    mockedGet.mockResolvedValue({ success: true, data: [], meta: { total_pages: 0 } });
    render(<ClubsPage />);
    await waitFor(() => expect(mockedGet).toHaveBeenCalledTimes(1));

    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'Knit' } });
    // Debounce (300ms) not elapsed yet → still one call
    expect(mockedGet).toHaveBeenCalledTimes(1);

    await waitFor(() => expect(mockedGet).toHaveBeenCalledTimes(2));
    expect(mockedGet.mock.calls[1][0] as string).toContain('search=Knit');
  });

  it('shows a "load more" button when there are multiple pages', async () => {
    mockedGet.mockResolvedValue({ success: true, data: CLUBS, meta: { total_pages: 3, current_page: 1, total: 60 } });
    render(<ClubsPage />);
    await screen.findByText('Knitting Circle');
    expect(screen.getByRole('button', { name: /view|more/i })).toBeInTheDocument();
  });

  it('requests page 2 when "load more" is clicked', async () => {
    mockedGet.mockResolvedValue({ success: true, data: CLUBS, meta: { total_pages: 2, current_page: 1, total: 4 } });
    render(<ClubsPage />);
    await screen.findByText('Knitting Circle');

    // The next request (load more) returns page 2.
    mockedGet.mockResolvedValueOnce({
      success: true,
      data: [{ ...CLUBS[0], id: 99, name: 'Third Club' }],
      meta: { total_pages: 2, current_page: 2, total: 4 },
    });
    fireEvent.click(screen.getByRole('button', { name: /view|more/i }));
    await waitFor(() => expect(mockedGet.mock.calls.some((c) => String(c[0]).includes('page=2'))).toBe(true));
  });

  it('appends new clubs to the existing list on load more', async () => {
    mockedGet.mockResolvedValue({ success: true, data: CLUBS, meta: { total_pages: 2, current_page: 1, total: 3 } });
    render(<ClubsPage />);
    await screen.findByText('Knitting Circle');

    mockedGet.mockResolvedValueOnce({
      success: true,
      data: [{ id: 99, name: 'Third Club', description: null, logo_url: null, contact_email: null, website: null, meeting_schedule: null, member_count: 5, created_at: '2024-03-01' }],
      meta: { total_pages: 2, current_page: 2, total: 3 },
    });
    fireEvent.click(screen.getByRole('button', { name: /view|more/i }));
    expect(await screen.findByText('Third Club')).toBeInTheDocument();
    expect(screen.getByText('Knitting Circle')).toBeInTheDocument();
  });

  it('calls GET /v2/clubs on mount', async () => {
    mockedGet.mockResolvedValue({ success: true, data: [], meta: { total_pages: 0 } });
    render(<ClubsPage />);
    await waitFor(() => expect(mockedGet).toHaveBeenCalledWith(expect.stringContaining('/v2/clubs')));
  });
});
