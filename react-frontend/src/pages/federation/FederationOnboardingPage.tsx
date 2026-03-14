// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Onboarding Page - Step-by-step wizard for first-time federation setup
 *
 * 4-step wizard:
 *  1. Welcome    - Benefits overview
 *  2. Privacy    - Profile visibility switches
 *  3. Communication - Messaging, transactions, service reach
 *  4. Confirmation  - Summary + enable
 *
 * Route: /federation/onboarding
 */

import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Switch,
  Select,
  SelectItem,
  Input,
  Progress,
  Spinner,
  Chip,
} from '@heroui/react';
import {
  Globe,
  Users,
  ArrowRightLeft,
  ArrowRight,
  ArrowLeft,
  Eye,
  Search,
  Zap,
  MapPin,
  Star,
  Send,
  CreditCard,
  Mail,
  Shield,
  CheckCircle,
  Sparkles,
  HandHeart,
  Network,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { usePageTitle } from '@/hooks';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { FederationPartner } from '@/types/api';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

type ServiceReach = 'local_only' | 'remote_ok' | 'travel_ok';

interface OnboardingSettings {
  // Step 2 — Privacy
  profile_visible_federated: boolean;
  appear_in_federated_search: boolean;
  show_skills_federated: boolean;
  show_location_federated: boolean;
  show_reviews_federated: boolean;
  // Step 3 — Communication
  messaging_enabled_federated: boolean;
  transactions_enabled_federated: boolean;
  email_notifications: boolean;
  service_reach: ServiceReach;
  travel_radius_km: number;
}

const DEFAULT_SETTINGS: OnboardingSettings = {
  profile_visible_federated: true,
  appear_in_federated_search: true,
  show_skills_federated: true,
  show_location_federated: false,
  show_reviews_federated: true,
  messaging_enabled_federated: true,
  transactions_enabled_federated: true,
  email_notifications: true,
  service_reach: 'local_only',
  travel_radius_km: 25,
};

const TOTAL_STEPS = 4;

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function FederationOnboardingPage() {
  const { t } = useTranslation('federation');
  usePageTitle(t('onboarding.page_title'));
  const navigate = useNavigate();
  useAuth(); // Ensure authenticated
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [currentStep, setCurrentStep] = useState(1);
  const [settings, setSettings] = useState<OnboardingSettings>(DEFAULT_SETTINGS);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Step 4 — partner data
  const [partners, setPartners] = useState<FederationPartner[]>([]);
  const [partnersLoading, setPartnersLoading] = useState(false);

  // Load partners when reaching step 4
  useEffect(() => {
    if (currentStep === 4) {
      loadPartners();
    }
  }, [currentStep]);

  const loadPartners = useCallback(async () => {
    try {
      setPartnersLoading(true);
      const response = await api.get<FederationPartner[]>('/v2/federation/partners');
      if (response.success && response.data) {
        setPartners(Array.isArray(response.data) ? response.data : []);
      }
    } catch (error) {
      logError('Failed to load federation partners', error);
    } finally {
      setPartnersLoading(false);
    }
  }, []);

  const updateSetting = useCallback(<K extends keyof OnboardingSettings>(key: K, value: OnboardingSettings[K]) => {
    setSettings((prev) => ({ ...prev, [key]: value }));
  }, []);

  const goNext = useCallback(() => {
    setCurrentStep((s) => Math.min(s + 1, TOTAL_STEPS));
  }, []);

  const goBack = useCallback(() => {
    setCurrentStep((s) => Math.max(s - 1, 1));
  }, []);

  const handleComplete = useCallback(async () => {
    try {
      setIsSubmitting(true);

      // Single atomic request: opt in + save settings together
      const response = await api.post('/v2/federation/setup', settings);
      if (!response.success) {
        toast.error(t('onboarding.toast_setup_failed'), response.error || t('onboarding.toast_enable_error'));
        return;
      }

      toast.success(t('onboarding.toast_enabled'), t('onboarding.toast_welcome'));
      navigate(tenantPath('/federation'));
    } catch (error) {
      logError('Failed to complete federation onboarding', error);
      toast.error(t('onboarding.toast_setup_failed'), t('onboarding.toast_generic_error'));
    } finally {
      setIsSubmitting(false);
    }
  }, [settings, toast, navigate, tenantPath, t]);

  const handleSkip = useCallback(() => {
    navigate(tenantPath('/federation'));
  }, [navigate, tenantPath]);

  // ───────────────────────────────────────────────────────────────────────────
  // Animation
  // ───────────────────────────────────────────────────────────────────────────

  const slideVariants = {
    enter: (direction: number) => ({
      x: direction > 0 ? 80 : -80,
      opacity: 0,
    }),
    center: { x: 0, opacity: 1 },
    exit: (direction: number) => ({
      x: direction < 0 ? 80 : -80,
      opacity: 0,
    }),
  };

  // Track slide direction for animation
  const [slideDirection, setSlideDirection] = useState(1);

  const goNextAnimated = useCallback(() => {
    setSlideDirection(1);
    goNext();
  }, [goNext]);

  const goBackAnimated = useCallback(() => {
    setSlideDirection(-1);
    goBack();
  }, [goBack]);

  // HeroUI shared classNames
  const selectClassNames = {
    trigger: 'bg-theme-elevated border-theme-default',
    value: 'text-theme-primary',
    label: 'text-theme-muted',
  };

  const inputClassNames = {
    input: 'bg-transparent text-theme-primary',
    inputWrapper: 'bg-theme-elevated border-theme-default',
    label: 'text-theme-muted',
  };

  // ───────────────────────────────────────────────────────────────────────────
  // Render
  // ───────────────────────────────────────────────────────────────────────────

  return (
    <div className="max-w-2xl mx-auto py-6 space-y-6">
      {/* Title */}
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        className="text-center"
      >
        <h1 className="text-2xl font-bold text-theme-primary">{t('onboarding.title')}</h1>
        <p className="text-theme-muted mt-1">{t('onboarding.subtitle')}</p>
      </motion.div>

      {/* Progress Bar */}
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ delay: 0.1 }}
      >
        <div className="flex items-center justify-between mb-2">
          <span className="text-sm text-theme-subtle">{t('onboarding.step_of', { current: currentStep, total: TOTAL_STEPS })}</span>
          <span className="text-sm text-theme-subtle">
            {currentStep === 1 && t('onboarding.step_welcome')}
            {currentStep === 2 && t('onboarding.step_privacy')}
            {currentStep === 3 && t('onboarding.step_communication')}
            {currentStep === 4 && t('onboarding.step_confirm')}
          </span>
        </div>
        <Progress
          value={(currentStep / TOTAL_STEPS) * 100}
          classNames={{
            indicator: 'bg-gradient-to-r from-indigo-500 to-purple-600',
            track: 'bg-theme-elevated',
          }}
          aria-label={`Step ${currentStep} of ${TOTAL_STEPS}`}
        />
      </motion.div>

      {/* Step Content */}
      <AnimatePresence mode="wait" custom={slideDirection}>
        <motion.div
          key={currentStep}
          custom={slideDirection}
          variants={slideVariants}
          initial="enter"
          animate="center"
          exit="exit"
          transition={{ duration: 0.25, ease: 'easeInOut' }}
        >
          {/* ─── Step 1: Welcome ─── */}
          {currentStep === 1 && (
            <div className="space-y-6">
              {/* Hero icons */}
              <GlassCard className="p-8 text-center">
                <div className="flex items-center justify-center gap-4 mb-6">
                  <motion.div
                    animate={{ y: [0, -6, 0] }}
                    transition={{ repeat: Infinity, duration: 3, ease: 'easeInOut' }}
                    className="p-4 rounded-2xl bg-indigo-500/20"
                  >
                    <Globe className="w-10 h-10 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                  </motion.div>
                  <motion.div
                    animate={{ y: [0, -6, 0] }}
                    transition={{ repeat: Infinity, duration: 3, ease: 'easeInOut', delay: 0.4 }}
                    className="p-4 rounded-2xl bg-purple-500/20"
                  >
                    <Users className="w-10 h-10 text-purple-600 dark:text-purple-400" aria-hidden="true" />
                  </motion.div>
                  <motion.div
                    animate={{ y: [0, -6, 0] }}
                    transition={{ repeat: Infinity, duration: 3, ease: 'easeInOut', delay: 0.8 }}
                    className="p-4 rounded-2xl bg-emerald-500/20"
                  >
                    <ArrowRightLeft className="w-10 h-10 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                  </motion.div>
                </div>

                <h2 className="text-xl font-bold text-theme-primary mb-2">
                  {t('onboarding.welcome_title')}
                </h2>
                <p className="text-theme-muted max-w-md mx-auto">
                  {t('onboarding.welcome_description')}
                </p>
              </GlassCard>

              {/* Benefit cards */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <BenefitCard
                  icon={<Sparkles className="w-6 h-6 text-indigo-500" />}
                  title={t('onboarding.benefit_discover_title')}
                  description={t('onboarding.benefit_discover_description')}
                />
                <BenefitCard
                  icon={<HandHeart className="w-6 h-6 text-purple-500" />}
                  title={t('onboarding.benefit_meet_title')}
                  description={t('onboarding.benefit_meet_description')}
                />
                <BenefitCard
                  icon={<Network className="w-6 h-6 text-emerald-500" />}
                  title={t('onboarding.benefit_exchange_title')}
                  description={t('onboarding.benefit_exchange_description')}
                />
              </div>

              {/* CTA */}
              <div className="flex justify-center">
                <Button
                  size="lg"
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-8"
                  endContent={<ArrowRight className="w-5 h-5" aria-hidden="true" />}
                  onPress={goNextAnimated}
                >
                  {t('onboarding.get_started')}
                </Button>
              </div>
            </div>
          )}

          {/* ─── Step 2: Privacy Settings ─── */}
          {currentStep === 2 && (
            <div className="space-y-6">
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
                  <Eye className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                  {t('onboarding.profile_visibility')}
                </h2>
                <p className="text-theme-muted text-sm mb-6">
                  {t('onboarding.profile_visibility_description')}
                </p>

                <div className="space-y-1">
                  <OnboardingToggle
                    icon={<Globe className="w-4 h-4 text-indigo-500" />}
                    label={t('onboarding.toggle_profile_visible')}
                    description={t('onboarding.toggle_profile_visible_desc')}
                    checked={settings.profile_visible_federated}
                    onChange={(v) => updateSetting('profile_visible_federated', v)}
                  />
                  <OnboardingToggle
                    icon={<Search className="w-4 h-4 text-indigo-500" />}
                    label={t('onboarding.toggle_search_visible')}
                    description={t('onboarding.toggle_search_visible_desc')}
                    checked={settings.appear_in_federated_search}
                    onChange={(v) => updateSetting('appear_in_federated_search', v)}
                  />
                  <OnboardingToggle
                    icon={<Zap className="w-4 h-4 text-indigo-500" />}
                    label={t('onboarding.toggle_skills_shared')}
                    description={t('onboarding.toggle_skills_shared_desc')}
                    checked={settings.show_skills_federated}
                    onChange={(v) => updateSetting('show_skills_federated', v)}
                  />
                  <OnboardingToggle
                    icon={<MapPin className="w-4 h-4 text-indigo-500" />}
                    label={t('onboarding.toggle_location_shared')}
                    description={t('onboarding.toggle_location_shared_desc')}
                    checked={settings.show_location_federated}
                    onChange={(v) => updateSetting('show_location_federated', v)}
                  />
                  <OnboardingToggle
                    icon={<Star className="w-4 h-4 text-indigo-500" />}
                    label={t('onboarding.toggle_reviews_visible')}
                    description={t('onboarding.toggle_reviews_visible_desc')}
                    checked={settings.show_reviews_federated}
                    onChange={(v) => updateSetting('show_reviews_federated', v)}
                  />
                </div>
              </GlassCard>

              <StepNavigation
                onBack={goBackAnimated}
                onNext={goNextAnimated}
              />
            </div>
          )}

          {/* ─── Step 3: Communication Preferences ─── */}
          {currentStep === 3 && (
            <div className="space-y-6">
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
                  <Shield className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                  {t('onboarding.communication_preferences')}
                </h2>
                <p className="text-theme-muted text-sm mb-6">
                  {t('onboarding.communication_preferences_description')}
                </p>

                <div className="space-y-1">
                  <OnboardingToggle
                    icon={<Send className="w-4 h-4 text-indigo-500" />}
                    label={t('onboarding.toggle_messaging')}
                    description={t('onboarding.toggle_messaging_desc')}
                    checked={settings.messaging_enabled_federated}
                    onChange={(v) => updateSetting('messaging_enabled_federated', v)}
                  />
                  <OnboardingToggle
                    icon={<CreditCard className="w-4 h-4 text-indigo-500" />}
                    label={t('onboarding.toggle_transactions')}
                    description={t('onboarding.toggle_transactions_desc')}
                    checked={settings.transactions_enabled_federated}
                    onChange={(v) => updateSetting('transactions_enabled_federated', v)}
                  />
                  <OnboardingToggle
                    icon={<Mail className="w-4 h-4 text-indigo-500" />}
                    label={t('onboarding.toggle_email_notifications')}
                    description={t('onboarding.toggle_email_notifications_desc')}
                    checked={settings.email_notifications}
                    onChange={(v) => updateSetting('email_notifications', v)}
                  />
                </div>
              </GlassCard>

              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
                  <MapPin className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                  {t('onboarding.service_reach')}
                </h2>
                <p className="text-theme-muted text-sm mb-6">
                  {t('onboarding.service_reach_description')}
                </p>

                <div className="space-y-4">
                  <Select
                    label={t('onboarding.service_availability')}
                    selectedKeys={[settings.service_reach]}
                    onSelectionChange={(keys) => {
                      const value = Array.from(keys)[0] as string;
                      if (value) {
                        updateSetting('service_reach', value as ServiceReach);
                      }
                    }}
                    classNames={selectClassNames}
                  >
                    <SelectItem key="local_only">{t('onboarding.reach_local_only')}</SelectItem>
                    <SelectItem key="remote_ok">{t('onboarding.reach_remote_ok')}</SelectItem>
                    <SelectItem key="travel_ok">{t('onboarding.reach_travel_ok')}</SelectItem>
                  </Select>

                  {settings.service_reach === 'travel_ok' && (
                    <motion.div
                      initial={{ opacity: 0, height: 0 }}
                      animate={{ opacity: 1, height: 'auto' }}
                      exit={{ opacity: 0, height: 0 }}
                      transition={{ duration: 0.2 }}
                    >
                      <Input
                        type="number"
                        label={t('onboarding.travel_radius')}
                        placeholder="25"
                        value={String(settings.travel_radius_km)}
                        onChange={(e) => {
                          const num = parseInt(e.target.value, 10);
                          updateSetting('travel_radius_km', isNaN(num) ? 0 : Math.max(0, Math.min(500, num)));
                        }}
                        endContent={
                          <span className="text-theme-subtle text-sm">km</span>
                        }
                        classNames={inputClassNames}
                        description={t('onboarding.travel_radius_description')}
                      />
                    </motion.div>
                  )}
                </div>
              </GlassCard>

              <StepNavigation
                onBack={goBackAnimated}
                onNext={goNextAnimated}
              />
            </div>
          )}

          {/* ─── Step 4: Confirmation ─── */}
          {currentStep === 4 && (
            <div className="space-y-6">
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
                  <CheckCircle className="w-5 h-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                  {t('onboarding.review_settings')}
                </h2>
                <p className="text-theme-muted text-sm mb-6">
                  {t('onboarding.review_settings_description')}
                </p>

                {/* Summary sections */}
                <div className="space-y-4">
                  {/* Privacy summary */}
                  <div>
                    <h3 className="text-sm font-medium text-theme-muted mb-2 flex items-center gap-2">
                      <Eye className="w-4 h-4" aria-hidden="true" />
                      {t('onboarding.profile_visibility')}
                    </h3>
                    <div className="flex flex-wrap gap-2">
                      <SummaryChip
                        label={t('onboarding.summary_profile_visible')}
                        enabled={settings.profile_visible_federated}
                      />
                      <SummaryChip
                        label={t('onboarding.summary_in_search')}
                        enabled={settings.appear_in_federated_search}
                      />
                      <SummaryChip
                        label={t('onboarding.summary_skills_shared')}
                        enabled={settings.show_skills_federated}
                      />
                      <SummaryChip
                        label={t('onboarding.summary_location_shared')}
                        enabled={settings.show_location_federated}
                      />
                      <SummaryChip
                        label={t('onboarding.summary_reviews_visible')}
                        enabled={settings.show_reviews_federated}
                      />
                    </div>
                  </div>

                  <div className="border-t border-theme-default" />

                  {/* Communication summary */}
                  <div>
                    <h3 className="text-sm font-medium text-theme-muted mb-2 flex items-center gap-2">
                      <Shield className="w-4 h-4" aria-hidden="true" />
                      {t('onboarding.summary_communication')}
                    </h3>
                    <div className="flex flex-wrap gap-2">
                      <SummaryChip
                        label={t('onboarding.summary_messaging')}
                        enabled={settings.messaging_enabled_federated}
                      />
                      <SummaryChip
                        label={t('onboarding.summary_transactions')}
                        enabled={settings.transactions_enabled_federated}
                      />
                      <SummaryChip
                        label={t('onboarding.summary_email_alerts')}
                        enabled={settings.email_notifications}
                      />
                    </div>
                  </div>

                  <div className="border-t border-theme-default" />

                  {/* Service reach summary */}
                  <div>
                    <h3 className="text-sm font-medium text-theme-muted mb-2 flex items-center gap-2">
                      <MapPin className="w-4 h-4" aria-hidden="true" />
                      {t('onboarding.service_reach')}
                    </h3>
                    <p className="text-theme-primary">
                      {t(`onboarding.reach_label_${settings.service_reach}`)}
                      {settings.service_reach === 'travel_ok' && (
                        <span className="text-theme-subtle"> {t('onboarding.up_to_km', { km: settings.travel_radius_km })}</span>
                      )}
                    </p>
                  </div>
                </div>
              </GlassCard>

              {/* Partner communities preview */}
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
                  <Globe className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                  {t('onboarding.partner_communities')}
                </h2>

                {partnersLoading ? (
                  <div className="flex items-center justify-center py-6">
                    <Spinner size="md" />
                  </div>
                ) : partners.length > 0 ? (
                  <div className="space-y-3">
                    <p className="text-theme-muted text-sm">
                      {t('onboarding.partners_available', { count: partners.length })}
                    </p>
                    <div className="space-y-2">
                      {partners.slice(0, 5).map((partner) => (
                        <div
                          key={partner.id}
                          className="flex items-center justify-between p-3 rounded-lg bg-theme-elevated"
                        >
                          <div className="flex items-center gap-3">
                            <div className="p-2 rounded-lg bg-indigo-500/20">
                              <Globe className="w-4 h-4 text-indigo-500" aria-hidden="true" />
                            </div>
                            <div>
                              <p className="font-medium text-theme-primary text-sm">{partner.name}</p>
                              {partner.location && (
                                <p className="text-xs text-theme-subtle">{partner.location}</p>
                              )}
                            </div>
                          </div>
                          <Chip size="sm" variant="flat" color="primary">
                            {t('onboarding.member_count', { count: partner.member_count })}
                          </Chip>
                        </div>
                      ))}
                      {partners.length > 5 && (
                        <p className="text-sm text-theme-subtle text-center pt-2">
                          {t('onboarding.and_more', { count: partners.length - 5 })}
                        </p>
                      )}
                    </div>
                  </div>
                ) : (
                  <div className="text-center py-4">
                    <Globe className="w-10 h-10 text-theme-subtle mx-auto mb-2" aria-hidden="true" />
                    <p className="text-theme-muted text-sm">
                      {t('onboarding.no_partners_yet')}
                    </p>
                  </div>
                )}
              </GlassCard>

              {/* Action buttons */}
              <div className="flex items-center justify-between gap-3">
                <Button
                  variant="light"
                  className="text-theme-muted"
                  onPress={goBackAnimated}
                  startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('onboarding.back')}
                </Button>

                <div className="flex items-center gap-3">
                  <Button
                    variant="light"
                    className="text-theme-subtle"
                    onPress={handleSkip}
                  >
                    {t('onboarding.do_this_later')}
                  </Button>
                  <Button
                    size="lg"
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    onPress={handleComplete}
                    isLoading={isSubmitting}
                    startContent={!isSubmitting && <CheckCircle className="w-5 h-5" aria-hidden="true" />}
                  >
                    {t('onboarding.enable_federation')}
                  </Button>
                </div>
              </div>
            </div>
          )}
        </motion.div>
      </AnimatePresence>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Sub-components
// ─────────────────────────────────────────────────────────────────────────────

interface OnboardingToggleProps {
  label: string;
  description: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
  icon?: React.ReactNode;
}

function OnboardingToggle({ label, description, checked, onChange, icon }: OnboardingToggleProps) {
  return (
    <div className="flex items-center justify-between p-4 rounded-lg bg-theme-elevated">
      <div className="flex items-start gap-3 flex-1 min-w-0">
        {icon && (
          <span className="mt-0.5 flex-shrink-0" aria-hidden="true">{icon}</span>
        )}
        <div className="min-w-0">
          <p className="font-medium text-theme-primary">{label}</p>
          <p className="text-sm text-theme-subtle">{description}</p>
        </div>
      </div>
      <Switch
        aria-label={label}
        isSelected={checked}
        onValueChange={onChange}
        classNames={{
          wrapper: 'group-data-[selected=true]:bg-indigo-500',
        }}
      />
    </div>
  );
}

interface BenefitCardProps {
  icon: React.ReactNode;
  title: string;
  description: string;
}

function BenefitCard({ icon, title, description }: BenefitCardProps) {
  return (
    <GlassCard className="p-5 text-center">
      <div className="flex justify-center mb-3" aria-hidden="true">
        {icon}
      </div>
      <h3 className="font-semibold text-theme-primary text-sm mb-1">{title}</h3>
      <p className="text-xs text-theme-subtle">{description}</p>
    </GlassCard>
  );
}

interface SummaryChipProps {
  label: string;
  enabled: boolean;
}

function SummaryChip({ label, enabled }: SummaryChipProps) {
  const { t } = useTranslation('federation');
  return (
    <Chip
      size="sm"
      variant="flat"
      color={enabled ? 'success' : 'default'}
      className={!enabled ? 'opacity-60' : ''}
    >
      {label}: {enabled ? t('onboarding.on') : t('onboarding.off')}
    </Chip>
  );
}

interface StepNavigationProps {
  onBack: () => void;
  onNext: () => void;
  nextLabel?: string;
  isLoading?: boolean;
}

function StepNavigation({ onBack, onNext, nextLabel, isLoading }: StepNavigationProps) {
  const { t } = useTranslation('federation');
  const resolvedNextLabel = nextLabel || t('onboarding.next');
  return (
    <div className="flex items-center justify-between">
      <Button
        variant="light"
        className="text-theme-muted"
        onPress={onBack}
        startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
      >
        {t('onboarding.back')}
      </Button>
      <Button
        className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
        endContent={<ArrowRight className="w-4 h-4" aria-hidden="true" />}
        onPress={onNext}
        isLoading={isLoading}
      >
        {resolvedNextLabel}
      </Button>
    </div>
  );
}

export default FederationOnboardingPage;
