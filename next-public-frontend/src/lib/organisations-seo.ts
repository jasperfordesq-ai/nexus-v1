// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { Metadata } from 'next';

import { buildMetadataAlternates, buildPageTitle, formatOpenGraphLocale } from './metadata';
import type { PublicOrganisation } from './tenant-api';

interface BuildOrganisationMetadataInput {
  canonicalUrl: string;
  fallbackImageUrl?: string;
  locale?: string;
  organisation: PublicOrganisation;
  platformName: string;
  tenantName?: string;
}

export function buildOrganisationMetadata(input: BuildOrganisationMetadataInput): Metadata {
  const title = buildPageTitle({
    pageLabel: input.organisation.name,
    platformName: input.platformName,
    tenantName: input.tenantName,
  });
  const description = input.organisation.excerpt || input.organisation.description;
  const imageUrl = input.organisation.logoImage?.url ?? input.fallbackImageUrl;

  return {
    alternates: buildMetadataAlternates({ canonicalUrl: input.canonicalUrl, locale: input.locale }),
    description,
    openGraph: {
      description,
      images: imageUrl ? [imageUrl] : undefined,
      locale: formatOpenGraphLocale(input.locale),
      title,
      type: 'website',
      url: input.canonicalUrl,
    },
    title,
    twitter: {
      card: imageUrl ? 'summary_large_image' : 'summary',
      description,
      images: imageUrl ? [imageUrl] : undefined,
      title,
    },
  };
}
