// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Super Admin - Tenant Detail View
 * View tenant details, sub-tenants, admins, and manage hub settings
 */

import { useState } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Divider,
  Chip,
  Switch,
} from '@heroui/react';
import {
  Edit,
  Trash2,
  Users,
  Building2,
  Network,
  Globe,
  MapPin,
  Mail,
  Phone,
  ExternalLink,
  UserPlus,
} from 'lucide-react';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useApi } from '@/hooks/useApi';
import { useToast } from '@/contexts/ToastContext';
import { adminSuper } from '@/admin/api/adminApi';
import { PageHeader } from '@/admin/components/PageHeader';
import { DataTable, StatusBadge, type Column } from '@/admin/components/DataTable';
import { ConfirmModal } from '@/admin/components/ConfirmModal';
import type { SuperAdminTenant, SuperAdminUser } from '@/admin/api/types';

export function TenantShow() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const toast = useToast();

  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [reactivateModalOpen, setReactivateModalOpen] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);

  const { data: tenant, isLoading, error, execute } = useApi<any>(
    `/v2/admin/super/tenants/${id}`,
    { immediate: true, deps: [id] }
  );

  usePageTitle(tenant?.name ? `${tenant.name} - Super Admin` : 'Tenant Details - Super Admin');

  const handleDelete = async () => {
    setActionLoading(true);
    try {
      const response = await adminSuper.deleteTenant(Number(id));
      if (response.success) {
        toast.success('Tenant deleted successfully');
        navigate(('/admin/super/tenants'));
      } else {
        toast.error(response.error || 'Failed to delete tenant');
      }
    } catch (error) {
      toast.error('An error occurred');
      console.error('Delete error:', error);
    } finally {
      setActionLoading(false);
      setDeleteModalOpen(false);
    }
  };

  const handleReactivate = async () => {
    setActionLoading(true);
    try {
      const response = await adminSuper.reactivateTenant(Number(id));
      if (response.success) {
        toast.success('Tenant reactivated successfully');
        setReactivateModalOpen(false);
        execute();
      } else {
        toast.error(response.error || 'Failed to reactivate tenant');
      }
    } catch (error) {
      toast.error('An error occurred');
      console.error('Reactivate error:', error);
    } finally {
      setActionLoading(false);
    }
  };

  const handleToggleHub = async (enable: boolean) => {
    try {
      const response = await adminSuper.toggleHub(Number(id), enable);
      if (response.success) {
        toast.success(`Hub ${enable ? 'enabled' : 'disabled'} successfully`);
        execute();
      } else {
        toast.error(response.error || 'Failed to toggle hub');
      }
    } catch (error) {
      toast.error('An error occurred');
      console.error('Toggle hub error:', error);
    }
  };

  // Sub-tenants table columns
  const subTenantColumns: Column<SuperAdminTenant>[] = [
    {
      key: 'name',
      label: 'Name',
      render: (t) => (
        <Link
          to={(`/admin/super/tenants/${t.id}`)}
          className="text-primary hover:underline font-medium"
        >
          {t.name}
        </Link>
      ),
    },
    {
      key: 'slug',
      label: 'Slug',
      render: (t) => <code className="text-xs bg-default-100 px-2 py-1 rounded">{t.slug}</code>,
    },
    {
      key: 'user_count',
      label: 'Users',
      render: (t) => <span>{t.user_count?.toLocaleString() || 0}</span>,
    },
    {
      key: 'is_active',
      label: 'Status',
      render: (t) => <StatusBadge status={t.is_active ? 'active' : 'inactive'} />,
    },
  ];

  // Admins table columns
  const adminColumns: Column<SuperAdminUser>[] = [
    {
      key: 'name',
      label: 'Name',
      render: (u) => (
        <div>
          <div className="font-medium">{u.name}</div>
          <div className="text-xs text-default-400">{u.email}</div>
        </div>
      ),
    },
    {
      key: 'role',
      label: 'Role',
      render: (u) => <Chip size="sm" variant="flat">{u.role}</Chip>,
    },
    {
      key: 'is_super_admin',
      label: 'Super Admin',
      render: (u) => (
        u.is_super_admin || u.is_tenant_super_admin ? (
          <Chip size="sm" color="warning">Yes</Chip>
        ) : (
          <span className="text-sm text-default-400">No</span>
        )
      ),
    },
  ];

  if (isLoading) {
    return (
      <div className="p-6 flex items-center justify-center h-64">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4" />
          <p className="text-default-500">Loading tenant...</p>
        </div>
      </div>
    );
  }

  if (error || !tenant) {
    return (
      <div className="p-6">
        <div className="p-4 bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800 rounded-lg">
          <p className="text-danger-700 dark:text-danger-400">{error || 'Tenant not found'}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="p-6">
      {/* Breadcrumbs */}
      {tenant.breadcrumb && tenant.breadcrumb.length > 0 && (
        <nav className="mb-4 flex items-center gap-2 text-sm text-default-500">
          {tenant.breadcrumb.map((crumb: any, idx: number) => (
            <span key={crumb.id} className="flex items-center gap-2">
              {idx > 0 && <span>/</span>}
              <Link
                to={`/admin/super/tenants/${crumb.id}`}
                className="hover:text-primary"
              >
                {crumb.name}
              </Link>
            </span>
          ))}
        </nav>
      )}

      <PageHeader
        title={tenant.name}
        description={tenant.tagline || undefined}
        actions={
          <div className="flex items-center gap-2">
            {!tenant.is_active && (
              <Button
                color="success"
                variant="flat"
                onPress={() => setReactivateModalOpen(true)}
              >
                Reactivate
              </Button>
            )}
            <Button
              as={Link}
              to={(`/admin/super/tenants/${tenant.id}/edit`)}
              color="primary"
              startContent={<Edit size={16} />}
            >
              Edit
            </Button>
          </div>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Column */}
        <div className="lg:col-span-2 space-y-6">
          {/* Basic Info */}
          <Card>
            <CardHeader>
              <div className="flex items-center gap-2">
                <Building2 size={18} />
                <span className="font-semibold">Information</span>
              </div>
            </CardHeader>
            <Divider />
            <CardBody className="gap-3">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-xs text-default-400 mb-1">Slug</p>
                  <code className="text-sm bg-default-100 px-2 py-1 rounded">{tenant.slug}</code>
                </div>
                <div>
                  <p className="text-xs text-default-400 mb-1">Domain</p>
                  <p className="text-sm">{tenant.domain || '—'}</p>
                </div>
                <div>
                  <p className="text-xs text-default-400 mb-1">Status</p>
                  <StatusBadge status={tenant.is_active ? 'active' : 'inactive'} />
                </div>
                <div>
                  <p className="text-xs text-default-400 mb-1">Hub</p>
                  <Chip size="sm" color={tenant.allows_subtenants ? 'primary' : 'default'}>
                    {tenant.allows_subtenants ? 'Yes' : 'No'}
                  </Chip>
                </div>
                <div>
                  <p className="text-xs text-default-400 mb-1">Max Depth</p>
                  <p className="text-sm">{tenant.max_depth}</p>
                </div>
                <div>
                  <p className="text-xs text-default-400 mb-1">Users</p>
                  <p className="text-sm font-medium">{tenant.user_count?.toLocaleString() || 0}</p>
                </div>
              </div>
              {tenant.description && (
                <>
                  <Divider className="my-2" />
                  <div>
                    <p className="text-xs text-default-400 mb-1">Description</p>
                    <p className="text-sm text-default-600">{tenant.description}</p>
                  </div>
                </>
              )}
            </CardBody>
          </Card>

          {/* Contact & Location */}
          {(tenant.contact_email || tenant.contact_phone || tenant.address || tenant.location_name) && (
            <Card>
              <CardHeader>
                <div className="flex items-center gap-2">
                  <MapPin size={18} />
                  <span className="font-semibold">Contact & Location</span>
                </div>
              </CardHeader>
              <Divider />
              <CardBody className="gap-3">
                {tenant.contact_email && (
                  <div className="flex items-center gap-2">
                    <Mail size={16} className="text-default-400" />
                    <a href={`mailto:${tenant.contact_email}`} className="text-sm text-primary hover:underline">
                      {tenant.contact_email}
                    </a>
                  </div>
                )}
                {tenant.contact_phone && (
                  <div className="flex items-center gap-2">
                    <Phone size={16} className="text-default-400" />
                    <a href={`tel:${tenant.contact_phone}`} className="text-sm">
                      {tenant.contact_phone}
                    </a>
                  </div>
                )}
                {tenant.address && (
                  <div className="flex items-start gap-2">
                    <MapPin size={16} className="text-default-400 mt-0.5" />
                    <p className="text-sm">{tenant.address}</p>
                  </div>
                )}
                {tenant.location_name && (
                  <div>
                    <p className="text-xs text-default-400 mb-1">Location</p>
                    <p className="text-sm">{tenant.location_name}</p>
                  </div>
                )}
              </CardBody>
            </Card>
          )}

          {/* Social Media */}
          {(tenant.social_facebook || tenant.social_twitter || tenant.social_instagram || tenant.social_linkedin || tenant.social_youtube) && (
            <Card>
              <CardHeader>
                <div className="flex items-center gap-2">
                  <Globe size={18} />
                  <span className="font-semibold">Social Media</span>
                </div>
              </CardHeader>
              <Divider />
              <CardBody className="gap-2">
                {tenant.social_facebook && (
                  <a href={tenant.social_facebook} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 text-sm text-primary hover:underline">
                    <ExternalLink size={14} />
                    Facebook
                  </a>
                )}
                {tenant.social_twitter && (
                  <a href={tenant.social_twitter} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 text-sm text-primary hover:underline">
                    <ExternalLink size={14} />
                    Twitter
                  </a>
                )}
                {tenant.social_instagram && (
                  <a href={tenant.social_instagram} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 text-sm text-primary hover:underline">
                    <ExternalLink size={14} />
                    Instagram
                  </a>
                )}
                {tenant.social_linkedin && (
                  <a href={tenant.social_linkedin} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 text-sm text-primary hover:underline">
                    <ExternalLink size={14} />
                    LinkedIn
                  </a>
                )}
                {tenant.social_youtube && (
                  <a href={tenant.social_youtube} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 text-sm text-primary hover:underline">
                    <ExternalLink size={14} />
                    YouTube
                  </a>
                )}
              </CardBody>
            </Card>
          )}

          {/* Sub-Tenants */}
          {tenant.children && tenant.children.length > 0 && (
            <Card>
              <CardHeader>
                <div className="flex items-center gap-2">
                  <Network size={18} />
                  <span className="font-semibold">Sub-Tenants ({tenant.children.length})</span>
                </div>
              </CardHeader>
              <Divider />
              <CardBody>
                <DataTable
                  columns={subTenantColumns}
                  data={tenant.children}
                  searchable={false}
                />
              </CardBody>
            </Card>
          )}

          {/* Admins */}
          {tenant.admins && tenant.admins.length > 0 && (
            <Card>
              <CardHeader>
                <div className="flex items-center gap-2">
                  <Users size={18} />
                  <span className="font-semibold">Administrators ({tenant.admins.length})</span>
                </div>
              </CardHeader>
              <Divider />
              <CardBody>
                <DataTable
                  columns={adminColumns}
                  data={tenant.admins}
                  searchable={false}
                />
              </CardBody>
            </Card>
          )}
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Quick Actions */}
          <Card>
            <CardHeader>
              <span className="font-semibold">Quick Actions</span>
            </CardHeader>
            <Divider />
            <CardBody className="gap-2">
              <Button
                as={Link}
                to={(`/admin/super/users/create?tenant_id=${tenant.id}`)}
                variant="flat"
                startContent={<UserPlus size={16} />}
                className="w-full justify-start"
              >
                Add Administrator
              </Button>
              <Button
                as={Link}
                to={(`/admin/super/tenants/create?parent_id=${tenant.id}`)}
                variant="flat"
                startContent={<Building2 size={16} />}
                className="w-full justify-start"
                isDisabled={!tenant.allows_subtenants}
              >
                Create Sub-Tenant
              </Button>
            </CardBody>
          </Card>

          {/* Hub Settings */}
          <Card>
            <CardHeader>
              <div className="flex items-center gap-2">
                <Network size={18} />
                <span className="font-semibold">Hub Settings</span>
              </div>
            </CardHeader>
            <Divider />
            <CardBody>
              <Switch
                isSelected={tenant.allows_subtenants}
                onValueChange={handleToggleHub}
              >
                Allow sub-tenants (Hub)
              </Switch>
              {tenant.allows_subtenants && (
                <p className="text-xs text-default-500 mt-2">
                  This tenant can have sub-tenants up to depth {tenant.max_depth || 'unlimited'}.
                </p>
              )}
            </CardBody>
          </Card>

          {/* Danger Zone */}
          <Card className="border-danger-200 dark:border-danger-800">
            <CardHeader>
              <div className="flex items-center gap-2 text-danger">
                <Trash2 size={18} />
                <span className="font-semibold">Danger Zone</span>
              </div>
            </CardHeader>
            <Divider />
            <CardBody>
              <Button
                color="danger"
                variant="flat"
                onPress={() => setDeleteModalOpen(true)}
                className="w-full"
              >
                Delete Tenant
              </Button>
            </CardBody>
          </Card>
        </div>
      </div>

      {/* Delete Confirmation */}
      <ConfirmModal
        isOpen={deleteModalOpen}
        onClose={() => setDeleteModalOpen(false)}
        onConfirm={handleDelete}
        title="Delete Tenant"
        message={`Are you sure you want to delete "${tenant.name}"? This will soft-delete the tenant. It can be reactivated later.`}
        confirmLabel="Delete"
        confirmColor="danger"
        isLoading={actionLoading}
      />

      {/* Reactivate Confirmation */}
      <ConfirmModal
        isOpen={reactivateModalOpen}
        onClose={() => setReactivateModalOpen(false)}
        onConfirm={handleReactivate}
        title="Reactivate Tenant"
        message={`Are you sure you want to reactivate "${tenant.name}"?`}
        confirmLabel="Reactivate"
        confirmColor="primary"
        isLoading={actionLoading}
      />
    </div>
  );
}

export default TenantShow;
