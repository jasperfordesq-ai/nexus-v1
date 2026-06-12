// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Regression tests for SlugUrlGuard (TenantShell).
 *
 * Pages rendered inside TenantShell's slug-stripped nested <Routes> resolve
 * relative navigations (setSearchParams, navigate('?...')) against the
 * STRIPPED pathname. EventsPage does exactly that on mount to sync its
 * filters into the URL, which rewrote the browser URL from
 * /hour-timebank/events to /events — after SlugUrlGuard's one-shot
 * mount-time check had already run. A slug-less URL then makes
 * detectTenantFromUrl() fall back to the master tenant on the next
 * TenantShell render. The guard must therefore re-assert the slug on every
 * router location change, not just on mount.
 */

import { describe, it, expect, beforeEach } from 'vitest';
import { render, waitFor } from '@testing-library/react';
import { BrowserRouter, Routes, Route, useSearchParams } from 'react-router-dom';
import { useEffect } from 'react';
import { SlugUrlGuard } from './TenantShell';

/** Mimics EventsPage: pushes its (stripped) location into the URL on mount. */
function StripOnMount() {
  const [, setSearchParams] = useSearchParams();
  useEffect(() => {
    setSearchParams(new URLSearchParams('q=workshop'), { replace: true });
    // eslint-disable-next-line react-hooks/exhaustive-deps -- mount-only, mirrors EventsPage
  }, []);
  return <div>events page</div>;
}

describe('SlugUrlGuard', () => {
  beforeEach(() => {
    window.history.replaceState(null, '', '/hour-timebank/events');
  });

  it('restores the slug after a nested-route setSearchParams rewrites the browser URL', async () => {
    render(
      <BrowserRouter>
        <SlugUrlGuard slug="hour-timebank" />
        {/* Same shape as TenantGuard: route matching runs on the stripped path */}
        <Routes location={{ pathname: '/events', search: '' }}>
          <Route path="events" element={<StripOnMount />} />
        </Routes>
      </BrowserRouter>
    );

    await waitFor(() => {
      expect(window.location.pathname).toBe('/hour-timebank/events');
      expect(window.location.search).toBe('?q=workshop');
    });
  });

  it('leaves an already-correct URL untouched', async () => {
    render(
      <BrowserRouter>
        <SlugUrlGuard slug="hour-timebank" />
        <Routes location={{ pathname: '/events', search: '' }}>
          <Route path="events" element={<div>events page</div>} />
        </Routes>
      </BrowserRouter>
    );

    await waitFor(() => {
      expect(window.location.pathname).toBe('/hour-timebank/events');
    });
  });
});
