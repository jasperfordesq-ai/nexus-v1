// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Layout Shell
 * Provides the broker sidebar + header + content area.
 * Simplified version of AdminLayout for broker users.
 */

import { useState, useEffect } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import { BrokerSidebar } from './components/BrokerSidebar';
import { BrokerHeader } from './components/BrokerHeader';
import { BrokerBreadcrumbs } from './components/BrokerBreadcrumbs';

export function BrokerLayout() {
  // Desktop collapse state (controls width of fixed sidebar on md+).
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  // Mobile drawer open state — independent of desktop collapse.
  // Default closed so first paint on mobile doesn't cover the page.
  const [mobileDrawerOpen, setMobileDrawerOpen] = useState(false);
  const location = useLocation();

  // Auto-close the mobile drawer whenever the route changes — without this
  // the drawer would stay open over the page after navigating from a nav link.
  useEffect(() => {
    setMobileDrawerOpen(false);
  }, [location.pathname]);

  return (
    <div className="min-h-screen bg-background">
      {/* Sidebar — fixed on md+ */}
      <div className="hidden md:block">
        <BrokerSidebar
          collapsed={sidebarCollapsed}
          onToggle={() => setSidebarCollapsed((prev) => !prev)}
        />
      </div>

      {/* Header */}
      <BrokerHeader
        sidebarCollapsed={sidebarCollapsed}
        onSidebarToggle={() => setMobileDrawerOpen((prev) => !prev)}
      />

      {/* Mobile sidebar overlay */}
      {mobileDrawerOpen && (
        <div
          className="fixed inset-0 z-30 bg-black/50 md:hidden"
          onClick={() => setMobileDrawerOpen(false)}
        />
      )}
      {/* Mobile sidebar drawer */}
      <div
        className={`fixed left-0 top-0 z-40 h-screen w-64 border-r border-divider bg-content1 transition-transform duration-300 md:hidden ${
          mobileDrawerOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <BrokerSidebar
          collapsed={false}
          onToggle={() => setMobileDrawerOpen(false)}
        />
      </div>

      {/* Main content */}
      <main
        className={`min-h-screen pt-16 transition-all duration-300 ${
          sidebarCollapsed ? 'md:ml-16' : 'md:ml-64'
        }`}
      >
        <div className="p-3 sm:p-4 md:p-6">
          <BrokerBreadcrumbs />
          <Outlet />
        </div>
      </main>
    </div>
  );
}

export default BrokerLayout;
