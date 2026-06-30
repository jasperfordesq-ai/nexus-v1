// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

'use client';

/**
 * Next host shell for the shared public header. Server passes serializable tenant
 * data; this client boundary builds the PublicRuntime (translator + Link + href +
 * branding + feature/module flags) and renders the SHARED PublicNavbar — so the
 * header is the real component, not a Next-side reimplementation.
 */

import type { ReactNode } from 'react';

import { resolveAssetUrl } from '../lib/assets';
import { createTranslator } from '../lib/i18n';
import { getApiBase, type TenantBootstrap } from '../lib/tenant-api';
import { PublicNavbar } from '../_shared/PublicNavbar';
import {
  PublicRuntimeProvider,
  type PublicRuntime,
  type PublicRuntimeLinkProps,
} from '../_shared/runtime';

function HostLink({ href, className, children, ...rest }: PublicRuntimeLinkProps): ReactNode {
  return (
    <a className={className} href={href} {...rest}>
      {children}
    </a>
  );
}

function isEnabled(value: unknown): boolean {
  if (value === undefined || value === null) return false;
  if (typeof value === 'boolean') return value;
  if (typeof value === 'number') return value > 0;
  if (typeof value === 'string') return ['1', 'true', 'yes', 'enabled'].includes(value.toLowerCase());
  return Boolean(value);
}

export function NavbarHost({
  tenant,
  tenantBasePath,
  accessibleFrontendUrl,
}: {
  tenant: TenantBootstrap | null;
  tenantBasePath: string;
  accessibleFrontendUrl?: string | null;
}): ReactNode {
  const locale = tenant?.default_language ?? 'en';
  const base = tenantBasePath.replace(/\/+$/, '');
  const features = (tenant?.features ?? {}) as Record<string, unknown>;
  const modules = (tenant?.modules ?? {}) as Record<string, unknown>;

  const runtime: PublicRuntime = {
    t: createTranslator(locale),
    Link: HostLink,
    hrefFor: (path) => `${base}${path.startsWith('/') ? path : `/${path}`}`,
    isAuthenticated: false,
    locale,
    branding: tenant
      ? {
          name: tenant.name,
          tagline: tenant.tagline,
          logoUrl: resolveAssetUrl(tenant.branding?.logo_url, getApiBase()),
          primaryColor: tenant.branding?.primary_color,
        }
      : null,
    hasFeature: (key) => isEnabled(features[key]),
    hasModule: (key) => isEnabled(modules[key] ?? features[key]),
  };

  return (
    <PublicRuntimeProvider runtime={runtime}>
      <PublicNavbar accessibleFrontendUrl={accessibleFrontendUrl} />
    </PublicRuntimeProvider>
  );
}
