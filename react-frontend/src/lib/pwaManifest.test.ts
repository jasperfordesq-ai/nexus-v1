// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it } from 'vitest';
import { configureTenantManifestLink, tenantManifestHref } from './pwaManifest';

describe('tenant PWA manifest link', () => {
  beforeEach(() => {
    document.head.innerHTML = '<link rel="manifest" href="/api/v2/pwa/manifest?path=%2F">';
  });

  it('encodes the current path for backend tenant resolution', () => {
    expect(tenantManifestHref('/hour-timebank/listings?tab=offers')).toBe(
      '/api/v2/pwa/manifest?path=%2Fhour-timebank%2Flistings%3Ftab%3Doffers',
    );
  });

  it('updates the document manifest before the app renders', () => {
    configureTenantManifestLink('/hour-timebank/dashboard');

    expect(document.querySelector('link[rel="manifest"]')).toHaveAttribute(
      'href',
      '/api/v2/pwa/manifest?path=%2Fhour-timebank%2Fdashboard',
    );
  });
});
