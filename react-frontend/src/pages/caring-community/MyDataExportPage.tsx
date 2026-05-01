// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { Button } from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Download from 'lucide-react/icons/download';
import ShieldCheck from 'lucide-react/icons/shield-check';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { useTenant } from '@/contexts';
import { useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';

/**
 * E3 — Member-side GDPR / FADP data export page.
 *
 * Streams a JSON download of every record the member has a stake in across
 * the caring-community module: profile, hours logged, support relationships,
 * help requests, hour gifts, hour transfers, loyalty redemptions, regional
 * points, safeguarding reports they filed, and civic-digest preferences.
 */
export default function MyDataExportPage(): JSX.Element {
  const { t } = useTranslation('caring_community');
  const { hasFeature, tenantPath } = useTenant();
  const { showToast } = useToast();
  usePageTitle(t('data_export.title'));

  const [downloading, setDownloading] = useState<boolean>(false);

  if (!hasFeature('caring_community')) {
    return <Navigate to={tenantPath('/dashboard')} replace />;
  }

  const handleDownload = async (): Promise<void> => {
    setDownloading(true);
    try {
      const today = new Date().toISOString().slice(0, 10);
      await api.download('/v2/caring-community/me/data-export', {
        method: 'GET',
        filename: `my-caring-community-data-${today}.json`,
      });
      showToast(t('data_export.success_toast'), 'success');
    } catch {
      showToast(t('data_export.error_toast'), 'error');
    } finally {
      setDownloading(false);
    }
  };

  return (
    <>
      <PageMeta title={t('data_export.title')} description={t('data_export.intro')} />
      <div className="container mx-auto max-w-3xl px-4 py-6">
        <div className="mb-4">
          <Button
            as={Link}
            to={tenantPath('/caring-community')}
            variant="light"
            size="sm"
            startContent={<ArrowLeft size={16} />}
          >
            {t('data_export.back', { defaultValue: 'Back' })}
          </Button>
        </div>

        <GlassCard className="space-y-5 p-6">
          <header className="flex items-center gap-3">
            <ShieldCheck className="text-success" size={28} aria-hidden="true" />
            <h1 className="text-2xl font-semibold">{t('data_export.title')}</h1>
          </header>

          <p className="text-sm text-default-600">{t('data_export.intro')}</p>

          <div className="rounded-lg border border-default-200 bg-default-50 p-4 text-xs text-default-600 dark:bg-default-100/30">
            {t('data_export.privacy_note')}
          </div>

          <Button
            color="primary"
            size="lg"
            startContent={<Download size={18} />}
            onPress={() => {
              void handleDownload();
            }}
            isLoading={downloading}
            isDisabled={downloading}
          >
            {downloading ? t('data_export.downloading') : t('data_export.download_button')}
          </Button>

          <p className="text-xs text-default-500">{t('data_export.format_note')}</p>
        </GlassCard>
      </div>
    </>
  );
}
