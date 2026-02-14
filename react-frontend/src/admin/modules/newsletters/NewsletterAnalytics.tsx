/**
 * Newsletter Analytics
 * Displays key email campaign metrics and performance stats.
 */

import { useState, useCallback, useEffect } from 'react';
import { Card, CardBody, Button } from '@heroui/react';
import { BarChart3, Mail, Users, MousePointer, Eye, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminNewsletters } from '../../api/adminApi';
import { PageHeader, StatCard } from '../../components';

interface AnalyticsData {
  total_newsletters: number;
  total_sent: number;
  avg_open_rate: number;
  avg_click_rate: number;
  total_subscribers: number;
}

export function NewsletterAnalytics() {
  usePageTitle('Admin - Newsletter Analytics');
  const [data, setData] = useState<AnalyticsData | null>(null);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminNewsletters.getAnalytics();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (payload && typeof payload === 'object' && 'data' in payload) {
          setData((payload as { data: AnalyticsData }).data);
        } else {
          setData(payload as AnalyticsData);
        }
      }
    } catch {
      setData(null);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  return (
    <div>
      <PageHeader
        title="Newsletter Analytics"
        description="Email campaign performance overview"
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>Refresh</Button>}
      />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <StatCard label="Total Campaigns" value={data?.total_newsletters ?? 0} icon={Mail} color="primary" loading={loading} />
        <StatCard label="Total Sent" value={data?.total_sent ?? 0} icon={BarChart3} color="success" loading={loading} />
        <StatCard label="Avg Open Rate" value={`${data?.avg_open_rate ?? 0}%`} icon={Eye} color="warning" loading={loading} />
        <StatCard label="Avg Click Rate" value={`${data?.avg_click_rate ?? 0}%`} icon={MousePointer} color="secondary" loading={loading} />
        <StatCard label="Subscribers" value={data?.total_subscribers ?? 0} icon={Users} color="primary" loading={loading} />
      </div>

      <Card shadow="sm" className="mt-6">
        <CardBody className="flex flex-col items-center justify-center py-12 text-center">
          <BarChart3 size={48} className="text-default-300 mb-3" />
          <p className="text-default-500">Detailed campaign performance charts will appear here once newsletters are sent.</p>
        </CardBody>
      </Card>
    </div>
  );
}

export default NewsletterAnalytics;
