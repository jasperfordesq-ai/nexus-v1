// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Route } from 'react-router-dom';
import { ErrorBoundary } from '@/components/feedback/ErrorBoundary';
import { FeatureGate } from '@/components/routing/FeatureGate';
import type { TenantFeatures } from '@/types';
import { lazyWithRetry } from './lazyWithRetry';

const GuardianConsentVerifyPage = lazyWithRetry(
  () => import('@/pages/volunteering/GuardianConsentVerifyPage'),
);
const ExplorePage = lazyWithRetry(() => import('@/pages/explore/ExplorePage'));

interface SharedPublicFeatureRoutePolicy {
  id: 'guardian-consent-verify' | 'explore';
  path: string;
  auth: 'public';
  feature: keyof TenantFeatures;
}

/**
 * Public routes rendered by both the full and public-only registries.
 * Keeping path, auth posture, and feature gate in one record prevents the two
 * startup paths from exposing different tenant capabilities.
 */
export const SHARED_PUBLIC_FEATURE_ROUTE_POLICIES = [
  {
    id: 'guardian-consent-verify',
    path: 'volunteering/guardian-consent/verify/:token',
    auth: 'public',
    feature: 'volunteering',
  },
  {
    id: 'explore',
    path: 'explore',
    auth: 'public',
    feature: 'explore',
  },
] as const satisfies readonly SharedPublicFeatureRoutePolicy[];

const routePages = {
  'guardian-consent-verify': GuardianConsentVerifyPage,
  explore: ExplorePage,
} as const;

export function renderSharedPublicFeatureRoutes() {
  return SHARED_PUBLIC_FEATURE_ROUTE_POLICIES.map((policy) => {
    const Page = routePages[policy.id];

    return (
      <Route
        key={policy.id}
        path={policy.path}
        element={
          <ErrorBoundary>
            <FeatureGate feature={policy.feature} redirect="/">
              <Page />
            </FeatureGate>
          </ErrorBoundary>
        }
      />
    );
  });
}
