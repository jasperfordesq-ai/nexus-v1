// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Main Layout Component
 * Wraps all pages with navigation, footer, and background
 */

import { useState, useCallback } from 'react';
import { Outlet } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Navbar } from './Navbar';
import { MobileDrawer } from './MobileDrawer';
import { MobileTabBar } from './MobileTabBar';
import { Footer } from './Footer';
import { BackToTop } from '@/components/ui/BackToTop';
import { OfflineIndicator } from '@/components/feedback/OfflineIndicator';
import { UpdateAvailableBanner } from '@/components/feedback/UpdateAvailableBanner';
import { DevelopmentStatusBanner } from './DevelopmentStatusBanner';
import { SessionExpiredModal } from '@/components/feedback';
import { AppUpdateModal } from '@/components/feedback/AppUpdateModal';
import { LanguageSwitcher } from '@/components/LanguageSwitcher';
import { useApiErrorHandler } from '@/hooks';
import { useHeaderScroll } from '@/hooks/useHeaderScroll';
import { useAppUpdate } from '@/hooks/useAppUpdate';

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

  // Scroll state for dynamic padding — when utility bar hides, reduce top padding
  const { isUtilityBarVisible } = useHeaderScroll(48);

  // Check for native app updates (Capacitor only, no-ops on web)
  const { updateInfo, dismiss: dismissUpdate } = useAppUpdate();

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

      {/* Offline indicator */}
      <OfflineIndicator />

      {/* Service worker update banner — user-controlled, never auto-reloads */}
      <UpdateAvailableBanner />

      {/* Navigation */}
      {showNavbar && (
        <>
          <Navbar
            onMobileMenuOpen={handleMobileMenuOpen}
            externalSearchOpen={isSearchOpen}
            onSearchOpenChange={handleSearchOpenChange}
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
              ? 'pt-20 sm:pt-[7.5rem]'
              : 'pt-16 sm:pt-[5.5rem]'
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
      {showNavbar && <MobileTabBar onMenuOpen={handleMobileMenuOpen} />}

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
  return (
    <div className="min-h-screen max-w-[100vw] flex flex-col overflow-x-clip">
      {/* Background blobs */}
      <div className="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div className="blob blob-indigo" />
        <div className="blob blob-purple" />
        <div className="blob blob-cyan" />
      </div>

      {/* Development status banner */}
      <DevelopmentStatusBanner />

      {/* Service worker update banner — user-controlled, never auto-reloads */}
      <UpdateAvailableBanner />

      {/* Language switcher — top-right on auth pages */}
      <div className="absolute top-4 right-4 z-20">
        <LanguageSwitcher />
      </div>

      {/* Main Content */}
      <main className="relative z-10 flex-1">
        <Outlet />
      </main>

      {/* Attribution (AGPL Section 7(b) — required on all pages) */}
      <footer className="relative z-10 py-4 text-center">
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
