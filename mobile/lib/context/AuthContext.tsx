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
  useRef,
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
  /** Set the in-memory auth state directly (e.g. after registration saves tokens to storage). */
  setSession: (token: string, user: AnyUser) => void;
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
  /** Track whether push notifications were successfully registered */
  const isPushRegisteredRef = useRef(false);

  /** Called by the API client when it receives a 401 response */
  const handleUnauthorized = useCallback(() => {
    setUser(null);
    setToken(null);
    router.replace('/(auth)/login');
  }, []);

  useEffect(() => {
    registerUnauthorizedCallback(handleUnauthorized);
  }, [handleUnauthorized]);

  // On app start: restore cached user immediately, then re-validate in background.
  // This avoids blocking the UI on a slow network — the app renders from cache first.
  useEffect(() => {
    let isMounted = true;

    async function restoreSession() {
      if (!isMounted) return;
      setIsLoading(true);
      try {
        const storedToken = await storage.get(STORAGE_KEYS.AUTH_TOKEN);
        if (!storedToken) return;

        // Show cached user data immediately so the app doesn't block on network
        const cachedUser = await storage.getJson<AnyUser>(STORAGE_KEYS.USER_DATA);
        if (cachedUser) {
          if (!isMounted) return;
          setToken(storedToken);
          setUser(cachedUser);
          setIsLoading(false);

          // Re-validate token with /users/me in the background
          try {
            const response = await getMe();
            if (!isMounted) return;
            setUser(response.data);
            await storage.setJson(STORAGE_KEYS.USER_DATA, response.data);
          } catch (err: unknown) {
            if (!isMounted) return;
            // Only clear session on 401 (token revoked). For network errors,
            // timeouts, or any other failure, keep the cached user so the app
            // remains usable offline.
            const status = (err as { status?: number })?.status
              ?? (err as { response?: { status?: number } })?.response?.status;
            if (status === 401) {
              await Promise.all([
                storage.remove(STORAGE_KEYS.AUTH_TOKEN),
                storage.remove(STORAGE_KEYS.REFRESH_TOKEN),
                storage.remove(STORAGE_KEYS.USER_DATA),
              ]);
              if (!isMounted) return;
              setToken(null);
              setUser(null);
            }
            // Non-401 errors (network down, timeout, etc.) — keep cached user
          }
          return;
        }

        // No cached user — must validate with network before proceeding
        const response = await getMe();
        if (!isMounted) return;
        setToken(storedToken);
        setUser(response.data);
        await storage.setJson(STORAGE_KEYS.USER_DATA, response.data);
      } catch {
        if (!isMounted) return;
        // Token invalid and no cache — clear everything
        await Promise.all([
          storage.remove(STORAGE_KEYS.AUTH_TOKEN),
          storage.remove(STORAGE_KEYS.REFRESH_TOKEN),
          storage.remove(STORAGE_KEYS.USER_DATA),
        ]);
        if (!isMounted) return;
        setToken(null);
        setUser(null);
      } finally {
        if (isMounted) {
          setIsLoading(false);
        }
      }
    }

    void restoreSession();

    return () => {
      isMounted = false;
    };
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
    registerForPushNotifications()
      .then(() => { isPushRegisteredRef.current = true; })
      .catch(() => { /* best-effort */ });
  }, []);

  const setSession = useCallback((newToken: string, newUser: AnyUser) => {
    setToken(newToken);
    setUser(newUser);
  }, []);

  const refreshUser = useCallback((updated: AnyUser) => {
    setUser(updated);
  }, []);

  const logout = useCallback(async () => {
    // Unregister push token BEFORE server logout — the server call invalidates the
    // auth token, so push unregister must happen first to avoid a silent 401 failure.
    if (isPushRegisteredRef.current) {
      try {
        await unregisterPushNotifications();
        isPushRegisteredRef.current = false;
      } catch {
        // Best-effort — continue with logout even if push unregister fails
      }
    }

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
      setSession,
      refreshUser,
      displayName,
    }),
    [user, token, isLoading, login, logout, setSession, refreshUser, displayName],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuthContext(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuthContext must be used within <AuthProvider>');
  return ctx;
}
