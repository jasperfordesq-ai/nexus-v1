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
import Plus from 'lucide-react/icons/plus';
import Pencil from 'lucide-react/icons/pencil';
import Trash2 from 'lucide-react/icons/trash-2';
import ToggleLeft from 'lucide-react/icons/toggle-left';
import Send from 'lucide-react/icons/send';
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
  usePageTitle("Blog");
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
      toast.error(res.error || "Result failed");
      return;
    }
    const data = (res.data as BulkActionResult) || { success: 0, failed: 0 };
    if (data.failed && data.failed > 0) {
      toast.error(`Result Partial`);
    } else {
      toast.success(`Result succeeded`);
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
      toast.error("Failed to load blog posts");
    } finally {
      setLoading(false);
    }
  }, [page, status, search, toast])


  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);

    try {
      const res = await adminBlog.delete(confirmDelete.id);
      if (res?.success) {
        toast.success("Blog post deleted successfully");
        loadItems();
      } else {
        toast.error(res?.error || "An unexpected error occurred");
      }
    } catch {
      toast.error("An unexpected error occurred");
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  const handleToggleStatus = async (post: AdminBlogPost) => {
    try {
      const res = await adminBlog.toggleStatus(post.id);
      if (res?.success) {
        toast.success("Item Updated");
        loadItems();
      } else {
        toast.error(res?.error || "An unexpected error occurred");
      }
    } catch {
      toast.error("An unexpected error occurred");
    }
  };

  const columns: Column<AdminBlogPost>[] = [
    {
      key: 'title',
      label: "Name",
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
      label: "Status",
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
      label: "Author",
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.author_name || t('blog.unknown', 'Unknown')}</span>
      ),
    },
    {
      key: 'category_name',
      label: "Categories",
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">{item.category_name || '--'}</span>
      ),
    },
    {
      key: 'created_at',
      label: "Created",
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: "Actions",
      render: (item) => (
        <div className="flex gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="primary"
            onPress={() => navigate(tenantPath(`/admin/blog/edit/${item.id}`))}
            aria-label={"Edit Post"}
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
            aria-label={"Delete Post"}
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
        title={"Blog Admin"}
        description={"Create, edit, and manage blog posts for your community"}
        actions={
          <Button
            color="primary"
            startContent={<Plus size={16} />}
            onPress={() => navigate(tenantPath('/admin/blog/create'))}
          >
            {"Create"} {"Blog"}
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
          <Tab key="all" title={"All"} />
          <Tab key="published" title={t('content.published', 'Published')} />
          <Tab key="draft" title={t('content.draft', 'Draft')} />
        </Tabs>
      </div>

      {(() => {
        const selectedIdList = Array.from(selectedIds).map((id) => Number(id)).filter((n) => Number.isFinite(n));
        const bulkActions: BulkAction[] = [
          {
            key: 'publish',
            label: "Publish",
            icon: <Send size={14} />,
            color: 'success',
            confirmTitle: "Publish Confirm",
            confirmMessage: `Publish Confirm`,
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
            key: 'archive',
            label: t('bulk.blog.archive', 'Archive (Unpublish)'),
            icon: <ToggleLeft size={14} />,
            color: 'warning',
            confirmTitle: t('bulk.blog.archive_confirm_title', 'Archive selected posts?'),
            confirmMessage: t('bulk.blog.archive_confirm_message', {
              count: selectedIdList.length,
              defaultValue: 'This will unpublish {{count}} post(s). They can be republished later.',
            }),
            onConfirm: async () => {
              setBulkLoading(true);
              try {
                // No bulk-archive endpoint — loop single-row toggleStatus for posts
                // that are currently published. Partial failures surface as a toast.
                const targetPosts = items.filter(
                  (p) => selectedIdList.includes(p.id) && p.status === 'published',
                );
                let success = 0;
                let failed = 0;
                for (const post of targetPosts) {
                  try {
                    const r = await adminBlog.toggleStatus(post.id);
                    if (r?.success) success += 1; else failed += 1;
                  } catch {
                    failed += 1;
                  }
                }
                handleBulkResult({ success: true, data: { success, failed } as BulkActionResult });
              } finally {
                setBulkLoading(false);
              }
            },
          },
          {
            key: 'delete',
            label: "Delete",
            icon: <Trash2 size={14} />,
            color: 'danger',
            destructive: true,
            confirmTitle: "Delete Confirm",
            confirmMessage: `Delete Confirm`,
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
          title={`${"Delete"} ${"Blog"}`}
          message={`Delete Campaign`}
          confirmLabel={"Delete"}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default BlogAdmin;
