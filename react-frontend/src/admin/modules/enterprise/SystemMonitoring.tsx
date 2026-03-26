// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * System Monitoring
 * System health dashboard with metric cards.
 */

import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, Button, Spinner, Chip } from '@heroui/react';
import {
  Server,
  Database,
  HardDrive,
  Clock,
  Cpu,
  RefreshCw,
  ArrowRight,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { SystemHealth } from '../../api/types';

import { useTranslation } from 'react-i18next';
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

  const metrics = health
    ? [
        { label: t('enterprise.metric_php_version'), value: health.php_version, icon: Cpu, color: 'primary' },
        { label: t('enterprise.metric_memory_usage'), value: health.memory_usage, icon: Server, color: 'warning' },
        { label: t('enterprise.metric_memory_limit'), value: health.memory_limit, icon: Server, color: 'default' },
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
      )}
    </div>
  );
}

export default SystemMonitoring;
