// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { createContext, use } from 'react';
import type { ApiMenu, MenusByLocation } from '@/types/menu';

export interface MenuContextValue {
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

export const emptyMenuContext: MenuContextValue = {
  headerMenus: [],
  mobileMenus: [],
  footerMenus: [],
  allMenus: {},
  isLoading: false,
  hasCustomMenus: false,
  refreshMenus: async () => {},
};

export const MenuContext = createContext<MenuContextValue | null>(null);

export function useMenuContext(): MenuContextValue {
  return use(MenuContext) ?? emptyMenuContext;
}
