// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Blog Posts List
 * Full CRUD management for blog posts with status filtering and search.
 * Parity: PHP Admin\BlogController::index()
 */

import { useState, useCallback, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Tabs, Tab, Button, Chip } from '@heroui/react';
import { Plus, Pencil, Trash2, ToggleLeft, Send } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminBlog, type BulkActionResult } from '../../api/adminApi';
import { DataTable, PageHeader, ConfirmModal, BulkActionToolbar, type BulkAction, type Column } from '../../components';
import type { AdminBlogPost } from '../../api/types';

import { useTranslation } from 'react-i18next';
const statusColors: Record<string, 'success' | 'default'> = {
  published: 'success',
  draft: 'default',
};

export function BlogAdmin() {
  const { t } = useTranslation('admin');
  usePageTitle(t('blog.page_title'));
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [items, setItems] = useState<AdminBlogPost[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('all');
  const [search, setSearch] = useState('');
  const [confirmDelete, setConfirmDelete] = useState<AdminBlogPost | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [bulkLoading, setBulkLoading] = useState(false);

  const handleBulkResult = (res: { success: boolean; error?: string; data?: BulkActionResult | unknown }) => {
    if (!res.success) {
      toast.error(res.error || t('bulk.result_failed'));
      return;
    }
    const data = (res.data as BulkActionResult) || { success: 0, failed: 0 };
    if (data.failed && data.failed > 0) {
      toast.error(t('bulk.result_partial', { success: data.success, failed: data.failed }));
    } else {
      toast.success(t('bulk.result_success', { count: data.success }));
    }
    setSelectedIds(new Set());
    loadItems();
  };

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminBlog.list({
        page,
        status: status === 'all' ? undefined : status,
        search: search || undefined,
      });
      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setItems(data);
          const metaTotal = (res.meta as Record<string, unknown> | undefined)?.total;
          setTotal(typeof metaTotal === 'number' ? metaTotal : data.length);
        } else if (data && typeof data === 'object') {
          const pd = data as { data: AdminBlogPost[]; meta?: { total: number } };
          setItems(pd.data || []);
          setTotal(pd.meta?.total || 0);
        }
      }
    } catch {
      toast.error(t('blog.failed_to_load_blog_posts'));
    } finally {
      setLoading(false);
    }
  }, [page, status, search, toast]);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);

    try {
      const res = await adminBlog.delete(confirmDelete.id);
      if (res?.success) {
        toast.success(t('blog.blog_post_deleted_successfully'));
        loadItems();
      } else {
        toast.error(res?.error || t('blog.an_unexpected_error_occurred'));
      }
    } catch {
      toast.error(t('blog.an_unexpected_error_occurred'));
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  const handleToggleStatus = async (post: AdminBlogPost) => {
    try {
      const res = await adminBlog.toggleStatus(post.id);
      if (res?.success) {
        toast.success(t('content.item_updated'));
        loadItems();
      } else {
        toast.error(res?.error || t('blog.an_unexpected_error_occurred'));
      }
    } catch {
      toast.error(t('blog.an_unexpected_error_occurred'));
    }
  };

  const columns: Column<AdminBlogPost>[] = [
    {
      key: 'title',
      label: t('content.label_name'),
      sortable: true,
      render: (item) => (
        <Button
          type="button"
          variant="light"
          onPress={() => navigate(tenantPath(`/admin/blog/edit/${item.id}`))}
          className="text-left font-medium text-primary hover:underline min-w-0 h-auto p-0 justify-start"
        >
          {item.title}
        </Button>
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
      key: 'author_name',
      label: t('listings.author'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.author_name || t('blog.unknown', 'Unknown')}</span>
      ),
    },
    {
      key: 'category_name',
      label: t('breadcrumbs.categories'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">{item.category_name || '--'}</span>
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
            onPress={() => navigate(tenantPath(`/admin/blog/edit/${item.id}`))}
            aria-label={t('blog.label_edit_post')}
          >
            <Pencil size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color={item.status === 'published' ? 'warning' : 'success'}
            onPress={() => handleToggleStatus(item)}
            aria-label={item.status === 'published' ? t('blog.unpublish', 'Unpublish') : t('blog.publish', 'Publish')}
          >
            <ToggleLeft size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            onPress={() => setConfirmDelete(item)}
            aria-label={t('blog.label_delete_post')}
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('blog.blog_admin_title')}
        description={t('blog.blog_admin_desc')}
        actions={
          <Button
            color="primary"
            startContent={<Plus size={16} />}
            onPress={() => navigate(tenantPath('/admin/blog/create'))}
          >
            {t('breadcrumbs.create')} {t('breadcrumbs.blog')}
          </Button>
        }
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

      {(() => {
        const selectedIdList = Array.from(selectedIds).map((id) => Number(id)).filter((n) => Number.isFinite(n));
        const bulkActions: BulkAction[] = [
          {
            key: 'publish',
            label: t('bulk.blog.publish'),
            icon: <Send size={14} />,
            color: 'success',
            confirmTitle: t('bulk.blog.publish_confirm_title'),
            confirmMessage: t('bulk.blog.publish_confirm_message', { count: selectedIdList.length }),
            onConfirm: async () => {
              setBulkLoading(true);
              try {
                const res = await adminBlog.bulkPublish(selectedIdList);
                handleBulkResult(res);
              } finally {
                setBulkLoading(false);
              }
            },
          },
          {
            key: 'delete',
            label: t('bulk.blog.delete'),
            icon: <Trash2 size={14} />,
            color: 'danger',
            destructive: true,
            confirmTitle: t('bulk.blog.delete_confirm_title'),
            confirmMessage: t('bulk.blog.delete_confirm_message', { count: selectedIdList.length }),
            onConfirm: async () => {
              setBulkLoading(true);
              try {
                const res = await adminBlog.bulkDelete(selectedIdList);
                handleBulkResult(res);
              } finally {
                setBulkLoading(false);
              }
            },
          },
        ];
        return (
          <BulkActionToolbar
            selectedCount={selectedIds.size}
            actions={bulkActions}
            onClearSelection={() => setSelectedIds(new Set())}
            isLoading={bulkLoading}
          />
        );
      })()}

      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchPlaceholder={t('data_table.search', 'Search blog posts...')}
        onSearch={(q) => { setSearch(q); setPage(1); }}
        onRefresh={loadItems}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
        selectable
        onSelectionChange={setSelectedIds}
      />

      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title={`${t('common.delete')} ${t('breadcrumbs.blog')}`}
          message={t('gamification.confirm_delete_campaign', { name: confirmDelete.title })}
          confirmLabel={t('common.delete')}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default BlogAdmin;
