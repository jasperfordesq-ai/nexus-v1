/**
 * Admin API Client
 * Wraps the main api client with admin-specific endpoints
 */

import { api, type ApiResponse } from '@/lib/api';
import type {
  AdminDashboardStats,
  MonthlyTrend,
  ActivityLogEntry,
  AdminUser,
  AdminUserDetail,
  UserListParams,
  CreateUserPayload,
  UpdateUserPayload,
  TenantConfig,
  CacheStats,
  BackgroundJob,
  AdminListing,
  AdminCategory,
  AdminAttribute,
  GamificationStats,
  Campaign,
  MatchApproval,
  SmartMatchingConfig,
  TimebankingStats,
  FraudAlert,
  OrgWallet,
  CronJob,
  PaginatedResponse,
} from './types';

// ─────────────────────────────────────────────────────────────────────────────
// Helper
// ─────────────────────────────────────────────────────────────────────────────

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function buildQuery(params: Record<string, any>): string {
  const parts: string[] = [];
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') {
      parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`);
    }
  }
  return parts.length > 0 ? `?${parts.join('&')}` : '';
}

// ─────────────────────────────────────────────────────────────────────────────
// Dashboard
// ─────────────────────────────────────────────────────────────────────────────

export const adminDashboard = {
  getStats: () =>
    api.get<AdminDashboardStats>('/v2/admin/dashboard/stats'),

  getTrends: (months = 6) =>
    api.get<MonthlyTrend[]>(`/v2/admin/dashboard/trends?months=${months}`),

  getActivity: (page = 1, limit = 20) =>
    api.get<PaginatedResponse<ActivityLogEntry>>(
      `/v2/admin/dashboard/activity?page=${page}&limit=${limit}`
    ),
};

// ─────────────────────────────────────────────────────────────────────────────
// Users
// ─────────────────────────────────────────────────────────────────────────────

export const adminUsers = {
  list: (params: UserListParams = {}) =>
    api.get<PaginatedResponse<AdminUser>>(
      `/v2/admin/users${buildQuery(params)}`
    ),

  get: (id: number) =>
    api.get<AdminUserDetail>(`/v2/admin/users/${id}`),

  create: (data: CreateUserPayload) =>
    api.post<AdminUser>('/v2/admin/users', data),

  update: (id: number, data: UpdateUserPayload) =>
    api.put<AdminUser>(`/v2/admin/users/${id}`, data),

  delete: (id: number) =>
    api.delete(`/v2/admin/users/${id}`),

  approve: (id: number) =>
    api.post(`/v2/admin/users/${id}/approve`),

  suspend: (id: number, reason?: string) =>
    api.post(`/v2/admin/users/${id}/suspend`, { reason }),

  ban: (id: number, reason?: string) =>
    api.post(`/v2/admin/users/${id}/ban`, { reason }),

  reactivate: (id: number) =>
    api.post(`/v2/admin/users/${id}/reactivate`),

  reset2fa: (id: number, reason: string) =>
    api.post(`/v2/admin/users/${id}/reset-2fa`, { reason }),

  addBadge: (userId: number, badgeSlug: string) =>
    api.post(`/v2/admin/users/${userId}/badges`, { badge_slug: badgeSlug }),

  removeBadge: (userId: number, badgeId: number) =>
    api.delete(`/v2/admin/users/${userId}/badges/${badgeId}`),

  recheckAllBadges: () =>
    api.post('/v2/admin/users/badges/recheck-all'),

  impersonate: (userId: number) =>
    api.post(`/v2/admin/users/${userId}/impersonate`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Config (Features & Modules) — V2 API already exists
// ─────────────────────────────────────────────────────────────────────────────

export const adminConfig = {
  get: () =>
    api.get<TenantConfig>('/v2/admin/config'),

  updateFeature: (feature: string, enabled: boolean) =>
    api.put('/v2/admin/config/features', { feature, enabled }),

  updateModule: (module: string, enabled: boolean) =>
    api.put('/v2/admin/config/modules', { module, enabled }),

  getCacheStats: () =>
    api.get<CacheStats>('/v2/admin/cache/stats'),

  clearCache: (type: 'all' | 'tenant' = 'tenant') =>
    api.post('/v2/admin/cache/clear', { type }),

  getJobs: () =>
    api.get<BackgroundJob[]>('/v2/admin/jobs'),

  runJob: (jobId: string) =>
    api.post(`/v2/admin/jobs/${jobId}/run`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Listings
// ─────────────────────────────────────────────────────────────────────────────

export const adminListings = {
  list: (params: { page?: number; status?: string; type?: string; search?: string } = {}) =>
    api.get<PaginatedResponse<AdminListing>>(
      `/v2/admin/listings${buildQuery(params)}`
    ),

  approve: (id: number) =>
    api.post(`/v2/admin/listings/${id}/approve`),

  delete: (id: number) =>
    api.delete(`/v2/admin/listings/${id}`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Categories
// ─────────────────────────────────────────────────────────────────────────────

export const adminCategories = {
  list: () =>
    api.get<AdminCategory[]>('/v2/admin/categories'),

  create: (data: { name: string; description?: string; parent_id?: number }) =>
    api.post<AdminCategory>('/v2/admin/categories', data),

  update: (id: number, data: { name?: string; description?: string }) =>
    api.put<AdminCategory>(`/v2/admin/categories/${id}`, data),

  delete: (id: number) =>
    api.delete(`/v2/admin/categories/${id}`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Attributes
// ─────────────────────────────────────────────────────────────────────────────

export const adminAttributes = {
  list: () =>
    api.get<AdminAttribute[]>('/v2/admin/attributes'),

  create: (data: { name: string; type: string; options?: string[] }) =>
    api.post<AdminAttribute>('/v2/admin/attributes', data),

  update: (id: number, data: { name?: string; type?: string; options?: string[] }) =>
    api.put<AdminAttribute>(`/v2/admin/attributes/${id}`, data),

  delete: (id: number) =>
    api.delete(`/v2/admin/attributes/${id}`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Gamification
// ─────────────────────────────────────────────────────────────────────────────

export const adminGamification = {
  getStats: () =>
    api.get<GamificationStats>('/v2/admin/gamification/stats'),

  recheckAll: () =>
    api.post('/v2/admin/gamification/recheck-all'),

  bulkAward: (badgeSlug: string, userIds: number[]) =>
    api.post('/v2/admin/gamification/bulk-award', { badge_slug: badgeSlug, user_ids: userIds }),

  listCampaigns: () =>
    api.get<Campaign[]>('/v2/admin/gamification/campaigns'),

  createCampaign: (data: Partial<Campaign>) =>
    api.post<Campaign>('/v2/admin/gamification/campaigns', data),

  updateCampaign: (id: number, data: Partial<Campaign>) =>
    api.put<Campaign>(`/v2/admin/gamification/campaigns/${id}`, data),

  deleteCampaign: (id: number) =>
    api.delete(`/v2/admin/gamification/campaigns/${id}`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Matching
// ─────────────────────────────────────────────────────────────────────────────

export const adminMatching = {
  getConfig: () =>
    api.get<SmartMatchingConfig>('/v2/admin/matching/config'),

  updateConfig: (data: Partial<SmartMatchingConfig>) =>
    api.put('/v2/admin/matching/config', data),

  getApprovals: (params: { status?: string; page?: number } = {}) =>
    api.get<PaginatedResponse<MatchApproval>>(
      `/v2/admin/matching/approvals${buildQuery(params)}`
    ),

  approveMatch: (id: number, notes?: string) =>
    api.post(`/v2/admin/matching/approvals/${id}/approve`, { notes }),

  rejectMatch: (id: number, reason: string) =>
    api.post(`/v2/admin/matching/approvals/${id}/reject`, { reason }),

  clearCache: () =>
    api.post('/v2/admin/matching/cache/clear'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Timebanking
// ─────────────────────────────────────────────────────────────────────────────

export const adminTimebanking = {
  getStats: () =>
    api.get<TimebankingStats>('/v2/admin/timebanking/stats'),

  getAlerts: (params: { status?: string; page?: number } = {}) =>
    api.get<PaginatedResponse<FraudAlert>>(
      `/v2/admin/timebanking/alerts${buildQuery(params)}`
    ),

  updateAlertStatus: (id: number, status: string) =>
    api.put(`/v2/admin/timebanking/alerts/${id}`, { status }),

  adjustBalance: (userId: number, amount: number, reason: string) =>
    api.post('/v2/admin/timebanking/adjust-balance', { user_id: userId, amount, reason }),

  getOrgWallets: () =>
    api.get<OrgWallet[]>('/v2/admin/timebanking/org-wallets'),
};

// ─────────────────────────────────────────────────────────────────────────────
// System
// ─────────────────────────────────────────────────────────────────────────────

export const adminSystem = {
  getCronJobs: () =>
    api.get<CronJob[]>('/v2/admin/system/cron-jobs'),

  runCronJob: (id: number) =>
    api.post(`/v2/admin/system/cron-jobs/${id}/run`),

  getActivityLog: (params: { page?: number; limit?: number } = {}) =>
    api.get<PaginatedResponse<ActivityLogEntry>>(
      `/v2/admin/system/activity-log${buildQuery(params)}`
    ),
};

// ─────────────────────────────────────────────────────────────────────────────
// Re-export for convenience
// ─────────────────────────────────────────────────────────────────────────────

export type { ApiResponse };
