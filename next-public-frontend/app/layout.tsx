// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ReactNode } from 'react';
import { headers } from 'next/headers';

import './globals.css';
import { NEXUS_PUBLIC_PATHNAME_HEADER, getHtmlDirection, normalizeSeoLocale } from '../src/lib/metadata';
import { resolvePathOwnership } from '../src/lib/route-guard';
import { fetchTenantBootstrap } from '../src/lib/tenant-api';

export default async function RootLayout({ children }: Readonly<{ children: ReactNode }>): Promise<ReactNode> {
  const locale = await resolveDocumentLocale();
  const htmlLocale = normalizeSeoLocale(locale);

  return (
    <html dir={getHtmlDirection(htmlLocale)} lang={htmlLocale}>
      <body>{children}</body>
    </html>
  );
}

async function resolveDocumentLocale(): Promise<string> {
  const headerList = await headers();
  const pathname = headerList.get(NEXUS_PUBLIC_PATHNAME_HEADER);

  if (!pathname) {
    return 'en';
  }

  const ownership = resolvePathOwnership(pathname, {
    host: headerList.get('x-forwarded-host') ?? headerList.get('host') ?? undefined,
    protocol: headerList.get('x-forwarded-proto') ?? undefined,
  });

  if (!ownership.shouldServeWithNext) {
    return 'en';
  }

  const bootstrap = await fetchTenantBootstrap(ownership.request);

  return bootstrap.tenant?.default_language ?? 'en';
}
