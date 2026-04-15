// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Group Analytics
 * Aggregate stats for community groups: totals, averages, most active.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Spinner } from '@heroui/react';
import { Users, UserCheck, BarChart3, Clock, ShieldCheck } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminGroups } from '../../api/adminApi';
import { PageHeader, StatCard } from '../../components';
import type { GroupAnalyticsData } from '../../api/types';

import { useTranslation } from 'react-i18next';
export function GroupAnalytics() {
  const { t } = useTranslation('admin');
  usePageTitle(t('groups.page_title'));
  const toast = useToast();

  const [data, setData] = useState<GroupAnalyticsData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      try {
        const res = await adminGroups.getAnalytics();
        if (res.success && res.data) {
          setData(res.data as GroupAnalyticsData);
        }
      } catch {
        toast.error(t('groups.failed_to_load_group_analytics'));
      } finally {
        setLoading(false);
      }
    };
    load();
  }, [toast, t])

  if (loading) {
    return (
      <div>
        <PageHeader title={t('groups.group_analytics_title')} description={t('groups.group_analytics_desc')} />
        <div className="flex items-center justify-center py-20">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  if (!data) {
    return (
      <div>
        <PageHeader title={t('groups.group_analytics_title')} description={t('groups.group_analytics_desc')} />
        <Card>
          <CardBody className="py-10 text-center text-default-500">
            {t('groups.no_analytics_data')}
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title={t('groups.group_analytics_title')} description={t('groups.group_analytics_desc')} />

      {/* Stat cards grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <StatCard
          label={t('groups.label_total_groups')}
          value={data.total_groups}
          icon={Users}
          color="primary"
        />
        <StatCard
          label={t('groups.label_total_members')}
          value={data.total_members}
          icon={UserCheck}
          color="success"
        />
        <StatCard
          label={t('groups.label_avg_members_group')}
          value={data.avg_members_per_group}
          icon={BarChart3}
          color="secondary"
        />
        <StatCard
          label={t('groups.label_active_groups')}
          value={data.active_groups}
          icon={ShieldCheck}
          color="warning"
        />
      </div>

      {/* Pending approvals callout */}
      {data.pending_approvals > 0 && (
        <Card className="mb-6 border-warning/30 bg-warning/5">
          <CardBody className="flex flex-row items-center gap-3 py-3">
            <Clock size={20} className="text-warning shrink-0" />
            <p className="text-sm text-foreground">
              {t('groups.pending_approvals_message', { count: data.pending_approvals })}
            </p>
          </CardBody>
        </Card>
      )}

      {/* Most active groups */}
      <Card>
        <CardHeader className="pb-2">
          <h3 className="text-lg font-semibold text-foreground">{t('groups.most_active_groups')}</h3>
        </CardHeader>
        <CardBody>
          {data.most_active_groups.length === 0 ? (
            <p className="text-sm text-default-500 py-4 text-center">{t('groups.no_groups_found')}</p>
          ) : (
            <div className="divide-y divide-default-100">
              {data.most_active_groups.map((group, index) => (
                <div key={group.id} className="flex items-center justify-between py-3">
                  <div className="flex items-center gap-3">
                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-sm font-bold text-primary">
                      {index + 1}
                    </span>
                    <span className="font-medium text-foreground">{group.name}</span>
                  </div>
                  <div className="flex items-center gap-1.5 text-sm text-default-500">
                    <Users size={14} />
                    <span>{t('groups.member_count', { count: group.member_count })}</span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

export default GroupAnalytics;
