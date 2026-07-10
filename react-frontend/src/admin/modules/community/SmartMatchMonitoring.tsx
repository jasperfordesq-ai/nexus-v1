// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Smart Match Monitoring
 * Monitor the smart matching engine performance and quality metrics.
 * Wired to adminMatching.getMatchingStats() API (existing module).
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import Activity from 'lucide-react/icons/activity';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import BarChart3 from 'lucide-react/icons/chart-column';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { logError } from '@/lib/logger';
import { Button, Card, CardBody, CardHeader, Spinner } from '@/components/ui';
import { adminMatching } from '../../api/adminApi';
import { EmptyState } from '../../components/EmptyState';
import { PageHeader } from '../../components/PageHeader';
import { StatCard } from '../../components/StatCard';
import type { MatchingStatsResponse } from '../../api/types';
import { parseMatchingStatsResponse } from '../matching/matchingResponseGuards';

export function SmartMatchMonitoring() {
  const { t } = useTranslation('admin_community');
  usePageTitle(t('community.page_title'));

  const [data, setData] = useState<MatchingStatsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadFailed, setLoadFailed] = useState(false);
  const requestIdRef = useRef(0);

  const loadData = useCallback(async () => {
    const requestId = ++requestIdRef.current;
    setLoading(true);
    try {
      const res = await adminMatching.getMatchingStats();
      if (requestId !== requestIdRef.current) return;
      const parsed = res.success ? parseMatchingStatsResponse(res.data) : null;
      if (parsed) {
        setData(parsed);
        setLoadFailed(false);
      } else {
        setLoadFailed(true);
      }
    } catch (err) {
      if (requestId !== requestIdRef.current) return;
      logError('Failed to load matching stats', err);
      setLoadFailed(true);
    } finally {
      if (requestId === requestIdRef.current) setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadData();
    return () => { requestIdRef.current += 1; };
  }, [loadData]);

  const refreshAction = (
    <Button variant="secondary" onPress={loadData} isLoading={loading} startContent={<RefreshCw size={16} aria-hidden="true" />}>
      {t('common.refresh')}
    </Button>
  );

  return (
    <div>
      <PageHeader title={t('community.smart_match_monitoring_title')} description={t('community.smart_match_monitoring_desc')} actions={refreshAction} />

      {loading && data === null ? (
        <div className="flex justify-center py-12" role="status" aria-busy="true" aria-label={t('common.loading')}><Spinner size="lg" /></div>
      ) : loadFailed && data === null ? (
        <div role="alert">
          <EmptyState icon={AlertTriangle} title={t('community.failed_to_load_matching_stats')} actionLabel={t('common.retry')} onAction={loadData} />
        </div>
      ) : data !== null ? (
        <>
          {loadFailed && (
            <div className="mb-4 flex flex-col gap-3 rounded-xl border border-danger/30 bg-danger/5 p-4 text-danger sm:flex-row sm:items-center sm:justify-between" role="alert">
              <div className="flex items-center gap-2"><AlertTriangle size={18} aria-hidden="true" /><p className="text-sm">{t('community.failed_to_load_matching_stats')}</p></div>
              <Button variant="outline" onPress={loadData} isLoading={loading}>{t('common.retry')}</Button>
            </div>
          )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label={t('community.label_matches_generated')}
          value={data.overview.total_matches_month}
          icon={Activity}
        />
        <StatCard
          label={t('community.label_avg_match_score')}
          value={`${data.overview.avg_match_score.toFixed(1)}%`}
          icon={BarChart3}
          color="default"
        />
        <StatCard
          label={t('community.label_approval_rate')}
          value={`${data.approval_rate.toFixed(0)}%`}
          icon={Activity}
          color="warning"
        />
        <StatCard
          label={t('community.label_cache_hit_rate')}
          value={data.overview.cache_hit_rate !== undefined
            ? `${data.overview.cache_hit_rate.toFixed(0)}%`
            : '---'}
          icon={Activity}
          color="default"
        />
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card >
          <CardHeader><h3 className="text-lg font-semibold">{t('community.engine_status')}</h3></CardHeader>
          <CardBody>
              <div className="space-y-3">
                <div className="flex items-center justify-between py-1 border-b border-border">
                  <span className="text-sm text-muted">{t('community.broker_approval')}</span>
                  <span className="text-sm font-medium">{data.broker_approval_enabled ? t('community.enabled') : t('community.disabled')}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-border">
                  <span className="text-sm text-muted">{t('community.pending_approvals')}</span>
                  <span className="text-sm font-medium">{data.pending_approvals}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-border">
                  <span className="text-sm text-muted">{t('community.approved_total')}</span>
                  <span className="text-sm font-medium">{data.approved_count}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-border">
                  <span className="text-sm text-muted">{t('community.rejected_total')}</span>
                  <span className="text-sm font-medium">{data.rejected_count}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-border">
                  <span className="text-sm text-muted">{t('community.matches_today')}</span>
                  <span className="text-sm font-medium">{data.overview.total_matches_today ?? '---'}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-border">
                  <span className="text-sm text-muted">{t('community.matches_this_week')}</span>
                  <span className="text-sm font-medium">{data.overview.total_matches_week ?? '---'}</span>
                </div>
                <div className="flex items-center justify-between py-1 border-b border-border">
                  <span className="text-sm text-muted">{t('community.hot_matches')}</span>
                  <span className="text-sm font-medium">{data.overview.hot_matches_count}</span>
                </div>
                <div className="flex items-center justify-between py-1">
                  <span className="text-sm text-muted">{t('community.active_matching_users')}</span>
                  <span className="text-sm font-medium">{data.overview.active_users_matching}</span>
                </div>
              </div>
          </CardBody>
        </Card>

        <Card >
          <CardHeader><h3 className="text-lg font-semibold">{t('community.score_distribution')}</h3></CardHeader>
          <CardBody>
            {Object.keys(data.score_distribution).length > 0 ? (
              <div className="space-y-3">
                {Object.entries(data.score_distribution).map(([range, count]) => (
                  <div key={range} className="flex items-center justify-between py-1 border-b border-border last:border-0">
                    <span className="text-sm">{range}</span>
                    <span className="text-sm font-medium text-accent">{count}</span>
                  </div>
                ))}
              </div>
            ) : (
              <div className="flex flex-col items-center py-8 text-muted">
                <BarChart3 size={40} className="mb-3" aria-hidden="true" />
                <p className="text-sm">{t('community.no_score_distribution')}</p>
              </div>
            )}
          </CardBody>
        </Card>
      </div>
        </>
      ) : null}
    </div>
  );
}

export default SmartMatchMonitoring;
