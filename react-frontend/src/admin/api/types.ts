/**
 * Admin Panel TypeScript Types
 * Types for admin API requests and responses
 */

// ─────────────────────────────────────────────────────────────────────────────
// Dashboard
// ─────────────────────────────────────────────────────────────────────────────

export interface AdminDashboardStats {
  total_users: number;
  active_users: number;
  pending_users: number;
  total_listings: number;
  active_listings: number;
  pending_listings?: number;
  total_transactions: number;
  total_hours_exchanged: number;
  new_users_this_month: number;
  new_listings_this_month: number;
}

export interface MonthlyTrend {
  month: string;
  users: number;
  listings: number;
  transactions: number;
  hours: number;
}

export interface ActivityLogEntry {
  id: number;
  user_id: number;
  user_name: string;
  user_email?: string;
  user_avatar?: string | null;
  action: string;
  description: string;
  ip_address?: string;
  created_at: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// User Management
// ─────────────────────────────────────────────────────────────────────────────

export interface AdminUser {
  id: number;
  name: string;
  first_name: string;
  last_name: string;
  email: string;
  username?: string;
  avatar?: string | null;
  avatar_url?: string | null;
  role: 'member' | 'admin' | 'moderator' | 'tenant_admin' | 'super_admin';
  status: 'active' | 'inactive' | 'suspended' | 'pending' | 'banned';
  tenant_id?: number;
  balance: number;
  total_earned?: number;
  total_spent?: number;
  has_2fa_enabled: boolean;
  is_super_admin: boolean;
  is_admin?: boolean;
  listing_count?: number;
  profile_type?: string;
  level?: number;
  badges_count?: number;
  created_at: string;
  last_active_at?: string | null;
}

export interface AdminUserDetail extends AdminUser {
  bio?: string;
  tagline?: string;
  location?: string;
  phone?: string;
  organization_name?: string;
  badges: AdminBadge[];
  permissions?: string[];
}

export interface AdminBadge {
  id: number;
  name: string;
  slug: string;
  description: string;
  icon?: string;
  awarded_at: string;
}

export interface UserListParams {
  page?: number;
  limit?: number;
  status?: 'all' | 'active' | 'pending' | 'suspended' | 'banned';
  role?: string;
  search?: string;
  sort?: string;
  order?: 'asc' | 'desc';
}

export interface CreateUserPayload {
  first_name: string;
  last_name: string;
  email: string;
  role: string;
  password?: string;
  send_welcome_email?: boolean;
}

export interface UpdateUserPayload {
  first_name?: string;
  last_name?: string;
  email?: string;
  role?: string;
  status?: string;
  bio?: string;
  tagline?: string;
  location?: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Config & Features
// ─────────────────────────────────────────────────────────────────────────────

export interface TenantConfig {
  tenant_id: number;
  features: Record<string, boolean>;
  modules: Record<string, boolean>;
}

export interface CacheStats {
  redis_connected: boolean;
  redis_memory_used: string;
  redis_keys_count: number;
  cache_hit_rate: number;
}

export interface BackgroundJob {
  id: string;
  name: string;
  status: 'idle' | 'running' | 'failed';
  last_run_at: string | null;
  next_run_at: string | null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Listings Administration
// ─────────────────────────────────────────────────────────────────────────────

export interface AdminListing {
  id: number;
  title: string;
  description?: string;
  type: string;
  status: 'active' | 'pending' | 'inactive' | 'archived';
  user_id: number;
  user_name: string;
  user_email?: string;
  user_avatar?: string | null;
  category_id?: number | null;
  category_name?: string | null;
  hours_estimated?: number | null;
  created_at: string;
  updated_at?: string | null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Categories & Attributes
// ─────────────────────────────────────────────────────────────────────────────

export interface AdminCategory {
  id: number;
  name: string;
  slug: string;
  color: string;
  type: 'listing' | 'event' | 'blog' | 'vol_opportunity';
  listing_count: number;
  created_at: string;
}

export interface AdminAttribute {
  id: number;
  name: string;
  slug: string;
  type: string;
  options?: string[] | null;
  category_id: number | null;
  category_name: string | null;
  is_active: boolean;
  target_type: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Gamification
// ─────────────────────────────────────────────────────────────────────────────

export interface GamificationStats {
  total_badges_awarded: number;
  active_users: number;
  total_xp_awarded: number;
  active_campaigns: number;
  badge_distribution: Array<{ badge_name: string; count: number }>;
}

export interface Campaign {
  id: number;
  name: string;
  description: string;
  status: 'draft' | 'active' | 'paused' | 'completed';
  badge_id?: number;
  badge_key?: string;
  badge_name: string;
  target_audience: string;
  start_date: string | null;
  end_date: string | null;
  total_awards?: number;
  created_at: string;
}

export interface BadgeDefinition {
  id: number | null;
  key: string;
  name: string;
  description: string;
  icon: string;
  type: 'built_in' | 'custom';
  awarded_count: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Matching & Broker
// ─────────────────────────────────────────────────────────────────────────────

export interface MatchApproval {
  id: number;
  user_1_id: number;
  user_1_name: string;
  user_1_email?: string;
  user_1_avatar?: string | null;
  user_2_id: number;
  user_2_name: string;
  user_2_email?: string;
  user_2_avatar?: string | null;
  listing_id?: number | null;
  listing_title?: string | null;
  listing_type?: string | null;
  listing_description?: string | null;
  match_score: number;
  match_type?: string;
  match_reasons?: string[];
  distance_km?: number | null;
  status: 'pending' | 'approved' | 'rejected';
  notes?: string | null;
  created_at: string;
  reviewed_at?: string | null;
  reviewer_id?: number | null;
  reviewer_name?: string | null;
}

export interface MatchApprovalDetail extends MatchApproval {
  user_1_bio?: string | null;
  user_1_location?: string | null;
  user_2_bio?: string | null;
  user_2_location?: string | null;
  listing_status?: string | null;
  category_name?: string | null;
}

export interface MatchApprovalStats {
  pending_count: number;
  approved_count: number;
  rejected_count: number;
  avg_approval_time: number;
  approval_rate: number;
}

export interface SmartMatchingConfig {
  category_weight: number;
  skill_weight: number;
  proximity_weight: number;
  freshness_weight: number;
  reciprocity_weight: number;
  quality_weight: number;
  proximity_bands: Array<{ distance_km: number; score: number }>;
  enabled?: boolean;
  broker_approval_enabled?: boolean;
  max_distance_km?: number;
  min_match_score?: number;
  hot_match_threshold?: number;
}

export interface MatchingOverviewStats {
  total_matches_today: number;
  total_matches_week: number;
  total_matches_month: number;
  hot_matches_count: number;
  mutual_matches_count: number;
  avg_match_score: number;
  avg_distance_km: number;
  cache_entries: number;
  cache_hit_rate: number;
  active_users_matching: number;
}

export interface MatchingStatsResponse {
  overview: MatchingOverviewStats;
  score_distribution: Record<string, number>;
  distance_distribution: Record<string, number>;
  broker_approval_enabled: boolean;
  pending_approvals: number;
  approved_count: number;
  rejected_count: number;
  approval_rate: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Timebanking
// ─────────────────────────────────────────────────────────────────────────────

export interface TimebankingStats {
  total_transactions: number;
  total_volume: number;
  avg_transaction: number;
  active_alerts: number;
  top_earners: Array<{ user_id: number; user_name: string; amount: number }>;
  top_spenders: Array<{ user_id: number; user_name: string; amount: number }>;
}

export interface FraudAlert {
  id: number;
  user_id: number;
  user_name: string;
  alert_type: string;
  severity: 'low' | 'medium' | 'high' | 'critical';
  status: 'new' | 'reviewing' | 'resolved' | 'dismissed';
  description: string;
  created_at: string;
}

export interface OrgWallet {
  id: number;
  org_id: number;
  org_name: string;
  balance: number;
  total_in: number;
  total_out: number;
  member_count: number;
  created_at: string;
}

export interface UserFinancialReport {
  id: number;
  name: string;
  first_name: string;
  last_name: string;
  email: string;
  avatar_url: string | null;
  balance: number;
  total_earned: number;
  total_spent: number;
  transaction_count: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Newsletters
// ─────────────────────────────────────────────────────────────────────────────

export interface Newsletter {
  id: number;
  name: string;
  subject: string;
  status: 'draft' | 'scheduled' | 'sending' | 'sent';
  recipients_count: number;
  open_rate: number;
  click_rate: number;
  sent_at: string | null;
  created_at: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Enterprise
// ─────────────────────────────────────────────────────────────────────────────

export interface Role {
  id: number;
  name: string;
  slug: string;
  description: string;
  permissions: string[];
  users_count: number;
  created_at: string;
}

export interface GdprRequest {
  id: number;
  user_id: number;
  user_name: string;
  user_email?: string;
  type: 'access' | 'deletion' | 'portability' | 'rectification';
  status: 'pending' | 'processing' | 'completed' | 'rejected';
  notes?: string;
  created_at: string;
  completed_at?: string;
}

export interface LegalDocument {
  id: number;
  title: string;
  content: string;
  type: string;
  version?: string;
  status: 'draft' | 'published' | 'archived';
  created_at: string;
  updated_at?: string;
}

export interface SystemHealth {
  php_version: string;
  memory_usage: string;
  memory_limit: string;
  db_connected: boolean;
  redis_connected: boolean;
  redis_memory: string;
  db_size: string;
  uptime: string;
  server_time: string;
  os: string;
}

export interface HealthCheckResult {
  status: 'healthy' | 'degraded' | 'unhealthy';
  checks: Array<{
    name: string;
    status: 'ok' | 'fail';
    free?: string;
    total?: string;
  }>;
}

export interface GdprDashboardStats {
  total_requests: number;
  pending_requests: number;
  total_consents: number;
  total_breaches: number;
}

export interface GdprConsent {
  id: number;
  user_id: number;
  user_name: string;
  consent_type: string;
  consented: boolean;
  consented_at?: string;
  created_at: string;
}

export interface GdprBreach {
  id: number;
  title: string;
  description: string;
  severity: 'low' | 'medium' | 'high' | 'critical';
  status: 'open' | 'investigating' | 'resolved';
  reported_at: string;
}

export interface GdprAuditEntry {
  id: number;
  user_id: number;
  user_name: string;
  action: string;
  description: string;
  created_at: string;
}

export interface EnterpriseDashboardStats {
  user_count: number;
  role_count: number;
  pending_gdpr_requests: number;
  health_status: 'healthy' | 'degraded' | 'unhealthy';
  db_connected: boolean;
  redis_connected: boolean;
}

export interface SecretEntry {
  key: string;
  is_set: boolean;
  masked_value: string;
}

export interface ErrorLogEntry {
  id: number;
  user_id?: number;
  user_name?: string;
  action: string;
  description: string;
  ip_address?: string;
  created_at: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// System
// ─────────────────────────────────────────────────────────────────────────────

export interface CronJob {
  id: number;
  name: string;
  command: string;
  schedule: string;
  status: 'active' | 'disabled';
  last_run_at: string | null;
  last_status: 'success' | 'failed' | null;
  next_run_at: string | null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Blog
// ─────────────────────────────────────────────────────────────────────────────

export interface AdminBlogPost {
  id: number;
  title: string;
  slug: string;
  content?: string;
  excerpt?: string;
  status: 'draft' | 'published';
  featured_image?: string | null;
  author_id: number;
  author_name?: string;
  category_id?: number | null;
  category_name?: string;
  created_at: string;
  updated_at?: string;
}

export interface CreateBlogPostPayload {
  title: string;
  content?: string;
  excerpt?: string;
  status?: 'draft' | 'published';
  featured_image?: string;
  category_id?: number;
}

export interface UpdateBlogPostPayload extends Partial<CreateBlogPostPayload> {}

// ─────────────────────────────────────────────────────────────────────────────
// Broker Controls
// ─────────────────────────────────────────────────────────────────────────────

export interface BrokerDashboardStats {
  pending_exchanges: number;
  unreviewed_messages: number;
  high_risk_listings: number;
  monitored_users: number;
  vetting_pending: number;
  vetting_expiring: number;
  safeguarding_alerts: number;
  recent_activity: BrokerActivityEntry[];
}

export interface BrokerActivityEntry {
  id: number;
  user_id: number;
  first_name: string;
  last_name: string;
  action_type: string;
  details: string;
  created_at: string;
}

export interface ExchangeRequest {
  id: number;
  requester_id: number;
  requester_name: string;
  provider_id: number;
  provider_name: string;
  listing_id?: number;
  listing_title?: string;
  status: string;
  broker_id?: number;
  broker_notes?: string;
  broker_conditions?: string;
  broker_approved_at?: string;
  final_hours?: number;
  created_at: string;
}

export interface RiskTag {
  id: number;
  listing_id: number;
  listing_title?: string;
  owner_name?: string;
  risk_level: 'low' | 'medium' | 'high' | 'critical';
  risk_category: string;
  risk_notes?: string;
  requires_approval: boolean;
  insurance_required: boolean;
  dbs_required: boolean;
  created_at: string;
}

export interface BrokerMessage {
  id: number;
  sender_id: number;
  sender_name: string;
  receiver_id: number;
  receiver_name: string;
  related_listing_id?: number;
  listing_title?: string;
  reviewed_by?: number;
  reviewed_at?: string;
  flagged: boolean;
  flag_reason?: string;
  flag_severity?: string;
  created_at: string;
}

export interface MonitoredUser {
  id: number;
  user_id: number;
  user_name: string;
  under_monitoring: boolean;
  monitoring_reason?: string;
  monitoring_started_at?: string;
  restricted_by?: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Groups
// ─────────────────────────────────────────────────────────────────────────────

export interface AdminGroup {
  id: number;
  name: string;
  description?: string;
  image_url?: string | null;
  visibility: string;
  status: string;
  creator_name?: string;
  member_count: number;
  created_at: string;
}

export interface GroupApproval {
  id: number;
  group_id: number;
  group_name: string;
  user_id: number;
  user_name: string;
  status: string;
  created_at: string;
}

export interface GroupAnalyticsData {
  total_groups: number;
  total_members: number;
  avg_members_per_group: number;
  active_groups: number;
  pending_approvals: number;
  most_active_groups: Array<{ id: number; name: string; member_count: number }>;
}

export interface GroupModerationItem {
  id: number;
  name: string;
  status: string;
  report_count: number;
  created_at: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Pagination
// ─────────────────────────────────────────────────────────────────────────────

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    page: number;
    total_pages: number;
    per_page: number;
    total: number;
    has_more: boolean;
    base_url?: string;
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// Super Admin — Tenants
// ─────────────────────────────────────────────────────────────────────────────

export interface SuperAdminDashboardStats {
  total_tenants: number;
  active_tenants: number;
  total_users: number;
  total_listings: number;
  hub_tenants: number;
  inactive_tenants: number;
}

export interface SuperAdminTenant {
  id: number;
  name: string;
  slug: string;
  domain: string;
  tagline?: string;
  description?: string;
  parent_id: number | null;
  parent_name?: string;
  is_active: boolean;
  allows_subtenants: boolean;
  max_depth: number;
  user_count?: number;
  listing_count?: number;
  contact_email?: string;
  contact_phone?: string;
  address?: string;
  meta_title?: string;
  meta_description?: string;
  h1_headline?: string;
  hero_intro?: string;
  og_image_url?: string;
  robots_directive?: string;
  location_name?: string;
  country_code?: string;
  service_area?: string;
  latitude?: string;
  longitude?: string;
  social_facebook?: string;
  social_twitter?: string;
  social_instagram?: string;
  social_linkedin?: string;
  social_youtube?: string;
  features?: Record<string, boolean>;
  configuration?: Record<string, unknown>;
  created_at: string;
  updated_at?: string;
}

export interface SuperAdminTenantDetail extends SuperAdminTenant {
  children: SuperAdminTenant[];
  admins: SuperAdminUser[];
  breadcrumb: Array<{ id: number; name: string }>;
}

export interface CreateTenantPayload {
  parent_id: number;
  name: string;
  slug: string;
  domain?: string;
  tagline?: string;
  description?: string;
  allows_subtenants?: boolean;
  max_depth?: number;
  is_active?: boolean;
}

export interface UpdateTenantPayload {
  name?: string;
  slug?: string;
  domain?: string;
  tagline?: string;
  description?: string;
  is_active?: boolean;
  allows_subtenants?: boolean;
  max_depth?: number;
  contact_email?: string;
  contact_phone?: string;
  address?: string;
  meta_title?: string;
  meta_description?: string;
  h1_headline?: string;
  hero_intro?: string;
  og_image_url?: string;
  robots_directive?: string;
  location_name?: string;
  country_code?: string;
  service_area?: string;
  latitude?: string;
  longitude?: string;
  social_facebook?: string;
  social_twitter?: string;
  social_instagram?: string;
  social_linkedin?: string;
  social_youtube?: string;
  features?: Record<string, boolean>;
}

export interface TenantHierarchyNode {
  id: number;
  name: string;
  slug: string;
  is_active: boolean;
  allows_subtenants: boolean;
  user_count: number;
  children: TenantHierarchyNode[];
}

// ─────────────────────────────────────────────────────────────────────────────
// Super Admin — Cross-Tenant Users
// ─────────────────────────────────────────────────────────────────────────────

export interface SuperAdminUser {
  id: number;
  first_name: string;
  last_name: string;
  name: string;
  email: string;
  role: string;
  status: string;
  tenant_id: number;
  tenant_name?: string;
  is_super_admin: boolean;
  is_tenant_super_admin: boolean;
  created_at: string;
  last_login_at?: string | null;
}

export interface SuperAdminUserDetail extends SuperAdminUser {
  location?: string;
  phone?: string;
  avatar?: string | null;
  balance?: number;
}

export interface CreateSuperUserPayload {
  tenant_id: number;
  first_name: string;
  last_name: string;
  email: string;
  password: string;
  role?: string;
  location?: string;
  phone?: string;
  is_tenant_super_admin?: boolean;
}

export interface SuperUserListParams {
  page?: number;
  limit?: number;
  search?: string;
  tenant_id?: number;
  role?: string;
  super_admins?: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Super Admin — Bulk Operations
// ─────────────────────────────────────────────────────────────────────────────

export interface BulkMoveUsersPayload {
  user_ids: number[];
  target_tenant_id: number;
  grant_super_admin?: boolean;
}

export interface BulkUpdateTenantsPayload {
  tenant_ids: number[];
  action: 'activate' | 'deactivate' | 'enable_hub' | 'disable_hub';
}

export interface BulkOperationResult {
  success: boolean;
  moved_count?: number;
  updated_count: number;
  total_requested: number;
  errors: string[];
}

// ─────────────────────────────────────────────────────────────────────────────
// Super Admin — Audit
// ─────────────────────────────────────────────────────────────────────────────

export interface SuperAuditEntry {
  id: number;
  action_type: string;
  target_type: string;
  target_id: number | null;
  target_label: string;
  actor_id: number;
  actor_name?: string;
  old_value?: Record<string, unknown> | null;
  new_value?: Record<string, unknown> | null;
  description: string;
  created_at: string;
}

export interface SuperAuditParams {
  action_type?: string;
  target_type?: string;
  search?: string;
  date_from?: string;
  date_to?: string;
  limit?: number;
  offset?: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Super Admin — Federation Controls
// ─────────────────────────────────────────────────────────────────────────────

export interface FederationSystemControls {
  federation_enabled: boolean;
  whitelist_mode_enabled: boolean;
  max_federation_level: number;
  cross_tenant_profiles_enabled: boolean;
  cross_tenant_messaging_enabled: boolean;
  cross_tenant_transactions_enabled: boolean;
  cross_tenant_listings_enabled: boolean;
  cross_tenant_events_enabled: boolean;
  cross_tenant_groups_enabled: boolean;
  is_locked_down: boolean;
  lockdown_reason?: string;
  updated_at?: string;
}

export interface FederationWhitelistEntry {
  tenant_id: number;
  tenant_name: string;
  tenant_domain?: string;
  added_by: number;
  added_at: string;
  notes?: string;
}

export interface FederationPartnership {
  id: number;
  tenant_1_id: number;
  tenant_1_name: string;
  tenant_2_id: number;
  tenant_2_name: string;
  status: 'pending' | 'active' | 'suspended' | 'terminated';
  created_at: string;
}

export interface FederationStatusOverview {
  system_controls: FederationSystemControls;
  partnership_stats: { total: number; active: number; pending: number; suspended: number };
  whitelisted_count: number;
  recent_audit: SuperAuditEntry[];
}

export interface TenantFederationFeatures {
  tenant_id: number;
  tenant_name: string;
  is_whitelisted: boolean;
  features: Record<string, boolean>;
  partnerships: FederationPartnership[];
}

// ─────────────────────────────────────────────────────────────────────────────
// Navigation
// ─────────────────────────────────────────────────────────────────────────────

export interface AdminNavItem {
  label: string;
  href: string;
  icon: string;
  badge?: string | number;
  children?: AdminNavItem[];
}

export interface AdminNavSection {
  key: string;
  label: string;
  icon: string;
  items?: AdminNavItem[];
  href?: string;
  condition?: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Vetting Records (TOL2 compliance)
// ─────────────────────────────────────────────────────────────────────────────

export interface VettingRecord {
  id: number;
  user_id: number;
  first_name: string;
  last_name: string;
  email: string;
  avatar?: string;
  vetting_type: 'dbs_basic' | 'dbs_standard' | 'dbs_enhanced' | 'garda_vetting' | 'access_ni' | 'pvg_scotland' | 'international' | 'other';
  status: 'pending' | 'submitted' | 'verified' | 'expired' | 'rejected' | 'revoked';
  reference_number: string | null;
  issue_date: string | null;
  expiry_date: string | null;
  verified_by: number | null;
  verifier_first_name: string | null;
  verifier_last_name: string | null;
  verified_at: string | null;
  document_url: string | null;
  notes: string | null;
  works_with_children: boolean;
  works_with_vulnerable_adults: boolean;
  requires_enhanced_check: boolean;
  created_at: string;
  updated_at: string | null;
}

export interface VettingStats {
  total: number;
  pending: number;
  verified: number;
  expired: number;
  expiring_soon: number;
}
