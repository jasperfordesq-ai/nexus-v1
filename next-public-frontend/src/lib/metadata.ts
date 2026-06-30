// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { TenantMode } from './tenant-request';
import { publicMessageLocales } from './i18n';

export interface BuildCanonicalUrlInput {
  origin: string;
  routeSegments: string[];
  tenantMode: TenantMode;
  tenantSlug?: string;
}

export interface BuildPageTitleInput {
  pageLabel: string;
  platformName: string;
  tenantName?: string;
}

export interface BuildMetadataAlternatesInput {
  canonicalUrl: string;
  locale?: string;
}

export const NEXUS_PUBLIC_PATHNAME_HEADER = 'x-nexus-public-pathname';

const rtlLanguageCodes = new Set(['ar', 'fa', 'he', 'ur']);

export function buildCanonicalUrl(input: BuildCanonicalUrlInput): string {
  const canonicalSegments = [
    input.tenantMode === 'path' ? input.tenantSlug : undefined,
    ...input.routeSegments,
  ].filter((segment): segment is string => Boolean(segment));

  const path = canonicalSegments.map((segment) => encodeURIComponent(segment)).join('/');
  const origin = input.origin.replace(/\/+$/, '');

  return path ? `${origin}/${path}` : origin;
}

export function buildPageTitle(input: BuildPageTitleInput): string {
  return [input.pageLabel, input.tenantName, input.platformName].filter(Boolean).join(' | ');
}

export function buildMetadataAlternates(input: BuildMetadataAlternatesInput): {
  canonical: string;
  languages: Record<string, string>;
} {
  const activeLocale = normalizeSeoLocale(input.locale);
  const languageAlternates = Object.fromEntries(
    publicMessageLocales.map((locale) => [normalizeSeoLocale(locale), input.canonicalUrl]),
  );

  return {
    canonical: input.canonicalUrl,
    languages: {
      ...languageAlternates,
      [activeLocale]: input.canonicalUrl,
      'x-default': input.canonicalUrl,
    },
  };
}

export function formatOpenGraphLocale(locale: string | undefined): string {
  const [language = 'en', region] = normalizeSeoLocale(locale).split('-');

  return region ? `${language}_${region.toUpperCase()}` : language;
}

export function getHtmlDirection(locale: string | undefined): 'ltr' | 'rtl' {
  return rtlLanguageCodes.has(normalizeSeoLocale(locale).split('-')[0] ?? 'en') ? 'rtl' : 'ltr';
}

export function normalizeSeoLocale(locale: string | undefined): string {
  const normalized = (locale ?? 'en').trim().replace('_', '-').toLowerCase();

  return normalized || 'en';
}

export function pathnameToSegments(pathname: string | null | undefined): string[] {
  const cleanPathname = (pathname ?? '').split('?')[0]?.split('#')[0] ?? '';

  return cleanPathname.split('/').filter(Boolean);
}
