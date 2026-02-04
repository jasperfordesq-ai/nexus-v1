/**
 * Frontend-specific type extensions
 * These types extend API types with computed properties for UI convenience
 */

import type { User as ApiUser, Listing as ApiListing } from './api';

// ─────────────────────────────────────────────────────────────────────────────
// Extended User type with computed name
// ─────────────────────────────────────────────────────────────────────────────

export interface FrontendUser extends ApiUser {
  /** Computed full name for display */
  name: string;
}

/**
 * Transform API user to frontend user with computed name
 */
export function transformUser(user: ApiUser): FrontendUser {
  return {
    ...user,
    name: user.name || `${user.first_name || ''} ${user.last_name || ''}`.trim() || 'Unknown',
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// Extended Listing type with computed user name
// ─────────────────────────────────────────────────────────────────────────────

export interface FrontendListing extends ApiListing {
  /** Alias for estimated_hours */
  hours_estimate?: number;
}

export function transformListing(listing: ApiListing): FrontendListing {
  return {
    ...listing,
    hours_estimate: listing.estimated_hours,
  };
}
