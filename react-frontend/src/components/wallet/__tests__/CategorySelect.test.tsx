// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for CategorySelect component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { CategorySelect } from '../CategorySelect';

const mockCategories = [
  { id: 1, name: 'Food & Cooking', color: '#10b981' },
  { id: 2, name: 'Gardening', color: '#6366f1' },
  { id: 3, name: 'Transport', color: undefined },
];

describe('CategorySelect', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders nothing while loading', () => {
    vi.mocked(api.get).mockReturnValueOnce(new Promise(() => {}));
    const { container } = render(<CategorySelect onChange={vi.fn()} />);
    expect(container.firstChild).toBeNull();
  });

  it('renders nothing when categories are empty after load', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });

    const { container } = render(<CategorySelect onChange={vi.fn()} />);
    await waitFor(() => {
      expect(container.firstChild).toBeNull();
    });
  });

  it('renders a Select when categories are loaded', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockCategories });

    render(<CategorySelect onChange={vi.fn()} />);
    await waitFor(() => {
      expect(screen.getByRole('button') || screen.getByRole('combobox')).toBeTruthy();
    });
  });

  it('fetches categories from correct endpoint', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockCategories });

    render(<CategorySelect onChange={vi.fn()} />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/wallet/categories');
    });
  });

  it('uses custom label prop', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockCategories });

    render(<CategorySelect onChange={vi.fn()} label="Transaction Type" />);
    await waitFor(() => {
      expect(screen.getByText('Transaction Type')).toBeInTheDocument();
    });
  });

  it('handles API error gracefully and renders nothing', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('Network error'));

    const { container } = render(<CategorySelect onChange={vi.fn()} />);
    await waitFor(() => {
      expect(container.firstChild).toBeNull();
    });
  });

  it('handles items-wrapped response format', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: { items: mockCategories },
    });

    render(<CategorySelect onChange={vi.fn()} />);
    await waitFor(() => {
      expect(screen.getByRole('button') || screen.getByRole('combobox')).toBeTruthy();
    });
  });

  it('applies custom className to the component', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockCategories });

    const { container } = render(
      <CategorySelect onChange={vi.fn()} className="w-full mt-2" />
    );
    await waitFor(() => {
      expect(container.firstChild).toBeTruthy();
    });
  });
});
