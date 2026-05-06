// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Volunteer Organizations — Full CRUD management page.
 * Lists organizations with search/filter, wallet adjustments,
 * transaction history, and status toggling.
 */

import { useState, useCallback, useEffect, useMemo } from 'react';
import {
  Button,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  Select,
  SelectItem,
  useDisclosure,
} from '@heroui/react';
import Building2 from 'lucide-react/icons/building-2';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Wallet from 'lucide-react/icons/wallet';
import ArrowLeftRight from 'lucide-react/icons/arrow-left-right';
import ShieldCheck from 'lucide-react/icons/shield-check';
import ShieldOff from 'lucide-react/icons/shield-off';
import Search from 'lucide-react/icons/search';
import Pencil from 'lucide-react/icons/pencil';
import Users from 'lucide-react/icons/users';
import Plus from 'lucide-react/icons/plus';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, type Column } from '../../components';
import { useTranslation } from 'react-i18next';

interface VolOrg {
  id: number;
  org_id: number;
  org_name: string;
  description?: string | null;
  contact_email?: string | null;
  website?: string | null;
  org_type?: 'organisation' | 'club' | null;
  meeting_schedule?: string | null;
  status: string;
  balance: number;
  total_in: number;
  total_out: number;
  member_count: number;
  opportunity_count: number;
  total_hours: number;
  created_at: string;
}

interface Transaction {
  id: number;
  amount: number;
  type: string;
  description: string;
  created_at: string;
  admin_name?: string;
}

interface OrgMember {
  id: number;
  user_id: number;
  first_name: string;
  last_name: string;
  role: string;
  total_hours: number;
}

interface OrgFormData {
  name: string;
  description: string;
  contact_email: string;
  website: string;
  org_type: 'organisation' | 'club';
  meeting_schedule: string;
}

const EMPTY_ORG_FORM: OrgFormData = { name: '', description: '', contact_email: '', website: '', org_type: 'organisation', meeting_schedule: '' };
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const STATUS_COLORS: Record<string, 'success' | 'danger' | 'warning' | 'default'> = {
  active: 'success',
  suspended: 'danger',
  pending: 'warning',
};

export function VolunteerOrganizations() {
  const { t } = useTranslation('admin');
  usePageTitle(t('volunteering.volunteer_organizations_title', 'Volunteer Organizations'));
  const toast = useToast();

  // Data state
  const [items, setItems] = useState<VolOrg[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');

  // Adjust balance modal
  const adjustModal = useDisclosure();
  const [adjustOrg, setAdjustOrg] = useState<VolOrg | null>(null);
  const [adjustAmount, setAdjustAmount] = useState('');
  const [adjustReason, setAdjustReason] = useState('');
  const [adjustSubmitting, setAdjustSubmitting] = useState(false);

  // Transactions modal
  const txModal = useDisclosure();
  const [txOrg, setTxOrg] = useState<VolOrg | null>(null);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [txLoading, setTxLoading] = useState(false);
  const [txCursor, setTxCursor] = useState<string | null>(null);
  const [txHasMore, setTxHasMore] = useState(false);

  // Edit org modal
  const editModal = useDisclosure();
  const [editOrg, setEditOrg] = useState<VolOrg | null>(null);
  const [editForm, setEditForm] = useState<OrgFormData>(EMPTY_ORG_FORM);
  const [editSubmitting, setEditSubmitting] = useState(false);

  // Members modal
  const membersModal = useDisclosure();
  const [membersOrg, setMembersOrg] = useState<VolOrg | null>(null);
  const [members, setMembers] = useState<OrgMember[]>([]);
  const [membersLoading, setMembersLoading] = useState(false);

  // Create org modal
  const createModal = useDisclosure();
  const [createForm, setCreateForm] = useState<OrgFormData>(EMPTY_ORG_FORM);
  const [createSubmitting, setCreateSubmitting] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getOrganizations();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setItems((payload as { data: VolOrg[] }).data || []);
        }
      }
    } catch {
      toast.error(t('volunteering.failed_to_load_organizations', 'Failed to load organizations'));
      setItems([]);
    }
    setLoading(false);
  }, [toast]);


  useEffect(() => { loadData(); }, [loadData]);

  // Filtered data
  const filteredItems = useMemo(() => {
    let result = items;
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      result = result.filter((item) => item.org_name?.toLowerCase().includes(q));
    }
    if (statusFilter !== 'all') {
      result = result.filter((item) => item.status === statusFilter);
    }
    return result;
  }, [items, searchQuery, statusFilter]);

  // --- Adjust Balance ---
  const openAdjustModal = useCallback((org: VolOrg) => {
    setAdjustOrg(org);
    setAdjustAmount('');
    setAdjustReason('');
    adjustModal.onOpen();
  }, [adjustModal]);

  const handleAdjustSubmit = useCallback(async () => {
    if (!adjustOrg) return;
    const amount = parseFloat(adjustAmount);
    if (isNaN(amount) || amount === 0) {
      toast.error(t('volunteering.amount_nonzero', 'Amount must be a non-zero number'));
      return;
    }
    if (!adjustReason.trim()) {
      toast.error(t('volunteering.reason_required', 'Reason is required'));
      return;
    }
    setAdjustSubmitting(true);
    try {
      const res = await adminVolunteering.adjustOrgWallet(adjustOrg.id, amount, adjustReason.trim());
      if (res.success) {
        toast.success(t('volunteering.balance_adjusted', 'Balance adjusted successfully'));
        adjustModal.onClose();
        loadData();
      } else {
        toast.error((res as { message?: string }).message || t('volunteering.adjust_failed', 'Failed to adjust balance'));
      }
    } catch {
      toast.error(t('volunteering.adjust_failed', 'Failed to adjust balance'));
    }
    setAdjustSubmitting(false);
  }, [adjustOrg, adjustAmount, adjustReason, toast, t, adjustModal, loadData]);

  // --- View Transactions ---
  const openTxModal = useCallback(async (org: VolOrg) => {
    setTxOrg(org);
    setTransactions([]);
    setTxCursor(null);
    setTxHasMore(false);
    txModal.onOpen();
    setTxLoading(true);
    try {
      const res = await adminVolunteering.getOrgTransactions(org.id);
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setTransactions(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          const p = payload as { data: Transaction[]; meta?: { next_cursor?: string; has_more?: boolean } };
          setTransactions(p.data || []);
          setTxCursor(p.meta?.next_cursor || null);
          setTxHasMore(p.meta?.has_more || false);
        }
      }
    } catch {
      toast.error(t('volunteering.failed_load_transactions', 'Failed to load transactions'));
    }
    setTxLoading(false);
  }, [txModal, toast]);


  const loadMoreTx = useCallback(async () => {
    if (!txOrg || !txCursor) return;
    setTxLoading(true);
    try {
      const res = await adminVolunteering.getOrgTransactions(txOrg.id, txCursor);
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (payload && typeof payload === 'object' && 'data' in payload) {
          const p = payload as { data: Transaction[]; meta?: { next_cursor?: string; has_more?: boolean } };
          setTransactions((prev) => [...prev, ...(p.data || [])]);
          setTxCursor(p.meta?.next_cursor || null);
          setTxHasMore(p.meta?.has_more || false);
        }
      }
    } catch {
      toast.error(t('volunteering.failed_load_transactions', 'Failed to load transactions'));
    }
    setTxLoading(false);
  }, [txOrg, txCursor, toast]);


  // --- Status Toggle ---
  const handleStatusToggle = useCallback(async (org: VolOrg) => {
    const newStatus = org.status === 'active' ? 'suspended' : 'active';
    try {
      const res = await adminVolunteering.updateOrgStatus(org.id, newStatus);
      if (res.success) {
        toast.success(t('volunteering.status_updated', `Organization ${newStatus}`));
        loadData();
      } else {
        toast.error((res as { message?: string }).message || t('volunteering.status_update_failed', 'Failed to update status'));
      }
    } catch {
      toast.error(t('volunteering.status_update_failed', 'Failed to update status'));
    }
  }, [toast, t, loadData]);

  // --- Edit Organization ---
  const openEditModal = useCallback((org: VolOrg) => {
    setEditOrg(org);
    setEditForm({
      name: org.org_name || '',
      description: org.description || '',
      contact_email: org.contact_email || '',
      website: org.website || '',
      org_type: org.org_type || 'organisation',
      meeting_schedule: org.meeting_schedule || '',
    });
    editModal.onOpen();
  }, [editModal]);

  const handleEditSubmit = useCallback(async () => {
    if (!editOrg) return;
    if (!editForm.name.trim()) {
      toast.error(t('volunteering.name_required', 'Organization name is required'));
      return;
    }
    if (editForm.description.trim().length < 20) {
      toast.error(t('volunteering.description_min_length', 'Description must be at least 20 characters'));
      return;
    }
    if (!EMAIL_PATTERN.test(editForm.contact_email.trim())) {
      toast.error(t('volunteering.contact_email_required', 'A valid contact email is required'));
      return;
    }
    setEditSubmitting(true);
    try {
      const res = await adminVolunteering.updateOrganization(editOrg.org_id || editOrg.id, {
        name: editForm.name.trim(),
        description: editForm.description.trim(),
        contact_email: editForm.contact_email.trim(),
        website: editForm.website.trim(),
        org_type: editForm.org_type,
        meeting_schedule: editForm.meeting_schedule.trim() || undefined,
      });
      if (res.success) {
        toast.success(t('volunteering.org_updated', 'Organization updated'));
        editModal.onClose();
        loadData();
      } else {
        toast.error((res as { message?: string }).message || t('volunteering.org_update_failed', 'Failed to update organization'));
      }
    } catch {
      toast.error(t('volunteering.org_update_failed', 'Failed to update organization'));
    }
    setEditSubmitting(false);
  }, [editOrg, editForm, toast, t, editModal, loadData]);

  // --- View Members ---
  const openMembersModal = useCallback(async (org: VolOrg) => {
    setMembersOrg(org);
    setMembers([]);
    membersModal.onOpen();
    setMembersLoading(true);
    try {
      const res = await adminVolunteering.getOrgMembers(org.org_id || org.id);
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setMembers(payload as OrgMember[]);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setMembers((payload as { data: OrgMember[] }).data || []);
        }
      }
    } catch {
      toast.error(t('volunteering.failed_load_members', 'Failed to load members'));
    }
    setMembersLoading(false);
  }, [membersModal, toast]);


  // --- Create Organization ---
  const openCreateModal = useCallback(() => {
    setCreateForm(EMPTY_ORG_FORM);
    createModal.onOpen();
  }, [createModal]);

  const handleCreateSubmit = useCallback(async () => {
    if (!createForm.name.trim()) {
      toast.error(t('volunteering.name_required', 'Organization name is required'));
      return;
    }
    if (createForm.description.trim().length < 20) {
      toast.error(t('volunteering.description_min_length', 'Description must be at least 20 characters'));
      return;
    }
    if (!EMAIL_PATTERN.test(createForm.contact_email.trim())) {
      toast.error(t('volunteering.contact_email_required', 'A valid contact email is required'));
      return;
    }
    setCreateSubmitting(true);
    try {
      const res = await adminVolunteering.createOrganization({
        name: createForm.name.trim(),
        description: createForm.description.trim(),
        contact_email: createForm.contact_email.trim(),
        website: createForm.website.trim(),
        org_type: createForm.org_type,
        meeting_schedule: createForm.meeting_schedule.trim() || undefined,
      });
      if (res.success) {
        toast.success(t('volunteering.org_created', 'Organization created'));
        createModal.onClose();
        loadData();
      } else {
        toast.error((res as { message?: string }).message || t('volunteering.org_create_failed', 'Failed to create organization'));
      }
    } catch {
      toast.error(t('volunteering.org_create_failed', 'Failed to create organization'));
    }
    setCreateSubmitting(false);
  }, [createForm, toast, t, createModal, loadData]);

  const columns: Column<VolOrg>[] = [
    { key: 'org_name', label: t('volunteering.col_organization', 'Organization'), sortable: true },
    {
      key: 'status',
      label: t('volunteering.col_status', 'Status'),
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat" color={STATUS_COLORS[item.status] || 'default'} className="capitalize">
          {item.status || 'unknown'}
        </Chip>
      ),
    },
    {
      key: 'balance',
      label: t('volunteering.col_balance', 'Balance'),
      sortable: true,
      render: (item) => <span className="font-mono">{(item.balance ?? 0).toLocaleString()} hrs</span>,
    },
    {
      key: 'opportunity_count',
      label: t('volunteering.col_opportunities', 'Opportunities'),
      sortable: true,
      render: (item) => <span>{item.opportunity_count ?? 0}</span>,
    },
    {
      key: 'member_count',
      label: t('volunteering.col_volunteers', 'Volunteers'),
      sortable: true,
      render: (item) => <span>{item.member_count ?? 0}</span>,
    },
    {
      key: 'total_hours',
      label: t('volunteering.col_total_hours', 'Total Hours'),
      sortable: true,
      render: (item) => <span className="font-mono">{(item.total_hours ?? 0).toLocaleString()}</span>,
    },
    {
      key: 'created_at',
      label: t('volunteering.col_created', 'Created'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('common.actions', 'Actions'),
      render: (item) => (
        <div className="flex gap-1 flex-wrap">
          <Button
            size="sm"
            variant="flat"
            color="default"
            startContent={<Pencil size={14} />}
            onPress={() => openEditModal(item)}
          >
            {t('common.edit', 'Edit')}
          </Button>
          <Button
            size="sm"
            variant="flat"
            color="default"
            startContent={<Users size={14} />}
            onPress={() => openMembersModal(item)}
          >
            {t('volunteering.members', 'Members')}
          </Button>
          <Button
            size="sm"
            variant="flat"
            color="primary"
            startContent={<Wallet size={14} />}
            onPress={() => openAdjustModal(item)}
          >
            {t('volunteering.adjust_balance', 'Adjust')}
          </Button>
          <Button
            size="sm"
            variant="flat"
            color="secondary"
            startContent={<ArrowLeftRight size={14} />}
            onPress={() => openTxModal(item)}
          >
            {t('volunteering.transactions', 'Txns')}
          </Button>
          <Button
            size="sm"
            variant="flat"
            color={item.status === 'active' ? 'danger' : 'success'}
            startContent={item.status === 'active' ? <ShieldOff size={14} /> : <ShieldCheck size={14} />}
            onPress={() => handleStatusToggle(item)}
          >
            {item.status === 'active'
              ? t('volunteering.suspend', 'Suspend')
              : t('volunteering.activate', 'Activate')}
          </Button>
        </div>
      ),
    },
  ];

  // Top content: search + filter
  const topContent = useMemo(() => (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <Input
        className="max-w-xs"
        placeholder={t('volunteering.search_organizations', 'Search organizations...')}
        startContent={<Search size={16} className="text-default-400" />}
        value={searchQuery}
        onValueChange={setSearchQuery}
        isClearable
        onClear={() => setSearchQuery('')}
      />
      <Select
        className="max-w-[180px]"
        label={t('volunteering.filter_status', 'Status')}
        size="sm"
        selectedKeys={new Set([statusFilter])}
        onSelectionChange={(keys) => {
          const val = Array.from(keys)[0] as string;
          setStatusFilter(val || 'all');
        }}
      >
        <SelectItem key="all">{t('common.all', 'All')}</SelectItem>
        <SelectItem key="active">{t('common.active', 'Active')}</SelectItem>
        <SelectItem key="suspended">{t('volunteering.suspended', 'Suspended')}</SelectItem>
        <SelectItem key="pending">{t('common.pending', 'Pending')}</SelectItem>
      </Select>
    </div>
  ), [searchQuery, statusFilter]);

  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader
          title={t('volunteering.volunteer_organizations_title', 'Volunteer Organizations')}
          description={t('volunteering.volunteer_organizations_desc', 'Manage volunteer organizations, wallets, and statuses')}
        />
        <EmptyState
          icon={Building2}
          title={t('volunteering.no_organizations', 'No organizations')}
          description={t('volunteering.desc_no_volunteer_organizations_have_cre', 'No volunteer organizations have been created yet.')}
        />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('volunteering.volunteer_organizations_title', 'Volunteer Organizations')}
        description={t('volunteering.volunteer_organizations_desc', 'Manage volunteer organizations, wallets, and statuses')}
        actions={
          <div className="flex gap-2">
            <Button variant="flat" color="primary" startContent={<Plus size={16} />} onPress={openCreateModal}>
              {t('volunteering.create_organization', 'Create Organization')}
            </Button>
            <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>
              {t('common.refresh', 'Refresh')}
            </Button>
          </div>
        }
      />

      <DataTable
        columns={columns}
        data={filteredItems}
        isLoading={loading}
        onRefresh={loadData}
        searchable={false}
        topContent={topContent}
      />

      {/* Adjust Balance Modal */}
      <Modal isOpen={adjustModal.isOpen} onOpenChange={adjustModal.onOpenChange} size="md">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>
                {t('volunteering.adjust_wallet_title', 'Adjust Wallet Balance')}
                {adjustOrg && (
                  <span className="block text-sm font-normal text-default-500 mt-1">
                    {adjustOrg.org_name} — {t('volunteering.current_balance', 'Current balance')}: {adjustOrg.balance ?? 0} hrs
                  </span>
                )}
              </ModalHeader>
              <ModalBody>
                <Input
                  label={t('volunteering.amount_label', 'Amount (positive = top-up, negative = deduct)')}
                  type="number"
                  value={adjustAmount}
                  onValueChange={setAdjustAmount}
                  placeholder="e.g. 50 or -20"
                  variant="bordered"
                />
                <Textarea
                  label={t('volunteering.reason_label', 'Reason')}
                  value={adjustReason}
                  onValueChange={setAdjustReason}
                  placeholder={t('volunteering.reason_placeholder', 'Explain the adjustment...')}
                  variant="bordered"
                  minRows={2}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  {t('common.cancel', 'Cancel')}
                </Button>
                <Button color="primary" onPress={handleAdjustSubmit} isLoading={adjustSubmitting}>
                  {t('volunteering.submit_adjustment', 'Submit Adjustment')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Transactions Modal */}
      <Modal isOpen={txModal.isOpen} onOpenChange={txModal.onOpenChange} size="2xl" scrollBehavior="inside">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>
                {t('volunteering.transaction_history', 'Transaction History')}
                {txOrg && (
                  <span className="block text-sm font-normal text-default-500 mt-1">{txOrg.org_name}</span>
                )}
              </ModalHeader>
              <ModalBody>
                {txLoading && transactions.length === 0 ? (
                  <div className="flex justify-center py-8">
                    <span className="text-default-400">{t('common.loading', 'Loading...')}</span>
                  </div>
                ) : transactions.length === 0 ? (
                  <div className="flex flex-col items-center py-8 text-default-400">
                    <ArrowLeftRight size={40} className="mb-2" />
                    <p>{t('volunteering.no_transactions', 'No transactions found')}</p>
                  </div>
                ) : (
                  <div className="space-y-2">
                    {transactions.map((tx) => (
                      <div
                        key={tx.id}
                        className="flex items-center justify-between rounded-lg border border-default-200 p-3"
                      >
                        <div>
                          <p className="text-sm font-medium">{tx.description || tx.type}</p>
                          <p className="text-xs text-default-400">
                            {tx.created_at ? new Date(tx.created_at).toLocaleString() : '--'}
                            {tx.admin_name && ` — by ${tx.admin_name}`}
                          </p>
                        </div>
                        <span
                          className={`font-mono text-sm font-semibold ${
                            tx.amount > 0 ? 'text-success' : 'text-danger'
                          }`}
                        >
                          {tx.amount > 0 ? '+' : ''}{tx.amount}
                        </span>
                      </div>
                    ))}
                    {txHasMore && (
                      <div className="flex justify-center pt-2">
                        <Button size="sm" variant="flat" onPress={loadMoreTx} isLoading={txLoading}>
                          {t('common.load_more', 'Load More')}
                        </Button>
                      </div>
                    )}
                  </div>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  {t('common.close', 'Close')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Edit Organization Modal */}
      <Modal isOpen={editModal.isOpen} onOpenChange={editModal.onOpenChange} size="lg">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>
                {t('volunteering.edit_organization', 'Edit Organization')}
                {editOrg && (
                  <span className="block text-sm font-normal text-default-500 mt-1">{editOrg.org_name}</span>
                )}
              </ModalHeader>
              <ModalBody className="flex flex-col gap-3">
                <Input
                  label={t('volunteering.org_name_label', 'Organization Name')}
                  value={editForm.name}
                  onValueChange={(v) => setEditForm(prev => ({ ...prev, name: v }))}
                  variant="bordered"
                  isRequired
                />
                <Textarea
                  label={t('volunteering.org_description_label', 'Description')}
                  value={editForm.description}
                  onValueChange={(v) => setEditForm(prev => ({ ...prev, description: v }))}
                  variant="bordered"
                  minRows={3}
                />
                <Input
                  label={t('volunteering.org_email_label', 'Contact Email')}
                  type="email"
                  value={editForm.contact_email}
                  onValueChange={(v) => setEditForm(prev => ({ ...prev, contact_email: v }))}
                  variant="bordered"
                />
                <Input
                  label={t('volunteering.org_website_label', 'Website')}
                  type="url"
                  value={editForm.website}
                  onValueChange={(v) => setEditForm(prev => ({ ...prev, website: v }))}
                  variant="bordered"
                  placeholder="https://"
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  {t('common.cancel', 'Cancel')}
                </Button>
                <Button color="primary" onPress={handleEditSubmit} isLoading={editSubmitting}>
                  {t('common.save', 'Save')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Members Modal */}
      <Modal isOpen={membersModal.isOpen} onOpenChange={membersModal.onOpenChange} size="2xl" scrollBehavior="inside">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>
                {t('volunteering.organization_members', 'Organization Members')}
                {membersOrg && (
                  <span className="block text-sm font-normal text-default-500 mt-1">
                    {membersOrg.org_name} — {membersOrg.member_count ?? 0} {t('volunteering.members', 'members')}
                  </span>
                )}
              </ModalHeader>
              <ModalBody>
                {membersLoading ? (
                  <div className="flex justify-center py-8">
                    <span className="text-default-400">{t('common.loading', 'Loading...')}</span>
                  </div>
                ) : members.length === 0 ? (
                  <div className="flex flex-col items-center py-8 text-default-400">
                    <Users size={40} className="mb-2" />
                    <p>{t('volunteering.no_members', 'No members found')}</p>
                  </div>
                ) : (
                  <div className="space-y-2">
                    {members.map((m) => (
                      <div
                        key={m.id || m.user_id}
                        className="flex items-center justify-between rounded-lg border border-default-200 p-3"
                      >
                        <div className="flex items-center gap-3">
                          <div className="w-8 h-8 rounded-full bg-default-100 flex items-center justify-center text-xs font-semibold text-default-600">
                            {(m.first_name?.[0] || '').toUpperCase()}{(m.last_name?.[0] || '').toUpperCase()}
                          </div>
                          <div>
                            <p className="text-sm font-medium">{m.first_name} {m.last_name}</p>
                            <p className="text-xs text-default-400 capitalize">{m.role || t('volunteering.volunteer', 'Volunteer')}</p>
                          </div>
                        </div>
                        <span className="text-sm font-mono text-default-500">
                          {(m.total_hours ?? 0).toLocaleString()} {t('volunteering.hrs', 'hrs')}
                        </span>
                      </div>
                    ))}
                  </div>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  {t('common.close', 'Close')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Create Organization Modal */}
      <Modal isOpen={createModal.isOpen} onOpenChange={createModal.onOpenChange} size="lg">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>
                {t('volunteering.create_organization', 'Create Organization')}
              </ModalHeader>
              <ModalBody className="flex flex-col gap-3">
                <Input
                  label={t('volunteering.org_name_label', 'Organization Name')}
                  value={createForm.name}
                  onValueChange={(v) => setCreateForm(prev => ({ ...prev, name: v }))}
                  variant="bordered"
                  isRequired
                />
                <Textarea
                  label={t('volunteering.org_description_label', 'Description')}
                  value={createForm.description}
                  onValueChange={(v) => setCreateForm(prev => ({ ...prev, description: v }))}
                  variant="bordered"
                  minRows={3}
                />
                <Input
                  label={t('volunteering.org_email_label', 'Contact Email')}
                  type="email"
                  value={createForm.contact_email}
                  onValueChange={(v) => setCreateForm(prev => ({ ...prev, contact_email: v }))}
                  variant="bordered"
                />
                <Input
                  label={t('volunteering.org_website_label', 'Website')}
                  type="url"
                  value={createForm.website}
                  onValueChange={(v) => setCreateForm(prev => ({ ...prev, website: v }))}
                  variant="bordered"
                  placeholder="https://"
                />
                <Select
                  label={t('volunteering.org_type_label', 'Type')}
                  selectedKeys={new Set([createForm.org_type])}
                  onSelectionChange={(keys) => {
                    const val = Array.from(keys)[0] as 'organisation' | 'club';
                    setCreateForm(prev => ({ ...prev, org_type: val || 'organisation' }));
                  }}
                  variant="bordered"
                >
                  <SelectItem key="organisation">{t('volunteering.org_type_organisation', 'Organisation')}</SelectItem>
                  <SelectItem key="club">{t('volunteering.org_type_club', 'Club')}</SelectItem>
                </Select>
                {createForm.org_type === 'club' && (
                  <Input
                    label={t('volunteering.meeting_schedule_label', 'Meeting Schedule')}
                    value={createForm.meeting_schedule}
                    onValueChange={(v) => setCreateForm(prev => ({ ...prev, meeting_schedule: v }))}
                    variant="bordered"
                    placeholder={t('volunteering.meeting_schedule_placeholder', 'e.g. Every Tuesday 19:00')}
                  />
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  {t('common.cancel', 'Cancel')}
                </Button>
                <Button color="primary" onPress={handleCreateSubmit} isLoading={createSubmitting}>
                  {t('volunteering.create', 'Create')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default VolunteerOrganizations;
