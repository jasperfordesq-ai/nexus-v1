// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for VisibilityRulesEditor — visibility rule controls (auth, role, feature)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Stable mock references ─────────────────────────────────────────────────

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, name: 'Admin User', role: 'admin' },
    isAuthenticated: true,
    logout: vi.fn(),
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Community', slug: 'test', configuration: {} },
    tenantSlug: 'test',
    branding: { name: 'Test Community' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    showToast: vi.fn(),
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

import { VisibilityRulesEditor } from '../VisibilityRulesEditor';

// ─── Wrapper ─────────────────────────────────────────────────────────────────

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test/admin']}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('VisibilityRulesEditor', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    const onChange = vi.fn();
    const { container } = render(
      <W><VisibilityRulesEditor value={null} onChange={onChange} /></W>
    );
    expect(container).toBeTruthy();
  });

  it('renders the title', () => {
    const onChange = vi.fn();
    render(
      <W><VisibilityRulesEditor value={null} onChange={onChange} /></W>
    );
    // Title uses translation key
    expect(screen.getByText('visibility_rules.title')).toBeTruthy();
  });

  it('renders the requires_auth switch', () => {
    const onChange = vi.fn();
    render(
      <W><VisibilityRulesEditor value={null} onChange={onChange} /></W>
    );
    expect(screen.getByText('visibility_rules.requires_auth')).toBeTruthy();
  });

  it('renders the min_role select', () => {
    const onChange = vi.fn();
    render(
      <W><VisibilityRulesEditor value={null} onChange={onChange} /></W>
    );
    // HeroUI Select renders label in both a hidden <label> and a visible <label>
    const labels = screen.getAllByText('visibility_rules.min_role');
    expect(labels.length).toBeGreaterThanOrEqual(1);
  });

  it('renders the requires_feature select', () => {
    const onChange = vi.fn();
    render(
      <W><VisibilityRulesEditor value={null} onChange={onChange} /></W>
    );
    // HeroUI Select renders label in both a hidden <label> and a visible <label>
    const labels = screen.getAllByText('visibility_rules.requires_feature');
    expect(labels.length).toBeGreaterThanOrEqual(1);
  });

  it('renders with existing visibility rules', () => {
    const onChange = vi.fn();
    render(
      <W>
        <VisibilityRulesEditor
          value={{ requires_auth: true, min_role: 'admin' }}
          onChange={onChange}
        />
      </W>
    );
    // Should render without error with pre-filled values
    expect(screen.getByText('visibility_rules.title')).toBeTruthy();
  });

  it('renders with null value (empty rules)', () => {
    const onChange = vi.fn();
    render(
      <W><VisibilityRulesEditor value={null} onChange={onChange} /></W>
    );
    expect(screen.getByText('visibility_rules.title')).toBeTruthy();
  });

  it('renders role options in the select', () => {
    const onChange = vi.fn();
    const { container } = render(
      <W><VisibilityRulesEditor value={null} onChange={onChange} /></W>
    );
    // The Select component should contain role options
    // HeroUI renders the select as a trigger button, not a native select
    const selects = container.querySelectorAll('[data-slot="trigger"]');
    expect(selects.length).toBeGreaterThanOrEqual(2); // min_role + requires_feature
  });
});
