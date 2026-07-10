// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';
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

vi.mock('@/contexts', () => createMockContexts());

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// Mock react-router-dom, preserving real exports but overriding useParams
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return {
    ...actual,
    useParams: vi.fn(() => ({ token: 'test-token-abc123' })),
  };
});

import { api } from '@/lib/api';
import { useParams } from 'react-router-dom';
import GuardianConsentVerifyPage from './GuardianConsentVerifyPage';

describe('GuardianConsentVerifyPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useParams).mockReturnValue({ token: 'test-token-abc123' });
  });

  it('renders the confirm state with a confirm button when token is present', () => {
    render(<GuardianConsentVerifyPage />);
    // Should show the confirm button (not success/error yet)
    expect(screen.getByRole('button')).toBeInTheDocument();
  });

  it('renders error state immediately when no token is provided', () => {
    vi.mocked(useParams).mockReturnValue({ token: undefined });
    render(<GuardianConsentVerifyPage />);
    // With no token, initial state is 'error' — no confirm button shown
    expect(screen.queryByRole('button', { name: /confirm/i })).not.toBeInTheDocument();
  });

  it('shows loading/submitting state while API call is in-flight', async () => {
    let resolvePromise!: (v: unknown) => void;
    vi.mocked(api.post).mockReturnValue(
      new Promise((res) => { resolvePromise = res; }) as ReturnType<typeof api.post>
    );

    render(<GuardianConsentVerifyPage />);
    fireEvent.click(screen.getByRole('button'));

    // After clicking, submitting state should show a spinner
    await waitFor(() => {
      // The Spinner renders with aria-label for the loading text
      const spinner = document.querySelector('[aria-label]');
      expect(spinner).toBeTruthy();
    });

    // Resolve to prevent dangling promise warning
    resolvePromise({ success: true });
  });

  it('shows success state after API returns success', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: true });

    render(<GuardianConsentVerifyPage />);
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      // The grant must be a POST — a GET on the verify URL is read-only.
      expect(api.post).toHaveBeenCalledWith(
        expect.stringContaining('guardian-consents/verify/test-token-abc123'),
        undefined,
        expect.objectContaining({ skipAuth: true }),
      );
    });

    // After success, the success heading/content should appear
    await waitFor(() => {
      // No confirm button present in success state
      expect(screen.queryByRole('button', { name: /confirm/i })).not.toBeInTheDocument();
    });
  });

  it('shows error state after API returns success:false', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: false, error: 'Token expired' });

    render(<GuardianConsentVerifyPage />);
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalled();
    });

    // In error state the confirm button disappears
    await waitFor(() => {
      expect(screen.queryByRole('button', { name: /confirm/i })).not.toBeInTheDocument();
    });
  });

  it('shows error state after API throws', async () => {
    vi.mocked(api.post).mockRejectedValueOnce(new Error('Network error'));

    render(<GuardianConsentVerifyPage />);
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalled();
    });

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: /confirm/i })).not.toBeInTheDocument();
    });
  });

  it('renders a home/close link regardless of state', () => {
    render(<GuardianConsentVerifyPage />);
    expect(screen.getByRole('link')).toBeInTheDocument();
  });

  it('calls the correct API endpoint with the token from useParams', async () => {
    vi.mocked(useParams).mockReturnValue({ token: 'my-special-token' });
    vi.mocked(api.post).mockResolvedValueOnce({ success: true });

    render(<GuardianConsentVerifyPage />);
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        expect.stringContaining('my-special-token'),
        undefined,
        expect.any(Object),
      );
    });
  });

  it('announces the error result via an assertive alert region (WCAG 4.1.3)', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: false, error: 'Token expired' });

    render(<GuardianConsentVerifyPage />);
    fireEvent.click(screen.getByRole('button'));

    const alert = await screen.findByRole('alert');
    expect(alert).toBeInTheDocument();
    // The error heading is inside the live region so it is announced.
    expect(alert.querySelector('h1')).toBeTruthy();
  });

  it('moves focus to the live result region when the action resolves', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: true });

    render(<GuardianConsentVerifyPage />);
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(document.activeElement?.getAttribute('aria-live')).toBeTruthy();
    });
  });
});
