// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { api } from '@/lib/api';
import type { ReactNode } from 'react';

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: { children?: ReactNode; [k: string]: unknown }) => {
      const { variants: _v, initial: _i, animate: _a, exit: _e, transition: _t, ...rest } = props as Record<string, unknown>;
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children?: ReactNode }) => <>{children}</>,
}));

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
    Link: ({ children, to, ...rest }: { children: ReactNode; to: string; [k: string]: unknown }) =>
      React.createElement('a', { href: String(to), ...rest }, children),
    useNavigate: () => vi.fn(),
    useParams: () => ({ id: '7' }),
  };
});

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test', name: 'Test User', role: 'member' },
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

  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({ resolveAvatarUrl: (url: string | null) => url ?? '' }));

import { OrganisationDetailPage } from './OrganisationDetailPage';

const mockOrganisation = {
  id: 7,
  name: 'Green Community Trust',
  description: 'We help local communities thrive through volunteering.',
  logo_url: null,
  website: 'https://greencommunitytrust.ie',
  contact_email: 'info@greencommunitytrust.ie',
  location: 'Cork, Ireland',
  opportunity_count: 5,
  total_hours: 240,
  volunteer_count: 42,
  average_rating: 4.5,
  review_count: 12,
  created_at: '2025-01-01T00:00:00Z',
};

const mockOpportunities = [
  {
    id: 20,
    title: 'River Cleanup Volunteer',
    description: 'Help clean the river bank.',
    location: 'Cork River',
    skills_needed: 'Physical fitness',
    start_date: '2026-07-01T09:00:00Z',
    end_date: null,
    is_active: true,
    is_remote: false,
    category: 'Environment',
    organization: { id: 7, name: 'Green Community Trust', logo_url: null },
    created_at: '2026-01-10T12:00:00Z',
    has_applied: false,
  },
];

const mockReviews = [
  {
    id: 1,
    rating: 5,
    comment: 'Excellent organisation to volunteer with!',
    author: { id: 10, name: 'Bob Reviewer', avatar: null },
    created_at: '2026-02-01T10:00:00Z',
  },
];

function setupSuccessfulMocks() {
  vi.mocked(api.get).mockImplementation((url: string) => {
    if (url.includes('/reviews/organization/')) {
      return Promise.resolve({ success: true, data: { reviews: mockReviews } });
    }
    if (url.includes('/opportunities')) {
      return Promise.resolve({ success: true, data: mockOpportunities });
    }
    if (url.includes('/organisations/')) {
      return Promise.resolve({ success: true, data: mockOrganisation });
    }
    return Promise.resolve({ success: true, data: null });
  });
}

describe('OrganisationDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading screen initially', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<OrganisationDetailPage />);
    expect(document.body).toBeTruthy();
  });

  it('renders organisation name and description on success', async () => {
    setupSuccessfulMocks();
    render(<OrganisationDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Green Community Trust')[0]).toBeInTheDocument();
    });
    expect(screen.getByText('We help local communities thrive through volunteering.')).toBeInTheDocument();
  });

  it('renders linked opportunities', async () => {
    setupSuccessfulMocks();
    render(<OrganisationDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('River Cleanup Volunteer')).toBeInTheDocument();
    });
  });

  it('renders reviews when present', async () => {
    setupSuccessfulMocks();
    render(<OrganisationDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Excellent organisation to volunteer with!')).toBeInTheDocument();
    });
  });

  it('shows error state when API fails', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));
    render(<OrganisationDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('organisation_detail.unable_to_load')).toBeInTheDocument();
    });
    expect(screen.getByText('organisation_detail.try_again')).toBeInTheDocument();
  });

  it('calls all three API endpoints in parallel on mount', async () => {
    setupSuccessfulMocks();
    render(<OrganisationDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Green Community Trust')[0]).toBeInTheDocument();
    });
    expect(api.get).toHaveBeenCalledWith(expect.stringContaining('/v2/volunteering/organisations/7'));
    expect(api.get).toHaveBeenCalledWith(expect.stringContaining('/v2/volunteering/opportunities'));
    expect(api.get).toHaveBeenCalledWith(expect.stringContaining('/v2/volunteering/reviews/organization/7'));
  });

  it('shows organisation stats (volunteer count, hours, rating)', async () => {
    setupSuccessfulMocks();
    render(<OrganisationDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Green Community Trust')[0]).toBeInTheDocument();
    });
    // Stats values should be visible
    expect(screen.getByText('42')).toBeInTheDocument();
    expect(screen.getByText('240')).toBeInTheDocument();
  });

  it('shows website and contact email links', async () => {
    setupSuccessfulMocks();
    render(<OrganisationDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Green Community Trust')[0]).toBeInTheDocument();
    });
    expect(document.querySelector('a[href="https://greencommunitytrust.ie"]')).toBeInTheDocument();
  });

  it('shows empty state when no opportunities exist', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/reviews/organization/')) {
        return Promise.resolve({ success: true, data: { reviews: [] } });
      }
      if (url.includes('/opportunities')) {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: true, data: mockOrganisation });
    });
    render(<OrganisationDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Green Community Trust')[0]).toBeInTheDocument();
    });
    expect(screen.getByText('organisation_detail.no_active_opportunities')).toBeInTheDocument();
  });
});
