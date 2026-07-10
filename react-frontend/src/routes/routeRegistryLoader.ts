// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ReactNode } from 'react';
import { importWithChunkRecovery } from './lazyWithRetry';

export type AppRoutesFactory = () => ReactNode;
export type LoadableRouteRegistryKind = 'auth' | 'public' | 'app';

export interface LoadedRouteRegistry {
  kind: LoadableRouteRegistryKind;
  routes: AppRoutesFactory;
}

export async function loadRouteRegistry(
  kind: LoadableRouteRegistryKind,
): Promise<LoadedRouteRegistry> {
  if (kind === 'auth') {
    const { AuthRoutes } = await importWithChunkRecovery(() => import('./AuthRoutes'));
    return { kind, routes: AuthRoutes };
  }

  if (kind === 'public') {
    const { PublicAppRoutes } = await importWithChunkRecovery(() => import('./PublicAppRoutes'));
    return { kind, routes: PublicAppRoutes };
  }

  const { AppRoutes } = await importWithChunkRecovery(() => import('./AppRoutes'));
  return { kind, routes: AppRoutes };
}
