// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

'use client';

/**
 * PublicRuntime port — the single seam that lets the SAME presentational
 * components render in BOTH the Vite SPA and the Next.js SSR public frontend.
 *
 * Shared components NEVER import react-router, @/contexts, @/lib/api,
 * react-i18next, react-helmet-async, or the @/components/ui barrel. Everything
 * runtime-specific (the translator, the Link component, href resolution, auth
 * state, tenant branding, feature/module flags) flows IN through this context.
 *
 * - SPA host feeds it from useTenant()/useAuth()/useTranslation().
 * - Next host feeds it from server-resolved tenant bootstrap + createTranslator.
 *
 * Because both hosts render identical component trees and differ ONLY in what
 * passes through this port, pixel-identity is structural, not a checklist.
 */

import { createContext, useContext } from 'react';
import type { ComponentType, ReactNode } from 'react';

export interface PublicRuntimeLinkProps {
  href: string;
  className?: string;
  children?: ReactNode;
  'aria-label'?: string;
  title?: string;
}

export type PublicTranslator = (
  key: string,
  vars?: Record<string, string | number>,
) => string;

export interface PublicRuntimeBranding {
  name?: string;
  tagline?: string;
  primaryColor?: string;
  logoUrl?: string;
}

export interface PublicRuntime {
  /** Translator scoped to the public namespace (SPA: i18next; Next: createTranslator). */
  t: PublicTranslator;
  /** Host link component. Shared components render <Link href={hrefFor('/about')}/>. */
  Link: ComponentType<PublicRuntimeLinkProps>;
  /** Resolves an app-relative path to a tenant-aware href (SPA: tenantPath; Next: withTenantBase). */
  hrefFor: (path: string) => string;
  /** Guest by default in SSR; the SPA reflects real auth. Public pages only swap CTAs on this. */
  isAuthenticated: boolean;
  /** Active locale code, e.g. 'en'. */
  locale: string;
  /** Tenant branding for chrome/theme. */
  branding: PublicRuntimeBranding | null;
  hasFeature: (key: string) => boolean;
  hasModule: (key: string) => boolean;
}

const PublicRuntimeContext = createContext<PublicRuntime | null>(null);

export function PublicRuntimeProvider({
  runtime,
  children,
}: {
  runtime: PublicRuntime;
  children: ReactNode;
}): ReactNode {
  return (
    <PublicRuntimeContext.Provider value={runtime}>
      {children}
    </PublicRuntimeContext.Provider>
  );
}

export function usePublicRuntime(): PublicRuntime {
  const ctx = useContext(PublicRuntimeContext);
  if (ctx === null) {
    throw new Error('usePublicRuntime must be used within a PublicRuntimeProvider');
  }
  return ctx;
}
