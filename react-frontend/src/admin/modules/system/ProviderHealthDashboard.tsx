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
import { useTranslation } from 'react-i18next';
import type { TFunction } from 'i18next';
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
function formatRelativeTime(dateStr: string | null, t: TFunction): string {
  if (!dateStr) return t('system.never');

  const date = new Date(dateStr);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffSec = Math.floor(diffMs / 1000);

  if (diffSec < 60) return t('system.just_now');
  if (diffSec < 3600) {
    const mins = Math.floor(diffSec / 60);
    return t('system.n_minutes_ago', { count: mins });
  }
  if (diffSec < 86400) {
    const hours = Math.floor(diffSec / 3600);
    return t('system.n_hours_ago', { count: hours });
  }
  if (diffSec < 604800) {
    const days = Math.floor(diffSec / 86400);
    return t('system.n_days_ago', { count: days });
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
  const { t } = useTranslation('admin');
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
      toast.error(t('system.failed_to_load_provider_health'));
    } finally {
      setLoading(false);
    }
  }, [toast, t])

  useEffect(() => {
    fetchHealth();
  }, [fetchHealth]);

  if (loading) {
    return (
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold flex items-center gap-2">
            <Activity size={20} /> {t('system.provider_health_title')}
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
            <Activity size={20} /> {t('system.provider_health_title')}
          </h3>
        </CardHeader>
        <CardBody>
          <p className="text-sm text-default-500 text-center py-4">
            {t('system.no_identity_providers')}
          </p>
        </CardBody>
      </Card>
    );
  }

  return (
    <Card shadow="sm">
      <CardHeader>
        <h3 className="text-lg font-semibold flex items-center gap-2">
          <Activity size={20} /> {t('system.provider_health_title')}
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
  const { t } = useTranslation('admin');
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
            <Tooltip content={t('system.tooltip_provider_available')}>
              <Chip size="sm" color="success" variant="flat" startContent={<Wifi size={12} />}>
                {t('system.provider_available')}
              </Chip>
            </Tooltip>
          ) : (
            <Tooltip content={t('system.tooltip_provider_unavailable')}>
              <Chip size="sm" color="danger" variant="flat" startContent={<WifiOff size={12} />}>
                {t('system.provider_unavailable')}
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
          <Tooltip content={t('system.tooltip_api_latency')}>
            <Chip size="sm" variant="flat" color={provider.latency_ms !== null && provider.latency_ms < 500 ? 'success' : provider.latency_ms !== null && provider.latency_ms < 2000 ? 'warning' : 'default'} className="text-xs">
              {provider.latency_ms !== null ? t('system.latency_ms', { ms: provider.latency_ms }) : t('system.n_a')}
            </Chip>
          </Tooltip>
          {provider.avg_completion_seconds !== null && (
            <Tooltip content={t('system.tooltip_avg_completion')}>
              <Chip size="sm" variant="flat" color="default" className="text-xs">
                {provider.avg_completion_seconds < 60
                  ? t('system.avg_completion_seconds', { s: provider.avg_completion_seconds })
                  : t('system.avg_completion_minutes', { m: Math.round(provider.avg_completion_seconds / 60) })}
              </Chip>
            </Tooltip>
          )}
        </div>

        {/* Success rate */}
        <div className="space-y-1">
          <div className="flex items-center justify-between text-sm">
            <span className="text-default-500">{t('system.success_rate_label')}</span>
            <span className="font-medium">
              {stats.success_rate !== null ? `${stats.success_rate}%` : t('system.n_a')}
            </span>
          </div>
          <Progress
            size="sm"
            value={stats.success_rate ?? 0}
            maxValue={100}
            color={successColor}
            aria-label={t('provider_health.aria_success_rate')}
          />
        </div>

        {/* Session counts */}
        <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
          <div className="flex items-center gap-1.5">
            <Activity size={14} className="text-default-400" />
            <span className="text-default-500">{t('system.stat_total')}</span>
            <span className="ml-auto font-medium">{stats.total_sessions}</span>
          </div>
          <div className="flex items-center gap-1.5">
            <CheckCircle size={14} className="text-success" />
            <span className="text-default-500">{t('system.stat_passed')}</span>
            <span className="ml-auto font-medium">{stats.passed}</span>
          </div>
          <div className="flex items-center gap-1.5">
            <XCircle size={14} className="text-danger" />
            <span className="text-default-500">{t('system.stat_failed')}</span>
            <span className="ml-auto font-medium">{stats.failed}</span>
          </div>
          <div className="flex items-center gap-1.5">
            <Clock size={14} className="text-warning" />
            <span className="text-default-500">{t('system.stat_pending')}</span>
            <span className="ml-auto font-medium">{stats.pending}</span>
          </div>
          <div className="flex items-center gap-1.5">
            <AlertTriangle size={14} className="text-default-400" />
            <span className="text-default-500">{t('system.stat_expired')}</span>
            <span className="ml-auto font-medium">{stats.expired}</span>
          </div>
        </div>

        {/* 24h stats */}
        {recent_24h.total > 0 && (
          <div className="rounded-lg bg-default-100 dark:bg-default-50 p-2">
            <p className="text-xs font-medium text-default-500 mb-1">{t('system.last_24h_label')}</p>
            <div className="flex items-center gap-3 text-sm">
              <span>{t('system.n_sessions', { count: recent_24h.total })}</span>
              <span className="text-success">{recent_24h.passed} {t('system.stat_passed').toLowerCase()}</span>
              <span className="text-danger">{recent_24h.failed} {t('system.stat_failed').toLowerCase()}</span>
            </div>
          </div>
        )}

        {/* Timestamps */}
        <div className="space-y-1 text-xs text-default-400 pt-1 border-t border-default-100">
          <Tooltip content={stats.last_session_at || t('system.no_sessions_yet')}>
            <div className="flex items-center justify-between">
              <span>{t('system.last_session_label')}</span>
              <span>{formatRelativeTime(stats.last_session_at, t)}</span>
            </div>
          </Tooltip>
          <Tooltip content={stats.last_success_at || t('system.no_successful_sessions')}>
            <div className="flex items-center justify-between">
              <span>{t('system.last_success_label')}</span>
              <span>{formatRelativeTime(stats.last_success_at, t)}</span>
            </div>
          </Tooltip>
          <Tooltip content={stats.last_failure_at || t('system.no_failed_sessions')}>
            <div className="flex items-center justify-between">
              <span>{t('system.last_failure_label')}</span>
              <span>{formatRelativeTime(stats.last_failure_at, t)}</span>
            </div>
          </Tooltip>
          {last_webhook && (
            <Tooltip content={t('system.tooltip_last_webhook_event', { type: last_webhook.type, at: last_webhook.at })}>
              <div className="flex items-center justify-between">
                <span>{t('system.last_webhook_label')}</span>
                <span>{formatRelativeTime(last_webhook.at, t)}</span>
              </div>
            </Tooltip>
          )}
        </div>
      </CardBody>
    </Card>
  );
}

export default ProviderHealthDashboard;
