// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * EmergencyAlertsTab - View and respond to urgent shift-fill requests (V9)
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import {
  Button,
  Chip,
} from '@heroui/react';
import {
  AlertTriangle,
  Bell,
  Calendar,
  Clock,
  MapPin,
  Building2,
  CheckCircle,
  XCircle,
  RefreshCw,
  Siren,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { useToast } from '@/contexts';

interface EmergencyAlert {
  id: number;
  priority: 'normal' | 'urgent' | 'critical';
  message: string;
  my_response: 'pending' | 'accepted' | 'declined';
  required_skills: string[];
  shift: {
    id: number;
    start_time: string;
    end_time: string;
  };
  opportunity: {
    title: string;
    location: string;
  };
  organization: {
    name: string;
  };
  coordinator: {
    name: string;
  };
  expires_at: string;
  created_at: string;
}

export function EmergencyAlertsTab() {
  const { t } = useTranslation('volunteering');
  const toast = useToast();
  const [alerts, setAlerts] = useState<EmergencyAlert[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [respondingTo, setRespondingTo] = useState<number | null>(null);

  // AbortController ref to cancel stale requests
  const abortRef = useRef<AbortController | null>(null);

  // Stable refs for t/toast — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

  const load = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      setIsLoading(true);
      setError(null);

      const response = await api.get<{ alerts?: EmergencyAlert[] }>(
        '/v2/volunteering/emergency-alerts'
      );

      if (controller.signal.aborted) return;
      if (response.success && response.data) {
        const payload = response.data as { alerts?: EmergencyAlert[] } | EmergencyAlert[];
        setAlerts(Array.isArray(payload) ? payload : (payload.alerts ?? []));
      } else {
        setError(tRef.current('emergency.error_load', 'Failed to load alerts'));
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load emergency alerts', err);
      setError(tRef.current('emergency.error_load_generic', 'Unable to load alerts.'));
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const handleRespond = async (alertId: number, response: 'accepted' | 'declined') => {
    try {
      setRespondingTo(alertId);
      const result = await api.put(`/v2/volunteering/emergency-alerts/${alertId}`, { response });
      if (result.success) {
        toastRef.current.success(response === 'accepted' ? tRef.current('emergency.alert_accepted', 'Alert accepted.') : tRef.current('emergency.alert_declined', 'Alert declined.'));
        load();
      } else {
        toastRef.current.error(result.error || tRef.current('emergency.respond_error', 'Failed to respond to alert.'));
      }
    } catch (err) {
      logError('Failed to respond to alert', err);
      toastRef.current.error(tRef.current('emergency.respond_error_generic', 'Failed to respond to alert. Please try again.'));
    } finally {
      setRespondingTo(null);
    }
  };

  const priorityConfig = {
    critical: { color: 'danger' as const, label: t('emergency.priority_critical', 'CRITICAL'), bgClass: 'border-red-500/30 bg-red-500/5' },
    urgent: { color: 'warning' as const, label: t('emergency.priority_urgent', 'URGENT'), bgClass: 'border-amber-500/30 bg-amber-500/5' },
    normal: { color: 'primary' as const, label: t('emergency.priority_normal', 'NORMAL'), bgClass: '' },
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.05 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Siren className="w-5 h-5 text-red-400" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary">{t('emergency.title', 'Emergency Alerts')}</h2>
          {alerts.filter(a => a.my_response === 'pending').length > 0 && (
            <Chip size="sm" color="danger" variant="flat">
              {t('emergency.pending_count', '{{count}} pending', { count: alerts.filter(a => a.my_response === 'pending').length })}
            </Chip>
          )}
        </div>
        <Button
          size="sm"
          variant="flat"
          className="bg-theme-elevated text-theme-muted"
          startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
          onPress={load}
          isLoading={isLoading}
        >
          {t('common.refresh', 'Refresh')}
        </Button>
      </div>

      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{error}</p>
          <Button className="bg-gradient-to-r from-rose-500 to-pink-600 text-white" onPress={load}>
            {t('common.try_again', 'Try Again')}
          </Button>
        </GlassCard>
      )}

      {!error && isLoading && (
        <div className="space-y-4">
          {[1, 2].map((i) => (
            <GlassCard key={i} className="p-5 animate-pulse">
              <div className="h-5 bg-theme-hover rounded w-1/3 mb-3" />
              <div className="h-3 bg-theme-hover rounded w-full mb-3" />
              <div className="h-3 bg-theme-hover rounded w-1/4" />
            </GlassCard>
          ))}
        </div>
      )}

      {!error && !isLoading && alerts.length === 0 && (
        <EmptyState
          icon={<Bell className="w-12 h-12" aria-hidden="true" />}
          title={t('emergency.empty_title', 'No emergency alerts')}
          description={t('emergency.empty_description', "You don't have any emergency shift requests at the moment.")}
        />
      )}

      {!error && !isLoading && alerts.length > 0 && (
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="space-y-4"
        >
          {alerts.map((alert) => {
            const config = priorityConfig[alert.priority];
            return (
              <motion.div key={alert.id} variants={itemVariants}>
                <GlassCard className={`p-5 border ${config.bgClass}`}>
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 mb-2 flex-wrap">
                        <Chip size="sm" color={config.color} variant="flat">
                          {config.label}
                        </Chip>
                        <h3 className="font-semibold text-theme-primary">{alert.opportunity.title}</h3>
                      </div>

                      <p className="text-sm text-theme-muted mb-3">{alert.message}</p>

                      <div className="flex flex-wrap items-center gap-3 text-xs text-theme-subtle mb-2">
                        <span className="flex items-center gap-1">
                          <Building2 className="w-3 h-3" aria-hidden="true" />
                          {alert.organization.name}
                        </span>
                        {alert.opportunity.location && (
                          <span className="flex items-center gap-1">
                            <MapPin className="w-3 h-3" aria-hidden="true" />
                            {alert.opportunity.location}
                          </span>
                        )}
                        <span className="flex items-center gap-1">
                          <Calendar className="w-3 h-3" aria-hidden="true" />
                          {new Date(alert.shift.start_time).toLocaleDateString()}
                        </span>
                        <span className="flex items-center gap-1">
                          <Clock className="w-3 h-3" aria-hidden="true" />
                          {new Date(alert.shift.start_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        </span>
                      </div>

                      {alert.required_skills.length > 0 && (
                        <div className="flex flex-wrap gap-1 mt-2">
                          {alert.required_skills.map((skill, i) => (
                            <Chip key={i} size="sm" variant="flat" className="text-xs">
                              {skill}
                            </Chip>
                          ))}
                        </div>
                      )}

                      <p className="text-xs text-theme-subtle mt-2">
                        {t('emergency.from', 'From')} {alert.coordinator.name} -- {t('emergency.expires', 'Expires')} {new Date(alert.expires_at).toLocaleString()}
                      </p>
                    </div>

                    {alert.my_response === 'pending' && (
                      <div className="flex flex-col gap-2 flex-shrink-0">
                        <Button
                          size="sm"
                          className="bg-gradient-to-r from-emerald-500 to-green-600 text-white"
                          startContent={<CheckCircle className="w-4 h-4" aria-hidden="true" />}
                          onPress={() => handleRespond(alert.id, 'accepted')}
                          isLoading={respondingTo === alert.id}
                        >
                          {t('emergency.accept', 'Accept')}
                        </Button>
                        <Button
                          size="sm"
                          variant="flat"
                          color="danger"
                          startContent={<XCircle className="w-4 h-4" aria-hidden="true" />}
                          onPress={() => handleRespond(alert.id, 'declined')}
                          isLoading={respondingTo === alert.id}
                        >
                          {t('emergency.decline', 'Decline')}
                        </Button>
                      </div>
                    )}

                    {alert.my_response !== 'pending' && (
                      <Chip
                        size="sm"
                        color={alert.my_response === 'accepted' ? 'success' : 'danger'}
                        variant="flat"
                      >
                        {alert.my_response === 'accepted' ? t('emergency.accepted', 'Accepted') : t('emergency.declined', 'Declined')}
                      </Chip>
                    )}
                  </div>
                </GlassCard>
              </motion.div>
            );
          })}
        </motion.div>
      )}
    </div>
  );
}

export default EmergencyAlertsTab;
