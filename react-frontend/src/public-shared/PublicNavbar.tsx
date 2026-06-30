// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

'use client';

/**
 * Shared public Navbar — the public-shared port of the SPA's guest header, so the
 * header is the SAME component in both the Vite SPA and the Next SSR app. Driven
 * by the runtime port (t / Link / hrefFor / branding / hasFeature / hasModule).
 * Reproduces the SPA header: the slim utility bar, the brand (real TenantLogo),
 * the Timebanking / Community / More mega-dropdowns, and the Log In / Sign Up
 * actions — using the same translation keys and HeroUI v3 components.
 */

import type { ReactNode } from 'react';
import { Button, Dropdown, Kbd, Label } from '@heroui/react';
import {
  ArrowRightLeft,
  BadgeCheck,
  BookOpen,
  Briefcase,
  Building2,
  Calendar,
  ChevronDown,
  ExternalLink,
  FolderOpen,
  Globe,
  Heart,
  HelpCircle,
  Info,
  ListTodo,
  Menu,
  Moon,
  Newspaper,
  Search,
  ShoppingBag,
  Users,
  Wallet,
  type LucideIcon,
} from 'lucide-react';

import { TenantLogo } from './TenantLogo';
import { usePublicRuntime, type PublicRuntime } from './runtime';

interface NavItem {
  href: string;
  labelKey: string;
  icon: LucideIcon;
  module?: string;
  feature?: string;
}

interface NavGroup {
  labelKey: string;
  items: NavItem[];
}

const NAV_GROUPS: NavGroup[] = [
  {
    labelKey: 'nav.timebanking',
    items: [
      { href: '/listings', labelKey: 'nav.listings', icon: ListTodo, module: 'listings' },
      { href: '/exchanges', labelKey: 'nav.exchanges', icon: ArrowRightLeft, feature: 'exchange_workflow' },
      { href: '/group-exchanges', labelKey: 'nav.group_exchanges', icon: Users, feature: 'group_exchanges' },
      { href: '/wallet', labelKey: 'nav.wallet', icon: Wallet, module: 'wallet' },
    ],
  },
  {
    labelKey: 'nav.community',
    items: [
      { href: '/members', labelKey: 'nav.members', icon: Users, feature: 'connections' },
      { href: '/events', labelKey: 'nav.events', icon: Calendar, feature: 'events' },
      { href: '/groups', labelKey: 'nav.groups', icon: Users, feature: 'groups' },
      { href: '/volunteering', labelKey: 'nav.volunteering', icon: Heart, feature: 'volunteering' },
      { href: '/organisations', labelKey: 'nav.organisations', icon: Building2, feature: 'volunteering' },
    ],
  },
  {
    labelKey: 'nav.more',
    items: [
      { href: '/blog', labelKey: 'nav.blog', icon: Newspaper, feature: 'blog' },
      { href: '/resources', labelKey: 'nav.resources', icon: FolderOpen, feature: 'resources' },
      { href: '/kb', labelKey: 'nav.knowledge_base', icon: BookOpen },
      { href: '/marketplace', labelKey: 'nav.marketplace', icon: ShoppingBag, feature: 'marketplace' },
      { href: '/jobs', labelKey: 'nav.jobs', icon: Briefcase, feature: 'job_vacancies' },
      { href: '/about', labelKey: 'nav.about', icon: Info },
      { href: '/help', labelKey: 'footer.help_center', icon: HelpCircle },
    ],
  },
];

const utilityBarActionClass =
  'utility-bar-action inline-flex items-center justify-center rounded-[8px] h-8 min-w-0 px-2.5 gap-1.5 text-xs shrink-0 transition-colors text-theme-muted hover:text-theme-primary';
const utilityBarDividerClass = 'text-theme-subtle/40 select-none px-0.5';
const navTriggerClass =
  'flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition-all text-theme-muted hover:bg-theme-hover hover:text-theme-primary !bg-transparent data-[hovered=true]:bg-theme-hover';

function visibleItems(items: NavItem[], rt: PublicRuntime): NavItem[] {
  return items.filter((item) => {
    if (item.module) return rt.hasModule(item.module);
    if (item.feature) return rt.hasFeature(item.feature);
    return true;
  });
}

function NavDropdown({ group, items }: { group: NavGroup; items: NavItem[] }): ReactNode {
  const { t, hrefFor } = usePublicRuntime();
  return (
    <Dropdown>
      <Dropdown.Trigger>
        <Button size="sm" variant="ghost" className={navTriggerClass}>
          {t(group.labelKey)}
          <ChevronDown className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
        </Button>
      </Dropdown.Trigger>
      <Dropdown.Popover className="min-w-[240px] bg-[var(--surface-dropdown,var(--surface-elevated))] border border-theme-default shadow-xl">
        <Dropdown.Menu aria-label={t(group.labelKey)}>
          {items.map((item) => {
            const Icon = item.icon;
            return (
              <Dropdown.Item key={item.href} id={item.href} href={hrefFor(item.href)} textValue={t(item.labelKey)}>
                <Icon className="w-4 h-4 shrink-0 text-theme-muted" aria-hidden="true" />
                <Label className="text-theme-primary">{t(item.labelKey)}</Label>
              </Dropdown.Item>
            );
          })}
        </Dropdown.Menu>
      </Dropdown.Popover>
    </Dropdown>
  );
}

export interface PublicNavbarProps {
  accessibleFrontendUrl?: string | null;
}

export function PublicNavbar({ accessibleFrontendUrl }: PublicNavbarProps): ReactNode {
  const rt = usePublicRuntime();
  const { t, Link, hrefFor } = rt;

  const groups = NAV_GROUPS
    .map((group) => ({ group, items: visibleItems(group.items, rt) }))
    .filter((entry) => entry.items.length > 0);

  return (
    <header className="sticky top-0 left-0 right-0 z-40 backdrop-blur-xl border-b border-theme-default glass-surface overflow-x-clip">
      {/* Utility bar */}
      <div className="hidden sm:block border-b border-theme-default bg-theme-elevated">
        <div className="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-end gap-1.5 h-9 flex-nowrap overflow-x-auto">
            {accessibleFrontendUrl ? (
              <>
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
                <span className={utilityBarDividerClass}>|</span>
              </>
            ) : null}
            <button type="button" className={utilityBarActionClass} aria-label={t('language.select_language')}>
              <Globe className="w-4 h-4 shrink-0" aria-hidden="true" />
              <span className="hidden md:inline uppercase">{rt.locale}</span>
            </button>
            <button type="button" className={utilityBarActionClass} aria-label={t('theme.toggle_theme')}>
              <Moon className="w-4 h-4 shrink-0" aria-hidden="true" />
            </button>
            <span className={utilityBarDividerClass}>|</span>
            <button type="button" className={utilityBarActionClass} aria-label={t('accessibility.search')}>
              <Search className="w-4 h-4 shrink-0" aria-hidden="true" />
              <span className="hidden md:inline">{t('accessibility.search')}</span>
              <Kbd className="hidden lg:inline-flex ms-0.5 text-[10px] !bg-transparent !border-transparent !shadow-none text-theme-subtle">
                {t('keyboard.command_symbol')}{t('keyboard.k_key')}
              </Kbd>
            </button>
          </div>
        </div>
      </div>

      {/* Main bar */}
      <div className="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between gap-2 min-h-14 sm:min-h-16 py-1.5">
          {/* Left: mobile toggle + brand */}
          <div className="flex items-center gap-2 sm:gap-3 min-w-0 flex-1 lg:flex-none">
            <span className="lg:hidden inline-flex items-center justify-center text-theme-muted min-w-[44px] min-h-[44px]" aria-hidden="true">
              <Menu className="w-5 h-5" />
            </span>
            <TenantLogo size="md" showName />
          </div>

          {/* Center: dropdown nav */}
          <nav className="hidden lg:flex items-center gap-1 flex-1 justify-center min-w-0" aria-label={t('aria.main_navigation')}>
            {groups.map((entry) => (
              <NavDropdown key={entry.group.labelKey} group={entry.group} items={entry.items} />
            ))}
          </nav>

          {/* Right: auth actions */}
          <div className="flex items-center gap-1 shrink-0">
            <Link href={hrefFor('/login')} className="hidden min-[360px]:inline-flex">
              <Button variant="ghost" size="sm" className="text-theme-secondary hover:text-theme-primary min-w-0 px-2 sm:px-3">
                {t('auth.log_in')}
              </Button>
            </Link>
            <Link href={hrefFor('/register')}>
              <Button size="sm" variant="primary" className="font-medium min-w-0 px-2 sm:px-3">
                {t('auth.sign_up')}
              </Button>
            </Link>
          </div>
        </div>
      </div>
    </header>
  );
}
