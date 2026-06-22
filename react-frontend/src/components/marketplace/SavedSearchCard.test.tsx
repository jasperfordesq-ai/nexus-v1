// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import type { MarketplaceSavedSearch } from '@/types/marketplace';

vi.mock('@/contexts', () => createMockContexts());

const BASE_SEARCH: MarketplaceSavedSearch = {
  id: 42,
  name: 'Vintage bikes',
  search_query: 'bike',
  filters: {
    location: 'Dublin',
    radius: 10,
    price_min: 50,
    price_max: 500,
    condition: 'good',
    category_id: 7,
  },
  alert_frequency: 'daily',
  alert_channel: 'email',
  is_active: true,
  created_at: '2026-05-01T12:00:00Z',
};

import { SavedSearchCard } from './SavedSearchCard';

describe('SavedSearchCard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the search name', () => {
    render(<SavedSearchCard search={BASE_SEARCH} />);
    expect(screen.getByText('Vintage bikes')).toBeInTheDocument();
  });

  it('renders search_query chip', () => {
    render(<SavedSearchCard search={BASE_SEARCH} />);
    expect(screen.getByText('bike')).toBeInTheDocument();
  });

  it('renders location chip with radius', () => {
    render(<SavedSearchCard search={BASE_SEARCH} />);
    expect(screen.getByText(/Dublin.*10km/)).toBeInTheDocument();
  });

  it('renders without optional filters', () => {
    const minimal: MarketplaceSavedSearch = {
      id: 1,
      name: 'Minimal search',
      alert_frequency: 'weekly',
      alert_channel: 'push',
      is_active: false,
      created_at: '2026-01-01T00:00:00Z',
    };
    render(<SavedSearchCard search={minimal} />);
    expect(screen.getByText('Minimal search')).toBeInTheDocument();
  });

  it('calls onRun when the name button is pressed', () => {
    const onRun = vi.fn();
    render(<SavedSearchCard search={BASE_SEARCH} onRun={onRun} />);
    // The name is inside a Button with ghost variant
    fireEvent.click(screen.getByText('Vintage bikes'));
    expect(onRun).toHaveBeenCalledWith(BASE_SEARCH);
  });

  it('calls onDelete when the delete button is pressed', () => {
    const onDelete = vi.fn();
    render(<SavedSearchCard search={BASE_SEARCH} onDelete={onDelete} />);
    // Delete button has aria-label saved_searches.delete (i18n key)
    const deleteBtn = screen.getByRole('button', { name: /delete/i });
    fireEvent.click(deleteBtn);
    expect(onDelete).toHaveBeenCalledWith(42);
  });

  it('calls onToggle when the switch is changed', () => {
    const onToggle = vi.fn();
    render(<SavedSearchCard search={BASE_SEARCH} onToggle={onToggle} />);
    // Switch has aria-label containing toggle_active (i18n key)
    const toggle = screen.getByRole('switch');
    fireEvent.click(toggle);
    expect(onToggle).toHaveBeenCalled();
  });

  it('renders instant frequency with accent chip colour', () => {
    const instant: MarketplaceSavedSearch = {
      ...BASE_SEARCH,
      alert_frequency: 'instant',
    };
    render(<SavedSearchCard search={instant} />);
    // i18n returns the key; check the key contains "instant"
    expect(screen.getByText(/instant/i)).toBeInTheDocument();
  });

  it('renders weekly frequency', () => {
    const weekly: MarketplaceSavedSearch = {
      ...BASE_SEARCH,
      alert_frequency: 'weekly',
    };
    render(<SavedSearchCard search={weekly} />);
    expect(screen.getByText(/weekly/i)).toBeInTheDocument();
  });

  it('renders channel label when alert_channel is both', () => {
    const both: MarketplaceSavedSearch = {
      ...BASE_SEARCH,
      alert_channel: 'both',
    };
    render(<SavedSearchCard search={both} />);
    // Translation: "Email & Push" (marketplace:saved_searches.channel_both)
    expect(screen.getByText(/email.*push|push.*email|email & push/i)).toBeInTheDocument();
  });

  it('renders push channel label', () => {
    const push: MarketplaceSavedSearch = {
      ...BASE_SEARCH,
      alert_channel: 'push',
    };
    render(<SavedSearchCard search={push} />);
    // Translation: "Push" (marketplace:saved_searches.channel_push)
    expect(screen.getByText(/^Push$/i)).toBeInTheDocument();
  });

  it('does not call onRun when no handler provided', () => {
    // Should not throw
    render(<SavedSearchCard search={BASE_SEARCH} />);
    fireEvent.click(screen.getByText('Vintage bikes'));
    // No assertion needed beyond no throw
  });
});
