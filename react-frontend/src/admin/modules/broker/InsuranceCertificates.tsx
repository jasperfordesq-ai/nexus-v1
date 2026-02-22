// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Insurance Certificates Management
 * Manage insurance certificates for compliance.
 * Parity: PHP AdminInsuranceCertificateApiController
 */

import { useState, useCallback, useEffect } from 'react';
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
  Avatar,
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
  Trash2,
  Eye,
  FileCheck,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminInsurance } from '../../api/adminApi';
import { DataTable, StatCard, PageHeader, ConfirmModal, EmptyState, type Column } from '../../components';
import type { InsuranceCertificate, InsuranceStats } from '../../api/types';

const INSURANCE_TYPE_LABELS: Record<string, string> = {
  public_liability: 'Public Liability',
  professional_indemnity: 'Professional Indemnity',
  employers_liability: "Employer's Liability",
  product_liability: 'Product Liability',
  personal_accident: 'Personal Accident',
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

export function InsuranceCertificates() {
  usePageTitle('Admin - Insurance Certificates');
  const { tenantPath } = useTenant();
  const toast = useToast();

  // List state
  const [items, setItems] = useState<InsuranceCertificate[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState('all');
  const [searchQuery, setSearchQuery] = useState('');

  // Stats
  const [stats, setStats] = useState<InsuranceStats | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);

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

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = { page };
      if (statusFilter === 'expiring_soon') {
        params.expiring_soon = true;
      } else if (statusFilter !== 'all') {
        params.status = statusFilter;
      }
      if (searchQuery.trim()) {
        params.search = searchQuery.trim();
      }

      const res = await adminInsurance.list(params as Parameters<typeof adminInsurance.list>[0]);
      if (res.success && Array.isArray(res.data)) {
        setItems(res.data as InsuranceCertificate[]);
        const meta = res.meta as Record<string, unknown> | undefined;
        setTotal(Number(meta?.total ?? meta?.total_items ?? res.data.length));
      }
    } catch {
      toast.error('Failed to load insurance certificates');
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter, searchQuery]);

  useEffect(() => { loadStats(); }, [loadStats]);
  useEffect(() => { loadItems(); }, [loadItems]);

  const handleVerify = async (item: InsuranceCertificate) => {
    setVerifyingId(item.id);
    try {
      const res = await adminInsurance.verify(item.id);
      if (res?.success) {
        toast.success(`Insurance certificate for ${item.first_name} ${item.last_name} verified`);
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || 'Failed to verify certificate');
      }
    } catch {
      toast.error('Failed to verify certificate');
    } finally {
      setVerifyingId(null);
    }
  };

  const handleReject = async () => {
    if (!rejectModal || !rejectReason.trim()) {
      toast.error('A reason is required to reject an insurance certificate');
      return;
    }
    setRejectLoading(true);
    try {
      const res = await adminInsurance.reject(rejectModal.id, rejectReason);
      if (res?.success) {
        toast.success('Insurance certificate rejected');
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || 'Failed to reject certificate');
      }
    } catch {
      toast.error('Failed to reject certificate');
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
        toast.success('Insurance certificate deleted');
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || 'Failed to delete certificate');
      }
    } catch {
      toast.error('Failed to delete certificate');
    } finally {
      setDeleteLoading(false);
      setDeleteItem(null);
    }
  };

  const handleCreate = async () => {
    if (!createForm.user_id) {
      toast.error('User ID is required');
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
        toast.success('Insurance certificate created');
        setCreateOpen(false);
        resetCreateForm();
        loadItems();
        loadStats();
      } else {
        toast.error(res?.error || 'Failed to create certificate');
      }
    } catch {
      toast.error('Failed to create certificate');
    } finally {
      setCreateLoading(false);
    }
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
  };

  const columns: Column<InsuranceCertificate>[] = [
    {
      key: 'member',
      label: 'Member',
      sortable: true,
      render: (item) => (
        <div className="flex items-center gap-2">
          <Avatar
            src={item.avatar_url || undefined}
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
      label: 'Type',
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat" color="primary">
          {INSURANCE_TYPE_LABELS[item.insurance_type] || item.insurance_type}
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
      key: 'provider_name',
      label: 'Provider',
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.provider_name || '\u2014'}
        </span>
      ),
    },
    {
      key: 'policy_number',
      label: 'Policy #',
      render: (item) => (
        <span className="text-sm text-default-600 font-mono">
          {item.policy_number || '\u2014'}
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
          {(item.status === 'pending' || item.status === 'submitted') && (
            <>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="success"
                isLoading={verifyingId === item.id}
                onPress={() => handleVerify(item)}
                aria-label="Verify certificate"
              >
                <Check size={14} />
              </Button>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="danger"
                onPress={() => { setRejectModal(item); setRejectReason(''); }}
                aria-label="Reject certificate"
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
            aria-label="Delete certificate"
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
        title="Insurance Certificates"
        description="Manage insurance certificates for compliance verification"
        actions={
          <div className="flex gap-2">
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              size="sm"
              onPress={() => { resetCreateForm(); setCreateOpen(true); }}
            >
              Add Certificate
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
          label="Total Certificates"
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
          placeholder="Search by name, email, provider, or policy number..."
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
            title="No insurance certificates found"
            description={statusFilter !== 'all' ? 'Try changing the filter or search query.' : 'Add a certificate to get started.'}
          />
        }
      />

      {/* Create Modal */}
      <Modal
        isOpen={createOpen}
        onClose={() => setCreateOpen(false)}
        size="lg"
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Plus size={20} className="text-primary" />
            Add Insurance Certificate
          </ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label="User ID"
              placeholder="Enter user ID"
              value={createForm.user_id}
              onValueChange={(val) => setCreateForm(prev => ({ ...prev, user_id: val }))}
              variant="bordered"
              type="number"
              isRequired
            />
            <Select
              label="Insurance Type"
              selectedKeys={[createForm.insurance_type]}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as InsuranceCertificate['insurance_type'];
                if (val) setCreateForm(prev => ({ ...prev, insurance_type: val }));
              }}
              variant="bordered"
              isRequired
            >
              {Object.entries(INSURANCE_TYPE_LABELS).map(([key, label]) => (
                <SelectItem key={key}>{label}</SelectItem>
              ))}
            </Select>
            <Input
              label="Provider Name"
              placeholder="e.g., Aviva, Zurich"
              value={createForm.provider_name}
              onValueChange={(val) => setCreateForm(prev => ({ ...prev, provider_name: val }))}
              variant="bordered"
            />
            <Input
              label="Policy Number"
              placeholder="e.g., PL-12345678"
              value={createForm.policy_number}
              onValueChange={(val) => setCreateForm(prev => ({ ...prev, policy_number: val }))}
              variant="bordered"
            />
            <Input
              label="Coverage Amount"
              placeholder="e.g., 1000000"
              value={createForm.coverage_amount}
              onValueChange={(val) => setCreateForm(prev => ({ ...prev, coverage_amount: val }))}
              variant="bordered"
              type="number"
              startContent={<span className="text-default-400 text-sm">&pound;</span>}
            />
            <div className="grid grid-cols-2 gap-4">
              <Input
                label="Start Date"
                type="date"
                value={createForm.start_date}
                onValueChange={(val) => setCreateForm(prev => ({ ...prev, start_date: val }))}
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
            <Textarea
              label="Notes"
              placeholder="Additional notes about this certificate..."
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
              Create Certificate
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

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
              Reject Insurance Certificate
            </ModalHeader>
            <ModalBody>
              <p className="text-default-600 mb-3">
                Reject the insurance certificate for{' '}
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

      {/* View Detail Modal */}
      {viewItem && (
        <Modal
          isOpen={!!viewItem}
          onClose={() => setViewItem(null)}
          size="lg"
        >
          <ModalContent>
            <ModalHeader className="flex items-center gap-2">
              <FileCheck size={20} className="text-primary" />
              Insurance Certificate Details
            </ModalHeader>
            <ModalBody>
              <div className="flex items-center gap-3 mb-4">
                <Avatar
                  src={viewItem.avatar_url || undefined}
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
                  <p className="font-medium">{INSURANCE_TYPE_LABELS[viewItem.insurance_type] || viewItem.insurance_type}</p>
                </div>
                <div>
                  <p className="text-default-400">Status</p>
                  <Chip size="sm" variant="flat" color={STATUS_COLOR_MAP[viewItem.status] || 'default'} className="capitalize">
                    {viewItem.status}
                  </Chip>
                </div>
                <div>
                  <p className="text-default-400">Provider</p>
                  <p className="font-medium">{viewItem.provider_name || '\u2014'}</p>
                </div>
                <div>
                  <p className="text-default-400">Policy Number</p>
                  <p className="font-medium font-mono">{viewItem.policy_number || '\u2014'}</p>
                </div>
                <div>
                  <p className="text-default-400">Coverage Amount</p>
                  <p className="font-medium">{viewItem.coverage_amount ? `\u00A3${Number(viewItem.coverage_amount).toLocaleString()}` : '\u2014'}</p>
                </div>
                <div>
                  <p className="text-default-400">Start Date</p>
                  <p className="font-medium">{viewItem.start_date ? new Date(viewItem.start_date).toLocaleDateString() : '\u2014'}</p>
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
              {viewItem.notes && (
                <div className="mt-4">
                  <p className="text-default-400 text-sm mb-1">Notes</p>
                  <p className="text-sm bg-default-100 p-3 rounded-lg">{viewItem.notes}</p>
                </div>
              )}
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
        title="Delete Insurance Certificate"
        message={deleteItem
          ? `Are you sure you want to delete the insurance certificate for ${deleteItem.first_name} ${deleteItem.last_name}? This action cannot be undone.`
          : ''}
        confirmLabel="Delete"
        confirmColor="danger"
        isLoading={deleteLoading}
      />
    </div>
  );
}

export default InsuranceCertificates;
