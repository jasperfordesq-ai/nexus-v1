/**
 * NEXUS Tenant Context
 *
 * Provides:
 * - Tenant bootstrap data
 * - Feature flags
 * - Tenant branding
 * - Module configuration
 * - Automatic tenant detection from subdomain/hostname
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
// Tenant Detection from URL/Subdomain
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Detect tenant from current hostname
 *
 * Examples:
 * - hour-timebank.project-nexus.ie → 'hour-timebank'
 * - app.project-nexus.ie → null (use default/bootstrap)
 * - localhost:5173 → null (development)
 * - project-nexus.ie → null (root domain)
 *
 * The backend will resolve the tenant based on:
 * 1. X-Tenant-ID header (from localStorage, set during login)
 * 2. Hostname/domain lookup in tenants table
 * 3. Default tenant if nothing matches
 */
function detectTenantFromHostname(): string | null {
  const hostname = window.location.hostname;

  // Skip detection for localhost (development)
  if (hostname === 'localhost' || hostname === '127.0.0.1') {
    return null;
  }

  // Known "app" subdomains that aren't tenant-specific
  const appSubdomains = ['app', 'www', 'api', 'admin'];

  // Parse subdomain from hostname
  // e.g., "hour-timebank.project-nexus.ie" → ["hour-timebank", "project-nexus", "ie"]
  const parts = hostname.split('.');

  // Need at least 3 parts for a subdomain (sub.domain.tld)
  if (parts.length >= 3) {
    const subdomain = parts[0];

    // If it's not a reserved subdomain, it might be a tenant slug
    if (!appSubdomains.includes(subdomain.toLowerCase())) {
      return subdomain;
    }
  }

  // No tenant detected from subdomain
  return null;
}

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
  exchange_workflow: false, // Broker control feature
  direct_messaging: true,   // Can be disabled by broker
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

  // Detect tenant from subdomain if not explicitly provided
  const effectiveTenantSlug = tenantSlug || detectTenantFromHostname();

  /**
   * Fetch tenant bootstrap data
   */
  const refreshTenant = async () => {
    setState((prev) => ({ ...prev, isLoading: true, error: null }));

    try {
      // Build endpoint with optional tenant slug
      let endpoint = '/v2/tenant/bootstrap';
      if (effectiveTenantSlug) {
        endpoint += `?slug=${encodeURIComponent(effectiveTenantSlug)}`;
      }

      const response = await api.get<TenantConfig>(endpoint, { skipAuth: true });

      if (response.success && response.data) {
        const tenant = response.data;

        // Only set tenant ID if no tenant was pre-selected (e.g., during login)
        // This allows users to access tenants other than their "home" tenant
        const existingTenantId = tokenManager.getTenantId();
        if (tenant.id && !existingTenantId) {
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

  // Fetch tenant on mount or when slug changes
  useEffect(() => {
    refreshTenant();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [effectiveTenantSlug]);

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
