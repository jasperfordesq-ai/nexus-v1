/**
 * Main Layout Component
 * Wraps all pages with navigation, footer, and background
 */

import { useState, useCallback } from 'react';
import { Outlet } from 'react-router-dom';
import { Navbar } from './Navbar';
import { MobileDrawer } from './MobileDrawer';
import { MobileTabBar } from './MobileTabBar';
import { Footer } from './Footer';
import { BackToTop } from '@/components/ui/BackToTop';
import { OfflineIndicator } from '@/components/feedback/OfflineIndicator';
import { SessionExpiredModal } from '@/components/feedback';
import { useApiErrorHandler } from '@/hooks';

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
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [isSearchOpen, setIsSearchOpen] = useState(false);

  // Memoize callbacks to prevent unnecessary re-renders
  const handleMobileMenuOpen = useCallback(() => setIsMobileMenuOpen(true), []);
  const handleMobileMenuClose = useCallback(() => setIsMobileMenuOpen(false), []);
  const handleSearchOpen = useCallback(() => setIsSearchOpen(true), []);
  const handleSearchOpenChange = useCallback((open: boolean) => setIsSearchOpen(open), []);

  // Listen for API errors and display toast notifications
  useApiErrorHandler();

  return (
    <div className="min-h-screen flex flex-col">
      {/* Background blobs */}
      <div className="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div className="blob blob-indigo" />
        <div className="blob blob-purple" />
        <div className="blob blob-cyan" />
      </div>

      {/* Offline indicator */}
      <OfflineIndicator />

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

      {/* Main Content */}
      <main
        className={`flex-1 relative z-10 ${withNavbarPadding && showNavbar ? 'pt-16' : ''}`}
      >
        <div className="container mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
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
    </div>
  );
}

/**
 * Auth Layout - simplified layout for auth pages (no navbar/footer)
 */
export function AuthLayout() {
  return (
    <div className="min-h-screen">
      {/* Background blobs */}
      <div className="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div className="blob blob-indigo" />
        <div className="blob blob-purple" />
        <div className="blob blob-cyan" />
      </div>

      {/* Main Content */}
      <main className="relative z-10">
        <Outlet />
      </main>
    </div>
  );
}

export default Layout;
