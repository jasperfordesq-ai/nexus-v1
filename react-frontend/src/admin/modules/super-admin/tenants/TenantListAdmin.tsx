// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Super Admin - Tenant List
 * View and filter all tenants with search, hub filter, and status filter
 */

import { useState } from 'react';
import { Link } from 'react-router-dom';
import {
  Button,
  Select,
  SelectItem,
  Checkbox,
  Chip
} from '@heroui/react';
import { Plus, Building2, Network } from 'lucide-react';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useApi } from '@/hooks/useApi';
import { PageHeader } from '@/admin/components/PageHeader';
import { DataTable, StatusBadge, type Column } from '@/admin/components/DataTable';
import type { SuperAdminTenant } from '@/admin/api/types';
import { tenantPath } from '@/lib/tenant-routing';

export function TenantListAdmin() {
  usePageTitle('Manage Tenants - Super Admin');

  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [hubOnly, setHubOnly] = useState(false);

  const { data: tenants, isLoading: loading } = useApi<SuperAdminTenant[]>(
    '/v2/admin/super/tenants',
    { immediate: true, deps: [search, statusFilter, hubOnly] }
  );

  const getTenantPath = (path: string) => tenantPath(path, null);

  const columns: Column<SuperAdminTenant>[] = [
    {
      key: 'name',
      label: 'Tenant',
      sortable: true,
      render: (tenant) => (
        <div className="flex items-center gap-2">
          <Building2 size={16} className="text-default-400 shrink-0" />
          <div>
            <Link
              to={getTenantPath(`/admin/super/tenants/${tenant.id}`)}
              className="font-medium text-primary hover:underline"
            >
              {tenant.name}
            </Link>
            <div className="text-xs text-default-400 flex items-center gap-2 mt-0.5">
              <span>Depth: {tenant.max_depth || 0}</span>
              {tenant.allows_subtenants && (
                <Chip size="sm" variant="flat" color="primary" className="h-5">
                  <Network size={10} className="mr-1" />
                  Hub
                </Chip>
              )}
            </div>
          </div>
        </div>
      ),
    },
    {
      key: 'slug',
      label: 'Slug',
      sortable: true,
      render: (tenant) => (
        <code className="text-xs bg-default-100 px-2 py-1 rounded">{tenant.slug}</code>
      ),
    },
    {
      key: 'domain',
      label: 'Domain',
      sortable: true,
      render: (tenant) => (
        <span className="text-sm text-default-600">{tenant.domain || '—'}</span>
      ),
    },
    {
      key: 'parent_name',
      label: 'Parent',
      render: (tenant) => (
        tenant.parent_name ? (
          <span className="text-sm">{tenant.parent_name}</span>
        ) : (
          <Chip size="sm" variant="flat" color="warning">Root</Chip>
        )
      ),
    },
    {
      key: 'user_count',
      label: 'Users',
      sortable: true,
      render: (tenant) => (
        <span className="text-sm font-medium">{tenant.user_count?.toLocaleString() || 0}</span>
      ),
    },
    {
      key: 'is_active',
      label: 'Status',
      sortable: true,
      render: (tenant) => (
        <StatusBadge status={tenant.is_active ? 'active' : 'inactive'} />
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (tenant) => (
        <div className="flex items-center gap-2">
          <Button
            as={Link}
            to={getTenantPath(`/admin/super/tenants/${tenant.id}`)}
            size="sm"
            variant="flat"
          >
            View
          </Button>
          <Button
            as={Link}
            to={getTenantPath(`/admin/super/tenants/${tenant.id}/edit`)}
            size="sm"
            variant="light"
          >
            Edit
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div className="p-6">
      <PageHeader
        title="Manage Tenants"
        description="View and manage all tenants in the platform hierarchy"
        actions={
          <Button
            as={Link}
            to={getTenantPath('/admin/super/tenants/create')}
            color="primary"
            startContent={<Plus size={16} />}
          >
            Create Tenant
          </Button>
        }
      />


      {/* Filters */}
      <div className="mb-4 flex items-center gap-3 flex-wrap">
        <Select
          label="Status"
          size="sm"
          variant="bordered"
          selectedKeys={[statusFilter]}
          onSelectionChange={(keys) => setStatusFilter(Array.from(keys)[0] as string)}
          className="w-40"
        >
          <SelectItem key="all">All</SelectItem>
          <SelectItem key="active">Active</SelectItem>
          <SelectItem key="inactive">Inactive</SelectItem>
        </Select>

        <Checkbox
          isSelected={hubOnly}
          onValueChange={setHubOnly}
          size="sm"
        >
          Hub tenants only
        </Checkbox>
      </div>

      <DataTable
        columns={columns}
        data={tenants || []}
        isLoading={loading}
        searchPlaceholder="Search by name, slug, or domain..."
        onSearch={setSearch}
      />
    </div>
  );
}

export default TenantListAdmin;
