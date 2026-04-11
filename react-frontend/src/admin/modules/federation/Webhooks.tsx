// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Webhooks
 * Full CRUD for managing federation webhook subscriptions.
 * Includes test delivery, delivery logs with retry, and expandable log rows.
 */

import { useState, useCallback, useEffect, useRef } from 'react';
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
  Checkbox,
  CheckboxGroup,
  Spinner,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  useDisclosure,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Tooltip,
  Snippet,
} from '@heroui/react';
import {
  Webhook,
  Plus,
  RefreshCw,
  MoreVertical,
  Pencil,
  Trash2,
  ScrollText,
  Send,
  Check,
  X,
  RotateCcw,
  ChevronDown,
  ChevronRight,
} from 'lucide-react';
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

interface WebhookItem {
  id: number;
  url: string;
  secret?: string;
  events: string[];
  status: string;
  description: string | null;
  consecutive_failures: number;
  last_triggered_at: string | null;
  last_success_at: string | null;
  last_failure_at: string | null;
  last_failure_reason: string | null;
  created_at: string;
  updated_at: string | null;
}

interface WebhookLog {
  id: number;
  webhook_id: number;
  event_type: string;
  payload: Record<string, unknown>;
  response_code: number | null;
  response_body: string | null;
  response_time_ms: number | null;
  success: boolean;
  error_message: string | null;
  attempt_number: number;
  created_at: string;
}

interface WebhookFormData {
  url: string;
  description: string;
  events: string[];
}

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const STATUS_COLORS: Record<string, 'success' | 'default' | 'warning' | 'danger'> = {
  active: 'success',
  inactive: 'default',
  failing: 'danger',
};

const ALL_EVENT_KEYS = [
  'partnership.requested',
  'partnership.approved',
  'partnership.rejected',
  'partnership.terminated',
  'member.opted_in',
  'member.opted_out',
  'message.sent',
  'message.received',
  'transaction.created',
  'transaction.completed',
  'connection.requested',
  'connection.accepted',
  'listing.shared',
];

const EMPTY_FORM: WebhookFormData = {
  url: '',
  description: '',
  events: [],
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function Webhooks() {
  const { t } = useTranslation('admin');
  usePageTitle(t('federation.webhooks_title', 'Federation Webhooks'));
  const toast = useToast();

  const ALL_EVENTS = ALL_EVENT_KEYS.map((key) => ({
    key,
    label: t(`federation.webhook_${key.replace('.', '_')}`, key),
  }));

  const formModal = useDisclosure();
  const logsModal = useDisclosure();

  const [webhooks, setWebhooks] = useState<WebhookItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [testingId, setTestingId] = useState<number | null>(null);

  // Form state
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<WebhookFormData>({ ...EMPTY_FORM });
  const [createdSecret, setCreatedSecret] = useState<string | null>(null);

  // Delete state
  const [deleteTarget, setDeleteTarget] = useState<WebhookItem | null>(null);
  const [deleting, setDeleting] = useState(false);

  // Logs state
  const [logs, setLogs] = useState<WebhookLog[]>([]);
  const [logsLoading, setLogsLoading] = useState(false);
  const [logsWebhookUrl, setLogsWebhookUrl] = useState('');
  const [expandedLogId, setExpandedLogId] = useState<number | null>(null);
  const [retryingLogId, setRetryingLogId] = useState<number | null>(null);

  // AbortController for cancelling in-flight requests
  const abortControllerRef = useRef<AbortController | null>(null);

  // ─── Load data ───
  const loadData = useCallback(async () => {
    if (abortControllerRef.current) abortControllerRef.current.abort();
    const controller = new AbortController();
    abortControllerRef.current = controller;

    setLoading(true);
    try {
      const res = await api.get('/v2/admin/federation/webhooks', { signal: controller.signal });
      if (res.success) {
        const payload = res.data;
        setWebhooks(Array.isArray(payload) ? payload : (payload as { data?: WebhookItem[] })?.data ?? []);
      }
    } catch (err) {
      if (err instanceof DOMException && err.name === 'AbortError') return;
      logError('Webhooks.load', err);
      toast.error(t('federation.webhooks_load_failed', 'Failed to load webhooks'));
    }
    setLoading(false);
  }, [toast, t]);

  useEffect(() => {
    loadData();
    return () => { if (abortControllerRef.current) abortControllerRef.current.abort(); };
  }, [loadData]);

  // ─── Open create modal ───
  const openCreate = useCallback(() => {
    setEditingId(null);
    setForm({ ...EMPTY_FORM });
    setCreatedSecret(null);
    formModal.onOpen();
  }, [formModal]);

  // ─── Open edit modal ───
  const openEdit = useCallback((webhook: WebhookItem) => {
    setEditingId(webhook.id);
    setForm({
      url: webhook.url,
      description: webhook.description || '',
      events: webhook.events || [],
    });
    setCreatedSecret(null);
    formModal.onOpen();
  }, [formModal]);

  // ─── Save (create or update) ───
  const handleSave = useCallback(async () => {
    if (!form.url.trim()) {
      toast.error(t('federation.webhooks_url_required', 'Webhook URL is required'));
      return;
    }
    if (!form.url.startsWith('https://')) {
      toast.error(t('federation.webhooks_https_required', 'URL must use HTTPS'));
      return;
    }
    if (form.events.length === 0) {
      toast.error(t('federation.webhooks_events_required', 'Select at least one event'));
      return;
    }

    setSaving(true);
    try {
      const payload: Record<string, unknown> = {
        url: form.url,
        description: form.description || null,
        events: form.events,
      };

      let res;
      if (editingId) {
        res = await api.put(`/v2/admin/federation/webhooks/${editingId}`, payload);
      } else {
        res = await api.post('/v2/admin/federation/webhooks', payload);
      }

      if (res.success) {
        if (!editingId) {
          // Show the generated secret on create
          const data = res.data as { secret?: string };
          if (data?.secret) {
            setCreatedSecret(data.secret);
            toast.success(t('federation.webhooks_created', 'Webhook created. Copy your signing secret now — it will not be shown again.'));
          } else {
            formModal.onClose();
            toast.success(t('federation.webhooks_created_no_secret', 'Webhook created successfully'));
          }
        } else {
          formModal.onClose();
          toast.success(t('federation.webhooks_updated', 'Webhook updated successfully'));
        }
        loadData();
      } else {
        const errorMsg = (res as { error?: string }).error || 'Failed to save webhook';
        toast.error(errorMsg);
      }
    } catch (err) {
      logError('Webhooks.save', err);
      toast.error(t('federation.webhooks_save_failed', 'Failed to save webhook'));
    }
    setSaving(false);
  }, [form, editingId, toast, t, formModal, loadData]);

  // ─── Delete ───
  const handleDelete = useCallback(async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      const res = await api.delete(`/v2/admin/federation/webhooks/${deleteTarget.id}`);
      if (res.success) {
        toast.success(t('federation.webhooks_deleted', 'Webhook deleted'));
        setDeleteTarget(null);
        loadData();
      } else {
        toast.error(t('federation.webhooks_delete_failed', 'Failed to delete webhook'));
      }
    } catch (err) {
      logError('Webhooks.delete', err);
      toast.error(t('federation.webhooks_delete_failed', 'Failed to delete webhook'));
    }
    setDeleting(false);
  }, [deleteTarget, toast, t, loadData]);

  // ─── Test webhook ───
  const handleTest = useCallback(async (webhook: WebhookItem) => {
    setTestingId(webhook.id);
    try {
      const res = await api.post(`/v2/admin/federation/webhooks/${webhook.id}/test`, {});
      if (res.success) {
        const data = res.data as { response_time_ms?: number; response_code?: number };
        toast.success(
          t('federation.webhooks_test_success', 'Test delivery successful ({{code}}, {{time}}ms)', {
            code: data?.response_code ?? '?',
            time: data?.response_time_ms ?? '?',
          })
        );
        loadData();
      } else {
        const errorMsg = (res as { error?: string }).error || 'Test delivery failed';
        toast.error(errorMsg);
      }
    } catch (err) {
      logError('Webhooks.test', err);
      toast.error(t('federation.webhooks_test_failed', 'Test delivery failed'));
    }
    setTestingId(null);
  }, [toast, t, loadData]);

  // ─── View logs ───
  const handleViewLogs = useCallback(async (webhook: WebhookItem) => {
    setLogsWebhookUrl(webhook.url);
    setLogsLoading(true);
    setLogs([]);
    setExpandedLogId(null);
    logsModal.onOpen();

    try {
      const res = await api.get(`/v2/admin/federation/webhooks/${webhook.id}/logs`);
      if (res.success) {
        const payload = res.data;
        setLogs(Array.isArray(payload) ? payload : (payload as { data?: WebhookLog[] })?.data ?? []);
      }
    } catch (err) {
      logError('Webhooks.logs', err);
      toast.error(t('federation.webhooks_logs_failed', 'Failed to load delivery logs'));
    }
    setLogsLoading(false);
  }, [toast, t, logsModal]);

  // ─── Retry failed delivery ───
  const handleRetry = useCallback(async (logEntry: WebhookLog) => {
    setRetryingLogId(logEntry.id);
    try {
      const res = await api.post(`/v2/admin/federation/webhook-logs/${logEntry.id}/retry`, {});
      if (res.success) {
        const data = res.data as { success?: boolean; response_code?: number; response_time_ms?: number; error_message?: string };
        if (data?.success) {
          toast.success(t('federation.webhooks_retry_success', 'Retry successful ({{code}}, {{time}}ms)', {
            code: data?.response_code ?? '?',
            time: data?.response_time_ms ?? '?',
          }));
        } else {
          toast.error(data?.error_message || t('federation.webhooks_retry_failed', 'Retry failed'));
        }
        // Reload logs by re-fetching from the webhook
        const webhook = webhooks.find(w => w.id === logEntry.webhook_id);
        if (webhook) {
          const logRes = await api.get(`/v2/admin/federation/webhooks/${webhook.id}/logs`);
          if (logRes.success) {
            const payload = logRes.data;
            setLogs(Array.isArray(payload) ? payload : (payload as { data?: WebhookLog[] })?.data ?? []);
          }
        }
      } else {
        toast.error(t('federation.webhooks_retry_failed', 'Retry failed'));
      }
    } catch (err) {
      logError('Webhooks.retry', err);
      toast.error(t('federation.webhooks_retry_failed', 'Retry failed'));
    }
    setRetryingLogId(null);
  }, [toast, t, webhooks]);

  // ─── Truncate URL for display ───
  const truncateUrl = (url: string, max = 45) => {
    if (url.length <= max) return url;
    return url.substring(0, max) + '...';
  };

  // ─── Render ───
  if (loading) {
    return (
      <div>
        <PageHeader
          title={t('federation.webhooks_title', 'Webhooks')}
          description={t('federation.webhooks_desc', 'Manage webhook subscriptions for federation events')}
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
        title={t('federation.webhooks_title', 'Webhooks')}
        description={t('federation.webhooks_desc', 'Manage webhook subscriptions for federation events')}
        actions={
          <div className="flex items-center gap-2">
            <Button variant="flat" size="sm" startContent={<RefreshCw size={16} />} onPress={() => loadData()} isLoading={loading}>
              {t('federation.refresh', 'Refresh')}
            </Button>
            <Button color="primary" size="sm" startContent={<Plus size={16} />} onPress={openCreate}>
              {t('federation.webhooks_add', 'Add Webhook')}
            </Button>
          </div>
        }
      />

      {/* Webhooks table */}
      <Table aria-label={t('federation.webhooks_title', 'Webhooks')} removeWrapper>
        <TableHeader>
          <TableColumn>{t('federation.webhooks_col_url', 'URL')}</TableColumn>
          <TableColumn>{t('federation.webhooks_col_events', 'Events')}</TableColumn>
          <TableColumn>{t('federation.col_status', 'Status')}</TableColumn>
          <TableColumn>{t('federation.webhooks_col_last_triggered', 'Last Triggered')}</TableColumn>
          <TableColumn>{t('federation.webhooks_col_failures', 'Failures')}</TableColumn>
          <TableColumn>{t('federation.col_actions', 'Actions')}</TableColumn>
        </TableHeader>
        <TableBody emptyContent={t('federation.webhooks_empty', 'No webhooks configured')}>
          {webhooks.map((webhook) => (
            <TableRow key={webhook.id}>
              <TableCell>
                <div>
                  <Tooltip content={webhook.url}>
                    <code className="text-xs bg-default-100 px-1.5 py-0.5 rounded cursor-help">
                      {truncateUrl(webhook.url)}
                    </code>
                  </Tooltip>
                  {webhook.description && (
                    <p className="text-xs text-default-400 mt-0.5 truncate max-w-[250px]">{webhook.description}</p>
                  )}
                </div>
              </TableCell>
              <TableCell>
                <div className="flex flex-wrap gap-1 max-w-[200px]">
                  {(webhook.events || []).slice(0, 3).map((evt) => (
                    <Chip key={evt} size="sm" variant="flat" className="text-xs">{evt.split('.')[1]}</Chip>
                  ))}
                  {(webhook.events || []).length > 3 && (
                    <Chip size="sm" variant="flat" className="text-xs">+{webhook.events.length - 3}</Chip>
                  )}
                </div>
              </TableCell>
              <TableCell>
                <Chip
                  size="sm"
                  variant="flat"
                  color={STATUS_COLORS[webhook.status] ?? 'default'}
                  className="capitalize"
                >
                  {webhook.status}
                </Chip>
              </TableCell>
              <TableCell>
                <span className="text-sm text-default-500">
                  {webhook.last_triggered_at ? formatRelativeTime(webhook.last_triggered_at) : t('federation.never', 'Never')}
                </span>
              </TableCell>
              <TableCell>
                <span className={`text-sm ${webhook.consecutive_failures > 0 ? 'text-danger font-medium' : 'text-default-400'}`}>
                  {webhook.consecutive_failures}
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
                    aria-label={t('federation.webhooks_actions', 'Webhook actions')}
                    onAction={(key) => {
                      if (key === 'edit') openEdit(webhook);
                      else if (key === 'test') handleTest(webhook);
                      else if (key === 'logs') handleViewLogs(webhook);
                      else if (key === 'delete') setDeleteTarget(webhook);
                    }}
                  >
                    <DropdownItem key="edit" startContent={<Pencil size={14} />}>
                      {t('federation.edit', 'Edit')}
                    </DropdownItem>
                    <DropdownItem key="test" startContent={testingId === webhook.id ? <Spinner size="sm" /> : <Send size={14} />}>
                      {t('federation.webhooks_test', 'Test')}
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
                <Webhook size={20} />
                {editingId
                  ? t('federation.webhooks_edit', 'Edit Webhook')
                  : t('federation.webhooks_add', 'Add Webhook')}
              </ModalHeader>
              <ModalBody className="gap-4">
                {/* Show secret after creation */}
                {createdSecret && (
                  <div className="rounded-lg border border-warning-200 bg-warning-50 p-4 space-y-2">
                    <p className="text-sm font-semibold text-warning-700">
                      {t('federation.webhooks_secret_warning', 'Save your signing secret now. It will not be shown again.')}
                    </p>
                    <Snippet
                      symbol=""
                      variant="flat"
                      color="warning"
                      className="w-full"
                    >
                      {createdSecret}
                    </Snippet>
                  </div>
                )}

                <Input
                  label={t('federation.webhooks_label_url', 'Webhook URL')}
                  placeholder="https://example.com/webhooks/nexus"
                  value={form.url}
                  onValueChange={(v) => setForm((prev) => ({ ...prev, url: v }))}
                  isRequired
                  isDisabled={!!createdSecret}
                  description={t('federation.webhooks_url_hint', 'Must use HTTPS')}
                />

                <Textarea
                  label={t('federation.label_description', 'Description')}
                  placeholder={t('federation.webhooks_desc_placeholder', 'Optional description for this webhook')}
                  value={form.description}
                  onValueChange={(v) => setForm((prev) => ({ ...prev, description: v }))}
                  minRows={2}
                  isDisabled={!!createdSecret}
                />

                {/* Event checkboxes */}
                <div className="space-y-3">
                  <p className="text-sm font-semibold text-default-700">
                    {t('federation.webhooks_events_label', 'Events to subscribe to')}
                  </p>
                  <CheckboxGroup
                    value={form.events}
                    onValueChange={(v) => setForm((prev) => ({ ...prev, events: v }))}
                    isDisabled={!!createdSecret}
                  >
                    <div className="grid grid-cols-2 gap-2">
                      {ALL_EVENTS.map((evt) => (
                        <Checkbox key={evt.key} value={evt.key} size="sm">
                          {evt.label}
                        </Checkbox>
                      ))}
                    </div>
                  </CheckboxGroup>
                  <div className="flex gap-2">
                    <Button
                      size="sm"
                      variant="flat"
                      onPress={() => setForm((prev) => ({ ...prev, events: ALL_EVENT_KEYS }))}
                      isDisabled={!!createdSecret}
                    >
                      {t('federation.webhooks_select_all', 'Select All')}
                    </Button>
                    <Button
                      size="sm"
                      variant="flat"
                      onPress={() => setForm((prev) => ({ ...prev, events: [] }))}
                      isDisabled={!!createdSecret}
                    >
                      {t('federation.webhooks_clear_all', 'Clear All')}
                    </Button>
                  </div>
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  {createdSecret ? t('federation.close', 'Close') : t('federation.cancel', 'Cancel')}
                </Button>
                {!createdSecret && (
                  <Button
                    color="primary"
                    isLoading={saving}
                    isDisabled={!form.url.trim() || form.events.length === 0}
                    onPress={handleSave}
                  >
                    {editingId
                      ? t('federation.save_changes', 'Save Changes')
                      : t('federation.webhooks_create', 'Create Webhook')}
                  </Button>
                )}
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Logs Modal (Drawer-style) */}
      <Modal isOpen={logsModal.isOpen} onOpenChange={logsModal.onOpenChange} size="4xl" scrollBehavior="inside">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex items-center gap-2">
                <ScrollText size={20} />
                {t('federation.webhooks_logs_title', 'Delivery Logs')}
                <code className="text-xs bg-default-100 px-1.5 py-0.5 rounded ml-2">{truncateUrl(logsWebhookUrl, 60)}</code>
              </ModalHeader>
              <ModalBody>
                {logsLoading ? (
                  <div className="flex h-48 items-center justify-center">
                    <Spinner size="lg" />
                  </div>
                ) : logs.length === 0 ? (
                  <div className="flex h-48 items-center justify-center text-default-400">
                    {t('federation.webhooks_no_logs', 'No delivery logs found')}
                  </div>
                ) : (
                  <div className="space-y-0">
                    {logs.map((log) => (
                      <div key={log.id}>
                        {/* Log row */}
                        <div className="grid grid-cols-[30px_1fr_60px_60px_80px_70px_120px_50px] items-center gap-2 py-2 border-b border-default-100 text-sm">
                          <Button
                            isIconOnly
                            size="sm"
                            variant="light"
                            onPress={() => setExpandedLogId(expandedLogId === log.id ? null : log.id)}
                            aria-label={t('federation.webhooks_expand', 'Toggle details')}
                          >
                            {expandedLogId === log.id
                              ? <ChevronDown size={14} className="text-default-400" />
                              : <ChevronRight size={14} className="text-default-400" />
                            }
                          </Button>
                          <div>
                            <Chip size="sm" variant="flat">{log.event_type}</Chip>
                          </div>
                          <div>
                            {log.success ? (
                              <Check size={16} className="text-success" />
                            ) : (
                              <X size={16} className="text-danger" />
                            )}
                          </div>
                          <div>
                            <span className={`text-sm ${log.response_code && log.response_code >= 200 && log.response_code < 300 ? 'text-success' : log.response_code ? 'text-danger' : 'text-default-400'}`}>
                              {log.response_code ?? '--'}
                            </span>
                          </div>
                          <div>
                            <span className="text-sm text-default-500">
                              {log.response_time_ms != null ? `${log.response_time_ms}ms` : '--'}
                            </span>
                          </div>
                          <div>
                            <span className="text-sm text-default-500">#{log.attempt_number}</span>
                          </div>
                          <div>
                            <span className="text-sm text-default-400">
                              {log.created_at ? formatRelativeTime(log.created_at) : '--'}
                            </span>
                          </div>
                          <div>
                            {!log.success && (
                              <Tooltip content={t('federation.webhooks_retry', 'Retry delivery')}>
                                <Button
                                  isIconOnly
                                  size="sm"
                                  variant="light"
                                  isLoading={retryingLogId === log.id}
                                  onPress={() => handleRetry(log)}
                                  aria-label={t('federation.webhooks_retry', 'Retry delivery')}
                                >
                                  <RotateCcw size={14} />
                                </Button>
                              </Tooltip>
                            )}
                          </div>
                        </div>

                        {/* Inline expanded detail */}
                        {expandedLogId === log.id && (
                          <div className="rounded-lg border border-default-200 p-4 space-y-3 bg-default-50 my-2">
                            {log.error_message && (
                              <div>
                                <p className="text-xs font-semibold text-danger mb-1">{t('federation.webhooks_error', 'Error')}</p>
                                <p className="text-sm text-danger">{log.error_message}</p>
                              </div>
                            )}
                            <div>
                              <p className="text-xs font-semibold text-default-500 mb-1">{t('federation.webhooks_payload', 'Payload')}</p>
                              <pre className="text-xs bg-default-100 rounded p-2 overflow-x-auto max-h-48">
                                {JSON.stringify(log.payload, null, 2)}
                              </pre>
                            </div>
                            {log.response_body && (
                              <div>
                                <p className="text-xs font-semibold text-default-500 mb-1">{t('federation.webhooks_response', 'Response Body')}</p>
                                <pre className="text-xs bg-default-100 rounded p-2 overflow-x-auto max-h-48">
                                  {log.response_body}
                                </pre>
                              </div>
                            )}
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
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
          title={t('federation.webhooks_delete_title', 'Delete Webhook')}
          message={t('federation.webhooks_delete_confirm', {
            url: deleteTarget.url,
          })}
          confirmLabel={t('federation.delete', 'Delete')}
          confirmColor="danger"
          isLoading={deleting}
        />
      )}
    </div>
  );
}

export default Webhooks;
