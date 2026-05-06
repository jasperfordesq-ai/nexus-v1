// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Volunteer Expenses
 * Admin page for managing volunteer expense submissions, reviews, and policies.
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
  Card,
  CardBody,
  CardHeader,
  Accordion,
  AccordionItem,
} from '@heroui/react';
import DollarSign from 'lucide-react/icons/dollar-sign';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Download from 'lucide-react/icons/download';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import XCircle from 'lucide-react/icons/circle-x';
import CreditCard from 'lucide-react/icons/credit-card';
import Clock from 'lucide-react/icons/clock';
import FileText from 'lucide-react/icons/file-text';
import Settings from 'lucide-react/icons/settings';
import CalendarRange from 'lucide-react/icons/calendar-range';
import Building2 from 'lucide-react/icons/building-2';
import Eye from 'lucide-react/icons/eye';
import ExternalLink from 'lucide-react/icons/external-link';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { DataTable, PageHeader, StatCard, EmptyState, type Column } from '../../components';
import { useTranslation } from 'react-i18next';

// ── Types ──────────────────────────────────────────────────────────────────────

interface Expense {
  id: number;
  volunteer_name: string;
  organization_name: string;
  amount: number;
  currency: string;
  type: 'travel' | 'meals' | 'supplies' | 'equipment' | 'parking' | 'other';
  status: 'pending' | 'approved' | 'rejected' | 'paid';
  submitted_at: string;
  has_receipt: boolean;
  receipt_path?: string;
  description?: string;
  review_notes?: string;
  payment_reference?: string;
}

interface ExpenseStats {
  total_submitted: number;
  pending_review: number;
  approved_total: number;
  paid_total: number;
}

interface ExpensePolicy {
  id?: number;
  type: string;
  expense_type?: string;
  max_amount: number;
  max_monthly: number;
  requires_receipt_above: number;
  requires_approval: boolean;
}

// ── Helpers ────────────────────────────────────────────────────────────────────

const STATUS_COLORS: Record<string, 'warning' | 'success' | 'danger' | 'primary'> = {
  pending: 'warning',
  approved: 'success',
  rejected: 'danger',
  paid: 'primary',
};

const TYPE_LABELS: Record<string, string> = {
  travel: 'Travel',
  meals: 'Meals',
  supplies: 'Supplies',
  equipment: 'Equipment',
  parking: 'Parking',
  other: 'Other',
};

function parsePayload<T>(raw: unknown): T {
  if (raw && typeof raw === 'object' && 'data' in raw) {
    return (raw as { data: T }).data;
  }
  return raw as T;
}

// ── Component ──────────────────────────────────────────────────────────────────

export function VolunteerExpenses() {
  const { t } = useTranslation('admin');
  usePageTitle(t('volunteering.expenses_page_title', 'Volunteer Expenses'));
  const toast = useToast();

  const [expenses, setExpenses] = useState<Expense[]>([]);
  const [stats, setStats] = useState<ExpenseStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);

  // Review modal
  const [reviewModal, setReviewModal] = useState(false);
  const [reviewExpense, setReviewExpense] = useState<Expense | null>(null);
  const [reviewAction, setReviewAction] = useState<'approved' | 'rejected' | 'paid'>('approved');
  const [reviewNotes, setReviewNotes] = useState('');
  const [paymentReference, setPaymentReference] = useState('');

  // Date range filter
  const [dateFrom, setDateFrom] = useState<string>('');
  const [dateTo, setDateTo] = useState<string>('');

  // Receipt preview modal
  const [receiptModal, setReceiptModal] = useState(false);
  const [receiptUrl, setReceiptUrl] = useState<string>('');
  const [receiptIsPdf, setReceiptIsPdf] = useState(false);

  // Policies
  const [policies, setPolicies] = useState<ExpensePolicy[]>([]);
  const [policiesLoading, setPoliciesLoading] = useState(false);
  const [policyModal, setPolicyModal] = useState(false);
  const [editingPolicy, setEditingPolicy] = useState<ExpensePolicy | null>(null);
  const [policyForm, setPolicyForm] = useState({
    max_amount: '',
    max_monthly: '',
    requires_receipt_above: '',
    requires_approval: true,
  });

  // ── Data loading ───────────────────────────────────────────────────────────

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getExpenses();
      if (res.success && res.data) {
        const payload = parsePayload<{ items?: Expense[]; expenses?: Expense[]; stats?: ExpenseStats } | Expense[]>(res.data);
        const rows = Array.isArray(payload) ? payload : payload.items || payload.expenses || [];
        setExpenses(rows);
        setStats(Array.isArray(payload) ? null : payload.stats || null);
      }
    } catch {
      toast.error(t('volunteering.failed_to_load_expenses', 'Failed to load expenses'));
      setExpenses([]);
      setStats(null);
    }
    setLoading(false);
  }, [toast]);


  const loadPolicies = useCallback(async () => {
    setPoliciesLoading(true);
    try {
      const res = await adminVolunteering.getExpensePolicies();
      if (res.success && res.data) {
        const payload = parsePayload<ExpensePolicy[] | { policies: ExpensePolicy[] }>(res.data);
        setPolicies(Array.isArray(payload) ? payload : (payload as { policies: ExpensePolicy[] }).policies || []);
      }
    } catch {
      toast.error(t('volunteering.failed_to_load_policies', 'Failed to load expense policies'));
    }
    setPoliciesLoading(false);
  }, [toast]);


  useEffect(() => { loadData(); loadPolicies(); }, [loadData, loadPolicies]);

  // ── Filtered data (by date range) ─────────────────────────────────────────
  const filteredExpenses = useMemo(() => {
    return expenses.filter((item) => {
      if (!item.submitted_at) return true;
      const itemDate = new Date(item.submitted_at);
      if (dateFrom) {
        const from = new Date(dateFrom);
        from.setHours(0, 0, 0, 0);
        if (itemDate < from) return false;
      }
      if (dateTo) {
        const to = new Date(dateTo);
        to.setHours(23, 59, 59, 999);
        if (itemDate > to) return false;
      }
      return true;
    });
  }, [expenses, dateFrom, dateTo]);

  // ── Per-org breakdown ───────────────────────────────────────────────────────
  const orgBreakdown = useMemo(() => {
    const map = new Map<string, { total: number; count: number; pending: number; approved: number }>();
    filteredExpenses.forEach((item) => {
      const orgName = item.organization_name || t('volunteering.unknown_org', 'Unknown');
      if (!map.has(orgName)) map.set(orgName, { total: 0, count: 0, pending: 0, approved: 0 });
      const entry = map.get(orgName)!;
      entry.total += item.amount;
      entry.count += 1;
      if (item.status === 'pending') entry.pending += item.amount;
      if (item.status === 'approved' || item.status === 'paid') entry.approved += item.amount;
    });
    return Array.from(map.entries())
      .map(([name, data]) => ({ name, ...data }))
      .sort((a, b) => b.total - a.total);
  }, [filteredExpenses]);


  // ── Actions ────────────────────────────────────────────────────────────────

  const openReceipt = (expense: Expense) => {
    if (!expense.receipt_path) return;
    const isPdf = expense.receipt_path.toLowerCase().endsWith('.pdf');
    setReceiptUrl(expense.receipt_path);
    setReceiptIsPdf(isPdf);
    if (isPdf) {
      window.open(expense.receipt_path, '_blank');
    } else {
      setReceiptModal(true);
    }
  };

  const openReview = (expense: Expense) => {
    setReviewExpense(expense);
    setReviewAction('approved');
    setReviewNotes('');
    setPaymentReference('');
    setReviewModal(true);
  };

  const handleReview = async () => {
    if (!reviewExpense) return;
    setActionLoading(true);
    try {
      const data: { status: string; review_notes?: string; payment_reference?: string } = {
        status: reviewAction,
      };
      if (reviewNotes.trim()) data.review_notes = reviewNotes.trim();
      if (reviewAction === 'paid' && paymentReference.trim()) {
        data.payment_reference = paymentReference.trim();
      }
      const res = await adminVolunteering.reviewExpense(reviewExpense.id, data);
      if (res.success) {
        toast.success(t('volunteering.expense_updated', 'Expense updated successfully'));
        setReviewModal(false);
        loadData();
      } else {
        toast.error(t('volunteering.failed_to_update_expense', 'Failed to update expense'));
      }
    } catch {
      toast.error(t('volunteering.failed_to_update_expense', 'Failed to update expense'));
    }
    setActionLoading(false);
  };

  const handleExport = async () => {
    try {
      const res = await adminVolunteering.exportExpenses();
      const blob = res instanceof Blob ? res : new Blob([res as BlobPart], { type: 'text/csv' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `volunteer-expenses-${new Date().toISOString().split('T')[0]}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      toast.error(t('volunteering.export_failed', 'Failed to export expenses'));
    }
  };

  const openPolicyEdit = (policy: ExpensePolicy) => {
    setEditingPolicy(policy);
    setPolicyForm({
      max_amount: String(policy.max_amount),
      max_monthly: String(policy.max_monthly),
      requires_receipt_above: String(policy.requires_receipt_above),
      requires_approval: policy.requires_approval,
    });
    setPolicyModal(true);
  };

  const handlePolicySave = async () => {
    if (!editingPolicy) return;
    setActionLoading(true);
    try {
      const res = await adminVolunteering.updateExpensePolicies({
        id: editingPolicy.id,
        expense_type: editingPolicy.expense_type ?? editingPolicy.type,
        max_amount: Number(policyForm.max_amount),
        max_monthly: Number(policyForm.max_monthly),
        requires_receipt_above: Number(policyForm.requires_receipt_above),
        requires_approval: policyForm.requires_approval,
      });
      if (res.success) {
        toast.success(t('volunteering.policy_updated', 'Policy updated successfully'));
        setPolicyModal(false);
        loadPolicies();
      } else {
        toast.error(t('volunteering.failed_to_update_policy', 'Failed to update policy'));
      }
    } catch {
      toast.error(t('volunteering.failed_to_update_policy', 'Failed to update policy'));
    }
    setActionLoading(false);
  };

  // ── Columns ────────────────────────────────────────────────────────────────

  const columns: Column<Expense>[] = [
    {
      key: 'volunteer_name',
      label: t('volunteering.col_volunteer', 'Volunteer'),
      sortable: true,
    },
    {
      key: 'organization_name',
      label: t('volunteering.col_organization', 'Organization'),
      sortable: true,
    },
    {
      key: 'amount',
      label: t('volunteering.col_amount', 'Amount'),
      sortable: true,
      render: (item) => (
        <span className="font-semibold">
          {item.currency || '\u20AC'}{item.amount.toFixed(2)}
        </span>
      ),
    },
    {
      key: 'type',
      label: t('volunteering.col_type', 'Type'),
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat">
          {TYPE_LABELS[item.type] || item.type}
        </Chip>
      ),
    },
    {
      key: 'status',
      label: t('volunteering.col_status', 'Status'),
      sortable: true,
      render: (item) => (
        <Chip size="sm" color={STATUS_COLORS[item.status] || 'default'} variant="flat">
          {item.status.charAt(0).toUpperCase() + item.status.slice(1)}
        </Chip>
      ),
    },
    {
      key: 'submitted_at',
      label: t('volunteering.col_submitted', 'Submitted'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.submitted_at ? new Date(item.submitted_at).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'has_receipt',
      label: t('volunteering.col_receipt', 'Receipt?'),
      render: (item) =>
        item.has_receipt ? (
          <div className="flex items-center gap-1.5">
            <Chip size="sm" color="success" variant="flat" startContent={<FileText size={12} />}>
              {t('common.yes', 'Yes')}
            </Chip>
            {item.receipt_path && (
              <Button
                size="sm"
                variant="light"
                color="primary"
                startContent={item.receipt_path.toLowerCase().endsWith('.pdf') ? <ExternalLink size={12} /> : <Eye size={12} />}
                onPress={() => openReceipt(item)}
                className="min-w-0 px-2"
              >
                {t('volunteering.view_receipt', 'View')}
              </Button>
            )}
          </div>
        ) : (
          <span className="text-sm text-default-400">{t('common.no', 'No')}</span>
        ),
    },
    {
      key: 'actions',
      label: t('volunteering.col_actions', 'Actions'),
      render: (item) => (
        <Button
          size="sm"
          variant="flat"
          color="primary"
          onPress={() => openReview(item)}
        >
          {t('volunteering.review', 'Review')}
        </Button>
      ),
    },
  ];

  // ── Render ─────────────────────────────────────────────────────────────────

  return (
    <div>
      <PageHeader
        title={t('volunteering.expenses_title', 'Volunteer Expenses')}
        description={t('volunteering.expenses_desc', 'Review and manage volunteer expense claims')}
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              startContent={<Download size={16} />}
              onPress={handleExport}
            >
              {t('volunteering.export_csv', 'Export CSV')}
            </Button>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              isLoading={loading}
            >
              {t('common.refresh', 'Refresh')}
            </Button>
          </div>
        }
      />

      {/* Stats Row */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label={t('volunteering.stat_total_submitted', 'Total Submitted')}
          value={stats?.total_submitted ?? 0}
          icon={FileText}
          color="default"
          loading={loading}
        />
        <StatCard
          label={t('volunteering.stat_pending_review', 'Pending Review')}
          value={stats?.pending_review ?? 0}
          icon={Clock}
          color="warning"
          loading={loading}
        />
        <StatCard
          label={t('volunteering.stat_approved_total', 'Approved Total')}
          value={stats?.approved_total ?? 0}
          icon={CheckCircle}
          color="success"
          loading={loading}
        />
        <StatCard
          label={t('volunteering.stat_paid_total', 'Paid Total')}
          value={stats?.paid_total ?? 0}
          icon={CreditCard}
          color="primary"
          loading={loading}
        />
      </div>

      {/* Date range filter */}
      <div className="flex flex-wrap items-end gap-3 mb-4">
        <Input
          type="date"
          label={t('volunteering.date_from', 'From')}
          size="sm"
          className="w-44"
          value={dateFrom}
          onValueChange={setDateFrom}
          startContent={<CalendarRange size={14} className="text-default-400" />}
        />
        <Input
          type="date"
          label={t('volunteering.date_to', 'To')}
          size="sm"
          className="w-44"
          value={dateTo}
          onValueChange={setDateTo}
          startContent={<CalendarRange size={14} className="text-default-400" />}
        />
        {(dateFrom || dateTo) && (
          <Button
            size="sm"
            variant="light"
            onPress={() => { setDateFrom(''); setDateTo(''); }}
          >
            {t('common.clear_filters', 'Clear dates')}
          </Button>
        )}
      </div>

      {/* Expenses Table */}
      {!loading && filteredExpenses.length === 0 ? (
        <EmptyState
          icon={DollarSign}
          title={t('volunteering.no_expenses', 'No expenses submitted')}
          description={t('volunteering.no_expenses_desc', 'There are no volunteer expense claims to review.')}
        />
      ) : (
        <DataTable columns={columns} data={filteredExpenses} isLoading={loading} onRefresh={loadData} />
      )}

      {/* Per-org expense breakdown */}
      {orgBreakdown.length > 0 && (
        <Card className="mt-6">
          <CardHeader>
            <div className="flex items-center gap-2">
              <Building2 size={18} />
              <span className="font-semibold">
                {t('volunteering.expense_org_breakdown_title', 'Expenses by Organization')}
              </span>
            </div>
          </CardHeader>
          <CardBody>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-divider text-left">
                    <th className="py-2 px-3 font-medium text-default-500">
                      {t('volunteering.col_organization', 'Organization')}
                    </th>
                    <th className="py-2 px-3 font-medium text-default-500 text-right">
                      {t('volunteering.col_claims', 'Claims')}
                    </th>
                    <th className="py-2 px-3 font-medium text-default-500 text-right">
                      {t('volunteering.col_pending_amount', 'Pending')}
                    </th>
                    <th className="py-2 px-3 font-medium text-default-500 text-right">
                      {t('volunteering.col_approved_amount', 'Approved/Paid')}
                    </th>
                    <th className="py-2 px-3 font-medium text-default-500 text-right">
                      {t('volunteering.col_total_amount', 'Total')}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {orgBreakdown.map((org) => (
                    <tr key={org.name} className="border-b border-divider/50 hover:bg-default-50">
                      <td className="py-2 px-3 font-medium">{org.name}</td>
                      <td className="py-2 px-3 text-right font-mono">{org.count}</td>
                      <td className="py-2 px-3 text-right font-mono text-warning">
                        {org.pending > 0 ? org.pending.toFixed(2) : '--'}
                      </td>
                      <td className="py-2 px-3 text-right font-mono text-success">
                        {org.approved > 0 ? org.approved.toFixed(2) : '--'}
                      </td>
                      <td className="py-2 px-3 text-right font-mono font-semibold">
                        {org.total.toFixed(2)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Expense Policies (collapsible) */}
      <Card className="mt-6">
        <CardHeader>
          <div className="flex items-center gap-2">
            <Settings size={18} />
            <span className="font-semibold">
              {t('volunteering.expense_policies_title', 'Expense Policies')}
            </span>
          </div>
        </CardHeader>
        <CardBody>
          {policiesLoading ? (
            <p className="text-default-400 text-sm">{t('common.loading', 'Loading...')}</p>
          ) : policies.length === 0 ? (
            <p className="text-default-400 text-sm">
              {t('volunteering.no_policies', 'No expense policies configured.')}
            </p>
          ) : (
            <Accordion variant="splitted">
              {policies.map((policy) => (
                <AccordionItem
                  key={policy.type}
                  title={
                    <span className="font-medium">
                      {TYPE_LABELS[policy.type] || policy.type}
                    </span>
                  }
                >
                  <div className="grid grid-cols-2 gap-4 text-sm mb-3">
                    <div>
                      <span className="text-default-400">
                        {t('volunteering.policy_max_amount', 'Max Amount')}:
                      </span>{' '}
                      <span className="font-medium">{policy.max_amount}</span>
                    </div>
                    <div>
                      <span className="text-default-400">
                        {t('volunteering.policy_max_monthly', 'Max Monthly')}:
                      </span>{' '}
                      <span className="font-medium">{policy.max_monthly}</span>
                    </div>
                    <div>
                      <span className="text-default-400">
                        {t('volunteering.policy_receipt_threshold', 'Receipt Required Above')}:
                      </span>{' '}
                      <span className="font-medium">{policy.requires_receipt_above}</span>
                    </div>
                    <div>
                      <span className="text-default-400">
                        {t('volunteering.policy_requires_approval', 'Requires Approval')}:
                      </span>{' '}
                      <span className="font-medium">
                        {policy.requires_approval ? t('common.yes', 'Yes') : t('common.no', 'No')}
                      </span>
                    </div>
                  </div>
                  <Button
                    size="sm"
                    variant="flat"
                    color="primary"
                    onPress={() => openPolicyEdit(policy)}
                  >
                    {t('common.edit', 'Edit')}
                  </Button>
                </AccordionItem>
              ))}
            </Accordion>
          )}
        </CardBody>
      </Card>

      {/* Review Modal */}
      <Modal isOpen={reviewModal} onClose={() => setReviewModal(false)} size="lg">
        <ModalContent>
          <ModalHeader>
            {t('volunteering.review_expense', 'Review Expense')}
          </ModalHeader>
          <ModalBody>
            {reviewExpense && (
              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-3 text-sm">
                  <div>
                    <span className="text-default-400">{t('volunteering.col_volunteer', 'Volunteer')}:</span>
                    <p className="font-medium">{reviewExpense.volunteer_name}</p>
                  </div>
                  <div>
                    <span className="text-default-400">{t('volunteering.col_organization', 'Organization')}:</span>
                    <p className="font-medium">{reviewExpense.organization_name}</p>
                  </div>
                  <div>
                    <span className="text-default-400">{t('volunteering.col_amount', 'Amount')}:</span>
                    <p className="font-semibold">{reviewExpense.currency || '\u20AC'}{reviewExpense.amount.toFixed(2)}</p>
                  </div>
                  <div>
                    <span className="text-default-400">{t('volunteering.col_type', 'Type')}:</span>
                    <p className="font-medium">{TYPE_LABELS[reviewExpense.type] || reviewExpense.type}</p>
                  </div>
                </div>

                {reviewExpense.description && (
                  <div>
                    <span className="text-default-400 text-sm">{t('volunteering.description', 'Description')}:</span>
                    <p className="text-sm">{reviewExpense.description}</p>
                  </div>
                )}

                <Select
                  label={t('volunteering.action', 'Action')}
                  selectedKeys={[reviewAction]}
                  onSelectionChange={(keys) => {
                    const val = Array.from(keys)[0] as string;
                    setReviewAction(val as 'approved' | 'rejected' | 'paid');
                  }}
                >
                  <SelectItem key="approved">{t('volunteering.approve', 'Approve')}</SelectItem>
                  <SelectItem key="rejected">{t('volunteering.reject', 'Reject')}</SelectItem>
                  <SelectItem key="paid">{t('volunteering.mark_as_paid', 'Mark as Paid')}</SelectItem>
                </Select>

                <Textarea
                  label={t('volunteering.review_notes', 'Review Notes')}
                  placeholder={t('volunteering.review_notes_placeholder', 'Optional notes about this decision...')}
                  value={reviewNotes}
                  onValueChange={setReviewNotes}
                />

                {reviewAction === 'paid' && (
                  <Input
                    label={t('volunteering.payment_reference', 'Payment Reference')}
                    placeholder={t('volunteering.payment_reference_placeholder', 'e.g. bank transfer ref, cheque number...')}
                    value={paymentReference}
                    onValueChange={setPaymentReference}
                  />
                )}
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setReviewModal(false)}>
              {t('common.cancel', 'Cancel')}
            </Button>
            <Button
              color={reviewAction === 'rejected' ? 'danger' : 'primary'}
              onPress={handleReview}
              isLoading={actionLoading}
              startContent={
                reviewAction === 'rejected' ? <XCircle size={16} /> :
                reviewAction === 'paid' ? <CreditCard size={16} /> :
                <CheckCircle size={16} />
              }
            >
              {reviewAction === 'approved' && t('volunteering.approve', 'Approve')}
              {reviewAction === 'rejected' && t('volunteering.reject', 'Reject')}
              {reviewAction === 'paid' && t('volunteering.mark_as_paid', 'Mark as Paid')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Receipt Preview Modal */}
      <Modal isOpen={receiptModal} onClose={() => setReceiptModal(false)} size="lg">
        <ModalContent>
          <ModalHeader>
            {t('volunteering.receipt_preview', 'Receipt Preview')}
          </ModalHeader>
          <ModalBody>
            {receiptUrl && !receiptIsPdf && (
              <div className="flex justify-center">
                <img
                  src={receiptUrl}
                  alt={t('volunteering.receipt_image', 'Receipt')}
                  className="max-h-[500px] object-contain rounded-lg"
                  onError={(e) => {
                    (e.target as HTMLImageElement).src = 'data:image/svg+xml,' + encodeURIComponent(
                      '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect fill="#f4f4f5" width="200" height="200"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#a1a1aa" font-size="14">Image unavailable</text></svg>'
                    );
                  }}
                />
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setReceiptModal(false)}>
              {t('common.close', 'Close')}
            </Button>
            <Button
              color="primary"
              variant="flat"
              startContent={<ExternalLink size={14} />}
              onPress={() => window.open(receiptUrl, '_blank')}
            >
              {t('volunteering.open_full_size', 'Open Full Size')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Policy Edit Modal */}
      <Modal isOpen={policyModal} onClose={() => setPolicyModal(false)} size="md">
        <ModalContent>
          <ModalHeader>
            {t('volunteering.edit_policy', 'Edit Expense Policy')} — {editingPolicy ? (TYPE_LABELS[editingPolicy.type] || editingPolicy.type) : ''}
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Input
                label={t('volunteering.policy_max_amount', 'Max Amount')}
                type="number"
                value={policyForm.max_amount}
                onValueChange={(v) => setPolicyForm({ ...policyForm, max_amount: v })}
              />
              <Input
                label={t('volunteering.policy_max_monthly', 'Max Monthly')}
                type="number"
                value={policyForm.max_monthly}
                onValueChange={(v) => setPolicyForm({ ...policyForm, max_monthly: v })}
              />
              <Input
                label={t('volunteering.policy_receipt_threshold', 'Receipt Required Above')}
                type="number"
                value={policyForm.requires_receipt_above}
                onValueChange={(v) => setPolicyForm({ ...policyForm, requires_receipt_above: v })}
              />
              <Select
                label={t('volunteering.policy_requires_approval', 'Requires Approval')}
                selectedKeys={[policyForm.requires_approval ? 'yes' : 'no']}
                onSelectionChange={(keys) => {
                  const val = Array.from(keys)[0] as string;
                  setPolicyForm({ ...policyForm, requires_approval: val === 'yes' });
                }}
              >
                <SelectItem key="yes">{t('common.yes', 'Yes')}</SelectItem>
                <SelectItem key="no">{t('common.no', 'No')}</SelectItem>
              </Select>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setPolicyModal(false)}>
              {t('common.cancel', 'Cancel')}
            </Button>
            <Button color="primary" onPress={handlePolicySave} isLoading={actionLoading}>
              {t('common.save', 'Save')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default VolunteerExpenses;
