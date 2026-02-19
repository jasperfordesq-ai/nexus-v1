// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Gamification Hub
 * Dashboard overview of badges, XP, campaigns, and quick links.
 * Parity: PHP Admin\GamificationController@index
 */

import { useState, useCallback, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, CardHeader, Button, Spinner } from '@heroui/react';
import { Award, Users, Zap, Target, RefreshCw, ArrowRight, Megaphone, BarChart3 } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminGamification } from '../../api/adminApi';
import { StatCard, PageHeader } from '../../components';
import type { GamificationStats } from '../../api/types';

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GamificationHub() {
  usePageTitle('Admin - Gamification');
  const toast = useToast();

  const [stats, setStats] = useState<GamificationStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [rechecking, setRechecking] = useState(false);

  const loadStats = useCallback(async () => {
    setLoading(true);
    const res = await adminGamification.getStats();
    if (res.success && res.data) {
      setStats(res.data as GamificationStats);
    } else {
      toast.error('Failed to load gamification stats');
    }
    setLoading(false);
  }, [toast]);

  useEffect(() => {
    loadStats();
  }, [loadStats]);

  const handleRecheckAll = async () => {
    setRechecking(true);
    const res = await adminGamification.recheckAll();
    if (res.success) {
      const data = res.data as { users_checked?: number; message?: string } | undefined;
      toast.success(data?.message || 'Badge recheck completed');
      loadStats();
    } else {
      toast.error(res.error || 'Badge recheck failed');
    }
    setRechecking(false);
  };

  // Find the max value for distribution chart scaling
  const maxDistCount = stats?.badge_distribution?.reduce((max, b) => Math.max(max, b.count), 0) || 1;

  return (
    <div>
      <PageHeader
        title="Gamification Hub"
        description="Badges, achievements, and XP"
        actions={
          <Button
            color="primary"
            startContent={<RefreshCw size={16} />}
            onPress={handleRecheckAll}
            isLoading={rechecking}
          >
            Recheck All Badges
          </Button>
        }
      />

      {/* Stats Row */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label="Total Badges Awarded"
          value={stats?.total_badges_awarded ?? 0}
          icon={Award}
          color="primary"
          loading={loading}
        />
        <StatCard
          label="Active Users"
          value={stats?.active_users ?? 0}
          icon={Users}
          color="success"
          loading={loading}
        />
        <StatCard
          label="Total XP Awarded"
          value={stats?.total_xp_awarded ?? 0}
          icon={Zap}
          color="warning"
          loading={loading}
        />
        <StatCard
          label="Active Campaigns"
          value={stats?.active_campaigns ?? 0}
          icon={Target}
          color="secondary"
          loading={loading}
        />
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Badge Distribution Chart */}
        <Card shadow="sm" className="lg:col-span-2">
          <CardHeader className="flex items-center justify-between pb-0">
            <div>
              <h3 className="text-lg font-semibold text-foreground">Badge Distribution</h3>
              <p className="text-sm text-default-500">Top 10 most awarded badges</p>
            </div>
          </CardHeader>
          <CardBody>
            {loading ? (
              <div className="flex items-center justify-center py-12">
                <Spinner size="lg" />
              </div>
            ) : stats?.badge_distribution && stats.badge_distribution.length > 0 ? (
              <div className="space-y-3">
                {stats.badge_distribution.map((badge, index) => (
                  <div key={index} className="flex items-center gap-3">
                    <span className="w-36 truncate text-sm text-foreground font-medium" title={badge.badge_name}>
                      {badge.badge_name}
                    </span>
                    <div className="flex-1 h-6 rounded-lg bg-default-100 overflow-hidden">
                      <div
                        className="h-full rounded-lg bg-primary transition-all duration-500"
                        style={{ width: `${Math.max(2, (badge.count / maxDistCount) * 100)}%` }}
                      />
                    </div>
                    <span className="w-10 text-right text-sm font-semibold text-foreground">
                      {badge.count}
                    </span>
                  </div>
                ))}
              </div>
            ) : (
              <div className="flex flex-col items-center justify-center py-12 text-center">
                <Award size={40} className="text-default-300 mb-2" />
                <p className="text-default-500">No badges awarded yet</p>
              </div>
            )}
          </CardBody>
        </Card>

        {/* Quick Links */}
        <Card shadow="sm">
          <CardHeader className="pb-0">
            <h3 className="text-lg font-semibold text-foreground">Quick Links</h3>
          </CardHeader>
          <CardBody className="gap-3">
            <Link to="../gamification/campaigns" className="block">
              <Card shadow="none" className="bg-default-50 hover:bg-default-100 transition-colors cursor-pointer">
                <CardBody className="flex flex-row items-center gap-3 p-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Megaphone size={20} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-foreground">Campaigns</p>
                    <p className="text-xs text-default-500">Manage badge campaigns</p>
                  </div>
                  <ArrowRight size={16} className="text-default-400" />
                </CardBody>
              </Card>
            </Link>

            <Link to="../custom-badges" className="block">
              <Card shadow="none" className="bg-default-50 hover:bg-default-100 transition-colors cursor-pointer">
                <CardBody className="flex flex-row items-center gap-3 p-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-success/10 text-success">
                    <Award size={20} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-foreground">Custom Badges</p>
                    <p className="text-xs text-default-500">Create and manage badges</p>
                  </div>
                  <ArrowRight size={16} className="text-default-400" />
                </CardBody>
              </Card>
            </Link>

            <Link to="../gamification/analytics" className="block">
              <Card shadow="none" className="bg-default-50 hover:bg-default-100 transition-colors cursor-pointer">
                <CardBody className="flex flex-row items-center gap-3 p-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-warning/10 text-warning">
                    <BarChart3 size={20} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-foreground">Analytics</p>
                    <p className="text-xs text-default-500">Gamification insights</p>
                  </div>
                  <ArrowRight size={16} className="text-default-400" />
                </CardBody>
              </Card>
            </Link>
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default GamificationHub;
