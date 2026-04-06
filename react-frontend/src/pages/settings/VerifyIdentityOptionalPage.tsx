// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * VerifyIdentityOptionalPage — Voluntary identity verification for active members.
 *
 * Accessible from the utility bar "Verify Identity" button.
 * On success, the user earns an "ID Verified" badge on their profile.
 * Uses Stripe Identity for document + selfie verification.
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Spinner } from '@heroui/react';
import {
  ShieldCheck,
  ShieldX,
  Fingerprint,
  ExternalLink,
  ArrowLeft,
  Loader2,
  RefreshCw,
  BadgeCheck,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { useTenant, useAuth } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';

interface VerificationStatusResponse {
  has_id_verified_badge: boolean;
  verification_status: string | null;
  latest_session: {
    id: number;
    status: string;
    provider: string | null;
    created_at: string;
    failure_reason: string | null;
  } | null;
}

interface StartVerificationResponse {
  session_id?: number;
  redirect_url?: string | null;
  client_token?: string | null;
  provider?: string;
  expires_at?: string | null;
  status?: string;
  already_verified?: boolean;
  message?: string;
}

type PageState = 'loading' | 'start' | 'in_progress' | 'verified' | 'failed' | 'error';

export function VerifyIdentityOptionalPage() {
  usePageTitle('Verify Identity');
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const { isAuthenticated } = useAuth();

  const [pageState, setPageState] = useState<PageState>('loading');
  const [redirectUrl, setRedirectUrl] = useState<string | null>(null);
  const [failureReason, setFailureReason] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState('');
  const [isStarting, setIsStarting] = useState(false);
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const userStartedRef = useRef(false); // Tracks if user clicked "Start Verification" this session

  const stopPolling = useCallback(() => {
    if (pollRef.current) {
      clearInterval(pollRef.current);
      pollRef.current = null;
    }
  }, []);

  const fetchStatus = useCallback(async () => {
    try {
      const response = await api.get<VerificationStatusResponse>('/v2/identity/status');
      if (!response.success || !response.data) return;

      const data = response.data;

      if (data.has_id_verified_badge) {
        setPageState('verified');
        stopPolling();
        return;
      }

      const sessionStatus = data.latest_session?.status;
      if (sessionStatus === 'passed') {
        setPageState('verified');
        stopPolling();
      } else if (sessionStatus === 'started' || sessionStatus === 'processing') {
        setPageState('in_progress');
      } else if (sessionStatus === 'created') {
        if (userStartedRef.current) {
          // User already clicked start — keep showing in_progress while Stripe loads
          setPageState('in_progress');
        } else {
          // Initial page load with a stale created session — let them start fresh
          setPageState('start');
        }
      } else if (sessionStatus === 'failed') {
        setFailureReason(data.latest_session?.failure_reason || null);
        setPageState('failed');
        stopPolling();
      } else {
        setPageState('start');
      }
    } catch {
      setPageState('error');
      setErrorMessage('Unable to check verification status. Please try again.');
    }
  }, [stopPolling]);

  const startPolling = useCallback(() => {
    stopPolling();
    pollRef.current = setInterval(fetchStatus, 5000);
  }, [fetchStatus, stopPolling]);

  useEffect(() => {
    if (!isAuthenticated) return;
    fetchStatus();
    return stopPolling;
  }, [isAuthenticated, fetchStatus, stopPolling]);

  const handleStartVerification = async () => {
    setIsStarting(true);
    setErrorMessage('');
    userStartedRef.current = true;
    try {
      const response = await api.post<StartVerificationResponse>('/v2/identity/start');
      if (response.success && response.data) {
        if (response.data.already_verified) {
          setPageState('verified');
          return;
        }
        if (response.data.redirect_url) {
          setRedirectUrl(response.data.redirect_url);
          setPageState('in_progress');
          startPolling();
          window.open(response.data.redirect_url, '_blank', 'noopener,noreferrer');
        } else {
          setPageState('in_progress');
          startPolling();
        }
      }
    } catch {
      setErrorMessage('Unable to start verification. Please try again later.');
    } finally {
      setIsStarting(false);
    }
  };

  if (!isAuthenticated) {
    return (
      <div className="min-h-[60vh] flex items-center justify-center p-4">
        <GlassCard className="p-8 text-center max-w-md">
          <p className="text-theme-muted mb-4">Please log in to verify your identity.</p>
          <Link to={tenantPath('/login')}>
            <Button color="primary">Go to Login</Button>
          </Link>
        </GlassCard>
      </div>
    );
  }

  // Loading
  if (pageState === 'loading') {
    return (
      <div className="min-h-[60vh] flex items-center justify-center p-4">
        <PageMeta title="Verify Identity" noIndex />
        <GlassCard className="p-8 text-center max-w-md">
          <div className="w-16 h-16 mx-auto mb-6 rounded-full bg-purple-500/20 flex items-center justify-center">
            <Loader2 className="w-8 h-8 text-purple-400 animate-spin" />
          </div>
          <h1 className="text-2xl font-bold text-theme-primary">Checking verification status...</h1>
        </GlassCard>
      </div>
    );
  }

  // Already verified
  if (pageState === 'verified') {
    return (
      <div className="min-h-[60vh] flex items-center justify-center p-4">
        <PageMeta title="Identity Verified" noIndex />
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md">
          <GlassCard className="p-8 text-center">
            <div className="w-16 h-16 mx-auto mb-6 rounded-full bg-emerald-500/20 flex items-center justify-center">
              <BadgeCheck className="w-8 h-8 text-emerald-400" />
            </div>
            <h1 className="text-2xl font-bold text-theme-primary mb-2">Identity Verified</h1>
            <p className="text-theme-muted mb-2">
              Your identity has been verified. The <strong className="text-purple-500">ID Verified</strong> badge
              is now visible on your profile.
            </p>
            <div className="flex items-center justify-center gap-2 mt-4 mb-6 p-3 rounded-xl bg-purple-500/10 border border-purple-500/20">
              <Fingerprint className="w-5 h-5 text-purple-500" />
              <span className="text-sm font-medium text-purple-600 dark:text-purple-400">ID Verified</span>
            </div>
            <div className="flex flex-col gap-3">
              <Button
                color="primary"
                className="w-full"
                onPress={() => navigate(tenantPath('/settings'))}
              >
                Go to Settings
              </Button>
              <Button
                variant="flat"
                className="w-full"
                startContent={<ArrowLeft className="w-4 h-4" />}
                onPress={() => navigate(tenantPath('/dashboard'))}
              >
                Back to Dashboard
              </Button>
            </div>
          </GlassCard>
        </motion.div>
      </div>
    );
  }

  // Failed
  if (pageState === 'failed') {
    return (
      <div className="min-h-[60vh] flex items-center justify-center p-4">
        <PageMeta title="Verification Failed" noIndex />
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md">
          <GlassCard className="p-8 text-center">
            <div className="w-16 h-16 mx-auto mb-6 rounded-full bg-red-500/20 flex items-center justify-center">
              <ShieldX className="w-8 h-8 text-red-400" />
            </div>
            <h1 className="text-2xl font-bold text-theme-primary mb-2">Verification Unsuccessful</h1>
            <p className="text-theme-muted mb-2">We were unable to verify your identity.</p>
            {failureReason && (
              <p className="text-red-600 dark:text-red-400 text-sm mb-4">{failureReason}</p>
            )}
            <p className="text-theme-subtle text-sm mb-6">
              Please ensure your photo ID is clear, well-lit, and that your selfie matches the document photo.
            </p>
            <div className="flex flex-col gap-3">
              <Button
                onPress={handleStartVerification}
                isLoading={isStarting}
                className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={!isStarting ? <RefreshCw className="w-4 h-4" /> : undefined}
              >
                Try Again
              </Button>
              <Button
                variant="flat"
                className="w-full"
                startContent={<ArrowLeft className="w-4 h-4" />}
                onPress={() => navigate(tenantPath('/dashboard'))}
              >
                Back to Dashboard
              </Button>
            </div>
          </GlassCard>
        </motion.div>
      </div>
    );
  }

  // Error
  if (pageState === 'error') {
    return (
      <div className="min-h-[60vh] flex items-center justify-center p-4">
        <PageMeta title="Verify Identity" noIndex />
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md">
          <GlassCard className="p-8 text-center">
            <div className="w-16 h-16 mx-auto mb-6 rounded-full bg-red-500/20 flex items-center justify-center">
              <ShieldX className="w-8 h-8 text-red-400" />
            </div>
            <h1 className="text-2xl font-bold text-theme-primary mb-2">Something went wrong</h1>
            <p className="text-theme-muted mb-6">{errorMessage}</p>
            <Button
              onPress={() => { setPageState('loading'); fetchStatus(); }}
              className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" />}
            >
              Try Again
            </Button>
          </GlassCard>
        </motion.div>
      </div>
    );
  }

  // Start / In-progress
  return (
    <div className="min-h-[60vh] flex items-center justify-center p-4">
      <PageMeta title="Verify Identity" noIndex />
      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md">
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
                <Fingerprint className="w-8 h-8 text-purple-500 dark:text-purple-400" />
              )}
            </motion.div>
            <h1 className="text-xl sm:text-2xl font-bold text-theme-primary">
              {pageState === 'in_progress' ? 'Verification in progress' : 'Verify your identity'}
            </h1>
            <p className="text-theme-muted mt-2">
              {pageState === 'in_progress'
                ? 'Please complete the verification in the opened window. This page will update automatically.'
                : 'Verify your identity to earn the ID Verified badge on your profile. This helps build trust in the community.'}
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
              {/* What you get */}
              <div className="p-4 rounded-xl bg-purple-500/5 border border-purple-500/10">
                <h3 className="font-medium text-theme-primary text-sm mb-2">What you get</h3>
                <div className="flex items-center gap-2 mb-2">
                  <div className="w-8 h-8 rounded-full bg-purple-500/10 flex items-center justify-center">
                    <Fingerprint className="w-4 h-4 text-purple-500" />
                  </div>
                  <div>
                    <p className="text-sm font-medium text-purple-600 dark:text-purple-400">ID Verified Badge</p>
                    <p className="text-xs text-theme-muted">Visible on your profile to other members</p>
                  </div>
                </div>
              </div>

              {/* What you need */}
              <div className="p-4 rounded-xl bg-indigo-500/5 border border-indigo-500/10">
                <h3 className="font-medium text-theme-primary text-sm mb-2">What you'll need</h3>
                <ul className="text-sm text-theme-muted space-y-1">
                  <li>• A valid government-issued photo ID</li>
                  <li>• A device with a camera</li>
                  <li>• About 2–5 minutes</li>
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
                Start Verification
              </Button>

              <Button
                variant="flat"
                className="w-full"
                startContent={<ArrowLeft className="w-4 h-4" />}
                onPress={() => navigate(tenantPath('/dashboard'))}
              >
                Maybe Later
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
                  Open Verification Window
                </Button>
              )}

              <div className="flex items-center justify-center gap-2 text-sm text-theme-muted">
                <Loader2 className="w-4 h-4 animate-spin" />
                Waiting for verification result...
              </div>

              <div className="flex flex-col gap-2 pt-2">
                <Button
                  variant="flat"
                  size="sm"
                  className="w-full text-theme-muted"
                  onPress={() => { stopPolling(); setPageState('start'); setRedirectUrl(null); userStartedRef.current = false; }}
                >
                  Cancel & Start Over
                </Button>
                <Button
                  variant="light"
                  size="sm"
                  className="w-full text-theme-subtle"
                  startContent={<ArrowLeft className="w-3.5 h-3.5" />}
                  onPress={() => navigate(tenantPath('/dashboard'))}
                >
                  Back to Dashboard
                </Button>
              </div>
            </div>
          )}
        </GlassCard>
      </motion.div>
    </div>
  );
}

export default VerifyIdentityOptionalPage;
