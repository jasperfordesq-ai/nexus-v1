// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const MANIFEST_ENDPOINT = '/api/v2/pwa/manifest';

export function tenantManifestHref(pathname: string): string {
  const path = pathname.startsWith('/') ? pathname : `/${pathname}`;
  return `${MANIFEST_ENDPOINT}?path=${encodeURIComponent(path || '/')}`;
}

/** Point the document manifest at a URL whose scope can follow path tenants. */
export function configureTenantManifestLink(
  pathname = window.location.pathname,
  documentRef: Document = document,
): void {
  const link = documentRef.querySelector<HTMLLinkElement>('link[rel="manifest"]');
  if (!link) return;

  link.href = tenantManifestHref(pathname);
}
