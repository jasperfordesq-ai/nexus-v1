// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Forgot Password Page - Request password reset
 */

import { useState } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input } from '@heroui/react';
import { Mail, ArrowLeft, CheckCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';

export function ForgotPasswordPage() {
  const { t } = useTranslation('auth');
  usePageTitle('Reset Password');
  const { branding, tenantPath } = useTenant();
  const [email, setEmail] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [isSubmitted, setIsSubmitted] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!email.trim()) return;

    try {
      setIsLoading(true);
      await api.post('/auth/forgot-password', { email });
      setIsSubmitted(true);
    } catch {
      // Don't reveal if email exists or not for security
      setIsSubmitted(true);
    } finally {
      setIsLoading(false);
    }
  }

  if (isSubmitted) {
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
            <h1 className="text-2xl font-bold text-theme-primary mb-2">{t('forgot_password.success_title')}</h1>
            <p className="text-theme-muted mb-6">
              {t('forgot_password.success_subtitle')}
            </p>
            <p className="text-theme-subtle text-sm mb-6">
              {t('forgot_password.help_text')}
            </p>
            <div className="flex flex-col gap-3">
              <Button
                onPress={() => setIsSubmitted(false)}
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
              >
                {t('forgot_password.try_again')}
              </Button>
              <Link to={tenantPath('/login')}>
                <Button
                  variant="flat"
                  className="w-full bg-theme-elevated text-theme-primary"
                  startContent={<ArrowLeft className="w-4 h-4" />}
                >
                  {t('forgot_password.back_to_login')}
                </Button>
              </Link>
            </div>
          </GlassCard>
        </motion.div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center p-4">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="w-full max-w-md"
      >
        {/* Back to login */}
        <Link
          to={tenantPath('/login')}
          className="flex items-center gap-2 text-theme-muted hover:text-theme-primary transition-colors mb-6"
        >
          <ArrowLeft className="w-4 h-4" />
          {t('forgot_password.back_to_login')}
        </Link>

        <GlassCard className="p-8">
          {/* Header */}
          <div className="text-center mb-8">
            <h1 className="text-2xl font-bold text-theme-primary mb-2">{t('forgot_password.page_title')}</h1>
            <p className="text-theme-muted">
              {t('forgot_password.page_subtitle')}
            </p>
          </div>

          {/* Form */}
          <form onSubmit={handleSubmit} className="space-y-6">
            <Input
              type="email"
              label={t('forgot_password.email_address_label')}
              placeholder={t('forgot_password.email_placeholder')}
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              startContent={<Mail className="w-4 h-4 text-theme-subtle" />}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
                label: 'text-theme-muted',
              }}
              isRequired
            />

            <Button
              type="submit"
              className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              isLoading={isLoading}
              isDisabled={!email.trim()}
            >
              {t('forgot_password.send_button')}
            </Button>
          </form>

          {/* Footer */}
          <div className="mt-6 text-center text-sm text-theme-subtle">
            {t('forgot_password.remember_password')}{' '}
            <Link to={tenantPath('/login')} className="text-indigo-600 dark:text-indigo-400 hover:underline">
              {t('forgot_password.sign_in_link')}
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

export default ForgotPasswordPage;
