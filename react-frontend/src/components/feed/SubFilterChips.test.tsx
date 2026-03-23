// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for SubFilterChips component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
}));

import { SubFilterChips } from './SubFilterChips';

describe('SubFilterChips', () => {
  const mockOnSubFilterChange = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('returns null for unsupported filter types', () => {
    render(
      <SubFilterChips filter="events" subFilter={null} onSubFilterChange={mockOnSubFilterChange} />
    );
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('returns null for "all" filter type', () => {
    render(
      <SubFilterChips filter="all" subFilter={null} onSubFilterChange={mockOnSubFilterChange} />
    );
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('renders sub-filter chips for "listings" filter', () => {
    render(
      <SubFilterChips filter="listings" subFilter={null} onSubFilterChange={mockOnSubFilterChange} />
    );
    expect(screen.getByText('All')).toBeInTheDocument();
    expect(screen.getByText('Offers')).toBeInTheDocument();
    expect(screen.getByText('Requests')).toBeInTheDocument();
  });

  it('renders three buttons for listings filter', () => {
    render(
      <SubFilterChips filter="listings" subFilter={null} onSubFilterChange={mockOnSubFilterChange} />
    );
    const buttons = screen.getAllByRole('button');
    expect(buttons).toHaveLength(3);
  });

  it('calls onSubFilterChange with null when "All" is clicked', async () => {
    const user = userEvent.setup();
    render(
      <SubFilterChips filter="listings" subFilter="offer" onSubFilterChange={mockOnSubFilterChange} />
    );
    await user.click(screen.getByText('All'));
    expect(mockOnSubFilterChange).toHaveBeenCalledWith(null);
  });

  it('calls onSubFilterChange with "offer" when "Offers" is clicked', async () => {
    const user = userEvent.setup();
    render(
      <SubFilterChips filter="listings" subFilter={null} onSubFilterChange={mockOnSubFilterChange} />
    );
    await user.click(screen.getByText('Offers'));
    expect(mockOnSubFilterChange).toHaveBeenCalledWith('offer');
  });

  it('calls onSubFilterChange with "request" when "Requests" is clicked', async () => {
    const user = userEvent.setup();
    render(
      <SubFilterChips filter="listings" subFilter={null} onSubFilterChange={mockOnSubFilterChange} />
    );
    await user.click(screen.getByText('Requests'));
    expect(mockOnSubFilterChange).toHaveBeenCalledWith('request');
  });

  it('applies active styling to the selected sub-filter', () => {
    render(
      <SubFilterChips filter="listings" subFilter="offer" onSubFilterChange={mockOnSubFilterChange} />
    );
    // The "Offers" button should have the active class with the primary color
    const offersBtn = screen.getByText('Offers').closest('button')!;
    expect(offersBtn.className).toContain('var(--color-primary)');
  });

  it('applies inactive styling to non-selected sub-filters', () => {
    render(
      <SubFilterChips filter="listings" subFilter="offer" onSubFilterChange={mockOnSubFilterChange} />
    );
    const allBtn = screen.getByText('All').closest('button')!;
    expect(allBtn.className).toContain('text-[var(--text-muted)]');
  });
});
