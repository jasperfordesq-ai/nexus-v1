// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Layout Shell
 * Provides the broker sidebar + header + content area.
 * Simplified version of AdminLayout for broker users.
 *
 * Owns the sidebar badge counts so we only fetch them once even though we
 * mount two BrokerSidebar instances (desktop fixed + mobile drawer).
 */

import { useState, useEffect, useCallback } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import { adminBroker, adminUsers } from '@/admin/api/adminApi';
import { BrokerSidebar, type BrokerBadgeCounts } from './components/BrokerSidebar';
import { BrokerHeader } from './components/BrokerHeader';
import { BrokerBreadcrumbs } from './components/BrokerBreadcrumbs';

const EMPTY_BADGES: BrokerBadgeCounts = {
  pending_members: 0,
  safeguarding_alerts: 0,
  vetting_expiring: 0,
  pending_exchanges: 0,
  unreviewed_messages: 0,
  monitored_users: 0,
  high_risk_listings: 0,
};

export function BrokerLayout() {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [mobileDrawerOpen, setMobileDrawerOpen] = useState(false);
  const [badges, setBadges] = useState<BrokerBadgeCounts>(EMPTY_BADGES);
  const location = useLocation();

  useEffect(() => {
    setMobileDrawerOpen(false);
  }, [location.pathname]);

  const fetchBadges = useCallback(async () => {
    try {
      const [dashRes, usersRes] = await Promise.all([
        adminBroker.getDashboard(),
        adminUsers.list({ status: 'pending', limit: 1 }),
      ]);

      let pendingMembers = 0;
      if (usersRes.success && usersRes.data) {
        const payload = usersRes.data as unknown;
        if (Array.isArray(payload)) {
          pendingMembers = payload.length;
        } else if (payload && typeof payload === 'object') {
          const paged = payload as { data: unknown[]; meta?: { total: number } };
          pendingMembers = paged.meta?.total ?? paged.data?.length ?? 0;
        }
      }

      if (dashRes.success && dashRes.data) {
        const d = dashRes.data as unknown as Record<string, unknown>;
        setBadges({
          pending_members: pendingMembers,
          safeguarding_alerts: Number(d.safeguarding_alerts ?? 0),
          vetting_expiring: Number(d.vetting_expiring ?? 0),
          pending_exchanges: Number(d.pending_exchanges ?? 0),
          unreviewed_messages: Number(d.unreviewed_messages ?? 0),
          monitored_users: Number(d.monitored_users ?? 0),
          high_risk_listings: Number(d.high_risk_listings ?? 0),
        });
      }
    } catch {
      // Badges are non-critical — silently fail (e.g. on 401/403/network).
    }
  }, []);

  useEffect(() => {
    void fetchBadges();
    const interval = setInterval(() => void fetchBadges(), 60_000);
    return () => clearInterval(interval);
  }, [fetchBadges]);

  return (
    <div className="min-h-screen bg-background">
      {/* Desktop fixed sidebar — md+ only */}
      <div className="hidden md:block">
        <BrokerSidebar
          collapsed={sidebarCollapsed}
          onToggle={() => setSidebarCollapsed((prev) => !prev)}
          badges={badges}
        />
      </div>

      <BrokerHeader
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
        className={`fixed left-0 top-0 z-40 h-[100dvh] w-64 max-w-[calc(100dvw-var(--safe-area-left)-var(--safe-area-right))] border-r border-divider bg-content1 transition-transform duration-300 md:hidden ${
          mobileDrawerOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <BrokerSidebar
          collapsed={false}
          onToggle={() => setMobileDrawerOpen(false)}
          badges={badges}
        />
      </div>

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
