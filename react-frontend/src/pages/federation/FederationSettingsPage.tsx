/**
 * Federation Settings Page - Configure federation preferences
 *
 * Lets users control profile visibility, communication, and service reach
 * across partner communities in the federation network.
 *
 * Route: /federation/settings
 */

import { useState, useEffect, useCallback, useMemo } from 'react';
import { motion } from 'framer-motion';
import {
  Button,
  Switch,
  Select,
  SelectItem,
  Input,
  Spinner,
} from '@heroui/react';
import {
  Settings,
  Eye,
  MessageSquare,
  MapPin,
  Save,
  ShieldCheck,
  ShieldOff,
  AlertTriangle,
  Globe,
  Star,
  Search,
  Zap,
  Send,
  CreditCard,
  Mail,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { FederationSettings } from '@/types/api';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

type ServiceReach = FederationSettings['service_reach'];

interface SettingsFormData {
  profile_visible_federated: boolean;
  appear_in_federated_search: boolean;
  show_skills_federated: boolean;
  show_location_federated: boolean;
  show_reviews_federated: boolean;
  messaging_enabled_federated: boolean;
  transactions_enabled_federated: boolean;
  email_notifications: boolean;
  service_reach: ServiceReach;
  travel_radius_km: number;
}

const DEFAULT_SETTINGS: SettingsFormData = {
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

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function FederationSettingsPage() {
  usePageTitle('Federation Settings');
  const toast = useToast();

  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [isTogglingStatus, setIsTogglingStatus] = useState(false);
  const [federationOptedIn, setFederationOptedIn] = useState(false);
  const [settings, setSettings] = useState<SettingsFormData>(DEFAULT_SETTINGS);
  const [originalSettings, setOriginalSettings] = useState<SettingsFormData>(DEFAULT_SETTINGS);

  // Track dirty state — only enable save when changes exist
  const isDirty = useMemo(() => {
    return JSON.stringify(settings) !== JSON.stringify(originalSettings);
  }, [settings, originalSettings]);

  // ───────────────────────────────────────────────────────────────────────────
  // Data Loading
  // ───────────────────────────────────────────────────────────────────────────

  const loadSettings = useCallback(async () => {
    try {
      setIsLoading(true);
      setLoadError(null);
      const response = await api.get<{
        settings: FederationSettings;
        enabled: boolean;
      }>('/v2/federation/settings');

      if (response.success && response.data) {
        const s = response.data.settings;
        const formData: SettingsFormData = {
          profile_visible_federated: s.profile_visible_federated ?? DEFAULT_SETTINGS.profile_visible_federated,
          appear_in_federated_search: s.appear_in_federated_search ?? DEFAULT_SETTINGS.appear_in_federated_search,
          show_skills_federated: s.show_skills_federated ?? DEFAULT_SETTINGS.show_skills_federated,
          show_location_federated: s.show_location_federated ?? DEFAULT_SETTINGS.show_location_federated,
          show_reviews_federated: s.show_reviews_federated ?? DEFAULT_SETTINGS.show_reviews_federated,
          messaging_enabled_federated: s.messaging_enabled_federated ?? DEFAULT_SETTINGS.messaging_enabled_federated,
          transactions_enabled_federated: s.transactions_enabled_federated ?? DEFAULT_SETTINGS.transactions_enabled_federated,
          email_notifications: s.email_notifications ?? DEFAULT_SETTINGS.email_notifications,
          service_reach: s.service_reach ?? DEFAULT_SETTINGS.service_reach,
          travel_radius_km: s.travel_radius_km ?? DEFAULT_SETTINGS.travel_radius_km,
        };
        setSettings(formData);
        setOriginalSettings(formData);
        setFederationOptedIn(response.data.enabled ?? s.federation_optin ?? false);
      } else {
        setLoadError('Failed to load federation settings');
      }
    } catch (error) {
      logError('Failed to load federation settings', error);
      setLoadError('Failed to load federation settings. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadSettings();
  }, [loadSettings]);

  // ───────────────────────────────────────────────────────────────────────────
  // Handlers
  // ───────────────────────────────────────────────────────────────────────────

  const handleToggleFederation = useCallback(async () => {
    const endpoint = federationOptedIn ? '/v2/federation/opt-out' : '/v2/federation/opt-in';
    const action = federationOptedIn ? 'disabled' : 'enabled';

    try {
      setIsTogglingStatus(true);
      const response = await api.post(endpoint);
      if (response.success) {
        setFederationOptedIn(!federationOptedIn);
        toast.success(`Federation ${action}`, `Federation has been ${action} for your account.`);
      } else {
        toast.error('Action failed', response.error || `Failed to ${federationOptedIn ? 'disable' : 'enable'} federation.`);
      }
    } catch (error) {
      logError(`Failed to toggle federation`, error);
      toast.error('Action failed', `Failed to ${federationOptedIn ? 'disable' : 'enable'} federation.`);
    } finally {
      setIsTogglingStatus(false);
    }
  }, [federationOptedIn, toast]);

  const handleSave = useCallback(async () => {
    try {
      setIsSaving(true);
      const response = await api.put('/v2/federation/settings', settings);
      if (response.success) {
        setOriginalSettings({ ...settings });
        toast.success('Settings saved', 'Your federation settings have been updated.');
      } else {
        toast.error('Save failed', response.error || 'Failed to save settings.');
      }
    } catch (error) {
      logError('Failed to save federation settings', error);
      toast.error('Save failed', 'Failed to save federation settings. Please try again.');
    } finally {
      setIsSaving(false);
    }
  }, [settings, toast]);

  const updateSetting = useCallback(<K extends keyof SettingsFormData>(key: K, value: SettingsFormData[K]) => {
    setSettings((prev) => ({ ...prev, [key]: value }));
  }, []);

  // ───────────────────────────────────────────────────────────────────────────
  // Animation Variants
  // ───────────────────────────────────────────────────────────────────────────

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.08 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  // HeroUI input classNames
  const inputClassNames = {
    input: 'bg-transparent text-theme-primary',
    inputWrapper: 'bg-theme-elevated border-theme-default',
    label: 'text-theme-muted',
  };

  const selectClassNames = {
    trigger: 'bg-theme-elevated border-theme-default',
    value: 'text-theme-primary',
    label: 'text-theme-muted',
  };

  // ───────────────────────────────────────────────────────────────────────────
  // Loading / Error States
  // ───────────────────────────────────────────────────────────────────────────

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" />
      </div>
    );
  }

  if (loadError) {
    return (
      <div className="max-w-3xl mx-auto">
        <Breadcrumbs items={[
          { label: 'Federation', href: '/federation' },
          { label: 'Settings' },
        ]} />
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{loadError}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            onPress={loadSettings}
          >
            Try Again
          </Button>
        </GlassCard>
      </div>
    );
  }

  // ───────────────────────────────────────────────────────────────────────────
  // Render
  // ───────────────────────────────────────────────────────────────────────────

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="max-w-3xl mx-auto space-y-6"
    >
      {/* Breadcrumbs */}
      <Breadcrumbs items={[
        { label: 'Federation', href: '/federation' },
        { label: 'Settings' },
      ]} />

      {/* Header */}
      <motion.div variants={itemVariants}>
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <Settings className="w-7 h-7 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
          Federation Settings
        </h1>
        <p className="text-theme-muted mt-1">
          Control how you appear and interact across partner communities
        </p>
      </motion.div>

      {/* ─── Status Banner ─── */}
      <motion.div variants={itemVariants}>
        {federationOptedIn ? (
          <GlassCard className="p-4 border-l-4 border-l-emerald-500">
            <div className="flex items-center justify-between flex-wrap gap-3">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-lg bg-emerald-500/20">
                  <ShieldCheck className="w-5 h-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                </div>
                <div>
                  <p className="font-medium text-emerald-700 dark:text-emerald-300">Federation is active</p>
                  <p className="text-sm text-theme-subtle">
                    Your profile is visible to partner communities
                  </p>
                </div>
              </div>
              <Button
                variant="flat"
                className="bg-red-500/10 text-red-600 dark:text-red-400"
                onPress={handleToggleFederation}
                isLoading={isTogglingStatus}
              >
                Disable Federation
              </Button>
            </div>
          </GlassCard>
        ) : (
          <GlassCard className="p-4 border-l-4 border-l-amber-500">
            <div className="flex items-center justify-between flex-wrap gap-3">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-lg bg-amber-500/20">
                  <ShieldOff className="w-5 h-5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                </div>
                <div>
                  <p className="font-medium text-amber-700 dark:text-amber-300">Federation is disabled</p>
                  <p className="text-sm text-theme-subtle">
                    You are not visible to partner communities
                  </p>
                </div>
              </div>
              <Button
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                onPress={handleToggleFederation}
                isLoading={isTogglingStatus}
              >
                Enable Federation
              </Button>
            </div>
          </GlassCard>
        )}
      </motion.div>

      {/* ─── 1. Profile Visibility ─── */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6">
          <h2 className="text-lg font-semibold text-theme-primary mb-6 flex items-center gap-2">
            <Eye className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
            Profile Visibility
          </h2>

          <div className="space-y-1">
            <SettingToggle
              icon={<Globe className="w-4 h-4 text-indigo-500" />}
              label="Show my profile to federated members"
              description="Allow members from partner communities to view your profile"
              checked={settings.profile_visible_federated}
              onChange={(v) => updateSetting('profile_visible_federated', v)}
            />
            <SettingToggle
              icon={<Search className="w-4 h-4 text-indigo-500" />}
              label="Appear in federated search results"
              description="Let partner community members find you via search"
              checked={settings.appear_in_federated_search}
              onChange={(v) => updateSetting('appear_in_federated_search', v)}
            />
            <SettingToggle
              icon={<Zap className="w-4 h-4 text-indigo-500" />}
              label="Share my skills across communities"
              description="Display your skills and expertise to federated members"
              checked={settings.show_skills_federated}
              onChange={(v) => updateSetting('show_skills_federated', v)}
            />
            <SettingToggle
              icon={<MapPin className="w-4 h-4 text-indigo-500" />}
              label="Share my location with partner communities"
              description="Show your general location to federated members"
              checked={settings.show_location_federated}
              onChange={(v) => updateSetting('show_location_federated', v)}
            />
            <SettingToggle
              icon={<Star className="w-4 h-4 text-indigo-500" />}
              label="Show my reviews to federated members"
              description="Allow partner community members to see your review history"
              checked={settings.show_reviews_federated}
              onChange={(v) => updateSetting('show_reviews_federated', v)}
            />
          </div>
        </GlassCard>
      </motion.div>

      {/* ─── 2. Communication ─── */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6">
          <h2 className="text-lg font-semibold text-theme-primary mb-6 flex items-center gap-2">
            <MessageSquare className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
            Communication
          </h2>

          <div className="space-y-1">
            <SettingToggle
              icon={<Send className="w-4 h-4 text-indigo-500" />}
              label="Allow federated messaging"
              description="Let members from partner communities send you messages"
              checked={settings.messaging_enabled_federated}
              onChange={(v) => updateSetting('messaging_enabled_federated', v)}
            />
            <SettingToggle
              icon={<CreditCard className="w-4 h-4 text-indigo-500" />}
              label="Allow federated transactions"
              description="Accept time credit transfers from partner communities"
              checked={settings.transactions_enabled_federated}
              onChange={(v) => updateSetting('transactions_enabled_federated', v)}
            />
            <SettingToggle
              icon={<Mail className="w-4 h-4 text-indigo-500" />}
              label="Email notifications for federation activity"
              description="Receive email alerts for federated messages and transactions"
              checked={settings.email_notifications}
              onChange={(v) => updateSetting('email_notifications', v)}
            />
          </div>
        </GlassCard>
      </motion.div>

      {/* ─── 3. Service Reach ─── */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6">
          <h2 className="text-lg font-semibold text-theme-primary mb-6 flex items-center gap-2">
            <MapPin className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
            Service Reach
          </h2>

          <div className="space-y-4">
            <Select
              label="Service Availability"
              selectedKeys={[settings.service_reach]}
              onSelectionChange={(keys) => {
                const value = Array.from(keys)[0] as string;
                if (value) {
                  updateSetting('service_reach', value as ServiceReach);
                }
              }}
              classNames={selectClassNames}
            >
              <SelectItem key="local_only">Local Only -- I only provide services in my area</SelectItem>
              <SelectItem key="remote_ok">Remote OK -- I can provide services remotely</SelectItem>
              <SelectItem key="travel_ok">Will Travel -- I am willing to travel for services</SelectItem>
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
                  label="Travel Radius"
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
                  description="Maximum distance you are willing to travel for services"
                />
              </motion.div>
            )}
          </div>
        </GlassCard>
      </motion.div>

      {/* ─── Save Button ─── */}
      <motion.div variants={itemVariants} className="flex justify-end">
        <Button
          onPress={handleSave}
          className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
          startContent={<Save className="w-4 h-4" aria-hidden="true" />}
          isLoading={isSaving}
          isDisabled={!isDirty}
        >
          Save Settings
        </Button>
      </motion.div>
    </motion.div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Sub-components
// ─────────────────────────────────────────────────────────────────────────────

interface SettingToggleProps {
  label: string;
  description: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
  icon?: React.ReactNode;
}

function SettingToggle({ label, description, checked, onChange, icon }: SettingToggleProps) {
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

export default FederationSettingsPage;
