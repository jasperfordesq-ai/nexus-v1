// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Accessibility regression tests for the 5 critical Caring Community pages
 * covered by the AG39 a11y audit. Each page is rendered in isolation with
 * mocked contexts/hooks/api and run through axe-core. The suite asserts
 * ZERO serious or critical violations — moderate/minor violations are
 * surfaced (logged) but do not fail the test, so the audit baseline is
 * preserved without blocking unrelated work.
 *
 * Pages covered (under react-frontend/src/pages/caring-community/):
 *   1. CaringCommunityPage         (the hub)
 *   2. RequestHelpPage
 *   3. OfferFavourPage
 *   4. MySupportRelationshipsPage
 *   5. SafeguardingReportPage
 *
 * Run locally:
 *   cd react-frontend
 *   npm test -- src/pages/caring-community/__tests__/caring-community.a11y.test.tsx
 *
 * Or via the convenience npm script:
 *   npm run test:a11y
 *
 * NOTE: not wired into CI / pre-push hooks per project policy
 * (Husky hooks are intentionally disabled). Run manually before PRs that
 * touch caring-community pages.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@testing-library/react';
import { axe } from 'vitest-axe';
import type { AxeResults, Result } from 'axe-core';
import { HeroUIProvider } from '@heroui/react';
import { MemoryRouter } from 'react-router-dom';

// ─── Mocks ────────────────────────────────────────────────────────────────────

// framer-motion: strip motion-only props so axe sees plain DOM elements
vi.mock('framer-motion', () => {
  const passthrough = (Tag: string) => ({ children, ...props }: Record<string, unknown>) => {
    const motionKeys = new Set([
      'variants', 'initial', 'animate', 'exit', 'transition',
      'whileHover', 'whileTap', 'whileInView', 'layout', 'layoutId',
      'viewport', 'drag', 'dragConstraints', 'dragElastic', 'dragMomentum',
    ]);
    const clean: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(props)) if (!motionKeys.has(k)) clean[k] = v;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    return <Tag {...(clean as any)}>{children as React.ReactNode}</Tag>;
  };
  return {
    motion: new Proxy({}, { get: (_t, prop: string) => passthrough(prop) }),
    AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
    useAnimation: () => ({ start: () => Promise.resolve() }),
    useInView: () => true,
  };
});

// PageMeta is a side-effect-only component; suppress it so axe doesn't see Helmet output
vi.mock('@/components/seo', () => ({ PageMeta: () => null }));

// usePageTitle is a no-op for tests
vi.mock('@/hooks', async () => {
  const actual = await vi.importActual<typeof import('@/hooks')>('@/hooks');
  return { ...actual, usePageTitle: vi.fn() };
});

// useApi: return a stable empty/loaded shape so MySupportRelationshipsPage renders
vi.mock('@/hooks/useApi', () => ({
  useApi: <T,>() => ({
    data: [] as unknown as T,
    isLoading: false,
    error: null as string | null,
    meta: null,
    execute: vi.fn(),
    refetch: vi.fn(),
    reset: vi.fn(),
    setData: vi.fn(),
    loading: false,
  }),
}));

// api client: every call resolves to an empty success
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ data: null }),
    post: vi.fn().mockResolvedValue({ data: { ok: true } }),
    put: vi.fn().mockResolvedValue({ data: null }),
    patch: vi.fn().mockResolvedValue({ data: null }),
    delete: vi.fn().mockResolvedValue({ data: null }),
    upload: vi.fn().mockResolvedValue({ data: null }),
  },
}));

// Tenant + auth contexts: every feature & module enabled, neutral branding
vi.mock('@/contexts', async () => {
  const actual = await vi.importActual<typeof import('@/contexts')>('@/contexts');
  return {
    ...actual,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Timebank', slug: 'test-timebank' },
      tenantSlug: 'test-timebank',
      tenantPath: (path: string) => `/test-timebank${path}`,
      hasFeature: () => true,
      hasModule: () => true,
      features: {},
      modules: {},
      branding: { name: 'Test Timebank' },
      isLoading: false,
      error: null,
      notFoundSlug: null,
      refreshTenant: () => Promise.resolve(),
    }),
    useFeature: () => true,
    useModule: () => true,
    useAuth: () => ({
      user: { id: 1, email: 'tester@example.com', display_name: 'Tester' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'authenticated',
      error: null,
    }),
    useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
    useCookieConsent: () => ({
      consent: null,
      showBanner: false,
      openPreferences: vi.fn(),
      resetConsent: vi.fn(),
      saveConsent: vi.fn(),
      hasConsent: () => true,
      updateConsent: vi.fn(),
    }),
  };
});

// ─── Imports under test (after mocks) ────────────────────────────────────────

import { CaringCommunityPage } from '../CaringCommunityPage';
import { RequestHelpPage } from '../RequestHelpPage';
import { OfferFavourPage } from '../OfferFavourPage';
import { MySupportRelationshipsPage } from '../MySupportRelationshipsPage';
import SafeguardingReportPage from '../SafeguardingReportPage';

// ─── Helpers ─────────────────────────────────────────────────────────────────

function renderWithProviders(ui: React.ReactElement) {
  return render(
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test-timebank/caring-community']}>
        {ui}
      </MemoryRouter>
    </HeroUIProvider>,
  );
}

/**
 * Assert zero `serious` and `critical` axe violations. Lower-impact
 * violations (`moderate`, `minor`) are logged but don't fail — they should
 * be triaged separately rather than blocking unrelated PRs.
 */
function assertNoSeriousViolations(results: AxeResults, label: string) {
  const violations = results.violations as Result[];
  const blocking = violations.filter(
    (v) => v.impact === 'serious' || v.impact === 'critical',
  );
  const nonBlocking = violations.filter(
    (v) => v.impact !== 'serious' && v.impact !== 'critical',
  );

  if (nonBlocking.length > 0) {
    // eslint-disable-next-line no-console
    console.info(
      `[a11y][${label}] ${nonBlocking.length} non-blocking violation(s):`,
      nonBlocking.map((v) => `${v.id} (${v.impact})`).join(', '),
    );
  }

  if (blocking.length > 0) {
    const summary = blocking
      .map((v) => `  - ${v.id} [${v.impact}]: ${v.help} (${v.nodes.length} node(s))`)
      .join('\n');
    throw new Error(
      `[a11y][${label}] ${blocking.length} serious/critical violation(s):\n${summary}`,
    );
  }

  expect(blocking).toEqual([]);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('Caring Community a11y regression (AG39 baseline)', () => {
  beforeEach(() => {
    // Reset onboarding modal state for CaringCommunityPage so it renders the
    // hub content and not the open modal (modals can introduce focus-trap
    // axe noise that has its own dedicated tests).
    try {
      window.localStorage.setItem('cc.onboarding.choice.v1', 'browse');
    } catch {
      // jsdom: ignore
    }
  });

  it('CaringCommunityPage (hub) has no serious/critical violations', async () => {
    const { container } = renderWithProviders(<CaringCommunityPage />);
    const results = await axe(container);
    assertNoSeriousViolations(results, 'CaringCommunityPage');
  });

  it('RequestHelpPage has no serious/critical violations', async () => {
    const { container } = renderWithProviders(<RequestHelpPage />);
    const results = await axe(container);
    assertNoSeriousViolations(results, 'RequestHelpPage');
  });

  it('OfferFavourPage has no serious/critical violations', async () => {
    const { container } = renderWithProviders(<OfferFavourPage />);
    const results = await axe(container);
    assertNoSeriousViolations(results, 'OfferFavourPage');
  });

  it('MySupportRelationshipsPage has no serious/critical violations', async () => {
    const { container } = renderWithProviders(<MySupportRelationshipsPage />);
    const results = await axe(container);
    assertNoSeriousViolations(results, 'MySupportRelationshipsPage');
  });

  it('SafeguardingReportPage has no serious/critical violations', async () => {
    const { container } = renderWithProviders(<SafeguardingReportPage />);
    const results = await axe(container);
    assertNoSeriousViolations(results, 'SafeguardingReportPage');
  });
});
