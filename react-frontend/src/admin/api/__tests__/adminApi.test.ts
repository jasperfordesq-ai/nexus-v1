// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for adminApi client
 * Verifies API method signatures and basic call patterns
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';

// ─── Mock @/lib/api ──────────────────────────────────────────────────────────

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    post: vi.fn().mockResolvedValue({ success: true }),
    put: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
    upload: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: {
    getAccessToken: vi.fn(() => 'test-token'),
    getTenantId: vi.fn(() => '2'),
  },
}));

// Import after mocks
import * as adminApi from '../adminApi';
import { api } from '@/lib/api';

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('adminApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    (api.get as any).mockResolvedValue({ success: true, data: [] });
    (api.post as any).mockResolvedValue({ success: true });
    (api.put as any).mockResolvedValue({ success: true });
    (api.delete as any).mockResolvedValue({ success: true });
  });

  // ─── Dashboard ─────────────────────────────────────────────────────────────

  describe('adminDashboard', () => {
    it('getStats calls correct endpoint', async () => {
      await adminApi.adminDashboard.getStats();
      expect(api.get).toHaveBeenCalledWith('/v2/admin/dashboard/stats');
    });

    it('getTrends calls with months parameter', async () => {
      await adminApi.adminDashboard.getTrends(12);
      expect(api.get).toHaveBeenCalledWith('/v2/admin/dashboard/trends?months=12');
    });

    it('getActivity calls with pagination', async () => {
      await adminApi.adminDashboard.getActivity(2, 50);
      expect(api.get).toHaveBeenCalledWith('/v2/admin/dashboard/activity?page=2&limit=50');
    });
  });

  // ─── Users ─────────────────────────────────────────────────────────────────

  describe('adminUsers', () => {
    it('list calls users endpoint', async () => {
      await adminApi.adminUsers.list({ page: 1, search: 'test' });
      expect(api.get).toHaveBeenCalledWith('/v2/admin/users?page=1&search=test');
    });

    it('get calls user detail endpoint', async () => {
      await adminApi.adminUsers.get(5);
      expect(api.get).toHaveBeenCalledWith('/v2/admin/users/5');
    });

    it('create posts user data', async () => {
      await adminApi.adminUsers.create({ email: 'test@example.com', first_name: 'Test', last_name: 'User' });
      expect(api.post).toHaveBeenCalledWith('/v2/admin/users', expect.objectContaining({ email: 'test@example.com' }));
    });

    it('suspend posts to suspend endpoint', async () => {
      await adminApi.adminUsers.suspend(10, 'violation');
      expect(api.post).toHaveBeenCalledWith('/v2/admin/users/10/suspend', { reason: 'violation' });
    });
  });

  // ─── Config ────────────────────────────────────────────────────────────────

  describe('adminConfig', () => {
    it('get calls config endpoint', async () => {
      await adminApi.adminConfig.get();
      expect(api.get).toHaveBeenCalledWith('/v2/admin/config');
    });

    it('updateFeature puts feature update', async () => {
      await adminApi.adminConfig.updateFeature('gamification', true);
      expect(api.put).toHaveBeenCalledWith('/v2/admin/config/features', { feature: 'gamification', enabled: true });
    });

    it('clearCache posts to cache clear endpoint', async () => {
      await adminApi.adminConfig.clearCache('all');
      expect(api.post).toHaveBeenCalledWith('/v2/admin/cache/clear', { type: 'all' });
    });
  });

  // ─── Listings ──────────────────────────────────────────────────────────────

  describe('adminListings', () => {
    it('list calls with filters', async () => {
      await adminApi.adminListings.list({ status: 'active', type: 'offer' });
      expect(api.get).toHaveBeenCalledWith('/v2/admin/listings?status=active&type=offer');
    });

    it('approve posts to approve endpoint', async () => {
      await adminApi.adminListings.approve(20);
      expect(api.post).toHaveBeenCalledWith('/v2/admin/listings/20/approve');
    });
  });

  // ─── Gamification ──────────────────────────────────────────────────────────

  describe('adminGamification', () => {
    it('getStats calls stats endpoint', async () => {
      await adminApi.adminGamification.getStats();
      expect(api.get).toHaveBeenCalledWith('/v2/admin/gamification/stats');
    });

    it('recheckAll posts to recheck endpoint', async () => {
      await adminApi.adminGamification.recheckAll();
      expect(api.post).toHaveBeenCalledWith('/v2/admin/gamification/recheck-all');
    });

    it('listBadges calls badges endpoint', async () => {
      await adminApi.adminGamification.listBadges();
      expect(api.get).toHaveBeenCalledWith('/v2/admin/gamification/badges');
    });
  });

  // ─── Matching ──────────────────────────────────────────────────────────────

  describe('adminMatching', () => {
    it('getConfig calls config endpoint', async () => {
      await adminApi.adminMatching.getConfig();
      expect(api.get).toHaveBeenCalledWith('/v2/admin/matching/config');
    });

    it('approveMatch posts approval', async () => {
      await adminApi.adminMatching.approveMatch(15, 'looks good');
      expect(api.post).toHaveBeenCalledWith('/v2/admin/matching/approvals/15/approve', { notes: 'looks good' });
    });

    it('rejectMatch posts rejection with reason', async () => {
      await adminApi.adminMatching.rejectMatch(15, 'not suitable');
      expect(api.post).toHaveBeenCalledWith('/v2/admin/matching/approvals/15/reject', { reason: 'not suitable' });
    });
  });

  // ─── Blog ──────────────────────────────────────────────────────────────────

  describe('adminBlog', () => {
    it('list calls with search param', async () => {
      await adminApi.adminBlog.list({ search: 'test', page: 1 });
      expect(api.get).toHaveBeenCalledWith('/v2/admin/blog?search=test&page=1');
    });

    it('create posts blog data', async () => {
      await adminApi.adminBlog.create({ title: 'Test Post', content: 'Content', status: 'draft' });
      expect(api.post).toHaveBeenCalledWith('/v2/admin/blog', expect.objectContaining({ title: 'Test Post' }));
    });

    it('delete calls delete endpoint', async () => {
      await adminApi.adminBlog.delete(5);
      expect(api.delete).toHaveBeenCalledWith('/v2/admin/blog/5');
    });
  });

  // ─── Groups ────────────────────────────────────────────────────────────────

  describe('adminGroups', () => {
    it('list calls groups endpoint', async () => {
      await adminApi.adminGroups.list({ page: 1 });
      expect(api.get).toHaveBeenCalledWith('/v2/admin/groups?page=1');
    });

    it('getAnalytics calls analytics endpoint', async () => {
      await adminApi.adminGroups.getAnalytics();
      expect(api.get).toHaveBeenCalledWith('/v2/admin/groups/analytics');
    });

    it('updateStatus puts status update', async () => {
      await adminApi.adminGroups.updateStatus(10, 'inactive');
      expect(api.put).toHaveBeenCalledWith('/v2/admin/groups/10/status', { status: 'inactive' });
    });
  });

  // ─── System ────────────────────────────────────────────────────────────────

  describe('adminSystem', () => {
    it('getCronJobs calls cron endpoint', async () => {
      await adminApi.adminSystem.getCronJobs();
      expect(api.get).toHaveBeenCalledWith('/v2/admin/system/cron-jobs');
    });

    it('runCronJob posts to run endpoint', async () => {
      await adminApi.adminSystem.runCronJob(3);
      expect(api.post).toHaveBeenCalledWith('/v2/admin/system/cron-jobs/3/run');
    });
  });

  // ─── Legal Documents ───────────────────────────────────────────────────────

  describe('adminLegalDocs', () => {
    it('list calls legal documents endpoint', async () => {
      await adminApi.adminLegalDocs.list();
      expect(api.get).toHaveBeenCalledWith('/v2/admin/legal-documents');
    });

    it('create posts legal doc data', async () => {
      await adminApi.adminLegalDocs.create({ title: 'Terms', content: 'Content', type: 'terms' });
      expect(api.post).toHaveBeenCalledWith('/v2/admin/legal-documents', expect.objectContaining({ title: 'Terms' }));
    });

    it('getVersions calls versions endpoint', async () => {
      await adminApi.adminLegalDocs.getVersions(5);
      expect(api.get).toHaveBeenCalledWith('/v2/admin/legal-documents/5/versions');
    });
  });

  // ─── Super Admin ───────────────────────────────────────────────────────────

  describe('adminSuper', () => {
    it('listTenants calls tenants endpoint', async () => {
      await adminApi.adminSuper.listTenants({ search: 'test' });
      expect(api.get).toHaveBeenCalledWith('/v2/admin/super/tenants?search=test');
    });

    it('createTenant posts tenant data', async () => {
      await adminApi.adminSuper.createTenant({ name: 'New Tenant', slug: 'new-tenant' });
      expect(api.post).toHaveBeenCalledWith('/v2/admin/super/tenants', expect.objectContaining({ name: 'New Tenant' }));
    });

    it('grantSuperAdmin posts to grant endpoint', async () => {
      await adminApi.adminSuper.grantSuperAdmin(10);
      expect(api.post).toHaveBeenCalledWith('/v2/admin/super/users/10/grant-super-admin');
    });
  });
});
