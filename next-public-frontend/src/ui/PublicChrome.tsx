// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { CSSProperties, ReactNode } from 'react';
import Image from 'next/image';
import { Button, Link } from '@heroui/react';
import {
  BadgeCheck,
  BookOpen,
  Briefcase,
  Calendar,
  CircleHelp,
  FileText,
  House,
  Info,
  ListTodo,
  Menu,
  Search,
  ShoppingBag,
  Users,
  X,
} from 'lucide-react';

import { resolveAssetUrl, safeCssColor } from '../lib/assets';
import { NavbarHost } from './NavbarHost';
import type { Translator } from '../lib/i18n';
import type { TenantBootstrap } from '../lib/tenant-api';
import { getApiBase } from '../lib/tenant-api';

interface PublicChromeProps {
  canonicalUrl: string;
  children: ReactNode;
  tenant: TenantBootstrap | null;
  tenantBasePath: string;
  t: Translator;
}

interface PublicChromeContext {
  accessibleFrontendUrl: string | null;
  logoUrl: string | undefined;
  navItems: PublicNavItem[];
  style: CSSProperties | undefined;
  tagline: string;
  tenant: TenantBootstrap | null;
  tenantBasePath: string;
  tenantName: string;
  t: Translator;
}

interface PublicNavItem {
  href: string;
  icon: typeof House;
  labelKey: string;
  module?: string;
}

const utilityBarActionBase =
  'utility-bar-action inline-flex items-center justify-center rounded-[8px] !bg-transparent hover:!bg-transparent data-[hovered=true]:!bg-transparent data-[pressed=true]:!bg-transparent !shadow-none border-0 outline-solid outline-transparent focus-visible:outline-2 focus-visible:outline-focus focus-visible:outline-offset-2 h-8 min-w-0 px-2.5 gap-1.5 text-xs shrink-0 transition-colors';
const utilityBarActionClass = `${utilityBarActionBase} text-theme-muted hover:text-theme-primary`;
const utilityBarIconActionClass = `${utilityBarActionClass} w-8 min-w-8 px-0`;
const utilityBarDividerClass = 'text-[var(--border-default)] text-xs select-none shrink-0 opacity-70';

const publicNavItems: PublicNavItem[] = [
  { href: '', icon: House, labelKey: 'navigation.home' },
  { href: 'listings', icon: ListTodo, labelKey: 'navigation.listings', module: 'listings' },
  { href: 'events', icon: Calendar, labelKey: 'navigation.events', module: 'events' },
  { href: 'jobs', icon: Briefcase, labelKey: 'navigation.jobs', module: 'job_vacancies' },
  { href: 'marketplace', icon: ShoppingBag, labelKey: 'navigation.marketplace', module: 'marketplace' },
  { href: 'organisations', icon: Users, labelKey: 'navigation.organisations', module: 'organisations' },
  { href: 'resources', icon: BookOpen, labelKey: 'navigation.resources', module: 'resources' },
  { href: 'blog', icon: BookOpen, labelKey: 'navigation.blog', module: 'blog' },
];

const footerSections = [
  {
    labelKey: 'footer.platform',
    links: [
      { href: 'listings', labelKey: 'navigation.listings' },
      { href: 'events', labelKey: 'navigation.events' },
      { href: 'jobs', labelKey: 'navigation.jobs' },
      { href: 'marketplace', labelKey: 'navigation.marketplace' },
      { href: 'organisations', labelKey: 'navigation.organisations' },
    ],
  },
  {
    labelKey: 'footer.support',
    links: [
      { href: 'help', labelKey: 'navigation.help' },
      { href: 'contact', labelKey: 'navigation.contact' },
      { href: 'faq', labelKey: 'navigation.faq' },
      { href: 'about', labelKey: 'navigation.about' },
    ],
  },
  {
    labelKey: 'footer.legal',
    links: [
      { href: 'legal', labelKey: 'pages.legal.title' },
      { href: 'terms', labelKey: 'pages.terms.title' },
      { href: 'privacy', labelKey: 'pages.privacy.title' },
      { href: 'accessibility', labelKey: 'pages.accessibility.title' },
    ],
  },
];

export function PublicChrome({ canonicalUrl, children, tenant, tenantBasePath, t }: PublicChromeProps): ReactNode {
  const context = buildPublicChromeContext({ tenant, tenantBasePath, t });

  return (
    <div
      className="surface surface--default min-h-screen bg-background text-foreground"
      data-nexus-ui="heroui-public"
      data-slot="surface"
      style={context.style}
    >
      <NavbarHost
        tenant={tenant}
        tenantBasePath={tenantBasePath}
        accessibleFrontendUrl={context.accessibleFrontendUrl}
      />
      <PublicMobileDrawer {...context} />
      <main>{children}</main>
      <PublicFooter {...context} canonicalUrl={canonicalUrl} />
    </div>
  );
}

function PublicNavbar({
  accessibleFrontendUrl,
  logoUrl,
  navItems,
  tagline,
  tenantBasePath,
  tenantName,
  t,
}: PublicChromeContext): ReactNode {
  return (
    <header
      className="sticky top-0 z-[var(--z-sticky)] w-full border-b border-theme-default bg-[var(--surface-base)]/90 backdrop-blur-xl"
      data-nexus-ui="react-public-navbar"
    >
      <div className="hidden w-full border-b border-theme-default/70 sm:block">
        <div className="mx-auto flex h-9 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
          <div className="flex min-w-0 items-center gap-2 text-xs text-theme-muted">
            <span className="truncate">{tagline}</span>
          </div>
          <div className="flex min-w-0 items-center gap-1 overflow-x-auto">
            {accessibleFrontendUrl ? (
              <>
                <a
                  aria-label={t('navigation.accessibleFrontend')}
                  className={utilityBarActionClass}
                  href={accessibleFrontendUrl}
                  rel="noopener noreferrer"
                  target="_blank"
                >
                  <BadgeCheck className="size-4 shrink-0" aria-hidden="true" />
                  <span className="hidden md:inline">{t('navigation.accessibleFrontend')}</span>
                </a>
                <span className={utilityBarDividerClass} aria-hidden="true">
                  |
                </span>
              </>
            ) : null}
            <Link className={utilityBarActionClass} href={withTenantBase(tenantBasePath, 'help')}>
              <CircleHelp className="size-4 shrink-0" aria-hidden="true" />
              <span className="hidden md:inline">{t('navigation.help')}</span>
            </Link>
            <Link className={utilityBarActionClass} href={withTenantBase(tenantBasePath, 'contact')}>
              <Info className="size-4 shrink-0" aria-hidden="true" />
              <span className="hidden md:inline">{t('navigation.contact')}</span>
            </Link>
            <span className={utilityBarDividerClass} aria-hidden="true">
              |
            </span>
            <Button aria-label={t('navigation.search')} className={utilityBarActionClass} size="sm" variant="ghost">
              <Search className="size-4 shrink-0" aria-hidden="true" />
              <span className="hidden md:inline">{t('navigation.search')}</span>
            </Button>
          </div>
        </div>
      </div>

      <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="flex min-h-14 items-center justify-between gap-2 py-1.5 sm:min-h-16">
          <div className="flex min-w-0 flex-1 items-center gap-2 sm:gap-3 lg:flex-none">
            <Button
              aria-controls="mobile-drawer"
              aria-expanded="false"
              aria-label={t('navigation.openMenu')}
              className="min-h-[44px] min-w-[44px] text-theme-muted hover:text-theme-primary lg:hidden"
              isIconOnly
              size="sm"
              variant="ghost"
            >
              <Menu className="size-5" aria-hidden="true" />
            </Button>
            <PublicTenantLogo
              href={withTenantBase(tenantBasePath, '')}
              logoUrl={logoUrl}
              showName
              size="md"
              tagline={tagline}
              tenantName={tenantName}
            />
          </div>

          <nav aria-label={t('navigation.aria')} className="hidden min-w-0 flex-1 items-center justify-center gap-1 lg:flex">
            {navItems.map((item) => {
              const Icon = item.icon;

              return (
                <Link
                  className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-theme-muted transition-all hover:bg-theme-hover hover:text-theme-primary"
                  href={withTenantBase(tenantBasePath, item.href)}
                  key={item.labelKey}
                >
                  <Icon className="size-4" aria-hidden="true" />
                  <span>{t(item.labelKey)}</span>
                </Link>
              );
            })}
          </nav>

          <div className="flex shrink-0 items-center gap-1 sm:gap-2">
            <Button
              aria-label={t('navigation.search')}
              className="min-h-[44px] min-w-[44px] text-theme-muted hover:text-theme-primary sm:hidden"
              isIconOnly
              size="sm"
              variant="ghost"
            >
              <Search className="size-5" aria-hidden="true" />
            </Button>
            <Link
              data-slot="button"
              href={withTenantBase(tenantBasePath, 'login')}
              className="button button--sm button--ghost hidden min-w-0 px-2 text-theme-secondary hover:text-theme-primary min-[360px]:inline-flex sm:px-3"
            >
              {t('navigation.login')}
            </Link>
            <Link
              data-slot="button"
              href={withTenantBase(tenantBasePath, 'register')}
              className="button button--sm button--primary min-w-0 px-2 font-medium sm:px-3"
            >
              {t('navigation.signup')}
            </Link>
          </div>
        </div>
      </div>
    </header>
  );
}

function PublicMobileDrawer({ navItems, tenantBasePath, t }: PublicChromeContext): ReactNode {
  return (
    <aside
      aria-label={t('navigation.mobileNavigation')}
      className="hidden"
      data-nexus-ui="react-public-mobile-drawer"
      id="mobile-drawer"
    >
      <div className="border-b border-theme-default p-4">
        <div className="flex items-center justify-between">
          <span className="text-sm font-semibold uppercase text-theme-muted">{t('navigation.mobileNavigation')}</span>
          <Button aria-label={t('navigation.closeMenu')} isIconOnly size="sm" variant="ghost">
            <X className="size-5" aria-hidden="true" />
          </Button>
        </div>
      </div>
      <nav className="grid gap-1 p-4" aria-label={t('navigation.mobileNavigation')}>
        {navItems.map((item) => {
          const Icon = item.icon;

          return (
            <Link
              className="flex min-h-[48px] items-center gap-3 rounded-xl px-4 py-3.5 text-base font-medium text-theme-muted transition-all hover:bg-theme-hover hover:text-theme-primary"
              href={withTenantBase(tenantBasePath, item.href)}
              key={item.labelKey}
            >
              <Icon className="size-5 shrink-0" aria-hidden="true" />
              <span>{t(item.labelKey)}</span>
            </Link>
          );
        })}
      </nav>
      <div className="border-t border-theme-default p-4">
        <div className="grid gap-2">
          <Link
            className="button button--full-width button--md button--secondary"
            data-slot="button"
            href={withTenantBase(tenantBasePath, 'login')}
          >
            {t('navigation.login')}
          </Link>
          <Link
            className="button button--full-width button--md button--primary"
            data-slot="button"
            href={withTenantBase(tenantBasePath, 'register')}
          >
            {t('navigation.signup')}
          </Link>
        </div>
      </div>
    </aside>
  );
}

function PublicFooter({
  canonicalUrl,
  logoUrl,
  tagline,
  tenantBasePath,
  tenantName,
  t,
}: PublicChromeContext & { canonicalUrl: string }): ReactNode {
  return (
    <footer
      className="relative z-10 mt-auto border-t border-theme-default glass-surface backdrop-blur-sm"
      data-nexus-ui="react-public-footer"
      data-nosnippet
    >
      <div className="mx-auto hidden max-w-7xl px-4 py-8 sm:px-6 md:block lg:px-8">
        <div className="space-y-8">
          <div className="grid grid-cols-2 gap-8 sm:grid-cols-4">
            <div className="col-span-2 space-y-3 sm:col-span-1">
              <PublicTenantLogo
                href={withTenantBase(tenantBasePath, '')}
                logoUrl={logoUrl}
                showName
                size="sm"
                tagline={tagline}
                tenantName={tenantName}
              />
              <p className="text-sm text-theme-subtle">{tagline}</p>
            </div>

            {footerSections.map((section) => (
              <nav aria-label={t(section.labelKey)} key={section.labelKey}>
                <h2 className="mb-3 text-sm font-semibold text-theme-primary">{t(section.labelKey)}</h2>
                <ul className="space-y-2">
                  {section.links.map((link) => (
                    <li key={`${section.labelKey}-${link.href}`}>
                      <Link
                        className="text-sm text-theme-muted transition-colors hover:text-theme-primary"
                        href={withTenantBase(tenantBasePath, link.href)}
                      >
                        {t(link.labelKey)}
                      </Link>
                    </li>
                  ))}
                </ul>
              </nav>
            ))}
          </div>

          <div className="flex flex-col gap-6 border-t border-theme-default pt-6">
            <div className="grid grid-cols-1 items-start gap-6 sm:grid-cols-3 sm:gap-8">
              <div className="flex min-w-0 flex-col items-start gap-2">
                <span className="text-[10px] font-semibold uppercase tracking-widest text-theme-subtle/50">
                  {t('footer.platform')}
                </span>
                <p className="text-sm text-theme-muted">{t('footer.attribution')}</p>
              </div>
              <div className="flex flex-col items-start gap-3 sm:items-center">
                <span className="text-[10px] font-semibold uppercase tracking-widest text-theme-subtle/50">
                  {t('navigation.sourceCode')}
                </span>
                <Link
                  className="button button--md button--outline"
                  data-slot="button"
                  href="https://github.com/jasperfordesq-ai/nexus-v1"
                  rel="noopener noreferrer"
                  target="_blank"
                >
                  <FileText className="size-4" aria-hidden="true" />
                  {t('navigation.sourceCode')}
                </Link>
              </div>
              <div className="flex min-w-0 flex-col items-start gap-2 sm:items-end">
                <span className="text-[10px] font-semibold uppercase tracking-widest text-theme-subtle/50">
                  {t('brand.platformName')}
                </span>
                <span className="text-sm font-semibold text-theme-primary">{t('brand.platformName')}</span>
              </div>
            </div>

            <div className="flex flex-wrap items-center justify-center gap-x-2 gap-y-1 border-t border-theme-default pt-4 text-[11px] text-theme-subtle/70">
              <span>{t('footer.copyright')}</span>
              <span aria-hidden="true">&middot;</span>
              <Link href={withTenantBase(tenantBasePath, 'platform/terms')} className="transition-colors hover:text-theme-primary">
                {t('pages.platformTerms.title')}
              </Link>
              <span aria-hidden="true">&middot;</span>
              <Link href={withTenantBase(tenantBasePath, 'platform/privacy')} className="transition-colors hover:text-theme-primary">
                {t('pages.platformPrivacy.title')}
              </Link>
              <span aria-hidden="true">&middot;</span>
              <Link href={canonicalUrl} className="transition-colors hover:text-theme-primary">
                {t('metadata.canonicalLabel')}
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div className="px-4 py-6 md:hidden">
        <div className="flex flex-col items-center gap-4">
          <Link
            className="button button--md button--outline"
            data-slot="button"
            href="https://github.com/jasperfordesq-ai/nexus-v1"
            rel="noopener noreferrer"
            target="_blank"
          >
            <FileText className="size-4" aria-hidden="true" />
            {t('navigation.sourceCode')}
          </Link>
          <p className="text-center text-sm text-theme-muted">
            <span className="font-medium text-theme-secondary">{t('brand.platformName')}</span>
            <span aria-hidden="true"> &middot; </span>
            <span>{t('footer.attribution')}</span>
          </p>
          <div className="flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-[11px] text-theme-subtle/70">
            <span>{t('footer.copyright')}</span>
            <span aria-hidden="true">&middot;</span>
            <Link href={withTenantBase(tenantBasePath, 'platform/terms')} className="transition-colors hover:text-theme-primary">
              {t('pages.platformTerms.title')}
            </Link>
            <span aria-hidden="true">&middot;</span>
            <Link href={withTenantBase(tenantBasePath, 'platform/privacy')} className="transition-colors hover:text-theme-primary">
              {t('pages.platformPrivacy.title')}
            </Link>
          </div>
        </div>
      </div>
    </footer>
  );
}

function PublicTenantLogo({
  href,
  logoUrl,
  showName,
  size,
  tagline,
  tenantName,
}: {
  href: string;
  logoUrl: string | undefined;
  showName: boolean;
  size: 'md' | 'sm';
  tagline: string;
  tenantName: string;
}): ReactNode {
  const imageClass =
    size === 'sm'
      ? 'h-7 max-w-[110px] object-contain sm:max-w-[150px]'
      : 'h-9 max-w-[200px] object-contain sm:h-11 sm:max-w-[260px]';
  const imageDimensions = size === 'sm' ? { height: 32, width: 150 } : { height: 48, width: 240 };

  return (
    <Link className="flex min-w-0 items-center gap-2 no-underline" href={href}>
      {logoUrl ? (
        <span className="inline-flex shrink-0 items-center">
          <Image
            alt={tenantName}
            className={`${imageClass} w-auto transition-all duration-200`}
            height={imageDimensions.height}
            src={logoUrl}
            unoptimized
            width={imageDimensions.width}
          />
        </span>
      ) : (
        <span
          aria-hidden="true"
          className="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-bold text-white ring-2 ring-border ring-offset-1 ring-offset-transparent"
        >
          {getInitials(tenantName)}
        </span>
      )}
      {showName && !logoUrl ? (
        <span className="hidden min-w-0 flex-col min-[480px]:flex">
          <span className="max-w-[120px] truncate text-lg font-bold text-gradient sm:max-w-[160px] md:max-w-[200px] lg:max-w-[240px]">
            {tenantName}
          </span>
          <span className="hidden max-w-[200px] truncate text-xs text-theme-subtle lg:inline">{tagline}</span>
        </span>
      ) : null}
    </Link>
  );
}

function buildPublicChromeContext({
  tenant,
  tenantBasePath,
  t,
}: {
  tenant: TenantBootstrap | null;
  tenantBasePath: string;
  t: Translator;
}): PublicChromeContext {
  const tenantName = tenant?.name || t('brand.platformName');
  const tagline = tenant?.tagline || t('pages.home.fallbackTagline');
  const logoUrl = resolveAssetUrl(tenant?.branding?.logo_url, getApiBase());
  const style = buildThemeStyle(
    safeCssColor(tenant?.branding?.primary_color),
    safeCssColor(tenant?.branding?.secondary_color),
  );
  const accessibleFrontendUrl = tenant?.accessible_domain
    ? `https://${tenant.accessible_domain.replace(/^https?:\/\//, '').replace(/\/+$/, '')}`
    : null;

  return {
    accessibleFrontendUrl,
    logoUrl,
    navItems: publicNavItems.filter((item) => !item.module || tenantAllowsPublicModule(tenant, item.module)),
    style,
    tagline,
    tenant,
    tenantBasePath,
    tenantName,
    t,
  };
}

function buildThemeStyle(
  accentColor: string | undefined,
  secondaryColor: string | undefined,
): CSSProperties | undefined {
  const style: CSSProperties & Record<string, string> = {};

  if (accentColor) {
    style['--accent-color'] = accentColor;
    style['--nexus-accent'] = accentColor;
  }

  if (secondaryColor) {
    style['--nexus-accent-secondary'] = secondaryColor;
  }

  return Object.keys(style).length > 0 ? style : undefined;
}

function tenantAllowsPublicModule(tenant: TenantBootstrap | null, key: string): boolean {
  const modules = tenant?.modules;
  const features = tenant?.features;

  if (!modules && !features) {
    return true;
  }

  return normalizeBoolean(modules?.[key] ?? features?.[key]);
}

function normalizeBoolean(value: unknown): boolean {
  if (typeof value === 'boolean') {
    return value;
  }

  if (typeof value === 'number') {
    return value > 0;
  }

  if (typeof value === 'string') {
    return ['1', 'true', 'yes', 'enabled', 'on'].includes(value.toLowerCase());
  }

  return false;
}

function getInitials(name: string): string {
  const words = name.trim().split(/\s+/);
  const firstWord = words[0] ?? '';
  const lastWord = words[words.length - 1] ?? '';

  if (words.length === 1) {
    return firstWord.substring(0, 2).toUpperCase();
  }

  return ((firstWord[0] ?? '') + (lastWord[0] ?? '')).toUpperCase();
}

function withTenantBase(tenantBasePath: string, path: string): string {
  const normalizedBase = tenantBasePath.replace(/\/+$/, '');
  const normalizedPath = path.replace(/^\/+/, '');

  if (!normalizedBase && !normalizedPath) {
    return '/';
  }

  if (!normalizedPath) {
    return normalizedBase || '/';
  }

  return `${normalizedBase}/${normalizedPath}` || '/';
}
