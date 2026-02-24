// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Verify Email Page - Verifies email address with token from URL
 *
 * The user arrives here via an email verification link containing a token.
 * On mount, the page automatically POSTs the token to the API.
 * Shows loading -> success or error states.
 */

import { useState, useEffect } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button } from '@heroui/react';
import { CheckCircle, XCircle, Loader2, ArrowLeft, Mail } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useTenant, useAuth } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';

type VerifyState = 'loading' | 'success' | 'error';

export function VerifyEmailPage() {
  usePageTitle('Verify Email');
  const { branding, tenantPath } = useTenant();
  const { isAuthenticated } = useAuth();
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token');

  const [state, setState] = useState<VerifyState>('loading');
  const [errorMessage, setErrorMessage] = useState('');
  const [isResending, setIsResending] = useState(false);
  const [resendSuccess, setResendSuccess] = useState(false);

  // Auto-verify on mount when token is present
  useEffect(() => {
    if (!token) {
      setState('error');
      setErrorMessage('No verification token found. Please check your email link.');
      return;
    }

    let cancelled = false;

    async function verifyEmail() {
      try {
        await api.post('/auth/verify-email', { token });
        if (!cancelled) {
          setState('success');
        }
      } catch {
        if (!cancelled) {
          setState('error');
          setErrorMessage('This verification link is invalid or has expired.');
        }
      }
    }

    verifyEmail();

    return () => {
      cancelled = true;
    };
  }, [token]);

  async function handleResendVerification() {
    if (!isAuthenticated) return;

    try {
      setIsResending(true);
      await api.post('/auth/resend-verification');
      setResendSuccess(true);
    } catch {
      setErrorMessage('Failed to resend verification email. Please try again later.');
    } finally {
      setIsResending(false);
    }
  }

  // Loading state
  if (state === 'loading') {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="w-full max-w-md"
        >
          <GlassCard className="p-8 text-center">
            <div className="w-16 h-16 mx-auto mb-6 rounded-full bg-indigo-500/20 flex items-center justify-center">
              <Loader2 className="w-8 h-8 text-indigo-400 animate-spin" />
            </div>
            <h1 className="text-2xl font-bold text-theme-primary mb-2">Verifying your email</h1>
            <p className="text-theme-muted">
              Please wait while we verify your email address...
            </p>
          </GlassCard>

          {/* Branding */}
          <p className="text-center text-theme-subtle text-sm mt-6">
            {branding.name}
          </p>
        </motion.div>
      </div>
    );
  }

  // Success state
  if (state === 'success') {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="w-full max-w-md"
        >
          <GlassCard className="p-8 text-center">
            <div className="w-16 h-16 mx-auto mb-6 rounded-full bg-emerald-500/20 flex items-center justify-center">
              <CheckCircle className="w-8 h-8 text-emerald-400" />
            </div>
            <h1 className="text-2xl font-bold text-theme-primary mb-2">Email verified</h1>
            <p className="text-theme-muted mb-6">
              Your email address has been successfully verified. You're all set!
            </p>
            {isAuthenticated ? (
              <Link to={tenantPath('/dashboard')}>
                <Button className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                  Go to Dashboard
                </Button>
              </Link>
            ) : (
              <Link to={tenantPath('/login')}>
                <Button className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                  Go to Login
                </Button>
              </Link>
            )}
          </GlassCard>

          {/* Branding */}
          <p className="text-center text-theme-subtle text-sm mt-6">
            {branding.name}
          </p>
        </motion.div>
      </div>
    );
  }

  // Error state
  return (
    <div className="min-h-screen flex items-center justify-center p-4">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="w-full max-w-md"
      >
        <GlassCard className="p-8 text-center">
          <div className="w-16 h-16 mx-auto mb-6 rounded-full bg-red-500/20 flex items-center justify-center">
            <XCircle className="w-8 h-8 text-red-400" />
          </div>
          <h1 className="text-2xl font-bold text-theme-primary mb-2">Verification failed</h1>
          <p className="text-theme-muted mb-6">
            {errorMessage}
          </p>

          <div className="flex flex-col gap-3">
            {/* Resend verification — only available when authenticated */}
            {isAuthenticated ? (
              resendSuccess ? (
                <div className="p-3 rounded-lg bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 text-sm">
                  A new verification email has been sent. Please check your inbox.
                </div>
              ) : (
                <Button
                  onPress={handleResendVerification}
                  isLoading={isResending}
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  startContent={!isResending ? <Mail className="w-4 h-4" /> : undefined}
                >
                  Resend verification email
                </Button>
              )
            ) : (
              <p className="text-theme-subtle text-sm">
                Please log in to request a new verification email.
              </p>
            )}

            <Link to={isAuthenticated ? tenantPath('/dashboard') : tenantPath('/login')}>
              <Button
                variant="flat"
                className="w-full bg-theme-elevated text-theme-primary"
                startContent={<ArrowLeft className="w-4 h-4" />}
              >
                {isAuthenticated ? 'Back to Dashboard' : 'Back to Login'}
              </Button>
            </Link>
          </div>
        </GlassCard>

        {/* Branding */}
        <p className="text-center text-theme-subtle text-sm mt-6">
          {branding.name}
        </p>
      </motion.div>
    </div>
  );
}

export default VerifyEmailPage;
