/**
 * Auth Context - Manages authentication state with two-step login flow
 *
 * State Machine:
 * - idle: Not logged in, no action in progress
 * - logging_in: Login request in progress
 * - requires_2fa: Login succeeded, awaiting 2FA verification
 * - authenticated: Fully authenticated
 * - error: Auth error occurred
 *
 * The two_factor_token is stored in MEMORY only (not localStorage) for security.
 */

import {
  createContext,
  useContext,
  useState,
  useCallback,
  useEffect,
  type ReactNode,
} from 'react';
import { login as apiLogin, logout as apiLogout } from '../api/auth';
import { getAccessToken, clearTokens, SESSION_EXPIRED_EVENT } from '../api/client';
import type { User, LoginRequest, LoginResponse, TwoFactorRequiredResponse } from '../api/types';

// ===========================================
// TYPES
// ===========================================

/**
 * Authentication status state machine
 */
export type AuthStatus =
  | 'idle'           // Not logged in, no action
  | 'logging_in'     // Login request in progress
  | 'requires_2fa'   // Login succeeded, awaiting 2FA
  | 'authenticated'  // Fully authenticated
  | 'error';         // Auth error occurred

interface AuthState {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  status: AuthStatus;
  error: string | null;
  /** 2FA challenge data - stored in memory only, never localStorage */
  twoFactorChallenge: TwoFactorRequiredResponse | null;
}

interface AuthContextValue extends AuthState {
  login: (credentials: LoginRequest) => Promise<LoginResponse>;
  logout: () => Promise<void>;
  setUser: (user: User) => void;
  /** Clear 2FA challenge and return to idle state */
  clearTwoFactorChallenge: () => void;
}

// ===========================================
// CONTEXT
// ===========================================

const AuthContext = createContext<AuthContextValue | null>(null);

// ===========================================
// STORAGE
// ===========================================

const USER_KEY = 'nexus_user';

function getStoredUser(): User | null {
  try {
    const stored = localStorage.getItem(USER_KEY);
    return stored ? JSON.parse(stored) : null;
  } catch {
    return null;
  }
}

function setStoredUser(user: User | null): void {
  if (user) {
    localStorage.setItem(USER_KEY, JSON.stringify(user));
  } else {
    localStorage.removeItem(USER_KEY);
  }
}

// ===========================================
// INITIAL STATE
// ===========================================

function createInitialState(): AuthState {
  const token = getAccessToken();
  const user = getStoredUser();
  const isAuthenticated = !!(token && user);

  return {
    user: isAuthenticated ? user : null,
    isAuthenticated,
    isLoading: false,
    status: isAuthenticated ? 'authenticated' : 'idle',
    error: null,
    twoFactorChallenge: null,
  };
}

// ===========================================
// PROVIDER
// ===========================================

interface AuthProviderProps {
  children: ReactNode;
}

export function AuthProvider({ children }: AuthProviderProps) {
  const [state, setState] = useState<AuthState>(createInitialState);

  // Listen for session expiration events from API client
  useEffect(() => {
    const handleSessionExpired = () => {
      // Clear all auth data
      clearTokens();
      setStoredUser(null);
      setState({
        user: null,
        isAuthenticated: false,
        isLoading: false,
        status: 'error',
        error: 'Session expired. Please log in again.',
        twoFactorChallenge: null,
      });
    };

    window.addEventListener(SESSION_EXPIRED_EVENT, handleSessionExpired);
    return () => {
      window.removeEventListener(SESSION_EXPIRED_EVENT, handleSessionExpired);
    };
  }, []);

  // Validate auth state on mount
  useEffect(() => {
    const token = getAccessToken();
    const user = getStoredUser();

    if (token && user) {
      setState({
        user,
        isAuthenticated: true,
        isLoading: false,
        status: 'authenticated',
        error: null,
        twoFactorChallenge: null,
      });
    } else {
      // Clear any stale data
      clearTokens();
      setStoredUser(null);
      setState({
        user: null,
        isAuthenticated: false,
        isLoading: false,
        status: 'idle',
        error: null,
        twoFactorChallenge: null,
      });
    }
  }, []);

  const login = useCallback(async (credentials: LoginRequest): Promise<LoginResponse> => {
    setState(prev => ({
      ...prev,
      isLoading: true,
      status: 'logging_in',
      error: null,
      twoFactorChallenge: null,
    }));

    try {
      const response = await apiLogin(credentials);

      // Check if 2FA is required
      if ('requires_2fa' in response && response.requires_2fa) {
        setState(prev => ({
          ...prev,
          isLoading: false,
          status: 'requires_2fa',
          // Store 2FA challenge in memory only (not localStorage)
          twoFactorChallenge: response,
        }));
        return response;
      }

      // Success - store user
      if ('user' in response) {
        setStoredUser(response.user);
        setState({
          user: response.user,
          isAuthenticated: true,
          isLoading: false,
          status: 'authenticated',
          error: null,
          twoFactorChallenge: null,
        });
      }

      return response;
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Login failed';
      setState(prev => ({
        ...prev,
        isLoading: false,
        status: 'error',
        error: errorMessage,
        twoFactorChallenge: null,
      }));
      throw error;
    }
  }, []);

  const logout = useCallback(async (): Promise<void> => {
    setState(prev => ({ ...prev, isLoading: true }));

    try {
      await apiLogout();
    } finally {
      setStoredUser(null);
      setState({
        user: null,
        isAuthenticated: false,
        isLoading: false,
        status: 'idle',
        error: null,
        twoFactorChallenge: null,
      });
    }
  }, []);

  // Set user directly (for 2FA flow where tokens are already stored)
  const setUser = useCallback((user: User): void => {
    setStoredUser(user);
    setState({
      user,
      isAuthenticated: true,
      isLoading: false,
      status: 'authenticated',
      error: null,
      twoFactorChallenge: null,
    });
  }, []);

  // Clear 2FA challenge and return to idle state
  const clearTwoFactorChallenge = useCallback((): void => {
    setState(prev => ({
      ...prev,
      status: 'idle',
      error: null,
      twoFactorChallenge: null,
    }));
  }, []);

  const value: AuthContextValue = {
    ...state,
    login,
    logout,
    setUser,
    clearTwoFactorChallenge,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}

// ===========================================
// HOOKS
// ===========================================

/**
 * Get auth context
 */
export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}

/**
 * Get current user (throws if not authenticated)
 */
export function useUser(): User {
  const { user } = useAuth();
  if (!user) {
    throw new Error('useUser requires authenticated user');
  }
  return user;
}

/**
 * Check if user is authenticated
 */
export function useIsAuthenticated(): boolean {
  const { isAuthenticated } = useAuth();
  return isAuthenticated;
}

/**
 * Get current auth status
 */
export function useAuthStatus(): AuthStatus {
  const { status } = useAuth();
  return status;
}
