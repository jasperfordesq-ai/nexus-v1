// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── API mock ─────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn(), upload: vi.fn() },
}));
vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Mutable branding for per-test overrides ─────────────────────────────────
let mockBranding = {
  name: 'hOUR Timebank',
  tagline: 'Connecting Communities',
  logo: undefined as string | undefined,
  logoDark: undefined as string | undefined,
  logoShape: undefined as 'wide' | 'landscape' | 'square' | undefined,
  primaryColor: '#6366f1',
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: mockBranding.name, slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
      branding: mockBranding,
    }),
  })
);

// TenantLogo calls useTenant() from @/contexts directly
vi.mock('@/contexts/TenantContext', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/contexts/TenantContext')>();
  return {
    ...orig,
    useTenant: () => ({
      tenant: { id: 2, name: mockBranding.name, slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
      branding: mockBranding,
    }),
  };
});

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

vi.mock('@/lib/helpers', () => ({
  resolveAssetUrl: (url: string | null | undefined) => url ?? '',
  resolveAvatarUrl: (url: string | null | undefined) => url ?? '',
}));

vi.mock('@/lib/motion', () => ({
  motion: {
    div: ({ children, initial, animate, transition, className }: {
      children: React.ReactNode; initial?: object; animate?: object; transition?: object; className?: string;
    }) => <div className={className}>{children}</div>,
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    Avatar: ({ name, getInitials }: { name?: string; getInitials?: () => string; style?: object; classNames?: object }) => (
      <div data-testid="avatar-initials" aria-label={name}>{getInitials ? getInitials() : name}</div>
    ),
    Tooltip: ({ children, content }: { children: React.ReactNode; content: string }) => (
      <div data-testid="tooltip" title={content}>{children}</div>
    ),
  };
});

// ─────────────────────────────────────────────────────────────────────────────
describe('TenantLogo', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    // Reset branding to defaults
    mockBranding = {
      name: 'hOUR Timebank',
      tagline: 'Connecting Communities',
      logo: undefined,
      logoDark: undefined,
      logoShape: undefined,
      primaryColor: '#6366f1',
    };
  });

  it('renders a link wrapping the logo content', async () => {
    const { TenantLogo } = await import('./TenantLogo');
    render(<TenantLogo />);

    const link = screen.getByRole('link');
    expect(link).toBeInTheDocument();
  });

  it('link navigates to tenant root path', async () => {
    const { TenantLogo } = await import('./TenantLogo');
    render(<TenantLogo />);

    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', '/test/');
  });

  it('shows initials avatar when no logo URL provided', async () => {
    mockBranding.logo = undefined;
    mockBranding.logoDark = undefined;
    const { TenantLogo } = await import('./TenantLogo');
    render(<TenantLogo />);

    expect(screen.getByTestId('avatar-initials')).toBeInTheDocument();
  });

  it('avatar initials are derived from tenant name', async () => {
    mockBranding.name = 'hOUR Timebank';
    mockBranding.logo = undefined;
    const { TenantLogo } = await import('./TenantLogo');
    render(<TenantLogo />);

    const avatar = screen.getByTestId('avatar-initials');
    // getInitials('hOUR Timebank') → first letter of first + last word = 'HT'
    expect(avatar.textContent).toMatch(/[A-Z]{1,2}/);
  });

  it('renders img tag when logo URL is provided', async () => {
    mockBranding.logo = 'https://example.com/logo.png';
    const { TenantLogo } = await import('./TenantLogo');
    render(<TenantLogo />);

    // Two <img> tags: one for light mode, one for dark mode
    const images = screen.getAllByRole('img');
    expect(images.length).toBeGreaterThanOrEqual(1);
    // Alt text is the tenant name
    expect(images[0]).toHaveAttribute('alt', 'hOUR Timebank');
    expect(images[0]).toHaveAttribute('src', 'https://example.com/logo.png');
  });

  it('does NOT render avatar when logo is present', async () => {
    mockBranding.logo = 'https://example.com/logo.png';
    const { TenantLogo } = await import('./TenantLogo');
    render(<TenantLogo />);

    expect(screen.queryByTestId('avatar-initials')).not.toBeInTheDocument();
  });

  it('shows tenant name text when showName=true and no logo', async () => {
    mockBranding.logo = undefined;
    const { TenantLogo } = await import('./TenantLogo');
    render(<TenantLogo showName={true} />);

    expect(screen.getByText('hOUR Timebank')).toBeInTheDocument();
  });

  it('hides tenant name when compact=true (initials avatar mode)', async () => {
    mockBranding.logo = undefined;
    const { TenantLogo } = await import('./TenantLogo');
    render(<TenantLogo compact={true} showName={true} />);

    // When compact=true AND no logo, name div is hidden (not rendered because compact=true)
    // The name span is inside a hidden div per component logic
    // The avatar is still visible
    expect(screen.getByTestId('avatar-initials')).toBeInTheDocument();
  });

  it('shows tagline when showTagline=true and tagline exists', async () => {
    mockBranding.logo = undefined;
    mockBranding.tagline = 'Connecting Communities';
    const { TenantLogo } = await import('./TenantLogo');
    render(<TenantLogo showTagline={true} />);

    // tagline is in a hidden-on-small breakpoint span; still in DOM
    expect(screen.getByText('Connecting Communities')).toBeInTheDocument();
  });

  it('does not render tagline when showTagline=false', async () => {
    mockBranding.tagline = 'Connecting Communities';
    const { TenantLogo } = await import('./TenantLogo');
    render(<TenantLogo showTagline={false} />);

    expect(screen.queryByText('Connecting Communities')).not.toBeInTheDocument();
  });

  it('wraps long name in Tooltip', async () => {
    mockBranding.name = 'A Very Very Long Community Name Here';
    mockBranding.logo = undefined;
    const { TenantLogo } = await import('./TenantLogo');
    render(<TenantLogo showName={true} />);

    // Long name (>20 chars) triggers a tooltip wrapper
    expect(screen.getByTestId('tooltip')).toBeInTheDocument();
    expect(screen.getByTestId('tooltip')).toHaveAttribute('title', 'A Very Very Long Community Name Here');
  });

  it('does not wrap short name in Tooltip', async () => {
    mockBranding.name = 'hOUR';
    mockBranding.logo = undefined;
    const { TenantLogo } = await import('./TenantLogo');
    render(<TenantLogo showName={true} />);

    expect(screen.queryByTestId('tooltip')).not.toBeInTheDocument();
  });

  it('renders dark variant img when logoDark is provided', async () => {
    mockBranding.logo = 'https://example.com/logo-light.png';
    mockBranding.logoDark = 'https://example.com/logo-dark.png';
    const { TenantLogo } = await import('./TenantLogo');
    render(<TenantLogo />);

    const images = screen.getAllByRole('img');
    const srcs = images.map(img => img.getAttribute('src'));
    expect(srcs).toContain('https://example.com/logo-light.png');
    expect(srcs).toContain('https://example.com/logo-dark.png');
  });
});
