// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PageMeta — Per-page SEO meta tags via react-helmet-async.
 *
 * Sets title, description, canonical URL (query-param-stripped), Open Graph,
 * Twitter Card, and robots directives. Uses tenant branding for sensible
 * defaults so every page has complete meta tags even without explicit props.
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
  const { branding } = useTenant();
  const location = useLocation();

  const siteName = branding.name || 'NEXUS';
  const fullTitle = title ? `${title} | ${siteName}` : siteName;
  const metaDescription =
    description ||
    branding.tagline ||
    'Community timebanking platform — exchange skills, build connections, strengthen your community.';

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
      {keywords && <meta name="keywords" content={keywords} />}

      {/* Canonical URL — always clean, no query params */}
      {canonicalUrl && <link rel="canonical" href={canonicalUrl} />}

      {/* Robots */}
      {noIndex && <meta name="robots" content="noindex, nofollow" />}

      {/* Open Graph / Facebook */}
      <meta property="og:type" content={type} />
      <meta property="og:site_name" content={siteName} />
      <meta property="og:title" content={fullTitle} />
      <meta property="og:description" content={metaDescription} />
      {canonicalUrl && <meta property="og:url" content={canonicalUrl} />}
      <meta property="og:image" content={ogImage} />
      <meta property="og:image:width" content="1200" />
      <meta property="og:image:height" content="630" />

      {/* Article-specific OG tags */}
      {type === 'article' && publishedTime && (
        <meta property="article:published_time" content={publishedTime} />
      )}
      {type === 'article' && modifiedTime && (
        <meta property="article:modified_time" content={modifiedTime} />
      )}

      {/* Twitter Card */}
      <meta name="twitter:card" content="summary_large_image" />
      <meta name="twitter:title" content={fullTitle} />
      <meta name="twitter:description" content={metaDescription} />
      <meta name="twitter:image" content={ogImage} />
    </Helmet>
  );
}

export default PageMeta;
