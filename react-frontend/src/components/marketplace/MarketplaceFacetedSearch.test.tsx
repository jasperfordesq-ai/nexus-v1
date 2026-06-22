// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    upload: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/contexts', () => createMockContexts());

import { MarketplaceFacetedSearch } from './MarketplaceFacetedSearch';
import type { MarketplaceFilters, MarketplaceCategory } from '@/types/marketplace';

const CATEGORIES: MarketplaceCategory[] = [
  { id: 1, name: 'Electronics', slug: 'electronics', listing_count: 10 },
  { id: 2, name: 'Clothing', slug: 'clothing', listing_count: 5 },
];

const EMPTY_FILTERS: MarketplaceFilters = {};

describe('MarketplaceFacetedSearch', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    const onChange = vi.fn();
    render(
      <MarketplaceFacetedSearch filters={EMPTY_FILTERS} onChange={onChange} categories={CATEGORIES} />,
    );
    // At minimum an Apply button should be present
    const applyButtons = screen.getAllByRole('button', { name: /apply/i });
    expect(applyButtons.length).toBeGreaterThan(0);
  });

  it('renders category options from props', () => {
    const onChange = vi.fn();
    render(
      <MarketplaceFacetedSearch filters={EMPTY_FILTERS} onChange={onChange} categories={CATEGORIES} />,
    );
    expect(screen.getAllByText('Electronics').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Clothing').length).toBeGreaterThan(0);
  });

  it('renders condition checkboxes', () => {
    const onChange = vi.fn();
    render(
      <MarketplaceFacetedSearch filters={EMPTY_FILTERS} onChange={onChange} categories={CATEGORIES} />,
    );
    // "new" condition should appear (translated key condition.new — falls back to key in test env)
    // Check at least one checkbox is in the document
    const checkboxes = screen.getAllByRole('checkbox');
    expect(checkboxes.length).toBeGreaterThan(0);
  });

  it('renders seller type radio buttons', () => {
    const onChange = vi.fn();
    render(
      <MarketplaceFacetedSearch filters={EMPTY_FILTERS} onChange={onChange} categories={CATEGORIES} />,
    );
    const radios = screen.getAllByRole('radio');
    expect(radios.length).toBeGreaterThan(0);
  });

  it('calls onChange when Apply is clicked', () => {
    const onChange = vi.fn();
    render(
      <MarketplaceFacetedSearch filters={EMPTY_FILTERS} onChange={onChange} categories={CATEGORIES} />,
    );
    const applyButtons = screen.getAllByRole('button', { name: /apply/i });
    fireEvent.click(applyButtons[0]);
    expect(onChange).toHaveBeenCalledTimes(1);
    expect(onChange).toHaveBeenCalledWith(expect.any(Object));
  });

  it('calls onChange with empty filters when Clear is clicked', () => {
    const onChange = vi.fn();
    render(
      <MarketplaceFacetedSearch
        filters={{ category_id: 1, sort: 'newest' }}
        onChange={onChange}
        categories={CATEGORIES}
      />,
    );
    const clearButtons = screen.getAllByRole('button', { name: /clear/i });
    fireEvent.click(clearButtons[0]);
    expect(onChange).toHaveBeenCalledWith({});
  });

  it('passes initial filter values to checkboxes', () => {
    const onChange = vi.fn();
    render(
      <MarketplaceFacetedSearch
        filters={{ condition: ['new'] }}
        onChange={onChange}
        categories={CATEGORIES}
      />,
    );
    // A "new" condition checkbox should exist and be checked
    const checkboxes = screen.getAllByRole('checkbox');
    const checked = checkboxes.filter((cb) => (cb as HTMLInputElement).checked);
    expect(checked.length).toBeGreaterThan(0);
  });

  it('calls onChange with updated filters after checking a condition', async () => {
    const onChange = vi.fn();
    render(
      <MarketplaceFacetedSearch filters={EMPTY_FILTERS} onChange={onChange} categories={CATEGORIES} />,
    );

    const checkboxes = screen.getAllByRole('checkbox');
    fireEvent.click(checkboxes[0]);

    const applyButtons = screen.getAllByRole('button', { name: /apply/i });
    fireEvent.click(applyButtons[0]);

    await waitFor(() => {
      expect(onChange).toHaveBeenCalledWith(expect.any(Object));
    });
  });
});
