// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for UpdateAvailableBanner component
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const { initial, animate, exit, transition, variants, ...rest } = props;
      void initial; void animate; void exit; void transition; void variants;
      return <div {...rest}>{children as React.ReactNode}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

import { UpdateAvailableBanner } from '../UpdateAvailableBanner';

describe('UpdateAvailableBanner', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    sessionStorage.clear();
    // Reset global flags
    delete (window as NexusWindow).__nexus_updatePending;
    delete (window as NexusWindow).__nexus_updateSW;
  });

  afterEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    delete (window as NexusWindow).__nexus_updatePending;
    delete (window as NexusWindow).__nexus_updateSW;
  });

  it('renders nothing by default (no pending update)', () => {
    const { container } = render(<UpdateAvailableBanner />);
    // No banner visible initially
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
  });

  it('shows the banner when __nexus_updatePending is set before mount', () => {
    (window as NexusWindow).__nexus_updatePending = true;
    render(<UpdateAvailableBanner />);
    expect(screen.getByRole('status')).toBeInTheDocument();
  });

  it('shows the banner when "nexus:sw_update_available" event is dispatched', async () => {
    render(<UpdateAvailableBanner />);

    window.dispatchEvent(new Event('nexus:sw_update_available'));

    await waitFor(() => {
      expect(screen.getByRole('status')).toBeInTheDocument();
    });
  });

  it('renders Update Now button when banner is visible', async () => {
    (window as NexusWindow).__nexus_updatePending = true;
    render(<UpdateAvailableBanner />);

    expect(screen.getByRole('button', { name: /update now/i })).toBeInTheDocument();
  });

  it('renders dismiss button when banner is visible', async () => {
    (window as NexusWindow).__nexus_updatePending = true;
    render(<UpdateAvailableBanner />);

    expect(screen.getByRole('button', { name: /dismiss/i })).toBeInTheDocument();
  });

  it('hides the banner when dismiss button is clicked', async () => {
    (window as NexusWindow).__nexus_updatePending = true;
    render(<UpdateAvailableBanner />);

    const dismissBtn = screen.getByRole('button', { name: /dismiss/i });
    fireEvent.click(dismissBtn);

    await waitFor(() => {
      expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });
  });

  it('calls __nexus_updateSW when Update Now is clicked', async () => {
    const replaceMock = vi.fn();
    const reloadMock = vi.fn();
    const originalLocation = window.location;
    Object.defineProperty(window, 'location', {
      writable: true,
      value: { ...originalLocation, replace: replaceMock, reload: reloadMock },
    });
    const updateSW = vi.fn().mockResolvedValue(undefined);
    (window as NexusWindow).__nexus_updatePending = true;
    (window as NexusWindow).__nexus_updateSW = updateSW;

    render(<UpdateAvailableBanner />);

    const updateBtn = screen.getByRole('button', { name: /update now/i });
    fireEvent.click(updateBtn);

    await waitFor(() => {
      expect(updateSW).toHaveBeenCalledWith(false);
    });
    await waitFor(() => {
      expect(replaceMock).toHaveBeenCalledWith(expect.stringContaining('nexus_refresh='));
    });

    Object.defineProperty(window, 'location', {
      writable: true,
      value: originalLocation,
    });
  });

  it('falls back to cache-busting navigation when no updateSW function exists', async () => {
    // jsdom marks location methods as non-configurable, so vi.spyOn fails.
    // Replace the entire location object with a spy-able version instead.
    const replaceMock = vi.fn();
    const reloadMock = vi.fn();
    const originalLocation = window.location;
    Object.defineProperty(window, 'location', {
      writable: true,
      value: { ...originalLocation, replace: replaceMock, reload: reloadMock },
    });

    (window as NexusWindow).__nexus_updatePending = true;
    (window as NexusWindow).__nexus_updateSW = null;

    render(<UpdateAvailableBanner />);

    const updateBtn = screen.getByRole('button', { name: /update now/i });
    fireEvent.click(updateBtn);

    await waitFor(() => {
      expect(replaceMock).toHaveBeenCalledWith(expect.stringContaining('nexus_refresh='));
    });

    // Restore original location
    Object.defineProperty(window, 'location', {
      writable: true,
      value: originalLocation,
    });
  });

  it('starts the update when the mobile-sized banner message area is clicked', async () => {
    const replaceMock = vi.fn();
    const reloadMock = vi.fn();
    const originalLocation = window.location;
    Object.defineProperty(window, 'location', {
      writable: true,
      value: { ...originalLocation, replace: replaceMock, reload: reloadMock },
    });

    (window as NexusWindow).__nexus_updatePending = true;

    render(<UpdateAvailableBanner />);

    const messageButton = screen.getByRole('button', { name: /new version is available/i });
    fireEvent.click(messageButton);

    await waitFor(() => {
      expect(replaceMock).toHaveBeenCalledWith(expect.stringContaining('nexus_refresh='));
    });

    Object.defineProperty(window, 'location', {
      writable: true,
      value: originalLocation,
    });
  });

  it('clears the __nexus_updatePending flag after mounting', () => {
    (window as NexusWindow).__nexus_updatePending = true;
    render(<UpdateAvailableBanner />);
    expect((window as NexusWindow).__nexus_updatePending).toBe(false);
  });

  it('has aria-live="polite" on the banner container', async () => {
    (window as NexusWindow).__nexus_updatePending = true;
    render(<UpdateAvailableBanner />);

    const status = screen.getByRole('status');
    expect(status).toHaveAttribute('aria-live', 'polite');
  });
});
