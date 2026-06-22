// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for OAuthButtons component.
 *
 * Behaviour under test:
 *  1. When `enabledProviders` is given, skip the /enabled-providers fetch and
 *     render only the listed providers.
 *  2. When `enabledProviders` is omitted, fetch /api/v2/auth/oauth/enabled-providers
 *     and render the returned providers (or nothing on empty / error).
 *  3. Clicking a provider button fetches /api/v2/auth/oauth/{provider}/redirect
 *     and navigates via window.location.href on success.
 *  4. On a failed redirect response, alert() is called with the fallback message.
 *  5. tenant_id / X-Tenant-Id forwarding.
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { OAuthButtons } from './OAuthButtons';

// ---------------------------------------------------------------------------
// Stub window.location so we can assert href assignments without navigation
// ---------------------------------------------------------------------------
const locationMock = { href: '' };
beforeEach(() => {
  Object.defineProperty(window, 'location', {
    value: locationMock,
    writable: true,
    configurable: true,
  });
  locationMock.href = '';
});

afterEach(() => {
  vi.unstubAllGlobals();
  vi.clearAllMocks();
});

// ---------------------------------------------------------------------------
// Stub window.alert (used by the component on redirect failure)
// ---------------------------------------------------------------------------
let alertSpy: ReturnType<typeof vi.fn>;
beforeEach(() => {
  alertSpy = vi.fn();
  vi.stubGlobal('alert', alertSpy);
});

// ---------------------------------------------------------------------------
// fetch helper — returns a mock Response-like object
// ---------------------------------------------------------------------------
function mockFetch(responses: Array<{ ok?: boolean; json: () => Promise<unknown> }>) {
  let callCount = 0;
  vi.stubGlobal('fetch', vi.fn(() => {
    const r = responses[callCount] ?? responses[responses.length - 1];
    callCount++;
    return Promise.resolve({ ok: r.ok ?? true, json: r.json });
  }));
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('OAuthButtons — prop-driven providers (no fetch)', () => {
  it('renders buttons for each enabled provider', async () => {
    render(<OAuthButtons enabledProviders={['google', 'apple', 'facebook']} />);
    await waitFor(() => {
      expect(screen.getByText('Continue with Google')).toBeInTheDocument();
      expect(screen.getByText('Continue with Apple')).toBeInTheDocument();
      expect(screen.getByText('Continue with Facebook')).toBeInTheDocument();
    });
  });

  it('renders only google when only google is enabled', async () => {
    render(<OAuthButtons enabledProviders={['google']} />);
    await waitFor(() => {
      expect(screen.getByText('Continue with Google')).toBeInTheDocument();
      expect(screen.queryByText('Continue with Apple')).not.toBeInTheDocument();
      expect(screen.queryByText('Continue with Facebook')).not.toBeInTheDocument();
    });
  });

  it('renders nothing when enabledProviders is an empty array', () => {
    render(<OAuthButtons enabledProviders={[]} />);
    expect(screen.queryByText(/Continue with/i)).not.toBeInTheDocument();
  });

  it('renders the "Or continue with email" divider when at least one provider is visible', async () => {
    render(<OAuthButtons enabledProviders={['google']} />);
    await waitFor(() => {
      expect(screen.getByText(/Or continue with email/i)).toBeInTheDocument();
    });
  });

  it('does NOT render the "Or continue with email" divider when no providers are visible', () => {
    render(<OAuthButtons enabledProviders={[]} />);
    expect(screen.queryByText(/Or continue with email/i)).not.toBeInTheDocument();
  });
});

describe('OAuthButtons — server-fetched providers', () => {
  it('renders no provider buttons while waiting for the server response (serverProviders === null)', () => {
    // fetch never resolves → component stays in null state → no provider buttons rendered
    vi.stubGlobal('fetch', vi.fn(() => new Promise(() => {})));
    render(<OAuthButtons />);
    // While pending, no "Continue with" buttons should be present
    expect(screen.queryByText(/Continue with/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/Or continue with email/i)).not.toBeInTheDocument();
  });

  it('renders provider buttons returned by the server', async () => {
    mockFetch([{
      json: () => Promise.resolve({ success: true, providers: ['google', 'facebook'] }),
    }]);
    render(<OAuthButtons />);
    await waitFor(() => {
      expect(screen.getByText('Continue with Google')).toBeInTheDocument();
      expect(screen.getByText('Continue with Facebook')).toBeInTheDocument();
      expect(screen.queryByText('Continue with Apple')).not.toBeInTheDocument();
    });
  });

  it('renders nothing when server returns an empty providers array', async () => {
    mockFetch([{
      json: () => Promise.resolve({ success: true, providers: [] }),
    }]);
    render(<OAuthButtons />);
    await waitFor(() => {
      expect(screen.queryByText(/Continue with/i)).not.toBeInTheDocument();
    });
  });

  it('renders nothing when the fetch fails (network error)', async () => {
    vi.stubGlobal('fetch', vi.fn(() => Promise.reject(new Error('network error'))));
    render(<OAuthButtons />);
    await waitFor(() => {
      expect(screen.queryByText(/Continue with/i)).not.toBeInTheDocument();
    });
  });

  it('includes X-Tenant-Id header and tenant_id param when tenantId is set', async () => {
    const fetchSpy = vi.fn(() => Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ success: true, providers: [] }),
    }));
    vi.stubGlobal('fetch', fetchSpy);
    render(<OAuthButtons tenantId={42} />);
    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        '/api/v2/auth/oauth/enabled-providers',
        expect.objectContaining({
          headers: expect.objectContaining({ 'X-Tenant-Id': '42' }),
        })
      );
    });
  });
});

describe('OAuthButtons — click → redirect flow', () => {
  it('navigates to the redirect_url on successful redirect fetch', async () => {
    // First call: enabled-providers check
    // Second call: redirect endpoint
    const fetchSpy = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, providers: ['google'] }),
      })
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, redirect_url: 'https://accounts.google.com/o/oauth2/auth?state=abc' }),
      });
    vi.stubGlobal('fetch', fetchSpy);

    render(<OAuthButtons />);
    await waitFor(() => expect(screen.getByText('Continue with Google')).toBeInTheDocument());

    fireEvent.click(screen.getByText('Continue with Google'));

    await waitFor(() => {
      expect(locationMock.href).toBe('https://accounts.google.com/o/oauth2/auth?state=abc');
    });
  });

  it('calls startFlow with correct URL including intent=login (default)', async () => {
    const fetchSpy = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, providers: ['google'] }),
      })
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, redirect_url: 'https://example.com' }),
      });
    vi.stubGlobal('fetch', fetchSpy);

    render(<OAuthButtons intent="login" />);
    await waitFor(() => expect(screen.getByText('Continue with Google')).toBeInTheDocument());
    fireEvent.click(screen.getByText('Continue with Google'));

    await waitFor(() => {
      const [url] = fetchSpy.mock.calls[1] as [string, ...unknown[]];
      expect(url).toContain('/api/v2/auth/oauth/google/redirect');
      expect(url).toContain('intent=login');
    });
  });

  it('uses intent=register when prop is set', async () => {
    const fetchSpy = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, providers: ['google'] }),
      })
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, redirect_url: 'https://example.com' }),
      });
    vi.stubGlobal('fetch', fetchSpy);

    render(<OAuthButtons intent="register" />);
    await waitFor(() => expect(screen.getByText('Continue with Google')).toBeInTheDocument());
    fireEvent.click(screen.getByText('Continue with Google'));

    await waitFor(() => {
      const [url] = fetchSpy.mock.calls[1] as [string, ...unknown[]];
      expect(url).toContain('intent=register');
    });
  });

  it('includes tenant_id in the redirect URL when tenantId prop is set', async () => {
    const fetchSpy = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, providers: ['google'] }),
      })
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, redirect_url: 'https://example.com' }),
      });
    vi.stubGlobal('fetch', fetchSpy);

    render(<OAuthButtons enabledProviders={['google']} tenantId={7} />);
    await waitFor(() => expect(screen.getByText('Continue with Google')).toBeInTheDocument());
    fireEvent.click(screen.getByText('Continue with Google'));

    await waitFor(() => {
      const [url] = fetchSpy.mock.calls[0] as [string, ...unknown[]];
      expect(url).toContain('tenant_id=7');
    });
  });

  it('calls alert with the server message when success=false', async () => {
    const fetchSpy = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, providers: ['google'] }),
      })
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: false, message: 'OAuth is disabled for this tenant.' }),
      });
    vi.stubGlobal('fetch', fetchSpy);

    render(<OAuthButtons />);
    await waitFor(() => expect(screen.getByText('Continue with Google')).toBeInTheDocument());
    fireEvent.click(screen.getByText('Continue with Google'));

    await waitFor(() => {
      expect(alertSpy).toHaveBeenCalledWith('OAuth is disabled for this tenant.');
    });
  });

  it('calls alert with the fallback message when redirect fetch fails with network error', async () => {
    const fetchSpy = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, providers: ['apple'] }),
      })
      .mockRejectedValueOnce(new Error('network failure'));
    vi.stubGlobal('fetch', fetchSpy);

    render(<OAuthButtons />);
    await waitFor(() => expect(screen.getByText('Continue with Apple')).toBeInTheDocument());
    fireEvent.click(screen.getByText('Continue with Apple'));

    await waitFor(() => {
      // Fallback message from common.json: "Sign-in failed. Please try again."
      expect(alertSpy).toHaveBeenCalledWith('Sign-in failed. Please try again.');
    });
  });
});
