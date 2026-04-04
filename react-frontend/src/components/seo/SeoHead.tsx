// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Helmet } from 'react-helmet-async';
import { useLocation } from 'react-router-dom';
import { useTenant } from '@/contexts';

/**
 * SeoHead — Global SEO tags rendered on every page via Layout.
 *
 * Renders:
 *  - Webmaster verification meta tags (Google, Bing)
 *  - Organization JSON-LD structured data
 *  - BreadcrumbList JSON-LD (non-homepage)
 *  - x-default hreflang (clean URL, no language query params)
 *
 * NOTE: This is a client-side SPA with i18next for localisation.
 * Language switching happens in the browser — there are no separate
 * server-side language URLs. Therefore we emit only `x-default`
 * hreflang pointing to the clean canonical URL, not per-language
 * `?lng=` variants (which created 3,997 hreflang/HTML-lang mismatches
 * in Ahrefs because the rendered HTML lang didn't match the hreflang).
 *
 * The `<html lang>` attribute is synced dynamically by i18n.ts via
 * `i18n.on('languageChanged')` — no Helmet intervention needed.
 */
export function SeoHead() {
  const { branding, tenant } = useTenant();
  const location = useLocation();

  const siteName = branding.name || 'NEXUS';
  const siteUrl = typeof window !== 'undefined' ? window.location.origin : '';
  // Clean canonical: pathname only, no query params or hash
  const cleanPath = location.pathname === '/' ? '/' : location.pathname.replace(/\/+$/, '');
  const canonicalPath = `${siteUrl}${cleanPath}`;

  // Build path segments for BreadcrumbList JSON-LD
  const pathSegments = location.pathname.split('/').filter(Boolean);

  const breadcrumbItems = [
    { '@type': 'ListItem' as const, position: 1, name: 'Home', item: siteUrl || '/' },
    ...pathSegments.map((segment, index) => ({
      '@type': 'ListItem' as const,
      position: index + 2,
      name: segment.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
      item: `${siteUrl}/${pathSegments.slice(0, index + 1).join('/')}`,
    })),
  ];

  const breadcrumbSchema = {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: breadcrumbItems,
  };

  const settings = tenant?.settings as Record<string, unknown> | undefined;

  // Build Organization JSON-LD
  const orgSchema = {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: siteName,
    url: siteUrl,
    ...(branding.logo ? { logo: branding.logo } : {}),
    ...(branding.tagline ? { description: branding.tagline } : {}),
  };

  const googleVerification = typeof settings?.seo_google_verification === 'string'
    ? settings.seo_google_verification
    : '';
  const bingVerification = typeof settings?.seo_bing_verification === 'string'
    ? settings.seo_bing_verification
    : '';

  return (
    <Helmet>
      {/* Google Search Console verification */}
      {googleVerification && (
        <meta name="google-site-verification" content={googleVerification} />
      )}

      {/* Bing Webmaster Tools verification */}
      {bingVerification && (
        <meta name="msvalidate.01" content={bingVerification} />
      )}

      {/* Organization JSON-LD */}
      <script type="application/ld+json">
        {JSON.stringify(orgSchema)}
      </script>

      {/* x-default hreflang — clean canonical URL, no ?lng= variants.
          This is the only hreflang needed for a client-side i18n SPA. */}
      <link rel="alternate" hrefLang="x-default" href={canonicalPath} />

      {/* BreadcrumbList JSON-LD (only on non-homepage routes) */}
      {pathSegments.length > 0 && (
        <script type="application/ld+json">
          {JSON.stringify(breadcrumbSchema)}
        </script>
      )}
    </Helmet>
  );
}

export default SeoHead;
