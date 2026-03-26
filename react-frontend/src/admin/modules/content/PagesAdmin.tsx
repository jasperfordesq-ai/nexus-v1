// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Pages Admin
 * Manage CMS pages for the platform.
 * Wired to adminPages API for full CRUD.
 */

import { useState, useEffect, useCallback } from 'react';
import { Button, Chip, Spinner } from '@heroui/react';
import { FileText, Plus, Pencil, Trash2 } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminPages } from '../../api/adminApi';
import { PageHeader, DataTable, StatusBadge, EmptyState, ConfirmModal, type Column } from '../../components';

import { useTranslation } from 'react-i18next';
interface PageItem {
  id: number;
  title: string;
  slug: string;
  status: string;
  sort_order: number;
  show_in_menu: number;
  menu_location: string;
  created_at: string;
}

export function PagesAdmin() {
  const { t } = useTranslation('admin');
  usePageTitle(t('content.page_title'));
  const { tenantPath, refreshTenant } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [data, setData] = useState<PageItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [confirmDelete, setConfirmDelete] = useState<PageItem | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminPages.list();
      if (res.success && res.data) {
        const result = res.data as unknown;
        if (Array.isArray(result)) {
          setData(result);
        } else if (result && typeof result === 'object') {
          const pd = result as { data?: PageItem[] };
          setData(pd.data || []);
        }
      }
    } catch {
      toast.error(t('content.failed_to_load_pages'));
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);
    try {
      const res = await adminPages.delete(confirmDelete.id);
      if (res?.success) {
        toast.success(t('content.page_deleted_successfully'));
        fetchData();
        refreshTenant();
      } else {
        toast.error(t('content.failed_to_delete_page'));
      }
    } catch {
      toast.error(t('content.an_unexpected_error_occurred'));
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  const columns: Column<PageItem>[] = [
    {
      key: 'title',
      label: t('content.label_name'),
      sortable: true,
      render: (item) => (
        <Button
          type="button"
          variant="light"
          onPress={() => navigate(tenantPath(`/admin/pages/builder/${item.id}`))}
          className="text-left font-medium text-primary hover:underline min-w-0 h-auto p-0 justify-start"
        >
          {item.title}
        </Button>
      ),
    },
    {
      key: 'slug',
      label: t('federation.col_slug'),
      sortable: true,
      render: (item) => <span className="text-sm text-default-500">/{item.slug}</span>,
    },
    {
      key: 'status',
      label: t('listings.status'),
      sortable: true,
      render: (item) => <StatusBadge status={item.status || 'draft'} />,
    },
    {
      key: 'show_in_menu',
      label: t('content.in_menu', 'In Menu'),
      sortable: true,
      render: (item) => item.show_in_menu ? (
        <Chip size="sm" variant="flat" color="primary">
          {item.menu_location === 'footer' ? 'Footer' : 'About'}
        </Chip>
      ) : (
        <span className="text-sm text-default-400">No</span>
      ),
    },
    {
      key: 'created_at',
      label: t('listings.created'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('listings.actions'),
      render: (item) => (
        <div className="flex gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="primary"
            onPress={() => navigate(tenantPath(`/admin/pages/builder/${item.id}`))}
            aria-label={t('content.label_edit_page')}
          >
            <Pencil size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            onPress={() => setConfirmDelete(item)}
            aria-label={t('content.label_delete_page')}
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  if (loading) {
    return (
      <div>
        <PageHeader title={t('content.pages_admin_title')} description={t('content.pages_admin_desc')} />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('content.pages_admin_title')}
        description={t('content.pages_admin_desc')}
        actions={
          <Button
            color="primary"
            startContent={<Plus size={16} />}
            onPress={() => navigate(tenantPath('/admin/pages/builder/new'))}
          >
            {t('breadcrumbs.create')} {t('breadcrumbs.pages')}
          </Button>
        }
      />

      {data.length === 0 ? (
        <EmptyState
          icon={FileText}
          title={t('no_data')}
          description={t('content.pages_admin_desc')}
          actionLabel={`${t('breadcrumbs.create')} ${t('breadcrumbs.pages')}`}
          onAction={() => navigate(tenantPath('/admin/pages/builder/new'))}
        />
      ) : (
        <DataTable
          columns={columns}
          data={data}
          searchPlaceholder={t('data_table.search', 'Search pages...')}
          onRefresh={fetchData}
        />
      )}

      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title={`${t('common.delete')} ${t('breadcrumbs.pages')}`}
          message={t('gamification.confirm_delete_campaign', { name: confirmDelete.title })}
          confirmLabel={t('common.delete')}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default PagesAdmin;
