// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Health Check
 * System health status indicators with history.
 */

import { useEffect, useState, useCallback } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Spinner,
  Chip,
  Divider,
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableColumn,
  TableCell,
} from '@heroui/react';
import {
  CheckCircle,
  XCircle,
  RefreshCw,
  HeartPulse,
  History,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { HealthCheckResult, HealthCheckHistoryEntry } from '../../api/types';

import { useTranslation } from 'react-i18next';

function statusBorderClass(status: string): string {
  switch (status) {
    case 'ok':
      return 'border-success';
    case 'fail':
      return 'border-danger';
    default:
      return 'border-default-200';
  }
}

export function HealthCheck() {
  const { t } = useTranslation('admin');
  usePageTitle(t('enterprise.page_title'));

  const [result, setResult] = useState<HealthCheckResult | null>(null);
  const [history, setHistory] = useState<HealthCheckHistoryEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [historyLoading, setHistoryLoading] = useState(true);

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

  const loadHistory = useCallback(async () => {
    setHistoryLoading(true);
    try {
      const res = await adminEnterprise.getHealthHistory();
      if (res.success && res.data) {
        const data = res.data as unknown;
        setHistory(Array.isArray(data) ? data.slice(0, 10) : []);
      }
    } catch {
      // Silently handle — history is supplementary
    } finally {
      setHistoryLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
    loadHistory();
  }, [loadData, loadHistory]);

  const handleRefresh = () => {
    loadData();
    loadHistory();
  };

  const statusColor = result?.status === 'healthy' ? 'success' : result?.status === 'degraded' ? 'warning' : 'danger';

  return (
    <div>
      <PageHeader
        title={t('enterprise.health_check_title')}
        description={t('enterprise.health_check_desc')}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={handleRefresh}
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
      ) : result ? (
        <div className="space-y-6">
          {/* Overall Status */}
          <Card shadow="sm">
            <CardBody className="flex flex-row items-center gap-4 p-6">
              <div className={`flex h-14 w-14 items-center justify-center rounded-full bg-${statusColor}/10`}>
                <HeartPulse size={28} className={`text-${statusColor}`} />
              </div>
              <div>
                <h3 className="text-lg font-bold text-foreground">{t('enterprise.system_status')}</h3>
                <Chip size="sm" variant="flat" color={statusColor} className="mt-1 capitalize">
                  {result.status}
                </Chip>
              </div>
            </CardBody>
          </Card>

          {/* Individual Checks - Enhanced with colored borders */}
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {result.checks.map((check) => (
              <Card
                key={check.name}
                shadow="sm"
                className={`border-l-4 ${statusBorderClass(check.status)}`}
              >
                <CardBody className="flex flex-row items-center gap-4 p-5">
                  <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-full ${check.status === 'ok' ? 'bg-success/10' : 'bg-danger/10'}`}>
                    {check.status === 'ok' ? (
                      <CheckCircle size={24} className="text-success" />
                    ) : (
                      <XCircle size={24} className="text-danger" />
                    )}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="text-base font-semibold text-foreground">{check.name}</p>
                    <p className="text-sm text-default-500">
                      {check.status === 'ok' ? t('enterprise.operational') : t('enterprise.failed')}
                    </p>
                    {(check.free || check.total) && (
                      <p className="text-xs text-default-400 mt-1">
                        {check.free && `Free: ${check.free}`}
                        {check.free && check.total && ' / '}
                        {check.total && `Total: ${check.total}`}
                      </p>
                    )}
                  </div>
                  <Chip
                    size="sm"
                    variant="flat"
                    color={check.status === 'ok' ? 'success' : 'danger'}
                  >
                    {check.status === 'ok' ? t('system.status_ok') : t('system.status_fail')}
                  </Chip>
                </CardBody>
              </Card>
            ))}
          </div>

          {/* Health Check History */}
          <Card shadow="sm">
            <CardHeader className="flex items-center gap-2 px-6 pt-5 pb-0">
              <History size={18} className="text-default-500" />
              <h3 className="text-base font-semibold">{t('shared.history')}</h3>
              <Chip size="sm" variant="flat" color="default">
                {t('enterprise.last_n_checks', { count: history.length })}
              </Chip>
            </CardHeader>
            <Divider className="mt-3" />
            <CardBody className="px-0 pb-0">
              {historyLoading ? (
                <div className="flex justify-center py-8">
                  <Spinner size="sm" />
                </div>
              ) : history.length === 0 ? (
                <div className="py-8 text-center">
                  <p className="text-default-500">{t('shared.no_history_available')}</p>
                </div>
              ) : (
                <Table aria-label="Health check history" removeWrapper>
                  <TableHeader>
                    <TableColumn>{t('enterprise.col_status')}</TableColumn>
                    <TableColumn>{t('enterprise.col_latency')}</TableColumn>
                    <TableColumn>{t('enterprise.col_timestamp')}</TableColumn>
                  </TableHeader>
                  <TableBody>
                    {history.map((entry) => {
                      const histColor = entry.status === 'healthy' ? 'success' : entry.status === 'degraded' ? 'warning' : 'danger';
                      return (
                        <TableRow key={entry.id}>
                          <TableCell>
                            <Chip size="sm" variant="flat" color={histColor} className="capitalize">
                              {entry.status}
                            </Chip>
                          </TableCell>
                          <TableCell>
                            <span className="font-mono text-default-600">
                              {entry.latency_ms !== null ? `${entry.latency_ms} ms` : '---'}
                            </span>
                          </TableCell>
                          <TableCell>
                            <span className="text-default-500">
                              {new Date(entry.created_at).toLocaleString()}
                            </span>
                          </TableCell>
                        </TableRow>
                      );
                    })}
                  </TableBody>
                </Table>
              )}
            </CardBody>
          </Card>
        </div>
      ) : null}
    </div>
  );
}

export default HealthCheck;
