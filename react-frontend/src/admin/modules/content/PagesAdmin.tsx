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
import { Button, Spinner } from '@heroui/react';
import { FileText, Plus, Pencil, Trash2 } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminPages } from '../../api/adminApi';
import { PageHeader, DataTable, StatusBadge, EmptyState, ConfirmModal, type Column } from '../../components';

interface PageItem {
  id: number;
  title: string;
  slug: string;
  status: string;
  sort_order: number;
  created_at: string;
}

export function PagesAdmin() {
  usePageTitle('Admin - Pages');
  const { tenantPath } = useTenant();
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
      toast.error('Failed to load pages');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);
    try {
      const res = await adminPages.delete(confirmDelete.id);
      if (res?.success) {
        toast.success('Page deleted successfully');
        fetchData();
      } else {
        toast.error('Failed to delete page');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  const columns: Column<PageItem>[] = [
    {
      key: 'title',
      label: 'Title',
      sortable: true,
      render: (item) => (
        <button
          type="button"
          className="text-left font-medium text-primary hover:underline"
          onClick={() => navigate(tenantPath(`/admin/pages/builder/${item.id}`))}
        >
          {item.title}
        </button>
      ),
    },
    {
      key: 'slug',
      label: 'Slug',
      sortable: true,
      render: (item) => <span className="text-sm text-default-500">/{item.slug}</span>,
    },
    {
      key: 'status',
      label: 'Status',
      sortable: true,
      render: (item) => <StatusBadge status={item.status || 'draft'} />,
    },
    {
      key: 'created_at',
      label: 'Created',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (item) => (
        <div className="flex gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="primary"
            onPress={() => navigate(tenantPath(`/admin/pages/builder/${item.id}`))}
            aria-label="Edit page"
          >
            <Pencil size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            onPress={() => setConfirmDelete(item)}
            aria-label="Delete page"
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
        <PageHeader title="Pages" description="Manage CMS pages" />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Pages"
        description="Manage CMS pages"
        actions={
          <Button
            color="primary"
            startContent={<Plus size={16} />}
            onPress={() => navigate(tenantPath('/admin/pages/builder/new'))}
          >
            Create Page
          </Button>
        }
      />

      {data.length === 0 ? (
        <EmptyState
          icon={FileText}
          title="No Pages Created"
          description="Create custom CMS pages for your community. Pages can be used for about, terms, privacy policy, and other static content."
          actionLabel="Create Page"
          onAction={() => navigate(tenantPath('/admin/pages/builder/new'))}
        />
      ) : (
        <DataTable
          columns={columns}
          data={data}
          searchPlaceholder="Search pages..."
          onRefresh={fetchData}
        />
      )}

      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title="Delete Page"
          message={`Are you sure you want to delete "${confirmDelete.title}"? This action cannot be undone.`}
          confirmLabel="Delete"
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default PagesAdmin;
