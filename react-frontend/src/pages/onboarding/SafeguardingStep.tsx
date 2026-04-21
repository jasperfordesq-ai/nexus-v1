// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Safeguarding step for the onboarding wizard.
 *
 * Framed as "accessing support" not "being labelled". Shows tenant-configured
 * safeguarding options as checkboxes. Records GDPR consent per option.
 * Triggers broker protections automatically when member selects options.
 *
 * This step only appears when admin enables it in Onboarding Settings.
 * All data is access-controlled and never visible in public profiles.
 */

import { useState, useEffect, useCallback, useMemo } from 'react';
import {
  Button,
  Checkbox,
  Select,
  SelectItem,
  Spinner,
} from '@heroui/react';
import {
  Shield,
  ArrowRight,
  ArrowLeft,
  SkipForward,
  ExternalLink,
  CheckCircle2,
  Eye,
  Zap,
  Settings as SettingsIcon,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

// ── Types ────────────────────────────────────────────────────────────────────

interface SafeguardingOption {
  id: number;
  option_key: string;
  option_type: 'checkbox' | 'info' | 'select';
  label: string;
  description: string | null;
  help_url: string | null;
  is_required: boolean;
  select_options: string | null; // JSON array of {value, label} for select type
  triggers?: Record<string, unknown> | null;
}

interface AggregatedTriggers {
  requires_broker_approval: boolean;
  restricts_messaging: boolean;
  restricts_matching: boolean;
  requires_vetted_interaction: boolean;
  notify_admin_on_selection: boolean;
  vetting_types_required: string[];
}

interface SafeguardingStepProps {
  onNext: () => void;
  onBack: () => void;
  onSkip?: () => void;
  isRequired: boolean;
  introText: string | null;
}

// ── Component ────────────────────────────────────────────────────────────────

export function SafeguardingStep({ onNext, onBack, onSkip, isRequired, introText }: SafeguardingStepProps) {
  const { t } = useTranslation('onboarding');
  const toast = useToast();
  const { tenantPath } = useTenant();

  const [options, setOptions] = useState<SafeguardingOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [selections, setSelections] = useState<Record<number, boolean>>({});
  const [selectValues, setSelectValues] = useState<Record<number, string>>({});
  const [confirmationShown, setConfirmationShown] = useState(false);

  // ── Aggregated triggers (OR-merge across selected options) ────────────────

  const selectedOptions = useMemo(
    () => options.filter(o =>
      selections[o.id] ||
      (o.option_type === 'select' && (selectValues[o.id] ?? '') !== '')
    ),
    [options, selections, selectValues]
  );

  const aggregatedTriggers = useMemo<AggregatedTriggers>(() => {
    const agg: AggregatedTriggers = {
      requires_broker_approval: false,
      restricts_messaging: false,
      restricts_matching: false,
      requires_vetted_interaction: false,
      notify_admin_on_selection: false,
      vetting_types_required: [],
    };
    for (const opt of selectedOptions) {
      const triggers = opt.triggers ?? {};
      if (triggers.requires_broker_approval) agg.requires_broker_approval = true;
      if (triggers.restricts_messaging) agg.restricts_messaging = true;
      if (triggers.restricts_matching) agg.restricts_matching = true;
      if (triggers.requires_vetted_interaction) agg.requires_vetted_interaction = true;
      if (triggers.notify_admin_on_selection) agg.notify_admin_on_selection = true;
      if (typeof triggers.vetting_type_required === 'string'
          && !agg.vetting_types_required.includes(triggers.vetting_type_required)) {
        agg.vetting_types_required.push(triggers.vetting_type_required);
      }
    }
    return agg;
  }, [selectedOptions]);

  // ── Load safeguarding options from API ────────────────────────────────────

  const loadOptions = useCallback(async () => {
    try {
      setLoading(true);
      const res = await api.get<SafeguardingOption[]>('/v2/onboarding/safeguarding-options');
      if (res.success && res.data) {
        const opts = Array.isArray(res.data) ? res.data : [];
        setOptions(opts);
        // Initialize all selections to false (no pre-ticked boxes — GDPR)
        const initial: Record<number, boolean> = {};
        opts.forEach(o => { initial[o.id] = false; });
        setSelections(initial);
      }
    } catch (error) {
      logError('Failed to load safeguarding options', error);
      toast.error(t('safeguarding.load_error'), t('safeguarding.try_again'));
    } finally {
      setLoading(false);
    }
  }, [toast, t]);

  useEffect(() => { loadOptions(); }, [loadOptions]);

  // ── Toggle a checkbox ────────────────────────────────────────────────────

  const toggleOption = useCallback((optionId: number) => {
    setSelections(prev => ({ ...prev, [optionId]: !prev[optionId] }));
  }, []);

  // ── Save and proceed ─────────────────────────────────────────────────────

  const handleSaveAndProceed = useCallback(async () => {
    // Check required options
    const requiredOptions = options.filter(o => o.is_required);
    const unmetRequired = requiredOptions.filter(o => !selections[o.id]);
    if (unmetRequired.length > 0) {
      toast.error(
        t('safeguarding.required'),
        t('safeguarding.required_respond', { items: unmetRequired.map(o => o.label).join(', ') })
      );
      return;
    }

    // Build preferences array — send checkbox selections and select values
    const selectedPrefs: Array<{ option_id: number; value: string }> = [];

    // Checkbox options
    Object.entries(selections)
      .filter(([, selected]) => selected)
      .forEach(([optionId]) => {
        selectedPrefs.push({ option_id: parseInt(optionId), value: '1' });
      });

    // Select options with a chosen value
    Object.entries(selectValues)
      .filter(([, value]) => value !== '')
      .forEach(([optionId, value]) => {
        // Don't double-add if already in checkbox selections
        if (!selections[parseInt(optionId)]) {
          selectedPrefs.push({ option_id: parseInt(optionId), value });
        }
      });

    if (selectedPrefs.length === 0 && !isRequired) {
      // Nothing selected and step is not required — just proceed
      onNext();
      return;
    }

    if (selectedPrefs.length > 0) {
      try {
        setSaving(true);
        const res = await api.post('/v2/onboarding/safeguarding', {
          preferences: selectedPrefs,
        });

        if (!res.success) {
          toast.error(t('safeguarding.save_failed'), res.error || t('safeguarding.try_again'));
          return;
        }
      } catch (error) {
        logError('Failed to save safeguarding preferences', error);
        toast.error(t('safeguarding.save_failed'), t('safeguarding.try_again'));
        return;
      } finally {
        setSaving(false);
      }

      // Show confirmation screen (Tier 3a) before advancing to the next step.
      // Only when the user actually made selections — empty submits skip through.
      setConfirmationShown(true);
      return;
    }

    onNext();
  }, [selections, selectValues, options, isRequired, onNext, toast, t]);

  // ── Loading state ────────────────────────────────────────────────────────

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Spinner size="lg" />
      </div>
    );
  }

  // If no options configured, skip this step silently
  if (options.length === 0) {
    return (
      <div className="space-y-6">
        <GlassCard className="p-6 text-center">
          <Shield className="w-10 h-10 mx-auto mb-3 text-theme-muted opacity-40" />
          <p className="text-theme-muted">{t('safeguarding.empty')}</p>
        </GlassCard>
        <div className="flex items-center justify-between gap-3">
          <Button variant="light" className="text-theme-muted" onPress={onBack}
            startContent={<ArrowLeft className="w-4 h-4" />}
          >
            {t('back')}
          </Button>
          <Button
            className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-semibold"
            onPress={onNext}
            endContent={<ArrowRight className="w-4 h-4" />}
          >
            {t('next')}
          </Button>
        </div>
      </div>
    );
  }

  const anySelected = Object.values(selections).some(Boolean);

  // ── Confirmation view (Tier 3a) ─────────────────────────────────────────
  // Rendered immediately after a successful save so the member can see a
  // plain-English summary of what they chose, who sees it, and what activates.

  if (confirmationShown) {
    const activations: string[] = [];
    if (aggregatedTriggers.requires_broker_approval) {
      activations.push(t('safeguarding.confirmation.activation_broker_review'));
    }
    if (aggregatedTriggers.restricts_matching || aggregatedTriggers.requires_broker_approval) {
      activations.push(t('safeguarding.confirmation.activation_match_approval'));
    }
    if (aggregatedTriggers.requires_vetted_interaction) {
      activations.push(t('safeguarding.confirmation.activation_discovery_hidden'));
    }
    if (aggregatedTriggers.notify_admin_on_selection) {
      activations.push(t('safeguarding.confirmation.activation_notification'));
    }
    if (activations.length === 0) {
      activations.push(t('safeguarding.confirmation.activation_none'));
    }

    return (
      <div className="space-y-6">
        <GlassCard className="p-6">
          <div className="flex items-center gap-3 mb-4">
            <div className="p-3 rounded-xl bg-emerald-500/20">
              <CheckCircle2 className="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-theme-primary">
                {t('safeguarding.confirmation.title')}
              </h2>
              <p className="text-sm text-theme-muted">
                {t('safeguarding.confirmation.intro')}
              </p>
            </div>
          </div>

          {/* Your selections */}
          <section className="mb-6">
            <h3 className="text-sm font-semibold text-theme-primary mb-2">
              {t('safeguarding.confirmation.your_selections')}
            </h3>
            {selectedOptions.length === 0 ? (
              <p className="text-sm text-theme-muted">
                {t('safeguarding.confirmation.no_selections')}
              </p>
            ) : (
              <ul className="space-y-2">
                {selectedOptions.map(opt => (
                  <li
                    key={opt.id}
                    className="flex items-start gap-2 p-3 rounded-lg bg-theme-elevated border border-theme-default"
                  >
                    <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
                    <span className="text-sm text-theme-secondary">{opt.label}</span>
                  </li>
                ))}
              </ul>
            )}
          </section>

          {/* Who can see this */}
          <section className="mb-6 p-4 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/20">
            <div className="flex items-start gap-2">
              <Eye className="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
              <div>
                <h3 className="text-sm font-semibold text-blue-700 dark:text-blue-300 mb-1">
                  {t('safeguarding.confirmation.who_can_see_heading')}
                </h3>
                <p className="text-xs text-blue-700 dark:text-blue-400 leading-relaxed">
                  {t('safeguarding.confirmation.who_can_see_body')}
                </p>
              </div>
            </div>
          </section>

          {/* What activates */}
          <section className="mb-6">
            <div className="flex items-center gap-2 mb-2">
              <Zap className="w-4 h-4 text-amber-500" />
              <h3 className="text-sm font-semibold text-theme-primary">
                {t('safeguarding.confirmation.what_activates_heading')}
              </h3>
            </div>
            <ul className="space-y-2">
              {activations.map((activation, idx) => (
                <li key={idx} className="flex items-start gap-2 pl-1">
                  <span className="text-amber-500 mt-1">•</span>
                  <span className="text-sm text-theme-secondary">{activation}</span>
                </li>
              ))}
            </ul>
          </section>

          {/* Revoke info */}
          <section className="p-4 rounded-lg border border-theme-default bg-theme-elevated">
            <div className="flex items-start gap-2">
              <SettingsIcon className="w-4 h-4 text-theme-muted shrink-0 mt-0.5" />
              <div>
                <h3 className="text-sm font-semibold text-theme-primary mb-1">
                  {t('safeguarding.confirmation.revoke_heading')}
                </h3>
                <p className="text-xs text-theme-secondary leading-relaxed mb-2">
                  {t('safeguarding.confirmation.revoke_body')}
                </p>
                <a
                  href={tenantPath('/settings/safeguarding')}
                  className="inline-flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 hover:underline"
                >
                  {t('safeguarding.confirmation.revoke_cta')}
                  <ExternalLink className="w-3 h-3" />
                </a>
              </div>
            </div>
          </section>
        </GlassCard>

        <div className="flex items-center justify-end">
          <Button
            className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-semibold shadow-lg shadow-emerald-500/20"
            onPress={onNext}
            endContent={<ArrowRight className="w-4 h-4" />}
          >
            {t('safeguarding.confirmation.continue_cta')}
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <GlassCard className="p-6">
        {/* Header */}
        <div className="flex items-center gap-3 mb-4">
          <div className="p-3 rounded-xl bg-blue-500/20">
            <Shield className="w-6 h-6 text-blue-600 dark:text-blue-400" />
          </div>
          <div>
            <h2 className="text-lg font-semibold text-theme-primary">
              {t('safeguarding_title', 'Support & Safeguarding')}
            </h2>
            <p className="text-sm text-theme-muted">
              {t('safeguarding_subtitle', 'Let us know if you\'d like additional support')}
            </p>
          </div>
        </div>

        {/* Intro text (admin-configurable or default) */}
        <div className="p-4 rounded-lg bg-theme-elevated mb-5">
          <p className="text-sm text-theme-secondary leading-relaxed">
            {introText || t('safeguarding_intro',
              'Your safety matters to us. If you consider yourself a vulnerable adult, or if you would like additional support when using this community, please let us know below. Our coordinators can arrange safeguarded exchanges, mediate contact on your behalf, and ensure you are matched with appropriately vetted members. Your responses are confidential and only visible to community coordinators — never on your public profile.'
            )}
          </p>
        </div>

        {/* GDPR consent notice */}
        <div className="p-3 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/20 mb-5">
          <p className="text-xs text-blue-700 dark:text-blue-400">
            {t('safeguarding_gdpr_notice',
              'This information is classified as sensitive personal data. We collect it solely to ensure your safety during community exchanges. Your selections are stored with a consent record and can be changed at any time from your settings. Only community coordinators and administrators can view your responses.'
            )}
          </p>
        </div>

        {/* Options */}
        <div className="space-y-3">
          {options.map((option) => (
            <div
              key={option.id}
              className={`
                p-4 rounded-lg border transition-all cursor-pointer
                ${selections[option.id]
                  ? 'border-blue-500 bg-blue-500/5 dark:bg-blue-500/10'
                  : 'border-theme-default bg-theme-surface hover:border-blue-300 dark:hover:border-blue-700'
                }
              `}
              onClick={() => option.option_type === 'checkbox' && toggleOption(option.id)}
              role={option.option_type === 'checkbox' ? 'button' : undefined}
              tabIndex={option.option_type === 'checkbox' ? 0 : undefined}
              onKeyDown={(e) => { if (option.option_type === 'checkbox' && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); toggleOption(option.id); } }}
            >
              <div className="flex items-start gap-3">
                {option.option_type === 'checkbox' && (
                  <Checkbox
                    isSelected={selections[option.id] || false}
                    onValueChange={() => toggleOption(option.id)}
                    className="mt-0.5"
                    aria-label={option.label}
                  />
                )}
                <div className="flex-1 min-w-0">
                  <p className="font-medium text-sm text-theme-primary">
                    {option.label}
                    {option.is_required && (
                      <span className="text-danger-500 ml-1">*</span>
                    )}
                  </p>
                  {option.description && (
                    <p className="text-xs text-theme-muted mt-1 leading-relaxed">
                      {option.description}
                    </p>
                  )}
                  {/* Select dropdown for 'select' type options */}
                  {option.option_type === 'select' && (() => {
                    let selectOpts: Array<{ value: string; label: string }> = [];
                    try {
                      selectOpts = JSON.parse(option.select_options || '[]');
                    } catch { /* ignore parse errors */ }
                    return selectOpts.length > 0 ? (
                      <Select
                        label={t('safeguarding.select_option', 'Choose an option')}
                        size="sm"
                        variant="bordered"
                        className="mt-2 max-w-xs"
                        selectedKeys={selectValues[option.id] != null ? [selectValues[option.id] ?? ''] : []}
                        onSelectionChange={(keys) => {
                          const value = Array.from(keys)[0] as string || '';
                          setSelectValues(prev => ({ ...prev, [option.id]: value }));
                        }}
                        onClick={(e) => e.stopPropagation()}
                      >
                        {selectOpts.map((so) => (
                          <SelectItem key={so.value}>{so.label}</SelectItem>
                        ))}
                      </Select>
                    ) : null;
                  })()}
                  {option.help_url && (
                    <a
                      href={option.help_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 hover:underline mt-1"
                      onClick={(e) => e.stopPropagation()}
                    >
                      Learn more <ExternalLink className="w-3 h-3" />
                    </a>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Required field notice */}
        {options.some(o => o.is_required) && (
          <p className="text-xs text-theme-muted mt-3">
            <span className="text-danger-500">*</span> {t('safeguarding.required_fields')}
          </p>
        )}
      </GlassCard>

      {/* Action buttons */}
      <div className="flex items-center justify-between gap-3">
        <Button
          variant="light"
          className="text-theme-muted"
          onPress={onBack}
          startContent={<ArrowLeft className="w-4 h-4" />}
        >
          {t('back')}
        </Button>

        <div className="flex items-center gap-3">
          {!isRequired && onSkip && (
            <Button
              variant="light"
              className="text-theme-subtle"
              onPress={onSkip}
              endContent={<SkipForward className="w-4 h-4" />}
            >
              {t('skip_for_now')}
            </Button>
          )}
          <Button
            className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-semibold shadow-lg shadow-emerald-500/20"
            onPress={handleSaveAndProceed}
            isLoading={saving}
            endContent={!saving ? <ArrowRight className="w-4 h-4" /> : undefined}
          >
            {anySelected ? t('safeguarding.save_continue') : t('next')}
          </Button>
        </div>
      </div>
    </div>
  );
}

export default SafeguardingStep;
