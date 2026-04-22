// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Enterprise Dashboard
 * Overview with stats cards (users, roles, GDPR requests, health) and quick links.
 */

import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, Button, Spinner, Chip } from '@heroui/react';
import {
  Users,
  Shield,
  FileWarning,
  HeartPulse,
  ArrowRight,
  RefreshCw,
  Database,
  Cpu,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { StatCard, PageHeader } from '../../components';
import type { EnterpriseDashboardStats } from '../../api/types';

export function EnterpriseDashboard() {
  usePageTitle("Enterprise");
  const { tenantPath } = useTenant();

  const [stats, setStats] = useState<EnterpriseDashboardStats | null>(null);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getDashboard();
      if (res.success && res.data) {
        setStats(res.data as unknown as EnterpriseDashboardStats);
      }
    } catch {
      // Silently handle
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const healthColor = stats?.health_status === 'healthy' ? 'success' : stats?.health_status === 'degraded' ? 'warning' : 'danger';

  const quickLinks = [
    { label: "Roles & Permissions", href: tenantPath('/admin/enterprise/roles'), icon: Shield },
    { label: "GDPR Dashboard", href: tenantPath('/admin/enterprise/gdpr'), icon: FileWarning },
    { label: "System Monitoring", href: tenantPath('/admin/enterprise/monitoring'), icon: HeartPulse },
    { label: "System Configuration", href: tenantPath('/admin/enterprise/config'), icon: Shield },
    { label: "Legal Documents", href: tenantPath('/admin/legal-documents'), icon: FileWarning },
  ];

  return (
    <div>
      <PageHeader
        title={"Enterprise Dashboard"}
        description={"Overview of GDPR compliance, system health, roles, and configuration"}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
            size="sm"
          >
            {"Refresh"}
          </Button>
        }
      />

      {/* Stats Grid */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label={"Total Users"}
          value={stats?.user_count ?? '---'}
          icon={Users}
          color="primary"
          loading={loading}
        />
        <StatCard
          label={"Roles"}
          value={stats?.role_count ?? '---'}
          icon={Shield}
          color="secondary"
          loading={loading}
        />
        <StatCard
          label={"Pending GDPR"}
          value={stats?.pending_gdpr_requests ?? 0}
          icon={FileWarning}
          color="warning"
          loading={loading}
        />
        <StatCard
          label={"System Health"}
          value={stats?.health_status ?? '---'}
          icon={HeartPulse}
          color={healthColor}
          loading={loading}
        />
      </div>

      {/* System Health */}
      {stats && (
        <Card shadow="sm" className="mb-6">
          <CardBody className="p-4">
            <p className="text-sm font-semibold text-default-700 mb-3">{"System Health"}</p>
            <div className="flex flex-wrap gap-3">
              <Chip color={stats.db_connected ? 'success' : 'danger'} variant="flat" size="sm" startContent={<Database size={12} />}>
                Database {stats.db_connected ? 'Connected' : 'Disconnected'}
              </Chip>
              <Chip color={stats.redis_connected ? 'success' : 'danger'} variant="flat" size="sm" startContent={<Cpu size={12} />}>
                Redis {stats.redis_connected ? 'Connected' : 'Disconnected'}
              </Chip>
              <Chip color={stats.memory_percent > 90 ? 'danger' : stats.memory_percent > 70 ? 'warning' : 'success'} variant="flat" size="sm">
                Memory {stats.memory_percent}%
              </Chip>
              <Chip color={stats.disk_percent > 90 ? 'danger' : stats.disk_percent > 70 ? 'warning' : 'success'} variant="flat" size="sm">
                Disk {stats.disk_percent}%
              </Chip>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Quick Links */}
      <Card shadow="sm">
        <CardBody className="p-4">
          <h3 className="text-lg font-semibold text-foreground mb-4">{"Quick Links"}</h3>
          {loading ? (
            <div className="flex justify-center py-8">
              <Spinner />
            </div>
          ) : (
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {quickLinks.map((link) => (
                <Button
                  key={link.href}
                  as={Link}
                  to={link.href}
                  variant="flat"
                  className="justify-between h-auto py-3"
                  endContent={<ArrowRight size={16} />}
                  startContent={<link.icon size={18} />}
                >
                  {link.label}
                </Button>
              ))}
            </div>
          )}
        </CardBody>
      </Card>

      {/* Recent GDPR Activity */}
      {stats?.recent_gdpr_activity && stats.recent_gdpr_activity.length > 0 && (
        <Card shadow="sm" className="mt-6">
          <CardBody className="p-4">
            <p className="text-sm font-semibold text-default-700 mb-3">{"Recent GDPR Activity"}</p>
            <div className="space-y-2">
              {stats.recent_gdpr_activity.map((entry) => (
                <div key={entry.id} className="flex items-center justify-between text-sm border-b border-divider pb-2 last:border-0">
                  <div className="flex items-center gap-2">
                    <Chip size="sm" variant="flat" color="primary">{entry.action}</Chip>
                    <span className="text-default-600">{entry.entity_type}</span>
                    {entry.user_name && <span className="text-default-400">by {entry.user_name}</span>}
                  </div>
                  <span className="text-default-400 text-xs">{new Date(entry.created_at).toLocaleString()}</span>
                </div>
              ))}
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  );
}

export default EnterpriseDashboard;
