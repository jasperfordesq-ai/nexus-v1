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
import {
  Tabs,
  Tab,
  Chip,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Button,
  Avatar,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
  Badge,
  Tooltip,
} from '@heroui/react';
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
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { adminUsers, adminCrm } from '@/admin/api/adminApi';
import type { AdminUser } from '@/admin/api/types';
import { DataTable, PageHeader, ConfirmModal } from '@/admin/components';
import type { Column } from '@/admin/components';
import { resolveAvatarUrl } from '@/lib/helpers';

// ─────────────────────────────────────────────────────────────────────────────
// Types & Constants
// ─────────────────────────────────────────────────────────────────────────────

type StatusTab = 'all' | 'pending' | 'active' | 'suspended';

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

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function useTimeAgo() {
  const { t } = useTranslation('broker');
  return (dateStr: string | null | undefined): string => {
    if (!dateStr) return t('members.time_never');
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return t('members.time_just_now');
    if (mins < 60) return t('members.time_minutes_ago', { count: mins });
    const hours = Math.floor(mins / 60);
    if (hours < 24) return t('members.time_hours_ago', { count: hours });
    const days = Math.floor(hours / 24);
    if (days < 30) return t('members.time_days_ago', { count: days });
    return new Date(dateStr).toLocaleDateString();
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
  usePageTitle(t('members.title') + ' - Broker');

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

  // Notes drawer state
  const [notesUser, setNotesUser] = useState<AdminUser | null>(null);
  const [notes, setNotes] = useState<MemberNote[]>([]);
  const [notesLoading, setNotesLoading] = useState(false);
  const [newNote, setNewNote] = useState('');
  const [addingNote, setAddingNote] = useState(false);

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
        category: 'broker',
      });
      if (res.success) {
        toast.success(t('members.note_added'));
        setNewNote('');
        // Refresh notes
        openNotes(notesUser);
      }
    } catch {
      toast.error(t('members.action_failed'));
    } finally {
      setAddingNote(false);
    }
  }, [notesUser, newNote, toast, t, openNotes]);

  // ─── Tab change / search ──────────────────────────────────────────────────

  const handleTabChange = useCallback((key: React.Key) => {
    setActiveTab(key as StatusTab);
    setPage(1);
  }, []);

  const handleSearch = useCallback((query: string) => {
    setSearch(query);
    setPage(1);
  }, []);

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

  const handleReactivate = useCallback(
    async (user: AdminUser) => {
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
      }
    },
    [toast, t, fetchMembers],
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
              <p className="text-xs text-default-400 truncate">{user.email}</p>
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
              variant="flat"
              color={STATUS_COLOR[user.status] ?? 'default'}
              className="capitalize"
            >
              {t(`status.${user.status}`, { defaultValue: user.status })}
            </Chip>
            {user.onboarding_completed === false && user.status !== 'pending' && (
              <Chip size="sm" variant="dot" color="warning" className="text-xs">
                {t('members.onboarding_incomplete')}
              </Chip>
            )}
          </div>
        ),
      },
      {
        key: 'balance',
        label: t('members.col_balance'),
        render: (user: AdminUser) => (
          <div className="flex items-center gap-1.5">
            <Coins size={14} className="text-default-400" />
            <span className="text-sm font-medium">
              {typeof user.balance === 'number' ? user.balance.toLocaleString() : '0'}
            </span>
            <span className="text-xs text-default-400">hrs</span>
          </div>
        ),
      },
      {
        key: 'last_active_at',
        label: t('members.col_last_active'),
        render: (user: AdminUser) => (
          <Tooltip content={user.last_active_at ? new Date(user.last_active_at).toLocaleString() : 'Never'}>
            <div className="flex items-center gap-1.5 text-sm text-default-500">
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
          <span className="text-sm text-default-500">
            {new Date(user.created_at).toLocaleDateString()}
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
              >
                <StickyNote size={15} className="text-default-400" />
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
                  key="view"
                  startContent={<ExternalLink size={14} />}
                  onPress={() => window.open(tenantPath(`/profile/${user.id}`), '_blank')}
                >
                  {t('members.view_profile')}
                </DropdownItem>
                <DropdownItem
                  key="message"
                  startContent={<Send size={14} />}
                  onPress={() => window.open(tenantPath(`/messages?to=${user.id}`), '_blank')}
                >
                  {t('members.send_message')}
                </DropdownItem>
                <DropdownItem
                  key="notes"
                  startContent={<StickyNote size={14} />}
                  onPress={() => openNotes(user)}
                >
                  {t('members.notes')}
                </DropdownItem>
                <DropdownItem
                  key="vetting"
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
                    key="status-action"
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
    [t, tenantPath, handleReactivate, openNotes],
  );

  // ─── Render ───────────────────────────────────────────────────────────────

  return (
    <div className="max-w-7xl mx-auto space-y-4">
      <PageHeader
        title={t('members.title')}
        description={t('members.description')}
      />

      <Tabs
        selectedKey={activeTab}
        onSelectionChange={handleTabChange}
        variant="underlined"
        classNames={{ tabList: 'mb-4' }}
      >
        <Tab key="all" title={t('members.tab_all')} />
        <Tab key="pending" title={t('members.tab_pending')} />
        <Tab key="active" title={t('members.tab_active')} />
        <Tab key="suspended" title={t('members.tab_suspended')} />
      </Tabs>

      <DataTable<AdminUser>
        columns={columns}
        data={members}
        keyField="id"
        isLoading={loading}
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
            <p className="text-default-400">{t('common.no_data')}</p>
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
                  <p className="text-xs text-default-400 font-normal">{notesUser.email}</p>
                </div>
              </ModalHeader>
              <ModalBody>
                {/* Add note */}
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
                  >
                    <Send size={16} />
                  </Button>
                </div>

                {/* Notes list */}
                {notesLoading ? (
                  <div className="py-8 text-center text-default-400">{t('common.loading')}</div>
                ) : notes.length === 0 ? (
                  <div className="py-8 text-center text-default-400">
                    <StickyNote size={32} className="mx-auto mb-2 opacity-30" />
                    <p>{t('members.no_notes')}</p>
                  </div>
                ) : (
                  <div className="space-y-3 mt-2">
                    {notes.map((note) => (
                      <div key={note.id} className="rounded-lg bg-default-50 p-3">
                        <p className="text-sm text-foreground whitespace-pre-wrap">{note.content}</p>
                        <div className="mt-2 flex items-center gap-2 text-xs text-default-400">
                          <span>{note.author_name || note.author?.name || 'System'}</span>
                          <span>&middot;</span>
                          <span>{new Date(note.created_at).toLocaleString()}</span>
                          {note.category && (
                            <>
                              <span>&middot;</span>
                              <Chip size="sm" variant="flat" className="text-xs capitalize">{note.category}</Chip>
                            </>
                          )}
                        </div>
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
    </div>
  );
}
