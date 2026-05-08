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
): string | null {
  const slug = tenantSlug?.trim();
  if (!slug) return null;

  const normalizedBaseUrl = (baseUrl.trim() || DEFAULT_ACCESSIBLE_FRONTEND_BASE_URL).replace(/\/+$/, '');
  const normalizedPath = !path || path === '/' ? '' : path.startsWith('/') ? path : `/${path}`;

  return `${normalizedBaseUrl}/${encodeURIComponent(slug)}/alpha${normalizedPath}`;
}
