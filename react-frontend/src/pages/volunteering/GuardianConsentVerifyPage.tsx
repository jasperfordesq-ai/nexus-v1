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
 * POST /api/v2/volunteering/guardian-consents/verify/{token} (public route,
 * the token is the credential). The GET on the same URL is a read-only
 * lookup and never grants, so scanner prefetches can't flip state either.
 */

import { useEffect, useRef, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Spinner } from '@/components/ui/Spinner';
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
  const resultRef = useRef<HTMLDivElement>(null);

  // Announce state transitions to assistive tech (WCAG 4.1.3): the result region
  // is a live region and receives focus when the async action resolves, so
  // screen-reader users hear success/failure even though the button that
  // triggered it unmounts.
  useEffect(() => {
    if (state === 'success' || state === 'error') {
      resultRef.current?.focus();
    }
  }, [state]);

  async function handleConfirm() {
    if (!token) return;
    setState('submitting');
    try {
      const res = await api.post(`/v2/volunteering/guardian-consents/verify/${encodeURIComponent(token)}`, undefined, { skipAuth: true });
      setState(res.success ? 'success' : 'error');
    } catch {
      setState('error');
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-surface-secondary px-4">
      <PageMeta title={t('guardian.verify_title')} noIndex />
      <Card className="max-w-md w-full p-8 text-center space-y-4">
        <div
          ref={resultRef}
          tabIndex={-1}
          role={state === 'error' ? 'alert' : 'status'}
          aria-live={state === 'error' ? 'assertive' : 'polite'}
          className="space-y-4 outline-none"
        >
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
        </div>

        <Link to={tenantPath('/')} className="text-sm text-[var(--color-primary)] underline block">
          {t('guardian.close')}
        </Link>
      </Card>
    </div>
  );
}
