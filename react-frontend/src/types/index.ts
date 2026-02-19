// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * NEXUS Types - Main exports
 *
 * For API contracts, import from './api'
 * For frontend-friendly types with computed properties, import from './frontend'
 */

// Export all API types as the primary source of truth
export * from './api';

// Export frontend transform functions and types that don't conflict
export {
  type FrontendUser,
  type FrontendListing,
  transformUser,
  transformListing,
} from './frontend';
