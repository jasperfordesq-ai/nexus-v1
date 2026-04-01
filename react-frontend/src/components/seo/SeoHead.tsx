// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Helmet } from 'react-helmet-async';
import { useLocation } from 'react-router-dom';
import { useTenant } from '@/contexts';

/**
 * SeoHead -- Global SEO tags rendered on every page.
 *
 * Renders: verification meta tags, Organization JSON-LD schema,
 * hreflang alternate links for multi-language SEO, and BreadcrumbList JSON-LD.
 * Place this in Layout.tsx so it runs on every page load.
 */
const SUPPORTED_LANGUAGES = ['en', 'ga', 'de', 'fr', 'it', 'pt', 'es'] as const;

export function SeoHead() {
  const { branding, tenant } = useTenant();
  const location = useLocation();

  const siteName = branding.name || 'NEXUS';
  const siteUrl = typeof window !== 'undefined' ? window.location.origin : '';
  const currentUrl = `${siteUrl}${location.pathname}`;

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

      {/* hreflang alternate links for multi-language SEO */}
      {SUPPORTED_LANGUAGES.map(lang => (
        <link key={lang} rel="alternate" hrefLang={lang} href={`${currentUrl}?lng=${lang}`} />
      ))}
      <link rel="alternate" hrefLang="x-default" href={currentUrl} />

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
