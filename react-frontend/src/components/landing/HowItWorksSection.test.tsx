// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { HowItWorksSection } from './HowItWorksSection';
import type { HowItWorksContent } from '@/types';

vi.mock('@/contexts', () => ({
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test Tenant', slug: 'test' }, tenantPath: (p: string) => `/test${p}`, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

describe('HowItWorksSection — default (no content prop)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the section with aria-labelledby', () => {
    render(<HowItWorksSection />);
    const section = document.querySelector('section#features');
    expect(section).toBeInTheDocument();
    expect(section).toHaveAttribute('aria-labelledby', 'how-it-works-heading');
  });

  it('renders the h2 heading with id', () => {
    render(<HowItWorksSection />);
    const heading = screen.getByRole('heading', { level: 2 });
    expect(heading).toBeInTheDocument();
    expect(heading).toHaveAttribute('id', 'how-it-works-heading');
  });

  it('renders exactly 4 step cards by default', () => {
    render(<HowItWorksSection />);
    // Each step card has an h3 — so 4 h3 elements
    const stepHeadings = screen.getAllByRole('heading', { level: 3 });
    expect(stepHeadings).toHaveLength(4);
  });

  it('renders step number badges 1-4', () => {
    render(<HowItWorksSection />);
    // Step number labels are visible inside the badge divs
    expect(screen.getByText('1')).toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();
    expect(screen.getByText('3')).toBeInTheDocument();
    expect(screen.getByText('4')).toBeInTheDocument();
  });
});

describe('HowItWorksSection — with custom content prop', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  const customContent: HowItWorksContent = {
    title: 'Three Easy Steps',
    subtitle: 'Join and get started today.',
    steps: [
      { title: 'Sign Up', description: 'Create your free account.', icon: 'user-plus' },
      { title: 'Browse', description: 'Find services near you.', icon: 'search' },
      { title: 'Exchange', description: 'Trade time credits.', icon: 'handshake' },
    ],
  };

  it('uses the custom title over the i18n fallback', () => {
    render(<HowItWorksSection content={customContent} />);
    expect(screen.getByRole('heading', { level: 2, name: 'Three Easy Steps' })).toBeInTheDocument();
  });

  it('uses the custom subtitle over the i18n fallback', () => {
    render(<HowItWorksSection content={customContent} />);
    expect(screen.getByText('Join and get started today.')).toBeInTheDocument();
  });

  it('renders the custom step titles', () => {
    render(<HowItWorksSection content={customContent} />);
    expect(screen.getByRole('heading', { level: 3, name: 'Sign Up' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 3, name: 'Browse' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 3, name: 'Exchange' })).toBeInTheDocument();
  });

  it('renders the custom step descriptions', () => {
    render(<HowItWorksSection content={customContent} />);
    expect(screen.getByText('Create your free account.')).toBeInTheDocument();
    expect(screen.getByText('Find services near you.')).toBeInTheDocument();
    expect(screen.getByText('Trade time credits.')).toBeInTheDocument();
  });

  it('renders exactly 3 step cards when 3 steps are provided', () => {
    render(<HowItWorksSection content={customContent} />);
    const stepHeadings = screen.getAllByRole('heading', { level: 3 });
    expect(stepHeadings).toHaveLength(3);
  });

  it('falls back to i18n title when content has no title', () => {
    render(<HowItWorksSection content={{ subtitle: 'Custom sub only' }} />);
    // The h2 heading exists — we don't assert the exact i18n string but ensure it renders
    expect(screen.getByRole('heading', { level: 2 })).toBeInTheDocument();
    expect(screen.getByText('Custom sub only')).toBeInTheDocument();
  });

  it('falls back to 4 default steps when content.steps is empty', () => {
    render(<HowItWorksSection content={{ title: 'Custom Title', steps: [] }} />);
    // Empty steps → use default 4
    const stepHeadings = screen.getAllByRole('heading', { level: 3 });
    expect(stepHeadings).toHaveLength(4);
  });
});
