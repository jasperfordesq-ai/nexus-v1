// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '@/lib/api';
import { memberPremiumAdminApi } from './memberPremiumApi';
import type { MemberPremiumTier, MemberSubscriberRow, SubscriberListResponse, TierUpsertPayload } from './memberPremiumApi';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
    patch: vi.fn(),
  },
}));

const mockTier: MemberPremiumTier = {
  id: 10,
  tenant_id: 2,
  slug: 'supporter',
  name: 'Supporter',
  description: 'Help the community grow',
  monthly_price_cents: 500,
  yearly_price_cents: 5000,
  stripe_price_id_monthly: 'price_monthly_abc',
  stripe_price_id_yearly: 'price_yearly_abc',
  features: ['exclusive_content', 'early_access'],
  sort_order: 1,
  is_active: true,
  active_subscriber_count: 42,
};

const mockSubscriberRow: MemberSubscriberRow = {
  id: 1001,
  user_id: 999,
  tier_id: 10,
  status: 'active',
  billing_interval: 'monthly',
  current_period_end: '2026-07-21T00:00:00Z',
  canceled_at: null,
  grace_period_ends_at: null,
  created_at: '2026-01-01T00:00:00Z',
  tier_name: 'Supporter',
  tier_slug: 'supporter',
  email: 'alice@example.com',
  user_name: 'alice',
  first_name: 'Alice',
};

const mockUpsertPayload: TierUpsertPayload = {
  slug: 'patron',
  name: 'Patron',
  description: 'Premium membership',
  monthly_price_cents: 1000,
  yearly_price_cents: 10000,
  features: ['patron_badge'],
  sort_order: 2,
  is_active: true,
};

describe('memberPremiumAdminApi', () => {
  beforeEach(() => {
    vi.mocked(api.get).mockReset();
    vi.mocked(api.post).mockReset();
    vi.mocked(api.put).mockReset();
    vi.mocked(api.delete).mockReset();
  });

  // ── listTiers ─────────────────────────────────────────────────────────────

  describe('listTiers', () => {
    it('calls the tiers list endpoint and returns the tiers array', async () => {
      vi.mocked(api.get).mockResolvedValueOnce({ tiers: [mockTier] });

      const result = await memberPremiumAdminApi.listTiers();

      expect(api.get).toHaveBeenCalledWith('/v2/admin/member-premium/tiers');
      expect(result).toEqual({ tiers: [mockTier] });
    });

    it('returns an empty tiers array when no tiers exist', async () => {
      vi.mocked(api.get).mockResolvedValueOnce({ tiers: [] });

      const result = await memberPremiumAdminApi.listTiers();

      expect(result.tiers).toEqual([]);
    });

    it('propagates errors', async () => {
      vi.mocked(api.get).mockRejectedValueOnce(new Error('Server error'));

      await expect(memberPremiumAdminApi.listTiers()).rejects.toThrow('Server error');
    });
  });

  // ── getTier ───────────────────────────────────────────────────────────────

  describe('getTier', () => {
    it('calls the correct endpoint with the tier id', async () => {
      vi.mocked(api.get).mockResolvedValueOnce({ tier: mockTier });

      const result = await memberPremiumAdminApi.getTier(10);

      expect(api.get).toHaveBeenCalledWith('/v2/admin/member-premium/tiers/10');
      expect(result).toEqual({ tier: mockTier });
    });

    it('uses the given id, not a hardcoded one', async () => {
      vi.mocked(api.get).mockResolvedValueOnce({ tier: { ...mockTier, id: 99 } });

      await memberPremiumAdminApi.getTier(99);

      expect(api.get).toHaveBeenCalledWith('/v2/admin/member-premium/tiers/99');
    });

    it('propagates errors', async () => {
      vi.mocked(api.get).mockRejectedValueOnce(new Error('Not found'));

      await expect(memberPremiumAdminApi.getTier(404)).rejects.toThrow('Not found');
    });
  });

  // ── createTier ────────────────────────────────────────────────────────────

  describe('createTier', () => {
    it('posts the full payload to the tiers endpoint', async () => {
      vi.mocked(api.post).mockResolvedValueOnce({ tier: mockTier });

      const result = await memberPremiumAdminApi.createTier(mockUpsertPayload);

      expect(api.post).toHaveBeenCalledWith('/v2/admin/member-premium/tiers', mockUpsertPayload);
      expect(result).toEqual({ tier: mockTier });
    });

    it('handles null description in payload', async () => {
      const payload: TierUpsertPayload = { ...mockUpsertPayload, description: null };
      vi.mocked(api.post).mockResolvedValueOnce({ tier: { ...mockTier, description: null } });

      await memberPremiumAdminApi.createTier(payload);

      expect(api.post).toHaveBeenCalledWith('/v2/admin/member-premium/tiers', payload);
    });

    it('propagates errors', async () => {
      vi.mocked(api.post).mockRejectedValueOnce(new Error('Validation error'));

      await expect(memberPremiumAdminApi.createTier(mockUpsertPayload)).rejects.toThrow('Validation error');
    });
  });

  // ── updateTier ────────────────────────────────────────────────────────────

  describe('updateTier', () => {
    it('puts a partial payload to the correct tier endpoint', async () => {
      const partialPayload: Partial<TierUpsertPayload> = { name: 'Super Supporter', is_active: false };
      vi.mocked(api.put).mockResolvedValueOnce({ tier: { ...mockTier, name: 'Super Supporter', is_active: false } });

      const result = await memberPremiumAdminApi.updateTier(10, partialPayload);

      expect(api.put).toHaveBeenCalledWith('/v2/admin/member-premium/tiers/10', partialPayload);
      expect(result.tier.name).toBe('Super Supporter');
    });

    it('uses the correct id in the URL', async () => {
      vi.mocked(api.put).mockResolvedValueOnce({ tier: mockTier });

      await memberPremiumAdminApi.updateTier(7, { sort_order: 3 });

      expect(api.put).toHaveBeenCalledWith('/v2/admin/member-premium/tiers/7', { sort_order: 3 });
    });

    it('propagates errors', async () => {
      vi.mocked(api.put).mockRejectedValueOnce(new Error('Conflict'));

      await expect(memberPremiumAdminApi.updateTier(10, {})).rejects.toThrow('Conflict');
    });
  });

  // ── deleteTier ────────────────────────────────────────────────────────────

  describe('deleteTier', () => {
    it('calls delete on the correct tier endpoint', async () => {
      vi.mocked(api.delete).mockResolvedValueOnce({ deleted: true });

      const result = await memberPremiumAdminApi.deleteTier(10);

      expect(api.delete).toHaveBeenCalledWith('/v2/admin/member-premium/tiers/10');
      expect(result).toEqual({ deleted: true });
    });

    it('uses the id from the argument', async () => {
      vi.mocked(api.delete).mockResolvedValueOnce({ deleted: true });

      await memberPremiumAdminApi.deleteTier(55);

      expect(api.delete).toHaveBeenCalledWith('/v2/admin/member-premium/tiers/55');
    });

    it('propagates errors', async () => {
      vi.mocked(api.delete).mockRejectedValueOnce(new Error('Has active subscribers'));

      await expect(memberPremiumAdminApi.deleteTier(10)).rejects.toThrow('Has active subscribers');
    });
  });

  // ── syncStripe ────────────────────────────────────────────────────────────

  describe('syncStripe', () => {
    it('posts to the sync-stripe endpoint with an empty body', async () => {
      vi.mocked(api.post).mockResolvedValueOnce({ tier: { ...mockTier, stripe_price_id_monthly: 'price_new' } });

      const result = await memberPremiumAdminApi.syncStripe(10);

      expect(api.post).toHaveBeenCalledWith('/v2/admin/member-premium/tiers/10/sync-stripe', {});
      expect(result.tier.stripe_price_id_monthly).toBe('price_new');
    });

    it('passes the correct id in the URL', async () => {
      vi.mocked(api.post).mockResolvedValueOnce({ tier: mockTier });

      await memberPremiumAdminApi.syncStripe(88);

      expect(api.post).toHaveBeenCalledWith('/v2/admin/member-premium/tiers/88/sync-stripe', {});
    });

    it('propagates errors', async () => {
      vi.mocked(api.post).mockRejectedValueOnce(new Error('Stripe API error'));

      await expect(memberPremiumAdminApi.syncStripe(10)).rejects.toThrow('Stripe API error');
    });
  });

  // ── listSubscribers ───────────────────────────────────────────────────────

  describe('listSubscribers', () => {
    const mockListResponse: SubscriberListResponse = {
      rows: [mockSubscriberRow],
      total: 1,
      page: 1,
      per_page: 20,
    };

    it('calls the subscribers endpoint with no query string when params is empty', async () => {
      vi.mocked(api.get).mockResolvedValueOnce(mockListResponse);

      const result = await memberPremiumAdminApi.listSubscribers();

      expect(api.get).toHaveBeenCalledWith('/v2/admin/member-premium/subscribers');
      expect(result).toEqual(mockListResponse);
    });

    it('calls with no query string when called with empty object', async () => {
      vi.mocked(api.get).mockResolvedValueOnce(mockListResponse);

      await memberPremiumAdminApi.listSubscribers({});

      expect(api.get).toHaveBeenCalledWith('/v2/admin/member-premium/subscribers');
    });

    it('appends page to query string', async () => {
      vi.mocked(api.get).mockResolvedValueOnce(mockListResponse);

      await memberPremiumAdminApi.listSubscribers({ page: 2 });

      expect(api.get).toHaveBeenCalledWith('/v2/admin/member-premium/subscribers?page=2');
    });

    it('appends per_page to query string', async () => {
      vi.mocked(api.get).mockResolvedValueOnce(mockListResponse);

      await memberPremiumAdminApi.listSubscribers({ per_page: 50 });

      expect(api.get).toHaveBeenCalledWith('/v2/admin/member-premium/subscribers?per_page=50');
    });

    it('appends status to query string', async () => {
      vi.mocked(api.get).mockResolvedValueOnce(mockListResponse);

      await memberPremiumAdminApi.listSubscribers({ status: 'cancelled' });

      expect(api.get).toHaveBeenCalledWith('/v2/admin/member-premium/subscribers?status=cancelled');
    });

    it('appends all provided params as a combined query string', async () => {
      vi.mocked(api.get).mockResolvedValueOnce(mockListResponse);

      await memberPremiumAdminApi.listSubscribers({ page: 3, per_page: 10, status: 'active' });

      // URLSearchParams inserts in set-order: page, per_page, status
      expect(api.get).toHaveBeenCalledWith(
        '/v2/admin/member-premium/subscribers?page=3&per_page=10&status=active'
      );
    });

    it('omits page when page is 0 (falsy)', async () => {
      // NOTE: The source uses `if (params.page)` — page=0 is falsy and will be omitted.
      // This is a documented behaviour; page numbering effectively starts at 1.
      vi.mocked(api.get).mockResolvedValueOnce(mockListResponse);

      await memberPremiumAdminApi.listSubscribers({ page: 0 });

      expect(api.get).toHaveBeenCalledWith('/v2/admin/member-premium/subscribers');
    });

    it('returns an empty rows array when no subscribers exist', async () => {
      const empty: SubscriberListResponse = { rows: [], total: 0, page: 1, per_page: 20 };
      vi.mocked(api.get).mockResolvedValueOnce(empty);

      const result = await memberPremiumAdminApi.listSubscribers();

      expect(result.rows).toEqual([]);
      expect(result.total).toBe(0);
    });

    it('returns a subscriber with null optional fields', async () => {
      const rowWithNulls: MemberSubscriberRow = {
        ...mockSubscriberRow,
        email: null,
        user_name: null,
        first_name: null,
        current_period_end: null,
      };
      vi.mocked(api.get).mockResolvedValueOnce({ rows: [rowWithNulls], total: 1, page: 1, per_page: 20 });

      const result = await memberPremiumAdminApi.listSubscribers();

      expect(result.rows[0].email).toBeNull();
      expect(result.rows[0].user_name).toBeNull();
    });

    it('propagates errors', async () => {
      vi.mocked(api.get).mockRejectedValueOnce(new Error('Unauthorized'));

      await expect(memberPremiumAdminApi.listSubscribers()).rejects.toThrow('Unauthorized');
    });
  });
});
