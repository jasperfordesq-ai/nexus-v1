// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';

import FeaturesPage from './components/FeaturesPage';
import HomePage from './components/HomePage';
import HostingPage from './components/HostingPage';
import SiteShell from './components/SiteShell';
import { normaliseSalesPath } from './lib/routes';

export default function App() {
  const [path, setPath] = useState(() => normaliseSalesPath(window.location.pathname));

  useEffect(() => {
    const handlePopState = () => setPath(normaliseSalesPath(window.location.pathname));
    window.addEventListener('popstate', handlePopState);
    return () => window.removeEventListener('popstate', handlePopState);
  }, []);

  const navigate = useCallback((href: string) => {
    const nextPath = normaliseSalesPath(href);
    window.history.pushState({}, '', nextPath);
    setPath(nextPath);
    window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  }, []);

  const page = useMemo(() => {
    if (path === '/hosting') {
      return <HostingPage onNavigate={navigate} />;
    }

    if (path === '/features') {
      return <FeaturesPage onNavigate={navigate} />;
    }

    return <HomePage onNavigate={navigate} />;
  }, [navigate, path]);

  return (
    <SiteShell currentPath={path} onNavigate={navigate}>
      {page}
    </SiteShell>
  );
}
