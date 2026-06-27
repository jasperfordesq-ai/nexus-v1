// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for usePageTitle hook
 */

import { describe, it, expect, afterEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { usePageTitle } from './usePageTitle';

describe('usePageTitle', () => {
  const originalTitle = document.title;

  afterEach(() => {
    document.title = originalTitle;
  });

  it('sets document title', async () => {
    renderHook(() => usePageTitle('Dashboard'));
    await waitFor(() => expect(document.title).toBe('Dashboard'));
  });

  it('restores previous title on unmount', async () => {
    document.title = 'Original Title';
    const { unmount } = renderHook(() => usePageTitle('New Title'));
    await waitFor(() => expect(document.title).toBe('New Title'));

    unmount();
    expect(document.title).toBe('Original Title');
  });

  it('updates title when title prop changes', async () => {
    const { rerender } = renderHook(
      ({ title }) => usePageTitle(title),
      { initialProps: { title: 'Page A' } }
    );
    await waitFor(() => expect(document.title).toBe('Page A'));

    rerender({ title: 'Page B' });
    await waitFor(() => expect(document.title).toBe('Page B'));
  });

  it('handles empty string title', async () => {
    renderHook(() => usePageTitle(''));
    await waitFor(() => expect(document.title).toBe(''));
  });

  // Regression: when PageMeta (react-helmet-async) is managing this route's <head>,
  // it is the canonical title owner. usePageTitle must yield — it must NOT overwrite
  // the title on mount NOR restore the previous title on unmount. PageMeta is detected
  // by the description / og:title meta tags it emits (which react-helmet-async renders
  // WITHOUT any data-rh marker — the old detection looked for that marker and so always
  // failed, causing the two title managers to race and leave a stale document.title).
  it('yields to PageMeta (does NOT overwrite the title) when a description meta is present', async () => {
    document.title = 'PageMeta Owned | Site';
    const meta = document.createElement('meta');
    meta.setAttribute('name', 'description');
    meta.setAttribute('content', 'A page managed by PageMeta');
    document.head.appendChild(meta);
    try {
      const { unmount } = renderHook(() => usePageTitle('Should Be Ignored'));
      // Let the hook's requestAnimationFrame run; it must leave the title untouched.
      await new Promise((resolve) => setTimeout(resolve, 50));
      expect(document.title).toBe('PageMeta Owned | Site');
      unmount();
      // Cleanup must also yield — it must not "restore" a previous title over PageMeta's.
      expect(document.title).toBe('PageMeta Owned | Site');
    } finally {
      meta.remove();
    }
  });

  it('yields to PageMeta when an og:title meta is present', async () => {
    document.title = 'Community Feed | Site';
    const og = document.createElement('meta');
    og.setAttribute('property', 'og:title');
    og.setAttribute('content', 'Community Feed | Site');
    document.head.appendChild(og);
    try {
      renderHook(() => usePageTitle('Feed'));
      await new Promise((resolve) => setTimeout(resolve, 50));
      expect(document.title).toBe('Community Feed | Site');
    } finally {
      og.remove();
    }
  });
});
