// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for TenantContext
 * Covers: initial/loading state, data fetching, feature/module gates, tenantPath, error handling
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { ReactNode } from 'react';
import { HeroUIProvider } from '@heroui/react';

vi.mock('framer-motion');
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (k: string) => k }),
}));

// Mock i18n
vi.mock('@/i18n', () => ({
  default: {
    changeLanguage: vi.fn(),
    language: 'en',
  },
}));

// Mock Sentry helpers
vi.mock('@/lib/sentry', () => ({
  setSentryUser: vi.fn(),
  captureAuthEvent: vi.fn(),
  setSentryTenant: vi.fn(),
}));

// Mock api-validation
vi.mock('@/lib/api-validation', () => ({
  validateResponseIfPresent: vi.fn(),
}));

// Mock api-schemas
vi.mock('@/lib/api-schemas', () => ({
  tenantBootstrapSchema: {},
}));

// Mock logger
vi.mock('@/lib/logger', () => ({
  logWarn: vi.fn(),
  logError: vi.fn(),
}));

// Mock tenant routing utilities
vi.mock('@/lib/tenant-routing', () => ({
  detectTenantFromUrl: vi.fn(() => ({ slug: null, source: null })),
  tenantPath: vi.fn((path: string, slug: string | null) => {
    if (!slug) return path;
    const normalized = path.startsWith('/') ? path : '/' + path;
    return '/' + slug + normalized;
  }),
}));

// API mock
const mockApiGet = vi.fn();
const mockApiClearInflight = vi.fn();
const mockFetchCsrfToken = vi.fn().mockResolvedValue(undefined);
const mockTokenManager = {
  getTenantId: vi.fn().mockReturnValue(null),
  setTenantId: vi.fn(),
};

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockApiGet(...args),
    clearInflightRequests: () => mockApiClearInflight(),
  },
  tokenManager: mockTokenManager,
  fetchCsrfToken: () => mockFetchCsrfToken(),
}));

import { TenantProvider, useTenant } from '../TenantContext';
import { detectTenantFromUrl } from '@/lib/tenant-routing';

const mockDetectTenantFromUrl = vi.mocked(detectTenantFromUrl);

// ─────────────────────────────────────────────────────────────────────────────
// Fixtures
// ─────────────────────────────────────────────────────────────────────────────

const mockTenantConfig = {
  id: 2,
  name: 'Hour Timebank',
  slug: 'hour-timebank',
  tagline: 'Exchange time in your community',
  features: {
    events: true,
    groups: true,
    gamification: false,
    goals: true,
    blog: true,
    resources: true,
    volunteering: true,
    exchange_workflow: true,
    organisations: true,
    federation: false,
    connections: true,
    reviews: true,
    polls: true,
    job_vacancies: false,
    ideation_challenges: false,
    direct_messaging: true,
    group_exchanges: true,
    search: true,
    ai_chat: true,
  },
  modules: {
    feed: true,
    listings: true,
    messages: true,
    wallet: true,
    notifications: true,
    profile: true,
    settings: true,
    dashboard: true,
  },
  branding: {
    primaryColor: '#4f46e5',
    secondaryColor: '#7c3aed',
  },
  supported_languages: ['en', 'ga'],
  default_language: 'en',
};

// ─────────────────────────────────────────────────────────────────────────────
// Wrappers
// ─────────────────────────────────────────────────────────────────────────────

function wrapper({ children }: { children: ReactNode }) {
  return <HeroUIProvider>{children}</HeroUIProvider>;
}

function tenantWrapper({ children }: { children: ReactNode }) {
  return (
    <HeroUIProvider>
      <TenantProvider>{children}</TenantProvider>
    </HeroUIProvider>
  );
}

function tenantWrapperWithSlug(slug: string) {
  return function SlugWrapper({ children }: { children: ReactNode }) {
    return (
      <HeroUIProvider>
        <TenantProvider tenantSlug={slug}>{children}</TenantProvider>
      </HeroUIProvider>
    );
  };
}

describe('TenantContext', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockDetectTenantFromUrl.mockReturnValue({ slug: null, source: null });
    mockTokenManager.getTenantId.mockReturnValue(null);
    mockApiGet.mockResolvedValue({ success: true, data: mockTenantConfig });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Hook guard
  // ─────────────────────────────────────────────────────────────────────────

  describe('useTenant() outside provider', () => {
    it('throws a descriptive error when used outside TenantProvider', () => {
      expect(() => {
        renderHook(() => useTenant(), { wrapper });
      }).toThrowError('useTenant must be used within a TenantProvider');
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Loading state
  // ─────────────────────────────────────────────────────────────────────────

  describe('loading state', () => {
    it('starts with isLoading true before bootstrap API responds', async () => {
      let resolveApi!: (v: unknown) => void;
      mockApiGet.mockReturnValue(new Promise((r) => { resolveApi = r; }));

      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });

      // Initially loading
      expect(result.current.isLoading).toBe(true);
      expect(result.current.tenant).toBeNull();

      resolveApi({ success: true, data: mockTenantConfig });
      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });
    });

    it('sets isLoading false after tenant data loads', async () => {
      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });
      expect(result.current.tenant).not.toBeNull();
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Tenant data fetching
  // ─────────────────────────────────────────────────────────────────────────

  describe('tenant data fetching', () => {
    it('fetches tenant bootstrap data from /v2/tenant/bootstrap on mount', async () => {
      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });

      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(mockApiGet).toHaveBeenCalledWith(
        '/v2/tenant/bootstrap',
        expect.objectContaining({ skipAuth: true, skipTenant: true })
      );
    });

    it('appends ?slug= parameter when a tenant slug is provided via prop', async () => {
      const { result } = renderHook(() => useTenant(), {
        wrapper: tenantWrapperWithSlug('hour-timebank'),
      });

      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(mockApiGet).toHaveBeenCalledWith(
        '/v2/tenant/bootstrap?slug=hour-timebank',
        expect.anything()
      );
    });

    it('populates tenant data including name and slug after successful fetch', async () => {
      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });

      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(result.current.tenant?.name).toBe('Hour Timebank');
      expect(result.current.tenant?.slug).toBe('hour-timebank');
    });

    it('stores tenant ID in tokenManager after bootstrap', async () => {
      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });

      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(mockTokenManager.setTenantId).toHaveBeenCalledWith(2);
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Feature flags
  // ─────────────────────────────────────────────────────────────────────────

  describe('hasFeature()', () => {
    it('returns true for a feature that is enabled in tenant config', async () => {
      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(result.current.hasFeature('events')).toBe(true);
      expect(result.current.hasFeature('groups')).toBe(true);
    });

    it('returns false for a feature that is explicitly disabled in tenant config', async () => {
      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      // gamification is false in mockTenantConfig
      expect(result.current.hasFeature('gamification')).toBe(false);
      // federation is false in mockTenantConfig
      expect(result.current.hasFeature('federation')).toBe(false);
    });

    it('falls back to default (true) when tenant has no feature config', async () => {
      const tenantWithoutFeatures = { ...mockTenantConfig, features: undefined };
      mockApiGet.mockResolvedValue({ success: true, data: tenantWithoutFeatures });

      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      // All features default to true per TenantFeatureConfig::FEATURE_DEFAULTS
      expect(result.current.hasFeature('events')).toBe(true);
      expect(result.current.hasFeature('gamification')).toBe(true);
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Module flags
  // ─────────────────────────────────────────────────────────────────────────

  describe('hasModule()', () => {
    it('returns true for an enabled module', async () => {
      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(result.current.hasModule('wallet')).toBe(true);
      expect(result.current.hasModule('messages')).toBe(true);
    });

    it('returns false for a disabled module', async () => {
      const tenantWithDisabledWallet = {
        ...mockTenantConfig,
        modules: { ...mockTenantConfig.modules, wallet: false },
      };
      mockApiGet.mockResolvedValue({ success: true, data: tenantWithDisabledWallet });

      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(result.current.hasModule('wallet')).toBe(false);
    });

    it('falls back to default modules (all enabled) when tenant has no module config', async () => {
      const tenantWithoutModules = { ...mockTenantConfig, modules: undefined };
      mockApiGet.mockResolvedValue({ success: true, data: tenantWithoutModules });

      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(result.current.hasModule('wallet')).toBe(true);
      expect(result.current.hasModule('feed')).toBe(true);
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // tenantPath
  // ─────────────────────────────────────────────────────────────────────────

  describe('tenantPath()', () => {
    it('prepends tenant slug when a slug prop is provided', async () => {
      const { result } = renderHook(() => useTenant(), {
        wrapper: tenantWrapperWithSlug('hour-timebank'),
      });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(result.current.tenantPath('/listings')).toBe('/hour-timebank/listings');
    });

    it('returns path unchanged when no tenant slug is present', async () => {
      mockDetectTenantFromUrl.mockReturnValue({ slug: null, source: null });

      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(result.current.tenantPath('/dashboard')).toBe('/dashboard');
    });

    it('uses slug detected from URL via detectTenantFromUrl', async () => {
      mockDetectTenantFromUrl.mockReturnValue({ slug: 'my-bank', source: 'path' });

      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(result.current.tenantPath('/events')).toBe('/my-bank/events');
    });

    it('exposes the effective tenantSlug in context', async () => {
      const { result } = renderHook(() => useTenant(), {
        wrapper: tenantWrapperWithSlug('hour-timebank'),
      });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(result.current.tenantSlug).toBe('hour-timebank');
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Error state
  // ─────────────────────────────────────────────────────────────────────────

  describe('error handling', () => {
    it('sets error state when bootstrap API returns success false', async () => {
      mockApiGet.mockResolvedValue({
        success: false,
        error: 'Tenant not found',
      });

      const { result } = renderHook(() => useTenant(), {
        wrapper: tenantWrapperWithSlug('unknown-tenant'),
      });

      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(result.current.tenant).toBeNull();
      expect(result.current.error).toBe('Tenant not found');
    });

    it('sets notFoundSlug when a named slug fails to resolve', async () => {
      mockApiGet.mockResolvedValue({
        success: false,
        error: 'Not found',
      });

      const { result } = renderHook(() => useTenant(), {
        wrapper: tenantWrapperWithSlug('ghost-timebank'),
      });

      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(result.current.notFoundSlug).toBe('ghost-timebank');
    });

    it('captures thrown errors and sets error state without crashing', async () => {
      mockApiGet.mockRejectedValue(new Error('Network failure'));

      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });

      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(result.current.tenant).toBeNull();
      expect(result.current.error).toBe('Network failure');
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Supported languages
  // ─────────────────────────────────────────────────────────────────────────

  describe('language support', () => {
    it('returns tenant supported_languages from bootstrap data', async () => {
      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(result.current.supportedLanguages).toEqual(['en', 'ga']);
    });

    it('falls back to [en, ga] when no supported_languages in tenant data', async () => {
      const tenantNoLangs = { ...mockTenantConfig, supported_languages: undefined };
      mockApiGet.mockResolvedValue({ success: true, data: tenantNoLangs });

      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(result.current.supportedLanguages).toEqual(['en', 'ga']);
    });

    it('exposes defaultLanguage from tenant data', async () => {
      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(result.current.defaultLanguage).toBe('en');
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // refreshTenant
  // ─────────────────────────────────────────────────────────────────────────

  describe('refreshTenant()', () => {
    it('re-fetches tenant data and updates state', async () => {
      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      const updatedTenant = { ...mockTenantConfig, name: 'Updated Timebank' };
      mockApiGet.mockResolvedValueOnce({ success: true, data: updatedTenant });

      await act(async () => {
        await result.current.refreshTenant();
      });

      expect(result.current.tenant?.name).toBe('Updated Timebank');
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Branding
  // ─────────────────────────────────────────────────────────────────────────

  describe('branding', () => {
    it('merges tenant branding with defaults', async () => {
      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(result.current.branding.primaryColor).toBe('#4f46e5');
      expect(result.current.branding.name).toBe('Hour Timebank'); // top-level tenant.name wins
    });

    it('falls back to default branding when tenant has no branding config', async () => {
      const tenantNoBranding = { ...mockTenantConfig, branding: undefined };
      mockApiGet.mockResolvedValue({ success: true, data: tenantNoBranding });

      const { result } = renderHook(() => useTenant(), { wrapper: tenantWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(result.current.branding.primaryColor).toBe('#6366f1'); // default
    });
  });
});
