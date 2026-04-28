// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { Button, Chip, Input, Spinner, Textarea } from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import ArrowRightLeft from 'lucide-react/icons/arrow-right-left';
import CheckCircle from 'lucide-react/icons/circle-check';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

type TransferStatus =
  | 'pending'
  | 'approved_by_source'
  | 'sent'
  | 'received'
  | 'completed'
  | 'rejected';

interface TransferHistoryItem {
  id: number;
  destination_tenant_slug: string;
  destination_member_email: string;
  hours: number;
  status: TransferStatus;
  reason: string;
  created_at: string;
}

interface HistoryResponse {
  items: TransferHistoryItem[];
}

interface InitiateResponse {
  transfer_id: number;
  status: TransferStatus;
  success: boolean;
}

const STATUS_COLOR: Record<TransferStatus, 'default' | 'primary' | 'success' | 'warning' | 'danger'> = {
  pending: 'warning',
  approved_by_source: 'primary',
  sent: 'primary',
  received: 'primary',
  completed: 'success',
  rejected: 'danger',
};

export function HourTransferPage() {
  const { t } = useTranslation('common');
  const { hasFeature, tenantPath } = useTenant();
  usePageTitle(t('hour_transfer.meta.title'));

  const [destinationSlug, setDestinationSlug] = useState('');
  const [hours, setHours] = useState('');
  const [reason, setReason] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [history, setHistory] = useState<TransferHistoryItem[]>([]);
  const [historyLoading, setHistoryLoading] = useState(true);

  const loadHistory = useCallback(async () => {
    try {
      setHistoryLoading(true);
      const res = await api.get<HistoryResponse>('/v2/caring-community/hour-transfer/my-history');
      if (res.success && res.data) {
        setHistory(res.data.items ?? []);
      }
    } catch (err) {
      logError('HourTransferPage: load history failed', err);
    } finally {
      setHistoryLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadHistory();
  }, [loadHistory]);

  if (!hasFeature('caring_community')) {
    return <Navigate to={tenantPath('/caring-community')} replace />;
  }

  const hoursValue = parseFloat(hours);
  const canSubmit =
    destinationSlug.trim().length > 0 &&
    !Number.isNaN(hoursValue) &&
    hoursValue > 0 &&
    !submitting;

  const handleSubmit = async () => {
    setError(null);
    if (!canSubmit) return;
    setSubmitting(true);
    try {
      const res = await api.post<InitiateResponse>('/v2/caring-community/hour-transfer/initiate', {
        destination_tenant_slug: destinationSlug.trim(),
        hours: hoursValue,
        reason: reason.trim(),
      });
      if (res.success) {
        setSuccess(true);
        setDestinationSlug('');
        setHours('');
        setReason('');
        void loadHistory();
      } else {
        const code = res.code;
        if (code === 'NO_MATCHING_EMAIL') {
          setError(t('hour_transfer.errors.no_matching_email'));
        } else if (code === 'DESTINATION_NOT_FOUND') {
          setError(t('hour_transfer.errors.destination_not_found'));
        } else if (code === 'INSUFFICIENT_HOURS') {
          setError(t('hour_transfer.errors.insufficient_hours'));
        } else {
          setError(res.error || t('hour_transfer.errors.submit_failed'));
        }
      }
    } catch (err) {
      logError('HourTransferPage: submit failed', err);
      setError(t('hour_transfer.errors.submit_failed'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <>
      <PageMeta
        title={t('hour_transfer.meta.title')}
        description={t('hour_transfer.meta.description')}
        noIndex
      />
      <div className="mx-auto max-w-2xl space-y-6">
        <div>
          <Link
            to={tenantPath('/caring-community')}
            className="inline-flex items-center gap-1 text-sm text-theme-muted hover:text-theme-primary"
          >
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
            {t('hour_transfer.back')}
          </Link>
        </div>

        <GlassCard className="p-6 sm:p-8">
          <div className="mb-7 flex items-center gap-4">
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/15">
              <ArrowRightLeft className="h-6 w-6 text-primary" aria-hidden="true" />
            </div>
            <div>
              <h1 className="text-2xl font-bold leading-tight text-theme-primary">
                {t('hour_transfer.title')}
              </h1>
              <p className="mt-1 text-base leading-7 text-theme-muted">
                {t('hour_transfer.subtitle')}
              </p>
            </div>
          </div>

          {success && (
            <div className="mb-6 flex items-start gap-3 rounded-lg bg-success/10 px-4 py-3 text-sm text-success">
              <CheckCircle className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
              <p>{t('hour_transfer.history.empty')}</p>
            </div>
          )}

          <div className="space-y-5">
            <Input
              label={t('hour_transfer.form.destination_label')}
              placeholder={t('hour_transfer.form.destination_placeholder')}
              description={t('hour_transfer.form.destination_help')}
              value={destinationSlug}
              onValueChange={setDestinationSlug}
              variant="bordered"
              isRequired
            />
            <Input
              type="number"
              label={t('hour_transfer.form.hours_label')}
              placeholder={t('hour_transfer.form.hours_placeholder')}
              value={hours}
              onValueChange={setHours}
              variant="bordered"
              min="0.01"
              step="0.5"
              isRequired
            />
            <Textarea
              label={t('hour_transfer.form.reason_label')}
              placeholder={t('hour_transfer.form.reason_placeholder')}
              value={reason}
              onValueChange={setReason}
              variant="bordered"
              minRows={2}
              maxRows={5}
            />

            <p className="rounded-lg bg-default-100 px-4 py-3 text-sm text-theme-muted">
              {t('hour_transfer.disclaimer')}
            </p>

            {error && (
              <p className="rounded-lg bg-danger/10 px-4 py-3 text-sm text-danger">{error}</p>
            )}

            <Button
              color="primary"
              size="lg"
              className="w-full text-base"
              isLoading={submitting}
              isDisabled={!canSubmit}
              onPress={() => void handleSubmit()}
            >
              {submitting ? t('hour_transfer.form.submitting') : t('hour_transfer.form.submit')}
            </Button>
          </div>
        </GlassCard>

        <GlassCard className="p-6 sm:p-8">
          <h2 className="mb-4 text-lg font-semibold text-theme-primary">
            {t('hour_transfer.history.title')}
          </h2>

          {historyLoading ? (
            <div className="flex justify-center py-8">
              <Spinner size="md" />
            </div>
          ) : history.length === 0 ? (
            <p className="text-sm text-theme-muted">{t('hour_transfer.history.empty')}</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs uppercase tracking-wide text-theme-muted">
                    <th className="py-2 pr-4">{t('hour_transfer.history.date')}</th>
                    <th className="py-2 pr-4">{t('hour_transfer.history.destination')}</th>
                    <th className="py-2 pr-4 text-right">{t('hour_transfer.history.hours')}</th>
                    <th className="py-2 pr-4">{t('hour_transfer.history.status')}</th>
                    <th className="py-2">{t('hour_transfer.history.reason')}</th>
                  </tr>
                </thead>
                <tbody>
                  {history.map((row) => (
                    <tr key={row.id} className="border-t border-default-200">
                      <td className="py-3 pr-4 whitespace-nowrap text-theme-muted">
                        {new Date(row.created_at).toLocaleDateString()}
                      </td>
                      <td className="py-3 pr-4">{row.destination_tenant_slug}</td>
                      <td className="py-3 pr-4 text-right tabular-nums">
                        {row.hours.toFixed(2)}
                      </td>
                      <td className="py-3 pr-4">
                        <Chip size="sm" variant="flat" color={STATUS_COLOR[row.status] ?? 'default'}>
                          {t(`hour_transfer.status.${row.status}`)}
                        </Chip>
                      </td>
                      <td className="py-3 text-theme-muted">
                        {row.reason ? row.reason : '—'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </GlassCard>
      </div>
    </>
  );
}

export default HourTransferPage;
