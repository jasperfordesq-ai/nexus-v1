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
  const { pathname } = useLocation();
  const [announcement, setAnnouncement] = useState('');
  const prevPathRef = useRef(pathname);

  useEffect(() => {
    window.scrollTo(0, 0);

    // Announce route change to screen readers after a short delay
    // to allow usePageTitle to update document.title first
    if (prevPathRef.current !== pathname) {
      prevPathRef.current = pathname;
      const timer = setTimeout(() => {
        const title = document.title;
        if (title) {
          setAnnouncement(title);
        }
      }, 100);
      return () => clearTimeout(timer);
    }
  }, [pathname]);

  return (
    <div
      className="sr-only"
      role="status"
      aria-live="assertive"
      aria-atomic="true"
    >
      {announcement}
    </div>
  );
}

export default ScrollToTop;
