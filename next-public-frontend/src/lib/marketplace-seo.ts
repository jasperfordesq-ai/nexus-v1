// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { Metadata } from 'next';

import { buildPageTitle } from './metadata';
import type { PublicMarketplaceListing } from './tenant-api';

interface BuildMarketplaceMetadataInput {
  canonicalUrl: string;
  item: PublicMarketplaceListing;
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
  const imageUrl = input.item.primaryImage?.url;

  return {
    alternates: {
      canonical: input.canonicalUrl,
    },
    description,
    openGraph: {
      description,
      images: imageUrl ? [imageUrl] : undefined,
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
