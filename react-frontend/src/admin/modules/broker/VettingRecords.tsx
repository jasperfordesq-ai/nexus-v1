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
import { Link } from 'react-router-dom';
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
import {
  ArrowLeft,
  ShieldCheck,
  ShieldAlert,
  Clock,
  Plus,
  Check,
  X,
  Search,
  FileText,
  Users,
  Baby,
  HeartHandshake,
  Trash2,
  Eye,
  Pencil,
  Upload,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import { adminVetting, adminUsers } from '../../api/adminApi';
import { DataTable, StatCard, PageHeader, ConfirmModal, EmptyState, type Column } from '../../components';
import type { VettingRecord, VettingStats } from '../../api/types';

const VETTING_TYPE_LABELS: Record<string, string> = {
  dbs_basic: 'DBS Basic',
  dbs_standard: 'DBS Standard',
  dbs_enhanced: 'DBS Enhanced',
  garda_vetting: 'Garda Vetting',
  access_ni: 'Access NI',
  pvg_scotland: 'PVG Scotland',
  international: 'International',
  other: 'Other',
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

interface UserSearchResult {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
}

export function VettingRecords() {
  usePageTitle('Admin - Vetting Records');
  const { tenantPath } = useTenant();
  const toast = useToast();

  // List state
  const [items, setItems] = useState<VettingRecord[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState('all');
  const [searchQuery, setSearchQuery] = useState('');

  // Stats
  const [stats, setStats] = useState<VettingStats | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);

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

  // Document upload (#10)
  const [uploadingId, setUploadingId] = useState<number | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

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
    try {
      const res = await adminVetting.stats();
      if (res.success && res.data) {
        setStats(res.data as VettingStats);
      }
    } catch {
      // Stats are non-critical
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
      if (searchQuery.trim()) {
        params.search = searchQuery.trim();
      }

      const res = await adminVetting.list(params as Parameters<typeof adminVetting.list>[0]);
      if (res.success && Array.isArray(res.data)) {
        setItems(res.data as VettingRecord[]);
        const meta = res.meta as Record<string, unknown> | undefined;
        setTotal(Number(meta?.total ?? meta?.total_items ?? res.data.length));
      }
    } catch {
      toast.error('Failed to load vetting records');
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter, searchQuery]);

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
        toast.success(`Vetting record for ${item.first_name} ${item.last_name} verified`);
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || 'Failed to verify record');
      }
    } catch {
      toast.error('Failed to verify record');
    } finally {
      setVerifyingId(null);
    }
  };

  const handleReject = async () => {
    if (!rejectModal || !rejectReason.trim()) {
      toast.error('A reason is required to reject a vetting record');
      return;
    }
    setRejectLoading(true);
    try {
      const res = await adminVetting.reject(rejectModal.id, rejectReason);
      if (res?.success) {
        toast.success(`Vetting record rejected`);
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || 'Failed to reject record');
      }
    } catch {
      toast.error('Failed to reject record');
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
        toast.success('Vetting record deleted');
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || 'Failed to delete record');
      }
    } catch {
      toast.error('Failed to delete record');
    } finally {
      setDeleteLoading(false);
      setDeleteItem(null);
    }
  };

  const handleCreate = async () => {
    if (!createForm.user_id) {
      toast.error('Please select a member');
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
        toast.success('Vetting record created');
        setCreateOpen(false);
        resetCreateForm();
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || 'Failed to create record');
      }
    } catch {
      toast.error('Failed to create record');
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
        toast.success('Vetting record updated');
        setEditItem(null);
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || 'Failed to update record');
      }
    } catch {
      toast.error('Failed to update record');
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
        toast.success('Document uploaded');
        loadItems();
        // Refresh view modal if open
        if (viewItem?.id === recordId && res.data) {
          setViewItem(res.data as VettingRecord);
        }
      } else {
        toast.error(res?.error || 'Failed to upload document');
      }
    } catch {
      toast.error('Failed to upload document');
    } finally {
      setUploadingId(null);
    }
  };

  // Bulk action handler
  const handleBulkAction = async () => {
    if (!bulkAction || selectedIds.size === 0) return;

    if (bulkAction === 'reject' && !bulkRejectReason.trim()) {
      toast.error('A reason is required for bulk rejection');
      return;
    }

    setBulkLoading(true);
    try {
      const ids = Array.from(selectedIds).map(Number);
      const res = await adminVetting.bulk(ids, bulkAction, bulkAction === 'reject' ? bulkRejectReason : undefined);
      if (res?.success && res.data) {
        const d = res.data as { processed: number; failed: number };
        const label = bulkAction === 'verify' ? 'verified' : bulkAction === 'reject' ? 'rejected' : 'deleted';
        toast.success(`${d.processed} record(s) ${label}${d.failed > 0 ? `, ${d.failed} failed` : ''}`);
        setSelectedIds(new Set());
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || `Bulk ${bulkAction} failed`);
      }
    } catch {
      toast.error(`Bulk ${bulkAction} failed`);
    } finally {
      setBulkLoading(false);
      setBulkAction(null);
      setBulkRejectReason('');
    }
  };

  const columns: Column<VettingRecord>[] = [
    {
      key: 'member',
      label: 'Member',
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
      label: 'Type',
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat" color="primary">
          {VETTING_TYPE_LABELS[item.vetting_type] || item.vetting_type}
        </Chip>
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
          color={STATUS_COLOR_MAP[item.status] || 'default'}
          className="capitalize"
        >
          {item.status}
        </Chip>
      ),
    },
    {
      key: 'reference_number',
      label: 'Reference #',
      render: (item) => (
        <span className="text-sm text-default-600 font-mono">
          {item.reference_number || '\u2014'}
        </span>
      ),
    },
    {
      key: 'issue_date',
      label: 'Issue Date',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.issue_date ? new Date(item.issue_date).toLocaleDateString() : '\u2014'}
        </span>
      ),
    },
    {
      key: 'expiry_date',
      label: 'Expiry Date',
      sortable: true,
      render: (item) => {
        if (!item.expiry_date) return <span className="text-sm text-default-500">{'\u2014'}</span>;
        const expiry = new Date(item.expiry_date);
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
      label: 'Safeguarding',
      render: (item) => (
        <div className="flex gap-1">
          {item.works_with_children && (
            <Chip size="sm" variant="dot" color="warning" startContent={<Baby size={10} />}>
              Children
            </Chip>
          )}
          {item.works_with_vulnerable_adults && (
            <Chip size="sm" variant="dot" color="warning" startContent={<HeartHandshake size={10} />}>
              Vulnerable
            </Chip>
          )}
          {!item.works_with_children && !item.works_with_vulnerable_adults && (
            <span className="text-sm text-default-400">{'\u2014'}</span>
          )}
        </div>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (item) => (
        <div className="flex gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            onPress={() => setViewItem(item)}
            aria-label="View details"
          >
            <Eye size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            onPress={() => openEditModal(item)}
            aria-label="Edit record"
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
                aria-label="Verify record"
              >
                <Check size={14} />
              </Button>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="danger"
                onPress={() => { setRejectModal(item); setRejectReason(''); }}
                aria-label="Reject record"
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
            aria-label="Delete record"
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
        title="Vetting Records"
        description="Manage DBS, Garda vetting, and safeguarding compliance records"
        actions={
          <div className="flex gap-2">
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              size="sm"
              onPress={() => { resetCreateForm(); setCreateOpen(true); }}
            >
              Add Record
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/broker-controls')}
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              size="sm"
            >
              Back
            </Button>
          </div>
        }
      />

      {/* Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <StatCard
          label="Total Records"
          value={stats?.total ?? 0}
          icon={FileText}
          color="primary"
          loading={statsLoading}
        />
        <StatCard
          label="Pending Review"
          value={stats?.pending ?? 0}
          icon={Clock}
          color="warning"
          loading={statsLoading}
        />
        <StatCard
          label="Verified"
          value={stats?.verified ?? 0}
          icon={ShieldCheck}
          color="success"
          loading={statsLoading}
        />
        <StatCard
          label="Expiring Soon"
          value={stats?.expiring_soon ?? 0}
          icon={ShieldAlert}
          color="danger"
          loading={statsLoading}
        />
      </div>

      {/* Search */}
      <div className="mb-4">
        <Input
          placeholder="Search by name, email, or reference number..."
          aria-label="Search vetting records"
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
          onSelectionChange={(key) => { setStatusFilter(key as string); setPage(1); }}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title="All" />
          <Tab key="pending" title="Pending" />
          <Tab key="submitted" title="Submitted" />
          <Tab key="verified" title="Verified" />
          <Tab key="expired" title="Expired" />
          <Tab key="expiring_soon" title="Expiring Soon" />
          <Tab key="rejected" title="Rejected" />
        </Tabs>
      </div>

      {/* Bulk Action Bar */}
      {selectedIds.size > 0 && (
        <div className="mb-4 flex items-center gap-3 p-3 rounded-lg bg-primary-50 border border-primary-200">
          <span className="text-sm font-medium text-primary">
            {selectedIds.size} record{selectedIds.size > 1 ? 's' : ''} selected
          </span>
          <div className="flex gap-2">
            <Button
              size="sm"
              color="success"
              variant="flat"
              startContent={<Check size={14} />}
              onPress={() => setBulkAction('verify')}
            >
              Verify
            </Button>
            <Button
              size="sm"
              color="danger"
              variant="flat"
              startContent={<X size={14} />}
              onPress={() => { setBulkAction('reject'); setBulkRejectReason(''); }}
            >
              Reject
            </Button>
            <Button
              size="sm"
              color="danger"
              variant="flat"
              startContent={<Trash2 size={14} />}
              onPress={() => setBulkAction('delete')}
            >
              Delete
            </Button>
          </div>
          <Button
            size="sm"
            variant="light"
            onPress={() => setSelectedIds(new Set())}
          >
            Clear
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
        onSelectionChange={setSelectedIds}
        emptyContent={
          <EmptyState
            icon={Users}
            title="No vetting records found"
            description={statusFilter !== 'all' ? 'Try changing the filter or search query.' : 'Add a vetting record to get started.'}
          />
        }
      />

      {/* Hidden file input for document uploads */}
      <input
        ref={fileInputRef}
        type="file"
        accept=".pdf,.jpg,.jpeg,.png,.webp"
        className="hidden"
        onChange={(e) => {
          const file = e.target.files?.[0];
          if (file && uploadingId) {
            handleDocumentUpload(uploadingId, file);
          }
          e.target.value = '';
        }}
      />

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
            Add Vetting Record
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
                  Change
                </Button>
              </div>
            ) : (
              <div>
                <Input
                  label="Search Member"
                  placeholder="Type a name or email to search..."
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
                  <p className="text-xs text-default-400 mt-1">No members found</p>
                )}
              </div>
            )}
            <Select
              label="Vetting Type"
              selectedKeys={[createForm.vetting_type]}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as VettingRecord['vetting_type'];
                if (val) setCreateForm(prev => ({ ...prev, vetting_type: val }));
              }}
              variant="bordered"
              isRequired
            >
              {Object.entries(VETTING_TYPE_LABELS).map(([key, label]) => (
                <SelectItem key={key}>{label}</SelectItem>
              ))}
            </Select>
            <Input
              label="Reference Number"
              placeholder="e.g., DBS-12345678"
              value={createForm.reference_number}
              onValueChange={(val) => setCreateForm(prev => ({ ...prev, reference_number: val }))}
              variant="bordered"
            />
            <div className="grid grid-cols-2 gap-4">
              <Input
                label="Issue Date"
                type="date"
                value={createForm.issue_date}
                onValueChange={(val) => setCreateForm(prev => ({ ...prev, issue_date: val }))}
                variant="bordered"
              />
              <Input
                label="Expiry Date"
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
                Works with children
              </Checkbox>
              <Checkbox
                isSelected={createForm.works_with_vulnerable_adults}
                onValueChange={(val) => setCreateForm(prev => ({ ...prev, works_with_vulnerable_adults: val }))}
              >
                Works with vulnerable adults
              </Checkbox>
              <Checkbox
                isSelected={createForm.requires_enhanced_check}
                onValueChange={(val) => setCreateForm(prev => ({ ...prev, requires_enhanced_check: val }))}
              >
                Requires enhanced check
              </Checkbox>
            </div>
            <Textarea
              label="Notes"
              placeholder="Additional notes about this vetting record..."
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
              Cancel
            </Button>
            <Button
              color="primary"
              onPress={handleCreate}
              isLoading={createLoading}
            >
              Create Record
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
              Edit Vetting Record
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
                label="Vetting Type"
                selectedKeys={[editForm.vetting_type]}
                onSelectionChange={(keys) => {
                  const val = Array.from(keys)[0] as VettingRecord['vetting_type'];
                  if (val) setEditForm(prev => ({ ...prev, vetting_type: val }));
                }}
                variant="bordered"
                isRequired
              >
                {Object.entries(VETTING_TYPE_LABELS).map(([key, label]) => (
                  <SelectItem key={key}>{label}</SelectItem>
                ))}
              </Select>
              <Input
                label="Reference Number"
                placeholder="e.g., DBS-12345678"
                value={editForm.reference_number}
                onValueChange={(val) => setEditForm(prev => ({ ...prev, reference_number: val }))}
                variant="bordered"
              />
              <div className="grid grid-cols-2 gap-4">
                <Input
                  label="Issue Date"
                  type="date"
                  value={editForm.issue_date}
                  onValueChange={(val) => setEditForm(prev => ({ ...prev, issue_date: val }))}
                  variant="bordered"
                />
                <Input
                  label="Expiry Date"
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
                  Works with children
                </Checkbox>
                <Checkbox
                  isSelected={editForm.works_with_vulnerable_adults}
                  onValueChange={(val) => setEditForm(prev => ({ ...prev, works_with_vulnerable_adults: val }))}
                >
                  Works with vulnerable adults
                </Checkbox>
                <Checkbox
                  isSelected={editForm.requires_enhanced_check}
                  onValueChange={(val) => setEditForm(prev => ({ ...prev, requires_enhanced_check: val }))}
                >
                  Requires enhanced check
                </Checkbox>
              </div>
              <Textarea
                label="Notes"
                placeholder="Additional notes about this vetting record..."
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
                Cancel
              </Button>
              <Button
                color="primary"
                onPress={handleEdit}
                isLoading={editLoading}
              >
                Save Changes
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
              Reject Vetting Record
            </ModalHeader>
            <ModalBody>
              <p className="text-default-600 mb-3">
                Reject the vetting record for{' '}
                <strong>{rejectModal.first_name} {rejectModal.last_name}</strong>?
              </p>
              <Textarea
                label="Reason (required)"
                placeholder="Provide a reason for rejection..."
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
                Cancel
              </Button>
              <Button
                color="danger"
                onPress={handleReject}
                isLoading={rejectLoading}
              >
                Reject
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
              Vetting Record Details
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
                  <p className="text-default-400">Type</p>
                  <p className="font-medium">{VETTING_TYPE_LABELS[viewItem.vetting_type] || viewItem.vetting_type}</p>
                </div>
                <div>
                  <p className="text-default-400">Status</p>
                  <Chip size="sm" variant="flat" color={STATUS_COLOR_MAP[viewItem.status] || 'default'} className="capitalize">
                    {viewItem.status}
                  </Chip>
                </div>
                <div>
                  <p className="text-default-400">Reference Number</p>
                  <p className="font-medium font-mono">{viewItem.reference_number || '\u2014'}</p>
                </div>
                <div>
                  <p className="text-default-400">Issue Date</p>
                  <p className="font-medium">{viewItem.issue_date ? new Date(viewItem.issue_date).toLocaleDateString() : '\u2014'}</p>
                </div>
                <div>
                  <p className="text-default-400">Expiry Date</p>
                  <p className="font-medium">{viewItem.expiry_date ? new Date(viewItem.expiry_date).toLocaleDateString() : '\u2014'}</p>
                </div>
                <div>
                  <p className="text-default-400">Verified By</p>
                  <p className="font-medium">
                    {viewItem.verifier_first_name
                      ? `${viewItem.verifier_first_name} ${viewItem.verifier_last_name}`
                      : '\u2014'}
                  </p>
                </div>
                <div>
                  <p className="text-default-400">Verified At</p>
                  <p className="font-medium">{viewItem.verified_at ? new Date(viewItem.verified_at).toLocaleString() : '\u2014'}</p>
                </div>
                <div>
                  <p className="text-default-400">Created</p>
                  <p className="font-medium">{new Date(viewItem.created_at).toLocaleString()}</p>
                </div>
              </div>

              {/* #11-12: Rejection details */}
              {viewItem.status === 'rejected' && (
                <div className="mt-4 p-3 rounded-lg bg-danger-50 border border-danger-200">
                  <p className="text-sm font-medium text-danger mb-1">Rejection Details</p>
                  <div className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                    <div>
                      <p className="text-default-400">Rejected By</p>
                      <p className="font-medium">
                        {viewItem.rejector_first_name
                          ? `${viewItem.rejector_first_name} ${viewItem.rejector_last_name}`
                          : '\u2014'}
                      </p>
                    </div>
                    <div>
                      <p className="text-default-400">Rejected At</p>
                      <p className="font-medium">{viewItem.rejected_at ? new Date(viewItem.rejected_at).toLocaleString() : '\u2014'}</p>
                    </div>
                  </div>
                  {viewItem.rejection_reason && (
                    <div className="mt-2">
                      <p className="text-default-400 text-sm">Reason</p>
                      <p className="text-sm">{viewItem.rejection_reason}</p>
                    </div>
                  )}
                </div>
              )}

              <div className="mt-4 flex gap-2 flex-wrap">
                {viewItem.works_with_children && (
                  <Chip size="sm" variant="flat" color="warning" startContent={<Baby size={12} />}>
                    Works with children
                  </Chip>
                )}
                {viewItem.works_with_vulnerable_adults && (
                  <Chip size="sm" variant="flat" color="warning" startContent={<HeartHandshake size={12} />}>
                    Works with vulnerable adults
                  </Chip>
                )}
                {viewItem.requires_enhanced_check && (
                  <Chip size="sm" variant="flat" color="danger" startContent={<ShieldAlert size={12} />}>
                    Requires enhanced check
                  </Chip>
                )}
              </div>
              {viewItem.notes && (
                <div className="mt-4">
                  <p className="text-default-400 text-sm mb-1">Notes</p>
                  <p className="text-sm bg-default-100 p-3 rounded-lg whitespace-pre-wrap">{viewItem.notes}</p>
                </div>
              )}

              {/* #10: Document section */}
              <div className="mt-4">
                <p className="text-default-400 text-sm mb-2">Document</p>
                {viewItem.document_url ? (
                  <div className="flex items-center gap-2">
                    <a
                      href={viewItem.document_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-sm text-primary hover:underline flex items-center gap-1"
                    >
                      <FileText size={14} />
                      View uploaded document
                    </a>
                    <Button
                      size="sm"
                      variant="flat"
                      isLoading={uploadingId === viewItem.id}
                      onPress={() => {
                        setUploadingId(viewItem.id);
                        fileInputRef.current?.click();
                      }}
                    >
                      Replace
                    </Button>
                  </div>
                ) : (
                  <Button
                    size="sm"
                    variant="flat"
                    startContent={<Upload size={14} />}
                    isLoading={uploadingId === viewItem.id}
                    onPress={() => {
                      setUploadingId(viewItem.id);
                      fileInputRef.current?.click();
                    }}
                  >
                    Upload Document
                  </Button>
                )}
                <p className="text-xs text-default-400 mt-1">PDF, JPEG, PNG, or WebP (max 10 MB)</p>
              </div>
            </ModalBody>
            <ModalFooter>
              <Button variant="flat" onPress={() => setViewItem(null)}>
                Close
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
        title="Delete Vetting Record"
        message={deleteItem
          ? `Are you sure you want to delete the vetting record for ${deleteItem.first_name} ${deleteItem.last_name}? This action cannot be undone.`
          : ''}
        confirmLabel="Delete"
        confirmColor="danger"
        isLoading={deleteLoading}
      />

      {/* Bulk Verify/Delete Confirm */}
      <ConfirmModal
        isOpen={bulkAction === 'verify' || bulkAction === 'delete'}
        onClose={() => setBulkAction(null)}
        onConfirm={handleBulkAction}
        title={bulkAction === 'verify' ? 'Bulk Verify Records' : 'Bulk Delete Records'}
        message={bulkAction === 'verify'
          ? `Verify ${selectedIds.size} selected vetting record(s)? Only records with pending/submitted status will be verified.`
          : `Permanently delete ${selectedIds.size} selected vetting record(s)? This action cannot be undone.`}
        confirmLabel={bulkAction === 'verify' ? 'Verify All' : 'Delete All'}
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
              Bulk Reject Records
            </ModalHeader>
            <ModalBody>
              <p className="text-default-600 mb-3">
                Reject <strong>{selectedIds.size}</strong> selected vetting record(s)?
              </p>
              <Textarea
                label="Reason (required)"
                placeholder="Provide a reason for rejection..."
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
                Cancel
              </Button>
              <Button
                color="danger"
                onPress={handleBulkAction}
                isLoading={bulkLoading}
              >
                Reject All
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>
      )}
    </div>
  );
}

export default VettingRecords;
