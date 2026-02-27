// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Contact Page
 *
 * Uses V2 API: POST /api/v2/contact
 */

import { useState, type FormEvent } from 'react';
import { motion } from 'framer-motion';
import { Button, Input, Textarea, Select, SelectItem } from '@heroui/react';
import { Mail, MessageSquare, Loader2, ArrowLeft } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useTenant, useAuth } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { GlassCard } from '@/components/ui';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

export function ContactPage() {
  const { t } = useTranslation('public');
  const { branding, tenantPath } = useTenant();
  const { user, isAuthenticated } = useAuth();
  usePageTitle('Contact Us');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [formData, setFormData] = useState({
    name: user?.name || `${user?.first_name || ''} ${user?.last_name || ''}`.trim() || '',
    email: user?.email || '',
    subject: '',
    message: '',
  });

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setError(null);

    try {
      const response = await api.post('/v2/contact', {
        name: formData.name,
        email: formData.email,
        subject: formData.subject || 'General Inquiry',
        message: formData.message,
      });

      if (response.success) {
        setSubmitted(true);
      } else {
        setError(response.error || t('contact.error_fallback'));
      }
    } catch (err) {
      logError('Failed to submit contact form', err);
      setError(t('contact.error_fallback'));
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <GlassCard className="p-5 sm:p-8">
          <div className="text-center mb-8">
            <div className="inline-flex items-center justify-center w-12 h-12 sm:w-16 sm:h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-4">
              <MessageSquare className="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
            </div>
            <h1 className="text-2xl font-bold text-theme-primary">{t('contact.title')}</h1>
            <p className="text-theme-muted mt-2">
              {t('contact.subtitle', { name: branding.name })}
            </p>
          </div>

          {submitted ? (
            <div className="text-center py-8">
              <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-500/20 mb-4">
                <Mail className="w-8 h-8 text-green-400" aria-hidden="true" />
              </div>
              <h2 className="text-xl font-semibold text-theme-primary mb-2">{t('contact.success_title')}</h2>
              <p className="text-theme-muted mb-4">
                {t('contact.success_message')}
              </p>
              <Link to={tenantPath('/help')}>
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-muted"
                  startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('contact.back_to_help')}
                </Button>
              </Link>
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="space-y-5">
              {error && (
                <div className="p-3 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 text-sm">
                  {error}
                </div>
              )}

              <Input
                label={t('contact.form.name_label')}
                placeholder={t('contact.form.name_placeholder')}
                isRequired
                value={formData.name}
                onChange={(e) => setFormData((prev) => ({ ...prev, name: e.target.value }))}
                classNames={{
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle',
                }}
              />

              <Input
                type="email"
                label={t('contact.form.email_label')}
                placeholder={t('contact.form.email_placeholder')}
                isRequired
                value={formData.email}
                onChange={(e) => setFormData((prev) => ({ ...prev, email: e.target.value }))}
                classNames={{
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle',
                }}
              />

              <Select
                label={t('contact.form.subject_label')}
                placeholder={t('contact.form.subject_placeholder')}
                selectedKeys={formData.subject ? [formData.subject] : []}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0] as string;
                  setFormData((prev) => ({ ...prev, subject: selected || '' }));
                }}
                classNames={{
                  trigger: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                  value: 'text-theme-primary',
                }}
              >
                <SelectItem key="general">{t('contact.form.subjects.general')}</SelectItem>
                <SelectItem key="account">{t('contact.form.subjects.account')}</SelectItem>
                <SelectItem key="technical">{t('contact.form.subjects.technical')}</SelectItem>
                <SelectItem key="feedback">{t('contact.form.subjects.feedback')}</SelectItem>
                <SelectItem key="other">{t('contact.form.subjects.other')}</SelectItem>
              </Select>

              <Textarea
                label={t('contact.form.message_label')}
                placeholder={t('contact.form.message_placeholder')}
                minRows={4}
                isRequired
                value={formData.message}
                onChange={(e) => setFormData((prev) => ({ ...prev, message: e.target.value }))}
                classNames={{
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                  input: 'text-theme-primary placeholder:text-theme-subtle',
                }}
              />

              <Button
                type="submit"
                isLoading={isSubmitting}
                isDisabled={!formData.name.trim() || !formData.email.trim() || !formData.message.trim()}
                className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium"
                size="lg"
                spinner={<Loader2 className="w-4 h-4 animate-spin" />}
              >
                {t('contact.form.submit')}
              </Button>

              {!isAuthenticated && (
                <p className="text-xs text-theme-subtle text-center">
                  {t('contact.form.login_prompt_before')}{' '}
                  <Link to={tenantPath('/login')} className="text-indigo-500 hover:underline">
                    {t('contact.form.login_link')}
                  </Link>{' '}
                  {t('contact.form.login_prompt_after')}
                </p>
              )}
            </form>
          )}
        </GlassCard>
      </motion.div>
    </div>
  );
}

export default ContactPage;
