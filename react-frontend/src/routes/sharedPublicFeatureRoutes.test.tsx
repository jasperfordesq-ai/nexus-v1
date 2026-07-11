// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { isValidElement, type ReactElement } from 'react';
import { describe, expect, it } from 'vitest';
import { ErrorBoundary } from '@/components/feedback/ErrorBoundary';
import { FeatureGate } from '@/components/routing/FeatureGate';
import {
  renderSharedPublicFeatureRoutes,
  SHARED_PUBLIC_FEATURE_ROUTE_POLICIES,
} from './sharedPublicFeatureRoutes';

describe('shared public feature routes', () => {
  it('keeps only identity-free token routes in the shared public registry', () => {
    expect(SHARED_PUBLIC_FEATURE_ROUTE_POLICIES).toEqual([
      {
        id: 'guardian-consent-verify',
        path: 'volunteering/guardian-consent/verify/:token',
        auth: 'public',
        feature: 'volunteering',
      },
    ]);
  });

  it('renders every shared route with its matching feature gate', () => {
    const routes = renderSharedPublicFeatureRoutes();

    expect(routes).toHaveLength(SHARED_PUBLIC_FEATURE_ROUTE_POLICIES.length);

    routes.forEach((route, index) => {
      const policy = SHARED_PUBLIC_FEATURE_ROUTE_POLICIES[index];
      expect(policy).toBeDefined();
      expect(route.props.path).toBe(policy?.path);

      const boundary = route.props.element;
      expect(isValidElement(boundary)).toBe(true);
      expect((boundary as ReactElement).type).toBe(ErrorBoundary);

      const gate = (boundary as ReactElement<{ children: ReactElement }>).props.children;
      expect(gate.type).toBe(FeatureGate);
      expect(gate.props.feature).toBe(policy?.feature);
      expect(gate.props.redirect).toBe('/');
    });
  });
});
