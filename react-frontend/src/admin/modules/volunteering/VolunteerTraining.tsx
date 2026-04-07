// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Volunteer Training
 * Admin page for verifying volunteer training certifications and compliance.
 */

import { useState, useCallback, useEffect, useMemo } from 'react';
import {
  Button,
  Chip,
  Checkbox,
  Select,
  SelectItem,
  Card,
  CardBody,
} from '@heroui/react';
import {
  GraduationCap,
  RefreshCw,
  CheckCircle,
  XCircle,
  Clock,
  AlertTriangle,
  ShieldCheck,
  ListChecks,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { DataTable, PageHeader, StatCard, EmptyState, type Column } from '../../components';
import { useTranslation } from 'react-i18next';

// ── Types ──────────────────────────────────────────────────────────────────────

interface TrainingRecord {
  id: number;
  volunteer_name: string;
  user_id: number;
  training_type: 'children_first' | 'vulnerable_adults' | 'first_aid' | 'manual_handling' | 'other';
  completed_date: string;
  expires_date: string | null;
  certificate_ref: string;
  status: 'pending' | 'verified' | 'expired' | 'rejected';
  description?: string;
}

interface TrainingStats {
  total_submissions: number;
  pending_verification: number;
  verified: number;
  expired: number;
}

// ── Helpers ────────────────────────────────────────────────────────────────────

const STATUS_COLORS: Record<string, 'warning' | 'success' | 'danger' | 'default'> = {
  pending: 'warning',
  verified: 'success',
  expired: 'danger',
  rejected: 'default',
};

const TYPE_LABELS: Record<string, string> = {
  children_first: 'Children First',
  vulnerable_adults: 'Vulnerable Adults',
  first_aid: 'First Aid',
  manual_handling: 'Manual Handling',
  other: 'Other',
};

function parsePayload<T>(raw: unknown): T {
  if (raw && typeof raw === 'object' && 'data' in raw) {
    return (raw as { data: T }).data;
  }
  return raw as T;
}

// ── Component ──────────────────────────────────────────────────────────────────

export function VolunteerTraining() {
  const { t } = useTranslation('admin');
  usePageTitle(t('volunteering.training_page_title', 'Volunteer Training'));
  const toast = useToast();

  const [records, setRecords] = useState<TrainingRecord[]>([]);
  const [stats, setStats] = useState<TrainingStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionId, setActionId] = useState<number | null>(null);

  // Bulk verify
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [bulkVerifying, setBulkVerifying] = useState(false);

  // Filters
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [typeFilter, setTypeFilter] = useState<string>('all');

  // ── Data loading ───────────────────────────────────────────────────────────

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getTraining();
      if (res.success && res.data) {
        const payload = parsePayload<{ records?: TrainingRecord[]; stats?: TrainingStats }>(res.data);
        setRecords(payload.records || []);
        setStats(payload.stats || null);
      }
    } catch {
      toast.error(t('volunteering.failed_to_load_training', 'Failed to load training records'));
      setRecords([]);
      setStats(null);
    }
    setLoading(false);
  }, [toast, t]);

  useEffect(() => { loadData(); }, [loadData]);

  // ── Actions ────────────────────────────────────────────────────────────────

  const handleVerify = async (id: number) => {
    setActionId(id);
    try {
      const res = await adminVolunteering.verifyTraining(id);
      if (res.success) {
        toast.success(t('volunteering.training_verified', 'Training record verified'));
        loadData();
      } else {
        toast.error(t('volunteering.failed_to_verify_training', 'Failed to verify training'));
      }
    } catch {
      toast.error(t('volunteering.failed_to_verify_training', 'Failed to verify training'));
    }
    setActionId(null);
  };

  const handleReject = async (id: number) => {
    setActionId(id);
    try {
      const res = await adminVolunteering.rejectTraining(id);
      if (res.success) {
        toast.success(t('volunteering.training_rejected', 'Training record rejected'));
        loadData();
      } else {
        toast.error(t('volunteering.failed_to_reject_training', 'Failed to reject training'));
      }
    } catch {
      toast.error(t('volunteering.failed_to_reject_training', 'Failed to reject training'));
    }
    setActionId(null);
  };

  // ── Bulk verify ────────────────────────────────────────────────────────────

  const handleBulkVerify = async () => {
    if (selectedIds.size === 0) return;
    setBulkVerifying(true);
    let successCount = 0;
    let failCount = 0;
    for (const id of selectedIds) {
      try {
        const res = await adminVolunteering.verifyTraining(id);
        if (res.success) successCount++;
        else failCount++;
      } catch {
        failCount++;
      }
    }
    if (successCount > 0) {
      toast.success(
        t('volunteering.bulk_verify_success', '{{count}} record(s) verified successfully', { count: successCount })
      );
    }
    if (failCount > 0) {
      toast.error(
        t('volunteering.bulk_verify_fail', '{{count}} record(s) failed to verify', { count: failCount })
      );
    }
    setSelectedIds(new Set());
    setBulkVerifying(false);
    loadData();
  };

  const toggleSelection = (id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  // ── Expiry alerts (within 30 days) ────────────────────────────────────────

  const expiringRecords = useMemo(() => {
    const now = new Date();
    const thirtyDaysFromNow = new Date();
    thirtyDaysFromNow.setDate(now.getDate() + 30);
    return records.filter((r) => {
      if (!r.expires_date) return false;
      if (r.status === 'expired' || r.status === 'rejected') return false;
      const expiryDate = new Date(r.expires_date);
      return expiryDate > now && expiryDate <= thirtyDaysFromNow;
    });
  }, [records]);

  // ── Filtered data ──────────────────────────────────────────────────────────

  const filteredRecords = records.filter((r) => {
    if (statusFilter !== 'all' && r.status !== statusFilter) return false;
    if (typeFilter !== 'all' && r.training_type !== typeFilter) return false;
    return true;
  });

  // ── Pending records for bulk select ────────────────────────────────────────

  const pendingRecords = useMemo(() => {
    return filteredRecords.filter((r) => r.status === 'pending');
  }, [filteredRecords]);

  const allPendingSelected = pendingRecords.length > 0 && pendingRecords.every((r) => selectedIds.has(r.id));

  const toggleSelectAll = () => {
    if (allPendingSelected) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(pendingRecords.map((r) => r.id)));
    }
  };

  // ── Columns ────────────────────────────────────────────────────────────────

  const columns: Column<TrainingRecord>[] = [
    {
      key: 'select',
      label: pendingRecords.length > 0 ? (
        <Checkbox
          isSelected={allPendingSelected}
          onValueChange={toggleSelectAll}
          size="sm"
          aria-label={t('volunteering.select_all', 'Select all pending')}
        />
      ) : '',
      render: (item) => {
        if (item.status !== 'pending') return null;
        return (
          <Checkbox
            isSelected={selectedIds.has(item.id)}
            onValueChange={() => toggleSelection(item.id)}
            size="sm"
            aria-label={t('volunteering.select_record', 'Select {{name}}', { name: item.volunteer_name })}
          />
        );
      },
    },
    {
      key: 'volunteer_name',
      label: t('volunteering.col_volunteer', 'Volunteer'),
      sortable: true,
    },
    {
      key: 'training_type',
      label: t('volunteering.col_training_type', 'Training Type'),
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat">
          {TYPE_LABELS[item.training_type] || item.training_type}
        </Chip>
      ),
    },
    {
      key: 'completed_date',
      label: t('volunteering.col_completed', 'Completed'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.completed_date ? new Date(item.completed_date).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'expires_date',
      label: t('volunteering.col_expires', 'Expires'),
      sortable: true,
      render: (item) => {
        if (!item.expires_date) return <span className="text-sm text-default-400">--</span>;
        const isExpired = new Date(item.expires_date) < new Date();
        return (
          <span className={`text-sm ${isExpired ? 'text-danger font-medium' : 'text-default-500'}`}>
            {new Date(item.expires_date).toLocaleDateString()}
          </span>
        );
      },
    },
    {
      key: 'certificate_ref',
      label: t('volunteering.col_certificate_ref', 'Certificate Ref'),
      render: (item) => (
        <span className="text-sm font-mono text-default-500">
          {item.certificate_ref || '--'}
        </span>
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
      key: 'actions',
      label: t('volunteering.col_actions', 'Actions'),
      render: (item) => (
        <div className="flex gap-1">
          {(item.status === 'pending') && (
            <>
              <Button
                size="sm"
                variant="flat"
                color="success"
                startContent={<CheckCircle size={14} />}
                onPress={() => handleVerify(item.id)}
                isLoading={actionId === item.id}
                isDisabled={actionId !== null && actionId !== item.id}
              >
                {t('volunteering.verify', 'Verify')}
              </Button>
              <Button
                size="sm"
                variant="flat"
                color="danger"
                startContent={<XCircle size={14} />}
                onPress={() => handleReject(item.id)}
                isLoading={actionId === item.id}
                isDisabled={actionId !== null && actionId !== item.id}
              >
                {t('volunteering.reject', 'Reject')}
              </Button>
            </>
          )}
          {item.status === 'verified' && (
            <Chip size="sm" color="success" variant="flat" startContent={<ShieldCheck size={12} />}>
              {t('volunteering.verified', 'Verified')}
            </Chip>
          )}
          {item.status === 'expired' && (
            <Chip size="sm" color="danger" variant="flat" startContent={<AlertTriangle size={12} />}>
              {t('volunteering.expired', 'Expired')}
            </Chip>
          )}
          {item.status === 'rejected' && (
            <Chip size="sm" color="default" variant="flat">
              {t('volunteering.rejected', 'Rejected')}
            </Chip>
          )}
        </div>
      ),
    },
  ];

  // ── Render ─────────────────────────────────────────────────────────────────

  return (
    <div>
      <PageHeader
        title={t('volunteering.training_title', 'Training Verification')}
        description={t('volunteering.training_desc', 'Review and verify volunteer training certifications')}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
          >
            {t('common.refresh', 'Refresh')}
          </Button>
        }
      />

      {/* Stats Row */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label={t('volunteering.stat_total_submissions', 'Total Submissions')}
          value={stats?.total_submissions ?? 0}
          icon={GraduationCap}
          color="default"
          loading={loading}
        />
        <StatCard
          label={t('volunteering.stat_pending_verification', 'Pending Verification')}
          value={stats?.pending_verification ?? 0}
          icon={Clock}
          color="warning"
          loading={loading}
        />
        <StatCard
          label={t('volunteering.stat_verified', 'Verified')}
          value={stats?.verified ?? 0}
          icon={CheckCircle}
          color="success"
          loading={loading}
        />
        <StatCard
          label={t('volunteering.stat_expired', 'Expired')}
          value={stats?.expired ?? 0}
          icon={AlertTriangle}
          color="danger"
          loading={loading}
        />
      </div>

      {/* Expiry alerts */}
      {expiringRecords.length > 0 && (
        <Card className="mb-4 border-warning/50 bg-warning-50/50">
          <CardBody className="py-3 px-4">
            <div className="flex items-start gap-2">
              <AlertTriangle size={18} className="text-warning mt-0.5 shrink-0" />
              <div>
                <p className="font-semibold text-sm text-warning-700">
                  {t('volunteering.expiry_alert_title', '{{count}} training record(s) expiring within 30 days', { count: expiringRecords.length })}
                </p>
                <ul className="mt-1.5 space-y-0.5">
                  {expiringRecords.map((r) => (
                    <li key={r.id} className="text-xs text-warning-600">
                      <span className="font-medium">{r.volunteer_name}</span>
                      {' — '}
                      {TYPE_LABELS[r.training_type] || r.training_type}
                      {' — '}
                      {t('volunteering.expires_on', 'expires {{date}}', {
                        date: new Date(r.expires_date!).toLocaleDateString(),
                      })}
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Bulk verify bar */}
      {selectedIds.size > 0 && (
        <div className="flex items-center gap-3 mb-4 p-3 rounded-lg bg-primary-50 border border-primary/20">
          <ListChecks size={18} className="text-primary" />
          <span className="text-sm font-medium text-primary-700">
            {t('volunteering.bulk_selected', '{{count}} pending record(s) selected', { count: selectedIds.size })}
          </span>
          <Button
            size="sm"
            color="success"
            variant="flat"
            startContent={<CheckCircle size={14} />}
            onPress={handleBulkVerify}
            isLoading={bulkVerifying}
          >
            {t('volunteering.bulk_verify', 'Bulk Verify')}
          </Button>
          <Button
            size="sm"
            variant="light"
            onPress={() => setSelectedIds(new Set())}
          >
            {t('common.clear_selection', 'Clear')}
          </Button>
        </div>
      )}

      {/* Filters */}
      <div className="flex flex-wrap gap-3 mb-4">
        <Select
          label={t('volunteering.filter_status', 'Status')}
          size="sm"
          className="w-48"
          selectedKeys={[statusFilter]}
          onSelectionChange={(keys) => setStatusFilter(Array.from(keys)[0] as string)}
        >
          <SelectItem key="all">{t('common.all', 'All')}</SelectItem>
          <SelectItem key="pending">{t('volunteering.status_pending', 'Pending')}</SelectItem>
          <SelectItem key="verified">{t('volunteering.status_verified', 'Verified')}</SelectItem>
          <SelectItem key="expired">{t('volunteering.status_expired', 'Expired')}</SelectItem>
          <SelectItem key="rejected">{t('volunteering.status_rejected', 'Rejected')}</SelectItem>
        </Select>
        <Select
          label={t('volunteering.filter_type', 'Training Type')}
          size="sm"
          className="w-48"
          selectedKeys={[typeFilter]}
          onSelectionChange={(keys) => setTypeFilter(Array.from(keys)[0] as string)}
        >
          <SelectItem key="all">{t('common.all', 'All')}</SelectItem>
          <SelectItem key="children_first">{t('volunteering.type_children_first', 'Children First')}</SelectItem>
          <SelectItem key="vulnerable_adults">{t('volunteering.type_vulnerable_adults', 'Vulnerable Adults')}</SelectItem>
          <SelectItem key="first_aid">{t('volunteering.type_first_aid', 'First Aid')}</SelectItem>
          <SelectItem key="manual_handling">{t('volunteering.type_manual_handling', 'Manual Handling')}</SelectItem>
          <SelectItem key="other">{t('volunteering.type_other', 'Other')}</SelectItem>
        </Select>
      </div>

      {/* Training Table */}
      {!loading && filteredRecords.length === 0 ? (
        <EmptyState
          icon={GraduationCap}
          title={t('volunteering.no_training', 'No training records')}
          description={t('volunteering.no_training_desc', 'There are no training submissions matching the current filters.')}
        />
      ) : (
        <DataTable columns={columns} data={filteredRecords} isLoading={loading} onRefresh={loadData} />
      )}
    </div>
  );
}

export default VolunteerTraining;
