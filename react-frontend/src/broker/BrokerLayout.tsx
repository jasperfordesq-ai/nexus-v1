// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Layout Shell
 * Provides the broker sidebar + header + content area.
 * Simplified version of AdminLayout for broker users.
 */

import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import { BrokerSidebar } from './components/BrokerSidebar';
import { BrokerHeader } from './components/BrokerHeader';
import { BrokerBreadcrumbs } from './components/BrokerBreadcrumbs';
export function BrokerLayout() {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);

  return (
    <div className="min-h-screen bg-background">
      {/* Sidebar — hidden on mobile, shown on md+ */}
      <div className="hidden md:block">
        <BrokerSidebar
          collapsed={sidebarCollapsed}
          onToggle={() => setSidebarCollapsed((prev) => !prev)}
        />
      </div>

      {/* Header */}
      <BrokerHeader
        sidebarCollapsed={sidebarCollapsed}
        onSidebarToggle={() => setSidebarCollapsed((prev) => !prev)}
      />

      {/* Mobile sidebar overlay */}
      {!sidebarCollapsed && (
        <div
          className="fixed inset-0 z-30 bg-black/50 md:hidden"
          onClick={() => setSidebarCollapsed(true)}
        />
      )}
      {/* Mobile sidebar drawer */}
      <div
        className={`fixed left-0 top-0 z-40 h-screen w-64 border-r border-divider bg-content1 transition-transform duration-300 md:hidden ${
          sidebarCollapsed ? '-translate-x-full' : 'translate-x-0'
        }`}
      >
        <BrokerSidebar
          collapsed={false}
          onToggle={() => setSidebarCollapsed(true)}
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
