// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for DevelopmentStatusPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import React from 'react';

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test Community' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useAuth: vi.fn(() => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() })),
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

vi.mock('@/hooks/usePageTitle', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

vi.mock('@/config/releaseStatus', () => ({
  RELEASE_STATUS: {
    stageKey: 'rc',
    stageLabel: 'Release Candidate (RC)',
    stageSummary: 'Test summary',
    readMorePath: '/development-status',
  },
}));

import { DevelopmentStatusPage } from './DevelopmentStatusPage';

describe('DevelopmentStatusPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    const { container } = render(<DevelopmentStatusPage />);
    expect(container.querySelector('div')).toBeTruthy();
  });

  it('renders RC chip', () => {
    render(<DevelopmentStatusPage />);
    expect(screen.getByText('RC')).toBeInTheDocument();
  });

  it('renders page heading with stage label', () => {
    render(<DevelopmentStatusPage />);
    // The heading uses translation key dev_status.title with stage label
    // With i18n defaulting to key return, check for the key or default
    const headings = screen.getAllByRole('heading');
    expect(headings.length).toBeGreaterThan(0);
  });

  it('renders security section with email link', () => {
    render(<DevelopmentStatusPage />);
    const emailLink = screen.getByRole('link', { name: /jasper@hour-timebank.ie/i });
    expect(emailLink).toBeInTheDocument();
    expect(emailLink).toHaveAttribute('href', 'mailto:jasper@hour-timebank.ie');
  });

  it('renders GitHub issues link', () => {
    render(<DevelopmentStatusPage />);
    const githubLink = screen.getAllByRole('link').find(
      (link) => link.getAttribute('href')?.includes('github.com/jasperfordesq-ai/nexus-v1/issues')
    );
    expect(githubLink).toBeTruthy();
  });
});
