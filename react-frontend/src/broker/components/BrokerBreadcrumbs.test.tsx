// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HelmetProvider } from 'react-helmet-async';
import { createMockContexts } from '@/test/mock-contexts';

// The broker i18n namespace has breadcrumbs.* keys.
// We mock i18next so the key fallback is transparent.
vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantSlug: 'test',
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

import { BrokerBreadcrumbs } from './BrokerBreadcrumbs';

function renderAt(path: string) {
  return render(
    <HelmetProvider>
      <MemoryRouter initialEntries={[path]}>
        <BrokerBreadcrumbs />
      </MemoryRouter>
    </HelmetProvider>
  );
}

describe('BrokerBreadcrumbs', () => {
  it('renders null when there is only one path segment (no crumbs)', () => {
    // /test/broker → after stripping tenant slug → /broker → 1 segment → returns null
    const { container } = renderAt('/test/broker');
    expect(container.firstChild).toBeNull();
  });

  it('renders a <nav> when there are two or more segments', () => {
    renderAt('/test/broker/members');
    expect(screen.getByRole('navigation')).toBeInTheDocument();
  });

  it('renders a link for the non-last segment', () => {
    renderAt('/test/broker/members/vetting');
    const links = screen.getAllByRole('link');
    expect(links.length).toBeGreaterThan(0);
  });

  it('renders the final segment as plain text (not a link)', () => {
    renderAt('/test/broker/members');
    // "members" is the last segment — should appear as a span, not a link
    const links = screen.queryAllByRole('link');
    // There should be one link ("Dashboard" for broker) and no link for "members"
    const memberLink = links.find((l) => /members/i.test(l.textContent || ''));
    expect(memberLink).toBeUndefined();
    expect(screen.getByText(/members/i)).toBeInTheDocument();
  });

  it('skips purely numeric segments', () => {
    // /test/broker/members/42 → numeric 42 should be skipped
    renderAt('/test/broker/members/42');
    expect(screen.queryByText('42')).not.toBeInTheDocument();
  });

  it('capitalises unknown segment labels', () => {
    renderAt('/test/broker/customsection');
    expect(screen.getByText('Customsection')).toBeInTheDocument();
  });

  it('converts hyphenated segments to spaced labels', () => {
    // risk-tags is in SEGMENT_LABELS but any unknown hyphenated segment also works
    renderAt('/test/broker/unknown-segment');
    expect(screen.getByText('Unknown segment')).toBeInTheDocument();
  });
});
