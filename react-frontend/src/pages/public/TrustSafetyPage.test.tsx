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
    branding: { name: 'Test Platform' },
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
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

import { TrustSafetyPage } from './TrustSafetyPage';

// Real English strings from public/locales/en/legal.json
const HEADING = 'Trust & Safety';
const SAFEGUARDING_TITLE = 'Report a safeguarding concern';
const HOW_EXCHANGES_TITLE = 'How an exchange works';
const WHAT_WE_DO_TITLE = 'What the platform does';
const CONTACT_BUTTON_TEXT = 'Contact us';

describe('TrustSafetyPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the main h1 heading "Trust & Safety"', () => {
    render(<TrustSafetyPage />);
    expect(screen.getByRole('heading', { level: 1, name: HEADING })).toBeInTheDocument();
  });

  it('renders the safeguarding callout section heading', () => {
    render(<TrustSafetyPage />);
    expect(screen.getByRole('heading', { level: 2, name: SAFEGUARDING_TITLE })).toBeInTheDocument();
  });

  it('renders the "How an exchange works" section heading', () => {
    render(<TrustSafetyPage />);
    expect(screen.getByRole('heading', { level: 2, name: HOW_EXCHANGES_TITLE })).toBeInTheDocument();
  });

  it('renders the "What the platform does" section heading', () => {
    render(<TrustSafetyPage />);
    expect(screen.getByRole('heading', { level: 2, name: WHAT_WE_DO_TITLE })).toBeInTheDocument();
  });

  it('renders at least 9 content section h2 headings (one per SECTIONS entry)', () => {
    render(<TrustSafetyPage />);
    // 9 SECTIONS + safeguarding callout + contact CTA ≥ 11 h2s
    const h2s = screen.getAllByRole('heading', { level: 2 });
    expect(h2s.length).toBeGreaterThanOrEqual(11);
  });

  it('renders the safeguarding emergency step list items', () => {
    render(<TrustSafetyPage />);
    expect(
      screen.getByText(/Use the Report button on the relevant listing/),
    ).toBeInTheDocument();
  });

  it('renders a link to the contact page', () => {
    render(<TrustSafetyPage />);
    const contactLinks = screen.getAllByRole('link').filter((l) =>
      l.getAttribute('href')?.includes('/contact'),
    );
    expect(contactLinks.length).toBeGreaterThan(0);
  });

  it('renders a link to the community-guidelines page', () => {
    render(<TrustSafetyPage />);
    const guidelinesLinks = screen.getAllByRole('link').filter((l) =>
      l.getAttribute('href')?.includes('/community-guidelines'),
    );
    expect(guidelinesLinks.length).toBeGreaterThan(0);
  });

  it('renders the contact CTA button text', () => {
    render(<TrustSafetyPage />);
    // The button text comes from trust_safety.contact_cta_button
    expect(screen.getByText(CONTACT_BUTTON_TEXT)).toBeInTheDocument();
  });

  it('does not render a page-level loading spinner (page is purely static)', () => {
    render(<TrustSafetyPage />);
    // The ToastProvider always injects a role="status" node for notifications.
    // Verify that no role="status" node with aria-busy="true" exists — that is
    // the signature of an active loading state rendered by the page itself.
    const statusNodes = screen.queryAllByRole('status');
    const loadingNodes = statusNodes.filter((n) => n.getAttribute('aria-busy') === 'true');
    expect(loadingNodes).toHaveLength(0);
  });
});
