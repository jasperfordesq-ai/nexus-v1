// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ScrollToTop + RouteAnnouncer
 * Scrolls the window to the top on every route change AND announces
 * the new page title to screen readers via an aria-live region.
 * Place once inside <BrowserRouter>, outside of <Routes>.
 */

import { useEffect, useState, useRef } from 'react';
import { useLocation } from 'react-router-dom';

export function ScrollToTop() {
  const { pathname, hash } = useLocation();
  const [announcement, setAnnouncement] = useState('');
  const prevPathRef = useRef(pathname);

  useEffect(() => {
    const pathChanged = prevPathRef.current !== pathname;

    // Preserve native/deep-link anchor positioning. Same-page hash changes do
    // not represent a new page and must not steal focus from the activated link.
    if (!hash) {
      window.scrollTo(0, 0);
    }

    // Announce route change to screen readers after a short delay
    // to allow usePageTitle to update document.title first
    if (pathChanged) {
      prevPathRef.current = pathname;
      const timer = setTimeout(() => {
        const title = document.title;
        if (title) {
          setAnnouncement(title);
        }

        // Move keyboard/screen-reader focus into the newly rendered page.
        // Layout exposes this programmatic-only target with tabIndex={-1}.
        if (!hash) {
          document.getElementById('main-content')?.focus({ preventScroll: true });
        }
      }, 100);
      return () => clearTimeout(timer);
    }
  }, [hash, pathname]);

  return (
    <div
      className="sr-only"
      role="status"
      aria-live="polite"
      aria-atomic="true"
    >
      {announcement}
    </div>
  );
}

export default ScrollToTop;
