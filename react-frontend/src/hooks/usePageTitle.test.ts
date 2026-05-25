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
});
