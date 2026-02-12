/**
 * NEXUS Tenant Context
 *
 * Provides:
 * - Tenant bootstrap data
 * - Feature flags
 * - Tenant branding
 * - Module configuration
 * - Automatic tenant detection from URL (subdomain or path slug)
 * - Tenant slug for URL-scoped navigation
 *
 * Implements TRS-001 resolution rules R1-R4.
 * @see docs/TRS-001-TENANT-RESOLUTION-SPEC.md
 */

import {
  createContext,
  useContext,
  useState,
  useEffect,
  useMemo,
  useCallback,
  type ReactNode,
} from 'react';
import { api, tokenManager, fetchCsrfToken } from '@/lib/api';
import { detectTenantFromUrl, tenantPath as buildTenantPath } from '@/lib/tenant-routing';
import type { TenantConfig, TenantFeatures, TenantModules, TenantBranding } from '@/types';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface TenantState {
  tenant: TenantConfig | null;
  isLoading: boolean;
  error: string | null;
  /** Slug that was NOT found (bootstrap returned error). Used for soft 404. */
  notFoundSlug: string | null;
}

interface TenantContextValue extends TenantState {
  features: TenantFeatures;
  modules: TenantModules;
  branding: TenantBranding;
  hasFeature: (feature: keyof TenantFeatures) => boolean;
  hasModule: (module: keyof TenantModules) => boolean;
  refreshTenant: () => Promise<void>;
  /** The current tenant slug from URL (path or subdomain). Null if no slug in URL. */
  tenantSlug: string | null;
  /** Build a path with the tenant slug prefix (if present). */
  tenantPath: (path: string) => string;
}

// Default features — synced with PHP TenantBootstrapController::buildFeaturesData() defaults
const defaultFeatures: TenantFeatures = {
  gamification: false,
  groups: true,
  events: true,
  marketplace: false,
  messaging: true,
  volunteering: false,
  connections: true,
  polls: false,
  goals: false,
  federation: false,
  blog: true,
  resources: false,
  reviews: true,
  search: true,
  exchange_workflow: false,
  direct_messaging: true,
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
  /** Tenant slug from route param (/:tenantSlug prefix). Takes priority over URL detection. */
  tenantSlug?: string;
}

export function TenantProvider({ children, tenantSlug }: TenantProviderProps) {
  const [state, setState] = useState<TenantState>({
    tenant: null,
    isLoading: true,
    error: null,
    notFoundSlug: null,
  });

  // Determine effective slug: route param > URL detection (subdomain/path)
  const detected = useMemo(() => detectTenantFromUrl(), []);
  const effectiveTenantSlug = tenantSlug || detected.slug;

  /**
   * Fetch tenant bootstrap data
   */
  const refreshTenant = useCallback(async () => {
    setState((prev) => ({ ...prev, isLoading: true, error: null, notFoundSlug: null }));

    try {
      // Build endpoint with optional tenant slug
      // Per TRS-001: NO ?domain= parameter. Slug only.
      let endpoint = '/v2/tenant/bootstrap';
      if (effectiveTenantSlug) {
        endpoint += `?slug=${encodeURIComponent(effectiveTenantSlug)}`;
      }

      const response = await api.get<TenantConfig>(endpoint, { skipAuth: true });

      if (response.success && response.data) {
        const tenant = response.data;

        // TRS-001: Stale localStorage override
        // If URL resolved a tenant and it differs from stored value, override.
        const storedTenantId = tokenManager.getTenantId();
        if (tenant.id) {
          if (storedTenantId && String(storedTenantId) !== String(tenant.id)) {
            console.warn(
              `[TenantContext] Overriding stale localStorage tenant_id. ` +
              `Stored: ${storedTenantId}, URL-resolved: ${tenant.id}`
            );
          }
          tokenManager.setTenantId(tenant.id);
        }

        // Fetch CSRF token for form submissions
        await fetchCsrfToken();

        setState({
          tenant,
          isLoading: false,
          error: null,
          notFoundSlug: null,
        });
      } else {
        // Bootstrap failed — if we had a slug, this is an unknown tenant (soft 404)
        setState({
          tenant: null,
          isLoading: false,
          error: response.error ?? 'Failed to load tenant configuration',
          notFoundSlug: effectiveTenantSlug || null,
        });
      }
    } catch (err) {
      setState({
        tenant: null,
        isLoading: false,
        error: err instanceof Error ? err.message : 'Failed to load tenant configuration',
        notFoundSlug: null,
      });
    }
  }, [effectiveTenantSlug]);

  // Fetch tenant on mount or when slug changes
  useEffect(() => {
    refreshTenant();
  }, [refreshTenant]);

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
  const hasFeature = useCallback((feature: keyof TenantFeatures): boolean => {
    return features[feature] ?? false;
  }, [features]);

  /**
   * Check if module is enabled
   */
  const hasModule = useCallback((module: keyof TenantModules): boolean => {
    return modules[module] ?? false;
  }, [modules]);

  /**
   * Build a path with the current tenant slug prefix.
   * Preserves the slug in all internal navigation.
   */
  const tenantPath = useCallback((path: string): string => {
    return buildTenantPath(path, effectiveTenantSlug);
  }, [effectiveTenantSlug]);

  const value = useMemo<TenantContextValue>(
    () => ({
      ...state,
      features,
      modules,
      branding,
      hasFeature,
      hasModule,
      refreshTenant,
      tenantSlug: effectiveTenantSlug || null,
      tenantPath,
    }),
    [state, features, modules, branding, hasFeature, hasModule, refreshTenant, effectiveTenantSlug, tenantPath]
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
