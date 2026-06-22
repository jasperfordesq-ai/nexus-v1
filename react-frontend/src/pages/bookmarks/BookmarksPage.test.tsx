// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn(), put: vi.fn() },
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/helpers')>();
  return {
    ...actual,
    formatRelativeTime: (_s: string) => 'just now',
  };
});

// ─── Contexts / hooks ─────────────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));

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

vi.mock('@/hooks/usePageTitle', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));
vi.mock('@/components/seo', () => ({ PageMeta: () => null }));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const makeBookmark = (overrides = {}) => ({
  id: 1,
  bookmarkable_type: 'listing',
  bookmarkable_id: 10,
  collection_id: null,
  created_at: '2025-01-01T10:00:00Z',
  title: 'Test Listing Bookmark',
  ...overrides,
});

const makeCollection = (overrides = {}) => ({
  id: 5,
  name: 'My Favourites',
  description: null,
  is_default: false,
  bookmarks_count: 3,
  ...overrides,
});

const makeBookmarkResponse = (data: object[] = [], meta = {}) => ({
  success: true,
  data,
  meta: { total: data.length, has_more: false, ...meta },
});

const makeCollectionsResponse = (data: object[] = []) => ({
  success: true,
  data,
});

// ─────────────────────────────────────────────────────────────────────────────

describe('BookmarksPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    // Default: empty bookmarks + empty collections
    mockApi.get.mockImplementation((url: string) => {
      if (url.startsWith('/v2/bookmark-collections')) {
        return Promise.resolve(makeCollectionsResponse());
      }
      return Promise.resolve(makeBookmarkResponse());
    });
  });

  it('shows a loading spinner initially', async () => {
    mockApi.get.mockImplementation(() => new Promise(() => {})); // never resolves
    const { default: BookmarksPage } = await import('./BookmarksPage');
    render(<BookmarksPage />);

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders empty state when no bookmarks are returned', async () => {
    const { default: BookmarksPage } = await import('./BookmarksPage');
    render(<BookmarksPage />);

    await waitFor(() => {
      // The empty state shows an h3 with the empty title (translation key renders key in test)
      expect(screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });
  });

  it('renders bookmark cards when bookmarks are returned', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.startsWith('/v2/bookmark-collections')) {
        return Promise.resolve(makeCollectionsResponse());
      }
      return Promise.resolve(makeBookmarkResponse([makeBookmark()]));
    });

    const { default: BookmarksPage } = await import('./BookmarksPage');
    render(<BookmarksPage />);

    await waitFor(() => {
      expect(screen.getByText('Test Listing Bookmark')).toBeInTheDocument();
    });
  });

  it('renders a bookmark with fallback title when title is null', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.startsWith('/v2/bookmark-collections')) {
        return Promise.resolve(makeCollectionsResponse());
      }
      return Promise.resolve(
        makeBookmarkResponse([makeBookmark({ title: null, bookmarkable_type: 'event', bookmarkable_id: 99 })])
      );
    });

    const { default: BookmarksPage } = await import('./BookmarksPage');
    render(<BookmarksPage />);

    await waitFor(() => {
      // fallback = "<typeLabel> #<id>"
      expect(screen.getByText(/99/)).toBeInTheDocument();
    });
  });

  it('renders the "New collection" button', async () => {
    const { default: BookmarksPage } = await import('./BookmarksPage');
    render(<BookmarksPage />);

    await waitFor(() => {
      const btn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('collection')
      );
      expect(btn).toBeInTheDocument();
    });
  });

  it('opens create collection modal when New Collection is clicked', async () => {
    const { default: BookmarksPage } = await import('./BookmarksPage');
    render(<BookmarksPage />);

    await waitFor(() => screen.getAllByRole('button'));

    const btn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('collection')
    );
    if (btn) fireEvent.click(btn);

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });
  });

  it('calls POST /v2/bookmark-collections when collection form is saved', async () => {
    mockApi.post.mockResolvedValue({ success: true });

    const { default: BookmarksPage } = await import('./BookmarksPage');
    render(<BookmarksPage />);

    await waitFor(() => screen.getAllByRole('button'));

    // Open create modal
    const newBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('collection')
    );
    if (newBtn) fireEvent.click(newBtn);

    await waitFor(() => document.querySelector('[role="dialog"]'));

    // Fill in the name input
    const inputs = document.querySelectorAll('input');
    const nameInput = Array.from(inputs).find((el) => el.getAttribute('maxlength') === '100');
    if (nameInput) fireEvent.change(nameInput, { target: { value: 'My Test Collection' } });

    // Click save/create button inside modal
    const modalBtns = Array.from(document.querySelectorAll('[role="dialog"] button'));
    const createBtn = modalBtns.find((b) =>
      b.textContent?.toLowerCase().includes('create') || b.textContent?.toLowerCase().includes('save')
    );
    if (createBtn) fireEvent.click(createBtn);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/bookmark-collections',
        expect.objectContaining({ name: 'My Test Collection' })
      );
    });
  });

  it('calls POST /v2/bookmarks to remove a bookmark', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.startsWith('/v2/bookmark-collections')) {
        return Promise.resolve(makeCollectionsResponse());
      }
      return Promise.resolve(makeBookmarkResponse([makeBookmark()]));
    });
    mockApi.post.mockResolvedValue({ success: true });

    const { default: BookmarksPage } = await import('./BookmarksPage');
    render(<BookmarksPage />);

    await waitFor(() => screen.getByText('Test Listing Bookmark'));

    // Find the remove (trash) icon button
    const removeBtns = screen.getAllByRole('button').filter((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('remove') ||
      b.getAttribute('aria-label')?.toLowerCase().includes('bookmark')
    );
    if (removeBtns.length > 0) fireEvent.click(removeBtns[0]);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/bookmarks',
        expect.objectContaining({ type: 'listing', id: 10 })
      );
    });
  });

  it('shows load more button when has_more is true', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.startsWith('/v2/bookmark-collections')) {
        return Promise.resolve(makeCollectionsResponse());
      }
      return Promise.resolve(makeBookmarkResponse([makeBookmark()], { has_more: true }));
    });

    const { default: BookmarksPage } = await import('./BookmarksPage');
    render(<BookmarksPage />);

    await waitFor(() => {
      const btn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('more') || b.textContent?.toLowerCase().includes('load')
      );
      expect(btn).toBeInTheDocument();
    });
  });

  it('shows error toast when remove bookmark fails', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.startsWith('/v2/bookmark-collections')) {
        return Promise.resolve(makeCollectionsResponse());
      }
      return Promise.resolve(makeBookmarkResponse([makeBookmark()]));
    });
    mockApi.post.mockRejectedValue(new Error('network error'));

    const { default: BookmarksPage } = await import('./BookmarksPage');
    render(<BookmarksPage />);

    await waitFor(() => screen.getByText('Test Listing Bookmark'));

    const removeBtns = screen.getAllByRole('button').filter((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('remove') ||
      b.getAttribute('aria-label')?.toLowerCase().includes('bookmark')
    );
    if (removeBtns.length > 0) fireEvent.click(removeBtns[0]);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows collections autocomplete when collections are loaded', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.startsWith('/v2/bookmark-collections')) {
        return Promise.resolve(makeCollectionsResponse([makeCollection()]));
      }
      return Promise.resolve(makeBookmarkResponse([makeBookmark()]));
    });

    const { default: BookmarksPage } = await import('./BookmarksPage');
    render(<BookmarksPage />);

    await waitFor(() => {
      // Collection name should appear in the autocomplete area
      expect(screen.getByText(/My Favourites/)).toBeInTheDocument();
    });
  });
});
