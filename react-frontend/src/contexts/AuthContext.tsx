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
  useMemo,
  type ReactNode,
} from 'react';
import { api, tokenManager, SESSION_EXPIRED_EVENT } from '@/lib/api';
import { logWarn } from '@/lib/logger';
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

interface AuthContextValue extends AuthState {
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (credentials: LoginRequest) => Promise<LoginResult>;
  verify2FA: (request: TwoFactorVerifyRequest) => Promise<boolean>;
  register: (data: RegisterRequest) => Promise<boolean>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
  clearError: () => void;
  cancel2FA: () => void;
}

interface LoginResult {
  success: boolean;
  requires2FA: boolean;
  error?: string;
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
      return { success: false, requires2FA: false, error: response.error };
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
    // Only set tenant ID from user data if no tenant was pre-selected at login
    // This allows super admins to access any tenant they selected
    if (data.user?.tenant_id && !tokenManager.getTenantId()) {
      tokenManager.setTenantId(data.user.tenant_id);
    }

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

  const register = useCallback(async (data: RegisterRequest): Promise<boolean> => {
    setState((prev) => ({ ...prev, status: 'loading', error: null }));

    const response = await api.post<LoginSuccessResponse>(
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
      return false;
    }

    const responseData = response.data!;

    if (responseData.access_token || responseData.token) {
      tokenManager.setAccessToken(responseData.access_token || responseData.token);
    }
    if (responseData.refresh_token) {
      tokenManager.setRefreshToken(responseData.refresh_token);
    }
    // Only set tenant ID from user data if no tenant was pre-selected
    if (responseData.user?.tenant_id && !tokenManager.getTenantId()) {
      tokenManager.setTenantId(responseData.user.tenant_id);
    }

    setState({
      user: responseData.user,
      status: 'authenticated',
      error: null,
      twoFactorToken: null,
      twoFactorMethods: [],
    });

    return true;
  }, []);

  // ─────────────────────────────────────────────────────────────────────────
  // Logout
  // ─────────────────────────────────────────────────────────────────────────

  const logout = useCallback(async () => {
    const userId = state.user?.id;
    const refreshToken = tokenManager.getRefreshToken();

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

    // Always clear ALL local data (tokens AND tenant ID) regardless of server response
    tokenManager.clearAll();

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
      setState({
        user: null,
        status: 'idle',
        error: 'Your session has expired. Please log in again.',
        twoFactorToken: null,
        twoFactorMethods: [],
      });
    };

    window.addEventListener(SESSION_EXPIRED_EVENT, handleSessionExpired);
    return () => {
      window.removeEventListener(SESSION_EXPIRED_EVENT, handleSessionExpired);
    };
  }, []);

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
      verify2FA,
      register,
      logout,
      refreshUser,
      clearError,
      cancel2FA,
    }),
    [state, login, verify2FA, register, logout, refreshUser, clearError, cancel2FA]
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
