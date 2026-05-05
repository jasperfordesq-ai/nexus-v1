// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button, Card, CardBody, CardHeader, Chip, Divider, Spinner, Switch } from '@heroui/react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import Heart from 'lucide-react/icons/heart';
import Info from 'lucide-react/icons/info';
import ListChecks from 'lucide-react/icons/list-checks';
import Users from 'lucide-react/icons/users';
import Building2 from 'lucide-react/icons/building-2';
import BarChart3 from 'lucide-react/icons/chart-column';
import FileText from 'lucide-react/icons/file-text';
import ShieldCheck from 'lucide-react/icons/shield-check';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { PageHeader, StatCard } from '../../components';
import { adminConfig } from '../../api/adminApi';
import type { TenantConfig } from '../../api/types';
import { CARING_COMMUNITY_ADMIN_ROUTE, CARING_COMMUNITY_ROUTE } from '@/pages/caring-community/config';

const dependentCapabilities = [
  { key: 'listings', type: 'module', icon: ListChecks, labelKey: 'timebank' },
  { key: 'volunteering', type: 'feature', icon: Heart, labelKey: 'volunteering' },
  { key: 'organisations', type: 'feature', icon: Building2, labelKey: 'organisations' },
  { key: 'groups', type: 'feature', icon: Users, labelKey: 'groups' },
  { key: 'resources', type: 'feature', icon: FileText, labelKey: 'resources' },
  { key: 'reviews', type: 'feature', icon: ShieldCheck, labelKey: 'trust' },
] as const;

export default function CaringCommunityAdmin() {
  const { t } = useTranslation('admin');
  usePageTitle(t('caring_community.meta.title'));
  const { tenantPath, refreshTenant } = useTenant();
  const toast = useToast();
  const [config, setConfig] = useState<TenantConfig | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const loadConfig = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminConfig.get();
      if (res.success && res.data) {
        setConfig(res.data);
      }
    } catch {
      toast.error(t('caring_community.errors.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [t, toast]);

  useEffect(() => {
    loadConfig();
  }, [loadConfig]);

  const enabled = config?.features?.[CARING_COMMUNITY_ROUTE.feature] === true;
  const activeCapabilityCount = useMemo(() => {
    if (!config) return 0;
    return dependentCapabilities.filter((capability) => {
      const source = capability.type === 'module' ? config.modules : config.features;
      return source?.[capability.key] !== false;
    }).length;
  }, [config]);

  const toggleMasterSwitch = async (value: boolean) => {
    setSaving(true);
    try {
      const res = await adminConfig.updateFeature(CARING_COMMUNITY_ROUTE.feature, value);
      if (res.success) {
        setConfig(prev => prev ? {
          ...prev,
          features: { ...prev.features, [CARING_COMMUNITY_ROUTE.feature]: value },
        } : prev);
        refreshTenant();
        toast.success(value ? t('caring_community.messages.enabled') : t('caring_community.messages.disabled'));
      } else {
        toast.error(t('caring_community.errors.save_failed'));
      }
    } catch {
      toast.error(t('caring_community.errors.save_failed'));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex min-h-[400px] items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-7xl px-4 pb-8">
      <PageHeader
        title={t('caring_community.meta.title')}
        description={t('caring_community.meta.description')}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Button
              as={Link}
              to={tenantPath(CARING_COMMUNITY_ROUTE.href)}
              variant="flat"
              size="sm"
              startContent={<Heart size={16} />}
            >
              {t('caring_community.actions.open_member_hub')}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/caring/workflow')}
              variant="flat"
              size="sm"
              startContent={<ListChecks size={16} />}
            >
              {t('caring_community.actions.open_workflow')}
            </Button>
            <Button
              variant="flat"
              size="sm"
              startContent={<RefreshCw size={16} />}
              onPress={loadConfig}
            >
              {t('caring_community.actions.refresh')}
            </Button>
          </div>
        }
      />

      <Card className="mb-6 border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">About this page</p>
              <p className="text-default-600">
                The Caring Community module enables your timebank to coordinate informal neighbourhood care — matching
                residents who need help with trusted members who can provide it. It adds a trust-tier system,
                coordinator workflow, pilot evaluation tools, and municipal reporting on top of your existing timebank.
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      <Card className="mb-6 border border-default-200" shadow="none">
        <CardBody className="px-4 py-3">
          <p className="mb-2 text-sm font-semibold text-default-800">Getting started</p>
          <ol className="space-y-1.5 text-sm text-default-600">
            <li><span className="font-semibold text-default-800">1.</span> Configure Operating Policy — set your approval workflow and CHF hourly rate.</li>
            <li><span className="font-semibold text-default-800">2.</span> Capture a pre-pilot baseline in the Pilot Scoreboard.</li>
            <li><span className="font-semibold text-default-800">3.</span> Configure Trust Tier thresholds.</li>
            <li><span className="font-semibold text-default-800">4.</span> Run the Pilot Launch Readiness check.</li>
            <li><span className="font-semibold text-default-800">5.</span> Once launched, use the Coordinator Workflow as your daily operations dashboard.</li>
          </ol>
        </CardBody>
      </Card>

      <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <StatCard
          label={t('caring_community.stats.master_switch')}
          value={enabled ? t('caring_community.status.enabled') : t('caring_community.status.disabled')}
          icon={Heart}
          color={enabled ? 'success' : 'default'}
        />
        <StatCard
          label={t('caring_community.stats.connected_capabilities')}
          value={`${activeCapabilityCount}/${dependentCapabilities.length}`}
          icon={ListChecks}
          color="primary"
        />
        <StatCard
          label={t('caring_community.stats.reporting_pack')}
          value={t('caring_community.stats.reporting_ready')}
          icon={BarChart3}
          color="secondary"
        />
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <Card shadow="sm">
          <CardHeader className="flex items-start justify-between gap-4">
            <div>
              <h2 className="text-lg font-semibold">{t('caring_community.switch.title')}</h2>
              <p className="mt-1 text-sm text-default-500">
                {t('caring_community.switch.description')}
              </p>
              <p className="mt-2 text-xs text-default-400">
                Caring Community relies on the modules and features listed below being enabled. A disabled module
                will limit coordinator and reporting features.
              </p>
            </div>
            <Switch
              isSelected={enabled}
              isDisabled={saving}
              onValueChange={toggleMasterSwitch}
              aria-label={t('caring_community.switch.aria')}
            />
          </CardHeader>
          <Divider />
          <CardBody className="space-y-4">
            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
              {dependentCapabilities.map((capability) => {
                const Icon = capability.icon;
                const source = capability.type === 'module' ? config?.modules : config?.features;
                const isActive = source?.[capability.key] !== false;
                return (
                  <div key={capability.key} className="flex items-center justify-between gap-3 rounded-lg border border-default-200 p-3">
                    <div className="flex min-w-0 items-center gap-3">
                      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Icon size={18} />
                      </div>
                      <div className="min-w-0">
                        <p className="truncate text-sm font-medium">
                          {t(`caring_community.capabilities.${capability.labelKey}`)}
                        </p>
                        <p className="text-xs text-default-500">
                          {t(`caring_community.capability_type.${capability.type}`)}
                        </p>
                      </div>
                    </div>
                    <Chip color={isActive ? 'success' : 'default'} variant="flat" size="sm">
                      {isActive ? t('caring_community.status.active') : t('caring_community.status.disabled')}
                    </Chip>
                  </div>
                );
              })}
            </div>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader>
            <div>
              <h2 className="text-lg font-semibold">{t('caring_community.reporting.title')}</h2>
              <p className="mt-1 text-sm text-default-500">
                {t('caring_community.reporting.description')}
              </p>
            </div>
          </CardHeader>
          <Divider />
          <CardBody className="space-y-3">
            <Button
              as={Link}
              to={tenantPath('/admin/community-analytics')}
              variant="flat"
              className="justify-start"
              startContent={<BarChart3 size={16} />}
            >
              {t('caring_community.reporting.community_analytics')}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/impact-report')}
              variant="flat"
              className="justify-start"
              startContent={<FileText size={16} />}
            >
              {t('caring_community.reporting.impact_report')}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/reports/municipal-impact')}
              variant="flat"
              className="justify-start"
              startContent={<ListChecks size={16} />}
            >
              {t('caring_community.reporting.municipal_pack')}
            </Button>
            <Divider />
            <div className="rounded-lg bg-default-100 p-3 text-sm text-default-600">
              {t('caring_community.reporting.note')}
            </div>
          </CardBody>
        </Card>
      </div>

      <div className="mt-6 rounded-lg border border-default-200 p-4 text-sm text-default-500">
        {t('caring_community.config_route_note', { route: CARING_COMMUNITY_ADMIN_ROUTE.href })}
      </div>
    </div>
  );
}
