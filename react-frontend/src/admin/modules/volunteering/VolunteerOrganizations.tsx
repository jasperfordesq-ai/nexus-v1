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
import {
  Building2,
  RefreshCw,
  Wallet,
  ArrowLeftRight,
  ShieldCheck,
  ShieldOff,
  Search,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, type Column } from '../../components';
import { useTranslation } from 'react-i18next';

interface VolOrg {
  id: number;
  org_id: number;
  org_name: string;
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
  }, [toast, t]);

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
  }, [txModal, toast, t]);

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
  }, [txOrg, txCursor, toast, t]);

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
  ), [searchQuery, statusFilter, t]);

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
          <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>
            {t('common.refresh', 'Refresh')}
          </Button>
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
    </div>
  );
}

export default VolunteerOrganizations;
