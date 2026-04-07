// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Volunteer Configuration
 * Tabbed settings page: Custom Fields, Reminder Settings, Webhooks.
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Button,
  Chip,
  Input,
  Textarea,
  Select,
  SelectItem,
  Switch,
  Tab,
  Tabs,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
  Card,
  CardBody,
} from '@heroui/react';
import {
  RefreshCw,
  Plus,
  Edit2,
  Trash2,
  Save,
  Webhook,
  Bell,
  FormInput,
  Play,
  FileText,
  CheckCircle,
  XCircle,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, ConfirmModal, type Column } from '../../components';
import { useTranslation } from 'react-i18next';

// ─── Custom Fields types ───────────────────────────────────────────────────────

interface CustomField {
  id: number;
  label: string;
  field_type: string;
  applies_to: string;
  is_required: boolean;
  options: string[] | null;
}

const fieldTypeOptions = [
  'text', 'textarea', 'select', 'checkbox', 'radio', 'date', 'file', 'number', 'email', 'phone',
];

const appliesToOptions = ['application', 'opportunity', 'shift', 'profile'];

const emptyFieldForm = {
  label: '',
  field_type: 'text',
  applies_to: 'application',
  is_required: false,
  options: '',
};

// ─── Reminder types ────────────────────────────────────────────────────────────

interface ReminderSetting {
  key: string;
  label: string;
  enabled: boolean;
  timing_value: number;
  timing_unit: string;
  email_enabled: boolean;
  push_enabled: boolean;
  sms_enabled: boolean;
}

// ─── Webhook types ─────────────────────────────────────────────────────────────

interface WebhookEntry {
  id: number;
  name: string;
  url: string;
  events: string[];
  is_active: boolean;
  failure_count: number;
  created_at: string;
}

interface WebhookLog {
  id: number;
  webhook_id: number;
  event: string;
  status_code: number;
  response_body: string;
  created_at: string;
}

const emptyWebhookForm = {
  name: '',
  url: '',
  events: '',
  is_active: true,
};

// ─── Main component ────────────────────────────────────────────────────────────

export default function VolunteerConfig() {
  const { t } = useTranslation('admin');
  usePageTitle(t('volunteering.config_title', 'Volunteering Settings'));

  const [activeTab, setActiveTab] = useState('custom-fields');

  return (
    <div>
      <PageHeader
        title={t('volunteering.config_title', 'Volunteering Settings')}
        description={t('volunteering.config_desc', 'Configure custom fields, reminders, and webhooks for the volunteering module')}
      />

      <Tabs
        selectedKey={activeTab}
        onSelectionChange={(key) => setActiveTab(String(key))}
        variant="underlined"
        classNames={{ tabList: 'mb-6' }}
      >
        <Tab
          key="custom-fields"
          title={
            <div className="flex items-center gap-2">
              <FormInput size={16} />
              {t('volunteering.tab_custom_fields', 'Custom Fields')}
            </div>
          }
        >
          <CustomFieldsTab />
        </Tab>
        <Tab
          key="reminders"
          title={
            <div className="flex items-center gap-2">
              <Bell size={16} />
              {t('volunteering.tab_reminders', 'Reminders')}
            </div>
          }
        >
          <RemindersTab />
        </Tab>
        <Tab
          key="webhooks"
          title={
            <div className="flex items-center gap-2">
              <Webhook size={16} />
              {t('volunteering.tab_webhooks', 'Webhooks')}
            </div>
          }
        >
          <WebhooksTab />
        </Tab>
      </Tabs>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Tab 1: Custom Fields
// ─────────────────────────────────────────────────────────────────────────────

function CustomFieldsTab() {
  const { t } = useTranslation('admin');
  const toast = useToast();

  const [fields, setFields] = useState<CustomField[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState(emptyFieldForm);
  const [deleteId, setDeleteId] = useState<number | null>(null);

  const { isOpen, onOpen, onClose } = useDisclosure();
  const { isOpen: isDeleteOpen, onOpen: onDeleteOpen, onClose: onDeleteClose } = useDisclosure();

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getCustomFields();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setFields(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setFields((payload as { data: CustomField[] }).data || []);
        }
      }
    } catch {
      toast.error(t('volunteering.failed_to_load_fields', 'Failed to load custom fields'));
      setFields([]);
    }
    setLoading(false);
  }, [toast, t]);

  useEffect(() => { loadData(); }, [loadData]);

  const openCreate = () => {
    setEditingId(null);
    setForm(emptyFieldForm);
    onOpen();
  };

  const openEdit = (field: CustomField) => {
    setEditingId(field.id);
    setForm({
      label: field.label,
      field_type: field.field_type,
      applies_to: field.applies_to,
      is_required: field.is_required,
      options: field.options?.join(', ') || '',
    });
    onOpen();
  };

  const handleSave = async () => {
    if (!form.label.trim()) {
      toast.error(t('volunteering.label_required', 'Label is required'));
      return;
    }
    setSaving(true);
    try {
      const payload = {
        label: form.label.trim(),
        field_type: form.field_type,
        applies_to: form.applies_to,
        is_required: form.is_required,
        options: form.options.trim()
          ? form.options.split(',').map((o) => o.trim()).filter(Boolean)
          : null,
      };
      if (editingId) {
        await adminVolunteering.updateCustomField(editingId, payload);
        toast.success(t('volunteering.field_updated', 'Custom field updated'));
      } else {
        await adminVolunteering.createCustomField(payload);
        toast.success(t('volunteering.field_created', 'Custom field created'));
      }
      onClose();
      loadData();
    } catch {
      toast.error(t('volunteering.failed_to_save_field', 'Failed to save custom field'));
    }
    setSaving(false);
  };

  const handleDelete = async () => {
    if (!deleteId) return;
    try {
      await adminVolunteering.deleteCustomField(deleteId);
      toast.success(t('volunteering.field_deleted', 'Custom field deleted'));
      onDeleteClose();
      loadData();
    } catch {
      toast.error(t('volunteering.failed_to_delete_field', 'Failed to delete custom field'));
    }
  };

  const columns: Column<CustomField>[] = [
    { key: 'label', label: t('volunteering.col_label', 'Label'), sortable: true },
    {
      key: 'field_type',
      label: t('volunteering.col_field_type', 'Field Type'),
      render: (row) => <Chip size="sm" variant="flat">{row.field_type}</Chip>,
    },
    {
      key: 'applies_to',
      label: t('volunteering.col_applies_to', 'Applies To'),
      render: (row) => <Chip size="sm" variant="flat" color="primary">{row.applies_to}</Chip>,
    },
    {
      key: 'is_required',
      label: t('volunteering.col_required', 'Required'),
      render: (row) => row.is_required
        ? <CheckCircle size={16} className="text-success" />
        : <XCircle size={16} className="text-default-400" />,
    },
    {
      key: 'options',
      label: t('volunteering.col_options', 'Options'),
      render: (row) => (
        <span className="text-sm text-default-500">
          {row.options?.length ? row.options.join(', ') : '-'}
        </span>
      ),
    },
    {
      key: 'actions' as keyof CustomField,
      label: t('common.actions', 'Actions'),
      render: (row) => (
        <div className="flex items-center gap-1">
          <Button size="sm" variant="flat" isIconOnly onPress={() => openEdit(row)} aria-label="Edit">
            <Edit2 size={14} />
          </Button>
          <Button
            size="sm"
            variant="flat"
            color="danger"
            isIconOnly
            onPress={() => { setDeleteId(row.id); onDeleteOpen(); }}
            aria-label="Delete"
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <div className="flex justify-between items-center mb-4">
        <h3 className="text-lg font-semibold">{t('volunteering.custom_fields_heading', 'Custom Fields')}</h3>
        <div className="flex gap-2">
          <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>
            {t('common.refresh', 'Refresh')}
          </Button>
          <Button color="primary" startContent={<Plus size={16} />} onPress={openCreate}>
            {t('volunteering.add_field', 'Add Field')}
          </Button>
        </div>
      </div>

      {fields.length === 0 && !loading ? (
        <EmptyState
          icon={FormInput}
          title={t('volunteering.no_custom_fields', 'No custom fields')}
          description={t('volunteering.no_custom_fields_desc', 'Add custom fields to collect additional data from volunteers.')}
        />
      ) : (
        <DataTable columns={columns} data={fields} isLoading={loading} />
      )}

      {/* Create/Edit Modal */}
      <Modal isOpen={isOpen} onClose={onClose} size="lg">
        <ModalContent>
          <ModalHeader>
            {editingId
              ? t('volunteering.edit_field', 'Edit Custom Field')
              : t('volunteering.add_field', 'Add Custom Field')}
          </ModalHeader>
          <ModalBody>
            <div className="flex flex-col gap-4">
              <Input
                label={t('volunteering.field_label', 'Label')}
                value={form.label}
                onValueChange={(v) => setForm((f) => ({ ...f, label: v }))}
                isRequired
                variant="bordered"
              />
              <Select
                label={t('volunteering.field_type_label', 'Field Type')}
                selectedKeys={[form.field_type]}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0];
                  if (typeof selected === 'string') setForm((f) => ({ ...f, field_type: selected }));
                }}
                variant="bordered"
              >
                {fieldTypeOptions.map((ft) => (
                  <SelectItem key={ft}>{ft}</SelectItem>
                ))}
              </Select>
              <Select
                label={t('volunteering.field_applies_to', 'Applies To')}
                selectedKeys={[form.applies_to]}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0];
                  if (typeof selected === 'string') setForm((f) => ({ ...f, applies_to: selected }));
                }}
                variant="bordered"
              >
                {appliesToOptions.map((at) => (
                  <SelectItem key={at}>{at}</SelectItem>
                ))}
              </Select>
              <Switch
                isSelected={form.is_required}
                onValueChange={(v) => setForm((f) => ({ ...f, is_required: v }))}
              >
                {t('volunteering.field_required', 'Required')}
              </Switch>
              <Input
                label={t('volunteering.field_options_label', 'Options (comma-separated)')}
                value={form.options}
                onValueChange={(v) => setForm((f) => ({ ...f, options: v }))}
                variant="bordered"
                description={t('volunteering.field_options_desc', 'Only for select, radio, and checkbox types')}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose}>{t('common.cancel', 'Cancel')}</Button>
            <Button color="primary" onPress={handleSave} isLoading={saving}>
              {editingId ? t('common.save', 'Save') : t('common.create', 'Create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Confirm */}
      <ConfirmModal
        isOpen={isDeleteOpen}
        onClose={onDeleteClose}
        onConfirm={handleDelete}
        title={t('volunteering.delete_field_title', 'Delete Custom Field')}
        message={t('volunteering.delete_field_confirm', 'Are you sure you want to delete this custom field? This action cannot be undone.')}
        confirmLabel={t('common.delete', 'Delete')}
        confirmColor="danger"
      />
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Tab 2: Reminder Settings
// ─────────────────────────────────────────────────────────────────────────────

const defaultReminders: ReminderSetting[] = [
  { key: 'pre_shift', label: 'Pre-shift Reminder', enabled: true, timing_value: 24, timing_unit: 'hours before', email_enabled: true, push_enabled: true, sms_enabled: false },
  { key: 'post_shift_feedback', label: 'Post-shift Feedback', enabled: true, timing_value: 2, timing_unit: 'hours after', email_enabled: true, push_enabled: true, sms_enabled: false },
  { key: 'lapsed_volunteer', label: 'Lapsed Volunteer', enabled: true, timing_value: 30, timing_unit: 'days inactive', email_enabled: true, push_enabled: false, sms_enabled: false },
  { key: 'credential_expiry', label: 'Credential Expiry', enabled: true, timing_value: 14, timing_unit: 'days before', email_enabled: true, push_enabled: true, sms_enabled: false },
  { key: 'training_expiry', label: 'Training Expiry', enabled: true, timing_value: 14, timing_unit: 'days before', email_enabled: true, push_enabled: true, sms_enabled: false },
];

function RemindersTab() {
  const { t } = useTranslation('admin');
  const toast = useToast();

  const [reminders, setReminders] = useState<ReminderSetting[]>(defaultReminders);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getReminderSettings();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        let data: ReminderSetting[];
        if (Array.isArray(payload)) {
          data = payload;
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          data = (payload as { data: ReminderSetting[] }).data || [];
        } else {
          data = [];
        }
        if (data.length > 0) setReminders(data);
      }
    } catch {
      // Use defaults on failure
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const updateReminder = (index: number, updates: Partial<ReminderSetting>) => {
    setReminders((prev) => prev.map((r, i) => (i === index ? { ...r, ...updates } : r)));
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      await adminVolunteering.updateReminderSettings({ reminders });
      toast.success(t('volunteering.reminders_saved', 'Reminder settings saved'));
    } catch {
      toast.error(t('volunteering.failed_to_save_reminders', 'Failed to save reminder settings'));
    }
    setSaving(false);
  };

  return (
    <div>
      <div className="flex justify-between items-center mb-4">
        <h3 className="text-lg font-semibold">{t('volunteering.reminders_heading', 'Reminder Settings')}</h3>
        <div className="flex gap-2">
          <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>
            {t('common.refresh', 'Refresh')}
          </Button>
          <Button color="primary" startContent={<Save size={16} />} onPress={handleSave} isLoading={saving}>
            {t('common.save', 'Save')}
          </Button>
        </div>
      </div>

      <div className="flex flex-col gap-4">
        {reminders.map((reminder, index) => (
          <Card key={reminder.key} className="p-0">
            <CardBody className="p-4">
              <div className="flex flex-col gap-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <Switch
                      isSelected={reminder.enabled}
                      onValueChange={(v) => updateReminder(index, { enabled: v })}
                    />
                    <span className="font-medium">
                      {t(`volunteering.reminder_${reminder.key}`, reminder.label)}
                    </span>
                  </div>
                  <Chip size="sm" variant="flat" color={reminder.enabled ? 'success' : 'default'}>
                    {reminder.enabled
                      ? t('volunteering.enabled', 'Enabled')
                      : t('volunteering.disabled', 'Disabled')}
                  </Chip>
                </div>

                {reminder.enabled && (
                  <div className="flex flex-wrap items-center gap-4 ml-12">
                    <Input
                      type="number"
                      label={t('volunteering.timing_value', 'Timing')}
                      value={String(reminder.timing_value)}
                      onValueChange={(v) => updateReminder(index, { timing_value: Number(v) || 0 })}
                      variant="bordered"
                      className="w-28"
                      size="sm"
                      endContent={
                        <span className="text-xs text-default-400 whitespace-nowrap">
                          {reminder.timing_unit}
                        </span>
                      }
                    />
                    <div className="flex items-center gap-3">
                      <Switch
                        size="sm"
                        isSelected={reminder.email_enabled}
                        onValueChange={(v) => updateReminder(index, { email_enabled: v })}
                      >
                        {t('volunteering.channel_email', 'Email')}
                      </Switch>
                      <Switch
                        size="sm"
                        isSelected={reminder.push_enabled}
                        onValueChange={(v) => updateReminder(index, { push_enabled: v })}
                      >
                        {t('volunteering.channel_push', 'Push')}
                      </Switch>
                      <Switch
                        size="sm"
                        isSelected={reminder.sms_enabled}
                        onValueChange={(v) => updateReminder(index, { sms_enabled: v })}
                      >
                        {t('volunteering.channel_sms', 'SMS')}
                      </Switch>
                    </div>
                  </div>
                )}
              </div>
            </CardBody>
          </Card>
        ))}
      </div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Tab 3: Webhooks
// ─────────────────────────────────────────────────────────────────────────────

function WebhooksTab() {
  const { t } = useTranslation('admin');
  const toast = useToast();

  const [webhooks, setWebhooks] = useState<WebhookEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState(emptyWebhookForm);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [testingId, setTestingId] = useState<number | null>(null);
  const [logs, setLogs] = useState<WebhookLog[]>([]);
  const [, setLogsWebhookId] = useState<number | null>(null);

  const { isOpen, onOpen, onClose } = useDisclosure();
  const { isOpen: isDeleteOpen, onOpen: onDeleteOpen, onClose: onDeleteClose } = useDisclosure();
  const { isOpen: isLogsOpen, onOpen: onLogsOpen, onClose: onLogsClose } = useDisclosure();

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getWebhooks();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setWebhooks(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setWebhooks((payload as { data: WebhookEntry[] }).data || []);
        }
      }
    } catch {
      toast.error(t('volunteering.failed_to_load_webhooks', 'Failed to load webhooks'));
      setWebhooks([]);
    }
    setLoading(false);
  }, [toast, t]);

  useEffect(() => { loadData(); }, [loadData]);

  const openCreate = () => {
    setEditingId(null);
    setForm(emptyWebhookForm);
    onOpen();
  };

  const openEdit = (wh: WebhookEntry) => {
    setEditingId(wh.id);
    setForm({
      name: wh.name,
      url: wh.url,
      events: wh.events?.join(', ') || '',
      is_active: wh.is_active,
    });
    onOpen();
  };

  const handleSave = async () => {
    if (!form.name.trim() || !form.url.trim()) {
      toast.error(t('volunteering.webhook_name_url_required', 'Name and URL are required'));
      return;
    }
    setSaving(true);
    try {
      const payload = {
        name: form.name.trim(),
        url: form.url.trim(),
        events: form.events.split(',').map((e) => e.trim()).filter(Boolean),
        is_active: form.is_active,
      };
      if (editingId) {
        await adminVolunteering.updateWebhook(editingId, payload);
        toast.success(t('volunteering.webhook_updated', 'Webhook updated'));
      } else {
        await adminVolunteering.createWebhook(payload);
        toast.success(t('volunteering.webhook_created', 'Webhook created'));
      }
      onClose();
      loadData();
    } catch {
      toast.error(t('volunteering.failed_to_save_webhook', 'Failed to save webhook'));
    }
    setSaving(false);
  };

  const handleDelete = async () => {
    if (!deleteId) return;
    try {
      await adminVolunteering.deleteWebhook(deleteId);
      toast.success(t('volunteering.webhook_deleted', 'Webhook deleted'));
      onDeleteClose();
      loadData();
    } catch {
      toast.error(t('volunteering.failed_to_delete_webhook', 'Failed to delete webhook'));
    }
  };

  const handleTest = async (id: number) => {
    setTestingId(id);
    try {
      await adminVolunteering.testWebhook(id);
      toast.success(t('volunteering.webhook_test_sent', 'Test webhook dispatched'));
    } catch {
      toast.error(t('volunteering.webhook_test_failed', 'Webhook test failed'));
    }
    setTestingId(null);
  };

  const handleViewLogs = async (id: number) => {
    setLogsWebhookId(id);
    try {
      const res = await adminVolunteering.getWebhookLogs(id);
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setLogs(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setLogs((payload as { data: WebhookLog[] }).data || []);
        }
      }
    } catch {
      toast.error(t('volunteering.failed_to_load_logs', 'Failed to load webhook logs'));
      setLogs([]);
    }
    onLogsOpen();
  };

  const columns: Column<WebhookEntry>[] = [
    { key: 'name', label: t('volunteering.col_name', 'Name'), sortable: true },
    {
      key: 'url',
      label: t('volunteering.col_url', 'URL'),
      render: (row) => (
        <span className="text-sm font-mono truncate max-w-[250px] block">{row.url}</span>
      ),
    },
    {
      key: 'events',
      label: t('volunteering.col_events', 'Events'),
      render: (row) => (
        <div className="flex flex-wrap gap-1">
          {row.events?.map((ev) => (
            <Chip key={ev} size="sm" variant="flat">{ev}</Chip>
          ))}
        </div>
      ),
    },
    {
      key: 'is_active',
      label: t('volunteering.col_active', 'Active'),
      render: (row) => (
        <Chip size="sm" color={row.is_active ? 'success' : 'default'} variant="flat">
          {row.is_active ? t('volunteering.active', 'Active') : t('volunteering.inactive', 'Inactive')}
        </Chip>
      ),
    },
    {
      key: 'failure_count',
      label: t('volunteering.col_failures', 'Failures'),
      render: (row) => (
        <Chip size="sm" color={row.failure_count > 0 ? 'danger' : 'default'} variant="flat">
          {row.failure_count}
        </Chip>
      ),
    },
    {
      key: 'actions' as keyof WebhookEntry,
      label: t('common.actions', 'Actions'),
      render: (row) => (
        <div className="flex items-center gap-1">
          <Button size="sm" variant="flat" isIconOnly onPress={() => openEdit(row)} aria-label="Edit">
            <Edit2 size={14} />
          </Button>
          <Button
            size="sm"
            variant="flat"
            color="primary"
            isIconOnly
            isLoading={testingId === row.id}
            onPress={() => handleTest(row.id)}
            aria-label="Test"
          >
            <Play size={14} />
          </Button>
          <Button
            size="sm"
            variant="flat"
            isIconOnly
            onPress={() => handleViewLogs(row.id)}
            aria-label="View logs"
          >
            <FileText size={14} />
          </Button>
          <Button
            size="sm"
            variant="flat"
            color="danger"
            isIconOnly
            onPress={() => { setDeleteId(row.id); onDeleteOpen(); }}
            aria-label="Delete"
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <div className="flex justify-between items-center mb-4">
        <h3 className="text-lg font-semibold">{t('volunteering.webhooks_heading', 'Webhooks')}</h3>
        <div className="flex gap-2">
          <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>
            {t('common.refresh', 'Refresh')}
          </Button>
          <Button color="primary" startContent={<Plus size={16} />} onPress={openCreate}>
            {t('volunteering.add_webhook', 'Add Webhook')}
          </Button>
        </div>
      </div>

      {webhooks.length === 0 && !loading ? (
        <EmptyState
          icon={Webhook}
          title={t('volunteering.no_webhooks', 'No webhooks configured')}
          description={t('volunteering.no_webhooks_desc', 'Set up webhooks to receive real-time notifications about volunteering events.')}
        />
      ) : (
        <DataTable columns={columns} data={webhooks} isLoading={loading} />
      )}

      {/* Create/Edit Webhook Modal */}
      <Modal isOpen={isOpen} onClose={onClose} size="lg">
        <ModalContent>
          <ModalHeader>
            {editingId
              ? t('volunteering.edit_webhook', 'Edit Webhook')
              : t('volunteering.add_webhook', 'Add Webhook')}
          </ModalHeader>
          <ModalBody>
            <div className="flex flex-col gap-4">
              <Input
                label={t('volunteering.webhook_name', 'Name')}
                value={form.name}
                onValueChange={(v) => setForm((f) => ({ ...f, name: v }))}
                isRequired
                variant="bordered"
              />
              <Input
                label={t('volunteering.webhook_url', 'URL')}
                value={form.url}
                onValueChange={(v) => setForm((f) => ({ ...f, url: v }))}
                isRequired
                variant="bordered"
                type="url"
                placeholder="https://example.com/webhook"
              />
              <Textarea
                label={t('volunteering.webhook_events', 'Events (comma-separated)')}
                value={form.events}
                onValueChange={(v) => setForm((f) => ({ ...f, events: v }))}
                variant="bordered"
                placeholder="application.created, shift.completed, hours.logged"
                description={t('volunteering.webhook_events_desc', 'Comma-separated list of event types to subscribe to')}
              />
              <Switch
                isSelected={form.is_active}
                onValueChange={(v) => setForm((f) => ({ ...f, is_active: v }))}
              >
                {t('volunteering.webhook_active', 'Active')}
              </Switch>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose}>{t('common.cancel', 'Cancel')}</Button>
            <Button color="primary" onPress={handleSave} isLoading={saving}>
              {editingId ? t('common.save', 'Save') : t('common.create', 'Create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Confirm */}
      <ConfirmModal
        isOpen={isDeleteOpen}
        onClose={onDeleteClose}
        onConfirm={handleDelete}
        title={t('volunteering.delete_webhook_title', 'Delete Webhook')}
        message={t('volunteering.delete_webhook_confirm', 'Are you sure you want to delete this webhook? This action cannot be undone.')}
        confirmLabel={t('common.delete', 'Delete')}
        confirmColor="danger"
      />

      {/* Webhook Logs Modal */}
      <Modal isOpen={isLogsOpen} onClose={onLogsClose} size="2xl" scrollBehavior="inside">
        <ModalContent>
          <ModalHeader>
            {t('volunteering.webhook_logs_title', 'Webhook Dispatch Logs')}
          </ModalHeader>
          <ModalBody>
            {logs.length === 0 ? (
              <p className="text-default-500 text-center py-8">
                {t('volunteering.no_webhook_logs', 'No dispatch logs for this webhook.')}
              </p>
            ) : (
              <div className="flex flex-col gap-3">
                {logs.map((log) => (
                  <Card key={log.id} className="p-0">
                    <CardBody className="p-3">
                      <div className="flex items-center justify-between mb-2">
                        <div className="flex items-center gap-2">
                          <Chip size="sm" variant="flat">{log.event}</Chip>
                          <Chip
                            size="sm"
                            color={log.status_code >= 200 && log.status_code < 300 ? 'success' : 'danger'}
                            variant="flat"
                          >
                            {log.status_code}
                          </Chip>
                        </div>
                        <span className="text-xs text-default-400">
                          {log.created_at ? new Date(log.created_at).toLocaleString() : '-'}
                        </span>
                      </div>
                      {log.response_body && (
                        <pre className="text-xs bg-default-100 p-2 rounded overflow-auto max-h-32">
                          {log.response_body}
                        </pre>
                      )}
                    </CardBody>
                  </Card>
                ))}
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onLogsClose}>{t('common.close', 'Close')}</Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
