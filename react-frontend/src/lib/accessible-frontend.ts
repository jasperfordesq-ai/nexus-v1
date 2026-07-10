// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const DEFAULT_ACCESSIBLE_FRONTEND_BASE_URL = 'https://accessible.project-nexus.ie';

export function getAccessibleFrontendBaseUrl(): string {
  const configured = (import.meta.env.VITE_ACCESSIBLE_FRONTEND_BASE_URL as string | undefined)?.trim();
  return (configured || DEFAULT_ACCESSIBLE_FRONTEND_BASE_URL).replace(/\/+$/, '');
}

export function buildAccessibleFrontendUrl(
  tenantSlug: string | null | undefined,
  path = '/',
  baseUrl = getAccessibleFrontendBaseUrl(),
  accessibleDomain?: string | null,
): string | null {
  const normalizedPath = !path || path === '/' ? '' : path.startsWith('/') ? path : `/${path}`;

  // The tenant has its own accessible (GOV.UK) custom domain → slug-less clean
  // entry: the host resolves the tenant server-side, so no /{slug} prefix.
  const customDomain = accessibleDomain
    ?.trim()
    .replace(/^https?:\/\//, '')
    .replace(/\/+$/, '');
  if (customDomain) {
    // The custom accessible domain is the clean public entry point: the host
    // resolves the tenant server-side, so every route is served slug-less at
    // the bare root (mirroring React custom domains). Deep links are bare
    // paths too — the server registers the same routes without any prefix.
    return normalizedPath
      ? `https://${customDomain}${normalizedPath}`
      : `https://${customDomain}`;
  }

  const slug = tenantSlug?.trim();
  if (!slug) return null;

  const normalizedBaseUrl = (baseUrl.trim() || DEFAULT_ACCESSIBLE_FRONTEND_BASE_URL).replace(/\/+$/, '');

  return `${normalizedBaseUrl}/${encodeURIComponent(slug)}/accessible${normalizedPath}`;
}
