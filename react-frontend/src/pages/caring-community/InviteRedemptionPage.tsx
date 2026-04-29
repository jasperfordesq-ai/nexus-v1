// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Button, Card, CardBody, Spinner } from '@heroui/react';
import HeartHandshake from 'lucide-react/icons/heart-handshake';
import AlertCircle from 'lucide-react/icons/alert-circle';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { api } from '@/lib/api';

type InviteLookupResult = {
  valid: boolean;
  expired: boolean;
  already_used: boolean;
  tenant_name: string;
  caring_community_enabled: boolean;
};

function isInviteLookupResult(value: unknown): value is InviteLookupResult {
  return (
    typeof value === 'object' &&
    value !== null &&
    'valid' in value &&
    'expired' in value &&
    'already_used' in value
  );
}

export default function InviteRedemptionPage() {
  const { t } = useTranslation('common');
  const { tenantPath } = useTenant();
  const navigate = useNavigate();
  const { code } = useParams<{ code: string }>();

  usePageTitle(t('invite.valid.title'));

  const [status, setStatus] = useState<'loading' | 'valid' | 'expired' | 'used' | 'invalid'>('loading');
  const [tenantName, setTenantName] = useState('');

  useEffect(() => {
    if (!code) {
      setStatus('invalid');
      return;
    }

    let cancelled = false;

    api
      .get<InviteLookupResult>(`/v2/caring-community/invite/${encodeURIComponent(code)}`)
      .then((res) => {
        if (cancelled) return;
        const data = res.data;
        if (!isInviteLookupResult(data)) {
          setStatus('invalid');
          return;
        }
        setTenantName(data.tenant_name ?? '');
        if (data.already_used) {
          setStatus('used');
        } else if (data.expired) {
          setStatus('expired');
        } else if (data.valid) {
          setStatus('valid');
        } else {
          setStatus('invalid');
        }
      })
      .catch(() => {
        if (!cancelled) setStatus('invalid');
      });

    return () => {
      cancelled = true;
    };
  }, [code]);

  const handleJoinNow = () => {
    const registerPath = tenantPath('/register');
    const query = code ? `?invite_code=${encodeURIComponent(code)}` : '';
    void navigate(registerPath + query);
  };

  if (status === 'loading') {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--color-background)]">
        <div className="flex flex-col items-center gap-5" role="status" aria-live="polite" aria-busy="true">
          <Spinner size="lg" color="primary" />
          <p className="text-base text-default-600">{t('invite.loading')}</p>
        </div>
      </div>
    );
  }

  if (status === 'valid') {
    return (
      <div className="flex min-h-screen items-center justify-center bg-gradient-to-b from-primary/5 to-background p-4">
        <Card className="w-full max-w-md" shadow="md">
          <CardBody className="flex flex-col items-center gap-7 p-8 text-center">
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary">
              <HeartHandshake size={32} aria-hidden="true" />
            </div>
            <div>
              <h1 className="text-2xl font-bold text-default-900 sm:text-3xl">{t('invite.valid.title')}</h1>
              <p className="mt-4 text-base leading-8 text-default-600">
                {t('invite.valid.body', { community: tenantName })}
              </p>
            </div>
            <Button
              color="primary"
              size="lg"
              className="w-full text-base"
              onPress={handleJoinNow}
            >
              {t('invite.valid.cta')}
            </Button>
          </CardBody>
        </Card>
      </div>
    );
  }

  // Expired / used / invalid — all show a gentle error card
  const titleKey = status === 'expired'
    ? 'invite.expired.title'
    : status === 'used'
      ? 'invite.used.title'
      : 'invite.invalid.title';

  const bodyKey = status === 'expired'
    ? 'invite.expired.body'
    : status === 'used'
      ? 'invite.used.body'
      : 'invite.invalid.body';

  return (
    <div className="flex min-h-screen items-center justify-center bg-[var(--color-background)] p-4">
      <Card className="w-full max-w-md" shadow="md">
        <CardBody className="flex flex-col items-center gap-7 p-8 text-center">
          <div className="flex h-16 w-16 items-center justify-center rounded-full bg-warning/10 text-warning">
            <AlertCircle size={32} aria-hidden="true" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-default-900 sm:text-3xl">{t(titleKey)}</h1>
            <p className="mt-4 text-base leading-8 text-default-600">{t(bodyKey)}</p>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
