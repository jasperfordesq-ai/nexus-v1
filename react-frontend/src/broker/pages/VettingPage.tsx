// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Vetting Records Management
 * Manage DBS/Garda vetting records for safeguarding compliance (TOL2).
 * Parity: PHP AdminVettingApiController
 */

import { useState, useCallback, useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useSearchParams } from 'react-router-dom';
import {
  Tabs,
  Tab,
  Button,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Select,
  SelectItem,
  Textarea,
  Checkbox,
  Avatar,
  Spinner,
} from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import ShieldCheck from 'lucide-react/icons/shield-check';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import Clock from 'lucide-react/icons/clock';
import Plus from 'lucide-react/icons/plus';
import Check from 'lucide-react/icons/check';
import X from 'lucide-react/icons/x';
import Search from 'lucide-react/icons/search';
import FileText from 'lucide-react/icons/file-text';
import Users from 'lucide-react/icons/users';
import Baby from 'lucide-react/icons/baby';
import HeartHandshake from 'lucide-react/icons/heart-handshake';
import Trash2 from 'lucide-react/icons/trash-2';
import Eye from 'lucide-react/icons/eye';
import Pencil from 'lucide-react/icons/pencil';
import Upload from 'lucide-react/icons/upload';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import { adminVetting, adminUsers } from '@/admin/api/adminApi';
import { DataTable, StatCard, PageHeader, ConfirmModal, EmptyState, type Column } from '@/admin/components';
import type { VettingRecord, VettingStats } from '@/admin/api/types';

const VETTING_TYPE_KEYS: Record<string, string> = {
  dbs_basic: 'type_dbs_basic',
  dbs_standard: 'type_dbs_standard',
  dbs_enhanced: 'type_dbs_enhanced',
  garda_vetting: 'type_garda_vetting',
  access_ni: 'type_access_ni',
  pvg_scotland: 'type_pvg_scotland',
  international: 'type_international',
  other: 'type_other',
};

const STATUS_COLOR_MAP: Record<string, 'warning' | 'success' | 'danger' | 'primary' | 'default'> = {
  pending: 'warning',
  submitted: 'primary',
  verified: 'success',
  expired: 'danger',
  rejected: 'danger',
  revoked: 'default',
};

const SEARCH_DEBOUNCE_MS = 300;

/**
 * MySQL DATE/DATETIME columns are returned as bare strings without a zone
 * marker; JS `new Date()` parses bare datetime strings as LOCAL time, which
 * shifts displayed dates across the day boundary for users far from server
 * timezone. Promote bare strings to ISO-8601 UTC before parsing. For pure
 * date columns (issue_date, expiry_date) the issue is more subtle but the
 * day-boundary math (days-until-expiry) is sensitive to it.
 */
function parseServerTimestamp(value: string | null | undefined): Date | null {
  if (!value) return null;
  if (/[zZ]|[+-]\d{2}:?\d{2}$/.test(value)) return new Date(value);
  // YYYY-MM-DD (date only) — anchor at UTC midday so day-of-month is
  // stable across timezones up to ±12h. YYYY-MM-DD HH:MM:SS — promote
  // to ISO with Z.
  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return new Date(value + 'T12:00:00Z');
  }
  const d = new Date(value.replace(' ', 'T') + 'Z');
  return Number.isNaN(d.getTime()) ? null : d;
}

function formatServerDate(value: string | null | undefined): string {
  const d = parseServerTimestamp(value);
  return d ? d.toLocaleDateString() : '—';
}

function formatServerDateTime(value: string | null | undefined): string {
  const d = parseServerTimestamp(value);
  return d ? d.toLocaleString() : '—';
}

interface UserSearchResult {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
}

export function VettingRecords() {
  const { t } = useTranslation('broker');
  usePageTitle(t('vetting.title'));
  const { tenantPath } = useTenant();
  const toast = useToast();

  const getTypeLabel = (key: string): string => {
    const tKey = VETTING_TYPE_KEYS[key];
    return tKey ? t(`vetting.${tKey}`) : key;
  };

  // Status filter is mirrored to the `?status=` URL param so the broker
  // dashboard stat cards can deep-link straight into a filtered view and
  // the browser back button round-trips correctly.
  // 'pending_review' is the union of literal-pending + submitted — both
  // are pre-verification states the broker still owns. This is what the
  // broker dashboard's "Vetting Pending" tile counts and what the
  // "Pending Review" stat card on this page surfaces. The narrower
  // 'pending' / 'submitted' filters are still available for drill-down.
  const VETTING_STATUSES = [
    'all', 'pending_review', 'pending', 'submitted', 'verified', 'expired', 'expiring_soon', 'rejected',
  ] as const;
  type VettingStatus = (typeof VETTING_STATUSES)[number];
  const [searchParams, setSearchParams] = useSearchParams();
  const urlStatus = searchParams.get('status') as VettingStatus | null;
  const statusFilter: VettingStatus =
    urlStatus && VETTING_STATUSES.includes(urlStatus) ? urlStatus : 'all';
  const setStatusFilter = useCallback(
    (next: VettingStatus) => {
      setSearchParams(
        (prev) => {
          const params = new URLSearchParams(prev);
          if (next === 'all') {
            params.delete('status');
          } else {
            params.set('status', next);
          }
          return params;
        },
        { replace: true }
      );
    },
    [setSearchParams]
  );

  // `?user_id=` is set by the "Manage Vetting" link from User Edit so the
  // page lands pre-filtered to that member's records.
  const userIdFilter = searchParams.get('user_id');

  // List state
  const [items, setItems] = useState<VettingRecord[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [searchQuery, setSearchQuery] = useState('');
  // Debounce the search input so we don't fire a network request on every
  // keystroke (300ms feels responsive without spamming the API).
  const [debouncedSearch, setDebouncedSearch] = useState('');
  useEffect(() => {
    const handle = setTimeout(() => setDebouncedSearch(searchQuery), 300);
    return () => clearTimeout(handle);
  }, [searchQuery]);

  // Stats
  const [stats, setStats] = useState<VettingStats | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);
  const [statsError, setStatsError] = useState(false);

  // Create modal
  const [createOpen, setCreateOpen] = useState(false);
  const [createLoading, setCreateLoading] = useState(false);
  const [createForm, setCreateForm] = useState({
    user_id: '',
    vetting_type: 'dbs_basic' as VettingRecord['vetting_type'],
    reference_number: '',
    issue_date: '',
    expiry_date: '',
    works_with_children: false,
    works_with_vulnerable_adults: false,
    requires_enhanced_check: false,
    notes: '',
  });

  // User search for create modal (#8)
  const [userSearchQuery, setUserSearchQuery] = useState('');
  const [userSearchResults, setUserSearchResults] = useState<UserSearchResult[]>([]);
  const [userSearchLoading, setUserSearchLoading] = useState(false);
  const [selectedUser, setSelectedUser] = useState<UserSearchResult | null>(null);
  const userSearchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Edit modal (#9)
  const [editItem, setEditItem] = useState<VettingRecord | null>(null);
  const [editLoading, setEditLoading] = useState(false);
  const [editForm, setEditForm] = useState({
    vetting_type: 'dbs_basic' as VettingRecord['vetting_type'],
    reference_number: '',
    issue_date: '',
    expiry_date: '',
    works_with_children: false,
    works_with_vulnerable_adults: false,
    requires_enhanced_check: false,
    notes: '',
  });

  // Reject modal
  const [rejectModal, setRejectModal] = useState<VettingRecord | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [rejectLoading, setRejectLoading] = useState(false);

  // View modal
  const [viewItem, setViewItem] = useState<VettingRecord | null>(null);

  // Delete confirm
  const [deleteItem, setDeleteItem] = useState<VettingRecord | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  // Verify loading tracker
  const [verifyingId, setVerifyingId] = useState<number | null>(null);

  // Document upload (#10) — uploadingId tracks per-record loading state for
  // the upload buttons. The actual <input type="file"> is spawned on demand
  // by openFilePickerForRecord so no shared ref is needed.
  const [uploadingId, setUploadingId] = useState<number | null>(null);

  // Bulk actions
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [bulkAction, setBulkAction] = useState<'verify' | 'reject' | 'delete' | null>(null);
  const [bulkLoading, setBulkLoading] = useState(false);
  const [bulkRejectReason, setBulkRejectReason] = useState('');

  // User search effect (#8)
  useEffect(() => {
    if (!userSearchQuery.trim() || userSearchQuery.trim().length < 2) {
      setUserSearchResults([]);
      return;
    }
    if (userSearchTimeoutRef.current) {
      clearTimeout(userSearchTimeoutRef.current);
    }
    userSearchTimeoutRef.current = setTimeout(async () => {
      setUserSearchLoading(true);
      try {
        const res = await adminUsers.list({ search: userSearchQuery.trim(), limit: 8 });
        if (res.success && Array.isArray(res.data)) {
          setUserSearchResults(res.data.map((u: Record<string, unknown>) => ({
            id: u.id as number,
            first_name: u.first_name as string,
            last_name: u.last_name as string,
            email: u.email as string,
          })));
        }
      } catch {
        // Non-critical
      } finally {
        setUserSearchLoading(false);
      }
    }, SEARCH_DEBOUNCE_MS);

    return () => {
      if (userSearchTimeoutRef.current) {
        clearTimeout(userSearchTimeoutRef.current);
      }
    };
  }, [userSearchQuery]);

  const loadStats = useCallback(async () => {
    setStatsLoading(true);
    setStatsError(false);
    try {
      const res = await adminVetting.stats();
      if (res.success && res.data) {
        setStats(res.data as VettingStats);
      } else {
        // Surface failure rather than silently zeroing — same lesson as
        // the broker dashboard's _partial flag. Vetting counts feed the
        // operational picture; a clean dashboard during outages hides
        // expiring records that need attention.
        setStatsError(true);
      }
    } catch {
      setStatsError(true);
    } finally {
      setStatsLoading(false);
    }
  }, []);

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = { page, per_page: 25 };
      if (statusFilter === 'expiring_soon') {
        params.expiring_soon = true;
      } else if (statusFilter !== 'all') {
        params.status = statusFilter;
      }
      if (debouncedSearch.trim()) {
        params.search = debouncedSearch.trim();
      }
      if (userIdFilter) {
        params.user_id = userIdFilter;
      }

      const res = await adminVetting.list(params as Parameters<typeof adminVetting.list>[0]);
      if (res.success && Array.isArray(res.data)) {
        setItems(res.data as VettingRecord[]);
        const meta = res.meta as Record<string, unknown> | undefined;
        setTotal(Number(meta?.total ?? meta?.total_items ?? res.data.length));
      }
    } catch {
      toast.error(t('vetting.toast_load_failed'));
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter, debouncedSearch, userIdFilter, toast, t])


  useEffect(() => {
    loadStats();
  }, [loadStats]);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleVerify = async (item: VettingRecord) => {
    setVerifyingId(item.id);
    try {
      const res = await adminVetting.verify(item.id);
      if (res?.success) {
        toast.success(t('vetting.verified_success'));
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || t('vetting.toast_verify_failed'));
      }
    } catch {
      toast.error(t('vetting.toast_verify_failed'));
    } finally {
      setVerifyingId(null);
    }
  };

  const handleReject = async () => {
    if (!rejectModal || !rejectReason.trim()) {
      toast.error(t('vetting.toast_reject_reason_required'));
      return;
    }
    setRejectLoading(true);
    try {
      const res = await adminVetting.reject(rejectModal.id, rejectReason);
      if (res?.success) {
        toast.success(t('vetting.rejected_success'));
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || t('vetting.toast_reject_failed'));
      }
    } catch {
      toast.error(t('vetting.toast_reject_failed'));
    } finally {
      setRejectLoading(false);
      setRejectModal(null);
      setRejectReason('');
    }
  };

  const handleDelete = async () => {
    if (!deleteItem) return;
    setDeleteLoading(true);
    try {
      const res = await adminVetting.destroy(deleteItem.id);
      if (res?.success) {
        toast.success(t('vetting.toast_deleted'));
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || t('vetting.toast_delete_failed'));
      }
    } catch {
      toast.error(t('vetting.toast_delete_failed'));
    } finally {
      setDeleteLoading(false);
      setDeleteItem(null);
    }
  };

  const handleCreate = async () => {
    if (!createForm.user_id) {
      toast.error(t('vetting.toast_select_member'));
      return;
    }
    setCreateLoading(true);
    try {
      const payload: Record<string, unknown> = {
        user_id: Number(createForm.user_id),
        vetting_type: createForm.vetting_type,
        works_with_children: createForm.works_with_children,
        works_with_vulnerable_adults: createForm.works_with_vulnerable_adults,
        requires_enhanced_check: createForm.requires_enhanced_check,
      };
      if (createForm.reference_number) payload.reference_number = createForm.reference_number;
      if (createForm.issue_date) payload.issue_date = createForm.issue_date;
      if (createForm.expiry_date) payload.expiry_date = createForm.expiry_date;
      if (createForm.notes) payload.notes = createForm.notes;

      const res = await adminVetting.create(payload as Partial<VettingRecord>);
      if (res?.success || res?.data) {
        toast.success(t('vetting.toast_created'));
        setCreateOpen(false);
        resetCreateForm();
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || t('vetting.toast_create_failed'));
      }
    } catch {
      toast.error(t('vetting.toast_create_failed'));
    } finally {
      setCreateLoading(false);
    }
  };

  const resetCreateForm = () => {
    setCreateForm({
      user_id: '',
      vetting_type: 'dbs_basic',
      reference_number: '',
      issue_date: '',
      expiry_date: '',
      works_with_children: false,
      works_with_vulnerable_adults: false,
      requires_enhanced_check: false,
      notes: '',
    });
    setSelectedUser(null);
    setUserSearchQuery('');
    setUserSearchResults([]);
  };

  // #9: Edit handlers
  const openEditModal = (item: VettingRecord) => {
    setEditForm({
      vetting_type: item.vetting_type,
      reference_number: item.reference_number || '',
      issue_date: item.issue_date || '',
      expiry_date: item.expiry_date || '',
      works_with_children: !!item.works_with_children,
      works_with_vulnerable_adults: !!item.works_with_vulnerable_adults,
      requires_enhanced_check: !!item.requires_enhanced_check,
      notes: item.notes || '',
    });
    setEditItem(item);
  };

  const handleEdit = async () => {
    if (!editItem) return;
    setEditLoading(true);
    try {
      const payload: Record<string, unknown> = {
        vetting_type: editForm.vetting_type,
        reference_number: editForm.reference_number || null,
        issue_date: editForm.issue_date || null,
        expiry_date: editForm.expiry_date || null,
        works_with_children: editForm.works_with_children,
        works_with_vulnerable_adults: editForm.works_with_vulnerable_adults,
        requires_enhanced_check: editForm.requires_enhanced_check,
        notes: editForm.notes || null,
      };

      const res = await adminVetting.update(editItem.id, payload as Partial<VettingRecord>);
      if (res?.success) {
        toast.success(t('vetting.toast_updated'));
        setEditItem(null);
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || t('vetting.toast_update_failed'));
      }
    } catch {
      toast.error(t('vetting.toast_update_failed'));
    } finally {
      setEditLoading(false);
    }
  };

  // #10: Document upload handler
  const handleDocumentUpload = async (recordId: number, file: File) => {
    setUploadingId(recordId);
    try {
      const res = await adminVetting.uploadDocument(recordId, file);
      if (res?.success) {
        toast.success(t('vetting.toast_document_uploaded'));
        loadItems();
        // Refresh view modal if open
        if (viewItem?.id === recordId && res.data) {
          setViewItem(res.data as VettingRecord);
        }
      } else {
        toast.error(res?.error || t('vetting.toast_upload_failed'));
      }
    } catch {
      toast.error(t('vetting.toast_upload_failed'));
    } finally {
      setUploadingId(null);
    }
  };

  /**
   * Open a fresh file picker bound to a specific record id. Spawning a
   * disposable <input> per click guarantees the chosen file is paired with
   * the recordId captured in the button's click handler — no shared state
   * to race over if upload buttons are ever moved out of the single-record
   * view modal onto row actions.
   */
  const openFilePickerForRecord = useCallback((recordId: number) => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.pdf,.jpg,.jpeg,.png,.webp';
    input.style.display = 'none';
    input.onchange = () => {
      const file = input.files?.[0];
      if (file) {
        // Validate size client-side (matches backend 10MB limit at
        // AdminVettingController::uploadDocument) before burning bandwidth
        // on a doomed request.
        const MAX_BYTES = 10 * 1024 * 1024;
        if (file.size > MAX_BYTES) {
          toast.error(t('vetting.toast_file_too_large'));
        } else {
          void handleDocumentUpload(recordId, file);
        }
      }
      input.remove();
    };
    input.oncancel = () => input.remove();
    document.body.appendChild(input);
    input.click();
  // handleDocumentUpload changes only when its enclosing closure refs change
  // (toast/viewItem); we explicitly omit it from deps to keep this callback
  // stable, the recordId argument is passed in fresh on every invocation.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [toast, t]);

  // Bulk action handler
  const handleBulkAction = async () => {
    if (!bulkAction || selectedIds.size === 0) return;

    if (bulkAction === 'reject' && !bulkRejectReason.trim()) {
      toast.error(t('vetting.toast_bulk_reject_reason_required'));
      return;
    }

    setBulkLoading(true);
    try {
      const ids = Array.from(selectedIds).map(Number);
      const res = await adminVetting.bulk(ids, bulkAction, bulkAction === 'reject' ? bulkRejectReason : undefined);
      if (res?.success && res.data) {
        const d = res.data as { processed: number; failed: number };
        toast.success(t('vetting.toast_bulk_success', {
          count: d.processed,
          action: t(`vetting.bulk_action_${bulkAction === 'verify' ? 'verified' : bulkAction === 'reject' ? 'rejected' : 'deleted'}`),
          failedSuffix: d.failed > 0 ? t('vetting.toast_bulk_failed_suffix', { count: d.failed }) : '',
        }));
        setSelectedIds(new Set());
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || t('vetting.toast_bulk_failed'));
      }
    } catch {
      toast.error(t('vetting.toast_bulk_failed'));
    } finally {
      setBulkLoading(false);
      setBulkAction(null);
      setBulkRejectReason('');
    }
  };

  const columns: Column<VettingRecord>[] = [
    {
      key: 'member',
      label: t('vetting.col_member'),
      sortable: true,
      render: (item) => (
        <div className="flex items-center gap-2">
          <Avatar
            src={resolveAvatarUrl(item.avatar_url) || undefined}
            name={`${item.first_name} ${item.last_name}`}
            size="sm"
            className="shrink-0"
          />
          <div className="min-w-0">
            <p className="font-medium text-foreground truncate">
              {item.first_name} {item.last_name}
            </p>
            <p className="text-xs text-default-400 truncate">{item.email}</p>
          </div>
        </div>
      ),
    },
    {
      key: 'vetting_type',
      label: t('vetting.col_type'),
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat" color="primary">
          {getTypeLabel(item.vetting_type)}
        </Chip>
      ),
    },
    {
      key: 'status',
      label: t('vetting.col_status'),
      sortable: true,
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={STATUS_COLOR_MAP[item.status] || 'default'}
          className="capitalize"
        >
          {t(`status.${item.status}`, { defaultValue: item.status })}
        </Chip>
      ),
    },
    {
      key: 'reference_number',
      label: t('vetting.col_reference'),
      render: (item) => (
        <span className="text-sm text-default-600 font-mono">
          {item.reference_number || '—'}
        </span>
      ),
    },
    {
      key: 'issue_date',
      label: t('vetting.col_issue_date'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {formatServerDate(item.issue_date)}
        </span>
      ),
    },
    {
      key: 'expiry_date',
      label: t('vetting.col_expiry'),
      sortable: true,
      render: (item) => {
        const expiry = parseServerTimestamp(item.expiry_date);
        if (!expiry) return <span className="text-sm text-default-500">{'—'}</span>;
        const now = new Date();
        const daysUntilExpiry = Math.ceil((expiry.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
        const isExpiringSoon = daysUntilExpiry > 0 && daysUntilExpiry <= 30;
        const isExpired = daysUntilExpiry <= 0;

        return (
          <span className={`text-sm ${isExpired ? 'text-danger font-medium' : isExpiringSoon ? 'text-warning font-medium' : 'text-default-500'}`}>
            {expiry.toLocaleDateString()}
          </span>
        );
      },
    },
    {
      key: 'safeguarding',
      label: t('vetting.col_safeguarding'),
      render: (item) => (
        <div className="flex gap-1">
          {item.works_with_children && (
            <Chip size="sm" variant="dot" color="warning" startContent={<Baby size={10} />}>
              {t('vetting.works_with_children')}
            </Chip>
          )}
          {item.works_with_vulnerable_adults && (
            <Chip size="sm" variant="dot" color="warning" startContent={<HeartHandshake size={10} />}>
              {t('vetting.works_with_vulnerable_adults')}
            </Chip>
          )}
          {!item.works_with_children && !item.works_with_vulnerable_adults && (
            <span className="text-sm text-default-400">{'—'}</span>
          )}
        </div>
      ),
    },
    {
      key: 'actions',
      label: t('vetting.col_actions'),
      render: (item) => (
        <div className="flex gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            onPress={() => setViewItem(item)}
            aria-label={t('vetting.action_view_details')}
          >
            <Eye size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            onPress={() => openEditModal(item)}
            aria-label={t('vetting.action_edit_record')}
          >
            <Pencil size={14} />
          </Button>
          {(item.status === 'pending' || item.status === 'submitted') && (
            <>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="success"
                isLoading={verifyingId === item.id}
                onPress={() => handleVerify(item)}
                aria-label={t('vetting.action_verify_record')}
              >
                <Check size={14} />
              </Button>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="danger"
                onPress={() => { setRejectModal(item); setRejectReason(''); }}
                aria-label={t('vetting.action_reject_record')}
              >
                <X size={14} />
              </Button>
            </>
          )}
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            onPress={() => setDeleteItem(item)}
            aria-label={t('vetting.action_delete_record')}
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('vetting.page_title')}
        description={t('vetting.page_description')}
        actions={
          <div className="flex gap-2">
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              size="sm"
              onPress={() => { resetCreateForm(); setCreateOpen(true); }}
            >
              {t('vetting.add_record')}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/broker')}
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              size="sm"
            >
              {t('vetting.back')}
            </Button>
          </div>
        }
      />

      {statsError && (
        <div className="rounded-lg border border-warning-200 bg-warning-50/50 p-3 flex items-start gap-3 mb-4">
          <ShieldAlert size={20} className="text-warning shrink-0 mt-0.5" />
          <div className="flex-1 text-sm">
            <p className="font-medium text-warning-700">{t('vetting.stats_error_title')}</p>
            <p className="text-default-600">{t('vetting.stats_error_body')}</p>
          </div>
          <Button size="sm" variant="flat" color="warning" onPress={loadStats}>
            {t('vetting.retry')}
          </Button>
        </div>
      )}

      {/* Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <StatCard
          label={t('vetting.stat_total_records')}
          value={stats?.total ?? 0}
          icon={FileText}
          color="primary"
          loading={statsLoading}
        />
        <StatCard
          label={t('vetting.stat_pending_review')}
          // pending_review = pending + submitted (pre-verification states
          // the broker still owns). Falls back to legacy `pending` for
          // backwards-compat with API responses that pre-date the field.
          value={stats?.pending_review ?? stats?.pending ?? 0}
          icon={Clock}
          color="warning"
          loading={statsLoading}
        />
        <StatCard
          label={t('vetting.stat_verified')}
          value={stats?.verified ?? 0}
          icon={ShieldCheck}
          color="success"
          loading={statsLoading}
        />
        <StatCard
          label={t('vetting.stat_expiring_soon')}
          value={stats?.expiring_soon ?? 0}
          icon={ShieldAlert}
          color="danger"
          loading={statsLoading}
        />
      </div>

      {/* Search */}
      <div className="mb-4">
        <Input
          placeholder={t('vetting.search_full_placeholder')}
          aria-label={t('vetting.search_aria')}
          value={searchQuery}
          onValueChange={(val) => { setSearchQuery(val); setPage(1); }}
          startContent={<Search size={16} className="text-default-400" />}
          variant="bordered"
          size="sm"
          className="max-w-md"
          isClearable
          onClear={() => { setSearchQuery(''); setPage(1); }}
        />
      </div>

      {/* Filter Tabs */}
      <div className="mb-4">
        <Tabs
          selectedKey={statusFilter}
          onSelectionChange={(key) => { setStatusFilter(key as VettingStatus); setPage(1); }}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title={t('vetting.tab_all')} />
          <Tab key="pending_review" title={t('vetting.tab_pending_review')} />
          <Tab key="pending" title={t('vetting.tab_pending')} />
          <Tab key="submitted" title={t('vetting.tab_submitted')} />
          <Tab key="verified" title={t('vetting.tab_verified')} />
          <Tab key="expired" title={t('vetting.tab_expired')} />
          <Tab key="expiring_soon" title={t('vetting.tab_expiring')} />
          <Tab key="rejected" title={t('vetting.tab_rejected')} />
        </Tabs>
      </div>

      {/* Bulk Action Bar */}
      {selectedIds.size > 0 && (
        <div className="mb-4 flex items-center gap-3 p-3 rounded-lg bg-primary-50 border border-primary-200">
          <span className="text-sm font-medium text-primary">
            {t('vetting.records_selected', { count: selectedIds.size })}
          </span>
          <div className="flex gap-2">
            <Button
              size="sm"
              color="success"
              variant="flat"
              startContent={<Check size={14} />}
              onPress={() => setBulkAction('verify')}
            >
              {t('vetting.bulk_verify')}
            </Button>
            <Button
              size="sm"
              color="danger"
              variant="flat"
              startContent={<X size={14} />}
              onPress={() => { setBulkAction('reject'); setBulkRejectReason(''); }}
            >
              {t('vetting.bulk_reject')}
            </Button>
            <Button
              size="sm"
              color="danger"
              variant="flat"
              startContent={<Trash2 size={14} />}
              onPress={() => setBulkAction('delete')}
            >
              {t('vetting.bulk_delete')}
            </Button>
          </div>
          <Button
            size="sm"
            variant="light"
            onPress={() => setSelectedIds(new Set())}
          >
            {t('vetting.bulk_clear')}
          </Button>
        </div>
      )}

      {/* Data Table */}
      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchable={false}
        onRefresh={loadItems}
        totalItems={total}
        page={page}
        pageSize={25}
        onPageChange={setPage}
        selectable
        selectedKeys={selectedIds}
        onSelectionChange={setSelectedIds}
        emptyContent={
          <EmptyState
            icon={Users}
            title={t('vetting.empty_title')}
            description={statusFilter !== 'all' ? t('vetting.empty_try_filter') : t('vetting.empty_add_to_start')}
          />
        }
      />

      {/* File pickers are spawned on demand via openFilePickerForRecord —
          the recordId is captured in the click closure so there's no
          shared state to race over. The legacy shared <input ref> was
          removed once both upload buttons in the View modal were
          migrated to openFilePickerForRecord. */}

      {/* Create Modal — #8: Member search autocomplete */}
      <Modal
        isOpen={createOpen}
        onClose={() => setCreateOpen(false)}
        size="lg"
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Plus size={20} className="text-primary" />
            {t('vetting.modal_add_title')}
          </ModalHeader>
          <ModalBody className="gap-4">
            {/* Member search instead of raw User ID */}
            {selectedUser ? (
              <div className="flex items-center justify-between p-3 rounded-lg border border-default-200 bg-default-50">
                <div className="flex items-center gap-2">
                  <Avatar name={`${selectedUser.first_name} ${selectedUser.last_name}`} size="sm" />
                  <div>
                    <p className="text-sm font-medium">{selectedUser.first_name} {selectedUser.last_name}</p>
                    <p className="text-xs text-default-400">{selectedUser.email}</p>
                  </div>
                </div>
                <Button
                  size="sm"
                  variant="flat"
                  onPress={() => {
                    setSelectedUser(null);
                    setCreateForm(prev => ({ ...prev, user_id: '' }));
                    setUserSearchQuery('');
                  }}
                >
                  {t('vetting.change')}
                </Button>
              </div>
            ) : (
              <div>
                <Input
                  label={t('vetting.search_member_label')}
                  placeholder={t('vetting.search_member_placeholder')}
                  value={userSearchQuery}
                  onValueChange={setUserSearchQuery}
                  variant="bordered"
                  isRequired
                  startContent={<Search size={14} className="text-default-400" />}
                  endContent={userSearchLoading ? <Spinner size="sm" /> : undefined}
                />
                {userSearchResults.length > 0 && (
                  <div className="mt-1 border border-default-200 rounded-lg overflow-hidden max-h-48 overflow-y-auto">
                    {userSearchResults.map((u) => (
                      <Button
                        key={u.id}
                        variant="light"
                        className="w-full flex items-center gap-2 p-2 justify-start h-auto rounded-none"
                        onPress={() => {
                          setSelectedUser(u);
                          setCreateForm(prev => ({ ...prev, user_id: String(u.id) }));
                          setUserSearchQuery('');
                          setUserSearchResults([]);
                        }}
                      >
                        <Avatar name={`${u.first_name} ${u.last_name}`} size="sm" className="shrink-0" />
                        <div className="min-w-0 text-left">
                          <p className="text-sm font-medium truncate">{u.first_name} {u.last_name}</p>
                          <p className="text-xs text-default-400 truncate">{u.email}</p>
                        </div>
                      </Button>
                    ))}
                  </div>
                )}
                {userSearchQuery.trim().length >= 2 && !userSearchLoading && userSearchResults.length === 0 && (
                  <p className="text-xs text-default-400 mt-1">{t('vetting.no_members_found')}</p>
                )}
              </div>
            )}
            <Select
              label={t('vetting.field_vetting_type')}
              selectedKeys={[createForm.vetting_type]}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as VettingRecord['vetting_type'];
                if (val) setCreateForm(prev => ({ ...prev, vetting_type: val }));
              }}
              variant="bordered"
              isRequired
            >
              {Object.keys(VETTING_TYPE_KEYS).map((key) => (
                <SelectItem key={key}>{getTypeLabel(key)}</SelectItem>
              ))}
            </Select>
            <Input
              label={t('vetting.field_reference_number')}
              placeholder={t('vetting.field_reference_number_placeholder')}
              value={createForm.reference_number}
              onValueChange={(val) => setCreateForm(prev => ({ ...prev, reference_number: val }))}
              variant="bordered"
            />
            <div className="grid grid-cols-2 gap-4">
              <Input
                label={t('vetting.field_issue_date')}
                type="date"
                value={createForm.issue_date}
                onValueChange={(val) => setCreateForm(prev => ({ ...prev, issue_date: val }))}
                variant="bordered"
              />
              <Input
                label={t('vetting.field_expiry_date')}
                type="date"
                value={createForm.expiry_date}
                onValueChange={(val) => setCreateForm(prev => ({ ...prev, expiry_date: val }))}
                variant="bordered"
              />
            </div>
            <div className="flex flex-col gap-2">
              <Checkbox
                isSelected={createForm.works_with_children}
                onValueChange={(val) => setCreateForm(prev => ({ ...prev, works_with_children: val }))}
              >
                {t('vetting.works_with_children')}
              </Checkbox>
              <Checkbox
                isSelected={createForm.works_with_vulnerable_adults}
                onValueChange={(val) => setCreateForm(prev => ({ ...prev, works_with_vulnerable_adults: val }))}
              >
                {t('vetting.works_with_vulnerable_adults')}
              </Checkbox>
              <Checkbox
                isSelected={createForm.requires_enhanced_check}
                onValueChange={(val) => setCreateForm(prev => ({ ...prev, requires_enhanced_check: val }))}
              >
                {t('vetting.requires_enhanced_check')}
              </Checkbox>
            </div>
            <Textarea
              label={t('vetting.field_notes')}
              placeholder={t('vetting.field_notes_placeholder')}
              value={createForm.notes}
              onValueChange={(val) => setCreateForm(prev => ({ ...prev, notes: val }))}
              variant="bordered"
              minRows={3}
            />
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={() => setCreateOpen(false)}
              isDisabled={createLoading}
            >
              {t('vetting.cancel')}
            </Button>
            <Button
              color="primary"
              onPress={handleCreate}
              isLoading={createLoading}
            >
              {t('vetting.create_record')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* #9: Edit Modal */}
      {editItem && (
        <Modal
          isOpen={!!editItem}
          onClose={() => setEditItem(null)}
          size="lg"
          scrollBehavior="inside"
        >
          <ModalContent>
            <ModalHeader className="flex items-center gap-2">
              <Pencil size={20} className="text-primary" />
              {t('vetting.modal_edit_title')}
            </ModalHeader>
            <ModalBody className="gap-4">
              <div className="flex items-center gap-2 p-3 rounded-lg border border-default-200 bg-default-50">
                <Avatar
                  src={resolveAvatarUrl(editItem.avatar_url) || undefined}
                  name={`${editItem.first_name} ${editItem.last_name}`}
                  size="sm"
                />
                <div>
                  <p className="text-sm font-medium">{editItem.first_name} {editItem.last_name}</p>
                  <p className="text-xs text-default-400">{editItem.email}</p>
                </div>
              </div>
              <Select
                label={t('vetting.field_vetting_type')}
                selectedKeys={[editForm.vetting_type]}
                onSelectionChange={(keys) => {
                  const val = Array.from(keys)[0] as VettingRecord['vetting_type'];
                  if (val) setEditForm(prev => ({ ...prev, vetting_type: val }));
                }}
                variant="bordered"
                isRequired
              >
                {Object.keys(VETTING_TYPE_KEYS).map((key) => (
                  <SelectItem key={key}>{getTypeLabel(key)}</SelectItem>
                ))}
              </Select>
              <Input
                label={t('vetting.field_reference_number')}
                placeholder={t('vetting.field_reference_number_placeholder')}
                value={editForm.reference_number}
                onValueChange={(val) => setEditForm(prev => ({ ...prev, reference_number: val }))}
                variant="bordered"
              />
              <div className="grid grid-cols-2 gap-4">
                <Input
                  label={t('vetting.field_issue_date')}
                  type="date"
                  value={editForm.issue_date}
                  onValueChange={(val) => setEditForm(prev => ({ ...prev, issue_date: val }))}
                  variant="bordered"
                />
                <Input
                  label={t('vetting.field_expiry_date')}
                  type="date"
                  value={editForm.expiry_date}
                  onValueChange={(val) => setEditForm(prev => ({ ...prev, expiry_date: val }))}
                  variant="bordered"
                />
              </div>
              <div className="flex flex-col gap-2">
                <Checkbox
                  isSelected={editForm.works_with_children}
                  onValueChange={(val) => setEditForm(prev => ({ ...prev, works_with_children: val }))}
                >
                  {t('vetting.works_with_children')}
                </Checkbox>
                <Checkbox
                  isSelected={editForm.works_with_vulnerable_adults}
                  onValueChange={(val) => setEditForm(prev => ({ ...prev, works_with_vulnerable_adults: val }))}
                >
                  {t('vetting.works_with_vulnerable_adults')}
                </Checkbox>
                <Checkbox
                  isSelected={editForm.requires_enhanced_check}
                  onValueChange={(val) => setEditForm(prev => ({ ...prev, requires_enhanced_check: val }))}
                >
                  {t('vetting.requires_enhanced_check')}
                </Checkbox>
              </div>
              <Textarea
                label={t('vetting.field_notes')}
                placeholder={t('vetting.field_notes_placeholder')}
                value={editForm.notes}
                onValueChange={(val) => setEditForm(prev => ({ ...prev, notes: val }))}
                variant="bordered"
                minRows={3}
              />
            </ModalBody>
            <ModalFooter>
              <Button
                variant="flat"
                onPress={() => setEditItem(null)}
                isDisabled={editLoading}
              >
                {t('vetting.cancel')}
              </Button>
              <Button
                color="primary"
                onPress={handleEdit}
                isLoading={editLoading}
              >
                {t('vetting.save_changes')}
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>
      )}

      {/* Reject Modal */}
      {rejectModal && (
        <Modal
          isOpen={!!rejectModal}
          onClose={() => { setRejectModal(null); setRejectReason(''); }}
          size="md"
        >
          <ModalContent>
            <ModalHeader className="flex items-center gap-2">
              <X size={20} className="text-danger" />
              {t('vetting.modal_reject_title')}
            </ModalHeader>
            <ModalBody>
              <p className="text-default-600 mb-3">
                {t('vetting.reject_confirm_prefix')}{' '}
                <strong>{rejectModal.first_name} {rejectModal.last_name}</strong>{t('vetting.reject_confirm_suffix')}
              </p>
              <Textarea
                label={t('vetting.reject_reason_label')}
                placeholder={t('vetting.reject_reason_placeholder')}
                value={rejectReason}
                onValueChange={setRejectReason}
                minRows={3}
                variant="bordered"
                isRequired
              />
            </ModalBody>
            <ModalFooter>
              <Button
                variant="flat"
                onPress={() => { setRejectModal(null); setRejectReason(''); }}
                isDisabled={rejectLoading}
              >
                {t('vetting.cancel')}
              </Button>
              <Button
                color="danger"
                onPress={handleReject}
                isLoading={rejectLoading}
              >
                {t('vetting.reject')}
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>
      )}

      {/* View Detail Modal — updated with #11-12 rejection fields + #10 document upload */}
      {viewItem && (
        <Modal
          isOpen={!!viewItem}
          onClose={() => setViewItem(null)}
          size="lg"
        >
          <ModalContent>
            <ModalHeader className="flex items-center gap-2">
              <FileText size={20} className="text-primary" />
              {t('vetting.modal_view_title')}
            </ModalHeader>
            <ModalBody>
              <div className="flex items-center gap-3 mb-4">
                <Avatar
                  src={resolveAvatarUrl(viewItem.avatar_url) || undefined}
                  name={`${viewItem.first_name} ${viewItem.last_name}`}
                  size="lg"
                />
                <div>
                  <p className="text-lg font-semibold">{viewItem.first_name} {viewItem.last_name}</p>
                  <p className="text-sm text-default-500">{viewItem.email}</p>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                  <p className="text-default-400">{t('vetting.col_type')}</p>
                  <p className="font-medium">{getTypeLabel(viewItem.vetting_type)}</p>
                </div>
                <div>
                  <p className="text-default-400">{t('vetting.col_status')}</p>
                  <Chip size="sm" variant="flat" color={STATUS_COLOR_MAP[viewItem.status] || 'default'} className="capitalize">
                    {t(`status.${viewItem.status}`, { defaultValue: viewItem.status })}
                  </Chip>
                </div>
                <div>
                  <p className="text-default-400">{t('vetting.field_reference_number')}</p>
                  <p className="font-medium font-mono">{viewItem.reference_number || '—'}</p>
                </div>
                <div>
                  <p className="text-default-400">{t('vetting.field_issue_date')}</p>
                  <p className="font-medium">{formatServerDate(viewItem.issue_date)}</p>
                </div>
                <div>
                  <p className="text-default-400">{t('vetting.field_expiry_date')}</p>
                  <p className="font-medium">{formatServerDate(viewItem.expiry_date)}</p>
                </div>
                <div>
                  <p className="text-default-400">{t('vetting.verified_by')}</p>
                  <p className="font-medium">
                    {viewItem.verifier_first_name
                      ? `${viewItem.verifier_first_name} ${viewItem.verifier_last_name}`
                      : '—'}
                  </p>
                </div>
                <div>
                  <p className="text-default-400">{t('vetting.verified_at')}</p>
                  <p className="font-medium">{formatServerDateTime(viewItem.verified_at)}</p>
                </div>
                <div>
                  <p className="text-default-400">{t('vetting.created')}</p>
                  <p className="font-medium">{formatServerDateTime(viewItem.created_at)}</p>
                </div>
              </div>

              {/* #11-12: Rejection details */}
              {viewItem.status === 'rejected' && (
                <div className="mt-4 p-3 rounded-lg bg-danger-50 border border-danger-200">
                  <p className="text-sm font-medium text-danger mb-1">{t('vetting.rejection_details')}</p>
                  <div className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                    <div>
                      <p className="text-default-400">{t('vetting.rejected_by')}</p>
                      <p className="font-medium">
                        {viewItem.rejector_first_name
                          ? `${viewItem.rejector_first_name} ${viewItem.rejector_last_name}`
                          : '—'}
                      </p>
                    </div>
                    <div>
                      <p className="text-default-400">{t('vetting.rejected_at')}</p>
                      <p className="font-medium">{formatServerDateTime(viewItem.rejected_at)}</p>
                    </div>
                  </div>
                  {viewItem.rejection_reason && (
                    <div className="mt-2">
                      <p className="text-default-400 text-sm">{t('vetting.reason')}</p>
                      <p className="text-sm">{viewItem.rejection_reason}</p>
                    </div>
                  )}
                </div>
              )}

              <div className="mt-4 flex gap-2 flex-wrap">
                {viewItem.works_with_children && (
                  <Chip size="sm" variant="flat" color="warning" startContent={<Baby size={12} />}>
                    {t('vetting.works_with_children')}
                  </Chip>
                )}
                {viewItem.works_with_vulnerable_adults && (
                  <Chip size="sm" variant="flat" color="warning" startContent={<HeartHandshake size={12} />}>
                    {t('vetting.works_with_vulnerable_adults')}
                  </Chip>
                )}
                {viewItem.requires_enhanced_check && (
                  <Chip size="sm" variant="flat" color="danger" startContent={<ShieldAlert size={12} />}>
                    {t('vetting.requires_enhanced_check')}
                  </Chip>
                )}
              </div>
              {viewItem.notes && (
                <div className="mt-4">
                  <p className="text-default-400 text-sm mb-1">{t('vetting.field_notes')}</p>
                  <p className="text-sm bg-default-100 p-3 rounded-lg whitespace-pre-wrap">{viewItem.notes}</p>
                </div>
              )}

              {/* #10: Document section */}
              <div className="mt-4">
                <p className="text-default-400 text-sm mb-2">{t('vetting.document')}</p>
                {viewItem.document_url ? (
                  <div className="flex items-center gap-2">
                    <a
                      href={viewItem.document_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-sm text-primary hover:underline flex items-center gap-1"
                    >
                      <FileText size={14} />
                      {t('vetting.view_document')}
                    </a>
                    <Button
                      size="sm"
                      variant="flat"
                      isLoading={uploadingId === viewItem.id}
                      onPress={() => openFilePickerForRecord(viewItem.id)}
                    >
                      {t('vetting.replace')}
                    </Button>
                  </div>
                ) : (
                  <Button
                    size="sm"
                    variant="flat"
                    startContent={<Upload size={14} />}
                    isLoading={uploadingId === viewItem.id}
                    onPress={() => openFilePickerForRecord(viewItem.id)}
                  >
                    {t('vetting.upload_document')}
                  </Button>
                )}
                <p className="text-xs text-default-400 mt-1">{t('vetting.document_types_hint')}</p>
              </div>
            </ModalBody>
            <ModalFooter>
              <Button variant="flat" onPress={() => setViewItem(null)}>
                {t('vetting.close')}
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>
      )}

      {/* Delete Confirmation */}
      <ConfirmModal
        isOpen={!!deleteItem}
        onClose={() => setDeleteItem(null)}
        onConfirm={handleDelete}
        title={t('vetting.delete_record_title')}
        message={deleteItem
          ? t('vetting.delete_record_confirm')
          : ''}
        confirmLabel={t('vetting.confirm_delete')}
        confirmColor="danger"
        isLoading={deleteLoading}
      />

      {/* Bulk Verify/Delete Confirm */}
      <ConfirmModal
        isOpen={bulkAction === 'verify' || bulkAction === 'delete'}
        onClose={() => setBulkAction(null)}
        onConfirm={handleBulkAction}
        title={bulkAction === 'verify' ? t('vetting.bulk_verify_title') : t('vetting.bulk_delete_title')}
        message={bulkAction === 'verify'
          ? t('vetting.bulk_verify_confirm', { count: selectedIds.size })
          : t('vetting.bulk_delete_confirm', { count: selectedIds.size })}
        confirmLabel={bulkAction === 'verify' ? t('vetting.verify_all') : t('vetting.delete_all')}
        confirmColor={bulkAction === 'verify' ? 'primary' : 'danger'}
        isLoading={bulkLoading}
      />

      {/* Bulk Reject Modal */}
      {bulkAction === 'reject' && (
        <Modal
          isOpen
          onClose={() => { setBulkAction(null); setBulkRejectReason(''); }}
          size="md"
        >
          <ModalContent>
            <ModalHeader className="flex items-center gap-2">
              <X size={20} className="text-danger" />
              {t('vetting.modal_bulk_reject_title')}
            </ModalHeader>
            <ModalBody>
              <p className="text-default-600 mb-3">
                {t('vetting.bulk_reject_confirm', { count: selectedIds.size })}
              </p>
              <Textarea
                label={t('vetting.reject_reason_label')}
                placeholder={t('vetting.reject_reason_placeholder')}
                value={bulkRejectReason}
                onValueChange={setBulkRejectReason}
                minRows={3}
                variant="bordered"
                isRequired
              />
            </ModalBody>
            <ModalFooter>
              <Button
                variant="flat"
                onPress={() => { setBulkAction(null); setBulkRejectReason(''); }}
                isDisabled={bulkLoading}
              >
                {t('vetting.cancel')}
              </Button>
              <Button
                color="danger"
                onPress={handleBulkAction}
                isLoading={bulkLoading}
              >
                {t('vetting.reject_all')}
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>
      )}
    </div>
  );
}

export default VettingRecords;
