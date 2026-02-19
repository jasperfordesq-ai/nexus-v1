// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for SearchPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({
      success: true,
      data: { listings: [], users: [], events: [], groups: [] },
    }),
    post: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test' },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  })),
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
}));

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <div>{title}</div>
      {description && <div>{description}</div>}
    </div>
  ),
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const { variants, initial, animate, layout, ...rest } = props;
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

import { SearchPage } from './SearchPage';
import { api } from '@/lib/api';

describe('SearchPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the page heading and description', () => {
    render(<SearchPage />);
    expect(screen.getByText('Search')).toBeInTheDocument();
    expect(screen.getByText('Find listings, members, events, and groups')).toBeInTheDocument();
  });

  it('shows search input', () => {
    render(<SearchPage />);
    expect(screen.getByPlaceholderText('Search for anything...')).toBeInTheDocument();
  });

  it('shows initial state prompt before searching', () => {
    render(<SearchPage />);
    expect(screen.getByText('Start searching')).toBeInTheDocument();
    expect(
      screen.getByText('Enter a search term to find listings, members, events, and groups')
    ).toBeInTheDocument();
  });

  it('does not show result tabs before a search is performed', () => {
    render(<SearchPage />);
    expect(screen.queryByText(/All \(\d+\)/)).not.toBeInTheDocument();
    expect(screen.queryByText(/Listings \(\d+\)/)).not.toBeInTheDocument();
  });

  it('shows no results state when search returns empty', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { listings: [], users: [], events: [], groups: [] },
    });

    render(<SearchPage />);

    // Simulate search by finding and submitting the form
    const input = screen.getByPlaceholderText('Search for anything...');
    const form = input.closest('form')!;

    // Update input value
    await import('@testing-library/react').then(({ fireEvent }) => {
      fireEvent.change(input, { target: { value: 'nonexistent' } });
      fireEvent.submit(form);
    });

    await waitFor(() => {
      expect(screen.getByText('No results found')).toBeInTheDocument();
    });
  });

  it('shows result tabs with counts after search', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: {
        listings: [
          { id: 1, title: 'Test Listing', description: 'A listing', type: 'offer', hours_estimate: 2 },
        ],
        users: [
          { id: 1, name: 'Alice Smith', avatar: null, tagline: 'Hello', location: 'Dublin' },
        ],
        events: [],
        groups: [],
      },
    });

    render(<SearchPage />);

    const input = screen.getByPlaceholderText('Search for anything...');
    const form = input.closest('form')!;

    await import('@testing-library/react').then(({ fireEvent }) => {
      fireEvent.change(input, { target: { value: 'test' } });
      fireEvent.submit(form);
    });

    await waitFor(() => {
      expect(screen.getByText('All (2)')).toBeInTheDocument();
    });
    // "Listings (1)" appears in both the tab and the section heading, use getAllByText
    expect(screen.getAllByText('Listings (1)').length).toBeGreaterThanOrEqual(1);
    // "Members (1)" also appears in both tab and section heading
    expect(screen.getAllByText('Members (1)').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('Events (0)')).toBeInTheDocument();
    expect(screen.getByText('Groups (0)')).toBeInTheDocument();
  });

  it('renders search results with listing and user details', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: {
        listings: [
          { id: 1, title: 'Garden Help', description: 'Need help in garden', type: 'request', hours_estimate: 3 },
        ],
        users: [
          { id: 2, name: 'Bob Jones', avatar: null, tagline: 'Gardener', location: 'Cork' },
        ],
        events: [],
        groups: [],
      },
    });

    render(<SearchPage />);

    const input = screen.getByPlaceholderText('Search for anything...');
    const form = input.closest('form')!;

    await import('@testing-library/react').then(({ fireEvent }) => {
      fireEvent.change(input, { target: { value: 'garden' } });
      fireEvent.submit(form);
    });

    await waitFor(() => {
      expect(screen.getByText('Garden Help')).toBeInTheDocument();
    });
    expect(screen.getByText('Need help in garden')).toBeInTheDocument();
    expect(screen.getByText('Bob Jones')).toBeInTheDocument();
    expect(screen.getByText('Gardener')).toBeInTheDocument();
  });

  it('shows error state when search API fails', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));

    render(<SearchPage />);

    const input = screen.getByPlaceholderText('Search for anything...');
    const form = input.closest('form')!;

    await import('@testing-library/react').then(({ fireEvent }) => {
      fireEvent.change(input, { target: { value: 'test' } });
      fireEvent.submit(form);
    });

    await waitFor(() => {
      expect(screen.getByText('Search Error')).toBeInTheDocument();
    });
    expect(screen.getByText('Try Again')).toBeInTheDocument();
  });
});
