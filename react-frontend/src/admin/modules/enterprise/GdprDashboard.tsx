// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GDPR Dashboard
 * Overview with stats and links to GDPR sub-pages.
 */

import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, Button } from '@heroui/react';
import {
  FileWarning,
  UserCheck,
  AlertTriangle,
  ClipboardList,
  ArrowRight,
  RefreshCw,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { StatCard, PageHeader } from '../../components';
import type { GdprDashboardStats } from '../../api/types';

export function GdprDashboard() {
  usePageTitle('Admin - GDPR Dashboard');
  const { tenantPath } = useTenant();

  const [stats, setStats] = useState<GdprDashboardStats | null>(null);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getGdprDashboard();
      if (res.success && res.data) {
        setStats(res.data as unknown as GdprDashboardStats);
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

  const links = [
    { label: 'Data Requests', href: tenantPath('/admin/enterprise/gdpr/requests'), icon: FileWarning, description: 'View and process data subject requests' },
    { label: 'Consent Records', href: tenantPath('/admin/enterprise/gdpr/consents'), icon: UserCheck, description: 'Review consent records and preferences' },
    { label: 'Data Breaches', href: tenantPath('/admin/enterprise/gdpr/breaches'), icon: AlertTriangle, description: 'Track and manage data breach incidents' },
    { label: 'GDPR Audit Log', href: tenantPath('/admin/enterprise/gdpr/audit'), icon: ClipboardList, description: 'Review GDPR-related audit trail' },
  ];

  return (
    <div>
      <PageHeader
        title="GDPR Dashboard"
        description="Data protection compliance overview"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
            size="sm"
          >
            Refresh
          </Button>
        }
      />

      {/* Stats */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label="Total Requests"
          value={stats?.total_requests ?? 0}
          icon={FileWarning}
          color="primary"
          loading={loading}
        />
        <StatCard
          label="Pending Requests"
          value={stats?.pending_requests ?? 0}
          icon={FileWarning}
          color="warning"
          loading={loading}
        />
        <StatCard
          label="Consent Records"
          value={stats?.total_consents ?? 0}
          icon={UserCheck}
          color="success"
          loading={loading}
        />
        <StatCard
          label="Data Breaches"
          value={stats?.total_breaches ?? 0}
          icon={AlertTriangle}
          color="danger"
          loading={loading}
        />
      </div>

      {/* Quick Links */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {links.map((link) => (
          <Card key={link.href} shadow="sm" isPressable as={Link} to={link.href}>
            <CardBody className="flex flex-row items-center gap-4 p-4">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                <link.icon size={20} className="text-primary" />
              </div>
              <div className="flex-1 min-w-0">
                <p className="font-semibold text-foreground">{link.label}</p>
                <p className="text-sm text-default-500">{link.description}</p>
              </div>
              <ArrowRight size={16} className="text-default-400 shrink-0" />
            </CardBody>
          </Card>
        ))}
      </div>
    </div>
  );
}

export default GdprDashboard;
