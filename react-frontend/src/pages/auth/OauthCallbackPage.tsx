// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * OAuth Callback Page (SOC13)
 *
 * The backend redirects the user here after a successful OAuth round-trip with
 * a short-lived `?code=<one-time-code>` that is exchanged via POST.
 * On error: `?error=<code>&message=<text>&provider=<x>`.
 */

import { useEffect, useState } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { GlassCard } from '@/components/ui/GlassCard';
import { Spinner } from '@/components/ui/Spinner';
import { PageMeta } from '@/components/seo/PageMeta';
import { tokenManager } from '@/lib/api';
import { useTenant } from '@/contexts/TenantContext';
import { usePageTitle } from '@/hooks/usePageTitle';

export function OauthCallbackPage() {
  const { t } = useTranslation('common');
  usePageTitle(t('oauth.callback_signing_in'));
  const [params] = useSearchParams();
  const { tenantPath } = useTenant();
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    const code = params.get('code');
    const errCode = params.get('error');
    const errMsg = params.get('message');

    if (errCode) {
      setError(errMsg || t('oauth.callback_failed'));
      return;
    }

    if (!code) {
      setError(t('oauth.callback_failed'));
      return;
    }

    async function exchangeCode() {
      try {
        const response = await fetch('/api/v2/auth/oauth/exchange', {
          method: 'POST',
          credentials: 'include',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ code }),
        });
        const data = await response.json();

        if (!response.ok || !data?.success || !data?.token) {
          throw new Error(data?.message || 'oauth_exchange_failed');
        }

        if (cancelled) return;

        if (data.tenant_id) {
          tokenManager.setTenantId(String(data.tenant_id));
        }
        tokenManager.setAccessToken(String(data.token));
        window.location.href = tenantPath('/dashboard');
      } catch {
        if (!cancelled) {
          setError(t('oauth.callback_failed'));
        }
      }
    }

    void exchangeCode();

    return () => {
      cancelled = true;
    };
  }, [params, tenantPath, t]);

  if (error) {
    return (
      <>
        <PageMeta title={t('oauth.callback_failed')} noIndex />
        <div className="min-h-screen flex items-center justify-center p-4">
          <GlassCard className="p-6 max-w-md w-full">
            <h1 className="text-xl font-bold text-theme-primary mb-3">{t('oauth.callback_failed')}</h1>
            <p className="text-theme-muted text-sm mb-6">{error}</p>
            <Button
              as={Link}
              to={tenantPath('/login')}
              variant="bordered"
              startContent={<ArrowLeft className="w-4 h-4" />}
            >
              {t('back_to_login')}
            </Button>
          </GlassCard>
        </div>
      </>
    );
  }

  return (
    <>
      <PageMeta title={t('oauth.callback_signing_in')} noIndex />
      <div className="min-h-screen flex items-center justify-center p-4">
        <div className="text-center">
          <Spinner size="lg" aria-hidden="true" />
          <p className="text-theme-muted mt-3">{t('oauth.callback_signing_in')}</p>
        </div>
      </div>
    </>
  );
}

export default OauthCallbackPage;
