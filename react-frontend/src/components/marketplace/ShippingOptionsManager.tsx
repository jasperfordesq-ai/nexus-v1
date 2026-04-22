// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ShippingOptionsManager — Seller shipping option configuration.
 *
 * CRUD interface for managing shipping options: list, add, edit, delete.
 * Each option has courier name, price, currency, estimated days, and default flag.
 * Uses HeroUI components throughout.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Input,
  Select,
  SelectItem,
  Switch,
  Spinner,
  Chip,
} from '@heroui/react';
import Plus from 'lucide-react/icons/plus';
import Pencil from 'lucide-react/icons/pencil';
import Trash2 from 'lucide-react/icons/trash-2';
import Truck from 'lucide-react/icons/truck';
import Check from 'lucide-react/icons/check';
import X from 'lucide-react/icons/x';
import Package from 'lucide-react/icons/package';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { MarketplaceShippingOption } from '@/types/marketplace';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface ShippingOptionsManagerProps {
  sellerId: number;
}

interface ShippingFormData {
  courier_name: string;
  price: string;
  currency: string;
  estimated_days: string;
  is_default: boolean;
}

const EMPTY_FORM: ShippingFormData = {
  courier_name: '',
  price: '',
  currency: 'EUR',
  estimated_days: '',
  is_default: false,
};

const CURRENCY_OPTIONS = [
  { value: 'EUR', label: 'EUR' },
  { value: 'GBP', label: 'GBP' },
  { value: 'USD', label: 'USD' },
  { value: 'CAD', label: 'CAD' },
  { value: 'AUD', label: 'AUD' },
  { value: 'NZD', label: 'NZD' },
  { value: 'CHF', label: 'CHF' },
  { value: 'SEK', label: 'SEK' },
  { value: 'NOK', label: 'NOK' },
  { value: 'DKK', label: 'DKK' },
  { value: 'PLN', label: 'PLN' },
  { value: 'JPY', label: 'JPY' },
];

// ─────────────────────────────────────────────────────────────────────────────
// Inline Form — shared between Add and Edit
// ─────────────────────────────────────────────────────────────────────────────

interface ShippingFormProps {
  form: ShippingFormData;
  onChange: (field: keyof ShippingFormData, value: string | boolean) => void;
  onSubmit: () => void;
  onCancel: () => void;
  isSubmitting: boolean;
  submitLabel: string;
}

function ShippingForm({ form, onChange, onSubmit, onCancel, isSubmitting, submitLabel }: ShippingFormProps) {
  const { t } = useTranslation('marketplace');

  return (
    <GlassCard className="p-4 space-y-3 border-2 border-primary/20">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Input
          label={t('shipping.courier_name', 'Courier Name')}
          placeholder={t('shipping.courier_name_placeholder', 'e.g. An Post, Royal Mail, DHL')}
          value={form.courier_name}
          onValueChange={(v) => onChange('courier_name', v)}
          size="sm"
          isRequired
          variant="bordered"
        />
        <div className="flex gap-2">
          <Input
            label={t('shipping.price', 'Price')}
            placeholder="0.00"
            type="number"
            min={0}
            step={0.01}
            value={form.price}
            onValueChange={(v) => onChange('price', v)}
            size="sm"
            isRequired
            variant="bordered"
            className="flex-1"
          />
          <Select
            label={t('shipping.currency', 'Currency')}
            selectedKeys={form.currency ? [form.currency] : []}
            onSelectionChange={(keys) => {
              const selected = Array.from(keys)[0];
              if (selected) onChange('currency', String(selected));
            }}
            size="sm"
            variant="bordered"
            className="w-28"
          >
            {CURRENCY_OPTIONS.map((opt) => (
              <SelectItem key={opt.value}>{opt.label}</SelectItem>
            ))}
          </Select>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
        <Input
          label={t('shipping.estimated_days', 'Estimated Days')}
          placeholder={t('shipping.estimated_days_placeholder', 'e.g. 3')}
          type="number"
          min={1}
          value={form.estimated_days}
          onValueChange={(v) => onChange('estimated_days', v)}
          size="sm"
          variant="bordered"
        />
        <div className="flex items-center gap-2 pb-1">
          <Switch
            isSelected={form.is_default}
            onValueChange={(v) => onChange('is_default', v)}
            size="sm"
          />
          <span className="text-sm text-foreground">
            {t('shipping.set_as_default', 'Set as default')}
          </span>
        </div>
      </div>

      <div className="flex gap-2 justify-end pt-1">
        <Button
          variant="flat"
          size="sm"
          onPress={onCancel}
          startContent={<X className="w-3.5 h-3.5" />}
        >
          {t('common.cancel', 'Cancel')}
        </Button>
        <Button
          color="primary"
          size="sm"
          onPress={onSubmit}
          isLoading={isSubmitting}
          isDisabled={!form.courier_name.trim() || !form.price}
          startContent={<Check className="w-3.5 h-3.5" />}
        >
          {submitLabel}
        </Button>
      </div>
    </GlassCard>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function ShippingOptionsManager({ sellerId: _sellerId }: ShippingOptionsManagerProps) {
  const { t } = useTranslation('marketplace');
  const toast = useToast();

  const [options, setOptions] = useState<MarketplaceShippingOption[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Add mode
  const [showAddForm, setShowAddForm] = useState(false);
  const [addForm, setAddForm] = useState<ShippingFormData>(EMPTY_FORM);

  // Edit mode
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editForm, setEditForm] = useState<ShippingFormData>(EMPTY_FORM);

  // ─── Load ──────────────────────────────────────────────────────────────────
  const loadOptions = useCallback(async () => {
    try {
      setIsLoading(true);
      const response = await api.get<MarketplaceShippingOption[]>(
        '/v2/marketplace/seller/shipping-options'
      );
      if (response.success && response.data) {
        setOptions(response.data);
      }
    } catch (err) {
      logError('Failed to load shipping options', err);
      toast.error(t('shipping.load_error', 'Failed to load shipping options'));
    } finally {
      setIsLoading(false);
    }
  }, [toast, t]);

  useEffect(() => {
    loadOptions();
  }, [loadOptions]);

  // ─── Add ───────────────────────────────────────────────────────────────────
  const handleAdd = useCallback(async () => {
    if (!addForm.courier_name.trim() || !addForm.price) return;
    setIsSubmitting(true);
    try {
      const response = await api.post<MarketplaceShippingOption>(
        '/v2/marketplace/seller/shipping-options',
        {
          courier_name: addForm.courier_name.trim(),
          price: parseFloat(addForm.price),
          currency: addForm.currency,
          estimated_days: addForm.estimated_days ? parseInt(addForm.estimated_days, 10) : null,
          is_default: addForm.is_default,
        }
      );
      if (response.success && response.data) {
        setOptions((prev) => {
          // If new option is default, unset others
          if (response.data!.is_default) {
            return [...prev.map((o) => ({ ...o, is_default: false })), response.data!];
          }
          return [...prev, response.data!];
        });
        setAddForm(EMPTY_FORM);
        setShowAddForm(false);
        toast.success(t('shipping.added_success', 'Shipping option added'));
      }
    } catch (err) {
      logError('Failed to add shipping option', err);
      toast.error(t('shipping.add_error', 'Failed to add shipping option'));
    } finally {
      setIsSubmitting(false);
    }
  }, [addForm, toast, t]);

  // ─── Edit ──────────────────────────────────────────────────────────────────
  const startEdit = useCallback((option: MarketplaceShippingOption) => {
    setEditingId(option.id);
    setEditForm({
      courier_name: option.courier_name,
      price: String(option.price),
      currency: option.currency,
      estimated_days: option.estimated_days != null ? String(option.estimated_days) : '',
      is_default: option.is_default,
    });
    // Close add form if open
    setShowAddForm(false);
  }, []);

  const handleUpdate = useCallback(async () => {
    if (editingId === null || !editForm.courier_name.trim() || !editForm.price) return;
    setIsSubmitting(true);
    try {
      const response = await api.put<MarketplaceShippingOption>(
        `/v2/marketplace/seller/shipping-options/${editingId}`,
        {
          courier_name: editForm.courier_name.trim(),
          price: parseFloat(editForm.price),
          currency: editForm.currency,
          estimated_days: editForm.estimated_days ? parseInt(editForm.estimated_days, 10) : null,
          is_default: editForm.is_default,
        }
      );
      if (response.success && response.data) {
        setOptions((prev) =>
          prev.map((o) => {
            if (o.id === editingId) return response.data!;
            // If updated option became default, unset others
            if (response.data!.is_default && o.is_default) {
              return { ...o, is_default: false };
            }
            return o;
          })
        );
        setEditingId(null);
        setEditForm(EMPTY_FORM);
        toast.success(t('shipping.updated_success', 'Shipping option updated'));
      }
    } catch (err) {
      logError('Failed to update shipping option', err);
      toast.error(t('shipping.update_error', 'Failed to update shipping option'));
    } finally {
      setIsSubmitting(false);
    }
  }, [editingId, editForm, toast, t]);

  // ─── Delete ────────────────────────────────────────────────────────────────
  const handleDelete = useCallback(async (id: number) => {
    try {
      await api.delete(`/v2/marketplace/seller/shipping-options/${id}`);
      setOptions((prev) => prev.filter((o) => o.id !== id));
      toast.success(t('shipping.deleted_success', 'Shipping option removed'));
    } catch (err) {
      logError('Failed to delete shipping option', err);
      toast.error(t('shipping.delete_error', 'Failed to remove shipping option'));
    }
  }, [toast, t]);

  // ─── Helpers ───────────────────────────────────────────────────────────────
  const updateAddForm = useCallback((field: keyof ShippingFormData, value: string | boolean) => {
    setAddForm((prev) => ({ ...prev, [field]: value }));
  }, []);

  const updateEditForm = useCallback((field: keyof ShippingFormData, value: string | boolean) => {
    setEditForm((prev) => ({ ...prev, [field]: value }));
  }, []);

  // ─── Render ────────────────────────────────────────────────────────────────
  if (isLoading) {
    return (
      <div className="flex justify-center py-8">
        <Spinner size="md" color="primary" />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h3 className="text-lg font-semibold text-foreground flex items-center gap-2">
          <Truck className="w-5 h-5 text-primary" />
          {t('shipping.title', 'Shipping Options')}
        </h3>
        {!showAddForm && editingId === null && (
          <Button
            color="primary"
            variant="flat"
            size="sm"
            startContent={<Plus className="w-4 h-4" />}
            onPress={() => { setShowAddForm(true); setAddForm(EMPTY_FORM); }}
          >
            {t('shipping.add_option', 'Add Shipping Option')}
          </Button>
        )}
      </div>

      {/* Add form */}
      {showAddForm && (
        <ShippingForm
          form={addForm}
          onChange={updateAddForm}
          onSubmit={handleAdd}
          onCancel={() => { setShowAddForm(false); setAddForm(EMPTY_FORM); }}
          isSubmitting={isSubmitting}
          submitLabel={t('shipping.add_option', 'Add Shipping Option')}
        />
      )}

      {/* Options list */}
      {options.length === 0 && !showAddForm ? (
        <EmptyState
          icon={<Package className="w-6 h-6" />}
          title={t('shipping.empty_title', 'No Shipping Options')}
          description={t('shipping.empty_description', 'Add shipping options so buyers know how you deliver items.')}
          action={{
            label: t('shipping.add_option', 'Add Shipping Option'),
            onClick: () => setShowAddForm(true),
          }}
        />
      ) : (
        <div className="space-y-3">
          {options.map((option) =>
            editingId === option.id ? (
              <ShippingForm
                key={option.id}
                form={editForm}
                onChange={updateEditForm}
                onSubmit={handleUpdate}
                onCancel={() => { setEditingId(null); setEditForm(EMPTY_FORM); }}
                isSubmitting={isSubmitting}
                submitLabel={t('shipping.save_changes', 'Save Changes')}
              />
            ) : (
              <GlassCard key={option.id} className="p-4">
                <div className="flex items-center justify-between gap-3">
                  <div className="flex items-center gap-3 min-w-0 flex-1">
                    <Truck className="w-5 h-5 text-default-400 shrink-0" />
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="font-medium text-foreground">
                          {option.courier_name}
                        </span>
                        {option.is_default && (
                          <Chip size="sm" color="primary" variant="flat">
                            {t('shipping.default_badge', 'Default')}
                          </Chip>
                        )}
                      </div>
                      <div className="flex items-center gap-3 text-sm text-default-500 mt-0.5">
                        <span className="font-semibold text-foreground">
                          {new Intl.NumberFormat(undefined, {
                            style: 'currency',
                            currency: option.currency || 'EUR',
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 2,
                          }).format(option.price)}
                        </span>
                        {option.estimated_days != null && (
                          <span>
                            {t('shipping.estimated_delivery', '~{{days}} days', {
                              days: option.estimated_days,
                            })}
                          </span>
                        )}
                      </div>
                    </div>
                  </div>

                  <div className="flex gap-1 shrink-0">
                    <Button
                      isIconOnly
                      variant="light"
                      size="sm"
                      onPress={() => startEdit(option)}
                      aria-label={t('shipping.edit_aria', 'Edit shipping option')}
                    >
                      <Pencil className="w-3.5 h-3.5" />
                    </Button>
                    <Button
                      isIconOnly
                      variant="light"
                      size="sm"
                      color="danger"
                      onPress={() => handleDelete(option.id)}
                      aria-label={t('shipping.delete_aria', 'Delete shipping option')}
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </Button>
                  </div>
                </div>
              </GlassCard>
            )
          )}
        </div>
      )}
    </div>
  );
}

export default ShippingOptionsManager;
