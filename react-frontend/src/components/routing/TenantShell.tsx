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
import { Outlet, Routes, useLocation } from 'react-router-dom';
import { TenantProvider, useTenant, useAuth } from '@/contexts';
import { AuthProvider, NotificationsProvider, PusherProvider, MenuProvider } from '@/contexts';
import { PresenceProvider } from '@/contexts/PresenceContext';
import { detectTenantFromUrl, RESERVED_PATHS } from '@/lib/tenant-routing';
import { tokenManager } from '@/lib/api';
import { CookieConsentBanner } from '@/components/feedback';
import { lazy, Suspense, useEffect, useLayoutEffect } from 'react';
import { Spinner } from '@heroui/react';
import { listenForImpersonationToken } from '@/lib/impersonate';

const MaintenancePage = lazy(() => import('@/pages/public/MaintenancePage'));

/**
 * Props for TenantShell — receives appRoutes from App.tsx so it can
 * re-render them inside a slug-stripped nested Routes when needed.
 */
interface TenantShellProps {
  appRoutes?: () => React.ReactNode;
}

export function TenantShell({ appRoutes }: TenantShellProps) {
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
  const effectiveSlug = detectedSlug ?? undefined;

  // ── SLUG RECOVERY REDIRECT — SYNCHRONOUS, BEFORE ANY CHILDREN MOUNT ────
  // On shared hosts (localhost, app.project-nexus.ie) the URL path MUST include
  // the tenant slug prefix. If the slug is missing but we know which tenant the
  // user is on (from localStorage), redirect IMMEDIATELY via window.location.
  //
  // GUARD: Only recover the slug when the user has active auth tokens. Without
  // this guard, a stale slug in localStorage (from visiting another tenant in a
  // different tab, or from a previous session) causes cross-tenant redirects —
  // e.g. user on hour-timebank gets sent to park-goodman's login page. When
  // tokens are cleared (logout / session expiry), the slug fallback is skipped
  // and the user sees the login page's tenant chooser instead.
  //
  // SAFE for custom domains: hostname check ensures this NEVER fires on custom
  // domains or subdomains — only on localhost, 127.0.0.1, app.project-nexus.ie.
  if (!effectiveSlug && typeof window !== 'undefined') {
    const hostname = window.location.hostname;
    const isSharedHost = hostname === 'localhost' || hostname === '127.0.0.1' || hostname === 'app.project-nexus.ie';

    if (isSharedHost) {
      const storedSlug = tokenManager.getTenantSlug();
      const hasAuthTokens = tokenManager.hasAccessToken() || tokenManager.hasRefreshToken();
      if (storedSlug && hasAuthTokens) {
        const firstSegment = window.location.pathname.split('/').filter(Boolean)[0]?.toLowerCase();
        const isReservedOrEmpty = !firstSegment || RESERVED_PATHS.has(firstSegment);

        // Guard: if the storedSlug is itself a reserved word (e.g. tenant slug "admin"),
        // prepending it would just produce another reserved-looking path → infinite loop.
        const slugIsReserved = RESERVED_PATHS.has(storedSlug.toLowerCase());
        if (isReservedOrEmpty && !slugIsReserved) {
          const path = window.location.pathname === '/' ? '' : window.location.pathname;
          window.location.replace(`${window.location.origin}/${storedSlug}${path}${window.location.search}${window.location.hash}`);
          return null;
        }
      }
    }
  }

  return (
    <TenantProvider tenantSlug={effectiveSlug}>
      <AuthProvider>
        <NotificationsProvider>
          <PusherProvider>
            <PresenceProvider>
              <MenuProvider>
                <TenantGuard slugPrefix={effectiveSlug} appRoutes={appRoutes}>
                  <Outlet />
                </TenantGuard>
                <CookieConsentBanner />
              </MenuProvider>
            </PresenceProvider>
          </PusherProvider>
        </NotificationsProvider>
      </AuthProvider>
    </TenantProvider>
  );
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
  children,
  slugPrefix,
  appRoutes,
}: {
  children: React.ReactNode;
  slugPrefix?: string;
  appRoutes?: () => React.ReactNode;
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
    return (
      <div className="min-h-screen flex items-center justify-center" aria-label={t('aria.loading_community')}>
        <Spinner size="lg" color="primary" />
      </div>
    );
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

  const authPaths = ['login', 'register', 'password/forgot', 'password/reset', 'verify-email'];
  const isAuthRoute = authPaths.some((p) =>
    lowerPath === `/${p}` ||
    (lowerPrefix && lowerPath === `/${lowerPrefix}/${p}`)
  );

  if (maintenanceMode && !isAdmin && !isAdminRoute && !isAuthRoute) {
    return (
      <Suspense fallback={<div>Loading...</div>}>
        <MaintenancePage />
      </Suspense>
    );
  }

  // If there's a slug prefix, render a nested Routes with the slug stripped
  // so child routes like "dashboard" match "/hour-timebank/dashboard" correctly
  if (slugPrefix && appRoutes) {
    const strippedPath = location.pathname.replace(new RegExp(`^/${slugPrefix}`, 'i'), '') || '/';
    return (
      <>
        <SlugUrlGuard slug={slugPrefix} />
        <Routes location={{ ...location, pathname: strippedPath }}>
          {appRoutes()}
        </Routes>
      </>
    );
  }

  return <>{children}</>;
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
 * SAFE for custom domains: this component is only rendered when slugPrefix is
 * defined, which only happens on shared hosts (localhost, app.project-nexus.ie).
 */
function SlugUrlGuard({ slug }: { slug: string }) {
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
  }, [slug]);
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
          <svg className="w-8 h-8 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
              d="M12 9v2m0 4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z" />
          </svg>
        </div>
        <h1 className="text-xl font-bold text-theme-primary mb-2">
          {t('errors.connection_failed', 'Unable to connect')}
        </h1>
        <p className="text-theme-muted mb-6 text-sm">
          {t('errors.connection_failed_detail', 'Check your internet connection and try again.')}
        </p>
        <button
          onClick={onRetry}
          className="px-6 py-2 rounded-lg bg-primary text-white font-medium text-sm hover:opacity-90 transition-opacity"
        >
          {t('actions.retry', 'Try again')}
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
import { motion } from 'framer-motion';
import { Button } from '@heroui/react';
import { Helmet } from 'react-helmet-async';
import Home from 'lucide-react/icons/house';
import Search from 'lucide-react/icons/search';
import Globe from 'lucide-react/icons/globe';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { usePageTitle } from '@/hooks';

function CommunityNotFound({ slug }: { slug: string }) {
  const { t } = useTranslation('errors');
  usePageTitle(t('community_not_found'));
  return (
    <div className="min-h-[80vh] flex items-center justify-center px-4">
      <PageMeta title={t('community_not_found')} noIndex />
      <Helmet>
        <meta name="prerender-status-code" content="404" />
      </Helmet>
      <motion.div
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        className="w-full max-w-md"
      >
        <GlassCard className="p-8 text-center">
          <div className="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-amber-500/20 to-orange-500/20 mb-6">
            <Globe className="w-10 h-10 text-amber-500" aria-hidden="true" />
          </div>

          <h1 className="text-2xl font-bold text-theme-primary mb-2">{t('community_not_found')}</h1>
          <p className="text-theme-muted mb-2">
            {t('community_not_found_message', { slug })}
          </p>
          <p className="text-sm text-theme-subtle mb-8">
            {t('community_not_found_detail')}
          </p>

          <div className="flex flex-col sm:flex-row gap-3">
            <Link to="/" className="flex-1">
              <Button
                className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<Home className="w-4 h-4" aria-hidden="true" />}
              >
                {t('go_home')}
              </Button>
            </Link>
            <Link to="/login" className="flex-1">
              <Button
                variant="flat"
                className="w-full bg-theme-elevated text-theme-muted"
                startContent={<Search className="w-4 h-4" aria-hidden="true" />}
              >
                {t('find_community')}
              </Button>
            </Link>
          </div>
        </GlassCard>
      </motion.div>
    </div>
  );
}

export default TenantShell;
