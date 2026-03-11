// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, { useState, useEffect } from 'react';
import {
  Button,
  Input,
  Select,
  SelectItem,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Chip,
  Avatar,
  Pagination,
  Spinner,
} from '@heroui/react';
import { Search, RefreshCw, EyeOff, Trash2 } from 'lucide-react';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useApi } from '@/hooks/useApi';
import { useAuth } from '@/contexts/AuthContext';
import { useToast } from '@/contexts/ToastContext';
import PageHeader from '@/admin/components/PageHeader';
import ConfirmModal from '@/admin/components/ConfirmModal';
import { adminModeration } from '@/admin/api/adminApi';
import { adminSuper } from '@/admin/api/adminApi';
import type { AdminFeedPost } from '@/admin/api/types';

const POST_TYPES = [
  { label: 'All Types', value: '' },
  { label: 'Text Post', value: 'text' },
  { label: 'Poll', value: 'poll' },
  { label: 'Event', value: 'event' },
  { label: 'Listing', value: 'listing' },
];

export default function FeedModeration() {
  usePageTitle('Feed Moderation');

  const toast = useToast();
  const { user } = useAuth();
  const userRecord = user as Record<string, unknown> | null;
  const isSuperAdmin =
    (user?.role as string) === 'super_admin' ||
    userRecord?.is_super_admin === true ||
    userRecord?.is_tenant_super_admin === true;

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [tenantFilter, setTenantFilter] = useState('');
  const [activeSearch, setActiveSearch] = useState('');
  const [activeType, setActiveType] = useState('');
  const [activeTenant, setActiveTenant] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [confirmAction, setConfirmAction] = useState<{
    type: 'hide' | 'delete';
    post: AdminFeedPost;
  } | null>(null);
  const [tenants, setTenants] = useState<Array<{ id: number; name: string }>>([]);

  // Load tenants list for super admin filter
  useEffect(() => {
    if (!isSuperAdmin) return;
    adminSuper.listTenants().then((res) => {
      if (res.success && Array.isArray(res.data)) {
        setTenants(res.data.map((t) => ({
          id: Number(t.id),
          name: String(t.name || 'Unknown'),
        })));
      }
    }).catch(() => {
      // Tenant list is optional; silently fail
    });
  }, [isSuperAdmin]);

  // Build query params for the endpoint
  const buildQueryString = () => {
    const params = new URLSearchParams();
    params.append('page', page.toString());
    params.append('limit', '20');
    if (activeSearch) params.append('search', activeSearch);
    if (activeType) params.append('type', activeType);
    if (activeTenant && activeTenant !== 'all') params.append('tenant_id', activeTenant);
    return params.toString();
  };

  const { data, isLoading, error, execute, meta } = useApi<AdminFeedPost[]>(
    `/v2/admin/feed/posts?${buildQueryString()}`,
    { immediate: true, deps: [page, activeSearch, activeType, activeTenant] }
  );

  const handleSearch = () => {
    setActiveSearch(search);
    setActiveType(typeFilter);
    setActiveTenant(tenantFilter);
    setPage(1);
  };

  const handleClear = () => {
    setSearch('');
    setTypeFilter('');
    setTenantFilter('');
    setActiveSearch('');
    setActiveType('');
    setActiveTenant('');
    setPage(1);
  };

  const handleAction = async () => {
    if (!confirmAction) return;

    setActionLoading(true);
    try {
      const response = confirmAction.type === 'hide'
        ? await adminModeration.hideFeedPost(confirmAction.post.id)
        : await adminModeration.deleteFeedPost(confirmAction.post.id);

      if (response.success) {
        toast.success(
          confirmAction.type === 'hide'
            ? 'Post hidden successfully'
            : 'Post deleted successfully'
        );
        setConfirmAction(null);
        execute();
      } else {
        toast.error(response.error || 'Action failed');
      }
    } catch {
      toast.error('An error occurred');
    } finally {
      setActionLoading(false);
    }
  };

  const posts = data || [];
  const totalPages = meta?.total_pages || 1;

  // Build cell content for a post row
  const renderCells = (post: AdminFeedPost): React.ReactElement[] => {
    const cells: React.ReactElement[] = [
      <TableCell key="user">
        <div className="flex items-center gap-3">
          <Avatar
            src={post.user_avatar || undefined}
            name={post.user_name}
            size="sm"
            className="flex-shrink-0"
          />
          <div className="flex flex-col">
            <span className="text-sm font-medium">{post.user_name}</span>
            <span className="text-xs text-default-400">ID: {post.user_id}</span>
          </div>
        </div>
      </TableCell>,
    ];

    if (isSuperAdmin) {
      cells.push(
        <TableCell key="tenant">
          <Chip size="sm" variant="flat" color="secondary">
            {post.tenant_name}
          </Chip>
        </TableCell>
      );
    }

    cells.push(
      <TableCell key="content">
        <div className="max-w-md">
          <p className="text-sm line-clamp-2">{post.content}</p>
          {post.is_flagged && (
            <Chip size="sm" color="warning" variant="flat" className="mt-1">
              Flagged
            </Chip>
          )}
        </div>
      </TableCell>,
      <TableCell key="type">
        <Chip size="sm" variant="flat">
          {post.type}
        </Chip>
      </TableCell>,
      <TableCell key="status">
        {post.is_hidden ? (
          <Chip size="sm" color="warning" variant="flat">Hidden</Chip>
        ) : (
          <Chip size="sm" color="success" variant="flat">Visible</Chip>
        )}
      </TableCell>,
      <TableCell key="created">
        <span className="text-sm text-default-500">
          {new Date(post.created_at).toLocaleDateString()}
        </span>
      </TableCell>,
      <TableCell key="actions">
        <div className="flex items-center gap-2">
          {!post.is_hidden && (
            <Button
              size="sm"
              variant="flat"
              color="warning"
              startContent={<EyeOff className="w-4 h-4" />}
              onPress={() => setConfirmAction({ type: 'hide', post })}
            >
              Hide
            </Button>
          )}
          <Button
            size="sm"
            variant="flat"
            color="danger"
            startContent={<Trash2 className="w-4 h-4" />}
            onPress={() => setConfirmAction({ type: 'delete', post })}
          >
            Delete
          </Button>
        </div>
      </TableCell>
    );

    return cells;
  };

  // Determine columns based on super admin status
  const columns = isSuperAdmin
    ? ['USER', 'TENANT', 'CONTENT', 'TYPE', 'STATUS', 'CREATED', 'ACTIONS']
    : ['USER', 'CONTENT', 'TYPE', 'STATUS', 'CREATED', 'ACTIONS'];

  return (
    <div className="space-y-6">
      <PageHeader
        title="Feed Moderation"
        description={isSuperAdmin ? 'Moderate feed posts across all tenants' : 'Moderate feed posts across your community'}
        actions={
          <Button
            color="primary"
            variant="flat"
            startContent={<RefreshCw className="w-4 h-4" />}
            onPress={() => execute()}
            isLoading={isLoading}
          >
            Refresh
          </Button>
        }
      />

      {/* Filter Bar */}
      <div className="flex flex-col sm:flex-row gap-4">
        <Input
          placeholder="Search posts or users..."
          aria-label="Search posts"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
          startContent={<Search className="w-4 h-4 text-default-400" />}
          className="flex-1"
        />
        <Select
          label="Post Type"
          selectedKeys={typeFilter ? [typeFilter] : []}
          onChange={(e) => setTypeFilter(e.target.value)}
          className="w-full sm:w-48"
        >
          {POST_TYPES.map((type) => (
            <SelectItem key={type.value}>
              {type.label}
            </SelectItem>
          ))}
        </Select>
        {isSuperAdmin && (
          <Select
            label="Tenant"
            selectedKeys={tenantFilter ? [tenantFilter] : []}
            onChange={(e) => setTenantFilter(e.target.value)}
            className="w-full sm:w-56"
          >
            {[
              <SelectItem key="all">All Tenants</SelectItem>,
              ...tenants.map((t) => (
                <SelectItem key={t.id.toString()}>
                  {t.name}
                </SelectItem>
              )),
            ]}
          </Select>
        )}
        <div className="flex gap-2">
          <Button color="primary" onPress={handleSearch}>
            Apply
          </Button>
          <Button variant="flat" onPress={handleClear}>
            Clear
          </Button>
        </div>
      </div>

      {/* Stats */}
      {meta && (
        <div className="text-sm text-default-500">
          Showing {posts.length} of {meta.total ?? posts.length} posts
          {isSuperAdmin && !activeTenant && ' (all tenants)'}
        </div>
      )}

      {/* Error State */}
      {error && (
        <div className="bg-danger-50 dark:bg-danger-950 text-danger border border-danger rounded-lg p-4">
          Failed to load posts. Please try again.
        </div>
      )}

      {/* Table */}
      <Table aria-label="Feed posts table">
        <TableHeader>
          {columns.map((col) => (
            <TableColumn key={col}>{col}</TableColumn>
          ))}
        </TableHeader>
        <TableBody
          items={posts}
          isLoading={isLoading}
          loadingContent={<Spinner />}
          emptyContent={
            <div className="text-center py-8 text-default-400">
              {activeSearch || activeType ? 'No posts match your filters' : 'No posts to moderate'}
            </div>
          }
        >
          {(post) => (
            <TableRow key={post.id}>
              {renderCells(post)}
            </TableRow>
          )}
        </TableBody>
      </Table>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex justify-center">
          <Pagination
            total={totalPages}
            page={page}
            onChange={setPage}
            showControls
            color="primary"
          />
        </div>
      )}

      {/* Confirm Modal */}
      <ConfirmModal
        isOpen={!!confirmAction}
        onClose={() => setConfirmAction(null)}
        onConfirm={handleAction}
        title={confirmAction?.type === 'hide' ? 'Hide Post' : 'Delete Post'}
        message={
          confirmAction?.type === 'hide'
            ? `Are you sure you want to hide this post${isSuperAdmin && confirmAction?.post ? ` from ${confirmAction.post.tenant_name}` : ''}? It will no longer be visible to members.`
            : `Are you sure you want to permanently delete this post${isSuperAdmin && confirmAction?.post ? ` from ${confirmAction.post.tenant_name}` : ''}? This action cannot be undone.`
        }
        confirmLabel={confirmAction?.type === 'hide' ? 'Hide Post' : 'Delete Post'}
        confirmColor={confirmAction?.type === 'hide' ? 'warning' : 'danger'}
        isLoading={actionLoading}
      />
    </div>
  );
}
