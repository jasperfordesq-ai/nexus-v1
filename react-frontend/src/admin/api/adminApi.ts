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
  ExchangeDetail,
  RiskTag,
  BrokerMessage,
  MonitoredUser,
  BrokerConfig,
  AdminGroup,
  GroupApproval,
  GroupAnalyticsData,
  GroupModerationItem,
  GroupType,
  // @ts-ignore - Type imports used for type annotations
  GroupPolicy,
  // @ts-ignore - Type imports used for type annotations
  GroupMember,
  // @ts-ignore - Type imports used for type annotations
  GroupRecommendation,
  // @ts-ignore - Type imports used for type annotations
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
  CronLog,
  CronJobSettings,
  GlobalCronSettings,
  CronHealthMetrics,
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
  setSuperAdmin: (userId: number, grant: boolean) =>
    api.put(`/v2/admin/users/${userId}/super-admin`, { grant }),

  recheckUserBadges: (userId: number) =>
    api.post<{ rechecked: boolean; user_id: number; badges: import('./types').AdminBadge[] }>(`/v2/admin/users/${userId}/badges/recheck`),

  getConsents: (userId: number) =>
    api.get<import('./types').UserConsent[]>(`/v2/admin/users/${userId}/consents`),

  setPassword: (userId: number, password: string) =>
    api.post(`/v2/admin/users/${userId}/password`, { password }),

  sendPasswordReset: (userId: number) =>
    api.post(`/v2/admin/users/${userId}/send-password-reset`),

  sendWelcomeEmail: (userId: number) =>
    api.post(`/v2/admin/users/${userId}/send-welcome-email`),

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

  create: (data: { name: string; type?: string; category_id?: number | null }) =>
    api.post<AdminAttribute>('/v2/admin/attributes', data),

  update: (id: number, data: { name?: string; type?: string; category_id?: number | null; is_active?: boolean }) =>
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

  createBadge: (data: { name: string; slug?: string; description: string; icon?: string; category?: string; xp?: number; is_active?: boolean }) =>
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
    const { tokenManager } = await import('@/lib/api');
    const apiBase = import.meta.env.VITE_API_BASE || '/api';
    const params = buildQuery({
      user_id: userId,
      format: 'csv',
      start_date: startDate,
      end_date: endDate,
    });
    const headers: Record<string, string> = { Accept: 'text/csv' };
    const token = tokenManager.getAccessToken();
    if (token) headers['Authorization'] = `Bearer ${token}`;
    const tenantId = tokenManager.getTenantId();
    if (tenantId) headers['X-Tenant-ID'] = tenantId;

    const response = await fetch(`${apiBase}/v2/admin/timebanking/user-statement${params}`, {
      headers,
      credentials: 'include',
    });
    if (!response.ok) throw new Error('Failed to download statement');
    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `statement_${userId}_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
  },
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

  flagMessage: (id: number, reason: string, severity: 'concern' | 'serious' | 'urgent') =>
    api.post(`/v2/admin/broker/messages/${id}/flag`, { reason, severity }),

  setMonitoring: (userId: number, data: { under_monitoring: boolean; reason?: string; messaging_disabled?: boolean }) =>
    api.post(`/v2/admin/broker/monitoring/${userId}`, data),

  saveRiskTag: (listingId: number, data: {
    risk_level: 'low' | 'medium' | 'high' | 'critical';
    risk_category: string;
    risk_notes?: string;
    member_visible_notes?: string;
    requires_approval?: boolean;
    insurance_required?: boolean;
    dbs_required?: boolean;
  }) => api.post(`/v2/admin/broker/risk-tags/${listingId}`, data),

  removeRiskTag: (listingId: number) =>
    api.delete(`/v2/admin/broker/risk-tags/${listingId}`),

  getConfiguration: () =>
    api.get<BrokerConfig>('/v2/admin/broker/configuration'),

  saveConfiguration: (config: Partial<BrokerConfig>) =>
    api.post<BrokerConfig>('/v2/admin/broker/configuration', config),

  showExchange: (id: number) =>
    api.get<ExchangeDetail>(`/v2/admin/broker/exchanges/${id}`),
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

  updateStatus: (id: number, status: 'active' | 'inactive') =>
    api.put(`/v2/admin/groups/${id}/status`, { status }),

  delete: (id: number) =>
    api.delete(`/v2/admin/groups/${id}`),

  // Group types
  getGroupTypes: () =>
    api.get('/v2/admin/groups/types'),

  createGroupType: (data: Partial<GroupType>) =>
    api.post('/v2/admin/groups/types', data),

  updateGroupType: (id: number, data: Partial<GroupType>) =>
    api.put(`/v2/admin/groups/types/${id}`, data),

  deleteGroupType: (id: number) =>
    api.delete(`/v2/admin/groups/types/${id}`),

  // Policies
  getPolicies: (typeId: number) =>
    api.get(`/v2/admin/groups/types/${typeId}/policies`),

  setPolicy: (typeId: number, key: string, value: unknown) =>
    api.put(`/v2/admin/groups/types/${typeId}/policies`, { key, value }),

  // Group detail
  getGroup: (id: number) =>
    api.get(`/v2/admin/groups/${id}`),

  updateGroup: (id: number, data: unknown) =>
    api.put(`/v2/admin/groups/${id}`, data),

  getMembers: (groupId: number, params?: { role?: string; limit?: number; offset?: number }) => {
    const query = new URLSearchParams();
    if (params?.role) query.append('role', params.role);
    if (params?.limit) query.append('limit', params.limit.toString());
    if (params?.offset) query.append('offset', params.offset.toString());
    const qs = query.toString();
    return api.get(`/v2/admin/groups/${groupId}/members${qs ? `?${qs}` : ''}`);
  },

  promoteMember: (groupId: number, userId: number) =>
    api.post(`/v2/admin/groups/${groupId}/members/${userId}/promote`, {}),

  demoteMember: (groupId: number, userId: number) =>
    api.post(`/v2/admin/groups/${groupId}/members/${userId}/demote`, {}),

  kickMember: (groupId: number, userId: number) =>
    api.delete(`/v2/admin/groups/${groupId}/members/${userId}`),

  // Geocoding
  geocodeGroup: (groupId: number) =>
    api.post(`/v2/admin/groups/${groupId}/geocode`, {}),

  batchGeocode: () =>
    api.post('/v2/admin/groups/batch-geocode', {}),

  // Recommendations
  getRecommendationData: (params?: { limit?: number; offset?: number }) => {
    const query = new URLSearchParams();
    if (params?.limit) query.append('limit', params.limit.toString());
    if (params?.offset) query.append('offset', params.offset.toString());
    const qs = query.toString();
    return api.get(`/v2/admin/groups/recommendations${qs ? `?${qs}` : ''}`);
  },

  // Ranking
  getFeaturedGroups: () =>
    api.get('/v2/admin/groups/featured'),

  updateFeaturedGroups: () =>
    api.post('/v2/admin/groups/featured/update', {}),

  toggleFeatured: (groupId: number) =>
    api.put(`/v2/admin/groups/${groupId}/toggle-featured`, {}),
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

  createBreach: (data: { title: string; description?: string; severity?: string; affected_users?: number }) =>
    api.post('/v2/admin/enterprise/gdpr/breaches', data),

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

  // Version Management
  getVersions: (docId: number) =>
    api.get<LegalDocumentVersion[]>(`/v2/admin/legal-documents/${docId}/versions`),

  compareVersions: (docId: number, v1: number, v2: number) =>
    api.get<VersionComparison>(`/v2/admin/legal-documents/${docId}/versions/compare?v1=${v1}&v2=${v2}`),

  createVersion: (docId: number, data: { version_number: string; version_label?: string; content: string; summary_of_changes?: string; effective_date: string; is_draft?: boolean }) =>
    api.post<{ id: number }>(`/v2/admin/legal-documents/${docId}/versions`, data),

  publishVersion: (versionId: number) =>
    api.post<{ published: boolean }>(`/v2/admin/legal-documents/versions/${versionId}/publish`, {}),

  // Compliance & Acceptance Tracking
  getComplianceStats: (docId?: number) =>
    api.get<ComplianceStats>(`/v2/admin/legal-documents/compliance${docId ? `?doc_id=${docId}` : ''}`),

  getAcceptances: (versionId: number, limit = 50, offset = 0) =>
    api.get<UserAcceptance[]>(`/v2/admin/legal-documents/versions/${versionId}/acceptances?limit=${limit}&offset=${offset}`),

  exportAcceptances: (docId: number, startDate?: string, endDate?: string) => {
    const query = buildQuery({ start_date: startDate, end_date: endDate });
    return api.get(`/v2/admin/legal-documents/${docId}/acceptances/export${query}`);
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

  // Bounce management
  getBounces: (params: { limit?: number; offset?: number; type?: string; startDate?: string; endDate?: string } = {}) =>
    api.get(`/v2/admin/newsletters/bounces${buildQuery(params)}`),

  getSuppressionList: () => api.get('/v2/admin/newsletters/suppression-list'),

  unsuppress: (email: string) =>
    api.post(`/v2/admin/newsletters/suppression-list/${encodeURIComponent(email)}/unsuppress`, {}),

  suppress: (email: string) =>
    api.post(`/v2/admin/newsletters/suppression-list/${encodeURIComponent(email)}/suppress`, {}),

  // Resend workflow
  getResendInfo: (newsletterId: number) =>
    api.get(`/v2/admin/newsletters/${newsletterId}/resend-info`),

  resend: (newsletterId: number, options: { target: string; segment_id?: number; subject_override?: string }) =>
    api.post(`/v2/admin/newsletters/${newsletterId}/resend`, options),

  // Send-time optimizer
  getSendTimeData: (params?: { days?: number }) =>
    api.get(`/v2/admin/newsletters/send-time-optimizer${params ? buildQuery(params) : ''}`),

  // Diagnostics
  getDiagnostics: () => api.get('/v2/admin/newsletters/diagnostics'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Volunteering
// ─────────────────────────────────────────────────────────────────────────────

export const adminVolunteering = {
  getOverview: () => api.get('/v2/admin/volunteering'),

  getApprovals: () => api.get('/v2/admin/volunteering/approvals'),

  approveApplication: (id: number) =>
    api.post('/v2/admin/volunteering/approvals/' + id + '/approve', {}),

  declineApplication: (id: number) =>
    api.post('/v2/admin/volunteering/approvals/' + id + '/decline', {}),

  getOrganizations: () => api.get('/v2/admin/volunteering/organizations'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Federation
// ─────────────────────────────────────────────────────────────────────────────

export const adminFederation = {
  getSettings: () => api.get('/v2/admin/federation/settings'),

  updateSettings: (data: Record<string, unknown>) =>
    api.put('/v2/admin/federation/settings', data),

  getPartnerships: () => api.get('/v2/admin/federation/partnerships'),

  approvePartnership: (id: number) =>
    api.post('/v2/admin/federation/partnerships/' + id + '/approve', {}),

  rejectPartnership: (id: number) =>
    api.post('/v2/admin/federation/partnerships/' + id + '/reject', {}),

  terminatePartnership: (id: number) =>
    api.post('/v2/admin/federation/partnerships/' + id + '/terminate', {}),

  getDirectory: () => api.get('/v2/admin/federation/directory'),

  getProfile: () => api.get('/v2/admin/federation/directory/profile'),

  updateProfile: (data: Record<string, unknown>) =>
    api.put('/v2/admin/federation/directory/profile', data),

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
  create: (data: { name: string; description?: string; price_monthly?: number; price_yearly?: number; tier_level?: number; max_menus?: number; max_menu_items?: number; features?: string[]; allowed_layouts?: string[]; is_active?: boolean }) =>
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
  create: (data: { title: string; description?: string; priority?: string; status?: string; due_date?: string; assigned_to?: number }) =>
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
  get: () => api.get<import('./types').AdminSettingsResponse>('/v2/admin/settings'),
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
    api.put('/v2/admin/super/tenants/' + id, data),

  deleteTenant: (id: number, hardDelete = false) =>
    api.delete(`/v2/admin/super/tenants/${id}${hardDelete ? '?hard=1' : ''}`),

  reactivateTenant: (id: number) =>
    api.post(`/v2/admin/super/tenants/${id}/reactivate`),

  toggleHub: (id: number, enable: boolean) =>
    api.post(`/v2/admin/super/tenants/${id}/toggle-hub`, { enable }),

  moveTenant: (id: number, newParentId: number) =>
    api.post(`/v2/admin/super/tenants/${id}/move`, { new_parent_id: newParentId }),

  // Users (Cross-Tenant)
  listUsers: (params: SuperUserListParams = {}) =>
    api.get<SuperAdminUser[]>(`/v2/admin/super/users${buildQuery(params)}`),

  getUser: (id: number) =>
    api.get<SuperAdminUserDetail>(`/v2/admin/super/users/${id}`),

  createUser: (data: CreateSuperUserPayload) =>
    api.post<{ user_id: number }>('/v2/admin/super/users', data),

  updateUser: (id: number, data: Record<string, unknown>) =>
    api.put(`/v2/admin/super/users/${id}`, data),

  grantSuperAdmin: (userId: number) =>
    api.post(`/v2/admin/super/users/${userId}/grant-super-admin`),

  revokeSuperAdmin: (userId: number) =>
    api.post(`/v2/admin/super/users/${userId}/revoke-super-admin`),

  grantGlobalSuperAdmin: (userId: number) =>
    api.post(`/v2/admin/super/users/${userId}/grant-global-super-admin`),

  revokeGlobalSuperAdmin: (userId: number) =>
    api.post(`/v2/admin/super/users/${userId}/revoke-global-super-admin`),

  moveUserTenant: (userId: number, newTenantId: number) =>
    api.post(`/v2/admin/super/users/${userId}/move-tenant`, { new_tenant_id: newTenantId }),

  moveAndPromote: (userId: number, targetTenantId: number) =>
    api.post(`/v2/admin/super/users/${userId}/move-and-promote`, { target_tenant_id: targetTenantId }),

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
    api.put('/v2/admin/super/federation/system-controls', data),

  emergencyLockdown: (reason: string) =>
    api.post('/v2/admin/super/federation/emergency-lockdown', { reason }),

  liftLockdown: () =>
    api.post('/v2/admin/super/federation/lift-lockdown'),

  getWhitelist: () =>
    api.get<FederationWhitelistEntry[]>('/v2/admin/super/federation/whitelist'),

  addToWhitelist: (tenantId: number, notes?: string) =>
    api.post('/v2/admin/super/federation/whitelist', { tenant_id: tenantId, notes }),

  removeFromWhitelist: (tenantId: number) =>
    api.delete(`/v2/admin/super/federation/whitelist/${tenantId}`),

  getFederationPartnerships: () =>
    api.get<FederationPartnership[]>('/v2/admin/super/federation/partnerships'),

  suspendPartnership: (id: number, reason: string) =>
    api.post(`/v2/admin/super/federation/partnerships/${id}/suspend`, { reason }),

  terminatePartnership: (id: number, reason: string) =>
    api.post(`/v2/admin/super/federation/partnerships/${id}/terminate`, { reason }),

  getTenantFederationFeatures: (tenantId: number) =>
    api.get<TenantFederationFeatures>(`/v2/admin/super/federation/tenant/${tenantId}/features`),

  updateTenantFederationFeature: (tenantId: number, feature: string, enabled: boolean) =>
    api.put(`/v2/admin/super/federation/tenant/${tenantId}/features`, { feature, enabled }),
};

// ─────────────────────────────────────────────────────────────────────────────
// Community Analytics
// ─────────────────────────────────────────────────────────────────────────────

export const adminCommunityAnalytics = {
  getData: () => api.get('/v2/admin/community-analytics'),
  exportCsv: () => api.get('/v2/admin/community-analytics/export'),
};

// ─────────────────────────────────────────────────────────────────────────────
// Impact Reporting
// ─────────────────────────────────────────────────────────────────────────────

export const adminImpactReport = {
  getData: (months = 12) => api.get(`/v2/admin/impact-report?months=${months}`),
  updateConfig: (data: { hourly_value?: number; social_multiplier?: number }) =>
    api.put('/v2/admin/impact-report/config', data),
};

// ─────────────────────────────────────────────────────────────────────────────
// Vetting Records
// ─────────────────────────────────────────────────────────────────────────────

export const adminVetting = {
  list: (params: { status?: string; vetting_type?: string; search?: string; page?: number; expiring_soon?: boolean } = {}) =>
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
// Re-export for convenience
// ─────────────────────────────────────────────────────────────────────────────

export type { ApiResponse };
