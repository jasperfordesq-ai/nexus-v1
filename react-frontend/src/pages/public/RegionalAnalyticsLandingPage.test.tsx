// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

vi.mock('@/contexts', () => ({
  useAuth: () => ({
    user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(),
    register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(),
    status: 'idle' as const, error: null,
  }),
  useTenant: () => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test Tenant' },
    tenantSlug: 'test',
    tenantPath: (p: string) => `/test${p}`,
    isLoading: false,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useNotifications: () => ({
    unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(),
    markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn(),
  }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({
    consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(),
    saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn(),
  }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

import RegionalAnalyticsLandingPage from './RegionalAnalyticsLandingPage';

// Real English strings from public/locales/en/common.json
const HERO_TITLE = 'Understand your region. Without compromising privacy.';
const FEATURES_HEADING = "What's included";
const PRICING_HEADING = 'Simple, transparent pricing';
const PRIVACY_BADGE = 'Privacy-first by design';
const PRIVACY_FOOTER = /All metrics are bucketed and anonymised/;
const CTA_REQUEST_ACCESS = 'Request access';
const CTA_LEARN_MORE = 'Learn more';
const TIER_CTA = 'Request a quote';
const TIER_BASIC = 'Basic';
const TIER_PRO = 'Pro';
const TIER_ENTERPRISE = 'Enterprise';

describe('RegionalAnalyticsLandingPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the hero h1 heading', () => {
    render(<RegionalAnalyticsLandingPage />);
    expect(screen.getByRole('heading', { level: 1, name: HERO_TITLE })).toBeInTheDocument();
  });

  it('renders the features section heading', () => {
    render(<RegionalAnalyticsLandingPage />);
    expect(screen.getByRole('heading', { level: 2, name: FEATURES_HEADING })).toBeInTheDocument();
  });

  it('renders the pricing section heading', () => {
    render(<RegionalAnalyticsLandingPage />);
    expect(screen.getByRole('heading', { level: 2, name: PRICING_HEADING })).toBeInTheDocument();
  });

  it('renders all four feature card headings', () => {
    render(<RegionalAnalyticsLandingPage />);
    expect(screen.getByRole('heading', { level: 3, name: 'Trends' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 3, name: 'Demand & Supply' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 3, name: 'Demographics' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 3, name: 'Footfall' })).toBeInTheDocument();
  });

  it('renders all three pricing tier label chips', () => {
    render(<RegionalAnalyticsLandingPage />);
    expect(screen.getByText(TIER_BASIC)).toBeInTheDocument();
    expect(screen.getByText(TIER_PRO)).toBeInTheDocument();
    expect(screen.getByText(TIER_ENTERPRISE)).toBeInTheDocument();
  });

  it('renders the privacy-first badge chip', () => {
    render(<RegionalAnalyticsLandingPage />);
    expect(screen.getByText(PRIVACY_BADGE)).toBeInTheDocument();
  });

  it('renders the privacy footer disclaimer', () => {
    render(<RegionalAnalyticsLandingPage />);
    expect(screen.getByText(PRIVACY_FOOTER)).toBeInTheDocument();
  });

  it('renders the "Request access" hero CTA link pointing to /contact', () => {
    render(<RegionalAnalyticsLandingPage />);
    const links = screen.getAllByRole('link').filter((l) =>
      l.textContent?.trim() === CTA_REQUEST_ACCESS &&
      l.getAttribute('href')?.includes('/contact'),
    );
    expect(links.length).toBeGreaterThan(0);
  });

  it('renders the "Learn more" CTA link pointing to /about', () => {
    render(<RegionalAnalyticsLandingPage />);
    const links = screen.getAllByRole('link').filter((l) =>
      l.textContent?.trim() === CTA_LEARN_MORE &&
      l.getAttribute('href')?.includes('/about'),
    );
    expect(links.length).toBeGreaterThan(0);
  });

  it('renders three "Request a quote" tier CTA links', () => {
    render(<RegionalAnalyticsLandingPage />);
    const tierCtaLinks = screen.getAllByRole('link').filter((l) =>
      l.textContent?.trim() === TIER_CTA,
    );
    expect(tierCtaLinks).toHaveLength(3);
  });

  it('does not render a page-level loading spinner (page is purely static — no data fetch)', () => {
    render(<RegionalAnalyticsLandingPage />);
    // The ToastProvider always injects a role="status" node for its notification region.
    // Verify no role="status" node has aria-busy="true" — that is the page-level
    // loading state signature.
    const statusNodes = screen.queryAllByRole('status');
    const loadingNodes = statusNodes.filter((n) => n.getAttribute('aria-busy') === 'true');
    expect(loadingNodes).toHaveLength(0);
  });
});
