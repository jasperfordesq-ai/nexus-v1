// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Proves the SHARED public header (PublicNavbar + TenantLogo, synced from
 * react-frontend/src/public-shared) renders server-side with the real brand, the
 * tenant-aware primary nav, and the login/sign-up actions — independent of the
 * live tenant fetch.
 */

import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import { PublicNavbar } from '../../_shared/PublicNavbar';
import { PublicRuntimeProvider, type PublicRuntime, type PublicRuntimeLinkProps } from '../../_shared/runtime';

function AnchorLink({ href, className, children }: PublicRuntimeLinkProps) {
  return (
    <a className={className} href={href}>
      {children}
    </a>
  );
}

function makeRuntime(): PublicRuntime {
  return {
    t: (key) => key,
    Link: AnchorLink,
    hrefFor: (path) => `/hour-timebank${path}`,
    isAuthenticated: false,
    locale: 'en',
    branding: { name: 'TimeBank Ireland', primaryColor: '#6366f1' },
    hasFeature: () => true,
    hasModule: () => true,
  };
}

function render(): string {
  return renderToStaticMarkup(
    <PublicRuntimeProvider runtime={makeRuntime()}>
      <PublicNavbar accessibleFrontendUrl="https://accessible.project-nexus.ie" />
    </PublicRuntimeProvider>,
  );
}

describe('shared PublicNavbar SSR', () => {
  const html = render();

  it('renders the real brand (TenantLogo)', () => {
    expect(html).toContain('TimeBank Ireland');
  });

  it('renders the tenant-aware primary nav', () => {
    expect(html).toContain('href="/hour-timebank/listings"');
    expect(html).toContain('href="/hour-timebank/events"');
    expect(html).toContain('href="/hour-timebank/blog"');
  });

  it('renders login + sign-up actions', () => {
    expect(html).toContain('href="/hour-timebank/login"');
    expect(html).toContain('href="/hour-timebank/register"');
  });

  it('renders the utility bar (accessible frontend + search)', () => {
    expect(html).toContain('https://accessible.project-nexus.ie');
    expect(html).toContain('utility-bar-action');
  });
});
