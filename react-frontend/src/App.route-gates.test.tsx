// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { existsSync, readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const appRoutesUrl = new URL('./routes/AppRoutes.tsx', import.meta.url);
const routeSourceUrl = existsSync(appRoutesUrl)
  ? appRoutesUrl
  : new URL('./App.tsx', import.meta.url);
const appSource = readFileSync(routeSourceUrl, 'utf8');

describe('App route feature gates', () => {
  it('gates matches routes behind the listings module', () => {
    expect(appSource).toMatch(/<Route path="matches" element=\{\s*<FeatureGate module="listings" redirect="\/dashboard">[\s\S]*?<MatchesPage \/>[\s\S]*?<\/FeatureGate>/);
    expect(appSource).toMatch(/<Route path="matches\/preferences" element=\{\s*<FeatureGate module="listings" redirect="\/dashboard">[\s\S]*?<MatchPreferencesPage \/>[\s\S]*?<\/FeatureGate>/);
  });

  it('gates reviews routes behind the reviews feature', () => {
    expect(appSource).toMatch(/<Route path="reviews" element=\{\s*<FeatureGate feature="reviews" redirect="\/dashboard">[\s\S]*?<ReviewsPage \/>[\s\S]*?<\/FeatureGate>/);
    expect(appSource).toMatch(/<Route path="reviews\/create" element=\{\s*<FeatureGate feature="reviews" redirect="\/dashboard">[\s\S]*?<ReviewsPage \/>[\s\S]*?<\/FeatureGate>/);
  });
});
