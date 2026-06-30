// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

'use client';

/**
 * Shared public Navbar — the public-shared port of the SPA's guest header, so the
 * header is the SAME component in both the Vite SPA and the Next SSR app. Driven
 * by the runtime port (t / Link / hrefFor / branding). Matches the SPA Navbar's
 * structure and classes: a slim utility bar, the brand (real TenantLogo), the
 * primary nav, and the login/sign-up actions.
 *
 * First pass: the language/theme switchers, search overlay, and nav mega-menus
 * are interactive client features of the SPA Navbar that are added next; the
 * static structure + styling here is what makes the header read identical.
 */

import type { ReactNode } from 'react';
import { BadgeCheck, ExternalLink, Menu, Search } from 'lucide-react';

import { TenantLogo } from './TenantLogo';
import { usePublicRuntime } from './runtime';

interface NavItem {
  href: string;
  labelKey: string;
  module?: string;
  feature?: string;
}

const PRIMARY_NAV: NavItem[] = [
  { href: '/feed', labelKey: 'nav.feed', module: 'feed' },
  { href: '/listings', labelKey: 'nav.listings', module: 'listings' },
  { href: '/events', labelKey: 'nav.events', feature: 'events' },
  { href: '/jobs', labelKey: 'nav.jobs', feature: 'job_vacancies' },
  { href: '/marketplace', labelKey: 'nav.marketplace', feature: 'marketplace' },
  { href: '/organisations', labelKey: 'nav.organisations', feature: 'volunteering' },
  { href: '/blog', labelKey: 'nav.blog', feature: 'blog' },
];

const utilityBarActionClass =
  'utility-bar-action inline-flex items-center justify-center rounded-[8px] h-8 min-w-0 px-2.5 gap-1.5 text-xs shrink-0 transition-colors text-theme-muted hover:text-theme-primary';
const utilityBarDividerClass = 'text-theme-subtle/40 select-none px-0.5';

export interface PublicNavbarProps {
  accessibleFrontendUrl?: string | null;
}

export function PublicNavbar({ accessibleFrontendUrl }: PublicNavbarProps): ReactNode {
  const { t, Link, hrefFor, hasModule, hasFeature } = usePublicRuntime();

  const navItems = PRIMARY_NAV.filter((item) => {
    if (item.module) return hasModule(item.module);
    if (item.feature) return hasFeature(item.feature);
    return true;
  });

  return (
    <header className="sticky top-0 left-0 right-0 z-40 backdrop-blur-xl border-b border-theme-default glass-surface overflow-x-clip">
      {/* Utility bar */}
      <div className="hidden sm:block border-b border-[var(--border-default)] bg-[var(--surface-elevated)]">
        <div className="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-end gap-2 h-9 flex-nowrap overflow-x-auto">
            {accessibleFrontendUrl ? (
              <a
                href={accessibleFrontendUrl}
                target="_blank"
                rel="noopener noreferrer"
                className={utilityBarActionClass}
                aria-label={t('nav.accessibility_alpha')}
              >
                <BadgeCheck className="w-4 h-4 shrink-0" aria-hidden="true" />
                <span className="hidden md:inline">{t('nav.accessibility_alpha')}</span>
                <ExternalLink className="hidden lg:block w-3.5 h-3.5 shrink-0" aria-hidden="true" />
              </a>
            ) : null}
            <span className={utilityBarDividerClass}>|</span>
            <span className={utilityBarActionClass}>
              <Search className="w-4 h-4 shrink-0" aria-hidden="true" />
              <span className="hidden md:inline">{t('accessibility.search')}</span>
            </span>
          </div>
        </div>
      </div>

      {/* Main bar */}
      <div className="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between gap-2 min-h-14 sm:min-h-16 py-1.5">
          {/* Left: mobile menu toggle (guest) + brand */}
          <div className="flex items-center gap-2 sm:gap-3 min-w-0 flex-1 lg:flex-none">
            <span
              className="lg:hidden inline-flex items-center justify-center text-theme-muted min-w-[44px] min-h-[44px]"
              aria-hidden="true"
            >
              <Menu className="w-5 h-5" />
            </span>
            <TenantLogo size="md" showName />
          </div>

          {/* Center: primary nav */}
          <nav className="hidden lg:flex items-center gap-1 flex-1 justify-center min-w-0" aria-label={t('aria.main_navigation')}>
            {navItems.map((item) => (
              <Link
                key={item.href}
                href={hrefFor(item.href)}
                className="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all text-theme-muted hover:bg-theme-hover hover:text-theme-primary"
              >
                {t(item.labelKey)}
              </Link>
            ))}
          </nav>

          {/* Right: auth actions */}
          <div className="flex items-center gap-2 shrink-0">
            <Link
              href={hrefFor('/login')}
              className="hidden min-[360px]:inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-theme-primary hover:bg-theme-hover transition-colors"
            >
              {t('nav.login')}
            </Link>
            <Link
              href={hrefFor('/register')}
              className="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white bg-[color:var(--accent-color,#6366f1)] hover:opacity-95 shadow-sm transition-opacity"
            >
              {t('nav.register')}
            </Link>
          </div>
        </div>
      </div>
    </header>
  );
}
