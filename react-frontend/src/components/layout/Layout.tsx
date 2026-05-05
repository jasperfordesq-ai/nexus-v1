// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Main Layout Component
 * Wraps all pages with navigation, footer, and background
 */

import { useState, useCallback, useEffect } from 'react';
import { Outlet, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Navbar } from './Navbar';
import { MobileDrawer } from './MobileDrawer';
import { MobileTabBar } from './MobileTabBar';
import { Footer } from './Footer';
import { BackToTop } from '@/components/ui/BackToTop';
import { OfflineIndicator } from '@/components/feedback/OfflineIndicator';
import { UpdateAvailableBanner } from '@/components/feedback/UpdateAvailableBanner';
import EmergencyAlertBanner from '@/components/caring-community/EmergencyAlertBanner';
import { FadpConsentBanner } from '@/components/legal/FadpConsentBanner';
import { SessionExpiredModal } from '@/components/feedback/SessionExpiredModal';
import { AppUpdateModal } from '@/components/feedback/AppUpdateModal';
import { LanguageSwitcher } from '@/components/LanguageSwitcher';
import { SeoHead } from '@/components/seo/SeoHead';
import { useApiErrorHandler } from '@/hooks/useApiErrorHandler';
import { useHeaderScroll } from '@/hooks/useHeaderScroll';
import { useAppUpdate } from '@/hooks/useAppUpdate';
import { useVersionCheck } from '@/hooks/useVersionCheck';
import { usePushNotifications } from '@/hooks/usePushNotifications';
import { useAuth } from '@/contexts/AuthContext';
import { useTenant } from '@/contexts/TenantContext';

interface LayoutProps {
  /**
   * Whether to show the footer (default: true)
   */
  showFooter?: boolean;

  /**
   * Whether to show the navbar (default: true)
   */
  showNavbar?: boolean;

  /**
   * Whether to add padding for the fixed navbar (default: true)
   */
  withNavbarPadding?: boolean;
}

export function Layout({
  showFooter = true,
  showNavbar = true,
  withNavbarPadding = true,
}: LayoutProps) {
  const { t } = useTranslation('common');
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [isSearchOpen, setIsSearchOpen] = useState(false);

  // Memoize callbacks to prevent unnecessary re-renders
  const handleMobileMenuOpen = useCallback(() => setIsMobileMenuOpen(true), []);
  const handleMobileMenuClose = useCallback(() => setIsMobileMenuOpen(false), []);
  const handleSearchOpen = useCallback(() => setIsSearchOpen(true), []);
  const handleSearchOpenChange = useCallback((open: boolean) => setIsSearchOpen(open), []);

  // Listen for API errors and display toast notifications
  useApiErrorHandler();

  // Poll build-info.json for deploy detection — SW-independent fallback that
  // rescues users with stale/broken service workers on all browsers.
  useVersionCheck();

  // Scroll state for dynamic padding — when utility bar hides, reduce top padding
  const { isUtilityBarVisible } = useHeaderScroll(48);

  // Check for native app updates (Capacitor only, no-ops on web)
  const { updateInfo, dismiss: dismissUpdate } = useAppUpdate();

  // Register FCM device token for push notifications once user is authenticated.
  // No-ops on web browsers — only runs inside the Capacitor native app.
  const { user } = useAuth();
  const { tenantPath } = useTenant();
  const navigate = useNavigate();
  usePushNotifications(user?.id ?? null);

  // Handle deep links when the Capacitor app is opened via a URL scheme or universal link.
  //
  // Supported formats:
  //   Universal link:  https://app.project-nexus.ie/{tenant-slug}/listings/42
  //                    → path already contains slug, tenantPath() returns it unchanged
  //   Custom scheme:   nexus://{tenant-slug}/listings/42
  //                    → URL#hostname is the tenant slug, URL#pathname is the page path
  //                    → we prepend the slug ourselves before navigating
  //
  // No-ops on web browsers — window.Capacitor is undefined there.
  useEffect(() => {
    if (!window.Capacitor?.isNativePlatform?.()) return;

    let cleanup: (() => void) | undefined;

    const init = async () => {
      try {
        const capacitorAppModule = '@capacitor/app';
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const { App } = await import(/* @vite-ignore */ capacitorAppModule) as any;
        const listener = await App.addListener('appUrlOpen', (event: { url: string }) => {
          try {
            const url = new URL(event.url);
            let path = url.pathname || '/';

            // Block protocol-relative paths — only allow root-relative
            if (path.startsWith('//')) return;

            // For custom scheme (nexus://{tenant-slug}/path), the tenant slug is in
            // the URL hostname. Prepend it so the router lands on the right community.
            //   nexus://hour-timebank/listings/42  → /hour-timebank/listings/42
            //   nexus://hour-timebank              → /hour-timebank  (root, pathname='/')
            //   nexus://hour-timebank/             → /hour-timebank/ (root with trailing slash)
            // Universal links (https://app.project-nexus.ie/{slug}/path) already have
            // the slug in the pathname, so no prefix is needed.
            if (url.protocol === 'nexus:' && url.hostname) {
              // Avoid double-slash when path is already '/'
              path = `/${url.hostname}${path === '/' ? '' : path}`;
            }

            // Final safety check — must be a root-relative path
            if (!path.startsWith('/')) return;

            navigate(tenantPath(path));
          } catch {
            // Malformed URL — ignore silently
          }
        });
        cleanup = () => listener.remove();
      } catch {
        // @capacitor/app not available (web build) — ignore
      }
    };

    init();
    return () => cleanup?.();
  }, [navigate, tenantPath]);

  return (
    <div className="min-h-screen max-w-[100vw] flex flex-col overflow-x-clip">
      {/* Global SEO tags (verification meta, Organization JSON-LD) */}
      <SeoHead />

      {/* Skip navigation — visible on focus only */}
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[9999] focus:px-4 focus:py-2 focus:bg-primary focus:text-primary-foreground focus:rounded-lg focus:shadow-lg"
      >
        {t('accessibility.skip_to_content', 'Skip to main content')}
      </a>

      {/* Background blobs */}
      <div className="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div className="blob blob-indigo" />
        <div className="blob blob-purple" />
        <div className="blob blob-cyan" />
      </div>

      {/* Offline indicator */}
      <OfflineIndicator />

      {/* Service worker update banner — user-controlled, never auto-reloads */}
      <UpdateAvailableBanner />

      {/* AG70 — Emergency/safety alert banner (caring community tenants only) */}
      <EmergencyAlertBanner />

      {/* AG42 — Swiss FADP consent banner (fadp_compliance feature tenants only) */}
      <FadpConsentBanner />

      {/* Navigation */}
      {showNavbar && (
        <>
          <Navbar
            onMobileMenuOpen={handleMobileMenuOpen}
            externalSearchOpen={isSearchOpen}
            onSearchOpenChange={handleSearchOpenChange}
            isMobileMenuOpen={isMobileMenuOpen}
          />
          <MobileDrawer
            isOpen={isMobileMenuOpen}
            onClose={handleMobileMenuClose}
            onSearchOpen={handleSearchOpen}
          />
        </>
      )}

      {/* Main Content — padding adapts when utility bar hides on scroll */}
      <main
        id="main-content"
        className={`flex-1 relative z-10 min-w-0 transition-[padding-top] duration-200 ${
          withNavbarPadding && showNavbar
            ? isUtilityBarVisible
              ? 'pt-[calc(var(--safe-area-top)+5rem)] sm:pt-[calc(var(--safe-area-top)+7.5rem)]'
              : 'pt-[calc(var(--safe-area-top)+4rem)] sm:pt-[calc(var(--safe-area-top)+5.5rem)]'
            : ''
        }`}
      >
        <div className="w-full max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8 py-4 sm:py-6 md:py-8 min-w-0">
          <Outlet />
        </div>
      </main>

      {/* Footer */}
      {showFooter && <Footer />}

      {/* Mobile bottom tab bar */}
      {showNavbar && <MobileTabBar onMenuOpen={handleMobileMenuOpen} isMenuOpen={isMobileMenuOpen} />}

      {/* Back to top button */}
      <BackToTop />

      {/* Session Expired Modal */}
      <SessionExpiredModal />

      {/* Native App Update Modal */}
      {updateInfo && (
        <AppUpdateModal updateInfo={updateInfo} onDismiss={dismissUpdate} />
      )}
    </div>
  );
}

/**
 * Auth Layout - simplified layout for auth pages (no navbar/footer)
 */
export function AuthLayout() {
  const { t } = useTranslation('common');
  return (
    <div className="min-h-screen max-w-[100vw] flex flex-col overflow-x-clip">
      {/* Skip navigation — visible on focus only */}
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[9999] focus:px-4 focus:py-2 focus:bg-primary focus:text-primary-foreground focus:rounded-lg focus:shadow-lg"
      >
        {t('accessibility.skip_to_content', 'Skip to main content')}
      </a>

      {/* Background blobs */}
      <div className="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div className="blob blob-indigo" />
        <div className="blob blob-purple" />
        <div className="blob blob-cyan" />
      </div>

      {/* Service worker update banner — user-controlled, never auto-reloads */}
      <UpdateAvailableBanner />

      {/* Language switcher — top-right on auth pages */}
      <div className="absolute top-[calc(var(--safe-area-top)+1rem)] right-[calc(var(--safe-area-right)+1rem)] z-20">
        <LanguageSwitcher />
      </div>

      {/* Main Content */}
      <main id="main-content" className="relative z-10 flex-1">
        <Outlet />
      </main>

      {/* Attribution (AGPL Section 7(b) — required on all pages) */}
      <footer className="relative z-10 py-4 pb-[calc(var(--safe-area-bottom)+1rem)] text-center">
        <a
          href="https://github.com/jasperfordesq-ai/nexus-v1"
          target="_blank"
          rel="noopener noreferrer"
          className="text-xs text-white/40 hover:text-white/70 transition-colors"
        >
          Built on Project NEXUS by Jasper Ford
        </a>
      </footer>
    </div>
  );
}

export default Layout;
