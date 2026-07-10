// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { buildAccessibleFrontendUrl } from './accessible-frontend';

describe('buildAccessibleFrontendUrl', () => {
  it('builds the public tenant accessible home URL', () => {
    expect(buildAccessibleFrontendUrl('hour-timebank')).toBe(
      'https://accessible.project-nexus.ie/hour-timebank/accessible',
    );
  });

  it('trims a configured base URL before adding the tenant path', () => {
    expect(buildAccessibleFrontendUrl('hour-timebank', '/', 'https://example.test/')).toBe(
      'https://example.test/hour-timebank/accessible',
    );
  });

  it('appends accessible frontend subpaths under the tenant accessible namespace', () => {
    expect(buildAccessibleFrontendUrl('hour-timebank', '/listings', 'https://example.test')).toBe(
      'https://example.test/hour-timebank/accessible/listings',
    );
  });

  it('returns null when no tenant slug is available', () => {
    expect(buildAccessibleFrontendUrl(null)).toBeNull();
    expect(buildAccessibleFrontendUrl('   ')).toBeNull();
  });

  it('uses the bare custom domain as the entry link (host resolves the tenant server-side)', () => {
    expect(
      buildAccessibleFrontendUrl('hour-timebank', '/', undefined, 'accessible.example.org'),
    ).toBe('https://accessible.example.org');
  });

  it('appends subpaths on the accessible custom domain as bare slug-less paths', () => {
    expect(
      buildAccessibleFrontendUrl('hour-timebank', '/listings', undefined, 'accessible.example.org'),
    ).toBe('https://accessible.example.org/listings');
  });

  it('strips scheme and trailing slash from a configured accessible domain', () => {
    expect(
      buildAccessibleFrontendUrl('hour-timebank', '/', undefined, 'https://accessible.example.org/'),
    ).toBe('https://accessible.example.org');
  });

  it('returns the bare accessible custom domain even without a slug', () => {
    expect(buildAccessibleFrontendUrl(null, '/', undefined, 'accessible.example.org')).toBe(
      'https://accessible.example.org',
    );
  });
});
