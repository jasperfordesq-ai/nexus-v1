// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import type { ReactNode } from 'react';

const mockNavigate = vi.fn();
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) =>
      (opts?.defaultValue as string | undefined) ?? key,
  }),
}));

vi.mock('react-router-dom', async () => {
  const actual = await import('react-router-dom');
  const React = await import('react');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
    useParams: () => ({ id: '1' }),
    Link: ({ children, to, ...rest }: { children: ReactNode; to: string; [k: string]: unknown }) =>
      React.createElement('a', { href: String(to), ...rest }, children),
    useSearchParams: () => [new URLSearchParams(), vi.fn()],
  };
});

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: null, meta: {} }),
    post: vi.fn().mockResolvedValue({ success: true }),
    put: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

const mockHasFeature = vi.fn(() => true);
const mockUseAuth = vi.fn(() => ({
  user: { id: 1, first_name: 'Test', name: 'Test User' },
  isAuthenticated: true,
}));

vi.mock('@/contexts', () => ({
  useAuth: (...args: unknown[]) => mockUseAuth(...args),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test$${p}`,
    hasFeature: mockHasFeature,
    hasModule: vi.fn(() => true),
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  })),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: ReactNode; className?: string }) => (
    <div data-testid='glass-card' className={className}>{children}</div>
  ),
}));

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid='empty-state'>
      <div>{title}</div>
      {description && <div>{description}</div>}
    </div>
  ),
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, variants: _v, initial: _i, animate: _a, layout: _l, ...rest }: Record<string, unknown>) => (
      <div {...(rest as object)}>{children as ReactNode}</div>
    ),
  },
  AnimatePresence: ({ children }: { children: ReactNode }) => <>({children as ReactNode})</>,
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
}));

import { JobDetailPage } from './JobDetailPage';
import { api } from '@/lib/api';

function makeVacancy(overrides: Record<string, unknown> = {}) {
  return {
    id: 1, title: 'Community Garden Coordinator',
    description: 'Help coordinate the community garden.',
    location: 'Dublin', is_remote: false, type: 'paid',
    commitment: 'part_time', category: 'Environment',
    skills: ['Gardening', 'Communication'],
    skills_required: null, hours_per_week: 10, time_credits: null,
    deadline: null, status: 'open', views_count: 42, applications_count: 3,
    created_at: '2026-01-01T00:00:00Z',
    creator: { id: 1, name: 'Alice', avatar_url: null },
    organization: null, has_applied: false, application_status: null,
    application_stage: null, is_saved: false, is_featured: false,
    featured_until: null, salary_min: null, salary_max: null,
    salary_type: null, salary_currency: null, salary_negotiable: false,
    expired_at: null, renewed_at: null, renewal_count: 0, user_id: 1,
    contact_email: null, contact_phone: null,
    ...overrides,
  };
}

const baseVacancy = makeVacancy();

describe('JobDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
    mockUseAuth.mockReturnValue({
      user: { id: 99, first_name: 'Viewer', name: 'Viewer User' },
      isAuthenticated: true,
    });
    // Different endpoints return different data shapes
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/match')) return Promise.resolve({ success: false, data: null, meta: {} });
      if (url.includes('/applications')) return Promise.resolve({ success: true, data: [], meta: {} });
      if (url.includes('/history')) return Promise.resolve({ success: true, data: [], meta: {} });
      return Promise.resolve({ success: true, data: baseVacancy, meta: {} });
    });
  });

  it('renders loading state initially', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<JobDetailPage />);
    expect(document.querySelectorAll('.animate-pulse').length).toBeGreaterThan(0);
  });

  it('renders not-found empty state when API returns no data', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/match')) return Promise.resolve({ success: false, data: null, meta: {} });
      return Promise.resolve({ success: false, data: null, meta: {} });
    });
    render(<JobDetailPage />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('renders job title and description when loaded', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/match')) return Promise.resolve({ success: false, data: null, meta: {} });
      if (url.includes('/applications')) return Promise.resolve({ success: true, data: [], meta: {} });
      if (url.includes('/history')) return Promise.resolve({ success: true, data: [], meta: {} });
      return Promise.resolve({ success: true, data: makeVacancy({ title: 'Test Vacancy Title', description: 'Test description text' }), meta: {} });
    });
    render(<JobDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Test Vacancy Title')).toBeInTheDocument();
    }, { timeout: 3000 });
    // Description rendered as text inside a div
    const descEl = screen.queryByText((t) => t.includes('Test description text'));
    expect(descEl).not.toBeNull();
  });

  it('shows Apply button when not owner and not applied', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/match')) return Promise.resolve({ success: false, data: null, meta: {} });
      if (url.includes('/applications')) return Promise.resolve({ success: true, data: [], meta: {} });
      if (url.includes('/history')) return Promise.resolve({ success: true, data: [], meta: {} });
      return Promise.resolve({ success: true, data: makeVacancy({ has_applied: false, user_id: 999 }), meta: {} });
    });
    render(<JobDetailPage />);
    await waitFor(() => {
      const applyBtns = screen.getAllByText('apply.button');
      expect(applyBtns.length).toBeGreaterThan(0);
    }, { timeout: 3000 });
  });

  it('does NOT show Apply button when already applied', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/match')) return Promise.resolve({ success: false, data: null, meta: {} });
      if (url.includes('/applications')) return Promise.resolve({ success: true, data: [], meta: {} });
      if (url.includes('/history')) return Promise.resolve({ success: true, data: [], meta: {} });
      return Promise.resolve({ success: true, data: makeVacancy({ has_applied: true, application_status: 'applied', user_id: 999 }), meta: {} });
    });
    render(<JobDetailPage />);
    await waitFor(() => {
      expect(screen.queryByText('apply.button')).not.toBeInTheDocument();
    });
  });

  it('shows Save button when authenticated and not owner (J1)', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/match')) return Promise.resolve({ success: false, data: null, meta: {} });
      if (url.includes('/applications')) return Promise.resolve({ success: true, data: [], meta: {} });
      if (url.includes('/history')) return Promise.resolve({ success: true, data: [], meta: {} });
      return Promise.resolve({ success: true, data: makeVacancy({ user_id: 999, is_saved: false }), meta: {} });
    });
    render(<JobDetailPage />);
    await waitFor(() => {
      expect(screen.getByLabelText('saved.save')).toBeInTheDocument();
    });
  });

  it('shows Edit and Delete buttons when current user is owner', async () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, first_name: 'Alice', name: 'Alice' },
      isAuthenticated: true,
    });
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/match')) return Promise.resolve({ success: false, data: null, meta: {} });
      if (url.includes('/applications')) return Promise.resolve({ success: true, data: [], meta: {} });
      if (url.includes('/history')) return Promise.resolve({ success: true, data: [], meta: {} });
      return Promise.resolve({ success: true, data: makeVacancy({ user_id: 1 }), meta: {} });
    });
    render(<JobDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('detail.edit')).toBeInTheDocument();
    });
    expect(screen.getByText('detail.delete')).toBeInTheDocument();
  });

  it('shows featured badge when job is featured (J10)', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/match')) return Promise.resolve({ success: false, data: null, meta: {} });
      if (url.includes('/applications')) return Promise.resolve({ success: true, data: [], meta: {} });
      if (url.includes('/history')) return Promise.resolve({ success: true, data: [], meta: {} });
      return Promise.resolve({ success: true, data: makeVacancy({ is_featured: true, user_id: 999 }), meta: {} });
    });
    render(<JobDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('featured')).toBeInTheDocument();
    });
  });

  it('shows salary info when salary_min and salary_max are present (J9)', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/match')) return Promise.resolve({ success: false, data: null, meta: {} });
      if (url.includes('/applications')) return Promise.resolve({ success: true, data: [], meta: {} });
      if (url.includes('/history')) return Promise.resolve({ success: true, data: [], meta: {} });
      return Promise.resolve({ success: true, data: makeVacancy({ salary_min: 40000, salary_max: 60000, salary_currency: 'USD', user_id: 999 }), meta: {} });
    });
    render(<JobDetailPage />);
    await waitFor(() => {
      // Salary rendered as text containing 40000 or 40,000 (may appear in multiple elements due to responsive layout)
      const els = screen.queryAllByText(/40[,.]?000/);
      expect(els.length).toBeGreaterThan(0);
    }, { timeout: 3000 });
  });

  it('shows skills chips (J2)', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/match')) return Promise.resolve({ success: false, data: null, meta: {} });
      if (url.includes('/applications')) return Promise.resolve({ success: true, data: [], meta: {} });
      if (url.includes('/history')) return Promise.resolve({ success: true, data: [], meta: {} });
      return Promise.resolve({ success: true, data: makeVacancy({ skills: ['JavaScript', 'React'], user_id: 999 }), meta: {} });
    });
    render(<JobDetailPage />);
    await waitFor(() => {
      // Skills chips may be inside a chip/badge component
      const jsEl = screen.queryByText((t) => t.trim() === 'JavaScript');
      expect(jsEl).not.toBeNull();
    }, { timeout: 3000 });
    const reactEl = screen.queryByText((t) => t.trim() === 'React');
    expect(reactEl).not.toBeNull();
  });

  it('shows Renew button when deadline has passed and user is owner (J7)', async () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, first_name: 'Alice', name: 'Alice' },
      isAuthenticated: true,
    });
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/match')) return Promise.resolve({ success: false, data: null, meta: {} });
      if (url.includes('/applications')) return Promise.resolve({ success: true, data: [], meta: {} });
      if (url.includes('/history')) return Promise.resolve({ success: true, data: [], meta: {} });
      return Promise.resolve({ success: true, data: makeVacancy({ user_id: 1, deadline: '2020-01-01' }), meta: {} });
    });
    render(<JobDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('detail.renew')).toBeInTheDocument();
    });
  });

  it('shows Analytics link when user is owner (J8)', async () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, first_name: 'Alice', name: 'Alice' },
      isAuthenticated: true,
    });
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/match')) return Promise.resolve({ success: false, data: null, meta: {} });
      if (url.includes('/applications')) return Promise.resolve({ success: true, data: [], meta: {} });
      if (url.includes('/history')) return Promise.resolve({ success: true, data: [], meta: {} });
      return Promise.resolve({ success: true, data: makeVacancy({ user_id: 1 }), meta: {} });
    });
    render(<JobDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('detail.analytics')).toBeInTheDocument();
    });
  });

  it('shows Am I Qualified button when authenticated and not owner (J5)', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/match')) return Promise.resolve({ success: false, data: null, meta: {} });
      if (url.includes('/applications')) return Promise.resolve({ success: true, data: [], meta: {} });
      if (url.includes('/history')) return Promise.resolve({ success: true, data: [], meta: {} });
      return Promise.resolve({ success: true, data: makeVacancy({ skills: ['Python'], user_id: 999 }), meta: {} });
    });
    render(<JobDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('detail.check_qualification')).toBeInTheDocument();
    });
  });
});
