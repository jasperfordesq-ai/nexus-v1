// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Smart Match Monitoring
 * Monitor the smart matching engine performance and quality metrics.
 * Wired to adminMatching.getMatchingStats() API (existing module).
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Spinner } from '@heroui/react';
import { Activity, BarChart3 } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminMatching } from '../../api/adminApi';
import { PageHeader, StatCard } from '../../components';
import type { MatchingStatsResponse } from '../../api/types';

import { useTranslation } from 'react-i18next';
export function SmartMatchMonitoring() {
  const { t } = useTranslation('admin');
  usePageTitle(t('community.page_title'));
  const toast = useToast();

  const [data, setData] = useState<MatchingStatsResponse | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    adminMatching.getMatchingStats()
      .then((res) => {
        if (res.success && res.data) {
          setData(res.data as MatchingStatsResponse);
        }
      })
      .catch(() => toast.error(t('community.failed_to_load_matching_stats')))
      .finally(() => setLoading(false));
  }, [toast, t])

  if (loading) {
    return (
      <div>
        <PageHeader title={t('community.smart_match_monitoring_title')} description={t('community.smart_match_monitoring_desc')} />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  const overview = data?.overview;

  return (
    <div>
      <PageHeader title={t('community.smart_match_monitoring_title')} description={t('community.smart_match_monitoring_desc')} />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label={t('community.label_matches_generated')}
          value={overview?.total_matches_month ?? 0}
          icon={Activity}
          color="primary"
        />
        <StatCard
          label={t('community.label_avg_match_score')}
          value={overview?.avg_match_score !== undefined ? `${Number(overview.avg_match_score).toFixed(1)}%` : '--'}
          icon={BarChart3}
          color="success"
        />
        <StatCard
          label={t('community.label_approval_rate')}
          value={data?.approval_rate !== undefined ? `${Number(data.approval_rate).toFixed(0)}%` : '--'}
          icon={Activity}
          color="warning"
        />
        <StatCard
          label={t('community.label_cache_hit_rate')}
          value={overview?.cache_hit_rate !== undefined ? `${Number(overview.cache_hit_rate).toFixed(0)}%` : '--'}
          icon={Activity}
          color="secondary"
        />
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">{t('community.engine_status')}</h3></CardHeader>
          <CardBody>
            {data ? (
              <div className="space-y-3">
                <div className="flex items-center justify-between py-1 border-b border-default-100">
                  <span className="text-sm text-default-500">{t('community.broker_approval')}</span>
                  <span className="text-sm font-medium">{data.broker_approval_enabled ? t('community.enabled') : t('community.disabled')}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-default-100">
                  <span className="text-sm text-default-500">{t('community.pending_approvals')}</span>
                  <span className="text-sm font-medium">{data.pending_approvals ?? 0}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-default-100">
                  <span className="text-sm text-default-500">{t('community.approved_total')}</span>
                  <span className="text-sm font-medium">{data.approved_count ?? 0}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-default-100">
                  <span className="text-sm text-default-500">{t('community.rejected_total')}</span>
                  <span className="text-sm font-medium">{data.rejected_count ?? 0}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-default-100">
                  <span className="text-sm text-default-500">{t('community.matches_today')}</span>
                  <span className="text-sm font-medium">{overview?.total_matches_today ?? 0}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-default-100">
                  <span className="text-sm text-default-500">{t('community.matches_this_week')}</span>
                  <span className="text-sm font-medium">{overview?.total_matches_week ?? 0}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-default-100">
                  <span className="text-sm text-default-500">{t('community.hot_matches')}</span>
                  <span className="text-sm font-medium">{overview?.hot_matches_count ?? 0}</span>
                </div>
                <div className="flex items-center justify-between py-1">
                  <span className="text-sm text-default-500">{t('community.active_matching_users')}</span>
                  <span className="text-sm font-medium">{overview?.active_users_matching ?? 0}</span>
                </div>
              </div>
            ) : (
              <div className="flex flex-col items-center py-8 text-default-400">
                <Activity size={40} className="mb-3" />
                <p>{t('community.no_monitoring_data')}</p>
                <p className="text-xs mt-1">{t('community.configure_matching_hint')}</p>
              </div>
            )}
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">{t('community.score_distribution')}</h3></CardHeader>
          <CardBody>
            {data?.score_distribution && Object.keys(data.score_distribution).length > 0 ? (
              <div className="space-y-3">
                {Object.entries(data.score_distribution).map(([range, count]) => (
                  <div key={range} className="flex items-center justify-between py-1 border-b border-default-100 last:border-0">
                    <span className="text-sm">{range}</span>
                    <span className="text-sm font-medium text-primary">{count}</span>
                  </div>
                ))}
              </div>
            ) : (
              <div className="flex flex-col items-center py-8 text-default-400">
                <BarChart3 size={40} className="mb-3" />
                <p className="text-sm">{t('community.no_score_distribution')}</p>
              </div>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default SmartMatchMonitoring;
