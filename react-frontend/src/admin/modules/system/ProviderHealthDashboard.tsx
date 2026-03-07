// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ProviderHealthDashboard
 * Admin component showing health/status of all identity verification providers.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card, CardBody, CardHeader, Chip, Progress, Spinner, Tooltip,
} from '@heroui/react';
import {
  Activity, CheckCircle, XCircle, Clock, AlertTriangle, Shield, Wifi, WifiOff,
} from 'lucide-react';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';

interface ProviderHealthStats {
  total_sessions: number;
  passed: number;
  failed: number;
  pending: number;
  expired: number;
  success_rate: number | null;
  last_session_at: string | null;
  last_success_at: string | null;
  last_failure_at: string | null;
}

interface ProviderRecent24h {
  total: number;
  passed: number;
  failed: number;
}

interface ProviderLastWebhook {
  at: string;
  type: string;
}

interface ProviderHealth {
  slug: string;
  name: string;
  available: boolean;
  supported_levels: string[];
  latency_ms: number | null;
  avg_completion_seconds: number | null;
  stats: ProviderHealthStats;
  recent_24h: ProviderRecent24h;
  last_webhook: ProviderLastWebhook | null;
}

/**
 * Format a date string as relative time (e.g., "2 hours ago").
 */
function formatRelativeTime(dateStr: string | null): string {
  if (!dateStr) return 'Never';

  const date = new Date(dateStr);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffSec = Math.floor(diffMs / 1000);

  if (diffSec < 60) return 'Just now';
  if (diffSec < 3600) {
    const mins = Math.floor(diffSec / 60);
    return `${mins} minute${mins === 1 ? '' : 's'} ago`;
  }
  if (diffSec < 86400) {
    const hours = Math.floor(diffSec / 3600);
    return `${hours} hour${hours === 1 ? '' : 's'} ago`;
  }
  if (diffSec < 604800) {
    const days = Math.floor(diffSec / 86400);
    return `${days} day${days === 1 ? '' : 's'} ago`;
  }

  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

/**
 * Format a verification level slug to a readable label.
 */
function formatLevel(level: string): string {
  return level
    .split('_')
    .map(w => w.charAt(0).toUpperCase() + w.slice(1))
    .join(' ');
}

export function ProviderHealthDashboard() {
  const toast = useToast();
  const [providers, setProviders] = useState<ProviderHealth[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchHealth = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<ProviderHealth[]>('/v2/admin/identity/provider-health');
      if (res.data) {
        setProviders(Array.isArray(res.data) ? res.data : []);
      }
    } catch {
      toast.error('Failed to load provider health data');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    fetchHealth();
  }, [fetchHealth]);

  if (loading) {
    return (
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold flex items-center gap-2">
            <Activity size={20} /> Provider Health Dashboard
          </h3>
        </CardHeader>
        <CardBody>
          <div className="flex justify-center py-8">
            <Spinner size="lg" />
          </div>
        </CardBody>
      </Card>
    );
  }

  if (providers.length === 0) {
    return (
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold flex items-center gap-2">
            <Activity size={20} /> Provider Health Dashboard
          </h3>
        </CardHeader>
        <CardBody>
          <p className="text-sm text-default-500 text-center py-4">
            No identity verification providers registered.
          </p>
        </CardBody>
      </Card>
    );
  }

  return (
    <Card shadow="sm">
      <CardHeader>
        <h3 className="text-lg font-semibold flex items-center gap-2">
          <Activity size={20} /> Provider Health Dashboard
        </h3>
      </CardHeader>
      <CardBody>
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          {providers.map((p) => (
            <ProviderCard key={p.slug} provider={p} />
          ))}
        </div>
      </CardBody>
    </Card>
  );
}

function ProviderCard({ provider }: { provider: ProviderHealth }) {
  const { stats, recent_24h, last_webhook } = provider;
  const successColor = stats.success_rate === null
    ? 'default'
    : stats.success_rate >= 80
      ? 'success'
      : stats.success_rate >= 50
        ? 'warning'
        : 'danger';

  return (
    <Card shadow="sm" className="border border-default-200 dark:border-default-100">
      <CardBody className="gap-3">
        {/* Header row: name + availability */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Shield size={18} className="text-default-500" />
            <span className="font-semibold text-base">{provider.name}</span>
          </div>
          {provider.available ? (
            <Tooltip content="Provider is available and configured">
              <Chip size="sm" color="success" variant="flat" startContent={<Wifi size={12} />}>
                Available
              </Chip>
            </Tooltip>
          ) : (
            <Tooltip content="Provider is not configured or unavailable">
              <Chip size="sm" color="danger" variant="flat" startContent={<WifiOff size={12} />}>
                Unavailable
              </Chip>
            </Tooltip>
          )}
        </div>

        {/* Supported levels */}
        <div className="flex flex-wrap gap-1">
          {provider.supported_levels.map((level) => (
            <Chip key={level} size="sm" variant="bordered" className="text-xs">
              {formatLevel(level)}
            </Chip>
          ))}
        </div>

        {/* Latency metrics */}
        <div className="flex items-center gap-3 text-sm">
          <Tooltip content="API health check response time">
            <Chip size="sm" variant="flat" color={provider.latency_ms !== null && provider.latency_ms < 500 ? 'success' : provider.latency_ms !== null && provider.latency_ms < 2000 ? 'warning' : 'default'} className="text-xs">
              {provider.latency_ms !== null ? `${provider.latency_ms}ms` : 'N/A'} latency
            </Chip>
          </Tooltip>
          {provider.avg_completion_seconds !== null && (
            <Tooltip content="Average time to complete verification (30d)">
              <Chip size="sm" variant="flat" color="default" className="text-xs">
                {provider.avg_completion_seconds < 60
                  ? `${provider.avg_completion_seconds}s avg`
                  : `${Math.round(provider.avg_completion_seconds / 60)}m avg`}
              </Chip>
            </Tooltip>
          )}
        </div>

        {/* Success rate */}
        <div className="space-y-1">
          <div className="flex items-center justify-between text-sm">
            <span className="text-default-500">Success Rate</span>
            <span className="font-medium">
              {stats.success_rate !== null ? `${stats.success_rate}%` : 'N/A'}
            </span>
          </div>
          <Progress
            size="sm"
            value={stats.success_rate ?? 0}
            maxValue={100}
            color={successColor}
            aria-label="Success rate"
          />
        </div>

        {/* Session counts */}
        <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
          <div className="flex items-center gap-1.5">
            <Activity size={14} className="text-default-400" />
            <span className="text-default-500">Total</span>
            <span className="ml-auto font-medium">{stats.total_sessions}</span>
          </div>
          <div className="flex items-center gap-1.5">
            <CheckCircle size={14} className="text-success" />
            <span className="text-default-500">Passed</span>
            <span className="ml-auto font-medium">{stats.passed}</span>
          </div>
          <div className="flex items-center gap-1.5">
            <XCircle size={14} className="text-danger" />
            <span className="text-default-500">Failed</span>
            <span className="ml-auto font-medium">{stats.failed}</span>
          </div>
          <div className="flex items-center gap-1.5">
            <Clock size={14} className="text-warning" />
            <span className="text-default-500">Pending</span>
            <span className="ml-auto font-medium">{stats.pending}</span>
          </div>
          <div className="flex items-center gap-1.5">
            <AlertTriangle size={14} className="text-default-400" />
            <span className="text-default-500">Expired</span>
            <span className="ml-auto font-medium">{stats.expired}</span>
          </div>
        </div>

        {/* 24h stats */}
        {recent_24h.total > 0 && (
          <div className="rounded-lg bg-default-100 dark:bg-default-50 p-2">
            <p className="text-xs font-medium text-default-500 mb-1">Last 24 hours</p>
            <div className="flex items-center gap-3 text-sm">
              <span>{recent_24h.total} session{recent_24h.total !== 1 ? 's' : ''}</span>
              <span className="text-success">{recent_24h.passed} passed</span>
              <span className="text-danger">{recent_24h.failed} failed</span>
            </div>
          </div>
        )}

        {/* Timestamps */}
        <div className="space-y-1 text-xs text-default-400 pt-1 border-t border-default-100">
          <Tooltip content={stats.last_session_at || 'No sessions yet'}>
            <div className="flex items-center justify-between">
              <span>Last session</span>
              <span>{formatRelativeTime(stats.last_session_at)}</span>
            </div>
          </Tooltip>
          <Tooltip content={stats.last_success_at || 'No successful sessions'}>
            <div className="flex items-center justify-between">
              <span>Last success</span>
              <span>{formatRelativeTime(stats.last_success_at)}</span>
            </div>
          </Tooltip>
          <Tooltip content={stats.last_failure_at || 'No failed sessions'}>
            <div className="flex items-center justify-between">
              <span>Last failure</span>
              <span>{formatRelativeTime(stats.last_failure_at)}</span>
            </div>
          </Tooltip>
          {last_webhook && (
            <Tooltip content={`Event: ${last_webhook.type} at ${last_webhook.at}`}>
              <div className="flex items-center justify-between">
                <span>Last webhook</span>
                <span>{formatRelativeTime(last_webhook.at)}</span>
              </div>
            </Tooltip>
          )}
        </div>
      </CardBody>
    </Card>
  );
}

export default ProviderHealthDashboard;
