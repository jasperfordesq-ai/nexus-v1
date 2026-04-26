// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button, Card, CardBody, CardHeader, Chip, Divider } from '@heroui/react';
import { Link } from 'react-router-dom';
import BarChart3 from 'lucide-react/icons/chart-column';
import Clock from 'lucide-react/icons/clock';
import Download from 'lucide-react/icons/download';
import FileText from 'lucide-react/icons/file-text';
import Heart from 'lucide-react/icons/heart';
import Users from 'lucide-react/icons/users';
import Building2 from 'lucide-react/icons/building-2';
import ShieldCheck from 'lucide-react/icons/shield-check';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { PageHeader, StatCard } from '../../components';

const reportCards = [
  { key: 'verified_hours', icon: Clock, href: '/admin/reports/hours' },
  { key: 'active_members', icon: Users, href: '/admin/reports/members' },
  { key: 'organisations', icon: Building2, href: '/admin/volunteering/organizations' },
  { key: 'trust_pack', icon: ShieldCheck, href: '/admin/safeguarding' },
] as const;

export default function MunicipalImpactReportsPage() {
  const { t } = useTranslation('admin');
  const { tenantPath } = useTenant();
  usePageTitle(t('municipal_reports.meta.title'));

  return (
    <div className="mx-auto max-w-7xl px-4 pb-8">
      <PageHeader
        title={t('municipal_reports.meta.title')}
        description={t('municipal_reports.meta.description')}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Button
              as={Link}
              to={tenantPath('/admin/community-analytics')}
              variant="flat"
              size="sm"
              startContent={<BarChart3 size={16} />}
            >
              {t('municipal_reports.actions.analytics')}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/impact-report')}
              variant="flat"
              size="sm"
              startContent={<FileText size={16} />}
            >
              {t('municipal_reports.actions.impact_report')}
            </Button>
          </div>
        }
      />

      <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
        <StatCard label={t('municipal_reports.stats.report_pack')} value={t('municipal_reports.stats.ready')} icon={Heart} color="success" />
        <StatCard label={t('municipal_reports.stats.hours_source')} value={t('municipal_reports.stats.verified')} icon={Clock} color="warning" />
        <StatCard label={t('municipal_reports.stats.audience')} value={t('municipal_reports.stats.municipal')} icon={Building2} color="primary" />
        <StatCard label={t('municipal_reports.stats.exports')} value={t('municipal_reports.stats.csv_pdf')} icon={Download} color="secondary" />
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {reportCards.map((report) => {
          const Icon = report.icon;
          return (
            <Card key={report.key} shadow="sm">
              <CardHeader className="flex items-start gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <Icon size={20} />
                </div>
                <div>
                  <h2 className="text-base font-semibold">{t(`municipal_reports.cards.${report.key}.title`)}</h2>
                  <p className="mt-1 text-sm text-default-500">{t(`municipal_reports.cards.${report.key}.description`)}</p>
                </div>
              </CardHeader>
              <Divider />
              <CardBody className="flex flex-col gap-3">
                <div className="flex flex-wrap gap-2">
                  <Chip size="sm" variant="flat" color="primary">{t(`municipal_reports.cards.${report.key}.metric_1`)}</Chip>
                  <Chip size="sm" variant="flat" color="secondary">{t(`municipal_reports.cards.${report.key}.metric_2`)}</Chip>
                  <Chip size="sm" variant="flat" color="success">{t(`municipal_reports.cards.${report.key}.metric_3`)}</Chip>
                </div>
                <Button
                  as={Link}
                  to={tenantPath(report.href)}
                  variant="flat"
                  className="justify-start"
                  startContent={<FileText size={16} />}
                >
                  {t('municipal_reports.actions.open_source_report')}
                </Button>
              </CardBody>
            </Card>
          );
        })}
      </div>
    </div>
  );
}
