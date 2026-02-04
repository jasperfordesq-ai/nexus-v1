/**
 * API Types - Matches PHP backend response shapes
 */

// ===========================================
// TENANT BOOTSTRAP
// ===========================================

export interface TenantBranding {
  logo_url?: string;
  favicon_url?: string;
  primary_color?: string;
  og_image_url?: string;
}

export interface TenantFeatures {
  listings: boolean;
  events: boolean;
  groups: boolean;
  wallet: boolean;
  messages: boolean;
  feed: boolean;
  notifications: boolean;
  search: boolean;
  connections: boolean;
  reviews: boolean;
  gamification: boolean;
  volunteering: boolean;
  federation: boolean;
  blog: boolean;
  resources: boolean;
  goals: boolean;
  polls: boolean;
}

export interface TenantSeo {
  meta_title?: string;
  meta_description?: string;
  h1_headline?: string;
  hero_intro?: string;
}

export interface TenantConfig {
  footer_text?: string;
  time_unit?: string;
  time_unit_plural?: string;
}

export interface TenantContact {
  email?: string;
  phone?: string;
  address?: string;
  location?: string;
}

export interface TenantSocial {
  facebook?: string;
  twitter?: string;
  instagram?: string;
  linkedin?: string;
  youtube?: string;
}

export interface TenantBootstrap {
  id: number;
  name: string;
  slug: string;
  domain?: string;
  tagline?: string;
  default_layout: 'modern' | 'civicone';
  branding: TenantBranding;
  features: TenantFeatures;
  seo?: TenantSeo;
  config?: TenantConfig;
  contact?: TenantContact;
  social?: TenantSocial;
}

// ===========================================
// AUTH
// ===========================================

export interface User {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  avatar_url?: string;
  tenant_id: number;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginSuccessResponse {
  success: true;
  user: User;
  access_token: string;
  refresh_token: string;
  token_type: 'Bearer';
  expires_in: number;
  refresh_expires_in: number;
  is_mobile: boolean;
  config?: {
    modules?: Record<string, boolean>;
  };
}

export interface TwoFactorRequiredResponse {
  success: false;
  requires_2fa: true;
  two_factor_token: string;
  methods: string[];
  code: string;
  message: string;
  user: {
    id: number;
    first_name: string;
    email_masked: string;
  };
}

export type LoginResponse = LoginSuccessResponse | TwoFactorRequiredResponse;

export interface RefreshTokenRequest {
  refresh_token: string;
}

export interface RefreshTokenResponse {
  success: true;
  access_token: string;
  expires_in: number;
}

// ===========================================
// TWO-FACTOR AUTHENTICATION
// ===========================================

export interface TwoFactorVerifyRequest {
  two_factor_token: string;
  code: string;
  use_backup_code?: boolean;
  trust_device?: boolean;
}

export interface TwoFactorVerifyResponse {
  success: true;
  user: User;
  access_token: string;
  refresh_token: string;
  token_type: 'Bearer';
  expires_in: number;
  refresh_expires_in: number;
  is_mobile: boolean;
  codes_remaining?: number;
}

// ===========================================
// LISTINGS
// ===========================================

export interface ListingUser {
  id: number;
  first_name: string;
  last_name?: string;
  avatar_url?: string;
}

export interface Listing {
  id: number;
  title: string;
  description?: string;
  type: 'offer' | 'request';
  category_id?: number;
  category_name?: string;
  category_color?: string;
  time_credits?: number;
  status?: string;
  user_id: number;
  // Author info (from joined user table)
  author_name?: string;
  author_avatar?: string;
  // Legacy user object format (for backward compatibility)
  user?: ListingUser;
  image_url?: string;
  location?: string;
  latitude?: number;
  longitude?: number;
  federated_visibility?: string;
  created_at: string;
  updated_at?: string;
}

/**
 * Listing detail includes additional fields from getById
 */
export interface ListingDetail extends Listing {
  author_email?: string;
  attributes?: ListingAttribute[];
  likes_count?: number;
  comments_count?: number;
}

export interface ListingAttribute {
  id: number;
  name: string;
  slug: string;
  value: string;
}

export interface PaginationMeta {
  per_page: number;
  has_more: boolean;
  cursor?: string;
  base_url?: string;
}

export interface ListingsResponse {
  data: Listing[];
  meta: PaginationMeta;
}

export interface ListingDetailResponse {
  data: ListingDetail;
}

// ===========================================
// API ERRORS
// ===========================================

export interface ApiError {
  code: string;
  message: string;
  field?: string;
}

export interface ApiErrorResponse {
  errors: ApiError[];
}

// Legacy error format (for auth endpoints)
export interface LegacyErrorResponse {
  success: false;
  error: string;
  code?: string;
}
