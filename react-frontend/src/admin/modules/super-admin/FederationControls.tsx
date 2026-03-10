// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, CardHeader, Button, Chip, Table, TableHeader, TableColumn, TableBody, TableRow, TableCell } from '@heroui/react';
import {
  Shield,
  Lock,
  Users,
  CheckCircle,
  Clock,
  AlertTriangle,
  Activity,
  BarChart3,
  Settings,
  FileText,
  TrendingUp
} from 'lucide-react';
import PageHeader from '../../components/PageHeader';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast, useTenant } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import type { FederationStatusOverview } from '../../api/types';

interface FederationStats {
  system: {
    enabled: boolean;
    whitelist_mode: boolean;
    lockdown_active: boolean;
    lockdown_reason?: string;
    lockdown_by?: string;
  };
  counts: {
    whitelisted_tenants: number;
    active_partnerships: number;
    pending_partnerships: number;
  };
  features: {
    profiles: boolean;
    messaging: boolean;
    transactions: boolean;
    listings: boolean;
    events: boolean;
    groups: boolean;
  };
  recent_partnerships: Array<{
    id: number;
    tenant_a_name: string;
    tenant_b_name: string;
    level: number;
    status: string;
    created_at: string;
  }>;
  whitelisted_tenants: Array<{
    id: number;
    name: string;
    domain: string;
    approved_at: string;
  }>;
  critical_events: Array<{
    action: string;
    actor: string;
    timestamp: string;
  }>;
  recent_activity: Array<{
    action: string;
    description: string;
    timestamp: string;
  }>;
  analytics: {
    total_transactions: number;
    total_messages: number;
    active_partnerships_30d: number;
  };
}

function mapOverviewToStats(overview: FederationStatusOverview): FederationStats {
  const sc = overview.system_controls;
  const ps = overview.partnership_stats;
  return {
    system: {
      enabled: sc.federation_enabled,
      whitelist_mode: sc.whitelist_mode_enabled,
      lockdown_active: sc.is_locked_down,
      lockdown_reason: sc.lockdown_reason,
    },
    counts: {
      whitelisted_tenants: overview.whitelisted_count,
      active_partnerships: ps.active,
      pending_partnerships: ps.pending,
    },
    features: {
      profiles: sc.cross_tenant_profiles_enabled,
      messaging: sc.cross_tenant_messaging_enabled,
      transactions: sc.cross_tenant_transactions_enabled,
      listings: sc.cross_tenant_listings_enabled,
      events: sc.cross_tenant_events_enabled,
      groups: sc.cross_tenant_groups_enabled,
    },
    recent_partnerships: [],
    whitelisted_tenants: [],
    critical_events: overview.recent_audit
      .filter(e => e.action_type?.includes('lockdown') || e.action_type?.includes('emergency'))
      .slice(0, 5)
      .map(e => ({ action: e.action_type || '', actor: e.actor_name || 'System', timestamp: e.created_at })),
    recent_activity: overview.recent_audit.slice(0, 5).map(e => ({
      action: e.action_type || '',
      description: e.description || '',
      timestamp: e.created_at,
    })),
    analytics: {
      total_transactions: 0,
      total_messages: 0,
      active_partnerships_30d: ps.active,
    },
  };
}

export default function FederationControls() {
  usePageTitle('Federation Controls');
  const toast = useToast();
  const { tenantPath } = useTenant();
  const [stats, setStats] = useState<FederationStats | null>(null);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    const res = await adminSuper.getFederationStatus();
    if (res.success && res.data) {
      setStats(mapOverviewToStats(res.data));
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const handleLiftLockdown = async () => {
    const res = await adminSuper.liftLockdown();
    if (res.success) {
      toast.success('Emergency lockdown lifted');
      loadData();
    } else {
      toast.error(res.error || 'Failed to lift lockdown');
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Federation Controls"
        description="Master controls for cross-community federation"
      />

      {stats?.system.lockdown_active && (
        <Card className="border-2 border-danger bg-danger-50 dark:bg-danger-950">
          <CardBody className="flex flex-row items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <Lock className="w-6 h-6 text-danger" />
              <div>
                <p className="font-semibold text-danger">Emergency Lockdown Active</p>
                <p className="text-sm text-danger-600 dark:text-danger-400">
                  {stats.system.lockdown_reason || 'Federation temporarily disabled'}
                </p>
                {stats.system.lockdown_by && (
                  <p className="text-xs text-danger-500 mt-1">
                    Initiated by: {stats.system.lockdown_by}
                  </p>
                )}
              </div>
            </div>
            <Button
              color="danger"
              variant="solid"
              onPress={handleLiftLockdown}
              startContent={<CheckCircle className="w-4 h-4" />}
            >
              Lift Lockdown
            </Button>
          </CardBody>
        </Card>
      )}

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className={`p-2 rounded-lg ${stats?.system.enabled ? 'bg-success-100 dark:bg-success-900' : 'bg-default-100'}`}>
                <Shield className={`w-5 h-5 ${stats?.system.enabled ? 'text-success' : 'text-default-500'}`} />
              </div>
              <div>
                <p className="text-xs text-default-500">System Status</p>
                <p className="text-lg font-semibold">
                  {stats?.system.enabled ? 'ON' : 'OFF'}
                </p>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-primary-100 dark:bg-primary-900">
                <Users className="w-5 h-5 text-primary" />
              </div>
              <div>
                <p className="text-xs text-default-500">Whitelisted</p>
                <p className="text-lg font-semibold">{stats?.counts.whitelisted_tenants || 0}</p>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-success-100 dark:bg-success-900">
                <CheckCircle className="w-5 h-5 text-success" />
              </div>
              <div>
                <p className="text-xs text-default-500">Active Partnerships</p>
                <p className="text-lg font-semibold">{stats?.counts.active_partnerships || 0}</p>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-warning-100 dark:bg-warning-900">
                <Clock className="w-5 h-5 text-warning" />
              </div>
              <div>
                <p className="text-xs text-default-500">Pending</p>
                <p className="text-lg font-semibold">{stats?.counts.pending_partnerships || 0}</p>
              </div>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Global Feature Status */}
      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">Global Feature Status</h3>
        </CardHeader>
        <CardBody>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            {[
              { key: 'profiles', label: 'Profiles' },
              { key: 'messaging', label: 'Messaging' },
              { key: 'transactions', label: 'Transactions' },
              { key: 'listings', label: 'Listings' },
              { key: 'events', label: 'Events' },
              { key: 'groups', label: 'Groups' },
            ].map(feature => (
              <div key={feature.key} className="text-center">
                <p className="text-sm text-default-600 mb-2">{feature.label}</p>
                <Chip
                  color={stats?.features[feature.key as keyof typeof stats.features] ? 'success' : 'default'}
                  variant="flat"
                  size="sm"
                >
                  {stats?.features[feature.key as keyof typeof stats.features] ? 'ON' : 'OFF'}
                </Chip>
              </div>
            ))}
          </div>
        </CardBody>
      </Card>

      {/* Whitelisted Tenants */}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <h3 className="text-lg font-semibold">Whitelisted Tenants</h3>
          <Button
            as={Link}
            to={tenantPath('/admin/super/federation/whitelist')}
            size="sm"
            color="primary"
            variant="flat"
          >
            Manage
          </Button>
        </CardHeader>
        <CardBody>
          <Table aria-label="Whitelisted tenants" shadow="sm" isStriped>
            <TableHeader>
              <TableColumn>Tenant</TableColumn>
              <TableColumn>Domain</TableColumn>
              <TableColumn>Approved</TableColumn>
            </TableHeader>
            <TableBody emptyContent="No whitelisted tenants">
              {(stats?.whitelisted_tenants ?? []).slice(0, 5).map(tenant => (
                <TableRow key={tenant.id}>
                  <TableCell>{tenant.name}</TableCell>
                  <TableCell className="text-sm text-default-600">{tenant.domain}</TableCell>
                  <TableCell className="text-sm text-default-600">
                    {new Date(tenant.approved_at).toLocaleDateString()}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardBody>
      </Card>

      {/* Recent Partnerships */}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <h3 className="text-lg font-semibold">Recent Partnerships</h3>
          <Button
            as={Link}
            to={tenantPath('/admin/super/federation/partnerships')}
            size="sm"
            color="primary"
            variant="flat"
          >
            View All
          </Button>
        </CardHeader>
        <CardBody>
          <Table aria-label="Recent partnerships" shadow="sm" isStriped>
            <TableHeader>
              <TableColumn>Partnership</TableColumn>
              <TableColumn>Level</TableColumn>
              <TableColumn>Status</TableColumn>
              <TableColumn>Created</TableColumn>
            </TableHeader>
            <TableBody emptyContent="No recent partnerships">
              {(stats?.recent_partnerships ?? []).slice(0, 5).map(partnership => (
                <TableRow key={partnership.id}>
                  <TableCell>
                    {partnership.tenant_a_name} ↔ {partnership.tenant_b_name}
                  </TableCell>
                  <TableCell>
                    <Chip size="sm" color="primary" variant="flat">
                      L{partnership.level}
                    </Chip>
                  </TableCell>
                  <TableCell>
                    <Chip
                      size="sm"
                      color={partnership.status === 'active' ? 'success' : 'warning'}
                      variant="flat"
                    >
                      {partnership.status}
                    </Chip>
                  </TableCell>
                  <TableCell className="text-sm text-default-600">
                    {new Date(partnership.created_at).toLocaleDateString()}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardBody>
      </Card>

      {/* Bottom Row: Critical Events, Recent Activity, Analytics */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Critical Events */}
        <Card>
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <AlertTriangle className="w-5 h-5 text-danger" />
              Critical Events
            </h3>
          </CardHeader>
          <CardBody>
            {stats?.critical_events && stats.critical_events.length > 0 ? (
              <div className="space-y-3">
                {stats.critical_events.map((event, idx) => (
                  <div key={idx} className="flex items-start gap-2">
                    <div className="w-2 h-2 rounded-full bg-danger mt-1.5"></div>
                    <div className="flex-1">
                      <p className="text-sm font-medium">{event.action}</p>
                      <p className="text-xs text-default-500">{event.actor}</p>
                      <p className="text-xs text-default-400">
                        {new Date(event.timestamp).toLocaleString()}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-default-500 text-center py-4">No critical events</p>
            )}
          </CardBody>
        </Card>

        {/* Recent Activity */}
        <Card>
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Activity className="w-5 h-5 text-primary" />
              Recent Activity
            </h3>
          </CardHeader>
          <CardBody>
            {stats?.recent_activity && stats.recent_activity.length > 0 ? (
              <div className="space-y-3">
                {stats.recent_activity.map((activity, idx) => (
                  <div key={idx} className="flex items-start gap-2">
                    <div className="w-2 h-2 rounded-full bg-primary mt-1.5"></div>
                    <div className="flex-1">
                      <p className="text-sm font-medium">{activity.action}</p>
                      <p className="text-xs text-default-500">{activity.description}</p>
                      <p className="text-xs text-default-400">
                        {new Date(activity.timestamp).toLocaleString()}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-default-500 text-center py-4">No recent activity</p>
            )}
          </CardBody>
        </Card>

        {/* Analytics (30d) */}
        <Card>
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <BarChart3 className="w-5 h-5 text-success" />
              Analytics (30d)
            </h3>
          </CardHeader>
          <CardBody>
            <div className="space-y-4">
              <div>
                <p className="text-sm text-default-600">Total Transactions</p>
                <p className="text-2xl font-bold text-success">
                  {stats?.analytics.total_transactions || 0}
                </p>
              </div>
              <div>
                <p className="text-sm text-default-600">Total Messages</p>
                <p className="text-2xl font-bold text-primary">
                  {stats?.analytics.total_messages || 0}
                </p>
              </div>
              <div>
                <p className="text-sm text-default-600">Active Partnerships</p>
                <p className="text-2xl font-bold text-warning">
                  {stats?.analytics.active_partnerships_30d || 0}
                </p>
              </div>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Quick Navigation */}
      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">Quick Navigation</h3>
        </CardHeader>
        <CardBody>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <Button
              as={Link}
              to={tenantPath('/admin/super/federation/system-controls')}
              className="h-auto py-6"
              variant="flat"
              color="primary"
            >
              <div className="flex flex-col items-center gap-2">
                <Settings className="w-6 h-6" />
                <span className="text-sm font-medium">System Controls</span>
              </div>
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/super/federation/whitelist')}
              className="h-auto py-6"
              variant="flat"
              color="primary"
            >
              <div className="flex flex-col items-center gap-2">
                <Shield className="w-6 h-6" />
                <span className="text-sm font-medium">Whitelist</span>
              </div>
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/super/federation/partnerships')}
              className="h-auto py-6"
              variant="flat"
              color="primary"
            >
              <div className="flex flex-col items-center gap-2">
                <TrendingUp className="w-6 h-6" />
                <span className="text-sm font-medium">Partnerships</span>
              </div>
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/super/federation/audit')}
              className="h-auto py-6"
              variant="flat"
              color="primary"
            >
              <div className="flex flex-col items-center gap-2">
                <FileText className="w-6 h-6" />
                <span className="text-sm font-medium">Audit Log</span>
              </div>
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
