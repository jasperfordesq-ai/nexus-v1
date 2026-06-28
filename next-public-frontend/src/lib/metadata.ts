// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { TenantMode } from './tenant-request';

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
