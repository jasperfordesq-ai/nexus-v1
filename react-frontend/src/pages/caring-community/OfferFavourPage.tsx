// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { Button, Select, SelectItem, Textarea, Input } from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import CheckCircle from 'lucide-react/icons/circle-check';
import Heart from 'lucide-react/icons/heart';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';

const CATEGORIES = [
  'companionship',
  'shopping',
  'transport',
  'home_help',
  'gardening',
  'meals',
  'other',
] as const;

export function OfferFavourPage() {
  const { t } = useTranslation('common');
  const { hasFeature, tenantPath } = useTenant();
  usePageTitle(t('offer_favour.meta.title'));

  const [description, setDescription] = useState('');
  const [category, setCategory] = useState('');
  const [recipientName, setRecipientName] = useState('');
  const [favourDate, setFavourDate] = useState(() => new Date().toISOString().split('T')[0]);
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!hasFeature('caring_community')) {
    return <Navigate to={tenantPath('/caring-community')} replace />;
  }

  const charCount = description.length;
  const charLimit = 500;

  const handleSubmit = async () => {
    setError(null);
    if (!description.trim()) return;

    setSubmitting(true);
    try {
      await api.post('/v2/caring-community/offer-favour', {
        description: description.trim(),
        category: category || undefined,
        received_by_name: recipientName.trim() || undefined,
        favour_date: favourDate,
        is_anonymous: !recipientName.trim(),
      });
      setSubmitted(true);
    } catch {
      setError(t('offer_favour.errors.submit_failed'));
    } finally {
      setSubmitting(false);
    }
  };

  if (submitted) {
    return (
      <>
        <PageMeta
          title={t('offer_favour.meta.title')}
          description={t('offer_favour.meta.description')}
          noIndex
        />
        <div className="mx-auto max-w-xl">
          <GlassCard className="p-8 text-center">
            <div className="mb-5 flex justify-center">
              <div className="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/15">
                <CheckCircle className="h-9 w-9 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
              </div>
            </div>
            <h1 className="text-2xl font-bold text-theme-primary">{t('offer_favour.success.title')}</h1>
            <p className="mt-4 text-base leading-8 text-theme-muted">{t('offer_favour.success.body')}</p>
            <div className="mt-7">
              <Button
                as={Link}
                to={tenantPath('/caring-community')}
                color="primary"
                variant="flat"
                size="lg"
                startContent={<ArrowLeft className="h-4 w-4" />}
              >
                {t('offer_favour.success.back')}
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
        title={t('offer_favour.meta.title')}
        description={t('offer_favour.meta.description')}
        noIndex
      />

      <div className="mx-auto max-w-xl space-y-5">
        <div>
          <Link
            to={tenantPath('/caring-community')}
            className="inline-flex items-center gap-1.5 text-sm text-theme-muted hover:text-theme-primary"
          >
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
            {t('offer_favour.back')}
          </Link>
        </div>

        <GlassCard className="p-6 sm:p-8">
          {/* Page header */}
          <div className="mb-7 flex items-center gap-4">
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-rose-500/15">
              <Heart className="h-6 w-6 text-rose-600 dark:text-rose-400" aria-hidden="true" />
            </div>
            <div>
              <h1 className="text-2xl font-bold leading-tight text-theme-primary">
                {t('offer_favour.meta.title')}
              </h1>
              <p className="mt-1 text-sm leading-6 text-theme-muted">
                {t('offer_favour.subtitle')}
              </p>
            </div>
          </div>

          <div className="space-y-6">
            {/* Description */}
            <Textarea
              label={t('offer_favour.form.what_label')}
              placeholder={t('offer_favour.form.what_placeholder')}
              value={description}
              onValueChange={setDescription}
              minRows={3}
              maxRows={7}
              variant="bordered"
              isRequired
              description={`${charCount} / ${charLimit}`}
              isInvalid={charCount > charLimit}
              errorMessage={charCount > charLimit ? t('offer_favour.form.what_too_long') : undefined}
              classNames={{ label: 'text-base font-medium' }}
            />

            {/* Category */}
            <Select
              label={t('offer_favour.form.category_label')}
              selectedKeys={category ? new Set([category]) : new Set()}
              onSelectionChange={(keys) => setCategory([...keys][0] as string ?? '')}
              variant="bordered"
              classNames={{ label: 'text-base font-medium' }}
            >
              {CATEGORIES.map((cat) => (
                <SelectItem key={cat}>
                  {t(`offer_favour.form.categories.${cat}`)}
                </SelectItem>
              ))}
            </Select>

            {/* Recipient (optional) */}
            <Input
              label={t('offer_favour.form.recipient_label')}
              placeholder={t('offer_favour.form.recipient_placeholder')}
              value={recipientName}
              onValueChange={setRecipientName}
              variant="bordered"
              classNames={{ label: 'text-base font-medium' }}
            />

            {/* Date */}
            <Input
              type="date"
              label={t('offer_favour.form.date_label')}
              value={favourDate}
              onValueChange={setFavourDate}
              variant="bordered"
              classNames={{ label: 'text-base font-medium' }}
            />

            {error && (
              <p className="rounded-lg bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">{error}</p>
            )}

            <Button
              color="primary"
              size="lg"
              className="w-full text-base"
              isLoading={submitting}
              isDisabled={!description.trim() || charCount > charLimit}
              onPress={handleSubmit}
            >
              {submitting ? t('offer_favour.form.submitting') : t('offer_favour.form.submit')}
            </Button>
          </div>
        </GlassCard>
      </div>
    </>
  );
}

export default OfferFavourPage;
