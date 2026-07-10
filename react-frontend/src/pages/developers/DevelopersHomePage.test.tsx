// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for DevelopersHomePage — static landing page for the Partner API.
 * No network calls; all content is i18n-driven from local constants.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/contexts', () => createMockContexts());

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/hooks', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/hooks')>();
  return { ...actual, usePageTitle: vi.fn() };
});

import DevelopersHomePage from './DevelopersHomePage';

describe('DevelopersHomePage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<DevelopersHomePage />);
    expect(document.body).toBeTruthy();
  });

  it('renders the h1 hero heading', () => {
    render(<DevelopersHomePage />);
    const heading = screen.getByRole('heading', { level: 1 });
    expect(heading).toBeInTheDocument();
  });

  it('renders all 4 feature cards', () => {
    render(<DevelopersHomePage />);
    // Four h3 headings correspond to the 4 feature cards.
    const featureHeadings = screen.getAllByRole('heading', { level: 3 });
    expect(featureHeadings).toHaveLength(4);
  });

  it('renders navigation links to auth, endpoints and webhooks sections', () => {
    render(<DevelopersHomePage />);
    // navLinks array has 3 entries rendered as pressable cards/links
    const links = screen.getAllByRole('link');
    const hrefs = links.map((l) => l.getAttribute('href') ?? '');
    expect(hrefs).toContain('/test/developers/auth');
    expect(hrefs).toContain('/test/developers/endpoints');
    expect(hrefs).toContain('/test/developers/webhooks');
  });

  it('renders the request-access CTA link to /contact', () => {
    render(<DevelopersHomePage />);
    const contactLinks = screen.getAllByRole('link').filter((l) =>
      (l.getAttribute('href') ?? '').includes('/contact'),
    );
    expect(contactLinks).toHaveLength(1);
    expect(contactLinks[0]).toHaveAttribute('href', '/test/contact');
  });

  it('renders an h2 for the section overview', () => {
    render(<DevelopersHomePage />);
    const h2s = screen.getAllByRole('heading', { level: 2 });
    expect(h2s.length).toBeGreaterThanOrEqual(1);
  });
});
