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
import { Search, RefreshCw, Flag, EyeOff, Trash2, Star } from 'lucide-react';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useApi } from '@/hooks/useApi';
import { useAuth } from '@/contexts/AuthContext';
import { useToast } from '@/contexts/ToastContext';
import PageHeader from '@/admin/components/PageHeader';
import ConfirmModal from '@/admin/components/ConfirmModal';
import { adminModeration } from '@/admin/api/adminApi';
import { adminSuper } from '@/admin/api/adminApi';
import type { AdminReview } from '@/admin/api/types';

const RATING_FILTERS = [
  { label: 'All Ratings', value: '' },
  { label: '5 Stars', value: '5' },
  { label: '4 Stars', value: '4' },
  { label: '3 Stars', value: '3' },
  { label: '2 Stars', value: '2' },
  { label: '1 Star', value: '1' },
];

export default function ReviewsModeration() {
  usePageTitle('Reviews Moderation');

  const toast = useToast();
  const { user } = useAuth();
  const userRecord = user as Record<string, unknown> | null;
  const isSuperAdmin =
    (user?.role as string) === 'super_admin' ||
    userRecord?.is_super_admin === true ||
    userRecord?.is_tenant_super_admin === true;

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [ratingFilter, setRatingFilter] = useState('');
  const [tenantFilter, setTenantFilter] = useState('');
  const [activeSearch, setActiveSearch] = useState('');
  const [activeRating, setActiveRating] = useState('');
  const [activeTenant, setActiveTenant] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [confirmAction, setConfirmAction] = useState<{
    type: 'flag' | 'hide' | 'delete';
    review: AdminReview;
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
    if (activeRating) params.append('rating', activeRating);
    if (activeTenant && activeTenant !== 'all') params.append('tenant_id', activeTenant);
    return params.toString();
  };

  const { data, isLoading, error, execute, meta } = useApi<AdminReview[]>(
    `/v2/admin/reviews?${buildQueryString()}`,
    { immediate: true, deps: [page, activeSearch, activeRating, activeTenant] }
  );

  const handleSearch = () => {
    setActiveSearch(search);
    setActiveRating(ratingFilter);
    setActiveTenant(tenantFilter);
    setPage(1);
  };

  const handleClear = () => {
    setSearch('');
    setRatingFilter('');
    setTenantFilter('');
    setActiveSearch('');
    setActiveRating('');
    setActiveTenant('');
    setPage(1);
  };

  const handleAction = async () => {
    if (!confirmAction) return;

    setActionLoading(true);
    try {
      let response;
      if (confirmAction.type === 'flag') {
        response = await adminModeration.flagReview(confirmAction.review.id);
      } else if (confirmAction.type === 'hide') {
        response = await adminModeration.hideReview(confirmAction.review.id);
      } else {
        response = await adminModeration.deleteReview(confirmAction.review.id);
      }

      if (response.success) {
        toast.success(
          confirmAction.type === 'flag'
            ? 'Review flagged successfully'
            : confirmAction.type === 'hide'
            ? 'Review hidden successfully'
            : 'Review deleted successfully'
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

  const reviews = data || [];
  const totalPages = meta?.total_pages || 1;

  const renderStars = (rating: number) => {
    return (
      <div className="flex gap-0.5">
        {[1, 2, 3, 4, 5].map((star) => (
          <Star
            key={star}
            className={`w-4 h-4 ${
              star <= rating
                ? 'fill-warning text-warning'
                : 'fill-default-200 text-default-200'
            }`}
          />
        ))}
      </div>
    );
  };

  // Build cell content for a review row
  const renderCells = (review: AdminReview): React.ReactElement[] => {
    const cells: React.ReactElement[] = [
      <TableCell key="reviewer">
        <div className="flex items-center gap-3">
          <Avatar
            src={review.reviewer_avatar || undefined}
            name={review.reviewer_name}
            size="sm"
            className="flex-shrink-0"
          />
          <span className="text-sm font-medium">{review.reviewer_name}</span>
        </div>
      </TableCell>,
      <TableCell key="reviewee">
        <div className="flex items-center gap-3">
          <Avatar
            src={review.reviewee_avatar || undefined}
            name={review.reviewee_name}
            size="sm"
            className="flex-shrink-0"
          />
          <span className="text-sm font-medium">{review.reviewee_name}</span>
        </div>
      </TableCell>,
    ];

    if (isSuperAdmin) {
      cells.push(
        <TableCell key="tenant">
          <Chip size="sm" variant="flat" color="secondary">
            {review.tenant_name}
          </Chip>
        </TableCell>
      );
    }

    cells.push(
      <TableCell key="rating">{renderStars(review.rating)}</TableCell>,
      <TableCell key="comment">
        <div className="max-w-md">
          <p className="text-sm line-clamp-2">{review.content}</p>
          {review.is_flagged && (
            <Chip size="sm" color="warning" variant="flat" className="mt-1">
              Flagged
            </Chip>
          )}
        </div>
      </TableCell>,
      <TableCell key="status">
        {review.is_hidden ? (
          <Chip size="sm" color="warning" variant="flat">Hidden</Chip>
        ) : (
          <Chip size="sm" color="success" variant="flat">Visible</Chip>
        )}
      </TableCell>,
      <TableCell key="created">
        <span className="text-sm text-default-500">
          {new Date(review.created_at).toLocaleDateString()}
        </span>
      </TableCell>,
      <TableCell key="actions">
        <div className="flex items-center gap-2">
          {!review.is_flagged && (
            <Button
              size="sm"
              variant="flat"
              color="warning"
              startContent={<Flag className="w-4 h-4" />}
              onPress={() => setConfirmAction({ type: 'flag', review })}
            >
              Flag
            </Button>
          )}
          {!review.is_hidden && (
            <Button
              size="sm"
              variant="flat"
              color="warning"
              startContent={<EyeOff className="w-4 h-4" />}
              onPress={() => setConfirmAction({ type: 'hide', review })}
            >
              Hide
            </Button>
          )}
          <Button
            size="sm"
            variant="flat"
            color="danger"
            startContent={<Trash2 className="w-4 h-4" />}
            onPress={() => setConfirmAction({ type: 'delete', review })}
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
    ? ['REVIEWER', 'REVIEWEE', 'TENANT', 'RATING', 'COMMENT', 'STATUS', 'CREATED', 'ACTIONS']
    : ['REVIEWER', 'REVIEWEE', 'RATING', 'COMMENT', 'STATUS', 'CREATED', 'ACTIONS'];

  return (
    <div className="space-y-6">
      <PageHeader
        title="Reviews Moderation"
        description={isSuperAdmin ? 'Moderate member reviews across all tenants' : 'Moderate member reviews and ratings'}
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
          placeholder="Search reviews or users..."
          aria-label="Search reviews"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
          startContent={<Search className="w-4 h-4 text-default-400" />}
          className="flex-1"
        />
        <Select
          label="Rating"
          selectedKeys={ratingFilter ? [ratingFilter] : []}
          onChange={(e) => setRatingFilter(e.target.value)}
          className="w-full sm:w-48"
        >
          {RATING_FILTERS.map((filter) => (
            <SelectItem key={filter.value}>
              {filter.label}
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
          Showing {reviews.length} of {meta.total ?? reviews.length} reviews
          {isSuperAdmin && !activeTenant && ' (all tenants)'}
        </div>
      )}

      {/* Error State */}
      {error && (
        <div className="bg-danger-50 dark:bg-danger-950 text-danger border border-danger rounded-lg p-4">
          Failed to load reviews. Please try again.
        </div>
      )}

      {/* Table */}
      <Table aria-label="Reviews table">
        <TableHeader>
          {columns.map((col) => (
            <TableColumn key={col}>{col}</TableColumn>
          ))}
        </TableHeader>
        <TableBody
          items={reviews}
          isLoading={isLoading}
          loadingContent={<Spinner />}
          emptyContent={
            <div className="text-center py-8 text-default-400">
              {activeSearch || activeRating
                ? 'No reviews match your filters'
                : 'No reviews to moderate'}
            </div>
          }
        >
          {(review) => (
            <TableRow key={review.id}>
              {renderCells(review)}
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
        title={
          confirmAction?.type === 'flag'
            ? 'Flag Review'
            : confirmAction?.type === 'hide'
            ? 'Hide Review'
            : 'Delete Review'
        }
        message={
          confirmAction?.type === 'flag'
            ? `Are you sure you want to flag this review${isSuperAdmin && confirmAction?.review ? ` from ${confirmAction.review.tenant_name}` : ''} for admin attention?`
            : confirmAction?.type === 'hide'
            ? `Are you sure you want to hide this review${isSuperAdmin && confirmAction?.review ? ` from ${confirmAction.review.tenant_name}` : ''}? It will no longer be visible to members.`
            : `Are you sure you want to permanently delete this review${isSuperAdmin && confirmAction?.review ? ` from ${confirmAction.review.tenant_name}` : ''}? This action cannot be undone.`
        }
        confirmLabel={
          confirmAction?.type === 'flag'
            ? 'Flag Review'
            : confirmAction?.type === 'hide'
            ? 'Hide Review'
            : 'Delete Review'
        }
        confirmColor={
          confirmAction?.type === 'delete' ? 'danger' : 'warning'
        }
        isLoading={actionLoading}
      />
    </div>
  );
}
