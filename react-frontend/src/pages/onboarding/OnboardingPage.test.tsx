// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for OnboardingPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

// Mock API module
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [], meta: {} }),
    post: vi.fn().mockResolvedValue({ success: true, data: { listings_created: 0 } }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

// Mock contexts - must include ToastProvider since test-utils.tsx uses it
vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test', name: 'Test User', onboarding_completed: false },
    isAuthenticated: true,
    refreshUser: vi.fn().mockResolvedValue(undefined),
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Community', slug: 'test', branding: { name: 'Test Community' } },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => children,

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

vi.mock('@/contexts/ToastContext', () => ({
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, ...props }: Record<string, unknown>) => <div {...props}>{children}</div>,

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

vi.mock('framer-motion', () => {  const motionProps = new Set(['variants', 'initial', 'animate', 'layout', 'transition', 'exit', 'whileHover', 'whileTap', 'whileInView', 'viewport', 'custom']);  const filterMotion = (props: Record<string, unknown>) => {    const filtered: Record<string, unknown> = {};    for (const [k, v] of Object.entries(props)) {      if (!motionProps.has(k)) filtered[k] = v;    }    return filtered;  };  return {    motion: {      div: ({ children, ...props }: Record<string, unknown>) => <div {...filterMotion(props)}>{children}</div>,    },    AnimatePresence: ({ children }: { children: React.ReactNode }) => children,  };});

import { OnboardingPage } from './OnboardingPage';

describe('OnboardingPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the page title and description', () => {
    render(<OnboardingPage />);
    expect(screen.getByText('Get Started')).toBeInTheDocument();
    expect(screen.getByText('Set up your profile in a few easy steps')).toBeInTheDocument();
  });

  it('shows step 1 welcome content initially', () => {
    render(<OnboardingPage />);
    expect(screen.getByText(/Welcome to Test Community!/)).toBeInTheDocument();
    expect(screen.getByText("Let's Get Started")).toBeInTheDocument();
  });

  it('shows benefit cards on step 1', () => {
    render(<OnboardingPage />);
    expect(screen.getByText('Find Help')).toBeInTheDocument();
    expect(screen.getByText('Share Skills')).toBeInTheDocument();
    expect(screen.getByText('Build Community')).toBeInTheDocument();
  });

  it('shows step progress indicator', () => {
    render(<OnboardingPage />);
    expect(screen.getByText('Step 1 of 4')).toBeInTheDocument();
    // The step label "Welcome" appears in the progress area
    expect(screen.getByText(/Welcome to Test Community/)).toBeInTheDocument();
  });

  it('navigates to step 2 when "Let\'s Get Started" is clicked', async () => {
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();

    render(<OnboardingPage />);

    const startButton = screen.getByText("Let's Get Started");
    await user.click(startButton);

    await waitFor(() => {
      expect(screen.getByText('Step 2 of 4')).toBeInTheDocument();
    });
    expect(screen.getByText('What are you interested in?')).toBeInTheDocument();
  });

  it('renders nothing when onboarding is already completed', async () => {
    const { useAuth } = await import('@/contexts');
    vi.mocked(useAuth).mockReturnValue({
      user: { id: 1, first_name: 'Test', name: 'Test User', onboarding_completed: true },
      isAuthenticated: true,
      refreshUser: vi.fn(),
    } as unknown as ReturnType<typeof useAuth>);

    const { container } = render(<OnboardingPage />);
    // Component returns null when onboarding_completed is true (redirect pending)
    // The container should only have the provider wrappers, no onboarding content
    expect(container.querySelector('h1')).toBeNull();
  });

  it('shows Back button on step 2', async () => {
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();

    render(<OnboardingPage />);

    // Navigate step 1 -> 2
    await user.click(screen.getByText("Let's Get Started"));
    await waitFor(() => {
      expect(screen.getByText('Step 2 of 4')).toBeInTheDocument();
    });

    // Back button should exist on step 2
    expect(screen.getByText('Back')).toBeInTheDocument();
  });

  it('shows category selection help text on step 2', async () => {
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();

    render(<OnboardingPage />);

    await user.click(screen.getByText("Let's Get Started"));
    await waitFor(() => {
      expect(screen.getByText('What are you interested in?')).toBeInTheDocument();
    });

    expect(screen.getByText(/Select the categories that interest you/)).toBeInTheDocument();
  });
});
