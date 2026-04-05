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
import { Award, Users, Zap, Target, RefreshCw, ArrowRight, Megaphone, BarChart3, Settings2 } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { adminGamification } from '../../api/adminApi';
import { StatCard, PageHeader } from '../../components';
import type { GamificationStats } from '../../api/types';

import { useTranslation } from 'react-i18next';
// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GamificationHub() {
  const { t } = useTranslation('admin');
  usePageTitle(t('gamification.page_title'));
  const toast = useToast();
  const { tenantPath } = useTenant();

  const [stats, setStats] = useState<GamificationStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [rechecking, setRechecking] = useState(false);

  const loadStats = useCallback(async () => {
    setLoading(true);
    const res = await adminGamification.getStats();
    if (res.success && res.data) {
      setStats(res.data as GamificationStats);
    } else {
      toast.error(t('gamification.failed_to_load_gamification_stats'));
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
      toast.success(data?.message || t('gamification.badge_recheck_completed'));
      loadStats();
    } else {
      toast.error(res.error || t('gamification.badge_recheck_failed'));
    }
    setRechecking(false);
  };

  // Find the max value for distribution chart scaling
  const maxDistCount = stats?.badge_distribution?.reduce((max, b) => Math.max(max, b.count), 0) || 1;

  return (
    <div>
      <PageHeader
        title={t('gamification.gamification_hub_title')}
        description={t('gamification.gamification_hub_desc')}
        actions={
          <Button
            color="primary"
            startContent={<RefreshCw size={16} />}
            onPress={handleRecheckAll}
            isLoading={rechecking}
          >
            {t('gamification.recheck_all_badges')}
          </Button>
        }
      />

      {/* Stats Row */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label={t('gamification.label_total_badges_awarded')}
          value={stats?.total_badges_awarded ?? 0}
          icon={Award}
          color="primary"
          loading={loading}
        />
        <StatCard
          label={t('gamification.label_active_users')}
          value={stats?.active_users ?? 0}
          icon={Users}
          color="success"
          loading={loading}
        />
        <StatCard
          label={t('gamification.label_total_x_p_awarded')}
          value={stats?.total_xp_awarded ?? 0}
          icon={Zap}
          color="warning"
          loading={loading}
        />
        <StatCard
          label={t('gamification.label_active_campaigns')}
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
              <h3 className="text-lg font-semibold text-foreground">{t('gamification.badge_distribution')}</h3>
              <p className="text-sm text-default-500">{t('gamification.top_10_badges')}</p>
            </div>
          </CardHeader>
          <CardBody>
            {loading ? (
              <div className="flex items-center justify-center py-12">
                <Spinner size="lg" />
              </div>
            ) : stats?.badge_distribution && stats.badge_distribution.length > 0 ? (
              <div className="space-y-3">
                {stats.badge_distribution.map((badge) => (
                  <div key={badge.badge_name} className="flex items-center gap-3">
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
                <p className="text-default-500">{t('gamification.no_badges_awarded')}</p>
              </div>
            )}
          </CardBody>
        </Card>

        {/* Quick Links */}
        <Card shadow="sm">
          <CardHeader className="pb-0">
            <h3 className="text-lg font-semibold text-foreground">{t('gamification.quick_links')}</h3>
          </CardHeader>
          <CardBody className="gap-3">
            <Link to={tenantPath("/admin/gamification/campaigns")} className="block">
              <Card shadow="none" className="bg-default-50 hover:bg-default-100 transition-colors cursor-pointer">
                <CardBody className="flex flex-row items-center gap-3 p-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Megaphone size={20} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-foreground">{t('gamification.campaigns')}</p>
                    <p className="text-xs text-default-500">{t('gamification.manage_badge_campaigns')}</p>
                  </div>
                  <ArrowRight size={16} className="text-default-400" />
                </CardBody>
              </Card>
            </Link>

            <Link to={tenantPath("/admin/custom-badges")} className="block">
              <Card shadow="none" className="bg-default-50 hover:bg-default-100 transition-colors cursor-pointer">
                <CardBody className="flex flex-row items-center gap-3 p-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-success/10 text-success">
                    <Award size={20} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-foreground">{t('gamification.custom_badges')}</p>
                    <p className="text-xs text-default-500">{t('gamification.create_and_manage_badges')}</p>
                  </div>
                  <ArrowRight size={16} className="text-default-400" />
                </CardBody>
              </Card>
            </Link>

            <Link to={tenantPath("/admin/gamification/badge-config")} className="block">
              <Card shadow="none" className="bg-default-50 hover:bg-default-100 transition-colors cursor-pointer">
                <CardBody className="flex flex-row items-center gap-3 p-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-secondary/10 text-secondary">
                    <Settings2 size={20} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-foreground">{t('gamification.badge_configuration', 'Badge Configuration')}</p>
                    <p className="text-xs text-default-500">{t('gamification.configure_badge_availability', 'Enable, disable & customize badges')}</p>
                  </div>
                  <ArrowRight size={16} className="text-default-400" />
                </CardBody>
              </Card>
            </Link>

            <Link to={tenantPath("/admin/gamification/analytics")} className="block">
              <Card shadow="none" className="bg-default-50 hover:bg-default-100 transition-colors cursor-pointer">
                <CardBody className="flex flex-row items-center gap-3 p-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-warning/10 text-warning">
                    <BarChart3 size={20} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-foreground">{t('gamification.analytics')}</p>
                    <p className="text-xs text-default-500">{t('gamification.gamification_insights')}</p>
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
