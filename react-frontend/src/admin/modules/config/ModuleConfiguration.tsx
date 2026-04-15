// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Module Configuration
 * Central admin page listing all platform modules with granular config options.
 * Marked as Beta — under active development.
 */

import { useState, useCallback, useEffect, useMemo } from 'react';
import { Input, Spinner, ButtonGroup, Button, Chip } from '@heroui/react';
import { Search, RefreshCw, Construction } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { adminConfig } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { TenantConfig } from '../../api/types';
import ModuleCard from './ModuleCard';
import ModuleConfigModal from './ModuleConfigModal';
import {
  getCoreModules,
  getFeatureModules,
  type ModuleDefinition,
} from './moduleRegistry';

type FilterType = 'all' | 'core' | 'feature';

export default function ModuleConfiguration() {
  const { t } = useTranslation('admin');
  usePageTitle(t('config.module_configuration_title'));
  const toast = useToast();
  const { refreshTenant } = useTenant();

  const [config, setConfig] = useState<TenantConfig | null>(null);
  const [loading, setLoading] = useState(true);
  const [toggling, setToggling] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [filterType, setFilterType] = useState<FilterType>('all');
  const [selectedModule, setSelectedModule] = useState<ModuleDefinition | null>(null);

  const coreModules = getCoreModules();
  const featureModules = getFeatureModules();

  const loadConfig = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminConfig.get();
      if (res.success && res.data) {
        setConfig(res.data);
      }
    } catch {
      toast.error(t('config.module_config_load_failed'));
    } finally {
      setLoading(false);
    }
  }, [toast, t])

  useEffect(() => {
    loadConfig();
  }, [loadConfig]);

  // ── Toggle handlers (same pattern as TenantFeatures.tsx) ────────────────

  const handleToggle = async (id: string, enabled: boolean) => {
    if (!config) return;

    // Determine if this is a core module or feature
    const isCore = coreModules.some(m => m.id === id);
    setToggling(id);

    const res = isCore
      ? await adminConfig.updateModule(id, enabled)
      : await adminConfig.updateFeature(id, enabled);

    if (res.success) {
      setConfig(prev => {
        if (!prev) return prev;
        if (isCore) {
          return { ...prev, modules: { ...prev.modules, [id]: enabled } };
        }
        return { ...prev, features: { ...prev.features, [id]: enabled } };
      });
      const mod = [...coreModules, ...featureModules].find(m => m.id === id);
      toast.success(t(enabled ? 'config.module_enabled' : 'config.module_disabled', { name: mod?.name || id }));
      refreshTenant();
    } else {
      toast.error(res.error || t('config.module_update_failed'));
    }
    setToggling(null);
  };

  // ── Filter logic ────────────────────────────────────────────────────────

  const filterModules = useCallback((modules: ModuleDefinition[]) => {
    if (!searchQuery) return modules;
    const q = searchQuery.toLowerCase();
    return modules.filter(
      m => m.name.toLowerCase().includes(q) || m.description.toLowerCase().includes(q)
    );
  }, [searchQuery]);

  const filteredCore = useMemo(
    () => filterType !== 'feature' ? filterModules(coreModules) : [],
    [filterType, filterModules, coreModules]
  );

  const filteredFeatures = useMemo(
    () => filterType !== 'core' ? filterModules(featureModules) : [],
    [filterType, filterModules, featureModules]
  );

  function isModuleEnabled(id: string, isCore: boolean): boolean {
    if (!config) return true; // Default to enabled while loading
    if (isCore) {
      return config.modules?.[id] !== false;
    }
    return config.features?.[id] !== false;
  }

  // ── Render ──────────────────────────────────────────────────────────────

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-[400px]">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4 pb-8">
      <PageHeader
        title={t('config.module_configuration_title')}
        description={t('config.module_configuration_desc')}
        actions={
          <div className="flex items-center gap-2">
            <Chip color="warning" variant="flat" size="sm" startContent={<Construction size={14} />}>
              {t('config.beta')}
            </Chip>
            <Button variant="flat" size="sm" startContent={<RefreshCw size={16} />} onPress={loadConfig}>
              {t('config.refresh')}
            </Button>
          </div>
        }
      />

      {/* Search + filter bar */}
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-6">
        <Input
          size="sm"
          variant="bordered"
          placeholder={t('config.search_modules')}
          startContent={<Search size={16} className="text-default-400" />}
          value={searchQuery}
          onValueChange={setSearchQuery}
          className="sm:max-w-xs"
          isClearable
          onClear={() => setSearchQuery('')}
        />
        <ButtonGroup size="sm" variant="flat">
          <Button
            color={filterType === 'all' ? 'primary' : 'default'}
            onPress={() => setFilterType('all')}
          >
            {t('config.filter_all')} ({coreModules.length + featureModules.length})
          </Button>
          <Button
            color={filterType === 'core' ? 'primary' : 'default'}
            onPress={() => setFilterType('core')}
          >
            {t('config.filter_core')} ({coreModules.length})
          </Button>
          <Button
            color={filterType === 'feature' ? 'primary' : 'default'}
            onPress={() => setFilterType('feature')}
          >
            {t('config.filter_features')} ({featureModules.length})
          </Button>
        </ButtonGroup>
      </div>

      {/* Core Modules section */}
      {filteredCore.length > 0 && (
        <section className="mb-8">
          <h2 className="text-lg font-semibold mb-4">{t('config.core_modules')}</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {filteredCore.map(mod => (
              <ModuleCard
                key={mod.id}
                module={mod}
                enabled={isModuleEnabled(mod.id, true)}
                onToggle={handleToggle}
                onConfigure={setSelectedModule}
                toggling={toggling === mod.id}
              />
            ))}
          </div>
        </section>
      )}

      {/* Optional Features section */}
      {filteredFeatures.length > 0 && (
        <section className="mb-8">
          <h2 className="text-lg font-semibold mb-4">{t('config.optional_features')}</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {filteredFeatures.map(mod => (
              <ModuleCard
                key={mod.id}
                module={mod}
                enabled={isModuleEnabled(mod.id, false)}
                onToggle={handleToggle}
                onConfigure={setSelectedModule}
                toggling={toggling === mod.id}
              />
            ))}
          </div>
        </section>
      )}

      {/* No results */}
      {filteredCore.length === 0 && filteredFeatures.length === 0 && (
        <div className="flex flex-col items-center justify-center py-16 text-default-400">
          <Search size={48} className="mb-4" />
          <p className="text-lg">{t('config.no_modules_match')}</p>
          <Button
            variant="light"
            size="sm"
            className="mt-2"
            onPress={() => { setSearchQuery(''); setFilterType('all'); }}
          >
            {t('config.clear_filters')}
          </Button>
        </div>
      )}

      {/* Config modal */}
      <ModuleConfigModal
        module={selectedModule}
        isOpen={selectedModule !== null}
        onClose={() => setSelectedModule(null)}
      />
    </div>
  );
}
