// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * System Monitoring
 * System health dashboard with metric cards and progress bars.
 */

import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, Button, Spinner, Chip, Progress } from '@heroui/react';
import {
  Server,
  Database,
  HardDrive,
  Clock,
  Cpu,
  RefreshCw,
  ArrowRight,
  FileText,
  Settings,
  ToggleLeft,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { SystemHealth } from '../../api/types';

import { useTranslation } from 'react-i18next';

/**
 * Parse a memory string like "24 MB" or "256M" to bytes.
 */
function parseMemory(str: string | undefined | null): number | null {
  if (!str) return null;
  const match = str.match(/^([\d.]+)\s*(B|KB|K|MB|M|GB|G|TB|T)?$/i);
  if (!match?.[1]) return null;
  const val = parseFloat(match[1]);
  const unit = (match[2] ?? 'B').toUpperCase();
  const multipliers: Record<string, number> = {
    B: 1,
    K: 1024,
    KB: 1024,
    M: 1024 ** 2,
    MB: 1024 ** 2,
    G: 1024 ** 3,
    GB: 1024 ** 3,
    T: 1024 ** 4,
    TB: 1024 ** 4,
  };
  return val * (multipliers[unit] || 1);
}

function memoryProgressColor(pct: number): 'success' | 'warning' | 'danger' {
  if (pct >= 90) return 'danger';
  if (pct >= 70) return 'warning';
  return 'success';
}

export function SystemMonitoring() {
  const { t } = useTranslation('admin');
  usePageTitle(t('enterprise.page_title'));
  const { tenantPath } = useTenant();

  const [health, setHealth] = useState<SystemHealth | null>(null);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getMonitoring();
      if (res.success && res.data) {
        setHealth(res.data as unknown as SystemHealth);
      }
    } catch (err) {
      console.error('Failed to load monitoring data', err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // Compute memory percentage
  const memUsageBytes = parseMemory(health?.memory_usage);
  const memLimitBytes = parseMemory(health?.memory_limit);
  const memoryPct = memUsageBytes && memLimitBytes && memLimitBytes > 0
    ? Math.round((memUsageBytes / memLimitBytes) * 100)
    : null;

  const metrics = health
    ? [
        { label: t('enterprise.metric_php_version'), value: health.php_version, icon: Cpu, color: 'primary' },
        { label: t('enterprise.metric_database_size'), value: health.db_size, icon: Database, color: 'success' },
        { label: t('enterprise.metric_redis_memory'), value: health.redis_memory, icon: HardDrive, color: 'secondary' },
        { label: t('enterprise.metric_db_uptime'), value: health.uptime, icon: Clock, color: 'primary' },
        { label: t('enterprise.metric_server_time'), value: health.server_time, icon: Clock, color: 'default' },
        { label: t('enterprise.metric_operating_system'), value: health.os, icon: Cpu, color: 'default' },
      ]
    : [];

  return (
    <div>
      <PageHeader
        title={t('enterprise.system_monitoring_title')}
        description={t('enterprise.system_monitoring_desc')}
        actions={
          <div className="flex gap-2">
            <Button
              as={Link}
              to={tenantPath('/admin/enterprise/monitoring/health')}
              variant="flat"
              size="sm"
              endContent={<ArrowRight size={14} />}
            >
              {t('enterprise.health_check')}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/enterprise/monitoring/logs')}
              variant="flat"
              size="sm"
              endContent={<ArrowRight size={14} />}
            >
              {t('enterprise.error_logs')}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/enterprise/monitoring/log-files')}
              variant="flat"
              size="sm"
              endContent={<ArrowRight size={14} />}
            >
              Log Files
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/enterprise/monitoring/requirements')}
              variant="flat"
              size="sm"
              endContent={<ArrowRight size={14} />}
            >
              Requirements
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/enterprise/config/features')}
              variant="flat"
              size="sm"
              endContent={<ToggleLeft size={14} />}
            >
              Feature Flags
            </Button>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              isLoading={loading}
              size="sm"
            >
              {t('common.refresh')}
            </Button>
          </div>
        }
      />

      {/* Connection Status */}
      <div className="flex gap-3 mb-6">
        <Chip
          size="sm"
          variant="flat"
          color={health?.db_connected ? 'success' : 'danger'}
        >
          {t('enterprise.database')}: {health?.db_connected ? t('enterprise.connected') : t('enterprise.disconnected')}
        </Chip>
        <Chip
          size="sm"
          variant="flat"
          color={health?.redis_connected ? 'success' : 'danger'}
        >
          {t('enterprise.redis')}: {health?.redis_connected ? t('enterprise.connected') : t('enterprise.disconnected')}
        </Chip>
      </div>

      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      ) : (
        <div className="space-y-6">
          {/* Memory Usage Progress Bar */}
          {memoryPct !== null && (
            <Card shadow="sm">
              <CardBody className="p-4">
                <div className="flex items-center justify-between mb-2">
                  <div className="flex items-center gap-2">
                    <Server size={18} className="text-warning" />
                    <span className="text-sm font-semibold text-foreground">
                      {t('enterprise.metric_memory_usage')}
                    </span>
                  </div>
                  <span className="text-sm text-default-500">
                    {health?.memory_usage} / {health?.memory_limit}
                  </span>
                </div>
                <Progress
                  size="md"
                  value={memoryPct}
                  color={memoryProgressColor(memoryPct)}
                  showValueLabel
                  aria-label="Memory usage"
                  className="max-w-full"
                />
              </CardBody>
            </Card>
          )}

          {/* Server Stats Grid */}
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {metrics.map((metric) => (
              <Card key={metric.label} shadow="sm">
                <CardBody className="flex flex-row items-center gap-3 p-4">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-default-100">
                    <metric.icon size={20} className="text-default-600" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="text-xs text-default-500">{metric.label}</p>
                    <p className="text-sm font-semibold text-foreground truncate">{metric.value || '---'}</p>
                  </div>
                </CardBody>
              </Card>
            ))}
          </div>

          {/* Quick Links */}
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Card shadow="sm" isPressable as={Link} to={tenantPath('/admin/enterprise/monitoring/log-files')}>
              <CardBody className="flex flex-row items-center gap-3 p-4">
                <FileText size={20} className="text-primary" />
                <div>
                  <p className="text-sm font-semibold text-foreground">{t('system_monitoring.log_files')}</p>
                  <p className="text-xs text-default-500">{t('system_monitoring.log_files_desc')}</p>
                </div>
              </CardBody>
            </Card>
            <Card shadow="sm" isPressable as={Link} to={tenantPath('/admin/enterprise/monitoring/requirements')}>
              <CardBody className="flex flex-row items-center gap-3 p-4">
                <Settings size={20} className="text-warning" />
                <div>
                  <p className="text-sm font-semibold text-foreground">{t('system_monitoring.system_requirements')}</p>
                  <p className="text-xs text-default-500">{t('system_monitoring.system_requirements_desc')}</p>
                </div>
              </CardBody>
            </Card>
            <Card shadow="sm" isPressable as={Link} to={tenantPath('/admin/enterprise/config/features')}>
              <CardBody className="flex flex-row items-center gap-3 p-4">
                <ToggleLeft size={20} className="text-success" />
                <div>
                  <p className="text-sm font-semibold text-foreground">{t('system_monitoring.feature_flags')}</p>
                  <p className="text-xs text-default-500">Toggle features &amp; modules</p>
                </div>
              </CardBody>
            </Card>
          </div>
        </div>
      )}
    </div>
  );
}

export default SystemMonitoring;
