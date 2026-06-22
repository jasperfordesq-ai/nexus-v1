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

import { api } from '@/lib/api';
import MyCollectionsPage from './MyCollectionsPage';

const makeCollection = (overrides = {}) => ({
  id: 1,
  name: 'Reading List',
  description: 'Books to read',
  color: '#3b82f6',
  icon: 'bookmark',
  items_count: 5,
  is_public: false,
  ...overrides,
});

describe('MyCollectionsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading state initially', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<MyCollectionsPage />);
    // LoadingScreen; the collections grid is not yet visible
    expect(screen.queryByText('Reading List')).not.toBeInTheDocument();
  });

  it('renders list of collections', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [
        makeCollection({ id: 1, name: 'Reading List' }),
        makeCollection({ id: 2, name: 'Favourite Events', items_count: 3 }),
      ],
    });

    render(<MyCollectionsPage />);

    await waitFor(() => {
      expect(screen.getByText('Reading List')).toBeInTheDocument();
      expect(screen.getByText('Favourite Events')).toBeInTheDocument();
    });

    // Item counts
    expect(screen.getByText('5')).toBeInTheDocument();
    expect(screen.getByText('3')).toBeInTheDocument();
  });

  it('shows empty state when no collections exist', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });

    render(<MyCollectionsPage />);

    await waitFor(() => {
      // No collection cards
      expect(screen.queryByText('Reading List')).not.toBeInTheDocument();
    });

    // "New collection" button should still be present
    const newBtn = screen.getByRole('button');
    expect(newBtn).toBeInTheDocument();
  });

  it('renders empty state on API failure (success:false)', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: false, error: 'Server error' });

    render(<MyCollectionsPage />);

    await waitFor(() => {
      expect(screen.queryByText('Reading List')).not.toBeInTheDocument();
    });
  });

  it('shows "Public" label for public collections', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [makeCollection({ id: 1, name: 'Open Collection', is_public: true })],
    });

    render(<MyCollectionsPage />);

    await waitFor(() => expect(screen.getByText('Open Collection')).toBeInTheDocument());

    // The public label translation key: 'collections.public_label'
    // i18n returns the key when no translation file loaded → just check it's present
    expect(document.body.textContent).toMatch(/public/i);
  });

  it('opens create-collection modal when "New" button is clicked', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });

    render(<MyCollectionsPage />);

    await waitFor(() => {
      // Wait for loading to finish — at minimum the New button must be rendered
      expect(screen.getByRole('button')).toBeInTheDocument();
    });

    const newBtn = screen.getByRole('button');
    fireEvent.click(newBtn);

    await waitFor(() => {
      // Modal header should be visible after open
      // The modal renders in a portal; query globally
      const modalHeading = document.querySelector('[role="dialog"]');
      expect(modalHeading).toBeTruthy();
    });
  });

  it('calls POST /v2/me/collections and appends new collection', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });
    vi.mocked(api.post).mockResolvedValueOnce({
      success: true,
      data: makeCollection({ id: 99, name: 'New One' }),
    });

    render(<MyCollectionsPage />);

    await waitFor(() => expect(screen.getByRole('button')).toBeInTheDocument());

    // Open modal
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });

    // Type a name in the name input (first input in modal)
    const inputs = document.querySelectorAll('input');
    // Find the name input (first in modal)
    if (inputs.length > 0) {
      fireEvent.change(inputs[0], { target: { value: 'New One' } });
    }

    // Click the Create button (last button in modal footer)
    const allButtons = screen.getAllByRole('button');
    const createBtn = allButtons[allButtons.length - 1];
    fireEvent.click(createBtn);

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/v2/me/collections',
        expect.objectContaining({ name: 'New One' }),
      );
    });

    expect(mockToast.success).toHaveBeenCalled();
  });

  it('shows error toast when create API call throws', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });
    vi.mocked(api.post).mockRejectedValueOnce(new Error('Network error'));

    render(<MyCollectionsPage />);

    await waitFor(() => expect(screen.getByRole('button')).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });

    const inputs = document.querySelectorAll('input');
    if (inputs.length > 0) {
      fireEvent.change(inputs[0], { target: { value: 'Fail Collection' } });
    }

    const allButtons = screen.getAllByRole('button');
    fireEvent.click(allButtons[allButtons.length - 1]);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('collection cards link to the correct detail URL', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [makeCollection({ id: 7, name: 'Travel Plans' })],
    });

    render(<MyCollectionsPage />);

    await waitFor(() => expect(screen.getByText('Travel Plans')).toBeInTheDocument());

    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', '/test/me/collections/7');
  });
});
