// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { Button, Textarea, Input, Radio, RadioGroup } from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import CheckCircle from 'lucide-react/icons/circle-check';
import Heart from 'lucide-react/icons/heart';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';

type ContactPreference = 'phone' | 'message' | 'either';

export function RequestHelpPage() {
  const { t } = useTranslation('common');
  const { hasFeature, tenantPath } = useTenant();
  usePageTitle(t('request_help.meta.title'));

  const [what, setWhat] = useState('');
  const [when, setWhen] = useState('');
  const [contactPref, setContactPref] = useState<ContactPreference>('either');
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Redirect if feature is off
  if (!hasFeature('caring_community')) {
    return <Navigate to={tenantPath('/caring-community')} replace />;
  }

  const charCount = what.length;
  const charLimit = 500;

  const handleSubmit = async () => {
    setError(null);
    if (!what.trim() || !when.trim()) return;

    setSubmitting(true);
    try {
      await api.post('/v2/caring-community/request-help', {
        what: what.trim(),
        when: when.trim(),
        contact_preference: contactPref,
      });
      setSubmitted(true);
    } catch {
      setError(t('request_help.errors.submit_failed'));
    } finally {
      setSubmitting(false);
    }
  };

  if (submitted) {
    return (
      <>
        <PageMeta
          title={t('request_help.meta.title')}
          description={t('request_help.meta.description')}
          noIndex
        />
        <div className="mx-auto max-w-xl">
          <GlassCard className="p-8 text-center">
            <div className="mb-4 flex justify-center">
              <div className="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/15">
                <CheckCircle className="h-8 w-8 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
              </div>
            </div>
            <h1 className="text-2xl font-bold text-theme-primary">{t('request_help.success.title')}</h1>
            <p className="mt-3 text-base leading-7 text-theme-muted">{t('request_help.success.body')}</p>
            <div className="mt-6">
              <Button
                as={Link}
                to={tenantPath('/caring-community')}
                color="primary"
                variant="flat"
                startContent={<ArrowLeft className="h-4 w-4" />}
              >
                {t('request_help.success.back')}
              </Button>
            </div>
          </GlassCard>
        </div>
      </>
    );
  }

  return (
    <>
      <PageMeta
        title={t('request_help.meta.title')}
        description={t('request_help.meta.description')}
        noIndex
      />

      <div className="mx-auto max-w-xl space-y-4">
        <div>
          <Link
            to={tenantPath('/caring-community')}
            className="inline-flex items-center gap-1 text-sm text-theme-muted hover:text-theme-primary"
          >
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
            {t('request_help.back')}
          </Link>
        </div>

        <GlassCard className="p-6 sm:p-8">
          <div className="mb-6 flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-rose-500/15">
              <Heart className="h-5 w-5 text-rose-600 dark:text-rose-400" aria-hidden="true" />
            </div>
            <div>
              <h1 className="text-xl font-bold text-theme-primary">{t('request_help.meta.title')}</h1>
              <p className="text-sm text-theme-muted">{t('request_help.meta.description')}</p>
            </div>
          </div>

          <div className="space-y-5">
            <div>
              <Textarea
                label={t('request_help.form.what_label')}
                placeholder={t('request_help.form.what_placeholder')}
                value={what}
                onValueChange={setWhat}
                minRows={3}
                maxRows={6}
                variant="bordered"
                isRequired
                description={`${charCount} / ${charLimit}`}
                isInvalid={charCount > charLimit}
              />
            </div>

            <div>
              <Input
                label={t('request_help.form.when_label')}
                placeholder={t('request_help.form.when_placeholder')}
                value={when}
                onValueChange={setWhen}
                variant="bordered"
                isRequired
              />
            </div>

            <RadioGroup
              label={t('request_help.form.contact_label')}
              value={contactPref}
              onValueChange={(v) => setContactPref(v as ContactPreference)}
            >
              <Radio value="phone">{t('request_help.form.contact_phone')}</Radio>
              <Radio value="message">{t('request_help.form.contact_message')}</Radio>
              <Radio value="either">{t('request_help.form.contact_either')}</Radio>
            </RadioGroup>

            {error && (
              <p className="text-sm text-danger">{error}</p>
            )}

            <Button
              color="primary"
              size="lg"
              className="w-full"
              isLoading={submitting}
              isDisabled={!what.trim() || !when.trim() || charCount > charLimit}
              onPress={handleSubmit}
            >
              {submitting ? t('request_help.form.submitting') : t('request_help.form.submit')}
            </Button>
          </div>
        </GlassCard>
      </div>
    </>
  );
}

export default RequestHelpPage;
