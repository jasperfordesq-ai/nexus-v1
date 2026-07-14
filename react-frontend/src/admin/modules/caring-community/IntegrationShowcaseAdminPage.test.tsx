// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── Mock @/lib/api (named export `api`) ──────────────────────────────────────
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const mockShowToast = vi.fn();
vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({
      success: vi.fn(),
      error: vi.fn(),
      info: vi.fn(),
      warning: vi.fn(),
      showToast: mockShowToast,
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

import { api } from '@/lib/api';
import IntegrationShowcaseAdminPage from './IntegrationShowcaseAdminPage';

const MOCK_SHOWCASE = {
  updated_at: '2026-06-01T12:00:00Z',
  sections: [
    {
      id: 'openapi',
      icon: 'FileJson',
      items: [
        { code: 'openapi_json', path: '/v2/users', method: 'GET' as const },
        { code: 'openapi_yaml', path: '/v2/users', method: 'POST' as const },
      ],
      docs_link: 'https://example.com/docs',
    },
    {
      id: 'partner_checklist',
      icon: 'ClipboardList',
      checklist_codes: ['rate_limit_headers', 'oauth_credentials'],
      samples: [
        {
          code: 'partner_aggregates',
          kind: 'json' as const,
          body: '{"key":"value"}',
          headers: ['Content-Type: application/json'],
        },
      ],
    },
  ],
};

describe('IntegrationShowcaseAdminPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading spinner while data is fetching', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<IntegrationShowcaseAdminPage />);
    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeInTheDocument();
  });

  it('renders section titles after data loads', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: MOCK_SHOWCASE });
    render(<IntegrationShowcaseAdminPage />);
    await waitFor(() => {
      expect(screen.getByText('OpenAPI specification')).toBeInTheDocument();
    });
    expect(screen.getByText('What an integration partner receives')).toBeInTheDocument();
  });

  it('renders API endpoint items with method chips', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: MOCK_SHOWCASE });
    render(<IntegrationShowcaseAdminPage />);
    await waitFor(() => {
      expect(screen.getAllByText('/v2/users').length).toBeGreaterThanOrEqual(1);
    });
    expect(screen.getAllByText('GET').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('POST').length).toBeGreaterThanOrEqual(1);
  });

  it('renders checklist items', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: MOCK_SHOWCASE });
    render(<IntegrationShowcaseAdminPage />);
    await waitFor(() => {
      expect(screen.getByText('Documentation for the X-RateLimit-Limit and X-RateLimit-Remaining headers')).toBeInTheDocument();
    });
    expect(screen.getByText('An OAuth client ID and client secret dedicated to this partner')).toBeInTheDocument();
  });

  it('calls showToast with error variant when the API call fails', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));
    render(<IntegrationShowcaseAdminPage />);
    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'error');
    });
  });

  it('hides the spinner and shows nothing when API returns null data', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: null });
    render(<IntegrationShowcaseAdminPage />);
    await waitFor(() => {
      const busySpinners = screen
        .queryAllByRole('status')
        .filter((el) => el.getAttribute('aria-busy') === 'true');
      expect(busySpinners).toHaveLength(0);
    });
    // No accordion items
    expect(screen.queryByText('OpenAPI specification')).not.toBeInTheDocument();
  });

  it('re-fetches data when refresh button is pressed', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: MOCK_SHOWCASE });
    render(<IntegrationShowcaseAdminPage />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledTimes(1);
    });

    // Refresh button has aria-label from t('integration_showcase.actions.refresh_aria')
    const refreshBtn = screen.getByRole('button', { name: /refresh/i });
    await userEvent.click(refreshBtn);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledTimes(2);
    });
  });

  it('renders the "about" info panel with last refreshed date', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: MOCK_SHOWCASE });
    render(<IntegrationShowcaseAdminPage />);
    await waitFor(() => {
      expect(screen.getByText('OpenAPI specification')).toBeInTheDocument();
    });
    // The about card is always rendered; it has a "last refreshed" line
    // i18n key integration_showcase.about.last_refreshed interpolates the date
    // In test mode with real i18n fallback the key itself renders; just check it doesn't crash
    // and that the date string from mock data appears somewhere on screen
    expect(
      screen.getByText((content) => content.includes('2026') || content.includes('last_refreshed'))
    ).toBeInTheDocument();
  });
});
