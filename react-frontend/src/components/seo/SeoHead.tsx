// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Helmet } from 'react-helmet-async';
import { useTenant } from '@/contexts';

/**
 * SeoHead -- Global SEO tags rendered on every page.
 *
 * Renders: verification meta tags, Organization JSON-LD schema.
 * Place this in Layout.tsx so it runs on every page load.
 */
export function SeoHead() {
  const { branding, tenant } = useTenant();

  const siteName = branding.name || 'NEXUS';
  const siteUrl = typeof window !== 'undefined' ? window.location.origin : '';

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
    </Helmet>
  );
}

export default SeoHead;
