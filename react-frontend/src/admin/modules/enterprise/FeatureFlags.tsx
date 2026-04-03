// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Feature Flags
 * Toggle features and modules for the current tenant.
 */

import { useEffect, useState, useCallback } from 'react';
import { Card, CardBody, CardHeader, Button, Spinner, Switch } from '@heroui/react';
import { RefreshCw, ToggleLeft, Boxes } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { FeatureFlags as FeatureFlagsType } from '../../api/types';

import { useTranslation } from 'react-i18next';

function formatKey(key: string): string {
  return key
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

export function FeatureFlags() {
  const { t } = useTranslation('admin');
  usePageTitle(t('enterprise.page_title'));
  const toast = useToast();

  const [data, setData] = useState<FeatureFlagsType | null>(null);
  const [loading, setLoading] = useState(true);
  const [togglingKeys, setTogglingKeys] = useState<Set<string>>(new Set());

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getFeatureFlags();
      if (res.success && res.data) {
        setData(res.data as unknown as FeatureFlagsType);
      }
    } catch {
      toast.error('Failed to load feature flags');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleToggle = async (key: string, value: boolean, type: 'feature' | 'module') => {
    const toggleKey = `${type}:${key}`;
    setTogglingKeys((prev) => new Set(prev).add(toggleKey));

    // Optimistic update
    setData((prev) => {
      if (!prev) return prev;
      const section = type === 'feature' ? 'features' : 'modules';
      return {
        ...prev,
        [section]: { ...prev[section], [key]: value },
      };
    });

    try {
      const res = await adminEnterprise.updateFeatureFlag({ key, value, type });
      if (res.success) {
        toast.success(`${formatKey(key)} ${value ? 'enabled' : 'disabled'}`);
      } else {
        // Revert on failure
        setData((prev) => {
          if (!prev) return prev;
          const section = type === 'feature' ? 'features' : 'modules';
          return {
            ...prev,
            [section]: { ...prev[section], [key]: !value },
          };
        });
        toast.error(`Failed to update ${formatKey(key)}`);
      }
    } catch {
      // Revert on error
      setData((prev) => {
        if (!prev) return prev;
        const section = type === 'feature' ? 'features' : 'modules';
        return {
          ...prev,
          [section]: { ...prev[section], [key]: !value },
        };
      });
      toast.error(`Failed to update ${formatKey(key)}`);
    } finally {
      setTogglingKeys((prev) => {
        const next = new Set(prev);
        next.delete(toggleKey);
        return next;
      });
    }
  };

  const renderSection = (
    title: string,
    icon: React.ReactNode,
    items: Record<string, boolean>,
    type: 'feature' | 'module',
  ) => {
    const sortedKeys = Object.keys(items).sort();

    return (
      <Card shadow="sm">
        <CardHeader className="flex items-center gap-2 px-6 pt-5 pb-0">
          {icon}
          <h3 className="text-base font-semibold">{title}</h3>
        </CardHeader>
        <CardBody className="px-6 pb-5">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {sortedKeys.map((key) => {
              const toggleKey = `${type}:${key}`;
              const isToggling = togglingKeys.has(toggleKey);

              return (
                <div
                  key={key}
                  className="flex items-center justify-between gap-3 rounded-lg border border-divider p-3"
                >
                  <span className="text-sm font-medium text-foreground">
                    {formatKey(key)}
                  </span>
                  <Switch
                    size="sm"
                    isSelected={items[key]}
                    isDisabled={isToggling}
                    onValueChange={(val) => handleToggle(key, val, type)}
                    aria-label={`Toggle ${formatKey(key)}`}
                  />
                </div>
              );
            })}
          </div>
        </CardBody>
      </Card>
    );
  };

  return (
    <div>
      <PageHeader
        title="Feature Flags"
        description="Enable or disable features and modules for this tenant"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
            size="sm"
          >
            {t('common.refresh')}
          </Button>
        }
      />

      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      ) : data ? (
        <div className="space-y-6">
          {renderSection(
            'Features',
            <ToggleLeft size={18} className="text-primary" />,
            data.features,
            'feature',
          )}
          {renderSection(
            'Modules',
            <Boxes size={18} className="text-secondary" />,
            data.modules,
            'module',
          )}
        </div>
      ) : null}
    </div>
  );
}

export default FeatureFlags;
