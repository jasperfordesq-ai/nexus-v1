// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import { resolvePathOwnership } from '../route-guard';

describe('route guard', () => {
  it('allows tenant-prefixed public pages on shared hosts', () => {
    expect(
      resolvePathOwnership('/hour-timebank/about', {
        host: 'app.project-nexus.ie',
        protocol: 'https',
      }),
    ).toMatchObject({
      route: { owner: 'next-public', routeKey: 'about' },
      shouldServeWithNext: true,
    });
  });

  it('blocks private shared-host routes before they reach the Next page renderer', () => {
    expect(
      resolvePathOwnership('/dashboard', {
        host: 'app.project-nexus.ie',
        protocol: 'https',
      }),
    ).toMatchObject({
      route: { owner: 'vite-private' },
      shouldServeWithNext: false,
    });
  });

  it('blocks private routes inside tenant-prefixed paths', () => {
    expect(
      resolvePathOwnership('/hour-timebank/dashboard', {
        host: 'app.project-nexus.ie',
        protocol: 'https',
      }),
    ).toMatchObject({
      route: { owner: 'vite-private' },
      shouldServeWithNext: false,
    });
  });
});
