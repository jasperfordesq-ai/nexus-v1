/**
 * NEXUS API Type Definitions
 * Matches the PHP backend API contracts from docs/API_CONTRACT_REFERENCE.md
 */

// ─────────────────────────────────────────────────────────────────────────────
// User Types
// ─────────────────────────────────────────────────────────────────────────────

export interface User {
  id: number;
  email?: string;
  name?: string;
  first_name?: string;
  last_name?: string;
  username?: string;
  avatar?: string | null;
  avatar_url?: string | null;
  bio?: string;
  tagline?: string;
  location?: string;
  latitude?: number | null;
  longitude?: number | null;
  skills?: string[];
  role?: 'member' | 'admin' | 'moderator' | 'tenant_admin' | 'super_admin';
  is_super_admin?: boolean;
  is_admin?: boolean;
  status?: 'active' | 'inactive' | 'suspended' | 'pending';
  tenant_id?: number;
  tenant_slug?: string;
  balance?: number;
  total_earned?: number;
  total_spent?: number;
  total_hours_given?: number;
  total_hours_received?: number;
  rating?: number;
  level?: number;
  phone?: string;
  profile_type?: 'individual' | 'organisation';
  organization_name?: string;
  has_2fa_enabled?: boolean;
  preferred_layout?: 'modern';
  onboarding_completed?: boolean;
  email_verified_at?: string | null;
  created_at?: string;
  updated_at?: string;
  last_login_at?: string;
}

export interface UserProfile extends User {
  interests?: string[];
  badges?: Badge[];
  stats?: UserStats;
  reviews_count?: number;
  average_rating?: number;
  listings_count?: number;
  notification_preferences?: NotificationPreferences;
  privacy_settings?: PrivacySettings;
}

export interface UserStats {
  hours_given: number;
  hours_received: number;
  listings_count: number;
  reviews_count: number;
  average_rating: number;
  connections_count: number;
}

export interface NotificationPreferences {
  email_digest: 'daily' | 'weekly' | 'none';
  push_enabled: boolean;
  email_on_message: boolean;
  email_on_match: boolean;
}

export interface PrivacySettings {
  profile_visible: boolean;
  show_balance: boolean;
  show_location: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Authentication Types
// ─────────────────────────────────────────────────────────────────────────────

export interface LoginRequest {
  email: string;
  password: string;
  platform?: 'web' | 'mobile' | 'pwa';
}

export interface LoginSuccessResponse {
  success: true;
  user: User;
  access_token: string;
  refresh_token: string;
  expires_in: number;
  refresh_expires_in?: number;
  token_type: 'Bearer';
  is_mobile?: boolean;
  token: string;  // Legacy alias for access_token
  config?: {
    modules?: Record<string, boolean>;
  };
}

export interface TwoFactorRequiredResponse {
  success: false;
  requires_2fa: true;
  two_factor_token: string;
  methods: ('totp' | 'backup_code' | 'webauthn')[];
  code: 'AUTH_2FA_REQUIRED';
  message: string;
  user: {
    id: number;
    first_name: string;
    email_masked: string;
  };
}

export type LoginResponse = LoginSuccessResponse | TwoFactorRequiredResponse;

export interface TwoFactorVerifyRequest {
  two_factor_token?: string;  // Optional - managed by AuthContext
  code: string;
  use_backup_code?: boolean;
  trust_device?: boolean;
}

export interface RefreshTokenRequest {
  refresh_token: string;
}

export interface RefreshTokenResponse {
  success: true;
  access_token: string;
  token_type: 'Bearer';
  expires_in: number;
  is_mobile?: boolean;
  token: string;
  refresh_token?: string;
  refresh_expires_in?: number;
}

export interface RegisterRequest {
  email: string;
  password: string;
  password_confirmation: string;
  first_name: string;
  last_name: string;
  tenant_id?: number;
  tenant_slug?: string;
  profile_type?: 'individual' | 'organisation';
  organization_name?: string;
  location?: string;
  latitude?: number;
  longitude?: number;
  phone?: string;
  terms_accepted: boolean;
  newsletter_opt_in?: boolean;
}

export interface RegisterResponse {
  success: true;
  user: User;
  message: string;
}

export interface ForgotPasswordRequest {
  email: string;
}

export interface ResetPasswordRequest {
  token: string;
  password: string;
  password_confirmation: string;
}

export interface TotpStatusResponse {
  success: true;
  enabled: boolean;
  setup_required: boolean;
  backup_codes_remaining: number;
  trusted_devices: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Listing Types
// ─────────────────────────────────────────────────────────────────────────────

export type ListingType = 'offer' | 'request';
export type ListingStatus = 'active' | 'paused' | 'completed' | 'expired' | 'deleted';

export interface Listing {
  id: number;
  user_id: number;
  title: string;
  description: string;
  type: ListingType;
  category_id: number | null;
  category_name?: string;
  category_color?: string;
  estimated_hours?: number;
  hours_estimate?: number;  // Alias for estimated_hours (frontend compatibility)
  location?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  image_url?: string | null;
  gallery?: string[];
  status: ListingStatus | null;
  federated_visibility?: 'none' | 'listed' | 'bookable';
  views_count?: number;
  responses_count?: number;
  is_favorited?: boolean;
  can_edit?: boolean;
  can_delete?: boolean;
  created_at: string;
  updated_at: string;

  // Nested author info
  author_name?: string;
  author_avatar?: string | null;
  user?: {
    id: number;
    first_name: string;
    last_name: string;
    name?: string;  // Computed name (frontend compatibility)
    avatar?: string | null;
    tagline?: string;
    average_rating?: number;
    reviews_count?: number;
    member_since?: string;
  };

  // Nested category (frontend compatibility)
  category?: {
    id: number;
    name: string;
    slug: string;
    color?: string;
  };
}

export interface ListingDetail extends Listing {
  author_email?: string;
  attributes?: ListingAttribute[];
  likes_count?: number;
  comments_count?: number;
  coordinates?: {
    lat: number;
    lng: number;
  };
}

export interface ListingAttribute {
  id: number;
  name: string;
  slug: string;
  value: string;
}

export interface ListingCreateRequest {
  title: string;
  description: string;
  type: ListingType;
  category_id: number;
  estimated_hours: number;
  location?: string;
  image_path?: string;
}

export interface ListingUpdateRequest {
  title?: string;
  description?: string;
  status?: ListingStatus;
  category_id?: number;
  estimated_hours?: number;
  location?: string;
}

export interface ListingFilters {
  type?: ListingType;
  category_id?: number;
  q?: string;
  status?: ListingStatus;
  user_id?: number;
  cursor?: string;
  per_page?: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Category Types
// ─────────────────────────────────────────────────────────────────────────────

export interface Category {
  id: number;
  name: string;
  slug: string;
  icon?: string;
  color?: string;
  parent_id?: number;
  children?: Category[];
  listings_count?: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Message Types
// ─────────────────────────────────────────────────────────────────────────────

export interface Conversation {
  id: number;
  // Backend returns other_user (this is the primary field)
  other_user: {
    id: number;
    name: string;
    first_name?: string;
    last_name?: string;
    avatar_url?: string | null;
    avatar?: string | null;  // Alias for avatar_url
    is_online?: boolean;
  };
  // Alias for other_user (deprecated, prefer other_user)
  participant?: {
    id: number;
    first_name?: string;
    last_name?: string;
    name?: string;
    avatar?: string | null;
    is_online?: boolean;
    last_seen_at?: string;
  };
  last_message?: Message;
  unread_count: number;
  listing_context?: {
    id: number;
    title: string;
    status?: string;
  };
  created_at?: string;
  updated_at?: string;
}

export interface Message {
  id: number;
  body: string;           // Backend returns 'body' (primary field)
  content?: string;       // Alias for body (deprecated)
  sender_id: number;
  is_own?: boolean;       // True if current user sent this message
  is_voice?: boolean;     // True if voice message
  audio_url?: string;
  audio_duration?: number;
  sender?: {
    id: number;
    name: string;
    avatar_url?: string;
  };
  is_read?: boolean;
  sent_at?: string;
  created_at: string;
  read_at?: string | null;
  attachments?: MessageAttachment[];
  reactions?: Record<string, number>;  // { emoji: count }
  is_edited?: boolean;
  is_deleted?: boolean;
}

export interface MessageAttachment {
  id: number;
  url: string;
  type: 'image' | 'file';
  name: string;
  size: number;
}

export interface SendMessageRequest {
  recipient_id?: number;
  conversation_id?: number;
  content: string;
  listing_id?: number;
}

export interface UnreadCountResponse {
  count: number;
  conversations_with_unread: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Wallet Types
// ─────────────────────────────────────────────────────────────────────────────

export interface WalletBalance {
  balance: number;
  pending_incoming: number;
  pending_outgoing: number;
  pending_in?: number;   // Alias for pending_incoming (frontend compatibility)
  pending_out?: number;  // Alias for pending_outgoing (frontend compatibility)
  total_earned: number;
  total_spent: number;
  currency: 'hours';
  last_transaction_at?: string;
}

export type TransactionType = 'credit' | 'debit';
export type TransactionStatus = 'pending' | 'completed' | 'cancelled' | 'disputed' | 'refunded';

export interface Transaction {
  id: number;
  type: TransactionType;
  amount: number;
  balance_after: number;
  status: TransactionStatus;
  description: string;
  listing_id?: number;
  listing_title?: string;
  other_party?: {
    id: number;
    first_name: string;
    last_name: string;
    name?: string;  // Computed name (frontend compatibility)
    avatar?: string | null;
  };
  // Alias for other_party (frontend compatibility)
  other_user?: {
    id: number;
    name: string;
    avatar?: string | null;
  };
  created_at: string;
  completed_at?: string;
}

export interface TransferRequest {
  recipient_id: number;
  amount: number;
  description: string;
  listing_id?: number;
}

export interface TransferResponse {
  transaction_id: number;
  amount: number;
  new_balance: number;
  recipient: {
    id: number;
    first_name: string;
    last_name: string;
  };
  message: string;
}

export interface WalletUserSearchResult {
  id: number;
  first_name: string;
  last_name: string;
  username?: string;
  avatar?: string | null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Event Types
// ─────────────────────────────────────────────────────────────────────────────

export type RsvpStatus = 'attending' | 'maybe' | 'not_attending';

export interface Event {
  id: number;
  title: string;
  description: string;
  start_date: string;
  end_date?: string;
  location?: string;
  coordinates?: {
    lat: number;
    lng: number;
  };
  is_online: boolean;
  online_url?: string;
  cover_image?: string | null;
  organizer: {
    id: number;
    first_name: string;
    last_name: string;
    avatar?: string | null;
  };
  group?: {
    id: number;
    name: string;
    slug?: string;
  };
  attendees_count: number;
  maybe_count?: number;
  max_attendees?: number;
  is_full?: boolean;
  category_name?: string;
  interested_count?: number;
  rsvp_status?: RsvpStatus | null;
  can_edit?: boolean;
  recent_attendees?: Array<{
    id: number;
    first_name: string;
    last_name: string;
    avatar?: string | null;
  }>;
  created_at: string;
  updated_at?: string;
}

export interface EventCreateRequest {
  title: string;
  description: string;
  start_date: string;
  end_date?: string;
  location?: string;
  is_online?: boolean;
  online_url?: string;
  max_attendees?: number;
  group_id?: number;
}

export interface RsvpRequest {
  status: RsvpStatus;
}

// ─────────────────────────────────────────────────────────────────────────────
// Group Types
// ─────────────────────────────────────────────────────────────────────────────

export type GroupVisibility = 'public' | 'private' | 'secret';
export type MembershipStatus = 'active' | 'pending' | 'rejected' | null;

export interface Group {
  id: number;
  name: string;
  slug?: string;
  description: string;
  image_url?: string | null;        // Backend returns image_url
  cover_image?: string | null;      // Alias for cover_image_url
  cover_image_url?: string | null;  // Backend returns cover_image_url
  category_id?: number;
  category_name?: string;
  member_count?: number;            // Backend returns member_count
  members_count: number;            // Alias (deprecated)
  posts_count?: number;
  is_member?: boolean;
  is_admin?: boolean;
  membership_status?: MembershipStatus;
  visibility: GroupVisibility;
  rules?: string;
  location?: string;
  latitude?: number | null;
  longitude?: number | null;
  parent_id?: number | null;
  owner?: {
    id: number;
    name?: string;
    avatar_url?: string | null;
  };
  admins?: Array<{
    id: number;
    first_name?: string;
    last_name?: string;
    name?: string;
    avatar?: string | null;
    avatar_url?: string | null;
  }>;
  recent_members?: Array<{
    id: number;
    name?: string;
    first_name?: string;
    last_name?: string;
    avatar?: string | null;
    avatar_url?: string | null;
  }>;
  // Sub-groups (returned by backend for parent groups)
  sub_groups?: Array<{
    id: number;
    name: string;
    member_count: number;
  }>;
  // Viewer's membership info (when authenticated)
  viewer_membership?: {
    status: 'none' | 'active' | 'pending';
    role: string | null;
    is_admin: boolean;
  };
  type?: {
    id: number;
    name?: string;
    icon?: string;
    color?: string;
  };
  is_featured?: boolean;
  federated_visibility?: 'none' | 'listed' | 'joinable';
  created_at: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Feed Post Types
// ─────────────────────────────────────────────────────────────────────────────

export interface FeedPost {
  id: number;
  user_id: number;
  content: string;
  type?: 'text' | 'image' | 'poll';
  media_urls?: string[];
  likes_count: number;
  comments_count: number;
  is_liked?: boolean;
  author: {
    id: number;
    name: string;
    avatar?: string | null;
  };
  created_at: string;
  updated_at: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Notification Types
// ─────────────────────────────────────────────────────────────────────────────

export interface Notification {
  id: number;
  type: string;
  title: string;
  body: string;
  message?: string;  // Alias for body (frontend compatibility)
  read_at?: string | null;
  action_url?: string;
  data?: Record<string, unknown>;
  created_at: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Search Types
// ─────────────────────────────────────────────────────────────────────────────

export interface SearchResults {
  query: string;
  total_results: number;
  results: {
    listings?: Array<Listing & { highlight?: string }>;
    users?: Array<{
      id: number;
      name: string;
      avatar?: string | null;
      skills?: string[];
    }>;
    events?: Array<{
      id: number;
      title: string;
      start_date: string;
    }>;
    groups?: Array<{
      id: number;
      name: string;
      members_count: number;
    }>;
  };
}

export interface SearchSuggestions {
  suggestions: string[];
}

export interface SearchFilters {
  q: string;
  types?: string;  // Comma-separated: 'listings,users,events,groups'
  limit?: number;
  limit_per_type?: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Gamification Types
// ─────────────────────────────────────────────────────────────────────────────

export interface GamificationProfile {
  xp: number;
  level: number;
  level_name: string;
  xp_to_next_level: number;
  total_xp_for_next_level: number;
  rank: number;
  rank_percentile: number;
  streak_days: number;
  badges_earned: number;
  badges_total: number;
  recent_achievements: Achievement[];
}

export interface Badge {
  id: number;
  name: string;
  description: string;
  icon: string;
  category: string;
  xp_value: number;
  rarity: 'common' | 'uncommon' | 'rare' | 'epic' | 'legendary';
  earned: boolean;
  earned_at?: string | null;
  progress?: {
    current: number;
    target: number;
    percentage: number;
  };
}

export interface Achievement {
  id: number;
  type: string;
  name: string;
  description: string;
  xp_awarded: number;
  earned_at: string;
}

export interface LeaderboardEntry {
  rank: number;
  user: {
    id: number;
    first_name: string;
    last_name: string;
    avatar?: string | null;
  };
  xp: number;
  level: number;
}

export interface Leaderboard {
  period: 'weekly' | 'monthly' | 'all_time';
  entries: LeaderboardEntry[];
  current_user?: {
    rank: number;
    xp: number;
    level: number;
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// Pagination Types
// ─────────────────────────────────────────────────────────────────────────────

export interface PaginationMeta {
  per_page: number;
  has_more?: boolean;
  cursor?: string;
  current_page?: number;
  total_items?: number;
  total_pages?: number;
  has_next_page?: boolean;
  has_previous_page?: boolean;
  next_cursor?: string;
  previous_cursor?: string;
  // Messages API returns conversation details in meta
  conversation?: {
    id: number;
    other_user: {
      id: number;
      name: string;
      avatar?: string | null;
      tagline?: string;
    };
  };
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginationMeta;
}

export interface CursorPaginationParams {
  cursor?: string;
  per_page?: number;
}

export interface OffsetPaginationParams {
  page?: number;
  per_page?: number;
  sort?: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Tenant Types
// ─────────────────────────────────────────────────────────────────────────────

export interface TenantFeatures {
  gamification: boolean;
  groups: boolean;
  events: boolean;
  marketplace: boolean;
  messaging: boolean;
  volunteering: boolean;
  connections: boolean;
  polls: boolean;
  goals: boolean;
  federation: boolean;
  blog: boolean;
  resources: boolean;
  reviews: boolean;
  search: boolean;
  exchange_workflow: boolean;
  direct_messaging: boolean;
  group_exchanges: boolean;
}

export interface TenantModules {
  feed: boolean;
  listings: boolean;
  messages: boolean;
  wallet: boolean;
  notifications: boolean;
  profile: boolean;
  settings: boolean;
  dashboard: boolean;
}

export interface TenantBranding {
  name: string;
  tagline?: string;
  logo?: string;
  favicon?: string;
  primaryColor?: string;
  secondaryColor?: string;
}

export interface TenantConfig {
  id: number;
  name: string;
  slug: string;
  features: Partial<TenantFeatures>;
  modules?: Partial<TenantModules>;
  branding?: Partial<TenantBranding>;
  settings?: Record<string, unknown>;
  categories?: Category[];
  menu_pages?: {
    about?: { title: string; slug: string }[];
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// API Error Types
// ─────────────────────────────────────────────────────────────────────────────

export interface ApiErrorResponse {
  success: false;
  error: {
    code: string;
    message: string;
    details?: {
      field_errors?: Record<string, string[]>;
      retry_after_seconds?: number;
      [key: string]: unknown;
    };
  };
  meta?: {
    timestamp: string;
    request_id?: string;
  };
}

// Common error codes
export type ApiErrorCode =
  | 'VALIDATION_ERROR'
  | 'INVALID_CREDENTIALS'
  | 'INVALID_TOKEN'
  | 'TOKEN_EXPIRED'
  | 'INVALID_REFRESH_TOKEN'
  | 'INVALID_RESET_TOKEN'
  | 'INVALID_TOTP_CODE'
  | 'INVALID_CSRF_TOKEN'
  | 'ACCOUNT_LOCKED'
  | 'ACCOUNT_SUSPENDED'
  | 'EMAIL_NOT_VERIFIED'
  | 'FORBIDDEN'
  | 'NOT_FOUND'
  | 'ALREADY_EXISTS'
  | 'INSUFFICIENT_BALANCE'
  | 'EVENT_FULL'
  | 'ALREADY_CLAIMED'
  | 'INVALID_FILE_TYPE'
  | 'FILE_TOO_LARGE'
  | 'RATE_LIMITED'
  | 'MAINTENANCE_MODE'
  | 'INTERNAL_ERROR'
  | 'NETWORK_ERROR'
  | 'SESSION_EXPIRED'
  | 'AUTH_2FA_REQUIRED'
  | 'AUTH_2FA_TOKEN_EXPIRED'
  | 'AUTH_2FA_MAX_ATTEMPTS'
  | 'AUTH_2FA_INVALID';

// ─────────────────────────────────────────────────────────────────────────────
// File Upload Types
// ─────────────────────────────────────────────────────────────────────────────

export type UploadType = 'avatar' | 'listing' | 'group' | 'message' | 'event';

export interface UploadResponse {
  path: string;
  webp_path?: string;
  url: string;
  webp_url?: string;
  mime_type: string;
  size_bytes: number;
  dimensions?: {
    width: number;
    height: number;
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// Review Types
// ─────────────────────────────────────────────────────────────────────────────

export interface Review {
  id: number;
  reviewer: {
    id: number;
    first_name: string;
    last_name: string;
    avatar?: string | null;
  };
  rating: number;
  comment?: string;
  listing_id?: number;
  listing_title?: string;
  created_at: string;
}

export interface ReviewStats {
  average_rating: number;
  total_reviews: number;
  rating_distribution: {
    1: number;
    2: number;
    3: number;
    4: number;
    5: number;
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// Exchange Types (Broker Controls)
// ─────────────────────────────────────────────────────────────────────────────

export type ExchangeStatus =
  | 'pending_provider'
  | 'pending_broker'
  | 'accepted'
  | 'in_progress'
  | 'pending_confirmation'
  | 'completed'
  | 'disputed'
  | 'cancelled';

export interface Exchange {
  id: number;
  listing_id: number;
  requester_id: number;
  provider_id: number;
  proposed_hours: number;
  status: ExchangeStatus;
  requester_confirmed_at?: string | null;
  requester_confirmed_hours?: number | null;
  provider_confirmed_at?: string | null;
  provider_confirmed_hours?: number | null;
  final_hours?: number | null;
  transaction_id?: number | null;
  broker_id?: number | null;
  broker_notes?: string | null;
  message?: string;
  created_at: string;
  updated_at?: string;

  // Nested relations
  listing?: {
    id: number;
    title: string;
    type: ListingType;
    description?: string;
    hours?: number;
  };
  requester?: {
    id: number;
    name: string;
    first_name?: string;
    last_name?: string;
    avatar?: string | null;
  };
  provider?: {
    id: number;
    name: string;
    first_name?: string;
    last_name?: string;
    avatar?: string | null;
  };

  // Status history for timeline
  status_history?: ExchangeHistoryEntry[];
}

export interface ExchangeHistoryEntry {
  action: string;
  new_status?: string;
  actor_name?: string;
  notes?: string;
  created_at: string;
}

export interface ExchangeConfig {
  exchange_workflow_enabled: boolean;
  direct_messaging_enabled: boolean;
  require_broker_approval: boolean;
  confirmation_deadline_hours: number;
}

export interface ExchangeCreateRequest {
  listing_id: number;
  proposed_hours: number;
  message?: string;
}

export interface ExchangeConfirmRequest {
  hours: number;
}

export interface ExchangeFilters {
  status?: ExchangeStatus | 'active';
  role?: 'requester' | 'provider';
  cursor?: string;
  per_page?: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Federation Types
// ─────────────────────────────────────────────────────────────────────────────

export interface FederationPartner {
  id: number;
  name: string;
  logo?: string | null;
  tagline?: string;
  location?: string;
  country?: string;
  member_count: number;
  federation_level: number;
  federation_level_name?: string;
  permissions?: string[];
  partnership_since?: string;
}

export interface FederatedEvent {
  id: number;
  title: string;
  description: string;
  start_date: string;
  end_date?: string;
  location?: string;
  is_online?: boolean;
  online_url?: string;
  cover_image?: string | null;
  attendees_count: number;
  max_attendees?: number;
  organizer?: {
    id: number;
    name: string;
    avatar?: string | null;
  };
  timebank: {
    id: number;
    name: string;
  };
  created_at?: string;
}

export interface FederatedListing {
  id: number;
  title: string;
  description: string;
  type: 'offer' | 'request';
  category_name?: string;
  image_url?: string | null;
  estimated_hours?: number;
  location?: string;
  author?: {
    id: number;
    name: string;
    avatar?: string | null;
  };
  timebank: {
    id: number;
    name: string;
  };
  created_at?: string;
}

export interface FederatedMember {
  id: number;
  name?: string;
  first_name?: string;
  last_name?: string;
  avatar?: string | null;
  bio?: string;
  skills?: string[];
  location?: string;
  service_reach?: string;
  messaging_enabled?: boolean;
  timebank: {
    id: number;
    name: string;
  };
}

export interface FederatedMessage {
  id: number;
  subject: string;
  body: string;
  direction: 'inbound' | 'outbound';
  status: 'unread' | 'delivered' | 'read';
  read_at?: string | null;
  created_at: string;
  sender: {
    id: number;
    name: string;
    avatar?: string | null;
    tenant_id: number;
    tenant_name: string;
  };
  receiver: {
    id: number;
    name: string;
    avatar?: string | null;
    tenant_id: number;
    tenant_name: string;
  };
  reference_message_id?: number;
}

export interface FederationStatus {
  enabled: boolean;
  tenant_federation_enabled?: boolean;
  partnerships_count?: number;
  federation_optin?: boolean;
}

export interface FederationActivityItem {
  id: number;
  type: 'message_received' | 'message_sent' | 'transaction_received' | 'transaction_sent' | 'partnership_approved' | 'member_joined';
  title: string;
  description: string;
  created_at: string;
  actor?: {
    id?: number;
    name: string;
    avatar?: string | null;
    tenant_name?: string;
  };
}

export interface FederationSettings {
  profile_visible_federated?: boolean;
  appear_in_federated_search?: boolean;
  show_skills_federated?: boolean;
  show_location_federated?: boolean;
  show_reviews_federated?: boolean;
  messaging_enabled_federated?: boolean;
  transactions_enabled_federated?: boolean;
  email_notifications?: boolean;
  service_reach: 'local_only' | 'remote_ok' | 'travel_ok';
  travel_radius_km?: number;
  federation_optin?: boolean;
}
