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
  MatchApprovalDetail,
  MatchApprovalStats,
  SmartMatchingConfig,
  MatchingStatsResponse,
  TimebankingStats,
  FraudAlert,
  OrgWallet,
  UserFinancialReport,
  CronJob,
  PaginatedResponse,
  AdminBlogPost,
  CreateBlogPostPayload,
  UpdateBlogPostPayload,
  BadgeDefinition,
  BrokerDashboardStats,
  ExchangeRequest,
  RiskTag,
  BrokerMessage,
  MonitoredUser,
  AdminGroup,
  GroupApproval,
  GroupAnalyticsData,
  GroupModerationItem,
  EnterpriseDashboardStats,
  Role,
  GdprDashboardStats,
  GdprRequest,
  GdprConsent,
  GdprBreach,
  GdprAuditEntry,
  SystemHealth,
  HealthCheckResult,
  ErrorLogEntry,
  SecretEntry,
  LegalDocument,
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
  list: (params: { type?: string } = {}) =>
    api.get<AdminCategory[]>(`/v2/admin/categories${buildQuery(params)}`),

  create: (data: { name: string; color?: string; type?: string }) =>
    api.post<AdminCategory>('/v2/admin/categories', data),

  update: (id: number, data: { name?: string; color?: string; type?: string }) =>
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

  listBadges: () =>
    api.get<BadgeDefinition[]>('/v2/admin/gamification/badges'),

  createBadge: (data: { name: string; slug?: string; description: string; icon?: string }) =>
    api.post<BadgeDefinition>('/v2/admin/gamification/badges', data),

  deleteBadge: (id: number) =>
    api.delete(`/v2/admin/gamification/badges/${id}`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Matching
// ─────────────────────────────────────────────────────────────────────────────

export const adminMatching = {
  getConfig: () =>
    api.get<SmartMatchingConfig>('/v2/admin/matching/config'),

  updateConfig: (data: Partial<SmartMatchingConfig>) =>
    api.put('/v2/admin/matching/config', data),

  getMatchingStats: () =>
    api.get<MatchingStatsResponse>('/v2/admin/matching/stats'),

  getApprovals: (params: { status?: string; page?: number } = {}) =>
    api.get<PaginatedResponse<MatchApproval>>(
      `/v2/admin/matching/approvals${buildQuery(params)}`
    ),

  getApproval: (id: number) =>
    api.get<MatchApprovalDetail>(`/v2/admin/matching/approvals/${id}`),

  getApprovalStats: (days = 30) =>
    api.get<MatchApprovalStats>(`/v2/admin/matching/approvals/stats?days=${days}`),

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

  getUserReport: (params: { page?: number; search?: string } = {}) =>
    api.get<PaginatedResponse<UserFinancialReport>>(
      `/v2/admin/timebanking/user-report${buildQuery(params)}`
    ),
};

// ─────────────────────────────────────────────────────────────────────────────
// Blog
// ─────────────────────────────────────────────────────────────────────────────

export const adminBlog = {
  list: (params: { page?: number; status?: string; search?: string } = {}) =>
    api.get<PaginatedResponse<AdminBlogPost>>(
      `/v2/admin/blog${buildQuery(params)}`
    ),

  get: (id: number) =>
    api.get<AdminBlogPost>(`/v2/admin/blog/${id}`),

  create: (data: CreateBlogPostPayload) =>
    api.post<AdminBlogPost>('/v2/admin/blog', data),

  update: (id: number, data: UpdateBlogPostPayload) =>
    api.put<AdminBlogPost>(`/v2/admin/blog/${id}`, data),

  delete: (id: number) =>
    api.delete(`/v2/admin/blog/${id}`),

  toggleStatus: (id: number) =>
    api.post(`/v2/admin/blog/${id}/toggle-status`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Broker Controls
// ─────────────────────────────────────────────────────────────────────────────

export const adminBroker = {
  getDashboard: () =>
    api.get<BrokerDashboardStats>('/v2/admin/broker/dashboard'),

  getExchanges: (params: { page?: number; status?: string } = {}) =>
    api.get<PaginatedResponse<ExchangeRequest>>(
      `/v2/admin/broker/exchanges${buildQuery(params)}`
    ),

  approveExchange: (id: number, notes?: string) =>
    api.post(`/v2/admin/broker/exchanges/${id}/approve`, { notes }),

  rejectExchange: (id: number, reason: string) =>
    api.post(`/v2/admin/broker/exchanges/${id}/reject`, { reason }),

  getRiskTags: (params: { risk_level?: string } = {}) =>
    api.get<RiskTag[]>(`/v2/admin/broker/risk-tags${buildQuery(params)}`),

  getMessages: (params: { page?: number; filter?: string } = {}) =>
    api.get<PaginatedResponse<BrokerMessage>>(
      `/v2/admin/broker/messages${buildQuery(params)}`
    ),

  reviewMessage: (id: number) =>
    api.post(`/v2/admin/broker/messages/${id}/review`),

  getMonitoring: () =>
    api.get<MonitoredUser[]>('/v2/admin/broker/monitoring'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Groups
// ─────────────────────────────────────────────────────────────────────────────

export const adminGroups = {
  list: (params: { page?: number; status?: string; search?: string } = {}) =>
    api.get<PaginatedResponse<AdminGroup>>(
      `/v2/admin/groups${buildQuery(params)}`
    ),

  getAnalytics: () =>
    api.get<GroupAnalyticsData>('/v2/admin/groups/analytics'),

  getApprovals: () =>
    api.get<GroupApproval[]>('/v2/admin/groups/approvals'),

  approveMember: (id: number) =>
    api.post(`/v2/admin/groups/approvals/${id}/approve`),

  rejectMember: (id: number) =>
    api.post(`/v2/admin/groups/approvals/${id}/reject`),

  getModeration: () =>
    api.get<GroupModerationItem[]>('/v2/admin/groups/moderation'),

  delete: (id: number) =>
    api.delete(`/v2/admin/groups/${id}`),
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
// Enterprise
// ─────────────────────────────────────────────────────────────────────────────

export const adminEnterprise = {
  getDashboard: () =>
    api.get<EnterpriseDashboardStats>('/v2/admin/enterprise/dashboard'),

  getRoles: () =>
    api.get<Role[]>('/v2/admin/enterprise/roles'),

  getRole: (id: number) =>
    api.get<Role>(`/v2/admin/enterprise/roles/${id}`),

  createRole: (data: { name: string; description: string; permissions: string[] }) =>
    api.post('/v2/admin/enterprise/roles', data),

  updateRole: (id: number, data: Partial<Role>) =>
    api.put(`/v2/admin/enterprise/roles/${id}`, data),

  deleteRole: (id: number) =>
    api.delete(`/v2/admin/enterprise/roles/${id}`),

  getPermissions: () =>
    api.get<Record<string, string[]>>('/v2/admin/enterprise/permissions'),

  getGdprDashboard: () =>
    api.get<GdprDashboardStats>('/v2/admin/enterprise/gdpr/dashboard'),

  getGdprRequests: (params: { page?: number; status?: string } = {}) =>
    api.get<PaginatedResponse<GdprRequest>>(
      `/v2/admin/enterprise/gdpr/requests${buildQuery(params)}`
    ),

  updateGdprRequest: (id: number, data: { status: string; notes?: string }) =>
    api.put(`/v2/admin/enterprise/gdpr/requests/${id}`, data),

  getGdprConsents: () =>
    api.get<GdprConsent[]>('/v2/admin/enterprise/gdpr/consents'),

  getGdprBreaches: () =>
    api.get<GdprBreach[]>('/v2/admin/enterprise/gdpr/breaches'),

  getGdprAudit: () =>
    api.get<GdprAuditEntry[]>('/v2/admin/enterprise/gdpr/audit'),

  getMonitoring: () =>
    api.get<SystemHealth>('/v2/admin/enterprise/monitoring'),

  getHealthCheck: () =>
    api.get<HealthCheckResult>('/v2/admin/enterprise/monitoring/health'),

  getLogs: (params: { page?: number } = {}) =>
    api.get<PaginatedResponse<ErrorLogEntry>>(
      `/v2/admin/enterprise/monitoring/logs${buildQuery(params)}`
    ),

  getConfig: () =>
    api.get<Record<string, unknown>>('/v2/admin/enterprise/config'),

  updateConfig: (data: Record<string, unknown>) =>
    api.put('/v2/admin/enterprise/config', data),

  getSecrets: () =>
    api.get<SecretEntry[]>('/v2/admin/enterprise/config/secrets'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Legal Documents
// ─────────────────────────────────────────────────────────────────────────────

export const adminLegalDocs = {
  list: () =>
    api.get<LegalDocument[]>('/v2/admin/legal-documents'),

  get: (id: number) =>
    api.get<LegalDocument>(`/v2/admin/legal-documents/${id}`),

  create: (data: { title: string; content: string; type: string; version?: string; status?: string }) =>
    api.post<LegalDocument>('/v2/admin/legal-documents', data),

  update: (id: number, data: Record<string, unknown>) =>
    api.put(`/v2/admin/legal-documents/${id}`, data),

  delete: (id: number) =>
    api.delete(`/v2/admin/legal-documents/${id}`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Newsletters
// ─────────────────────────────────────────────────────────────────────────────

export const adminNewsletters = {
  list: (params: { page?: number; status?: string } = {}) =>
    api.get(`/v2/admin/newsletters${buildQuery(params)}`),

  get: (id: number) => api.get(`/v2/admin/newsletters/${id}`),

  create: (data: Record<string, unknown>) =>
    api.post('/v2/admin/newsletters', data),

  update: (id: number, data: Record<string, unknown>) =>
    api.put(`/v2/admin/newsletters/${id}`, data),

  delete: (id: number) => api.delete(`/v2/admin/newsletters/${id}`),

  getSubscribers: () => api.get('/v2/admin/newsletters/subscribers'),

  getSegments: () => api.get('/v2/admin/newsletters/segments'),

  getTemplates: () => api.get('/v2/admin/newsletters/templates'),

  getAnalytics: () => api.get('/v2/admin/newsletters/analytics'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Volunteering
// ─────────────────────────────────────────────────────────────────────────────

export const adminVolunteering = {
  getOverview: () => api.get('/v2/admin/volunteering'),

  getApprovals: () => api.get('/v2/admin/volunteering/approvals'),

  getOrganizations: () => api.get('/v2/admin/volunteering/organizations'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Federation
// ─────────────────────────────────────────────────────────────────────────────

export const adminFederation = {
  getSettings: () => api.get('/v2/admin/federation/settings'),

  getPartnerships: () => api.get('/v2/admin/federation/partnerships'),

  getDirectory: () => api.get('/v2/admin/federation/directory'),

  getProfile: () => api.get('/v2/admin/federation/directory/profile'),

  getAnalytics: () => api.get('/v2/admin/federation/analytics'),

  getApiKeys: () => api.get('/v2/admin/federation/api-keys'),

  createApiKey: (data: { name: string; scopes?: string[] }) =>
    api.post('/v2/admin/federation/api-keys', data),

  getDataManagement: () => api.get('/v2/admin/federation/data'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Pages (CMS)
// ─────────────────────────────────────────────────────────────────────────────

export const adminPages = {
  list: () => api.get<Array<{ id: number; title: string; slug: string; status: string; sort_order: number; created_at: string }>>('/v2/admin/pages'),
  get: (id: number) => api.get('/v2/admin/pages/' + id),
  create: (data: { title: string; content?: string; meta_description?: string; status?: string }) =>
    api.post('/v2/admin/pages', data),
  update: (id: number, data: Record<string, unknown>) =>
    api.put('/v2/admin/pages/' + id, data),
  delete: (id: number) => api.delete('/v2/admin/pages/' + id),
};

// ─────────────────────────────────────────────────────────────────────────────
// Menus
// ─────────────────────────────────────────────────────────────────────────────

export const adminMenus = {
  list: () => api.get<Array<{ id: number; name: string; slug: string; location: string; is_active: boolean; item_count: number }>>('/v2/admin/menus'),
  get: (id: number) => api.get('/v2/admin/menus/' + id),
  create: (data: { name: string; location: string; description?: string }) =>
    api.post('/v2/admin/menus', data),
  update: (id: number, data: Record<string, unknown>) =>
    api.put('/v2/admin/menus/' + id, data),
  delete: (id: number) => api.delete('/v2/admin/menus/' + id),
  getItems: (menuId: number) => api.get('/v2/admin/menus/' + menuId + '/items'),
  createItem: (menuId: number, data: Record<string, unknown>) =>
    api.post('/v2/admin/menus/' + menuId + '/items', data),
  updateItem: (itemId: number, data: Record<string, unknown>) =>
    api.put('/v2/admin/menu-items/' + itemId, data),
  deleteItem: (itemId: number) => api.delete('/v2/admin/menu-items/' + itemId),
  reorderItems: (menuId: number, items: Array<{ id: number; sort_order: number; parent_id?: number | null }>) =>
    api.post('/v2/admin/menus/' + menuId + '/items/reorder', { items }),
};

// ─────────────────────────────────────────────────────────────────────────────
// Plans & Subscriptions
// ─────────────────────────────────────────────────────────────────────────────

export const adminPlans = {
  list: () => api.get<Array<{ id: number; name: string; slug: string; tier_level: number; price_monthly: number; price_yearly: number; is_active: boolean }>>('/v2/admin/plans'),
  get: (id: number) => api.get('/v2/admin/plans/' + id),
  create: (data: { name: string; description?: string; price_monthly?: number; price_yearly?: number; tier_level?: number }) =>
    api.post('/v2/admin/plans', data),
  update: (id: number, data: Record<string, unknown>) =>
    api.put('/v2/admin/plans/' + id, data),
  delete: (id: number) => api.delete('/v2/admin/plans/' + id),
  getSubscriptions: () => api.get('/v2/admin/subscriptions'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Deliverability
// ─────────────────────────────────────────────────────────────────────────────

export const adminDeliverability = {
  getDashboard: () => api.get('/v2/admin/deliverability/dashboard'),
  list: (params: { page?: number; status?: string; priority?: string } = {}) =>
    api.get('/v2/admin/deliverability' + buildQuery(params)),
  get: (id: number) => api.get('/v2/admin/deliverability/' + id),
  create: (data: { title: string; description?: string; priority?: string; due_date?: string; assigned_to?: number }) =>
    api.post('/v2/admin/deliverability', data),
  update: (id: number, data: Record<string, unknown>) =>
    api.put('/v2/admin/deliverability/' + id, data),
  delete: (id: number) => api.delete('/v2/admin/deliverability/' + id),
  getAnalytics: () => api.get('/v2/admin/deliverability/analytics'),
  addComment: (id: number, data: { comment_text: string; comment_type?: string }) =>
    api.post('/v2/admin/deliverability/' + id + '/comments', data),
};

// ─────────────────────────────────────────────────────────────────────────────
// Diagnostics (Matching & Nexus Score)
// ─────────────────────────────────────────────────────────────────────────────

export const adminDiagnostics = {
  diagnoseUser: (userId: number) =>
    api.get('/v2/admin/matching/stats?user_id=' + userId),
  diagnoseListing: (listingId: number) =>
    api.get('/v2/admin/matching/stats?listing_id=' + listingId),
  getMatchingStats: () => api.get('/v2/admin/matching/stats'),
  getNexusScoreStats: () => api.get('/v2/admin/gamification/stats'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Admin Settings
// ─────────────────────────────────────────────────────────────────────────────

export const adminSettings = {
  get: () => api.get<Record<string, unknown>>('/v2/admin/settings'),
  update: (data: Record<string, unknown>) => api.put('/v2/admin/settings', data),

  getAiConfig: () => api.get<Record<string, unknown>>('/v2/admin/config/ai'),
  updateAiConfig: (data: Record<string, unknown>) => api.put('/v2/admin/config/ai', data),

  getFeedAlgorithm: () => api.get<Record<string, unknown>>('/v2/admin/config/feed-algorithm'),
  updateFeedAlgorithm: (data: Record<string, unknown>) => api.put('/v2/admin/config/feed-algorithm', data),

  getImageSettings: () => api.get<Record<string, unknown>>('/v2/admin/config/images'),
  updateImageSettings: (data: Record<string, unknown>) => api.put('/v2/admin/config/images', data),

  getSeoSettings: () => api.get<Record<string, unknown>>('/v2/admin/config/seo'),
  updateSeoSettings: (data: Record<string, unknown>) => api.put('/v2/admin/config/seo', data),

  getNativeAppSettings: () => api.get<Record<string, unknown>>('/v2/admin/config/native-app'),
  updateNativeAppSettings: (data: Record<string, unknown>) => api.put('/v2/admin/config/native-app', data),
};

// ─────────────────────────────────────────────────────────────────────────────
// Admin Tools (SEO, Health, WebP, Seeds, Backups)
// ─────────────────────────────────────────────────────────────────────────────

export const adminTools = {
  getRedirects: () => api.get<Array<{ id: number; from_url: string; to_url: string; status_code: number; hits: number; created_at: string }>>('/v2/admin/tools/redirects'),
  createRedirect: (data: { from_url: string; to_url: string; status_code?: number }) =>
    api.post('/v2/admin/tools/redirects', data),
  deleteRedirect: (id: number) => api.delete('/v2/admin/tools/redirects/' + id),

  get404Errors: () => api.get<Array<{ id: number; url: string; referrer: string; hits: number; first_seen: string; last_seen: string }>>('/v2/admin/tools/404-errors'),
  delete404Error: (id: number) => api.delete('/v2/admin/tools/404-errors/' + id),

  runHealthCheck: () => api.post<Array<{ name: string; status: string; duration_ms: number; error?: string }>>('/v2/admin/tools/health-check'),

  getWebpStats: () => api.get<{ total_images: number; webp_images: number; pending_conversion: number }>('/v2/admin/tools/webp-stats'),
  runWebpConversion: () => api.post('/v2/admin/tools/webp-convert'),

  runSeedGenerator: (data: { types: string[]; counts: Record<string, number> }) =>
    api.post('/v2/admin/tools/seed', data),

  getBlogBackups: () => api.get<Array<{ id: number; filename: string; created_at: string; size: string }>>('/v2/admin/tools/blog-backups'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Re-export for convenience
// ─────────────────────────────────────────────────────────────────────────────

export type { ApiResponse };
