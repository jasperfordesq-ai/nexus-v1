// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * EmergencyAlertBanner — AG70 Emergency/Safety Alert Tier
 *
 * Renders a persistent full-width banner for each active emergency alert.
 * Polls GET /v2/caring-community/emergency-alerts every 5 minutes.
 * Severity colours: info=blue, warning=amber, danger=red.
 * Dismissal calls POST /v2/caring-community/emergency-alerts/{id}/dismiss
 * and hides that individual alert — it does NOT deactivate it for other members.
 *
 * Only rendered when hasFeature('caring_community') is true.
 */

import { useEffect, useState } from 'react';
import { Button } from '@heroui/react';
import AlertTriangle from 'lucide-react/icons/alert-triangle';
import Info from 'lucide-react/icons/info';
import X from 'lucide-react/icons/x';
import { useTranslation } from 'react-i18next';
import api from '@/lib/api';
import { useAuth, useTenant } from '@/contexts';
import { logError } from '@/lib/logger';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface EmergencyAlert {
  id: number;
  title: string;
  body: string;
  severity: 'info' | 'warning' | 'danger';
  expires_at: string | null;
  created_at: string;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const POLL_INTERVAL_MS = 5 * 60 * 1000; // 5 minutes

const SEVERITY_CLASSES: Record<EmergencyAlert['severity'], string> = {
  info: [
    'bg-blue-50 dark:bg-blue-950/60',
    'border-blue-300 dark:border-blue-700',
    'text-blue-900 dark:text-blue-100',
  ].join(' '),
  warning: [
    'bg-amber-50 dark:bg-amber-950/60',
    'border-amber-400 dark:border-amber-600',
    'text-amber-900 dark:text-amber-100',
  ].join(' '),
  danger: [
    'bg-red-50 dark:bg-red-950/60',
    'border-red-400 dark:border-red-600',
    'text-red-900 dark:text-red-100',
  ].join(' '),
};

const ICON_CLASSES: Record<EmergencyAlert['severity'], string> = {
  info: 'text-blue-500 dark:text-blue-400',
  warning: 'text-amber-500 dark:text-amber-400',
  danger: 'text-red-500 dark:text-red-400',
};

const DISMISS_CLASSES: Record<EmergencyAlert['severity'], string> = {
  info: 'text-blue-700 dark:text-blue-300',
  warning: 'text-amber-700 dark:text-amber-300',
  danger: 'text-red-700 dark:text-red-300',
};

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export default function EmergencyAlertBanner() {
  const { t } = useTranslation('caring_community');
  const { hasFeature } = useTenant();
  const { isAuthenticated } = useAuth();

  const [alerts, setAlerts] = useState<EmergencyAlert[]>([]);
  const [dismissed, setDismissed] = useState<Set<number>>(new Set());

  const fetchAlerts = async () => {
    try {
      const res = await api.get<{ data: EmergencyAlert[] }>('/v2/caring-community/emergency-alerts');
      const data = res.data;
      // Unwrap v2 envelope: data may already be the array or wrapped in { data: [...] }
      const list: EmergencyAlert[] = Array.isArray(data)
        ? data
        : (Array.isArray((data as { data?: EmergencyAlert[] }).data)
            ? (data as { data: EmergencyAlert[] }).data
            : []);
      setAlerts(list);
    } catch (err) {
      logError('EmergencyAlertBanner: fetchAlerts failed', err);
    }
  };

  useEffect(() => {
    if (!hasFeature('caring_community') || !isAuthenticated) {
      setAlerts([]);
      return;
    }

    void fetchAlerts();

    const interval = setInterval(() => {
      void fetchAlerts();
    }, POLL_INTERVAL_MS);

    return () => clearInterval(interval);
  }, [hasFeature, isAuthenticated]);

  if (!hasFeature('caring_community') || !isAuthenticated) return null;

  const visible = alerts.filter((a) => !dismissed.has(a.id));
  if (visible.length === 0) return null;

  const handleDismiss = async (id: number) => {
    // Optimistically hide immediately
    setDismissed((prev) => new Set(prev).add(id));
    try {
      await api.post(`/v2/caring-community/emergency-alerts/${id}/dismiss`);
    } catch (err) {
      logError('EmergencyAlertBanner: dismiss failed', err);
    }
  };

  return (
    <div className="w-full flex flex-col gap-0" role="region" aria-label={t('emergency_alert.region_label')}>
      {visible.map((alert) => (
        <div
          key={alert.id}
          className={[
            'w-full border-b-2 px-4 py-3 sm:py-4',
            SEVERITY_CLASSES[alert.severity],
          ].join(' ')}
          role="alert"
          aria-live={alert.severity === 'danger' ? 'assertive' : 'polite'}
        >
          <div className="max-w-5xl mx-auto flex items-start gap-3">
            {/* Icon */}
            <span className={['mt-0.5 shrink-0', ICON_CLASSES[alert.severity]].join(' ')}>
              {alert.severity === 'info' ? (
                <Info size={22} aria-hidden="true" />
              ) : (
                <AlertTriangle size={22} aria-hidden="true" />
              )}
            </span>

            {/* Content */}
            <div className="flex-1 min-w-0">
              <p className="font-bold text-base sm:text-lg leading-snug">{alert.title}</p>
              <p className="mt-1 text-sm sm:text-base leading-relaxed">{alert.body}</p>
            </div>

            {/* Dismiss button */}
            <Button
              type="button"
              isIconOnly
              size="sm"
              variant="light"
              onPress={() => void handleDismiss(alert.id)}
              className={['shrink-0', DISMISS_CLASSES[alert.severity]].join(' ')}
              aria-label={t('emergency_alert.dismiss')}
            >
              <X size={18} aria-hidden="true" />
            </Button>
          </div>
        </div>
      ))}
    </div>
  );
}
