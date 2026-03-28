// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Billing API Client
 * Stripe billing endpoints for subscription management.
 */

import { api } from '@/lib/api';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface Plan {
  id: number;
  name: string;
  slug: string;
  description: string;
  tier_level: number;
  monthly_price: number;
  yearly_price: number;
  features: string[];
  is_active: boolean;
}

export interface SubscriptionDetails {
  id: number;
  plan_id: number;
  plan_name: string;
  plan_tier_level: number;
  status: 'active' | 'trialing' | 'past_due' | 'cancelled' | 'expired' | 'incomplete';
  billing_interval: 'monthly' | 'yearly';
  current_period_start: string;
  current_period_end: string;
  trial_ends_at: string | null;
  cancel_at_period_end: boolean;
  stripe_subscription_id: string | null;
}

export interface Invoice {
  id: string;
  number: string;
  date: string;
  amount: number;
  currency: string;
  status: 'paid' | 'open' | 'draft' | 'void' | 'uncollectible';
  hosted_invoice_url: string | null;
  invoice_pdf: string | null;
}

// ─────────────────────────────────────────────────────────────────────────────
// API
// ─────────────────────────────────────────────────────────────────────────────

export const billingApi = {
  /** Fetch active plans with prices (public) */
  getPlans: () =>
    api.get<Plan[]>('/v2/billing/plans'),

  /** Get current tenant subscription details */
  getSubscription: () =>
    api.get<SubscriptionDetails>('/v2/admin/billing/subscription'),

  /** Create a Stripe Checkout session, returns a redirect URL */
  createCheckout: (data: { plan_id: number; billing_interval: 'monthly' | 'yearly' }) =>
    api.post<{ checkout_url: string }>('/v2/admin/billing/checkout', data),

  /** Create a Stripe Customer Portal session, returns a redirect URL */
  createPortal: () =>
    api.post<{ portal_url: string }>('/v2/admin/billing/portal'),

  /** List invoices for the current tenant */
  getInvoices: () =>
    api.get<Invoice[]>('/v2/admin/billing/invoices'),
};
