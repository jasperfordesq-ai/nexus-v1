// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import ExternalLink from 'lucide-react/icons/external-link';
import FileWarning from 'lucide-react/icons/file-warning';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import { Button } from '@/components/ui/Button';
import { Card, CardBody, CardHeader } from '@/components/ui/Card';
import { Chip } from '@/components/ui/Chip';
import { Spinner } from '@/components/ui/Spinner';
import { Textarea } from '@/components/ui/Textarea';
import { Breadcrumbs } from '@/components/navigation/Breadcrumbs';
import { PageMeta } from '@/components/seo/PageMeta';
import { useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { getFormattingLocale } from '@/lib/helpers';
import type { MarketplaceReportCase } from '@/types/marketplaceCases';

const STATUS_COLORS: Record<string, 'default' | 'success' | 'warning' | 'danger' | 'accent'> = {
  received: 'warning',
  acknowledged: 'accent',
  under_review: 'accent',
  action_taken: 'danger',
  no_action: 'success',
  appealed: 'warning',
  appeal_resolved: 'success',
};

export function MarketplaceReportPage() {
  const { id } = useParams<{ id: string }>();
  const { t } = useTranslation('marketplace_cases');
  const { tenantPath } = useTenant();
  const toast = useToast();
  usePageTitle(t('report.page_title'));

  const [report, setReport] = useState<MarketplaceReportCase | null>(null);
  const [loading, setLoading] = useState(true);
  const [appealText, setAppealText] = useState('');
  const [appealing, setAppealing] = useState(false);

  const loadReport = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    try {
      const response = await api.get<MarketplaceReportCase>(`/v2/marketplace/reports/${id}`);
      if (response.success && response.data) {
        setReport(response.data);
      } else {
        setReport(null);
        toast.error(response.error || t('report.load_error'));
      }
    } catch {
      setReport(null);
      toast.error(t('report.load_error'));
    } finally {
      setLoading(false);
    }
  }, [id, t, toast]);

  useEffect(() => {
    void loadReport();
  }, [loadReport]);

  const canAppeal = Boolean(
    report && (report.can_appeal ?? ['action_taken', 'no_action'].includes(report.status)),
  );

  const submitAppeal = async () => {
    if (!report || appealText.trim().length < 20) return;
    setAppealing(true);
    try {
      const response = await api.post<{ id: number; status: string; message?: string }>(
        `/v2/marketplace/reports/${report.id}/appeal`,
        { appeal_text: appealText.trim() },
      );
      if (!response.success) {
        toast.error(response.error || t('report.appeal_error'));
        return;
      }
      toast.success(t('report.appeal_success'));
      setAppealText('');
      await loadReport();
    } catch {
      toast.error(t('report.appeal_error'));
    } finally {
      setAppealing(false);
    }
  };

  if (loading) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center" role="status" aria-busy="true" aria-label={t('common.loading')}>
        <Spinner label={t('common.loading')} />
      </div>
    );
  }

  if (!report) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10 text-center">
        <FileWarning className="mx-auto mb-4 size-10 text-muted" aria-hidden="true" />
        <h1 className="text-2xl font-semibold text-foreground">{t('report.not_found')}</h1>
        <Button className="mt-6" variant="secondary" onPress={() => void loadReport()}>
          <RefreshCw className="size-4" aria-hidden="true" />
          {t('common.retry')}
        </Button>
      </div>
    );
  }

  const createdAt = report.created_at
    ? new Date(report.created_at).toLocaleString(getFormattingLocale())
    : t('common.not_available');

  return (
    <>
      <PageMeta title={t('report.page_title')} noIndex />
      <div className="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6">
        <Breadcrumbs items={[
          { label: t('report.marketplace'), href: '/marketplace' },
          { label: t('report.title') },
        ]} />

        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <p className="text-sm font-medium text-accent">{t('report.reference', { id: report.id })}</p>
            <h1 className="mt-1 text-3xl font-bold text-foreground">
              {t(report.viewer_role === 'seller' ? 'report.seller_title' : 'report.title')}
            </h1>
            <p className="mt-2 text-muted">
              {t(report.viewer_role === 'seller' ? 'report.seller_subtitle' : 'report.subtitle')}
            </p>
          </div>
          <Chip color={STATUS_COLORS[report.status] || 'default'} variant="soft">
            {t(`status.${report.status}`, { defaultValue: report.status })}
          </Chip>
        </div>

        <Card className="border border-divider/70 bg-surface">
          <CardHeader className="flex items-center justify-between gap-3">
            <h2 className="text-lg font-semibold text-foreground">{t('report.details_title')}</h2>
            <span className="text-sm text-muted">{createdAt}</span>
          </CardHeader>
          <CardBody className="space-y-5">
            {report.listing && (
              <div>
                <p className="text-sm font-medium text-muted">{t('common.listing')}</p>
                <Link className="mt-1 inline-flex items-center gap-1 font-medium text-accent hover:underline" to={tenantPath(`/marketplace/${report.listing.id}`)}>
                  {report.listing.title}
                  <ExternalLink className="size-4" aria-hidden="true" />
                </Link>
              </div>
            )}
            <div>
              <p className="text-sm font-medium text-muted">{t('common.reason')}</p>
              <p className="mt-1 text-foreground">{t(`reason.${report.reason}`, { defaultValue: report.reason })}</p>
            </div>
            {report.description && (
              <div>
                <p className="text-sm font-medium text-muted">{t('common.description')}</p>
                <p className="mt-1 whitespace-pre-wrap text-foreground">{report.description}</p>
              </div>
            )}
            {report.action_taken && (
              <div>
                <p className="text-sm font-medium text-muted">{t('common.action')}</p>
                <p className="mt-1 text-foreground">{t(`action.${report.action_taken}`, { defaultValue: report.action_taken })}</p>
              </div>
            )}
            {report.resolution_reason && (
              <div>
                <p className="text-sm font-medium text-muted">{t('common.resolution')}</p>
                <p className="mt-1 whitespace-pre-wrap text-foreground">{report.resolution_reason}</p>
              </div>
            )}
            {report.appeal_text && (
              <div>
                <p className="text-sm font-medium text-muted">{t('report.appeal_submitted')}</p>
                <p className="mt-1 whitespace-pre-wrap text-foreground">{report.appeal_text}</p>
              </div>
            )}
          </CardBody>
        </Card>

        {canAppeal && (
          <Card className="border border-warning/40 bg-warning/5">
            <CardHeader>
              <div>
                <h2 className="text-lg font-semibold text-foreground">{t('report.appeal_title')}</h2>
                <p className="mt-1 text-sm text-muted">{t('report.appeal_description')}</p>
              </div>
            </CardHeader>
            <CardBody className="space-y-4">
              <Textarea
                label={t('report.appeal_label')}
                placeholder={t('report.appeal_placeholder')}
                value={appealText}
                onValueChange={setAppealText}
                minRows={4}
                maxRows={8}
                isRequired
              />
              <div className="flex justify-end">
                <Button isPending={appealing} isDisabled={appealText.trim().length < 20} onPress={() => void submitAppeal()}>
                  {t('report.appeal_submit')}
                </Button>
              </div>
            </CardBody>
          </Card>
        )}

        {report.status === 'appealed' && (
          <Card className="border border-warning/40 bg-warning/5">
            <CardBody>
              <p className="font-medium text-foreground">{t('report.appeal_pending')}</p>
            </CardBody>
          </Card>
        )}

        <Button as={Link} to={tenantPath('/marketplace')} variant="tertiary">
          <ArrowLeft className="size-4" aria-hidden="true" />
          {t('report.back_to_marketplace')}
        </Button>
      </div>
    </>
  );
}

export default MarketplaceReportPage;
