/**
 * Admin Panel TypeScript Types
 * Types for admin API requests and responses
 */

// ─────────────────────────────────────────────────────────────────────────────
// Dashboard
// ─────────────────────────────────────────────────────────────────────────────

export interface AdminDashboardStats {
  total_users: number;
  total_listings: number;
  total_transactions: number;
  total_volume: number;
  pending_users: number;
  pending_listings: number;
  active_sessions: number;
}

export interface MonthlyTrend {
  month: string;
  volume: number;
  transactions: number;
  new_users: number;
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
  tenant_id: number;
  balance: number;
  total_earned: number;
  total_spent: number;
  has_2fa_enabled: boolean;
  is_super_admin: boolean;
  is_admin: boolean;
  level: number;
  badges_count: number;
  created_at: string;
  last_login_at: string | null;
}

export interface AdminUserDetail extends AdminUser {
  bio?: string;
  tagline?: string;
  location?: string;
  skills?: string[];
  interests?: string[];
  badges: AdminBadge[];
  permissions?: string[];
  notification_preferences?: Record<string, boolean>;
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
  type: 'listing' | 'event' | 'poll' | 'goal' | 'resource' | 'volunteer';
  status: 'active' | 'pending' | 'inactive' | 'archived';
  user_id: number;
  user_name: string;
  tenant_id: number;
  tenant_name?: string;
  category?: string;
  created_at: string;
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
  options?: string[];
  created_at: string;
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
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
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
