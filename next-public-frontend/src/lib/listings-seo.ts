// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { Metadata } from 'next';

import { buildPageTitle } from './metadata';
import type { PublicListing } from './tenant-api';

interface BuildListingMetadataInput {
  canonicalUrl: string;
  listing: PublicListing;
  platformName: string;
  tenantName?: string;
}

export function buildListingMetadata(input: BuildListingMetadataInput): Metadata {
  const title = buildPageTitle({
    pageLabel: input.listing.title,
    platformName: input.platformName,
    tenantName: input.tenantName,
  });
  const description = input.listing.excerpt || input.listing.description;
  const imageUrl = input.listing.primaryImage?.url;

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
