/**
 * Tenant Routing Utilities
 *
 * Shared constants and helpers for tenant slug resolution from URLs.
 * These lists are derived from TRS-001 Section 4 (Reserved Words).
 *
 * @see docs/TRS-001-TENANT-RESOLUTION-SPEC.md
 */

/**
 * Reserved subdomains — TRS-001 Section 4 (27 entries).
 * Used for R2 (subdomain resolution). If the subdomain matches, it is NOT a tenant slug.
 */
export const RESERVED_SUBDOMAINS = new Set([
  'app', 'api', 'www', 'admin', 'mail', 'smtp', 'imap', 'pop', 'ftp',
  'ns1', 'ns2', 'ns3', 'ns4', 'cdn', 'static', 'assets', 'status',
  'help', 'support', 'docs', 'blog', 'staging', 'dev', 'test', 'demo',
  'beta', 'alpha', 'preview',
]);

/**
 * Reserved top-level path segments — TRS-001 Section 4 (42 entries).
 * Used for R3 (path-based resolution on app.project-nexus.ie).
 * If the first path segment matches, it is a React route, NOT a tenant slug.
 */
export const RESERVED_PATHS = new Set([
  'login', 'register', 'password', 'logout', 'dashboard', 'listings',
  'events', 'groups', 'messages', 'notifications', 'wallet', 'feed',
  'search', 'members', 'profile', 'settings', 'exchanges', 'achievements',
  'leaderboard', 'goals', 'volunteering', 'blog', 'resources',
  'organisations', 'federation', 'onboarding', 'group-exchanges',
  'help', 'contact', 'about', 'faq', 'legal', 'terms',
  'privacy', 'accessibility', 'cookies', 'admin', 'admin-legacy', 'super-admin', 'api', 'assets',
  'uploads', 'classic', 'health', 'favicon.ico', 'robots.txt',
  'sitemap.xml', 'manifest.json', 'service-worker.js', '.well-known',
]);

/**
 * Platform domain used for R3 path-based resolution.
 * Path-based tenant slugs only apply on this domain (and localhost for dev).
 */
const PLATFORM_DOMAIN = 'project-nexus.ie';

/**
 * Detect tenant slug from the current URL.
 *
 * Implements TRS-001 resolution rules:
 * - R1: Custom domain → not handled here (backend resolves via Host header)
 * - R2: Subdomain of project-nexus.ie → extract subdomain as slug
 * - R3: First path segment on app.project-nexus.ie → extract as slug if not reserved
 * - R4: No tenant → return null (show chooser)
 *
 * @returns Object with slug (if detected) and source ('subdomain' | 'path' | null)
 */
export function detectTenantFromUrl(): { slug: string | null; source: 'subdomain' | 'path' | null } {
  const hostname = window.location.hostname;
  const pathname = window.location.pathname;

  // Development: localhost/127.0.0.1 — only path-based resolution
  if (hostname === 'localhost' || hostname === '127.0.0.1') {
    const slug = extractSlugFromPath(pathname);
    return { slug, source: slug ? 'path' : null };
  }

  // R2: Subdomain resolution — {sub}.project-nexus.ie
  if (hostname.endsWith('.' + PLATFORM_DOMAIN)) {
    const subdomain = hostname.slice(0, hostname.length - PLATFORM_DOMAIN.length - 1);
    // Must be a single-level subdomain (no dots)
    if (subdomain && !subdomain.includes('.') && !RESERVED_SUBDOMAINS.has(subdomain.toLowerCase())) {
      return { slug: subdomain.toLowerCase(), source: 'subdomain' };
    }

    // Reserved subdomain (app, api, etc.) — fall through to path-based
    if (subdomain === 'app') {
      const slug = extractSlugFromPath(pathname);
      return { slug, source: slug ? 'path' : null };
    }

    // Other reserved subdomains — no tenant resolution here
    return { slug: null, source: null };
  }

  // R1: Custom domain — not project-nexus.ie
  // The backend resolves tenant from Host header via bootstrap API.
  // We don't extract slug from URL; the bootstrap call handles it.
  // Return null slug but the bootstrap API will resolve tenant from domain.
  return { slug: null, source: null };
}

/**
 * Extract tenant slug from the first path segment.
 * Returns null if the segment is a reserved path or empty.
 */
function extractSlugFromPath(pathname: string): string | null {
  const segments = pathname.split('/').filter(Boolean);
  const firstSegment = segments[0]?.toLowerCase();

  if (!firstSegment) return null;
  if (RESERVED_PATHS.has(firstSegment)) return null;

  return firstSegment;
}

/**
 * Build a path with the tenant slug prefix.
 * Used by components to generate tenant-scoped links.
 *
 * @param path - The page path (e.g., "/dashboard", "/listings/42")
 * @param tenantSlug - The current tenant slug (from context), or null
 * @returns The full path with slug prefix if applicable
 */
export function tenantPath(path: string, tenantSlug: string | null | undefined): string {
  if (!tenantSlug) return path;
  // Ensure path starts with /
  const normalizedPath = path.startsWith('/') ? path : '/' + path;
  return '/' + tenantSlug + normalizedPath;
}

/**
 * Strip the tenant slug prefix from a pathname.
 * Used when we need the "real" page path without the slug.
 *
 * @param pathname - Full pathname (e.g., "/hour-timebank/dashboard")
 * @param tenantSlug - The tenant slug to strip
 * @returns The path without the slug prefix (e.g., "/dashboard")
 */
export function stripTenantSlug(pathname: string, tenantSlug: string): string {
  const prefix = '/' + tenantSlug;
  if (pathname.toLowerCase().startsWith(prefix.toLowerCase())) {
    const rest = pathname.slice(prefix.length);
    return rest || '/';
  }
  return pathname;
}
