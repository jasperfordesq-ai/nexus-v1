// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation External Partners
 * Full CRUD for managing external federation partner connections.
 * Includes health check, API call logs, and feature toggles.
 */

import { useState, useCallback, useEffect } from 'react';
import {
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
  Switch,
  Spinner,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Textarea,
  useDisclosure,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
} from '@heroui/react';
import Globe from 'lucide-react/icons/globe';
import Plus from 'lucide-react/icons/plus';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import MoreVertical from 'lucide-react/icons/ellipsis-vertical';
import Pencil from 'lucide-react/icons/pencil';
import Trash2 from 'lucide-react/icons/trash-2';
import HeartPulse from 'lucide-react/icons/heart-pulse';
import ScrollText from 'lucide-react/icons/scroll-text';
import X from 'lucide-react/icons/x';
import Check from 'lucide-react/icons/check';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';
import { PageHeader, ConfirmModal } from '../../components';

import { useTranslation } from 'react-i18next';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface ExternalPartner {
  id: number;
  name: string;
  description: string | null;
  base_url: string;
  api_path: string;
  auth_method: string;
  protocol_type: string;
  status: string;
  last_sync_at: string | null;
  created_at: string;
  updated_at: string | null;
  allow_member_search: boolean;
  allow_listing_search: boolean;
  allow_messaging: boolean;
  allow_transactions: boolean;
  allow_events: boolean;
  allow_groups: boolean;
  error_count: number;
  last_error: string | null;
  partner_name: string | null;
  partner_version: string | null;
  oauth_token_url?: string | null;
}

interface PartnerFormData {
  name: string;
  base_url: string;
  api_path: string;
  auth_method: string;
  protocol_type: string;
  description: string;
  status: string;
  api_key: string;
  signing_secret: string;
  oauth_client_id: string;
  oauth_client_secret: string;
  oauth_token_url: string;
  allow_member_search: boolean;
  allow_listing_search: boolean;
  allow_messaging: boolean;
  allow_transactions: boolean;
  allow_events: boolean;
  allow_groups: boolean;
}

interface PartnerLog {
  id: number;
  partner_id: number;
  endpoint: string;
  method: string;
  response_code: number | null;
  response_time_ms: number | null;
  success: boolean;
  error_message: string | null;
  created_at: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

const STATUS_COLORS: Record<string, 'success' | 'default' | 'warning' | 'danger'> = {
  active: 'success',
  inactive: 'default',
  suspended: 'warning',
  error: 'danger',
  pending: 'warning',
  failed: 'danger',
};

const AUTH_METHODS = [
  { key: 'api_key', i18nKey: 'federation.auth_method_api_key' },
  { key: 'hmac', i18nKey: 'federation.auth_method_hmac' },
  { key: 'oauth2', i18nKey: 'federation.auth_method_oauth2' },
];

const PROTOCOL_TYPES = [
  { key: 'nexus', label: 'Project NEXUS' },
  { key: 'timeoverflow', label: 'TimeOverflow' },
  { key: 'komunitin', label: 'Komunitin (JSON:API)' },
  { key: 'credit_commons', label: 'Credit Commons' },
];

const PARTNER_STATUSES = [
  { key: 'pending', i18nKey: 'federation.status_pending' },
  { key: 'active', i18nKey: 'federation.status_active' },
  { key: 'suspended', i18nKey: 'federation.status_suspended' },
];

const EMPTY_FORM: PartnerFormData = {
  name: '',
  base_url: '',
  api_path: '/api/v1/federation',
  auth_method: 'api_key',
  protocol_type: 'nexus',
  description: '',
  status: 'pending',
  api_key: '',
  signing_secret: '',
  oauth_client_id: '',
  oauth_client_secret: '',
  oauth_token_url: '',
  allow_member_search: true,
  allow_listing_search: true,
  allow_messaging: false,
  allow_transactions: false,
  allow_events: false,
  allow_groups: false,
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function ExternalPartners() {
  const { t } = useTranslation('admin');
  usePageTitle("Federation");
  const toast = useToast();

  const formModal = useDisclosure();
  const logsModal = useDisclosure();

  const [partners, setPartners] = useState<ExternalPartner[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [healthCheckLoading, setHealthCheckLoading] = useState<number | null>(null);

  // Form state
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<PartnerFormData>({ ...EMPTY_FORM });

  // Delete state
  const [deleteTarget, setDeleteTarget] = useState<ExternalPartner | null>(null);
  const [deleting, setDeleting] = useState(false);

  // Logs state
  const [logs, setLogs] = useState<PartnerLog[]>([]);
  const [logsLoading, setLogsLoading] = useState(false);
  const [logsPartnerName, setLogsPartnerName] = useState('');

  // ─── Load data ───
  const loadData = useCallback(async () => {
    // NOTE: We intentionally do NOT pass an AbortController signal here.
    // The api.get() deduplication cache keys on endpoint only (not signal),
    // so in React StrictMode's mount→unmount→remount cycle, aborting the
    // first request would poison the cached promise for the second caller.
    setLoading(true);
    try {
      const res = await api.get('/v2/admin/federation/external-partners');
      if (res.success) {
        const payload = res.data;
        setPartners(Array.isArray(payload) ? payload : (payload as { data?: ExternalPartner[] })?.data ?? []);
      }
    } catch (err) {
      logError('ExternalPartners.load', err);
      toast.error(t('federation.failed_to_load_external_partners', 'Failed to load external partners'));
    }
    setLoading(false);
  }, [toast]);


  useEffect(() => {
    loadData();
  }, [loadData]);

  // ─── Open create modal ───
  const openCreate = useCallback(() => {
    setEditingId(null);
    setForm({ ...EMPTY_FORM });
    formModal.onOpen();
  }, [formModal]);

  // ─── Open edit modal ───
  const openEdit = useCallback((partner: ExternalPartner) => {
    setEditingId(partner.id);
    setForm({
      name: partner.name,
      base_url: partner.base_url,
      api_path: partner.api_path || '/api/v1/federation',
      auth_method: partner.auth_method || 'api_key',
      protocol_type: partner.protocol_type || 'nexus',
      description: partner.description || '',
      status: partner.status || 'pending',
      api_key: '',
      signing_secret: '',
      oauth_client_id: '',
      oauth_client_secret: '',
      oauth_token_url: partner.oauth_token_url || '',
      allow_member_search: partner.allow_member_search,
      allow_listing_search: partner.allow_listing_search,
      allow_messaging: partner.allow_messaging,
      allow_transactions: partner.allow_transactions,
      allow_events: partner.allow_events,
      allow_groups: partner.allow_groups,
    });
    formModal.onOpen();
  }, [formModal]);

  // ─── Save (create or update) ───
  const handleSave = useCallback(async () => {
    if (!form.name.trim() || !form.base_url.trim()) {
      toast.error(t('federation.name_and_url_required', 'Name and Base URL are required'));
      return;
    }

    setSaving(true);
    try {
      const payload: Record<string, unknown> = {
        name: form.name,
        base_url: form.base_url,
        api_path: form.api_path,
        auth_method: form.auth_method,
        protocol_type: form.protocol_type,
        description: form.description || null,
        ...(editingId ? { status: form.status } : {}),
        allow_member_search: form.allow_member_search,
        allow_listing_search: form.allow_listing_search,
        allow_messaging: form.allow_messaging,
        allow_transactions: form.allow_transactions,
        allow_events: form.allow_events,
        allow_groups: form.allow_groups,
      };

      // Only include credential fields if they have values
      if (form.api_key) payload.api_key = form.api_key;
      if (form.signing_secret) payload.signing_secret = form.signing_secret;
      if (form.oauth_client_id) payload.oauth_client_id = form.oauth_client_id;
      if (form.oauth_client_secret) payload.oauth_client_secret = form.oauth_client_secret;
      if (form.oauth_token_url) payload.oauth_token_url = form.oauth_token_url;
      let res;
      if (editingId) {
        res = await api.put(`/v2/admin/federation/external-partners/${editingId}`, payload);
      } else {
        res = await api.post('/v2/admin/federation/external-partners', payload);
      }

      if (res.success) {
        toast.success(
          editingId
            ? t('federation.partner_updated', 'Partner updated successfully')
            : t('federation.partner_created', 'Partner created successfully')
        );
        formModal.onClose();
        loadData();
      } else {
        const errorMsg = (res as { error?: string }).error || "Failed to save partner";
        toast.error(errorMsg);
      }
    } catch (err) {
      logError('ExternalPartners.save', err);
      toast.error(t('federation.failed_to_save_partner', 'Failed to save partner'));
    }
    setSaving(false);
  }, [form, editingId, toast, t, formModal, loadData]);

  // ─── Delete ───
  const handleDelete = useCallback(async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      const res = await api.delete(`/v2/admin/federation/external-partners/${deleteTarget.id}`);
      if (res.success) {
        toast.success(`Partner Deleted`);
        setDeleteTarget(null);
        loadData();
      } else {
        toast.error(t('federation.failed_to_delete_partner', 'Failed to delete partner'));
      }
    } catch (err) {
      logError('ExternalPartners.delete', err);
      toast.error(t('federation.failed_to_delete_partner', 'Failed to delete partner'));
    }
    setDeleting(false);
  }, [deleteTarget, toast, t, loadData]);

  // ─── Health check ───
  const handleHealthCheck = useCallback(async (partner: ExternalPartner) => {
    setHealthCheckLoading(partner.id);
    try {
      const res = await api.post(`/v2/admin/federation/external-partners/${partner.id}/health-check`, {});
      if (res.success) {
        const data = res.data as { healthy?: boolean; response_time_ms?: number; error?: string };
        if (data?.healthy) {
          toast.success(
            t('federation.health_check_success', {
              name: partner.name,
              time: data?.response_time_ms ?? '?',
            }) || `${partner.name}: Healthy (${data?.response_time_ms ?? '?'}ms)`
          );
        } else {
          toast.error(
            t('federation.health_check_partner_error', {
              name: partner.name,
              error: data?.error ?? 'Partner unreachable',
            }) || `${partner.name}: ${data?.error ?? 'Partner unreachable'}`
          );
        }
        loadData();
      } else {
        const errorMsg = (res as { error?: string }).error || "Health Check error";
        toast.error(`Health Check Partner error`);
      }
    } catch (err) {
      logError('ExternalPartners.healthCheck', err);
      toast.error(`Health check failed` || `${partner.name}: Health check failed`);
    }
    setHealthCheckLoading(null);
  }, [toast, t, loadData]);

  // ─── View logs ───
  const handleViewLogs = useCallback(async (partner: ExternalPartner) => {
    setLogsPartnerName(partner.name);
    setLogsLoading(true);
    setLogs([]);
    logsModal.onOpen();

    try {
      const res = await api.get(`/v2/admin/federation/external-partners/${partner.id}/logs`);
      if (res.success) {
        const payload = res.data;
        setLogs(Array.isArray(payload) ? payload : (payload as { data?: PartnerLog[] })?.data ?? []);
      }
    } catch (err) {
      logError('ExternalPartners.logs', err);
      toast.error(t('federation.failed_to_load_logs', 'Failed to load partner logs'));
    }
    setLogsLoading(false);
  }, [toast, t, logsModal]);

  // ─── Update form field ───
  const updateForm = useCallback((field: keyof PartnerFormData, value: string | boolean) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  }, []);

  // ─── Render ───
  if (loading) {
    return (
      <div>
        <PageHeader
          title={t('federation.external_partners_title', 'External Partners')}
          description={t('federation.external_partners_desc', 'Manage connections to external federation partners')}
        />
        <div className="flex h-64 items-center justify-center">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('federation.external_partners_title', 'External Partners')}
        description={t('federation.external_partners_desc', 'Manage connections to external federation partners')}
        actions={
          <div className="flex items-center gap-2">
            <Button variant="flat" size="sm" startContent={<RefreshCw size={16} />} onPress={() => loadData()} isLoading={loading}>
              {"Refresh"}
            </Button>
            <Button color="primary" size="sm" startContent={<Plus size={16} />} onPress={openCreate}>
              {t('federation.add_partner', 'Add Partner')}
            </Button>
          </div>
        }
      />

      {/* Partners table */}
      <Table aria-label={t('federation.external_partners_title', 'External Partners')} removeWrapper>
        <TableHeader>
          <TableColumn>{t('federation.col_name', 'Name')}</TableColumn>
          <TableColumn>{t('federation.col_base_url', 'Base URL')}</TableColumn>
          <TableColumn>{t('federation.col_auth_method', 'Auth Method')}</TableColumn>
          <TableColumn>{t('federation.col_protocol', 'Protocol')}</TableColumn>
          <TableColumn>{t('federation.col_status', 'Status')}</TableColumn>
          <TableColumn>{t('federation.col_last_sync', 'Last Sync')}</TableColumn>
          <TableColumn>{t('federation.col_created', 'Created')}</TableColumn>
          <TableColumn>{t('federation.col_actions', 'Actions')}</TableColumn>
        </TableHeader>
        <TableBody emptyContent={t('federation.no_external_partners', 'No external partners configured')}>
          {partners.map((partner) => (
            <TableRow key={partner.id}>
              <TableCell>
                <div>
                  <p className="font-medium text-sm">{partner.name}</p>
                  {partner.description && (
                    <p className="text-xs text-default-400 truncate max-w-[200px]">{partner.description}</p>
                  )}
                </div>
              </TableCell>
              <TableCell>
                <code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">{partner.base_url}</code>
              </TableCell>
              <TableCell>
                <Chip size="sm" variant="flat">
                  {t(`federation.auth_method_${partner.auth_method}`, partner.auth_method)}
                </Chip>
              </TableCell>
              <TableCell>
                <Chip size="sm" variant="flat" color="secondary">
                  {PROTOCOL_TYPES.find((p) => p.key === partner.protocol_type)?.label ?? partner.protocol_type ?? 'NEXUS'}
                </Chip>
              </TableCell>
              <TableCell>
                <Chip
                  size="sm"
                  variant="flat"
                  color={STATUS_COLORS[partner.status] ?? 'default'}
                >
                  {t(`federation.status_${partner.status}`)}
                </Chip>
              </TableCell>
              <TableCell>
                <span className="text-sm text-default-500">
                  {partner.last_sync_at ? formatRelativeTime(partner.last_sync_at) : t('federation.never', 'Never')}
                </span>
              </TableCell>
              <TableCell>
                <span className="text-sm text-default-400">
                  {partner.created_at ? formatRelativeTime(partner.created_at) : '--'}
                </span>
              </TableCell>
              <TableCell>
                <Dropdown>
                  <DropdownTrigger>
                    <Button isIconOnly size="sm" variant="light" aria-label={t('federation.label_actions', 'Actions')}>
                      <MoreVertical size={16} />
                    </Button>
                  </DropdownTrigger>
                  <DropdownMenu
                    aria-label={t('federation.label_partner_actions', 'Partner actions')}
                    onAction={(key) => {
                      if (key === 'edit') openEdit(partner);
                      else if (key === 'health') handleHealthCheck(partner);
                      else if (key === 'logs') handleViewLogs(partner);
                      else if (key === 'delete') setDeleteTarget(partner);
                    }}
                  >
                    <DropdownItem key="edit" startContent={<Pencil size={14} />}>
                      {t('federation.edit', 'Edit')}
                    </DropdownItem>
                    <DropdownItem key="health" startContent={healthCheckLoading === partner.id ? <Spinner size="sm" /> : <HeartPulse size={14} />}>
                      {t('federation.health_check', 'Health Check')}
                    </DropdownItem>
                    <DropdownItem key="logs" startContent={<ScrollText size={14} />}>
                      {t('federation.view_logs', 'View Logs')}
                    </DropdownItem>
                    <DropdownItem key="delete" startContent={<Trash2 size={14} />} className="text-danger" color="danger">
                      {t('federation.delete', 'Delete')}
                    </DropdownItem>
                  </DropdownMenu>
                </Dropdown>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      {/* Create/Edit Modal */}
      <Modal isOpen={formModal.isOpen} onOpenChange={formModal.onOpenChange} size="2xl" scrollBehavior="inside">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex items-center gap-2">
                <Globe size={20} />
                {editingId
                  ? t('federation.edit_partner', 'Edit External Partner')
                  : t('federation.add_partner', 'Add External Partner')}
              </ModalHeader>
              <ModalBody className="gap-4">
                {/* Basic fields */}
                <Input
                  label={t('federation.label_partner_name', 'Partner Name')}
                  placeholder={t('federation.placeholder_partner_name', 'e.g. Community Exchange Network')}
                  value={form.name}
                  onValueChange={(v) => updateForm('name', v)}
                  isRequired
                />
                <Input
                  label={t('federation.label_base_url', 'Base URL')}
                  placeholder="https://api.partner-timebank.org"
                  value={form.base_url}
                  onValueChange={(v) => updateForm('base_url', v)}
                  isRequired
                />
                <Input
                  label={t('federation.label_api_path', 'API Path')}
                  placeholder="/api/v1/federation"
                  value={form.api_path}
                  onValueChange={(v) => updateForm('api_path', v)}
                />
                {/* Federation protocol */}
                <Select
                  label={t('federation.label_protocol_type', 'Federation Protocol')}
                  description={t('federation.protocol_type_desc', 'The protocol this partner uses for data exchange')}
                  selectedKeys={[form.protocol_type]}
                  onSelectionChange={(keys) => {
                    const selected = Array.from(keys)[0];
                    if (selected) {
                      updateForm('protocol_type', String(selected));
                      // Auto-set API path based on protocol
                      const paths: Record<string, string> = {
                        nexus: '/api/v2/federation',
                        timeoverflow: '/api/v1',
                        komunitin: '/api/v1',
                        credit_commons: '',
                      };
                      const nextPath = paths[String(selected)];
                      if (nextPath !== undefined) {
                        updateForm('api_path', nextPath);
                      }
                    }
                  }}
                >
                  {PROTOCOL_TYPES.map((p) => (
                    <SelectItem key={p.key}>{p.label}</SelectItem>
                  ))}
                </Select>

                <Textarea
                  label={t('federation.label_description', 'Description')}
                  placeholder={t('federation.placeholder_description', 'Brief description of this partner')}
                  value={form.description}
                  onValueChange={(v) => updateForm('description', v)}
                  minRows={2}
                />

                {/* Status — only shown when editing */}
                {editingId && (
                  <Select
                    label={t('federation.label_status', 'Status')}
                    selectedKeys={[form.status]}
                    onSelectionChange={(keys) => {
                      const selected = Array.from(keys)[0];
                      if (selected) updateForm('status', String(selected));
                    }}
                  >
                    {PARTNER_STATUSES.map((s) => (
                      <SelectItem key={s.key}>{t(s.i18nKey)}</SelectItem>
                    ))}
                  </Select>
                )}

                {/* Auth method */}
                <Select
                  label={t('federation.label_auth_method', 'Authentication Method')}
                  selectedKeys={[form.auth_method]}
                  onSelectionChange={(keys) => {
                    const selected = Array.from(keys)[0];
                    if (selected) updateForm('auth_method', String(selected));
                  }}
                >
                  {AUTH_METHODS.map((m) => (
                    <SelectItem key={m.key}>{t(m.i18nKey)}</SelectItem>
                  ))}
                </Select>

                {/* Credential fields — shown based on auth_method */}
                {(form.auth_method === 'api_key' || form.auth_method === 'oauth2') && (
                  <Input
                    label={t('federation.label_api_key', 'API Key / Token')}
                    placeholder={editingId ? t('federation.placeholder_leave_blank', 'Leave blank to keep existing') : ''}
                    value={form.api_key}
                    onValueChange={(v) => updateForm('api_key', v)}
                    type="password"
                  />
                )}
                {form.auth_method === 'hmac' && (
                  <Input
                    label={t('federation.label_signing_secret', 'Signing Secret')}
                    placeholder={editingId ? t('federation.placeholder_leave_blank', 'Leave blank to keep existing') : ''}
                    value={form.signing_secret}
                    onValueChange={(v) => updateForm('signing_secret', v)}
                    type="password"
                  />
                )}
                {form.auth_method === 'oauth2' && (
                  <>
                    <Input
                      label={t('federation.label_oauth_client_id', 'OAuth Client ID')}
                      value={form.oauth_client_id}
                      onValueChange={(v) => updateForm('oauth_client_id', v)}
                    />
                    <Input
                      label={t('federation.label_oauth_client_secret', 'OAuth Client Secret')}
                      placeholder={editingId ? t('federation.placeholder_leave_blank', 'Leave blank to keep existing') : ''}
                      value={form.oauth_client_secret}
                      onValueChange={(v) => updateForm('oauth_client_secret', v)}
                      type="password"
                    />
                    <Input
                      label={t('federation.label_oauth_token_url', 'OAuth Token URL')}
                      placeholder="https://api.partner-timebank.org/oauth/token"
                      value={form.oauth_token_url}
                      onValueChange={(v) => updateForm('oauth_token_url', v)}
                    />
                  </>
                )}

                {/* Feature toggles */}
                <div className="space-y-3 pt-2">
                  <p className="text-sm font-semibold text-default-700">
                    {t('federation.feature_toggles', 'Feature Toggles')}
                  </p>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <Switch
                      isSelected={form.allow_member_search}
                      onValueChange={(v) => updateForm('allow_member_search', v)}
                      size="sm"
                    >
                      {t('federation.member_search_enabled', 'Member Search')}
                    </Switch>
                    <Switch
                      isSelected={form.allow_listing_search}
                      onValueChange={(v) => updateForm('allow_listing_search', v)}
                      size="sm"
                    >
                      {t('federation.listing_search_enabled', 'Listing Search')}
                    </Switch>
                    <Switch
                      isSelected={form.allow_messaging}
                      onValueChange={(v) => updateForm('allow_messaging', v)}
                      size="sm"
                    >
                      {t('federation.messaging_enabled', 'Messaging')}
                    </Switch>
                    <Switch
                      isSelected={form.allow_transactions}
                      onValueChange={(v) => updateForm('allow_transactions', v)}
                      size="sm"
                    >
                      {t('federation.transactions_enabled', 'Transactions')}
                    </Switch>
                    <Switch
                      isSelected={form.allow_events}
                      onValueChange={(v) => updateForm('allow_events', v)}
                      size="sm"
                    >
                      {t('federation.events_enabled', 'Events')}
                    </Switch>
                    <Switch
                      isSelected={form.allow_groups}
                      onValueChange={(v) => updateForm('allow_groups', v)}
                      size="sm"
                    >
                      {t('federation.groups_enabled', 'Groups')}
                    </Switch>
                  </div>
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>{t('federation.cancel', 'Cancel')}</Button>
                <Button
                  color="primary"
                  isLoading={saving}
                  isDisabled={!form.name.trim() || !form.base_url.trim()}
                  onPress={handleSave}
                >
                  {editingId ? t('federation.save_changes', 'Save Changes') : t('federation.create_partner', 'Create Partner')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Logs Modal */}
      <Modal isOpen={logsModal.isOpen} onOpenChange={logsModal.onOpenChange} size="3xl" scrollBehavior="inside">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex items-center gap-2">
                <ScrollText size={20} />
                {t('federation.api_logs_for', 'API Logs for')} {logsPartnerName}
              </ModalHeader>
              <ModalBody>
                {logsLoading ? (
                  <div className="flex h-48 items-center justify-center">
                    <Spinner size="lg" />
                  </div>
                ) : logs.length === 0 ? (
                  <div className="flex h-48 items-center justify-center text-default-400">
                    {t('federation.no_logs', 'No API call logs found')}
                  </div>
                ) : (
                  <Table aria-label={t('federation.api_logs', 'API Logs')} removeWrapper>
                    <TableHeader>
                      <TableColumn>{t('federation.col_endpoint', 'Endpoint')}</TableColumn>
                      <TableColumn>{t('federation.col_method', 'Method')}</TableColumn>
                      <TableColumn>{t('federation.col_status_code', 'Status')}</TableColumn>
                      <TableColumn>{t('federation.col_success', 'Success')}</TableColumn>
                      <TableColumn>{t('federation.col_response_time', 'Response Time')}</TableColumn>
                      <TableColumn>{t('federation.col_timestamp', 'Timestamp')}</TableColumn>
                    </TableHeader>
                    <TableBody>
                      {logs.map((log) => (
                        <TableRow key={log.id}>
                          <TableCell>
                            <code className="text-xs bg-default-100 px-1 py-0.5 rounded">{log.endpoint}</code>
                          </TableCell>
                          <TableCell>
                            <Chip size="sm" variant="flat">{log.method}</Chip>
                          </TableCell>
                          <TableCell>
                            <span className="text-sm">{log.response_code ?? '--'}</span>
                          </TableCell>
                          <TableCell>
                            {log.success ? (
                              <Check size={16} className="text-success" />
                            ) : (
                              <X size={16} className="text-danger" />
                            )}
                          </TableCell>
                          <TableCell>
                            <span className="text-sm text-default-500">
                              {log.response_time_ms != null ? `${log.response_time_ms}ms` : '--'}
                            </span>
                          </TableCell>
                          <TableCell>
                            <span className="text-sm text-default-400">
                              {log.created_at ? formatRelativeTime(log.created_at) : '--'}
                            </span>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>{t('federation.close', 'Close')}</Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Delete confirmation */}
      {deleteTarget && (
        <ConfirmModal
          isOpen={!!deleteTarget}
          onClose={() => setDeleteTarget(null)}
          onConfirm={handleDelete}
          title={t('federation.delete_partner', 'Delete Partner')}
          message={t('federation.delete_partner_confirm', {
            name: deleteTarget.name,
          })}
          confirmLabel={t('federation.delete', 'Delete')}
          confirmColor="danger"
          isLoading={deleting}
        />
      )}
    </div>
  );
}

export default ExternalPartners;
