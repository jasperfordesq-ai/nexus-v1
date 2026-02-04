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
