// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { api } from '@/lib/api';
import type { ReactNode } from 'react';

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: { children?: ReactNode; [k: string]: unknown }) => {
      const { variants: _v, initial: _i, animate: _a, exit: _e, transition: _t, custom: _c, ...rest } = props as Record<string, unknown>;
      return <div {...rest}>{children}</div>;
    },
    custom: undefined,
  },
  AnimatePresence: ({ children }: { children?: ReactNode }) => <>{children}</>,
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) =>
      (opts?.defaultValue as string | undefined) ?? key,
  }),
}));

const mockNavigate = vi.fn();

vi.mock('react-router-dom', async () => {
  const actual = await import('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
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

import { FederationOnboardingPage } from './FederationOnboardingPage';

describe('FederationOnboardingPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    vi.mocked(api.post).mockResolvedValue({ success: true, data: {} });
  });

  it('renders step 1 welcome content on initial load', async () => {
    render(<FederationOnboardingPage />);
    await waitFor(() => {
      expect(screen.getByText('onboarding.welcome_title')).toBeInTheDocument();
    });
  });

  it('shows step indicator / progress', async () => {
    render(<FederationOnboardingPage />);
    await waitFor(() => {
      expect(screen.getByText('onboarding.step_of')).toBeInTheDocument();
    });
  });

  it('advances to step 2 when Next is clicked', async () => {
    render(<FederationOnboardingPage />);
    await waitFor(() => {
      expect(screen.getByText('onboarding.welcome_title')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('onboarding.get_started'));
    await waitFor(() => {
      expect(screen.getByText('onboarding.profile_visibility')).toBeInTheDocument();
    });
  });

  it('goes back to step 1 when Back is clicked on step 2', async () => {
    render(<FederationOnboardingPage />);
    await waitFor(() => {
      expect(screen.getByText('onboarding.welcome_title')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('onboarding.get_started'));
    await waitFor(() => {
      expect(screen.getByText('onboarding.profile_visibility')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('onboarding.back'));
    await waitFor(() => {
      expect(screen.getByText('onboarding.welcome_title')).toBeInTheDocument();
    });
  });

  it('shows Skip button to bypass onboarding (available on step 4)', async () => {
    render(<FederationOnboardingPage />);
    await waitFor(() => {
      expect(screen.getByText('onboarding.welcome_title')).toBeInTheDocument();
    });
    // Navigate to step 4 where do_this_later button appears
    fireEvent.click(screen.getByText('onboarding.get_started'));
    await waitFor(() => expect(screen.getByText('onboarding.profile_visibility')).toBeInTheDocument());
    fireEvent.click(screen.getByText('onboarding.next'));
    await waitFor(() => expect(screen.getByText('onboarding.communication_preferences')).toBeInTheDocument());
    fireEvent.click(screen.getByText('onboarding.next'));
    await waitFor(() => {
      expect(screen.getByText('onboarding.do_this_later')).toBeInTheDocument();
    });
  });

  it('navigates away when Skip is clicked on step 4', async () => {
    render(<FederationOnboardingPage />);
    await waitFor(() => {
      expect(screen.getByText('onboarding.welcome_title')).toBeInTheDocument();
    });
    // Navigate to step 4
    fireEvent.click(screen.getByText('onboarding.get_started'));
    await waitFor(() => expect(screen.getByText('onboarding.profile_visibility')).toBeInTheDocument());
    fireEvent.click(screen.getByText('onboarding.next'));
    await waitFor(() => expect(screen.getByText('onboarding.communication_preferences')).toBeInTheDocument());
    fireEvent.click(screen.getByText('onboarding.next'));
    await waitFor(() => expect(screen.getByText('onboarding.do_this_later')).toBeInTheDocument());
    fireEvent.click(screen.getByText('onboarding.do_this_later'));
    expect(mockNavigate).toHaveBeenCalledWith('/test/federation');
  });

  it('calls POST /v2/federation/setup on final step completion', async () => {
    render(<FederationOnboardingPage />);
    // Advance through all 4 steps
    await waitFor(() => {
      expect(screen.getByText('onboarding.welcome_title')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('onboarding.get_started'));
    await waitFor(() => {
      expect(screen.getByText('onboarding.profile_visibility')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('onboarding.next'));
    await waitFor(() => {
      expect(screen.getByText('onboarding.communication_preferences')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('onboarding.next'));
    await waitFor(() => {
      expect(screen.getByText('onboarding.review_settings')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('onboarding.enable_federation'));
    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/v2/federation/setup', expect.any(Object));
    });
  });
});
