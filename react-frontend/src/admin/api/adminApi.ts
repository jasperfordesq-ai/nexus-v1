// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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
  FeaturedListing,
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
  WalletGrant,
  CronJob,
  PaginatedResponse,
  AdminBlogPost,
  CreateBlogPostPayload,
  UpdateBlogPostPayload,
  BadgeDefinition,
  BadgeConfigEntry,
  BadgeConfigUpdate,
  BrokerDashboardStats,
  ExchangeRequest,
  ExchangeDetail,
  RiskTag,
  BrokerMessage,
  BrokerMessageDetail,
  BrokerArchive,
  BrokerArchiveDetail,
  MonitoredUser,
  BrokerConfig,
  AdminGroup,
  GroupApproval,
  GroupAnalyticsData,
  GroupModerationItem,
  GroupType,
  GroupPolicy,
  GroupMember,
  GroupRecommendation,
  FeaturedGroup,
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
  LegalDocumentVersion,
  VersionComparison,
  ComplianceStats,
  UserAcceptance,
  Newsletter,
  NewsletterBounce,
  SuppressionListEntry,
  ResendInfo,
  SendTimeData,
  NewsletterDiagnostics,
  BounceTrendsData,
  SuperAdminDashboardStats,
  SuperAdminTenant,
  SuperAdminTenantDetail,
  CreateTenantPayload,
  UpdateTenantPayload,
  TenantHierarchyNode,
  SuperAdminUser,
  SuperAdminUserDetail,
  CreateSuperUserPayload,
  SuperUserListParams,
  BulkMoveUsersPayload,
  BulkUpdateTenantsPayload,
  BulkOperationResult,
  SuperAuditEntry,
  SuperAuditParams,
  FederationSystemControls,
  FederationWhitelistEntry,
  FederationPartnership,
  FederationStatusOverview,
  TenantFederationFeatures,
  VettingRecord,
  VettingStats,
  InsuranceCertificate,
  InsuranceStats,
  CronLog,
  CronJobSettings,
  GlobalCronSettings,
  CronHealthMetrics,
  AdminFeedPost,
  AdminComment,
  AdminReview,
  AdminReport,
  ModerationStats,
  CrmDashboardStats,
  CrmFunnelData,
  MemberNote,
  CoordinatorTask,
  MemberTag,
  TagSummary,
  CrmAdmin,
  TimelineEntry,
} from './types';

// ─────────────────────────────────────────────────────────────────────────────
// Helper
// ─────────────────────────────────────────────────────────────────────────────

function buildQuery<T extends object>(params: T): string {
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
    api.get<ActivityLogEntry[]>(
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
    api.delete<{ success: boolean }>(`/v2/admin/users/${id}`),

  approve: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/users/${id}/approve`),

  suspend: (id: number, reason?: string) =>
    api.post<{ success: boolean }>(`/v2/admin/users/${id}/suspend`, { reason }),

  ban: (id: number, reason?: string) =>
    api.post<{ success: boolean }>(`/v2/admin/users/${id}/ban`, { reason }),

  reactivate: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/users/${id}/reactivate`),

  reset2fa: (id: number, reason: string) =>
    api.post<{ success: boolean }>(`/v2/admin/users/${id}/reset-2fa`, { reason }),

  addBadge: (userId: number, badgeSlug: string) =>
    api.post<{ success: boolean }>(`/v2/admin/users/${userId}/badges`, { badge_slug: badgeSlug }),

  removeBadge: (userId: number, badgeId: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/users/${userId}/badges/${badgeId}`),

  recheckAllBadges: () =>
    api.post<{ success: boolean }>('/v2/admin/users/badges/recheck-all'),

  impersonate: (userId: number) =>
    api.post<{ token: string }>(`/v2/admin/users/${userId}/impersonate`),
  setSuperAdmin: (userId: number, grant: boolean) =>
    api.put<{ success: boolean }>(`/v2/admin/users/${userId}/super-admin`, { grant }),
  setGlobalSuperAdmin: (userId: number, grant: boolean) =>
    api.put<{ success: boolean }>(`/v2/admin/users/${userId}/global-super-admin`, { grant }),

  recheckUserBadges: (userId: number) =>
    api.post<{ rechecked: boolean; user_id: number; badges: import('./types').AdminBadge[] }>(`/v2/admin/users/${userId}/badges/recheck`),

  getConsents: (userId: number) =>
    api.get<import('./types').UserConsent[]>(`/v2/admin/users/${userId}/consents`),

  setPassword: (userId: number, password: string) =>
    api.post<{ success: boolean }>(`/v2/admin/users/${userId}/password`, { password }),

  sendPasswordReset: (userId: number) =>
    api.post<{ success: boolean }>(`/v2/admin/users/${userId}/send-password-reset`),

  sendWelcomeEmail: (userId: number) =>
    api.post<{ success: boolean }>(`/v2/admin/users/${userId}/send-welcome-email`),

  importUsers: (file: File, options?: { default_role?: string }) => {
    const formData = new FormData();
    formData.append('csv_file', file);
    if (options?.default_role) formData.append('default_role', options.default_role);
    return api.upload<{ imported: number; skipped: number; errors: string[]; total_rows: number }>('/v2/admin/users/import', formData);
  },

  downloadImportTemplate: () => {
    const baseUrl = import.meta.env.VITE_API_BASE || '/api';
    window.open(`${baseUrl}/v2/admin/users/import/template`, '_blank');
  },
};

// ─────────────────────────────────────────────────────────────────────────────
// Config (Features & Modules) — V2 API already exists
// ─────────────────────────────────────────────────────────────────────────────

export const adminConfig = {
  get: () =>
    api.get<TenantConfig>('/v2/admin/config'),

  updateFeature: (feature: string, enabled: boolean) =>
    api.put<{ success: boolean }>('/v2/admin/config/features', { feature, enabled }),

  updateModule: (module: string, enabled: boolean) =>
    api.put<{ success: boolean }>('/v2/admin/config/modules', { module, enabled }),

  getCacheStats: () =>
    api.get<CacheStats>('/v2/admin/cache/stats'),

  clearCache: (type: 'all' | 'tenant' = 'tenant') =>
    api.post<{ success: boolean }>('/v2/admin/cache/clear', { type }),

  getJobs: () =>
    api.get<BackgroundJob[]>('/v2/admin/background-jobs'),

  runJob: (jobId: string) =>
    api.post<{ success: boolean }>(`/v2/admin/background-jobs/${jobId}/run`),

  getLanguageConfig: () =>
    api.get<{ default_language: string; supported_languages: string[] }>(
      '/v2/admin/config/languages'
    ),

  updateLanguageConfig: (config: {
    default_language?: string;
    supported_languages?: string[];
  }) =>
    api.put<{ default_language: string; supported_languages: string[] }>(
      '/v2/admin/config/languages',
      config
    ),
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
    api.post<{ success: boolean }>(`/v2/admin/listings/${id}/approve`),

  reject: (id: number, reason?: string) =>
    api.post<{ success: boolean }>(`/v2/admin/listings/${id}/reject`, reason ? { reason } : {}),

  feature: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/listings/${id}/feature`),

  unfeature: (id: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/listings/${id}/feature`),

  delete: (id: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/listings/${id}`),

  // Featured listings
  getFeatured: () =>
    api.get<FeaturedListing[]>('/v2/admin/listings/featured'),
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
    api.delete<{ success: boolean }>(`/v2/admin/categories/${id}`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Attributes
// ─────────────────────────────────────────────────────────────────────────────

export const adminAttributes = {
  list: () =>
    api.get<AdminAttribute[]>('/v2/admin/attributes'),

  create: (data: { name: string; type?: string; category_id?: number | null }) =>
    api.post<AdminAttribute>('/v2/admin/attributes', data),

  update: (id: number, data: { name?: string; type?: string; category_id?: number | null; is_active?: boolean }) =>
    api.put<AdminAttribute>(`/v2/admin/attributes/${id}`, data),

  delete: (id: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/attributes/${id}`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Gamification
// ─────────────────────────────────────────────────────────────────────────────

export const adminGamification = {
  getStats: () =>
    api.get<GamificationStats>('/v2/admin/gamification/stats'),

  recheckAll: () =>
    api.post<{ success: boolean }>('/v2/admin/gamification/recheck-all'),

  bulkAward: (badgeSlug: string, userIds: number[]) =>
    api.post<{ success: boolean; awarded: number }>('/v2/admin/gamification/bulk-award', { badge_slug: badgeSlug, user_ids: userIds }),

  listCampaigns: () =>
    api.get<Campaign[]>('/v2/admin/gamification/campaigns'),

  createCampaign: (data: Partial<Campaign>) =>
    api.post<Campaign>('/v2/admin/gamification/campaigns', data),

  updateCampaign: (id: number, data: Partial<Campaign>) =>
    api.put<Campaign>(`/v2/admin/gamification/campaigns/${id}`, data),

  deleteCampaign: (id: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/gamification/campaigns/${id}`),

  listBadges: () =>
    api.get<BadgeDefinition[]>('/v2/admin/gamification/badges'),

  createBadge: (data: { name: string; slug?: string; description: string; icon?: string; category?: string; xp?: number; is_active?: boolean }) =>
    api.post<BadgeDefinition>('/v2/admin/gamification/badges', data),

  deleteBadge: (id: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/gamification/badges/${id}`),

  getBadgeConfig: () =>
    api.get<BadgeConfigEntry[]>('/v2/admin/gamification/badge-config'),

  updateBadgeConfig: (badgeKey: string, data: Partial<BadgeConfigUpdate>) =>
    api.put<{ success: boolean }>(`/v2/admin/gamification/badge-config/${badgeKey}`, data),

  resetBadgeConfig: (badgeKey: string) =>
    api.post<{ success: boolean }>(`/v2/admin/gamification/badge-config/${badgeKey}/reset`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Matching
// ─────────────────────────────────────────────────────────────────────────────

export const adminMatching = {
  getConfig: () =>
    api.get<SmartMatchingConfig>('/v2/admin/matching/config'),

  updateConfig: (data: Partial<SmartMatchingConfig>) =>
    api.put<{ success: boolean }>('/v2/admin/matching/config', data),

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
    api.post<{ success: boolean }>(`/v2/admin/matching/approvals/${id}/approve`, { notes }),

  rejectMatch: (id: number, reason: string) =>
    api.post<{ success: boolean }>(`/v2/admin/matching/approvals/${id}/reject`, { reason }),

  clearCache: () =>
    api.post<{ success: boolean }>('/v2/admin/matching/cache/clear'),
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
    api.put<{ success: boolean }>(`/v2/admin/timebanking/alerts/${id}`, { status }),

  adjustBalance: (userId: number, amount: number, reason: string) =>
    api.post<{ success: boolean }>('/v2/admin/timebanking/adjust-balance', { user_id: userId, amount, reason }),

  getOrgWallets: () =>
    api.get<OrgWallet[]>('/v2/admin/timebanking/org-wallets'),

  getUserReport: (params: { page?: number; search?: string } = {}) =>
    api.get<PaginatedResponse<UserFinancialReport>>(
      `/v2/admin/timebanking/user-report${buildQuery(params)}`
    ),

  getUserStatement: (params: { user_id: number; start_date?: string; end_date?: string }) =>
    api.get<{
      user: { id: number; first_name: string; last_name: string; email: string; balance: number };
      period: { start: string; end: string };
      summary: {
        total_transactions: number;
        hours_earned: number;
        hours_spent: number;
        net_change: number;
        current_balance: number;
      };
      transactions: Array<Record<string, unknown>>;
    }>(`/v2/admin/timebanking/user-statement${buildQuery(params)}`),

  downloadStatementCsv: async (userId: number, startDate?: string, endDate?: string) => {
    const params = buildQuery({
      user_id: userId,
      format: 'csv',
      start_date: startDate,
      end_date: endDate,
    });
    await api.download(`/v2/admin/timebanking/user-statement${params}`, {
      filename: `statement_${userId}_${new Date().toISOString().slice(0, 10)}.csv`,
    });
  },

  // Starting balance grants
  getGrants: (params: { page?: number; search?: string } = {}) =>
    api.get<PaginatedResponse<WalletGrant>>(
      `/v2/admin/wallet/grants${buildQuery(params)}`
    ),

  grantCredits: (data: { user_id: number; amount: number; reason: string }) =>
    api.post<{ success: boolean }>('/v2/admin/wallet/grant', data),
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
    api.delete<{ success: boolean }>(`/v2/admin/blog/${id}`),

  toggleStatus: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/blog/${id}/toggle-status`),
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
    api.post<{ success: boolean }>(`/v2/admin/broker/exchanges/${id}/approve`, { notes }),

  rejectExchange: (id: number, reason: string) =>
    api.post<{ success: boolean }>(`/v2/admin/broker/exchanges/${id}/reject`, { reason }),

  getRiskTags: (params: { risk_level?: string } = {}) =>
    api.get<RiskTag[]>(`/v2/admin/broker/risk-tags${buildQuery(params)}`),

  getMessages: (params: { page?: number; filter?: string } = {}) =>
    api.get<PaginatedResponse<BrokerMessage>>(
      `/v2/admin/broker/messages${buildQuery(params)}`
    ),

  getUnreviewedCount: () =>
    api.get<{ count: number }>('/v2/admin/broker/messages/unreviewed-count'),

  reviewMessage: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/broker/messages/${id}/review`),

  getMonitoring: () =>
    api.get<MonitoredUser[]>('/v2/admin/broker/monitoring'),

  flagMessage: (id: number, reason: string, severity: 'info' | 'warning' | 'concern' | 'urgent') =>
    api.post<{ success: boolean }>(`/v2/admin/broker/messages/${id}/flag`, { reason, severity }),

  setMonitoring: (userId: number, data: { under_monitoring: boolean; reason?: string; messaging_disabled?: boolean; expires_days?: number }) =>
    api.post<{ success: boolean }>(`/v2/admin/broker/monitoring/${userId}`, data),

  saveRiskTag: (listingId: number, data: {
    risk_level: 'low' | 'medium' | 'high' | 'critical';
    risk_category: string;
    risk_notes?: string;
    member_visible_notes?: string;
    requires_approval?: boolean;
    insurance_required?: boolean;
    dbs_required?: boolean;
  }) => api.post<{ success: boolean }>(`/v2/admin/broker/risk-tags/${listingId}`, data),

  removeRiskTag: (listingId: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/broker/risk-tags/${listingId}`),

  getConfiguration: () =>
    api.get<BrokerConfig>('/v2/admin/broker/configuration'),

  saveConfiguration: (config: Partial<BrokerConfig>) =>
    api.post<BrokerConfig>('/v2/admin/broker/configuration', config),

  showExchange: (id: number) =>
    api.get<ExchangeDetail>(`/v2/admin/broker/exchanges/${id}`),

  showMessage: (id: number) =>
    api.get<BrokerMessageDetail>(`/v2/admin/broker/messages/${id}`),

  approveMessage: (id: number, notes?: string) =>
    api.post<{ success: boolean }>(`/v2/admin/broker/messages/${id}/approve`, { notes }),

  getArchives: (params: { page?: number; decision?: string; search?: string; from?: string; to?: string } = {}) =>
    api.get<PaginatedResponse<BrokerArchive>>(
      `/v2/admin/broker/archives${buildQuery(params)}`
    ),

  showArchive: (id: number) =>
    api.get<BrokerArchiveDetail>(`/v2/admin/broker/archives/${id}`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Groups
// ─────────────────────────────────────────────────────────────────────────────

export const adminGroups = {
  list: (params: { page?: number; per_page?: number; status?: string; search?: string } = {}) =>
    api.get<PaginatedResponse<AdminGroup>>(
      `/v2/admin/groups${buildQuery(params)}`
    ),

  getAnalytics: () =>
    api.get<GroupAnalyticsData>('/v2/admin/groups/analytics'),

  getApprovals: () =>
    api.get<GroupApproval[]>('/v2/admin/groups/approvals'),

  approveMember: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/groups/approvals/${id}/approve`),

  rejectMember: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/groups/approvals/${id}/reject`),

  getModeration: () =>
    api.get<GroupModerationItem[]>('/v2/admin/groups/moderation'),

  updateStatus: (id: number, status: 'active' | 'inactive') =>
    api.put<{ success: boolean }>(`/v2/admin/groups/${id}/status`, { status }),

  delete: (id: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/groups/${id}`),

  // Group types
  getGroupTypes: () =>
    api.get<GroupType[]>('/v2/admin/groups/types'),

  createGroupType: (data: Partial<GroupType>) =>
    api.post<GroupType>('/v2/admin/groups/types', data),

  updateGroupType: (id: number, data: Partial<GroupType>) =>
    api.put<GroupType>(`/v2/admin/groups/types/${id}`, data),

  deleteGroupType: (id: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/groups/types/${id}`),

  // Policies
  getPolicies: (typeId: number) =>
    api.get<GroupPolicy[]>(`/v2/admin/groups/types/${typeId}/policies`),

  setPolicy: (typeId: number, key: string, value: string | number | boolean) =>
    api.put<{ success: boolean }>(`/v2/admin/groups/types/${typeId}/policies`, { key, value }),

  // Group detail
  getGroup: (id: number) =>
    api.get<AdminGroup>(`/v2/admin/groups/${id}`),

  updateGroup: (id: number, data: Partial<Pick<AdminGroup, 'name' | 'description' | 'location' | 'status'>>) =>
    api.put<AdminGroup>(`/v2/admin/groups/${id}`, data),

  getMembers: (groupId: number, params?: { role?: string; limit?: number; offset?: number }) =>
    api.get<GroupMember[]>(`/v2/admin/groups/${groupId}/members${buildQuery(params || {})}`),

  promoteMember: (groupId: number, userId: number) =>
    api.post<{ success: boolean }>(`/v2/admin/groups/${groupId}/members/${userId}/promote`, {}),

  demoteMember: (groupId: number, userId: number) =>
    api.post<{ success: boolean }>(`/v2/admin/groups/${groupId}/members/${userId}/demote`, {}),

  kickMember: (groupId: number, userId: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/groups/${groupId}/members/${userId}`),

  // Geocoding
  geocodeGroup: (groupId: number) =>
    api.post<{ success: boolean }>(`/v2/admin/groups/${groupId}/geocode`, {}),

  batchGeocode: () =>
    api.post<{ success: boolean; geocoded: number }>('/v2/admin/groups/batch-geocode', {}),

  // Recommendations
  getRecommendationData: (params?: { limit?: number; offset?: number }) =>
    api.get<{ recommendations: GroupRecommendation[]; stats: { total: number; avg_score: number; join_rate: number } }>(`/v2/admin/groups/recommendations${buildQuery(params || {})}`),

  // Ranking
  getFeaturedGroups: () =>
    api.get<FeaturedGroup[]>('/v2/admin/groups/featured'),

  updateFeaturedGroups: () =>
    api.post<{ success: boolean }>('/v2/admin/groups/featured/update', {}),

  toggleFeatured: (groupId: number) =>
    api.put<{ success: boolean }>(`/v2/admin/groups/${groupId}/toggle-featured`, {}),
};

// ─────────────────────────────────────────────────────────────────────────────
// System
// ─────────────────────────────────────────────────────────────────────────────

export const adminSystem = {
  getCronJobs: () =>
    api.get<CronJob[]>('/v2/admin/system/cron-jobs'),

  runCronJob: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/system/cron-jobs/${id}/run`),

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
    api.post<Role>('/v2/admin/enterprise/roles', data),

  updateRole: (id: number, data: Partial<Role>) =>
    api.put<Role>(`/v2/admin/enterprise/roles/${id}`, data),

  deleteRole: (id: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/enterprise/roles/${id}`),

  getPermissions: () =>
    api.get<Record<string, string[]>>('/v2/admin/enterprise/permissions'),

  getGdprDashboard: () =>
    api.get<GdprDashboardStats>('/v2/admin/enterprise/gdpr/dashboard'),

  getGdprRequests: (params: { page?: number; status?: string } = {}) =>
    api.get<PaginatedResponse<GdprRequest>>(
      `/v2/admin/enterprise/gdpr/requests${buildQuery(params)}`
    ),

  updateGdprRequest: (id: number, data: { status: string; notes?: string }) =>
    api.put<{ success: boolean }>(`/v2/admin/enterprise/gdpr/requests/${id}`, data),

  getGdprConsents: () =>
    api.get<GdprConsent[]>('/v2/admin/enterprise/gdpr/consents'),

  getGdprBreaches: () =>
    api.get<GdprBreach[]>('/v2/admin/enterprise/gdpr/breaches'),

  createBreach: (data: { title: string; description?: string; severity?: string; affected_users?: number }) =>
    api.post<GdprBreach>('/v2/admin/enterprise/gdpr/breaches', data),

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
    api.put<{ success: boolean }>('/v2/admin/enterprise/config', data),

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
    api.put<LegalDocument>(`/v2/admin/legal-documents/${id}`, data),

  delete: (id: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/legal-documents/${id}`),

  // Version Management
  getVersions: (docId: number) =>
    api.get<LegalDocumentVersion[]>(`/v2/admin/legal-documents/${docId}/versions`),

  compareVersions: (docId: number, v1: number, v2: number) =>
    api.get<VersionComparison>(`/v2/admin/legal-documents/${docId}/versions/compare${buildQuery({ v1, v2 })}`),

  createVersion: (docId: number, data: { version_number: string; version_label?: string; content: string; summary_of_changes?: string; effective_date: string; is_draft?: boolean }) =>
    api.post<{ id: number }>(`/v2/admin/legal-documents/${docId}/versions`, data),

  updateVersion: (docId: number, versionId: number, data: { version_number?: string; version_label?: string; content?: string; summary_of_changes?: string; effective_date?: string }) =>
    api.put<{ updated: boolean }>(`/v2/admin/legal-documents/${docId}/versions/${versionId}`, data),

  deleteVersion: (docId: number, versionId: number) =>
    api.delete<{ deleted: boolean }>(`/v2/admin/legal-documents/${docId}/versions/${versionId}`),

  publishVersion: (versionId: number) =>
    api.post<{ published: boolean }>(`/v2/admin/legal-documents/versions/${versionId}/publish`, {}),

  // Compliance & Acceptance Tracking
  getComplianceStats: (docId?: number) =>
    api.get<ComplianceStats>(`/v2/admin/legal-documents/compliance${buildQuery({ doc_id: docId })}`),

  getAcceptances: (versionId: number, limit = 50, offset = 0) =>
    api.get<UserAcceptance[]>(`/v2/admin/legal-documents/versions/${versionId}/acceptances${buildQuery({ limit, offset })}`),

  exportAcceptances: (docId: number, startDate?: string, endDate?: string) => {
    const query = buildQuery({ start_date: startDate, end_date: endDate });
    return api.get<{ data: unknown }>(`/v2/admin/legal-documents/${docId}/acceptances/export${query}`);
  },

  // Notifications
  notifyUsers: (docId: number, versionId: number, notify: { target: 'all' | 'non_accepted' }) =>
    api.post<{ notified: boolean }>(`/v2/admin/legal-documents/${docId}/versions/${versionId}/notify`, notify),

  getUsersPendingCount: (docId: number, versionId: number) =>
    api.get<{ count: number }>(`/v2/admin/legal-documents/${docId}/versions/${versionId}/pending-count`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Newsletters
// ─────────────────────────────────────────────────────────────────────────────

export const adminNewsletters = {
  list: (params: { page?: number; status?: string } = {}) =>
    api.get<PaginatedResponse<Newsletter>>(`/v2/admin/newsletters${buildQuery(params)}`),

  get: (id: number) => api.get<Newsletter & Record<string, unknown>>(`/v2/admin/newsletters/${id}`),

  create: (data: Record<string, unknown>) =>
    api.post<Newsletter>('/v2/admin/newsletters', data),

  update: (id: number, data: Record<string, unknown>) =>
    api.put<Newsletter>(`/v2/admin/newsletters/${id}`, data),

  delete: (id: number) => api.delete<{ success: boolean }>(`/v2/admin/newsletters/${id}`),

  getSubscribers: (params?: { page?: number; per_page?: number; status?: string; search?: string }) =>
    api.get<PaginatedResponse<{ id: number; email: string; first_name?: string; last_name?: string; status: string; subscribed_at: string }>>(`/v2/admin/newsletters/subscribers${buildQuery(params || {})}`),

  addSubscriber: (data: { email: string; first_name?: string; last_name?: string }) =>
    api.post<{ success: boolean }>('/v2/admin/newsletters/subscribers', data),

  removeSubscriber: (id: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/newsletters/subscribers/${id}`),

  importSubscribers: (rows: Array<{ email: string; first_name?: string; last_name?: string }>) =>
    api.post<{ imported: number; skipped: number; errors: string[] }>('/v2/admin/newsletters/subscribers/import', { rows }),

  exportSubscribers: () =>
    api.get<{ data: unknown }>('/v2/admin/newsletters/subscribers/export'),

  syncMembers: () =>
    api.post<{ synced: number }>('/v2/admin/newsletters/subscribers/sync', {}),

  getSegments: () => api.get<Array<{ id: number; name: string; rules: Record<string, unknown>; subscriber_count: number; created_at: string }>>('/v2/admin/newsletters/segments'),

  getSegment: (id: number) => api.get<{ id: number; name: string; rules: Record<string, unknown>; subscriber_count: number; created_at: string }>(`/v2/admin/newsletters/segments/${id}`),

  createSegment: (data: Record<string, unknown>) =>
    api.post<{ id: number; name: string }>('/v2/admin/newsletters/segments', data),

  updateSegment: (id: number, data: Record<string, unknown>) =>
    api.put<{ success: boolean }>(`/v2/admin/newsletters/segments/${id}`, data),

  deleteSegment: (id: number) => api.delete<{ success: boolean }>(`/v2/admin/newsletters/segments/${id}`),

  previewSegment: (rules: Record<string, unknown>) =>
    api.post<{ matching_count: number; count?: number; sample?: Array<{ id: number; email: string; name: string }> }>('/v2/admin/newsletters/segments/preview', rules),

  getSegmentSuggestions: () => api.get<Array<{ name: string; description: string; match_type: string; rules: Array<{ field: string; operator: string; value: string }>; estimated_count: number }>>('/v2/admin/newsletters/segments/suggestions'),

  getTemplates: () => api.get<Array<{ id: number; name: string; subject: string; content: string; created_at: string }>>('/v2/admin/newsletters/templates'),

  getTemplate: (id: number) => api.get<{ id: number; name: string; subject: string; content: string; created_at: string }>(`/v2/admin/newsletters/templates/${id}`),

  createTemplate: (data: Record<string, unknown>) =>
    api.post<{ id: number; name: string }>('/v2/admin/newsletters/templates', data),

  updateTemplate: (id: number, data: Record<string, unknown>) =>
    api.put<{ success: boolean }>(`/v2/admin/newsletters/templates/${id}`, data),

  deleteTemplate: (id: number) => api.delete<{ success: boolean }>(`/v2/admin/newsletters/templates/${id}`),

  duplicateTemplate: (id: number) =>
    api.post<{ id: number; name: string }>(`/v2/admin/newsletters/templates/${id}/duplicate`, {}),

  previewTemplate: (id: number) => api.get<{ html: string }>(`/v2/admin/newsletters/templates/${id}/preview`),

  getAnalytics: () => api.get<{ total_sent: number; total_opened: number; total_clicked: number; avg_open_rate: number; avg_click_rate: number }>('/v2/admin/newsletters/analytics'),

  // Bounce management
  getBounces: (params: { limit?: number; offset?: number; type?: string; startDate?: string; endDate?: string } = {}) =>
    api.get<PaginatedResponse<NewsletterBounce>>(`/v2/admin/newsletters/bounces${buildQuery(params)}`),

  getSuppressionList: () => api.get<SuppressionListEntry[]>('/v2/admin/newsletters/suppression-list'),

  unsuppress: (email: string) =>
    api.post<{ success: boolean }>(`/v2/admin/newsletters/suppression-list/${encodeURIComponent(email)}/unsuppress`, {}),

  suppress: (email: string) =>
    api.post<{ success: boolean }>(`/v2/admin/newsletters/suppression-list/${encodeURIComponent(email)}/suppress`, {}),

  // Resend workflow
  getResendInfo: (newsletterId: number) =>
    api.get<ResendInfo>(`/v2/admin/newsletters/${newsletterId}/resend-info`),

  resend: (newsletterId: number, options: { target: string; segment_id?: number; subject_override?: string }) =>
    api.post<{ success: boolean }>(`/v2/admin/newsletters/${newsletterId}/resend`, options),

  // Send-time optimizer
  getSendTimeData: (params?: { days?: number }) =>
    api.get<SendTimeData>(`/v2/admin/newsletters/send-time-optimizer${buildQuery(params || {})}`),

  // Diagnostics
  getDiagnostics: () => api.get<NewsletterDiagnostics>('/v2/admin/newsletters/diagnostics'),
  getBounceTrends: (params?: { weeks?: number }) =>
    api.get<BounceTrendsData>(`/v2/admin/newsletters/bounce-trends${buildQuery(params || {})}`),

  // Per-campaign stats
  getStats: (id: number) => api.get<{ total_sent: number; total_opened: number; total_clicked: number; open_rate: number; click_rate: number }>(`/v2/admin/newsletters/${id}/stats`),

  selectAbWinner: (id: number, winner: 'a' | 'b') =>
    api.post<{ success: boolean }>(`/v2/admin/newsletters/${id}/ab-winner`, { winner }),

  // Send workflow
  sendNewsletter: (id: number) =>
    api.post<{ queued: number; status: string; message: string }>(`/v2/admin/newsletters/${id}/send`, {}),

  sendTest: (id: number) =>
    api.post<{ sent_to: string; message: string }>(`/v2/admin/newsletters/${id}/send-test`, {}),

  getRecipientCount: (params: { target_audience: string; segment_id?: number }) =>
    api.post<{ count: number }>('/v2/admin/newsletters/recipient-count', params),

  // Duplicate
  duplicateNewsletter: (id: number) =>
    api.post<Newsletter>(`/v2/admin/newsletters/${id}/duplicate`, {}),

  // Activity log
  getActivity: (id: number, params?: { page?: number; per_page?: number; type?: string }) =>
    api.get<PaginatedResponse<{ id: number; type: string; description: string; created_at: string }>>(`/v2/admin/newsletters/${id}/activity${buildQuery(params || {})}`),

  // Per-subscriber engagement lists
  getOpeners: (id: number, params?: { page?: number; per_page?: number }) =>
    api.get<PaginatedResponse<{ email: string; name: string; opened_at: string }>>(`/v2/admin/newsletters/${id}/openers${buildQuery(params || {})}`),

  getClickers: (id: number, params?: { page?: number; per_page?: number }) =>
    api.get<PaginatedResponse<{ email: string; name: string; clicked_at: string }>>(`/v2/admin/newsletters/${id}/clickers${buildQuery(params || {})}`),

  getNonOpeners: (id: number, params?: { page?: number; per_page?: number }) =>
    api.get<PaginatedResponse<{ email: string; name: string }>>(`/v2/admin/newsletters/${id}/non-openers${buildQuery(params || {})}`),

  getOpenersNoClick: (id: number, params?: { page?: number; per_page?: number }) =>
    api.get<PaginatedResponse<{ email: string; name: string; opened_at: string }>>(`/v2/admin/newsletters/${id}/openers-no-click${buildQuery(params || {})}`),

  getEmailClients: (id: number) =>
    api.get<Array<{ client: string; count: number; percentage: number }>>(`/v2/admin/newsletters/${id}/email-clients`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Volunteering
// ─────────────────────────────────────────────────────────────────────────────

export const adminVolunteering = {
  getOverview: () => api.get<{ total_opportunities: number; active_volunteers: number; pending_approvals: number; total_hours: number }>('/v2/admin/volunteering'),

  getApprovals: () => api.get<Array<{ id: number; user_id: number; user_name: string; opportunity_id: number; opportunity_title: string; status: string; created_at: string }>>('/v2/admin/volunteering/approvals'),

  approveApplication: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/volunteering/approvals/${id}/approve`, {}),

  declineApplication: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/volunteering/approvals/${id}/decline`, {}),

  getOrganizations: () => api.get<Array<{ id: number; name: string; opportunity_count: number; volunteer_count: number }>>('/v2/admin/volunteering/organizations'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Federation
// ─────────────────────────────────────────────────────────────────────────────

export const adminFederation = {
  getSettings: () => api.get<FederationSystemControls>('/v2/admin/federation/settings'),

  updateSettings: (data: Record<string, unknown>) =>
    api.put<{ success: boolean }>('/v2/admin/federation/settings', data),

  getPartnerships: () => api.get<FederationPartnership[]>('/v2/admin/federation/partnerships'),

  approvePartnership: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/federation/partnerships/${id}/approve`, {}),

  rejectPartnership: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/federation/partnerships/${id}/reject`, {}),

  terminatePartnership: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/federation/partnerships/${id}/terminate`, {}),

  getDirectory: (params?: { search?: string; region?: string; category?: string; exclude_partnered?: boolean }) =>
    api.get<Array<{ id: number; name: string; slug: string; domain: string; description?: string; region?: string }>>(`/v2/admin/federation/directory${buildQuery(params || {})}`),

  requestPartnership: (targetTenantId: number, notes?: string) =>
    api.post<{ success: boolean }>('/v2/admin/federation/partnerships/request', { target_tenant_id: targetTenantId, notes }),

  getProfile: () => api.get<{ id: number; name: string; description?: string; region?: string; category?: string; is_visible: boolean }>('/v2/admin/federation/directory/profile'),

  updateProfile: (data: Record<string, unknown>) =>
    api.put<{ success: boolean }>('/v2/admin/federation/directory/profile', data),

  getAnalytics: () => api.get<{ total_partnerships: number; active_partnerships: number; cross_tenant_transactions: number; cross_tenant_messages: number }>('/v2/admin/federation/analytics'),

  getApiKeys: () => api.get<Array<{ id: number; name: string; key_prefix: string; scopes: string[]; created_at: string; last_used_at?: string }>>('/v2/admin/federation/api-keys'),

  createApiKey: (data: { name: string; scopes?: string[]; expires_at?: string }) =>
    api.post<{ id: number; key: string; name: string }>('/v2/admin/federation/api-keys', data),

  revokeApiKey: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/federation/api-keys/${id}/revoke`, {}),

  getDataManagement: () => api.get<{ export_formats: string[]; available_exports: Record<string, string>; import_supported: boolean; last_export_at: string | null; last_import_at: string | null }>('/v2/admin/federation/data'),

  getActivityFeed: (params?: {
    limit?: number;
    cursor?: string;
    event_type?: string;
    partner_tenant_id?: number;
    date_from?: string;
    date_to?: string;
    search?: string;
  }) => api.get<{
    items: Array<{
      id: number;
      type: string;
      category: string;
      level: string;
      description: string;
      detail: string | null;
      actor_name: string | null;
      actor_user_id: number | null;
      direction: 'inbound' | 'outbound';
      partner_tenant_id: number | null;
      partner_tenant_name: string | null;
      partner_tenant_slug: string | null;
      timestamp: string;
      data: Record<string, unknown>;
    }>;
    total: number;
    has_more: boolean;
    next_cursor: string | null;
  }>(`/v2/admin/federation/activity${buildQuery(params || {})}`),

  getPartnershipDetail: (id: number) =>
    api.get<Record<string, unknown>>(`/v2/admin/federation/partnerships/${id}`),

  counterProposePartnership: (id: number, data: { level: number; permissions: Record<string, boolean>; message?: string }) =>
    api.post<{ success: boolean }>(`/v2/admin/federation/partnerships/${id}/counter-propose`, data),

  updatePartnershipPermissions: (id: number, permissions: Record<string, boolean>) =>
    api.put<{ success: boolean }>(`/v2/admin/federation/partnerships/${id}/permissions`, { permissions }),

  getPartnershipAuditLog: (id: number) =>
    api.get<Array<Record<string, unknown>>>(`/v2/admin/federation/partnerships/${id}/audit-log`),

  getPartnershipStats: (id: number) =>
    api.get<{ messages_exchanged: number; transactions_completed: number; connections_made: number }>(`/v2/admin/federation/partnerships/${id}/stats`),

  getCreditAgreementTransactions: (id: number) =>
    api.get<{ transactions: Array<Record<string, unknown>>; month_usage: number; monthly_limit: number | null }>(`/v2/admin/federation/credit-agreements/${id}/transactions`),

  getCreditBalances: () =>
    api.get<{ balances: Array<{ agreement_id: number; partner_tenant_id: number; partner_name: string; credits_sent: number; credits_received: number; net_balance: number }>; net_total: number }>('/v2/admin/federation/credit-balances'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Pages (CMS)
// ─────────────────────────────────────────────────────────────────────────────

export const adminPages = {
  list: () => api.get<Array<{ id: number; title: string; slug: string; status: string; sort_order: number; show_in_menu: number; menu_location: string; menu_order: number; created_at: string }>>('/v2/admin/pages'),
  get: (id: number) => api.get<{ id: number; title: string; slug: string; content: string; status: string; meta_description?: string; show_in_menu: number; menu_location: string; menu_order: number; created_at: string }>(`/v2/admin/pages/${id}`),
  create: (data: { title: string; content?: string; meta_description?: string; status?: string; show_in_menu?: number; menu_location?: string; menu_order?: number }) =>
    api.post<{ id: number }>('/v2/admin/pages', data),
  update: (id: number, data: Record<string, unknown>) =>
    api.put<{ success: boolean }>(`/v2/admin/pages/${id}`, data),
  delete: (id: number) => api.delete<{ success: boolean }>(`/v2/admin/pages/${id}`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Menus
// ─────────────────────────────────────────────────────────────────────────────

export const adminMenus = {
  list: () => api.get<Array<{ id: number; name: string; slug: string; location: string; is_active: boolean; item_count: number }>>('/v2/admin/menus'),
  get: (id: number) => api.get<{ id: number; name: string; slug: string; location: string; description?: string; is_active: boolean; item_count: number }>(`/v2/admin/menus/${id}`),
  create: (data: { name: string; location: string; description?: string }) =>
    api.post<{ id: number }>('/v2/admin/menus', data),
  update: (id: number, data: Record<string, unknown>) =>
    api.put<{ success: boolean }>(`/v2/admin/menus/${id}`, data),
  delete: (id: number) => api.delete<{ success: boolean }>(`/v2/admin/menus/${id}`),
  getItems: (menuId: number) => api.get<Array<{ id: number; label: string; url: string; sort_order: number; parent_id: number | null }>>(`/v2/admin/menus/${menuId}/items`),
  createItem: (menuId: number, data: Record<string, unknown>) =>
    api.post<{ id: number }>(`/v2/admin/menus/${menuId}/items`, data),
  updateItem: (itemId: number, data: Record<string, unknown>) =>
    api.put<{ success: boolean }>(`/v2/admin/menu-items/${itemId}`, data),
  deleteItem: (itemId: number) => api.delete<{ success: boolean }>(`/v2/admin/menu-items/${itemId}`),
  reorderItems: (menuId: number, items: Array<{ id: number; sort_order: number; parent_id?: number | null }>) =>
    api.post<{ success: boolean }>(`/v2/admin/menus/${menuId}/items/reorder`, { items }),
};

// ─────────────────────────────────────────────────────────────────────────────
// Plans & Subscriptions
// ─────────────────────────────────────────────────────────────────────────────

export const adminPlans = {
  list: () => api.get<Array<{ id: number; name: string; slug: string; tier_level: number; price_monthly: number; price_yearly: number; is_active: boolean }>>('/v2/admin/plans'),
  get: (id: number) => api.get<{ id: number; name: string; slug: string; description?: string; tier_level: number; price_monthly: number; price_yearly: number; max_menus?: number; max_menu_items?: number; features?: string[]; allowed_layouts?: string[]; is_active: boolean }>(`/v2/admin/plans/${id}`),
  create: (data: { name: string; description?: string; price_monthly?: number; price_yearly?: number; tier_level?: number; max_menus?: number; max_menu_items?: number; features?: string[]; allowed_layouts?: string[]; is_active?: boolean }) =>
    api.post<{ id: number }>('/v2/admin/plans', data),
  update: (id: number, data: Record<string, unknown>) =>
    api.put<{ success: boolean }>(`/v2/admin/plans/${id}`, data),
  delete: (id: number) => api.delete<{ success: boolean }>(`/v2/admin/plans/${id}`),
  getSubscriptions: () => api.get<Array<{ id: number; tenant_id: number; tenant_name: string; plan_id: number; plan_name: string; status: string; created_at: string }>>('/v2/admin/subscriptions'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Deliverability
// ─────────────────────────────────────────────────────────────────────────────

export const adminDeliverability = {
  // Consumer casts to local DashboardData type
  getDashboard: () => api.get('/v2/admin/deliverability/dashboard'),
  list: (params: { page?: number; status?: string; priority?: string } = {}) =>
    api.get<PaginatedResponse<{ id: number; title: string; description?: string; priority: string; status: string; due_date?: string; assigned_to?: number; created_at: string }>>(`/v2/admin/deliverability${buildQuery(params)}`),
  get: (id: number) => api.get<{ id: number; title: string; description?: string; priority: string; status: string; due_date?: string; assigned_to?: number; comments: Array<{ id: number; comment_text: string; comment_type?: string; created_at: string }>; created_at: string }>(`/v2/admin/deliverability/${id}`),
  create: (data: { title: string; description?: string; priority?: string; status?: string; due_date?: string; assigned_to?: number }) =>
    api.post<{ id: number }>('/v2/admin/deliverability', data),
  update: (id: number, data: Record<string, unknown>) =>
    api.put<{ success: boolean }>(`/v2/admin/deliverability/${id}`, data),
  delete: (id: number) => api.delete<{ success: boolean }>(`/v2/admin/deliverability/${id}`),
  // Consumer casts to local AnalyticsData type
  getAnalytics: () => api.get('/v2/admin/deliverability/analytics'),
  addComment: (id: number, data: { comment_text: string; comment_type?: string }) =>
    api.post<{ id: number }>(`/v2/admin/deliverability/${id}/comments`, data),
};

// ─────────────────────────────────────────────────────────────────────────────
// Diagnostics (Matching & Nexus Score)
// ─────────────────────────────────────────────────────────────────────────────

export const adminDiagnostics = {
  diagnoseUser: (userId: number) =>
    api.get<Record<string, unknown>>(`/v2/admin/matching/stats${buildQuery({ user_id: userId })}`),
  diagnoseListing: (listingId: number) =>
    api.get<Record<string, unknown>>(`/v2/admin/matching/stats${buildQuery({ listing_id: listingId })}`),
  getMatchingStats: () => api.get<Record<string, unknown>>('/v2/admin/matching/stats'),
  getNexusScoreStats: () => api.get<GamificationStats>('/v2/admin/gamification/stats'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Admin Settings
// ─────────────────────────────────────────────────────────────────────────────

export const adminSettings = {
  get: () => api.get<import('./types').AdminSettingsResponse>('/v2/admin/settings'),
  update: (data: Record<string, unknown>) => api.put<{ success: boolean }>('/v2/admin/settings', data),

  getAiConfig: () => api.get<Record<string, unknown>>('/v2/admin/config/ai'),
  updateAiConfig: (data: Record<string, unknown>) => api.put<{ success: boolean }>('/v2/admin/config/ai', data),

  getFeedAlgorithm: () => api.get<Record<string, unknown>>('/v2/admin/config/feed-algorithm'),
  updateFeedAlgorithm: (data: Record<string, unknown>) => api.put<{ success: boolean }>('/v2/admin/config/feed-algorithm', data),

  getAlgorithmConfig: () => api.get<Record<string, unknown>>('/v2/admin/config/algorithms'),
  updateAlgorithmConfig: (area: string, data: Record<string, unknown>) => api.put<{ success: boolean }>(`/v2/admin/config/algorithm/${area}`, data),
  getAlgorithmHealth: () => api.get<Record<string, unknown>>('/v2/admin/config/algorithm-health'),

  getImageSettings: () => api.get<Record<string, unknown>>('/v2/admin/config/images'),
  updateImageSettings: (data: Record<string, unknown>) => api.put<{ success: boolean }>('/v2/admin/config/images', data),

  getSeoSettings: () => api.get<Record<string, unknown>>('/v2/admin/config/seo'),
  updateSeoSettings: (data: Record<string, unknown>) => api.put<{ success: boolean }>('/v2/admin/config/seo', data),

  getNativeAppSettings: () => api.get<Record<string, unknown>>('/v2/admin/config/native-app'),
  updateNativeAppSettings: (data: Record<string, unknown>) => api.put<{ success: boolean }>('/v2/admin/config/native-app', data),

  getEmailConfig: () => api.get<Record<string, unknown>>('/v2/admin/email/config'),
  updateEmailConfig: (data: Record<string, unknown>) => api.put<{ success: boolean }>('/v2/admin/email/config', data),
  testEmailProvider: (data: { to: string }) => api.post<{ success: boolean; message: string }>('/v2/admin/email/test-provider', data),
};

// ─────────────────────────────────────────────────────────────────────────────
// Admin Tools (SEO, Health, WebP, Seeds, Backups)
// ─────────────────────────────────────────────────────────────────────────────

export const adminTools = {
  getRedirects: () => api.get<Array<{ id: number; from_url: string; to_url: string; status_code: number; hits: number; created_at: string }>>('/v2/admin/tools/redirects'),
  createRedirect: (data: { from_url: string; to_url: string; status_code?: number }) =>
    api.post<{ id: number }>('/v2/admin/tools/redirects', data),
  deleteRedirect: (id: number) => api.delete<{ success: boolean }>(`/v2/admin/tools/redirects/${id}`),

  get404Errors: (page = 1, perPage = 50) =>
    api.get<{ items: Array<{ id: number; url: string; referrer: string; hits: number; first_seen: string; last_seen: string }>; total: number; page: number; per_page: number }>(
      `/v2/admin/tools/404-errors?page=${page}&per_page=${perPage}`
    ),
  delete404Error: (id: number) => api.delete<{ success: boolean }>(`/v2/admin/tools/404-errors/${id}`),

  runHealthCheck: () => api.post<Array<{ name: string; status: string; duration_ms: number; error?: string }>>('/v2/admin/tools/health-check'),

  getWebpStats: () => api.get<{ total_images: number; webp_images: number; pending_conversion: number }>('/v2/admin/tools/webp-stats'),
  runWebpConversion: () => api.post<{ converted: number }>('/v2/admin/tools/webp-convert'),

  runSeedGenerator: (data: { types: string[]; counts: Record<string, number> }) =>
    api.post<{ success: boolean; created: Record<string, number> }>('/v2/admin/tools/seed', data),

  getBlogBackups: () => api.get<Array<{ id: number; filename: string; created_at: string; size: string }>>('/v2/admin/tools/blog-backups'),

  restoreBlogBackup: (backupId: number) =>
    api.post<{ restored_count: number }>(`/v2/admin/tools/blog-backups/${backupId}/restore`),

  runSeoAudit: () =>
    api.post<Array<{ name: string; description: string; status: 'pass' | 'warning' | 'fail'; details?: string }>>('/v2/admin/tools/seo-audit'),

  getSeoAudit: () =>
    api.get<{ checks: Array<{ name: string; description: string; status: 'pass' | 'warning' | 'fail'; details?: string }>; last_run_at: string | null }>('/v2/admin/tools/seo-audit'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Super Admin (Tenant CRUD, Cross-Tenant Users, Bulk, Audit, Federation Controls)
// ─────────────────────────────────────────────────────────────────────────────

export const adminSuper = {
  // Dashboard
  getDashboard: () =>
    api.get<SuperAdminDashboardStats>('/v2/admin/super/dashboard'),

  // Tenants
  listTenants: (params: { search?: string; is_active?: boolean; hub?: boolean } = {}) =>
    api.get<SuperAdminTenant[]>(`/v2/admin/super/tenants${buildQuery(params)}`),

  getTenant: (id: number) =>
    api.get<SuperAdminTenantDetail>(`/v2/admin/super/tenants/${id}`),

  getHierarchy: () =>
    api.get<TenantHierarchyNode[]>('/v2/admin/super/tenants/hierarchy'),

  createTenant: (data: CreateTenantPayload) =>
    api.post<{ tenant_id: number }>('/v2/admin/super/tenants', data),

  updateTenant: (id: number, data: UpdateTenantPayload) =>
    api.put<{ success: boolean }>(`/v2/admin/super/tenants/${id}`, data),

  deleteTenant: (id: number, hardDelete = false) =>
    api.delete<{ success: boolean }>(`/v2/admin/super/tenants/${id}${hardDelete ? '?hard=1' : ''}`),

  reactivateTenant: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/super/tenants/${id}/reactivate`),

  toggleHub: (id: number, enable: boolean) =>
    api.post<{ success: boolean }>(`/v2/admin/super/tenants/${id}/toggle-hub`, { enable }),

  moveTenant: (id: number, newParentId: number) =>
    api.post<{ success: boolean }>(`/v2/admin/super/tenants/${id}/move`, { new_parent_id: newParentId }),

  // Users (Cross-Tenant)
  listUsers: (params: SuperUserListParams = {}) =>
    api.get<SuperAdminUser[]>(`/v2/admin/super/users${buildQuery(params)}`),

  getUser: (id: number) =>
    api.get<SuperAdminUserDetail>(`/v2/admin/super/users/${id}`),

  createUser: (data: CreateSuperUserPayload) =>
    api.post<{ user_id: number }>('/v2/admin/super/users', data),

  updateUser: (id: number, data: Record<string, unknown>) =>
    api.put<{ success: boolean }>(`/v2/admin/super/users/${id}`, data),

  grantSuperAdmin: (userId: number) =>
    api.post<{ success: boolean }>(`/v2/admin/super/users/${userId}/grant-super-admin`),

  revokeSuperAdmin: (userId: number) =>
    api.post<{ success: boolean }>(`/v2/admin/super/users/${userId}/revoke-super-admin`),

  grantGlobalSuperAdmin: (userId: number) =>
    api.post<{ success: boolean }>(`/v2/admin/super/users/${userId}/grant-global-super-admin`),

  revokeGlobalSuperAdmin: (userId: number) =>
    api.post<{ success: boolean }>(`/v2/admin/super/users/${userId}/revoke-global-super-admin`),

  moveUserTenant: (userId: number, newTenantId: number) =>
    api.post<{ success: boolean }>(`/v2/admin/super/users/${userId}/move-tenant`, { new_tenant_id: newTenantId }),

  moveAndPromote: (userId: number, targetTenantId: number) =>
    api.post<{ success: boolean }>(`/v2/admin/super/users/${userId}/move-and-promote`, { target_tenant_id: targetTenantId }),

  // Bulk Operations
  bulkMoveUsers: (data: BulkMoveUsersPayload) =>
    api.post<BulkOperationResult>('/v2/admin/super/bulk/move-users', data),

  bulkUpdateTenants: (data: BulkUpdateTenantsPayload) =>
    api.post<BulkOperationResult>('/v2/admin/super/bulk/update-tenants', data),

  // Audit
  getAudit: (params: SuperAuditParams = {}) =>
    api.get<SuperAuditEntry[]>(`/v2/admin/super/audit${buildQuery(params)}`),

  // Federation Controls
  getFederationStatus: () =>
    api.get<FederationStatusOverview>('/v2/admin/super/federation'),

  getSystemControls: () =>
    api.get<FederationSystemControls>('/v2/admin/super/federation/system-controls'),

  updateSystemControls: (data: Partial<FederationSystemControls>) =>
    api.put<{ success: boolean }>('/v2/admin/super/federation/system-controls', data),

  emergencyLockdown: (reason: string) =>
    api.post<{ success: boolean }>('/v2/admin/super/federation/emergency-lockdown', { reason }),

  liftLockdown: () =>
    api.post<{ success: boolean }>('/v2/admin/super/federation/lift-lockdown'),

  getWhitelist: () =>
    api.get<FederationWhitelistEntry[]>('/v2/admin/super/federation/whitelist'),

  addToWhitelist: (tenantId: number, notes?: string) =>
    api.post<{ success: boolean }>('/v2/admin/super/federation/whitelist', { tenant_id: tenantId, notes }),

  removeFromWhitelist: (tenantId: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/super/federation/whitelist/${tenantId}`),

  getFederationPartnerships: () =>
    api.get<FederationPartnership[]>('/v2/admin/super/federation/partnerships'),

  suspendPartnership: (id: number, reason: string) =>
    api.post<{ success: boolean }>(`/v2/admin/super/federation/partnerships/${id}/suspend`, { reason }),

  terminatePartnership: (id: number, reason: string) =>
    api.post<{ success: boolean }>(`/v2/admin/super/federation/partnerships/${id}/terminate`, { reason }),

  getTenantFederationFeatures: (tenantId: number) =>
    api.get<TenantFederationFeatures>(`/v2/admin/super/federation/tenant/${tenantId}/features`),

  updateTenantFederationFeature: (tenantId: number, feature: string, enabled: boolean) =>
    api.put<{ success: boolean }>(`/v2/admin/super/federation/tenant/${tenantId}/features`, { feature, enabled }),
};

// ─────────────────────────────────────────────────────────────────────────────
// Community Analytics
// ─────────────────────────────────────────────────────────────────────────────

export const adminCommunityAnalytics = {
  getData: () => api.get<{ members: Record<string, unknown>; activity: Record<string, unknown>; engagement: Record<string, unknown> }>('/v2/admin/community-analytics'),
  exportCsv: () => api.get<{ data: unknown }>('/v2/admin/community-analytics/export'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Impact Reporting
// ─────────────────────────────────────────────────────────────────────────────

export const adminImpactReport = {
  getData: (months = 12) => api.get<{ total_hours: number; economic_value: number; social_impact: number; members_active: number; monthly_data: Array<{ month: string; hours: number; value: number }> }>(`/v2/admin/impact-report${buildQuery({ months })}`),
  updateConfig: (data: { hourly_value?: number; social_multiplier?: number }) =>
    api.put<{ success: boolean }>('/v2/admin/impact-report/config', data),
};

// ─────────────────────────────────────────────────────────────────────────────
// Vetting Records
// ─────────────────────────────────────────────────────────────────────────────

export const adminVetting = {
  list: (params: { status?: string; vetting_type?: string; search?: string; page?: number; per_page?: number; expiring_soon?: boolean } = {}) =>
    api.get<PaginatedResponse<VettingRecord>>(
      `/v2/admin/vetting${buildQuery(params)}`
    ),

  stats: () =>
    api.get<VettingStats>('/v2/admin/vetting/stats'),

  show: (id: number) =>
    api.get<VettingRecord>(`/v2/admin/vetting/${id}`),

  create: (data: Partial<VettingRecord>) =>
    api.post<{ id: number }>('/v2/admin/vetting', data),

  update: (id: number, data: Partial<VettingRecord>) =>
    api.put<{ success: boolean }>(`/v2/admin/vetting/${id}`, data),

  verify: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/vetting/${id}/verify`),

  reject: (id: number, reason: string) =>
    api.post<{ success: boolean }>(`/v2/admin/vetting/${id}/reject`, { reason }),

  destroy: (id: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/vetting/${id}`),

  getUserRecords: (userId: number) =>
    api.get<VettingRecord[]>(`/v2/admin/vetting/user/${userId}`),

  uploadDocument: (id: number, file: File) =>
    api.upload<VettingRecord>(`/v2/admin/vetting/${id}/upload`, file),

  bulk: (ids: number[], action: 'verify' | 'reject' | 'delete', reason?: string) =>
    api.post<{ action: string; processed: number; failed: number; total: number }>(
      '/v2/admin/vetting/bulk',
      { ids, action, reason }
    ),
};

// ─────────────────────────────────────────────────────────────────────────────
// Insurance Certificates
// ─────────────────────────────────────────────────────────────────────────────

export const adminInsurance = {
  list: (params: { status?: string; insurance_type?: string; search?: string; page?: number; expiring_soon?: boolean } = {}) =>
    api.get<PaginatedResponse<InsuranceCertificate>>(
      `/v2/admin/insurance${buildQuery(params)}`
    ),

  stats: () =>
    api.get<InsuranceStats>('/v2/admin/insurance/stats'),

  show: (id: number) =>
    api.get<InsuranceCertificate>(`/v2/admin/insurance/${id}`),

  create: (data: Partial<InsuranceCertificate>) =>
    api.post<{ id: number }>('/v2/admin/insurance', data),

  update: (id: number, data: Partial<InsuranceCertificate>) =>
    api.put<{ success: boolean }>(`/v2/admin/insurance/${id}`, data),

  verify: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/insurance/${id}/verify`),

  reject: (id: number, reason: string) =>
    api.post<{ success: boolean }>(`/v2/admin/insurance/${id}/reject`, { reason }),

  destroy: (id: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/insurance/${id}`),

  getUserCertificates: (userId: number) =>
    api.get<InsuranceCertificate[]>(`/v2/admin/insurance/user/${userId}`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Cron Job Monitoring
// ─────────────────────────────────────────────────────────────────────────────

export const adminCron = {
  // Logs
  getLogs: (params?: { jobId?: string; limit?: number; offset?: number; status?: string; startDate?: string; endDate?: string }) =>
    api.get<PaginatedResponse<CronLog>>(`/v2/admin/system/cron-jobs/logs${buildQuery(params || {})}`),

  getLogDetail: (logId: number) =>
    api.get<CronLog>(`/v2/admin/system/cron-jobs/logs/${logId}`),

  clearLogs: (beforeDate: string) =>
    api.delete<{ success: boolean }>(`/v2/admin/system/cron-jobs/logs?before=${beforeDate}`),

  // Settings
  getJobSettings: (jobId: string) =>
    api.get<CronJobSettings>(`/v2/admin/system/cron-jobs/${jobId}/settings`),

  updateJobSettings: (jobId: string, data: Partial<CronJobSettings>) =>
    api.put<{ success: boolean }>(`/v2/admin/system/cron-jobs/${jobId}/settings`, data),

  getGlobalSettings: () =>
    api.get<GlobalCronSettings>('/v2/admin/system/cron-jobs/settings'),

  updateGlobalSettings: (data: Partial<GlobalCronSettings>) =>
    api.put<{ success: boolean }>('/v2/admin/system/cron-jobs/settings', data),

  // Health
  getHealthMetrics: () =>
    api.get<CronHealthMetrics>('/v2/admin/system/cron-jobs/health'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Content Moderation
// ─────────────────────────────────────────────────────────────────────────────

export const adminModeration = {
  // Feed Posts
  getFeedPosts: (params?: { type?: string; status?: string; user_id?: number; tenant_id?: number; page?: number; limit?: number }) =>
    api.get<PaginatedResponse<AdminFeedPost>>(`/v2/admin/feed/posts${buildQuery(params || {})}`),

  getFeedPost: (id: number) =>
    api.get<AdminFeedPost>(`/v2/admin/feed/posts/${id}`),

  hideFeedPost: (id: number, type = 'post') =>
    api.post<{ success: boolean }>(`/v2/admin/feed/posts/${id}/hide`, { type }),

  deleteFeedPost: (id: number, type = 'post') =>
    api.delete<{ success: boolean }>(`/v2/admin/feed/posts/${id}?type=${encodeURIComponent(type)}`),

  getFeedStats: () =>
    api.get<ModerationStats>('/v2/admin/feed/stats'),

  // Comments
  getComments: (params?: { content_type?: string; is_flagged?: boolean; tenant_id?: number; page?: number; limit?: number }) =>
    api.get<PaginatedResponse<AdminComment>>(`/v2/admin/comments${buildQuery(params || {})}`),

  getComment: (id: number) =>
    api.get<AdminComment>(`/v2/admin/comments/${id}`),

  hideComment: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/comments/${id}/hide`),

  deleteComment: (id: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/comments/${id}`),

  // Reviews
  getReviews: (params?: { rating?: number; is_flagged?: boolean; tenant_id?: number; page?: number; limit?: number }) =>
    api.get<PaginatedResponse<AdminReview>>(`/v2/admin/reviews${buildQuery(params || {})}`),

  getReview: (id: number) =>
    api.get<AdminReview>(`/v2/admin/reviews/${id}`),

  flagReview: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/reviews/${id}/flag`),

  hideReview: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/reviews/${id}/hide`),

  deleteReview: (id: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/reviews/${id}`),

  // Reports
  getReports: (params?: { type?: string; status?: string; tenant_id?: number; page?: number; limit?: number }) =>
    api.get<PaginatedResponse<AdminReport>>(`/v2/admin/reports${buildQuery(params || {})}`),

  getReport: (id: number) =>
    api.get<AdminReport>(`/v2/admin/reports/${id}`),

  resolveReport: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/reports/${id}/resolve`),

  dismissReport: (id: number) =>
    api.post<{ success: boolean }>(`/v2/admin/reports/${id}/dismiss`),

  getReportStats: () =>
    api.get<ModerationStats>('/v2/admin/reports/stats'),
};

// ─────────────────────────────────────────────────────────────────────────────
// CRM (Member Notes, Coordinator Tasks, Tags, Funnel)
// ─────────────────────────────────────────────────────────────────────────────

export const adminCrm = {
  // Dashboard
  getDashboard: () =>
    api.get<CrmDashboardStats>('/v2/admin/crm/dashboard'),

  // Onboarding Funnel
  getFunnel: () =>
    api.get<CrmFunnelData>('/v2/admin/crm/funnel'),

  // Admin list (for task assignment)
  getAdmins: () =>
    api.get<CrmAdmin[]>('/v2/admin/crm/admins'),

  // Member Notes
  getNotes: (params?: { user_id?: number; page?: number; limit?: number; category?: string; search?: string }) =>
    api.get<PaginatedResponse<MemberNote>>(`/v2/admin/crm/notes${buildQuery(params || {})}`),

  createNote: (payload: { user_id: number; content: string; category?: string; is_pinned?: boolean }) =>
    api.post<MemberNote>('/v2/admin/crm/notes', payload),

  updateNote: (id: number, payload: { content?: string; category?: string; is_pinned?: boolean }) =>
    api.put<MemberNote>(`/v2/admin/crm/notes/${id}`, payload),

  deleteNote: (id: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/crm/notes/${id}`),

  // Coordinator Tasks
  getTasks: (params?: { page?: number; limit?: number; status?: string; priority?: string; assigned_to?: number; search?: string }) =>
    api.get<PaginatedResponse<CoordinatorTask>>(`/v2/admin/crm/tasks${buildQuery(params || {})}`),

  createTask: (payload: { title: string; description?: string; priority?: string; assigned_to?: number; user_id?: number; due_date?: string }) =>
    api.post<CoordinatorTask>('/v2/admin/crm/tasks', payload),

  updateTask: (id: number, payload: { title?: string; description?: string; priority?: string; status?: string; assigned_to?: number; user_id?: number; due_date?: string | null }) =>
    api.put<CoordinatorTask>(`/v2/admin/crm/tasks/${id}`, payload),

  deleteTask: (id: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/crm/tasks/${id}`),

  // Member Tags
  getTags: (params?: { user_id?: number; tag?: string }) =>
    api.get<MemberTag[] | TagSummary[]>(`/v2/admin/crm/tags${buildQuery(params || {})}`),

  addTag: (payload: { user_id: number; tag: string }) =>
    api.post<MemberTag>('/v2/admin/crm/tags', payload),

  removeTag: (id: number) =>
    api.delete<{ success: boolean }>(`/v2/admin/crm/tags/${id}`),

  bulkRemoveTag: (tag: string) =>
    api.delete<{ success: boolean; deleted: number }>(`/v2/admin/crm/tags/bulk?tag=${encodeURIComponent(tag)}`),

  // Activity Timeline
  getTimeline: (params?: { user_id?: number; type?: string; days?: number; page?: number; limit?: number }) =>
    api.get<PaginatedResponse<TimelineEntry>>(`/v2/admin/crm/timeline${buildQuery(params || {})}`),

  // CSV Exports (return blob URLs)
  exportNotesUrl: () => '/v2/admin/crm/export/notes',
  exportTasksUrl: () => '/v2/admin/crm/export/tasks',
  exportDashboardUrl: () => '/v2/admin/crm/export/dashboard',
};

// ─────────────────────────────────────────────────────────────────────────────
// Knowledge Base
// ─────────────────────────────────────────────────────────────────────────────

export const adminKb = {
  get: (id: number) =>
    api.get(`/v2/admin/resources/${id}`),

  create: (data: {
    title: string;
    slug?: string;
    content?: string;
    content_type?: 'html' | 'plain' | 'markdown';
    category_id?: number | null;
    parent_article_id?: number | null;
    sort_order?: number;
    is_published?: boolean;
  }) =>
    api.post('/v2/kb', data),

  update: (id: number, data: {
    title?: string;
    slug?: string;
    content?: string;
    content_type?: 'html' | 'plain' | 'markdown';
    category_id?: number | null;
    parent_article_id?: number | null;
    sort_order?: number;
    is_published?: boolean;
  }) =>
    api.put(`/v2/kb/${id}`, data),

  delete: (id: number) =>
    api.delete(`/v2/kb/${id}`),

  uploadAttachment: (articleId: number, file: File) =>
    api.upload(`/v2/kb/${articleId}/attachments`, file),

  deleteAttachment: (articleId: number, attachmentId: number) =>
    api.delete(`/v2/kb/${articleId}/attachments/${attachmentId}`),
};

// ─────────────────────────────────────────────────────────────────────────────
// Re-export for convenience
// ─────────────────────────────────────────────────────────────────────────────

export type { ApiResponse };
