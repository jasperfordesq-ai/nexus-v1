// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Resources / Knowledge Base Management
 * List, search, filter, and delete help articles and resources.
 * Parity: PHP Admin resource management
 */

import { getFormattingLocale } from '@/lib/helpers';
import { useState, useCallback, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

import BookOpen from 'lucide-react/icons/book-open';
import Eye from 'lucide-react/icons/eye';
import Trash2 from 'lucide-react/icons/trash-2';
import ThumbsUp from 'lucide-react/icons/thumbs-up';
import Plus from 'lucide-react/icons/plus';
import Pencil from 'lucide-react/icons/pencil';
import FolderTree from 'lucide-react/icons/folder-tree';
import { useAdminPageMeta } from '../../AdminMetaContext';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components/PageHeader';
import { DataTable, type Column } from '../../components/DataTable';
import { ConfirmModal } from '../../components/ConfirmModal';
import { EmptyState } from '../../components/EmptyState';

import { useTranslation } from 'react-i18next';
import { Button, Chip, Tabs, Tab } from '@/components/ui';
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
  const { t } = useTranslation('admin_resources');
  useAdminPageMeta({ title: t('resources.resources_admin_title') });
  const toast = useToast();
  const { tenantPath } = useTenant();
  const navigate = useNavigate();

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
  }, [page, status, search, toast, t])


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
      label: t('resources.resources_admin_name'),
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.title}</span>
      ),
    },
    {
      key: 'category',
      label: t('resources.categories'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-muted">{item.category || '--'}</span>
      ),
    },
    {
      key: 'author_name',
      label: t('resources.resources_admin_author'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-muted">{item.author_name || t('resources.unknown')}</span>
      ),
    },
    {
      key: 'views',
      label: t('resources.views'),
      sortable: true,
      render: (item) => (
        <div className="flex items-center gap-1 text-sm text-muted">
          <Eye aria-hidden="true" size={14} className="text-muted" />
          {(item.views ?? 0).toLocaleString(getFormattingLocale())}
        </div>
      ),
    },
    {
      key: 'helpful_votes',
      label: t('resources.helpful'),
      sortable: true,
      render: (item) => (
        <div className="flex items-center gap-1 text-sm text-muted">
          <ThumbsUp aria-hidden="true" size={14} className="text-muted" />
          {(item.helpful_votes ?? 0).toLocaleString(getFormattingLocale())}
        </div>
      ),
    },
    {
      key: 'status',
      label: t('resources.resources_admin_status'),
      sortable: true,
      render: (item) => (
        <Chip
          size="sm"
          variant="soft"
          color={statusColors[item.status] || 'default'}
          className="capitalize"
        >
          {item.status}
        </Chip>
      ),
    },
    {
      key: 'updated_at',
      label: t('resources.updated'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-muted">
          {item.updated_at ? new Date(item.updated_at).toLocaleDateString(getFormattingLocale()) : '--'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('resources.resources_admin_actions'),
      render: (item) => (
        <div className="flex gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="tertiary"
            onPress={() => navigate(tenantPath(`/admin/resources/edit/${item.id}`))}
            aria-label={t('resources.label_edit_resource')}
          >
            <Pencil size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="tertiary"
            as="a"
            href={tenantPath(`/kb/${item.id}`)}
            target="_blank"
            rel="noopener noreferrer"
            aria-label={t('resources.label_view_resource')}
          >
            <Eye size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="danger"
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
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="tertiary"
              startContent={<FolderTree aria-hidden="true" size={16} />}
              onPress={() => navigate(tenantPath('/admin/resources/categories'))}
            >
              {t('resources.manage_categories')}
            </Button>
            <Button
              startContent={<Plus aria-hidden="true" size={16} />}
              onPress={() => navigate(tenantPath('/admin/resources/create'))}
            >
              {t('resources.new_article')}
            </Button>
          </div>
        }
      />

      <div className="mb-4">
        <Tabs
          aria-label={t('resources.status_tabs_aria')}
          selectedKey={status}
          onSelectionChange={(key) => { setStatus(key as string); setPage(1); }}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title={t('common.all')} />
          <Tab key="published" title={t('content.published')} />
          <Tab key="draft" title={t('content.draft')} />
        </Tabs>
      </div>

      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchPlaceholder={t('data_table.search')}
        onSearch={(q) => { setSearch(q); setPage(1); }}
        onRefresh={loadItems}
        totalItems={total}
        page={page}
        pageSize={PAGE_SIZE}
        onPageChange={setPage}
        emptyContent={
          <EmptyState
            icon={BookOpen}
            title={t('resources.resources_admin_empty')}
            description={t('resources.resources_admin_desc')}
          />
        }
      />

      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title={t('resources.resources_admin_delete_title')}
          message={t('resources.resources_admin_delete_message', { title: confirmDelete.title })}
          confirmLabel={t('common.delete')}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default ResourcesAdmin;
