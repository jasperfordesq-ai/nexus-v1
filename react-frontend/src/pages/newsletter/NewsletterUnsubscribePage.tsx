// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Button } from '@heroui/react';
import { CheckCircle, XCircle, Loader2, Mail } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';

type State = 'loading' | 'success' | 'already_done' | 'invalid' | 'error';

/**
 * Public page for newsletter unsubscribe via email link.
 *
 * Linked from the weekly digest email footer:
 *   {APP_URL}/{tenant}/newsletter/unsubscribe?token=...
 *
 * Calls POST /api/v2/newsletter/unsubscribe with the token from the URL.
 * No authentication required — the token acts as the credential.
 */
export default function NewsletterUnsubscribePage() {
  const { t } = useTranslation('utility');
  usePageTitle(t('newsletter.page_title'));

  const [searchParams] = useSearchParams();
  const { tenantPath } = useTenant();
  const token = searchParams.get('token') ?? '';

  const [state, setState] = useState<State>(token ? 'loading' : 'invalid');

  useEffect(() => {
    if (!token) {
      setState('invalid');
      return;
    }

    type UnsubBody = { success: boolean; already_done?: boolean };
    api
      .post<UnsubBody>('/v2/newsletter/unsubscribe', { token }, { skipAuth: true })
      .then((res) => {
        if (!res.success) {
          // ApiResponse.success is false when PHP returned 4xx/5xx
          setState('invalid');
          return;
        }
        if (res.data?.already_done) {
          setState('already_done');
        } else {
          setState('success');
        }
      })
      .catch(() => {
        setState('error');
      });
   
  }, [token]);

  return (
    <div className="min-h-screen flex items-center justify-center bg-content2 px-4">
      <div className="w-full max-w-md bg-content1 rounded-2xl shadow-lg p-8 text-center">
        <div className="mb-6">
          <Mail className="mx-auto text-default-400" size={40} />
        </div>

        {state === 'loading' && (
          <>
            <Loader2 className="mx-auto animate-spin text-primary mb-4" size={32} />
            <h1 className="text-xl font-semibold text-foreground">{t('newsletter.processing')}</h1>
            <p className="mt-2 text-default-500 text-sm">{t('newsletter.removing_from_list')}</p>
          </>
        )}

        {state === 'success' && (
          <>
            <CheckCircle className="mx-auto text-success mb-4" size={36} />
            <h1 className="text-xl font-semibold text-foreground">{t('newsletter.unsubscribed')}</h1>
            <p className="mt-2 text-default-500 text-sm">
              {t('newsletter.unsubscribed_description')}
            </p>
            <Button
              className="mt-6"
              color="primary"
              variant="flat"
              as="a"
              href={tenantPath('/settings')}
            >
              {t('newsletter.manage_preferences')}
            </Button>
          </>
        )}

        {state === 'already_done' && (
          <>
            <CheckCircle className="mx-auto text-success mb-4" size={36} />
            <h1 className="text-xl font-semibold text-foreground">{t('newsletter.already_unsubscribed')}</h1>
            <p className="mt-2 text-default-500 text-sm">
              {t('newsletter.already_unsubscribed_description')}
            </p>
          </>
        )}

        {state === 'invalid' && (
          <>
            <XCircle className="mx-auto text-danger mb-4" size={36} />
            <h1 className="text-xl font-semibold text-foreground">{t('newsletter.invalid_link')}</h1>
            <p className="mt-2 text-default-500 text-sm">
              {t('newsletter.invalid_link_description')}
            </p>
            <Button
              className="mt-6"
              color="primary"
              variant="flat"
              as="a"
              href={tenantPath('/settings')}
            >
              {t('newsletter.go_to_settings')}
            </Button>
          </>
        )}

        {state === 'error' && (
          <>
            <XCircle className="mx-auto text-danger mb-4" size={36} />
            <h1 className="text-xl font-semibold text-foreground">{t('newsletter.something_went_wrong')}</h1>
            <p className="mt-2 text-default-500 text-sm">
              {t('newsletter.error_description')}
            </p>
            <Button
              className="mt-6"
              color="default"
              variant="flat"
              onPress={() => window.location.reload()}
            >
              {t('newsletter.try_again')}
            </Button>
          </>
        )}
      </div>
    </div>
  );
}
