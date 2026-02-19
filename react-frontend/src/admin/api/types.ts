// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Panel TypeScript Types
 * Types for admin API requests and responses
 */

// ─────────────────────────────────────────────────────────────────────────────
// Groups - Advanced Management (Phase 3)
// ─────────────────────────────────────────────────────────────────────────────

export interface GroupType {
  id: number;
  name: string;
  description?: string;
  icon?: string;
  color?: string;
  member_count: number;
  policy_count: number;
  created_at: string;
}

export interface GroupPolicy {
  category: string;
  key: string;
  value: string | number | boolean;
  type: 'boolean' | 'number' | 'string';
  label: string;
  description?: string;
}

export interface GroupMember {
  user_id: number;
  user_name: string;
  user_avatar?: string;
  role: 'owner' | 'admin' | 'member';
  joined_at: string;
}

export interface GroupRecommendation {
  user_id: number;
  user_name: string;
  group_id: number;
  group_name: string;
  score: number;
  joined: boolean;
  created_at: string;
}

export interface FeaturedGroup {
  group_id: number;
  name: string;
  member_count: number;
  engagement_score: number;
  geographic_diversity: number;
  ranking_score: number;
  is_featured: boolean;
  manual_rank?: number;
}

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
  status?: string;
  phone?: string;
  password?: string;
  send_welcome_email?: boolean;
}

export interface UpdateUserPayload {
  first_name?: string;
  last_name?: string;
  email?: string;
  phone?: string;
  role?: string;
  status?: string;
  bio?: string;
  tagline?: string;
  location?: string;
  profile_type?: 'individual' | 'organisation';
  organization_name?: string;
}

export interface UserConsent {
  consent_type: string;
  name: string;
  description: string | null;
  category: string | null;
  is_required: boolean;
  consent_given: boolean;
  consent_version: string | null;
  given_at: string | null;
  withdrawn_at: string | null;
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

export interface NewsletterBounce {
  id: number;
  newsletter_id: number;
  email: string;
  bounce_type: 'hard' | 'soft' | 'complaint';
  bounce_reason: string;
  bounced_at: string;
  campaign_name?: string;
  newsletter_subject?: string;
}

export interface SuppressionListEntry {
  email: string;
  reason: string;
  suppressed_at: string;
  bounce_count: number;
}

export interface ResendInfo {
  newsletter_id: number;
  total_sent: number;
  total_opened: number;
  total_clicked: number;
  non_openers_count: number;
  non_clickers_count: number;
}

export interface ResendOptions {
  target: 'non_openers' | 'non_clickers' | 'segment';
  segment_id?: number;
  subject_override?: string;
}

export interface SendTimeData {
  heatmap: Array<{
    day_of_week: number;
    hour: number;
    engagement_score: number;
    opens: number;
    clicks: number;
  }>;
  recommendations: Array<{
    day_of_week: number;
    hour: number;
    score: number;
    description: string;
  }>;
  insights: string;
}

export interface NewsletterDiagnostics {
  queue_status: {
    total: number;
    pending: number;
    sending: number;
    sent: number;
    failed: number;
  };
  bounce_rate: number;
  sender_score: number;
  configuration: {
    smtp_configured: boolean;
    api_configured: boolean;
    tracking_enabled: boolean;
  };
  health_status: 'healthy' | 'warning' | 'critical';
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

export interface LegalDocumentVersion {
  id: number;
  document_id: number;
  version_number: string;
  version_label?: string | null;
  content: string;
  content_plain: string;
  summary_of_changes?: string | null;
  effective_date?: string | null;
  published_at?: string | null;
  is_draft: boolean;
  is_current: boolean;
  notification_sent?: boolean;
  notification_sent_at?: string | null;
  created_by: number;
  created_by_name?: string;
  published_by?: number | null;
  published_by_name?: string | null;
  created_at: string;
}

export interface VersionComparison {
  version1: LegalDocumentVersion;
  version2: LegalDocumentVersion;
  diff_html: string;
  changes_count: number;
}

export interface ComplianceStats {
  total_users: number;
  overall_compliance_rate: number;
  users_pending_acceptance: number;
  documents: Array<{
    id: number;
    document_type: string;
    title: string;
    current_version_id: number | null;
    version_number: string;
    effective_date: string | null;
    users_accepted: number;
    users_not_accepted: number;
    acceptance_rate: number;
  }>;
}

export interface UserAcceptance {
  user_id: number;
  user_name: string;
  user_email: string;
  version_id: number;
  version_number: string;
  accepted_at: string;
  acceptance_method: string;
  ip_address: string | null;
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
  meta_title?: string | null;
  meta_description?: string | null;
  noindex?: boolean;
  created_at: string;
  updated_at?: string;
}

export interface CreateBlogPostPayload {
  title: string;
  slug?: string;
  content?: string;
  excerpt?: string;
  status?: 'draft' | 'published';
  featured_image?: string;
  category_id?: number;
  meta_title?: string;
  meta_description?: string;
  noindex?: boolean;
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

export interface BrokerConfig {
  // Messaging
  broker_messaging_enabled: boolean;
  broker_copy_all_messages: boolean;
  broker_copy_threshold_hours: number;
  new_member_monitoring_days: number;
  require_exchange_for_listings: boolean;
  // Risk Tagging
  risk_tagging_enabled: boolean;
  auto_flag_high_risk: boolean;
  require_approval_high_risk: boolean;
  notify_on_high_risk_match: boolean;
  // Exchange Workflow
  broker_approval_required: boolean;
  auto_approve_low_risk: boolean;
  exchange_timeout_days: number;
  max_hours_without_approval: number;
  confirmation_deadline_hours: number;
  allow_hour_adjustment: boolean;
  max_hour_variance_percent: number;
  expiry_hours: number;
  // Broker Visibility / Message Copy
  broker_visible_to_members: boolean;
  show_broker_name: boolean;
  broker_contact_email: string;
  copy_first_contact: boolean;
  copy_new_member_messages: boolean;
  copy_high_risk_listing_messages: boolean;
  random_sample_percentage: number;
  retention_days: number;
}

export interface ExchangeHistoryEntry {
  id: number;
  exchange_id: number;
  actor_id?: number;
  actor_name?: string;
  action: string;
  notes?: string;
  created_at: string;
}

export interface ExchangeDetail {
  exchange: ExchangeRequest & {
    requester_email?: string;
    requester_avatar?: string;
    provider_email?: string;
    provider_avatar?: string;
    listing_type?: string;
    hours_offered?: number;
  };
  history: ExchangeHistoryEntry[];
  risk_tag?: RiskTag | null;
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
  location?: string;
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

// ─────────────────────────────────────────────────────────────────────────────
// Admin Settings Response
// ─────────────────────────────────────────────────────────────────────────────

export interface AdminSettingsResponse {
  tenant_id: number;
  tenant: {
    name: string;
    description: string;
    contact_email: string;
    contact_phone: string;
  };
  settings: {
    registration_mode: string;
    email_verification: string | boolean;
    admin_approval: string | boolean;
    maintenance_mode: string | boolean;
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// Cron Job Monitoring
// ─────────────────────────────────────────────────────────────────────────────

export interface CronLog {
  id: number;
  job_id: string;
  job_name: string;
  status: 'success' | 'failed';
  output: string;
  duration_seconds: number;
  executed_at: string;
  executed_by: string; // 'cron' or 'manual-{user_id}'
}

export interface CronJobSettings {
  job_id: string;
  is_enabled: boolean;
  custom_schedule?: string;
  notify_on_failure: boolean;
  notify_emails?: string;
  max_retries: number;
  timeout_seconds: number;
}

export interface GlobalCronSettings {
  default_notify_email?: string;
  log_retention_days: number;
  max_concurrent_jobs: number;
}

export interface CronHealthMetrics {
  health_score: number;
  recent_failures: Array<{
    job_name: string;
    failed_at: string;
    reason: string;
  }>;
  jobs_failed_24h: number;
  jobs_overdue: Array<{
    job_id: string;
    job_name: string;
    last_run: string;
    expected_interval: string;
  }>;
  avg_success_rate_7d: number;
  alert_status: 'critical' | 'warning' | 'healthy';
}

// ─────────────────────────────────────────────────────────────────────────────
// Match Approvals
// ─────────────────────────────────────────────────────────────────────────────

export interface MatchApproval {
  id: number;
  user_a_id: number;
  user_b_id: number;
  user_a_name: string;
  user_b_name: string;
  user_a_avatar?: string | null;
  user_b_avatar?: string | null;
  match_score: number;
  reasoning: string[];
  status: 'pending' | 'approved' | 'rejected';
  created_at: string;
  skills_overlap?: string[];
  interests_overlap?: string[];
  location_proximity?: number;
}

export interface MatchStats {
  total: number;
  pending: number;
  approved: number;
  rejected: number;
  avg_score: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Content Moderation
// ─────────────────────────────────────────────────────────────────────────────

export interface AdminFeedPost {
  id: number;
  user_id: number;
  user_name: string;
  user_avatar?: string | null;
  content: string;
  type: 'text' | 'poll' | 'event' | 'listing';
  status: 'active' | 'hidden' | 'flagged';
  is_hidden: boolean;
  is_flagged: boolean;
  comments_count: number;
  reactions_count: number;
  reports_count: number;
  created_at: string;
}

export interface AdminComment {
  id: number;
  user_id: number;
  user_name: string;
  user_avatar?: string | null;
  content_type: 'listing' | 'event' | 'post' | 'blog';
  content_id: number;
  content_title?: string;
  content: string;
  is_hidden: boolean;
  is_flagged: boolean;
  reports_count: number;
  created_at: string;
}

export interface AdminReview {
  id: number;
  reviewer_id: number;
  reviewer_name: string;
  reviewer_avatar?: string | null;
  reviewee_id: number;
  reviewee_name: string;
  reviewee_avatar?: string | null;
  rating: number;
  content: string;
  is_hidden: boolean;
  is_flagged: boolean;
  reports_count: number;
  created_at: string;
}

export interface AdminReport {
  id: number;
  reporter_id: number;
  reporter_name: string;
  reporter_avatar?: string | null;
  content_type: 'listing' | 'event' | 'post' | 'comment' | 'review' | 'user';
  content_id: number;
  content_preview?: string;
  reason: string;
  description?: string;
  status: 'pending' | 'resolved' | 'dismissed';
  created_at: string;
  resolved_at?: string | null;
  resolved_by?: string | null;
}

export interface ModerationStats {
  feed_posts_total: number;
  feed_posts_hidden: number;
  feed_posts_flagged: number;
  comments_total: number;
  comments_hidden: number;
  comments_flagged: number;
  reviews_total: number;
  reviews_hidden: number;
  reviews_flagged: number;
  reports_pending: number;
  reports_resolved: number;
  reports_dismissed: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Super Admin - Tenant Management
// ─────────────────────────────────────────────────────────────────────────────

export interface SuperTenant {
  id: number;
  name: string;
  slug: string;
  domain: string | null;
  tagline: string | null;
  description: string | null;
  parent_id: number | null;
  parent_name?: string | null;
  is_active: boolean;
  allows_subtenants: boolean;
  max_depth: number;
  depth: number;
  path: string;
  user_count: number;
  child_count: number;
  can_manage: boolean;
  relationship: 'self' | 'ancestor' | 'descendant' | 'other';
  meta_title?: string | null;
  meta_description?: string | null;
  meta_keywords?: string | null;
  contact_email?: string | null;
  contact_phone?: string | null;
  address?: string | null;
  city?: string | null;
  country?: string | null;
  facebook_url?: string | null;
  twitter_url?: string | null;
  linkedin_url?: string | null;
  instagram_url?: string | null;
  created_at: string;
  updated_at: string;
}

export interface TenantHierarchyNode {
  id: number;
  name: string;
  slug: string;
  parent_id: number | null;
  depth: number;
  is_active: boolean;
  allows_subtenants: boolean;
  user_count: number;
  children: TenantHierarchyNode[];
}

// ─────────────────────────────────────────────────────────────────────────────
// Super Admin - User Management
// ─────────────────────────────────────────────────────────────────────────────

export interface SuperUser {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  tenant_id: number;
  tenant_name: string;
  role: string;
  status: 'active' | 'inactive' | 'suspended';
  is_super_admin: boolean;
  is_tenant_super_admin: boolean;
  avatar?: string | null;
  location?: string | null;
  phone?: string | null;
  last_login_at?: string | null;
  created_at: string;
  updated_at: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Super Admin - Audit Logs
// ─────────────────────────────────────────────────────────────────────────────

export interface FederationAuditEntry {
  id: number;
  level: 'critical' | 'warning' | 'info' | 'debug';
  action_type: string;
  category: 'system' | 'tenant' | 'partnership' | 'profile' | 'messaging' | 'transaction';
  actor_id: number | null;
  actor_name: string | null;
  actor_email: string | null;
  tenant_from_id: number | null;
  tenant_to_id: number | null;
  ip_address: string | null;
  user_agent: string | null;
  data: Record<string, any> | null;
  created_at: string;
}

export interface AuditStats {
  actions_30d: number;
  tenant_changes_30d: number;
  user_changes_30d: number;
  active_admins_30d: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Super Admin - Federation
// ─────────────────────────────────────────────────────────────────────────────

export interface FederationStats {
  system_enabled: boolean;
  whitelist_mode: boolean;
  whitelisted_count: number;
  active_partnerships: number;
  pending_requests: number;
  federation_enabled_count: number;
  profiles_enabled: boolean;
  messaging_enabled: boolean;
  transactions_enabled: boolean;
  listings_enabled: boolean;
  events_enabled: boolean;
  groups_enabled: boolean;
  emergency_lockdown_active: boolean;
  lockdown_reason?: string | null;
}

export interface WhitelistedTenant {
  tenant_id: number;
  tenant_name: string;
  tenant_domain: string | null;
  approved_by: string;
  approved_at: string;
  notes?: string | null;
}

export interface Partnership {
  id: number;
  tenant_a_id: number;
  tenant_a_name: string;
  tenant_b_id: number;
  tenant_b_name: string;
  level: number;
  level_name: string;
  status: 'active' | 'pending' | 'suspended' | 'terminated';
  features_enabled: {
    profiles: boolean;
    messaging: boolean;
    transactions: boolean;
    listings: boolean;
    events: boolean;
    groups: boolean;
  };
  created_at: string;
  suspended_at?: string | null;
  suspended_reason?: string | null;
  terminated_at?: string | null;
  terminated_reason?: string | null;
}

export interface TenantFederationFeatures {
  tenant_id: number;
  tenant_name: string;
  tenant_domain: string | null;
  is_whitelisted: boolean;
  active_partnerships_count: number;
  federation_enabled: boolean;
  directory_visible: boolean;
  profiles_enabled: boolean;
  messaging_enabled: boolean;
  transactions_enabled: boolean;
  listings_enabled: boolean;
  events_enabled: boolean;
  groups_enabled: boolean;
}
