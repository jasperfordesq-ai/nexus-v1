// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

'use client';

/**
 * Next host shell for the shared FAQ. The Server Component passes serializable
 * props (locale, tenantBasePath); this client boundary builds the PublicRuntime
 * (the translator + Link + href resolver — none of which are RSC-serializable)
 * and renders the SHARED FaqView. The tree still SSRs to crawler-readable HTML.
 */

import type { ReactNode } from 'react';

import { createTranslator } from '../lib/i18n';
import { FaqView } from '../_shared/FaqView';
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

export function FaqHost({ locale, tenantBasePath }: { locale: string; tenantBasePath: string }): ReactNode {
  const base = tenantBasePath.replace(/\/+$/, '');
  const runtime: PublicRuntime = {
    t: createTranslator(locale),
    Link: HostLink,
    hrefFor: (path) => `${base}${path.startsWith('/') ? path : `/${path}`}`,
    isAuthenticated: false,
    locale,
    branding: null,
    hasFeature: () => false,
    hasModule: () => false,
  };

  return (
    <PublicRuntimeProvider runtime={runtime}>
      <FaqView />
    </PublicRuntimeProvider>
  );
}
