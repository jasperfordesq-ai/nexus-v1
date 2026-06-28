// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { getRouteOwnership, type RouteOwnership } from './public-routes';
import { resolveTenantRequest, type ResolveTenantRequestInput, type ResolvedTenantRequest } from './tenant-request';

export interface PathOwnership {
  request: ResolvedTenantRequest;
  route: RouteOwnership;
  shouldServeWithNext: boolean;
}

export function resolvePathOwnership(pathname: string, input: ResolveTenantRequestInput): PathOwnership {
  const request = resolveTenantRequest(pathToSegments(pathname), input);
  const route = getRouteOwnership(request.routeSegments);

  return {
    request,
    route,
    shouldServeWithNext: route.owner === 'next-public',
  };
}

function pathToSegments(pathname: string): string[] {
  return pathname.split('/').filter(Boolean);
}
