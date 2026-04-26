// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Insurance Certificates Management
 * Manage insurance certificates for compliance.
 * Parity: PHP AdminInsuranceCertificateApiController
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
import Trash2 from 'lucide-react/icons/trash-2';
import Eye from 'lucide-react/icons/eye';
import FileCheck from 'lucide-react/icons/file-check';
import Pencil from 'lucide-react/icons/pencil';
import ExternalLink from 'lucide-react/icons/external-link';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { resolveAssetUrl, resolveAvatarUrl } from '@/lib/helpers';
import { parseServerTimestamp, formatServerDate, formatServerDateTime } from '@/lib/serverTime';
import { adminInsurance, adminUsers, adminBroker } from '@/admin/api/adminApi';
import { DataTable, StatCard, PageHeader, ConfirmModal, EmptyState, type Column } from '@/admin/components';
import type { InsuranceCertificate, InsuranceStats, BrokerConfig } from '@/admin/api/types';

const INSURANCE_TYPE_KEYS = [
  'public_liability',
  'professional_indemnity',
  'employers_liability',
  'product_liability',
  'personal_accident',
  'other',
] as const;

const INSURANCE_TYPE_LABEL_KEYS: Record<(typeof INSURANCE_TYPE_KEYS)[number], string> = {
  public_liability: 'insurance.type_public_liability',
  professional_indemnity: 'insurance.type_professional_indemnity',
  employers_liability: 'insurance.type_employers_liability',
  product_liability: 'insurance.type_product_liability',
  personal_accident: 'insurance.type_personal_accident',
  other: 'insurance.type_other',
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

export function InsuranceCertificates() {
  const { t } = useTranslation('broker');
  usePageTitle(t('insurance.title'));
  const { tenantPath } = useTenant();
  const toast = useToast();

  const formatInsuranceType = (type: string | null | undefined): string => {
    if (!type) return '—';
    const key = INSURANCE_TYPE_LABEL_KEYS[type as keyof typeof INSURANCE_TYPE_LABEL_KEYS];
    return key ? t(key) : type;
  };

  // List state
  // Status filter is mirrored to `?status=` so stat-card deep-links and
  // browser back/forward work correctly.
  const INSURANCE_STATUSES = [
    'all', 'pending', 'submitted', 'verified', 'expired', 'expiring_soon', 'rejected',
  ] as const;
  type InsuranceStatus = (typeof INSURANCE_STATUSES)[number];
  const [searchParams, setSearchParams] = useSearchParams();
  const urlStatus = searchParams.get('status') as InsuranceStatus | null;
  const statusFilter: InsuranceStatus =
    urlStatus && INSURANCE_STATUSES.includes(urlStatus) ? urlStatus : 'all';
  const setStatusFilter = useCallback(
    (next: InsuranceStatus) => {
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

  // `?user_id=` is set by the "Manage Insurance" link from User Edit so the
  // page lands pre-filtered to that member's certificates.
  const userIdFilter = searchParams.get('user_id');

  const [items, setItems] = useState<InsuranceCertificate[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Stats
  const [stats, setStats] = useState<InsuranceStats | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);

  // Broker config (for expiry warning days)
  const [expiryWarningDays, setExpiryWarningDays] = useState(30);

  // Create modal
  const [createOpen, setCreateOpen] = useState(false);
  const [createLoading, setCreateLoading] = useState(false);
  const [createForm, setCreateForm] = useState({
    user_id: '',
    insurance_type: 'public_liability' as InsuranceCertificate['insurance_type'],
    provider_name: '',
    policy_number: '',
    coverage_amount: '',
    start_date: '',
    expiry_date: '',
    notes: '',
  });

  // User search for create modal (#9)
  const [userSearchQuery, setUserSearchQuery] = useState('');
  const [userSearchResults, setUserSearchResults] = useState<UserSearchResult[]>([]);
  const [userSearchLoading, setUserSearchLoading] = useState(false);
  const [selectedUser, setSelectedUser] = useState<UserSearchResult | null>(null);
  const userSearchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Edit modal (#10)
  const [editItem, setEditItem] = useState<InsuranceCertificate | null>(null);
  const [editLoading, setEditLoading] = useState(false);
  const [editForm, setEditForm] = useState({
    insurance_type: 'public_liability' as InsuranceCertificate['insurance_type'],
    provider_name: '',
    policy_number: '',
    coverage_amount: '',
    start_date: '',
    expiry_date: '',
    notes: '',
  });

  // Reject modal
  const [rejectModal, setRejectModal] = useState<InsuranceCertificate | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [rejectLoading, setRejectLoading] = useState(false);

  // View modal
  const [viewItem, setViewItem] = useState<InsuranceCertificate | null>(null);

  // Delete confirm
  const [deleteItem, setDeleteItem] = useState<InsuranceCertificate | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  // Verify loading tracker
  const [verifyingId, setVerifyingId] = useState<number | null>(null);

  // #6: Debounce search input
  useEffect(() => {
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }
    searchTimeoutRef.current = setTimeout(() => {
      setDebouncedSearch(searchQuery);
      setPage(1);
    }, SEARCH_DEBOUNCE_MS);

    return () => {
      if (searchTimeoutRef.current) {
        clearTimeout(searchTimeoutRef.current);
      }
    };
  }, [searchQuery]);

  // Load broker config for expiry warning days (#12)
  useEffect(() => {
    (async () => {
      try {
        const res = await adminBroker.getConfiguration();
        if (res.success && res.data) {
          const cfg = res.data as BrokerConfig;
          if (cfg.insurance_expiry_warning_days) {
            setExpiryWarningDays(cfg.insurance_expiry_warning_days);
          }
        }
      } catch {
        // Use default 30 days
      }
    })();
  }, []);

  const loadStats = useCallback(async () => {
    setStatsLoading(true);
    try {
      const res = await adminInsurance.stats();
      if (res.success && res.data) {
        setStats(res.data as InsuranceStats);
      }
    } catch {
      // Stats are non-critical
    } finally {
      setStatsLoading(false);
    }
  }, []);

  // #5: Added toast to dependency array
  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = { page };
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

      const res = await adminInsurance.list(params as Parameters<typeof adminInsurance.list>[0]);
      if (res.success && Array.isArray(res.data)) {
        setItems(res.data as InsuranceCertificate[]);
        const meta = res.meta as Record<string, unknown> | undefined;
        setTotal(Number(meta?.total ?? meta?.total_items ?? res.data.length));
      }
    } catch {
      toast.error(t('insurance.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter, debouncedSearch, userIdFilter, toast, t])


  useEffect(() => { loadStats(); }, [loadStats]);
  useEffect(() => { loadItems(); }, [loadItems]);

  // #9: User search for create modal
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

  const handleVerify = async (item: InsuranceCertificate) => {
    setVerifyingId(item.id);
    try {
      const res = await adminInsurance.verify(item.id);
      if (res?.success) {
        toast.success(t('insurance.verify_success'));
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || t('insurance.verify_failed'));
      }
    } catch {
      toast.error(t('insurance.verify_failed'));
    } finally {
      setVerifyingId(null);
    }
  };

  const handleReject = async () => {
    if (!rejectModal || !rejectReason.trim()) {
      toast.error(t('insurance.reject_reason_required'));
      return;
    }
    setRejectLoading(true);
    try {
      const res = await adminInsurance.reject(rejectModal.id, rejectReason);
      if (res?.success) {
        toast.success(t('insurance.reject_success'));
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || t('insurance.reject_failed'));
      }
    } catch {
      toast.error(t('insurance.reject_failed'));
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
      const res = await adminInsurance.destroy(deleteItem.id);
      if (res?.success) {
        toast.success(t('insurance.delete_success'));
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || t('insurance.delete_failed'));
      }
    } catch {
      toast.error(t('insurance.delete_failed'));
    } finally {
      setDeleteLoading(false);
      setDeleteItem(null);
    }
  };

  const handleCreate = async () => {
    if (!createForm.user_id) {
      toast.error(t('insurance.select_member_required'));
      return;
    }
    setCreateLoading(true);
    try {
      const payload: Record<string, unknown> = {
        user_id: Number(createForm.user_id),
        insurance_type: createForm.insurance_type,
      };
      if (createForm.provider_name) payload.provider_name = createForm.provider_name;
      if (createForm.policy_number) payload.policy_number = createForm.policy_number;
      if (createForm.coverage_amount) payload.coverage_amount = Number(createForm.coverage_amount);
      if (createForm.start_date) payload.start_date = createForm.start_date;
      if (createForm.expiry_date) payload.expiry_date = createForm.expiry_date;
      if (createForm.notes) payload.notes = createForm.notes;

      const res = await adminInsurance.create(payload as Partial<InsuranceCertificate>);
      if (res?.success || res?.data) {
        toast.success(t('insurance.create_success'));
        setCreateOpen(false);
        resetCreateForm();
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || t('insurance.create_failed'));
      }
    } catch {
      toast.error(t('insurance.create_failed'));
    } finally {
      setCreateLoading(false);
    }
  };

  // #10: Edit handler
  const handleEdit = async () => {
    if (!editItem) return;
    setEditLoading(true);
    try {
      const payload: Record<string, unknown> = {
        insurance_type: editForm.insurance_type,
        provider_name: editForm.provider_name || null,
        policy_number: editForm.policy_number || null,
        coverage_amount: editForm.coverage_amount ? Number(editForm.coverage_amount) : null,
        start_date: editForm.start_date || null,
        expiry_date: editForm.expiry_date || null,
        notes: editForm.notes || null,
      };

      const res = await adminInsurance.update(editItem.id, payload as Partial<InsuranceCertificate>);
      if (res?.success) {
        toast.success(t('insurance.update_success'));
        setEditItem(null);
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || t('insurance.update_failed'));
      }
    } catch {
      toast.error(t('insurance.update_failed'));
    } finally {
      setEditLoading(false);
    }
  };

  const openEditModal = (item: InsuranceCertificate) => {
    setEditForm({
      insurance_type: item.insurance_type,
      provider_name: item.provider_name || '',
      policy_number: item.policy_number || '',
      coverage_amount: item.coverage_amount ? String(item.coverage_amount) : '',
      start_date: item.start_date || '',
      expiry_date: item.expiry_date || '',
      notes: item.notes || '',
    });
    setEditItem(item);
  };

  const resetCreateForm = () => {
    setCreateForm({
      user_id: '',
      insurance_type: 'public_liability',
      provider_name: '',
      policy_number: '',
      coverage_amount: '',
      start_date: '',
      expiry_date: '',
      notes: '',
    });
    setSelectedUser(null);
    setUserSearchQuery('');
    setUserSearchResults([]);
  };

  const columns: Column<InsuranceCertificate>[] = [
    {
      key: 'member',
      label: t('insurance.col_member'),
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
      key: 'insurance_type',
      label: t('insurance.col_type'),
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat" color="primary">
          {formatInsuranceType(item.insurance_type)}
        </Chip>
      ),
    },
    {
      key: 'status',
      label: t('insurance.col_status'),
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
      key: 'provider_name',
      label: t('insurance.col_provider'),
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.provider_name || '—'}
        </span>
      ),
    },
    {
      key: 'policy_number',
      label: t('insurance.col_policy'),
      render: (item) => (
        <span className="text-sm text-default-600 font-mono">
          {item.policy_number || '—'}
        </span>
      ),
    },
    {
      key: 'expiry_date',
      label: t('insurance.col_expiry'),
      sortable: true,
      render: (item) => {
        const expiry = parseServerTimestamp(item.expiry_date);
        if (!expiry) return <span className="text-sm text-default-500">{'—'}</span>;
        const now = new Date();
        const daysUntilExpiry = Math.ceil((expiry.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
        // #12: Use configurable expiry warning days
        const isExpiringSoon = daysUntilExpiry > 0 && daysUntilExpiry <= expiryWarningDays;
        const isExpired = daysUntilExpiry <= 0;

        return (
          <span className={`text-sm ${isExpired ? 'text-danger font-medium' : isExpiringSoon ? 'text-warning font-medium' : 'text-default-500'}`}>
            {expiry.toLocaleDateString()}
          </span>
        );
      },
    },
    {
      key: 'actions',
      label: t('insurance.col_actions'),
      render: (item) => (
        <div className="flex gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            onPress={() => setViewItem(item)}
            aria-label={t('insurance.view_details_aria')}
          >
            <Eye size={14} />
          </Button>
          {/* #10: Edit button */}
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            onPress={() => openEditModal(item)}
            aria-label={t('insurance.edit_certificate_aria')}
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
                aria-label={t('insurance.verify_certificate_aria')}
              >
                <Check size={14} />
              </Button>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="danger"
                onPress={() => { setRejectModal(item); setRejectReason(''); }}
                aria-label={t('insurance.reject_certificate_aria')}
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
            aria-label={t('insurance.delete_certificate_aria')}
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
        title={t('insurance.page_title')}
        description={t('insurance.page_description')}
        actions={
          <div className="flex gap-2">
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              size="sm"
              onPress={() => { resetCreateForm(); setCreateOpen(true); }}
            >
              {t('insurance.add_certificate')}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/broker')}
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              size="sm"
            >
              {t('insurance.back')}
            </Button>
          </div>
        }
      />

      {/* Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <StatCard
          label={t('insurance.stat_total')}
          value={stats?.total ?? 0}
          icon={FileText}
          color="primary"
          loading={statsLoading}
        />
        <StatCard
          label={t('insurance.stat_pending')}
          value={stats?.pending ?? 0}
          icon={Clock}
          color="warning"
          loading={statsLoading}
        />
        <StatCard
          label={t('insurance.stat_verified')}
          value={stats?.verified ?? 0}
          icon={ShieldCheck}
          color="success"
          loading={statsLoading}
        />
        <StatCard
          label={t('insurance.stat_expiring_soon')}
          value={stats?.expiring_soon ?? 0}
          icon={ShieldAlert}
          color="danger"
          loading={statsLoading}
        />
      </div>

      {/* Search — #6: now debounced */}
      <div className="mb-4">
        <Input
          placeholder={t('insurance.search_placeholder')}
          aria-label={t('insurance.search_aria')}
          value={searchQuery}
          onValueChange={setSearchQuery}
          startContent={<Search size={16} className="text-default-400" />}
          variant="bordered"
          size="sm"
          className="max-w-md"
          isClearable
          onClear={() => setSearchQuery('')}
        />
      </div>

      {/* Filter Tabs */}
      <div className="mb-4">
        <Tabs
          selectedKey={statusFilter}
          onSelectionChange={(key) => { setStatusFilter(key as InsuranceStatus); setPage(1); }}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title={t('insurance.tab_all')} />
          <Tab key="pending" title={t('insurance.tab_pending')} />
          <Tab key="submitted" title={t('insurance.tab_submitted')} />
          <Tab key="verified" title={t('insurance.tab_verified')} />
          <Tab key="expired" title={t('insurance.tab_expired')} />
          <Tab key="expiring_soon" title={t('insurance.tab_expiring_soon')} />
          <Tab key="rejected" title={t('insurance.tab_rejected')} />
        </Tabs>
      </div>

      {/* Data Table */}
      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchable={false}
        onRefresh={loadItems}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
        emptyContent={
          <EmptyState
            icon={Users}
            title={t('insurance.empty_title')}
            description={statusFilter !== 'all' ? t('insurance.empty_try_filter') : t('insurance.empty_add_to_start')}
          />
        }
      />

      {/* Create Modal — #9: User search instead of raw ID */}
      <Modal
        isOpen={createOpen}
        onClose={() => setCreateOpen(false)}
        size="lg"
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Plus size={20} className="text-primary" />
            {t('insurance.modal_create_title')}
          </ModalHeader>
          <ModalBody className="gap-4">
            {/* #9: Member search autocomplete */}
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
                  {t('insurance.change')}
                </Button>
              </div>
            ) : (
              <div>
                <Input
                  label={t('insurance.search_member_label')}
                  placeholder={t('insurance.search_member_placeholder')}
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
                  <p className="text-xs text-default-400 mt-1">{t('insurance.no_members_found')}</p>
                )}
              </div>
            )}
            <Select
              label={t('insurance.field_insurance_type')}
              selectedKeys={[createForm.insurance_type]}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as InsuranceCertificate['insurance_type'];
                if (val) setCreateForm(prev => ({ ...prev, insurance_type: val }));
              }}
              variant="bordered"
              isRequired
            >
              {INSURANCE_TYPE_KEYS.map((key) => (
                <SelectItem key={key}>{t(INSURANCE_TYPE_LABEL_KEYS[key])}</SelectItem>
              ))}
            </Select>
            <Input
              label={t('insurance.field_provider_name')}
              placeholder={t('insurance.field_provider_name_placeholder')}
              value={createForm.provider_name}
              onValueChange={(val) => setCreateForm(prev => ({ ...prev, provider_name: val }))}
              variant="bordered"
            />
            <Input
              label={t('insurance.field_policy_number')}
              placeholder={t('insurance.field_policy_number_placeholder')}
              value={createForm.policy_number}
              onValueChange={(val) => setCreateForm(prev => ({ ...prev, policy_number: val }))}
              variant="bordered"
            />
            {/* #11: EUR instead of GBP */}
            <Input
              label={t('insurance.field_coverage_amount')}
              placeholder={t('insurance.field_coverage_amount_placeholder')}
              value={createForm.coverage_amount}
              onValueChange={(val) => setCreateForm(prev => ({ ...prev, coverage_amount: val }))}
              variant="bordered"
              type="number"
              startContent={<span className="text-default-400 text-sm">&euro;</span>}
            />
            <div className="grid grid-cols-2 gap-4">
              <Input
                label={t('insurance.field_start_date')}
                type="date"
                value={createForm.start_date}
                onValueChange={(val) => setCreateForm(prev => ({ ...prev, start_date: val }))}
                variant="bordered"
              />
              <Input
                label={t('insurance.field_expiry_date')}
                type="date"
                value={createForm.expiry_date}
                onValueChange={(val) => setCreateForm(prev => ({ ...prev, expiry_date: val }))}
                variant="bordered"
              />
            </div>
            <Textarea
              label={t('insurance.field_notes')}
              placeholder={t('insurance.field_notes_placeholder')}
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
              {t('insurance.cancel')}
            </Button>
            <Button
              color="primary"
              onPress={handleCreate}
              isLoading={createLoading}
            >
              {t('insurance.add_certificate')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* #10: Edit Modal */}
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
              {t('insurance.modal_edit_title')}
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
                label={t('insurance.field_insurance_type')}
                selectedKeys={[editForm.insurance_type]}
                onSelectionChange={(keys) => {
                  const val = Array.from(keys)[0] as InsuranceCertificate['insurance_type'];
                  if (val) setEditForm(prev => ({ ...prev, insurance_type: val }));
                }}
                variant="bordered"
                isRequired
              >
                {INSURANCE_TYPE_KEYS.map((key) => (
                  <SelectItem key={key}>{t(INSURANCE_TYPE_LABEL_KEYS[key])}</SelectItem>
                ))}
              </Select>
              <Input
                label={t('insurance.field_provider_name')}
                placeholder={t('insurance.field_provider_name_placeholder')}
                value={editForm.provider_name}
                onValueChange={(val) => setEditForm(prev => ({ ...prev, provider_name: val }))}
                variant="bordered"
              />
              <Input
                label={t('insurance.field_policy_number')}
                placeholder={t('insurance.field_policy_number_placeholder')}
                value={editForm.policy_number}
                onValueChange={(val) => setEditForm(prev => ({ ...prev, policy_number: val }))}
                variant="bordered"
              />
              <Input
                label={t('insurance.field_coverage_amount')}
                placeholder={t('insurance.field_coverage_amount_placeholder')}
                value={editForm.coverage_amount}
                onValueChange={(val) => setEditForm(prev => ({ ...prev, coverage_amount: val }))}
                variant="bordered"
                type="number"
                startContent={<span className="text-default-400 text-sm">&euro;</span>}
              />
              <div className="grid grid-cols-2 gap-4">
                <Input
                  label={t('insurance.field_start_date')}
                  type="date"
                  value={editForm.start_date}
                  onValueChange={(val) => setEditForm(prev => ({ ...prev, start_date: val }))}
                  variant="bordered"
                />
                <Input
                  label={t('insurance.field_expiry_date')}
                  type="date"
                  value={editForm.expiry_date}
                  onValueChange={(val) => setEditForm(prev => ({ ...prev, expiry_date: val }))}
                  variant="bordered"
                />
              </div>
              <Textarea
                label={t('insurance.field_notes')}
                placeholder={t('insurance.field_notes_placeholder')}
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
                {t('insurance.cancel')}
              </Button>
              <Button
                color="primary"
                onPress={handleEdit}
                isLoading={editLoading}
              >
                {t('insurance.save_changes')}
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
              {t('insurance.modal_reject_title')}
            </ModalHeader>
            <ModalBody>
              <p className="text-default-600 mb-3">
                {t('insurance.confirm_reject')}
              </p>
              <Textarea
                label={t('insurance.field_reason')}
                placeholder={t('insurance.field_reason_placeholder')}
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
                {t('insurance.cancel')}
              </Button>
              <Button
                color="danger"
                onPress={handleReject}
                isLoading={rejectLoading}
              >
                {t('insurance.reject')}
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>
      )}

      {/* View Detail Modal — #1: Added certificate file display */}
      {viewItem && (
        <Modal
          isOpen={!!viewItem}
          onClose={() => setViewItem(null)}
          size="lg"
        >
          <ModalContent>
            <ModalHeader className="flex items-center gap-2">
              <FileCheck size={20} className="text-primary" />
              {t('insurance.modal_view_title')}
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
                  <p className="text-default-400">{t('insurance.label_type')}</p>
                  <p className="font-medium">{formatInsuranceType(viewItem.insurance_type)}</p>
                </div>
                <div>
                  <p className="text-default-400">{t('insurance.label_status')}</p>
                  <Chip size="sm" variant="flat" color={STATUS_COLOR_MAP[viewItem.status] || 'default'} className="capitalize">
                    {t(`status.${viewItem.status}`, { defaultValue: viewItem.status })}
                  </Chip>
                </div>
                <div>
                  <p className="text-default-400">{t('insurance.label_provider')}</p>
                  <p className="font-medium">{viewItem.provider_name || '—'}</p>
                </div>
                <div>
                  <p className="text-default-400">{t('insurance.label_policy_number')}</p>
                  <p className="font-medium font-mono">{viewItem.policy_number || '—'}</p>
                </div>
                <div>
                  <p className="text-default-400">{t('insurance.label_coverage_amount')}</p>
                  {/* #11: EUR instead of GBP */}
                  <p className="font-medium">{viewItem.coverage_amount ? `€${Number(viewItem.coverage_amount).toLocaleString()}` : '—'}</p>
                </div>
                <div>
                  <p className="text-default-400">{t('insurance.label_start_date')}</p>
                  <p className="font-medium">{viewItem.start_date ? formatServerDate(viewItem.start_date) : '—'}</p>
                </div>
                <div>
                  <p className="text-default-400">{t('insurance.label_expiry_date')}</p>
                  <p className="font-medium">{viewItem.expiry_date ? formatServerDate(viewItem.expiry_date) : '—'}</p>
                </div>
                <div>
                  <p className="text-default-400">{t('insurance.label_verified_by')}</p>
                  <p className="font-medium">
                    {viewItem.verifier_first_name
                      ? `${viewItem.verifier_first_name} ${viewItem.verifier_last_name}`
                      : '—'}
                  </p>
                </div>
                <div>
                  <p className="text-default-400">{t('insurance.label_verified_at')}</p>
                  <p className="font-medium">{viewItem.verified_at ? formatServerDateTime(viewItem.verified_at) : '—'}</p>
                </div>
                <div>
                  <p className="text-default-400">{t('insurance.label_created')}</p>
                  <p className="font-medium">{formatServerDateTime(viewItem.created_at)}</p>
                </div>
              </div>
              {/* #1: Certificate file display/download */}
              {viewItem.certificate_file_path && (
                <div className="mt-4">
                  <p className="text-default-400 text-sm mb-1">{t('insurance.label_certificate_file')}</p>
                  <a
                    href={resolveAssetUrl(viewItem.certificate_file_path)}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 text-sm text-primary hover:underline bg-primary-50 dark:bg-primary-50/10 px-3 py-2 rounded-lg"
                  >
                    <FileText size={16} />
                    {t('insurance.view_certificate_file')}
                    <ExternalLink size={14} />
                  </a>
                </div>
              )}
              {viewItem.notes && (
                <div className="mt-4">
                  <p className="text-default-400 text-sm mb-1">{t('insurance.label_notes')}</p>
                  <p className="text-sm bg-default-100 p-3 rounded-lg">{viewItem.notes}</p>
                </div>
              )}
            </ModalBody>
            <ModalFooter>
              <Button variant="flat" onPress={() => setViewItem(null)}>
                {t('insurance.close')}
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
        title={t('insurance.confirm_delete_title')}
        message={deleteItem
          ? t('insurance.confirm_delete_message')
          : ''}
        confirmLabel={t('insurance.delete')}
        confirmColor="danger"
        isLoading={deleteLoading}
      />
    </div>
  );
}

export default InsuranceCertificates;
