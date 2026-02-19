// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for OrganisationsPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [], meta: {} }),
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

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className, hoverable }: { children: React.ReactNode; className?: string; hoverable?: boolean }) => (
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

vi.mock('@/components/navigation', () => ({
  Breadcrumbs: ({ items }: { items: { label: string; href?: string }[] }) => (
    <nav data-testid="breadcrumbs">
      {items.map((item, i) => (
        <span key={i}>{item.label}</span>
      ))}
    </nav>
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

import { OrganisationsPage } from './OrganisationsPage';
import { api } from '@/lib/api';

describe('OrganisationsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the page heading and description', () => {
    render(<OrganisationsPage />);
    // "Organisations" appears in breadcrumbs and heading, use getAllByText
    expect(screen.getAllByText('Organisations').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('Discover volunteer organisations in your community')).toBeInTheDocument();
  });

  it('shows breadcrumbs', () => {
    render(<OrganisationsPage />);
    expect(screen.getByTestId('breadcrumbs')).toBeInTheDocument();
    expect(screen.getByText('Volunteering')).toBeInTheDocument();
  });

  it('shows search input', () => {
    render(<OrganisationsPage />);
    expect(screen.getByPlaceholderText('Search organisations...')).toBeInTheDocument();
  });

  it('shows empty state when no organisations exist', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [],
      meta: { cursor: null, has_more: false },
    });
    render(<OrganisationsPage />);
    await waitFor(() => {
      expect(screen.getByText('No organisations found')).toBeInTheDocument();
    });
  });

  it('renders organisation cards with details', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [
        {
          id: 1,
          name: 'Green Earth Foundation',
          description: 'Environmental conservation organisation',
          logo_url: null,
          website: 'https://greenearth.example.com',
          contact_email: 'contact@greenearth.example.com',
          location: 'Dublin, Ireland',
          opportunity_count: 5,
          total_hours: 120,
          volunteer_count: 15,
          average_rating: 4.5,
          created_at: '2026-01-01',
        },
        {
          id: 2,
          name: 'Community Care',
          description: 'Supporting local communities',
          logo_url: null,
          website: null,
          contact_email: null,
          location: 'Cork',
          opportunity_count: 0,
          total_hours: 0,
          volunteer_count: 0,
          average_rating: null,
          created_at: '2026-01-15',
        },
      ],
      meta: { cursor: null, has_more: false },
    });
    render(<OrganisationsPage />);
    await waitFor(() => {
      expect(screen.getByText('Green Earth Foundation')).toBeInTheDocument();
    });
    expect(screen.getByText('Community Care')).toBeInTheDocument();
    expect(screen.getByText('Environmental conservation organisation')).toBeInTheDocument();
    expect(screen.getByText('Dublin, Ireland')).toBeInTheDocument();
  });

  it('displays stats on organisation cards (opportunities, volunteers, hours)', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [
        {
          id: 1,
          name: 'Active Org',
          description: 'Has lots of stats',
          logo_url: null,
          website: 'https://example.com',
          contact_email: null,
          location: 'Limerick',
          opportunity_count: 3,
          total_hours: 50,
          volunteer_count: 8,
          average_rating: 4.2,
          created_at: '2026-01-01',
        },
      ],
      meta: { cursor: null, has_more: false },
    });
    render(<OrganisationsPage />);
    await waitFor(() => {
      expect(screen.getByText('3 opportunities')).toBeInTheDocument();
    });
    expect(screen.getByText('8 volunteers')).toBeInTheDocument();
    expect(screen.getByText('50h logged')).toBeInTheDocument();
    expect(screen.getByText('4.2')).toBeInTheDocument();
    expect(screen.getByText('Website')).toBeInTheDocument();
  });

  it('shows error state when API fails', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));
    render(<OrganisationsPage />);
    await waitFor(() => {
      expect(screen.getByText('Unable to Load Organisations')).toBeInTheDocument();
    });
    expect(screen.getByText('Try Again')).toBeInTheDocument();
  });
});
