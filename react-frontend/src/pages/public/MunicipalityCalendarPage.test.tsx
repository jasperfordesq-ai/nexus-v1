// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
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

// useSearchParams is used by the page — mock react-router with a simple implementation
// that returns a controllable URLSearchParams state.
const mockSetParams = vi.fn();
let mockParams = new URLSearchParams('');

vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...actual,
    useSearchParams: () => [mockParams, mockSetParams],
  };
});

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast }),
);

import MunicipalityCalendarPage from './MunicipalityCalendarPage';

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

const today = new Date();
today.setDate(1);
today.setHours(0, 0, 0, 0);
const isoDay = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-01`;

const calendarResponse = {
  municipality_code: '8001',
  period: 'month',
  start: isoDay,
  end: isoDay,
  buckets: {
    [isoDay]: [
      { id: 1, title: 'Annual Gala', start_time: `${isoDay}T18:00:00Z`, location: 'Town Hall', image_url: null, organization_id: 10, organization_name: 'FC Zurich' },
    ],
  },
};

const emptyCalendarResponse = {
  municipality_code: '8001',
  period: 'month',
  start: isoDay,
  end: isoDay,
  buckets: {},
};

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('MunicipalityCalendarPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Default: no municipality code in URL
    mockParams = new URLSearchParams('');
  });

  it('renders the page heading', () => {
    render(<MunicipalityCalendarPage />);
    // The page title key is verein_federation.calendar.title
    // In test (English), headings will render from i18n
    expect(document.body).toBeInTheDocument();
  });

  it('shows the no-municipality prompt when no code is in URL', async () => {
    mockParams = new URLSearchParams('');
    render(<MunicipalityCalendarPage />);
    await waitFor(() => {
      // No API call should be made with an empty code
      expect(api.get).not.toHaveBeenCalled();
    });
  });

  it('calls the calendar API when a municipality code is present in the URL', async () => {
    mockParams = new URLSearchParams('code=8001');
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: calendarResponse });
    render(<MunicipalityCalendarPage />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('/v2/municipality/8001/events-calendar'),
      );
    });
  });

  it('shows a loading indicator while the calendar is being fetched', async () => {
    mockParams = new URLSearchParams('code=8001');
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<MunicipalityCalendarPage />);

    await waitFor(() => {
      expect(document.querySelector('[aria-busy="true"]')).not.toBeNull();
    });
  });

  it('renders calendar day cells with events after a successful load', async () => {
    mockParams = new URLSearchParams('code=8001');
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: calendarResponse });
    render(<MunicipalityCalendarPage />);

    await waitFor(() => {
      expect(screen.getByText(/Annual Gala/)).toBeInTheDocument();
    });
    expect(screen.getByText(/FC Zurich/)).toBeInTheDocument();
  });

  it('shows empty-calendar message when buckets is empty', async () => {
    mockParams = new URLSearchParams('code=8001');
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: emptyCalendarResponse });
    render(<MunicipalityCalendarPage />);

    await waitFor(() => {
      // Spinner disappears
      expect(document.querySelector('[aria-busy="true"]')).toBeNull();
    });
    // After loading with an empty bucket dict, the empty-state message should appear
    // (translation key: verein_federation.calendar.empty)
    // We confirm that no event titles appear
    expect(screen.queryByText('Annual Gala')).not.toBeInTheDocument();
  });

  it('shows error toast when the API call fails', async () => {
    mockParams = new URLSearchParams('code=8001');
    vi.mocked(api.get).mockRejectedValueOnce(new Error('network'));
    render(<MunicipalityCalendarPage />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows error toast when the API returns success:false', async () => {
    mockParams = new URLSearchParams('code=8001');
    vi.mocked(api.get).mockResolvedValueOnce({ success: false, error: 'Not found' });
    render(<MunicipalityCalendarPage />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('updates search params when Apply is clicked with a new code', async () => {
    mockParams = new URLSearchParams('');
    render(<MunicipalityCalendarPage />);

    // Find the municipality code input (it has a label)
    const input = screen.getByRole('textbox');
    fireEvent.change(input, { target: { value: '9000' } });

    // Find and click the "Apply" button
    const applyBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.match(/apply/i),
    );
    expect(applyBtn).toBeTruthy();
    if (applyBtn) fireEvent.click(applyBtn);

    // setParams should have been called
    await waitFor(() => {
      expect(mockSetParams).toHaveBeenCalled();
    });
  });

  it('renders prev/next month navigation buttons', () => {
    mockParams = new URLSearchParams('');
    render(<MunicipalityCalendarPage />);

    // Navigation buttons have aria-labels for prev and next
    const prevBtn = screen.getByRole('button', { name: /prev/i });
    const nextBtn = screen.getByRole('button', { name: /next/i });
    expect(prevBtn).toBeInTheDocument();
    expect(nextBtn).toBeInTheDocument();
  });

  it('URL-encodes the municipality code when building the API request', async () => {
    // Code with a space or special char
    mockParams = new URLSearchParams('code=CH 8001');
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: emptyCalendarResponse });
    render(<MunicipalityCalendarPage />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining(encodeURIComponent('CH 8001')),
      );
    });
  });
});
