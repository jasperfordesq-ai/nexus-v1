// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Marketplace Listing Moderation Queue
 * Filter, review, approve/reject marketplace listings with moderation notes.
 */

import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
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
  const { t } = useTranslation('admin');
  usePageTitle(t('marketplace.moderation_page_title'));
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
      toast.error(t('marketplace.failed_load_listings'));
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
        toast.success(t('marketplace.listing_approved', { title: item.title }));
        loadListings();
      } else {
        toast.error((res as { error?: string }).error || t('marketplace.failed_approve_listing'));
      }
    } catch {
      toast.error(t('marketplace.unexpected_error'));
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
        toast.success(t('marketplace.listing_rejected', { title: rejectTarget.title }));
        setRejectTarget(null);
        setRejectNotes('');
        loadListings();
      } else {
        toast.error((res as { error?: string }).error || t('marketplace.failed_reject_listing'));
      }
    } catch {
      toast.error(t('marketplace.unexpected_error'));
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
        toast.success(t('marketplace.listing_removed', { title: confirmDelete.title }));
        loadListings();
      } else {
        toast.error((res as { error?: string }).error || t('marketplace.failed_delete_listing'));
      }
    } catch {
      toast.error(t('marketplace.unexpected_error'));
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
      label: t('marketplace.col_title'),
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.title}</span>
      ),
    },
    {
      key: 'user',
      label: t('marketplace.col_seller'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.user?.name ?? '--'}</span>
      ),
    },
    {
      key: 'price',
      label: t('marketplace.col_price'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.price_currency ?? ''}{Number(item.price ?? 0).toFixed(2)}
        </span>
      ),
    },
    {
      key: 'category',
      label: t('marketplace.col_category'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">{item.category || '--'}</span>
      ),
    },
    {
      key: 'status',
      label: t('marketplace.col_status'),
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
      label: t('marketplace.col_moderation'),
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
      label: t('marketplace.col_created'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('marketplace.col_actions'),
      render: (item) => (
        <div className="flex gap-1">
          {(item.moderation_status === 'pending' || item.moderation_status === 'flagged') && (
            <>
              <Tooltip content={t('marketplace.action_approve')}>
                <Button
                  isIconOnly
                  size="sm"
                  variant="flat"
                  color="success"
                  onPress={() => handleApprove(item)}
                  isDisabled={actionLoading}
                  aria-label={t('marketplace.action_approve_listing')}
                >
                  <CheckCircle size={14} />
                </Button>
              </Tooltip>
              <Tooltip content={t('marketplace.action_reject')}>
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
                  aria-label={t('marketplace.action_reject_listing')}
                >
                  <XCircle size={14} />
                </Button>
              </Tooltip>
            </>
          )}
          <Tooltip content={t('marketplace.action_view_listing')}>
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="primary"
              as="a"
              href={tenantPath(`/marketplace/${item.id}`)}
              target="_blank"
              rel="noopener noreferrer"
              aria-label={t('marketplace.action_view_listing')}
            >
              <Eye size={14} />
            </Button>
          </Tooltip>
          <Tooltip content={t('marketplace.action_delete')}>
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="danger"
              onPress={() => setConfirmDelete(item)}
              isDisabled={actionLoading}
              aria-label={t('marketplace.action_delete_listing')}
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
        title={t('marketplace.moderation_title')}
        description={t('marketplace.moderation_description')}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadListings}
          >
            {t('marketplace.refresh')}
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
        searchPlaceholder={t('marketplace.search_listings')}
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
            title={t('marketplace.no_listings_found')}
            description={
              search || moderationFilter !== 'all'
                ? t('marketplace.try_adjusting_filters')
                : t('marketplace.no_listings_created_yet')
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
              {t('marketplace.reject_listing_title')}
            </ModalHeader>
            <ModalBody>
              <p className="text-sm text-default-600 mb-3">
                {t('marketplace.reject_listing_message', { title: rejectTarget.title, seller: rejectTarget.user?.name ?? '--' })}
              </p>
              <Textarea
                label={t('marketplace.moderation_notes_label')}
                placeholder={t('marketplace.rejection_reason_placeholder')}
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
                {t('marketplace.cancel')}
              </Button>
              <Button
                color="danger"
                onPress={handleRejectSubmit}
                isLoading={rejectLoading}
                isDisabled={rejectLoading || !rejectNotes.trim()}
              >
                {t('marketplace.reject_listing_btn')}
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
          title={t('marketplace.delete_listing_title')}
          message={t('marketplace.delete_listing_message', { title: confirmDelete.title })}
          confirmLabel={t('marketplace.delete_btn')}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default MarketplaceModerationPage;
