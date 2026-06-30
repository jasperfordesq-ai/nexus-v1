// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import { publicMessageLocales } from '../i18n';
import {
  buildCanonicalUrl,
  buildMetadataAlternates,
  buildPageTitle,
  formatOpenGraphLocale,
  getHtmlDirection,
  normalizeSeoLocale,
  pathnameToSegments,
} from '../metadata';

describe('metadata helpers', () => {
  it('builds slug-prefixed canonicals for shared-host tenants', () => {
    expect(
      buildCanonicalUrl({
        origin: 'https://app.project-nexus.ie',
        routeSegments: ['about'],
        tenantMode: 'path',
        tenantSlug: 'hour-timebank',
      }),
    ).toBe('https://app.project-nexus.ie/hour-timebank/about');
  });

  it('builds clean canonicals for custom-domain tenants', () => {
    expect(
      buildCanonicalUrl({
        origin: 'https://community.example',
        routeSegments: ['blog', 'community-news'],
        tenantMode: 'host',
        tenantSlug: undefined,
      }),
    ).toBe('https://community.example/blog/community-news');
  });

  it('combines the route label with tenant branding', () => {
    expect(
      buildPageTitle({
        pageLabel: 'About',
        platformName: 'Project NEXUS',
        tenantName: 'Hour Timebank',
      }),
    ).toBe('About | Hour Timebank | Project NEXUS');
  });

  it('builds canonical hreflang alternates for the tenant default locale', () => {
    expect(
      buildMetadataAlternates({
        canonicalUrl: 'https://app.project-nexus.ie/hour-timebank/about',
        locale: 'ga-IE',
      }),
    ).toEqual({
      canonical: 'https://app.project-nexus.ie/hour-timebank/about',
      languages: expect.objectContaining({
        ...Object.fromEntries(publicMessageLocales.map((locale) => [locale, 'https://app.project-nexus.ie/hour-timebank/about'])),
        'ga-ie': 'https://app.project-nexus.ie/hour-timebank/about',
        'x-default': 'https://app.project-nexus.ie/hour-timebank/about',
      }),
    });
    expect(Object.keys(buildMetadataAlternates({
      canonicalUrl: 'https://app.project-nexus.ie/hour-timebank/about',
      locale: 'ga-IE',
    }).languages)).toHaveLength(publicMessageLocales.length + 2);
  });

  it('normalizes locale, OpenGraph locale, and HTML direction safely', () => {
    expect(normalizeSeoLocale('GA_ie')).toBe('ga-ie');
    expect(formatOpenGraphLocale('pt-BR')).toBe('pt_BR');
    expect(getHtmlDirection('ar')).toBe('rtl');
    expect(getHtmlDirection('en')).toBe('ltr');
  });

  it('parses proxy pathnames into tenant request segments', () => {
    expect(pathnameToSegments('/hour-timebank/listings/7?preview=1#content')).toEqual([
      'hour-timebank',
      'listings',
      '7',
    ]);
  });
});
