// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback, useRef } from 'react';
import { menuApi } from '@/lib/api';
import type { ApiMenu, MenusByLocation } from '@/types/menu';

interface UseMenusReturn {
  /** Menus keyed by location */
  menus: MenusByLocation;
  /** True while fetching */
  isLoading: boolean;
  /** Error message if fetch failed */
  error: string | null;
  /** True when API returned real menu data with items */
  hasCustomMenus: boolean;
  /** Force re-fetch menus */
  refresh: () => Promise<void>;
}

/**
 * Fetches navigation menus from the public menu API.
 * Re-fetches when authentication state or tenant changes (menus are role-filtered
 * and tenant-scoped server-side).
 */
export function useMenus(isAuthenticated: boolean, tenantId?: number | null): UseMenusReturn {
  const [menus, setMenus] = useState<MenusByLocation>({});
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [hasCustomMenus, setHasCustomMenus] = useState(false);
  const mountedRef = useRef(true);

  const fetchMenus = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await menuApi.getMenus();

      if (!mountedRef.current) return;

      if (response.success && response.data) {
        const data = response.data;

        // API returns either an array (when location filter used) or an object keyed by location
        let menusByLoc: MenusByLocation;
        if (Array.isArray(data)) {
          // Group by location
          menusByLoc = {};
          for (const menu of data as ApiMenu[]) {
            const loc = menu.location as keyof MenusByLocation;
            if (!menusByLoc[loc]) menusByLoc[loc] = [];
            menusByLoc[loc]!.push(menu);
          }
        } else {
          menusByLoc = data as MenusByLocation;
        }

        setMenus(menusByLoc);

        // Detect real custom menus vs DefaultMenus fallback.
        // DefaultMenus have string IDs like "default-main" and slugs like "default-main-nav".
        // Real DB menus have numeric IDs. Only switch to API-driven rendering for real menus,
        // since DefaultMenus use Font Awesome icons incompatible with React's Lucide renderer.
        const isRealCustomMenu = (m: ApiMenu) =>
          typeof m.id === 'number' && !String(m.slug).startsWith('default-');

        const hasItems = Object.values(menusByLoc).some(
          (locationMenus) =>
            Array.isArray(locationMenus) &&
            locationMenus.some((m) => isRealCustomMenu(m) && m.items && m.items.length > 0)
        );
        setHasCustomMenus(hasItems);
      } else {
        setError(response.error ?? 'Failed to load menus');
        setHasCustomMenus(false);
      }
    } catch {
      if (mountedRef.current) {
        setError('Failed to load menus');
        setHasCustomMenus(false);
      }
    } finally {
      if (mountedRef.current) {
        setIsLoading(false);
      }
    }
  }, []);

  useEffect(() => {
    mountedRef.current = true;
    fetchMenus();
    return () => {
      mountedRef.current = false;
    };
  }, [fetchMenus, isAuthenticated, tenantId]);

  return { menus, isLoading, error, hasCustomMenus, refresh: fetchMenus };
}
