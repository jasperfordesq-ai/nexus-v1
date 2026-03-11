// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for LoginPage — Passkey/WebAuthn functionality
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, cleanup } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { type ReactNode } from 'react';

// ── Mock webauthn module ────────────────────────────────────────────────────
const mockIsBiometricAvailable = vi.fn();
const mockIsConditionalMediationAvailable = vi.fn();
const mockStartConditionalAuthentication = vi.fn();

vi.mock('@/lib/webauthn', () => ({
  isBiometricAvailable: (...args: unknown[]) => mockIsBiometricAvailable(...args),
  isConditionalMediationAvailable: (...args: unknown[]) => mockIsConditionalMediationAvailable(...args),
  startConditionalAuthentication: (...args: unknown[]) => mockStartConditionalAuthentication(...args),
}));

// ── Mock auth context ───────────────────────────────────────────────────────
const mockLogin = vi.fn();
const mockLoginWithBiometric = vi.fn();
const mockVerify2FA = vi.fn();
const mockClearError = vi.fn();
const mockCancel2FA = vi.fn();
const mockTenantPath = vi.fn((p: string) => `/test${p}`);
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

const authDefaults = {
  status: 'idle' as string,
  error: null as string | null,
  isAuthenticated: false,
  login: mockLogin,
  loginWithBiometric: mockLoginWithBiometric,
  verify2FA: mockVerify2FA,
  clearError: mockClearError,
  cancel2FA: mockCancel2FA,
  twoFactorMethods: [] as string[],
  user: null,
  logout: vi.fn(),
  register: vi.fn(),
  updateUser: vi.fn(),
  refreshUser: vi.fn(),
};

let authOverrides: Partial<typeof authDefaults> = {};

vi.mock('@/contexts', () => ({
  useAuth: () => ({ ...authDefaults, ...authOverrides }),
  useTenant: () => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test', tagline: null },
    branding: { name: 'Test Community', logo_url: null },
    tenantSlug: 'test',
    tenantPath: mockTenantPath,
    isLoading: false,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  }),
  useToast: () => mockToast,
}));

// ── Mock react-i18next ──────────────────────────────────────────────────────
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: { defaultValue?: string }) => opts?.defaultValue || key,
  }),
}));

// ── Mock react-router-dom ───────────────────────────────────────────────────
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const React = await import('react');
  return {
    useNavigate: () => mockNavigate,
    useLocation: () => ({ state: null, pathname: '/login' }),
    useSearchParams: () => [new URLSearchParams(), vi.fn()],
    Link: ({ children, to, ...props }: { children: ReactNode; to: string; [k: string]: unknown }) =>
      React.createElement('a', { href: to, ...props }, children),
    BrowserRouter: ({ children }: { children: ReactNode }) =>
      React.createElement(React.Fragment, null, children),
  };
});

// ── Mock api + tokenManager ─────────────────────────────────────────────────
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    post: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: {
    setTenantId: vi.fn(),
    clearTokens: vi.fn(),
    setAccessToken: vi.fn(),
    setRefreshToken: vi.fn(),
    getTenantId: vi.fn(),
  },
}));

// ── Mock UI dependencies ────────────────────────────────────────────────────
vi.mock('@/components/ui', async () => {
  const React = await import('react');
  return {
    GlassCard: ({ children, className }: { children: ReactNode; className?: string }) => {
      return React.createElement('div', { 'data-testid': 'glass-card', className }, children);
    },
  };
});

vi.mock('@/components/seo', () => ({ PageMeta: () => null }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ── Mock framer-motion ──────────────────────────────────────────────────────
vi.mock('framer-motion', async () => {
  const React = await import('react');
  const handler: ProxyHandler<Record<string, never>> = {
    get: (_target, prop) => {
      return ({ children, _initial, _animate, _exit, _transition, _whileHover, _whileTap, _variants, _layout, ...rest }: Record<string, unknown>) =>
        React.createElement(prop as string, rest, children);
    },
  };
  return {
    motion: new Proxy({} as Record<string, never>, handler),
    AnimatePresence: ({ children }: { children: ReactNode }) => children,
  };
});

// ── Mock HeroUI components ──────────────────────────────────────────────────
vi.mock('@heroui/react', async () => {
  const React = await import('react');
  return {
    Button: ({ children, onPress, isDisabled, isLoading, type, ...props }: Record<string, unknown>) =>
      React.createElement(
        'button',
        {
          onClick: onPress as (() => void) | undefined,
          disabled: isDisabled || isLoading,
          type: type || 'button',
          'data-testid': props['data-testid'],
        },
        isLoading ? 'Loading...' : children as ReactNode,
      ),
    Input: ({ label, value, onChange, placeholder, type, autoComplete }: Record<string, unknown>) =>
      React.createElement('input', {
        'aria-label': label as string,
        value: value as string,
        onChange: onChange as React.ChangeEventHandler<HTMLInputElement>,
        placeholder: placeholder as string,
        type: type as string,
        autoComplete: autoComplete as string,
      }),
    Checkbox: ({ children, isSelected, onValueChange }: Record<string, unknown>) =>
      React.createElement(
        'label',
        null,
        React.createElement('input', {
          type: 'checkbox',
          checked: isSelected as boolean,
          onChange: (e: React.ChangeEvent<HTMLInputElement>) =>
            (onValueChange as (v: boolean) => void)?.(e.target.checked),
        }),
        children as ReactNode,
      ),
    Divider: () => React.createElement('hr'),
    Select: ({ children }: Record<string, unknown>) =>
      React.createElement('div', { 'data-testid': 'select' }, children as ReactNode),
    SelectItem: ({ children }: Record<string, unknown>) =>
      React.createElement('div', null, children as ReactNode),
    HeroUIProvider: ({ children }: { children: ReactNode }) =>
      React.createElement(React.Fragment, null, children),
  };
});

// ── Mock lucide-react icons ─────────────────────────────────────────────────
vi.mock('lucide-react', async () => {
  const React = await import('react');
  const Icon = () => React.createElement('span');
  return {
    Mail: Icon, Lock: Icon, Eye: Icon, EyeOff: Icon,
    Shield: Icon, ArrowLeft: Icon, Loader2: Icon,
    Building2: Icon, Fingerprint: Icon, ShieldAlert: Icon, ShieldX: Icon,
  };
});

// ── Import component under test ─────────────────────────────────────────────
import { LoginPage } from './LoginPage';

// ── Tests ───────────────────────────────────────────────────────────────────
describe('LoginPage — Passkey/WebAuthn functionality', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    authOverrides = {};
    // Defaults: biometric available, conditional mediation not supported
    mockIsBiometricAvailable.mockResolvedValue(true);
    mockIsConditionalMediationAvailable.mockResolvedValue(false);
    mockStartConditionalAuthentication.mockResolvedValue(null);
    mockLoginWithBiometric.mockResolvedValue({ success: false, error: 'cancelled' });
  });

  afterEach(() => {
    cleanup();
  });

  // ─── 1. Passkey button renders when biometric available + tenant selected ──
  it('renders passkey button when biometric is available and tenant is selected', async () => {
    render(<LoginPage />);

    await waitFor(() => {
      expect(screen.getByText('Sign in with a passkey')).toBeDefined();
    });
  });

  // ─── 2. Passkey button NOT rendered when biometric unavailable ─────────────
  it('does NOT render passkey button when biometric is unavailable', async () => {
    mockIsBiometricAvailable.mockResolvedValue(false);

    render(<LoginPage />);

    // Wait for the async biometric check to settle
    await waitFor(() => {
      expect(mockIsBiometricAvailable).toHaveBeenCalled();
    });

    expect(screen.queryByText('Sign in with a passkey')).toBeNull();
  });

  // ─── 3. handleBiometricLogin calls loginWithBiometric (no email) ───────────
  it('calls loginWithBiometric with undefined when no email entered', async () => {
    mockLoginWithBiometric.mockResolvedValue({ success: false, error: 'cancelled' });
    const user = userEvent.setup();

    render(<LoginPage />);

    await waitFor(() => {
      expect(screen.getByText('Sign in with a passkey')).toBeDefined();
    });

    await user.click(screen.getByText('Sign in with a passkey'));

    expect(mockLoginWithBiometric).toHaveBeenCalledWith(undefined);
  });

  // ─── 4. handleBiometricLogin passes email when filled ──────────────────────
  it('calls loginWithBiometric with email when email field is filled', async () => {
    mockLoginWithBiometric.mockResolvedValue({ success: false, error: 'cancelled' });
    const user = userEvent.setup();

    render(<LoginPage />);

    await waitFor(() => {
      expect(screen.getByText('Sign in with a passkey')).toBeDefined();
    });

    // Set email value via fireEvent.change to avoid per-keystroke re-render issues
    const emailInput = document.querySelector('input[autocomplete="username webauthn"]') as HTMLInputElement;
    expect(emailInput).not.toBeNull();
    const { fireEvent } = await import('@testing-library/react');
    fireEvent.change(emailInput, { target: { value: 'alice@example.com' } });

    await user.click(screen.getByText('Sign in with a passkey'));

    expect(mockLoginWithBiometric).toHaveBeenCalledWith('alice@example.com');
  });

  // ─── 5. Conditional mediation starts when tenant selected ──────────────────
  it('starts conditional mediation when tenant is selected and mediation is available', async () => {
    mockIsConditionalMediationAvailable.mockResolvedValue(true);
    mockStartConditionalAuthentication.mockResolvedValue(null);

    render(<LoginPage />);

    await waitFor(() => {
      expect(mockIsConditionalMediationAvailable).toHaveBeenCalled();
    });

    await waitFor(() => {
      expect(mockStartConditionalAuthentication).toHaveBeenCalledWith(
        expect.any(AbortSignal),
      );
    });
  });

  // ─── 6. Conditional mediation aborted on unmount ───────────────────────────
  it('aborts conditional mediation on unmount', async () => {
    mockIsConditionalMediationAvailable.mockResolvedValue(true);
    // Keep the conditional auth pending so the abort ref stays active
    mockStartConditionalAuthentication.mockReturnValue(new Promise(() => {}));

    const abortSpy = vi.spyOn(AbortController.prototype, 'abort');

    const { unmount } = render(<LoginPage />);

    await waitFor(() => {
      expect(mockStartConditionalAuthentication).toHaveBeenCalled();
    });

    unmount();

    expect(abortSpy).toHaveBeenCalled();
    abortSpy.mockRestore();
  });

  // ─── 7. Error toast when no passkey found ──────────────────────────────────
  it('shows error toast when loginWithBiometric returns "Credential not found"', async () => {
    mockLoginWithBiometric.mockResolvedValue({
      success: false,
      error: 'Credential not found',
    });
    const user = userEvent.setup();

    render(<LoginPage />);

    await waitFor(() => {
      expect(screen.getByText('Sign in with a passkey')).toBeDefined();
    });

    await user.click(screen.getByText('Sign in with a passkey'));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalledWith(
        'No passkey found for this account. Log in with your password, then go to Settings to register a passkey.',
      );
    });
  });

  // ─── 8. Successful passkey login navigates to dashboard ────────────────────
  it('navigates to dashboard on successful passkey login', async () => {
    mockLoginWithBiometric.mockResolvedValue({ success: true });
    const user = userEvent.setup();

    render(<LoginPage />);

    await waitFor(() => {
      expect(screen.getByText('Sign in with a passkey')).toBeDefined();
    });

    await user.click(screen.getByText('Sign in with a passkey'));

    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('/test/dashboard', { replace: true });
    });
  });
});
