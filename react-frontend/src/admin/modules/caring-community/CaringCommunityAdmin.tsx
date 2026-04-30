// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button, Card, CardBody, CardHeader, Chip, Divider, Spinner, Switch } from '@heroui/react';
import { Link } from 'react-router-dom';
import Heart from 'lucide-react/icons/heart';
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
  { key: 'listings', type: 'module', icon: ListChecks, label: 'Timebank' },
  { key: 'volunteering', type: 'feature', icon: Heart, label: 'Volunteering' },
  { key: 'organisations', type: 'feature', icon: Building2, label: 'Organisations' },
  { key: 'groups', type: 'feature', icon: Users, label: 'Groups' },
  { key: 'resources', type: 'feature', icon: FileText, label: 'Resources' },
  { key: 'reviews', type: 'feature', icon: ShieldCheck, label: 'Reviews & Trust' },
] as const;

const capabilityTypeLabel = (type: 'module' | 'feature') =>
  type === 'module' ? 'Module' : 'Feature';

export default function CaringCommunityAdmin() {
  usePageTitle('Caring Community');
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
      toast.error('Failed to load Caring Community configuration');
    } finally {
      setLoading(false);
    }
  }, [toast]);

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
        toast.success(value ? 'Caring Community enabled' : 'Caring Community disabled');
      } else {
        toast.error('Failed to save Caring Community configuration');
      }
    } catch {
      toast.error('Failed to save Caring Community configuration');
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
        title="Caring Community"
        description="Configure the integrated care hub, dependent capabilities, and municipal reporting surfaces."
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Button
              as={Link}
              to={tenantPath(CARING_COMMUNITY_ROUTE.href)}
              variant="flat"
              size="sm"
              startContent={<Heart size={16} />}
            >
              Open Member Hub
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/caring-community/workflow')}
              variant="flat"
              size="sm"
              startContent={<ListChecks size={16} />}
            >
              Open Workflow
            </Button>
            <Button
              variant="flat"
              size="sm"
              startContent={<RefreshCw size={16} />}
              onPress={loadConfig}
            >
              Refresh
            </Button>
          </div>
        }
      />

      <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <StatCard
          label="Master switch"
          value={enabled ? 'Enabled' : 'Disabled'}
          icon={Heart}
          color={enabled ? 'success' : 'default'}
        />
        <StatCard
          label="Connected capabilities"
          value={`${activeCapabilityCount}/${dependentCapabilities.length}`}
          icon={ListChecks}
          color="primary"
        />
        <StatCard
          label="Reporting pack"
          value="Ready"
          icon={BarChart3}
          color="secondary"
        />
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <Card shadow="sm">
          <CardHeader className="flex items-start justify-between gap-4">
            <div>
              <h2 className="text-lg font-semibold">Module kill switch</h2>
              <p className="mt-1 text-sm text-default-500">
                When this switch is off, the Caring Community route, navigation entry, dashboard cards, and quick-create actions are hidden.
              </p>
            </div>
            <Switch
              isSelected={enabled}
              isDisabled={saving}
              onValueChange={toggleMasterSwitch}
              aria-label="Toggle Caring Community module"
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
                        <p className="truncate text-sm font-medium">{capability.label}</p>
                        <p className="text-xs text-default-500">{capabilityTypeLabel(capability.type)}</p>
                      </div>
                    </div>
                    <Chip color={isActive ? 'success' : 'default'} variant="flat" size="sm">
                      {isActive ? 'Active' : 'Disabled'}
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
              <h2 className="text-lg font-semibold">Municipal reporting</h2>
              <p className="mt-1 text-sm text-default-500">
                Jump into the reporting surfaces needed for canton, municipality, and cooperative conversations.
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
              Community analytics
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/impact-report')}
              variant="flat"
              className="justify-start"
              startContent={<FileText size={16} />}
            >
              Impact report
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/reports/municipal-impact')}
              variant="flat"
              className="justify-start"
              startContent={<ListChecks size={16} />}
            >
              Municipal impact pack
            </Button>
            <Divider />
            <div className="rounded-lg bg-default-100 p-3 text-sm text-default-600">
              These surfaces use existing NEXUS reporting today and are ready for KISS-specific exports next.
            </div>
          </CardBody>
        </Card>
      </div>

      <div className="mt-6 rounded-lg border border-default-200 p-4 text-sm text-default-500">
        Dedicated admin configuration route: {CARING_COMMUNITY_ADMIN_ROUTE.href}
      </div>
    </div>
  );
}
