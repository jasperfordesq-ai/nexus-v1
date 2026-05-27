// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Layout Shell
 * Provides the admin sidebar + header + content area.
 * All admin pages render inside this layout.
 */

import { useEffect, useMemo, useState } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AdminSidebar } from './components/AdminSidebar';
import { AdminHeader } from './components/AdminHeader';
import { AdminBreadcrumbs } from './components/AdminBreadcrumbs';
import { AdminMetaProvider, AdminMetaTags } from './AdminMetaContext';
export function AdminLayout() {
  const { t } = useTranslation('admin_nav');
  const defaultMeta = useMemo(() => ({
    title: t('admin'),
    description: t('admin_meta_description'),
  }), [t]);

  return (
    <AdminMetaProvider defaultMeta={defaultMeta}>
      <AdminLayoutShell />
    </AdminMetaProvider>
  );
}

function AdminLayoutShell() {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [mobileDrawerOpen, setMobileDrawerOpen] = useState(false);
  const location = useLocation();

  useEffect(() => {
    setMobileDrawerOpen(false);
  }, [location.pathname]);

  const { t } = useTranslation('admin_nav');

  return (
    <div className="min-h-screen bg-background">
      {/* Skip navigation — screen-reader / keyboard users jump straight to content */}
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[9999] focus:rounded-lg focus:bg-accent focus:px-4 focus:py-2 focus:text-white focus:shadow-lg"
      >
        {t('skip_to_main', 'Skip to main content')}
      </a>
      <AdminMetaTags />
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
        <button
          type="button"
          aria-label={t('close_sidebar')}
          className="fixed inset-0 z-30 w-full cursor-default bg-black/50 md:hidden"
          onClick={() => setMobileDrawerOpen(false)}
        />
      )}
      {/* Mobile sidebar drawer */}
      <div className={`fixed left-0 top-0 z-40 h-[100dvh] w-64 max-w-[calc(100dvw-var(--safe-area-left)-var(--safe-area-right))] border-r border-divider bg-surface transition-transform duration-300 md:hidden ${mobileDrawerOpen ? 'translate-x-0' : '-translate-x-full'}`}>
        <AdminSidebar
          collapsed={false}
          onToggle={() => setMobileDrawerOpen(false)}
        />
      </div>

      {/* Main content */}
      <main
        id="main-content"
        tabIndex={-1}
        className={`min-h-screen pt-16 transition-all duration-300 outline-none ${
          sidebarCollapsed ? 'md:ml-16' : 'md:ml-64'
        }`}
      >
        <div className="mx-auto w-full max-w-[1600px] p-3 sm:p-4 md:p-6">
          <AdminBreadcrumbs />
          <Outlet />
        </div>
      </main>
    </div>
  );
}

export default AdminLayout;
