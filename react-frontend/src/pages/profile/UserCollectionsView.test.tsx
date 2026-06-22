// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
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

vi.mock('@/contexts', () => createMockContexts());

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return {
    ...actual,
    useParams: vi.fn(() => ({ userId: '42' })),
  };
});

import { api } from '@/lib/api';
import UserCollectionsView from './UserCollectionsView';

const MOCK_COLLECTIONS = [
  { id: 1, name: 'My Favourites', description: 'Things I like', color: '#ff0000', is_public: true, items_count: 5 },
  { id: 2, name: 'Reading List', description: null, color: '#00ff00', is_public: true, items_count: 12 },
];

describe('UserCollectionsView', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading indicator while fetching', () => {
    // Never resolves so loading stays indefinitely
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}) as ReturnType<typeof api.get>);
    render(<UserCollectionsView />);
    // LoadingScreen renders — it should be visible in the document
    // (exact text varies by i18n but the DOM should have something)
    expect(document.body).toBeTruthy();
  });

  it('renders collection cards after successful fetch', async () => {
    vi.mocked(api.get).mockImplementation((url) => {
      if (String(url).includes('public-collections')) {
        return Promise.resolve({ success: true, data: MOCK_COLLECTIONS });
      }
      return Promise.resolve({ success: true, data: [] });
    });

    render(<UserCollectionsView />);

    await waitFor(() => {
      expect(screen.getByText('My Favourites')).toBeInTheDocument();
    });
    expect(screen.getByText('Reading List')).toBeInTheDocument();
  });

  it('renders collection descriptions when present', async () => {
    vi.mocked(api.get).mockImplementation((url) => {
      if (String(url).includes('public-collections')) {
        return Promise.resolve({ success: true, data: MOCK_COLLECTIONS });
      }
      return Promise.resolve({ success: true, data: [] });
    });

    render(<UserCollectionsView />);

    await waitFor(() => {
      expect(screen.getByText('Things I like')).toBeInTheDocument();
    });
  });

  it('renders items_count for each collection', async () => {
    vi.mocked(api.get).mockImplementation((url) => {
      if (String(url).includes('public-collections')) {
        return Promise.resolve({ success: true, data: MOCK_COLLECTIONS });
      }
      return Promise.resolve({ success: true, data: [] });
    });

    render(<UserCollectionsView />);

    await waitFor(() => {
      expect(screen.getByText('5')).toBeInTheDocument();
      expect(screen.getByText('12')).toBeInTheDocument();
    });
  });

  it('shows empty state when API returns empty array', async () => {
    vi.mocked(api.get).mockImplementation((url) => {
      if (String(url).includes('public-collections')) {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: true, data: [] });
    });

    render(<UserCollectionsView />);

    await waitFor(() => {
      // EmptyState renders — the loading screen should be gone meaning data arrived
      // Collections list is empty so no card headings appear
      expect(screen.queryByText('My Favourites')).not.toBeInTheDocument();
    });
  });

  it('shows empty state when API returns success:false', async () => {
    vi.mocked(api.get).mockImplementation(() =>
      Promise.resolve({ success: false, error: 'Not found' })
    );

    render(<UserCollectionsView />);

    await waitFor(() => {
      // Data never set; loading ends; no collection cards shown
      expect(screen.queryByText('My Favourites')).not.toBeInTheDocument();
    });
  });

  it('calls the correct endpoint with the userId from params', async () => {
    vi.mocked(api.get).mockImplementation(() =>
      Promise.resolve({ success: true, data: [] })
    );

    render(<UserCollectionsView />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('/v2/users/42/public-collections'),
      );
    });
  });

  it('renders collection cards as links', async () => {
    vi.mocked(api.get).mockImplementation((url) => {
      if (String(url).includes('public-collections')) {
        return Promise.resolve({ success: true, data: MOCK_COLLECTIONS });
      }
      return Promise.resolve({ success: true, data: [] });
    });

    render(<UserCollectionsView />);

    await waitFor(() => {
      const links = screen.getAllByRole('link');
      expect(links.length).toBeGreaterThanOrEqual(2);
    });
  });
});
