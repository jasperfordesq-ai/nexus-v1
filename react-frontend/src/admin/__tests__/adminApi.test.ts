// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for adminApi.ts — the admin API client
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';

// Mock the main api client
const mockGet = vi.fn().mockResolvedValue({ success: true, data: {} });
const mockPost = vi.fn().mockResolvedValue({ success: true, data: {} });
const mockPut = vi.fn().mockResolvedValue({ success: true, data: {} });
const mockDelete = vi.fn().mockResolvedValue({ success: true });
const mockUpload = vi.fn().mockResolvedValue({ success: true, data: {} });

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: any[]) => mockGet(...args),
    post: (...args: any[]) => mockPost(...args),
    put: (...args: any[]) => mockPut(...args),
    delete: (...args: any[]) => mockDelete(...args),
    upload: (...args: any[]) => mockUpload(...args),
  },
  tokenManager: {
    getTenantId: vi.fn(() => '2'),
    getAccessToken: vi.fn(() => 'mock-token'),
  },
}));

import {
  adminDashboard,
  adminUsers,
  adminConfig,
  adminListings,
  adminCategories,
  adminAttributes,
  adminGamification,
  adminMatching,
  adminTimebanking,
  adminBlog,
  adminBroker,
  adminGroups,
  adminSystem,
  adminEnterprise,
  adminLegalDocs,
  adminNewsletters,
  adminVolunteering,
  adminFederation,
  adminPages,
  adminMenus,
  adminPlans,
  adminDeliverability,
  adminDiagnostics,
  adminSettings,
  adminTools,
  adminSuper,
  adminCommunityAnalytics,
  adminImpactReport,
  adminVetting,
  adminCron,
  adminModeration,
} from '../api/adminApi';

describe('adminApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Dashboard
  // ─────────────────────────────────────────────────────────────────────────

  describe('adminDashboard', () => {
    it('getStats calls correct endpoint', async () => {
      await adminDashboard.getStats();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/dashboard/stats');
    });

    it('getTrends calls with months param', async () => {
      await adminDashboard.getTrends(3);
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/dashboard/trends?months=3');
    });

    it('getActivity calls with pagination', async () => {
      await adminDashboard.getActivity(2, 10);
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/dashboard/activity?page=2&limit=10');
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Users
  // ─────────────────────────────────────────────────────────────────────────

  describe('adminUsers', () => {
    it('list calls with default params', async () => {
      await adminUsers.list();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/users');
    });

    it('list calls with search param', async () => {
      await adminUsers.list({ search: 'john', page: 1 });
      expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('search=john'));
    });

    it('get calls with user id', async () => {
      await adminUsers.get(5);
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/users/5');
    });

    it('create calls POST with data', async () => {
      const data = { first_name: 'John', last_name: 'Doe', email: 'john@test.com', role: 'member' };
      await adminUsers.create(data);
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/users', data);
    });

    it('update calls PUT with data', async () => {
      await adminUsers.update(5, { first_name: 'Jane' });
      expect(mockPut).toHaveBeenCalledWith('/v2/admin/users/5', { first_name: 'Jane' });
    });

    it('delete calls DELETE', async () => {
      await adminUsers.delete(5);
      expect(mockDelete).toHaveBeenCalledWith('/v2/admin/users/5');
    });

    it('approve calls POST', async () => {
      await adminUsers.approve(5);
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/users/5/approve');
    });

    it('suspend calls POST with reason', async () => {
      await adminUsers.suspend(5, 'Violation');
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/users/5/suspend', { reason: 'Violation' });
    });

    it('ban calls POST with reason', async () => {
      await adminUsers.ban(5, 'Spam');
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/users/5/ban', { reason: 'Spam' });
    });

    it('reactivate calls POST', async () => {
      await adminUsers.reactivate(5);
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/users/5/reactivate');
    });

    it('reset2fa calls POST with reason', async () => {
      await adminUsers.reset2fa(5, 'Lost phone');
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/users/5/reset-2fa', { reason: 'Lost phone' });
    });

    it('addBadge calls POST', async () => {
      await adminUsers.addBadge(5, 'early_adopter');
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/users/5/badges', { badge_slug: 'early_adopter' });
    });

    it('removeBadge calls DELETE', async () => {
      await adminUsers.removeBadge(5, 10);
      expect(mockDelete).toHaveBeenCalledWith('/v2/admin/users/5/badges/10');
    });

    it('impersonate calls POST', async () => {
      await adminUsers.impersonate(5);
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/users/5/impersonate');
    });

    it('setSuperAdmin calls PUT', async () => {
      await adminUsers.setSuperAdmin(5, true);
      expect(mockPut).toHaveBeenCalledWith('/v2/admin/users/5/super-admin', { grant: true });
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Config
  // ─────────────────────────────────────────────────────────────────────────

  describe('adminConfig', () => {
    it('get calls correct endpoint', async () => {
      await adminConfig.get();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/config');
    });

    it('updateFeature calls PUT', async () => {
      await adminConfig.updateFeature('events', true);
      expect(mockPut).toHaveBeenCalledWith('/v2/admin/config/features', { feature: 'events', enabled: true });
    });

    it('updateModule calls PUT', async () => {
      await adminConfig.updateModule('wallet', false);
      expect(mockPut).toHaveBeenCalledWith('/v2/admin/config/modules', { module: 'wallet', enabled: false });
    });

    it('clearCache calls POST', async () => {
      await adminConfig.clearCache('all');
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/cache/clear', { type: 'all' });
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Listings
  // ─────────────────────────────────────────────────────────────────────────

  describe('adminListings', () => {
    it('list calls with default params', async () => {
      await adminListings.list();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/listings');
    });

    it('approve calls POST', async () => {
      await adminListings.approve(10);
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/listings/10/approve');
    });

    it('delete calls DELETE', async () => {
      await adminListings.delete(10);
      expect(mockDelete).toHaveBeenCalledWith('/v2/admin/listings/10');
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Categories
  // ─────────────────────────────────────────────────────────────────────────

  describe('adminCategories', () => {
    it('list calls correct endpoint', async () => {
      await adminCategories.list();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/categories');
    });

    it('create calls POST', async () => {
      await adminCategories.create({ name: 'Tech', color: '#ff0000' });
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/categories', { name: 'Tech', color: '#ff0000' });
    });

    it('update calls PUT', async () => {
      await adminCategories.update(3, { name: 'Technology' });
      expect(mockPut).toHaveBeenCalledWith('/v2/admin/categories/3', { name: 'Technology' });
    });

    it('delete calls DELETE', async () => {
      await adminCategories.delete(3);
      expect(mockDelete).toHaveBeenCalledWith('/v2/admin/categories/3');
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Attributes
  // ─────────────────────────────────────────────────────────────────────────

  describe('adminAttributes', () => {
    it('list calls correct endpoint', async () => {
      await adminAttributes.list();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/attributes');
    });

    it('create calls POST', async () => {
      await adminAttributes.create({ name: 'Skill Level' });
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/attributes', { name: 'Skill Level' });
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Gamification
  // ─────────────────────────────────────────────────────────────────────────

  describe('adminGamification', () => {
    it('getStats calls correct endpoint', async () => {
      await adminGamification.getStats();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/gamification/stats');
    });

    it('listCampaigns calls correct endpoint', async () => {
      await adminGamification.listCampaigns();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/gamification/campaigns');
    });

    it('createBadge calls POST', async () => {
      await adminGamification.createBadge({ name: 'Pioneer', description: 'First user' });
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/gamification/badges', { name: 'Pioneer', description: 'First user' });
    });

    it('deleteBadge calls DELETE', async () => {
      await adminGamification.deleteBadge(5);
      expect(mockDelete).toHaveBeenCalledWith('/v2/admin/gamification/badges/5');
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Matching
  // ─────────────────────────────────────────────────────────────────────────

  describe('adminMatching', () => {
    it('getConfig calls correct endpoint', async () => {
      await adminMatching.getConfig();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/matching/config');
    });

    it('updateConfig calls PUT', async () => {
      await adminMatching.updateConfig({ category_weight: 0.5 });
      expect(mockPut).toHaveBeenCalledWith('/v2/admin/matching/config', { category_weight: 0.5 });
    });

    it('approveMatch calls POST', async () => {
      await adminMatching.approveMatch(10, 'Looks good');
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/matching/approvals/10/approve', { notes: 'Looks good' });
    });

    it('rejectMatch calls POST with reason', async () => {
      await adminMatching.rejectMatch(10, 'Not suitable');
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/matching/approvals/10/reject', { reason: 'Not suitable' });
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Timebanking
  // ─────────────────────────────────────────────────────────────────────────

  describe('adminTimebanking', () => {
    it('getStats calls correct endpoint', async () => {
      await adminTimebanking.getStats();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/timebanking/stats');
    });

    it('adjustBalance calls POST', async () => {
      await adminTimebanking.adjustBalance(5, 10, 'Bonus');
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/timebanking/adjust-balance', { user_id: 5, amount: 10, reason: 'Bonus' });
    });

    it('getOrgWallets calls correct endpoint', async () => {
      await adminTimebanking.getOrgWallets();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/timebanking/org-wallets');
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Blog
  // ─────────────────────────────────────────────────────────────────────────

  describe('adminBlog', () => {
    it('list calls correct endpoint', async () => {
      await adminBlog.list();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/blog');
    });

    it('get calls with id', async () => {
      await adminBlog.get(5);
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/blog/5');
    });

    it('create calls POST', async () => {
      await adminBlog.create({ title: 'Test Post' });
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/blog', { title: 'Test Post' });
    });

    it('toggleStatus calls POST', async () => {
      await adminBlog.toggleStatus(5);
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/blog/5/toggle-status');
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Broker
  // ─────────────────────────────────────────────────────────────────────────

  describe('adminBroker', () => {
    it('getDashboard calls correct endpoint', async () => {
      await adminBroker.getDashboard();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/broker/dashboard');
    });

    it('approveExchange calls POST', async () => {
      await adminBroker.approveExchange(10, 'Approved');
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/broker/exchanges/10/approve', { notes: 'Approved' });
    });

    it('rejectExchange calls POST with reason', async () => {
      await adminBroker.rejectExchange(10, 'Rejected');
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/broker/exchanges/10/reject', { reason: 'Rejected' });
    });

    it('flagMessage calls POST', async () => {
      await adminBroker.flagMessage(5, 'Suspicious', 'serious');
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/broker/messages/5/flag', { reason: 'Suspicious', severity: 'serious' });
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Groups
  // ─────────────────────────────────────────────────────────────────────────

  describe('adminGroups', () => {
    it('list calls correct endpoint', async () => {
      await adminGroups.list();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/groups');
    });

    it('getAnalytics calls correct endpoint', async () => {
      await adminGroups.getAnalytics();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/groups/analytics');
    });

    it('delete calls DELETE', async () => {
      await adminGroups.delete(5);
      expect(mockDelete).toHaveBeenCalledWith('/v2/admin/groups/5');
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Enterprise
  // ─────────────────────────────────────────────────────────────────────────

  describe('adminEnterprise', () => {
    it('getDashboard calls correct endpoint', async () => {
      await adminEnterprise.getDashboard();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/enterprise/dashboard');
    });

    it('getRoles calls correct endpoint', async () => {
      await adminEnterprise.getRoles();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/enterprise/roles');
    });

    it('createRole calls POST', async () => {
      await adminEnterprise.createRole({ name: 'Editor', description: 'Can edit', permissions: ['edit'] });
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/enterprise/roles', { name: 'Editor', description: 'Can edit', permissions: ['edit'] });
    });

    it('getGdprDashboard calls correct endpoint', async () => {
      await adminEnterprise.getGdprDashboard();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/enterprise/gdpr/dashboard');
    });

    it('getMonitoring calls correct endpoint', async () => {
      await adminEnterprise.getMonitoring();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/enterprise/monitoring');
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Legal Documents
  // ─────────────────────────────────────────────────────────────────────────

  describe('adminLegalDocs', () => {
    it('list calls correct endpoint', async () => {
      await adminLegalDocs.list();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/legal-documents');
    });

    it('create calls POST', async () => {
      await adminLegalDocs.create({ title: 'Terms', content: 'Content', type: 'terms' });
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/legal-documents', expect.objectContaining({ title: 'Terms' }));
    });

    it('publishVersion calls POST', async () => {
      await adminLegalDocs.publishVersion(5);
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/legal-documents/versions/5/publish', {});
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Super Admin
  // ─────────────────────────────────────────────────────────────────────────

  describe('adminSuper', () => {
    it('getDashboard calls correct endpoint', async () => {
      await adminSuper.getDashboard();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/super/dashboard');
    });

    it('listTenants calls correct endpoint', async () => {
      await adminSuper.listTenants();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/super/tenants');
    });

    it('createTenant calls POST', async () => {
      await adminSuper.createTenant({ parent_id: 1, name: 'New Tenant', slug: 'new-tenant' });
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/super/tenants', expect.objectContaining({ name: 'New Tenant' }));
    });

    it('deleteTenant calls DELETE', async () => {
      await adminSuper.deleteTenant(5);
      expect(mockDelete).toHaveBeenCalledWith('/v2/admin/super/tenants/5');
    });

    it('deleteTenant with hard delete', async () => {
      await adminSuper.deleteTenant(5, true);
      expect(mockDelete).toHaveBeenCalledWith('/v2/admin/super/tenants/5?hard=1');
    });

    it('emergencyLockdown calls POST', async () => {
      await adminSuper.emergencyLockdown('Security breach');
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/super/federation/emergency-lockdown', { reason: 'Security breach' });
    });

    it('bulkMoveUsers calls POST', async () => {
      await adminSuper.bulkMoveUsers({ user_ids: [1, 2], target_tenant_id: 3 });
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/super/bulk/move-users', { user_ids: [1, 2], target_tenant_id: 3 });
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Other APIs
  // ─────────────────────────────────────────────────────────────────────────

  describe('adminNewsletters', () => {
    it('list calls correct endpoint', async () => {
      await adminNewsletters.list();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/newsletters');
    });

    it('getSubscribers calls correct endpoint', async () => {
      await adminNewsletters.getSubscribers();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/newsletters/subscribers');
    });
  });

  describe('adminVolunteering', () => {
    it('getOverview calls correct endpoint', async () => {
      await adminVolunteering.getOverview();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/volunteering');
    });
  });

  describe('adminFederation', () => {
    it('getSettings calls correct endpoint', async () => {
      await adminFederation.getSettings();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/federation/settings');
    });

    it('getPartnerships calls correct endpoint', async () => {
      await adminFederation.getPartnerships();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/federation/partnerships');
    });
  });

  describe('adminPages', () => {
    it('list calls correct endpoint', async () => {
      await adminPages.list();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/pages');
    });
  });

  describe('adminCommunityAnalytics', () => {
    it('getData calls correct endpoint', async () => {
      await adminCommunityAnalytics.getData();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/community-analytics');
    });
  });

  describe('adminImpactReport', () => {
    it('getData calls with months param', async () => {
      await adminImpactReport.getData(6);
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/impact-report?months=6');
    });
  });

  describe('adminVetting', () => {
    it('list calls correct endpoint', async () => {
      await adminVetting.list();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/vetting');
    });

    it('verify calls POST', async () => {
      await adminVetting.verify(5);
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/vetting/5/verify');
    });
  });

  describe('adminCron', () => {
    it('getLogs calls correct endpoint', async () => {
      await adminCron.getLogs();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/system/cron-jobs/logs');
    });

    it('getHealthMetrics calls correct endpoint', async () => {
      await adminCron.getHealthMetrics();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/system/cron-jobs/health');
    });
  });

  describe('adminModeration', () => {
    it('getFeedPosts calls correct endpoint', async () => {
      await adminModeration.getFeedPosts();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/feed/posts');
    });

    it('hideFeedPost calls POST', async () => {
      await adminModeration.hideFeedPost(5);
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/feed/posts/5/hide');
    });

    it('resolveReport calls POST', async () => {
      await adminModeration.resolveReport(5);
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/reports/5/resolve');
    });
  });

  describe('adminDeliverability', () => {
    it('getDashboard calls correct endpoint', async () => {
      await adminDeliverability.getDashboard();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/deliverability/dashboard');
    });
  });

  describe('adminTools', () => {
    it('getRedirects calls correct endpoint', async () => {
      await adminTools.getRedirects();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/tools/redirects');
    });

    it('runHealthCheck calls POST', async () => {
      await adminTools.runHealthCheck();
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/tools/health-check');
    });

    it('runSeoAudit calls POST', async () => {
      await adminTools.runSeoAudit();
      expect(mockPost).toHaveBeenCalledWith('/v2/admin/tools/seo-audit');
    });
  });

  describe('adminSettings', () => {
    it('get calls correct endpoint', async () => {
      await adminSettings.get();
      expect(mockGet).toHaveBeenCalledWith('/v2/admin/settings');
    });

    it('update calls PUT', async () => {
      await adminSettings.update({ key: 'value' });
      expect(mockPut).toHaveBeenCalledWith('/v2/admin/settings', { key: 'value' });
    });
  });
});
