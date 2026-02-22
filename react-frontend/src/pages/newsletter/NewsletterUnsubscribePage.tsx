// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Button } from '@heroui/react';
import { CheckCircle, XCircle, Loader2, Mail } from 'lucide-react';
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
  usePageTitle('Unsubscribe from Newsletter');

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
      });
  // eslint-disable-next-line react-hooks/exhaustive-deps
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
            <h1 className="text-xl font-semibold text-foreground">Processing…</h1>
            <p className="mt-2 text-default-500 text-sm">Removing you from the mailing list.</p>
          </>
        )}

        {state === 'success' && (
          <>
            <CheckCircle className="mx-auto text-success mb-4" size={36} />
            <h1 className="text-xl font-semibold text-foreground">You're unsubscribed</h1>
            <p className="mt-2 text-default-500 text-sm">
              You've been removed from our newsletter. You'll still receive important account notifications.
            </p>
            <Button
              className="mt-6"
              color="primary"
              variant="flat"
              as="a"
              href={tenantPath('/settings')}
            >
              Manage all preferences
            </Button>
          </>
        )}

        {state === 'already_done' && (
          <>
            <CheckCircle className="mx-auto text-success mb-4" size={36} />
            <h1 className="text-xl font-semibold text-foreground">Already unsubscribed</h1>
            <p className="mt-2 text-default-500 text-sm">
              You're already off our mailing list. No further action needed.
            </p>
          </>
        )}

        {state === 'invalid' && (
          <>
            <XCircle className="mx-auto text-danger mb-4" size={36} />
            <h1 className="text-xl font-semibold text-foreground">Invalid link</h1>
            <p className="mt-2 text-default-500 text-sm">
              This unsubscribe link is invalid or has already been used. If you still want to unsubscribe,
              you can manage your email preferences from your settings.
            </p>
            <Button
              className="mt-6"
              color="primary"
              variant="flat"
              as="a"
              href={tenantPath('/settings')}
            >
              Go to Settings
            </Button>
          </>
        )}

        {state === 'error' && (
          <>
            <XCircle className="mx-auto text-danger mb-4" size={36} />
            <h1 className="text-xl font-semibold text-foreground">Something went wrong</h1>
            <p className="mt-2 text-default-500 text-sm">
              We couldn't process your request. Please try again or contact support.
            </p>
            <Button
              className="mt-6"
              color="default"
              variant="flat"
              onPress={() => window.location.reload()}
            >
              Try again
            </Button>
          </>
        )}
      </div>
    </div>
  );
}
