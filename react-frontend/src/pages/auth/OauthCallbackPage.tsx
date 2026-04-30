// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * OAuth Callback Page (SOC13)
 *
 * The backend redirects the user here after a successful OAuth round-trip with
 * `?token=<sanctum>&provider=<x>&is_new=<0|1>&tenant_id=<id>`.
 * On error: `?error=<code>&message=<text>&provider=<x>`.
 */

import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams, Link } from 'react-router-dom';
import { Button } from '@heroui/react';
import Loader2 from 'lucide-react/icons/loader-circle';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { tokenManager } from '@/lib/api';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';

export function OauthCallbackPage() {
  const { t } = useTranslation('common');
  usePageTitle(t('oauth.callback_signing_in'));
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const token = params.get('token');
    const errCode = params.get('error');
    const errMsg = params.get('message');
    const tenantId = params.get('tenant_id');

    if (errCode) {
      setError(errMsg || t('oauth.callback_failed'));
      return;
    }

    if (!token) {
      setError(t('oauth.callback_failed'));
      return;
    }

    if (tenantId) {
      tokenManager.setTenantId(tenantId);
    }
    tokenManager.setAccessToken(token);
    // Reload so AuthContext bootstraps from the freshly stored token.
    window.location.href = tenantPath('/dashboard');
  }, [params, navigate, tenantPath, t]);

  if (error) {
    return (
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
            {t('back_to_login', { defaultValue: 'Back to login' })}
          </Button>
        </GlassCard>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center p-4">
      <div className="text-center">
        <Loader2 className="w-8 h-8 animate-spin mx-auto text-indigo-500" aria-hidden="true" />
        <p className="text-theme-muted mt-3">{t('oauth.callback_signing_in')}</p>
      </div>
    </div>
  );
}

export default OauthCallbackPage;
