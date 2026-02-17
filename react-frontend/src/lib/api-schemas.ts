/**
 * NEXUS API Response Schemas (Zod)
 *
 * Runtime validation schemas for critical API responses.
 * These schemas validate the ESSENTIAL fields only — extra fields are allowed
 * via .passthrough() so the API can evolve without breaking validation.
 *
 * Used ONLY in development mode for diagnostics. In production these are
 * never imported (tree-shaken away by the dev-mode guard in validateResponse).
 */

import { z } from 'zod';

// ─────────────────────────────────────────────────────────────────────────────
// Base API Response Envelope
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Validates the base API response wrapper returned by api.ts after unwrapping.
 * After api.ts processes the raw response, it returns:
 *   { success, data?, error?, message?, code?, meta? }
 */
export const apiResponseSchema = z.object({
  success: z.boolean(),
  data: z.unknown().optional(),
  error: z.string().optional(),
  message: z.string().optional(),
  code: z.string().optional(),
  meta: z.object({
    per_page: z.number(),
    has_more: z.boolean().optional(),
    cursor: z.string().optional(),
    next_cursor: z.string().optional(),
  }).passthrough().optional(),
}).passthrough();

// ─────────────────────────────────────────────────────────────────────────────
// User Schema
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Validates the essential fields of a User object.
 * Many fields are optional because the backend returns different subsets
 * depending on the endpoint (e.g., /me vs /users/:id vs login response).
 */
export const userSchema = z.object({
  id: z.number(),
  email: z.string().optional(),
  first_name: z.string().optional(),
  last_name: z.string().optional(),
  name: z.string().optional(),
  username: z.string().optional(),
  avatar: z.string().nullable().optional(),
  avatar_url: z.string().nullable().optional(),
  bio: z.string().optional(),
  role: z.enum(['member', 'admin', 'moderator', 'tenant_admin', 'super_admin']).optional(),
  status: z.enum(['active', 'inactive', 'suspended', 'pending']).optional(),
  tenant_id: z.number().optional(),
  tenant_slug: z.string().optional(),
  balance: z.number().optional(),
  level: z.number().optional(),
  onboarding_completed: z.boolean().optional(),
  created_at: z.string().optional(),
}).passthrough();

// ─────────────────────────────────────────────────────────────────────────────
// Login Response Schema
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Validates a successful login response (after api.ts unwraps `data`).
 * The login endpoint returns user + tokens at the top level (not inside `data`),
 * so api.ts returns the full response as `data` because `'data' in response` is false.
 */
export const loginSuccessResponseSchema = z.object({
  success: z.literal(true).optional(),
  user: userSchema,
  access_token: z.string().optional(),
  refresh_token: z.string().optional(),
  token: z.string().optional(),
  expires_in: z.number().optional(),
  token_type: z.string().optional(),
}).passthrough();

/**
 * Validates a 2FA-required response.
 */
export const twoFactorRequiredSchema = z.object({
  requires_2fa: z.literal(true),
  two_factor_token: z.string(),
  methods: z.array(z.string()),
}).passthrough();

/**
 * Validates either a success or 2FA response from login.
 */
export const loginResponseSchema = z.union([
  loginSuccessResponseSchema,
  twoFactorRequiredSchema,
]);

// ─────────────────────────────────────────────────────────────────────────────
// Paginated Response Schema
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Validates the pagination meta object.
 */
export const paginationMetaSchema = z.object({
  per_page: z.number(),
  has_more: z.boolean().optional(),
  cursor: z.string().nullable().optional(),
  next_cursor: z.string().nullable().optional(),
  current_page: z.number().optional(),
  total_items: z.number().optional(),
  total_pages: z.number().optional(),
  total: z.number().optional(),
}).passthrough();

/**
 * Creates a paginated response schema for a given item schema.
 * Validates that `data` is an array and `meta` has pagination fields.
 */
export function paginatedResponseSchema<T extends z.ZodTypeAny>(itemSchema: T) {
  return z.object({
    data: z.array(itemSchema),
    meta: paginationMetaSchema,
  }).passthrough();
}

// ─────────────────────────────────────────────────────────────────────────────
// Listing Schema
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Validates the essential fields of a Listing object.
 */
export const listingSchema = z.object({
  id: z.number(),
  title: z.string(),
  description: z.string(),
  type: z.enum(['offer', 'request']),
  user_id: z.number(),
  category_id: z.number().nullable().optional(),
  status: z.enum(['active', 'paused', 'completed', 'expired', 'deleted']).nullable().optional(),
  created_at: z.string(),
  updated_at: z.string(),
  // Optional nested author
  user: z.object({
    id: z.number(),
    first_name: z.string().optional(),
    last_name: z.string().optional(),
    name: z.string().optional(),
  }).passthrough().optional(),
}).passthrough();

// ─────────────────────────────────────────────────────────────────────────────
// Tenant Bootstrap Schema
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Validates the tenant bootstrap response.
 * This is the most critical schema — if bootstrap fails, the app cannot function.
 */
export const tenantBootstrapSchema = z.object({
  id: z.number(),
  name: z.string(),
  slug: z.string(),
  tagline: z.string().optional(),
  features: z.record(z.string(), z.boolean()).optional(),
  modules: z.record(z.string(), z.boolean()).optional(),
  branding: z.object({
    name: z.string().optional(),
    tagline: z.string().optional(),
    logo: z.string().nullable().optional(),
    logo_url: z.string().nullable().optional(),
    favicon: z.string().nullable().optional(),
    favicon_url: z.string().nullable().optional(),
    primaryColor: z.string().optional(),
    primary_color: z.string().optional(),
  }).passthrough().optional(),
  contact: z.object({
    email: z.string().optional(),
    phone: z.string().optional(),
    address: z.string().optional(),
  }).passthrough().optional(),
  categories: z.array(z.object({
    id: z.number(),
    name: z.string(),
    slug: z.string(),
  }).passthrough()).optional(),
}).passthrough();

// ─────────────────────────────────────────────────────────────────────────────
// Wallet Schemas
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Validates the wallet balance response.
 */
export const walletBalanceSchema = z.object({
  balance: z.number(),
  total_earned: z.number(),
  total_spent: z.number(),
  currency: z.string().optional(),
}).passthrough();

/**
 * Validates a transaction object.
 */
export const transactionSchema = z.object({
  id: z.number(),
  type: z.enum(['credit', 'debit']),
  amount: z.number(),
  status: z.enum(['pending', 'completed', 'cancelled', 'disputed', 'refunded']),
  description: z.string(),
  created_at: z.string(),
}).passthrough();
