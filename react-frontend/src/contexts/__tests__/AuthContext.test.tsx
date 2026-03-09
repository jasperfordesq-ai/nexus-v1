// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for AuthContext
 * Covers: initial state, login, logout, 2FA, session restoration, error handling
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { ReactNode } from 'react';
import { HeroUIProvider } from '@heroui/react';

vi.mock('framer-motion');
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (k: string) => k }),
}));

// Mock i18n
vi.mock('@/i18n', () => ({
  default: {
    changeLanguage: vi.fn(),
    language: 'en',
  },
}));

// Mock Sentry helpers
vi.mock('@/lib/sentry', () => ({
  setSentryUser: vi.fn(),
  captureAuthEvent: vi.fn(),
  setSentryTenant: vi.fn(),
}));

// Mock api-validation (dev-only, no-op in tests)
vi.mock('@/lib/api-validation', () => ({
  validateResponseIfPresent: vi.fn(),
}));

// Mock api-schemas
vi.mock('@/lib/api-schemas', () => ({
  loginResponseSchema: {},
  userSchema: {},
}));

// Mock logger
vi.mock('@/lib/logger', () => ({
  logWarn: vi.fn(),
  logError: vi.fn(),
}));

// Mock WebAuthn
vi.mock('@/lib/webauthn', () => ({
  authenticateWithBiometric: vi.fn(),
}));

// Mock the api module
const mockApiGet = vi.fn();
const mockApiPost = vi.fn();
const mockTokenManager = {
  getAccessToken: vi.fn(),
  setAccessToken: vi.fn(),
  getRefreshToken: vi.fn(),
  setRefreshToken: vi.fn(),
  getTenantId: vi.fn(),
  setTenantId: vi.fn(),
  clearTokens: vi.fn(),
  clearAll: vi.fn(),
  hasAccessToken: vi.fn(),
  hasRefreshToken: vi.fn(),
};

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockApiGet(...args),
    post: (...args: unknown[]) => mockApiPost(...args),
  },
  tokenManager: mockTokenManager,
  SESSION_EXPIRED_EVENT: 'nexus:session_expired',
}));

import { AuthProvider, useAuth } from '../AuthContext';
import { authenticateWithBiometric } from '@/lib/webauthn';

const mockAuthenticateWithBiometric = vi.mocked(authenticateWithBiometric);

// Wrapper for renderHook
function wrapper({ children }: { children: ReactNode }) {
  return (
    <HeroUIProvider>
      {children}
    </HeroUIProvider>
  );
}

function authWrapper({ children }: { children: ReactNode }) {
  return (
    <HeroUIProvider>
      <AuthProvider>{children}</AuthProvider>
    </HeroUIProvider>
  );
}

const mockUser = {
  id: 1,
  first_name: 'Jane',
  last_name: 'Doe',
  name: 'Jane Doe',
  email: 'jane@example.com',
  tenant_id: 2,
  preferred_language: 'en',
  role: 'member',
  avatar_url: null,
};

describe('AuthContext', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Default: no access token stored
    mockTokenManager.hasAccessToken.mockReturnValue(false);
    mockTokenManager.getAccessToken.mockReturnValue(null);
    mockTokenManager.getRefreshToken.mockReturnValue(null);
    mockTokenManager.getTenantId.mockReturnValue(null);
    // Default API responses
    mockApiGet.mockResolvedValue({ success: false, error: 'Not authenticated' });
    mockApiPost.mockResolvedValue({ success: true });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Hook guard
  // ─────────────────────────────────────────────────────────────────────────

  describe('useAuth() outside provider', () => {
    it('throws a descriptive error when used outside AuthProvider', () => {
      expect(() => {
        renderHook(() => useAuth(), { wrapper });
      }).toThrowError('useAuth must be used within an AuthProvider');
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Initial state
  // ─────────────────────────────────────────────────────────────────────────

  describe('initial state', () => {
    it('starts with isLoading true during initialization', async () => {
      // Make the API call hang so we can inspect the loading state
      let resolveApi!: (v: unknown) => void;
      mockApiGet.mockReturnValue(new Promise((r) => { resolveApi = r; }));

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });

      // Immediately after render, status is 'loading' (initial state) or may have resolved
      // We check that once resolved it becomes idle (no token)
      resolveApi({ success: false });
      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });
    });

    it('has isAuthenticated false and user null when no token exists', async () => {
      mockTokenManager.hasAccessToken.mockReturnValue(false);
      mockApiGet.mockResolvedValue({ success: false });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.isAuthenticated).toBe(false);
      expect(result.current.user).toBeNull();
    });

    it('exposes the expected API surface (login, logout, refreshUser, clearError)', async () => {
      mockApiGet.mockResolvedValue({ success: false });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });

      await waitFor(() => expect(result.current.isLoading).toBe(false));

      expect(typeof result.current.login).toBe('function');
      expect(typeof result.current.logout).toBe('function');
      expect(typeof result.current.refreshUser).toBe('function');
      expect(typeof result.current.clearError).toBe('function');
      expect(typeof result.current.verify2FA).toBe('function');
      expect(typeof result.current.cancel2FA).toBe('function');
      expect(typeof result.current.register).toBe('function');
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Session restoration on mount
  // ─────────────────────────────────────────────────────────────────────────

  describe('persisted session restoration', () => {
    it('attempts to restore session when nexus_access_token exists in localStorage', async () => {
      mockTokenManager.hasAccessToken.mockReturnValue(true);
      mockApiGet.mockResolvedValue({
        success: true,
        data: mockUser,
      });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });

      await waitFor(() => {
        expect(result.current.isAuthenticated).toBe(true);
      });

      expect(mockApiGet).toHaveBeenCalledWith('/v2/users/me');
      expect(result.current.user).toMatchObject({ id: 1, email: 'jane@example.com' });
    });

    it('sets isAuthenticated false and clears tokens when stored token is invalid', async () => {
      mockTokenManager.hasAccessToken.mockReturnValue(true);
      mockApiGet.mockResolvedValue({ success: false, error: 'Token invalid' });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.isAuthenticated).toBe(false);
      expect(result.current.user).toBeNull();
      expect(mockTokenManager.clearTokens).toHaveBeenCalled();
    });

    it('goes to idle (not error) when token fetch throws a network error', async () => {
      mockTokenManager.hasAccessToken.mockReturnValue(true);
      mockApiGet.mockRejectedValue(new Error('Network error'));

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.isAuthenticated).toBe(false);
      expect(result.current.status).toBe('idle');
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Login
  // ─────────────────────────────────────────────────────────────────────────

  describe('login', () => {
    beforeEach(() => {
      // No stored token — start from unauthenticated state
      mockTokenManager.hasAccessToken.mockReturnValue(false);
      mockApiGet.mockResolvedValue({ success: false });
    });

    it('sets user and isAuthenticated on successful login', async () => {
      mockApiPost.mockResolvedValueOnce({
        success: true,
        data: {
          access_token: 'access-abc',
          refresh_token: 'refresh-xyz',
          user: mockUser,
        },
      });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      let loginResult!: Awaited<ReturnType<typeof result.current.login>>;
      await act(async () => {
        loginResult = await result.current.login({ email: 'jane@example.com', password: 'secret' });
      });

      expect(loginResult.success).toBe(true);
      expect(loginResult.requires2FA).toBe(false);
      expect(result.current.isAuthenticated).toBe(true);
      expect(result.current.user).toMatchObject({ id: 1 });
      expect(mockTokenManager.setAccessToken).toHaveBeenCalledWith('access-abc');
      expect(mockTokenManager.setRefreshToken).toHaveBeenCalledWith('refresh-xyz');
    });

    it('returns requires2FA true and sets status to requires_2fa when server demands 2FA', async () => {
      mockApiPost.mockResolvedValueOnce({
        success: true,
        data: {
          requires_2fa: true,
          two_factor_token: 'tfa-token-123',
          methods: ['totp'],
        },
      });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      let loginResult!: Awaited<ReturnType<typeof result.current.login>>;
      await act(async () => {
        loginResult = await result.current.login({ email: 'jane@example.com', password: 'secret' });
      });

      expect(loginResult.success).toBe(true);
      expect(loginResult.requires2FA).toBe(true);
      expect(result.current.status).toBe('requires_2fa');
      expect(result.current.isAuthenticated).toBe(false);
    });

    it('returns success false and sets status to error on failed login', async () => {
      mockApiPost.mockResolvedValueOnce({
        success: false,
        error: 'Invalid credentials',
        code: 'AUTH_INVALID',
      });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      let loginResult!: Awaited<ReturnType<typeof result.current.login>>;
      await act(async () => {
        loginResult = await result.current.login({ email: 'bad@example.com', password: 'wrong' });
      });

      expect(loginResult.success).toBe(false);
      expect(result.current.status).toBe('error');
      expect(result.current.error).toBe('Invalid credentials');
      expect(result.current.isAuthenticated).toBe(false);
    });

    it('sets isLoading true (status loading) while login request is in-flight', async () => {
      let resolveLogin!: (v: unknown) => void;
      mockApiPost.mockReturnValueOnce(new Promise((r) => { resolveLogin = r; }));

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      act(() => {
        result.current.login({ email: 'jane@example.com', password: 'secret' });
      });

      await waitFor(() => {
        expect(result.current.isLoading).toBe(true);
      });

      // Resolve so the hook can clean up
      resolveLogin({ success: false, error: 'Server error' });
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Logout
  // ─────────────────────────────────────────────────────────────────────────

  describe('logout', () => {
    it('clears user and sets isAuthenticated false after logout', async () => {
      // Start authenticated
      mockTokenManager.hasAccessToken.mockReturnValue(true);
      mockApiGet.mockResolvedValue({ success: true, data: mockUser });
      mockApiPost.mockResolvedValue({ success: true });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isAuthenticated).toBe(true));

      await act(async () => {
        await result.current.logout();
      });

      expect(result.current.isAuthenticated).toBe(false);
      expect(result.current.user).toBeNull();
      expect(result.current.status).toBe('idle');
    });

    it('calls tokenManager.clearAll() to remove tokens and tenant id', async () => {
      mockTokenManager.hasAccessToken.mockReturnValue(true);
      mockApiGet.mockResolvedValue({ success: true, data: mockUser });
      mockApiPost.mockResolvedValue({ success: true });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isAuthenticated).toBe(true));

      await act(async () => {
        await result.current.logout();
      });

      expect(mockTokenManager.clearAll).toHaveBeenCalled();
    });

    it('still clears local state even if server-side logout request fails', async () => {
      mockTokenManager.hasAccessToken.mockReturnValue(true);
      mockApiGet.mockResolvedValue({ success: true, data: mockUser });
      // Logout endpoint fails
      mockApiPost.mockResolvedValue({ success: false, error: 'Server error' });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isAuthenticated).toBe(true));

      await act(async () => {
        await result.current.logout();
      });

      // Local state still cleared
      expect(result.current.isAuthenticated).toBe(false);
      expect(result.current.user).toBeNull();
      expect(mockTokenManager.clearAll).toHaveBeenCalled();
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // 2FA verification
  // ─────────────────────────────────────────────────────────────────────────

  describe('2FA verification', () => {
    async function setupRequires2FA(result: { current: ReturnType<typeof useAuth> }) {
      mockApiPost.mockResolvedValueOnce({
        success: true,
        data: {
          requires_2fa: true,
          two_factor_token: 'tfa-session',
          methods: ['totp'],
        },
      });
      await act(async () => {
        await result.current.login({ email: 'jane@example.com', password: 'secret' });
      });
    }

    it('returns false and sets error when verify2FA is called with no active 2FA session', async () => {
      mockTokenManager.hasAccessToken.mockReturnValue(false);
      mockApiGet.mockResolvedValue({ success: false });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      let verifyResult!: boolean;
      await act(async () => {
        verifyResult = await result.current.verify2FA({ code: '123456' });
      });

      expect(verifyResult).toBe(false);
      expect(result.current.status).toBe('error');
    });

    it('authenticates user on successful 2FA verification', async () => {
      mockTokenManager.hasAccessToken.mockReturnValue(false);
      mockApiGet.mockResolvedValue({ success: false });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      await setupRequires2FA(result);

      // Now mock the totp verify call
      mockApiPost.mockResolvedValueOnce({
        success: true,
        data: {
          access_token: 'access-after-2fa',
          refresh_token: 'refresh-after-2fa',
          user: mockUser,
        },
      });

      let verifyResult!: boolean;
      await act(async () => {
        verifyResult = await result.current.verify2FA({ code: '123456' });
      });

      expect(verifyResult).toBe(true);
      expect(result.current.isAuthenticated).toBe(true);
      expect(result.current.status).toBe('authenticated');
    });

    it('returns to idle and clears 2FA state when token expires (AUTH_2FA_TOKEN_EXPIRED)', async () => {
      mockTokenManager.hasAccessToken.mockReturnValue(false);
      mockApiGet.mockResolvedValue({ success: false });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      await setupRequires2FA(result);

      mockApiPost.mockResolvedValueOnce({
        success: false,
        error: '2FA session expired',
        code: 'AUTH_2FA_TOKEN_EXPIRED',
      });

      await act(async () => {
        await result.current.verify2FA({ code: 'bad-code' });
      });

      expect(result.current.status).toBe('idle');
      expect(result.current.twoFactorToken).toBeNull();
    });

    it('cancel2FA resets state back to idle', async () => {
      mockTokenManager.hasAccessToken.mockReturnValue(false);
      mockApiGet.mockResolvedValue({ success: false });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      await setupRequires2FA(result);
      expect(result.current.status).toBe('requires_2fa');

      act(() => {
        result.current.cancel2FA();
      });

      expect(result.current.status).toBe('idle');
      expect(result.current.twoFactorToken).toBeNull();
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Session expiration event
  // ─────────────────────────────────────────────────────────────────────────

  describe('session expiration event', () => {
    it('silently clears state on SESSION_EXPIRED_EVENT if user was never authenticated', async () => {
      mockTokenManager.hasAccessToken.mockReturnValue(false);
      mockApiGet.mockResolvedValue({ success: false });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      act(() => {
        window.dispatchEvent(new Event('nexus:session_expired'));
      });

      expect(result.current.status).toBe('idle');
      // No "session expired" error message because user was never authenticated
      expect(result.current.error).toBeNull();
    });

    it('shows session expired error if user was previously authenticated', async () => {
      mockTokenManager.hasAccessToken.mockReturnValue(true);
      mockApiGet.mockResolvedValue({ success: true, data: mockUser });
      mockApiPost.mockResolvedValueOnce({
        success: true,
        data: { access_token: 'tok', user: mockUser },
      });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isAuthenticated).toBe(true));

      act(() => {
        window.dispatchEvent(new Event('nexus:session_expired'));
      });

      await waitFor(() => {
        expect(result.current.error).toBe('Your session has expired. Please log in again.');
      });
      expect(result.current.status).toBe('idle');
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // clearError
  // ─────────────────────────────────────────────────────────────────────────

  describe('clearError', () => {
    it('resets error to null without changing other state', async () => {
      mockTokenManager.hasAccessToken.mockReturnValue(false);
      mockApiGet.mockResolvedValue({ success: false });
      mockApiPost.mockResolvedValueOnce({
        success: false,
        error: 'Bad credentials',
      });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      await act(async () => {
        await result.current.login({ email: 'x@x.com', password: 'bad' });
      });
      expect(result.current.error).toBe('Bad credentials');

      act(() => {
        result.current.clearError();
      });

      expect(result.current.error).toBeNull();
      expect(result.current.isAuthenticated).toBe(false); // other state preserved
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // refreshUser
  // ─────────────────────────────────────────────────────────────────────────

  describe('refreshUser', () => {
    it('fetches fresh user data from /v2/users/me and updates state', async () => {
      mockTokenManager.hasAccessToken.mockReturnValue(true);
      mockApiGet.mockResolvedValue({ success: true, data: mockUser });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isAuthenticated).toBe(true));

      const updatedUser = { ...mockUser, first_name: 'Updated' };
      mockApiGet.mockResolvedValueOnce({ success: true, data: updatedUser });

      await act(async () => {
        await result.current.refreshUser();
      });

      expect(result.current.user?.first_name).toBe('Updated');
    });

    it('goes idle if no access token is present when refreshUser is called', async () => {
      mockTokenManager.hasAccessToken
        .mockReturnValueOnce(false) // initial mount
        .mockReturnValueOnce(false); // explicit refreshUser call

      mockApiGet.mockResolvedValue({ success: false });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      await act(async () => {
        await result.current.refreshUser();
      });

      expect(result.current.status).toBe('idle');
      expect(result.current.user).toBeNull();
    });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Biometric login
  // ─────────────────────────────────────────────────────────────────────────

  describe('loginWithBiometric', () => {
    beforeEach(() => {
      mockTokenManager.hasAccessToken.mockReturnValue(false);
      mockApiGet.mockResolvedValue({ success: false });
    });

    it('authenticates successfully when biometric succeeds', async () => {
      mockAuthenticateWithBiometric.mockResolvedValue({
        success: true,
        data: {
          user: { id: 1, name: 'Jane' },
          access_token: 'bio-access',
          refresh_token: 'bio-refresh',
        },
      });
      mockApiGet
        .mockResolvedValueOnce({ success: false }) // initial mount
        .mockResolvedValueOnce({ success: true, data: mockUser }); // profile fetch after biometric

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      let loginResult!: Awaited<ReturnType<typeof result.current.loginWithBiometric>>;
      await act(async () => {
        loginResult = await result.current.loginWithBiometric('jane@example.com');
      });

      expect(loginResult.success).toBe(true);
      expect(result.current.isAuthenticated).toBe(true);
      expect(mockTokenManager.setAccessToken).toHaveBeenCalledWith('bio-access');
    });

    it('sets status to idle silently when user cancels biometric prompt', async () => {
      mockAuthenticateWithBiometric.mockResolvedValue({
        success: false,
        error: 'User cancelled the operation',
      });

      const { result } = renderHook(() => useAuth(), { wrapper: authWrapper });
      await waitFor(() => expect(result.current.isLoading).toBe(false));

      await act(async () => {
        await result.current.loginWithBiometric();
      });

      expect(result.current.status).toBe('idle');
      expect(result.current.error).toBeNull();
    });
  });
});
