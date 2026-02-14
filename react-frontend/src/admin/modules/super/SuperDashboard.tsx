/**
 * Super Admin Dashboard
 * Platform-wide overview with tenant stats, tenant cards, and quick actions.
 */

import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, Button, Chip, Spinner } from '@heroui/react';
import {
  Building2,
  Users,
  Shield,
  Globe,
  Plus,
  ArrowRight,
  RefreshCw,
  Network,
  Activity,
  ListChecks,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import { StatCard, PageHeader } from '../../components';
import type { SuperAdminDashboardStats, SuperAdminTenant } from '../../api/types';

export function SuperDashboard() {
  usePageTitle('Super Admin - Dashboard');
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [stats, setStats] = useState<SuperAdminDashboardStats | null>(null);
  const [tenants, setTenants] = useState<SuperAdminTenant[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [dashRes, tenantsRes] = await Promise.all([
        adminSuper.getDashboard(),
        adminSuper.listTenants(),
      ]);

      if (dashRes.success && dashRes.data) {
        setStats(dashRes.data);
      }

      if (tenantsRes.success && tenantsRes.data) {
        setTenants(Array.isArray(tenantsRes.data) ? tenantsRes.data : []);
      }
    } catch {
      toast.error('Failed to load dashboard data');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const quickActions = [
    { label: 'Create Tenant', href: tenantPath('/admin/super/tenants/create'), icon: Plus },
    { label: 'View Hierarchy', href: tenantPath('/admin/super/tenants/hierarchy'), icon: Network },
    { label: 'Bulk Operations', href: tenantPath('/admin/super/bulk'), icon: ListChecks },
    { label: 'Cross-Tenant Users', href: tenantPath('/admin/super/users'), icon: Users },
    { label: 'Audit Log', href: tenantPath('/admin/super/audit'), icon: Activity },
    { label: 'Federation Controls', href: tenantPath('/admin/super/federation'), icon: Globe },
  ];

  return (
    <div>
      <PageHeader
        title="Super Admin Dashboard"
        description="Platform-wide overview and management"
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

      {/* Stats Grid */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label="Total Tenants"
          value={stats?.total_tenants ?? '---'}
          icon={Building2}
          color="primary"
          loading={loading}
        />
        <StatCard
          label="Active Tenants"
          value={stats?.active_tenants ?? '---'}
          icon={Shield}
          color="success"
          loading={loading}
        />
        <StatCard
          label="Total Users"
          value={stats?.total_users ?? '---'}
          icon={Users}
          color="secondary"
          loading={loading}
        />
        <StatCard
          label="Total Listings"
          value={stats?.total_listings ?? '---'}
          icon={Globe}
          color="warning"
          loading={loading}
        />
      </div>

      {/* Quick Actions */}
      <Card shadow="sm" className="mb-6">
        <CardBody className="p-4">
          <h3 className="text-lg font-semibold text-foreground mb-4">Quick Actions</h3>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {quickActions.map((action) => (
              <Button
                key={action.href}
                as={Link}
                to={action.href}
                variant="flat"
                className="justify-between h-auto py-3"
                endContent={<ArrowRight size={16} />}
                startContent={<action.icon size={18} />}
              >
                {action.label}
              </Button>
            ))}
          </div>
        </CardBody>
      </Card>

      {/* Tenant Cards */}
      <h3 className="text-lg font-semibold text-foreground mb-4">Tenants</h3>
      {loading ? (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      ) : tenants.length === 0 ? (
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center py-12 text-default-400">
            <Building2 size={40} className="mb-2" />
            <p>No tenants found.</p>
          </CardBody>
        </Card>
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {tenants.map((tenant) => (
            <Card
              key={tenant.id}
              shadow="sm"
              isPressable
              as={Link}
              to={tenantPath(`/admin/super/tenants/${tenant.id}/edit`)}
            >
              <CardBody className="p-4">
                <div className="flex items-start justify-between mb-2">
                  <div className="min-w-0 flex-1">
                    <p className="font-semibold text-foreground truncate">{tenant.name}</p>
                    <p className="text-xs text-default-400 truncate">{tenant.domain || tenant.slug}</p>
                  </div>
                  <Chip
                    size="sm"
                    variant="flat"
                    color={tenant.is_active ? 'success' : 'default'}
                  >
                    {tenant.is_active ? 'Active' : 'Inactive'}
                  </Chip>
                </div>
                <div className="flex items-center gap-4 text-sm text-default-500">
                  <span className="flex items-center gap-1">
                    <Users size={14} />
                    {tenant.user_count ?? 0} users
                  </span>
                  {tenant.allows_subtenants && (
                    <Chip size="sm" variant="flat" color="secondary">Hub</Chip>
                  )}
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}

export default SuperDashboard;
