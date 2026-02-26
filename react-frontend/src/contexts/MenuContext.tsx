// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { createContext, useContext, useMemo } from 'react';
import { useAuth } from './AuthContext';
import { useTenant } from './TenantContext';
import { useMenus } from '@/hooks/useMenus';
import type { ApiMenu, MenusByLocation } from '@/types/menu';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface MenuContextValue {
  /** Header-main menus (primary navigation) */
  headerMenus: ApiMenu[];
  /** Mobile menus */
  mobileMenus: ApiMenu[];
  /** Footer menus */
  footerMenus: ApiMenu[];
  /** All menus keyed by location */
  allMenus: MenusByLocation;
  /** True while fetching from API */
  isLoading: boolean;
  /** True when API returned menus with actual items */
  hasCustomMenus: boolean;
  /** Force re-fetch menus */
  refreshMenus: () => Promise<void>;
}

// ─────────────────────────────────────────────────────────────────────────────
// Context
// ─────────────────────────────────────────────────────────────────────────────

const MenuContext = createContext<MenuContextValue | null>(null);

// ─────────────────────────────────────────────────────────────────────────────
// Provider
// ─────────────────────────────────────────────────────────────────────────────

export function MenuProvider({ children }: { children: React.ReactNode }) {
  const { isAuthenticated } = useAuth();
  const { tenant } = useTenant();
  const { menus, isLoading, hasCustomMenus, refresh } = useMenus(isAuthenticated, tenant?.id);

  const value = useMemo<MenuContextValue>(
    () => ({
      headerMenus: menus['header-main'] ?? [],
      mobileMenus: menus.mobile ?? [],
      footerMenus: menus.footer ?? [],
      allMenus: menus,
      isLoading,
      hasCustomMenus,
      refreshMenus: refresh,
    }),
    [menus, isLoading, hasCustomMenus, refresh]
  );

  return (
    <MenuContext.Provider value={value}>{children}</MenuContext.Provider>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Hook
// ─────────────────────────────────────────────────────────────────────────────

export function useMenuContext(): MenuContextValue {
  const context = useContext(MenuContext);
  if (!context) {
    throw new Error('useMenuContext must be used within a MenuProvider');
  }
  return context;
}
