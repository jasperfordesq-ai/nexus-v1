// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Partnerships
 * Lists and manages federation partnerships with other communities.
 * Features: Counter-proposals, detail drawer, permissions matrix, audit timeline,
 * incoming requests tab, partnership statistics.
 */

import { useState, useCallback, useEffect, useMemo, useRef } from 'react';
import {
  Button, Dropdown, DropdownTrigger, DropdownMenu, DropdownItem,
  Tabs, Tab, Chip, Modal, ModalContent, ModalHeader, ModalBody, ModalFooter,
  Select, SelectItem, Switch, Textarea, Card, CardBody, CardHeader,
  Spinner, useDisclosure,
} from '@heroui/react';
import Handshake from 'lucide-react/icons/handshake';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import MoreVertical from 'lucide-react/icons/ellipsis-vertical';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import XCircle from 'lucide-react/icons/circle-x';
import Ban from 'lucide-react/icons/ban';
import Eye from 'lucide-react/icons/eye';
import MessageSquare from 'lucide-react/icons/message-square';
import ArrowLeftRight from 'lucide-react/icons/arrow-left-right';
import ShieldCheck from 'lucide-react/icons/shield-check';
import Clock from 'lucide-react/icons/clock';
import BarChart3 from 'lucide-react/icons/chart-column';
import Users from 'lucide-react/icons/users';
import Mail from 'lucide-react/icons/mail';
import ShoppingBag from 'lucide-react/icons/shopping-bag';
import Calendar from 'lucide-react/icons/calendar';
import UsersRound from 'lucide-react/icons/users-round';
import Inbox from 'lucide-react/icons/inbox';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminFederation } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, StatusBadge, ConfirmModal, type Column } from '../../components';
import { logError } from '@/lib/logger';

import { useTranslation } from 'react-i18next';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface Partnership {
  id: number;
  tenant_id: number;
  partner_tenant_id: number;
  partner_name: string;
  partner_slug: string;
  status: string;
  federation_level?: number;
  profiles_enabled?: number;
  messaging_enabled?: number;
  transactions_enabled?: number;
  listings_enabled?: number;
  events_enabled?: number;
  groups_enabled?: number;
  counter_proposed_at?: string;
  counter_proposed_by?: number;
  counter_proposal_message?: string;
  counter_proposed_level?: number;
  counter_proposed_permissions?: string;
  created_at: string;
  updated_at?: string;
  requested_at?: string;
  notes?: string;
}

interface PartnershipDetail extends Partnership {
  is_initiator: boolean;
  resolved_partner_tenant_id: number;
  resolved_partner_name: string;
  tenant_name?: string;
  partner_domain?: string;
  tenant_domain?: string;
  approved_at?: string;
}

interface AuditLogEntry {
  id: number;
  action: string;
  category: string;
  level: string;
  actor_user_id: number | null;
  first_name: string | null;
  last_name: string | null;
  source_tenant_id: number | null;
  target_tenant_id: number | null;
  details: string | null;
  created_at: string;
}

interface PartnershipStats {
  messages_exchanged: number;
  transactions_completed: number;
  connections_made: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

const LEVEL_LABEL_KEYS: Record<number, string> = {
  1: 'federation.level_discovery',
  2: 'federation.level_social',
  3: 'federation.level_economic',
  4: 'federation.level_integrated',
};

const LEVEL_DESCRIPTION_KEYS: Record<number, string> = {
  1: 'federation.level_desc_discovery',
  2: 'federation.level_desc_social',
  3: 'federation.level_desc_economic',
  4: 'federation.level_desc_integrated',
};

const PERMISSION_KEYS = ['profiles', 'messaging', 'transactions', 'listings', 'events', 'groups'] as const;

const PERMISSION_ICONS: Record<string, typeof Users> = {
  profiles: Users,
  messaging: Mail,
  transactions: ArrowLeftRight,
  listings: ShoppingBag,
  events: Calendar,
  groups: UsersRound,
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function Partnerships() {
  const { t } = useTranslation('admin');
  usePageTitle("Federation");
  const toast = useToast();

  const [items, setItems] = useState<Partnership[]>([]);
  const [loading, setLoading] = useState(true);
  const [terminateTarget, setTerminateTarget] = useState<Partnership | null>(null);
  const [approveTarget, setApproveTarget] = useState<Partnership | null>(null);
  const [rejectTarget, setRejectTarget] = useState<Partnership | null>(null);
  const [reactivateTarget, setReactivateTarget] = useState<Partnership | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [activeTab, setActiveTab] = useState<string>('all');

  // Detail modal
  const detailModal = useDisclosure();
  const [detailPartnership, setDetailPartnership] = useState<PartnershipDetail | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailTab, setDetailTab] = useState<string>('info');
  const [auditLog, setAuditLog] = useState<AuditLogEntry[]>([]);
  const [auditLoading, setAuditLoading] = useState(false);
  const [stats, setStats] = useState<PartnershipStats | null>(null);
  const [statsLoading, setStatsLoading] = useState(false);
  const [permissionsLoading, setPermissionsLoading] = useState(false);

  // Bulk selection
  const [selectedKeys, setSelectedKeys] = useState<Set<string>>(new Set());

  // Counter-proposal modal
  const counterModal = useDisclosure();
  const [counterTarget, setCounterTarget] = useState<Partnership | null>(null);
  const [counterLevel, setCounterLevel] = useState('1');
  const [counterPermissions, setCounterPermissions] = useState<Record<string, boolean>>({
    profiles: true, messaging: false, transactions: false, listings: false, events: false, groups: false,
  });
  const [counterMessage, setCounterMessage] = useState('');
  const [counterLoading, setCounterLoading] = useState(false);

  // AbortController for cancelling in-flight requests
  const abortControllerRef = useRef<AbortController | null>(null);

  // ─── Load data ───
  const loadData = useCallback(async () => {
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }
    const controller = new AbortController();
    abortControllerRef.current = controller;

    setLoading(true);
    try {
      const res = await adminFederation.getPartnerships();
      if (controller.signal.aborted) return;
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setItems((payload as { data: Partnership[] }).data || []);
        }
      }
    } catch {
      if (!controller.signal.aborted) {
        setItems([]);
      }
    }
    if (!controller.signal.aborted) {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
    return () => { abortControllerRef.current?.abort(); };
  }, [loadData]);

  // ─── Filtered items ───
  const incomingRequests = useMemo(() =>
    items.filter(p => p.status === 'pending'),
    [items]
  );

  const filteredItems = useMemo(() => {
    if (activeTab === 'incoming') return incomingRequests;
    if (activeTab === 'active') return items.filter(p => p.status === 'active');
    return items;
  }, [items, activeTab, incomingRequests]);

  // ─── Actions ───
  const confirmApprove = async () => {
    if (!approveTarget) return;
    setActionLoading(true);
    try {
      const res = await adminFederation.approvePartnership(approveTarget.id);
      if (res.success) {
        toast.success(`Partnership Approved`);
        loadData();
      } else {
        toast.error("Failed to approve partnership");
      }
    } catch {
      toast.error("Failed to approve partnership");
    } finally {
      setActionLoading(false);
      setApproveTarget(null);
    }
  };

  const confirmReject = async () => {
    if (!rejectTarget) return;
    setActionLoading(true);
    try {
      const res = await adminFederation.rejectPartnership(rejectTarget.id);
      if (res.success) {
        toast.success(`Partnership Rejected`);
        loadData();
      } else {
        toast.error("Failed to reject partnership");
      }
    } catch {
      toast.error("Failed to reject partnership");
    } finally {
      setActionLoading(false);
      setRejectTarget(null);
    }
  };

  const handleTerminate = async () => {
    if (!terminateTarget) return;
    setActionLoading(true);
    try {
      const res = await adminFederation.terminatePartnership(terminateTarget.id);
      if (res.success) {
        toast.success(`Partnership Terminated`);
        setTerminateTarget(null);
        loadData();
      } else {
        toast.error("Failed to terminate partnership");
      }
    } catch {
      toast.error("Failed to terminate partnership");
    } finally {
      setActionLoading(false);
    }
  };

  const handleReactivate = async () => {
    if (!reactivateTarget) return;
    setActionLoading(true);
    try {
      const res = await adminFederation.reactivatePartnership(reactivateTarget.id);
      if (res.success) {
        toast.success(`Partnership Reactivated`);
        setReactivateTarget(null);
        loadData();
      } else {
        toast.error("Failed to reactivate partnership");
      }
    } catch {
      toast.error("Failed to reactivate partnership");
    } finally {
      setActionLoading(false);
    }
  };

  // ─── Bulk actions ───
  const selectedPendingItems = useMemo(() => {
    if (selectedKeys.size === 0) return [];
    return filteredItems.filter(p => selectedKeys.has(String(p.id)) && p.status === 'pending');
  }, [selectedKeys, filteredItems]);

  const handleBulkApprove = async () => {
    if (selectedPendingItems.length === 0) return;
    setActionLoading(true);
    let successCount = 0;
    for (const item of selectedPendingItems) {
      try {
        const res = await adminFederation.approvePartnership(item.id);
        if (res.success) successCount++;
      } catch { /* continue */ }
    }
    toast.success(`Bulk Approved`);
    setSelectedKeys(new Set());
    loadData();
    setActionLoading(false);
  };

  const handleBulkReject = async () => {
    if (selectedPendingItems.length === 0) return;
    setActionLoading(true);
    let successCount = 0;
    for (const item of selectedPendingItems) {
      try {
        const res = await adminFederation.rejectPartnership(item.id);
        if (res.success) successCount++;
      } catch { /* continue */ }
    }
    toast.success(`Bulk Rejected`);
    setSelectedKeys(new Set());
    loadData();
    setActionLoading(false);
  };

  // ─── Counter-propose ───
  const openCounterProposal = (item: Partnership) => {
    setCounterTarget(item);
    setCounterLevel(String(item.federation_level || 1));
    setCounterPermissions({
      profiles: true, messaging: false, transactions: false,
      listings: false, events: false, groups: false,
    });
    setCounterMessage('');
    counterModal.onOpen();
  };

  const handleCounterPropose = async () => {
    if (!counterTarget) return;
    setCounterLoading(true);
    try {
      const res = await adminFederation.counterProposePartnership(counterTarget.id, {
        level: parseInt(counterLevel),
        permissions: counterPermissions,
        message: counterMessage || undefined,
      });
      if (res.success) {
        toast.success(`Counter proposal sent`);
        counterModal.onClose();
        loadData();
      } else {
        toast.error("Failed to counter propose");
      }
    } catch {
      toast.error("Failed to counter propose");
    } finally {
      setCounterLoading(false);
    }
  };

  // ─── Detail modal ───
  const openDetail = async (item: Partnership) => {
    setDetailPartnership(null);
    setDetailTab('info');
    setAuditLog([]);
    setStats(null);
    detailModal.onOpen();
    setDetailLoading(true);

    try {
      const res = await adminFederation.getPartnershipDetail(item.id);
      if (res.success && res.data) {
        setDetailPartnership(res.data as unknown as PartnershipDetail);
      }
    } catch (err) {
      logError('Partnerships.loadDetail', err);
      toast.error("Failed to load partnership detail");
    } finally {
      setDetailLoading(false);
    }
  };

  const loadAuditLog = async (partnershipId: number) => {
    setAuditLoading(true);
    try {
      const res = await adminFederation.getPartnershipAuditLog(partnershipId);
      if (res.success && res.data) {
        const payload = res.data;
        setAuditLog(Array.isArray(payload) ? (payload as unknown as AuditLogEntry[]) : []);
      }
    } catch (err) {
      logError('Partnerships.loadAuditLog', err);
    } finally {
      setAuditLoading(false);
    }
  };

  const loadStats = async (partnershipId: number) => {
    setStatsLoading(true);
    try {
      const res = await adminFederation.getPartnershipStats(partnershipId);
      if (res.success && res.data) {
        setStats(res.data as unknown as PartnershipStats);
      }
    } catch (err) {
      logError('Partnerships.loadStats', err);
    } finally {
      setStatsLoading(false);
    }
  };

  // Load audit/stats when detail tab changes
  useEffect(() => {
    if (!detailPartnership) return;
    if (detailTab === 'history' && auditLog.length === 0) {
      loadAuditLog(detailPartnership.id);
    }
    if (detailTab === 'stats' && !stats) {
      loadStats(detailPartnership.id);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- lazy load stats tab; loadStats excluded to avoid loop
  }, [detailTab, detailPartnership]);

  // ─── Update permissions ───
  const handlePermissionToggle = async (key: string, value: boolean) => {
    if (!detailPartnership) return;
    setPermissionsLoading(true);
    try {
      const res = await adminFederation.updatePartnershipPermissions(detailPartnership.id, { [key]: value });
      if (res.success) {
        setDetailPartnership(prev => prev ? { ...prev, [`${key}_enabled`]: value ? 1 : 0 } : null);
        toast.success("Permissions Updated");
      } else {
        toast.error("Failed to update permissions");
      }
    } catch {
      toast.error("Failed to update permissions");
    } finally {
      setPermissionsLoading(false);
    }
  };

  // ─── Columns ───
  const columns: Column<Partnership>[] = [
    { key: 'partner_name', label: "Partner Community", sortable: true },
    { key: 'partner_slug', label: "Slug" },
    {
      key: 'federation_level', label: "Level",
      render: (item) => (
        <Chip size="sm" variant="flat" color="primary">
          {t(LEVEL_LABEL_KEYS[item.federation_level || 1] || 'federation.level_discovery')}
        </Chip>
      ),
    },
    {
      key: 'status', label: "Status",
      render: (item) => <StatusBadge status={item.status} />,
    },
    {
      key: 'created_at', label: "Since", sortable: true,
      render: (item) => <span className="text-sm text-default-500">{item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}</span>,
    },
    {
      key: 'actions', label: "Actions",
      render: (item) => (
        <div className="flex items-center gap-1">
          <Button
            isIconOnly size="sm" variant="light"
            aria-label={"View Detail"}
            onPress={() => openDetail(item)}
          >
            <Eye size={16} />
          </Button>
          <Dropdown>
            <DropdownTrigger>
              <Button isIconOnly size="sm" variant="light" aria-label={"Actions"} isDisabled={actionLoading}>
                <MoreVertical size={16} />
              </Button>
            </DropdownTrigger>
            <DropdownMenu
              aria-label={"Partnership Actions"}
              onAction={(key) => {
                if (key === 'approve') setApproveTarget(item);
                else if (key === 'reject') setRejectTarget(item);
                else if (key === 'terminate') setTerminateTarget(item);
                else if (key === 'counter') openCounterProposal(item);
                else if (key === 'reactivate') setReactivateTarget(item);
              }}
              items={[
                ...(item.status === 'pending' ? [{ key: 'approve' }, { key: 'reject' }, { key: 'counter' }] : []),
                ...(item.status === 'active' ? [{ key: 'terminate' }] : []),
                ...(item.status === 'suspended' ? [{ key: 'reactivate' }] : []),
              ]}
            >
              {(action) => {
                if (action.key === 'approve') return (
                  <DropdownItem key="approve" startContent={<CheckCircle size={14} />} className="text-success">
                    {"Approve"}
                  </DropdownItem>
                );
                if (action.key === 'reject') return (
                  <DropdownItem key="reject" startContent={<XCircle size={14} />} className="text-danger" color="danger">
                    {"Reject"}
                  </DropdownItem>
                );
                if (action.key === 'counter') return (
                  <DropdownItem key="counter" startContent={<MessageSquare size={14} />} className="text-warning">
                    {"Counter Propose"}
                  </DropdownItem>
                );
                if (action.key === 'terminate') return (
                  <DropdownItem key="terminate" startContent={<Ban size={14} />} className="text-danger" color="danger">
                    {"Terminate"}
                  </DropdownItem>
                );
                return (
                  <DropdownItem key="reactivate" startContent={<CheckCircle size={14} />} className="text-success">
                    {"Reactivate"}
                  </DropdownItem>
                );
              }}
            </DropdownMenu>
          </Dropdown>
        </div>
      ),
    },
  ];

  // ─── Render ───
  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader title={"Partnerships"} description={"View and manage all active and pending federation partnerships"} />
        <EmptyState icon={Handshake} title={"No partnerships"} description={"No federation partnerships have been established yet"} />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <PageHeader
        title={"Partnerships"}
        description={"View and manage all active and pending federation partnerships"}
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>{"Refresh"}</Button>}
      />

      {/* Tabs: All / Incoming / Active */}
      <Tabs
        selectedKey={activeTab}
        onSelectionChange={(key) => setActiveTab(String(key))}
        variant="underlined"
        aria-label={"Partnership Tabs"}
      >
        <Tab
          key="all"
          title={
            <div className="flex items-center gap-2">
              <Handshake size={16} />
              <span>{"All"}</span>
              <Chip size="sm" variant="flat">{items.length}</Chip>
            </div>
          }
        />
        <Tab
          key="incoming"
          title={
            <div className="flex items-center gap-2">
              <Inbox size={16} />
              <span>{"Incoming Requests"}</span>
              {incomingRequests.length > 0 && (
                <Chip size="sm" variant="solid" color="warning">{incomingRequests.length}</Chip>
              )}
            </div>
          }
        />
        <Tab
          key="active"
          title={
            <div className="flex items-center gap-2">
              <CheckCircle size={16} />
              <span>{"Active"}</span>
              <Chip size="sm" variant="flat" color="success">
                {items.filter(p => p.status === 'active').length}
              </Chip>
            </div>
          }
        />
      </Tabs>

      {/* Bulk action bar */}
      {selectedPendingItems.length > 0 && (
        <div className="flex items-center gap-3 p-3 rounded-lg bg-primary-50 dark:bg-primary-950 border border-primary-200 dark:border-primary-800">
          <span className="text-sm font-medium">
            {`Bulk Selected`}
          </span>
          <Button
            size="sm"
            color="success"
            variant="flat"
            startContent={<CheckCircle size={14} />}
            onPress={handleBulkApprove}
            isLoading={actionLoading}
          >
            {"Bulk Approve"}
          </Button>
          <Button
            size="sm"
            color="danger"
            variant="flat"
            startContent={<XCircle size={14} />}
            onPress={handleBulkReject}
            isLoading={actionLoading}
          >
            {"Bulk Reject"}
          </Button>
        </div>
      )}

      <DataTable
        columns={columns}
        data={filteredItems}
        isLoading={loading}
        onRefresh={loadData}
        selectable
        onSelectionChange={setSelectedKeys}
      />

      {/* Approve confirmation */}
      {approveTarget && (
        <ConfirmModal
          isOpen={!!approveTarget}
          onClose={() => setApproveTarget(null)}
          onConfirm={confirmApprove}
          title={t('federation.approve_partnership', 'Approve Partnership')}
          message={`Are you sure you want to approve partnership?`}
          confirmLabel={t('federation.approve', 'Approve')}
          confirmColor="primary"
          isLoading={actionLoading}
        />
      )}

      {/* Reject confirmation */}
      {rejectTarget && (
        <ConfirmModal
          isOpen={!!rejectTarget}
          onClose={() => setRejectTarget(null)}
          onConfirm={confirmReject}
          title={t('federation.reject_partnership', 'Reject Partnership')}
          message={`Are you sure you want to reject partnership?`}
          confirmLabel={t('federation.reject', 'Reject')}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}

      {/* Terminate confirmation */}
      {terminateTarget && (
        <ConfirmModal
          isOpen={!!terminateTarget}
          onClose={() => setTerminateTarget(null)}
          onConfirm={handleTerminate}
          title={"Terminate Partnership"}
          message={`Are you sure you want to terminate this partnership? This cannot be undone.`}
          confirmLabel={"Terminate"}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}

      {/* Reactivate confirmation */}
      {reactivateTarget && (
        <ConfirmModal
          isOpen={!!reactivateTarget}
          onClose={() => setReactivateTarget(null)}
          onConfirm={handleReactivate}
          title={"Reactivate Partnership"}
          message={`Reactivate Partnership Confirm`}
          confirmLabel={"Reactivate"}
          confirmColor="primary"
          isLoading={actionLoading}
        />
      )}

      {/* Counter-Proposal Modal */}
      <Modal isOpen={counterModal.isOpen} onOpenChange={counterModal.onOpenChange} size="lg">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex items-center gap-2">
                <MessageSquare size={20} />
                {"Counter Propose"}
              </ModalHeader>
              <ModalBody className="gap-4">
                {counterTarget && (
                  <p className="text-sm text-default-500">
                    {`Send a counter-proposal to modify the terms of a partnership request`}
                  </p>
                )}

                <Select
                  label={"Federation Level"}
                  selectedKeys={[counterLevel]}
                  onSelectionChange={(keys) => {
                    const selected = Array.from(keys)[0];
                    if (selected) setCounterLevel(String(selected));
                  }}
                >
                  {[1, 2, 3, 4].map((level) => (
                    <SelectItem key={String(level)} textValue={t(LEVEL_LABEL_KEYS[level] || 'federation.level_discovery')}>
                      <div>
                        <p className="font-medium">{t(LEVEL_LABEL_KEYS[level] || 'federation.level_discovery')}</p>
                        <p className="text-xs text-default-400">{t(LEVEL_DESCRIPTION_KEYS[level] || 'federation.level_desc_discovery')}</p>
                      </div>
                    </SelectItem>
                  ))}
                </Select>

                <div className="space-y-3">
                  <p className="text-sm font-medium">{"Feature Permissions"}</p>
                  {PERMISSION_KEYS.map((key) => {
                    const Icon = PERMISSION_ICONS[key] ?? Users;
                    return (
                      <div key={key} className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <Icon size={16} className="text-default-400" />
                          <span className="text-sm">{t(`federation.permission_${key}`)}</span>
                        </div>
                        <Switch
                          size="sm"
                          isSelected={counterPermissions[key] ?? false}
                          onValueChange={(val) => setCounterPermissions(prev => ({ ...prev, [key]: val }))}
                        />
                      </div>
                    );
                  })}
                </div>

                <Textarea
                  label={"Message"}
                  placeholder={"Counter Message..."}
                  value={counterMessage}
                  onValueChange={setCounterMessage}
                  maxLength={1000}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>{"Cancel"}</Button>
                <Button
                  color="warning"
                  isLoading={counterLoading}
                  onPress={handleCounterPropose}
                >
                  {"Send Counter Proposal"}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Partnership Detail Modal */}
      <Modal isOpen={detailModal.isOpen} onOpenChange={detailModal.onOpenChange} size="2xl" scrollBehavior="inside">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex items-center gap-2">
                <Handshake size={20} />
                {detailPartnership
                  ? `Partnership`
                  : "Partnership Detail"
                }
              </ModalHeader>
              <ModalBody>
                {detailLoading ? (
                  <div className="flex h-40 items-center justify-center">
                    <Spinner size="lg" />
                  </div>
                ) : detailPartnership ? (
                  <div className="space-y-4">
                    <Tabs
                      selectedKey={detailTab}
                      onSelectionChange={(key) => setDetailTab(String(key))}
                      variant="underlined"
                      size="sm"
                    >
                      <Tab key="info" title={
                        <div className="flex items-center gap-1.5">
                          <ShieldCheck size={14} />
                          <span>{"Info"}</span>
                        </div>
                      } />
                      <Tab key="permissions" title={
                        <div className="flex items-center gap-1.5">
                          <ShieldCheck size={14} />
                          <span>{"Permissions"}</span>
                        </div>
                      } />
                      <Tab key="history" title={
                        <div className="flex items-center gap-1.5">
                          <Clock size={14} />
                          <span>{"History"}</span>
                        </div>
                      } />
                      <Tab key="stats" title={
                        <div className="flex items-center gap-1.5">
                          <BarChart3 size={14} />
                          <span>{"Statistics"}</span>
                        </div>
                      } />
                    </Tabs>

                    {/* Info Tab */}
                    {detailTab === 'info' && (
                      <Card shadow="none" className="border border-default-200">
                        <CardBody className="gap-3">
                          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div>
                              <p className="text-default-400">{"Partner"}</p>
                              <p className="font-medium">{detailPartnership.resolved_partner_name || detailPartnership.partner_name}</p>
                            </div>
                            <div>
                              <p className="text-default-400">{"Status"}</p>
                              <StatusBadge status={detailPartnership.status} />
                            </div>
                            <div>
                              <p className="text-default-400">{"Level"}</p>
                              <Chip size="sm" variant="flat" color="primary">
                                {t(LEVEL_LABEL_KEYS[detailPartnership.federation_level || 1] || 'federation.level_discovery')}
                              </Chip>
                            </div>
                            <div>
                              <p className="text-default-400">{"Direction"}</p>
                              <p className="font-medium">
                                {detailPartnership.is_initiator
                                  ? "Outgoing Request"
                                  : "Incoming Request"
                                }
                              </p>
                            </div>
                            <div>
                              <p className="text-default-400">{"Created"}</p>
                              <p>{detailPartnership.created_at ? new Date(detailPartnership.created_at).toLocaleDateString() : '--'}</p>
                            </div>
                            {detailPartnership.approved_at && (
                              <div>
                                <p className="text-default-400">{"Approved"}</p>
                                <p>{new Date(detailPartnership.approved_at).toLocaleDateString()}</p>
                              </div>
                            )}
                          </div>
                          {detailPartnership.notes && (
                            <div className="mt-2">
                              <p className="text-sm text-default-400">{"Notes"}</p>
                              <p className="text-sm mt-1 rounded-lg bg-default-100 p-3">{detailPartnership.notes}</p>
                            </div>
                          )}
                          {detailPartnership.counter_proposal_message && (
                            <div className="mt-2">
                              <p className="text-sm text-default-400">{"Counter Proposal"}</p>
                              <p className="text-sm mt-1 rounded-lg bg-warning-50 p-3">{detailPartnership.counter_proposal_message}</p>
                            </div>
                          )}
                        </CardBody>
                      </Card>
                    )}

                    {/* Permissions Tab */}
                    {detailTab === 'permissions' && (
                      <Card shadow="none" className="border border-default-200">
                        <CardHeader>
                          <p className="text-sm font-semibold">{"Feature Permissions"}</p>
                        </CardHeader>
                        <CardBody className="gap-4">
                          {PERMISSION_KEYS.map((key) => {
                            const Icon = PERMISSION_ICONS[key] ?? Users;
                            const isEnabled = detailPartnership[`${key}_enabled` as keyof PartnershipDetail] === 1;
                            const canEdit = detailPartnership.status === 'active';
                            return (
                              <div key={key} className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                  <Icon size={18} className={isEnabled ? 'text-success' : 'text-default-300'} />
                                  <div>
                                    <p className="text-sm font-medium">{t(`federation.permission_${key}`)}</p>
                                    <p className="text-xs text-default-400">
                                      {isEnabled ? "Enabled" : "Disabled"}
                                    </p>
                                  </div>
                                </div>
                                <Switch
                                  size="sm"
                                  isSelected={isEnabled}
                                  isDisabled={!canEdit || permissionsLoading}
                                  onValueChange={(val) => handlePermissionToggle(key, val)}
                                />
                              </div>
                            );
                          })}
                          {detailPartnership.status !== 'active' && (
                            <p className="text-xs text-default-400 italic">
                              {"Permissions are editable when the partnership is active"}
                            </p>
                          )}
                        </CardBody>
                      </Card>
                    )}

                    {/* History/Audit Tab */}
                    {detailTab === 'history' && (
                      <Card shadow="none" className="border border-default-200">
                        <CardHeader>
                          <p className="text-sm font-semibold">{"Timeline"}</p>
                        </CardHeader>
                        <CardBody>
                          {auditLoading ? (
                            <div className="flex h-24 items-center justify-center">
                              <Spinner size="sm" />
                            </div>
                          ) : auditLog.length === 0 ? (
                            <p className="text-sm text-default-400 text-center py-4">
                              {"No audit log entries"}
                            </p>
                          ) : (
                            <div className="space-y-3">
                              {auditLog.map((entry) => (
                                <div key={entry.id} className="flex items-start gap-3 border-b border-default-100 pb-3 last:border-0">
                                  <div className="mt-1 h-2 w-2 rounded-full bg-primary shrink-0" />
                                  <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium">
                                      {entry.action.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                                    </p>
                                    <p className="text-xs text-default-400">
                                      {entry.first_name && entry.last_name
                                        ? `${entry.first_name} ${entry.last_name}`
                                        : "System"
                                      }
                                      {' — '}
                                      {new Date(entry.created_at).toLocaleString()}
                                    </p>
                                    {entry.details && (() => {
                                      try {
                                        const parsed = JSON.parse(entry.details);
                                        if (parsed.reason) return <p className="text-xs text-default-500 mt-1">{parsed.reason}</p>;
                                        if (parsed.message) return <p className="text-xs text-default-500 mt-1">{parsed.message}</p>;
                                      } catch { /* not JSON */ }
                                      return null;
                                    })()}
                                  </div>
                                  <Chip size="sm" variant="flat" color={
                                    entry.level === 'warning' ? 'warning'
                                      : entry.level === 'critical' ? 'danger'
                                        : 'default'
                                  }>
                                    {t(`federation.audit_level_${entry.level}`)}
                                  </Chip>
                                </div>
                              ))}
                            </div>
                          )}
                        </CardBody>
                      </Card>
                    )}

                    {/* Statistics Tab */}
                    {detailTab === 'stats' && (
                      <Card shadow="none" className="border border-default-200">
                        <CardHeader>
                          <p className="text-sm font-semibold">{"Partnership Statistics"}</p>
                        </CardHeader>
                        <CardBody>
                          {statsLoading ? (
                            <div className="flex h-24 items-center justify-center">
                              <Spinner size="sm" />
                            </div>
                          ) : stats ? (
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                              <div className="text-center p-4 rounded-lg bg-default-50">
                                <Mail size={24} className="mx-auto mb-2 text-primary" />
                                <p className="text-2xl font-bold">{stats.messages_exchanged}</p>
                                <p className="text-xs text-default-400">{"Messages"}</p>
                              </div>
                              <div className="text-center p-4 rounded-lg bg-default-50">
                                <ArrowLeftRight size={24} className="mx-auto mb-2 text-success" />
                                <p className="text-2xl font-bold">{stats.transactions_completed}</p>
                                <p className="text-xs text-default-400">{"Transactions"}</p>
                              </div>
                              <div className="text-center p-4 rounded-lg bg-default-50">
                                <Users size={24} className="mx-auto mb-2 text-secondary" />
                                <p className="text-2xl font-bold">{stats.connections_made}</p>
                                <p className="text-xs text-default-400">{"Connections"}</p>
                              </div>
                            </div>
                          ) : (
                            <p className="text-sm text-default-400 text-center py-4">
                              {"No stats available"}
                            </p>
                          )}
                        </CardBody>
                      </Card>
                    )}
                  </div>
                ) : (
                  <p className="text-sm text-default-400 text-center py-8">
                    {"Partnership not Found"}
                  </p>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>{"Close"}</Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default Partnerships;
