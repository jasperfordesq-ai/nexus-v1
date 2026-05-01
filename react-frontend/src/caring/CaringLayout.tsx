// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import { CaringPanelSidebar } from './components/CaringPanelSidebar';
import { CaringPanelHeader } from './components/CaringPanelHeader';
import { CaringPanelBreadcrumbs } from './components/CaringPanelBreadcrumbs';

export function CaringLayout() {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [mobileDrawerOpen, setMobileDrawerOpen] = useState(false);
  const location = useLocation();

  useEffect(() => {
    setMobileDrawerOpen(false);
  }, [location.pathname]);

  return (
    <div className="min-h-screen bg-background">
      {/* Desktop fixed sidebar — md+ only */}
      <div className="hidden md:block">
        <CaringPanelSidebar
          collapsed={sidebarCollapsed}
          onToggle={() => setSidebarCollapsed((prev) => !prev)}
        />
      </div>

      <CaringPanelHeader
        sidebarCollapsed={sidebarCollapsed}
        onSidebarToggle={() => setMobileDrawerOpen((prev) => !prev)}
      />

      {mobileDrawerOpen && (
        <div
          className="fixed inset-0 z-30 bg-black/50 md:hidden"
          onClick={() => setMobileDrawerOpen(false)}
        />
      )}
      <div
        className={`fixed left-0 top-0 z-40 h-screen w-64 border-r border-divider bg-content1 transition-transform duration-300 md:hidden ${
          mobileDrawerOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <CaringPanelSidebar
          collapsed={false}
          onToggle={() => setMobileDrawerOpen(false)}
        />
      </div>

      <main
        className={`min-h-screen pt-16 transition-all duration-300 ${
          sidebarCollapsed ? 'md:ml-16' : 'md:ml-64'
        }`}
      >
        <div className="p-3 sm:p-4 md:p-6">
          <CaringPanelBreadcrumbs />
          <Outlet />
        </div>
      </main>
    </div>
  );
}

export default CaringLayout;
