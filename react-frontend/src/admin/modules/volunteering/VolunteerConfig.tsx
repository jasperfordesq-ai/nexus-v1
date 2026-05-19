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
  Checkbox,
  RadioGroup,
  Radio,
} from '@heroui/react';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Plus from 'lucide-react/icons/plus';
import Edit2 from 'lucide-react/icons/pen';
import Trash2 from 'lucide-react/icons/trash-2';
import Save from 'lucide-react/icons/save';
import Webhook from 'lucide-react/icons/webhook';
import Bell from 'lucide-react/icons/bell';
import FormInput from 'lucide-react/icons/rectangle-ellipsis';
import Play from 'lucide-react/icons/play';
import FileText from 'lucide-react/icons/file-text';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import XCircle from 'lucide-react/icons/circle-x';
import ArrowUp from 'lucide-react/icons/arrow-up';
import ArrowDown from 'lucide-react/icons/arrow-down';
import Eye from 'lucide-react/icons/eye';
import Send from 'lucide-react/icons/send';
import RotateCcw from 'lucide-react/icons/rotate-ccw';
import Search from 'lucide-react/icons/search';
import Clock from 'lucide-react/icons/clock';
import { usePageTitle } from '@/hooks';
import { useAuth, useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, ConfirmModal, type Column } from '../../components';
import { useTranslation } from 'react-i18next';

function apiErrorMessage(res: { error?: string; message?: string } | null | undefined): string | undefined {
  return res?.error || res?.message;
}

// ─── Custom Fields types ───────────────────────────────────────────────────────

interface CustomField {
  id: number;
  label: string;
  field_type: string;
  applies_to: string;
  is_required: boolean;
  options: string[] | null;
  sort_order?: number;
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

interface ReminderSettingResponse {
  reminder_type: string;
  enabled: boolean;
  hours_before: number | null;
  hours_after: number | null;
  days_inactive: number | null;
  days_before_expiry: number | null;
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
  usePageTitle(t('volunteering.config_title'));

  const [activeTab, setActiveTab] = useState('custom-fields');

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('volunteering.config_title')}
        description={t('volunteering.config_desc')}
      />

      <Tabs
        selectedKey={activeTab}
        onSelectionChange={(key) => setActiveTab(String(key))}
        variant="underlined"
        classNames={{
          tabList: 'rounded-2xl border border-divider/70 bg-content1 p-1 shadow-sm shadow-black/[0.03]',
          cursor: 'rounded-xl',
        }}
      >
        <Tab
          key="custom-fields"
          title={
            <div className="flex items-center gap-2">
              <FormInput size={16} />
              {t('volunteering.tab_custom_fields')}
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
              {t('volunteering.tab_reminders')}
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
              {t('volunteering.tab_webhooks')}
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

function FieldPreviewModal({
  isOpen,
  onClose,
  field,
}: {
  isOpen: boolean;
  onClose: () => void;
  field: CustomField | null;
}) {
  const { t } = useTranslation('admin');
  if (!field) return null;

  const renderPreview = () => {
    switch (field.field_type) {
      case 'text':
      case 'email':
      case 'phone':
      case 'number':
        return (
          <Input
            label={field.label}
            type={field.field_type === 'phone' ? 'tel' : field.field_type}
            variant="bordered"
            isRequired={field.is_required}
            placeholder={t('volunteering.field_preview_input_placeholder', { label: field.label.toLowerCase() })}
            isReadOnly
          />
        );
      case 'textarea':
        return (
          <Textarea
            label={field.label}
            variant="bordered"
            isRequired={field.is_required}
            placeholder={t('volunteering.field_preview_input_placeholder', { label: field.label.toLowerCase() })}
            minRows={3}
            isReadOnly
          />
        );
      case 'select':
        return (
          <Select
            label={field.label}
            variant="bordered"
            isRequired={field.is_required}
            placeholder={t('volunteering.field_preview_select_placeholder', { label: field.label.toLowerCase() })}
          >
            {(field.options || [
              t('volunteering.preview_option_one'),
              t('volunteering.preview_option_two'),
              t('volunteering.preview_option_three'),
            ]).map((opt) => (
              <SelectItem key={opt}>{opt}</SelectItem>
            ))}
          </Select>
        );
      case 'checkbox':
        return (
          <div className="flex flex-col gap-2">
            <span className="text-sm font-medium">{field.label} {field.is_required && '*'}</span>
            {(field.options || [
              t('volunteering.preview_option_one'),
              t('volunteering.preview_option_two'),
            ]).map((opt) => (
              <Checkbox key={opt}>{opt}</Checkbox>
            ))}
          </div>
        );
      case 'radio':
        return (
          <RadioGroup label={field.label} isRequired={field.is_required}>
            {(field.options || [
              t('volunteering.preview_option_one'),
              t('volunteering.preview_option_two'),
            ]).map((opt) => (
              <Radio key={opt} value={opt}>{opt}</Radio>
            ))}
          </RadioGroup>
        );
      case 'date':
        return (
          <Input
            label={field.label}
            type="date"
            variant="bordered"
            isRequired={field.is_required}
            isReadOnly
          />
        );
      case 'file':
        return (
          <Input
            label={field.label}
            type="file"
            variant="bordered"
            isRequired={field.is_required}
            isReadOnly
          />
        );
      default:
        return (
          <Input
            label={field.label}
            variant="bordered"
            isRequired={field.is_required}
            isReadOnly
          />
        );
    }
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="md">
      <ModalContent>
        <ModalHeader>
          {t('volunteering.field_preview')}: {field.label}
        </ModalHeader>
        <ModalBody>
          <Card className="border border-divider/70 bg-content2/50">
            <CardBody className="p-4">
              <p className="mb-3 text-xs text-default-400">
                {t('volunteering.preview_description')}
              </p>
              {renderPreview()}
            </CardBody>
          </Card>
          <div className="mt-3 flex flex-wrap gap-2 text-xs text-default-500">
            <Chip size="sm" variant="flat">{field.field_type}</Chip>
            <Chip size="sm" variant="flat" color="primary">{field.applies_to}</Chip>
            {field.is_required && <Chip size="sm" variant="flat" color="danger">{t('volunteering.required')}</Chip>}
          </div>
        </ModalBody>
        <ModalFooter>
          <Button variant="flat" onPress={onClose}>{t('volunteering.close')}</Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

function CustomFieldsTab() {
  const { t } = useTranslation('admin');
  const toast = useToast();

  const [fields, setFields] = useState<CustomField[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState(emptyFieldForm);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [previewField, setPreviewField] = useState<CustomField | null>(null);
  const [orderChanged, setOrderChanged] = useState(false);

  const { isOpen, onOpen, onClose } = useDisclosure();
  const { isOpen: isDeleteOpen, onOpen: onDeleteOpen, onClose: onDeleteClose } = useDisclosure();
  const { isOpen: isPreviewOpen, onOpen: onPreviewOpen, onClose: onPreviewClose } = useDisclosure();

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getCustomFields();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        let loadedFields: CustomField[];
        if (Array.isArray(payload)) {
          loadedFields = payload;
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          loadedFields = (payload as { data: CustomField[] }).data || [];
        } else {
          loadedFields = [];
        }
        // Assign sort_order if missing
        setFields(loadedFields.map((f, i) => ({ ...f, sort_order: f.sort_order ?? i })));
        setOrderChanged(false);
      }
    } catch {
      toast.error(t('volunteering.failed_to_load_fields'));
      setFields([]);
    }
    setLoading(false);
  }, [toast, t]);


  useEffect(() => { loadData(); }, [loadData]);

  const sortedFields = [...fields].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));

  const moveField = (index: number, direction: 'up' | 'down') => {
    const sorted = [...sortedFields];
    const newIndex = direction === 'up' ? index - 1 : index + 1;
    if (newIndex < 0 || newIndex >= sorted.length) return;
    // Swap sort_order values (bounds already checked above)
    const currentItem = sorted[index]!;
    const targetItem = sorted[newIndex]!;
    const tempOrder = currentItem.sort_order ?? index;
    sorted[index] = { ...currentItem, sort_order: targetItem.sort_order ?? newIndex };
    sorted[newIndex] = { ...targetItem, sort_order: tempOrder };
    setFields(sorted);
    setOrderChanged(true);
  };

  const openPreview = (field: CustomField) => {
    setPreviewField(field);
    onPreviewOpen();
  };

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
      toast.error(t('volunteering.label_required'));
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
        const res = await adminVolunteering.updateCustomField(editingId, payload);
        if (!res.success) throw new Error(apiErrorMessage(res) || 'update_failed');
        toast.success(t('volunteering.field_updated'));
      } else {
        const res = await adminVolunteering.createCustomField(payload);
        if (!res.success) throw new Error(apiErrorMessage(res) || 'create_failed');
        toast.success(t('volunteering.field_created'));
      }
      onClose();
      loadData();
    } catch {
      toast.error(t('volunteering.failed_to_save_field'));
    }
    setSaving(false);
  };

  const handleDelete = async () => {
    if (!deleteId) return;
    try {
      const res = await adminVolunteering.deleteCustomField(deleteId);
      if (!res.success) throw new Error(apiErrorMessage(res) || 'delete_failed');
      toast.success(t('volunteering.field_deleted'));
      onDeleteClose();
      loadData();
    } catch {
      toast.error(t('volunteering.failed_to_delete_field'));
    }
  };

  const columns: Column<CustomField>[] = [
    { key: 'label', label: t('volunteering.col_label'), sortable: true },
    {
      key: 'field_type',
      label: t('volunteering.col_field_type'),
      render: (row) => <Chip size="sm" variant="flat">{t(`volunteering.field_type_${row.field_type}`)}</Chip>,
    },
    {
      key: 'applies_to',
      label: t('volunteering.col_applies_to'),
      render: (row) => <Chip size="sm" variant="flat" color="primary">{t(`volunteering.applies_to_${row.applies_to}`)}</Chip>,
    },
    {
      key: 'is_required',
      label: t('volunteering.col_required'),
      render: (row) => row.is_required
        ? <CheckCircle size={16} className="text-success" />
        : <XCircle size={16} className="text-default-400" />,
    },
    {
      key: 'options',
      label: t('volunteering.col_options'),
      render: (row) => (
        <span className="text-sm text-default-500">
          {row.options?.length ? row.options.join(', ') : '-'}
        </span>
      ),
    },
    {
      key: 'actions' as keyof CustomField,
      label: t('volunteering.col_actions'),
      render: (row) => {
        const idx = sortedFields.findIndex((f) => f.id === row.id);
        return (
          <div className="flex items-center gap-1">
            <Button
              size="sm"
              variant="flat"
              isIconOnly
              isDisabled={idx <= 0}
              onPress={() => moveField(idx, 'up')}
              aria-label={t('volunteering.move_up')}
            >
              <ArrowUp size={14} />
            </Button>
            <Button
              size="sm"
              variant="flat"
              isIconOnly
              isDisabled={idx >= sortedFields.length - 1}
              onPress={() => moveField(idx, 'down')}
              aria-label={t('volunteering.move_down')}
            >
              <ArrowDown size={14} />
            </Button>
            <Button size="sm" variant="flat" isIconOnly onPress={() => openPreview(row)} aria-label={t('volunteering.preview')}>
              <Eye size={14} />
            </Button>
            <Button size="sm" variant="flat" isIconOnly onPress={() => openEdit(row)} aria-label={t('volunteering.edit')}>
              <Edit2 size={14} />
            </Button>
            <Button
              size="sm"
              variant="flat"
              color="danger"
              isIconOnly
              onPress={() => { setDeleteId(row.id); onDeleteOpen(); }}
              aria-label={t('volunteering.delete')}
            >
              <Trash2 size={14} />
            </Button>
          </div>
        );
      },
    },
  ];

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h3 className="text-lg font-semibold">{t('volunteering.custom_fields_heading')}</h3>
        <div className="flex gap-2">
          <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>
            {t('volunteering.refresh')}
          </Button>
          <Button color="primary" startContent={<Plus size={16} />} onPress={openCreate}>
            {t('volunteering.add_field')}
          </Button>
        </div>
      </div>

      {orderChanged && (
        <Card className="border border-primary/30 bg-primary-50/40 shadow-sm shadow-primary/10">
          <CardBody className="p-3 flex items-center justify-between">
            <p className="text-sm text-primary-700">
              {t('volunteering.order_changed_note')}
            </p>
            <Button
              size="sm"
              color="primary"
              startContent={<Save size={14} />}
              isLoading={saving}
              onPress={async () => {
                setSaving(true);
                try {
                  const fieldIds = sortedFields.map((f) => f.id);
                  await adminVolunteering.reorderCustomFields(fieldIds);
                  toast.success(t('volunteering.order_saved'));
                  setOrderChanged(false);
                  loadData();
                } catch {
                  toast.error(t('volunteering.order_save_failed'));
                }
                setSaving(false);
              }}
            >
              {t('volunteering.save_order')}
            </Button>
          </CardBody>
        </Card>
      )}

      {sortedFields.length === 0 && !loading ? (
        <EmptyState
          icon={FormInput}
          title={t('volunteering.no_custom_fields')}
          description={t('volunteering.no_custom_fields_desc')}
        />
      ) : (
        <DataTable columns={columns} data={sortedFields} isLoading={loading} />
      )}

      {/* Field Preview Modal */}
      <FieldPreviewModal isOpen={isPreviewOpen} onClose={onPreviewClose} field={previewField} />

      {/* Create/Edit Modal */}
      <Modal isOpen={isOpen} onClose={onClose} size="lg">
        <ModalContent>
          <ModalHeader>
            {editingId
              ? t('volunteering.edit_field')
              : t('volunteering.add_field')}
          </ModalHeader>
          <ModalBody>
            <div className="flex flex-col gap-4">
              <Input
                label={t('volunteering.field_label')}
                value={form.label}
                onValueChange={(v) => setForm((f) => ({ ...f, label: v }))}
                isRequired
                variant="bordered"
              />
              <Select
                label={t('volunteering.field_type_label')}
                selectedKeys={[form.field_type]}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0];
                  if (typeof selected === 'string') setForm((f) => ({ ...f, field_type: selected }));
                }}
                variant="bordered"
              >
                {fieldTypeOptions.map((ft) => (
                  <SelectItem key={ft}>{t(`volunteering.field_type_${ft}`)}</SelectItem>
                ))}
              </Select>
              <Select
                label={t('volunteering.field_applies_to')}
                selectedKeys={[form.applies_to]}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0];
                  if (typeof selected === 'string') setForm((f) => ({ ...f, applies_to: selected }));
                }}
                variant="bordered"
              >
                {appliesToOptions.map((at) => (
                  <SelectItem key={at}>{t(`volunteering.applies_to_${at}`)}</SelectItem>
                ))}
              </Select>
              <Switch
                isSelected={form.is_required}
                onValueChange={(v) => setForm((f) => ({ ...f, is_required: v }))}
              >
                {t('volunteering.field_required')}
              </Switch>
              <Input
                label={t('volunteering.field_options_label')}
                value={form.options}
                onValueChange={(v) => setForm((f) => ({ ...f, options: v }))}
                variant="bordered"
                description={t('volunteering.field_options_desc')}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose}>{t('volunteering.cancel')}</Button>
            <Button color="primary" onPress={handleSave} isLoading={saving}>
              {editingId ? t('volunteering.save') : t('volunteering.create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Confirm */}
      <ConfirmModal
        isOpen={isDeleteOpen}
        onClose={onDeleteClose}
        onConfirm={handleDelete}
        title={t('volunteering.delete_field_title')}
        message={t('volunteering.delete_field_confirm')}
        confirmLabel={t('volunteering.delete')}
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

function fromReminderResponse(row: ReminderSettingResponse): ReminderSetting {
  const template = defaultReminders.find((item) => item.key === row.reminder_type);
  const timingValue = row.hours_before ?? row.hours_after ?? row.days_inactive ?? row.days_before_expiry ?? template?.timing_value ?? 0;
  return {
    key: row.reminder_type,
    label: template?.label ?? row.reminder_type,
    enabled: row.enabled,
    timing_value: timingValue,
    timing_unit: template?.timing_unit ?? '',
    email_enabled: row.email_enabled,
    push_enabled: row.push_enabled,
    sms_enabled: row.sms_enabled,
  };
}

function toReminderRequest(reminder: ReminderSetting): Record<string, unknown> {
  return {
    reminder_type: reminder.key,
    enabled: reminder.enabled,
    hours_before: reminder.key === 'pre_shift' ? reminder.timing_value : null,
    hours_after: reminder.key === 'post_shift_feedback' ? reminder.timing_value : null,
    days_inactive: reminder.key === 'lapsed_volunteer' ? reminder.timing_value : null,
    days_before_expiry: ['credential_expiry', 'training_expiry'].includes(reminder.key) ? reminder.timing_value : null,
    email_enabled: reminder.email_enabled,
    push_enabled: reminder.push_enabled,
    sms_enabled: reminder.sms_enabled,
  };
}

function RemindersTab() {
  const { t } = useTranslation('admin');
  const toast = useToast();
  const { user } = useAuth();
  const canRunReminderJobs = Boolean(
    user?.is_super_admin ||
    user?.is_god ||
    user?.is_tenant_super_admin ||
    user?.role === 'super_admin',
  );

  const [reminders, setReminders] = useState<ReminderSetting[]>(defaultReminders);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [runningReminderJob, setRunningReminderJob] = useState(false);
  const { isOpen: isTestOpen, onOpen: onTestOpen, onClose: onTestClose } = useDisclosure();

  // Delivery log state
  interface DeliveryLog {
    id: number;
    user_name: string;
    user_avatar: string | null;
    reminder_type: string;
    channel: string;
    sent_at: string;
  }
  interface DeliveryStats {
    total_sent: number;
    by_channel: Record<string, number>;
    by_type: Record<string, number>;
  }
  const [deliveryLogs, setDeliveryLogs] = useState<DeliveryLog[]>([]);
  const [deliveryStats, setDeliveryStats] = useState<DeliveryStats | null>(null);
  const [deliveryLoading, setDeliveryLoading] = useState(false);
  const [deliveryFilterType, setDeliveryFilterType] = useState('');
  const [deliveryFilterChannel, setDeliveryFilterChannel] = useState('');

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getReminderSettings();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        let data: ReminderSettingResponse[];
        if (Array.isArray(payload)) {
          data = payload;
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          data = (payload as { data: ReminderSettingResponse[] }).data || [];
        } else {
          data = [];
        }
        if (data.length > 0) setReminders(data.map(fromReminderResponse));
      }
    } catch {
      // Use defaults on failure
    }
    setLoading(false);
  }, []);

  const loadDeliveryLogs = useCallback(async (type?: string, channel?: string) => {
    setDeliveryLoading(true);
    try {
      const res = await adminVolunteering.getReminderLogs({
        type: type || undefined,
        channel: channel || undefined,
        per_page: 10,
      });
      if (res.success && res.data) {
        const rows = Array.isArray(res.data) ? res.data as DeliveryLog[] : [];
        const meta = res.meta as { stats?: DeliveryStats } | undefined;
        setDeliveryLogs(rows);
        if (meta?.stats) setDeliveryStats(meta.stats);
      }
    } catch {
      setDeliveryLogs([]);
    }
    setDeliveryLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);
  useEffect(() => { loadDeliveryLogs(); }, [loadDeliveryLogs]);

  const updateReminder = (index: number, updates: Partial<ReminderSetting>) => {
    setReminders((prev) => prev.map((r, i) => (i === index ? { ...r, ...updates } : r)));
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      const results = await Promise.all(reminders.map((reminder) => adminVolunteering.updateReminderSettings(toReminderRequest(reminder))));
      if (results.some((res) => !res.success)) {
        throw new Error('reminder_settings_update_failed');
      }
      toast.success(t('volunteering.reminders_saved'));
    } catch {
      toast.error(t('volunteering.failed_to_save_reminders'));
    }
    setSaving(false);
  };

  const handleTestSend = async () => {
    setRunningReminderJob(true);
    try {
      const res = await adminVolunteering.sendShiftReminders();
      if (!res.success) {
        throw new Error('send_shift_reminders_failed');
      }
      onTestClose();
      toast.success(
        t('volunteering.reminder_job_sent', {
          count: (res.data as { reminders_sent?: number } | undefined)?.reminders_sent ?? 0,
        }),
      );
      loadDeliveryLogs();
    } catch {
      toast.error(t('volunteering.reminder_job_failed'));
    } finally {
      setRunningReminderJob(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h3 className="text-lg font-semibold">{t('volunteering.reminders_heading')}</h3>
        <div className="flex gap-2">
          <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>
            {t('volunteering.refresh')}
          </Button>
          {canRunReminderJobs && (
            <Button variant="flat" color="primary" startContent={<Send size={16} />} onPress={onTestOpen} isLoading={runningReminderJob}>
              {t('volunteering.run_due_reminders')}
            </Button>
          )}
          <Button color="primary" startContent={<Save size={16} />} onPress={handleSave} isLoading={saving}>
            {t('volunteering.save')}
          </Button>
        </div>
      </div>

      <div className="flex flex-col gap-4">
        {reminders.map((reminder, index) => (
          <Card key={reminder.key} className="border border-divider/70 p-0 shadow-sm shadow-black/[0.03]">
            <CardBody className="p-4">
              <div className="flex flex-col gap-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <Switch
                      isSelected={reminder.enabled}
                      onValueChange={(v) => updateReminder(index, { enabled: v })}
                    />
                    <span className="font-medium">
                      {t(`volunteering.reminder_${reminder.key}`)}
                    </span>
                  </div>
                  <div className="flex items-center gap-2">
                    <Chip size="sm" variant="flat" color={reminder.enabled ? 'success' : 'default'}>
                      {reminder.enabled
                        ? t('volunteering.enabled')
                        : t('volunteering.disabled')}
                    </Chip>
                  </div>
                </div>

                {reminder.enabled && (
                  <div className="flex flex-wrap items-center gap-4 ml-12">
                    <Input
                      type="number"
                      label={t('volunteering.timing_value')}
                      value={String(reminder.timing_value)}
                      onValueChange={(v) => updateReminder(index, { timing_value: Number(v) || 0 })}
                      variant="bordered"
                      className="w-28"
                      size="sm"
                      endContent={
                        <span className="text-xs text-default-400 whitespace-nowrap">
                          {t(`volunteering.timing_unit_${reminder.key}`)}
                        </span>
                      }
                    />
                    <div className="flex items-center gap-3">
                      <Switch
                        size="sm"
                        isSelected={reminder.email_enabled}
                        onValueChange={(v) => updateReminder(index, { email_enabled: v })}
                      >
                        {t('volunteering.channel_email')}
                      </Switch>
                      <Switch
                        size="sm"
                        isSelected={reminder.push_enabled}
                        onValueChange={(v) => updateReminder(index, { push_enabled: v })}
                      >
                        {t('volunteering.channel_push')}
                      </Switch>
                      <Switch
                        size="sm"
                        isSelected={reminder.sms_enabled}
                        onValueChange={(v) => updateReminder(index, { sms_enabled: v })}
                      >
                        {t('volunteering.channel_sms')}
                      </Switch>
                    </div>
                  </div>
                )}
              </div>
            </CardBody>
          </Card>
        ))}
      </div>

      {/* Recent Deliveries */}
      <div className="space-y-3 pt-2">
        <h4 className="text-md flex items-center gap-2 font-semibold">
          <Clock size={16} />
          {t('volunteering.recent_deliveries')}
        </h4>

        {/* Stats summary */}
        {deliveryStats && (
          <div className="flex flex-wrap gap-2">
            {Object.entries(deliveryStats.by_channel).map(([ch, count]) => (
              <Chip key={ch} size="sm" variant="flat" color={ch === 'email' ? 'primary' : ch === 'push' ? 'secondary' : 'warning'}>
                {count} {ch}
              </Chip>
            ))}
            <Chip size="sm" variant="flat" color="default">
              {t('volunteering.delivery_total_sent', { count: deliveryStats.total_sent })}
            </Chip>
          </div>
        )}

        {/* Filters */}
        <div className="flex gap-2 rounded-2xl border border-divider/70 bg-content1 p-3 shadow-sm shadow-black/[0.03]">
          <Select
            label={t('volunteering.filter_type')}
            size="sm"
            variant="bordered"
            className="w-44"
            selectedKeys={deliveryFilterType ? [deliveryFilterType] : []}
            onSelectionChange={(keys) => {
              const val = Array.from(keys)[0] as string || '';
              setDeliveryFilterType(val);
              loadDeliveryLogs(val, deliveryFilterChannel);
            }}
          >
            <SelectItem key="">{t('volunteering.tab_all')}</SelectItem>
            <SelectItem key="pre_shift">{t('volunteering.reminder_pre_shift')}</SelectItem>
            <SelectItem key="post_shift_feedback">{t('volunteering.reminder_post_shift_feedback')}</SelectItem>
            <SelectItem key="lapsed_volunteer">{t('volunteering.reminder_lapsed_volunteer')}</SelectItem>
            <SelectItem key="credential_expiry">{t('volunteering.reminder_credential_expiry')}</SelectItem>
            <SelectItem key="training_expiry">{t('volunteering.reminder_training_expiry')}</SelectItem>
          </Select>
          <Select
            label={t('volunteering.filter_channel')}
            size="sm"
            variant="bordered"
            className="w-36"
            selectedKeys={deliveryFilterChannel ? [deliveryFilterChannel] : []}
            onSelectionChange={(keys) => {
              const val = Array.from(keys)[0] as string || '';
              setDeliveryFilterChannel(val);
              loadDeliveryLogs(deliveryFilterType, val);
            }}
          >
            <SelectItem key="">{t('volunteering.tab_all')}</SelectItem>
            <SelectItem key="email">{t('volunteering.channel_email')}</SelectItem>
            <SelectItem key="push">{t('volunteering.channel_push')}</SelectItem>
            <SelectItem key="sms">{t('volunteering.channel_sms')}</SelectItem>
          </Select>
        </div>

        {/* Log list */}
        {deliveryLoading ? (
          <Card className="border border-divider/70 bg-content2/50">
            <CardBody className="p-6 flex justify-center">
              <div className="flex items-center gap-2 text-default-400">
                <RefreshCw size={16} className="animate-spin" />
                <span className="text-sm">{t('volunteering.loading')}</span>
              </div>
            </CardBody>
          </Card>
        ) : deliveryLogs.length === 0 ? (
          <Card className="border border-divider/70 bg-content2/50">
            <CardBody className="p-6 text-center">
              <Clock size={32} className="mx-auto mb-3 text-default-300" />
              <p className="text-default-500 text-sm">
                {t('volunteering.no_delivery_logs')}
              </p>
            </CardBody>
          </Card>
        ) : (
          <div className="flex flex-col gap-1">
            {deliveryLogs.map((log) => (
              <Card key={log.id} className="border border-divider/70 p-0 shadow-sm shadow-black/[0.02]">
                <CardBody className="p-3">
                  <div className="flex items-center gap-3">
                    <div className="flex-1 min-w-0">
                      <span className="text-sm font-medium">{log.user_name}</span>
                    </div>
                    <Chip size="sm" variant="flat" color="primary">
                      {t(`volunteering.reminder_${log.reminder_type}`)}
                    </Chip>
                    <Chip
                      size="sm"
                      variant="flat"
                      color={log.channel === 'email' ? 'default' : log.channel === 'push' ? 'secondary' : 'warning'}
                    >
                      {t(`volunteering.channel_${log.channel}`)}
                    </Chip>
                    <span className="text-xs text-default-400 whitespace-nowrap">
                      {log.sent_at ? new Date(log.sent_at).toLocaleString() : ''}
                    </span>
                  </div>
                </CardBody>
              </Card>
            ))}
          </div>
        )}
      </div>

      {/* Reminder Job Confirmation Modal */}
      {canRunReminderJobs && (
        <Modal isOpen={isTestOpen} onClose={onTestClose} size="sm">
          <ModalContent>
            <ModalHeader>{t('volunteering.run_reminder_job_title')}</ModalHeader>
            <ModalBody>
              <p className="text-default-600">
                {t('volunteering.run_reminder_job_confirm')}
              </p>
            </ModalBody>
            <ModalFooter>
              <Button variant="flat" onPress={onTestClose}>{t('volunteering.cancel')}</Button>
              <Button color="primary" startContent={<Send size={14} />} onPress={handleTestSend} isLoading={runningReminderJob}>
                {t('volunteering.confirm_run_reminders')}
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>
      )}
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
  const [retryingId, setRetryingId] = useState<number | null>(null);
  const [logFilter, setLogFilter] = useState('');

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
      toast.error(t('volunteering.failed_to_load_webhooks'));
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
      toast.error(t('volunteering.webhook_name_url_required'));
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
        const res = await adminVolunteering.updateWebhook(editingId, payload);
        if (!res.success) throw new Error(apiErrorMessage(res) || 'webhook_update_failed');
        toast.success(t('volunteering.webhook_updated'));
      } else {
        const res = await adminVolunteering.createWebhook(payload);
        if (!res.success) throw new Error(apiErrorMessage(res) || 'webhook_create_failed');
        toast.success(t('volunteering.webhook_created'));
      }
      onClose();
      loadData();
    } catch {
      toast.error(t('volunteering.failed_to_save_webhook'));
    }
    setSaving(false);
  };

  const handleDelete = async () => {
    if (!deleteId) return;
    try {
      const res = await adminVolunteering.deleteWebhook(deleteId);
      if (!res.success) throw new Error(apiErrorMessage(res) || 'webhook_delete_failed');
      toast.success(t('volunteering.webhook_deleted'));
      onDeleteClose();
      loadData();
    } catch {
      toast.error(t('volunteering.failed_to_delete_webhook'));
    }
  };

  const handleTest = async (id: number) => {
    setTestingId(id);
    try {
      const res = await adminVolunteering.testWebhook(id);
      if (!res.success) throw new Error(apiErrorMessage(res) || 'webhook_test_failed');
      toast.success(t('volunteering.webhook_test_sent'));
    } catch {
      toast.error(t('volunteering.webhook_test_failed'));
    }
    setTestingId(null);
  };

  const handleRetry = async (id: number) => {
    setRetryingId(id);
    try {
      const res = await adminVolunteering.testWebhook(id);
      if (!res.success) throw new Error(apiErrorMessage(res) || 'webhook_retry_failed');
      toast.success(t('volunteering.webhook_retry_sent'));
      loadData();
    } catch {
      toast.error(t('volunteering.webhook_retry_failed'));
    }
    setRetryingId(null);
  };

  const filteredLogs = logFilter.trim()
    ? logs.filter(
        (log) =>
          log.event.toLowerCase().includes(logFilter.toLowerCase()) ||
          String(log.status_code).includes(logFilter),
      )
    : logs;

  const handleViewLogs = async (id: number) => {
    setLogFilter('');
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
      toast.error(t('volunteering.failed_to_load_logs'));
      setLogs([]);
    }
    onLogsOpen();
  };

  const columns: Column<WebhookEntry>[] = [
    { key: 'name', label: t('volunteering.col_name'), sortable: true },
    {
      key: 'url',
      label: t('volunteering.col_url'),
      render: (row) => (
        <span className="text-sm font-mono truncate max-w-[250px] block">{row.url}</span>
      ),
    },
    {
      key: 'events',
      label: t('volunteering.col_events'),
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
      label: t('volunteering.col_active'),
      render: (row) => (
        <Chip size="sm" color={row.is_active ? 'success' : 'default'} variant="flat">
          {row.is_active ? t('volunteering.active') : t('volunteering.inactive')}
        </Chip>
      ),
    },
    {
      key: 'failure_count',
      label: t('volunteering.col_failures'),
      render: (row) => (
        <Chip size="sm" color={row.failure_count > 0 ? 'danger' : 'default'} variant="flat">
          {row.failure_count}
        </Chip>
      ),
    },
    {
      key: 'actions' as keyof WebhookEntry,
      label: t('volunteering.col_actions'),
      render: (row) => (
        <div className="flex items-center gap-1">
          <Button size="sm" variant="flat" isIconOnly onPress={() => openEdit(row)} aria-label={t('volunteering.edit')}>
            <Edit2 size={14} />
          </Button>
          <Button
            size="sm"
            variant="flat"
            color="primary"
            isIconOnly
            isLoading={testingId === row.id}
            onPress={() => handleTest(row.id)}
            aria-label={t('volunteering.test_webhook')}
          >
            <Play size={14} />
          </Button>
          {row.failure_count > 0 && (
            <Button
              size="sm"
              variant="flat"
              color="warning"
              isIconOnly
              isLoading={retryingId === row.id}
              onPress={() => handleRetry(row.id)}
              aria-label={t('volunteering.retry_webhook')}
            >
              <RotateCcw size={14} />
            </Button>
          )}
          <Button
            size="sm"
            variant="flat"
            isIconOnly
            onPress={() => handleViewLogs(row.id)}
            aria-label={t('volunteering.view_logs')}
          >
            <FileText size={14} />
          </Button>
          <Button
            size="sm"
            variant="flat"
            color="danger"
            isIconOnly
            onPress={() => { setDeleteId(row.id); onDeleteOpen(); }}
            aria-label={t('volunteering.delete')}
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h3 className="text-lg font-semibold">{t('volunteering.webhooks_heading')}</h3>
        <div className="flex gap-2">
          <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>
            {t('volunteering.refresh')}
          </Button>
          <Button color="primary" startContent={<Plus size={16} />} onPress={openCreate}>
            {t('volunteering.add_webhook')}
          </Button>
        </div>
      </div>

      {webhooks.length === 0 && !loading ? (
        <EmptyState
          icon={Webhook}
          title={t('volunteering.no_webhooks')}
          description={t('volunteering.no_webhooks_desc')}
        />
      ) : (
        <DataTable columns={columns} data={webhooks} isLoading={loading} />
      )}

      {/* Create/Edit Webhook Modal */}
      <Modal isOpen={isOpen} onClose={onClose} size="lg">
        <ModalContent>
          <ModalHeader>
            {editingId
              ? t('volunteering.edit_webhook')
              : t('volunteering.add_webhook')}
          </ModalHeader>
          <ModalBody>
            <div className="flex flex-col gap-4">
              <Input
                label={t('volunteering.webhook_name')}
                value={form.name}
                onValueChange={(v) => setForm((f) => ({ ...f, name: v }))}
                isRequired
                variant="bordered"
              />
              <Input
                label={t('volunteering.webhook_url')}
                value={form.url}
                onValueChange={(v) => setForm((f) => ({ ...f, url: v }))}
                isRequired
                variant="bordered"
                type="url"
                placeholder={t('volunteering.webhook_url_placeholder')}
              />
              <Textarea
                label={t('volunteering.webhook_events')}
                value={form.events}
                onValueChange={(v) => setForm((f) => ({ ...f, events: v }))}
                variant="bordered"
                placeholder={t('volunteering.webhook_events_placeholder')}
                description={t('volunteering.webhook_events_desc')}
              />
              <Switch
                isSelected={form.is_active}
                onValueChange={(v) => setForm((f) => ({ ...f, is_active: v }))}
              >
                {t('volunteering.webhook_active')}
              </Switch>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose}>{t('volunteering.cancel')}</Button>
            <Button color="primary" onPress={handleSave} isLoading={saving}>
              {editingId ? t('volunteering.save') : t('volunteering.create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Confirm */}
      <ConfirmModal
        isOpen={isDeleteOpen}
        onClose={onDeleteClose}
        onConfirm={handleDelete}
        title={t('volunteering.delete_webhook_title')}
        message={t('volunteering.delete_webhook_confirm')}
        confirmLabel={t('volunteering.delete')}
        confirmColor="danger"
      />

      {/* Webhook Logs Modal */}
      <Modal isOpen={isLogsOpen} onClose={onLogsClose} size="2xl" scrollBehavior="inside">
        <ModalContent>
          <ModalHeader>
            {t('volunteering.webhook_logs_title')}
          </ModalHeader>
          <ModalBody>
            {logs.length > 0 && (
              <Input
                placeholder={t('volunteering.filter_logs')}
                value={logFilter}
                onValueChange={setLogFilter}
                variant="bordered"
                size="sm"
                startContent={<Search size={14} className="text-default-400" />}
                className="mb-3"
                isClearable
                onClear={() => setLogFilter('')}
              />
            )}
            {filteredLogs.length === 0 ? (
              <p className="text-default-500 text-center py-8">
                {logs.length === 0
                  ? t('volunteering.no_webhook_logs')
                  : t('volunteering.no_matching_logs')}
              </p>
            ) : (
              <div className="flex flex-col gap-3">
                {filteredLogs.map((log) => (
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
            <Button variant="flat" onPress={onLogsClose}>{t('volunteering.close')}</Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
