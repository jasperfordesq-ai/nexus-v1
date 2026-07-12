// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Events Management
 * Lifecycle-aware list view with moderation, operations, search, and archive controls.
 * Calls GET /api/v2/admin/events for paginated data.
 */

import { getFormattingLocale } from '@/lib/helpers';
import { useState, useEffect, useCallback, type Key } from 'react';
import { useTranslation } from 'react-i18next';

import Calendar from 'lucide-react/icons/calendar';
import Eye from 'lucide-react/icons/eye';
import XCircle from 'lucide-react/icons/circle-x';
import MapPin from 'lucide-react/icons/map-pin';
import Users from 'lucide-react/icons/users';
import MoreVertical from 'lucide-react/icons/more-vertical';
import { useAdminPageMeta } from '../../AdminMetaContext';
import { useTenant, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components/PageHeader';
import { DataTable, type Column } from '../../components/DataTable';
import { EmptyState } from '../../components/EmptyState';
import {
  Button,
  Chip,
  Dropdown,
  DropdownItem,
  DropdownMenu,
  DropdownTrigger,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Tab,
  Tabs,
  Textarea,
} from '@/components/ui';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

type PublicationState = 'draft' | 'pending_review' | 'published' | 'archived';
type OperationalState = 'scheduled' | 'postponed' | 'cancelled' | 'completed';
type LifecycleAction =
  | 'approve'
  | 'reject'
  | 'postpone'
  | 'cancel'
  | 'complete'
  | 'archive'
  | 'restore';

interface AdminEvent {
  id: number;
  title: string;
  description?: string;
  start_date: string;
  end_date?: string;
  timezone: string;
  all_day: boolean;
  location?: string;
  organizer_name?: string;
  status: string;
  publication_state: PublicationState;
  operational_state: OperationalState;
  lifecycle_version: number;
  attendees_count?: number;
  max_attendees?: number;
  waitlist_count: number;
  attendance_count: number;
  created_at: string;
}

interface RawAdminEvent {
  id: number;
  title: string;
  description?: string;
  start_date: string;
  end_date?: string;
  timezone?: string | null;
  all_day?: boolean;
  location?: string;
  creator_name?: string;
  organizer_name?: string;
  status: string;
  publication_state?: PublicationState;
  operational_state?: OperationalState;
  lifecycle_version?: number;
  attendees_count?: number;
  max_attendees?: number;
  capacity?: {
    limit?: number | null;
    confirmed?: number;
  };
  metrics?: {
    confirmed_count?: number;
    waitlist_count?: number;
    attendance_count?: number;
  };
  created_at: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Status colours
// ─────────────────────────────────────────────────────────────────────────────

const statusColors: Record<string, 'success' | 'danger' | 'default' | 'warning'> = {
  published: 'success',
  pending_review: 'warning',
  archived: 'default',
  scheduled: 'success',
  postponed: 'warning',
  completed: 'default',
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
    timezone: item.timezone ?? 'UTC',
    all_day: Boolean(item.all_day),
    location: item.location,
    organizer_name: item.organizer_name ?? item.creator_name,
    status: item.status,
    publication_state: item.publication_state ?? (item.status === 'draft' ? 'draft' : 'published'),
    operational_state: item.operational_state
      ?? (item.status === 'cancelled' ? 'cancelled' : item.status === 'completed' ? 'completed' : 'scheduled'),
    lifecycle_version: item.lifecycle_version ?? 0,
    attendees_count: item.metrics?.confirmed_count ?? item.capacity?.confirmed ?? item.attendees_count,
    max_attendees: item.capacity?.limit ?? item.max_attendees,
    waitlist_count: item.metrics?.waitlist_count ?? 0,
    attendance_count: item.metrics?.attendance_count ?? 0,
    created_at: item.created_at,
  };
}

const formatDateTime = (iso: string, timezone: string, allDay: boolean) => {
  const d = new Date(iso);
  const options: Intl.DateTimeFormatOptions = {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    ...(allDay ? {} : {
      hour: '2-digit',
      minute: '2-digit',
      timeZoneName: 'short',
    }),
  };
  try {
    return d.toLocaleDateString(getFormattingLocale(), { ...options, timeZone: timezone });
  } catch {
    return d.toLocaleDateString(getFormattingLocale(), { ...options, timeZone: 'UTC' });
  }
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function EventsAdmin() {
  const { t } = useTranslation('admin_events');
  useAdminPageMeta({ title: t('events.events_admin_title') });
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [items, setItems] = useState<AdminEvent[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [publicationState, setPublicationState] = useState<'all' | PublicationState>('all');
  const [search, setSearch] = useState('');

  const [actionModal, setActionModal] = useState<{
    action: LifecycleAction;
    event: AdminEvent;
  } | null>(null);
  const [actionReason, setActionReason] = useState('');
  const [actionLoading, setActionLoading] = useState(false);

  // ── Fetch ──────────────────────────────────────────────────────────────────

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      params.set('page', String(page));
      params.set('limit', String(PAGE_SIZE));
      if (publicationState !== 'all') params.set('publication_state', publicationState);
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
  }, [page, publicationState, search, toast, t]);


  useEffect(() => {
    loadItems();
  }, [loadItems]);

  // ── Delete ─────────────────────────────────────────────────────────────────

  const closeActionModal = () => {
    if (actionLoading) return;
    setActionModal(null);
    setActionReason('');
  };

  const openActionModal = (event: AdminEvent, action: LifecycleAction) => {
    setActionReason('');
    setActionModal({ event, action });
  };

  // ── Cancel ─────────────────────────────────────────────────────────────────

  const executeAction = async () => {
    if (!actionModal) return;
    const reason = actionReason.trim();
    const requiresReason = actionModal.action === 'reject' || actionModal.action === 'cancel';
    if (requiresReason && !reason) return;

    setActionLoading(true);
    try {
      const res = await api.post(
        `/v2/admin/events/${actionModal.event.id}/${actionModal.action}`,
        reason ? { reason } : {},
      );
      if (res?.success) {
        toast.success(t('events.action_success', {
          action: t(`events.action_${actionModal.action}`),
        }));
        await loadItems();
      } else {
        toast.error(res?.error || t('events.action_failed', {
          action: t(`events.action_${actionModal.action}`),
        }));
      }
    } catch {
      toast.error(t('events.an_unexpected_error_occurred'));
    } finally {
      setActionLoading(false);
      setActionModal(null);
      setActionReason('');
    }
  };

  const availableActions = (event: AdminEvent): LifecycleAction[] => {
    const actions: LifecycleAction[] = [];
    if (event.publication_state === 'draft' || event.publication_state === 'pending_review') {
      actions.push('approve');
    }
    if (event.publication_state === 'pending_review') actions.push('reject');
    if (event.publication_state === 'published' && event.operational_state === 'scheduled') {
      actions.push('postpone', 'complete');
    }
    if (
      event.publication_state !== 'archived'
      && (event.operational_state === 'scheduled' || event.operational_state === 'postponed')
    ) {
      actions.push('cancel');
    }
    if (
      event.operational_state === 'postponed'
      || event.operational_state === 'cancelled'
      || (event.publication_state === 'archived' && event.operational_state !== 'completed')
    ) {
      actions.push('restore');
    }
    if (event.publication_state !== 'archived') actions.push('archive');

    return actions;
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
        <div className="text-sm text-muted">
          <time dateTime={item.start_date}>
            {formatDateTime(item.start_date, item.timezone, item.all_day)}
          </time>
          {item.all_day && (
            <span className="mt-0.5 block text-xs">{t('events.all_day')}</span>
          )}
        </div>
      ),
    },
    {
      key: 'location',
      label: t('events.col_location'),
      render: (item) => (
        <span className="flex items-center gap-1 text-sm text-muted">
          {item.location ? (
            <>
              <MapPin size={12} className="shrink-0" aria-hidden="true" />
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
        <span className="text-sm text-muted">
          {item.organizer_name || t('events.unknown')}
        </span>
      ),
    },
    {
      key: 'status',
      label: t('events.col_status'),
      sortable: true,
      render: (item) => (
        <div className="flex min-w-[150px] flex-col items-start gap-1">
          <Chip
            size="sm"
            variant="soft"
            color={statusColors[item.publication_state] || 'default'}
          >
            {t(`events.publication_${item.publication_state}`)}
          </Chip>
          <Chip
            size="sm"
            variant="soft"
            color={statusColors[item.operational_state] || 'default'}
          >
            {t(`events.operational_${item.operational_state}`)}
          </Chip>
        </div>
      ),
    },
    {
      key: 'attendees_count',
      label: t('events.col_attendees'),
      sortable: true,
      render: (item) => (
        <div className="text-sm text-muted">
          <span className="flex items-center gap-1">
            <Users size={12} aria-hidden="true" />
            {t('events.confirmed_count', {
              count: item.attendees_count ?? 0,
              capacity: item.max_attendees ?? t('events.unlimited_capacity'),
            })}
          </span>
          {(item.waitlist_count > 0 || item.attendance_count > 0) && (
            <span className="mt-1 block text-xs">
              {t('events.people_secondary_counts', {
                waitlist: item.waitlist_count,
                attended: item.attendance_count,
              })}
            </span>
          )}
        </div>
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
            variant="tertiary"
            aria-label={t('events.label_view_event')}
          >
            <Eye size={14} aria-hidden="true" />
          </Button>
          {availableActions(item).length > 0 && (
            <Dropdown>
              <DropdownTrigger>
                <Button
                  isIconOnly
                  size="sm"
                  variant="tertiary"
                  aria-label={t('events.label_event_actions', { title: item.title })}
                >
                  <MoreVertical size={14} aria-hidden="true" />
                </Button>
              </DropdownTrigger>
              <DropdownMenu
                aria-label={t('events.label_event_actions', { title: item.title })}
                onAction={(key: Key) => openActionModal(item, key as LifecycleAction)}
              >
                {availableActions(item).map((action) => (
                  <DropdownItem
                    key={action}
                    id={action}
                    className={['reject', 'cancel', 'archive'].includes(action) ? 'text-danger' : undefined}
                    variant={['reject', 'cancel', 'archive'].includes(action) ? 'danger' : undefined}
                    startContent={action === 'cancel' ? <XCircle size={14} aria-hidden="true" /> : undefined}
                  >
                    {t(`events.action_${action}`)}
                  </DropdownItem>
                ))}
              </DropdownMenu>
            </Dropdown>
          )}
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
          aria-label={t('events.tabs_aria')}
          selectedKey={publicationState}
          onSelectionChange={(key) => {
            setPublicationState(key as 'all' | PublicationState);
            setPage(1);
          }}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title={t('events.tab_all')} />
          <Tab key="draft" title={t('events.tab_draft')} />
          <Tab key="pending_review" title={t('events.tab_pending_review')} />
          <Tab key="published" title={t('events.tab_published')} />
          <Tab key="archived" title={t('events.tab_archived')} />
        </Tabs>
      </div>

      {!loading && items.length === 0 && !search ? (
        <EmptyState
          icon={Calendar}
          title={t('events.no_events_found')}
          description={
            publicationState === 'all'
              ? t('events.no_events_desc')
              : t('events.no_status_events')
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

      <Modal isOpen={actionModal !== null} onClose={closeActionModal} size="md">
        <ModalContent>
          {actionModal && (
            <>
              <ModalHeader>
                {t(`events.action_${actionModal.action}`)}: {actionModal.event.title}
              </ModalHeader>
              <ModalBody>
                <p className="text-sm text-muted">
                  {t('events.confirm_action', {
                    action: t(`events.action_${actionModal.action}`).toLocaleLowerCase(),
                    title: actionModal.event.title,
                  })}
                </p>
                {actionModal.action !== 'approve' && (
                  <Textarea
                    label={t('events.action_reason_label')}
                    placeholder={t('events.action_reason_placeholder')}
                    value={actionReason}
                    onValueChange={setActionReason}
                    isRequired={actionModal.action === 'reject' || actionModal.action === 'cancel'}
                    minRows={3}
                    maxRows={6}
                  />
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="tertiary" onPress={closeActionModal} isDisabled={actionLoading}>
                  {t('common.cancel')}
                </Button>
                <Button
                  variant={['reject', 'cancel', 'archive'].includes(actionModal.action) ? 'danger' : 'secondary'}
                  isLoading={actionLoading}
                  isDisabled={
                    (actionModal.action === 'reject' || actionModal.action === 'cancel')
                    && !actionReason.trim()
                  }
                  onPress={executeAction}
                >
                  {t(`events.action_${actionModal.action}`)}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default EventsAdmin;
