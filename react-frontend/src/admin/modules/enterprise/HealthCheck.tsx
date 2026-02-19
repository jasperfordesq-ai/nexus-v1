// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Health Check
 * Quick system health status indicators.
 */

import { useEffect, useState, useCallback } from 'react';
import { Card, CardBody, Button, Spinner, Chip } from '@heroui/react';
import {
  CheckCircle,
  XCircle,
  RefreshCw,
  HeartPulse,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { HealthCheckResult } from '../../api/types';

export function HealthCheck() {
  usePageTitle('Admin - Health Check');

  const [result, setResult] = useState<HealthCheckResult | null>(null);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getHealthCheck();
      if (res.success && res.data) {
        setResult(res.data as unknown as HealthCheckResult);
      }
    } catch {
      setResult({
        status: 'unhealthy',
        checks: [{ name: 'API', status: 'fail' }],
      });
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const statusColor = result?.status === 'healthy' ? 'success' : result?.status === 'degraded' ? 'warning' : 'danger';

  return (
    <div>
      <PageHeader
        title="Health Check"
        description="Quick system health status"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
            size="sm"
          >
            Refresh
          </Button>
        }
      />

      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      ) : result ? (
        <div className="space-y-4">
          {/* Overall Status */}
          <Card shadow="sm">
            <CardBody className="flex flex-row items-center gap-4 p-6">
              <div className={`flex h-14 w-14 items-center justify-center rounded-full bg-${statusColor}/10`}>
                <HeartPulse size={28} className={`text-${statusColor}`} />
              </div>
              <div>
                <h3 className="text-lg font-bold text-foreground">System Status</h3>
                <Chip size="sm" variant="flat" color={statusColor} className="mt-1 capitalize">
                  {result.status}
                </Chip>
              </div>
            </CardBody>
          </Card>

          {/* Individual Checks */}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {result.checks.map((check) => (
              <Card key={check.name} shadow="sm">
                <CardBody className="flex flex-row items-center gap-3 p-4">
                  {check.status === 'ok' ? (
                    <CheckCircle size={20} className="text-success shrink-0" />
                  ) : (
                    <XCircle size={20} className="text-danger shrink-0" />
                  )}
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-foreground">{check.name}</p>
                    <p className="text-xs text-default-500">
                      {check.status === 'ok' ? 'Operational' : 'Failed'}
                      {check.free && ` | Free: ${check.free}`}
                      {check.total && ` / Total: ${check.total}`}
                    </p>
                  </div>
                  <Chip
                    size="sm"
                    variant="flat"
                    color={check.status === 'ok' ? 'success' : 'danger'}
                  >
                    {check.status === 'ok' ? 'OK' : 'FAIL'}
                  </Chip>
                </CardBody>
              </Card>
            ))}
          </div>
        </div>
      ) : null}
    </div>
  );
}

export default HealthCheck;
