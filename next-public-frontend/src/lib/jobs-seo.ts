// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { Metadata } from 'next';

import { buildMetadataAlternates, buildPageTitle, formatOpenGraphLocale } from './metadata';
import type { PublicJob } from './tenant-api';

interface BuildJobMetadataInput {
  canonicalUrl: string;
  fallbackImageUrl?: string;
  job: PublicJob;
  locale?: string;
  platformName: string;
  tenantName?: string;
}

export function buildJobMetadata(input: BuildJobMetadataInput): Metadata {
  const title = buildPageTitle({
    pageLabel: input.job.title,
    platformName: input.platformName,
    tenantName: input.tenantName,
  });
  const description = input.job.excerpt || input.job.description;
  const imageUrl = input.job.primaryImage?.url ?? input.fallbackImageUrl;

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
