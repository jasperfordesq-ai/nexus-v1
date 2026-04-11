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
import {
  Handshake, RefreshCw, MoreVertical, CheckCircle, XCircle, Ban,
  Eye, MessageSquare, ArrowLeftRight, ShieldCheck, Clock, BarChart3,
  Users, Mail, ShoppingBag, Calendar, UsersRound, Inbox,
} from 'lucide-react';
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
  usePageTitle(t('federation.page_title'));
  const toast = useToast();

  const [items, setItems] = useState<Partnership[]>([]);
  const [loading, setLoading] = useState(true);
  const [terminateTarget, setTerminateTarget] = useState<Partnership | null>(null);
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
  const handleApprove = async (item: Partnership) => {
    if (!window.confirm(t('federation.confirm_approve_partnership'))) return;
    setActionLoading(true);
    try {
      const res = await adminFederation.approvePartnership(item.id);
      if (res.success) {
        toast.success(t('federation.partnership_approved', { name: item.partner_name }));
        loadData();
      } else {
        toast.error(t('federation.failed_to_approve_partnership'));
      }
    } catch {
      toast.error(t('federation.failed_to_approve_partnership'));
    } finally {
      setActionLoading(false);
    }
  };

  const handleReject = async (item: Partnership) => {
    if (!window.confirm(t('federation.confirm_reject_partnership'))) return;
    setActionLoading(true);
    try {
      const res = await adminFederation.rejectPartnership(item.id);
      if (res.success) {
        toast.success(t('federation.partnership_rejected', { name: item.partner_name }));
        loadData();
      } else {
        toast.error(t('federation.failed_to_reject_partnership'));
      }
    } catch {
      toast.error(t('federation.failed_to_reject_partnership'));
    } finally {
      setActionLoading(false);
    }
  };

  const handleTerminate = async () => {
    if (!terminateTarget) return;
    setActionLoading(true);
    try {
      const res = await adminFederation.terminatePartnership(terminateTarget.id);
      if (res.success) {
        toast.success(t('federation.partnership_terminated', { name: terminateTarget.partner_name }));
        setTerminateTarget(null);
        loadData();
      } else {
        toast.error(t('federation.failed_to_terminate_partnership'));
      }
    } catch {
      toast.error(t('federation.failed_to_terminate_partnership'));
    } finally {
      setActionLoading(false);
    }
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
        toast.success(t('federation.counter_proposal_sent', { name: counterTarget.partner_name }));
        counterModal.onClose();
        loadData();
      } else {
        toast.error(t('federation.failed_to_counter_propose'));
      }
    } catch {
      toast.error(t('federation.failed_to_counter_propose'));
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
      toast.error(t('federation.failed_to_load_partnership_detail'));
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
        toast.success(t('federation.permissions_updated'));
      } else {
        toast.error(t('federation.failed_to_update_permissions'));
      }
    } catch {
      toast.error(t('federation.failed_to_update_permissions'));
    } finally {
      setPermissionsLoading(false);
    }
  };

  // ─── Columns ───
  const columns: Column<Partnership>[] = [
    { key: 'partner_name', label: t('federation.col_partner_community'), sortable: true },
    { key: 'partner_slug', label: t('federation.col_slug') },
    {
      key: 'federation_level', label: t('federation.col_level'),
      render: (item) => (
        <Chip size="sm" variant="flat" color="primary">
          {t(LEVEL_LABEL_KEYS[item.federation_level || 1] || 'federation.level_discovery')}
        </Chip>
      ),
    },
    {
      key: 'status', label: t('federation.col_status'),
      render: (item) => <StatusBadge status={item.status} />,
    },
    {
      key: 'created_at', label: t('federation.col_since'), sortable: true,
      render: (item) => <span className="text-sm text-default-500">{item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}</span>,
    },
    {
      key: 'actions', label: t('federation.col_actions'),
      render: (item) => (
        <div className="flex items-center gap-1">
          <Button
            isIconOnly size="sm" variant="light"
            aria-label={t('federation.label_view_detail')}
            onPress={() => openDetail(item)}
          >
            <Eye size={16} />
          </Button>
          <Dropdown>
            <DropdownTrigger>
              <Button isIconOnly size="sm" variant="light" aria-label={t('federation.label_actions')} isDisabled={actionLoading}>
                <MoreVertical size={16} />
              </Button>
            </DropdownTrigger>
            <DropdownMenu
              aria-label={t('federation.label_partnership_actions')}
              onAction={(key) => {
                if (key === 'approve') handleApprove(item);
                else if (key === 'reject') handleReject(item);
                else if (key === 'terminate') setTerminateTarget(item);
                else if (key === 'counter') openCounterProposal(item);
              }}
              items={[
                ...(item.status === 'pending' ? [{ key: 'approve' }, { key: 'reject' }, { key: 'counter' }] : []),
                ...(item.status === 'active' ? [{ key: 'terminate' }] : []),
              ]}
            >
              {(action) => {
                if (action.key === 'approve') return (
                  <DropdownItem key="approve" startContent={<CheckCircle size={14} />} className="text-success">
                    {t('federation.approve')}
                  </DropdownItem>
                );
                if (action.key === 'reject') return (
                  <DropdownItem key="reject" startContent={<XCircle size={14} />} className="text-danger" color="danger">
                    {t('federation.reject')}
                  </DropdownItem>
                );
                if (action.key === 'counter') return (
                  <DropdownItem key="counter" startContent={<MessageSquare size={14} />} className="text-warning">
                    {t('federation.counter_propose')}
                  </DropdownItem>
                );
                return (
                  <DropdownItem key="terminate" startContent={<Ban size={14} />} className="text-danger" color="danger">
                    {t('federation.terminate')}
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
        <PageHeader title={t('federation.partnerships_title')} description={t('federation.partnerships_desc')} />
        <EmptyState icon={Handshake} title={t('federation.no_partnerships')} description={t('federation.desc_no_federation_partnerships_have_been_est')} />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <PageHeader
        title={t('federation.partnerships_title')}
        description={t('federation.partnerships_desc')}
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>{t('federation.refresh')}</Button>}
      />

      {/* Tabs: All / Incoming / Active */}
      <Tabs
        selectedKey={activeTab}
        onSelectionChange={(key) => setActiveTab(String(key))}
        variant="underlined"
        aria-label={t('federation.label_partnership_tabs')}
      >
        <Tab
          key="all"
          title={
            <div className="flex items-center gap-2">
              <Handshake size={16} />
              <span>{t('federation.tab_all')}</span>
              <Chip size="sm" variant="flat">{items.length}</Chip>
            </div>
          }
        />
        <Tab
          key="incoming"
          title={
            <div className="flex items-center gap-2">
              <Inbox size={16} />
              <span>{t('federation.tab_incoming_requests')}</span>
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
              <span>{t('federation.tab_active')}</span>
              <Chip size="sm" variant="flat" color="success">
                {items.filter(p => p.status === 'active').length}
              </Chip>
            </div>
          }
        />
      </Tabs>

      <DataTable columns={columns} data={filteredItems} isLoading={loading} onRefresh={loadData} />

      {/* Terminate confirmation */}
      {terminateTarget && (
        <ConfirmModal
          isOpen={!!terminateTarget}
          onClose={() => setTerminateTarget(null)}
          onConfirm={handleTerminate}
          title={t('federation.terminate_partnership')}
          message={t('federation.terminate_partnership_confirm', { name: terminateTarget.partner_name })}
          confirmLabel={t('federation.terminate')}
          confirmColor="danger"
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
                {t('federation.counter_propose_title')}
              </ModalHeader>
              <ModalBody className="gap-4">
                {counterTarget && (
                  <p className="text-sm text-default-500">
                    {t('federation.counter_propose_desc', { name: counterTarget.partner_name })}
                  </p>
                )}

                <Select
                  label={t('federation.label_federation_level')}
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
                  <p className="text-sm font-medium">{t('federation.label_feature_permissions')}</p>
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
                  label={t('federation.label_message')}
                  placeholder={t('federation.placeholder_counter_message')}
                  value={counterMessage}
                  onValueChange={setCounterMessage}
                  maxLength={1000}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>{t('federation.cancel')}</Button>
                <Button
                  color="warning"
                  isLoading={counterLoading}
                  onPress={handleCounterPropose}
                >
                  {t('federation.send_counter_proposal')}
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
                  ? t('federation.partnership_with', { name: detailPartnership.resolved_partner_name || detailPartnership.partner_name })
                  : t('federation.partnership_detail')
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
                          <span>{t('federation.tab_info')}</span>
                        </div>
                      } />
                      <Tab key="permissions" title={
                        <div className="flex items-center gap-1.5">
                          <ShieldCheck size={14} />
                          <span>{t('federation.tab_permissions')}</span>
                        </div>
                      } />
                      <Tab key="history" title={
                        <div className="flex items-center gap-1.5">
                          <Clock size={14} />
                          <span>{t('federation.tab_history')}</span>
                        </div>
                      } />
                      <Tab key="stats" title={
                        <div className="flex items-center gap-1.5">
                          <BarChart3 size={14} />
                          <span>{t('federation.tab_statistics')}</span>
                        </div>
                      } />
                    </Tabs>

                    {/* Info Tab */}
                    {detailTab === 'info' && (
                      <Card shadow="none" className="border border-default-200">
                        <CardBody className="gap-3">
                          <div className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                              <p className="text-default-400">{t('federation.label_partner')}</p>
                              <p className="font-medium">{detailPartnership.resolved_partner_name || detailPartnership.partner_name}</p>
                            </div>
                            <div>
                              <p className="text-default-400">{t('federation.col_status')}</p>
                              <StatusBadge status={detailPartnership.status} />
                            </div>
                            <div>
                              <p className="text-default-400">{t('federation.col_level')}</p>
                              <Chip size="sm" variant="flat" color="primary">
                                {t(LEVEL_LABEL_KEYS[detailPartnership.federation_level || 1] || 'federation.level_discovery')}
                              </Chip>
                            </div>
                            <div>
                              <p className="text-default-400">{t('federation.label_direction')}</p>
                              <p className="font-medium">
                                {detailPartnership.is_initiator
                                  ? t('federation.outgoing_request')
                                  : t('federation.incoming_request')
                                }
                              </p>
                            </div>
                            <div>
                              <p className="text-default-400">{t('federation.label_created')}</p>
                              <p>{detailPartnership.created_at ? new Date(detailPartnership.created_at).toLocaleDateString() : '--'}</p>
                            </div>
                            {detailPartnership.approved_at && (
                              <div>
                                <p className="text-default-400">{t('federation.label_approved')}</p>
                                <p>{new Date(detailPartnership.approved_at).toLocaleDateString()}</p>
                              </div>
                            )}
                          </div>
                          {detailPartnership.notes && (
                            <div className="mt-2">
                              <p className="text-sm text-default-400">{t('federation.label_notes')}</p>
                              <p className="text-sm mt-1 rounded-lg bg-default-100 p-3">{detailPartnership.notes}</p>
                            </div>
                          )}
                          {detailPartnership.counter_proposal_message && (
                            <div className="mt-2">
                              <p className="text-sm text-default-400">{t('federation.label_counter_proposal')}</p>
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
                          <p className="text-sm font-semibold">{t('federation.label_feature_permissions')}</p>
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
                                      {isEnabled ? t('federation.enabled') : t('federation.disabled')}
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
                              {t('federation.permissions_editable_when_active')}
                            </p>
                          )}
                        </CardBody>
                      </Card>
                    )}

                    {/* History/Audit Tab */}
                    {detailTab === 'history' && (
                      <Card shadow="none" className="border border-default-200">
                        <CardHeader>
                          <p className="text-sm font-semibold">{t('federation.label_timeline')}</p>
                        </CardHeader>
                        <CardBody>
                          {auditLoading ? (
                            <div className="flex h-24 items-center justify-center">
                              <Spinner size="sm" />
                            </div>
                          ) : auditLog.length === 0 ? (
                            <p className="text-sm text-default-400 text-center py-4">
                              {t('federation.no_audit_log_entries')}
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
                                        : t('federation.system')
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
                          <p className="text-sm font-semibold">{t('federation.label_partnership_statistics')}</p>
                        </CardHeader>
                        <CardBody>
                          {statsLoading ? (
                            <div className="flex h-24 items-center justify-center">
                              <Spinner size="sm" />
                            </div>
                          ) : stats ? (
                            <div className="grid grid-cols-3 gap-4">
                              <div className="text-center p-4 rounded-lg bg-default-50">
                                <Mail size={24} className="mx-auto mb-2 text-primary" />
                                <p className="text-2xl font-bold">{stats.messages_exchanged}</p>
                                <p className="text-xs text-default-400">{t('federation.stat_messages')}</p>
                              </div>
                              <div className="text-center p-4 rounded-lg bg-default-50">
                                <ArrowLeftRight size={24} className="mx-auto mb-2 text-success" />
                                <p className="text-2xl font-bold">{stats.transactions_completed}</p>
                                <p className="text-xs text-default-400">{t('federation.stat_transactions')}</p>
                              </div>
                              <div className="text-center p-4 rounded-lg bg-default-50">
                                <Users size={24} className="mx-auto mb-2 text-secondary" />
                                <p className="text-2xl font-bold">{stats.connections_made}</p>
                                <p className="text-xs text-default-400">{t('federation.stat_connections')}</p>
                              </div>
                            </div>
                          ) : (
                            <p className="text-sm text-default-400 text-center py-4">
                              {t('federation.no_stats_available')}
                            </p>
                          )}
                        </CardBody>
                      </Card>
                    )}
                  </div>
                ) : (
                  <p className="text-sm text-default-400 text-center py-8">
                    {t('federation.partnership_not_found')}
                  </p>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>{t('federation.close')}</Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default Partnerships;
