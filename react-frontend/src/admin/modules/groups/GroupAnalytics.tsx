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

export function GroupAnalytics() {
  usePageTitle('Admin - Group Analytics');
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
        toast.error('Failed to load group analytics');
      } finally {
        setLoading(false);
      }
    };
    load();
  }, []);

  if (loading) {
    return (
      <div>
        <PageHeader title="Group Analytics" description="Aggregate statistics for community groups" />
        <div className="flex items-center justify-center py-20">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  if (!data) {
    return (
      <div>
        <PageHeader title="Group Analytics" description="Aggregate statistics for community groups" />
        <Card>
          <CardBody className="py-10 text-center text-default-500">
            No analytics data available.
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title="Group Analytics" description="Aggregate statistics for community groups" />

      {/* Stat cards grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <StatCard
          label="Total Groups"
          value={data.total_groups}
          icon={Users}
          color="primary"
        />
        <StatCard
          label="Total Members"
          value={data.total_members}
          icon={UserCheck}
          color="success"
        />
        <StatCard
          label="Avg Members / Group"
          value={data.avg_members_per_group}
          icon={BarChart3}
          color="secondary"
        />
        <StatCard
          label="Active Groups"
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
              <span className="font-semibold">{data.pending_approvals}</span> pending membership
              {data.pending_approvals === 1 ? ' request' : ' requests'} awaiting review.
            </p>
          </CardBody>
        </Card>
      )}

      {/* Most active groups */}
      <Card>
        <CardHeader className="pb-2">
          <h3 className="text-lg font-semibold text-foreground">Most Active Groups</h3>
        </CardHeader>
        <CardBody>
          {data.most_active_groups.length === 0 ? (
            <p className="text-sm text-default-500 py-4 text-center">No groups found.</p>
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
                    <span>{group.member_count} members</span>
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
