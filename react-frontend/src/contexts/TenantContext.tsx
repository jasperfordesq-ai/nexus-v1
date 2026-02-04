/**
 * NEXUS Tenant Context
 *
 * Provides:
 * - Tenant bootstrap data
 * - Feature flags
 * - Tenant branding
 * - Module configuration
 */

import {
  createContext,
  useContext,
  useState,
  useEffect,
  useMemo,
  type ReactNode,
} from 'react';
import { api, tokenManager, fetchCsrfToken } from '@/lib/api';
import type { TenantConfig, TenantFeatures, TenantModules, TenantBranding } from '@/types';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface TenantState {
  tenant: TenantConfig | null;
  isLoading: boolean;
  error: string | null;
}

interface TenantContextValue extends TenantState {
  features: TenantFeatures;
  modules: TenantModules;
  branding: TenantBranding;
  hasFeature: (feature: keyof TenantFeatures) => boolean;
  hasModule: (module: keyof TenantModules) => boolean;
  refreshTenant: () => Promise<void>;
}

// Default features (all disabled)
const defaultFeatures: TenantFeatures = {
  gamification: false,
  groups: false,
  events: false,
  marketplace: false,
  messaging: true,  // Always enabled
  volunteering: false,
  connections: false,
  polls: false,
  goals: false,
  federation: false,
  blog: false,
  resources: false,
  reviews: false,
  search: true,     // Always enabled
};

// Default modules (all enabled)
const defaultModules: TenantModules = {
  feed: true,
  listings: true,
  messages: true,
  wallet: true,
  notifications: true,
  profile: true,
  settings: true,
  dashboard: true,
};

// Default branding
const defaultBranding: TenantBranding = {
  name: 'NEXUS',
  tagline: 'Time Banking Platform',
  logo: undefined,
  favicon: undefined,
  primaryColor: '#6366f1',
  secondaryColor: '#a855f7',
};

// ─────────────────────────────────────────────────────────────────────────────
// Context
// ─────────────────────────────────────────────────────────────────────────────

const TenantContext = createContext<TenantContextValue | null>(null);

// ─────────────────────────────────────────────────────────────────────────────
// Provider
// ─────────────────────────────────────────────────────────────────────────────

interface TenantProviderProps {
  children: ReactNode;
  tenantSlug?: string;  // Can be passed from URL or environment
}

export function TenantProvider({ children, tenantSlug }: TenantProviderProps) {
  const [state, setState] = useState<TenantState>({
    tenant: null,
    isLoading: true,
    error: null,
  });

  /**
   * Fetch tenant bootstrap data
   */
  const refreshTenant = async () => {
    setState((prev) => ({ ...prev, isLoading: true, error: null }));

    try {
      // Build endpoint with optional tenant slug
      let endpoint = '/v2/tenant/bootstrap';
      if (tenantSlug) {
        endpoint += `?slug=${encodeURIComponent(tenantSlug)}`;
      }

      const response = await api.get<TenantConfig>(endpoint, { skipAuth: true });

      if (response.success && response.data) {
        const tenant = response.data;

        // Store tenant ID for future requests
        if (tenant.id) {
          tokenManager.setTenantId(tenant.id);
        }

        // Fetch CSRF token for form submissions
        await fetchCsrfToken();

        setState({
          tenant,
          isLoading: false,
          error: null,
        });
      } else {
        setState({
          tenant: null,
          isLoading: false,
          error: response.error ?? 'Failed to load tenant configuration',
        });
      }
    } catch (err) {
      setState({
        tenant: null,
        isLoading: false,
        error: err instanceof Error ? err.message : 'Failed to load tenant configuration',
      });
    }
  };

  // Fetch tenant on mount
  useEffect(() => {
    refreshTenant();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tenantSlug]);

  /**
   * Get features with fallback to defaults
   */
  const features = useMemo<TenantFeatures>(() => {
    if (!state.tenant?.features) {
      return defaultFeatures;
    }
    return { ...defaultFeatures, ...state.tenant.features };
  }, [state.tenant?.features]);

  /**
   * Get modules with fallback to defaults
   */
  const modules = useMemo<TenantModules>(() => {
    if (!state.tenant?.modules) {
      return defaultModules;
    }
    return { ...defaultModules, ...state.tenant.modules };
  }, [state.tenant?.modules]);

  /**
   * Get branding with fallback to defaults
   */
  const branding = useMemo<TenantBranding>(() => {
    if (!state.tenant?.branding) {
      return defaultBranding;
    }
    return { ...defaultBranding, ...state.tenant.branding };
  }, [state.tenant?.branding]);

  /**
   * Check if feature is enabled
   */
  const hasFeature = (feature: keyof TenantFeatures): boolean => {
    return features[feature] ?? false;
  };

  /**
   * Check if module is enabled
   */
  const hasModule = (module: keyof TenantModules): boolean => {
    return modules[module] ?? true;
  };

  const value = useMemo<TenantContextValue>(
    () => ({
      ...state,
      features,
      modules,
      branding,
      hasFeature,
      hasModule,
      refreshTenant,
    }),
    [state, features, modules, branding]
  );

  return (
    <TenantContext.Provider value={value}>{children}</TenantContext.Provider>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Hooks
// ─────────────────────────────────────────────────────────────────────────────

export function useTenant(): TenantContextValue {
  const context = useContext(TenantContext);

  if (!context) {
    throw new Error('useTenant must be used within a TenantProvider');
  }

  return context;
}

/**
 * Convenience hook for feature checking
 */
export function useFeature(feature: keyof TenantFeatures): boolean {
  const { hasFeature } = useTenant();
  return hasFeature(feature);
}

/**
 * Convenience hook for module checking
 */
export function useModule(module: keyof TenantModules): boolean {
  const { hasModule } = useTenant();
  return hasModule(module);
}

export default TenantContext;
