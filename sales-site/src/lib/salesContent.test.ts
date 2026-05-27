// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { describe, expect, it } from 'vitest';

const publicSalesFiles = [
  'src/components/HomePage.tsx',
  'src/components/FeaturesPage.tsx',
  'src/components/HostingPage.tsx',
  'src/components/QuoteBuilder.tsx',
  'src/data/pricing.ts',
].map((path) => resolve(__dirname, '..', '..', path));

describe('sales-site public content policy', () => {
  it('does not advertise round-the-clock support in page or calculator copy', () => {
    for (const file of publicSalesFiles) {
      const content = readFileSync(file, 'utf8');

      expect(content).not.toMatch(/24\s*(?:\/|x)\s*7/i);
    }
  });

  it('uses the hOUR Timebank address for hosting enquiries', () => {
    const pricingEngine = readFileSync(resolve(__dirname, 'pricingEngine.ts'), 'utf8');
    const siteShell = readFileSync(resolve(__dirname, '..', 'components', 'SiteShell.tsx'), 'utf8');
    const orderForm = readFileSync(resolve(__dirname, '..', 'components', 'OrderForm.tsx'), 'utf8');

    expect(pricingEngine).toContain('mailto:jasper@hour-timebank.ie');
    expect(siteShell).toContain('mailto:jasper@hour-timebank.ie');
    expect(orderForm).toContain('jasper@hour-timebank.ie');
    expect(`${pricingEngine}\n${siteShell}\n${orderForm}`).not.toContain('mailto:hello@project-nexus.ie');
  });

  it('uses an immersive product hero and keeps the detailed platform map visible', () => {
    const homePage = readFileSync(resolve(__dirname, '..', 'components', 'HomePage.tsx'), 'utf8');
    const styles = readFileSync(resolve(__dirname, '..', 'styles.css'), 'utf8');
    const heroStart = homePage.indexOf('<section className="sales-hero border-b border-white/10">');
    const heroEnd = homePage.indexOf('<section className="border-b border-white/10 bg-white/[0.025]">');
    const heroMarkup = homePage.slice(heroStart, heroEnd);

    expect(heroStart).toBeGreaterThan(-1);
    expect(heroEnd).toBeGreaterThan(heroStart);
    expect(heroMarkup).toContain('src="/images/nexus-logo.png"');
    expect(homePage).toContain('src="/images/nexus-banner.png"');
    expect(styles).toContain('.sales-hero');
    expect(styles).toContain('.sales-hero__image');
  });

  it('uses stacked feature catalogue sections instead of uneven module category cards', () => {
    const featuresPage = readFileSync(resolve(__dirname, '..', 'components', 'FeaturesPage.tsx'), 'utf8');

    expect(featuresPage).toContain('module-category-stack');
    expect(featuresPage).toContain('module-row-list');
    expect(featuresPage).not.toContain('className="grid module-grid gap-4"');
  });

  it('keeps the hosting page focused on pricing and order flow, not public competitor comparison cards', () => {
    const hostingPage = readFileSync(resolve(__dirname, '..', 'components', 'HostingPage.tsx'), 'utf8');

    expect(hostingPage).not.toContain('ComparisonWorkbench');
    expect(hostingPage).not.toContain('ModuleMatrix');
    expect(hostingPage).not.toContain('SourceDrawer');
    expect(hostingPage).not.toContain('selectedCompetitorId');
    expect(hostingPage).not.toContain('Compare competitors');
    expect(hostingPage).not.toContain('Made Open');
    expect(hostingPage).not.toContain('Community Timebanks benchmark');
    expect(hostingPage).not.toContain('GBP');
    expect(hostingPage).toContain('Community Edition details');
  });

  it('uses a guided hosting calculator instead of cryptic dropdowns and a raw range slider', () => {
    const quoteBuilder = readFileSync(resolve(__dirname, '..', 'components', 'QuoteBuilder.tsx'), 'utf8');

    expect(quoteBuilder).toContain('CapacityPreset');
    expect(quoteBuilder).toContain('ChoiceCardSection');
    expect(quoteBuilder).toContain('CommunityPlanCard');
    expect(quoteBuilder).toContain('ProductLineButton');
    expect(quoteBuilder).toContain('What support do you want us to provide?');
    expect(quoteBuilder).not.toContain('type="range"');
    expect(quoteBuilder).not.toContain('function SelectField');
  });

  it('advertises a cheaper but feature-limited Community Timebanking entry lane', () => {
    const pricing = readFileSync(resolve(__dirname, '..', 'data', 'pricing.ts'), 'utf8');
    const hostingPage = readFileSync(resolve(__dirname, '..', 'components', 'HostingPage.tsx'), 'utf8');
    const quoteBuilder = readFileSync(resolve(__dirname, '..', 'components', 'QuoteBuilder.tsx'), 'utf8');

    expect(pricing).toContain("id: 'community-edition'");
    expect(pricing).toContain('annualMonthlyEur: 29');
    expect(pricing).not.toContain('comparisonMonthlyGbp');
    expect(`${pricing}\n${hostingPage}\n${quoteBuilder}`).not.toContain('Made Open');
    expect(hostingPage).toContain('A cheaper way in, without cheapening the platform.');
    expect(quoteBuilder).toContain('Feature-limited on purpose.');
  });
});
