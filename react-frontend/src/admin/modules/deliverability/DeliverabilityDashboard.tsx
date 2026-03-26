// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Deliverability Dashboard
 * Overview of project deliverables, milestones, and progress tracking.
 * Wired to adminDeliverability.getDashboard() API.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Spinner } from '@heroui/react';
import { Target, CheckCircle, Clock, AlertCircle } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminDeliverability } from '../../api/adminApi';
import { PageHeader, StatCard } from '../../components';

import { useTranslation } from 'react-i18next';
interface DashboardData {
  total: number;
  by_status: Record<string, number>;
  overdue: number;
  completion_rate: number;
  recent_activity: Array<{
    id: number;
    deliverable_title: string;
    action_type: string;
    user_name: string;
    action_timestamp: string;
  }>;
}

export function DeliverabilityDashboard() {
  const { t } = useTranslation('admin');
  usePageTitle(t('deliverability.page_title'));
  const toast = useToast();

  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    adminDeliverability.getDashboard()
      .then((res) => {
        if (res.success && res.data) {
          setData(res.data as DashboardData);
        }
      })
      .catch(() => toast.error(t('deliverability.failed_to_load_dashboard_data')))
      .finally(() => setLoading(false));
  }, [toast]);

  if (loading) {
    return (
      <div>
        <PageHeader title={t('deliverability.deliverability_dashboard_title')} description={t('deliverability.deliverability_dashboard_desc')} />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  const stats = data || { total: 0, by_status: {}, overdue: 0, completion_rate: 0, recent_activity: [] };
  const completed = stats.by_status?.completed ?? 0;
  const inProgress = stats.by_status?.in_progress ?? 0;

  return (
    <div>
      <PageHeader title={t('deliverability.deliverability_dashboard_title')} description={t('deliverability.deliverability_dashboard_desc')} />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard label={t('deliverability.label_total_deliverables')} value={stats.total} icon={Target} color="primary" />
        <StatCard label={t('deliverability.label_completed')} value={completed} icon={CheckCircle} color="success" />
        <StatCard label={t('deliverability.label_in_progress')} value={inProgress} icon={Clock} color="warning" />
        <StatCard label={t('deliverability.label_overdue')} value={stats.overdue} icon={AlertCircle} color="danger" />
      </div>

      <Card shadow="sm">
        <CardHeader><h3 className="text-lg font-semibold">{t('deliverability.recent_activity')}</h3></CardHeader>
        <CardBody>
          {stats.recent_activity && stats.recent_activity.length > 0 ? (
            <div className="space-y-3">
              {stats.recent_activity.map((activity) => (
                <div key={activity.id} className="flex items-start gap-3 py-2 border-b border-default-100 last:border-0">
                  <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10">
                    <Target size={14} className="text-primary" />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium">{activity.deliverable_title}</p>
                    <p className="text-xs text-default-400">
                      {activity.action_type} by {activity.user_name} -- {new Date(activity.action_timestamp).toLocaleDateString()}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="flex flex-col items-center py-8 text-default-400">
              <Target size={40} className="mb-3" />
              <p>{t('deliverability.empty_dashboard')}</p>
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

export default DeliverabilityDashboard;
