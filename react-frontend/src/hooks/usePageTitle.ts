// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * usePageTitle Hook
 * Sets the document title to "Page Title - Tenant Name" format.
 */

import { useEffect } from 'react';

export function usePageTitle(title: string) {
  useEffect(() => {
    const hasHelmetMeta = () => Boolean(document.querySelector(
      'meta[name="description"][data-rh="true"], link[rel="canonical"][data-rh="true"], meta[property="og:title"][data-rh="true"], meta[name="description"][data-nexus-page-meta="true"], link[rel="canonical"][data-nexus-page-meta="true"], meta[property="og:title"][data-nexus-page-meta="true"]'
    ));
    const prev = document.title;
    const raf = window.requestAnimationFrame(() => {
      if (!hasHelmetMeta()) {
        document.title = title;
      }
    });
    return () => {
      window.cancelAnimationFrame(raf);
      if (!hasHelmetMeta()) {
        document.title = prev;
      }
    };
  }, [title]);
}
