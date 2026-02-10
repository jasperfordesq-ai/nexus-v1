/**
 * NEXUS Theme Context
 *
 * Provides light/dark mode toggle with:
 * - System preference detection
 * - localStorage persistence
 * - Backend sync for authenticated users
 * - Smooth theme transitions
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
import { api, tokenManager } from '@/lib/api';
import { logWarn } from '@/lib/logger';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export type ThemeMode = 'light' | 'dark' | 'system';
export type ResolvedTheme = 'light' | 'dark';

interface ThemeState {
  theme: ThemeMode;
  resolvedTheme: ResolvedTheme;
  isInitialized: boolean;
}

interface ThemeContextValue extends ThemeState {
  setTheme: (theme: ThemeMode) => Promise<void>;
  toggleTheme: () => Promise<void>;
  isLoading: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const THEME_STORAGE_KEY = 'nexus_theme';
const DEFAULT_THEME: ThemeMode = 'dark';

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function getSystemTheme(): ResolvedTheme {
  if (typeof window === 'undefined') return 'dark';
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function resolveTheme(theme: ThemeMode): ResolvedTheme {
  if (theme === 'system') {
    return getSystemTheme();
  }
  return theme;
}

function applyThemeToDOM(resolvedTheme: ResolvedTheme): void {
  if (typeof document === 'undefined') return;

  const html = document.documentElement;

  // Set data-theme attribute
  html.setAttribute('data-theme', resolvedTheme);

  // Update class for compatibility
  html.classList.remove('light', 'dark');
  html.classList.add(resolvedTheme);

  // Update meta theme-color for mobile browsers
  const metaThemeColor = document.querySelector('meta[name="theme-color"]');
  if (metaThemeColor) {
    metaThemeColor.setAttribute(
      'content',
      resolvedTheme === 'dark' ? '#0a0a0f' : '#f8fafc'
    );
  }
}

function getStoredTheme(): ThemeMode | null {
  if (typeof localStorage === 'undefined') return null;
  const stored = localStorage.getItem(THEME_STORAGE_KEY);
  if (stored === 'light' || stored === 'dark' || stored === 'system') {
    return stored;
  }
  return null;
}

function storeTheme(theme: ThemeMode): void {
  if (typeof localStorage === 'undefined') return;
  localStorage.setItem(THEME_STORAGE_KEY, theme);
}

// ─────────────────────────────────────────────────────────────────────────────
// Context
// ─────────────────────────────────────────────────────────────────────────────

const ThemeContext = createContext<ThemeContextValue | null>(null);

// ─────────────────────────────────────────────────────────────────────────────
// Provider
// ─────────────────────────────────────────────────────────────────────────────

interface ThemeProviderProps {
  children: ReactNode;
  defaultTheme?: ThemeMode;
}

export function ThemeProvider({
  children,
  defaultTheme = DEFAULT_THEME,
}: ThemeProviderProps) {
  const [theme, setThemeState] = useState<ThemeMode>(() => {
    // Try to get stored theme, fall back to default
    return getStoredTheme() ?? defaultTheme;
  });
  const [isLoading, setIsLoading] = useState(false);
  const [isInitialized, setIsInitialized] = useState(false);

  // Resolve the actual theme (handles 'system' mode)
  const resolvedTheme = useMemo<ResolvedTheme>(() => {
    return resolveTheme(theme);
  }, [theme]);

  // Apply theme to DOM on mount and when theme changes
  useEffect(() => {
    applyThemeToDOM(resolvedTheme);
    setIsInitialized(true);
  }, [resolvedTheme]);

  // Listen for system preference changes (only relevant when theme is 'system')
  useEffect(() => {
    if (theme !== 'system') return;

    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

    const handleChange = () => {
      const newResolved = getSystemTheme();
      applyThemeToDOM(newResolved);
      // Force re-render to update resolvedTheme
      setThemeState((current) => current);
    };

    mediaQuery.addEventListener('change', handleChange);
    return () => mediaQuery.removeEventListener('change', handleChange);
  }, [theme]);

  // Set theme with persistence
  const setTheme = useCallback(async (newTheme: ThemeMode) => {
    setThemeState(newTheme);
    storeTheme(newTheme);

    // Sync to backend if authenticated
    if (tokenManager.getAccessToken()) {
      setIsLoading(true);
      try {
        await api.put('/v2/users/me/theme', { theme: newTheme });
      } catch (error) {
        logWarn('Failed to persist theme preference to backend:', error);
        // Don't throw - theme still works locally
      } finally {
        setIsLoading(false);
      }
    }
  }, []);

  // Toggle between light and dark (ignores system)
  const toggleTheme = useCallback(async () => {
    const nextTheme: ThemeMode = resolvedTheme === 'dark' ? 'light' : 'dark';
    await setTheme(nextTheme);
  }, [resolvedTheme, setTheme]);

  // Context value
  const value = useMemo<ThemeContextValue>(
    () => ({
      theme,
      resolvedTheme,
      isInitialized,
      setTheme,
      toggleTheme,
      isLoading,
    }),
    [theme, resolvedTheme, isInitialized, setTheme, toggleTheme, isLoading]
  );

  return (
    <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Hook
// ─────────────────────────────────────────────────────────────────────────────

export function useTheme(): ThemeContextValue {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useTheme must be used within a ThemeProvider');
  }
  return context;
}

export default ThemeContext;
