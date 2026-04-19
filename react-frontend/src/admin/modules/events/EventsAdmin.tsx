// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Events Management
 * Full list view with status filtering, search, cancel, and delete.
 * Calls GET /api/v2/admin/events for paginated data.
 */

import { useState, useEffect, useCallback } from 'react';
import { Tabs, Tab, Button, Chip } from '@heroui/react';
import {
  Calendar,
  Eye,
  Trash2,
  XCircle,
  MapPin,
  Users,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader, DataTable, ConfirmModal, EmptyState, type Column } from '../../components';

import { useTranslation } from 'react-i18next';
// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface AdminEvent {
  id: number;
  title: string;
  description?: string;
  start_date: string;
  end_date?: string;
  location?: string;
  organizer_name?: string;
  status: string;
  attendees_count?: number;
  max_attendees?: number;
  created_at: string;
}

interface RawAdminEvent {
  id: number;
  title: string;
  description?: string;
  start_date: string;
  end_date?: string;
  location?: string;
  creator_name?: string;
  organizer_name?: string;
  status: string;
  attendees_count?: number;
  max_attendees?: number;
  created_at: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Status colours
// ─────────────────────────────────────────────────────────────────────────────

const statusColors: Record<string, 'success' | 'danger' | 'default' | 'warning'> = {
  published: 'success',
  active: 'success',
  cancelled: 'danger',
  canceled: 'danger',
  draft: 'default',
  past: 'warning',
};

const PAGE_SIZE = 50;

function normalizeAdminEvent(item: RawAdminEvent): AdminEvent {
  return {
    id: item.id,
    title: item.title,
    description: item.description,
    start_date: item.start_date,
    end_date: item.end_date,
    location: item.location,
    organizer_name: item.organizer_name ?? item.creator_name,
    status: item.status,
    attendees_count: item.attendees_count,
    max_attendees: item.max_attendees,
    created_at: item.created_at,
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function EventsAdmin() {
  const { t } = useTranslation('admin');
  usePageTitle(t('events.page_title'));
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [items, setItems] = useState<AdminEvent[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('all');
  const [search, setSearch] = useState('');

  // Confirm dialogs
  const [confirmDelete, setConfirmDelete] = useState<AdminEvent | null>(null);
  const [confirmCancel, setConfirmCancel] = useState<AdminEvent | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  // ── Fetch ──────────────────────────────────────────────────────────────────

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      params.set('page', String(page));
      params.set('limit', String(PAGE_SIZE));
      if (status !== 'all') params.set('status', status);
      if (search) params.set('search', search);

      const res = await api.get(`/v2/admin/events?${params.toString()}`);
      if (res.success && res.data) {
        const items = Array.isArray(res.data)
          ? (res.data as RawAdminEvent[]).map(normalizeAdminEvent)
          : [];
        setItems(items);
        setTotal(res.meta?.total ?? 0);
      }
    } catch {
      toast.error(t('events.failed_to_load_events'));
    } finally {
      setLoading(false);
    }
  }, [page, status, search, toast, t])

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  // ── Delete ─────────────────────────────────────────────────────────────────

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);
    try {
      const res = await api.delete(`/v2/admin/events/${confirmDelete.id}`);
      if (res?.success) {
        toast.success(t('events.event_deleted_successfully'));
        loadItems();
      } else {
        toast.error(res?.error || t('events.failed_to_delete_event'));
      }
    } catch {
      toast.error(t('events.an_unexpected_error_occurred'));
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  // ── Cancel ─────────────────────────────────────────────────────────────────

  const handleCancel = async () => {
    if (!confirmCancel) return;
    setActionLoading(true);
    try {
      const res = await api.post(`/v2/admin/events/${confirmCancel.id}/cancel`);
      if (res?.success) {
        toast.success(t('events.event_cancelled_successfully'));
        loadItems();
      } else {
        toast.error(res?.error || t('events.failed_to_cancel_event'));
      }
    } catch {
      toast.error(t('events.an_unexpected_error_occurred'));
    } finally {
      setActionLoading(false);
      setConfirmCancel(null);
    }
  };

  // ── Helpers ────────────────────────────────────────────────────────────────

  const formatDateTime = (iso: string) => {
    const d = new Date(iso);
    return d.toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  // ── Columns ────────────────────────────────────────────────────────────────

  const columns: Column<AdminEvent>[] = [
    {
      key: 'title',
      label: t('events.col_title'),
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.title}</span>
      ),
    },
    {
      key: 'start_date',
      label: t('events.col_date_time'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {formatDateTime(item.start_date)}
        </span>
      ),
    },
    {
      key: 'location',
      label: t('events.col_location'),
      render: (item) => (
        <span className="flex items-center gap-1 text-sm text-default-500">
          {item.location ? (
            <>
              <MapPin size={12} className="shrink-0" />
              <span className="truncate max-w-[180px]">{item.location}</span>
            </>
          ) : (
            '—'
          )}
        </span>
      ),
    },
    {
      key: 'organizer_name',
      label: t('events.col_organizer'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.organizer_name || t('events.unknown')}
        </span>
      ),
    },
    {
      key: 'status',
      label: t('events.col_status'),
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
      key: 'attendees_count',
      label: t('events.col_attendees'),
      sortable: true,
      render: (item) => (
        <span className="flex items-center gap-1 text-sm text-default-600">
          <Users size={12} />
          {item.attendees_count ?? 0}
          {item.max_attendees ? ` / ${item.max_attendees}` : ''}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('events.col_actions'),
      render: (item) => (
        <div className="flex gap-1">
          <Button
            as="a"
            href={tenantPath(`/events/${item.id}`)}
            target="_blank"
            rel="noopener noreferrer"
            isIconOnly
            size="sm"
            variant="flat"
            color="primary"
            aria-label={t('events.label_view_event')}
          >
            <Eye size={14} />
          </Button>
          {item.status !== 'cancelled' && item.status !== 'canceled' && (
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="warning"
              onPress={() => setConfirmCancel(item)}
              aria-label={t('events.label_cancel_event')}
            >
              <XCircle size={14} />
            </Button>
          )}
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            onPress={() => setConfirmDelete(item)}
            aria-label={t('events.label_delete_event')}
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  // ── Render ─────────────────────────────────────────────────────────────────

  return (
    <div>
      <PageHeader
        title={t('events.events_admin_title')}
        description={t('events.events_admin_desc')}
      />

      <div className="mb-4">
        <Tabs
          selectedKey={status}
          onSelectionChange={(key) => {
            setStatus(key as string);
            setPage(1);
          }}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title={t('events.tab_all')} />
          <Tab key="published" title={t('events.tab_published')} />
          <Tab key="cancelled" title={t('events.tab_cancelled')} />
          <Tab key="draft" title={t('events.tab_draft')} />
        </Tabs>
      </div>

      {!loading && items.length === 0 && !search ? (
        <EmptyState
          icon={Calendar}
          title={t('events.no_events_found')}
          description={
            status === 'all'
              ? t('events.no_events_desc')
              : t('events.no_status_events', { status })
          }
        />
      ) : (
        <DataTable
          columns={columns}
          data={items}
          isLoading={loading}
          searchPlaceholder={t('events.search_events_placeholder')}
          onSearch={(q) => {
            setSearch(q);
            setPage(1);
          }}
          onRefresh={loadItems}
          totalItems={total}
          page={page}
          pageSize={PAGE_SIZE}
          onPageChange={setPage}
        />
      )}

      {/* Delete confirmation */}
      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title={t('events.delete_event')}
          message={t('events.confirm_delete_event', { title: confirmDelete.title })}
          confirmLabel={t('common.delete')}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}

      {/* Cancel confirmation */}
      {confirmCancel && (
        <ConfirmModal
          isOpen={!!confirmCancel}
          onClose={() => setConfirmCancel(null)}
          onConfirm={handleCancel}
          title={t('events.cancel_event')}
          message={t('events.confirm_cancel_event', { title: confirmCancel.title })}
          confirmLabel={t('events.cancel_event')}
          confirmColor="warning"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default EventsAdmin;
