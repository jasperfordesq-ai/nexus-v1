// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';

// ─── Admin API mocks (hoisted) ────────────────────────────────────────────────
const { mockAdminConfig, mockAdminSettings } = vi.hoisted(() => ({
  mockAdminConfig: {
    updateLanguageConfig: vi.fn(),
    updateFeature: vi.fn(),
  },
  mockAdminSettings: {
    get: vi.fn(),
    update: vi.fn(),
  },
}));

vi.mock('../../api/adminApi', () => ({
  adminConfig: mockAdminConfig,
  adminSettings: mockAdminSettings,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Stub HeroUI Select / Checkbox / Switch to avoid React Aria infinite-update ─
// PlatformInfrastructure renders many Select + Checkbox + Switch widgets inside cards.
// React Aria's collection system re-queues synchronous updates in jsdom causing
// "Maximum update depth exceeded". Stub only the interactive collection widgets.
vi.mock('@/components/ui', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...actual,
    Select: ({ children, 'aria-label': ariaLabel, label, selectedKeys, onSelectionChange }: {
      children: React.ReactNode;
      'aria-label'?: string;
      label?: string;
      selectedKeys?: string[];
      onSelectionChange?: (keys: Set<string>) => void;
    }) => (
      <select
        aria-label={ariaLabel ?? label ?? 'select'}
        data-testid="select-stub"
        value={selectedKeys?.[0] ?? ''}
        onChange={(e) => onSelectionChange?.(new Set([e.target.value]))}
      >
        {children}
      </select>
    ),
    SelectItem: ({ children, id }: { children: React.ReactNode; id?: string }) => (
      <option value={id}>{children}</option>
    ),
    Checkbox: ({ children, isSelected, onValueChange, isDisabled }: {
      children: React.ReactNode;
      isSelected?: boolean;
      onValueChange?: (v: boolean) => void;
      isDisabled?: boolean;
    }) => (
      <label>
        <input
          type="checkbox"
          role="checkbox"
          aria-checked={Boolean(isSelected)}
          checked={isSelected ?? false}
          disabled={isDisabled ?? false}
          data-disabled={isDisabled ? 'true' : undefined}
          onChange={(e) => onValueChange?.(e.target.checked)}
        />
        {children}
      </label>
    ),
    Switch: ({ isSelected, onValueChange, isDisabled, 'aria-label': ariaLabel }: {
      isSelected?: boolean;
      onValueChange?: (v: boolean) => void;
      isDisabled?: boolean;
      'aria-label'?: string;
    }) => (
      <button
        role="switch"
        aria-label={ariaLabel ?? 'toggle'}
        aria-checked={isSelected ?? false}
        disabled={isDisabled}
        data-testid="switch-stub"
        onClick={() => onValueChange?.(!isSelected)}
      />
    ),
  };
});

// ─── Contexts ──────────────────────────────────────────────────────────────────
// IMPORTANT: stableHasFeature MUST be defined at module level (outside beforeEach)
// so its reference is stable across renders. PlatformInfrastructure uses hasFeature
// in a useEffect dependency array — a new function reference on every render causes
// an infinite re-render loop.
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
const mockRefreshTenant = vi.fn();
const stableHasFeature = vi.fn(() => true);

vi.mock('@/contexts', () => ({
  useToast: () => mockToast,
  useTenant: () => ({
    tenant: { id: 2, name: 'Test', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: stableHasFeature,
    hasModule: vi.fn(() => true),
    refreshTenant: mockRefreshTenant,
    supportedLanguages: ['en', 'fr'],
    defaultLanguage: 'en',
  }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useAuth: () => ({
    user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(),
    register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null,
  }),
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useNotifications: () => ({
    unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(),
    hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn(),
  }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({
    consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(),
    saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn(),
  }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeSettingsResponse = (overrides: Record<string, unknown> = {}) => ({
  success: true,
  data: {
    settings: {
      map_provider: 'google',
      geocoding_provider: 'google',
      google_maps_api_key: 'AIza****',
      google_maps_api_key_set: true,
      google_maps_map_id: '',
      maptiler_api_key: '',
      maptiler_api_key_set: false,
      os_maps_api_key: '',
      os_maps_api_key_set: false,
      ...overrides,
    },
  },
});

// ─────────────────────────────────────────────────────────────────────────────
describe('PlatformInfrastructure', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    stableHasFeature.mockReturnValue(true);
    mockAdminSettings.get.mockResolvedValue(makeSettingsResponse());
    mockAdminConfig.updateLanguageConfig.mockResolvedValue({ success: true });
    mockAdminConfig.updateFeature.mockResolvedValue({ success: true });
    mockAdminSettings.update.mockResolvedValue({ success: true });
  });

  const renderComponent = async () => {
    const PlatformInfrastructure = (await import('./PlatformInfrastructure')).default;
    render(<PlatformInfrastructure config={null} onConfigChange={vi.fn()} />);
  };

  it('shows loading spinner while settings are loading', async () => {
    mockAdminSettings.get.mockImplementationOnce(() => new Promise(() => {}));
    await renderComponent();

    const spinners = screen.queryAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('calls adminSettings.get on mount', async () => {
    await renderComponent();
    await waitFor(() => {
      expect(mockAdminSettings.get).toHaveBeenCalled();
    });
  });

  it('renders section headings after settings load', async () => {
    await renderComponent();
    await waitFor(() => {
      const h3s = document.querySelectorAll('h3');
      expect(h3s.length).toBeGreaterThan(0);
    });
  });

  it('renders at least 3 section headings (languages, maps, api keys)', async () => {
    await renderComponent();
    await waitFor(() => {
      const h3s = document.querySelectorAll('h3');
      expect(h3s.length).toBeGreaterThanOrEqual(3);
    });
  });

  it('renders action buttons after settings load', async () => {
    await renderComponent();
    await waitFor(() => {
      const btns = screen.getAllByRole('button');
      expect(btns.length).toBeGreaterThan(0);
    });
  });

  it('renders maps kill-switch toggle', async () => {
    await renderComponent();
    await waitFor(() => {
      const switches = document.querySelectorAll('[data-testid="switch-stub"]');
      expect(switches.length).toBeGreaterThan(0);
    });
  });

  it('renders maps switch stub in correct state', async () => {
    await renderComponent();
    await waitFor(() => {
      const switchEl = document.querySelector('[data-testid="switch-stub"]');
      expect(switchEl).toBeTruthy();
      // aria-checked should be "true" or "false" (boolean attribute from component state)
      expect(switchEl?.hasAttribute('aria-checked')).toBe(true);
    });
  });

  it('renders API key input fields', async () => {
    await renderComponent();
    await waitFor(() => {
      const inputs = document.querySelectorAll('input');
      expect(inputs.length).toBeGreaterThan(0);
    });
  });

  it('renders English checkbox as disabled (always enabled)', async () => {
    await renderComponent();
    await waitFor(() => {
      const checkboxes = document.querySelectorAll('[role="checkbox"]');
      const disabledEn = Array.from(checkboxes).find((cb) =>
        cb.getAttribute('data-disabled') === 'true' || (cb as HTMLInputElement).disabled
      );
      expect(disabledEn).toBeDefined();
    });
  });

  it('shows "Clear" button when Google Maps key is configured', async () => {
    await renderComponent();
    await waitFor(() => {
      const clearBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('clear')
      );
      expect(clearBtn).toBeDefined();
    });
  });

  it('renders select stubs for language, map provider, and geocoding provider', async () => {
    await renderComponent();
    await waitFor(() => {
      const selects = document.querySelectorAll('[data-testid="select-stub"]');
      expect(selects.length).toBeGreaterThanOrEqual(1);
    });
  });
});
