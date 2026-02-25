// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// ─────────────────────────────────────────────────────────────────────────────
// Menu System Types
// Matches the PHP backend menu API response contracts
// ─────────────────────────────────────────────────────────────────────────────

export type MenuItemType = 'link' | 'dropdown' | 'page' | 'route' | 'external' | 'divider';

export type MenuLocation = 'header-main' | 'header-secondary' | 'footer' | 'sidebar' | 'mobile';

export interface VisibilityRules {
  requires_auth?: boolean;
  min_role?: 'user' | 'admin' | 'tenant_admin' | 'super_admin';
  requires_feature?: string;
  exclude_roles?: string[];
}

export interface ApiMenuItem {
  id: number | string;
  menu_id?: number;
  parent_id: number | null;
  type: MenuItemType;
  label: string;
  url: string | null;
  route_name?: string | null;
  page_id?: number | null;
  icon: string | null;
  css_class: string | null;
  target: '_self' | '_blank';
  sort_order: number;
  visibility_rules: VisibilityRules | null;
  is_active: number;
  children?: ApiMenuItem[];
}

export interface ApiMenu {
  id: number | string;
  name: string;
  slug: string;
  location: MenuLocation;
  description?: string;
  layout?: string | null;
  is_active: number;
  items: ApiMenuItem[];
}

export interface MenusByLocation {
  'header-main'?: ApiMenu[];
  'header-secondary'?: ApiMenu[];
  footer?: ApiMenu[];
  sidebar?: ApiMenu[];
  mobile?: ApiMenu[];
}
