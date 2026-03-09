// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';
import { router } from 'expo-router';

import {
  login as apiLogin,
  logout as apiLogout,
  getMe,
  extractToken,
  buildDisplayName,
  type User,
  type LoginUser,
  type LoginPayload,
} from '@/lib/api/auth';
import { registerUnauthorizedCallback } from '@/lib/api/client';
import { STORAGE_KEYS } from '@/lib/constants';
import { storage } from '@/lib/storage';
import {
  registerForPushNotifications,
  unregisterPushNotifications,
} from '@/lib/notifications';

/**
 * The app uses two user shapes:
 * - LoginUser: embedded in login/register response (slim, no balance)
 * - User: from GET /users/me (full profile with balance)
 *
 * After login we store the LoginUser immediately so the app is usable,
 * then silently upgrade to the full User in the background.
 */
type AnyUser = User | LoginUser;

interface AuthState {
  user: AnyUser | null;
  token: string | null;
  isLoading: boolean;
  isAuthenticated: boolean;
}

interface AuthContextValue extends AuthState {
  login: (payload: LoginPayload) => Promise<void>;
  logout: () => Promise<void>;
  /** Patch the in-memory user after a profile update (avoids a full re-fetch). */
  refreshUser: (updated: AnyUser) => void;
  /** Display-ready name for the current user */
  displayName: string;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<AnyUser | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  /** Called by the API client when it receives a 401 response */
  const handleUnauthorized = useCallback(() => {
    setUser(null);
    setToken(null);
    router.replace('/(auth)/login');
  }, []);

  useEffect(() => {
    registerUnauthorizedCallback(handleUnauthorized);
  }, [handleUnauthorized]);

  // On app start: restore stored token and validate it with /users/me
  useEffect(() => {
    async function restoreSession() {
      setIsLoading(true);
      try {
        const storedToken = await storage.get(STORAGE_KEYS.AUTH_TOKEN);
        if (!storedToken) return;

        // Use stored user data immediately so UI isn't blank while we validate
        const cached = await storage.getJson<AnyUser>(STORAGE_KEYS.USER_DATA);
        if (cached) {
          setToken(storedToken);
          setUser(cached);
        }

        // Validate + upgrade to full User from /users/me
        const response = await getMe();
        setToken(storedToken);
        setUser(response.data);
        await storage.setJson(STORAGE_KEYS.USER_DATA, response.data);
      } catch {
        // Token invalid — clear everything
        await Promise.all([
          storage.remove(STORAGE_KEYS.AUTH_TOKEN),
          storage.remove(STORAGE_KEYS.REFRESH_TOKEN),
          storage.remove(STORAGE_KEYS.USER_DATA),
        ]);
        setToken(null);
        setUser(null);
      } finally {
        setIsLoading(false);
      }
    }

    void restoreSession();
  }, []);

  const login = useCallback(async (payload: LoginPayload) => {
    const response = await apiLogin(payload);
    const bearerToken = extractToken(response);

    await Promise.all([
      storage.set(STORAGE_KEYS.AUTH_TOKEN, bearerToken),
      storage.set(STORAGE_KEYS.REFRESH_TOKEN, response.refresh_token),
      storage.setJson<LoginUser>(STORAGE_KEYS.USER_DATA, response.user),
    ]);

    setToken(bearerToken);
    setUser(response.user);

    router.replace('/(tabs)/home');

    // Register device for push notifications (non-blocking, best-effort)
    void registerForPushNotifications();
  }, []);

  const refreshUser = useCallback((updated: AnyUser) => {
    setUser(updated);
  }, []);

  const logout = useCallback(async () => {
    // Best-effort: unregister push token before clearing auth
    void unregisterPushNotifications();

    try {
      await apiLogout();
    } catch {
      // Continue with local cleanup even if server call fails
    }

    await Promise.all([
      storage.remove(STORAGE_KEYS.AUTH_TOKEN),
      storage.remove(STORAGE_KEYS.REFRESH_TOKEN),
      storage.remove(STORAGE_KEYS.USER_DATA),
    ]);

    setToken(null);
    setUser(null);
    router.replace('/(auth)/login');
  }, []);

  const displayName = useMemo(
    () => (user ? buildDisplayName(user as LoginUser) : ''),
    [user],
  );

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      token,
      isLoading,
      isAuthenticated: !!token && !!user,
      login,
      logout,
      refreshUser,
      displayName,
    }),
    [user, token, isLoading, login, logout, refreshUser, displayName],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuthContext(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuthContext must be used within <AuthProvider>');
  return ctx;
}
