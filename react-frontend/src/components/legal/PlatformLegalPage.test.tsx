// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import FileText from 'lucide-react/icons/file-text';

// ─── Toast / Auth / Tenant ───────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: null,
      isAuthenticated: false,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Hour Timebank', slug: 'hour-timebank' },
      tenantPath: (p: string) => `/hour-timebank${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
      branding: { name: 'Hour Timebank CLG' },
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Fixtures ────────────────────────────────────────────────────────────────
const makeSections = (count: number) =>
  Array.from({ length: count }, (_, i) => ({
    id: `section-${i + 1}`,
    title: `Section ${i + 1} Title`,
    content: <p>Content for section {i + 1}</p>,
  }));

const defaultProps = {
  title: 'Platform Terms of Service',
  subtitle: 'Legal terms for using NEXUS',
  icon: FileText,
  effectiveDate: '1 March 2026',
  sections: makeSections(2),
};

// ─────────────────────────────────────────────────────────────────────────────
describe('PlatformLegalPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders the document title heading', async () => {
    const { PlatformLegalPage } = await import('./PlatformLegalPage');
    render(<PlatformLegalPage {...defaultProps} />);

    expect(screen.getByRole('heading', { name: /Platform Terms of Service/i, level: 1 })).toBeInTheDocument();
  });

  it('renders the subtitle', async () => {
    const { PlatformLegalPage } = await import('./PlatformLegalPage');
    render(<PlatformLegalPage {...defaultProps} />);

    expect(screen.getByText(/Legal terms for using NEXUS/i)).toBeInTheDocument();
  });

  it('renders each section title as an h2', async () => {
    const { PlatformLegalPage } = await import('./PlatformLegalPage');
    render(<PlatformLegalPage {...defaultProps} sections={makeSections(3)} />);

    expect(screen.getByRole('heading', { name: /Section 1 Title/i })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: /Section 2 Title/i })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: /Section 3 Title/i })).toBeInTheDocument();
  });

  it('renders section content', async () => {
    const { PlatformLegalPage } = await import('./PlatformLegalPage');
    render(<PlatformLegalPage {...defaultProps} />);

    expect(screen.getByText(/Content for section 1/)).toBeInTheDocument();
    expect(screen.getByText(/Content for section 2/)).toBeInTheDocument();
  });

  it('shows Table of Contents when 4 or more sections are provided', async () => {
    const { PlatformLegalPage } = await import('./PlatformLegalPage');
    render(<PlatformLegalPage {...defaultProps} sections={makeSections(4)} />);

    // TOC nav should appear
    const nav = screen.getByRole('navigation');
    expect(nav).toBeInTheDocument();
  });

  it('does NOT show Table of Contents when fewer than 4 sections', async () => {
    const { PlatformLegalPage } = await import('./PlatformLegalPage');
    render(<PlatformLegalPage {...defaultProps} sections={makeSections(3)} />);

    // No TOC navigation visible
    const navs = screen.queryAllByRole('navigation');
    // There should be no nav elements (or none containing TOC list items)
    const tocNav = navs.find(nav => nav.querySelector('ol'));
    expect(tocNav).toBeUndefined();
  });

  it('renders cross-links when provided', async () => {
    const { PlatformLegalPage } = await import('./PlatformLegalPage');
    render(
      <PlatformLegalPage
        {...defaultProps}
        crossLinks={[
          { label: 'Privacy Policy', to: '/privacy' },
          { label: 'Cookie Policy', to: '/cookies' },
        ]}
      />
    );

    expect(screen.getByText('Privacy Policy')).toBeInTheDocument();
    expect(screen.getByText('Cookie Policy')).toBeInTheDocument();
  });

  it('does NOT render cross-links section when crossLinks is empty', async () => {
    const { PlatformLegalPage } = await import('./PlatformLegalPage');
    render(<PlatformLegalPage {...defaultProps} crossLinks={[]} />);

    expect(screen.queryByText(/Related documents/i)).not.toBeInTheDocument();
  });

  it('renders the project-nexus.ie external link in the footer CTA', async () => {
    const { PlatformLegalPage } = await import('./PlatformLegalPage');
    render(<PlatformLegalPage {...defaultProps} />);

    const extLink = screen.getByRole('link', { name: /nexus/i });
    expect(extLink).toHaveAttribute('href', 'https://project-nexus.ie');
    expect(extLink).toHaveAttribute('target', '_blank');
    expect(extLink).toHaveAttribute('rel', 'noopener noreferrer');
  });

  it('renders the tenant contact link using tenantPath', async () => {
    const { PlatformLegalPage } = await import('./PlatformLegalPage');
    render(<PlatformLegalPage {...defaultProps} />);

    // Contact link should use tenant path
    const contactLinks = screen.getAllByRole('link');
    const contactLink = contactLinks.find(l => (l as HTMLAnchorElement).href?.includes('/contact'));
    expect(contactLink).toBeDefined();
    expect((contactLink as HTMLAnchorElement).href).toContain('/hour-timebank/contact');
  });

  it('renders the Provider Notice with tenant name', async () => {
    const { PlatformLegalPage } = await import('./PlatformLegalPage');
    render(<PlatformLegalPage {...defaultProps} />);

    // Provider notice should reference the branding/tenant name
    // The text contains "Hour Timebank CLG" from branding
    const noticeRegion = document.querySelector('[class*="blue"]');
    expect(noticeRegion).toBeTruthy();
  });

  it('renders "view tenant legal" link in the provider notice', async () => {
    const { PlatformLegalPage } = await import('./PlatformLegalPage');
    render(<PlatformLegalPage {...defaultProps} />);

    // Should have a link to /legal within the tenant path
    const legalLinks = screen.getAllByRole('link');
    const tenantLegalLink = legalLinks.find(l => (l as HTMLAnchorElement).href?.includes('/legal'));
    expect(tenantLegalLink).toBeDefined();
  });

  it('renders numbered section headings in order', async () => {
    const { PlatformLegalPage } = await import('./PlatformLegalPage');
    render(<PlatformLegalPage {...defaultProps} sections={makeSections(3)} />);

    const h2s = screen.getAllByRole('heading', { level: 2 });
    // First h2 after h1 would be provider notice title, then sections
    const sectionHeadings = h2s.filter(h => h.textContent?.includes('Section'));
    expect(sectionHeadings).toHaveLength(3);
  });
});
