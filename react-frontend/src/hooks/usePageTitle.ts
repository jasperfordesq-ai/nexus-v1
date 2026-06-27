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
    // Yield to PageMeta (react-helmet-async) when it is managing this route's
    // <head>. PageMeta is the canonical title owner — it renders a richer
    // "<title> | <site>" plus Open Graph tags. It always emits
    // `<meta name="description">` and (when OG is enabled) `<meta property="og:title">`;
    // neither tag exists in index.html nor is emitted by the global SeoHead, so the
    // presence of either reliably means PageMeta is active on this route and
    // usePageTitle must NOT also write document.title.
    //
    // Previously this only matched `data-rh="true"` / `data-nexus-page-meta="true"`
    // marker attributes — but react-helmet-async emits NO such markers, so detection
    // ALWAYS failed and the two title managers raced, leaving document.title stale /
    // inconsistent (e.g. the previous page's title lingering after navigation).
    const hasHelmetMeta = () => Boolean(
      document.querySelector('meta[name="description"], meta[property="og:title"]')
    );
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
