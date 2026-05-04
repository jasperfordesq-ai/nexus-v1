// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for FederationHubPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

// Mock API module
const mockApiGet = vi.fn();
const mockApiPost = vi.fn();

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockApiGet(...args),
    post: (...args: unknown[]) => mockApiPost(...args),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

// Mock contexts - must include ToastProvider since test-utils.tsx uses it
vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test', name: 'Test User' },
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
  })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,

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
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
}));

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

vi.mock('@/components/navigation', () => ({
  Breadcrumbs: ({ items }: { items: { label: string }[] }) => (
    <nav data-testid="breadcrumbs">
      {items.map((item, i) => (
        <span key={i}>{item.label}</span>
      ))}
    </nav>
  ),
}));

vi.mock('@/components/seo', () => ({ PageMeta: () => null }));

vi.mock('framer-motion', () => {  const motionProps = new Set(['variants', 'initial', 'animate', 'layout', 'transition', 'exit', 'whileHover', 'whileTap', 'whileInView', 'viewport', 'custom']);  const filterMotion = (props: Record<string, unknown>) => {    const filtered: Record<string, unknown> = {};    for (const [k, v] of Object.entries(props)) {      if (!motionProps.has(k)) filtered[k] = v;    }    return filtered;  };  return {    motion: {      div: ({ children, ...props }: Record<string, unknown>) => <div {...filterMotion(props)}>{children}</div>,    },    AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,  };});

import FederationHubPage from './FederationHubPage';

describe('FederationHubPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading state initially', async () => {
    // Use a controllable promise instead of never-resolving one (prevents Vitest hang on CI)
    let resolveApi: (value: unknown) => void;
    mockApiGet.mockReturnValue(new Promise((resolve) => { resolveApi = resolve; }));

    render(<FederationHubPage />);
    expect(screen.getByText('Loading federation data...')).toBeInTheDocument();
    // Clean up: resolve the promise so Vitest can exit cleanly
    resolveApi!({ success: true, data: { enabled: false } });
  });

  it('shows breadcrumbs', async () => {
    let resolveApi: (value: unknown) => void;
    mockApiGet.mockReturnValue(new Promise((resolve) => { resolveApi = resolve; }));

    render(<FederationHubPage />);
    expect(screen.getByTestId('breadcrumbs')).toBeInTheDocument();
    expect(screen.getByText('Federation')).toBeInTheDocument();
    // Clean up: resolve the promise so Vitest can exit cleanly
    resolveApi!({ success: true, data: { enabled: false } });
  });

  it('shows error state when API fails', async () => {
    mockApiGet.mockRejectedValueOnce(new Error('Network error'));

    render(<FederationHubPage />);

    await waitFor(() => {
      expect(screen.getByText('Unable to Load')).toBeInTheDocument();
    });
    expect(screen.getByText('An error occurred. Please try again.')).toBeInTheDocument();
    expect(screen.getByText('Try Again')).toBeInTheDocument();
  });

  it('shows Federation Not Available when tenant federation is disabled', async () => {
    mockApiGet.mockResolvedValueOnce({
      success: true,
      data: {
        enabled: false,
        tenant_federation_enabled: false,
        partnerships_count: 0,
      },
    });

    render(<FederationHubPage />);

    await waitFor(() => {
      expect(screen.getByText('Federation Not Available')).toBeInTheDocument();
    });
  });

  it('shows hero section with Enable Federation button when not opted in', async () => {
    mockApiGet.mockResolvedValueOnce({
      success: true,
      data: {
        enabled: false,
        tenant_federation_enabled: true,
        partnerships_count: 0,
      },
    });

    render(<FederationHubPage />);

    await waitFor(() => {
      expect(screen.getByText('Federation Network')).toBeInTheDocument();
    });
    expect(screen.getByText('Connect with Communities Worldwide')).toBeInTheDocument();
    expect(screen.getByText('Enable Federation')).toBeInTheDocument();
  });

  it('shows How It Works cards when not opted in', async () => {
    mockApiGet.mockResolvedValueOnce({
      success: true,
      data: {
        enabled: false,
        tenant_federation_enabled: true,
        partnerships_count: 0,
      },
    });

    render(<FederationHubPage />);

    await waitFor(() => {
      expect(screen.getByText('How It Works')).toBeInTheDocument();
    });
    expect(screen.getByText('Discover Partners')).toBeInTheDocument();
    expect(screen.getByText('Meet Members')).toBeInTheDocument();
    expect(screen.getByText('Exchange Services')).toBeInTheDocument();
  });

  it('shows dashboard when opted in with partner communities', async () => {
    // First call: status
    mockApiGet.mockResolvedValueOnce({
      success: true,
      data: {
        enabled: true,
        tenant_federation_enabled: true,
        partnerships_count: 2,
      },
    });
    // Second call: partners
    mockApiGet.mockResolvedValueOnce({
      success: true,
      data: [
        {
          id: 1,
          name: 'Partner Community A',
          logo: null,
          location: 'Dublin',
          tagline: 'A great community',
          federation_level: 2,
          federation_level_name: 'Standard',
          member_count: 50,
        },
      ],
    });
    // Third call: activity
    mockApiGet.mockResolvedValueOnce({
      success: true,
      data: [],
    });

    render(<FederationHubPage />);

    await waitFor(() => {
      expect(screen.getByText('Federation Network')).toBeInTheDocument();
    });
    // Quick links section
    expect(screen.getByText('Explore Network')).toBeInTheDocument();
    // Partner communities section
    expect(screen.getByText('Partner Community A')).toBeInTheDocument();
  });

  it('shows quick navigation links when opted in', async () => {
    mockApiGet.mockResolvedValueOnce({
      success: true,
      data: {
        enabled: true,
        tenant_federation_enabled: true,
        partnerships_count: 0,
      },
    });
    mockApiGet.mockResolvedValueOnce({ success: true, data: [] });
    mockApiGet.mockResolvedValueOnce({ success: true, data: [] });

    render(<FederationHubPage />);

    await waitFor(() => {
      expect(screen.getByText('Explore Network')).toBeInTheDocument();
    });
    // Quick link titles - some may appear in both title and description,
    // so use getAllByText where needed
    expect(screen.getByText('Federation Members')).toBeInTheDocument();
    expect(screen.getAllByText(/Federation Messages/).length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('Federation Listings')).toBeInTheDocument();
    expect(screen.getByText('Federation Events')).toBeInTheDocument();
    expect(screen.getByText('Federation Settings')).toBeInTheDocument();
  });
});
