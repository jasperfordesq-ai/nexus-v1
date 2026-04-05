// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Marketplace Listing Moderation Queue
 * Filter, review, approve/reject marketplace listings with moderation notes.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Tabs,
  Tab,
  Button,
  Chip,
  Avatar,
  Textarea,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Tooltip,
} from '@heroui/react';
import {
  CheckCircle,
  XCircle,
  Trash2,
  Eye,
  RefreshCw,
  ShoppingBag,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader, DataTable, ConfirmModal, EmptyState, type Column } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface MarketplaceListing {
  id: number;
  title: string;
  seller_name: string;
  seller_id: number;
  image_url?: string;
  price: number;
  currency: string;
  category?: string;
  status: string;
  moderation_status: string;
  moderation_notes?: string;
  created_at: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Color maps
// ─────────────────────────────────────────────────────────────────────────────

const moderationColors: Record<string, 'success' | 'warning' | 'danger' | 'default' | 'primary'> = {
  approved: 'success',
  pending: 'warning',
  rejected: 'danger',
  flagged: 'danger',
};

const statusColors: Record<string, 'success' | 'warning' | 'danger' | 'default'> = {
  active: 'success',
  inactive: 'default',
  sold: 'default',
  expired: 'warning',
};

const MODERATION_TABS = ['all', 'pending', 'approved', 'rejected', 'flagged'] as const;

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function MarketplaceModerationPage() {
  usePageTitle('Marketplace Moderation');
  const toast = useToast();
  const { tenantPath } = useTenant();

  const [items, setItems] = useState<MarketplaceListing[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [moderationFilter, setModerationFilter] = useState('all');
  const [search, setSearch] = useState('');

  // Action states
  const [actionLoading, setActionLoading] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState<MarketplaceListing | null>(null);

  // Reject modal
  const [rejectTarget, setRejectTarget] = useState<MarketplaceListing | null>(null);
  const [rejectNotes, setRejectNotes] = useState('');
  const [rejectLoading, setRejectLoading] = useState(false);

  const loadListings = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: String(page), limit: '20' });
      if (search) params.set('search', search);
      if (moderationFilter !== 'all') params.set('moderation_status', moderationFilter);

      const res = await api.get<MarketplaceListing[]>(
        `/v2/admin/marketplace/listings?${params.toString()}`
      );

      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setItems(data);
          const metaTotal = (res.meta as Record<string, unknown> | undefined)?.total;
          setTotal(typeof metaTotal === 'number' ? metaTotal : data.length);
        } else if (data && typeof data === 'object') {
          const pd = data as { data: MarketplaceListing[]; meta?: { total: number } };
          setItems(pd.data || []);
          setTotal(pd.meta?.total || 0);
        }
      }
    } catch {
      toast.error('Failed to load marketplace listings');
    } finally {
      setLoading(false);
    }
  }, [page, moderationFilter, search, toast]);

  useEffect(() => {
    loadListings();
  }, [loadListings]);

  // ─── Actions ────────────────────────────────────────────────────────────────

  const handleApprove = async (item: MarketplaceListing) => {
    setActionLoading(true);
    try {
      const res = await api.post(`/v2/admin/marketplace/listings/${item.id}/approve`);
      if (res?.success) {
        toast.success(`"${item.title}" approved`);
        loadListings();
      } else {
        toast.error((res as { error?: string }).error || 'Failed to approve listing');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setActionLoading(false);
    }
  };

  const handleRejectSubmit = async () => {
    if (!rejectTarget) return;
    setRejectLoading(true);
    try {
      const res = await api.post(`/v2/admin/marketplace/listings/${rejectTarget.id}/reject`, {
        notes: rejectNotes,
      });
      if (res?.success) {
        toast.success(`"${rejectTarget.title}" rejected`);
        setRejectTarget(null);
        setRejectNotes('');
        loadListings();
      } else {
        toast.error((res as { error?: string }).error || 'Failed to reject listing');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setRejectLoading(false);
    }
  };

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);
    try {
      const res = await api.delete(`/v2/admin/marketplace/listings/${confirmDelete.id}`);
      if (res?.success) {
        toast.success(`"${confirmDelete.title}" removed`);
        loadListings();
      } else {
        toast.error((res as { error?: string }).error || 'Failed to delete listing');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  // ─── Table columns ─────────────────────────────────────────────────────────

  const columns: Column<MarketplaceListing>[] = [
    {
      key: 'image',
      label: '',
      render: (item) => (
        <Avatar
          src={item.image_url || undefined}
          name={item.title.charAt(0)}
          size="sm"
          radius="lg"
          className="shrink-0"
        />
      ),
    },
    {
      key: 'title',
      label: 'Title',
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.title}</span>
      ),
    },
    {
      key: 'seller_name',
      label: 'Seller',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.seller_name}</span>
      ),
    },
    {
      key: 'price',
      label: 'Price',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.currency ?? ''}{item.price?.toFixed(2) ?? '0.00'}
        </span>
      ),
    },
    {
      key: 'category',
      label: 'Category',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">{item.category || '--'}</span>
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
      key: 'moderation_status',
      label: 'Moderation',
      sortable: true,
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={moderationColors[item.moderation_status] || 'default'}
          className="capitalize"
        >
          {item.moderation_status}
        </Chip>
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
          {(item.moderation_status === 'pending' || item.moderation_status === 'flagged') && (
            <>
              <Tooltip content="Approve">
                <Button
                  isIconOnly
                  size="sm"
                  variant="flat"
                  color="success"
                  onPress={() => handleApprove(item)}
                  isDisabled={actionLoading}
                  aria-label="Approve listing"
                >
                  <CheckCircle size={14} />
                </Button>
              </Tooltip>
              <Tooltip content="Reject">
                <Button
                  isIconOnly
                  size="sm"
                  variant="flat"
                  color="danger"
                  onPress={() => {
                    setRejectTarget(item);
                    setRejectNotes('');
                  }}
                  isDisabled={actionLoading}
                  aria-label="Reject listing"
                >
                  <XCircle size={14} />
                </Button>
              </Tooltip>
            </>
          )}
          <Tooltip content="View listing">
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="primary"
              as="a"
              href={tenantPath(`/marketplace/${item.id}`)}
              target="_blank"
              rel="noopener noreferrer"
              aria-label="View listing"
            >
              <Eye size={14} />
            </Button>
          </Tooltip>
          <Tooltip content="Delete">
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="danger"
              onPress={() => setConfirmDelete(item)}
              isDisabled={actionLoading}
              aria-label="Delete listing"
            >
              <Trash2 size={14} />
            </Button>
          </Tooltip>
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Marketplace Moderation"
        description="Review and moderate marketplace listings"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadListings}
          >
            Refresh
          </Button>
        }
      />

      {/* Moderation status filter tabs */}
      <div className="mb-4">
        <Tabs
          selectedKey={moderationFilter}
          onSelectionChange={(key) => {
            setModerationFilter(key as string);
            setPage(1);
          }}
          variant="underlined"
          size="sm"
        >
          {MODERATION_TABS.map((tab) => (
            <Tab key={tab} title={<span className="capitalize">{tab}</span>} />
          ))}
        </Tabs>
      </div>

      {/* Data table */}
      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchPlaceholder="Search listings..."
        onSearch={(q) => {
          setSearch(q);
          setPage(1);
        }}
        onRefresh={loadListings}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
        emptyContent={
          <EmptyState
            icon={ShoppingBag}
            title="No listings found"
            description={
              search || moderationFilter !== 'all'
                ? 'Try adjusting your filters or search query'
                : 'No marketplace listings have been created yet'
            }
          />
        }
      />

      {/* Reject modal */}
      {rejectTarget && (
        <Modal
          isOpen={!!rejectTarget}
          onClose={() => {
            setRejectTarget(null);
            setRejectNotes('');
          }}
          size="md"
        >
          <ModalContent>
            <ModalHeader className="flex items-center gap-2">
              <XCircle size={20} className="text-danger" />
              Reject Listing
            </ModalHeader>
            <ModalBody>
              <p className="text-sm text-default-600 mb-3">
                You are rejecting <strong>{rejectTarget.title}</strong> by{' '}
                {rejectTarget.seller_name}. Please provide a reason.
              </p>
              <Textarea
                label="Moderation Notes"
                placeholder="Enter the reason for rejection..."
                value={rejectNotes}
                onValueChange={setRejectNotes}
                minRows={3}
                maxRows={6}
                variant="bordered"
              />
            </ModalBody>
            <ModalFooter>
              <Button
                variant="flat"
                onPress={() => {
                  setRejectTarget(null);
                  setRejectNotes('');
                }}
                isDisabled={rejectLoading}
              >
                Cancel
              </Button>
              <Button
                color="danger"
                onPress={handleRejectSubmit}
                isLoading={rejectLoading}
                isDisabled={rejectLoading || !rejectNotes.trim()}
              >
                Reject Listing
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>
      )}

      {/* Delete confirmation modal */}
      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title="Delete Listing"
          message={`Are you sure you want to permanently remove "${confirmDelete.title}"? This action cannot be undone.`}
          confirmLabel="Delete"
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default MarketplaceModerationPage;
