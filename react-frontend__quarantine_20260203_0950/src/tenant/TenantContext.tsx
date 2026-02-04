/**
 * Tenant Context - Provides tenant configuration to the app
 */

import { createContext, useContext, type ReactNode } from 'react';
import type { TenantBootstrap, TenantFeatures } from '../api/types';

// ===========================================
// CONTEXT
// ===========================================

const TenantContext = createContext<TenantBootstrap | null>(null);

// ===========================================
// PROVIDER
// ===========================================

interface TenantProviderProps {
  value: TenantBootstrap;
  children: ReactNode;
}

export function TenantProvider({ value, children }: TenantProviderProps) {
  return (
    <TenantContext.Provider value={value}>
      {children}
    </TenantContext.Provider>
  );
}

// ===========================================
// HOOKS
// ===========================================

/**
 * Get the current tenant configuration
 * Throws if used outside TenantProvider
 */
export function useTenant(): TenantBootstrap {
  const context = useContext(TenantContext);
  if (!context) {
    throw new Error('useTenant must be used within a TenantProvider');
  }
  return context;
}

/**
 * Check if a feature is enabled for the current tenant
 */
export function useFeature(feature: keyof TenantFeatures): boolean {
  const tenant = useTenant();
  return tenant.features[feature] ?? false;
}

/**
 * Get all enabled features as an array
 */
export function useEnabledFeatures(): (keyof TenantFeatures)[] {
  const tenant = useTenant();
  return (Object.entries(tenant.features) as [keyof TenantFeatures, boolean][])
    .filter(([, enabled]) => enabled)
    .map(([key]) => key);
}
