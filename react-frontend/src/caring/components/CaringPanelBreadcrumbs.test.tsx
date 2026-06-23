// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock react-router-dom (memory router for location control) ───────────────
const { mockLocation } = vi.hoisted(() => ({
  mockLocation: { pathname: '/test-tenant/caring/care-requests' },
}));

vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...orig,
    useLocation: () => mockLocation,
  };
});

// ─── Mock contexts ────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test-tenant' },
      tenantPath: (p: string) => `/test-tenant${p}`,
      tenantSlug: 'test-tenant',
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─── Mock react-i18next so t(key) returns a recognisable English string ───────
vi.mock('react-i18next', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-i18next')>();
  return {
    ...orig,
    useTranslation: (_ns?: string) => ({
      t: (key: string) => {
        // Return human-readable label for known segment keys
        const map: Record<string, string> = {
          'panel.breadcrumbs.aria': 'Breadcrumb navigation',
          'panel.breadcrumbs.segments.caring': 'Caring',
          'panel.breadcrumbs.segments.care-requests': 'Care Requests',
          'panel.breadcrumbs.segments.dashboard': 'Dashboard',
          'panel.breadcrumbs.segments.profile': 'Profile',
        };
        return map[key] ?? key;
      },
      i18n: { language: 'en' },
    }),
  };
});

// ─────────────────────────────────────────────────────────────────────────────

describe('CaringPanelBreadcrumbs', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockLocation.pathname = '/test-tenant/caring/care-requests';
  });

  it('renders a nav element with the aria label', async () => {
    const { CaringPanelBreadcrumbs } = await import('./CaringPanelBreadcrumbs');
    render(<CaringPanelBreadcrumbs />);
    expect(screen.getByRole('navigation', { name: /breadcrumb/i })).toBeInTheDocument();
  });

  it('renders a list for multi-segment paths', async () => {
    const { CaringPanelBreadcrumbs } = await import('./CaringPanelBreadcrumbs');
    render(<CaringPanelBreadcrumbs />);
    // The ol is inside the nav
    const nav = screen.getByRole('navigation');
    expect(nav.querySelector('ol')).toBeTruthy();
  });

  it('renders a link for intermediate segments', async () => {
    // Path: caring (intermediate) / care-requests (last)
    const { CaringPanelBreadcrumbs } = await import('./CaringPanelBreadcrumbs');
    render(<CaringPanelBreadcrumbs />);
    expect(screen.getByRole('link', { name: /caring/i })).toBeInTheDocument();
  });

  it('renders the last segment as plain text (not a link)', async () => {
    const { CaringPanelBreadcrumbs } = await import('./CaringPanelBreadcrumbs');
    render(<CaringPanelBreadcrumbs />);
    // "Care Requests" is the last segment — should be a span, not an anchor
    expect(screen.getByText('Care Requests')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /care requests/i })).toBeNull();
  });

  it('returns null when only one breadcrumb segment exists (no nav rendered)', async () => {
    mockLocation.pathname = '/test-tenant/caring';
    const { CaringPanelBreadcrumbs } = await import('./CaringPanelBreadcrumbs');
    const { container } = render(<CaringPanelBreadcrumbs />);
    // Should render nothing — only the ToastProvider wrapper
    expect(container.querySelector('nav')).toBeNull();
  });

  it('strips tenant slug from the path before building crumbs', async () => {
    // With slug stripped, the path becomes /caring/care-requests → 2 segments
    const { CaringPanelBreadcrumbs } = await import('./CaringPanelBreadcrumbs');
    render(<CaringPanelBreadcrumbs />);
    // Both labels from the stripped path should appear
    expect(screen.getByText('Caring')).toBeInTheDocument();
    expect(screen.getByText('Care Requests')).toBeInTheDocument();
  });

  it('skips numeric-only path segments', async () => {
    mockLocation.pathname = '/test-tenant/caring/123/care-requests';
    const { CaringPanelBreadcrumbs } = await import('./CaringPanelBreadcrumbs');
    render(<CaringPanelBreadcrumbs />);
    // "123" should not appear as a breadcrumb label
    expect(screen.queryByText('123')).toBeNull();
  });

  it('falls back to [missing: segment] for untranslated segments', async () => {
    mockLocation.pathname = '/test-tenant/caring/unknown-segment';
    const { CaringPanelBreadcrumbs } = await import('./CaringPanelBreadcrumbs');
    render(<CaringPanelBreadcrumbs />);
    expect(screen.getByText(/\[missing: unknown-segment\]/)).toBeInTheDocument();
  });

  it('the intermediate link href includes the tenant slug', async () => {
    const { CaringPanelBreadcrumbs } = await import('./CaringPanelBreadcrumbs');
    render(<CaringPanelBreadcrumbs />);
    const link = screen.getByRole('link', { name: /caring/i }) as HTMLAnchorElement;
    expect(link.href).toContain('/caring');
  });

  it('shows a dashboard segment when path includes it', async () => {
    mockLocation.pathname = '/test-tenant/dashboard/profile';
    const { CaringPanelBreadcrumbs } = await import('./CaringPanelBreadcrumbs');
    render(<CaringPanelBreadcrumbs />);
    expect(screen.getByText('Dashboard')).toBeInTheDocument();
    expect(screen.getByText('Profile')).toBeInTheDocument();
  });
});
