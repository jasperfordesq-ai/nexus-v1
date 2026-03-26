// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Matching Analytics Page
 * Displays smart matching statistics, score distribution,
 * distance distribution, and approval metrics.
 */

import { useState, useCallback, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Progress,
  Spinner,
  Divider,
} from '@heroui/react';
import {
  ArrowLeft,
  BarChart3,
  Target,
  MapPin,
  TrendingUp,
  Users,
  Zap,
  CheckCircle,
  RefreshCw,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminMatching } from '../../api/adminApi';
import { StatCard, PageHeader, EmptyState } from '../../components';
import type { MatchingStatsResponse } from '../../api/types';

import { useTranslation } from 'react-i18next';
/** Color map for score distribution bars */
const SCORE_COLORS: Record<string, 'danger' | 'warning' | 'primary' | 'success'> = {
  '0-40': 'danger',
  '40-60': 'warning',
  '60-80': 'primary',
  '80-100': 'success',
};

const SCORE_LABEL_KEYS: Record<string, string> = {
  '0-40': 'matching.score_range_low',
  '40-60': 'matching.score_range_medium',
  '60-80': 'matching.score_range_good',
  '80-100': 'matching.score_range_hot',
};

/** Color map for distance distribution bars */
const DIST_COLORS: Record<string, 'success' | 'primary' | 'secondary' | 'warning' | 'danger'> = {
  walking: 'success',
  local: 'primary',
  city: 'secondary',
  regional: 'warning',
  distant: 'danger',
};

const DIST_LABEL_KEYS: Record<string, string> = {
  walking: 'matching.dist_walking',
  local: 'matching.dist_local',
  city: 'matching.dist_city',
  regional: 'matching.dist_regional',
  distant: 'matching.dist_distant',
};

export function MatchingAnalytics() {
  const { t } = useTranslation('admin');
  usePageTitle(t('matching.page_title'));
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [stats, setStats] = useState<MatchingStatsResponse | null>(null);
  const [loading, setLoading] = useState(true);

  const loadStats = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminMatching.getMatchingStats();
      if (res.success && res.data) {
        setStats(res.data);
      }
    } catch {
      toast.error(t('matching.failed_to_load_matching_analytics'));
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadStats();
  }, [loadStats]);

  const overview = stats?.overview;

  // Check if there is any data at all
  const hasData =
    overview &&
    (overview.cache_entries > 0 ||
      overview.total_matches_month > 0 ||
      overview.total_matches_week > 0);

  return (
    <div>
      <PageHeader
        title={t('matching.matching_analytics_title')}
        description={t('matching.matching_analytics_desc')}
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/smart-matching'))}
              size="sm"
            >
              {t('matching.back')}
            </Button>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadStats}
              isLoading={loading}
              size="sm"
            >
              {t('matching.refresh')}
            </Button>
          </div>
        }
      />

      {loading ? (
        <div className="flex h-64 items-center justify-center">
          <Spinner size="lg" />
        </div>
      ) : !hasData ? (
        <EmptyState
          icon={BarChart3}
          title={t('matching.no_matching_data_yet')}
          description={t('matching.no_matching_data_desc')}
          actionLabel={t('matching.configure_matching')}
          onAction={() => navigate(tenantPath('/admin/smart-matching/configuration'))}
        />
      ) : (
        <>
          {/* Stats Row */}
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <StatCard
              label={t('matching.label_total_matches')}
              value={overview?.total_matches_month ?? 0}
              icon={Target}
              color="primary"
              loading={loading}
            />
            <StatCard
              label={t('matching.label_approval_rate')}
              value={`${stats?.approval_rate ?? 0}%`}
              icon={CheckCircle}
              color="success"
              loading={loading}
            />
            <StatCard
              label={t('matching.label_average_score')}
              value={overview?.avg_match_score !== undefined
                ? `${overview.avg_match_score}%`
                : '---'}
              icon={TrendingUp}
              color="primary"
              loading={loading}
            />
            <StatCard
              label={t('matching.label_avg_distance')}
              value={overview?.avg_distance_km !== undefined
                ? `${overview.avg_distance_km} km`
                : '---'}
              icon={MapPin}
              color="warning"
              loading={loading}
            />
          </div>

          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {/* Score Distribution */}
            <Card shadow="sm">
              <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
                <Zap size={18} className="text-primary" />
                <h3 className="font-semibold">{t('matching.score_distribution')}</h3>
              </CardHeader>
              <CardBody className="px-4 pb-4">
                {stats?.score_distribution ? (
                  <div className="space-y-4">
                    {Object.entries(stats.score_distribution).map(([range, count]) => {
                      const total = Object.values(stats.score_distribution).reduce(
                        (a, b) => a + b,
                        0
                      );
                      const pct = total > 0 ? Math.round((count / total) * 100) : 0;
                      return (
                        <div key={range}>
                          <div className="flex items-center justify-between mb-1">
                            <span className="text-sm text-default-600">
                              {SCORE_LABEL_KEYS[range] ? t(SCORE_LABEL_KEYS[range]) : range}
                            </span>
                            <span className="text-sm font-medium tabular-nums">
                              {count} ({pct}%)
                            </span>
                          </div>
                          <Progress
                            value={pct}
                            color={SCORE_COLORS[range] ?? 'primary'}
                            size="sm"
                            aria-label={`${SCORE_LABEL_KEYS[range] ? t(SCORE_LABEL_KEYS[range]) : range}: ${count} matches (${pct}%)`}
                          />
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <p className="py-8 text-center text-sm text-default-400">
                    {t('matching.no_score_data')}
                  </p>
                )}
              </CardBody>
            </Card>

            {/* Distance Distribution */}
            <Card shadow="sm">
              <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
                <MapPin size={18} className="text-primary" />
                <h3 className="font-semibold">{t('matching.distance_distribution')}</h3>
              </CardHeader>
              <CardBody className="px-4 pb-4">
                {stats?.distance_distribution ? (
                  <div className="space-y-4">
                    {Object.entries(stats.distance_distribution).map(
                      ([band, count]) => {
                        const total = Object.values(
                          stats.distance_distribution
                        ).reduce((a, b) => a + b, 0);
                        const pct =
                          total > 0 ? Math.round((count / total) * 100) : 0;
                        return (
                          <div key={band}>
                            <div className="flex items-center justify-between mb-1">
                              <span className="text-sm text-default-600">
                                {DIST_LABEL_KEYS[band] ? t(DIST_LABEL_KEYS[band]) : band}
                              </span>
                              <span className="text-sm font-medium tabular-nums">
                                {count} ({pct}%)
                              </span>
                            </div>
                            <Progress
                              value={pct}
                              color={DIST_COLORS[band] ?? 'primary'}
                              size="sm"
                              aria-label={`${DIST_LABEL_KEYS[band] ? t(DIST_LABEL_KEYS[band]) : band}: ${count} matches (${pct}%)`}
                            />
                          </div>
                        );
                      }
                    )}
                  </div>
                ) : (
                  <p className="py-8 text-center text-sm text-default-400">
                    {t('matching.no_distance_data')}
                  </p>
                )}
              </CardBody>
            </Card>

            {/* Matching Activity */}
            <Card shadow="sm">
              <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
                <Users size={18} className="text-primary" />
                <h3 className="font-semibold">{t('matching.matching_activity')}</h3>
              </CardHeader>
              <CardBody className="px-4 pb-4">
                {overview ? (
                  <div className="space-y-3">
                    <ActivityRow
                      label={t('matching.label_matches_today')}
                      value={overview.total_matches_today}
                    />
                    <Divider />
                    <ActivityRow
                      label={t('matching.label_matches_this_week')}
                      value={overview.total_matches_week}
                    />
                    <Divider />
                    <ActivityRow
                      label={t('matching.label_matches_this_month')}
                      value={overview.total_matches_month}
                    />
                    <Divider />
                    <ActivityRow
                      label={t('matching.label_hot_matches')}
                      value={overview.hot_matches_count}
                    />
                    <Divider />
                    <ActivityRow
                      label={t('matching.label_mutual_matches')}
                      value={overview.mutual_matches_count}
                    />
                    <Divider />
                    <ActivityRow
                      label={t('matching.label_active_users_in_matching')}
                      value={overview.active_users_matching}
                    />
                    <Divider />
                    <ActivityRow
                      label={t('matching.label_cache_entries')}
                      value={overview.cache_entries}
                    />
                  </div>
                ) : (
                  <p className="py-8 text-center text-sm text-default-400">
                    {t('matching.no_activity_data')}
                  </p>
                )}
              </CardBody>
            </Card>

            {/* Approval Metrics */}
            <Card shadow="sm">
              <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
                <CheckCircle size={18} className="text-primary" />
                <h3 className="font-semibold">{t('matching.approval_metrics')}</h3>
              </CardHeader>
              <CardBody className="px-4 pb-4">
                {stats ? (
                  <div className="space-y-3">
                    <ActivityRow
                      label={t('matching.label_pending_approvals')}
                      value={stats.pending_approvals}
                      color="text-warning"
                    />
                    <Divider />
                    <ActivityRow
                      label={t('matching.label_approved_matches')}
                      value={stats.approved_count}
                      color="text-success"
                    />
                    <Divider />
                    <ActivityRow
                      label={t('matching.label_rejected_matches')}
                      value={stats.rejected_count}
                      color="text-danger"
                    />
                    <Divider />
                    <div className="flex items-center justify-between py-1">
                      <span className="text-sm text-default-600">{t('matching.label_approval_rate')}</span>
                      <span className="text-sm font-bold text-success">
                        {stats.approval_rate}%
                      </span>
                    </div>
                    <Progress
                      value={stats.approval_rate}
                      color="success"
                      size="sm"
                      aria-label={`Approval rate: ${stats.approval_rate}%`}
                    />
                    <Divider />
                    <div className="flex items-center justify-between py-1">
                      <span className="text-sm text-default-600">
                        {t('matching.label_broker_approval')}
                      </span>
                      <span
                        className={`text-sm font-medium ${
                          stats.broker_approval_enabled
                            ? 'text-success'
                            : 'text-default-400'
                        }`}
                      >
                        {stats.broker_approval_enabled ? t('matching.enabled') : t('matching.disabled')}
                      </span>
                    </div>
                  </div>
                ) : (
                  <p className="py-8 text-center text-sm text-default-400">
                    {t('matching.no_approval_data')}
                  </p>
                )}
              </CardBody>
            </Card>
          </div>
        </>
      )}
    </div>
  );
}

/** Simple key-value row for activity/metrics cards */
function ActivityRow({
  label,
  value,
  color,
}: {
  label: string;
  value: number;
  color?: string;
}) {
  return (
    <div className="flex items-center justify-between py-1">
      <span className="text-sm text-default-600">{label}</span>
      <span className={`text-sm font-bold tabular-nums ${color ?? 'text-foreground'}`}>
        {value.toLocaleString()}
      </span>
    </div>
  );
}

export default MatchingAnalytics;
