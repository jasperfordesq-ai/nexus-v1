import { Card, CardBody, CardHeader, Button, Chip, Spinner, Progress } from '@/components/ui';
import {
  useState,
  useCallback,
  useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Separator } from '@heroui/react';
import Settings from 'lucide-react/icons/settings';
import BarChart3 from 'lucide-react/icons/chart-column';
import Trash2 from 'lucide-react/icons/trash-2';
import Zap from 'lucide-react/icons/zap';
import Target from 'lucide-react/icons/target';
import Database from 'lucide-react/icons/database';
import TrendingUp from 'lucide-react/icons/trending-up';
import Users from 'lucide-react/icons/users';
import ShieldCheck from 'lucide-react/icons/shield-check';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminMatching } from '../../api/adminApi';
import { StatCard, PageHeader, ConfirmModal } from '../../components';
import type { SmartMatchingConfig, MatchingStatsResponse } from '../../api/types';
import { useTranslation } from 'react-i18next';
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Smart Matching Overview
 * Dashboard showing algorithm configuration summary, matching stats, * and quick actions for the Smart Matching admin module.
 */

/** Weight metadata for display */
const WEIGHT_META: Array<{
  key: keyof Pick<SmartMatchingConfig,
    'category_weight' | 'skill_weight' | 'proximity_weight' |
    'freshness_weight' | 'reciprocity_weight' | 'quality_weight'
  >;
  label: string;
  color: 'default' | 'success' | 'warning' | 'danger';
}> = [
  { key: 'category_weight',    label: 'matching.weight_category',    color: 'default' },
  { key: 'skill_weight',       label: 'matching.weight_skill',       color: 'success' },
  { key: 'proximity_weight',   label: 'matching.weight_proximity',   color: 'warning' },
  { key: 'freshness_weight',   label: 'matching.weight_freshness',   color: 'default' },
  { key: 'reciprocity_weight', label: 'matching.weight_reciprocity', color: 'default' },
  { key: 'quality_weight',     label: 'matching.weight_quality',     color: 'danger' },
];

export function SmartMatchingOverview() {
  const { t } = useTranslation('admin');
  usePageTitle(t('matching.page_title'));
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [config, setConfig] = useState<SmartMatchingConfig | null>(null);
  const [stats, setStats] = useState<MatchingStatsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [clearModalOpen, setClearModalOpen] = useState(false);
  const [clearing, setClearing] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [configRes, statsRes] = await Promise.all([
        adminMatching.getConfig(),
        adminMatching.getMatchingStats(),
      ]);

      if (configRes.success && configRes.data) {
        setConfig(configRes.data);
      }
      if (statsRes.success && statsRes.data) {
        setStats(statsRes.data);
      }
    } catch {
      // Silently handle - stats will show as loading
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleClearCache = useCallback(async () => {
    setClearing(true);
    try {
      const res = await adminMatching.clearCache();
      if (res.success) {
        const cleared = (res.data as { entries_cleared?: number })?.entries_cleared ?? 0;
        toast.success(t('matching.cache_cleared_count', { count: cleared }));
        setClearModalOpen(false);
        loadData();
      } else {
        toast.error(t('matching.failed_to_clear_cache'));
      }
    } catch {
      toast.error(t('matching.failed_to_clear_cache'));
    } finally {
      setClearing(false);
    }
  }, [t, toast, loadData])


  const overview = stats?.overview;

  return (
    <div>
      <PageHeader
        title={t('matching.smart_matching_overview_title')}
        description={t('matching.smart_matching_overview_desc')}
        actions={
          <Button
            variant="tertiary"
            startContent={<RefreshCw aria-hidden="true" size={16} />}
            onPress={loadData}
            isLoading={loading}
            size="sm"
          >
            {t('matching.refresh')}
          </Button>
        }
      />

      {/* Stats Row */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label={t('matching.label_active_matches')}
          value={overview?.active_users_matching ?? '---'}
          icon={Target}
          color="default"
          loading={loading}
        />
        <StatCard
          label={t('matching.label_cache_size')}
          value={overview?.cache_entries ?? '---'}
          icon={Database}
          color="default"
          loading={loading}
        />
        <StatCard
          label={t('matching.label_avg_score')}
          value={overview?.avg_match_score !== undefined
            ? `${overview.avg_match_score}%`
            : '---'}
          icon={TrendingUp}
          color="success"
          loading={loading}
        />
        <StatCard
          label={t('matching.label_broker_approval')}
          value={stats?.broker_approval_enabled ? t('matching.enabled') : t('matching.disabled')}
          icon={ShieldCheck}
          color={stats?.broker_approval_enabled ? 'success' : 'warning'}
          loading={loading}
        />
      </div>

      {/* Main Content Grid */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Algorithm Weights */}
        <Card>
          <CardHeader className="flex items-center justify-between px-4 pt-4 pb-0">
            <div className="flex items-center gap-2">
              <Zap aria-hidden="true" size={18} className="text-accent" />
              <h3 className="font-semibold">{t('matching.algorithm_weights')}</h3>
            </div>
            {config && (
              <Chip
                size="sm"
                variant="soft"
                color={config.enabled ? 'success' : 'default'}
              >
                {config.enabled ? t('matching.active') : t('matching.inactive')}
              </Chip>
            )}
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div role="status" aria-busy="true" aria-label="Loading" className="flex h-48 items-center justify-center">
                <Spinner />
              </div>
            ) : config ? (
              <div className="space-y-4">
                {WEIGHT_META.map(({ key, label: labelKey, color }) => {
                  const value = config[key] ?? 0;
                  const pct = Math.round(value * 100);
                  const labelText = t(labelKey);
                  return (
                    <div key={key}>
                      <div className="flex items-center justify-between mb-1">
                        <span className="text-sm text-foreground/80">{labelText}</span>
                        <span className="text-sm font-medium text-foreground">
                          {pct}%
                        </span>
                      </div>
                      <Progress
                        value={pct}
                        color={color}
                        size="sm"
                        aria-label={t('matching.weight_aria', { label: labelText, value: pct })}
                      />
                    </div>
                  );
                })}
                <Separator className="my-2" />
                <div className="flex items-center justify-between text-sm">
                  <span className="text-muted">{t('matching.total')}</span>
                  <span className="font-semibold">
                    {Math.round(
                      ((config.category_weight ?? 0) +
                        (config.skill_weight ?? 0) +
                        (config.proximity_weight ?? 0) +
                        (config.freshness_weight ?? 0) +
                        (config.reciprocity_weight ?? 0) +
                        (config.quality_weight ?? 0)) * 100
                    )}%
                  </span>
                </div>
              </div>
            ) : (
              <p className="py-8 text-center text-sm text-muted/80">
                {t('matching.no_configuration_loaded')}
              </p>
            )}
          </CardBody>
        </Card>

        {/* Quick Actions */}
        <Card>
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Settings aria-hidden="true" size={18} className="text-accent" />
            <h3 className="font-semibold">{t('matching.quick_actions')}</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            <div className="space-y-3">
              <Button
                as={Link}
                to={tenantPath('/admin/smart-matching/configuration')}
                fullWidth
                variant="secondary"
                startContent={<Settings aria-hidden="true" size={16} />}
                className="justify-start"
              >
                {t('matching.configure_algorithm')}
              </Button>
              <Button
                as={Link}
                to={tenantPath('/admin/smart-matching/analytics')}
                fullWidth
                variant="tertiary"
                startContent={<BarChart3 aria-hidden="true" size={16} />}
                className="justify-start"
              >
                {t('matching.view_analytics')}
              </Button>
              <Button
                fullWidth
                variant="danger-soft"
                startContent={<Trash2 aria-hidden="true" size={16} />}
                className="justify-start"
                onPress={() => setClearModalOpen(true)}
              >
                {t('matching.clear_match_cache')}
              </Button>
              <Separator className="my-2" />
              <Button
                as={Link}
                to={tenantPath('/admin/match-approvals')}
                fullWidth
                variant="tertiary"
                startContent={<ShieldCheck aria-hidden="true" size={16} />}
                className="justify-start"
              >
                {t('matching.broker_approvals')}
                {stats?.pending_approvals ? (
                  <Chip size="sm" color="warning" variant="soft" className="ml-auto">
                    {stats.pending_approvals}
                  </Chip>
                ) : null}
              </Button>
            </div>
          </CardBody>
        </Card>

        {/* Matching Activity Summary */}
        <Card>
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Users aria-hidden="true" size={18} className="text-accent" />
            <h3 className="font-semibold">{t('matching.matching_activity')}</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div role="status" aria-busy="true" aria-label="Loading" className="flex h-32 items-center justify-center">
                <Spinner />
              </div>
            ) : overview ? (
              <div className="grid grid-cols-2 gap-4">
                <div className="text-center p-3 rounded-lg bg-surface-secondary">
                  <p className="text-2xl font-bold text-foreground">
                    {overview.total_matches_today}
                  </p>
                  <p className="text-xs text-muted">{t('matching.matches_today')}</p>
                </div>
                <div className="text-center p-3 rounded-lg bg-surface-secondary">
                  <p className="text-2xl font-bold text-foreground">
                    {overview.total_matches_week}
                  </p>
                  <p className="text-xs text-muted">{t('matching.this_week')}</p>
                </div>
                <div className="text-center p-3 rounded-lg bg-surface-secondary">
                  <p className="text-2xl font-bold text-foreground">
                    {overview.total_matches_month}
                  </p>
                  <p className="text-xs text-muted">{t('matching.this_month')}</p>
                </div>
                <div className="text-center p-3 rounded-lg bg-surface-secondary">
                  <p className="text-2xl font-bold text-foreground">
                    {overview.active_users_matching}
                  </p>
                  <p className="text-xs text-muted">{t('matching.active_users')}</p>
                </div>
                <div className="text-center p-3 rounded-lg bg-surface-secondary">
                  <p className="text-2xl font-bold text-foreground">
                    {overview.hot_matches_count}
                  </p>
                  <p className="text-xs text-muted">{t('matching.hot_matches')}</p>
                </div>
                <div className="text-center p-3 rounded-lg bg-surface-secondary">
                  <p className="text-2xl font-bold text-foreground">
                    {overview.mutual_matches_count}
                  </p>
                  <p className="text-xs text-muted">{t('matching.mutual')}</p>
                </div>
              </div>
            ) : (
              <p className="py-8 text-center text-sm text-muted/80">
                {t('matching.no_matching_data')}
              </p>
            )}
          </CardBody>
        </Card>

        {/* Approval Summary */}
        <Card>
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <ShieldCheck aria-hidden="true" size={18} className="text-accent" />
            <h3 className="font-semibold">{t('matching.approval_summary')}</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div className="flex h-32 items-center justify-center">
                <div role="status" aria-busy="true" aria-label="Loading" className="flex justify-center py-4"><Spinner /></div>
              </div>
            ) : stats ? (
              <div className="space-y-4">
                <div className="grid grid-cols-3 gap-3">
                  <div className="text-center p-3 rounded-lg bg-warning/10">
                    <p className="text-xl font-bold text-warning">
                      {stats.pending_approvals}
                    </p>
                    <p className="text-xs text-muted">{t('matching.label_pending')}</p>
                  </div>
                  <div className="text-center p-3 rounded-lg bg-success/10">
                    <p className="text-xl font-bold text-success">
                      {stats.approved_count}
                    </p>
                    <p className="text-xs text-muted">{t('matching.label_approved')}</p>
                  </div>
                  <div className="text-center p-3 rounded-lg bg-danger/10">
                    <p className="text-xl font-bold text-danger">
                      {stats.rejected_count}
                    </p>
                    <p className="text-xs text-muted">{t('matching.label_rejected')}</p>
                  </div>
                </div>
                <div>
                  <div className="flex items-center justify-between mb-1">
                    <span className="text-sm text-foreground/80">{t('matching.label_approval_rate')}</span>
                    <span className="text-sm font-medium">{stats.approval_rate}%</span>
                  </div>
                  <Progress
                    value={stats.approval_rate}
                    color="success"
                    size="sm"
                    aria-label={t('matching.approval_rate_aria', { value: stats.approval_rate })}
                  />
                </div>
              </div>
            ) : (
              <p className="py-8 text-center text-sm text-muted/80">
                {t('matching.no_approval_data')}
              </p>
            )}
          </CardBody>
        </Card>
      </div>

      {/* Clear Cache Confirmation */}
      <ConfirmModal
        isOpen={clearModalOpen}
        onClose={() => setClearModalOpen(false)}
        onConfirm={handleClearCache}
        title={t('matching.clear_match_cache')}
        message={t('matching.clear_cache_confirm_with_count', { count: overview?.cache_entries ?? 0 })}
        confirmLabel={t('matching.clear_cache_btn')}
        confirmColor="danger"
        isLoading={clearing}
      />
    </div>
  );
}

export default SmartMatchingOverview;
