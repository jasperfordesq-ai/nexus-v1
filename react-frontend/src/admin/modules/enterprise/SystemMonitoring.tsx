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
import Server from 'lucide-react/icons/server';
import Database from 'lucide-react/icons/database';
import HardDrive from 'lucide-react/icons/hard-drive';
import Clock from 'lucide-react/icons/clock';
import Cpu from 'lucide-react/icons/cpu';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import ArrowRight from 'lucide-react/icons/arrow-right';
import FileText from 'lucide-react/icons/file-text';
import Settings from 'lucide-react/icons/settings';
import ToggleLeft from 'lucide-react/icons/toggle-left';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { SystemHealth } from '../../api/types';

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
  usePageTitle("Enterprise");
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
        { label: "Metric PHP Version", value: health.php_version, icon: Cpu, color: 'primary' },
        { label: "Database Size", value: health.db_size, icon: Database, color: 'success' },
        { label: "Redis Memory", value: health.redis_memory, icon: HardDrive, color: 'secondary' },
        { label: "Metric DB Uptime", value: health.uptime, icon: Clock, color: 'primary' },
        { label: "Server Time", value: health.server_time, icon: Clock, color: 'default' },
        { label: "Operating System", value: health.os, icon: Cpu, color: 'default' },
      ]
    : [];

  return (
    <div>
      <PageHeader
        title={"System Monitoring"}
        description={"Monitor system health, uptime, and performance metrics"}
        actions={
          <div className="flex gap-2">
            <Button
              as={Link}
              to={tenantPath('/admin/enterprise/monitoring/health')}
              variant="flat"
              size="sm"
              endContent={<ArrowRight size={14} />}
            >
              {"Health Check"}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/enterprise/monitoring/logs')}
              variant="flat"
              size="sm"
              endContent={<ArrowRight size={14} />}
            >
              {"Error Logs"}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/enterprise/monitoring/log-files')}
              variant="flat"
              size="sm"
              endContent={<ArrowRight size={14} />}
            >
              {"Log Files"}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/enterprise/monitoring/requirements')}
              variant="flat"
              size="sm"
              endContent={<ArrowRight size={14} />}
            >
              {"Requirements"}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/enterprise/config/features')}
              variant="flat"
              size="sm"
              endContent={<ToggleLeft size={14} />}
            >
              {"Feature Flags"}
            </Button>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              isLoading={loading}
              size="sm"
            >
              {"Refresh"}
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
          {"Database"}: {health?.db_connected ? "Connected" : "Disconnected"}
        </Chip>
        <Chip
          size="sm"
          variant="flat"
          color={health?.redis_connected ? 'success' : 'danger'}
        >
          {"Redis"}: {health?.redis_connected ? "Connected" : "Disconnected"}
        </Chip>
      </div>

      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      ) : (
        <div className="space-y-6">
          {/* Memory Cards */}
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {/* PHP Process Memory */}
            {memoryPct !== null && (
              <Card shadow="sm">
                <CardBody className="p-4">
                  <div className="flex items-center justify-between mb-2">
                    <div className="flex items-center gap-2">
                      <Server size={18} className="text-warning" />
                      <span className="text-sm font-semibold text-foreground">
                        {"PHP Process Memory"}
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
                    aria-label="PHP memory usage"
                    className="max-w-full"
                  />
                </CardBody>
              </Card>
            )}

            {/* System / VM Memory */}
            {health?.sys_memory && (
              <Card shadow="sm">
                <CardBody className="p-4">
                  <div className="flex items-center justify-between mb-2">
                    <div className="flex items-center gap-2">
                      <Cpu size={18} className="text-primary" />
                      <span className="text-sm font-semibold text-foreground">
                        {"VM Memory"}
                      </span>
                    </div>
                    <span className="text-sm text-default-500">
                      {health.sys_memory.used} / {health.sys_memory.total}
                    </span>
                  </div>
                  <Progress
                    size="md"
                    value={health.sys_memory.used_pct}
                    color={memoryProgressColor(health.sys_memory.used_pct)}
                    showValueLabel
                    aria-label="VM memory usage"
                    className="max-w-full"
                  />
                  <p className="text-xs text-default-400 mt-1">
                    {`${health.sys_memory.available} available`}
                  </p>
                </CardBody>
              </Card>
            )}
          </div>

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
                  <p className="text-sm font-semibold text-foreground">{"Log Files"}</p>
                  <p className="text-xs text-default-500">{"Log Files."}</p>
                </div>
              </CardBody>
            </Card>
            <Card shadow="sm" isPressable as={Link} to={tenantPath('/admin/enterprise/monitoring/requirements')}>
              <CardBody className="flex flex-row items-center gap-3 p-4">
                <Settings size={20} className="text-warning" />
                <div>
                  <p className="text-sm font-semibold text-foreground">{"System Requirements"}</p>
                  <p className="text-xs text-default-500">{"System Requirements."}</p>
                </div>
              </CardBody>
            </Card>
            <Card shadow="sm" isPressable as={Link} to={tenantPath('/admin/enterprise/config/features')}>
              <CardBody className="flex flex-row items-center gap-3 p-4">
                <ToggleLeft size={20} className="text-success" />
                <div>
                  <p className="text-sm font-semibold text-foreground">{"Feature Flags"}</p>
                  <p className="text-xs text-default-500">{"Toggle Features Modules"}</p>
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
