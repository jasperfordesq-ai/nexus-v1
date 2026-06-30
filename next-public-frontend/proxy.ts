// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { type NextRequest, NextResponse } from 'next/server';

import { NEXUS_PUBLIC_PATHNAME_HEADER } from './src/lib/metadata';
import { resolvePathOwnership } from './src/lib/route-guard';

export function proxy(request: NextRequest): NextResponse {
  if (request.nextUrl.pathname.startsWith('/_next/')) {
    return NextResponse.next();
  }

  const ownership = resolvePathOwnership(request.nextUrl.pathname, {
    host: request.headers.get('x-forwarded-host') ?? request.headers.get('host') ?? undefined,
    protocol: request.headers.get('x-forwarded-proto') ?? request.nextUrl.protocol.replace(':', ''),
  });

  if (!ownership.shouldServeWithNext) {
    return new NextResponse(null, { status: 404 });
  }

  const requestHeaders = new Headers(request.headers);
  requestHeaders.set(NEXUS_PUBLIC_PATHNAME_HEADER, request.nextUrl.pathname);

  return NextResponse.next({
    request: {
      headers: requestHeaders,
    },
  });
}

export const config = {
  matcher: ['/((?!_next/static|_next/image|favicon.ico|robots.txt|sitemap.xml).*)'],
};
