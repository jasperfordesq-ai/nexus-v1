// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { Button } from '@heroui/react';
import { useTranslation } from 'react-i18next';
import { GoogleIcon } from '@/components/icons/GoogleIcon';
import { AppleIcon } from '@/components/icons/AppleIcon';
import { FacebookIcon } from '@/components/icons/FacebookIcon';

type Provider = 'google' | 'apple' | 'facebook';

interface OAuthButtonsProps {
  /**
   * "login" or "register" — passed to the backend so the state token records intent.
   */
  intent?: 'login' | 'register';
  /**
   * Restrict which providers to show (e.g. from tenant settings). Empty/undefined = show all.
   */
  enabledProviders?: Provider[];
  /**
   * Optional tenant id to forward when no tenant is resolved by host/slug yet.
   */
  tenantId?: string | number;
}

const PROVIDERS: ReadonlyArray<{ id: Provider; Icon: typeof GoogleIcon; key: string }> = [
  { id: 'google', Icon: GoogleIcon, key: 'oauth.continue_with_google' },
  { id: 'apple', Icon: AppleIcon, key: 'oauth.continue_with_apple' },
  { id: 'facebook', Icon: FacebookIcon, key: 'oauth.continue_with_facebook' },
];

export function OAuthButtons({ intent = 'login', enabledProviders, tenantId }: OAuthButtonsProps) {
  const { t } = useTranslation('common');

  // If parent didn't pass enabledProviders, ask the backend which providers are enabled.
  // Backend returns [] when OAUTH_ENABLED is off — buttons hide entirely.
  const [serverProviders, setServerProviders] = useState<Provider[] | null>(
    enabledProviders ?? null
  );
  useEffect(() => {
    if (enabledProviders !== undefined) return;
    const headers: Record<string, string> = {};
    if (tenantId) headers['X-Tenant-Id'] = String(tenantId);
    fetch('/api/v2/auth/oauth/enabled-providers', { credentials: 'include', headers })
      .then((r) => r.json())
      .then((data: { success?: boolean; providers?: Provider[] }) => {
        setServerProviders(Array.isArray(data?.providers) ? data.providers : []);
      })
      .catch(() => setServerProviders([]));
  }, [enabledProviders, tenantId]);

  // While we don't yet know, render nothing (no flash of unavailable buttons)
  if (serverProviders === null) return null;

  const visible = PROVIDERS.filter((p) =>
    serverProviders.length > 0 ? serverProviders.includes(p.id) : false
  );

  if (visible.length === 0) {
    return null;
  }

  function startFlow(provider: Provider) {
    const params = new URLSearchParams({ intent });
    if (tenantId) params.set('tenant_id', String(tenantId));
    // The redirect endpoint returns JSON `{ redirect_url }` — fetch it then navigate.
    fetch(`/api/v2/auth/oauth/${provider}/redirect?${params.toString()}`, {
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
      {visible.map(({ id, Icon, key }) => (
        <Button
          key={id}
          type="button"
          variant="bordered"
          fullWidth
          size="lg"
          startContent={<Icon className="w-5 h-5" />}
          onPress={() => startFlow(id)}
          className="border-[var(--border-default)] text-theme-primary hover:bg-theme-hover"
        >
          {t(key)}
        </Button>
      ))}
      <div className="relative flex items-center my-2">
        <div className="flex-grow border-t border-[var(--border-default)]" />
        <span className="flex-shrink mx-3 text-xs text-theme-subtle">
          {t('oauth.or_continue_with_email')}
        </span>
        <div className="flex-grow border-t border-[var(--border-default)]" />
      </div>
    </div>
  );
}

export default OAuthButtons;
