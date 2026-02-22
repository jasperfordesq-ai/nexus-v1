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
import { Plus, Pencil, Trash2, ToggleLeft } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminBlog } from '../../api/adminApi';
import { DataTable, PageHeader, ConfirmModal, type Column } from '../../components';
import type { AdminBlogPost } from '../../api/types';

const statusColors: Record<string, 'success' | 'default'> = {
  published: 'success',
  draft: 'default',
};

export function BlogAdmin() {
  usePageTitle('Admin - Blog Posts');
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
          setTotal(data.length);
        } else if (data && typeof data === 'object') {
          const pd = data as { data: AdminBlogPost[]; meta?: { total: number } };
          setItems(pd.data || []);
          setTotal(pd.meta?.total || 0);
        }
      }
    } catch {
      toast.error('Failed to load blog posts');
    } finally {
      setLoading(false);
    }
  }, [page, status, search]);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);

    try {
      const res = await adminBlog.delete(confirmDelete.id);
      if (res?.success) {
        toast.success('Blog post deleted successfully');
        loadItems();
      } else {
        toast.error(res?.error || 'Failed to delete blog post');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  const handleToggleStatus = async (post: AdminBlogPost) => {
    try {
      const res = await adminBlog.toggleStatus(post.id);
      if (res?.success) {
        const newStatus = post.status === 'published' ? 'draft' : 'published';
        toast.success(`Post "${post.title}" is now ${newStatus}`);
        loadItems();
      } else {
        toast.error(res?.error || 'Failed to toggle status');
      }
    } catch {
      toast.error('An unexpected error occurred');
    }
  };

  const columns: Column<AdminBlogPost>[] = [
    {
      key: 'title',
      label: 'Title',
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
      label: 'Status',
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
      label: 'Author',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.author_name || 'Unknown'}</span>
      ),
    },
    {
      key: 'category_name',
      label: 'Category',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">{item.category_name || '--'}</span>
      ),
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
            onPress={() => navigate(tenantPath(`/admin/blog/edit/${item.id}`))}
            aria-label="Edit post"
          >
            <Pencil size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color={item.status === 'published' ? 'warning' : 'success'}
            onPress={() => handleToggleStatus(item)}
            aria-label={item.status === 'published' ? 'Unpublish' : 'Publish'}
          >
            <ToggleLeft size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            onPress={() => setConfirmDelete(item)}
            aria-label="Delete post"
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
        title="Blog Posts"
        description="Create and manage blog posts"
        actions={
          <Button
            color="primary"
            startContent={<Plus size={16} />}
            onPress={() => navigate(tenantPath('/admin/blog/create'))}
          >
            Create Post
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
          <Tab key="all" title="All" />
          <Tab key="published" title="Published" />
          <Tab key="draft" title="Draft" />
        </Tabs>
      </div>

      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchPlaceholder="Search blog posts..."
        onSearch={(q) => { setSearch(q); setPage(1); }}
        onRefresh={loadItems}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
      />

      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title="Delete Blog Post"
          message={`Are you sure you want to delete "${confirmDelete.title}"? This action cannot be undone.`}
          confirmLabel="Delete"
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default BlogAdmin;
