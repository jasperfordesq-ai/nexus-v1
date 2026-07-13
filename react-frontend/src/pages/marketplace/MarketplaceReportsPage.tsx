// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import ChevronRight from 'lucide-react/icons/chevron-right';
import FileWarning from 'lucide-react/icons/file-warning';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { Chip } from '@/components/ui/Chip';
import { Spinner } from '@/components/ui/Spinner';
import { Breadcrumbs } from '@/components/navigation/Breadcrumbs';
import { EmptyState } from '@/components/feedback';
import { PageMeta } from '@/components/seo/PageMeta';
import { useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { getFormattingLocale } from '@/lib/helpers';
import type { MarketplaceReportCase } from '@/types/marketplaceCases';
import { normalizeMarketplaceCasePage } from '@/types/marketplaceCases';

const STATUS_COLORS: Record<string, 'default' | 'success' | 'warning' | 'danger' | 'primary'> = {
  received: 'warning',
  acknowledged: 'primary',
  under_review: 'primary',
  action_taken: 'danger',
  no_action: 'success',
  appealed: 'warning',
  appeal_resolved: 'success',
};

export function MarketplaceReportsPage() {
  const { t } = useTranslation('marketplace_cases');
  const { tenantPath } = useTenant();
  const toast = useToast();
  usePageTitle(t('report.index_page_title'));
  const [reports, setReports] = useState<MarketplaceReportCase[]>([]);
  const [loading, setLoading] = useState(true);

  const loadReports = useCallback(async () => {
    setLoading(true);
    try {
      const response = await api.get<unknown>('/v2/marketplace/reports');
      if (!response.success) {
        toast.error(response.error || t('report.load_error'));
        return;
      }
      setReports(normalizeMarketplaceCasePage<MarketplaceReportCase>(response.data).items);
    } catch {
      toast.error(t('report.load_error'));
    } finally {
      setLoading(false);
    }
  }, [t, toast]);

  useEffect(() => { void loadReports(); }, [loadReports]);

  return (
    <>
      <PageMeta title={t('report.index_page_title')} noIndex />
      <div className="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6">
        <Breadcrumbs items={[
          { label: t('report.marketplace'), href: '/marketplace' },
          { label: t('report.index_title') },
        ]} />
        <div>
          <h1 className="text-3xl font-bold text-foreground">{t('report.index_title')}</h1>
          <p className="mt-2 text-muted">{t('report.index_subtitle')}</p>
        </div>

        {loading ? (
          <div className="flex min-h-48 items-center justify-center" role="status" aria-busy="true" aria-label={t('common.loading')}>
            <Spinner label={t('common.loading')} />
          </div>
        ) : reports.length === 0 ? (
          <EmptyState
            icon={<FileWarning className="size-8" aria-hidden="true" />}
            title={t('report.index_empty')}
            description={t('report.index_empty_description')}
          />
        ) : (
          <div className="space-y-3">
            {reports.map((report) => (
              <Link key={report.id} to={tenantPath(`/marketplace/reports/${report.id}`)} className="block rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                <Card className="border border-divider/70 bg-surface transition-colors hover:border-accent/40">
                  <CardBody className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="font-semibold text-foreground">{t('report.reference', { id: report.id })}</span>
                        <Chip size="sm" color={STATUS_COLORS[report.status] || 'default'} variant="soft">
                          {t(`status.${report.status}`, { defaultValue: report.status })}
                        </Chip>
                        {report.viewer_role && (
                          <Chip size="sm" variant="soft">{t(`report.role_${report.viewer_role}`)}</Chip>
                        )}
                      </div>
                      <p className="mt-2 truncate text-sm font-medium text-foreground">
                        {report.listing?.title || `#${report.marketplace_listing_id}`}
                      </p>
                      {report.created_at && (
                        <p className="mt-1 text-xs text-muted">{new Date(report.created_at).toLocaleString(getFormattingLocale())}</p>
                      )}
                    </div>
                    <span className="inline-flex items-center gap-1 text-sm font-medium text-accent">
                      {t('report.view_report')}
                      <ChevronRight className="size-4" aria-hidden="true" />
                    </span>
                  </CardBody>
                </Card>
              </Link>
            ))}
          </div>
        )}

        <Button as={Link} to={tenantPath('/marketplace')} variant="tertiary">
          <ArrowLeft className="size-4" aria-hidden="true" />
          {t('report.back_to_marketplace')}
        </Button>
      </div>
    </>
  );
}

export default MarketplaceReportsPage;
