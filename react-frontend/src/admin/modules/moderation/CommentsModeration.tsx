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
import type { AdminComment } from '@/admin/api/types';

const CONTENT_TYPES = [
  { label: 'All Types', value: '' },
  { label: 'Post', value: 'post' },
  { label: 'Listing', value: 'listing' },
  { label: 'Event', value: 'event' },
  { label: 'Group', value: 'group' },
];

export default function CommentsModeration() {
  usePageTitle('Comments Moderation');

  const toast = useToast();
  const { user } = useAuth();
  const userRecord = user as Record<string, unknown> | null;
  const isSuperAdmin =
    (user?.role as string) === 'super_admin' ||
    userRecord?.is_super_admin === true ||
    userRecord?.is_tenant_super_admin === true;

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [contentTypeFilter, setContentTypeFilter] = useState('');
  const [tenantFilter, setTenantFilter] = useState('');
  const [activeSearch, setActiveSearch] = useState('');
  const [activeContentType, setActiveContentType] = useState('');
  const [activeTenant, setActiveTenant] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [confirmAction, setConfirmAction] = useState<{
    type: 'hide' | 'delete';
    comment: AdminComment;
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
    if (activeContentType) params.append('content_type', activeContentType);
    if (activeTenant && activeTenant !== 'all') params.append('tenant_id', activeTenant);
    return params.toString();
  };

  const { data, isLoading, error, execute, meta } = useApi<AdminComment[]>(
    `/v2/admin/comments?${buildQueryString()}`,
    { immediate: true, deps: [page, activeSearch, activeContentType, activeTenant] }
  );

  const handleSearch = () => {
    setActiveSearch(search);
    setActiveContentType(contentTypeFilter);
    setActiveTenant(tenantFilter);
    setPage(1);
  };

  const handleClear = () => {
    setSearch('');
    setContentTypeFilter('');
    setTenantFilter('');
    setActiveSearch('');
    setActiveContentType('');
    setActiveTenant('');
    setPage(1);
  };

  const handleAction = async () => {
    if (!confirmAction) return;

    setActionLoading(true);
    try {
      const response = confirmAction.type === 'hide'
        ? await adminModeration.hideComment(confirmAction.comment.id)
        : await adminModeration.deleteComment(confirmAction.comment.id);

      if (response.success) {
        toast.success(
          confirmAction.type === 'hide'
            ? 'Comment hidden successfully'
            : 'Comment deleted successfully'
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

  const comments = data || [];
  const totalPages = meta?.total_pages || 1;

  // Build cell content for a comment row
  const renderCells = (comment: AdminComment): React.ReactElement[] => {
    const cells: React.ReactElement[] = [
      <TableCell key="user">
        <div className="flex items-center gap-3">
          <Avatar
            src={comment.user_avatar || undefined}
            name={comment.user_name}
            size="sm"
            className="flex-shrink-0"
          />
          <div className="flex flex-col">
            <span className="text-sm font-medium">{comment.user_name}</span>
            <span className="text-xs text-default-400">ID: {comment.user_id}</span>
          </div>
        </div>
      </TableCell>,
    ];

    if (isSuperAdmin) {
      cells.push(
        <TableCell key="tenant">
          <Chip size="sm" variant="flat" color="secondary">
            {comment.tenant_name}
          </Chip>
        </TableCell>
      );
    }

    cells.push(
      <TableCell key="comment">
        <div className="max-w-md">
          <p className="text-sm line-clamp-2">{comment.content}</p>
          {comment.is_flagged && (
            <Chip size="sm" color="warning" variant="flat" className="mt-1">
              Flagged
            </Chip>
          )}
        </div>
      </TableCell>,
      <TableCell key="contentType">
        <Chip size="sm" variant="flat">
          {comment.content_type}
        </Chip>
      </TableCell>,
      <TableCell key="created">
        <span className="text-sm text-default-500">
          {new Date(comment.created_at).toLocaleDateString()}
        </span>
      </TableCell>,
      <TableCell key="actions">
        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant="flat"
            color="warning"
            startContent={<EyeOff className="w-4 h-4" />}
            onPress={() => setConfirmAction({ type: 'hide', comment })}
          >
            Hide
          </Button>
          <Button
            size="sm"
            variant="flat"
            color="danger"
            startContent={<Trash2 className="w-4 h-4" />}
            onPress={() => setConfirmAction({ type: 'delete', comment })}
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
    ? ['USER', 'TENANT', 'COMMENT', 'CONTENT TYPE', 'CREATED', 'ACTIONS']
    : ['USER', 'COMMENT', 'CONTENT TYPE', 'CREATED', 'ACTIONS'];

  return (
    <div className="space-y-6">
      <PageHeader
        title="Comments Moderation"
        description={isSuperAdmin ? 'Moderate comments across all tenants' : 'Moderate comments across all content types'}
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
          placeholder="Search comments or users..."
          aria-label="Search comments"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
          startContent={<Search className="w-4 h-4 text-default-400" />}
          className="flex-1"
        />
        <Select
          label="Content Type"
          selectedKeys={contentTypeFilter ? [contentTypeFilter] : []}
          onChange={(e) => setContentTypeFilter(e.target.value)}
          className="w-full sm:w-48"
        >
          {CONTENT_TYPES.map((type) => (
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
          Showing {comments.length} of {meta.total ?? comments.length} comments
          {isSuperAdmin && !activeTenant && ' (all tenants)'}
        </div>
      )}

      {/* Error State */}
      {error && (
        <div className="bg-danger-50 dark:bg-danger-950 text-danger border border-danger rounded-lg p-4">
          Failed to load comments. Please try again.
        </div>
      )}

      {/* Table */}
      <Table aria-label="Comments table">
        <TableHeader>
          {columns.map((col) => (
            <TableColumn key={col}>{col}</TableColumn>
          ))}
        </TableHeader>
        <TableBody
          items={comments}
          isLoading={isLoading}
          loadingContent={<Spinner />}
          emptyContent={
            <div className="text-center py-8 text-default-400">
              {activeSearch || activeContentType
                ? 'No comments match your filters'
                : 'No comments to moderate'}
            </div>
          }
        >
          {(comment) => (
            <TableRow key={comment.id}>
              {renderCells(comment)}
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
        title={confirmAction?.type === 'hide' ? 'Hide Comment' : 'Delete Comment'}
        message={
          confirmAction?.type === 'hide'
            ? `Are you sure you want to hide this comment${isSuperAdmin && confirmAction?.comment ? ` from ${confirmAction.comment.tenant_name}` : ''}? It will no longer be visible to members.`
            : `Are you sure you want to permanently delete this comment${isSuperAdmin && confirmAction?.comment ? ` from ${confirmAction.comment.tenant_name}` : ''}? This action cannot be undone.`
        }
        confirmLabel={confirmAction?.type === 'hide' ? 'Hide Comment' : 'Delete Comment'}
        confirmColor={confirmAction?.type === 'hide' ? 'warning' : 'danger'}
        isLoading={actionLoading}
      />
    </div>
  );
}
