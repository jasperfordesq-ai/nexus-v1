// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export function resolveAssetUrl(url: string | null | undefined, apiBase: string | undefined): string | undefined {
  if (!url) {
    return undefined;
  }

  if (/^https?:\/\//i.test(url)) {
    return url;
  }

  const base = apiBase ?? 'https://api.project-nexus.ie/api';
  const origin = new URL(base).origin;

  return new URL(url, origin).toString();
}

export function safeCssColor(value: string | null | undefined): string | undefined {
  if (!value) {
    return undefined;
  }

  return /^#[0-9a-f]{3,8}$/i.test(value) ? value : undefined;
}
