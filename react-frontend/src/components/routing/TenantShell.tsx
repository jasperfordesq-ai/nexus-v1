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

import { Outlet, useLocation, Routes } from 'react-router-dom';
import { TenantProvider, useTenant, useAuth } from '@/contexts';
import { AuthProvider, NotificationsProvider, PusherProvider } from '@/contexts';
import { RESERVED_PATHS } from '@/lib/tenant-routing';
import { lazy, Suspense } from 'react';

const MaintenancePage = lazy(() => import('@/pages/public/MaintenancePage'));

/**
 * Props for TenantShell — receives appRoutes from App.tsx so it can
 * re-render them inside a slug-stripped nested Routes when needed.
 */
interface TenantShellProps {
  appRoutes?: () => React.ReactNode;
}

export function TenantShell({ appRoutes }: TenantShellProps) {
  const location = useLocation();

  // Extract the first path segment to check if it's a tenant slug.
  // e.g. "/hour-timebank/dashboard" → "hour-timebank"
  // e.g. "/admin/listings" → "admin" (reserved, not a slug)
  // e.g. "/dashboard" → "dashboard" (reserved, not a slug)
  const segments = location.pathname.split('/').filter(Boolean);
  const firstSegment = segments[0]?.toLowerCase();

  // Only treat as a tenant slug if it's NOT a reserved path
  const effectiveSlug = firstSegment && !RESERVED_PATHS.has(firstSegment)
    ? firstSegment
    : undefined;

  return (
    <TenantProvider tenantSlug={effectiveSlug}>
      <AuthProvider>
        <NotificationsProvider>
          <PusherProvider>
            <TenantGuard slugPrefix={effectiveSlug} appRoutes={appRoutes}>
              <Outlet />
            </TenantGuard>
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
  const { isLoading, notFoundSlug, tenant } = useTenant();
  const { user } = useAuth();
  const location = useLocation();

  // While loading, let the Suspense fallback handle it
  if (isLoading) {
    // If slug-prefixed and we have appRoutes, still need nested Routes for correct path matching
    if (slugPrefix && appRoutes) {
      const strippedPath = location.pathname.replace(new RegExp(`^/${slugPrefix}`, 'i'), '') || '/';
      return (
        <Routes location={{ ...location, pathname: strippedPath }}>
          {appRoutes()}
        </Routes>
      );
    }
    return <>{children}</>;
  }

  // If the slug was not found, show "Community Not Found" page
  if (notFoundSlug) {
    return <CommunityNotFound slug={notFoundSlug} />;
  }

  // Check for maintenance mode (after tenant is loaded)
  const maintenanceMode = tenant?.settings?.maintenance_mode === true;
  const isAdmin = user?.role && ['admin', 'tenant_admin', 'super_admin'].includes(user.role);

  // Show maintenance page to non-admin users when maintenance mode is enabled
  // Allow access to admin routes and auth pages (login, register) even in maintenance mode
  const isAdminRoute = location.pathname.startsWith('/admin') ||
                       location.pathname.includes('/admin') ||
                       (slugPrefix ? location.pathname.startsWith(`/${slugPrefix}/admin`) : false);

  const isAuthRoute = location.pathname === '/login' ||
                      location.pathname === '/register' ||
                      (slugPrefix && (location.pathname === `/${slugPrefix}/login` || location.pathname === `/${slugPrefix}/register`));

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
      <Routes location={{ ...location, pathname: strippedPath }}>
        {appRoutes()}
      </Routes>
    );
  }

  return <>{children}</>;
}

/**
 * Community Not Found page — shown when a URL slug doesn't match any tenant.
 * Inline component to avoid circular dependency with lazy-loaded NotFoundPage.
 */
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button } from '@heroui/react';
import { Home, Search, Globe } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { usePageTitle } from '@/hooks';

function CommunityNotFound({ slug }: { slug: string }) {
  usePageTitle('Community Not Found');
  return (
    <div className="min-h-[80vh] flex items-center justify-center px-4">
      <motion.div
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        className="w-full max-w-md"
      >
        <GlassCard className="p-8 text-center">
          <div className="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-amber-500/20 to-orange-500/20 mb-6">
            <Globe className="w-10 h-10 text-amber-500" aria-hidden="true" />
          </div>

          <h1 className="text-2xl font-bold text-theme-primary mb-2">Community Not Found</h1>
          <p className="text-theme-muted mb-2">
            We couldn&apos;t find a community called &ldquo;<strong>{slug}</strong>&rdquo;.
          </p>
          <p className="text-sm text-theme-subtle mb-8">
            The community may have been renamed, deactivated, or the URL may be incorrect.
          </p>

          <div className="flex flex-col sm:flex-row gap-3">
            <Link to="/" className="flex-1">
              <Button
                className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<Home className="w-4 h-4" aria-hidden="true" />}
              >
                Go Home
              </Button>
            </Link>
            <Link to="/login" className="flex-1">
              <Button
                variant="flat"
                className="w-full bg-theme-elevated text-theme-muted"
                startContent={<Search className="w-4 h-4" aria-hidden="true" />}
              >
                Find Community
              </Button>
            </Link>
          </div>
        </GlassCard>
      </motion.div>
    </div>
  );
}

export default TenantShell;
