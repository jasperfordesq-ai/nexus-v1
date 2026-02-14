/**
 * ScrollToTop
 * Scrolls the window to the top on every route change.
 * Place once inside <BrowserRouter>, outside of <Routes>.
 */

import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

export function ScrollToTop() {
  const { pathname } = useLocation();

  useEffect(() => {
    window.scrollTo(0, 0);
  }, [pathname]);

  return null;
}

export default ScrollToTop;
