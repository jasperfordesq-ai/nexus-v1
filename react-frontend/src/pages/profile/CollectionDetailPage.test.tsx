// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
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
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return {
    ...actual,
    useParams: () => ({ id: '42' }),
    useNavigate: () => vi.fn(),
  };
});

import { api } from '@/lib/api';
import CollectionDetailPage from './CollectionDetailPage';

const makeCollection = (overrides = {}) => ({
  id: 42,
  name: 'My Favourites',
  description: 'Things I like',
  color: '#ff5733',
  is_public: true,
  items_count: 2,
  ...overrides,
});

const makeItem = (overrides = {}): object => ({
  id: 1,
  item_type: 'listing',
  item_id: 100,
  note: null,
  saved_at: '2024-05-01T10:00:00Z',
  preview: { title: 'My Listing Title' },
  ...overrides,
});

const makePayload = (items: object[] = [], collectionOverrides = {}) => ({
  items,
  collection: makeCollection(collectionOverrides),
});

describe('CollectionDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading state initially (no data yet)', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<CollectionDetailPage />);
    // While loading and no data, LoadingScreen is rendered; content not yet visible
    expect(screen.queryByText('My Favourites')).not.toBeInTheDocument();
  });

  it('renders collection name and items when data loads', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: makePayload([makeItem()]),
    });

    render(<CollectionDetailPage />);

    await waitFor(() => {
      expect(screen.getByText('My Favourites')).toBeInTheDocument();
    });

    expect(screen.getByText('My Listing Title')).toBeInTheDocument();
  });

  it('shows item count from collection metadata', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: makePayload([makeItem()], { items_count: 7 }),
    });

    render(<CollectionDetailPage />);

    await waitFor(() => expect(screen.getByText('My Favourites')).toBeInTheDocument());

    // i18n resolves 'collections.items_count' → "{{n}} items" → "7 items"
    expect(screen.getByText('7 items')).toBeInTheDocument();
  });

  it('shows empty state when collection has no items', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: makePayload([]),
    });

    render(<CollectionDetailPage />);

    await waitFor(() => {
      expect(screen.getByText('My Favourites')).toBeInTheDocument();
    });

    // No item cards rendered
    expect(screen.queryByText('My Listing Title')).not.toBeInTheDocument();
  });

  it('renders nothing meaningful on API failure (success:false)', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: false, error: 'Unauthorized' });

    render(<CollectionDetailPage />);

    await waitFor(() => {
      // loading finishes but data stays null → no collection name visible
      expect(screen.queryByText('My Favourites')).not.toBeInTheDocument();
    });
  });

  it('shows item note when present', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: makePayload([makeItem({ note: 'Check this out later' })]),
    });

    render(<CollectionDetailPage />);

    await waitFor(() => expect(screen.getByText('My Listing Title')).toBeInTheDocument());

    expect(screen.getByText('Check this out later')).toBeInTheDocument();
  });

  it('falls back to generic title when preview has no title', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: makePayload([makeItem({ preview: null, item_type: 'event', item_id: 55 })]),
    });

    render(<CollectionDetailPage />);

    await waitFor(() => expect(screen.getByText('My Favourites')).toBeInTheDocument());

    // Generic fallback: "event #55"
    expect(screen.getByText('event #55')).toBeInTheDocument();
  });

  it('calls DELETE and removes item from list when remove button is clicked', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: makePayload([
        makeItem({ id: 10, preview: { title: 'Remove Me' } }),
        makeItem({ id: 11, preview: { title: 'Keep Me' } }),
      ]),
    });
    vi.mocked(api.delete).mockResolvedValueOnce({ success: true });

    render(<CollectionDetailPage />);

    await waitFor(() => {
      expect(screen.getByText('Remove Me')).toBeInTheDocument();
      expect(screen.getByText('Keep Me')).toBeInTheDocument();
    });

    const removeButtons = screen.getAllByRole('button');
    // Each item has one remove button; click the first
    fireEvent.click(removeButtons[0]);

    await waitFor(() => {
      expect(api.delete).toHaveBeenCalledWith('/v2/me/saved-items/10');
    });

    await waitFor(() => {
      expect(screen.queryByText('Remove Me')).not.toBeInTheDocument();
      expect(screen.getByText('Keep Me')).toBeInTheDocument();
    });

    expect(mockToast.success).toHaveBeenCalled();
  });

  it('shows error toast when remove API call fails', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: makePayload([makeItem({ id: 20, preview: { title: 'Item A' } })]),
    });
    vi.mocked(api.delete).mockRejectedValueOnce(new Error('Network error'));

    render(<CollectionDetailPage />);

    await waitFor(() => expect(screen.getByText('Item A')).toBeInTheDocument());

    const removeButtons = screen.getAllByRole('button');
    fireEvent.click(removeButtons[0]);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('renders a back-to-collections link', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: makePayload([]),
    });

    render(<CollectionDetailPage />);

    await waitFor(() => expect(screen.getByText('My Favourites')).toBeInTheDocument());

    const backLink = screen.getByRole('link', { name: /back/i });
    expect(backLink).toBeInTheDocument();
    expect(backLink).toHaveAttribute('href', '/test/me/collections');
  });
});
