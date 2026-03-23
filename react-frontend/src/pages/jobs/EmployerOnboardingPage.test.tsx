// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
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

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
const mockTenant = {
  tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
  tenantPath: (p: string) => `/test${p}`,
  hasFeature: vi.fn(() => true),
  hasModule: vi.fn(() => true),
};

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test', name: 'Test User', organization: null },
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

import { EmployerOnboardingPage } from './EmployerOnboardingPage';

describe('EmployerOnboardingPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Clear localStorage to ensure clean wizard state
    try { localStorage.removeItem('nexus_employer_onboarding'); } catch { /* ignore */ }
  });

  it('renders without crashing', () => {
    render(<EmployerOnboardingPage />);
    expect(document.body).toBeTruthy();
  });

  it('renders the welcome step heading by default', () => {
    render(<EmployerOnboardingPage />);
    expect(screen.getByText('onboarding.welcome_title')).toBeInTheDocument();
  });

  it('renders the Get Started button on welcome step', () => {
    render(<EmployerOnboardingPage />);
    expect(screen.getByText('onboarding.get_started')).toBeInTheDocument();
  });

  it('renders the progress indicator', () => {
    render(<EmployerOnboardingPage />);
    // Step counter text
    const stepText = screen.getByText(/1 \/ 4/);
    expect(stepText).toBeInTheDocument();
  });

  it('renders the welcome description', () => {
    render(<EmployerOnboardingPage />);
    expect(screen.getByText('onboarding.welcome_desc')).toBeInTheDocument();
  });

  it('renders back link to browse vacancies', () => {
    render(<EmployerOnboardingPage />);
    expect(screen.getByText('detail.browse_vacancies')).toBeInTheDocument();
  });

  it('navigates to step 2 when Get Started is clicked', async () => {
    const { userEvent } = await import('@/test/test-utils');
    render(<EmployerOnboardingPage />);
    await userEvent.click(screen.getByText('onboarding.get_started'));
    expect(screen.getByText('onboarding.org_title')).toBeInTheDocument();
  });

  it('renders step 2 organization form fields', async () => {
    const { userEvent } = await import('@/test/test-utils');
    render(<EmployerOnboardingPage />);
    await userEvent.click(screen.getByText('onboarding.get_started'));
    expect(screen.getByText('onboarding.org_title')).toBeInTheDocument();
    expect(screen.getByText('onboarding.org_desc')).toBeInTheDocument();
  });
});
