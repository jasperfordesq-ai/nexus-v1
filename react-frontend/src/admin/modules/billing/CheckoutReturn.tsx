// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CheckoutReturn
 * Post-checkout landing page that polls for subscription activation.
 */

import { useEffect, useState, useRef, useCallback } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Card, CardBody, Button, Spinner } from '@heroui/react';
import { CheckCircle, AlertCircle, ArrowRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { billingApi, type SubscriptionDetails } from '../../api/billingApi';

const MAX_POLL_ATTEMPTS = 10;
const POLL_INTERVAL_MS = 2000;

export function CheckoutReturn() {
  const { t } = useTranslation('admin');
  usePageTitle(t('billing.checkout_title', 'Checkout'));
  const { tenantPath } = useTenant();
  const [searchParams] = useSearchParams();
  const sessionId = searchParams.get('session_id');

  const [status, setStatus] = useState<'polling' | 'success' | 'failed'>('polling');
  const [subscription, setSubscription] = useState<SubscriptionDetails | null>(null);
  const attemptRef = useRef(0);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const poll = useCallback(async () => {
    attemptRef.current += 1;

    try {
      const res = await billingApi.getSubscription();
      if (res.success && res.data) {
        const sub = res.data as unknown as SubscriptionDetails;
        if (sub.status === 'active' || sub.status === 'trialing') {
          setSubscription(sub);
          setStatus('success');
          return;
        }
      }
    } catch {
      // Continue polling
    }

    if (attemptRef.current >= MAX_POLL_ATTEMPTS) {
      setStatus('failed');
      return;
    }

    timerRef.current = setTimeout(poll, POLL_INTERVAL_MS);
  }, []);

  useEffect(() => {
    if (sessionId) {
      poll();
    } else {
      setStatus('failed');
    }

    return () => {
      if (timerRef.current) {
        clearTimeout(timerRef.current);
      }
    };
  }, [sessionId, poll]);

  return (
    <div className="flex justify-center items-center min-h-[60vh]">
      <Card className="max-w-md w-full">
        <CardBody className="text-center py-10 px-8 gap-4">
          {status === 'polling' && (
            <>
              <Spinner size="lg" className="mx-auto" />
              <h2 className="text-xl font-semibold mt-4">
                {t('billing.processing', 'Processing your subscription...')}
              </h2>
              <p className="text-default-500">
                {t('billing.processing_desc', 'Please wait while we confirm your payment')}
              </p>
            </>
          )}

          {status === 'success' && (
            <>
              <CheckCircle className="w-16 h-16 text-success mx-auto" />
              <h2 className="text-xl font-semibold mt-4">
                {t('billing.checkout_success', 'Subscription activated!')}
              </h2>
              <p className="text-default-500">
                {subscription
                  ? t('billing.checkout_success_desc', 'You are now on the {{plan}} plan', {
                      plan: subscription.plan_name,
                    })
                  : t('billing.checkout_success_generic', 'Your subscription is now active')}
              </p>
              <Button
                as={Link}
                to={tenantPath('/admin/billing')}
                color="primary"
                endContent={<ArrowRight className="w-4 h-4" />}
                className="mt-4"
              >
                {t('billing.go_to_billing', 'Go to Billing')}
              </Button>
            </>
          )}

          {status === 'failed' && (
            <>
              <AlertCircle className="w-16 h-16 text-danger mx-auto" />
              <h2 className="text-xl font-semibold mt-4">
                {t('billing.checkout_failed', 'Something went wrong')}
              </h2>
              <p className="text-default-500">
                {t(
                  'billing.checkout_failed_desc',
                  'We could not confirm your subscription. If you were charged, your subscription should activate shortly.'
                )}
              </p>
              <div className="flex gap-3 justify-center mt-4">
                <Button
                  as={Link}
                  to={tenantPath('/admin/billing/plans')}
                  color="primary"
                  variant="flat"
                >
                  {t('billing.try_again', 'Try Again')}
                </Button>
                <Button
                  as={Link}
                  to={tenantPath('/admin/billing')}
                  variant="bordered"
                >
                  {t('billing.go_to_billing', 'Go to Billing')}
                </Button>
              </div>
            </>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

export default CheckoutReturn;
