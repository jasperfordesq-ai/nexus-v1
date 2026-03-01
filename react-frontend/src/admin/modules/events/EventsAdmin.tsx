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

interface EventsMeta {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
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

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function EventsAdmin() {
  usePageTitle('Admin - Events');
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
        const payload = res.data as { items?: AdminEvent[]; meta?: EventsMeta };
        setItems(payload.items || []);
        setTotal(payload.meta?.total || 0);
      }
    } catch {
      toast.error('Failed to load events');
    } finally {
      setLoading(false);
    }
  }, [page, status, search, toast]);

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
        toast.success('Event deleted successfully');
        loadItems();
      } else {
        toast.error(res?.error || 'Failed to delete event');
      }
    } catch {
      toast.error('An unexpected error occurred');
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
        toast.success('Event cancelled successfully');
        loadItems();
      } else {
        toast.error(res?.error || 'Failed to cancel event');
      }
    } catch {
      toast.error('An unexpected error occurred');
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
      label: 'Title',
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.title}</span>
      ),
    },
    {
      key: 'start_date',
      label: 'Date / Time',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {formatDateTime(item.start_date)}
        </span>
      ),
    },
    {
      key: 'location',
      label: 'Location',
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
      label: 'Organizer',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.organizer_name || 'Unknown'}
        </span>
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
      key: 'attendees_count',
      label: 'Attendees',
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
      label: 'Actions',
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
            aria-label="View event"
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
              aria-label="Cancel event"
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
            aria-label="Delete event"
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
        title="Events"
        description="View and manage community events"
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
          <Tab key="all" title="All" />
          <Tab key="published" title="Published" />
          <Tab key="cancelled" title="Cancelled" />
          <Tab key="draft" title="Draft" />
        </Tabs>
      </div>

      {!loading && items.length === 0 && !search ? (
        <EmptyState
          icon={Calendar}
          title="No Events Found"
          description={
            status === 'all'
              ? 'There are no events in this community yet.'
              : `No ${status} events found.`
          }
        />
      ) : (
        <DataTable
          columns={columns}
          data={items}
          isLoading={loading}
          searchPlaceholder="Search events..."
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
          title="Delete Event"
          message={`Are you sure you want to delete "${confirmDelete.title}"? This action cannot be undone.`}
          confirmLabel="Delete"
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
          title="Cancel Event"
          message={`Are you sure you want to cancel "${confirmCancel.title}"? Attendees will be notified.`}
          confirmLabel="Cancel Event"
          confirmColor="warning"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default EventsAdmin;
