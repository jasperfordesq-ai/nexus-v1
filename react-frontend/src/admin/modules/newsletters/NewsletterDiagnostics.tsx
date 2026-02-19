// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Newsletter Diagnostics
 * Email health dashboard - queue status, bounce rate, configuration checks
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Button, Card, CardBody, CardHeader, Chip, Progress,
} from '@heroui/react';
import {
  RefreshCw, AlertCircle, CheckCircle, XCircle, Mail, Settings, Activity, Wrench,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminNewsletters } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { NewsletterDiagnostics as DiagnosticsData } from '../../api/types';

export function NewsletterDiagnostics() {
  usePageTitle('Admin - Newsletter Diagnostics');
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<DiagnosticsData | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminNewsletters.getDiagnostics();
      if (res.success && res.data) {
        setData(res.data as DiagnosticsData);
      }
    } catch {
      setData(null);
      toast.error('Failed to load diagnostics');
    }
    setLoading(false);
  }, [toast]);

  useEffect(() => { loadData(); }, [loadData]);

  const getHealthColor = (status: string) => {
    switch (status) {
      case 'healthy': return 'success';
      case 'warning': return 'warning';
      case 'critical': return 'danger';
      default: return 'default';
    }
  };

  const getHealthIcon = (status: string) => {
    switch (status) {
      case 'healthy': return <CheckCircle size={24} className="text-success" />;
      case 'warning': return <AlertCircle size={24} className="text-warning" />;
      case 'critical': return <XCircle size={24} className="text-danger" />;
      default: return <Activity size={24} className="text-default-400" />;
    }
  };

  const getConfigIcon = (enabled: boolean) => {
    return enabled ? (
      <CheckCircle size={16} className="text-success" />
    ) : (
      <XCircle size={16} className="text-danger" />
    );
  };

  const queueTotal = data?.queue_status?.total || 0;
  const queuePending = data?.queue_status?.pending || 0;
  const queueSending = data?.queue_status?.sending || 0;
  const queueSent = data?.queue_status?.sent || 0;
  const queueFailed = data?.queue_status?.failed || 0;

  const queueHealth = queueTotal > 0
    ? ((queueSent / queueTotal) * 100)
    : 100;

  return (
    <div>
      <PageHeader
        title="Newsletter Diagnostics"
        description="Email health dashboard and system checks"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
          >
            Refresh
          </Button>
        }
      />

      <div className="grid gap-6">
        {/* Overall Health */}
        <Card>
          <CardBody className="flex-row items-center gap-4">
            {data && getHealthIcon(data.health_status)}
            <div className="flex-1">
              <p className="text-lg font-semibold">System Health</p>
              <p className="text-sm text-default-500">
                {data?.health_status === 'healthy' && 'All systems operational'}
                {data?.health_status === 'warning' && 'Minor issues detected'}
                {data?.health_status === 'critical' && 'Critical issues require attention'}
              </p>
            </div>
            {data && (
              <Chip
                color={getHealthColor(data.health_status)}
                variant="flat"
                size="lg"
              >
                {data.health_status.toUpperCase()}
              </Chip>
            )}
          </CardBody>
        </Card>

        <div className="grid gap-6 md:grid-cols-2">
          {/* Queue Status */}
          <Card>
            <CardHeader className="flex gap-2 items-center">
              <Mail size={20} />
              <span>Queue Status</span>
            </CardHeader>
            <CardBody className="gap-4">
              {loading ? (
                <div className="flex items-center justify-center py-8">
                  <div className="text-default-400">Loading...</div>
                </div>
              ) : (
                <>
                  <div className="space-y-3">
                    <div className="flex items-center justify-between">
                      <span className="text-sm text-default-600">Total</span>
                      <span className="font-semibold">{queueTotal.toLocaleString()}</span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-sm text-default-600">Pending</span>
                      <Chip size="sm" color="warning" variant="flat">{queuePending}</Chip>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-sm text-default-600">Sending</span>
                      <Chip size="sm" color="primary" variant="flat">{queueSending}</Chip>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-sm text-default-600">Sent</span>
                      <Chip size="sm" color="success" variant="flat">{queueSent}</Chip>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-sm text-default-600">Failed</span>
                      <Chip size="sm" color="danger" variant="flat">{queueFailed}</Chip>
                    </div>
                  </div>

                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-sm text-default-600">Success Rate</span>
                      <span className="text-sm font-semibold">{queueHealth.toFixed(1)}%</span>
                    </div>
                    <Progress
                      value={queueHealth}
                      color={queueHealth > 90 ? 'success' : queueHealth > 70 ? 'warning' : 'danger'}
                      size="sm"
                    />
                  </div>

                  {queueFailed > 10 && (
                    <Button
                      size="sm"
                      variant="flat"
                      color="warning"
                      startContent={<Wrench size={16} />}
                    >
                      Repair Queue
                    </Button>
                  )}
                </>
              )}
            </CardBody>
          </Card>

          {/* Bounce Rate */}
          <Card>
            <CardHeader className="flex gap-2 items-center">
              <Activity size={20} />
              <span>Delivery Health</span>
            </CardHeader>
            <CardBody className="gap-4">
              {loading ? (
                <div className="flex items-center justify-center py-8">
                  <div className="text-default-400">Loading...</div>
                </div>
              ) : (
                <>
                  <div>
                    <p className="text-sm text-default-600 mb-2">Bounce Rate</p>
                    <div className="flex items-baseline gap-2">
                      <span className="text-3xl font-bold">
                        {data?.bounce_rate?.toFixed(2) || '0.00'}%
                      </span>
                      {data && data.bounce_rate < 5 && (
                        <Chip size="sm" color="success" variant="flat">Good</Chip>
                      )}
                      {data && data.bounce_rate >= 5 && data.bounce_rate < 10 && (
                        <Chip size="sm" color="warning" variant="flat">Warning</Chip>
                      )}
                      {data && data.bounce_rate >= 10 && (
                        <Chip size="sm" color="danger" variant="flat">Critical</Chip>
                      )}
                    </div>
                  </div>

                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-sm text-default-600">Health</span>
                      <span className="text-sm font-semibold">
                        {data && data.bounce_rate < 5 ? '95%+' : data && data.bounce_rate < 10 ? '85-95%' : '<85%'}
                      </span>
                    </div>
                    <Progress
                      value={data ? Math.max(0, 100 - (data.bounce_rate * 2)) : 100}
                      color={data && data.bounce_rate < 5 ? 'success' : data && data.bounce_rate < 10 ? 'warning' : 'danger'}
                      size="sm"
                    />
                  </div>

                  <div>
                    <p className="text-sm text-default-600 mb-2">Sender Score</p>
                    <div className="flex items-baseline gap-2">
                      <span className="text-3xl font-bold text-success">
                        {data?.sender_score || 100}
                      </span>
                      <span className="text-sm text-default-500">/ 100</span>
                    </div>
                  </div>
                </>
              )}
            </CardBody>
          </Card>
        </div>

        {/* Configuration */}
        <Card>
          <CardHeader className="flex gap-2 items-center">
            <Settings size={20} />
            <span>Email Configuration</span>
          </CardHeader>
          <CardBody>
            {loading ? (
              <div className="flex items-center justify-center py-8">
                <div className="text-default-400">Loading...</div>
              </div>
            ) : (
              <div className="grid gap-3 md:grid-cols-3">
                <div className="flex items-center gap-3 p-3 rounded-lg bg-default-100 dark:bg-default-50">
                  {getConfigIcon(data?.configuration?.smtp_configured || false)}
                  <div className="flex-1">
                    <p className="text-sm font-medium">SMTP Configured</p>
                    <p className="text-xs text-default-500">
                      {data?.configuration?.smtp_configured ? 'Active' : 'Not configured'}
                    </p>
                  </div>
                </div>

                <div className="flex items-center gap-3 p-3 rounded-lg bg-default-100 dark:bg-default-50">
                  {getConfigIcon(data?.configuration?.api_configured || false)}
                  <div className="flex-1">
                    <p className="text-sm font-medium">Gmail API</p>
                    <p className="text-xs text-default-500">
                      {data?.configuration?.api_configured ? 'Active' : 'Not configured'}
                    </p>
                  </div>
                </div>

                <div className="flex items-center gap-3 p-3 rounded-lg bg-default-100 dark:bg-default-50">
                  {getConfigIcon(data?.configuration?.tracking_enabled || false)}
                  <div className="flex-1">
                    <p className="text-sm font-medium">Tracking Enabled</p>
                    <p className="text-xs text-default-500">
                      {data?.configuration?.tracking_enabled ? 'Active' : 'Disabled'}
                    </p>
                  </div>
                </div>
              </div>
            )}
          </CardBody>
        </Card>

        {/* Recommendations */}
        {data && data.health_status !== 'healthy' && (
          <Card className="bg-warning-50 dark:bg-warning-50/10">
            <CardHeader>
              <div className="flex items-center gap-2">
                <AlertCircle size={20} className="text-warning" />
                <span className="font-semibold text-warning">Recommendations</span>
              </div>
            </CardHeader>
            <CardBody>
              <ul className="space-y-2 text-sm text-warning-700 dark:text-warning-300">
                {data.bounce_rate > 5 && (
                  <li>• High bounce rate detected. Review your email list quality and consider removing invalid addresses.</li>
                )}
                {queueFailed > 10 && (
                  <li>• {queueFailed} failed sends detected. Check SMTP configuration and email service status.</li>
                )}
                {!data.configuration.smtp_configured && !data.configuration.api_configured && (
                  <li>• No email service configured. Set up SMTP or Gmail API to send newsletters.</li>
                )}
              </ul>
            </CardBody>
          </Card>
        )}
      </div>
    </div>
  );
}

export default NewsletterDiagnostics;
