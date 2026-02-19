// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for usePageTitle hook
 */

import { describe, it, expect, afterEach } from 'vitest';
import { renderHook } from '@testing-library/react';
import { usePageTitle } from './usePageTitle';

describe('usePageTitle', () => {
  const originalTitle = document.title;

  afterEach(() => {
    document.title = originalTitle;
  });

  it('sets document title', () => {
    renderHook(() => usePageTitle('Dashboard'));
    expect(document.title).toBe('Dashboard');
  });

  it('restores previous title on unmount', () => {
    document.title = 'Original Title';
    const { unmount } = renderHook(() => usePageTitle('New Title'));
    expect(document.title).toBe('New Title');

    unmount();
    expect(document.title).toBe('Original Title');
  });

  it('updates title when title prop changes', () => {
    const { rerender } = renderHook(
      ({ title }) => usePageTitle(title),
      { initialProps: { title: 'Page A' } }
    );
    expect(document.title).toBe('Page A');

    rerender({ title: 'Page B' });
    expect(document.title).toBe('Page B');
  });

  it('handles empty string title', () => {
    renderHook(() => usePageTitle(''));
    expect(document.title).toBe('');
  });
});
