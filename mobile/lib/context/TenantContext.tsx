// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

import { getTenantConfig, type TenantConfig } from '@/lib/api/tenant';
import { DEFAULT_TENANT, STORAGE_KEYS } from '@/lib/constants';
import { storage } from '@/lib/storage';

interface TenantContextValue {
  tenant: TenantConfig | null;
  tenantSlug: string;
  isLoading: boolean;
  /** Check if a feature flag is enabled for the current tenant */
  hasFeature: (feature: string) => boolean;
  /** Check if a module is enabled for the current tenant */
  hasModule: (module: string) => boolean;
  /** Switch the active tenant (persists to storage) */
  setTenantSlug: (slug: string) => Promise<void>;
}

const TenantContext = createContext<TenantContextValue | null>(null);

/** Default brand color used before tenant config loads */
const FALLBACK_PRIMARY = '#006FEE';

/** SecureStore key for cached tenant config — mirrors AuthContext's cache-first pattern. */
const TENANT_CONFIG_CACHE_KEY = 'nexus_tenant_config';

export function TenantProvider({ children }: { children: React.ReactNode }) {
  // Start with null slug to indicate "not yet read from storage".
  // This prevents a flicker where the default tenant config renders briefly
  // before the stored tenant slug is read from SecureStore/AsyncStorage.
  const [tenantSlug, setSlug] = useState<string | null>(null);
  const [tenant, setTenant] = useState<TenantConfig | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const loadTenantConfig = useCallback(async (slug: string, skipCache = false) => {
    setIsLoading(true);
    try {
      // Write the slug so the API client's X-Tenant-Slug header is correct.
      await storage.set(STORAGE_KEYS.TENANT_SLUG, slug);

      // Cache-first: render from cached config immediately, then validate
      // in the background. Mirrors AuthContext's session restore pattern.
      if (!skipCache) {
        const cached = await storage.getJson<TenantConfig>(TENANT_CONFIG_CACHE_KEY);
        if (cached) {
          setTenant(cached);
          setIsLoading(false);

          // Background: fetch fresh config and update if successful.
          // On network failure, keep the cached config (offline resilience).
          getTenantConfig()
            .then(async (response) => {
              setTenant(response.data);
              await storage.setJson(TENANT_CONFIG_CACHE_KEY, response.data);
            })
            .catch(() => { /* keep cached config */ });
          return;
        }
      }

      // No cache (first launch or explicit refresh) — must wait for network
      const response = await getTenantConfig();
      setTenant(response.data);
      await storage.setJson(TENANT_CONFIG_CACHE_KEY, response.data);
    } catch {
      // Tenant config failed — app still works with null tenant (graceful degradation)
      setTenant(null);
    } finally {
      setIsLoading(false);
    }
  }, []);

  // Restore previously selected tenant on app start — read storage FIRST,
  // then set slug and load config, so the initial render never shows the
  // wrong tenant.
  useEffect(() => {
    async function init() {
      const stored = await storage.get(STORAGE_KEYS.TENANT_SLUG);
      const slug = stored ?? DEFAULT_TENANT;
      setSlug(slug);
      await loadTenantConfig(slug);
    }
    void init();
  }, [loadTenantConfig]);

  const setTenantSlug = useCallback(
    async (slug: string) => {
      setSlug(slug);
      // Clear stale cache when switching tenants — force fresh fetch
      await storage.remove(TENANT_CONFIG_CACHE_KEY);
      await loadTenantConfig(slug, true);
    },
    [loadTenantConfig],
  );

  const hasFeature = useCallback(
    (feature: string): boolean => {
      return tenant?.features[feature] === true;
    },
    [tenant],
  );

  const hasModule = useCallback(
    (module: string): boolean => {
      return tenant?.modules[module] === true;
    },
    [tenant],
  );

  // Use the resolved slug once storage has been read, otherwise fall back
  // to DEFAULT_TENANT for the public context value type (string, not null).
  const resolvedSlug = tenantSlug ?? DEFAULT_TENANT;

  const value = useMemo<TenantContextValue>(
    () => ({
      tenant,
      tenantSlug: resolvedSlug,
      // Stay in loading state until the stored slug has been read from storage
      // AND the tenant config has been fetched — prevents flicker.
      isLoading: isLoading || tenantSlug === null,
      hasFeature,
      hasModule,
      setTenantSlug,
    }),
    [tenant, resolvedSlug, isLoading, tenantSlug, hasFeature, hasModule, setTenantSlug],
  );

  return <TenantContext.Provider value={value}>{children}</TenantContext.Provider>;
}

export function useTenantContext(): TenantContextValue {
  const ctx = useContext(TenantContext);
  if (!ctx) throw new Error('useTenantContext must be used within <TenantProvider>');
  return ctx;
}

/** Resolve the primary brand color, falling back to NEXUS blue */
export function usePrimaryColor(): string {
  const { tenant } = useTenantContext();
  return tenant?.branding.primary_color ?? FALLBACK_PRIMARY;
}
