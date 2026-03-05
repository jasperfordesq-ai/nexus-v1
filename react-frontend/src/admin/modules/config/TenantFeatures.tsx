// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tenant Features & Modules Configuration
 * Toggle switches for all features and modules.
 * Parity: PHP Admin\TenantFeaturesController + AdminConfigApiController (V2 API ready)
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Card, CardBody, CardHeader, Switch, Spinner, Button, Divider,
  Select, SelectItem, Checkbox,
} from '@heroui/react';
import {
  Cog,
  Zap,
  RefreshCw,
  Trash2,
  Database,
  Timer,
  Play,
  Globe,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { adminConfig } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { TenantConfig, CacheStats, BackgroundJob } from '../../api/types';

// Feature metadata for display
const FEATURE_META: Record<string, { label: string; description: string }> = {
  events: { label: 'Events', description: 'Community events with RSVPs' },
  groups: { label: 'Groups', description: 'Community groups and discussions' },
  gamification: { label: 'Gamification', description: 'Badges, achievements, XP, leaderboards' },
  goals: { label: 'Goals', description: 'Personal and community goals' },
  blog: { label: 'Blog', description: 'Community blog/news posts' },
  resources: { label: 'Resources', description: 'Shared resource library' },
  volunteering: { label: 'Volunteering', description: 'Volunteer opportunities and hours' },
  exchange_workflow: { label: 'Exchange Workflow', description: 'Structured exchange requests with broker approval' },
  organisations: { label: 'Organisations', description: 'Organization profiles and management' },
  federation: { label: 'Federation', description: 'Multi-community network and partnerships' },
  connections: { label: 'Connections', description: 'User connections and friend requests' },
  reviews: { label: 'Reviews', description: 'Member reviews and ratings' },
  polls: { label: 'Polls', description: 'Community polls and voting' },
  job_vacancies: { label: 'Job Vacancies', description: 'Job postings and application management' },
  ideation_challenges: { label: 'Ideation Challenges', description: 'Community challenges with idea voting' },
  direct_messaging: { label: 'Direct Messaging', description: 'Private messaging between members' },
  group_exchanges: { label: 'Group Exchanges', description: 'Multi-party group exchange sessions' },
  search: { label: 'Search', description: 'Full-text search across listings, members, and content' },
  ai_chat: { label: 'AI Assistant', description: 'AI-powered chat assistant for members' },
};

const MODULE_META: Record<string, { label: string; description: string }> = {
  listings: { label: 'Listings', description: 'Service offers and requests marketplace' },
  wallet: { label: 'Wallet', description: 'Time credit transactions and balance' },
  messages: { label: 'Messages', description: 'Messaging system' },
  dashboard: { label: 'Dashboard', description: 'Member dashboard' },
  feed: { label: 'Feed', description: 'Social activity feed' },
  notifications: { label: 'Notifications', description: 'In-app notifications' },
  profile: { label: 'Profile', description: 'User profiles' },
  settings: { label: 'Settings', description: 'User settings' },
};

const PLATFORM_LANGUAGES = [
  { code: 'en', label: 'English', short: 'EN' },
  { code: 'ga', label: 'Gaeilge', short: 'GA' },
  { code: 'de', label: 'Deutsch', short: 'DE' },
  { code: 'fr', label: 'Français', short: 'FR' },
  { code: 'it', label: 'Italiano', short: 'IT' },
  { code: 'pt', label: 'Português', short: 'PT' },
  { code: 'es', label: 'Español', short: 'ES' },
];

export function TenantFeatures() {
  usePageTitle('Admin - Tenant Features');
  const toast = useToast();
  const { refreshTenant, supportedLanguages, defaultLanguage } = useTenant();

  const [config, setConfig] = useState<TenantConfig | null>(null);
  const [cacheStats, setCacheStats] = useState<CacheStats | null>(null);
  const [jobs, setJobs] = useState<BackgroundJob[]>([]);
  const [loading, setLoading] = useState(true);
  const [toggling, setToggling] = useState<string | null>(null);

  // Language config state
  const [langDefault, setLangDefault] = useState(defaultLanguage);
  const [langSupported, setLangSupported] = useState<string[]>(supportedLanguages);
  const [savingLang, setSavingLang] = useState(false);

  // Sync language state when TenantContext data arrives
  useEffect(() => {
    setLangDefault(defaultLanguage);
    setLangSupported(supportedLanguages);
  }, [defaultLanguage, supportedLanguages]);

  const loadConfig = useCallback(async () => {
    setLoading(true);
    const [configRes, cacheRes, jobsRes] = await Promise.all([
      adminConfig.get(),
      adminConfig.getCacheStats(),
      adminConfig.getJobs(),
    ]);

    if (configRes.success && configRes.data) {
      setConfig(configRes.data);
    }
    if (cacheRes.success && cacheRes.data) {
      setCacheStats(cacheRes.data);
    }
    if (jobsRes.success && jobsRes.data) {
      setJobs(Array.isArray(jobsRes.data) ? jobsRes.data : []);
    }
    setLoading(false);
  }, []);

  useEffect(() => {
    loadConfig();
  }, [loadConfig]);

  const handleFeatureToggle = async (feature: string, enabled: boolean) => {
    setToggling(feature);
    const res = await adminConfig.updateFeature(feature, enabled);
    if (res.success) {
      setConfig((prev) =>
        prev ? { ...prev, features: { ...prev.features, [feature]: enabled } } : prev
      );
      toast.success(`${FEATURE_META[feature]?.label || feature} ${enabled ? 'enabled' : 'disabled'}`);
      // Refresh TenantContext so nav items update immediately
      refreshTenant();
    } else {
      toast.error(res.error || 'Failed to update feature');
    }
    setToggling(null);
  };

  const handleModuleToggle = async (module: string, enabled: boolean) => {
    setToggling(module);
    const res = await adminConfig.updateModule(module, enabled);
    if (res.success) {
      setConfig((prev) =>
        prev ? { ...prev, modules: { ...prev.modules, [module]: enabled } } : prev
      );
      toast.success(`${MODULE_META[module]?.label || module} ${enabled ? 'enabled' : 'disabled'}`);
      // Refresh TenantContext so nav items update immediately
      refreshTenant();
    } else {
      toast.error(res.error || 'Failed to update module');
    }
    setToggling(null);
  };

  const handleClearCache = async () => {
    const res = await adminConfig.clearCache('tenant');
    if (res.success) {
      toast.success('Cache cleared successfully');
      // Refresh cache stats
      const statsRes = await adminConfig.getCacheStats();
      if (statsRes.success && statsRes.data) {
        setCacheStats(statsRes.data);
      }
    } else {
      toast.error('Failed to clear cache');
    }
  };

  const handleRunJob = async (jobId: string) => {
    const res = await adminConfig.runJob(jobId);
    if (res.success) {
      toast.success('Job triggered successfully');
    } else {
      toast.error('Failed to trigger job');
    }
  };

  const handleLangToggle = (code: string, checked: boolean) => {
    if (code === 'en') return; // English cannot be removed
    const updated = checked
      ? [...langSupported, code]
      : langSupported.filter((c) => c !== code);
    setLangSupported(updated);
    // If the current default was unchecked, reset to English
    if (!checked && langDefault === code) {
      setLangDefault('en');
    }
  };

  const handleSaveLanguages = async () => {
    setSavingLang(true);
    const res = await adminConfig.updateLanguageConfig({
      default_language: langDefault,
      supported_languages: langSupported,
    });
    if (res.success) {
      toast.success('Language settings saved');
      refreshTenant();
    } else {
      toast.error(res.error || 'Failed to save language settings');
    }
    setSavingLang(false);
  };

  if (loading) {
    return (
      <div className="flex h-64 items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Tenant Features & Modules"
        description="Enable or disable platform features and core modules for this tenant"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadConfig}
            size="sm"
          >
            Refresh
          </Button>
        }
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Features */}
        <div className="lg:col-span-2 space-y-6">
          {/* Language & Localisation */}
          <Card shadow="sm">
            <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
              <Globe size={18} className="text-primary" />
              <h3 className="font-semibold">Language &amp; Localisation</h3>
            </CardHeader>
            <CardBody className="px-4 pb-4 space-y-4">
              <div>
                <p className="text-sm font-medium mb-1">Default Language</p>
                <p className="text-xs text-default-400 mb-2">
                  Shown to new visitors without a preference
                </p>
                <Select
                  aria-label="Default language"
                  selectedKeys={[langDefault]}
                  onSelectionChange={(keys) => {
                    const val = Array.from(keys)[0] as string;
                    if (val) setLangDefault(val);
                  }}
                  className="max-w-xs"
                  size="sm"
                >
                  {PLATFORM_LANGUAGES.filter((l) => langSupported.includes(l.code)).map(
                    (lang) => (
                      <SelectItem key={lang.code}>
                        {lang.label} ({lang.short})
                      </SelectItem>
                    )
                  )}
                </Select>
              </div>
              <Divider />
              <div>
                <p className="text-sm font-medium mb-1">Available Languages</p>
                <p className="text-xs text-default-400 mb-3">
                  Languages shown in the language switcher
                </p>
                <div className="space-y-2">
                  {PLATFORM_LANGUAGES.map((lang) => (
                    <Checkbox
                      key={lang.code}
                      isSelected={langSupported.includes(lang.code)}
                      isDisabled={lang.code === 'en'}
                      onValueChange={(checked) => handleLangToggle(lang.code, checked)}
                    >
                      <span className="text-sm">
                        {lang.label} ({lang.short})
                        {lang.code === 'en' && (
                          <span className="ml-2 text-xs text-default-400">always enabled</span>
                        )}
                      </span>
                    </Checkbox>
                  ))}
                </div>
              </div>
              <div className="flex justify-end">
                <Button
                  color="primary"
                  size="sm"
                  isLoading={savingLang}
                  onPress={handleSaveLanguages}
                >
                  Save Changes
                </Button>
              </div>
            </CardBody>
          </Card>

          <Card shadow="sm">
            <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
              <Zap size={18} className="text-primary" />
              <h3 className="font-semibold">Features</h3>
              <span className="text-sm text-default-400">Optional add-on modules</span>
            </CardHeader>
            <CardBody className="divide-y divide-divider px-4">
              {Object.entries(config?.features || {}).map(([key, enabled]) => {
                const meta = FEATURE_META[key];
                return (
                  <div key={key} className="flex items-center justify-between py-3">
                    <div>
                      <p className="font-medium">{meta?.label || key}</p>
                      <p className="text-sm text-default-500">{meta?.description || ''}</p>
                    </div>
                    <Switch
                      isSelected={enabled}
                      onValueChange={(val) => handleFeatureToggle(key, val)}
                      isDisabled={toggling === key}
                      size="sm"
                    />
                  </div>
                );
              })}
            </CardBody>
          </Card>

          <Card shadow="sm">
            <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
              <Cog size={18} className="text-secondary" />
              <h3 className="font-semibold">Core Modules</h3>
              <span className="text-sm text-default-400">Core platform functionality</span>
            </CardHeader>
            <CardBody className="divide-y divide-divider px-4">
              {Object.entries(config?.modules || {}).map(([key, enabled]) => {
                const meta = MODULE_META[key];
                return (
                  <div key={key} className="flex items-center justify-between py-3">
                    <div>
                      <p className="font-medium">{meta?.label || key}</p>
                      <p className="text-sm text-default-500">{meta?.description || ''}</p>
                    </div>
                    <Switch
                      isSelected={enabled}
                      onValueChange={(val) => handleModuleToggle(key, val)}
                      isDisabled={toggling === key}
                      size="sm"
                    />
                  </div>
                );
              })}
            </CardBody>
          </Card>
        </div>

        {/* Sidebar: Cache + Jobs */}
        <div className="space-y-6">
          {/* Cache Stats */}
          <Card shadow="sm">
            <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
              <Database size={18} className="text-warning" />
              <h3 className="font-semibold">Cache</h3>
            </CardHeader>
            <CardBody className="px-4 pb-4 space-y-3">
              <div className="flex justify-between text-sm">
                <span className="text-default-500">Redis</span>
                <span className={cacheStats?.redis_connected ? 'text-success' : 'text-danger'}>
                  {cacheStats?.redis_connected ? 'Connected' : 'Disconnected'}
                </span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-default-500">Memory Used</span>
                <span>{cacheStats?.redis_memory_used || '—'}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-default-500">Keys</span>
                <span>{cacheStats?.redis_keys_count ?? '—'}</span>
              </div>
              <Divider />
              <Button
                fullWidth
                variant="flat"
                color="warning"
                startContent={<Trash2 size={14} />}
                onPress={handleClearCache}
                size="sm"
              >
                Clear Tenant Cache
              </Button>
            </CardBody>
          </Card>

          {/* Background Jobs */}
          <Card shadow="sm">
            <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
              <Timer size={18} className="text-secondary" />
              <h3 className="font-semibold">Background Jobs</h3>
            </CardHeader>
            <CardBody className="px-4 pb-4 space-y-3">
              {jobs.length > 0 ? jobs.map((job) => (
                <div key={job.id} className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium">{job.name}</p>
                    <p className="text-xs text-default-400">
                      {job.last_run_at ? `Last: ${new Date(job.last_run_at).toLocaleString()}` : 'Never run'}
                    </p>
                  </div>
                  <Button
                    isIconOnly
                    size="sm"
                    variant="flat"
                    onPress={() => handleRunJob(job.id)}
                    aria-label={`Run ${job.name}`}
                  >
                    <Play size={14} />
                  </Button>
                </div>
              )) : (
                <p className="text-sm text-default-400">No jobs configured</p>
              )}
            </CardBody>
          </Card>
        </div>
      </div>
    </div>
  );
}

export default TenantFeatures;
