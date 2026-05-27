// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { describe, expect, it } from 'vitest';

import { legalPages } from '../data/legal';

describe('sales legal content', () => {
  it('publishes the expected provider legal page set', () => {
    expect(legalPages.map((page) => page.path)).toEqual([
      '/legal/terms',
      '/legal/privacy',
      '/legal/cookies',
      '/legal/acceptable-use',
      '/legal/data-processing',
    ]);
  });

  it('links the legal page set from the sales-site footer', () => {
    const siteShell = readFileSync(resolve(__dirname, '..', 'components', 'SiteShell.tsx'), 'utf8');

    expect(siteShell).toContain('title="Legal"');
    expect(siteShell).toContain('legalPages.map');
    expect(siteShell).toContain('nativeInternalLinks onNavigate={handleInternalNav}');
    expect(siteShell).toContain('onClick={nativeInternalLinks ? undefined : (event) => handleInternalLink(event, href)}');
  });

  it('renders internal sales-site navigation as real links with hrefs', () => {
    const siteShell = readFileSync(resolve(__dirname, '..', 'components', 'SiteShell.tsx'), 'utf8');
    const legalPage = readFileSync(resolve(__dirname, '..', 'components', 'LegalPage.tsx'), 'utf8');

    expect(siteShell).toContain('href={href}');
    expect(siteShell).toContain('nativeInternalLinks');
    expect(siteShell).toContain('onClick={nativeInternalLinks ? undefined : (event) => handleInternalLink(event, href)}');
    expect(legalPage).toContain('href={item.path}');
    expect(legalPage).toContain('onClick={(event) => handleInternalLink(event, item.path)}');
    expect(siteShell).not.toContain('<button key={href} type="button" className="text-left hover:text-white" onClick={() => onNavigate(href)}>');
    expect(legalPage).not.toContain('<button');
  });

  it('separates software authorship, managed hosting, and customer-controller responsibilities', () => {
    const content = JSON.stringify(legalPages);

    expect(content).toContain('Jasper Ford is the creator, copyright holder, and licensor');
    expect(content).toContain('PROJECT NEXUS PLATFORM IRELAND LTD');
    expect(content).toContain('Customer as controller');
    expect(content).toContain('not a substitute for a signed data processing agreement');
  });

  it('covers provider-grade privacy, cookie, and processor transparency points', () => {
    const content = JSON.stringify(legalPages);

    expect(content).toContain('Irish Data Protection Commission');
    expect(content).toContain('right to object');
    expect(content).toContain('Standard Contractual Clauses');
    expect(content).toContain('Sub-processor transparency');
    expect(content).toContain('No sale of customer tenant data');
    expect(content).toContain('Cookie register');
  });
});
