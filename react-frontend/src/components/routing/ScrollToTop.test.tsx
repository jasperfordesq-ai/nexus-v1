// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, afterEach } from 'vitest';
import { act, fireEvent, render as renderWithoutProviders, screen } from '@testing-library/react';
import { MemoryRouter, useNavigate } from 'react-router-dom';
import { render } from '@/test/test-utils';
import { ScrollToTop } from './ScrollToTop';

function RouteFocusHarness() {
  const navigate = useNavigate();

  return (
    <>
      <button type="button" onClick={() => navigate('/next')}>Next page</button>
      <button type="button" onClick={() => navigate('/anchor#details')}>Anchor page</button>
      <main id="main-content" tabIndex={-1}>Page content</main>
      <ScrollToTop />
    </>
  );
}

describe('ScrollToTop', () => {
  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  it('renders nothing visible', () => {
    const { container } = render(<ScrollToTop />);
    // ScrollToTop returns null, while app providers may add overlay containers
    // Verify no visible content from ScrollToTop itself
    expect(container.querySelector('[data-scrolltop]')).toBeNull();
  });

  it('calls window.scrollTo on mount', () => {
    const scrollToSpy = vi.spyOn(window, 'scrollTo');
    render(<ScrollToTop />);
    expect(scrollToSpy).toHaveBeenCalledWith(0, 0);
    scrollToSpy.mockRestore();
  });

  it('moves focus to the main landmark after a pathname change', async () => {
    vi.useFakeTimers();
    vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
    document.title = 'Next page - Test community';

    renderWithoutProviders(
      <MemoryRouter initialEntries={['/start']}>
        <RouteFocusHarness />
      </MemoryRouter>
    );

    fireEvent.click(screen.getByRole('button', { name: 'Next page' }));
    await act(async () => {
      vi.advanceTimersByTime(100);
    });

    expect(screen.getByRole('main')).toHaveFocus();
    expect(screen.getByRole('status')).toHaveTextContent('Next page - Test community');
  });

  it('preserves anchor navigation instead of focusing the page root', async () => {
    vi.useFakeTimers();
    const scrollToSpy = vi.spyOn(window, 'scrollTo').mockImplementation(() => {});

    renderWithoutProviders(
      <MemoryRouter initialEntries={['/start']}>
        <RouteFocusHarness />
      </MemoryRouter>
    );

    const anchorButton = screen.getByRole('button', { name: 'Anchor page' });
    anchorButton.focus();
    fireEvent.click(anchorButton);
    await act(async () => {
      vi.advanceTimersByTime(100);
    });

    expect(screen.getByRole('main')).not.toHaveFocus();
    expect(scrollToSpy).toHaveBeenCalledTimes(1);
  });
});
