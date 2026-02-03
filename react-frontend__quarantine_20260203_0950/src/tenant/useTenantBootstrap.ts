/**
 * Tenant Bootstrap Hook - Fetches and applies tenant configuration
 *
 * Hardening:
 * - cache: 'no-store' to prevent stale tenant config
 * - Error details include status + body snippet (no secrets)
 * - Warns if VITE_TENANT_ID mismatches returned tenant
 */

import { useState, useEffect, useCallback } from 'react';
import type { TenantBootstrap } from '../api/types';

// ===========================================
// CONFIGURATION
// ===========================================

const API_BASE = (import.meta.env.VITE_API_BASE || '').replace(/\/+$/, '');
const TENANT_ID = import.meta.env.VITE_TENANT_ID || '';
const IS_DEV = import.meta.env.DEV;

// ===========================================
// TYPES
// ===========================================

interface TenantBootstrapState {
  tenant: TenantBootstrap | null;
  loading: boolean;
  error: string | null;
  statusCode: number | null;
}

interface TenantBootstrapResponse {
  data: TenantBootstrap;
  meta: {
    base_url: string;
  };
}

// ===========================================
// BRANDING
// ===========================================

/**
 * Apply tenant branding to the document
 */
function applyTenantBranding(tenant: TenantBootstrap): void {
  // Set document title
  const title = tenant.seo?.meta_title || tenant.name || 'NEXUS';
  document.title = title;

  // Set meta description
  if (tenant.seo?.meta_description) {
    let metaDesc = document.querySelector('meta[name="description"]');
    if (!metaDesc) {
      metaDesc = document.createElement('meta');
      metaDesc.setAttribute('name', 'description');
      document.head.appendChild(metaDesc);
    }
    metaDesc.setAttribute('content', tenant.seo.meta_description);
  }

  // Set favicon
  if (tenant.branding.favicon_url) {
    const existingFavicon = document.querySelector("link[rel~='icon']") as HTMLLinkElement;
    if (existingFavicon) {
      existingFavicon.href = tenant.branding.favicon_url;
    } else {
      const favicon = document.createElement('link');
      favicon.rel = 'icon';
      favicon.href = tenant.branding.favicon_url;
      document.head.appendChild(favicon);
    }
  }

  // Set CSS custom property for primary color
  if (tenant.branding.primary_color) {
    document.documentElement.style.setProperty(
      '--color-primary',
      tenant.branding.primary_color
    );
    // Also set Hero UI compatible variable
    document.documentElement.style.setProperty(
      '--heroui-primary',
      tenant.branding.primary_color
    );
  }
}

/**
 * Truncate string for safe error display (no secrets)
 */
function truncateForDisplay(str: string, maxLen: number = 200): string {
  if (str.length <= maxLen) return str;
  return str.slice(0, maxLen) + '...';
}

// ===========================================
// HOOK
// ===========================================

/**
 * Hook to fetch and manage tenant bootstrap data
 */
export function useTenantBootstrap(): TenantBootstrapState & { retry: () => void } {
  const [state, setState] = useState<TenantBootstrapState>({
    tenant: null,
    loading: true,
    error: null,
    statusCode: null,
  });

  const fetchTenant = useCallback(async () => {
    setState(prev => ({ ...prev, loading: true, error: null, statusCode: null }));

    const url = `${API_BASE}/api/v2/tenant/bootstrap`;

    try {
      // Build headers
      const headers: HeadersInit = {
        'Accept': 'application/json',
      };

      // Add tenant ID header in dev mode
      if (IS_DEV && TENANT_ID) {
        headers['X-Tenant-ID'] = TENANT_ID;
      }

      // Fetch with no-store to prevent cached stale tenant config
      const response = await fetch(url, {
        method: 'GET',
        headers,
        credentials: IS_DEV ? 'include' : 'same-origin',
        cache: 'no-store', // Critical: always fetch fresh tenant config
      });

      const statusCode = response.status;

      if (!response.ok) {
        // Read body for error details (truncated for safety)
        let bodySnippet = '';
        try {
          const text = await response.text();
          bodySnippet = truncateForDisplay(text);
        } catch {
          bodySnippet = '(unable to read response body)';
        }

        const errorMessage = `Failed to load tenant configuration.\n\nStatus: ${statusCode}\nResponse: ${bodySnippet}`;

        setState({
          tenant: null,
          loading: false,
          error: errorMessage,
          statusCode,
        });
        return;
      }

      const json: TenantBootstrapResponse = await response.json();
      const tenant = json.data;

      // Warn if configured TENANT_ID doesn't match returned tenant
      if (IS_DEV && TENANT_ID) {
        const configuredId = String(TENANT_ID);
        const returnedId = String(tenant.id);
        if (configuredId !== returnedId) {
          console.warn(
            `[Tenant Mismatch] VITE_TENANT_ID is "${configuredId}" but API returned tenant ID "${returnedId}" (${tenant.name}).\n` +
            `This may indicate misrouted requests or incorrect configuration.`
          );
        }
      }

      // Apply branding
      applyTenantBranding(tenant);

      setState({
        tenant,
        loading: false,
        error: null,
        statusCode: 200,
      });
    } catch (err) {
      // Network error or other failure
      const message = err instanceof Error ? err.message : 'Failed to fetch';
      const errorMessage = `Failed to load tenant configuration.\n\nError: ${message}\nURL: ${url}`;

      setState({
        tenant: null,
        loading: false,
        error: errorMessage,
        statusCode: null,
      });
    }
  }, []);

  useEffect(() => {
    fetchTenant();
  }, [fetchTenant]);

  return {
    ...state,
    retry: fetchTenant,
  };
}
