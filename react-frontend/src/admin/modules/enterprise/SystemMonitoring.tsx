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

export function SystemMonitoring() {
  usePageTitle('Admin - System Monitoring');
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
    } catch {
      // Silently handle
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const metrics = health
    ? [
        { label: 'PHP Version', value: health.php_version, icon: Cpu, color: 'primary' },
        { label: 'Memory Usage', value: health.memory_usage, icon: Server, color: 'warning' },
        { label: 'Memory Limit', value: health.memory_limit, icon: Server, color: 'default' },
        { label: 'Database Size', value: health.db_size, icon: Database, color: 'success' },
        { label: 'Redis Memory', value: health.redis_memory, icon: HardDrive, color: 'secondary' },
        { label: 'DB Uptime', value: health.uptime, icon: Clock, color: 'primary' },
        { label: 'Server Time', value: health.server_time, icon: Clock, color: 'default' },
        { label: 'Operating System', value: health.os, icon: Cpu, color: 'default' },
      ]
    : [];

  return (
    <div>
      <PageHeader
        title="System Monitoring"
        description="Server metrics and system health information"
        actions={
          <div className="flex gap-2">
            <Button
              as={Link}
              to={tenantPath('/admin/enterprise/monitoring/health')}
              variant="flat"
              size="sm"
              endContent={<ArrowRight size={14} />}
            >
              Health Check
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/enterprise/monitoring/logs')}
              variant="flat"
              size="sm"
              endContent={<ArrowRight size={14} />}
            >
              Error Logs
            </Button>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              isLoading={loading}
              size="sm"
            >
              Refresh
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
          Database: {health?.db_connected ? 'Connected' : 'Disconnected'}
        </Chip>
        <Chip
          size="sm"
          variant="flat"
          color={health?.redis_connected ? 'success' : 'danger'}
        >
          Redis: {health?.redis_connected ? 'Connected' : 'Disconnected'}
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
