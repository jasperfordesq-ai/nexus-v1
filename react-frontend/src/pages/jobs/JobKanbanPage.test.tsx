// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import type { ReactNode } from 'react';

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
    useParams: () => ({ id: '5' }),
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
  API_BASE: 'http://localhost:8090',
  tokenManager: { getTenantId: vi.fn() },
}));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
const mockTenant = {
  tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
  tenantPath: (p: string) => `/test${p}`,
  hasFeature: vi.fn(() => true),
  hasModule: vi.fn(() => true),
};

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test', name: 'Test User' },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => mockTenant),
  useToast: vi.fn(() => mockToast),
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

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
  GlassButton: ({ children }: Record<string, unknown>) => children as never,
  GlassInput: () => null,
  BackToTop: () => null,
  AlgorithmLabel: () => null,
  ImagePlaceholder: () => null,
  DynamicIcon: () => null,
  ICON_MAP: {},
  ICON_NAMES: [],
  ListingSkeleton: () => null,
  MemberCardSkeleton: () => null,
  StatCardSkeleton: () => null,
  EventCardSkeleton: () => null,
  GroupCardSkeleton: () => null,
  ConversationSkeleton: () => null,
  ExchangeCardSkeleton: () => null,
  NotificationSkeleton: () => null,
  ProfileHeaderSkeleton: () => null,
  SkeletonList: () => null,
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
    div: ({ children, ...rest }: Record<string, unknown>) => (
      <div {...(rest as object)}>{children as ReactNode}</div>
    ),
  },
  AnimatePresence: ({ children }: { children: ReactNode }) => <>{children}</>,
}));

import { JobKanbanPage } from './JobKanbanPage';
import { api } from '@/lib/api';

function makeVacancy(overrides: Record<string, unknown> = {}) {
  return {
    id: 5,
    title: 'Community Coordinator',
    user_id: 1,
    status: 'open',
    ...overrides,
  };
}

function makeApplication(overrides: Record<string, unknown> = {}) {
  return {
    id: 10,
    vacancy_id: 5,
    user_id: 2,
    status: 'pending',
    stage: 'pending',
    message: 'I am very interested in this role.',
    created_at: '2026-01-15T10:00:00Z',
    match_percentage: 75,
    cv_path: null,
    applicant: { id: 2, name: 'Bob Smith', avatar_url: null },
    ...overrides,
  };
}

describe('JobKanbanPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/applications')) {
        return Promise.resolve({
          success: true,
          data: [makeApplication()],
          meta: {},
        });
      }
      return Promise.resolve({
        success: true,
        data: makeVacancy(),
        meta: {},
      });
    });
  });

  it('renders without crashing', () => {
    render(<JobKanbanPage />);
    expect(document.body).toBeTruthy();
  });

  it('shows loading state initially when API is pending', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<JobKanbanPage />);
    // When loading, the kanban columns are not rendered yet
    expect(screen.queryByText('Applied')).not.toBeInTheDocument();
  });

  it('renders the job title when data is loaded', async () => {
    render(<JobKanbanPage />);
    await waitFor(() => {
      expect(screen.getByText('Community Coordinator')).toBeInTheDocument();
    });
  });

  it('renders the pipeline title heading', async () => {
    render(<JobKanbanPage />);
    await waitFor(() => {
      expect(screen.getByText('kanban.pipeline_title')).toBeInTheDocument();
    });
  });

  it('renders all kanban columns', async () => {
    render(<JobKanbanPage />);
    await waitFor(() => {
      expect(screen.getByText('Applied')).toBeInTheDocument();
      expect(screen.getByText('Screening')).toBeInTheDocument();
      expect(screen.getByText('Interview')).toBeInTheDocument();
      expect(screen.getByText('Offer')).toBeInTheDocument();
      expect(screen.getByText('Accepted')).toBeInTheDocument();
      expect(screen.getByText('Rejected')).toBeInTheDocument();
    });
  });

  it('renders applicant name on card', async () => {
    render(<JobKanbanPage />);
    await waitFor(() => {
      expect(screen.getByText('Bob Smith')).toBeInTheDocument();
    });
  });

  it('shows error state when vacancy fails to load', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/applications')) {
        return Promise.resolve({ success: true, data: [], meta: {} });
      }
      return Promise.resolve({ success: false, data: null, meta: {} });
    });
    render(<JobKanbanPage />);
    await waitFor(() => {
      expect(screen.getByText('detail.not_found')).toBeInTheDocument();
    });
  });

  it('renders back navigation link when loaded', async () => {
    render(<JobKanbanPage />);
    await waitFor(() => {
      expect(screen.getByText('detail.browse_vacancies')).toBeInTheDocument();
    });
  });

  it('shows applications count when data is loaded', async () => {
    render(<JobKanbanPage />);
    await waitFor(() => {
      expect(screen.getByText('Community Coordinator')).toBeInTheDocument();
    });
    // The chip renders "1 applications" (translated key)
    const appCountEl = screen.queryByText((text) => text.includes('applications'));
    expect(appCountEl).toBeTruthy();
  });
});
