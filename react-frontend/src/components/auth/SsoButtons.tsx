// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Building2 from 'lucide-react/icons/building-2';
import KeyRound from 'lucide-react/icons/key-round';
import { Button } from '@/components/ui/Button';

interface SsoProvider {
  key: string;
  display_name: string;
  preset: 'generic' | 'entra' | 'hivebrite';
}

interface SsoButtonsProps {
  /**
   * Optional tenant id to forward when no tenant is resolved by host/slug yet.
   */
  tenantId?: string | number;
}

/**
 * Per-tenant OIDC SSO buttons (IT-Sec-05). Fetches the tenant's enabled
 * providers and renders one button each (e.g. "Sign in with Coventry City
 * Council"). Renders nothing when the tenant has no enabled providers, so
 * there is zero visual impact for tenants without SSO.
 */
export function SsoButtons({ tenantId }: SsoButtonsProps) {
  const { t } = useTranslation('common');

  const [providers, setProviders] = useState<SsoProvider[] | null>(null);

  useEffect(() => {
    const headers: Record<string, string> = {};
    if (tenantId) headers['X-Tenant-Id'] = String(tenantId);
    const params = new URLSearchParams();
    if (tenantId) params.set('tenant_id', String(tenantId));
    const qs = params.toString();
    fetch(`/api/v2/auth/sso/providers${qs ? `?${qs}` : ''}`, { credentials: 'include', headers })
      .then((r) => r.json())
      .then((data: { success?: boolean; providers?: SsoProvider[] }) => {
        setProviders(Array.isArray(data?.providers) ? data.providers : []);
      })
      .catch(() => setProviders([]));
  }, [tenantId]);

  // While we don't yet know, render nothing (no flash of unavailable buttons)
  if (providers === null || providers.length === 0) {
    return null;
  }

  function startFlow(key: string) {
    const params = new URLSearchParams();
    if (tenantId) params.set('tenant_id', String(tenantId));
    const qs = params.toString();
    // The redirect endpoint returns JSON `{ redirect_url }` — fetch it then navigate.
    fetch(`/api/v2/auth/sso/${encodeURIComponent(key)}/redirect${qs ? `?${qs}` : ''}`, {
      method: 'GET',
      credentials: 'include',
      headers: tenantId ? { 'X-Tenant-Id': String(tenantId) } : {},
    })
      .then((r) => r.json())
      .then((data: { success?: boolean; redirect_url?: string; message?: string }) => {
        if (data?.success && data.redirect_url) {
          window.location.href = data.redirect_url;
        } else {
          alert(data?.message || t('oauth.callback_failed'));
        }
      })
      .catch(() => alert(t('oauth.callback_failed')));
  }

  return (
    <div className="space-y-3">
      {providers.map((provider) => {
        const Icon = provider.preset === 'entra' ? Building2 : KeyRound;
        return (
          <Button
            key={provider.key}
            type="button"
            variant="outline"
            fullWidth
            size="lg"
            startContent={<Icon className="w-5 h-5" aria-hidden="true" />}
            onPress={() => startFlow(provider.key)}
            className="border-[var(--border-default)] text-theme-primary hover:bg-theme-hover"
          >
            {t('oauth.sign_in_with_provider', { name: provider.display_name })}
          </Button>
        );
      })}
    </div>
  );
}

export default SsoButtons;
