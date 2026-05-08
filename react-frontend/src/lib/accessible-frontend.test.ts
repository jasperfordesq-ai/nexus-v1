// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { buildAccessibleFrontendUrl } from './accessible-frontend';

describe('buildAccessibleFrontendUrl', () => {
  it('builds the public tenant alpha home URL', () => {
    expect(buildAccessibleFrontendUrl('hour-timebank')).toBe(
      'https://accessible.project-nexus.ie/hour-timebank/alpha',
    );
  });

  it('trims a configured base URL before adding the tenant path', () => {
    expect(buildAccessibleFrontendUrl('hour-timebank', '/', 'https://example.test/')).toBe(
      'https://example.test/hour-timebank/alpha',
    );
  });

  it('appends accessible frontend subpaths under the tenant alpha namespace', () => {
    expect(buildAccessibleFrontendUrl('hour-timebank', '/listings', 'https://example.test')).toBe(
      'https://example.test/hour-timebank/alpha/listings',
    );
  });

  it('returns null when no tenant slug is available', () => {
    expect(buildAccessibleFrontendUrl(null)).toBeNull();
    expect(buildAccessibleFrontendUrl('   ')).toBeNull();
  });
});
