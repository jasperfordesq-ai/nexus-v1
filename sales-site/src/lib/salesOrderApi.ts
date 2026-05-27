// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { formatCurrency, formatQuoteAmount, type QuoteEstimate } from './pricingEngine';

const PRODUCTION_API_BASE = 'https://api.project-nexus.ie/api';
const LOCAL_API_BASE = 'http://127.0.0.1:8088/api';
const SALES_ORDER_ENDPOINT = '/v2/sales/orders';

export interface SalesOrderFormState {
  contactName: string;
  organisation: string;
  email: string;
  region: string;
  note: string;
  website: string;
  pageUrl?: string;
}

export interface SalesOrderLineItemPayload {
  label: string;
  amount_label: string;
  quantity: number;
  cadence: 'monthly' | 'one-off';
}

export interface SalesOrderPayload {
  contact_name: string;
  organisation: string;
  email: string;
  region: string;
  note: string;
  website: string;
  page_url?: string;
  quote: {
    product_line_label: string;
    plan_name: string;
    active_member_label: string;
    billing_cycle: string;
    pricing_mode: string;
    monthly_recurring_label: string;
    annual_recurring_label: string;
    annual_savings_label: string;
    one_off_label: string;
    first_year_label: string;
    line_items: SalesOrderLineItemPayload[];
  };
}

export interface SalesOrderSubmissionResult {
  status: 'received';
  reference: string;
  message: string;
}

export function buildSalesOrderPayload(form: SalesOrderFormState, quote: QuoteEstimate): SalesOrderPayload {
  return {
    contact_name: form.contactName.trim(),
    organisation: form.organisation.trim(),
    email: form.email.trim(),
    region: form.region.trim(),
    note: form.note.trim(),
    website: form.website.trim(),
    page_url: form.pageUrl,
    quote: {
      product_line_label: quote.productLineLabel,
      plan_name: quote.hostingPlan.name,
      active_member_label: quote.hostingPlan.activeMemberLabel,
      billing_cycle: quote.billingCycle,
      pricing_mode: quote.pricingMode,
      monthly_recurring_label: formatQuoteAmount(quote, quote.monthlyRecurring),
      annual_recurring_label: formatQuoteAmount(quote, quote.annualRecurring),
      annual_savings_label: quote.pricingMode === 'custom' ? 'Discovery' : formatCurrency(quote.annualSavings),
      one_off_label: formatQuoteAmount(quote, quote.oneOffTotal),
      first_year_label: formatQuoteAmount(quote, quote.firstYearTotal),
      line_items: quote.lineItems.map((item) => ({
        label: item.label,
        amount_label: quote.pricingMode === 'custom' && item.amountEur === 0
          ? 'Custom quote'
          : `${formatCurrency(item.amountEur)}${item.cadence === 'monthly' ? '/mo' : ''}`,
        quantity: item.quantity,
        cadence: item.cadence,
      })),
    },
  };
}

export async function submitSalesOrder(form: SalesOrderFormState, quote: QuoteEstimate): Promise<SalesOrderSubmissionResult> {
  let response: Response;
  try {
    response = await fetch(`${getApiBase()}${SALES_ORDER_ENDPOINT}`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(buildSalesOrderPayload(form, quote)),
    });
  } catch {
    throw new Error('The order service could not be reached. Please try again in a moment or email jasper.ford.esq@gmail.com.');
  }

  const payload = await readJson(response);

  if (!response.ok) {
    throw new Error(extractErrorMessage(payload));
  }

  return payload.data as SalesOrderSubmissionResult;
}

export function getApiBase(): string {
  const meta = import.meta as ImportMeta & {
    env?: Record<string, string | undefined>;
  };
  const configured = (meta.env?.VITE_API_BASE || meta.env?.VITE_NEXUS_API_BASE || '').trim();

  return (configured || resolveDefaultApiBase()).replace(/\/$/, '');
}

function resolveDefaultApiBase(): string {
  if (typeof window !== 'undefined' && ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname)) {
    return LOCAL_API_BASE;
  }

  return PRODUCTION_API_BASE;
}

async function readJson(response: Response): Promise<Record<string, unknown>> {
  try {
    return (await response.json()) as Record<string, unknown>;
  } catch {
    return {};
  }
}

function extractErrorMessage(payload: Record<string, unknown>): string {
  const errors = payload.errors;
  if (Array.isArray(errors)) {
    const first = errors[0] as { message?: unknown } | undefined;
    if (typeof first?.message === 'string' && first.message.trim() !== '') {
      return first.message;
    }
  }

  return 'The order enquiry could not be sent. Please try again.';
}
