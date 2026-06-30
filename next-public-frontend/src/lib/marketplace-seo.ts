// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { Metadata } from 'next';

import { buildMetadataAlternates, buildPageTitle, formatOpenGraphLocale } from './metadata';
import type { PublicMarketplaceListing } from './tenant-api';

interface BuildMarketplaceMetadataInput {
  canonicalUrl: string;
  fallbackImageUrl?: string;
  item: PublicMarketplaceListing;
  locale?: string;
  platformName: string;
  tenantName?: string;
}

export function buildMarketplaceMetadata(input: BuildMarketplaceMetadataInput): Metadata {
  const title = buildPageTitle({
    pageLabel: input.item.title,
    platformName: input.platformName,
    tenantName: input.tenantName,
  });
  const description = input.item.excerpt || input.item.description;
  const imageUrl = input.item.primaryImage?.url ?? input.fallbackImageUrl;

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
