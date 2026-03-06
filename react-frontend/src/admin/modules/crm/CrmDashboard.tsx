// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CRM Dashboard
 * Main dashboard for the Member CRM module.
 * Displays key member metrics, quick actions, and recent activity summary.
 */

import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, CardHeader, Button, Chip, Spinner } from '@heroui/react';
import {
  Users,
  Activity,
  UserPlus,
  UserCheck,
  ClipboardList,
  AlertTriangle,
  StickyNote,
  TrendingUp,
  ChevronRight,
  RefreshCw,
  Download,
  Tag,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { API_BASE, tokenManager } from '@/lib/api';
import { adminCrm } from '../../api/adminApi';
import { StatCard, PageHeader } from '../../components';

interface CrmDashboardData {
  total_members: number;
  active_members: number;
  new_this_month: number;
  pending_approvals: number;
  open_tasks: number;
  overdue_tasks: number;
  total_notes: number;
  never_logged_in: number;
  retention_rate: number;
}

const QUICK_ACTIONS = [
  { label: 'Member Notes', path: '/admin/crm/notes', icon: StickyNote, color: 'text-primary bg-primary/10' },
  { label: 'CRM Tasks', path: '/admin/crm/tasks', icon: ClipboardList, color: 'text-warning bg-warning/10' },
  { label: 'Member Tags', path: '/admin/crm/tags', icon: Tag, color: 'text-secondary bg-secondary/10' },
  { label: 'Activity Timeline', path: '/admin/crm/timeline', icon: Activity, color: 'text-danger bg-danger/10' },
  { label: 'Onboarding Funnel', path: '/admin/crm/funnel', icon: TrendingUp, color: 'text-success bg-success/10' },
  { label: 'All Members', path: '/admin/users', icon: Users, color: 'text-default bg-default/10' },
] as const;

export function CrmDashboard() {
  usePageTitle('Admin - Member CRM');
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [data, setData] = useState<CrmDashboardData | null>(null);
  const [loading, setLoading] = useState(true);

  const loadDashboard = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminCrm.getDashboard();
      if (res.success && res.data) {
        setData(res.data as CrmDashboardData);
      }
    } catch {
      toast.error('Failed to load CRM dashboard data');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  const handleExport = useCallback(async (type: 'dashboard' | 'notes' | 'tasks') => {
    try {
      const url = `${API_BASE}/v2/admin/crm/export/${type}`;
      const token = tokenManager.getAccessToken();
      const res = await fetch(url, { headers: token ? { Authorization: `Bearer ${token}` } : {} });
      if (!res.ok) throw new Error('Export failed');
      const blob = await res.blob();
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = `crm-${type}-${new Date().toISOString().slice(0, 10)}.csv`;
      a.click();
      URL.revokeObjectURL(a.href);
      toast.success(`${type} exported successfully`);
    } catch {
      toast.error(`Failed to export ${type}`);
    }
  }, [toast]);

  useEffect(() => {
    loadDashboard();
  }, [loadDashboard]);

  if (loading) {
    return (
      <div className="flex min-h-[400px] items-center justify-center">
        <Spinner size="lg" label="Loading CRM dashboard..." />
      </div>
    );
  }

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      <PageHeader
        title="Member CRM"
        description="Manage member relationships, notes, and follow-ups"
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              size="sm"
              startContent={<Download size={14} />}
              onPress={() => handleExport('dashboard')}
            >
              Export Stats
            </Button>
            <Button
              variant="flat"
              size="sm"
              startContent={<Download size={14} />}
              onPress={() => handleExport('notes')}
            >
              Export Notes
            </Button>
            <Button
              variant="flat"
              size="sm"
              startContent={<Download size={14} />}
              onPress={() => handleExport('tasks')}
            >
              Export Tasks
            </Button>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadDashboard}
              isLoading={loading}
            >
              Refresh
            </Button>
          </div>
        }
      />

      {/* Stat Cards */}
      <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
        <StatCard
          label="Total Members"
          value={data?.total_members ?? 0}
          icon={Users}
          color="primary"
          loading={!data}
        />
        <StatCard
          label="Active Members"
          value={data?.active_members ?? 0}
          icon={Activity}
          color="success"
          loading={!data}
        />
        <StatCard
          label="New This Month"
          value={data?.new_this_month ?? 0}
          icon={UserPlus}
          color="secondary"
          loading={!data}
        />
        <StatCard
          label="Pending Approvals"
          value={data?.pending_approvals ?? 0}
          icon={UserCheck}
          color="warning"
          loading={!data}
        />
        <StatCard
          label="Open Tasks"
          value={data?.open_tasks ?? 0}
          icon={ClipboardList}
          color="primary"
          loading={!data}
        />
        <StatCard
          label="Overdue Tasks"
          value={data?.overdue_tasks ?? 0}
          icon={AlertTriangle}
          color={(data?.overdue_tasks ?? 0) > 0 ? 'danger' : 'default'}
          loading={!data}
        />
        <StatCard
          label="Member Notes"
          value={data?.total_notes ?? 0}
          icon={StickyNote}
          color="secondary"
          loading={!data}
        />
        <StatCard
          label="Retention Rate"
          value={data ? `${data.retention_rate}%` : '0%'}
          icon={TrendingUp}
          color="success"
          loading={!data}
        />
      </div>

      {/* Quick Actions + Activity Summary */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {/* Quick Actions */}
        <Card shadow="sm">
          <CardHeader className="flex-col items-start px-4 pb-0 pt-4">
            <h3 className="text-lg font-semibold text-foreground">Quick Actions</h3>
            <p className="text-sm text-default-500">Jump to common CRM tasks</p>
          </CardHeader>
          <CardBody className="gap-3 px-4 pb-4 pt-3">
            {QUICK_ACTIONS.map((action) => {
              const Icon = action.icon;
              return (
                <Button
                  key={action.path}
                  as={Link}
                  to={tenantPath(action.path)}
                  variant="flat"
                  className="justify-between"
                  fullWidth
                  endContent={<ChevronRight size={16} className="text-default-400" />}
                >
                  <span className="flex items-center gap-3">
                    <span className={`flex h-8 w-8 items-center justify-center rounded-lg ${action.color}`}>
                      <Icon size={16} />
                    </span>
                    <span>{action.label}</span>
                  </span>
                </Button>
              );
            })}
          </CardBody>
        </Card>

        {/* Activity Summary */}
        <Card shadow="sm">
          <CardHeader className="flex-col items-start px-4 pb-0 pt-4">
            <h3 className="text-lg font-semibold text-foreground">Activity Summary</h3>
            <p className="text-sm text-default-500">Overview of member engagement</p>
          </CardHeader>
          <CardBody className="gap-4 px-4 pb-4 pt-3">
            <div className="flex items-center justify-between rounded-lg bg-default-50 p-3">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-lg text-success bg-success/10">
                  <Activity size={20} />
                </div>
                <div>
                  <p className="text-sm font-medium text-foreground">Active in last 30 days</p>
                  <p className="text-xs text-default-500">Members who logged in recently</p>
                </div>
              </div>
              <Chip color="success" variant="flat" size="lg">
                {data?.active_members ?? 0}
              </Chip>
            </div>

            <div className="flex items-center justify-between rounded-lg bg-default-50 p-3">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-lg text-secondary bg-secondary/10">
                  <UserPlus size={20} />
                </div>
                <div>
                  <p className="text-sm font-medium text-foreground">New this month</p>
                  <p className="text-xs text-default-500">Signups in current month</p>
                </div>
              </div>
              <Chip color="secondary" variant="flat" size="lg">
                {data?.new_this_month ?? 0}
              </Chip>
            </div>

            <div className="flex items-center justify-between rounded-lg bg-default-50 p-3">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-lg text-warning bg-warning/10">
                  <AlertTriangle size={20} />
                </div>
                <div>
                  <p className="text-sm font-medium text-foreground">Never logged in</p>
                  <p className="text-xs text-default-500">Members who have not signed in</p>
                </div>
              </div>
              <Chip color="warning" variant="flat" size="lg">
                {data?.never_logged_in ?? 0}
              </Chip>
            </div>

            <div className="flex items-center justify-between rounded-lg bg-default-50 p-3">
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-lg text-primary bg-primary/10">
                  <TrendingUp size={20} />
                </div>
                <div>
                  <p className="text-sm font-medium text-foreground">Retention rate</p>
                  <p className="text-xs text-default-500">Members active vs total</p>
                </div>
              </div>
              <Chip
                color={(data?.retention_rate ?? 0) >= 50 ? 'success' : 'danger'}
                variant="flat"
                size="lg"
              >
                {data?.retention_rate ?? 0}%
              </Chip>
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default CrmDashboard;
