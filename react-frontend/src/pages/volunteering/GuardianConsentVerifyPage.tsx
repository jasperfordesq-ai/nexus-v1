// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Public page where a parent/guardian lands from the consent email:
 *   {frontend}/{tenant}/volunteering/guardian-consent/verify/{token}
 *
 * Deliberately does NOT grant on page load — email link scanners prefetch
 * URLs, and consent must come from a human action — so the guardian is shown
 * an explicit "Confirm my approval" button which calls
 * GET /api/v2/volunteering/guardian-consents/verify/{token} (public route,
 * the token is the credential).
 */

import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Button, Card, Spinner } from '@/components/ui';
import ShieldCheck from 'lucide-react/icons/shield-check';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import XCircle from 'lucide-react/icons/circle-x';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo';
import { useTenant } from '@/contexts';

type State = 'confirm' | 'submitting' | 'success' | 'error';

export default function GuardianConsentVerifyPage() {
  const { t } = useTranslation('volunteering');
  usePageTitle(t('guardian.verify_title'));

  const { token } = useParams<{ token: string }>();
  const { tenantPath } = useTenant();
  const [state, setState] = useState<State>(token ? 'confirm' : 'error');

  async function handleConfirm() {
    if (!token) return;
    setState('submitting');
    try {
      const res = await api.get(`/v2/volunteering/guardian-consents/verify/${encodeURIComponent(token)}`, { skipAuth: true });
      setState(res.success ? 'success' : 'error');
    } catch {
      setState('error');
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-surface-secondary px-4">
      <PageMeta title={t('guardian.verify_title')} noIndex />
      <Card className="max-w-md w-full p-8 text-center space-y-4">
        {state === 'confirm' && (
          <>
            <ShieldCheck className="w-12 h-12 text-[var(--color-primary)] mx-auto" aria-hidden="true" />
            <h1 className="text-xl font-semibold">{t('guardian.verify_title')}</h1>
            <p className="text-theme-muted">{t('guardian.verify_confirm_intro')}</p>
            <Button color="primary" className="w-full" onPress={handleConfirm}>
              {t('guardian.verify_confirm_button')}
            </Button>
          </>
        )}

        {state === 'submitting' && (
          <>
            <Spinner className="mx-auto" aria-label={t('guardian.verify_loading')} />
            <p className="text-theme-muted">{t('guardian.verify_loading')}</p>
          </>
        )}

        {state === 'success' && (
          <>
            <CheckCircle className="w-12 h-12 text-[var(--color-success)] mx-auto" aria-hidden="true" />
            <h1 className="text-xl font-semibold">{t('guardian.verify_success_title')}</h1>
            <p className="text-theme-muted">{t('guardian.verify_success_body')}</p>
          </>
        )}

        {state === 'error' && (
          <>
            <XCircle className="w-12 h-12 text-[var(--color-danger)] mx-auto" aria-hidden="true" />
            <h1 className="text-xl font-semibold">{t('guardian.verify_error_title')}</h1>
            <p className="text-theme-muted">{t('guardian.verify_error_body')}</p>
          </>
        )}

        <Link to={tenantPath('/')} className="text-sm text-[var(--color-primary)] underline block">
          {t('guardian.close')}
        </Link>
      </Card>
    </div>
  );
}
