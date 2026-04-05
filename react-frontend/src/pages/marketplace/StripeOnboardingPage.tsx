// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * StripeOnboardingPage — Stripe Connect onboarding flow for marketplace sellers.
 *
 * Flow:
 *   1. Explain what Stripe Connect is and what sellers need
 *   2. "Start Onboarding" button → POST /v2/marketplace/seller/onboard → redirect to Stripe
 *   3. Return page checks status via GET /v2/marketplace/seller/onboard/status
 *   4. Shows success or retry UI based on onboarding completion
 *
 * Auth required.
 */

import { useState, useEffect, useCallback } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import {
  Button,
  Spinner,
  Chip,
} from '@heroui/react';
import {
  CreditCard,
  CheckCircle2,
  AlertCircle,
  Building2,
  Shield,
  ArrowRight,
  RefreshCw,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo/PageMeta';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface OnboardingStatus {
  stripe_onboarding_complete: boolean;
  stripe_account_id?: string;
  charges_enabled?: boolean;
  payouts_enabled?: boolean;
  details_submitted?: boolean;
}

interface OnboardingResponse {
  url: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function StripeOnboardingPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const { t } = useTranslation('marketplace');
  usePageTitle(t('onboarding.page_title', 'Seller Onboarding - Marketplace'));
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  // State
  const [status, setStatus] = useState<OnboardingStatus | null>(null);
  const [isLoadingStatus, setIsLoadingStatus] = useState(true);
  const [isStarting, setIsStarting] = useState(false);

  // Did Stripe just redirect back?
  const isReturn = searchParams.get('return') === '1' || searchParams.get('complete') === '1' || searchParams.get('refresh') === '1';

  // Redirect if not authenticated
  useEffect(() => {
    if (!isAuthenticated) {
      navigate(tenantPath('/auth/login'), { replace: true });
    }
  }, [isAuthenticated, navigate, tenantPath]);

  // Load onboarding status
  const loadStatus = useCallback(async () => {
    setIsLoadingStatus(true);
    try {
      const response = await api.get<OnboardingStatus>('/v2/marketplace/seller/onboard/status');
      if (response.success && response.data) {
        setStatus(response.data);
      }
    } catch (err) {
      logError('Failed to load Stripe onboarding status', err);
    } finally {
      setIsLoadingStatus(false);
    }
  }, []);

  useEffect(() => {
    if (!isAuthenticated) return;
    loadStatus();
  }, [isAuthenticated, loadStatus]);

  // Show toast if returning from Stripe
  useEffect(() => {
    if (isReturn && status) {
      if (status.stripe_onboarding_complete) {
        toast.success(t('onboarding.complete_toast', 'Stripe onboarding complete! You can now receive payments.'));
      }
    }
  }, [isReturn, status?.stripe_onboarding_complete]); // eslint-disable-line react-hooks/exhaustive-deps

  // Start onboarding
  const handleStartOnboarding = useCallback(async () => {
    setIsStarting(true);
    try {
      const response = await api.post<OnboardingResponse>('/v2/marketplace/seller/onboard');
      if (response.success && response.data?.url) {
        window.location.href = response.data.url;
        return;
      }
      toast.error(response.error || t('onboarding.start_error', 'Failed to start onboarding'));
    } catch (err) {
      logError('Failed to start Stripe onboarding', err);
      toast.error(t('onboarding.start_error', 'Failed to start onboarding'));
    } finally {
      setIsStarting(false);
    }
  }, [toast]);

  if (!isAuthenticated) return null;

  // Loading
  if (isLoadingStatus) {
    return (
      <div className="flex justify-center py-24">
        <Spinner size="lg" color="primary" />
      </div>
    );
  }

  const isComplete = status?.stripe_onboarding_complete === true;
  const isIncomplete = status && !status.stripe_onboarding_complete && status.stripe_account_id;

  return (
    <>
      <PageMeta title={t('onboarding.page_title', 'Seller Onboarding - Marketplace')} />

      <div className="max-w-2xl mx-auto px-4 py-8 space-y-6">
        {/* Header */}
        <div className="text-center space-y-2">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary/10 mb-2">
            <CreditCard className="w-8 h-8 text-primary" />
          </div>
          <h1 className="text-2xl font-bold text-foreground">
            {t('onboarding.title', 'Seller Payment Setup')}
          </h1>
          <p className="text-default-500 text-sm max-w-md mx-auto">
            {t('onboarding.subtitle', 'Set up Stripe Connect to receive payments from marketplace sales.')}
          </p>
        </div>

        {/* Success State */}
        {isComplete && (
          <GlassCard className="p-6 text-center space-y-4">
            <div className="inline-flex items-center justify-center w-14 h-14 rounded-full bg-success/10">
              <CheckCircle2 className="w-7 h-7 text-success" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-foreground">
                {t('onboarding.complete_title', "You're All Set!")}
              </h2>
              <p className="text-default-500 text-sm mt-1">
                {t('onboarding.complete_description', 'Your Stripe account is connected and you can now receive payments from marketplace sales.')}
              </p>
            </div>
            <div className="flex items-center justify-center gap-3">
              {status?.charges_enabled && (
                <Chip size="sm" color="success" variant="flat">
                  {t('onboarding.charges_enabled', 'Charges Enabled')}
                </Chip>
              )}
              {status?.payouts_enabled && (
                <Chip size="sm" color="success" variant="flat">
                  {t('onboarding.payouts_enabled', 'Payouts Enabled')}
                </Chip>
              )}
            </div>
            <Button
              color="primary"
              variant="flat"
              onPress={() => navigate(tenantPath('/marketplace/my-listings'))}
            >
              {t('onboarding.go_to_listings', 'Go to My Listings')}
            </Button>
          </GlassCard>
        )}

        {/* Incomplete State (returned from Stripe without finishing) */}
        {isIncomplete && (
          <GlassCard className="p-6 text-center space-y-4">
            <div className="inline-flex items-center justify-center w-14 h-14 rounded-full bg-warning/10">
              <AlertCircle className="w-7 h-7 text-warning" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-foreground">
                {t('onboarding.incomplete_title', 'Complete Your Onboarding')}
              </h2>
              <p className="text-default-500 text-sm mt-1">
                {t('onboarding.incomplete_description', 'Your Stripe account setup is not yet complete. Please finish the onboarding process to start receiving payments.')}
              </p>
            </div>
            <div className="flex items-center justify-center gap-3">
              <Button
                color="primary"
                onPress={handleStartOnboarding}
                isLoading={isStarting}
                startContent={!isStarting ? <RefreshCw className="w-4 h-4" /> : undefined}
              >
                {t('onboarding.continue_onboarding', 'Continue Onboarding')}
              </Button>
              <Button
                variant="flat"
                onPress={loadStatus}
                startContent={<RefreshCw className="w-4 h-4" />}
              >
                {t('onboarding.check_status', 'Check Status')}
              </Button>
            </div>
          </GlassCard>
        )}

        {/* Initial State (no Stripe account yet) */}
        {!isComplete && !isIncomplete && (
          <>
            {/* What is Stripe Connect */}
            <GlassCard className="p-6 space-y-4">
              <h2 className="text-lg font-semibold text-foreground">
                {t('onboarding.what_is_stripe_title', 'What is Stripe Connect?')}
              </h2>
              <p className="text-sm text-default-600">
                {t('onboarding.what_is_stripe_description', 'Stripe Connect is a secure payment platform that allows you to receive payments directly to your bank account when buyers purchase your marketplace listings. Your financial information is handled entirely by Stripe and is never stored on our servers.')}
              </p>
            </GlassCard>

            {/* What you need */}
            <GlassCard className="p-6 space-y-4">
              <h2 className="text-lg font-semibold text-foreground">
                {t('onboarding.what_you_need_title', 'What You\'ll Need')}
              </h2>
              <div className="space-y-3">
                <div className="flex items-start gap-3">
                  <div className="shrink-0 w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
                    <Building2 className="w-4 h-4 text-primary" />
                  </div>
                  <div>
                    <p className="text-sm font-medium text-foreground">
                      {t('onboarding.need_bank', 'Bank Account Details')}
                    </p>
                    <p className="text-xs text-default-500">
                      {t('onboarding.need_bank_desc', 'Your bank account or debit card to receive payouts.')}
                    </p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <div className="shrink-0 w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
                    <Shield className="w-4 h-4 text-primary" />
                  </div>
                  <div>
                    <p className="text-sm font-medium text-foreground">
                      {t('onboarding.need_id', 'Identity Verification')}
                    </p>
                    <p className="text-xs text-default-500">
                      {t('onboarding.need_id_desc', 'A government-issued ID to verify your identity (required by financial regulations).')}
                    </p>
                  </div>
                </div>
              </div>
            </GlassCard>

            {/* Start button */}
            <div className="flex justify-center">
              <Button
                color="primary"
                size="lg"
                onPress={handleStartOnboarding}
                isLoading={isStarting}
                endContent={!isStarting ? <ArrowRight className="w-5 h-5" /> : undefined}
                className="min-w-[240px]"
              >
                {t('onboarding.start_button', 'Start Onboarding')}
              </Button>
            </div>

            {/* Security note */}
            <p className="text-center text-xs text-default-400">
              {t('onboarding.security_note', 'You will be redirected to Stripe\'s secure website to complete the setup. Your financial information is never stored on our servers.')}
            </p>
          </>
        )}
      </div>
    </>
  );
}

export default StripeOnboardingPage;
