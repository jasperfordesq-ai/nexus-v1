// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Resources / Knowledge Base Management
 * List, search, filter, and delete help articles and resources.
 * Parity: PHP Admin resource management
 */

import { useState, useCallback, useEffect } from 'react';
import { Tabs, Tab, Chip, Button } from '@heroui/react';
import { BookOpen, Eye, Trash2, ThumbsUp } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader, DataTable, ConfirmModal, EmptyState, type Column } from '../../components';

import { useTranslation } from 'react-i18next';
// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface Resource {
  id: number;
  title: string;
  category: string;
  author_name: string;
  views: number;
  helpful_votes: number;
  status: string;
  updated_at: string;
}

interface ResourceMeta {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

const statusColors: Record<string, 'success' | 'default'> = {
  published: 'success',
  draft: 'default',
};

const PAGE_SIZE = 50;

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function ResourcesAdmin() {
  const { t } = useTranslation('admin');
  usePageTitle(t('resources.page_title'));
  const toast = useToast();
  const { tenantPath } = useTenant();

  const [items, setItems] = useState<Resource[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('all');
  const [search, setSearch] = useState('');
  const [confirmDelete, setConfirmDelete] = useState<Resource | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  // ── Data fetching ────────────────────────────────────────────────────────

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      params.set('page', String(page));
      params.set('limit', String(PAGE_SIZE));
      if (search) params.set('search', search);
      if (status !== 'all') params.set('status', status);

      const res = await api.get(`/v2/admin/resources?${params.toString()}`);

      if (res.success && res.data) {
        const payload = res.data as { items?: Resource[]; meta?: ResourceMeta };
        setItems(payload.items || []);
        setTotal(payload.meta?.total || 0);
      }
    } catch {
      toast.error(t('resources.failed_to_load_resources'));
    } finally {
      setLoading(false);
    }
  }, [page, status, search, toast]);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  // ── Delete handler ───────────────────────────────────────────────────────

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);

    try {
      const res = await api.delete(`/v2/admin/resources/${confirmDelete.id}`);
      if (res?.success) {
        toast.success(t('resources.resource_deleted_successfully'));
        loadItems();
      } else {
        toast.error(res?.error || t('resources.an_unexpected_error_occurred'));
      }
    } catch {
      toast.error(t('resources.an_unexpected_error_occurred'));
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  // ── Column definitions ───────────────────────────────────────────────────

  const columns: Column<Resource>[] = [
    {
      key: 'title',
      label: t('content.label_name'),
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.title}</span>
      ),
    },
    {
      key: 'category',
      label: t('breadcrumbs.categories'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">{item.category || '--'}</span>
      ),
    },
    {
      key: 'author_name',
      label: t('listings.author'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.author_name || t('resources.unknown', 'Unknown')}</span>
      ),
    },
    {
      key: 'views',
      label: t('resources.views', 'Views'),
      sortable: true,
      render: (item) => (
        <div className="flex items-center gap-1 text-sm text-default-500">
          <Eye size={14} className="text-default-400" />
          {(item.views ?? 0).toLocaleString()}
        </div>
      ),
    },
    {
      key: 'helpful_votes',
      label: t('resources.helpful', 'Helpful'),
      sortable: true,
      render: (item) => (
        <div className="flex items-center gap-1 text-sm text-default-500">
          <ThumbsUp size={14} className="text-default-400" />
          {(item.helpful_votes ?? 0).toLocaleString()}
        </div>
      ),
    },
    {
      key: 'status',
      label: t('listings.status'),
      sortable: true,
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={statusColors[item.status] || 'default'}
          className="capitalize"
        >
          {item.status}
        </Chip>
      ),
    },
    {
      key: 'updated_at',
      label: t('resources.updated', 'Updated'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.updated_at ? new Date(item.updated_at).toLocaleDateString() : '--'}
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
            as="a"
            href={tenantPath(`/resources/${item.id}`)}
            target="_blank"
            rel="noopener noreferrer"
            aria-label={t('resources.label_view_resource')}
          >
            <Eye size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            onPress={() => setConfirmDelete(item)}
            aria-label={t('resources.label_delete_resource')}
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  // ── Render ───────────────────────────────────────────────────────────────

  return (
    <div>
      <PageHeader
        title={t('resources.resources_admin_title')}
        description={t('resources.resources_admin_desc')}
      />

      <div className="mb-4">
        <Tabs
          selectedKey={status}
          onSelectionChange={(key) => { setStatus(key as string); setPage(1); }}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title={t('listings.filter_all')} />
          <Tab key="published" title={t('content.published', 'Published')} />
          <Tab key="draft" title={t('content.draft', 'Draft')} />
        </Tabs>
      </div>

      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchPlaceholder={t('data_table.search', 'Search resources...')}
        onSearch={(q) => { setSearch(q); setPage(1); }}
        onRefresh={loadItems}
        totalItems={total}
        page={page}
        pageSize={PAGE_SIZE}
        onPageChange={setPage}
        emptyContent={
          <EmptyState
            icon={BookOpen}
            title={t('no_data')}
            description={t('resources.resources_admin_desc')}
          />
        }
      />

      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title={`${t('common.delete')} ${t('resources.page_title')}`}
          message={t('gamification.confirm_delete_campaign', { name: confirmDelete.title })}
          confirmLabel={t('common.delete')}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default ResourcesAdmin;
