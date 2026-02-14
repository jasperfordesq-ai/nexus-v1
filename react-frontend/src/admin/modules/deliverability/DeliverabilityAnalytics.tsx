/**
 * Deliverability Analytics
 * Analytics and reporting for project deliverables.
 * Wired to adminDeliverability.getAnalytics() API.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Spinner } from '@heroui/react';
import { BarChart3, CheckCircle, Clock, TrendingUp } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminDeliverability } from '../../api/adminApi';
import { PageHeader, StatCard } from '../../components';

interface AnalyticsData {
  total_deliverables: number;
  completion_rate: number;
  avg_completion_days: number;
  on_time_rate: number;
  by_priority: Record<string, number>;
  by_status: Record<string, number>;
}

export function DeliverabilityAnalytics() {
  usePageTitle('Admin - Deliverability Analytics');
  const toast = useToast();

  const [data, setData] = useState<AnalyticsData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    adminDeliverability.getAnalytics()
      .then((res) => {
        if (res.success && res.data) {
          setData(res.data as AnalyticsData);
        }
      })
      .catch(() => toast.error('Failed to load analytics'))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div>
        <PageHeader title="Deliverability Analytics" description="Deliverable progress and performance reports" />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  if (!data) {
    return (
      <div>
        <PageHeader title="Deliverability Analytics" description="Deliverable progress and performance reports" />
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center justify-center py-16 text-center">
            <BarChart3 size={48} className="text-default-300 mb-3" />
            <h3 className="text-lg font-semibold text-foreground">No Analytics Data</h3>
            <p className="mt-1 max-w-md text-sm text-default-500">
              Analytics will be generated automatically as deliverables are created and tracked.
              Create deliverables from the Deliverables list to get started.
            </p>
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title="Deliverability Analytics" description="Deliverable progress and performance reports" />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard label="Total Deliverables" value={data.total_deliverables} icon={BarChart3} color="primary" />
        <StatCard label="Completion Rate" value={`${data.completion_rate ?? 0}%`} icon={CheckCircle} color="success" />
        <StatCard label="Avg Completion (days)" value={data.avg_completion_days ?? '--'} icon={Clock} color="warning" />
        <StatCard label="On-Time Rate" value={`${data.on_time_rate ?? 0}%`} icon={TrendingUp} color="secondary" />
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">By Priority</h3></CardHeader>
          <CardBody>
            {data.by_priority && Object.keys(data.by_priority).length > 0 ? (
              <div className="space-y-3">
                {Object.entries(data.by_priority).map(([priority, count]) => (
                  <div key={priority} className="flex items-center justify-between py-1 border-b border-default-100 last:border-0">
                    <span className="text-sm capitalize">{priority}</span>
                    <span className="text-sm font-medium">{count}</span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-default-400 text-center py-4">No priority data available</p>
            )}
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">By Status</h3></CardHeader>
          <CardBody>
            {data.by_status && Object.keys(data.by_status).length > 0 ? (
              <div className="space-y-3">
                {Object.entries(data.by_status).map(([status, count]) => (
                  <div key={status} className="flex items-center justify-between py-1 border-b border-default-100 last:border-0">
                    <span className="text-sm capitalize">{status.replace('_', ' ')}</span>
                    <span className="text-sm font-medium">{count}</span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-default-400 text-center py-4">No status data available</p>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default DeliverabilityAnalytics;
