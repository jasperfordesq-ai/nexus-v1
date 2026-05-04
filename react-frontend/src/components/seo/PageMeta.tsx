// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PageMeta — Per-page SEO meta tags via react-helmet-async.
 *
 * Sets title, description, canonical URL (query-param-stripped), Open Graph,
 * Twitter Card, and robots directives.
 *
 * Reads admin-configured SEO settings from the tenant context:
 *  - seo_title_suffix → custom title suffix (overrides branding.name)
 *  - seo_meta_description → global fallback description
 *  - seo_meta_keywords → global fallback keywords
 *  - seo_og_image_url → default OG image (via branding.og_image_url)
 *  - seo_canonical_urls → whether to emit canonical link tags
 *  - seo_open_graph → whether to emit OG tags
 *  - seo_twitter_cards → whether to emit Twitter Card tags
 */

import { Helmet } from 'react-helmet-async';
import { useLocation } from 'react-router-dom';
import { useTenant } from '@/contexts';

interface PageMetaProps {
  /** Page title (appears before the site name suffix). */
  title?: string;
  /** Meta description — aim for 150–160 characters. */
  description?: string;
  /** Comma-separated meta keywords. */
  keywords?: string;
  /** Open Graph / Twitter Card image URL (absolute). Falls back to tenant og_image_url or logo. */
  image?: string;
  /** Canonical URL override. Defaults to current pathname (query params stripped). */
  url?: string;
  /** Open Graph type. */
  type?: 'website' | 'article' | 'profile';
  /** Set true for pages that should not be indexed (auth, dashboard, settings, etc.). */
  noIndex?: boolean;
  /** Published date for article type (ISO 8601). */
  publishedTime?: string;
  /** Modified date for article type (ISO 8601). */
  modifiedTime?: string;
}

/**
 * Strip query parameters and hash from a URL to produce a clean canonical.
 * Search engines treat ?page=2, ?lng=fr, ?q=test as separate pages — we
 * canonicalize them all back to the base pathname URL.
 */
function buildCanonicalUrl(origin: string, pathname: string): string {
  // Ensure no trailing slash except for root
  const cleanPath = pathname === '/' ? '/' : pathname.replace(/\/+$/, '');
  return `${origin}${cleanPath}`;
}

/** Read a string setting from tenant.settings safely. */
function getSetting(settings: Record<string, unknown> | undefined, key: string): string {
  if (!settings || typeof settings[key] !== 'string') return '';
  return settings[key] as string;
}

/** Read a boolean setting from tenant.settings safely (defaults to true). */
function getBoolSetting(settings: Record<string, unknown> | undefined, key: string): boolean {
  if (!settings || settings[key] === undefined) return true;
  const val = settings[key];
  if (typeof val === 'boolean') return val;
  if (val === '1' || val === 'true') return true;
  if (val === '0' || val === 'false') return false;
  return true;
}

export function PageMeta({
  title,
  description,
  keywords,
  image,
  url,
  type = 'website',
  noIndex = false,
  publishedTime,
  modifiedTime,
}: PageMetaProps) {
  const { branding: tenantBranding, tenant } = useTenant();
  const branding = tenantBranding ?? { name: 'NEXUS', tagline: '', og_image_url: '', logo: '' };
  const location = useLocation();

  const settings = tenant?.settings as Record<string, unknown> | undefined;

  // Admin-configured SEO settings (from tenant_settings table via bootstrap)
  const adminTitleSuffix = getSetting(settings, 'seo_title_suffix');
  const adminDescription = getSetting(settings, 'seo_meta_description');
  const adminKeywords = getSetting(settings, 'seo_meta_keywords');
  const enableCanonical = getBoolSetting(settings, 'seo_canonical_urls');
  const enableOpenGraph = getBoolSetting(settings, 'seo_open_graph');
  const enableTwitterCards = getBoolSetting(settings, 'seo_twitter_cards');

  // Site name: admin title suffix > tenant meta title > branding name > NEXUS
  const seoTitle = (tenant?.seo?.meta_title || '').trim();
  const siteName = adminTitleSuffix
    ? adminTitleSuffix.replace(/^\s*\|\s*/, '') // Strip leading " | " if present
    : seoTitle || branding.name || 'NEXUS';
  const titleSuffix = adminTitleSuffix || ` | ${siteName}`;
  const fullTitle = title ? `${title}${titleSuffix}` : siteName;

  // Description: explicit > admin global > tenant seo description > branding tagline > platform default
  const metaDescription =
    description ||
    adminDescription ||
    (tenant?.seo?.meta_description || '').trim() ||
    branding.tagline ||
    'Community timebanking platform — exchange skills, build connections, strengthen your community.';

  // Keywords: explicit > admin global keywords
  const metaKeywords = keywords || adminKeywords || undefined;

  // Canonical: always clean pathname, never include query params or hash
  const origin = typeof window !== 'undefined' ? window.location.origin : '';
  const canonicalUrl = url || buildCanonicalUrl(origin, location.pathname);

  // OG image fallback chain: explicit prop → tenant og_image_url → tenant logo → platform default
  const ogImage =
    image ||
    branding.og_image_url ||
    branding.logo ||
    `${origin}/og-default.svg`;

  return (
    <Helmet>
      {/* Primary Meta Tags */}
      <title>{fullTitle}</title>
      <meta name="description" content={metaDescription} />
      {metaKeywords && <meta name="keywords" content={metaKeywords} />}

      {/* Canonical URL — always clean, no query params */}
      {enableCanonical && canonicalUrl && <link rel="canonical" href={canonicalUrl} />}

      {/* Robots */}
      {noIndex && <meta name="robots" content="noindex, nofollow" />}

      {/* Open Graph / Facebook (respects admin toggle) */}
      {enableOpenGraph && (
        <>
          <meta property="og:type" content={type} />
          <meta property="og:site_name" content={siteName} />
          <meta property="og:title" content={fullTitle} />
          <meta property="og:description" content={metaDescription} />
          {canonicalUrl && <meta property="og:url" content={canonicalUrl} />}
          <meta property="og:image" content={ogImage} />
          <meta property="og:image:width" content="1200" />
          <meta property="og:image:height" content="630" />
        </>
      )}

      {/* Article-specific OG tags */}
      {enableOpenGraph && type === 'article' && publishedTime && (
        <meta property="article:published_time" content={publishedTime} />
      )}
      {enableOpenGraph && type === 'article' && modifiedTime && (
        <meta property="article:modified_time" content={modifiedTime} />
      )}

      {/* Twitter Card (respects admin toggle) */}
      {enableTwitterCards && (
        <>
          <meta name="twitter:card" content="summary_large_image" />
          <meta name="twitter:title" content={fullTitle} />
          <meta name="twitter:description" content={metaDescription} />
          <meta name="twitter:image" content={ogImage} />
        </>
      )}
    </Helmet>
  );
}

export default PageMeta;
