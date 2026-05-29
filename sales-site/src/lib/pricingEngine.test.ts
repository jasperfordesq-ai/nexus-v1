// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import {
  estimateQuote,
  formatCurrency,
  recommendCommunityTimebankPlan,
  recommendHostingPlan,
} from './pricingEngine';

describe('pricingEngine', () => {
  it('recommends the smallest hosting tier that covers the active member count', () => {
    expect(recommendHostingPlan(100).id).toBe('spark');
    expect(recommendHostingPlan(101).id).toBe('community');
    expect(recommendHostingPlan(1000).id).toBe('community');
    expect(recommendHostingPlan(1001).id).toBe('growth');
    expect(recommendHostingPlan(30001).id).toBe('network');
    expect(recommendHostingPlan(100001).id).toBe('enterprise-custom');
  });

  it('recommends the smallest community timebanking tier that covers the active member count', () => {
    expect(recommendCommunityTimebankPlan(50).id).toBe('community-edition');
    expect(recommendCommunityTimebankPlan(151).id).toBe('community-plus');
    expect(recommendCommunityTimebankPlan(501).id).toBe('community-pro');
    expect(recommendCommunityTimebankPlan(5000).id).toBe('community-pro');
  });

  it('prices Community Edition as a low-cost annual entry plan', () => {
    const quote = estimateQuote({
      productLine: 'community-timebanking',
      activeMembers: 150,
      billingCycle: 'annual',
      communityPlanId: 'community-edition',
      supportTierId: 'standard',
      maintenancePlanId: 'track-latest',
      onboardingPackageId: 'community-assisted-launch',
      addOns: {},
      oneOffServices: {},
    });

    expect(quote.productLineLabel).toBe('Community Timebanking');
    expect(quote.hostingPlan.id).toBe('community-edition');
    expect(quote.monthlyRecurring).toBe(29);
    expect(quote.annualRecurring).toBe(348);
    expect(quote.annualSavings).toBe(120);
    expect(quote.oneOffTotal).toBe(250);
    expect(quote.firstYearTotal).toBe(598);
  });

  it('applies annual billing as two months free on recurring charges', () => {
    const quote = estimateQuote({
      activeMembers: 750,
      billingCycle: 'annual',
      supportTierId: 'standard',
      maintenancePlanId: 'track-latest',
      onboardingPackageId: 'none',
      addOns: {},
      oneOffServices: {},
    });

    expect(quote.hostingPlan.id).toBe('community');
    expect(quote.monthlyRecurring).toBe(299);
    expect(quote.annualRecurring).toBe(2990);
    expect(quote.annualSavings).toBe(598);
  });

  it('switches full platform enquiries above 100,000 active members to custom pricing', () => {
    const quote = estimateQuote({
      activeMembers: 250000,
      billingCycle: 'annual',
      supportTierId: 'managed',
      maintenancePlanId: 'custom-fork',
      onboardingPackageId: 'enterprise-launch',
      addOns: {
        'compliance-pack': 1,
      },
      oneOffServices: {
        'data-migration': 1,
      },
    });

    expect(quote.hostingPlan.id).toBe('enterprise-custom');
    expect(quote.pricingMode).toBe('custom');
    expect(quote.monthlyRecurring).toBe(0);
    expect(quote.firstYearTotal).toBe(0);
    expect(quote.lineItems.map((item) => item.label).join(' ')).toContain('Bespoke architecture');
  });

  it('combines support, maintenance, add-ons, and launch services into one estimate', () => {
    const quote = estimateQuote({
      activeMembers: 75,
      billingCycle: 'monthly',
      supportTierId: 'priority',
      maintenancePlanId: 'pinned-release',
      onboardingPackageId: 'quick-start',
      addOns: {
        'extra-storage-100gb': 2,
        'dedicated-staging': 1,
      },
      oneOffServices: {
        'branding-theme-pack': 1,
        'mobile-app-store-submission': 2,
      },
    });

    expect(quote.hostingPlan.id).toBe('spark');
    expect(quote.monthlyRecurring).toBe(99 + 399 + 199 + 50 + 199);
    expect(quote.oneOffTotal).toBe(750 + 950 + 3000);
    expect(quote.lineItems.some((item) => item.label === 'Priority async support')).toBe(true);
  });

  it('routes dedicated managed server enquiries to custom pricing with a starting-from signal', () => {
    const quote = estimateQuote({
      activeMembers: 750,
      billingCycle: 'monthly',
      deploymentModeId: 'dedicated-managed-server',
      supportTierId: 'standard',
      maintenancePlanId: 'track-latest',
      onboardingPackageId: 'none',
      addOns: {},
      oneOffServices: {},
    });

    expect(quote.hostingPlan.id).toBe('community');
    expect(quote.pricingMode).toBe('custom');
    expect(quote.monthlyRecurring).toBe(299);
    expect(quote.oneOffTotal).toBe(250);
    expect(quote.lineItems.map((item) => item.label).join(' ')).toContain('Dedicated managed infrastructure discovery');
    expect(quote.lineItems.find((item) => item.id === 'dedicated-managed-server')?.priceLabel).toBe('Starts from €650/mo');
  });

  it('formats EUR currency consistently for the public calculator', () => {
    expect(formatCurrency(4499)).toBe('€4,499');
    expect(formatCurrency(8999)).toBe('€8,999');
  });

  it('keeps enterprise custom quote totals as custom labels for downstream order payloads', () => {
    const quote = estimateQuote({
      activeMembers: 100001,
      billingCycle: 'annual',
      supportTierId: 'standard',
      maintenancePlanId: 'track-latest',
      onboardingPackageId: 'none',
      addOns: {},
      oneOffServices: {},
    });

    expect(quote.pricingMode).toBe('custom');
    expect(quote.hostingPlan.name).toBe('Enterprise Custom');
    expect(quote.lineItems.map((item) => item.label).join(' ')).toContain('Bespoke architecture');
  });
});
