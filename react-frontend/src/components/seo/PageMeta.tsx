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

import { useEffect, useMemo } from 'react';
import { Helmet } from 'react-helmet-async';
import { useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';

// Map our short language codes to OG locale codes (BCP 47 with region).
// Google and Facebook expect e.g. en_GB / fr_FR, not the bare lang code.
// Picked plausible defaults for each tenant region; if a tenant later
// needs a different region (e.g. pt_BR vs pt_PT), this can be moved into
// tenant settings.
const OG_LOCALES: Record<string, string> = {
  en: 'en_GB',
  ga: 'ga_IE',
  de: 'de_DE',
  fr: 'fr_FR',
  it: 'it_IT',
  pt: 'pt_PT',
  es: 'es_ES',
  nl: 'nl_NL',
  pl: 'pl_PL',
  ja: 'ja_JP',
  ar: 'ar_001', // Modern Standard Arabic, no region
};

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
  const { i18n } = useTranslation();

  // Active language → OG locale (BCP 47). Falls back to en_GB if unknown.
  const activeLang = (i18n.language || 'en').split('-')[0] ?? 'en';
  const ogLocale = OG_LOCALES[activeLang] || OG_LOCALES.en;
  const ogLocaleAlternates = useMemo(
    () => Object.entries(OG_LOCALES)
      .filter(([code]) => code !== activeLang)
      .map(([, locale]) => locale),
    [activeLang]
  );

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

  // OG image fallback chain: explicit prop -> tenant og_image_url -> tenant logo -> platform default
  const richCardImage = image || branding.og_image_url || branding.logo;
  const ogImage = richCardImage || `${origin}/og-default.svg`;
  const twitterCard = richCardImage ? 'summary_large_image' : 'summary';
  const ogLocaleAlternatesKey = ogLocaleAlternates.join('|');

  useEffect(() => {
    const pageMetaAttr = 'data-nexus-page-meta';

    const setMeta = (selector: string, attrs: Record<string, string>) => {
      let element = document.head.querySelector<HTMLMetaElement>(selector);
      if (!element) {
        element = document.createElement('meta');
        document.head.appendChild(element);
      }

      element.setAttribute(pageMetaAttr, 'true');
      Object.entries(attrs).forEach(([key, value]) => {
        element.setAttribute(key, value);
      });
    };

    const setLink = (selector: string, attrs: Record<string, string>) => {
      let element = document.head.querySelector<HTMLLinkElement>(selector);
      if (!element) {
        element = document.createElement('link');
        document.head.appendChild(element);
      }

      element.setAttribute(pageMetaAttr, 'true');
      Object.entries(attrs).forEach(([key, value]) => {
        element.setAttribute(key, value);
      });
    };

    const removeOwned = (selector: string) => {
      document.head.querySelectorAll(`${selector}[${pageMetaAttr}="true"]`).forEach((element) => {
        element.remove();
      });
    };

    document.title = fullTitle;
    setMeta('meta[name="description"]', { name: 'description', content: metaDescription });

    if (metaKeywords) {
      setMeta('meta[name="keywords"]', { name: 'keywords', content: metaKeywords });
    } else {
      removeOwned('meta[name="keywords"]');
    }

    if (enableCanonical && canonicalUrl) {
      setLink('link[rel="canonical"]', { rel: 'canonical', href: canonicalUrl });
    } else {
      removeOwned('link[rel="canonical"]');
    }

    if (noIndex) {
      setMeta('meta[name="robots"]', { name: 'robots', content: 'noindex, nofollow' });
    } else {
      removeOwned('meta[name="robots"]');
    }

    if (enableOpenGraph) {
      setMeta('meta[property="og:type"]', { property: 'og:type', content: type });
      setMeta('meta[property="og:site_name"]', { property: 'og:site_name', content: siteName });
      setMeta('meta[property="og:title"]', { property: 'og:title', content: fullTitle });
      setMeta('meta[property="og:description"]', { property: 'og:description', content: metaDescription });
      if (canonicalUrl) {
        setMeta('meta[property="og:url"]', { property: 'og:url', content: canonicalUrl });
      }
      setMeta('meta[property="og:image"]', { property: 'og:image', content: ogImage });
      setMeta('meta[property="og:image:width"]', { property: 'og:image:width', content: '1200' });
      setMeta('meta[property="og:image:height"]', { property: 'og:image:height', content: '630' });
      setMeta('meta[property="og:locale"]', { property: 'og:locale', content: ogLocale });
      removeOwned('meta[property="og:locale:alternate"]');
      ogLocaleAlternates.forEach((locale) => {
        const element = document.createElement('meta');
        element.setAttribute(pageMetaAttr, 'true');
        element.setAttribute('property', 'og:locale:alternate');
        element.setAttribute('content', locale);
        document.head.appendChild(element);
      });
      if (type === 'article' && publishedTime) {
        setMeta('meta[property="article:published_time"]', {
          property: 'article:published_time',
          content: publishedTime,
        });
      } else {
        removeOwned('meta[property="article:published_time"]');
      }
      if (type === 'article' && modifiedTime) {
        setMeta('meta[property="article:modified_time"]', {
          property: 'article:modified_time',
          content: modifiedTime,
        });
      } else {
        removeOwned('meta[property="article:modified_time"]');
      }
    } else {
      removeOwned('meta[property^="og:"]');
      removeOwned('meta[property^="article:"]');
    }

    if (enableTwitterCards) {
      setMeta('meta[name="twitter:card"]', { name: 'twitter:card', content: twitterCard });
      setMeta('meta[name="twitter:title"]', { name: 'twitter:title', content: fullTitle });
      setMeta('meta[name="twitter:description"]', { name: 'twitter:description', content: metaDescription });
      setMeta('meta[name="twitter:image"]', { name: 'twitter:image', content: ogImage });
    } else {
      removeOwned('meta[name^="twitter:"]');
    }
  }, [
    canonicalUrl,
    enableCanonical,
    enableOpenGraph,
    enableTwitterCards,
    fullTitle,
    metaDescription,
    metaKeywords,
    modifiedTime,
    noIndex,
    ogImage,
    ogLocale,
    ogLocaleAlternates,
    ogLocaleAlternatesKey,
    publishedTime,
    siteName,
    twitterCard,
    type,
  ]);

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
      {enableOpenGraph && <meta property="og:type" content={type} />}
      {enableOpenGraph && <meta property="og:site_name" content={siteName} />}
      {enableOpenGraph && <meta property="og:title" content={fullTitle} />}
      {enableOpenGraph && <meta property="og:description" content={metaDescription} />}
      {enableOpenGraph && canonicalUrl && <meta property="og:url" content={canonicalUrl} />}
      {enableOpenGraph && <meta property="og:image" content={ogImage} />}
      {enableOpenGraph && <meta property="og:image:width" content="1200" />}
      {enableOpenGraph && <meta property="og:image:height" content="630" />}
      {enableOpenGraph && <meta property="og:locale" content={ogLocale} />}
      {enableOpenGraph && ogLocaleAlternates.map((locale) => (
        <meta key={locale} property="og:locale:alternate" content={locale} />
      ))}

      {/* Article-specific OG tags */}
      {enableOpenGraph && type === 'article' && publishedTime && (
        <meta property="article:published_time" content={publishedTime} />
      )}
      {enableOpenGraph && type === 'article' && modifiedTime && (
        <meta property="article:modified_time" content={modifiedTime} />
      )}

      {/* Twitter Card (respects admin toggle) */}
      {enableTwitterCards && <meta name="twitter:card" content={twitterCard} />}
      {enableTwitterCards && <meta name="twitter:title" content={fullTitle} />}
      {enableTwitterCards && <meta name="twitter:description" content={metaDescription} />}
      {enableTwitterCards && <meta name="twitter:image" content={ogImage} />}
    </Helmet>
  );
}

export default PageMeta;
