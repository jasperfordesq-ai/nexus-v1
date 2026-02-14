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
  description?: string;
  parent_id: number | null;
  sort_order: number;
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
  badge_id: number;
  badge_name: string;
  target_audience: string;
  start_date: string | null;
  end_date: string | null;
  created_at: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Matching & Broker
// ─────────────────────────────────────────────────────────────────────────────

export interface MatchApproval {
  id: number;
  user_1_id: number;
  user_1_name: string;
  user_1_avatar?: string;
  user_2_id: number;
  user_2_name: string;
  user_2_avatar?: string;
  listing_id?: number;
  listing_title?: string;
  match_score: number;
  status: 'pending' | 'approved' | 'rejected';
  notes?: string;
  created_at: string;
  reviewed_at?: string;
  reviewer_id?: number;
}

export interface SmartMatchingConfig {
  category_weight: number;
  skill_weight: number;
  proximity_weight: number;
  freshness_weight: number;
  reciprocity_weight: number;
  quality_weight: number;
  proximity_bands: Array<{ distance_km: number; score: number }>;
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
  status: 'open' | 'investigating' | 'resolved' | 'dismissed';
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
  type: 'access' | 'deletion' | 'portability' | 'rectification';
  status: 'pending' | 'processing' | 'completed' | 'rejected';
  notes?: string;
  created_at: string;
  completed_at?: string;
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
