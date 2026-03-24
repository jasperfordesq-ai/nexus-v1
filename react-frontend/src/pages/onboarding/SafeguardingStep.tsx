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

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Checkbox,
  Spinner,
} from '@heroui/react';
import {
  Shield,
  ArrowRight,
  ArrowLeft,
  SkipForward,
  ExternalLink,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useToast } from '@/contexts';
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

  const [options, setOptions] = useState<SafeguardingOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [selections, setSelections] = useState<Record<number, boolean>>({});

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
      toast.error('Failed to load options', 'Please try again');
    } finally {
      setLoading(false);
    }
  }, [toast]);

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
        'Required options',
        `Please respond to: ${unmetRequired.map(o => o.label).join(', ')}`
      );
      return;
    }

    // Build preferences array — only send selected options
    const selectedPrefs = Object.entries(selections)
      .filter(([, selected]) => selected)
      .map(([optionId]) => ({
        option_id: parseInt(optionId),
        value: '1',
      }));

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
          toast.error('Save failed', res.error || 'Please try again');
          return;
        }
      } catch (error) {
        logError('Failed to save safeguarding preferences', error);
        toast.error('Save failed', 'Please try again');
        return;
      } finally {
        setSaving(false);
      }
    }

    onNext();
  }, [selections, options, isRequired, onNext, toast]);

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
          <p className="text-theme-muted">No safeguarding options have been configured for this community.</p>
        </GlassCard>
        <div className="flex items-center justify-between gap-3">
          <Button variant="light" className="text-theme-muted" onPress={onBack}
            startContent={<ArrowLeft className="w-4 h-4" />}
          >
            Back
          </Button>
          <Button
            className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-semibold"
            onPress={onNext}
            endContent={<ArrowRight className="w-4 h-4" />}
          >
            Continue
          </Button>
        </div>
      </div>
    );
  }

  const anySelected = Object.values(selections).some(Boolean);

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
              'We want to make sure everyone feels safe in our community. The information below helps our coordinators arrange safe and appropriate exchanges for you. Your responses are confidential and only visible to community coordinators — never on your public profile.'
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
              role="button"
              tabIndex={0}
              onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleOption(option.id); } }}
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
            <span className="text-danger-500">*</span> Required fields
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
          Back
        </Button>

        <div className="flex items-center gap-3">
          {!isRequired && onSkip && (
            <Button
              variant="light"
              className="text-theme-subtle"
              onPress={onSkip}
              endContent={<SkipForward className="w-4 h-4" />}
            >
              Skip for now
            </Button>
          )}
          <Button
            className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-semibold shadow-lg shadow-emerald-500/20"
            onPress={handleSaveAndProceed}
            isLoading={saving}
            endContent={!saving ? <ArrowRight className="w-4 h-4" /> : undefined}
          >
            {anySelected ? 'Save & Continue' : 'Continue'}
          </Button>
        </div>
      </div>
    </div>
  );
}

export default SafeguardingStep;
