// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * NEXUS Authentication Context
 *
 * Provides:
 * - Login with email/password
 * - 2FA verification flow
 * - Token refresh
 * - Session management
 * - Logout
 */

import {
  createContext,
  useContext,
  useState,
  useEffect,
  useCallback,
  useRef,
  useMemo,
  type ReactNode,
} from 'react';
import { api, tokenManager, SESSION_EXPIRED_EVENT } from '@/lib/api';
import { logWarn } from '@/lib/logger';
import i18n from '@/i18n';
import { validateResponseIfPresent } from '@/lib/api-validation';
import { loginResponseSchema, userSchema } from '@/lib/api-schemas';
import { setSentryUser, captureAuthEvent } from '@/lib/sentry';
import type {
  User,
  LoginRequest,
  LoginSuccessResponse,
  TwoFactorRequiredResponse,
  TwoFactorVerifyRequest,
  RegisterRequest,
} from '@/types';
import { authenticateWithBiometric } from '@/lib/webauthn';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export type AuthStatus =
  | 'idle'           // Not logged in, no action
  | 'loading'        // Auth operation in progress
  | 'requires_2fa'   // Login succeeded, awaiting 2FA
  | 'authenticated'  // Fully authenticated
  | 'error';         // Auth error occurred

interface AuthState {
  user: User | null;
  status: AuthStatus;
  error: string | null;
  twoFactorToken: string | null;  // Store in memory only!
  twoFactorMethods: string[];
}

export interface RegisterResult {
  success: boolean;
  requiresVerification?: boolean;
  requiresApproval?: boolean;
  requiresWaitlist?: boolean;
  waitlistPosition?: number;
  nextSteps?: string[];
  message?: string;
  error?: string;
}

interface AuthContextValue extends AuthState {
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (credentials: LoginRequest) => Promise<LoginResult>;
  loginWithBiometric: (email?: string) => Promise<LoginResult>;
  verify2FA: (request: TwoFactorVerifyRequest) => Promise<boolean>;
  register: (data: RegisterRequest) => Promise<RegisterResult>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
  clearError: () => void;
  cancel2FA: () => void;
}

interface LoginResult {
  success: boolean;
  requires2FA: boolean;
  error?: string;
  errorCode?: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Context
// ─────────────────────────────────────────────────────────────────────────────

const AuthContext = createContext<AuthContextValue | null>(null);

// ─────────────────────────────────────────────────────────────────────────────
// Provider
// ─────────────────────────────────────────────────────────────────────────────

interface AuthProviderProps {
  children: ReactNode;
}

export function AuthProvider({ children }: AuthProviderProps) {
  const [state, setState] = useState<AuthState>({
    user: null,
    status: 'loading',  // Start loading to check existing token
    error: null,
    twoFactorToken: null,
    twoFactorMethods: [],
  });

  // Track whether user was ever authenticated (prevents false "session expired" on first visit)
  const wasAuthenticated = useRef(false);

  // Prevent concurrent logout calls (e.g., triggered by 401 loop while logout is in flight)
  const isLoggingOutRef = useRef(false);

  // ─────────────────────────────────────────────────────────────────────────
  // User Refresh
  // ─────────────────────────────────────────────────────────────────────────

  const refreshUser = useCallback(async () => {
    if (!tokenManager.hasAccessToken()) {
      setState((prev) => ({
        ...prev,
        status: 'idle',
        user: null,
      }));
      return;
    }

    try {
      const response = await api.get<User>('/v2/users/me');

      if (response.success && response.data) {
        // Dev-only: validate user profile shape
        validateResponseIfPresent(userSchema, response.data, 'GET /v2/users/me');

        // Only set tenant ID from user data if no tenant was pre-selected
        // This allows super admins to access any tenant they selected at login
        if (response.data.tenant_id && !tokenManager.getTenantId()) {
          tokenManager.setTenantId(response.data.tenant_id);
        }

        // Set Sentry user context
        setSentryUser(response.data);

        // Apply user's server-side language preference (overrides browser detection)
        if (response.data.preferred_language) {
          i18n.changeLanguage(response.data.preferred_language);
        }

        wasAuthenticated.current = true;
        setState({
          user: response.data,
          status: 'authenticated',
          error: null,
          twoFactorToken: null,
          twoFactorMethods: [],
        });
      } else {
        // Token invalid or expired
        tokenManager.clearTokens();
        setSentryUser(null);
        setState({
          user: null,
          status: 'idle',
          error: null,
          twoFactorToken: null,
          twoFactorMethods: [],
        });
      }
    } catch {
      setState((prev) => ({
        ...prev,
        status: 'idle',
        user: null,
      }));
    }
  }, []);

  // ─────────────────────────────────────────────────────────────────────────
  // Login
  // ─────────────────────────────────────────────────────────────────────────

  const login = useCallback(async (credentials: LoginRequest): Promise<LoginResult> => {
    setState((prev) => ({ ...prev, status: 'loading', error: null }));

    const response = await api.post<LoginSuccessResponse | TwoFactorRequiredResponse>(
      '/auth/login',
      credentials,
      { skipAuth: true }
    );

    if (!response.success) {
      captureAuthEvent('failed_login', undefined, {
        error: response.error,
        code: response.code,
      });
      setState((prev) => ({
        ...prev,
        status: 'error',
        error: response.error ?? 'Login failed',
      }));
      return { success: false, requires2FA: false, error: response.error, errorCode: response.code };
    }

    const data = response.data;

    // Dev-only: validate login response shape
    validateResponseIfPresent(loginResponseSchema, data, 'POST /auth/login');

    // Check if 2FA is required
    if (data && 'requires_2fa' in data && data.requires_2fa) {
      const twoFAData = data as TwoFactorRequiredResponse;
      setState((prev) => ({
        ...prev,
        status: 'requires_2fa',
        error: null,
        twoFactorToken: twoFAData.two_factor_token,
        twoFactorMethods: twoFAData.methods || ['totp'],
      }));
      return { success: true, requires2FA: true };
    }

    // Login successful without 2FA
    const loginData = data as LoginSuccessResponse;

    if (loginData.access_token || loginData.token) {
      tokenManager.setAccessToken(loginData.access_token || loginData.token);
    }
    if (loginData.refresh_token) {
      tokenManager.setRefreshToken(loginData.refresh_token);
    }
    // Only set tenant ID from user data if no tenant was pre-selected at login
    // This allows super admins to access any tenant they selected
    if (loginData.user?.tenant_id && !tokenManager.getTenantId()) {
      tokenManager.setTenantId(loginData.user.tenant_id);
    }

    // Set Sentry user context and capture login event
    setSentryUser(loginData.user);
    captureAuthEvent('login', loginData.user.id);

    // Apply user's server-side language preference (overrides browser detection)
    if (loginData.user?.preferred_language) {
      i18n.changeLanguage(loginData.user.preferred_language);
    }

    wasAuthenticated.current = true;
    setState({
      user: loginData.user,
      status: 'authenticated',
      error: null,
      twoFactorToken: null,
      twoFactorMethods: [],
    });

    return { success: true, requires2FA: false };
  }, []);

  // ─────────────────────────────────────────────────────────────────────────
  // Biometric / WebAuthn Login
  // ─────────────────────────────────────────────────────────────────────────

  const loginWithBiometric = useCallback(async (email?: string): Promise<LoginResult> => {
    setState((prev) => ({ ...prev, status: 'loading', error: null }));

    const result = await authenticateWithBiometric(email);

    if (!result.success || !result.data) {
      // User cancelled the biometric prompt — return to idle silently
      const wasCancelled = result.error?.includes('cancelled');
      setState((prev) => ({
        ...prev,
        status: wasCancelled ? 'idle' : 'error',
        error: wasCancelled ? null : (result.error ?? 'Biometric login failed'),
      }));
      return { success: false, requires2FA: false, error: wasCancelled ? undefined : result.error };
    }

    const { user, access_token, refresh_token } = result.data;

    tokenManager.setAccessToken(access_token);
    tokenManager.setRefreshToken(refresh_token);

    // Set Sentry user context
    setSentryUser(user satisfies User);
    captureAuthEvent('biometric_login', user.id);

    wasAuthenticated.current = true;

    // Fetch full user profile (biometric auth response has minimal user data)
    try {
      const profileRes = await api.get<User>('/v2/users/me');
      if (profileRes.success && profileRes.data) {
        if (profileRes.data.preferred_language) {
          i18n.changeLanguage(profileRes.data.preferred_language);
        }
        setState({
          user: profileRes.data,
          status: 'authenticated',
          error: null,
          twoFactorToken: null,
          twoFactorMethods: [],
        });
      } else {
        // Token works but profile fetch failed — set minimal user data
        setState({
          user: user satisfies User,
          status: 'authenticated',
          error: null,
          twoFactorToken: null,
          twoFactorMethods: [],
        });
      }
    } catch {
      setState({
        user: user satisfies User,
        status: 'authenticated',
        error: null,
        twoFactorToken: null,
        twoFactorMethods: [],
      });
    }

    return { success: true, requires2FA: false };
  }, []);

  // ─────────────────────────────────────────────────────────────────────────
  // 2FA Verification
  // ─────────────────────────────────────────────────────────────────────────

  const verify2FA = useCallback(async (request: TwoFactorVerifyRequest): Promise<boolean> => {
    if (!state.twoFactorToken) {
      setState((prev) => ({
        ...prev,
        status: 'error',
        error: 'No 2FA session active',
      }));
      return false;
    }

    setState((prev) => ({ ...prev, status: 'loading', error: null }));

    const response = await api.post<LoginSuccessResponse>(
      '/totp/verify',
      {
        two_factor_token: state.twoFactorToken,
        code: request.code,
        use_backup_code: request.use_backup_code,
        trust_device: request.trust_device,
      },
      { skipAuth: true }
    );

    if (!response.success) {
      // Check if we need to restart login
      if (response.code === 'AUTH_2FA_TOKEN_EXPIRED' || response.code === 'AUTH_2FA_MAX_ATTEMPTS') {
        setState({
          user: null,
          status: 'idle',
          error: response.error ?? '2FA session expired. Please log in again.',
          twoFactorToken: null,
          twoFactorMethods: [],
        });
      } else {
        setState((prev) => ({
          ...prev,
          status: 'requires_2fa',
          error: response.error ?? 'Invalid code',
        }));
      }
      return false;
    }

    const data = response.data!;

    if (data.access_token || data.token) {
      tokenManager.setAccessToken(data.access_token || data.token);
    }
    if (data.refresh_token) {
      tokenManager.setRefreshToken(data.refresh_token);
    }
    // Store trusted device token so future logins skip 2FA on this device
    if (data.trusted_device_token) {
      tokenManager.setTrustedDeviceToken(data.trusted_device_token);
    }
    // Only set tenant ID from user data if no tenant was pre-selected at login
    // This allows super admins to access any tenant they selected
    if (data.user?.tenant_id && !tokenManager.getTenantId()) {
      tokenManager.setTenantId(data.user.tenant_id);
    }

    wasAuthenticated.current = true;
    setState({
      user: data.user,
      status: 'authenticated',
      error: null,
      twoFactorToken: null,
      twoFactorMethods: [],
    });

    return true;
  }, [state.twoFactorToken]);

  // ─────────────────────────────────────────────────────────────────────────
  // Cancel 2FA (go back to login)
  // ─────────────────────────────────────────────────────────────────────────

  const cancel2FA = useCallback(() => {
    setState({
      user: null,
      status: 'idle',
      error: null,
      twoFactorToken: null,
      twoFactorMethods: [],
    });
  }, []);

  // ─────────────────────────────────────────────────────────────────────────
  // Register
  // ─────────────────────────────────────────────────────────────────────────

  const register = useCallback(async (data: RegisterRequest): Promise<RegisterResult> => {
    setState((prev) => ({ ...prev, status: 'loading', error: null }));

    // Use a generic type since the response shape varies based on whether tokens are issued
    const response = await api.post<Record<string, unknown>>(
      '/v2/auth/register',
      data,
      { skipAuth: true }
    );

    if (!response.success) {
      setState((prev) => ({
        ...prev,
        status: 'error',
        error: response.error ?? 'Registration failed',
      }));
      return { success: false, error: response.error ?? 'Registration failed' };
    }

    const responseData = response.data as Record<string, unknown>;

    // Check if verification, approval, or waitlist is required (no tokens issued)
    const requiresVerification = responseData.requires_verification === true;
    const requiresApproval = responseData.requires_approval === true;
    const requiresWaitlist = responseData.requires_waitlist === true;

    if (requiresVerification || requiresApproval || requiresWaitlist) {
      // Registration succeeded but user cannot log in yet.
      // Reset to idle state — do NOT store any tokens.
      setState((prev) => ({
        ...prev,
        status: 'idle',
        error: null,
      }));
      return {
        success: true,
        requiresVerification,
        requiresApproval,
        requiresWaitlist,
        waitlistPosition: typeof responseData.waitlist_position === 'number' ? responseData.waitlist_position : undefined,
        nextSteps: (responseData.next_steps as string[]) ?? [],
        message: (responseData.message as string) ?? 'Registration successful!',
      };
    }

    // No gates — tokens were issued, authenticate immediately
    const accessToken = (responseData.access_token || responseData.token) as string | undefined;
    const refreshToken = responseData.refresh_token as string | undefined;
    const user = responseData.user as User | undefined;

    if (accessToken) {
      tokenManager.setAccessToken(accessToken);
    }
    if (refreshToken) {
      tokenManager.setRefreshToken(refreshToken);
    }
    if (user?.tenant_id && !tokenManager.getTenantId()) {
      tokenManager.setTenantId(user.tenant_id);
    }

    wasAuthenticated.current = true;
    setState({
      user: user ?? null,
      status: 'authenticated',
      error: null,
      twoFactorToken: null,
      twoFactorMethods: [],
    });

    return { success: true };
  }, []);

  // ─────────────────────────────────────────────────────────────────────────
  // Logout
  // ─────────────────────────────────────────────────────────────────────────

  const logout = useCallback(async () => {
    // Guard against re-entrant logout calls (e.g., a 401 firing while logout is in flight)
    if (isLoggingOutRef.current) return;
    isLoggingOutRef.current = true;

    const userId = state.user?.id;
    const refreshToken = tokenManager.getRefreshToken();

    try {
      // Call logout endpoint to invalidate tokens server-side
      // We verify the response but still proceed with local logout regardless
      try {
        const response = await api.post('/auth/logout', { refresh_token: refreshToken });

        // Log if server-side logout failed (for audit purposes)
        if (!response.success) {
          logWarn('Server-side logout failed', {
            code: response.code,
            error: response.error
          });
        }
      } catch {
        // Log network errors but proceed with local logout
        logWarn('Logout request failed - proceeding with local logout');
      }

      // Clear auth tokens but preserve tenant context (slug + ID).
      // Tenant is not a security credential — it's a UX preference that should
      // survive logout so the user stays on the same community's login page.
      // Logout preserves trusted device token by design.
      // Call tokenManager.clearTrustedDeviceToken() for an explicit "forget this device" flow.
      tokenManager.clearTokens();

      // Clear Sentry user context and capture logout event
      captureAuthEvent('logout', userId);
      setSentryUser(null);

      setState({
        user: null,
        status: 'idle',
        error: null,
        twoFactorToken: null,
        twoFactorMethods: [],
      });
    } finally {
      isLoggingOutRef.current = false;
    }
  }, [state.user?.id]);

  // ─────────────────────────────────────────────────────────────────────────
  // Clear Error
  // ─────────────────────────────────────────────────────────────────────────

  const clearError = useCallback(() => {
    setState((prev) => ({ ...prev, error: null }));
  }, []);

  // ─────────────────────────────────────────────────────────────────────────
  // Session Expiration Handler
  // ─────────────────────────────────────────────────────────────────────────

  useEffect(() => {
    const handleSessionExpired = () => {
      // Only set error message if user had an active session — stale tokens
      // on first visit should silently clear without showing "session expired"
      if (wasAuthenticated.current) {
        setState({
          user: null,
          status: 'idle',
          error: 'Your session has expired. Please log in again.',
          twoFactorToken: null,
          twoFactorMethods: [],
        });
      } else {
        // Stale tokens from a previous session — just silently clear
        setState({
          user: null,
          status: 'idle',
          error: null,
          twoFactorToken: null,
          twoFactorMethods: [],
        });
      }
    };

    window.addEventListener(SESSION_EXPIRED_EVENT, handleSessionExpired);
    return () => {
      window.removeEventListener(SESSION_EXPIRED_EVENT, handleSessionExpired);
    };
  }, []);

  // ─────────────────────────────────────────────────────────────────────────
  // Cross-Tab Logout — sync auth state when tokens are cleared in another tab
  // ─────────────────────────────────────────────────────────────────────────

  useEffect(() => {
    const handleStorageChange = (event: StorageEvent) => {
      // localStorage 'storage' event only fires in OTHER tabs (not the one that made the change).
      // When access token is removed (logout in another tab), clear state here too.
      if (event.key === 'nexus_access_token' && event.newValue === null && state.status === 'authenticated') {
        tokenManager.clearTokens();
        // Also clear tenant context so the next login gets a fresh tenant bootstrap.
        // Without this, a stale tenant slug from the previous session could cause
        // the wrong community's branding to appear on the login page in this tab.
        localStorage.removeItem('nexus_tenant_id');
        localStorage.removeItem('nexus_tenant_slug');
        setState({
          user: null,
          status: 'idle',
          error: null,
          twoFactorToken: null,
          twoFactorMethods: [],
        });
      }
    };

    window.addEventListener('storage', handleStorageChange);
    return () => {
      window.removeEventListener('storage', handleStorageChange);
    };
  }, [state.status]);

  // ─────────────────────────────────────────────────────────────────────────
  // Initial Auth Check
  // ─────────────────────────────────────────────────────────────────────────

  useEffect(() => {
    refreshUser();
  }, [refreshUser]);

  // ─────────────────────────────────────────────────────────────────────────
  // Context Value
  // ─────────────────────────────────────────────────────────────────────────

  const value = useMemo<AuthContextValue>(
    () => ({
      ...state,
      isAuthenticated: state.status === 'authenticated',
      isLoading: state.status === 'loading',
      login,
      loginWithBiometric,
      verify2FA,
      register,
      logout,
      refreshUser,
      clearError,
      cancel2FA,
    }),
    [state, login, loginWithBiometric, verify2FA, register, logout, refreshUser, clearError, cancel2FA]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

// ─────────────────────────────────────────────────────────────────────────────
// Hook
// ─────────────────────────────────────────────────────────────────────────────

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }

  return context;
}

export default AuthContext;
