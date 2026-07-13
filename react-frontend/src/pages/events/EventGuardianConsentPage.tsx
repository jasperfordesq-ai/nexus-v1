// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import ShieldCheck from 'lucide-react/icons/shield-check';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { Checkbox } from '@/components/ui/Checkbox';
import { Input } from '@/components/ui/Input';
import { PageMeta } from '@/components/seo/PageMeta';
import { useTenant } from '@/contexts/TenantContext';
import { usePageTitle } from '@/hooks/usePageTitle';
import { eventSafetyApi } from '@/lib/event-safety-api';
import { logError } from '@/lib/logger';

type GrantState = 'ready' | 'submitting' | 'granted' | 'invalid';

function idempotencyKey(): string {
  if (typeof globalThis.crypto?.randomUUID === 'function') {
    return `event-guardian-grant-${globalThis.crypto.randomUUID()}`;
  }

  return `event-guardian-grant-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export function EventGuardianConsentPage() {
  const { t } = useTranslation('event_safety');
  const { tenantPath } = useTenant();
  const [searchParams] = useSearchParams();
  const [token] = useState(() => searchParams.get('token')?.trim() ?? '');
  const [guardianEmail, setGuardianEmail] = useState('');
  const [confirmed, setConfirmed] = useState(false);
  const [state, setState] = useState<GrantState>(token ? 'ready' : 'invalid');

  usePageTitle(t('safety.guardian_grant.page_title'));

  useEffect(() => {
    if (!token) return;

    // Keep the capability out of same-origin Referer headers while the guardian
    // reviews the request. The token remains only in this page's memory.
    const cleanUrl = `${window.location.pathname}${window.location.hash}`;
    window.history.replaceState(window.history.state, '', cleanUrl);
  }, [token]);

  const submit = async () => {
    if (!token || !guardianEmail.trim() || !confirmed || state === 'submitting') return;
    setState('submitting');
    try {
      const response = await eventSafetyApi.grantGuardianConsent(
        token,
        guardianEmail.trim(),
        idempotencyKey(),
      );
      if (!response.success || response.data?.status !== 'granted') {
        setState('invalid');
        return;
      }

      setGuardianEmail('');
      setConfirmed(false);
      setState('granted');
    } catch {
      // Do not log the capability token or guardian address.
      logError('Event guardian consent grant request failed', {
        endpoint: '/v2/events/safety/guardian-consents/grant',
      });
      setState('invalid');
    }
  };

  return (
    <div className="mx-auto flex min-h-[70vh] max-w-2xl items-center px-4 py-10 sm:px-6">
      <PageMeta title={t('safety.guardian_grant.page_title')} noIndex />
      <Card className="w-full border border-theme-default bg-theme-surface">
        <CardBody className="space-y-6 p-6 sm:p-8">
          <div className="flex items-start gap-4">
            <span className="rounded-xl bg-accent/10 p-3 text-accent" aria-hidden="true">
              <ShieldCheck className="h-7 w-7" />
            </span>
            <div>
              <h1 className="text-2xl font-bold text-theme-primary">{t('safety.guardian_grant.title')}</h1>
              <p className="mt-2 text-sm leading-6 text-theme-muted">{t('safety.guardian_grant.description')}</p>
            </div>
          </div>

          {state === 'granted' ? (
            <div className="space-y-5">
              <Alert
                color="success"
                icon={<CheckCircle2 className="h-5 w-5" aria-hidden="true" />}
                title={t('safety.guardian_grant.success_title')}
                description={t('safety.guardian_grant.success_description')}
              />
              <Button as={Link} to={tenantPath('/events')} color="primary">
                {t('safety.guardian_grant.browse_events')}
              </Button>
            </div>
          ) : (
            <form
              className="space-y-5"
              onSubmit={(event) => {
                event.preventDefault();
                void submit();
              }}
            >
              {state === 'invalid' && (
                <Alert
                  color="danger"
                  title={t('safety.guardian_grant.invalid_title')}
                  description={t('safety.guardian_grant.invalid_description')}
                />
              )}

              <Input
                type="email"
                isRequired
                maxLength={254}
                autoComplete="email"
                label={t('safety.guardian_grant.email_label')}
                description={t('safety.guardian_grant.email_hint')}
                value={guardianEmail}
                onValueChange={(value) => {
                  setGuardianEmail(value);
                  if (state === 'invalid' && token) setState('ready');
                }}
              />

              <div className="rounded-xl border border-theme-default bg-theme-elevated p-4">
                <Checkbox isSelected={confirmed} onValueChange={setConfirmed}>
                  {t('safety.guardian_grant.confirm_label')}
                </Checkbox>
                <p className="mt-2 text-xs leading-5 text-theme-muted">{t('safety.guardian_grant.privacy_notice')}</p>
              </div>

              <Button
                type="submit"
                color="primary"
                isLoading={state === 'submitting'}
                isDisabled={!token || !guardianEmail.trim() || !confirmed || state === 'submitting'}
              >
                {t('safety.guardian_grant.submit')}
              </Button>
            </form>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

export default EventGuardianConsentPage;
