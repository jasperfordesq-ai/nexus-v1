// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Layout Shell
 * Provides the admin sidebar + header + content area.
 * All admin pages render inside this layout.
 */

import { useEffect, useState } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import { AdminSidebar } from './components/AdminSidebar';
import { AdminHeader } from './components/AdminHeader';
import { AdminBreadcrumbs } from './components/AdminBreadcrumbs';
export function AdminLayout() {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [mobileDrawerOpen, setMobileDrawerOpen] = useState(false);
  const location = useLocation();

  useEffect(() => {
    setMobileDrawerOpen(false);
  }, [location.pathname]);

  return (
    <div className="min-h-screen bg-background">
      {/* Sidebar — hidden on mobile, shown on md+ */}
      <div className="hidden md:block">
        <AdminSidebar
          collapsed={sidebarCollapsed}
          onToggle={() => setSidebarCollapsed((prev) => !prev)}
        />
      </div>

      {/* Header */}
      <AdminHeader sidebarCollapsed={sidebarCollapsed} onSidebarToggle={() => setMobileDrawerOpen((prev) => !prev)} />

      {/* Mobile sidebar overlay */}
      {mobileDrawerOpen && (
        <div
          className="fixed inset-0 z-30 bg-black/50 md:hidden"
          onClick={() => setMobileDrawerOpen(false)}
        />
      )}
      {/* Mobile sidebar drawer */}
      <div className={`fixed left-0 top-0 z-40 h-[100dvh] w-64 max-w-[calc(100dvw-var(--safe-area-left)-var(--safe-area-right))] border-r border-divider bg-content1 transition-transform duration-300 md:hidden ${mobileDrawerOpen ? 'translate-x-0' : '-translate-x-full'}`}>
        <AdminSidebar
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
          <AdminBreadcrumbs />
          <Outlet />
        </div>
      </main>
    </div>
  );
}

export default AdminLayout;
