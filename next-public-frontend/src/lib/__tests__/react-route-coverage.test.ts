// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { describe, expect, it } from 'vitest';

import { getRouteOwnership } from '../public-routes';

describe('React route ownership coverage', () => {
  it('classifies every concrete React route for future shadow canary routing', () => {
    const appSource = readFileSync(
      resolve(process.cwd(), '..', 'react-frontend', 'src', 'App.tsx'),
      'utf8',
    );
    const routePaths = [...appSource.matchAll(/<Route\s+path="([^"]+)"/g)]
      .map((match) => match[1])
      .filter((path) => !path.includes('*'))
      .filter((path) => !path.startsWith(':tenantSlug/alpha'));
    const unknownRoutes = routePaths.filter((path) => (
      getRouteOwnership(path.split('/')).owner === 'unknown'
    ));

    expect(unknownRoutes).toEqual([]);
  });
});
