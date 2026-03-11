// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Verify Identity Page — Guides users through identity verification.
 *
 * Flow:
 * 1. On mount, polls /api/v2/auth/verification-status
 * 2. If status is 'pending_verification' and no active session → shows "Start Verification"
 * 3. If a session exists with a redirect_url → shows "Continue to Verification"
 * 4. Polls periodically until status changes to passed/failed/active
 * 5. Shows appropriate success/failure states
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Spinner } from '@heroui/react';
import {
  ShieldCheck,
  ShieldX,
  ShieldAlert,
  ExternalLink,
  ArrowLeft,
  Loader2,
  CheckCircle,
  RefreshCw,
  Clock,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useTenant, useAuth } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';

interface VerificationStatus {
  status: string;
  email_verified: boolean;
  is_approved: boolean;
  verification_status: string;
  verification_provider: string | null;
  registration_mode: string;
  latest_session: {
    id: number;
    status: string;
    provider: string;
    created_at: string;
    completed_at: string | null;
    failure_reason: string | null;
  } | null;
}

interface StartVerificationResult {
  session_id: number;
  redirect_url: string | null;
  client_token: string | null;
  provider: string;
  expires_at: string | null;
  status: string;
}

type PageState = 'loading' | 'start' | 'in_progress' | 'passed' | 'failed' | 'active' | 'error';

export function VerifyIdentityPage() {
  const { t } = useTranslation('auth');
  usePageTitle('Verify Identity');
  const navigate = useNavigate();
  const { branding, tenantPath } = useTenant();
  const { isAuthenticated } = useAuth();

  const [pageState, setPageState] = useState<PageState>('loading');
  const [verificationData, setVerificationData] = useState<VerificationStatus | null>(null);
  const [redirectUrl, setRedirectUrl] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState('');
  const [isStarting, setIsStarting] = useState(false);
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const fetchStatus = useCallback(async () => {
    try {
      const response = await api.get<VerificationStatus>('/v2/auth/verification-status');
      if (!response.success || !response.data) return;

      const data = response.data;
      setVerificationData(data);

      switch (data.status) {
        case 'active':
          setPageState('active');
          stopPolling();
          break;
        case 'pending_verification':
          if (data.latest_session?.status === 'started' || data.latest_session?.status === 'processing') {
            setPageState('in_progress');
          } else {
            setPageState('start');
          }
          break;
        case 'verification_failed':
          setPageState('failed');
          stopPolling();
          break;
        case 'pending_admin_review':
          setPageState('passed');
          stopPolling();
          break;
        default:
          // If verification_status is 'passed' but waiting for admin
          if (data.verification_status === 'passed') {
            setPageState('passed');
            stopPolling();
          } else if (data.verification_status === 'none' && data.registration_mode !== 'verified_identity' && data.registration_mode !== 'government_id') {
            // Not a verification tenant — redirect to dashboard
            navigate(tenantPath('/dashboard'), { replace: true });
          } else {
            setPageState('start');
          }
      }
    } catch {
      setPageState('error');
      setErrorMessage(t('verify_identity.fetch_error', { defaultValue: 'Unable to check verification status. Please try again.' }));
    }
  }, [navigate, tenantPath, t]);

  const stopPolling = useCallback(() => {
    if (pollRef.current) {
      clearInterval(pollRef.current);
      pollRef.current = null;
    }
  }, []);

  const startPolling = useCallback(() => {
    stopPolling();
    pollRef.current = setInterval(fetchStatus, 5000);
  }, [fetchStatus, stopPolling]);

  // Initial fetch + polling
  useEffect(() => {
    if (!isAuthenticated) return;
    fetchStatus();
    startPolling();
    return stopPolling;
  }, [isAuthenticated, fetchStatus, startPolling, stopPolling]);

  const handleStartVerification = async () => {
    setIsStarting(true);
    setErrorMessage('');
    try {
      const response = await api.post<StartVerificationResult>('/v2/auth/start-verification');
      if (response.success && response.data) {
        if (response.data.redirect_url) {
          setRedirectUrl(response.data.redirect_url);
          setPageState('in_progress');
          startPolling();
          // Open in new tab
          window.open(response.data.redirect_url, '_blank', 'noopener,noreferrer');
        } else {
          // No redirect — poll for status
          setPageState('in_progress');
          startPolling();
        }
      }
    } catch {
      setErrorMessage(t('verify_identity.start_error', { defaultValue: 'Unable to start verification. Please try again later.' }));
    } finally {
      setIsStarting(false);
    }
  };

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <GlassCard className="p-8 text-center max-w-md">
          <p className="text-theme-muted mb-4">
            {t('verify_identity.login_required', { defaultValue: 'Please log in to verify your identity.' })}
          </p>
          <Link to={tenantPath('/login')}>
            <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
              {t('verify_identity.go_to_login', { defaultValue: 'Go to Login' })}
            </Button>
          </Link>
        </GlassCard>
      </div>
    );
  }

  // Loading state
  if (pageState === 'loading') {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md">
          <GlassCard className="p-8 text-center">
            <div className="w-16 h-16 mx-auto mb-6 rounded-full bg-indigo-500/20 flex items-center justify-center">
              <Loader2 className="w-8 h-8 text-indigo-400 animate-spin" />
            </div>
            <h1 className="text-2xl font-bold text-theme-primary mb-2">
              {t('verify_identity.loading_title', { defaultValue: 'Checking verification status...' })}
            </h1>
          </GlassCard>
        </motion.div>
      </div>
    );
  }

  // Active / already verified
  if (pageState === 'active') {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md">
          <GlassCard className="p-8 text-center">
            <div className="w-16 h-16 mx-auto mb-6 rounded-full bg-emerald-500/20 flex items-center justify-center">
              <CheckCircle className="w-8 h-8 text-emerald-400" />
            </div>
            <h1 className="text-2xl font-bold text-theme-primary mb-2">
              {t('verify_identity.active_title', { defaultValue: 'Account verified' })}
            </h1>
            <p className="text-theme-muted mb-6">
              {t('verify_identity.active_subtitle', { defaultValue: 'Your account is fully verified and active.' })}
            </p>
            <Link to={tenantPath('/dashboard')}>
              <Button className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                {t('verify_identity.go_to_dashboard', { defaultValue: 'Go to Dashboard' })}
              </Button>
            </Link>
          </GlassCard>
          <p className="text-center text-theme-subtle text-sm mt-6">{branding.name}</p>
        </motion.div>
      </div>
    );
  }

  // Verification passed — pending admin review
  if (pageState === 'passed') {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md">
          <GlassCard className="p-8 text-center">
            <div className="w-16 h-16 mx-auto mb-6 rounded-full bg-emerald-500/20 flex items-center justify-center">
              <ShieldCheck className="w-8 h-8 text-emerald-400" />
            </div>
            <h1 className="text-2xl font-bold text-theme-primary mb-2">
              {t('verify_identity.passed_title', { defaultValue: 'Identity verified' })}
            </h1>
            <p className="text-theme-muted mb-4">
              {t('verify_identity.passed_subtitle', { defaultValue: 'Your identity has been successfully verified.' })}
            </p>
            {verificationData && !verificationData.is_approved && (
              <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-sm text-left mb-6">
                <div className="flex items-start gap-3">
                  <Clock className="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
                  <div>
                    <p className="font-medium text-amber-600 dark:text-amber-400">
                      {t('verify_identity.pending_approval_title', { defaultValue: 'Awaiting admin approval' })}
                    </p>
                    <p className="text-amber-600/80 dark:text-amber-300/80 mt-1">
                      {t('verify_identity.pending_approval_body', {
                        defaultValue: 'Your identity is verified, but your account still needs to be approved by a community administrator. You\'ll receive an email once approved.',
                      })}
                    </p>
                  </div>
                </div>
              </div>
            )}
            <Link to={tenantPath('/login')}>
              <Button variant="flat" className="w-full bg-theme-elevated text-theme-primary" startContent={<ArrowLeft className="w-4 h-4" />}>
                {t('verify_identity.back_to_login', { defaultValue: 'Back to Login' })}
              </Button>
            </Link>
          </GlassCard>
          <p className="text-center text-theme-subtle text-sm mt-6">{branding.name}</p>
        </motion.div>
      </div>
    );
  }

  // Verification failed
  if (pageState === 'failed') {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md">
          <GlassCard className="p-8 text-center">
            <div className="w-16 h-16 mx-auto mb-6 rounded-full bg-red-500/20 flex items-center justify-center">
              <ShieldX className="w-8 h-8 text-red-400" />
            </div>
            <h1 className="text-2xl font-bold text-theme-primary mb-2">
              {t('verify_identity.failed_title', { defaultValue: 'Verification unsuccessful' })}
            </h1>
            <p className="text-theme-muted mb-2">
              {t('verify_identity.failed_subtitle', { defaultValue: 'We were unable to verify your identity.' })}
            </p>
            {verificationData?.latest_session?.failure_reason && (
              <p className="text-red-600 dark:text-red-400 text-sm mb-6">
                {verificationData.latest_session.failure_reason}
              </p>
            )}
            <div className="flex flex-col gap-3">
              <Button
                onPress={handleStartVerification}
                isLoading={isStarting}
                className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={!isStarting ? <RefreshCw className="w-4 h-4" /> : undefined}
              >
                {t('verify_identity.retry', { defaultValue: 'Try again' })}
              </Button>
              <Link to={tenantPath('/login')}>
                <Button variant="flat" className="w-full bg-theme-elevated text-theme-primary" startContent={<ArrowLeft className="w-4 h-4" />}>
                  {t('verify_identity.back_to_login', { defaultValue: 'Back to Login' })}
                </Button>
              </Link>
            </div>
          </GlassCard>
          <p className="text-center text-theme-subtle text-sm mt-6">{branding.name}</p>
        </motion.div>
      </div>
    );
  }

  // Error state
  if (pageState === 'error') {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md">
          <GlassCard className="p-8 text-center">
            <div className="w-16 h-16 mx-auto mb-6 rounded-full bg-red-500/20 flex items-center justify-center">
              <ShieldX className="w-8 h-8 text-red-400" />
            </div>
            <h1 className="text-2xl font-bold text-theme-primary mb-2">
              {t('verify_identity.error_title', { defaultValue: 'Something went wrong' })}
            </h1>
            <p className="text-theme-muted mb-6">{errorMessage}</p>
            <Button
              onPress={() => { setPageState('loading'); fetchStatus(); }}
              className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" />}
            >
              {t('verify_identity.retry', { defaultValue: 'Try again' })}
            </Button>
          </GlassCard>
          <p className="text-center text-theme-subtle text-sm mt-6">{branding.name}</p>
        </motion.div>
      </div>
    );
  }

  // Start / In-progress states
  return (
    <div className="min-h-screen flex items-center justify-center p-4">
      <div className="fixed inset-0 overflow-hidden pointer-events-none">
        <div className="blob blob-indigo" />
        <div className="blob blob-purple" />
      </div>
      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md relative z-10">
        <GlassCard className="p-5 sm:p-8">
          <div className="text-center mb-8">
            <motion.div
              initial={{ scale: 0.8 }}
              animate={{ scale: 1 }}
              className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-4"
            >
              {pageState === 'in_progress' ? (
                <Spinner size="lg" color="secondary" />
              ) : (
                <ShieldAlert className="w-8 h-8 text-indigo-500 dark:text-indigo-400" />
              )}
            </motion.div>
            <h1 className="text-xl sm:text-2xl font-bold text-theme-primary">
              {pageState === 'in_progress'
                ? t('verify_identity.in_progress_title', { defaultValue: 'Verification in progress' })
                : t('verify_identity.start_title', { defaultValue: 'Verify your identity' })}
            </h1>
            <p className="text-theme-muted mt-2">
              {pageState === 'in_progress'
                ? t('verify_identity.in_progress_subtitle', {
                    defaultValue: 'Please complete the verification in the opened window. This page will update automatically.',
                  })
                : t('verify_identity.start_subtitle', {
                    defaultValue: 'This community requires identity verification before you can access your account.',
                  })}
            </p>
          </div>

          {errorMessage && (
            <motion.div
              initial={{ opacity: 0, y: -10 }}
              animate={{ opacity: 1, y: 0 }}
              className="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-sm text-red-600 dark:text-red-400"
            >
              {errorMessage}
            </motion.div>
          )}

          {pageState === 'start' && (
            <div className="space-y-4">
              <div className="p-4 rounded-xl bg-indigo-500/5 border border-indigo-500/10">
                <h3 className="font-medium text-theme-primary text-sm mb-2">
                  {t('verify_identity.what_needed_title', { defaultValue: 'What you\'ll need' })}
                </h3>
                <ul className="text-sm text-theme-muted space-y-1">
                  <li>• {t('verify_identity.need_document', { defaultValue: 'A valid government-issued photo ID' })}</li>
                  <li>• {t('verify_identity.need_camera', { defaultValue: 'A device with a camera' })}</li>
                  <li>• {t('verify_identity.need_minutes', { defaultValue: 'About 2–5 minutes' })}</li>
                </ul>
              </div>

              <Button
                onPress={handleStartVerification}
                isLoading={isStarting}
                className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium"
                size="lg"
                startContent={!isStarting ? <ShieldCheck className="w-5 h-5" /> : undefined}
                spinner={<Loader2 className="w-4 h-4 animate-spin" />}
              >
                {t('verify_identity.start_button', { defaultValue: 'Start verification' })}
              </Button>
            </div>
          )}

          {pageState === 'in_progress' && (
            <div className="space-y-4">
              {redirectUrl && (
                <Button
                  as="a"
                  href={redirectUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  variant="bordered"
                  className="w-full border-indigo-500/30 text-theme-primary hover:bg-indigo-500/10"
                  size="lg"
                  startContent={<ExternalLink className="w-4 h-4" />}
                >
                  {t('verify_identity.open_verification', { defaultValue: 'Open verification window' })}
                </Button>
              )}

              <div className="flex items-center justify-center gap-2 text-sm text-theme-muted">
                <Loader2 className="w-4 h-4 animate-spin" />
                {t('verify_identity.waiting', { defaultValue: 'Waiting for verification result...' })}
              </div>
            </div>
          )}

          <div className="mt-6 text-center">
            <Link
              to={tenantPath('/login')}
              className="inline-flex items-center gap-2 text-theme-subtle hover:text-theme-secondary text-sm transition-colors"
            >
              <ArrowLeft className="w-4 h-4" />
              {t('verify_identity.back_to_login', { defaultValue: 'Back to Login' })}
            </Link>
          </div>
        </GlassCard>
        <p className="text-center text-theme-subtle text-sm mt-6">{branding.name}</p>
      </motion.div>
    </div>
  );
}

export default VerifyIdentityPage;
