// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin page for configuring the onboarding module per tenant.
 *
 * Sections:
 *  1. Module Control — enabled/disabled, mandatory/optional
 *  2. Step Configuration — each step with enabled/required toggles
 *  3. Profile Requirements — avatar, bio, min bio length
 *  4. Listing Creation — mode selector, max auto-generated
 *  5. Visibility Gating — completion/avatar/bio required for public profile
 *  6. Safeguarding — country preset, options management link
 *  7. Custom Text — welcome text, help text
 *
 * Route: /admin/onboarding-settings
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Switch,
  Button,
  Spinner,
  Select,
  SelectItem,
  Input,
  Textarea,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@heroui/react';
import {
  Save,
  Sparkles,
  UserCircle,
  Heart,
  HandHeart,
  Shield,
  CheckCircle,
  Eye,
  ListChecks,
  FileText,
  Globe,
  AlertTriangle,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { PageHeader } from '../../components';

// ── Types ────────────────────────────────────────────────────────────────────

interface OnboardingConfig {
  enabled: boolean;
  mandatory: boolean;
  step_welcome_enabled: boolean;
  step_profile_enabled: boolean;
  step_profile_required: boolean;
  step_interests_enabled: boolean;
  step_interests_required: boolean;
  step_skills_enabled: boolean;
  step_skills_required: boolean;
  step_safeguarding_enabled: boolean;
  step_safeguarding_required: boolean;
  step_confirm_enabled: boolean;
  avatar_required: boolean;
  bio_required: boolean;
  bio_min_length: number;
  listing_creation_mode: string;
  listing_max_auto: number;
  require_completion_for_visibility: boolean;
  require_avatar_for_visibility: boolean;
  require_bio_for_visibility: boolean;
  welcome_text: string | null;
  help_text: string | null;
  safeguarding_intro_text: string | null;
  country_preset: string;
}

interface SafeguardingOption {
  id: number;
  option_key: string;
  option_type: string;
  label: string;
  description: string | null;
  is_active: boolean;
  is_required: boolean;
  triggers: Record<string, unknown> | null;
  preset_source: string | null;
}

// ── Constants ────────────────────────────────────────────────────────────────

const LISTING_MODES = [
  { key: 'disabled', label: 'Disabled (recommended)', description: 'No listings created during onboarding. Members create their own listings afterward.' },
  { key: 'suggestions_only', label: 'Suggestions only', description: 'Onboarding skills are shown as listing suggestions on the dashboard, but no listings are created.' },
  { key: 'draft', label: 'Draft (member reviews)', description: 'Listings created as drafts. Members must review and publish them.' },
  { key: 'pending_review', label: 'Pending review (admin approves)', description: 'Listings created but require admin/broker approval before publishing.' },
  { key: 'active', label: 'Active (not recommended)', description: 'Listings published immediately. May produce low-quality directory content.' },
];

const COUNTRY_PRESETS_MAP: Record<string, string> = {
  ireland: 'Ireland',
  england_wales: 'England & Wales',
  scotland: 'Scotland',
  northern_ireland: 'Northern Ireland',
  custom: 'Custom',
};

const STEPS_CONFIG = [
  { key: 'welcome', label: 'Welcome', icon: Sparkles, description: 'Introduction and benefits overview' },
  { key: 'profile', label: 'Profile', icon: UserCircle, description: 'Photo upload and bio (recommended: always required)' },
  { key: 'interests', label: 'Interests', icon: Heart, description: 'Category interest selection' },
  { key: 'skills', label: 'Skills', icon: HandHeart, description: 'Skill offers and needs' },
  { key: 'safeguarding', label: 'Support & Safeguarding', icon: Shield, description: 'Safeguarding preferences and support needs' },
  { key: 'confirm', label: 'Confirm', icon: CheckCircle, description: 'Review and complete onboarding' },
];

// ── Component ────────────────────────────────────────────────────────────────

export function OnboardingSettings() {
  usePageTitle('Onboarding Settings');
  const toast = useToast();
  const { tenant } = useTenant();

  const [config, setConfig] = useState<OnboardingConfig | null>(null);
  const [safeguardingOptions, setSafeguardingOptions] = useState<SafeguardingOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [applyingPreset, setApplyingPreset] = useState(false);

  const presetModal = useDisclosure();

  // ── Fetch config on mount ────────────────────────────────────────────────

  const fetchConfig = useCallback(async () => {
    try {
      setLoading(true);
      const configRes = await api.get<{ config: OnboardingConfig; safeguarding_options: SafeguardingOption[] }>('/v2/admin/config/onboarding');

      if (configRes.success && configRes.data) {
        const data = configRes.data;
        setConfig(data.config);
        setSafeguardingOptions(data.safeguarding_options ?? []);
      }
    } catch (error) {
      logError('Failed to load onboarding config', error);
      toast.error('Failed to load settings', 'Please try again');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => { fetchConfig(); }, [fetchConfig]);

  // ── Save handler ─────────────────────────────────────────────────────────

  const handleSave = useCallback(async () => {
    if (!config) return;
    try {
      setSaving(true);
      const res = await api.put('/v2/admin/config/onboarding', config);
      if (res.success) {
        toast.success('Settings saved', 'Onboarding configuration updated');
      } else {
        toast.error('Save failed', res.error || 'Please try again');
      }
    } catch (error) {
      logError('Failed to save onboarding config', error);
      toast.error('Save failed', 'Please try again');
    } finally {
      setSaving(false);
    }
  }, [config, toast]);

  // ── Apply preset handler ─────────────────────────────────────────────────

  const handleApplyPreset = useCallback(async () => {
    if (!config) return;
    try {
      setApplyingPreset(true);
      const res = await api.post<{ options_created: string[] }>('/v2/admin/config/onboarding/apply-preset', {
        preset: config.country_preset,
      });
      if (res.success) {
        const created = res.data?.options_created ?? [];
        if (created.length > 0) {
          toast.success('Preset applied', `Created ${created.length} safeguarding option(s)`);
        } else {
          toast.info('No changes', 'All options from this preset already exist');
        }
        presetModal.onClose();
        fetchConfig(); // Reload to show new options
      } else {
        toast.error('Failed to apply preset', res.error || 'Please try again');
      }
    } catch (error) {
      logError('Failed to apply preset', error);
      toast.error('Failed to apply preset', 'Please try again');
    } finally {
      setApplyingPreset(false);
    }
  }, [config, toast, fetchConfig, presetModal]);

  // ── Helpers ──────────────────────────────────────────────────────────────

  const updateConfig = useCallback((key: keyof OnboardingConfig, value: unknown) => {
    setConfig(prev => prev ? { ...prev, [key]: value } : prev);
  }, []);

  // ── Loading state ────────────────────────────────────────────────────────

  if (loading || !config) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" />
      </div>
    );
  }

  const activeOptionCount = safeguardingOptions.filter(o => o.is_active).length;

  return (
    <div>
      <PageHeader
        title="Onboarding Settings"
        description={`Configure the onboarding wizard for ${tenant?.name || 'your community'}`}
      />

      <div className="space-y-6">

        {/* ─── Section 1: Module Control ─── */}
        <Card shadow="sm">
          <CardHeader className="flex flex-col items-start gap-1 pb-0">
            <h3 className="text-lg font-semibold">Module Control</h3>
            <p className="text-sm text-theme-muted">Enable or disable the onboarding wizard</p>
          </CardHeader>
          <CardBody className="gap-4">
            <Switch
              isSelected={config.enabled}
              onValueChange={(v) => updateConfig('enabled', v)}
            >
              <div>
                <p className="font-medium">Onboarding enabled</p>
                <p className="text-xs text-theme-muted">Show the onboarding wizard to new members</p>
              </div>
            </Switch>
            <Switch
              isSelected={config.mandatory}
              onValueChange={(v) => updateConfig('mandatory', v)}
              isDisabled={!config.enabled}
            >
              <div>
                <p className="font-medium">Onboarding mandatory</p>
                <p className="text-xs text-theme-muted">Members must complete onboarding before accessing the platform</p>
              </div>
            </Switch>
          </CardBody>
        </Card>

        {/* ─── Section 2: Step Configuration ─── */}
        <Card shadow="sm">
          <CardHeader className="flex flex-col items-start gap-1 pb-0">
            <h3 className="text-lg font-semibold">Step Configuration</h3>
            <p className="text-sm text-theme-muted">Choose which steps appear in the onboarding wizard</p>
          </CardHeader>
          <CardBody>
            <div className="space-y-3">
              {STEPS_CONFIG.map((step) => {
                const enabledKey = `step_${step.key}_enabled` as keyof OnboardingConfig;
                const requiredKey = `step_${step.key}_required` as keyof OnboardingConfig;
                const Icon = step.icon;
                const isEnabled = config[enabledKey] as boolean;
                const hasRequired = requiredKey in config;

                return (
                  <div key={step.key} className="flex items-center justify-between p-3 rounded-lg bg-theme-elevated">
                    <div className="flex items-center gap-3">
                      <Icon className="w-5 h-5 text-theme-muted" />
                      <div>
                        <p className="font-medium text-sm">{step.label}</p>
                        <p className="text-xs text-theme-muted">{step.description}</p>
                      </div>
                    </div>
                    <div className="flex items-center gap-4">
                      <Switch
                        size="sm"
                        isSelected={isEnabled}
                        onValueChange={(v) => updateConfig(enabledKey, v)}
                        aria-label={`Enable ${step.label} step`}
                      >
                        <span className="text-xs">Enabled</span>
                      </Switch>
                      {hasRequired && (
                        <Switch
                          size="sm"
                          isSelected={config[requiredKey] as boolean}
                          onValueChange={(v) => updateConfig(requiredKey, v)}
                          isDisabled={!isEnabled}
                          aria-label={`Require ${step.label} step`}
                        >
                          <span className="text-xs">Required</span>
                        </Switch>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </CardBody>
        </Card>

        {/* ─── Section 3: Profile Requirements ─── */}
        <Card shadow="sm">
          <CardHeader className="flex flex-col items-start gap-1 pb-0">
            <h3 className="text-lg font-semibold">Profile Requirements</h3>
            <p className="text-sm text-theme-muted">Set minimum profile standards for new members</p>
          </CardHeader>
          <CardBody className="gap-4">
            <Switch
              isSelected={config.avatar_required}
              onValueChange={(v) => updateConfig('avatar_required', v)}
            >
              <div>
                <p className="font-medium">Require profile photo</p>
                <p className="text-xs text-theme-muted">Members must upload a photo before completing onboarding</p>
              </div>
            </Switch>
            <Switch
              isSelected={config.bio_required}
              onValueChange={(v) => updateConfig('bio_required', v)}
            >
              <div>
                <p className="font-medium">Require bio</p>
                <p className="text-xs text-theme-muted">Members must write a bio before completing onboarding</p>
              </div>
            </Switch>
            <Input
              type="number"
              label="Minimum bio length"
              value={String(config.bio_min_length)}
              onValueChange={(v) => updateConfig('bio_min_length', parseInt(v) || 0)}
              variant="bordered"
              min={0}
              max={500}
              description="Minimum number of characters required in the bio"
              isDisabled={!config.bio_required}
              className="max-w-xs"
            />
          </CardBody>
        </Card>

        {/* ─── Section 4: Listing Creation ─── */}
        <Card shadow="sm">
          <CardHeader className="flex flex-col items-start gap-1 pb-0">
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <ListChecks className="w-5 h-5" />
              Listing Creation
            </h3>
            <p className="text-sm text-theme-muted">Control how listings are created from onboarding skill selections</p>
          </CardHeader>
          <CardBody className="gap-4">
            <Select
              label="Listing creation mode"
              selectedKeys={[config.listing_creation_mode]}
              onSelectionChange={(keys) => {
                const key = Array.from(keys)[0] as string;
                updateConfig('listing_creation_mode', key);
              }}
              variant="bordered"
              description={LISTING_MODES.find(m => m.key === config.listing_creation_mode)?.description}
            >
              {LISTING_MODES.map((mode) => (
                <SelectItem key={mode.key} textValue={mode.label}>
                  <div>
                    <p className="font-medium">{mode.label}</p>
                    <p className="text-xs text-default-500">{mode.description}</p>
                  </div>
                </SelectItem>
              ))}
            </Select>
            {config.listing_creation_mode !== 'disabled' && config.listing_creation_mode !== 'suggestions_only' && (
              <Input
                type="number"
                label="Max auto-generated listings"
                value={String(config.listing_max_auto)}
                onValueChange={(v) => updateConfig('listing_max_auto', Math.min(10, Math.max(0, parseInt(v) || 0)))}
                variant="bordered"
                min={0}
                max={10}
                description="Maximum listings auto-created per member (0-10)"
                className="max-w-xs"
              />
            )}
          </CardBody>
        </Card>

        {/* ─── Section 5: Public Visibility Gating ─── */}
        <Card shadow="sm">
          <CardHeader className="flex flex-col items-start gap-1 pb-0">
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Eye className="w-5 h-5" />
              Public Visibility Gating
            </h3>
            <p className="text-sm text-theme-muted">Control when member profiles become publicly visible</p>
          </CardHeader>
          <CardBody className="gap-4">
            <Switch
              isSelected={config.require_completion_for_visibility}
              onValueChange={(v) => updateConfig('require_completion_for_visibility', v)}
            >
              <div>
                <p className="font-medium">Require onboarding completion</p>
                <p className="text-xs text-theme-muted">Profile hidden from directory until onboarding is complete</p>
              </div>
            </Switch>
            <Switch
              isSelected={config.require_avatar_for_visibility}
              onValueChange={(v) => updateConfig('require_avatar_for_visibility', v)}
            >
              <div>
                <p className="font-medium">Require avatar for visibility</p>
                <p className="text-xs text-theme-muted">Profile hidden from directory until a photo is uploaded</p>
              </div>
            </Switch>
            <Switch
              isSelected={config.require_bio_for_visibility}
              onValueChange={(v) => updateConfig('require_bio_for_visibility', v)}
            >
              <div>
                <p className="font-medium">Require bio for visibility</p>
                <p className="text-xs text-theme-muted">Profile hidden from directory until a bio is written</p>
              </div>
            </Switch>
          </CardBody>
        </Card>

        {/* ─── Section 6: Safeguarding Configuration ─── */}
        <Card shadow="sm">
          <CardHeader className="flex flex-col items-start gap-1 pb-0">
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Shield className="w-5 h-5" />
              Safeguarding Configuration
            </h3>
            <p className="text-sm text-theme-muted">Configure safeguarding options shown during onboarding</p>
          </CardHeader>
          <CardBody className="gap-4">
            {/* Country Preset */}
            <div className="flex items-end gap-3">
              <Select
                label="Country preset"
                selectedKeys={[config.country_preset]}
                onSelectionChange={(keys) => {
                  const key = Array.from(keys)[0] as string;
                  updateConfig('country_preset', key);
                }}
                variant="bordered"
                description="Select a country to load pre-configured safeguarding terminology"
                className="max-w-xs"
              >
                {Object.entries(COUNTRY_PRESETS_MAP).map(([key, label]) => (
                  <SelectItem key={key} textValue={label}>
                    <div className="flex items-center gap-2">
                      <Globe className="w-4 h-4" />
                      {label}
                    </div>
                  </SelectItem>
                ))}
              </Select>
              <Button
                color="secondary"
                variant="flat"
                onPress={presetModal.onOpen}
                isDisabled={config.country_preset === 'custom'}
              >
                Apply Preset
              </Button>
            </div>

            {/* Legal warning */}
            <div className="flex items-start gap-2 p-3 rounded-lg bg-warning-50 dark:bg-warning-950/20 border border-warning-200 dark:border-warning-800">
              <AlertTriangle className="w-5 h-5 text-warning-600 flex-shrink-0 mt-0.5" />
              <div className="text-sm">
                <p className="font-medium text-warning-700 dark:text-warning-400">Legal notice</p>
                <p className="text-warning-600 dark:text-warning-500 mt-1">
                  Requiring vetting (DBS, Garda, PVG, etc.) may be unlawful for roles that do not constitute regulated activity.
                  Consult legal advice before enabling vetting requirements. This platform supports compliance but does not determine legal compliance.
                </p>
              </div>
            </div>

            {/* Current options summary */}
            <div className="p-4 rounded-lg bg-theme-elevated">
              <div className="flex items-center justify-between mb-2">
                <p className="font-medium text-sm">Configured safeguarding options</p>
                <Chip size="sm" variant="flat" color={activeOptionCount > 0 ? 'success' : 'default'}>
                  {activeOptionCount} active
                </Chip>
              </div>
              {safeguardingOptions.filter(o => o.is_active).length > 0 ? (
                <div className="space-y-1.5">
                  {safeguardingOptions.filter(o => o.is_active).map((opt) => (
                    <div key={opt.id} className="flex items-center gap-2 text-sm">
                      <CheckCircle className="w-3.5 h-3.5 text-success-500" />
                      <span>{opt.label}</span>
                      {opt.triggers && Object.values(opt.triggers).some(Boolean) && (
                        <Chip size="sm" variant="flat" color="warning" className="text-xs">
                          has triggers
                        </Chip>
                      )}
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-theme-muted">No safeguarding options configured. Apply a country preset or add custom options.</p>
              )}
              <Button
                size="sm"
                variant="light"
                color="primary"
                className="mt-3"
                onPress={() => {
                  window.location.href = '/admin/safeguarding-options';
                }}
              >
                Manage Safeguarding Options
              </Button>
            </div>

            {/* Safeguarding intro text */}
            <Textarea
              label="Safeguarding intro text"
              value={config.safeguarding_intro_text || ''}
              onValueChange={(v) => updateConfig('safeguarding_intro_text', v || null)}
              variant="bordered"
              placeholder="We want to make sure everyone feels safe in our community. Let us know if you'd like additional support..."
              description="Custom intro text shown at the top of the safeguarding step. Leave blank for default."
              minRows={2}
              maxRows={5}
            />
          </CardBody>
        </Card>

        {/* ─── Section 7: Custom Text ─── */}
        <Card shadow="sm">
          <CardHeader className="flex flex-col items-start gap-1 pb-0">
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <FileText className="w-5 h-5" />
              Custom Text
            </h3>
            <p className="text-sm text-theme-muted">Customise text shown in the onboarding wizard</p>
          </CardHeader>
          <CardBody className="gap-4">
            <Textarea
              label="Welcome text"
              value={config.welcome_text || ''}
              onValueChange={(v) => updateConfig('welcome_text', v || null)}
              variant="bordered"
              placeholder="Leave blank for default welcome message"
              description="Custom welcome message shown on the first step"
              minRows={2}
              maxRows={4}
            />
            <Textarea
              label="Help text"
              value={config.help_text || ''}
              onValueChange={(v) => updateConfig('help_text', v || null)}
              variant="bordered"
              placeholder="Leave blank for default help text"
              description="Help text shown throughout the onboarding process"
              minRows={2}
              maxRows={4}
            />
          </CardBody>
        </Card>

        {/* ─── Save Button ─── */}
        <div className="flex justify-end">
          <Button
            color="primary"
            size="lg"
            startContent={!saving ? <Save className="w-5 h-5" /> : undefined}
            onPress={handleSave}
            isLoading={saving}
          >
            Save Settings
          </Button>
        </div>
      </div>

      {/* ─── Preset Confirmation Modal ─── */}
      <Modal isOpen={presetModal.isOpen} onClose={presetModal.onClose}>
        <ModalContent>
          <ModalHeader>Apply Country Preset</ModalHeader>
          <ModalBody>
            <p>
              This will create default safeguarding options for <strong>{COUNTRY_PRESETS_MAP[config.country_preset] || config.country_preset}</strong> with
              country-appropriate terminology.
            </p>
            <p className="text-sm text-theme-muted mt-2">
              Existing custom options will not be overwritten. You can edit or remove any option after applying the preset.
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={presetModal.onClose}>
              Cancel
            </Button>
            <Button
              color="primary"
              onPress={handleApplyPreset}
              isLoading={applyingPreset}
            >
              Apply Preset
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default OnboardingSettings;
