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

export function TenantProvider({ children }: { children: React.ReactNode }) {
  const [tenantSlug, setSlug] = useState<string>(DEFAULT_TENANT);
  const [tenant, setTenant] = useState<TenantConfig | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const loadTenantConfig = useCallback(async (slug: string) => {
    setIsLoading(true);
    try {
      // The API client attaches X-Tenant-Slug from storage automatically.
      // We first write the slug so the header is set correctly.
      await storage.set(STORAGE_KEYS.TENANT_SLUG, slug);
      const response = await getTenantConfig();
      setTenant(response.data);
    } catch {
      // Tenant config failed — app still works with null tenant (graceful degradation)
      setTenant(null);
    } finally {
      setIsLoading(false);
    }
  }, []);

  // Restore previously selected tenant on app start
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
      await loadTenantConfig(slug);
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

  const value = useMemo<TenantContextValue>(
    () => ({
      tenant,
      tenantSlug,
      isLoading,
      hasFeature,
      hasModule,
      setTenantSlug,
    }),
    [tenant, tenantSlug, isLoading, hasFeature, hasModule, setTenantSlug],
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
