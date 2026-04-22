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
import Save from 'lucide-react/icons/save';
import Sparkles from 'lucide-react/icons/sparkles';
import UserCircle from 'lucide-react/icons/circle-user';
import Heart from 'lucide-react/icons/heart';
import HandHeart from 'lucide-react/icons/hand-heart';
import Shield from 'lucide-react/icons/shield';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import Eye from 'lucide-react/icons/eye';
import ListChecks from 'lucide-react/icons/list-checks';
import FileText from 'lucide-react/icons/file-text';
import Globe from 'lucide-react/icons/globe';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
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

const COUNTRY_PRESET_LABEL_KEYS: Record<string, string> = {
  ireland: 'system.onboarding.preset_ireland',
  england_wales: 'system.onboarding.preset_england_wales',
  scotland: 'system.onboarding.preset_scotland',
  northern_ireland: 'system.onboarding.preset_northern_ireland',
  custom: 'system.onboarding.preset_custom',
};
const DEFAULT_COUNTRY_PRESET_LABEL_KEY = 'system.onboarding.preset_custom';

const STEP_ICONS: Record<string, typeof Sparkles> = {
  welcome: Sparkles,
  profile: UserCircle,
  interests: Heart,
  skills: HandHeart,
  safeguarding: Shield,
  confirm: CheckCircle,
};

// ── Component ────────────────────────────────────────────────────────────────

export function OnboardingSettings() {
  const { t } = useTranslation('admin');
  usePageTitle("System");
  const toast = useToast();
  const { tenant, tenantPath } = useTenant();
  const navigate = useNavigate();
  const getCountryPresetLabel = (preset: string) =>
    t(COUNTRY_PRESET_LABEL_KEYS[preset] ?? DEFAULT_COUNTRY_PRESET_LABEL_KEY);

  const [config, setConfig] = useState<OnboardingConfig | null>(null);
  const [safeguardingOptions, setSafeguardingOptions] = useState<SafeguardingOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [applyingPreset, setApplyingPreset] = useState(false);

  const presetModal = useDisclosure();

  const LISTING_MODES = [
    { key: 'disabled', label: "Disabled (no listings during onboarding)", description: "Members cannot create listings during onboarding" },
    { key: 'suggestions_only', label: "Suggestions (show templates)", description: "Members are shown suggested listing templates to choose from" },
    { key: 'draft', label: "Draft (admin review required)", description: "New listings are saved as drafts for admin review" },
    { key: 'pending_review', label: "Pending (awaiting approval)", description: "New listings are held as pending until approved by an admin" },
    { key: 'active', label: "Active (published immediately)", description: "New listings are published immediately as active" },
  ];

  const STEPS_CONFIG = [
    { key: 'welcome', label: "Step Welcome", description: "Welcome step introducing members to the platform" },
    { key: 'profile', label: "Step Profile", description: "Step where members fill in their profile information" },
    { key: 'interests', label: "Step Interests", description: "Step where members choose their interests and categories" },
    { key: 'skills', label: "Step Skills", description: "Step where members add their skills and what they can offer" },
    { key: 'safeguarding', label: "Step Safeguarding", description: "Step where members complete safeguarding declarations" },
    { key: 'confirm', label: "Step Confirm", description: "Final confirmation step where members review and submit their profile" },
  ];

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
      toast.error("Failed to load", "Please try again");
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
        toast.success("Settings Saved", "Config Updated");
      } else {
        toast.error("Save Failed", res.error || "Please try again");
      }
    } catch (error) {
      logError('Failed to save onboarding config', error);
      toast.error("Save Failed", "Please try again");
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
          toast.success("Preset Applied", `Preset applied and options created`);
        } else {
          toast.info("No changes", "A preset with this name already exists");
        }
        presetModal.onClose();
        fetchConfig();
      } else {
        toast.error("Failed to apply preset", res.error || "Please try again");
      }
    } catch (error) {
      logError('Failed to apply preset', error);
      toast.error("Failed to apply preset", "Please try again");
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
        title={"System"}
        description={`Configure the onboarding flow for new members`}
      />

      <div className="space-y-6">

        {/* Section 1: Module Control */}
        <Card shadow="sm">
          <CardHeader className="flex flex-col items-start gap-1 pb-0">
            <h3 className="text-lg font-semibold">{"Module Control"}</h3>
            <p className="text-sm text-theme-muted">{"Control which modules are available during the onboarding flow"}</p>
          </CardHeader>
          <CardBody className="gap-4">
            <Switch isSelected={config.enabled} onValueChange={(v) => updateConfig('enabled', v)}>
              <div>
                <p className="font-medium">{"Onboarding Enabled"}</p>
                <p className="text-xs text-theme-muted">{"Enable or disable the guided onboarding flow for new members"}</p>
              </div>
            </Switch>
            <Switch isSelected={config.mandatory} onValueChange={(v) => updateConfig('mandatory', v)} isDisabled={!config.enabled}>
              <div>
                <p className="font-medium">{"Onboarding Mandatory"}</p>
                <p className="text-xs text-theme-muted">{"Require new members to complete onboarding before accessing the platform"}</p>
              </div>
            </Switch>
          </CardBody>
        </Card>

        {/* Section 2: Step Configuration */}
        <Card shadow="sm">
          <CardHeader className="flex flex-col items-start gap-1 pb-0">
            <h3 className="text-lg font-semibold">{"Step Configuration"}</h3>
            <p className="text-sm text-theme-muted">{"Configure the steps shown in the onboarding wizard"}</p>
          </CardHeader>
          <CardBody>
            <div className="space-y-3">
              {STEPS_CONFIG.map((step) => {
                const enabledKey = `step_${step.key}_enabled` as keyof OnboardingConfig;
                const requiredKey = `step_${step.key}_required` as keyof OnboardingConfig;
                const Icon = STEP_ICONS[step.key] || Sparkles;
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
                      <Switch size="sm" isSelected={isEnabled} onValueChange={(v) => updateConfig(enabledKey, v)} aria-label={`Enable Step`}>
                        <span className="text-xs">{"Enabled"}</span>
                      </Switch>
                      {hasRequired && (
                        <Switch size="sm" isSelected={config[requiredKey] as boolean} onValueChange={(v) => updateConfig(requiredKey, v)} isDisabled={!isEnabled} aria-label={`Require Step`}>
                          <span className="text-xs">{"Required"}</span>
                        </Switch>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </CardBody>
        </Card>

        {/* Section 3: Profile Requirements */}
        <Card shadow="sm">
          <CardHeader className="flex flex-col items-start gap-1 pb-0">
            <h3 className="text-lg font-semibold">{"Profile Requirements"}</h3>
            <p className="text-sm text-theme-muted">{"Set the minimum profile requirements members must complete"}</p>
          </CardHeader>
          <CardBody className="gap-4">
            <Switch isSelected={config.avatar_required} onValueChange={(v) => updateConfig('avatar_required', v)}>
              <div>
                <p className="font-medium">{"Require Photo"}</p>
                <p className="text-xs text-theme-muted">{"Require members to upload a profile photo before continuing"}</p>
              </div>
            </Switch>
            <Switch isSelected={config.bio_required} onValueChange={(v) => updateConfig('bio_required', v)}>
              <div>
                <p className="font-medium">{"Require Bio"}</p>
                <p className="text-xs text-theme-muted">{"Require members to fill in their bio before continuing"}</p>
              </div>
            </Switch>
            <Input type="number" label={"Min Bio Length"} value={String(config.bio_min_length)} onValueChange={(v) => updateConfig('bio_min_length', parseInt(v) || 0)} variant="bordered" min={0} max={500} description={"Minimum number of characters required in the member bio"} isDisabled={!config.bio_required} className="max-w-xs" />
          </CardBody>
        </Card>

        {/* Section 4: Listing Creation */}
        <Card shadow="sm">
          <CardHeader className="flex flex-col items-start gap-1 pb-0">
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <ListChecks className="w-5 h-5" />
              {"Listing Creation"}
            </h3>
            <p className="text-sm text-theme-muted">{"Configure when and how new listings are published during onboarding"}</p>
          </CardHeader>
          <CardBody className="gap-4">
            <Select label={"Listing Creation Mode"} selectedKeys={[config.listing_creation_mode]} onSelectionChange={(keys) => { const key = Array.from(keys)[0] as string; updateConfig('listing_creation_mode', key); }} variant="bordered" description={LISTING_MODES.find(m => m.key === config.listing_creation_mode)?.description}>
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
              <Input type="number" label={"Max Auto Listings"} value={String(config.listing_max_auto)} onValueChange={(v) => updateConfig('listing_max_auto', Math.min(10, Math.max(0, parseInt(v) || 0)))} variant="bordered" min={0} max={10} description={"Maximum number of listings that can be auto-created during onboarding"} className="max-w-xs" />
            )}
          </CardBody>
        </Card>

        {/* Section 5: Public Visibility Gating */}
        <Card shadow="sm">
          <CardHeader className="flex flex-col items-start gap-1 pb-0">
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Eye className="w-5 h-5" />
              {"Visibility Gating"}
            </h3>
            <p className="text-sm text-theme-muted">{"Control what content members can see before completing onboarding"}</p>
          </CardHeader>
          <CardBody className="gap-4">
            <Switch isSelected={config.require_completion_for_visibility} onValueChange={(v) => updateConfig('require_completion_for_visibility', v)}>
              <div>
                <p className="font-medium">{"Require Completion"}</p>
                <p className="text-xs text-theme-muted">{"Require members to fully complete this step before proceeding"}</p>
              </div>
            </Switch>
            <Switch isSelected={config.require_avatar_for_visibility} onValueChange={(v) => updateConfig('require_avatar_for_visibility', v)}>
              <div>
                <p className="font-medium">{"Require Avatar Visibility"}</p>
                <p className="text-xs text-theme-muted">{"Require members to set their avatar visibility preference"}</p>
              </div>
            </Switch>
            <Switch isSelected={config.require_bio_for_visibility} onValueChange={(v) => updateConfig('require_bio_for_visibility', v)}>
              <div>
                <p className="font-medium">{"Require Bio Visibility"}</p>
                <p className="text-xs text-theme-muted">{"Require members to set their bio visibility preference"}</p>
              </div>
            </Switch>
          </CardBody>
        </Card>

        {/* Section 6: Safeguarding Configuration */}
        <Card shadow="sm">
          <CardHeader className="flex flex-col items-start gap-1 pb-0">
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Shield className="w-5 h-5" />
              {"Safeguarding Config"}
            </h3>
            <p className="text-sm text-theme-muted">{"Configure safeguarding questions and options shown during onboarding"}</p>
          </CardHeader>
          <CardBody className="gap-4">
            <div className="flex items-end gap-3">
              <Select label={"Country Preset"} selectedKeys={[config.country_preset]} onSelectionChange={(keys) => { const key = Array.from(keys)[0] as string; updateConfig('country_preset', key); }} variant="bordered" description={"Apply a country preset to pre-fill registration policy settings"} className="max-w-xs">
                {Object.keys(COUNTRY_PRESET_LABEL_KEYS).map((key) => {
                  const label = getCountryPresetLabel(key);
                  return (
                    <SelectItem key={key} textValue={label}>
                      <div className="flex items-center gap-2">
                        <Globe className="w-4 h-4" />
                        {label}
                      </div>
                    </SelectItem>
                  );
                })}
              </Select>
              <Button color="secondary" variant="flat" onPress={presetModal.onOpen} isDisabled={config.country_preset === 'custom'}>
                {"Apply Preset"}
              </Button>
            </div>

            <div className="flex items-start gap-2 p-3 rounded-lg bg-warning-50 dark:bg-warning-950/20 border border-warning-200 dark:border-warning-800">
              <AlertTriangle className="w-5 h-5 text-warning-600 flex-shrink-0 mt-0.5" />
              <div className="text-sm">
                <p className="font-medium text-warning-700 dark:text-warning-400">{"Legal Notice"}</p>
                <p className="text-warning-600 dark:text-warning-500 mt-1">{"Legal notice text shown to members during this onboarding step"}</p>
              </div>
            </div>

            <div className="p-4 rounded-lg bg-theme-elevated">
              <div className="flex items-center justify-between mb-2">
                <p className="font-medium text-sm">{"Configured Options"}</p>
                <Chip size="sm" variant="flat" color={activeOptionCount > 0 ? 'success' : 'default'}>
                  {`${activeOptionCount} active`}
                </Chip>
              </div>
              {safeguardingOptions.filter(o => o.is_active).length > 0 ? (
                <div className="space-y-1.5">
                  {safeguardingOptions.filter(o => o.is_active).map((opt) => (
                    <div key={opt.id} className="flex items-center gap-2 text-sm">
                      <CheckCircle className="w-3.5 h-3.5 text-success-500" />
                      <span>{opt.label}</span>
                      {opt.triggers && Object.values(opt.triggers).some(Boolean) && (
                        <Chip size="sm" variant="flat" color="warning" className="text-xs">{"Has Triggers"}</Chip>
                      )}
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-theme-muted">{"No options configured"}</p>
              )}
              <Button size="sm" variant="light" color="primary" className="mt-3" onPress={() => { navigate(tenantPath('/admin/safeguarding-options')); }}>
                {"Manage Options"}
              </Button>
            </div>

            <Textarea label={"Safeguarding Intro"} value={config.safeguarding_intro_text || ''} onValueChange={(v) => updateConfig('safeguarding_intro_text', v || null)} variant="bordered" placeholder={"Safeguarding introduction..."} description={"Introductory text explaining safeguarding to new members"} minRows={2} maxRows={5} />
          </CardBody>
        </Card>

        {/* Section 7: Custom Text */}
        <Card shadow="sm">
          <CardHeader className="flex flex-col items-start gap-1 pb-0">
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <FileText className="w-5 h-5" />
              {"Custom Text"}
            </h3>
            <p className="text-sm text-theme-muted">{"Custom text to display to members during this onboarding step"}</p>
          </CardHeader>
          <CardBody className="gap-4">
            <Textarea label={"Welcome Text"} value={config.welcome_text || ''} onValueChange={(v) => updateConfig('welcome_text', v || null)} variant="bordered" placeholder={"Welcome message..."} description={"Welcome message shown to new members at the start of onboarding"} minRows={2} maxRows={4} />
            <Textarea label={"Help Text"} value={config.help_text || ''} onValueChange={(v) => updateConfig('help_text', v || null)} variant="bordered" placeholder={"Help text..."} description={"Help text shown below the input during this onboarding step"} minRows={2} maxRows={4} />
          </CardBody>
        </Card>

        {/* Save Button */}
        <div className="flex justify-end">
          <Button color="primary" size="lg" startContent={!saving ? <Save className="w-5 h-5" /> : undefined} onPress={handleSave} isLoading={saving} isDisabled={saving}>
            {"Save Settings"}
          </Button>
        </div>
      </div>

      {/* Preset Confirmation Modal */}
      <Modal isOpen={presetModal.isOpen} onClose={presetModal.onClose}>
        <ModalContent>
          <ModalHeader>{"Apply Country Preset"}</ModalHeader>
          <ModalBody>
            <p>{`Are you sure you want to apply this preset? It will overwrite current settings.`}</p>
            <p className="text-sm text-theme-muted mt-2">{"Applying a preset will overwrite your current registration policy settings"}</p>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={presetModal.onClose}>{"Cancel"}</Button>
            <Button color="primary" onPress={handleApplyPreset} isLoading={applyingPreset} isDisabled={applyingPreset}>{"Apply Preset"}</Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default OnboardingSettings;
