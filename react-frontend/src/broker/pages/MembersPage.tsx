// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Members Page
 * Rich member management with avatar, balance, last activity, onboarding
 * status, notes drawer, and contextual actions for brokers.
 */

import { useEffect, useState, useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Chip } from '@/components/ui';
import { Badge } from '@/components/ui';
import MoreVertical from 'lucide-react/icons/ellipsis-vertical';
import Clock from 'lucide-react/icons/clock';
import Coins from 'lucide-react/icons/coins';
import StickyNote from 'lucide-react/icons/sticky-note';
import ShieldCheck from 'lucide-react/icons/shield-check';
import UserCheck from 'lucide-react/icons/user-check';
import UserX from 'lucide-react/icons/user-x';
import RotateCcw from 'lucide-react/icons/rotate-ccw';
import ExternalLink from 'lucide-react/icons/external-link';
import Send from 'lucide-react/icons/send';
import MailCheck from 'lucide-react/icons/mail-check';
import MailX from 'lucide-react/icons/mail-x';
import X from 'lucide-react/icons/x';
import IdCard from 'lucide-react/icons/id-card';
import Pin from 'lucide-react/icons/pin';
import Pencil from 'lucide-react/icons/pencil';
import Trash2 from 'lucide-react/icons/trash-2';
import Check from 'lucide-react/icons/check';
import MemberDetailModal from '@/broker/components/MemberDetailModal';
import { usePageTitle } from '@/hooks';
import { useToast,
  useTenant } from '@/contexts';
import { adminUsers,
  adminCrm } from '@/admin/api/adminApi';
import type { AdminUser } from '@/admin/api/types';
import { DataTable,
  PageHeader,
  ConfirmModal } from '@/admin/components';
import type { Column } from '@/admin/components';
import { resolveAvatarUrl } from '@/lib/helpers';
import { parseServerTimestamp,
  formatServerDate,
  formatServerDateTime } from '@/lib/serverTime';

import { Dropdown, DropdownTrigger, DropdownMenu, DropdownItem, Button, Textarea, Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, Avatar, Tabs, Tab, Tooltip, Select, SelectItem } from '@/components/ui';
// ─────────────────────────────────────────────────────────────────────────────
// Types & Constants
// ─────────────────────────────────────────────────────────────────────────────

type StatusTab = 'all' | 'pending' | 'active' | 'suspended' | 'never_logged_in' | 'onboarding_incomplete';

interface MemberNote {
  id: number;
  content: string;
  category?: string;
  is_pinned?: boolean;
  created_at: string;
  author_name?: string;
  author?: { name: string };
}

const STATUS_COLOR: Record<string, 'warning' | 'success' | 'danger' | 'default'> = {
  pending: 'warning',
  active: 'success',
  suspended: 'danger',
  banned: 'danger',
};

const PAGE_SIZE = 20;

// CRM note categories — mirrors the admin MemberNotes module.
const NOTE_CATEGORIES = ['general', 'outreach', 'support', 'onboarding', 'concern', 'follow_up'] as const;

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function useTimeAgo() {
  const { t } = useTranslation('broker');
  return (dateStr: string | null | undefined): string => {
    if (!dateStr) return t('members.time_never');
    const parsed = parseServerTimestamp(dateStr);
    if (!parsed) return t('members.time_never');
    // Clamp to 0 so server clock skew doesn't render rows as "in the future".
    const diff = Math.max(0, Date.now() - parsed.getTime());
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return t('members.time_just_now');
    if (mins < 60) return t('members.time_minutes_ago', { count: mins });
    const hours = Math.floor(mins / 60);
    if (hours < 24) return t('members.time_hours_ago', { count: hours });
    const days = Math.floor(hours / 24);
    if (days < 30) return t('members.time_days_ago', { count: days });
    return formatServerDate(dateStr);
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function MembersPage() {
  const { t } = useTranslation('broker');
  const timeAgo = useTimeAgo();
  const toast = useToast();
  const { tenantPath } = useTenant();
  usePageTitle(t('members.page_title'));

  // Data state
  const [members, setMembers] = useState<AdminUser[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);

  // Filter / pagination state
  const [activeTab, setActiveTab] = useState<StatusTab>('all');
  const [search, setSearch] = useState('');
  // Debounce search so typing doesn't fire a network request on every keystroke.
  const [debouncedSearch, setDebouncedSearch] = useState('');
  useEffect(() => {
    const handle = setTimeout(() => setDebouncedSearch(search), 300);
    return () => clearTimeout(handle);
  }, [search]);
  const [page, setPage] = useState(1);

  // Action modal state
  const [confirmAction, setConfirmAction] = useState<{
    type: 'approve' | 'suspend';
    user: AdminUser;
  } | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  // Bulk selection state — powers the "approve/suspend selected" bar.
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [bulkLoading, setBulkLoading] = useState(false);

  // Member detail modal state (holds the id of the member being viewed).
  const [detailUserId, setDetailUserId] = useState<number | null>(null);

  // Notes drawer state
  const [notesUser, setNotesUser] = useState<AdminUser | null>(null);
  const [notes, setNotes] = useState<MemberNote[]>([]);
  const [notesLoading, setNotesLoading] = useState(false);
  const [newNote, setNewNote] = useState('');
  const [noteCategory, setNoteCategory] = useState('general');
  const [addingNote, setAddingNote] = useState(false);
  // Inline note editing
  const [editingNoteId, setEditingNoteId] = useState<number | null>(null);
  const [editingContent, setEditingContent] = useState('');
  const [editingCategory, setEditingCategory] = useState('general');
  const [savingNote, setSavingNote] = useState(false);
  const [noteBusyId, setNoteBusyId] = useState<number | null>(null);

  // ─── Fetch members ────────────────────────────────────────────────────────

  const fetchMembers = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = {
        page,
        limit: PAGE_SIZE,
      };
      if (activeTab !== 'all') params.status = activeTab;
      if (debouncedSearch.trim()) params.search = debouncedSearch.trim();

      const res = await adminUsers.list(params as Parameters<typeof adminUsers.list>[0]);
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setMembers(payload as AdminUser[]);
          setTotal(payload.length);
        } else if (payload && typeof payload === 'object') {
          const paged = payload as { data: AdminUser[]; meta?: { total: number } };
          setMembers(paged.data || []);
          setTotal(paged.meta?.total ?? 0);
        }
      }
    } catch {
      toast.error(t('members.action_failed'));
    } finally {
      setLoading(false);
    }
  }, [page, activeTab, debouncedSearch, toast, t]);

  useEffect(() => {
    fetchMembers();
  }, [fetchMembers]);

  // ─── Notes ────────────────────────────────────────────────────────────────

  const openNotes = useCallback(async (user: AdminUser) => {
    setNotesUser(user);
    setNotesLoading(true);
    setNotes([]);
    setNewNote('');
    try {
      const res = await adminCrm.getNotes({ user_id: user.id, limit: 20 });
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setNotes(payload as MemberNote[]);
        } else if (payload && typeof payload === 'object') {
          const paged = payload as { data: MemberNote[] };
          setNotes(paged.data || []);
        }
      }
    } catch {
      // silently fail
    } finally {
      setNotesLoading(false);
    }
  }, []);

  const handleAddNote = useCallback(async () => {
    if (!notesUser || !newNote.trim()) return;
    setAddingNote(true);
    try {
      const res = await adminCrm.createNote({
        user_id: notesUser.id,
        content: newNote.trim(),
        category: noteCategory,
      });
      if (res.success) {
        toast.success(t('members.note_added'));
        setNewNote('');
        setNoteCategory('general');
        // Refresh notes
        openNotes(notesUser);
      }
    } catch {
      toast.error(t('members.action_failed'));
    } finally {
      setAddingNote(false);
    }
  }, [notesUser, newNote, noteCategory, toast, t, openNotes]);

  const startEditNote = useCallback((note: MemberNote) => {
    setEditingNoteId(note.id);
    setEditingContent(note.content);
    setEditingCategory(note.category || 'general');
  }, []);

  const cancelEditNote = useCallback(() => {
    setEditingNoteId(null);
    setEditingContent('');
  }, []);

  const handleUpdateNote = useCallback(async () => {
    if (editingNoteId == null || !editingContent.trim() || !notesUser) return;
    setSavingNote(true);
    try {
      const res = await adminCrm.updateNote(editingNoteId, {
        content: editingContent.trim(),
        category: editingCategory,
      });
      if (res.success) {
        toast.success(t('members.note_updated'));
        setEditingNoteId(null);
        openNotes(notesUser);
      } else {
        toast.error(t('members.action_failed'));
      }
    } catch {
      toast.error(t('members.action_failed'));
    } finally {
      setSavingNote(false);
    }
  }, [editingNoteId, editingContent, editingCategory, notesUser, toast, t, openNotes]);

  const handleDeleteNote = useCallback(async (noteId: number) => {
    if (!notesUser) return;
    setNoteBusyId(noteId);
    try {
      const res = await adminCrm.deleteNote(noteId);
      if (res.success) {
        toast.success(t('members.note_deleted'));
        openNotes(notesUser);
      } else {
        toast.error(t('members.action_failed'));
      }
    } catch {
      toast.error(t('members.action_failed'));
    } finally {
      setNoteBusyId(null);
    }
  }, [notesUser, toast, t, openNotes]);

  const handleTogglePin = useCallback(async (note: MemberNote) => {
    if (!notesUser) return;
    setNoteBusyId(note.id);
    try {
      const res = await adminCrm.updateNote(note.id, { is_pinned: !note.is_pinned });
      if (res.success) {
        openNotes(notesUser);
      } else {
        toast.error(t('members.action_failed'));
      }
    } catch {
      toast.error(t('members.action_failed'));
    } finally {
      setNoteBusyId(null);
    }
  }, [notesUser, toast, t, openNotes]);

  // ─── Tab change / search ──────────────────────────────────────────────────

  const handleTabChange = useCallback((key: React.Key) => {
    setActiveTab(key as StatusTab);
    setPage(1);
    setSelectedIds(new Set());
  }, []);

  const handleSearch = useCallback((query: string) => {
    setSearch(query);
    setPage(1);
    setSelectedIds(new Set());
  }, []);

  // ─── Bulk actions ───────────────────────────────────────────────────────────

  const clearSelection = useCallback(() => setSelectedIds(new Set()), []);

  const runBulk = useCallback(
    async (
      fn: (ids: number[]) => Promise<{ success?: boolean; error?: string }>,
      successKey: string,
    ) => {
      const ids = Array.from(selectedIds).map(Number).filter((n) => Number.isFinite(n) && n > 0);
      if (ids.length === 0) return;
      setBulkLoading(true);
      try {
        const res = await fn(ids);
        if (res?.success) {
          toast.success(t(successKey, { count: ids.length }));
          clearSelection();
          fetchMembers();
        } else {
          toast.error(res?.error || t('members.action_failed'));
        }
      } catch {
        toast.error(t('members.action_failed'));
      } finally {
        setBulkLoading(false);
      }
    },
    [selectedIds, toast, t, clearSelection, fetchMembers],
  );

  const handleBulkApprove = useCallback(
    () => runBulk((ids) => adminUsers.bulkApprove(ids), 'members.bulk_approved_success'),
    [runBulk],
  );
  const handleBulkSuspend = useCallback(
    () => runBulk((ids) => adminUsers.bulkSuspend(ids), 'members.bulk_suspended_success'),
    [runBulk],
  );

  // ─── Actions ──────────────────────────────────────────────────────────────

  const handleApprove = useCallback(async () => {
    if (!confirmAction || confirmAction.type !== 'approve') return;
    setActionLoading(true);
    try {
      const res = await adminUsers.approve(confirmAction.user.id);
      if (res.success) {
        toast.success(t('members.approved_success'));
        setConfirmAction(null);
        fetchMembers();
      } else {
        toast.error(t('members.action_failed'));
      }
    } catch {
      toast.error(t('members.action_failed'));
    } finally {
      setActionLoading(false);
    }
  }, [confirmAction, toast, t, fetchMembers]);

  const handleSuspend = useCallback(async () => {
    if (!confirmAction || confirmAction.type !== 'suspend') return;
    setActionLoading(true);
    try {
      const res = await adminUsers.suspend(confirmAction.user.id);
      if (res.success) {
        toast.success(t('members.suspended_success'));
        setConfirmAction(null);
        fetchMembers();
      } else {
        toast.error(t('members.action_failed'));
      }
    } catch {
      toast.error(t('members.action_failed'));
    } finally {
      setActionLoading(false);
    }
  }, [confirmAction, toast, t, fetchMembers]);

  // Per-id loading flag prevents double-click from spamming the
  // reactivate endpoint (which fires welcome-back emails + bell
  // notifications on every call — the backend is not idempotent).
  const [reactivatingId, setReactivatingId] = useState<number | null>(null);
  const handleReactivate = useCallback(
    async (user: AdminUser) => {
      if (reactivatingId !== null) return;
      setReactivatingId(user.id);
      try {
        const res = await adminUsers.reactivate(user.id);
        if (res.success) {
          toast.success(t('members.reactivated_success'));
          fetchMembers();
        } else {
          toast.error(t('members.action_failed'));
        }
      } catch {
        toast.error(t('members.action_failed'));
      } finally {
        setReactivatingId(null);
      }
    },
    [reactivatingId, toast, t, fetchMembers],
  );

  // ─── Columns ──────────────────────────────────────────────────────────────

  const columns: Column<AdminUser>[] = useMemo(
    () => [
      {
        key: 'member',
        label: t('members.col_name'),
        render: (user: AdminUser) => (
          <div className="flex items-center gap-3">
            <Badge
              content=""
              color={user.status === 'active' ? 'success' : user.status === 'suspended' ? 'danger' : 'warning'}
              placement="bottom-right"
              shape="circle"
              size="sm"
              isInvisible={!user.status}
            >
              <Avatar
                src={resolveAvatarUrl(user.avatar_url || user.avatar) || undefined}
                name={user.name}
                size="sm"
                className="h-9 w-9"
              />
            </Badge>
            <div className="min-w-0">
              <p className="text-sm font-medium text-foreground truncate">{user.name}</p>
              <p className="text-xs text-muted truncate">{user.email}</p>
            </div>
          </div>
        ),
      },
      {
        key: 'status',
        label: t('members.col_status'),
        render: (user: AdminUser) => (
          <div className="flex flex-col gap-1">
            <Chip
              size="sm"
              variant="tertiary"
              color={STATUS_COLOR[user.status] ?? 'default'}
              className="capitalize"
            >
              {t(`status.${user.status}`)}
            </Chip>
            {user.onboarding_completed === false && user.status !== 'pending' && (
              <Chip size="sm" variant="soft" color="warning" className="text-xs">
                <span className="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />
                <Chip.Label>{t('members.onboarding_incomplete')}</Chip.Label>
              </Chip>
            )}
          </div>
        ),
      },
      {
        key: 'role',
        label: t('members.col_role'),
        render: (user: AdminUser) => (
          <Chip
            size="sm"
            variant="tertiary"
            color={user.role === 'member' ? 'default' : 'accent'}
          >
            {t(`members.role_${user.role}`, { defaultValue: user.role })}
          </Chip>
        ),
      },
      {
        key: 'verified',
        label: t('members.col_verified'),
        render: (user: AdminUser) => (
          user.email_verified_at ? (
            <Chip size="sm" variant="tertiary" color="success">
              <MailCheck size={12} />
              <Chip.Label>{t('members.email_verified')}</Chip.Label>
            </Chip>
          ) : (
            <Chip size="sm" variant="tertiary" color="warning">
              <MailX size={12} />
              <Chip.Label>{t('members.email_unverified')}</Chip.Label>
            </Chip>
          )
        ),
      },
      {
        key: 'balance',
        label: t('members.col_balance'),
        render: (user: AdminUser) => (
          <div className="flex items-center gap-1.5">
            <Coins size={14} className="text-muted" />
            <span className="text-sm font-medium">
              {typeof user.balance === 'number' ? user.balance.toLocaleString() : '0'}
            </span>
            <span className="text-xs text-muted">{t('members.hours_short')}</span>
          </div>
        ),
      },
      {
        key: 'last_active_at',
        label: t('members.col_last_active'),
        render: (user: AdminUser) => (
          <Tooltip content={user.last_active_at ? formatServerDateTime(user.last_active_at) : t('members.time_never')}>
            <div className="flex items-center gap-1.5 text-sm text-muted">
              <Clock size={14} />
              <span>{timeAgo(user.last_active_at)}</span>
            </div>
          </Tooltip>
        ),
      },
      {
        key: 'created_at',
        label: t('members.col_joined'),
        sortable: true,
        render: (user: AdminUser) => (
          <span className="text-sm text-muted">
            {formatServerDate(user.created_at)}
          </span>
        ),
      },
      {
        key: 'actions',
        label: '',
        render: (user: AdminUser) => (
          <div className="flex items-center gap-1">
            {/* Quick note button */}
            <Tooltip content={t('members.notes')}>
              <Button
                isIconOnly
                variant="light"
                size="sm"
                onPress={() => openNotes(user)}
                aria-label={t('members.open_notes_for', { name: user.name })}
              >
                <StickyNote size={15} className="text-muted" />
              </Button>
            </Tooltip>

            {/* Context menu */}
            <Dropdown>
              <DropdownTrigger>
                <Button isIconOnly variant="light" size="sm" aria-label={t('members.col_actions')}>
                  <MoreVertical size={16} />
                </Button>
              </DropdownTrigger>
              <DropdownMenu aria-label={t('members.col_actions')}>
                <DropdownItem
                  key="details" id="details"
                  startContent={<IdCard size={14} />}
                  onPress={() => setDetailUserId(user.id)}
                >
                  {t('members.view_details')}
                </DropdownItem>
                <DropdownItem
                  key="view" id="view"
                  startContent={<ExternalLink size={14} />}
                  onPress={() => window.open(tenantPath(`/profile/${user.id}`), '_blank')}
                >
                  {t('members.view_profile')}
                </DropdownItem>
                <DropdownItem
                  key="message" id="message"
                  startContent={<Send size={14} />}
                  onPress={() => window.open(tenantPath(`/messages?to=${user.id}`), '_blank')}
                >
                  {t('members.send_message')}
                </DropdownItem>
                <DropdownItem
                  key="notes" id="notes"
                  startContent={<StickyNote size={14} />}
                  onPress={() => openNotes(user)}
                >
                  {t('members.notes')}
                </DropdownItem>
                <DropdownItem
                  key="vetting" id="vetting"
                  startContent={<ShieldCheck size={14} />}
                  onPress={() => window.open(tenantPath(`/broker/vetting?user_id=${user.id}`), '_self')}
                >
                  {t('members.check_vetting')}
                </DropdownItem>
                {/* Status-action item is omitted entirely for banned members
                    (broker can't unban — that's an admin-only action). For
                    other statuses we render an action that maps to the
                    appropriate state transition. */}
                {user.status !== 'banned' ? (
                  <DropdownItem
                    key="status-action" id="status-action"
                    startContent={
                      user.status === 'pending' ? <UserCheck size={14} /> :
                      user.status === 'active' ? <UserX size={14} /> :
                      <RotateCcw size={14} />
                    }
                    color={user.status === 'active' ? 'danger' : user.status === 'pending' ? 'success' : 'default'}
                    className={user.status === 'active' ? 'text-danger' : user.status === 'pending' ? 'text-success' : ''}
                    onPress={() => {
                      if (user.status === 'pending') setConfirmAction({ type: 'approve', user });
                      else if (user.status === 'active') setConfirmAction({ type: 'suspend', user });
                      else if (user.status === 'suspended') handleReactivate(user);
                    }}
                  >
                    {user.status === 'pending' ? t('members.approve') :
                     user.status === 'active' ? t('members.suspend') :
                     t('members.reactivate')}
                  </DropdownItem>
                ) : null}
              </DropdownMenu>
            </Dropdown>
          </div>
        ),
      },
    ],
    [t, tenantPath, timeAgo, handleReactivate, openNotes],
  );

  // ─── Render ───────────────────────────────────────────────────────────────

  return (
    <div className="max-w-7xl mx-auto space-y-4">
      <PageHeader
        title={t('members.title')}
        description={t('members.description')}
      />

      <Tabs
        aria-label={t('members.tabs_aria')}
        selectedKey={activeTab}
        onSelectionChange={handleTabChange}
        variant="underlined"
        classNames={{ tabList: 'mb-4' }}
      >
        <Tab key="all" title={t('members.tab_all')} />
        <Tab key="pending" title={t('members.tab_pending')} />
        <Tab key="active" title={t('members.tab_active')} />
        <Tab key="suspended" title={t('members.tab_suspended')} />
        <Tab key="never_logged_in" title={t('members.tab_never_logged_in')} />
        <Tab key="onboarding_incomplete" title={t('members.tab_onboarding_incomplete')} />
      </Tabs>

      {selectedIds.size > 0 && (
        <div className="flex flex-wrap items-center gap-2 rounded-lg border border-divider bg-surface-secondary px-3 py-2">
          <span className="text-sm font-medium text-foreground">
            {t('members.bulk_selected', { count: selectedIds.size })}
          </span>
          <div className="flex-1" />
          <Button
            size="sm"
            color="success"
            variant="flat"
            startContent={<UserCheck size={14} />}
            onPress={handleBulkApprove}
            isLoading={bulkLoading}
          >
            {t('members.bulk_approve')}
          </Button>
          <Button
            size="sm"
            color="danger"
            variant="flat"
            startContent={<UserX size={14} />}
            onPress={handleBulkSuspend}
            isLoading={bulkLoading}
          >
            {t('members.bulk_suspend')}
          </Button>
          <Button
            size="sm"
            variant="light"
            isIconOnly
            onPress={clearSelection}
            aria-label={t('members.bulk_clear')}
          >
            <X size={16} />
          </Button>
        </div>
      )}

      <DataTable<AdminUser>
        columns={columns}
        data={members}
        keyField="id"
        isLoading={loading}
        selectable
        selectedKeys={selectedIds}
        onSelectionChange={setSelectedIds}
        searchable
        searchPlaceholder={t('members.search_placeholder')}
        totalItems={total}
        page={page}
        pageSize={PAGE_SIZE}
        onPageChange={setPage}
        onSearch={handleSearch}
        onRefresh={fetchMembers}
        emptyContent={
          <div className="flex flex-col items-center py-8">
            <p className="text-muted">{t('common.no_data')}</p>
          </div>
        }
      />

      {/* Approve confirmation */}
      <ConfirmModal
        isOpen={confirmAction?.type === 'approve'}
        onClose={() => setConfirmAction(null)}
        onConfirm={handleApprove}
        title={t('members.confirm_approve_title')}
        message={t('members.confirm_approve_message')}
        confirmLabel={t('members.approve')}
        cancelLabel={t('common.cancel')}
        confirmColor="primary"
        isLoading={actionLoading}
      />

      {/* Suspend confirmation */}
      <ConfirmModal
        isOpen={confirmAction?.type === 'suspend'}
        onClose={() => setConfirmAction(null)}
        onConfirm={handleSuspend}
        title={t('members.confirm_suspend_title')}
        message={t('members.confirm_suspend_message')}
        confirmLabel={t('members.suspend')}
        cancelLabel={t('common.cancel')}
        confirmColor="danger"
        isLoading={actionLoading}
      />

      {/* Notes Modal */}
      <Modal
        isOpen={!!notesUser}
        onClose={() => setNotesUser(null)}
        size="lg"
        scrollBehavior="inside"
      >
        <ModalContent>
          {notesUser && (
            <>
              <ModalHeader className="flex items-center gap-3">
                <Avatar
                  src={resolveAvatarUrl(notesUser.avatar_url || notesUser.avatar) || undefined}
                  name={notesUser.name}
                  size="sm"
                />
                <div>
                  <p className="text-base font-semibold">{t('members.notes_for', { name: notesUser.name })}</p>
                  <p className="text-xs text-muted font-normal">{notesUser.email}</p>
                </div>
              </ModalHeader>
              <ModalBody>
                {/* Add note */}
                <div className="space-y-2">
                  <Select
                    aria-label={t('members.note_category_label')}
                    size="sm"
                    variant="bordered"
                    selectedKeys={[noteCategory]}
                    onSelectionChange={(keys) => setNoteCategory((Array.from(keys)[0] as string) ?? 'general')}
                    className="max-w-[220px]"
                  >
                    {NOTE_CATEGORIES.map((cat) => (
                      <SelectItem key={cat} id={cat}>{t(`members.note_category_${cat}`)}</SelectItem>
                    ))}
                  </Select>
                  <div className="flex gap-2">
                    <Textarea
                      placeholder={t('members.note_placeholder')}
                      value={newNote}
                      onValueChange={setNewNote}
                      minRows={2}
                      maxRows={4}
                      className="flex-1"
                    />
                    <Button
                      color="primary"
                      isIconOnly
                      isLoading={addingNote}
                      isDisabled={!newNote.trim()}
                      onPress={handleAddNote}
                      className="self-end"
                      aria-label={t('members.send_note')}
                    >
                      <Send size={16} />
                    </Button>
                  </div>
                </div>

                {/* Notes list — pinned first */}
                {notesLoading ? (
                  <div className="py-8 text-center text-muted">{t('common.loading')}</div>
                ) : notes.length === 0 ? (
                  <div className="py-8 text-center text-muted">
                    <StickyNote size={32} className="mx-auto mb-2 opacity-30" />
                    <p>{t('members.no_notes')}</p>
                  </div>
                ) : (
                  <div className="space-y-3 mt-2">
                    {[...notes].sort((a, b) => Number(b.is_pinned ?? false) - Number(a.is_pinned ?? false)).map((note) => (
                      <div key={note.id} className={`rounded-lg p-3 ${note.is_pinned ? 'border border-accent/20 bg-accent/10' : 'bg-surface-secondary'}`}>
                        {editingNoteId === note.id ? (
                          <div className="space-y-2">
                            <Select
                              aria-label={t('members.note_category_label')}
                              size="sm"
                              variant="bordered"
                              selectedKeys={[editingCategory]}
                              onSelectionChange={(keys) => setEditingCategory((Array.from(keys)[0] as string) ?? 'general')}
                              className="max-w-[220px]"
                            >
                              {NOTE_CATEGORIES.map((cat) => (
                                <SelectItem key={cat} id={cat}>{t(`members.note_category_${cat}`)}</SelectItem>
                              ))}
                            </Select>
                            <Textarea value={editingContent} onValueChange={setEditingContent} minRows={2} maxRows={5} variant="bordered" />
                            <div className="flex gap-2">
                              <Button size="sm" color="primary" isLoading={savingNote} isDisabled={!editingContent.trim()} startContent={<Check size={14} />} onPress={handleUpdateNote}>
                                {t('members.note_save')}
                              </Button>
                              <Button size="sm" variant="flat" isDisabled={savingNote} onPress={cancelEditNote}>
                                {t('common.cancel')}
                              </Button>
                            </div>
                          </div>
                        ) : (
                          <>
                            <div className="flex items-start justify-between gap-2">
                              <p className="flex-1 whitespace-pre-wrap text-sm text-foreground">{note.content}</p>
                              <div className="flex shrink-0 items-center gap-0.5">
                                <Tooltip content={note.is_pinned ? t('members.note_unpin') : t('members.note_pin')}>
                                  <Button isIconOnly size="sm" variant="light" isLoading={noteBusyId === note.id} onPress={() => handleTogglePin(note)} aria-label={note.is_pinned ? t('members.note_unpin') : t('members.note_pin')}>
                                    <Pin size={13} className={note.is_pinned ? 'fill-current text-accent' : 'text-muted'} />
                                  </Button>
                                </Tooltip>
                                <Tooltip content={t('members.note_edit')}>
                                  <Button isIconOnly size="sm" variant="light" onPress={() => startEditNote(note)} aria-label={t('members.note_edit')}>
                                    <Pencil size={13} className="text-muted" />
                                  </Button>
                                </Tooltip>
                                <Tooltip content={t('members.note_delete')}>
                                  <Button isIconOnly size="sm" variant="light" color="danger" isLoading={noteBusyId === note.id} onPress={() => handleDeleteNote(note.id)} aria-label={t('members.note_delete')}>
                                    <Trash2 size={13} />
                                  </Button>
                                </Tooltip>
                              </div>
                            </div>
                            <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted">
                              <span>{note.author_name || note.author?.name || t('members.note_system_author')}</span>
                              <span>&middot;</span>
                              <span>{formatServerDateTime(note.created_at)}</span>
                              {note.category && (
                                <Chip size="sm" variant="tertiary" className="text-xs">{t(`members.note_category_${note.category}`, { defaultValue: note.category })}</Chip>
                              )}
                            </div>
                          </>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={() => setNotesUser(null)}>
                  {t('common.cancel')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Member detail modal — operational actions, safe edits, compliance view */}
      <MemberDetailModal
        userId={detailUserId}
        onClose={() => setDetailUserId(null)}
        onChanged={fetchMembers}
      />
    </div>
  );
}
