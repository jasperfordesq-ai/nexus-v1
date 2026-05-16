// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * RegistrationBreakerCard
 *
 * Tenant-level circuit-breaker status for new-account registration. Wraps
 * the two admin endpoints added alongside the breaker:
 *   GET  /api/v2/admin/registration/breaker         — current status
 *   POST /api/v2/admin/registration/resume-signups  — manual clear
 *
 * Rendered as the first card on the Registration Policy Settings page so
 * that during a signup-flood incident, the admin sees the status front-and-
 * centre and can resume signups in one click. Polls every 30s so the
 * "paused" state surfaces without a manual reload.
 *
 * Additive: never touches existing components. Safe to ship.
 */

import { useEffect, useState, useCallback } from 'react';
import { Card, CardBody, CardHeader, Button, Chip, Spinner } from '@heroui/react';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import ShieldCheck from 'lucide-react/icons/shield-check';
import PlayCircle from 'lucide-react/icons/play-circle';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import { api } from '@/lib/api';
import { useToast } from '@/contexts';

interface BreakerStatus {
  tripped: boolean;
  count_in_current_hour: number;
  threshold: number;
  auto_resume_in_seconds: number | null;
}

export function RegistrationBreakerCard() {
  const toast = useToast();
  const [status, setStatus] = useState<BreakerStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [resuming, setResuming] = useState(false);

  const fetchStatus = useCallback(async () => {
    try {
      const res = await api.get<BreakerStatus>('/v2/admin/registration/breaker');
      if (res.success && res.data) {
        setStatus(res.data);
      }
    } catch {
      // Silent — surfaces as stale data, not a toast. The page works without this card.
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchStatus();
    const id = setInterval(fetchStatus, 30_000);
    return () => clearInterval(id);
  }, [fetchStatus]);

  const handleResume = useCallback(async () => {
    setResuming(true);
    try {
      const res = await api.post('/v2/admin/registration/resume-signups', {});
      if (res.success) {
        toast.success('Registration resumed for this community.');
        await fetchStatus();
      } else {
        toast.error(res.error ?? 'Failed to resume registration.');
      }
    } finally {
      setResuming(false);
    }
  }, [toast, fetchStatus]);

  if (loading) {
    return (
      <Card shadow="sm">
        <CardBody className="flex items-center justify-center py-6">
          <Spinner size="sm" />
        </CardBody>
      </Card>
    );
  }

  if (!status) {
    return null;
  }

  const tripped = status.tripped;
  const pctOfThreshold =
    status.threshold > 0
      ? Math.min(100, Math.round((status.count_in_current_hour / status.threshold) * 100))
      : 0;

  return (
    <Card shadow="sm" className={tripped ? 'border-2 border-danger' : ''}>
      <CardHeader className="pb-0">
        <div className="flex items-center justify-between w-full">
          <div className="flex items-center gap-2">
            {tripped ? (
              <ShieldAlert size={20} className="text-danger" />
            ) : (
              <ShieldCheck size={20} className="text-success" />
            )}
            <div>
              <h3 className="text-lg font-semibold">Registration Security</h3>
              <p className="text-sm text-default-500 mt-1">
                Automatic pause when this community gets an unusual flood of signups in one hour.
              </p>
            </div>
          </div>
          <Button
            isIconOnly
            size="sm"
            variant="light"
            onPress={fetchStatus}
            aria-label="Refresh status"
          >
            <RefreshCw size={16} />
          </Button>
        </div>
      </CardHeader>
      <CardBody className="gap-3">
        <div className="flex items-center gap-3">
          <Chip
            color={tripped ? 'danger' : 'success'}
            variant="flat"
            startContent={tripped ? <ShieldAlert size={14} /> : <ShieldCheck size={14} />}
          >
            {tripped ? 'Signups paused' : 'Signups active'}
          </Chip>
          <span className="text-sm text-default-600">
            {status.count_in_current_hour} of {status.threshold} signups this hour
            {pctOfThreshold >= 50 && !tripped && (
              <span className="text-warning ml-2">
                ({pctOfThreshold}% of threshold)
              </span>
            )}
          </span>
        </div>

        {tripped && (
          <div className="bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800 rounded-lg p-3 text-sm">
            <p className="font-medium text-danger">Account creation is currently paused.</p>
            <p className="text-default-700 mt-1">
              The hourly signup threshold ({status.threshold} new accounts) was exceeded.
              Registration will auto-resume in about{' '}
              {Math.ceil((status.auto_resume_in_seconds ?? 3600) / 60)} minutes, or you can resume it now.
              Check Sentry / logs for the source IPs before clearing if you suspect an attack.
            </p>
            <Button
              color="danger"
              variant="solid"
              size="sm"
              className="mt-3"
              startContent={<PlayCircle size={16} />}
              onPress={handleResume}
              isLoading={resuming}
            >
              Resume signups now
            </Button>
          </div>
        )}

        {!tripped && (
          <p className="text-xs text-default-500">
            Threshold is configured at the platform level via{' '}
            <code className="font-mono">REGISTRATION_TENANT_HOURLY_CAP</code>.
            When tripped, this banner turns red and a one-click resume button appears here.
          </p>
        )}
      </CardBody>
    </Card>
  );
}

export default RegistrationBreakerCard;
