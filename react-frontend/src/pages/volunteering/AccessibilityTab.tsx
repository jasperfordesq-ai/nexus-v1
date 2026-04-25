// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback, useRef } from 'react';
import { motion } from 'framer-motion';
import {
  Button,
  Chip,
  Input,
  Select,
  SelectItem,
  Textarea,
} from '@heroui/react';
import Accessibility from 'lucide-react/icons/accessibility';
import Info from 'lucide-react/icons/info';
import Plus from 'lucide-react/icons/plus';
import Save from 'lucide-react/icons/save';
import Trash2 from 'lucide-react/icons/trash-2';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

/* ---------- Types ---------- */

interface AccessibilityNeed {
  id?: number;
  need_type: 'mobility' | 'visual' | 'hearing' | 'cognitive' | 'dietary' | 'language' | 'other';
  description: string;
  accommodations_required: string;
  emergency_contact_name: string;
  emergency_contact_phone: string;
}

const NEED_TYPES = [
  'mobility',
  'visual',
  'hearing',
  'cognitive',
  'dietary',
  'language',
  'other',
] as const;

const NEED_TYPE_COLORS: Record<string, 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'default'> = {
  mobility: 'primary',
  visual: 'secondary',
  hearing: 'success',
  cognitive: 'warning',
  dietary: 'danger',
  language: 'default',
  other: 'default',
};

function emptyNeed(): AccessibilityNeed {
  return {
    need_type: 'other',
    description: '',
    accommodations_required: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
  };
}

/* ---------- Main Component ---------- */

export function AccessibilityTab() {
  const { t } = useTranslation('volunteering');
  const toast = useToast();
  const [needs, setNeeds] = useState<AccessibilityNeed[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // AbortController ref to cancel stale requests
  const abortRef = useRef<AbortController | null>(null);

  // Stable refs for t/toast — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

  const load = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<AccessibilityNeed[]>('/v2/volunteering/accessibility-needs');
      if (controller.signal.aborted) return;
      if (response.success && response.data) {
        setNeeds(response.data as AccessibilityNeed[]);
      } else {
        setError(tRef.current('accessibility.load_error', 'Unable to load accessibility needs.'));
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load accessibility needs', err);
      setError(tRef.current('accessibility.load_error', 'Unable to load accessibility needs.'));
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const handleSave = async () => {
    try {
      setIsSaving(true);
      const response = await api.put('/v2/volunteering/accessibility-needs', { needs });
      if (response.success) {
        toastRef.current.success(tRef.current('accessibility.saved', 'Accessibility needs saved successfully.'));
        load();
      } else {
        toastRef.current.error(tRef.current('accessibility.save_error', 'Failed to save changes.'));
      }
    } catch (err) {
      logError('Failed to save accessibility needs', err);
      toastRef.current.error(tRef.current('accessibility.save_error', 'Failed to save changes.'));
    } finally {
      setIsSaving(false);
    }
  };

  const updateNeed = (index: number, field: keyof AccessibilityNeed, value: string) => {
    setNeeds((prev) => prev.map((n, i) => (i === index ? { ...n, [field]: value } : n)));
  };

  const removeNeed = (index: number) => {
    setNeeds((prev) => prev.filter((_, i) => i !== index));
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.05 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Accessibility className="w-5 h-5 text-rose-400" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary">
            {t('accessibility.heading', 'Accessibility & Accommodations')}
          </h2>
        </div>
        <Button
          size="sm"
          className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
          startContent={<Save className="w-4 h-4" aria-hidden="true" />}
          onPress={handleSave}
          isLoading={isSaving}
          isDisabled={isLoading}
        >
          {t('accessibility.save', 'Save Changes')}
        </Button>
      </div>

      {/* Info Banner */}
      <GlassCard className="p-4 border-l-4 border-rose-500">
        <div className="flex items-start gap-3">
          <Info className="w-5 h-5 text-rose-400 flex-shrink-0 mt-0.5" aria-hidden="true" />
          <p className="text-sm text-theme-muted">
            {t(
              'accessibility.info_banner',
              'This information helps organizations provide appropriate support and accommodations for your volunteer activities. All data is kept confidential.',
            )}
          </p>
        </div>
      </GlassCard>

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-[var(--color-warning)] mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={load}
          >
            {t('accessibility.try_again', 'Try Again')}
          </Button>
        </GlassCard>
      )}

      {/* Loading */}
      {!error && isLoading && (
        <div className="space-y-4">
          {[1, 2].map((i) => (
            <GlassCard key={i} className="p-5 animate-pulse">
              <div className="h-4 bg-theme-hover rounded w-1/4 mb-3" />
              <div className="h-16 bg-theme-hover rounded w-full mb-2" />
              <div className="h-8 bg-theme-hover rounded w-1/2" />
            </GlassCard>
          ))}
        </div>
      )}

      {/* Content */}
      {!error && !isLoading && (
        <motion.div variants={containerVariants} initial="hidden" animate="visible" className="space-y-4">
          {needs.length === 0 && (
            <EmptyState
              icon={<Accessibility className="w-12 h-12" aria-hidden="true" />}
              title={t('accessibility.no_needs_title', 'No accessibility needs added')}
              description={t('accessibility.no_needs_desc', 'Add your accessibility requirements so organizations can provide the right support.')}
              action={
                <Button
                  className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
                  startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
                  onPress={() => setNeeds([emptyNeed()])}
                >
                  {t('accessibility.add_first', 'Add Your First Need')}
                </Button>
              }
            />
          )}

          {needs.map((need, index) => (
            <motion.div key={need.id ?? index} variants={itemVariants}>
              <GlassCard className="p-5 space-y-4">
                <div className="flex items-center justify-between">
                  <Chip size="sm" color={NEED_TYPE_COLORS[need.need_type] ?? 'default'} variant="flat">
                    {t(`accessibility.types.${need.need_type}`, need.need_type)}
                  </Chip>
                  <Button
                    size="sm"
                    variant="light"
                    color="danger"
                    isIconOnly
                    aria-label={t('accessibility.remove', 'Remove need')}
                    onPress={() => removeNeed(index)}
                  >
                    <Trash2 className="w-4 h-4" />
                  </Button>
                </div>

                <Select
                  label={t('accessibility.need_type_label', 'Need Type')}
                  selectedKeys={new Set([need.need_type])}
                  onSelectionChange={(keys) => {
                    const val = Array.from(keys)[0] as string;
                    if (val) updateNeed(index, 'need_type', val);
                  }}
                  classNames={{ trigger: 'bg-theme-elevated border-theme-default' }}
                >
                  {NEED_TYPES.map((type) => (
                    <SelectItem key={type}>{t(`accessibility.types.${type}`, type)}</SelectItem>
                  ))}
                </Select>

                <Textarea
                  label={t('accessibility.description_label', 'Description')}
                  placeholder={t('accessibility.description_placeholder', 'Describe your accessibility need...')}
                  value={need.description}
                  onChange={(e) => updateNeed(index, 'description', e.target.value)}
                  classNames={{ input: 'bg-transparent text-theme-primary', inputWrapper: 'bg-theme-elevated border-theme-default' }}
                />

                <Textarea
                  label={t('accessibility.accommodations_label', 'Accommodations Required')}
                  placeholder={t('accessibility.accommodations_placeholder', 'What accommodations would help you participate?')}
                  value={need.accommodations_required}
                  onChange={(e) => updateNeed(index, 'accommodations_required', e.target.value)}
                  classNames={{ input: 'bg-transparent text-theme-primary', inputWrapper: 'bg-theme-elevated border-theme-default' }}
                />

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <Input
                    label={t('accessibility.emergency_name', 'Emergency Contact Name')}
                    value={need.emergency_contact_name}
                    onChange={(e) => updateNeed(index, 'emergency_contact_name', e.target.value)}
                    classNames={{ input: 'bg-transparent text-theme-primary', inputWrapper: 'bg-theme-elevated border-theme-default' }}
                  />
                  <Input
                    label={t('accessibility.emergency_phone', 'Emergency Contact Phone')}
                    value={need.emergency_contact_phone}
                    onChange={(e) => updateNeed(index, 'emergency_contact_phone', e.target.value)}
                    classNames={{ input: 'bg-transparent text-theme-primary', inputWrapper: 'bg-theme-elevated border-theme-default' }}
                  />
                </div>
              </GlassCard>
            </motion.div>
          ))}

          {needs.length > 0 && (
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-muted w-full"
              startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
              onPress={() => setNeeds((prev) => [...prev, emptyNeed()])}
            >
              {t('accessibility.add_need', 'Add Another Need')}
            </Button>
          )}
        </motion.div>
      )}
    </div>
  );
}

export default AccessibilityTab;
