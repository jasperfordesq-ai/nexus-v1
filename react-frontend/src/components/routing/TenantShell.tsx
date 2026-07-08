// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * TenantShell
 *
 * Wraps tenant-scoped content. Detects tenant slug from the first URL path
 * segment (if it's not a reserved path like "admin", "login", etc.),
 * passes it to TenantProvider, and handles unknown-slug soft 404.
 *
 * Used by App.tsx inside a single `/*` catch-all route to support both:
 *   /dashboard              (no slug prefix — tenant from domain or chooser)
 *   /hour-timebank/dashboard (slug prefix — Phase 0-1 path-based resolution)
 *
 * When a tenant slug IS detected in the URL, TenantShell strips the slug prefix
 * and re-renders child routes via a nested <Routes> so that `/hour-timebank/dashboard`
 * matches the `dashboard` child route correctly.
 *
 * The single catch-all approach avoids React Router v6's route ranking issue
 * where `/:tenantSlug/listings` (dynamic+static) outranks `/admin/*` (static+splat)
 * because splat routes have the lowest priority in RRv6.
 *
 * Implements TRS-001 R3 (path-based resolution) at the React routing level.
 * @see docs/TRS-001-TENANT-RESOLUTION-SPEC.md
 */

import { useTranslation } from 'react-i18next';
import { Routes, useLocation } from 'react-router-dom';
import { TenantProvider, useTenant } from '@/contexts/TenantContext';
import { useAuth, AuthProvider } from '@/contexts/AuthContext';
import { useCookieConsent } from '@/contexts/CookieConsentContext';
import { detectTenantFromUrl } from '@/lib/tenant-routing';
import { LoadingScreen } from '@/components/feedback/LoadingScreen';
import { lazy, Suspense, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { listenForImpersonationToken } from '@/lib/impersonate';

const MaintenancePage = lazy(() => import('@/pages/public/MaintenancePage'));
const TenantPublicProviders = lazy(() => import('./TenantPublicProviders'));
const TenantAppProviders = lazy(() => import('./TenantAppProviders'));
const CookieConsentBanner = lazy(() =>
  import('@/components/feedback/CookieConsentBanner').then((module) => ({
    default: module.CookieConsentBanner,
  })),
);

type AppRoutesFactory = () => React.ReactNode;

/**
 * Props for TenantShell — receives appRoutes from App.tsx so it can
 * re-render them inside a slug-stripped nested Routes when needed.
 */
interface TenantShellProps {
  appRoutes?: () => React.ReactNode;
}

export function TenantShell({ appRoutes }: TenantShellProps) {
  const shellLocation = useLocation();
  const [loadedAppRoutes, setLoadedAppRoutes] = useState<AppRoutesFactory | null>(appRoutes ?? null);

  // Listen for impersonation token handoff from admin tab via BroadcastChannel.
  // Token is set in tokenManager (memory → localStorage, same as normal login)
  // and page reloads to pick up the new auth state.
  useEffect(() => {
    return listenForImpersonationToken(() => {
      window.location.reload();
    });
  }, []);

  // Use detectTenantFromUrl() which correctly implements TRS-001 R1–R4:
  // - R1: Custom domain (e.g. hour-timebank.ie) → slug = null (backend resolves from Host)
  // - R2: Subdomain of project-nexus.ie → slug from subdomain
  // - R3: app.project-nexus.ie or localhost → slug from first path segment if not reserved
  // - R4: No tenant → null
  const { slug: detectedSlug } = detectTenantFromUrl();

  // Sticky within this document: pages inside the slug-stripped nested Routes
  // resolve relative URL rewrites (setSearchParams, navigate('?...')) against
  // the stripped pathname, so the browser URL briefly reads /listings instead
  // of /hour-timebank/listings. TenantShell re-renders on every navigation;
  // re-reading the slug from that momentarily slug-less URL unmounted
  // SlugUrlGuard (before it could restore the URL) and flipped the whole app
  // to the master tenant. Once a slug is detected for this document, keep it —
  // SlugUrlGuard puts it back in the URL. A real tenant/master switch always
  // arrives as a fresh document load, which starts with an empty ref.
  // (Unlike the localStorage slug-recovery removed 2026-05-08, this never
  // crosses page loads, so app.project-nexus.ie/ still renders the master
  // landing page.)
  const stickySlugRef = useRef<string | null>(null);
  if (detectedSlug) {
    stickySlugRef.current = detectedSlug;
  }
  const effectiveSlug = detectedSlug ?? stickySlugRef.current ?? undefined;
  const isAuthRoute = isAuthEntryPath(shellLocation.pathname, effectiveSlug);

  useEffect(() => {
    if (appRoutes) {
      setLoadedAppRoutes(() => appRoutes);
      return;
    }

    let mounted = true;
    setLoadedAppRoutes(null);

    if (isAuthRoute) {
      import('@/routes/AuthRoutes').then(({ AuthRoutes }) => {
        if (mounted) {
          setLoadedAppRoutes(() => AuthRoutes);
        }
      });
    } else {
      import('@/routes/AppRoutes').then(({ AppRoutes }) => {
        if (mounted) {
          setLoadedAppRoutes(() => AppRoutes);
        }
      });
    }

    return () => {
      mounted = false;
    };
  }, [appRoutes, isAuthRoute]);

  // Cross-tenant slug-recovery redirect REMOVED (2026-05-08). The previous
  // logic re-prepended a stored slug from localStorage when a user hit a
  // slug-less URL on app.project-nexus.ie. That mechanism caused a persistent
  // bug: visiting app.project-nexus.ie/ (the master/platform landing page)
  // bounced authenticated users straight into whichever tenant they had last
  // visited (e.g. /agoris). The URL is now respected as-typed: the master
  // tenant landing renders at /, and tenant-scoped pages require the slug
  // to be present in the URL.

  return (
    <TenantProvider tenantSlug={effectiveSlug}>
      <AuthProvider>
        <TenantShellRuntime
          appRoutes={loadedAppRoutes}
          isAuthRoute={isAuthRoute}
          slugPrefix={effectiveSlug}
        />
        <DeferredCookieConsentBanner />
      </AuthProvider>
    </TenantProvider>
  );
}

function TenantShellRuntime({
  appRoutes,
  isAuthRoute,
  slugPrefix,
}: {
  appRoutes: AppRoutesFactory | null;
  isAuthRoute: boolean;
  slugPrefix?: string;
}) {
  const { isAuthenticated } = useAuth();
  const location = useLocation();

  if (isAuthRoute) {
    return <TenantRouteSurface slugPrefix={slugPrefix} appRoutes={appRoutes} />;
  }

  const needsFullRuntime = isAuthenticated && routeNeedsTenantAppRuntime(location.pathname, slugPrefix);
  const Providers = needsFullRuntime ? TenantAppProviders : TenantPublicProviders;

  return (
    <Suspense fallback={<LoadingScreen />}>
      <Providers>
        <TenantRouteSurface slugPrefix={slugPrefix} appRoutes={appRoutes} />
      </Providers>
    </Suspense>
  );
}

function TenantRouteSurface({
  slugPrefix,
  appRoutes,
}: {
  slugPrefix?: string;
  appRoutes: AppRoutesFactory | null;
}) {
  return <TenantGuard slugPrefix={slugPrefix} appRoutes={appRoutes} />;
}

function DeferredCookieConsentBanner() {
  const { showBanner } = useCookieConsent();
  const [canRenderBanner, setCanRenderBanner] = useState(false);

  useEffect(() => {
    if (!showBanner) {
      setCanRenderBanner(false);
      return;
    }

    const timer = window.setTimeout(() => {
      if (typeof window.requestIdleCallback === 'function') {
        window.requestIdleCallback(() => setCanRenderBanner(true), { timeout: 1500 });
      } else {
        setCanRenderBanner(true);
      }
    }, 1200);

    return () => window.clearTimeout(timer);
  }, [showBanner]);

  if (!showBanner || !canRenderBanner) return null;

  return (
    <Suspense fallback={null}>
      <CookieConsentBanner />
    </Suspense>
  );
}

const authPaths = [
  'login',
  'register',
  'password/forgot',
  'password/reset',
  'verify-email',
  'verify-identity',
  'auth/oauth/callback',
];

function isAuthEntryPath(pathname: string, slugPrefix?: string): boolean {
  const lowerPath = pathname.toLowerCase().replace(/\/+$/, '') || '/';
  const lowerPrefix = slugPrefix?.toLowerCase();
  return authPaths.some((path) => {
    const authPath = `/${path}`;
    return lowerPath === authPath || (lowerPrefix ? lowerPath === `/${lowerPrefix}${authPath}` : false);
  });
}

const protectedRuntimePrefixes = [
  'admin',
  'super-admin',
  'broker',
  'dashboard',
  'feed',
  'profile',
  'messages',
  'wallet',
  'settings',
  'search',
  'notifications',
  'onboarding',
  'activity',
  'saved',
  'connections',
  'members',
  'matches',
  'reviews',
  'goals',
  'achievements',
  'leaderboard',
  'nexus-score',
  'skills',
  'chat',
  'exchanges',
  'group-exchanges',
  'federation',
  'clubs',
  'vereine',
  'advertise',
  'partners',
  'caring',
  'listings/create',
  'listings/edit',
  'events/create',
  'events/edit',
  'groups/create',
  'groups/edit',
  'marketplace/create',
  'marketplace/edit',
  'marketplace/my',
  'marketplace/buyer',
  'marketplace/seller',
  'courses/create',
  'courses/instructor',
  'courses/my-learning',
  'volunteering/create',
  'organisations/register',
  'ideation/create',
];

const publicRuntimePrefixes = [
  '',
  'features',
  'changelog',
  'development-status',
  'about',
  'faq',
  'contact',
  'pilot-inquiry',
  'pilot-apply',
  'help',
  'terms',
  'privacy',
  'accessibility',
  'cookies',
  'community-guidelines',
  'trust-and-safety',
  'acceptable-use',
  'legal',
  'platform',
  'timebanking-guide',
  'developers',
  'regional-analytics',
  'partner-analytics',
  'newsletter',
  'volunteering/guardian-consent',
  'explore',
  'partner',
  'social-prescribing',
  'impact-summary',
  'impact-report',
  'strategic-plan',
  'page',
  'blog',
  'listings',
  'events',
  'groups',
  'jobs',
  'courses',
  'marketplace',
  'sellers',
  'coupons',
  'pricing',
  'volunteering',
  'resources',
  'kb',
  'organisations',
  'ideation',
  'join',
  'podcasts',
  'public',
];

function routeNeedsTenantAppRuntime(pathname: string, slugPrefix?: string): boolean {
  let routePath = pathname.toLowerCase().replace(/\/+$/, '');
  const lowerPrefix = slugPrefix?.toLowerCase();
  if (lowerPrefix && (routePath === `/${lowerPrefix}` || routePath.startsWith(`/${lowerPrefix}/`))) {
    routePath = routePath.slice(lowerPrefix.length + 1) || '/';
  }
  const normalized = routePath.replace(/^\/+/, '');

  if (protectedRuntimePrefixes.some((prefix) => normalized === prefix || normalized.startsWith(`${prefix}/`))) {
    return true;
  }

  if (publicRuntimePrefixes.some((prefix) => normalized === prefix || normalized.startsWith(`${prefix}/`))) {
    return false;
  }

  return true;
}

/**
 * Inner guard that checks if tenant resolution failed with a notFoundSlug.
 * Must be inside TenantProvider to access useTenant().
 *
 * When a slug prefix is detected, this component renders a nested <Routes>
 * with the slug stripped from the location, so child routes match correctly.
 * e.g. "/hour-timebank/dashboard" → nested Routes sees "/dashboard"
 */
function TenantGuard({
  slugPrefix,
  appRoutes,
}: {
  slugPrefix?: string;
  appRoutes: AppRoutesFactory | null;
}) {
  const { t } = useTranslation('common');
  const { isLoading, notFoundSlug, tenant, error } = useTenant();
  const { user } = useAuth();
  const location = useLocation();

  // While tenant is loading, block page rendering to prevent API calls before
  // X-Tenant-ID is set in localStorage. Bootstrap is fast (50-200ms, Redis-cached).
  // Without this, pages on custom domains (hour-timebank.ie) fire API calls before
  // the tenant ID is known, causing the API to return master tenant (ID 1) results.
  if (isLoading) {
    return <LoadingScreen message={t('aria.loading_community')} />;
  }

  // If the slug was not found, show "Community Not Found" page
  if (notFoundSlug) {
    return <CommunityNotFound slug={notFoundSlug} />;
  }

  // Bootstrap failed (network error, server error, or offline first launch).
  // Show a retry screen instead of a blank page or partial render.
  if (!tenant && error) {
    return <BootstrapError onRetry={() => window.location.reload()} />;
  }

  // Check for maintenance mode (after tenant is loaded)
  const maintenanceMode = tenant?.settings?.maintenance_mode === true;
  const isAdmin = user?.role && ['admin', 'tenant_admin', 'super_admin'].includes(user.role);

  // Show maintenance page to non-admin users when maintenance mode is enabled
  // Allow access to admin routes and auth pages (login, register) even in maintenance mode
  const lowerPath = location.pathname.toLowerCase();
  const lowerPrefix = slugPrefix?.toLowerCase();

  const isAdminRoute = lowerPath === '/admin' ||
                       lowerPath.startsWith('/admin/') ||
                       (lowerPrefix ? (lowerPath === `/${lowerPrefix}/admin` ||
                                       lowerPath.startsWith(`/${lowerPrefix}/admin/`)) : false);

  const isAuthRoute = authPaths.some((p) =>
    lowerPath === `/${p}` ||
    (lowerPrefix && lowerPath === `/${lowerPrefix}/${p}`)
  );

  if (maintenanceMode && !isAdmin && !isAdminRoute && !isAuthRoute) {
    return (
      <Suspense fallback={<div>{t('loading')}</div>}>
        <MaintenancePage />
      </Suspense>
    );
  }

  if (!appRoutes) {
    return <LoadingScreen />;
  }

  return <TenantRoutes slugPrefix={slugPrefix} appRoutes={appRoutes} />;
}

function TenantRoutes({
  slugPrefix,
  appRoutes,
}: {
  slugPrefix?: string;
  appRoutes: AppRoutesFactory;
}) {
  const location = useLocation();
  const nestedRouteContent = appRoutes();

  // If there's a slug prefix, render a nested Routes with the slug stripped
  // so child routes like "dashboard" match "/hour-timebank/dashboard" correctly
  if (slugPrefix) {
    const strippedPath = location.pathname.replace(new RegExp(`^/${slugPrefix}`, 'i'), '') || '/';
    return (
      <>
        <SlugUrlGuard slug={slugPrefix} />
        <Routes location={{ ...location, pathname: strippedPath }}>
          {nestedRouteContent}
        </Routes>
      </>
    );
  }

  return (
    <Routes>
      {nestedRouteContent}
    </Routes>
  );
}

/**
 * Slug URL Guard — restores the tenant slug in the browser URL.
 *
 * React Router v6's <Routes location={customLocation}> with a stripped pathname
 * can cause the browser URL to lose the slug prefix (the nested Routes syncs
 * the browser URL to the custom location's pathname on certain renders).
 *
 * This component fires a synchronous useLayoutEffect (before the browser paints)
 * that checks if the slug is missing from the browser URL and restores it via
 * history.replaceState. The user never sees the wrong URL.
 *
 * It re-runs on EVERY router location change, not just on mount: pages that
 * call setSearchParams/navigate from inside the slug-stripped nested <Routes>
 * (e.g. EventsPage syncing filters to the URL) rewrite the browser URL from
 * the router's stripped pathname, dropping the slug AFTER the mount-time
 * check ran. A slug-less URL is not just cosmetic — detectTenantFromUrl()
 * re-reads it on the next TenantShell render and silently flips the app to
 * the master tenant.
 *
 * SAFE for custom domains: this component is only rendered when slugPrefix is
 * defined, which only happens on shared hosts (localhost, app.project-nexus.ie).
 */
export function SlugUrlGuard({ slug }: { slug: string }) {
  const routerLocation = useLocation();
  useLayoutEffect(() => {
    // ONLY restore slug on shared hosts where the path prefix IS the tenant identifier.
    // On subdomains (hour-timebank.project-nexus.ie) or custom domains (hour-timebank.ie),
    // the slug belongs in the domain, NOT the path — never touch the URL there.
    const hostname = window.location.hostname;
    const isSharedHost = hostname === 'localhost' || hostname === '127.0.0.1' || hostname === 'app.project-nexus.ie';
    if (!isSharedHost) return;

    const currentPath = window.location.pathname;
    const prefix = '/' + slug;
    // If the browser URL doesn't start with the slug prefix, restore it
    if (!currentPath.toLowerCase().startsWith(prefix.toLowerCase() + '/') &&
        currentPath.toLowerCase() !== prefix.toLowerCase()) {
      const correctedUrl = prefix + currentPath + window.location.search + window.location.hash;
      window.history.replaceState(window.history.state, '', correctedUrl);
    }
  }, [slug, routerLocation]);
  return null;
}

/**
 * Bootstrap error screen — shown when tenant config fails to load (offline /
 * network error / server error). Gives the user a clear message and retry button
 * instead of a blank screen or a confusing partial render.
 */
function BootstrapError({ onRetry }: { onRetry: () => void }) {
  const { t } = useTranslation('common');
  return (
    <div className="min-h-screen flex items-center justify-center px-4 bg-[var(--surface-base)]">
      <div className="text-center max-w-sm">
        <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-amber-500/20 mb-6">
          <svg className="w-8 h-8 text-[var(--color-warning)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
              d="M12 9v2m0 4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z" />
          </svg>
        </div>
        <h1 className="text-xl font-bold text-theme-primary mb-2">
          {t('errors.connection_failed')}
        </h1>
        <p className="text-theme-muted mb-6 text-sm">
          {t('errors.connection_failed_detail')}
        </p>
        <button
          type="button"
          onClick={onRetry}
          className="px-6 py-2 rounded-lg bg-accent text-white font-medium text-sm hover:opacity-90 transition-opacity"
        >
          {t('actions.retry')}
        </button>
      </div>
    </div>
  );
}

/**
 * Community Not Found page — shown when a URL slug doesn't match any tenant.
 * Inline component to avoid circular dependency with lazy-loaded NotFoundPage.
 */
import { Link } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import Home from 'lucide-react/icons/house';
import Search from 'lucide-react/icons/search';
import Globe from 'lucide-react/icons/globe';
import { PageMeta } from '@/components/seo/PageMeta';
import { usePageTitle } from '@/hooks/usePageTitle';

function CommunityNotFound({ slug }: { slug: string }) {
  const { t } = useTranslation('errors');
  usePageTitle(t('community_not_found'));
  return (
    <div className="min-h-[80vh] flex items-center justify-center px-4">
      <PageMeta title={t('community_not_found')} noIndex />
      <Helmet>
        <meta name="prerender-status-code" content="404" />
      </Helmet>
      <div className="w-full max-w-md">
        <div className="rounded-lg border border-theme-default bg-theme-surface/90 p-8 text-center shadow-xl">
          <div className="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-amber-500/20 to-orange-500/20 mb-6">
            <Globe className="w-10 h-10 text-[var(--color-warning)]" aria-hidden="true" />
          </div>

          <h1 className="text-2xl font-bold text-theme-primary mb-2">{t('community_not_found')}</h1>
          <p className="text-theme-muted mb-2">
            {t('community_not_found_message', { slug })}
          </p>
          <p className="text-sm text-theme-subtle mb-8">
            {t('community_not_found_detail')}
          </p>

          <div className="flex flex-col sm:flex-row gap-3">
            <Link
              to="/"
              className="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-theme-accent px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-theme-focus"
            >
              <Home className="w-4 h-4" aria-hidden="true" />
                {t('go_home')}
            </Link>
            <Link
              to="/login"
              className="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-theme-elevated px-4 py-2.5 text-sm font-semibold text-theme-muted transition hover:bg-theme-muted/10 focus:outline-none focus:ring-2 focus:ring-theme-focus"
            >
              <Search className="w-4 h-4" aria-hidden="true" />
                {t('find_community')}
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}

export default TenantShell;
