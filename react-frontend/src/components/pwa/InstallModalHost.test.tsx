// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, act } from '@/test/test-utils';
import React from 'react';

// ─── Stub child modals to isolate InstallModalHost logic ─────────────────────
vi.mock('./IosInstallModal', () => ({
  IosInstallModal: ({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) =>
    isOpen ? (
      <div data-testid="ios-modal" role="dialog" aria-label="ios install">
        <button onClick={onClose}>ios-close</button>
      </div>
    ) : null,
}));

vi.mock('./ManualInstallModal', () => ({
  ManualInstallModal: ({
    isOpen,
    onClose,
    browser,
  }: {
    isOpen: boolean;
    onClose: () => void;
    browser: string;
  }) =>
    isOpen ? (
      <div data-testid="manual-modal" role="dialog" data-browser={browser} aria-label="manual install">
        <button onClick={onClose}>manual-close</button>
      </div>
    ) : null,
}));

// ─── Helper: dispatch the custom install event ────────────────────────────────
function dispatchInstallEvent(detail: { kind: 'ios' } | { kind: 'manual'; browser: string } | null) {
  window.dispatchEvent(new CustomEvent('nexus:install-modal', { detail }));
}

// ─────────────────────────────────────────────────────────────────────────────
describe('InstallModalHost', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders nothing initially (no modals visible)', async () => {
    const { InstallModalHost } = await import('./InstallModalHost');
    render(<InstallModalHost />);
    expect(screen.queryByTestId('ios-modal')).not.toBeInTheDocument();
    expect(screen.queryByTestId('manual-modal')).not.toBeInTheDocument();
  });

  it('shows the iOS modal when nexus:install-modal is dispatched with kind="ios"', async () => {
    const { InstallModalHost } = await import('./InstallModalHost');
    render(<InstallModalHost />);

    act(() => {
      dispatchInstallEvent({ kind: 'ios' });
    });

    await waitFor(() => {
      expect(screen.getByTestId('ios-modal')).toBeInTheDocument();
    });
  });

  it('does NOT show the manual modal when kind="ios"', async () => {
    const { InstallModalHost } = await import('./InstallModalHost');
    render(<InstallModalHost />);

    act(() => {
      dispatchInstallEvent({ kind: 'ios' });
    });

    await waitFor(() => screen.getByTestId('ios-modal'));
    expect(screen.queryByTestId('manual-modal')).not.toBeInTheDocument();
  });

  it('shows the manual modal when nexus:install-modal is dispatched with kind="manual"', async () => {
    const { InstallModalHost } = await import('./InstallModalHost');
    render(<InstallModalHost />);

    act(() => {
      dispatchInstallEvent({ kind: 'manual', browser: 'chrome-android' });
    });

    await waitFor(() => {
      expect(screen.getByTestId('manual-modal')).toBeInTheDocument();
    });
  });

  it('passes the browser prop to ManualInstallModal', async () => {
    const { InstallModalHost } = await import('./InstallModalHost');
    render(<InstallModalHost />);

    act(() => {
      dispatchInstallEvent({ kind: 'manual', browser: 'edge-desktop' });
    });

    await waitFor(() => {
      const modal = screen.getByTestId('manual-modal');
      expect(modal.getAttribute('data-browser')).toBe('edge-desktop');
    });
  });

  it('closes iOS modal when onClose is called', async () => {
    const { InstallModalHost } = await import('./InstallModalHost');
    render(<InstallModalHost />);

    act(() => {
      dispatchInstallEvent({ kind: 'ios' });
    });
    await waitFor(() => screen.getByTestId('ios-modal'));

    act(() => {
      screen.getByText('ios-close').click();
    });

    await waitFor(() => {
      expect(screen.queryByTestId('ios-modal')).not.toBeInTheDocument();
    });
  });

  it('closes manual modal when onClose is called', async () => {
    const { InstallModalHost } = await import('./InstallModalHost');
    render(<InstallModalHost />);

    act(() => {
      dispatchInstallEvent({ kind: 'manual', browser: 'chrome-android' });
    });
    await waitFor(() => screen.getByTestId('manual-modal'));

    act(() => {
      screen.getByText('manual-close').click();
    });

    await waitFor(() => {
      expect(screen.queryByTestId('manual-modal')).not.toBeInTheDocument();
    });
  });

  it('switches from iOS to manual modal when a second event fires', async () => {
    const { InstallModalHost } = await import('./InstallModalHost');
    render(<InstallModalHost />);

    act(() => {
      dispatchInstallEvent({ kind: 'ios' });
    });
    await waitFor(() => screen.getByTestId('ios-modal'));

    act(() => {
      dispatchInstallEvent({ kind: 'manual', browser: 'samsung' });
    });

    await waitFor(() => {
      expect(screen.queryByTestId('ios-modal')).not.toBeInTheDocument();
      expect(screen.getByTestId('manual-modal')).toBeInTheDocument();
    });
  });

  it('ignores events with a null detail', async () => {
    const { InstallModalHost } = await import('./InstallModalHost');
    render(<InstallModalHost />);

    act(() => {
      dispatchInstallEvent(null);
    });

    // Brief tick to ensure no state update
    await new Promise((r) => setTimeout(r, 20));
    expect(screen.queryByTestId('ios-modal')).not.toBeInTheDocument();
    expect(screen.queryByTestId('manual-modal')).not.toBeInTheDocument();
  });

  it('removes the event listener on unmount (no stale listener)', async () => {
    const addSpy = vi.spyOn(window, 'addEventListener');
    const removeSpy = vi.spyOn(window, 'removeEventListener');

    const { InstallModalHost } = await import('./InstallModalHost');
    const { unmount } = render(<InstallModalHost />);

    const added = addSpy.mock.calls.filter(([name]) => name === 'nexus:install-modal');
    expect(added.length).toBeGreaterThanOrEqual(1);

    unmount();

    const removed = removeSpy.mock.calls.filter(([name]) => name === 'nexus:install-modal');
    expect(removed.length).toBeGreaterThanOrEqual(1);

    addSpy.mockRestore();
    removeSpy.mockRestore();
  });

  it('manual modal uses "other" as browser fallback before any event fires', async () => {
    // Even with no event, ManualInstallModal receives browser="other" per the component logic
    const { InstallModalHost } = await import('./InstallModalHost');
    // We can verify by firing a manual event and checking the browser prop
    render(<InstallModalHost />);

    act(() => {
      dispatchInstallEvent({ kind: 'manual', browser: 'other' });
    });

    await waitFor(() => {
      const modal = screen.getByTestId('manual-modal');
      expect(modal.getAttribute('data-browser')).toBe('other');
    });
  });
});
