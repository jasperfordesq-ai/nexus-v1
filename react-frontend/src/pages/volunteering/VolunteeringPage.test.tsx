// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for VolunteeringPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [], meta: {} }),
    post: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
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

import { VolunteeringPage } from './VolunteeringPage';
import { api } from '@/lib/api';

describe('VolunteeringPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the page heading and description', () => {
    render(<VolunteeringPage />);
    expect(screen.getByText('Volunteering')).toBeInTheDocument();
    expect(screen.getByText('Find opportunities and track your impact')).toBeInTheDocument();
  });

  it('shows Opportunities tab button', () => {
    render(<VolunteeringPage />);
    expect(screen.getByText('Opportunities')).toBeInTheDocument();
  });

  it('shows My Applications and My Hours tabs when authenticated', () => {
    render(<VolunteeringPage />);
    expect(screen.getByText('My Applications')).toBeInTheDocument();
    expect(screen.getByText('My Hours')).toBeInTheDocument();
  });

  it('shows Browse Organisations button', () => {
    render(<VolunteeringPage />);
    expect(screen.getByText('Browse Organisations')).toBeInTheDocument();
  });

  it('shows search input for opportunities', () => {
    render(<VolunteeringPage />);
    expect(screen.getByPlaceholderText('Search opportunities...')).toBeInTheDocument();
  });

  it('shows empty state when no opportunities exist', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [],
      meta: { cursor: null, has_more: false },
    });
    render(<VolunteeringPage />);
    await waitFor(() => {
      expect(screen.getByText('No opportunities found')).toBeInTheDocument();
    });
  });

  it('renders opportunity cards with Apply button', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [
        {
          id: 1,
          title: 'Community Garden Helper',
          description: 'Help maintain the community garden',
          location: 'Dublin',
          skills_needed: 'Gardening',
          start_date: '2026-03-01',
          end_date: '2026-06-30',
          is_active: true,
          is_remote: false,
          category: 'Environment',
          organization: { id: 1, name: 'Green Org', logo_url: null },
          created_at: '2026-02-01',
          has_applied: false,
        },
      ],
      meta: { cursor: null, has_more: false },
    });
    render(<VolunteeringPage />);
    await waitFor(() => {
      expect(screen.getByText('Community Garden Helper')).toBeInTheDocument();
    });
    expect(screen.getByText('Green Org')).toBeInTheDocument();
    expect(screen.getByText('Apply')).toBeInTheDocument();
  });

  it('shows Applied chip and hides Apply button when already applied', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [
        {
          id: 1,
          title: 'Already Applied Opportunity',
          description: 'Test',
          location: 'Cork',
          skills_needed: '',
          start_date: null,
          end_date: null,
          is_active: true,
          is_remote: false,
          category: null,
          organization: { id: 1, name: 'Test Org', logo_url: null },
          created_at: '2026-02-01',
          has_applied: true,
        },
      ],
      meta: { cursor: null, has_more: false },
    });
    render(<VolunteeringPage />);
    await waitFor(() => {
      expect(screen.getByText('Applied')).toBeInTheDocument();
    });
    expect(screen.queryByText('Apply')).not.toBeInTheDocument();
  });
});
