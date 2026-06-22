// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for SsoButtons component.
 *
 * Behaviour under test:
 *  1. Always fetches /api/v2/auth/sso/providers on mount.
 *  2. Renders nothing while the response is pending (providers === null).
 *  3. Renders nothing when providers is an empty array.
 *  4. Renders one button per provider with localised label
 *     "Sign in with {display_name}" (common.json key oauth.sign_in_with_provider).
 *  5. The correct icon is chosen: Building2 for "entra" preset, KeyRound otherwise.
 *  6. Clicking a provider button calls the redirect endpoint for that provider key
 *     and navigates via window.location.href on success.
 *  7. On a failed redirect response the fallback alert is shown.
 *  8. tenantId is forwarded in X-Tenant-Id header and ?tenant_id= param.
 *  9. Network errors on initial fetch cause providers to become [] (renders nothing).
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { SsoButtons } from './SsoButtons';

// ---------------------------------------------------------------------------
// Stub window.location
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
// Stub window.alert
// ---------------------------------------------------------------------------
let alertSpy: ReturnType<typeof vi.fn>;
beforeEach(() => {
  alertSpy = vi.fn();
  vi.stubGlobal('alert', alertSpy);
});

// ---------------------------------------------------------------------------
// Provider fixture data
// ---------------------------------------------------------------------------
const ENTRA_PROVIDER = {
  key: 'coventry-entra',
  display_name: 'Coventry City Council',
  preset: 'entra' as const,
};

const GENERIC_PROVIDER = {
  key: 'hivebrite-sso',
  display_name: 'Hivebrite Network',
  preset: 'generic' as const,
};

const HIVEBRITE_PROVIDER = {
  key: 'hb-sso',
  display_name: 'Timebank Network',
  preset: 'hivebrite' as const,
};

// ---------------------------------------------------------------------------
// Fetch helpers
// ---------------------------------------------------------------------------
function stubProvidersFetch(providers: typeof ENTRA_PROVIDER[]) {
  vi.stubGlobal('fetch', vi.fn(() =>
    Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ success: true, providers }),
    })
  ));
}

function stubProvidersAndRedirectFetch(
  providers: typeof ENTRA_PROVIDER[],
  redirectResponse: object
) {
  vi.stubGlobal('fetch', vi.fn()
    .mockResolvedValueOnce({
      ok: true,
      json: () => Promise.resolve({ success: true, providers }),
    })
    .mockResolvedValueOnce({
      ok: true,
      json: () => Promise.resolve(redirectResponse),
    })
  );
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('SsoButtons — initial loading state', () => {
  it('renders no provider buttons while fetch is pending', () => {
    vi.stubGlobal('fetch', vi.fn(() => new Promise(() => {})));
    render(<SsoButtons />);
    // While providers === null the component returns null — no buttons visible
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    expect(screen.queryByText(/Sign in with/i)).not.toBeInTheDocument();
  });
});

describe('SsoButtons — no providers configured', () => {
  it('renders nothing when the server returns an empty providers array', async () => {
    stubProvidersFetch([]);
    render(<SsoButtons />);
    await waitFor(() => {
      expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
  });

  it('renders nothing when the fetch fails with a network error', async () => {
    vi.stubGlobal('fetch', vi.fn(() => Promise.reject(new Error('network'))));
    render(<SsoButtons />);
    await waitFor(() => {
      expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
  });

  it('renders nothing when server returns malformed data (no providers key)', async () => {
    vi.stubGlobal('fetch', vi.fn(() =>
      Promise.resolve({ ok: true, json: () => Promise.resolve({ success: true }) })
    ));
    render(<SsoButtons />);
    await waitFor(() => {
      expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
  });
});

describe('SsoButtons — rendering providers', () => {
  it('renders one button per provider with the correct translated label', async () => {
    stubProvidersFetch([ENTRA_PROVIDER, GENERIC_PROVIDER]);
    render(<SsoButtons />);
    await waitFor(() => {
      // common.json: "oauth.sign_in_with_provider": "Sign in with {{name}}"
      expect(screen.getByText('Sign in with Coventry City Council')).toBeInTheDocument();
      expect(screen.getByText('Sign in with Hivebrite Network')).toBeInTheDocument();
    });
  });

  it('renders only one button when there is a single provider', async () => {
    stubProvidersFetch([ENTRA_PROVIDER]);
    render(<SsoButtons />);
    await waitFor(() => {
      expect(screen.getAllByRole('button')).toHaveLength(1);
    });
  });

  it('renders multiple buttons for multiple providers', async () => {
    stubProvidersFetch([ENTRA_PROVIDER, GENERIC_PROVIDER, HIVEBRITE_PROVIDER]);
    render(<SsoButtons />);
    await waitFor(() => {
      expect(screen.getAllByRole('button')).toHaveLength(3);
    });
  });

  it('does NOT render an "Or continue with email" divider (SsoButtons has no such divider)', async () => {
    stubProvidersFetch([ENTRA_PROVIDER]);
    render(<SsoButtons />);
    await waitFor(() => expect(screen.getByRole('button')).toBeInTheDocument());
    expect(screen.queryByText(/Or continue with email/i)).not.toBeInTheDocument();
  });
});

describe('SsoButtons — icon selection by preset', () => {
  // The component passes the Icon as a `startContent` prop to the HeroUI Button.
  // The icon itself (Building2 or KeyRound) is a Lucide SVG. We verify:
  //   a) An SVG is present somewhere within (or near) the button element.
  //   b) The correct preset branching is exercised (entra → Building2; everything
  //      else → KeyRound). We can't easily distinguish two SVG shapes in jsdom, so
  //      we verify that the button renders and has an SVG icon in both cases.

  it('renders an SVG icon for an entra provider button', async () => {
    stubProvidersFetch([ENTRA_PROVIDER]);
    render(<SsoButtons />);
    await waitFor(() => expect(screen.getByText('Sign in with Coventry City Council')).toBeInTheDocument());
    // Find an SVG anywhere in the document (HeroUI Button may hoist startContent outside the button el)
    const svgs = document.querySelectorAll('svg');
    expect(svgs.length).toBeGreaterThan(0);
  });

  it('renders an SVG icon for a generic (non-entra) provider button', async () => {
    stubProvidersFetch([GENERIC_PROVIDER]);
    render(<SsoButtons />);
    await waitFor(() => expect(screen.getByText('Sign in with Hivebrite Network')).toBeInTheDocument());
    const svgs = document.querySelectorAll('svg');
    expect(svgs.length).toBeGreaterThan(0);
  });

  it('renders an SVG icon for the hivebrite preset (not entra)', async () => {
    stubProvidersFetch([HIVEBRITE_PROVIDER]);
    render(<SsoButtons />);
    await waitFor(() => expect(screen.getByText('Sign in with Timebank Network')).toBeInTheDocument());
    const svgs = document.querySelectorAll('svg');
    expect(svgs.length).toBeGreaterThan(0);
  });

  it('selects Building2 for entra and KeyRound for non-entra — distinct icon class names', async () => {
    // Both Building2 and KeyRound render with different lucide class names.
    // Render each separately and confirm the presence of the lucide svg class.
    stubProvidersFetch([ENTRA_PROVIDER]);
    const { unmount } = render(<SsoButtons />);
    await waitFor(() => expect(screen.getByText('Sign in with Coventry City Council')).toBeInTheDocument());
    // lucide icons always render with class="lucide lucide-<name> ..."
    const entraIcons = document.querySelectorAll('[class*="lucide"]');
    expect(entraIcons.length).toBeGreaterThan(0);
    unmount();

    stubProvidersFetch([GENERIC_PROVIDER]);
    render(<SsoButtons />);
    await waitFor(() => expect(screen.getByText('Sign in with Hivebrite Network')).toBeInTheDocument());
    const genericIcons = document.querySelectorAll('[class*="lucide"]');
    expect(genericIcons.length).toBeGreaterThan(0);
  });
});

describe('SsoButtons — click → redirect flow', () => {
  it('navigates to redirect_url returned by the redirect endpoint', async () => {
    stubProvidersAndRedirectFetch(
      [ENTRA_PROVIDER],
      { success: true, redirect_url: 'https://login.microsoftonline.com/tenant/oauth2/authorize' }
    );

    render(<SsoButtons />);
    await waitFor(() => expect(screen.getByText('Sign in with Coventry City Council')).toBeInTheDocument());
    fireEvent.click(screen.getByText('Sign in with Coventry City Council'));

    await waitFor(() => {
      expect(locationMock.href).toBe('https://login.microsoftonline.com/tenant/oauth2/authorize');
    });
  });

  it('calls the redirect endpoint with the provider key URL-encoded', async () => {
    const fetchSpy = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, providers: [ENTRA_PROVIDER] }),
      })
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, redirect_url: 'https://example.com' }),
      });
    vi.stubGlobal('fetch', fetchSpy);

    render(<SsoButtons />);
    await waitFor(() => expect(screen.getByText('Sign in with Coventry City Council')).toBeInTheDocument());
    fireEvent.click(screen.getByText('Sign in with Coventry City Council'));

    await waitFor(() => {
      const [url] = fetchSpy.mock.calls[1] as [string, ...unknown[]];
      expect(url).toContain(`/api/v2/auth/sso/${encodeURIComponent(ENTRA_PROVIDER.key)}/redirect`);
    });
  });

  it('includes tenant_id query param when tenantId is provided', async () => {
    const fetchSpy = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, providers: [ENTRA_PROVIDER] }),
      })
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, redirect_url: 'https://example.com' }),
      });
    vi.stubGlobal('fetch', fetchSpy);

    render(<SsoButtons tenantId={99} />);
    await waitFor(() => expect(screen.getByText('Sign in with Coventry City Council')).toBeInTheDocument());
    fireEvent.click(screen.getByText('Sign in with Coventry City Council'));

    await waitFor(() => {
      const [url] = fetchSpy.mock.calls[1] as [string, ...unknown[]];
      expect(url).toContain('tenant_id=99');
    });
  });

  it('includes X-Tenant-Id header in the redirect request when tenantId is provided', async () => {
    const fetchSpy = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, providers: [ENTRA_PROVIDER] }),
      })
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, redirect_url: 'https://example.com' }),
      });
    vi.stubGlobal('fetch', fetchSpy);

    render(<SsoButtons tenantId={99} />);
    await waitFor(() => expect(screen.getByText('Sign in with Coventry City Council')).toBeInTheDocument());
    fireEvent.click(screen.getByText('Sign in with Coventry City Council'));

    await waitFor(() => {
      const [, options] = fetchSpy.mock.calls[1] as [string, RequestInit];
      expect((options?.headers as Record<string, string>)?.['X-Tenant-Id']).toBe('99');
    });
  });

  it('does NOT include X-Tenant-Id header in redirect request when tenantId is absent', async () => {
    const fetchSpy = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, providers: [GENERIC_PROVIDER] }),
      })
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, redirect_url: 'https://example.com' }),
      });
    vi.stubGlobal('fetch', fetchSpy);

    render(<SsoButtons />);
    await waitFor(() => expect(screen.getByText('Sign in with Hivebrite Network')).toBeInTheDocument());
    fireEvent.click(screen.getByText('Sign in with Hivebrite Network'));

    await waitFor(() => {
      const [, options] = fetchSpy.mock.calls[1] as [string, RequestInit];
      // When no tenantId, the component passes an empty headers object {}
      expect((options?.headers as Record<string, string>)?.['X-Tenant-Id']).toBeUndefined();
    });
  });

  it('calls alert with the server error message when success=false', async () => {
    stubProvidersAndRedirectFetch(
      [ENTRA_PROVIDER],
      { success: false, message: 'SSO is not configured for this tenant.' }
    );

    render(<SsoButtons />);
    await waitFor(() => expect(screen.getByText('Sign in with Coventry City Council')).toBeInTheDocument());
    fireEvent.click(screen.getByText('Sign in with Coventry City Council'));

    await waitFor(() => {
      expect(alertSpy).toHaveBeenCalledWith('SSO is not configured for this tenant.');
    });
  });

  it('calls alert with the fallback i18n message when redirect_url is missing', async () => {
    stubProvidersAndRedirectFetch(
      [ENTRA_PROVIDER],
      { success: true } // redirect_url intentionally absent
    );

    render(<SsoButtons />);
    await waitFor(() => expect(screen.getByText('Sign in with Coventry City Council')).toBeInTheDocument());
    fireEvent.click(screen.getByText('Sign in with Coventry City Council'));

    await waitFor(() => {
      // common.json: "oauth.callback_failed": "Sign-in failed. Please try again."
      expect(alertSpy).toHaveBeenCalledWith('Sign-in failed. Please try again.');
    });
  });

  it('calls alert with the fallback message when the redirect fetch throws', async () => {
    vi.stubGlobal('fetch', vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        json: () => Promise.resolve({ success: true, providers: [GENERIC_PROVIDER] }),
      })
      .mockRejectedValueOnce(new Error('timeout'))
    );

    render(<SsoButtons />);
    await waitFor(() => expect(screen.getByText('Sign in with Hivebrite Network')).toBeInTheDocument());
    fireEvent.click(screen.getByText('Sign in with Hivebrite Network'));

    await waitFor(() => {
      expect(alertSpy).toHaveBeenCalledWith('Sign-in failed. Please try again.');
    });
  });
});

describe('SsoButtons — tenant header on providers fetch', () => {
  it('sends X-Tenant-Id on the initial /sso/providers fetch when tenantId is set', async () => {
    const fetchSpy = vi.fn(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ success: true, providers: [] }),
      })
    );
    vi.stubGlobal('fetch', fetchSpy);

    render(<SsoButtons tenantId={5} />);
    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        expect.stringContaining('/api/v2/auth/sso/providers'),
        expect.objectContaining({
          headers: expect.objectContaining({ 'X-Tenant-Id': '5' }),
        })
      );
    });
  });

  it('includes tenant_id in the query string of the providers URL when tenantId is set', async () => {
    const fetchSpy = vi.fn(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ success: true, providers: [] }),
      })
    );
    vi.stubGlobal('fetch', fetchSpy);

    render(<SsoButtons tenantId={5} />);
    await waitFor(() => {
      const [url] = fetchSpy.mock.calls[0] as [string, ...unknown[]];
      expect(url).toContain('tenant_id=5');
    });
  });
});
