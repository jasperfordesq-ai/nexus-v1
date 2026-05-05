// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Connected Accounts Tab (SOC13)
 *
 * Lists Google / Apple / Facebook identities currently linked to the user.
 * Allows connecting new providers (initiates OAuth link flow) and disconnecting
 * existing ones (refuses if it would remove the user's only auth method —
 * backend returns 422 in that case).
 */

import { useCallback, useEffect, useState } from 'react';
import { Button } from '@heroui/react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { GoogleIcon } from '@/components/icons/GoogleIcon';
import { AppleIcon } from '@/components/icons/AppleIcon';
import { FacebookIcon } from '@/components/icons/FacebookIcon';
import { api } from '@/lib/api';
import { useToast } from '@/contexts';
import { logError } from '@/lib/logger';

type Provider = 'google' | 'apple' | 'facebook';

interface OauthIdentity {
  provider: Provider;
  provider_email: string | null;
  avatar_url: string | null;
  linked_at: string;
  last_used_at: string | null;
}

interface IdentitiesResponse {
  identities: OauthIdentity[];
  enabled_providers: Provider[];
  supported_providers: Provider[];
}

const PROVIDER_META: Record<Provider, { Icon: typeof GoogleIcon; providerLabelKey: string }> = {
  google: { Icon: GoogleIcon, providerLabelKey: 'oauth.provider_google' },
  apple: { Icon: AppleIcon, providerLabelKey: 'oauth.provider_apple' },
  facebook: { Icon: FacebookIcon, providerLabelKey: 'oauth.provider_facebook' },
};

export function ConnectedAccountsTab() {
  const { t } = useTranslation('common');
  const toast = useToast();
  const [data, setData] = useState<IdentitiesResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [busyProvider, setBusyProvider] = useState<Provider | null>(null);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const res = await api.get<IdentitiesResponse>('/v2/auth/oauth/me/identities');
      if (res.success && res.data) {
        setData(res.data);
      }
    } catch (err) {
      logError('[ConnectedAccountsTab] Failed to load identities', err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function handleConnect(provider: Provider) {
    setBusyProvider(provider);
    try {
      const res = await api.post<{ redirect_url: string }>(`/v2/auth/oauth/${provider}/link`, {});
      if (res.success && res.data?.redirect_url) {
        window.location.href = res.data.redirect_url;
      } else {
        toast.error(res.error || t('oauth.callback_failed'));
        setBusyProvider(null);
      }
    } catch (err) {
      logError('[ConnectedAccountsTab] connect failed', err);
      toast.error(t('oauth.callback_failed'));
      setBusyProvider(null);
    }
  }

  async function handleDisconnect(provider: Provider) {
    setBusyProvider(provider);
    try {
      const res = await api.delete(`/v2/auth/oauth/${provider}/unlink`);
      if (res.success) {
        toast.success(t('oauth.disconnected'));
        await load();
      } else {
        toast.error(res.error || t('oauth.cannot_disconnect_last'));
      }
    } catch (err) {
      logError('[ConnectedAccountsTab] disconnect failed', err);
      toast.error(t('oauth.cannot_disconnect_last'));
    } finally {
      setBusyProvider(null);
    }
  }

  const supported: Provider[] = data?.supported_providers ?? ['google', 'apple', 'facebook'];
  const enabled = new Set(data?.enabled_providers ?? []);
  const linkedMap = new Map((data?.identities ?? []).map((i) => [i.provider, i] as const));

  return (
    <GlassCard className="p-6">
      <h2 className="text-lg font-semibold text-theme-primary mb-1">
        {t('oauth.connected_accounts.title')}
      </h2>
      <p className="text-sm text-theme-muted mb-6">
        {t('oauth.connected_accounts.subtitle')}
      </p>

      <ul className="space-y-3">
        {supported.map((provider) => {
          const meta = PROVIDER_META[provider];
          const linked = linkedMap.get(provider);
          const isBusy = busyProvider === provider;
          const isOnlyAuthMethod = !!linked && (data?.identities.length ?? 0) <= 1;
          const isProviderEnabled = enabled.has(provider);
          return (
            <li
              key={provider}
              className="flex items-center gap-4 p-4 rounded-xl border border-[var(--border-default)] bg-[var(--color-surface)]"
            >
              <meta.Icon className="w-7 h-7 flex-shrink-0" />
              <div className="flex-1 min-w-0">
                <p className="font-medium text-theme-primary">{t(meta.providerLabelKey)}</p>
                {linked ? (
                  <p className="text-xs text-theme-muted truncate">
                    {linked.provider_email ?? ''}
                    {linked.linked_at && (
                      <span className="ml-2">
                        {t('oauth.connected_at')}{' '}
                        {new Date(linked.linked_at).toLocaleDateString()}
                      </span>
                    )}
                  </p>
                ) : (
                  <p className="text-xs text-theme-subtle">
                    {isProviderEnabled ? t('oauth.not_connected') : t('oauth.provider_unavailable')}
                  </p>
                )}
              </div>
              {linked ? (
                <Button
                  size="sm"
                  variant="bordered"
                  isDisabled={isBusy || isOnlyAuthMethod}
                  isLoading={isBusy}
                  onPress={() => handleDisconnect(provider)}
                >
                  {isOnlyAuthMethod ? t('oauth.cannot_disconnect_last') : t('oauth.disconnect')}
                </Button>
              ) : (
                <Button
                  size="sm"
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  isDisabled={isBusy || loading || !isProviderEnabled}
                  isLoading={isBusy}
                  onPress={() => handleConnect(provider)}
                >
                  {t('oauth.connect')}
                </Button>
              )}
            </li>
          );
        })}
      </ul>
    </GlassCard>
  );
}

export default ConnectedAccountsTab;
