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
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useTenant, useAuth } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';

type VerifyState = 'loading' | 'success' | 'error';

export function VerifyEmailPage() {
  const { t } = useTranslation('auth');
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
      setErrorMessage(t('verify_email.no_token_error'));
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
          setErrorMessage(t('verify_email.expired_error'));
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
      setErrorMessage(t('verify_email.resend_error'));
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
            <h1 className="text-2xl font-bold text-theme-primary mb-2">{t('verify_email.loading_title')}</h1>
            <p className="text-theme-muted">
              {t('verify_email.loading_subtitle')}
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
            <h1 className="text-2xl font-bold text-theme-primary mb-2">{t('verify_email.success_title')}</h1>
            <p className="text-theme-muted mb-6">
              {t('verify_email.success_subtitle')}
            </p>
            {isAuthenticated ? (
              <Link to={tenantPath('/dashboard')}>
                <Button className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                  {t('verify_email.go_to_dashboard')}
                </Button>
              </Link>
            ) : (
              <Link to={tenantPath('/login')}>
                <Button className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                  {t('verify_email.go_to_login')}
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
          <h1 className="text-2xl font-bold text-theme-primary mb-2">{t('verify_email.error_title')}</h1>
          <p className="text-theme-muted mb-6">
            {errorMessage}
          </p>

          <div className="flex flex-col gap-3">
            {/* Resend verification — only available when authenticated */}
            {isAuthenticated ? (
              resendSuccess ? (
                <div className="p-3 rounded-lg bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 text-sm">
                  {t('verify_email.resend_success')}
                </div>
              ) : (
                <Button
                  onPress={handleResendVerification}
                  isLoading={isResending}
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  startContent={!isResending ? <Mail className="w-4 h-4" /> : undefined}
                >
                  {t('verify_email.resend')}
                </Button>
              )
            ) : (
              <p className="text-theme-subtle text-sm">
                {t('verify_email.login_required')}
              </p>
            )}

            <Link to={isAuthenticated ? tenantPath('/dashboard') : tenantPath('/login')}>
              <Button
                variant="flat"
                className="w-full bg-theme-elevated text-theme-primary"
                startContent={<ArrowLeft className="w-4 h-4" />}
              >
                {isAuthenticated ? t('verify_email.back_to_dashboard') : t('verify_email.back_to_login')}
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
