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
import { adminMarketplace, type BulkActionResult } from '../../api/adminApi';
import { PageHeader, DataTable, ConfirmModal, EmptyState, BulkActionToolbar, type BulkAction, type Column } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface MarketplaceListing {
  id: number;
  title: string;
  price: number;
  price_currency: string;
  price_type: string;
  status: string;
  moderation_status: string;
  moderation_notes?: string;
  seller_type: string;
  views_count: number;
  image: string | null;
  category: string | null;
  user: { id: number; name: string } | null;
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
  usePageTitle("Listing Moderation");
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

  // Bulk selection state
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [bulkLoading, setBulkLoading] = useState(false);

  const loadListings = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: String(page), per_page: '20' });
      if (search) params.set('q', search);
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
      toast.error("Failed to load listings");
    } finally {
      setLoading(false);
    }
  }, [page, moderationFilter, search, toast, t])

  useEffect(() => {
    loadListings();
  }, [loadListings]);

  // ─── Actions ────────────────────────────────────────────────────────────────

  const handleApprove = async (item: MarketplaceListing) => {
    setActionLoading(true);
    try {
      const res = await api.post(`/v2/admin/marketplace/listings/${item.id}/approve`);
      if (res?.success) {
        toast.success(`Listing approved`);
        loadListings();
      } else {
        toast.error((res as { error?: string }).error || "Failed to approve listing");
      }
    } catch {
      toast.error("An unexpected error occurred");
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
        toast.success(`Listing rejected`);
        setRejectTarget(null);
        setRejectNotes('');
        loadListings();
      } else {
        toast.error((res as { error?: string }).error || "Failed to reject listing");
      }
    } catch {
      toast.error("An unexpected error occurred");
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
        toast.success(`Listing removed`);
        loadListings();
      } else {
        toast.error((res as { error?: string }).error || "Failed to delete listing");
      }
    } catch {
      toast.error("An unexpected error occurred");
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
          src={item.image || undefined}
          name={item.title.charAt(0)}
          size="sm"
          radius="lg"
          className="shrink-0"
        />
      ),
    },
    {
      key: 'title',
      label: "Title",
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.title}</span>
      ),
    },
    {
      key: 'user',
      label: "Seller",
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.user?.name ?? '--'}</span>
      ),
    },
    {
      key: 'price',
      label: "Price",
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.price_currency ?? ''}{Number(item.price ?? 0).toFixed(2)}
        </span>
      ),
    },
    {
      key: 'category',
      label: "Category",
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">{item.category || '--'}</span>
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
      key: 'moderation_status',
      label: "Moderation",
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
          {(item.moderation_status === 'pending' || item.moderation_status === 'flagged') && (
            <>
              <Tooltip content={"Approve"}>
                <Button
                  isIconOnly
                  size="sm"
                  variant="flat"
                  color="success"
                  onPress={() => handleApprove(item)}
                  isDisabled={actionLoading}
                  aria-label={"Approve Listing"}
                >
                  <CheckCircle size={14} />
                </Button>
              </Tooltip>
              <Tooltip content={"Reject"}>
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
                  aria-label={"Reject Listing"}
                >
                  <XCircle size={14} />
                </Button>
              </Tooltip>
            </>
          )}
          <Tooltip content={"View Listing"}>
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="primary"
              as="a"
              href={tenantPath(`/marketplace/${item.id}`)}
              target="_blank"
              rel="noopener noreferrer"
              aria-label={"View Listing"}
            >
              <Eye size={14} />
            </Button>
          </Tooltip>
          <Tooltip content={"Delete"}>
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="danger"
              onPress={() => setConfirmDelete(item)}
              isDisabled={actionLoading}
              aria-label={"Delete Listing"}
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
        title={"Moderation Queue"}
        description={"Review and moderate marketplace listings pending approval"}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadListings}
          >
            {"Refresh"}
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

      {/* Bulk action toolbar */}
      {(() => {
        const selectedIdList = Array.from(selectedIds).map((id) => Number(id)).filter((n) => Number.isFinite(n));
        const bulkActions: BulkAction[] = [
          {
            key: 'reject',
            label: "Reject",
            icon: <XCircle size={14} />,
            color: 'danger',
            destructive: true,
            needsReason: true,
            reasonLabel: "Reason",
            reasonPlaceholder: "Enter reason...",
            confirmTitle: "Reject Confirm",
            confirmMessage: `Reject Confirm`,
            onConfirm: async (reason) => {
              if (!reason) return;
              setBulkLoading(true);
              try {
                const res = await adminMarketplace.bulkReject(selectedIdList, reason);
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
                loadListings();
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

      {/* Data table */}
      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchPlaceholder={"Search listings..."}
        onSearch={(q) => {
          setSearch(q);
          setPage(1);
        }}
        onRefresh={loadListings}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
        selectable
        onSelectionChange={setSelectedIds}
        emptyContent={
          <EmptyState
            icon={ShoppingBag}
            title={"No listings found"}
            description={
              search || moderationFilter !== 'all'
                ? "Try adjusting your search or filters"
                : "No listings have been created yet"
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
              {"Reject Listing"}
            </ModalHeader>
            <ModalBody>
              <p className="text-sm text-default-600 mb-3">
                {`Please provide a reason for rejecting this listing.`}
              </p>
              <Textarea
                label={"Moderation Notes"}
                placeholder={"Enter reason for rejection..."}
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
                {"Cancel"}
              </Button>
              <Button
                color="danger"
                onPress={handleRejectSubmit}
                isLoading={rejectLoading}
                isDisabled={rejectLoading || !rejectNotes.trim()}
              >
                {"Reject Listing"}
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
          title={"Delete Listing"}
          message={`Are you sure you want to delete this listing? This cannot be undone.`}
          confirmLabel={"Delete"}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default MarketplaceModerationPage;
