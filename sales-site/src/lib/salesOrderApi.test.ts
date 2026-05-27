// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { afterEach, describe, expect, it, vi } from 'vitest';

import { buildSalesOrderPayload, getApiBase, submitSalesOrder, type SalesOrderFormState } from './salesOrderApi';
import type { QuoteEstimate } from './pricingEngine';

describe('salesOrderApi', () => {
  afterEach(() => {
    vi.unstubAllEnvs();
    vi.unstubAllGlobals();
  });

  it('builds the full order payload from the visible quote and contact form', () => {
    const payload = buildSalesOrderPayload(formState(), quoteEstimate());

    expect(payload.contact_name).toBe('Ava Murphy');
    expect(payload.email).toBe('ava@example.org');
    expect(payload.quote.product_line_label).toBe('Full Platform Hosting');
    expect(payload.quote.plan_name).toBe('Network');
    expect(payload.quote.monthly_recurring_label).toBe('€4,499');
    expect(payload.quote.first_year_label).toBe('€46,990');
    expect(payload.quote.line_items).toEqual([
      {
        label: 'Network hosting',
        amount_label: '€4,499/mo',
        quantity: 1,
        cadence: 'monthly',
      },
      {
        label: 'Enterprise launch',
        amount_label: '€2,000',
        quantity: 1,
        cadence: 'one-off',
      },
    ]);
  });

  it('uses the production API origin by default and respects explicit Vite API configuration', () => {
    expect(getApiBase()).toBe('https://api.project-nexus.ie/api');

    vi.stubEnv('VITE_API_BASE', 'http://127.0.0.1:8088/api/');

    expect(getApiBase()).toBe('http://127.0.0.1:8088/api');
  });

  it('uses the local API automatically during localhost development', () => {
    vi.stubGlobal('window', {
      location: {
        hostname: '127.0.0.1',
      },
    });

    expect(getApiBase()).toBe('http://127.0.0.1:8088/api');
  });

  it('replaces low-level network failures with a professional order-service message', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));

    await expect(submitSalesOrder(formState(), quoteEstimate())).rejects.toThrow(
      'The order service could not be reached. Please try again in a moment or email jasper.ford.esq@gmail.com.',
    );
  });
});

function formState(): SalesOrderFormState {
  return {
    contactName: ' Ava Murphy ',
    organisation: ' Civic Network ',
    email: ' ava@example.org ',
    region: ' Ireland and UK ',
    note: ' We need procurement support. ',
    website: '',
    pageUrl: 'https://project-nexus.ie/hosting#quote-builder',
  };
}

function quoteEstimate(): QuoteEstimate {
  return {
    productLine: 'full-platform',
    productLineLabel: 'Full Platform Hosting',
    hostingPlan: {
      id: 'network',
      name: 'Network',
      activeMemberLabel: '30,001 to 100,000 active members',
      activeMemberLimit: 100000,
      monthlyEur: 4499,
      setupEur: 2000,
      infrastructure: 'VM cluster',
      tenants: 'Up to 100 tenants',
      storage: '2 TB',
      email: '5M emails/month',
      p1Response: '30-minute P1 response',
      bestFor: 'national networks',
    },
    billingCycle: 'annual',
    pricingMode: 'published',
    monthlyRecurring: 4499,
    annualRecurring: 44990,
    annualSavings: 8998,
    oneOffTotal: 2000,
    firstYearTotal: 46990,
    lineItems: [
      {
        id: 'network',
        label: 'Network hosting',
        amountEur: 4499,
        quantity: 1,
        cadence: 'monthly',
      },
      {
        id: 'enterprise-launch',
        label: 'Enterprise launch',
        amountEur: 2000,
        quantity: 1,
        cadence: 'one-off',
      },
    ],
  };
}
