// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const translations: Record<string, string> = {
        'application_status.pending': 'Pending',
        'application_status.screening': 'Screening',
        'application_status.interview': 'Interview',
        'application_status.offer': 'Offer',
        'application_status.accepted': 'Accepted',
        'application_status.rejected': 'Rejected',
      };

      return translations[key] ?? (opts?.fallbackValue as string | undefined) ?? key;
    },
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

vi.mock('react-router-dom', () => {
  return {
    BrowserRouter: ({ children }: { children?: ReactNode }) => <>{children}</>,
    MemoryRouter: ({ children }: { children?: ReactNode }) => <>{children}</>,
    useParams: () => ({ id: '5' }),
    Link: ({ children, to, ...rest }: { children: ReactNode; to: string; [k: string]: unknown }) =>
      <a href={String(to)} {...rest}>{children}</a>,
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
  cn: (...classes: unknown[]) => classes.filter(Boolean).join(' '),
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
}));

vi.mock('@/components/ui', () => {
  const makeStub = (name: string) => ({ children, label, title, description, onPress, onClick, onValueChange, ...props }: Record<string, unknown>) => {
    const lower = name.toLowerCase();
    if (lower.includes('button')) {
      return <button type="button" onClick={(onPress ?? onClick) as (() => void) | undefined}>{(children ?? label ?? title) as ReactNode}</button>;
    }
    if (lower.includes('input') || lower.includes('textarea') || lower.includes('field') || lower.includes('select')) {
      return <input placeholder={props.placeholder as string | undefined} onChange={(event) => typeof onValueChange === 'function' && (onValueChange as (value: string) => void)(event.target.value)} />;
    }
    if (lower.includes('switch') || lower.includes('checkbox')) {
      return <label><input type="checkbox" />{children as ReactNode}</label>;
    }
    if (lower.includes('skeleton') || lower.includes('spinner')) {
      return <div role="status" />;
    }
    return <div>{label as ReactNode}{title as ReactNode}{description as ReactNode}{children as ReactNode}</div>;
  };

  return new Proxy({}, {
    get(_target, prop) {
      if (typeof prop === 'symbol') return undefined;
      if (prop === '__esModule') return true;
      if (prop === 'default') return undefined;
      if (prop === 'useConfirm') return () => () => Promise.resolve(true);
      if (/^use[A-Z]/.test(prop)) return () => ({});
      return makeStub(String(prop));
    },
  });
});

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <div>{title}</div>
      {description && <div>{description}</div>}
    </div>
  ),
}));

vi.mock('@/lib/motion', () => ({
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
    vi.mocked(api.get).mockReturnValue(new Promise((resolve) => {
      window.setTimeout(() => resolve({ success: true, data: makeVacancy(), meta: {} }), 25);
    }));
    const { unmount } = render(<JobKanbanPage />);
    // When loading, the kanban columns are not rendered yet
    expect(screen.queryByText('Applied')).not.toBeInTheDocument();
    unmount();
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
      expect(screen.getAllByText('Pending').length).toBeGreaterThan(0);
      expect(screen.getAllByText('Screening').length).toBeGreaterThan(0);
      expect(screen.getAllByText('Interview').length).toBeGreaterThan(0);
      expect(screen.getAllByText('Offer').length).toBeGreaterThan(0);
      expect(screen.getAllByText('Accepted').length).toBeGreaterThan(0);
      expect(screen.getAllByText('Rejected').length).toBeGreaterThan(0);
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
