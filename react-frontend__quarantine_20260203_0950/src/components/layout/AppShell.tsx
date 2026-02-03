/**
 * AppShell - NEXUS Layout Shell with Visual Identity
 *
 * Features:
 * - Holographic gradient background (soft iridescent)
 * - Glassmorphism header and content surfaces
 * - Floating content container with depth
 * - Tenant-branded accent colors
 *
 * Structure:
 * - Header (glass, sticky top)
 * - Main content area (glass container, scrollable)
 * - Footer (dark glass, at bottom)
 * - MobileNav (fixed bottom, mobile only)
 */

import { Outlet, useLocation } from 'react-router-dom';
import { useEffect } from 'react';
import { Header } from './Header';
import { Footer } from './Footer';
import { MobileNav } from './MobileNav';

export function AppShell() {
  const location = useLocation();

  // Scroll to top on route change
  useEffect(() => {
    window.scrollTo(0, 0);
  }, [location.pathname]);

  return (
    <div className="min-h-screen flex flex-col nexus-bg">
      {/* Header - glass effect applied via CSS */}
      <Header />

      {/* Main content - floating glass container */}
      <main className="flex-1 pb-24 sm:pb-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          {/* Content wrapper with fade-in animation */}
          <div className="animate-fade-in">
            <Outlet />
          </div>
        </div>
      </main>

      {/* Footer - dark glass, hidden on mobile */}
      <div className="hidden sm:block">
        <Footer />
      </div>

      {/* Mobile bottom navigation */}
      <MobileNav />
    </div>
  );
}
