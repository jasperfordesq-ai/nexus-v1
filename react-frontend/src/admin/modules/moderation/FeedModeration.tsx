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

import { useTranslation } from 'react-i18next';

export default function FeedModeration() {
  const { t } = useTranslation('admin');
  usePageTitle(t('moderation.page_title'));

  const POST_TYPES = [
    { label: t('moderation.filter_all_types'), value: '' },
    { label: t('moderation.post_type_text'), value: 'post' },
    { label: t('moderation.post_type_poll'), value: 'poll' },
    { label: t('moderation.content_type_event'), value: 'event' },
    { label: t('moderation.content_type_listing'), value: 'listing' },
    { label: t('moderation.post_type_goal'), value: 'goal' },
    { label: t('moderation.content_type_review'), value: 'review' },
    { label: t('moderation.post_type_job'), value: 'job' },
    { label: t('moderation.post_type_challenge'), value: 'challenge' },
    { label: t('moderation.post_type_volunteer'), value: 'volunteer' },
    { label: t('moderation.post_type_blog'), value: 'blog' },
    { label: t('moderation.post_type_discussion'), value: 'discussion' },
  ];

  const toast = useToast();
  const { user } = useAuth();
  const isSuperAdmin =
    user?.role === 'super_admin' ||
    user?.is_super_admin === true ||
    user?.is_tenant_super_admin === true;

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

    // Re-validate role from auth context before performing any moderation action
    const isAdmin = user?.role === 'admin' || user?.role === 'super_admin' || user?.is_super_admin === true || user?.is_tenant_super_admin === true;
    const isModerator = user?.role === 'moderator';
    if (!isAdmin && !isModerator) {
      toast.error(t('moderation.unauthorized'));
      setConfirmAction(null);
      return;
    }

    setActionLoading(true);
    try {
      const postType = confirmAction.post.type || 'post';
      const response = confirmAction.type === 'hide'
        ? await adminModeration.hideFeedPost(confirmAction.post.id, postType)
        : await adminModeration.deleteFeedPost(confirmAction.post.id, postType);

      if (response.success) {
        toast.success(
          confirmAction.type === 'hide'
            ? t('moderation.post_hidden_successfully')
            : t('moderation.post_deleted_successfully')
        );
        setConfirmAction(null);
        execute();
      } else {
        toast.error(response.error || t('moderation.action_failed'));
      }
    } catch {
      toast.error(t('moderation.an_error_occurred'));
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
              {t('moderation.flagged')}
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
          <Chip size="sm" color="warning" variant="flat">{t('moderation.hidden')}</Chip>
        ) : (
          <Chip size="sm" color="success" variant="flat">{t('moderation.visible')}</Chip>
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
              {t('moderation.hide')}
            </Button>
          )}
          <Button
            size="sm"
            variant="flat"
            color="danger"
            startContent={<Trash2 className="w-4 h-4" />}
            onPress={() => setConfirmAction({ type: 'delete', post })}
          >
            {t('moderation.delete')}
          </Button>
        </div>
      </TableCell>
    );

    return cells;
  };

  // Determine columns based on super admin status
  const columns = isSuperAdmin
    ? [t('moderation.col_user'), t('moderation.col_tenant'), t('moderation.col_content'), t('moderation.col_type'), t('moderation.col_status'), t('moderation.col_created'), t('moderation.col_actions')]
    : [t('moderation.col_user'), t('moderation.col_content'), t('moderation.col_type'), t('moderation.col_status'), t('moderation.col_created'), t('moderation.col_actions')];

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('moderation.feed_moderation_title')}
        description={isSuperAdmin ? t('moderation.feed_desc_super') : t('moderation.feed_desc')}
        actions={
          <Button
            color="primary"
            variant="flat"
            startContent={<RefreshCw className="w-4 h-4" />}
            onPress={() => execute()}
            isLoading={isLoading}
          >
            {t('moderation.refresh')}
          </Button>
        }
      />

      {/* Filter Bar */}
      <div className="flex flex-col sm:flex-row gap-4">
        <Input
          placeholder={t('moderation.placeholder_search_posts_or_users')}
          aria-label={t('moderation.label_search_posts')}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
          startContent={<Search className="w-4 h-4 text-default-400" />}
          className="flex-1"
        />
        <Select
          label={t('moderation.label_post_type')}
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
            label={t('moderation.label_tenant')}
            selectedKeys={tenantFilter ? [tenantFilter] : []}
            onChange={(e) => setTenantFilter(e.target.value)}
            className="w-full sm:w-56"
          >
            {[
              <SelectItem key="all">{t('moderation.filter_all_tenants')}</SelectItem>,
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
            {t('moderation.apply')}
          </Button>
          <Button variant="flat" onPress={handleClear}>
            {t('moderation.clear')}
          </Button>
        </div>
      </div>

      {/* Stats */}
      {meta && (
        <div className="text-sm text-default-500">
          {t('moderation.showing_count', { shown: posts.length, total: meta.total ?? posts.length, item: t('moderation.items_posts') })}
          {isSuperAdmin && !activeTenant && ` (${t('moderation.all_tenants')})`}
        </div>
      )}

      {/* Error State */}
      {error && (
        <div className="bg-danger-50 dark:bg-danger-950 text-danger border border-danger rounded-lg p-4">
          {t('moderation.failed_to_load_posts')}
        </div>
      )}

      {/* Table */}
      <Table aria-label={t('moderation.label_feed_posts_table')}>
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
              {activeSearch || activeType ? t('moderation.no_posts_match_filters') : t('moderation.no_posts_to_moderate')}
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
        title={confirmAction?.type === 'hide' ? t('moderation.hide_post') : t('moderation.delete_post')}
        message={
          confirmAction?.type === 'hide'
            ? t('moderation.confirm_hide_post', { tenant: isSuperAdmin && confirmAction?.post ? ` from ${confirmAction.post.tenant_name}` : '' })
            : t('moderation.confirm_delete_post', { tenant: isSuperAdmin && confirmAction?.post ? ` from ${confirmAction.post.tenant_name}` : '' })
        }
        confirmLabel={confirmAction?.type === 'hide' ? t('moderation.hide_post') : t('moderation.delete_post')}
        confirmColor={confirmAction?.type === 'hide' ? 'warning' : 'danger'}
        isLoading={actionLoading}
      />
    </div>
  );
}
