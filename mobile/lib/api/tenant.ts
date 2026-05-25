// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export interface TenantBranding {
  logo_url: string | null;
  primary_color: string;
  favicon_url: string | null;
  og_image_url: string | null;
}

export interface TenantConfig {
  name: string;
  slug: string;
  tagline: string | null;
  branding: TenantBranding;
  features: Record<string, boolean>;
  modules: Record<string, boolean>;
  config: {
    time_unit: string;
    time_unit_plural: string;
    footer_text: string | null;
  };
  supported_languages: string[];
  default_language: string;
}

export interface TenantListItem {
  id: number;
  slug: string;
  name: string;
  logo_url: string | null;
}

/** GET /api/v2/tenant/bootstrap — config & branding for the active tenant (from X-Tenant-Slug header) */
export function getTenantConfig(): Promise<{ data: TenantConfig }> {
  return api.get<{ data: TenantConfig }>(`${API_V2}/tenant/bootstrap`);
}

/** GET /api/v2/tenants — public list of available tenants (for tenant picker) */
export function listTenants(): Promise<{ data: TenantListItem[] }> {
  return api.get<{ data: TenantListItem[] }>(`${API_V2}/tenants`);
}
