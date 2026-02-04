/**
 * Auth API - Login, logout, token refresh
 */

import { apiPost, setAccessToken, setRefreshToken, clearTokens, getRefreshToken } from './client';
import type {
  LoginRequest,
  LoginResponse,
  RefreshTokenRequest,
  RefreshTokenResponse,
  TwoFactorVerifyRequest,
  TwoFactorVerifyResponse,
} from './types';

/**
 * Login with email and password
 *
 * Returns either:
 * - LoginSuccessResponse with tokens and user
 * - TwoFactorRequiredResponse if 2FA is enabled
 */
export async function login(credentials: LoginRequest): Promise<LoginResponse> {
  const response = await apiPost<LoginResponse>('/api/auth/login', credentials, {
    skipAuth: true,
  });

  // If login successful (not 2FA required), store tokens
  if ('access_token' in response) {
    setAccessToken(response.access_token);
    setRefreshToken(response.refresh_token);
  }

  return response;
}

/**
 * Refresh access token using refresh token
 */
export async function refreshAccessToken(): Promise<RefreshTokenResponse | null> {
  const refreshToken = getRefreshToken();

  if (!refreshToken) {
    return null;
  }

  try {
    const response = await apiPost<RefreshTokenResponse>(
      '/api/auth/refresh-token',
      { refresh_token: refreshToken } as RefreshTokenRequest,
      { skipAuth: true }
    );

    if (response.success && response.access_token) {
      setAccessToken(response.access_token);
    }

    return response;
  } catch {
    // Refresh failed, clear tokens
    clearTokens();
    return null;
  }
}

/**
 * Verify 2FA code and complete login
 *
 * Called after login returns requires_2fa: true
 */
export async function verify2FA(request: TwoFactorVerifyRequest): Promise<TwoFactorVerifyResponse> {
  const response = await apiPost<TwoFactorVerifyResponse>('/api/totp/verify', request, {
    skipAuth: true,
  });

  // Store tokens on successful verification
  if (response.success && response.access_token) {
    setAccessToken(response.access_token);
    setRefreshToken(response.refresh_token);
  }

  return response;
}

/**
 * Logout - clear tokens (backend revocation optional)
 */
export async function logout(): Promise<void> {
  try {
    // Attempt to revoke token on backend (optional, may fail)
    const refreshToken = getRefreshToken();
    if (refreshToken) {
      await apiPost('/api/auth/logout', { refresh_token: refreshToken });
    }
  } catch {
    // Ignore errors, still clear local tokens
  } finally {
    clearTokens();
  }
}
