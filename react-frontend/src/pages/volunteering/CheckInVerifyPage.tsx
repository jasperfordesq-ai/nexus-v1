// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Landing page a shift coordinator reaches by scanning a volunteer's check-in QR:
 *   {frontend}/{tenant}/volunteering/checkin/{token}
 *
 * The QR previously encoded the POST-only, auth-gated JSON API route, so scanning
 * it just produced a 401/405. This page is the human-facing target: the
 * authenticated coordinator confirms, which POSTs the verify endpoint, and can
 * then check the volunteer out. Deliberately does NOT verify on page load — link
 * scanners prefetch URLs and a check-in is a state change that needs a human tap.
 */

import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Spinner } from '@/components/ui/Spinner';
import QrCode from 'lucide-react/icons/qr-code';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import XCircle from 'lucide-react/icons/circle-x';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo';
import { useTenant } from '@/contexts';

type State = 'confirm' | 'submitting' | 'checked_in' | 'checking_out' | 'checked_out' | 'error';

interface VerifyResult {
  status?: string;
  user?: { id: number; name: string };
  checked_in_at?: string;
}

export default function CheckInVerifyPage() {
  const { t } = useTranslation('volunteering');
  usePageTitle(t('check_in.verify_title'));

  const { token } = useParams<{ token: string }>();
  const { tenantPath } = useTenant();
  const [state, setState] = useState<State>(token ? 'confirm' : 'error');
  const [volunteerName, setVolunteerName] = useState<string>('');
  const [errorMessage, setErrorMessage] = useState<string>(t('check_in.error'));

  const errorForCode = (code?: string): string => {
    if (code === 'FORBIDDEN') return t('check_in.forbidden');
    if (code === 'NOT_FOUND') return t('check_in.invalid');
    return t('check_in.error');
  };

  const messageFor = (res: { message?: string; code?: string; errors?: Array<{ code?: string; message?: string }> }): string => {
    const first = res.errors?.[0];
    return first?.message || res.message || errorForCode(first?.code ?? res.code);
  };

  async function handleConfirm() {
    if (!token) return;
    setState('submitting');
    try {
      const res = await api.post<VerifyResult>(`/v2/volunteering/checkin/verify/${encodeURIComponent(token)}`, {});
      if (res.success && res.data) {
        setVolunteerName(res.data.user?.name || '');
        setState('checked_in');
      } else {
        setErrorMessage(messageFor(res));
        setState('error');
      }
    } catch {
      setErrorMessage(t('check_in.error'));
      setState('error');
    }
  }

  async function handleCheckout() {
    if (!token) return;
    setState('checking_out');
    try {
      const res = await api.post(`/v2/volunteering/checkin/checkout/${encodeURIComponent(token)}`, {});
      if (res.success) {
        setState('checked_out');
      } else {
        setErrorMessage(messageFor(res));
        setState('error');
      }
    } catch {
      setErrorMessage(t('check_in.error'));
      setState('error');
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-surface-secondary px-4">
      <PageMeta title={t('check_in.verify_title')} noIndex />
      <Card className="max-w-md w-full p-8 text-center space-y-4">
        {state === 'confirm' && (
          <>
            <QrCode className="w-12 h-12 text-[var(--color-primary)] mx-auto" aria-hidden="true" />
            <h1 className="text-xl font-semibold">{t('check_in.verify_title')}</h1>
            <p className="text-theme-muted">{t('check_in.verify_intro')}</p>
            <Button color="primary" className="w-full" onPress={handleConfirm}>
              {t('check_in.confirm_button')}
            </Button>
          </>
        )}

        {(state === 'submitting' || state === 'checking_out') && (
          <>
            <Spinner className="mx-auto" aria-label={t('loading')} />
            <p className="text-theme-muted">{t('loading')}</p>
          </>
        )}

        {state === 'checked_in' && (
          <>
            <CheckCircle className="w-12 h-12 text-[var(--color-success)] mx-auto" aria-hidden="true" />
            <h1 className="text-xl font-semibold">{t('check_in.success', { name: volunteerName })}</h1>
            <Button color="primary" variant="secondary" className="w-full" onPress={handleCheckout}>
              {t('check_in.checkout_button')}
            </Button>
          </>
        )}

        {state === 'checked_out' && (
          <>
            <CheckCircle className="w-12 h-12 text-[var(--color-success)] mx-auto" aria-hidden="true" />
            <h1 className="text-xl font-semibold">{t('check_in.checkout_success', { name: volunteerName })}</h1>
          </>
        )}

        {state === 'error' && (
          <>
            <XCircle className="w-12 h-12 text-[var(--color-danger)] mx-auto" aria-hidden="true" />
            <p className="text-theme-muted">{errorMessage}</p>
          </>
        )}

        <Link to={tenantPath('/volunteering')} className="text-sm text-[var(--color-primary)] underline block">
          {t('check_in.done')}
        </Link>
      </Card>
    </div>
  );
}
