// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin CRUD page for tenant safeguarding options.
 *
 * These are the checkboxes/options shown to members during onboarding.
 * Admins can create, edit, reorder, and deactivate options.
 * Country presets are applied from OnboardingSettings — this page manages
 * the resulting options.
 *
 * Route: /admin/safeguarding-options
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Spinner,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  Switch,
  Select,
  SelectItem,
  useDisclosure,
} from '@heroui/react';
import Plus from 'lucide-react/icons/plus';
import Edit3 from 'lucide-react/icons/pen-line';
import Trash2 from 'lucide-react/icons/trash-2';
import Shield from 'lucide-react/icons/shield';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import Bell from 'lucide-react/icons/bell';
import MessageSquare from 'lucide-react/icons/message-square';
import Users from 'lucide-react/icons/users';
import ShieldCheck from 'lucide-react/icons/shield-check';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { PageHeader } from '../../components';
import { useTranslation } from 'react-i18next';

// ── Types ────────────────────────────────────────────────────────────────────

interface SafeguardingOption {
  id: number;
  option_key: string;
  option_type: 'checkbox' | 'info' | 'select';
  label: string;
  description: string | null;
  help_url: string | null;
  sort_order: number;
  is_active: boolean;
  is_required: boolean;
  select_options: unknown[] | null;
  triggers: Record<string, boolean | string> | null;
  preset_source: string | null;
}

interface OptionFormData {
  option_key: string;
  option_type: 'checkbox' | 'info' | 'select';
  label: string;
  description: string;
  help_url: string;
  is_required: boolean;
  triggers: {
    requires_vetted_interaction: boolean;
    requires_broker_approval: boolean;
    restricts_messaging: boolean;
    restricts_matching: boolean;
    notify_admin_on_selection: boolean;
    vetting_type_required: string;
  };
}

const EMPTY_FORM: OptionFormData = {
  option_key: '',
  option_type: 'checkbox',
  label: '',
  description: '',
  help_url: '',
  is_required: false,
  triggers: {
    requires_vetted_interaction: false,
    requires_broker_approval: false,
    restricts_messaging: false,
    restricts_matching: false,
    notify_admin_on_selection: true, // Default: always notify
    vetting_type_required: '',
  },
};

const TRIGGER_ICONS: Record<string, typeof Bell> = {
  notify_admin_on_selection: Bell,
  requires_broker_approval: MessageSquare,
  restricts_messaging: MessageSquare,
  restricts_matching: Users,
  requires_vetted_interaction: ShieldCheck,
};

// Maps trigger key → translation key suffix used in safeguarding.trigger_*_label / *_desc
const TRIGGER_I18N_KEY: Record<string, string> = {
  notify_admin_on_selection: 'notify_admin',
  requires_broker_approval: 'broker_approval',
  restricts_messaging: 'monitor_messaging',
  restricts_matching: 'restrict_matching',
  requires_vetted_interaction: 'vetted_interaction',
};

const TRIGGER_KEYS = [
  'notify_admin_on_selection',
  'requires_broker_approval',
  'restricts_messaging',
  'restricts_matching',
  'requires_vetted_interaction',
] as const;

// ── Component ────────────────────────────────────────────────────────────────

export function SafeguardingOptionsAdmin() {
  const { t } = useTranslation('admin');
  usePageTitle("Safeguarding Options");
  const toast = useToast();

  const [options, setOptions] = useState<SafeguardingOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [editingOption, setEditingOption] = useState<SafeguardingOption | null>(null);
  const [form, setForm] = useState<OptionFormData>(EMPTY_FORM);

  const createModal = useDisclosure();
  const deleteModal = useDisclosure();
  const [deleteTarget, setDeleteTarget] = useState<SafeguardingOption | null>(null);

  // ── Fetch options ────────────────────────────────────────────────────────

  const fetchOptions = useCallback(async () => {
    try {
      setLoading(true);
      const res = await api.get<SafeguardingOption[]>('/v2/admin/safeguarding/options');
      if (res.success && res.data) {
        setOptions(Array.isArray(res.data) ? res.data : []);
      }
    } catch (error) {
      logError('Failed to load safeguarding options', error);
      toast.error("Failed to load options");
    } finally {
      setLoading(false);
    }
  }, [toast])


  useEffect(() => { fetchOptions(); }, [fetchOptions]);

  // ── Create/Update handler ────────────────────────────────────────────────

  const handleSave = useCallback(async () => {
    if (!form.label.trim()) {
      toast.error("Is Required");
      return;
    }
    if (!editingOption && !form.option_key.trim()) {
      toast.error("Option Key is Required");
      return;
    }

    try {
      setSaving(true);

      const payload = {
        ...form,
        triggers: { ...form.triggers },
      };

      // Remove empty vetting_type_required
      if (!payload.triggers.vetting_type_required) {
        delete (payload.triggers as Record<string, unknown>).vetting_type_required;
      }

      if (editingOption) {
        // Update
        const res = await api.put(`/v2/admin/safeguarding/options/${editingOption.id}`, payload);
        if (res.success) {
          toast.success(`Option Updated`);
          createModal.onClose();
          fetchOptions();
        } else {
          toast.error(res.error || "Update Failed");
        }
      } else {
        // Create
        const res = await api.post('/v2/admin/safeguarding/options', payload);
        if (res.success) {
          toast.success(`Option Created`);
          createModal.onClose();
          fetchOptions();
        } else {
          toast.error(res.error || "Create Failed");
        }
      }
    } catch (error) {
      logError('Failed to save safeguarding option', error);
      toast.error("Save Failed");
    } finally {
      setSaving(false);
    }
  }, [form, editingOption, toast, fetchOptions, createModal])


  // ── Delete handler ───────────────────────────────────────────────────────

  const handleDelete = useCallback(async () => {
    if (!deleteTarget) return;
    try {
      const res = await api.delete(`/v2/admin/safeguarding/options/${deleteTarget.id}`);
      if (res.success) {
        toast.success(`Option Deactivated`);
        deleteModal.onClose();
        fetchOptions();
      } else {
        toast.error(res.error || "Deactivation Failed");
      }
    } catch (error) {
      logError('Failed to deactivate safeguarding option', error);
      toast.error("Deactivation Failed");
    }
  }, [deleteTarget, toast, fetchOptions, deleteModal])


  // ── Open edit modal ──────────────────────────────────────────────────────

  const openCreate = useCallback(() => {
    setEditingOption(null);
    setForm(EMPTY_FORM);
    createModal.onOpen();
  }, [createModal]);

  const openEdit = useCallback((opt: SafeguardingOption) => {
    setEditingOption(opt);
    setForm({
      option_key: opt.option_key,
      option_type: opt.option_type,
      label: opt.label,
      description: opt.description || '',
      help_url: opt.help_url || '',
      is_required: opt.is_required,
      triggers: {
        requires_vetted_interaction: opt.triggers?.requires_vetted_interaction === true,
        requires_broker_approval: opt.triggers?.requires_broker_approval === true,
        restricts_messaging: opt.triggers?.restricts_messaging === true,
        restricts_matching: opt.triggers?.restricts_matching === true,
        notify_admin_on_selection: opt.triggers?.notify_admin_on_selection !== false, // Default true
        vetting_type_required: (opt.triggers?.vetting_type_required as string) || '',
      },
    });
    createModal.onOpen();
  }, [createModal]);

  const openDelete = useCallback((opt: SafeguardingOption) => {
    setDeleteTarget(opt);
    deleteModal.onOpen();
  }, [deleteModal]);

  // ── Update form trigger ──────────────────────────────────────────────────

  const updateTrigger = useCallback((key: string, value: boolean | string) => {
    setForm(prev => ({
      ...prev,
      triggers: { ...prev.triggers, [key]: value },
    }));
  }, []);

  // ── Loading state ────────────────────────────────────────────────────────

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" />
      </div>
    );
  }

  const activeOptions = options.filter(o => o.is_active);
  const inactiveOptions = options.filter(o => !o.is_active);

  return (
    <div>
      <PageHeader
        title={"Safeguarding Options"}
        description={"Create and manage safeguarding options available to members"}
      />

      <div className="space-y-6">
        {/* ─── Active Options ─── */}
        <Card shadow="sm">
          <CardHeader className="flex items-center justify-between">
            <div>
              <h3 className="text-lg font-semibold">{"Active Options"}</h3>
              <p className="text-sm text-theme-muted">{"Safeguarding options that are currently active and available to members"}</p>
            </div>
            <Button
              color="primary"
              startContent={<Plus className="w-4 h-4" />}
              onPress={openCreate}
            >
              {"Add Option"}
            </Button>
          </CardHeader>
          <CardBody>
            {activeOptions.length > 0 ? (
              <div className="space-y-3">
                {activeOptions.map((opt) => (
                  <OptionCard
                    key={opt.id}
                    option={opt}
                    onEdit={() => openEdit(opt)}
                    onDelete={() => openDelete(opt)}
                  />
                ))}
              </div>
            ) : (
              <div className="text-center py-8 text-theme-muted">
                <Shield className="w-10 h-10 mx-auto mb-2 opacity-40" />
                <p>{"No options configured"}</p>
                <p className="text-sm mt-1">{"No safeguarding options have been created yet"}</p>
              </div>
            )}
          </CardBody>
        </Card>

        {/* ─── Inactive Options ─── */}
        {inactiveOptions.length > 0 && (
          <Card shadow="sm">
            <CardHeader>
              <div>
                <h3 className="text-lg font-semibold text-theme-muted">{"Inactive Options"}</h3>
                <p className="text-sm text-theme-muted">{"Safeguarding options that have been deactivated"}</p>
              </div>
            </CardHeader>
            <CardBody>
              <div className="space-y-2 opacity-60">
                {inactiveOptions.map((opt) => (
                  <div key={opt.id} className="flex items-center justify-between p-3 rounded-lg bg-theme-elevated">
                    <div>
                      <p className="text-sm line-through">{opt.label}</p>
                      <p className="text-xs text-theme-muted">{opt.option_key}</p>
                    </div>
                    <Chip size="sm" variant="flat" color="default">{"Inactive"}</Chip>
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>
        )}
      </div>

      {/* ─── Create/Edit Modal ─── */}
      <Modal isOpen={createModal.isOpen} onClose={createModal.onClose} size="2xl" scrollBehavior="inside">
        <ModalContent>
          <ModalHeader>{editingOption ? "Edit Option" : "Add Safeguarding Option"}</ModalHeader>
          <ModalBody className="gap-4">
            {!editingOption && (
              <Input
                label={"Option Key"}
                value={form.option_key}
                onValueChange={(v) => setForm(prev => ({ ...prev, option_key: v }))}
                variant="bordered"
                description={"A unique identifier for this safeguarding option"}
                placeholder="e.g. works_with_children"
              />
            )}
            <Input
              label={"Display Label"}
              value={form.label}
              onValueChange={(v) => setForm(prev => ({ ...prev, label: v }))}
              variant="bordered"
              placeholder="e.g. I may work with children or young people (under 18)"
              isRequired
            />
            <Textarea
              label={"Description Help"}
              value={form.description}
              onValueChange={(v) => setForm(prev => ({ ...prev, description: v }))}
              variant="bordered"
              placeholder={"Description Help..."}
              minRows={2}
            />
            <Input
              label={"Help URL"}
              value={form.help_url}
              onValueChange={(v) => setForm(prev => ({ ...prev, help_url: v }))}
              variant="bordered"
              placeholder="https://..."
              description={"A link to help documentation for this safeguarding option"}
            />
            <Select
              label={"Option Type"}
              selectedKeys={[form.option_type]}
              onSelectionChange={(keys) => {
                const key = Array.from(keys)[0] as 'checkbox' | 'info' | 'select';
                setForm(prev => ({ ...prev, option_type: key }));
              }}
              variant="bordered"
            >
              <SelectItem key="checkbox">{"Checkbox"}</SelectItem>
              <SelectItem key="info">{"Info"}</SelectItem>
              <SelectItem key="select">{"Select"}</SelectItem>
            </Select>
            <Switch
              isSelected={form.is_required}
              onValueChange={(v) => setForm(prev => ({ ...prev, is_required: v }))}
            >
              <div>
                <p className="font-medium text-sm">{"Required"}</p>
                <p className="text-xs text-theme-muted">{"This safeguarding option is mandatory and cannot be opt-out"}</p>
              </div>
            </Switch>

            <div className="border-t pt-4 mt-2">
              <h4 className="font-semibold text-sm mb-3">{"Behavioral Triggers"}</h4>
              <p className="text-xs text-theme-muted mb-3">
                {"Configure automatic flags triggered by specific member behaviours"}
              </p>
              <div className="space-y-3">
                {TRIGGER_KEYS.map((key) => {
                  const Icon = TRIGGER_ICONS[key]!;
                  const i18nKey = TRIGGER_I18N_KEY[key]!;
                  return (
                    <Switch
                      key={key}
                      isSelected={form.triggers[key as keyof typeof form.triggers] === true}
                      onValueChange={(v) => updateTrigger(key, v)}
                      size="sm"
                    >
                      <div className="flex items-start gap-2">
                        <Icon className="w-4 h-4 text-theme-muted mt-0.5 flex-shrink-0" />
                        <div>
                          <p className="font-medium text-sm">{t(`safeguarding.trigger_${i18nKey}_label`)}</p>
                          <p className="text-xs text-theme-muted">{t(`safeguarding.trigger_${i18nKey}_desc`)}</p>
                        </div>
                      </div>
                    </Switch>
                  );
                })}
              </div>

              {(form.triggers.requires_vetted_interaction || form.triggers.restricts_matching) && (
                <Select
                  label={"Required Vetting Type"}
                  selectedKeys={form.triggers.vetting_type_required ? [form.triggers.vetting_type_required] : []}
                  onSelectionChange={(keys) => {
                    const key = Array.from(keys)[0] as string || '';
                    updateTrigger('vetting_type_required', key);
                  }}
                  variant="bordered"
                  className="mt-3"
                  description={"The vetting type required before this safeguarding option can be assigned"}
                >
                  <SelectItem key="garda_vetting">{"Vetting Garda"}</SelectItem>
                  <SelectItem key="dbs_basic">{"Vetting Dbs Basic"}</SelectItem>
                  <SelectItem key="dbs_standard">{"Vetting Dbs Standard"}</SelectItem>
                  <SelectItem key="dbs_enhanced">{"Vetting Dbs Enhanced"}</SelectItem>
                  <SelectItem key="pvg_scotland">{"Vetting Pvg Scotland"}</SelectItem>
                  <SelectItem key="access_ni">{"Vetting Access Ni"}</SelectItem>
                  <SelectItem key="international">{"Vetting International"}</SelectItem>
                  <SelectItem key="other">{"Vetting Other"}</SelectItem>
                </Select>
              )}
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={createModal.onClose}>{"Cancel"}</Button>
            <Button color="primary" onPress={handleSave} isLoading={saving} isDisabled={saving}>
              {editingOption ? "Save Changes" : "Create Option"}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* ─── Delete Confirmation Modal ─── */}
      <Modal isOpen={deleteModal.isOpen} onClose={deleteModal.onClose}>
        <ModalContent>
          <ModalHeader>{"Deactivate Option"}</ModalHeader>
          <ModalBody>
            <p>
              {"Deactivate Confirm Question"} <strong>&quot;{deleteTarget?.label}&quot;</strong>?
            </p>
            <p className="text-sm text-theme-muted mt-2">
              {"Are you sure you want to deactivate this safeguarding option?"}
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={deleteModal.onClose}>{"Cancel"}</Button>
            <Button color="danger" onPress={handleDelete}>{"Deactivate"}</Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

// ── Option Card Sub-Component ────────────────────────────────────────────────

function OptionCard({
  option,
  onEdit,
  onDelete,
}: {
  option: SafeguardingOption;
  onEdit: () => void;
  onDelete: () => void;
}) {
  const { t } = useTranslation('admin');
  const triggers = option.triggers || {};
  const activeTriggers = Object.entries(triggers).filter(
    ([k, v]) => v === true && k in TRIGGER_I18N_KEY
  );

  return (
    <div className="flex items-start justify-between p-4 rounded-lg bg-theme-elevated border border-theme-default">
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2 mb-1">
          <CheckCircle className="w-4 h-4 text-success-500 flex-shrink-0" />
          <p className="font-medium text-sm">{option.label}</p>
          {option.is_required && (
            <Chip size="sm" variant="flat" color="danger" className="text-xs">{"Required"}</Chip>
          )}
          {option.preset_source && (
            <Chip size="sm" variant="flat" color="secondary" className="text-xs">{option.preset_source}</Chip>
          )}
        </div>
        {option.description && (
          <p className="text-xs text-theme-muted ml-6 mb-1">{option.description}</p>
        )}
        {activeTriggers.length > 0 && (
          <div className="flex flex-wrap gap-1 ml-6 mt-1">
            {activeTriggers.map(([key]) => (
              <Chip key={key} size="sm" variant="flat" color="warning" className="text-xs">
                {TRIGGER_I18N_KEY[key] ? t(`safeguarding.trigger_${TRIGGER_I18N_KEY[key]}_label`) : key}
              </Chip>
            ))}
          </div>
        )}
      </div>
      <div className="flex items-center gap-1 ml-3 flex-shrink-0">
        <Button isIconOnly size="sm" variant="light" onPress={onEdit} aria-label={"Edit Option"}>
          <Edit3 className="w-4 h-4" />
        </Button>
        <Button isIconOnly size="sm" variant="light" color="danger" onPress={onDelete} aria-label={"Deactivate Option"}>
          <Trash2 className="w-4 h-4" />
        </Button>
      </div>
    </div>
  );
}

export default SafeguardingOptionsAdmin;
