/**
 * TenantShell
 *
 * Wraps tenant-scoped content. Reads :tenantSlug from route params,
 * passes it to TenantProvider, and handles unknown-slug soft 404.
 *
 * Used by App.tsx to support both:
 *   /dashboard           (no slug prefix — tenant from domain or chooser)
 *   /:tenantSlug/dashboard (slug prefix — Phase 0-1 path-based resolution)
 *
 * Implements TRS-001 R3 (path-based resolution) at the React routing level.
 * @see docs/TRS-001-TENANT-RESOLUTION-SPEC.md
 */

import { Outlet, useParams } from 'react-router-dom';
import { TenantProvider, useTenant } from '@/contexts';
import { AuthProvider, NotificationsProvider, PusherProvider } from '@/contexts';
import { RESERVED_PATHS } from '@/lib/tenant-routing';

export function TenantShell() {
  const { tenantSlug } = useParams<{ tenantSlug: string }>();

  // If the tenantSlug param matches a reserved path, this shouldn't be treated
  // as a tenant slug. React Router should have matched the more specific route
  // first, but this is a safety check.
  const effectiveSlug = tenantSlug && !RESERVED_PATHS.has(tenantSlug.toLowerCase())
    ? tenantSlug.toLowerCase()
    : undefined;

  return (
    <TenantProvider tenantSlug={effectiveSlug}>
      <AuthProvider>
        <NotificationsProvider>
          <PusherProvider>
            <TenantGuard>
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
 */
function TenantGuard({ children }: { children: React.ReactNode }) {
  const { isLoading, notFoundSlug } = useTenant();

  // While loading, let the Suspense fallback handle it
  if (isLoading) {
    return <>{children}</>;
  }

  // If the slug was not found, show "Community Not Found" page
  if (notFoundSlug) {
    return <CommunityNotFound slug={notFoundSlug} />;
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
