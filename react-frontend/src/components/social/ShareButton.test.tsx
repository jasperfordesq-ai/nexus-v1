// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast })
);

import { ShareButton } from './ShareButton';

const mockShareToFeed = vi.fn();

function renderShareButton(overrides: Partial<React.ComponentProps<typeof ShareButton>> = {}) {
  return render(
    <ShareButton
      shareToFeed={mockShareToFeed}
      isAuthenticated={true}
      title="Test Title"
      description="Test description"
      {...overrides}
    />
  );
}

describe('ShareButton', () => {
  let originalClipboard: Clipboard;
  let originalShare: typeof navigator.share | undefined;
  let originalCanShare: typeof navigator.canShare | undefined;

  beforeEach(() => {
    vi.clearAllMocks();
    originalClipboard = navigator.clipboard;
    originalShare = navigator.share;
    originalCanShare = navigator.canShare;

    // Stub clipboard
    Object.defineProperty(navigator, 'clipboard', {
      value: { writeText: vi.fn().mockResolvedValue(undefined) },
      configurable: true,
      writable: true,
    });
  });

  afterEach(() => {
    Object.defineProperty(navigator, 'clipboard', {
      value: originalClipboard,
      configurable: true,
      writable: true,
    });
    if (originalShare !== undefined) {
      Object.defineProperty(navigator, 'share', {
        value: originalShare,
        configurable: true,
        writable: true,
      });
    }
    if (originalCanShare !== undefined) {
      Object.defineProperty(navigator, 'canShare', {
        value: originalCanShare,
        configurable: true,
        writable: true,
      });
    }
  });

  it('renders a share button', () => {
    renderShareButton();
    expect(screen.getByRole('button', { name: /share/i })).toBeInTheDocument();
  });

  it('opens the dropdown menu on click', async () => {
    renderShareButton();
    fireEvent.click(screen.getByRole('button', { name: /share/i }));
    await waitFor(() => {
      // "Share to feed" option should appear
      expect(screen.getByText(/share to feed/i)).toBeInTheDocument();
    });
  });

  it('shows "Share to feed" when authenticated', async () => {
    renderShareButton({ isAuthenticated: true });
    fireEvent.click(screen.getByRole('button', { name: /share/i }));
    await waitFor(() => {
      expect(screen.getByText(/share to feed/i)).toBeInTheDocument();
    });
  });

  it('shows "Log in to share" when not authenticated', async () => {
    renderShareButton({ isAuthenticated: false });
    fireEvent.click(screen.getByRole('button', { name: /share/i }));
    await waitFor(() => {
      // i18n key social:login_to_share → "Log in to share"
      expect(screen.getByText(/log in to share/i)).toBeInTheDocument();
    });
  });

  it('calls shareToFeed and shows success toast on success', async () => {
    mockShareToFeed.mockResolvedValue(true);
    renderShareButton({ isAuthenticated: true, canShareToFeed: true });
    fireEvent.click(screen.getByRole('button', { name: /share/i }));
    await waitFor(() => {
      expect(screen.getByText(/share to feed/i)).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText(/share to feed/i));
    await waitFor(() => {
      expect(mockShareToFeed).toHaveBeenCalled();
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when shareToFeed returns false', async () => {
    mockShareToFeed.mockResolvedValue(false);
    renderShareButton({ isAuthenticated: true, canShareToFeed: true });
    fireEvent.click(screen.getByRole('button', { name: /share/i }));
    await waitFor(() => {
      expect(screen.getByText(/share to feed/i)).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText(/share to feed/i));
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows error toast when shareToFeed throws', async () => {
    mockShareToFeed.mockRejectedValue(new Error('Network error'));
    renderShareButton({ isAuthenticated: true, canShareToFeed: true });
    fireEvent.click(screen.getByRole('button', { name: /share/i }));
    await waitFor(() => {
      expect(screen.getByText(/share to feed/i)).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText(/share to feed/i));
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('copies to clipboard and shows success toast when navigator.share is unavailable', async () => {
    // Remove navigator.share so code falls through to clipboard copy
    Object.defineProperty(navigator, 'share', {
      value: undefined,
      configurable: true,
      writable: true,
    });

    renderShareButton();
    fireEvent.click(screen.getByRole('button', { name: /share/i }));
    await waitFor(() => {
      expect(screen.getByText(/copy link/i)).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText(/copy link/i));
    await waitFor(() => {
      expect(navigator.clipboard.writeText).toHaveBeenCalledWith(window.location.href);
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('uses navigator.share when available and canShare returns true', async () => {
    const shareMock = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, 'share', {
      value: shareMock,
      configurable: true,
      writable: true,
    });
    Object.defineProperty(navigator, 'canShare', {
      value: vi.fn().mockReturnValue(true),
      configurable: true,
      writable: true,
    });

    renderShareButton({ title: 'Hello', description: 'World' });
    fireEvent.click(screen.getByRole('button', { name: /share/i }));
    await waitFor(() => {
      expect(screen.getByText(/copy link/i)).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText(/copy link/i));
    await waitFor(() => {
      expect(shareMock).toHaveBeenCalledWith(
        expect.objectContaining({ title: 'Hello', url: window.location.href })
      );
    });
  });

  it('shows disabled reason text when canShareToFeed is false', async () => {
    renderShareButton({
      isAuthenticated: true,
      canShareToFeed: false,
      shareToFeedDisabledReason: 'Already shared',
    });
    fireEvent.click(screen.getByRole('button', { name: /share/i }));
    await waitFor(() => {
      expect(screen.getByText('Already shared')).toBeInTheDocument();
    });
  });

  it('does not call shareToFeed when canShareToFeed is false', async () => {
    // HeroUI disabled items swallow onPress — handler should not be called
    renderShareButton({ isAuthenticated: true, canShareToFeed: false });
    fireEvent.click(screen.getByRole('button', { name: /share/i }));
    await waitFor(() => {
      expect(screen.getAllByRole('menuitem').length).toBeGreaterThan(0);
    });
    // shareToFeed must not have been called just by opening the menu
    expect(mockShareToFeed).not.toHaveBeenCalled();
  });
});
