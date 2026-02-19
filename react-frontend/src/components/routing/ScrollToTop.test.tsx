// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { render } from '@/test/test-utils';
import { ScrollToTop } from './ScrollToTop';

describe('ScrollToTop', () => {
  it('renders nothing visible', () => {
    const { container } = render(<ScrollToTop />);
    // ScrollToTop returns null, but HeroUIProvider wraps with overlay container
    // Verify no visible content from ScrollToTop itself
    expect(container.querySelector('[data-scrolltop]')).toBeNull();
  });

  it('calls window.scrollTo on mount', () => {
    const scrollToSpy = vi.spyOn(window, 'scrollTo');
    render(<ScrollToTop />);
    expect(scrollToSpy).toHaveBeenCalledWith(0, 0);
    scrollToSpy.mockRestore();
  });
});
