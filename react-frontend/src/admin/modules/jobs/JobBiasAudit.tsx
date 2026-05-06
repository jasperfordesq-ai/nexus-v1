// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Job Bias Audit Dashboard
 *
 * Displays hiring bias metrics including funnel visualization,
 * rejection rates by stage, time-in-stage analysis, and source
 * effectiveness comparison.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Chip,
  Button,
  Spinner,
  Input,
  Divider,
} from '@heroui/react';
import BarChart3 from 'lucide-react/icons/chart-column';
import Clock from 'lucide-react/icons/clock';
import Users from 'lucide-react/icons/users';
import Target from 'lucide-react/icons/target';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Filter from 'lucide-react/icons/filter';
import TrendingUp from 'lucide-react/icons/trending-up';
import TrendingDown from 'lucide-react/icons/trending-down';
import Briefcase from 'lucide-react/icons/briefcase';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader, StatCard } from '../../components';

interface BiasReport {
  period: { from: string; to: string };
  total_applications: number;
  funnel: {
    applied: number;
    screening: number;
    interview: number;
    offer: number;
    accepted: number;
  };
  rejection_rates: Record<
    string,
    { rejected: number; total: number; rate: number }
  >;
  avg_time_in_stage: Record<string, number>;
  skills_match_correlation: { accepted_avg: number; rejected_avg: number };
  source_effectiveness: {
    direct: { applications: number; accepted: number; rate: number };
    referral: { applications: number; accepted: number; rate: number };
  };
  hiring_velocity_days: number | null;
}

const FUNNEL_STAGES = ['applied', 'screening', 'interview', 'offer', 'accepted'] as const;

const FUNNEL_COLORS: Record<string, string> = {
  applied: 'bg-primary',
  screening: 'bg-secondary',
  interview: 'bg-warning',
  offer: 'bg-success',
  accepted: 'bg-success',
};

function JobBiasAudit() {
  const { t } = useTranslation('admin');
  usePageTitle(t('jobs.bias_page_title', 'Hiring Bias Audit'));
  const toast = useToast();

  const [report, setReport] = useState<BiasReport | null>(null);
  const [loading, setLoading] = useState(true);
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [jobId, setJobId] = useState('');

  const fetchReport = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (dateFrom) params.set('from', dateFrom);
      if (dateTo) params.set('to', dateTo);
      if (jobId) params.set('job_id', jobId);

      const qs = params.toString();
      const url = `/v2/admin/jobs/bias-audit${qs ? `?${qs}` : ''}`;
      const res = await api.get<BiasReport>(url);
      if (res.success) setReport(res.data as BiasReport);
    } catch {
      toast.error(t('jobs.bias_failed_load', 'Failed to load bias audit data'));
    } finally {
      setLoading(false);
    }
  }, [dateFrom, dateTo, jobId, t, toast]);


  useEffect(() => {
    fetchReport();
  }, [fetchReport]);

  const formatStage = (stage: string): string =>
    stage
      .replace(/_/g, ' ')
      .replace(/\b\w/g, (c) => c.toUpperCase());

  const maxFunnel = report
    ? Math.max(...Object.values(report.funnel), 1)
    : 1;

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('jobs.bias_page_title', 'Hiring Bias Audit')}
        description={t(
          'jobs.bias_page_description',
          'Analyze hiring funnel metrics for potential bias indicators'
        )}
      />

      {/* Filters */}
      <Card>
        <CardBody>
          <div className="flex flex-wrap items-end gap-4">
            <Input
              type="date"
              label={t('jobs.bias_date_from', 'From')}
              value={dateFrom}
              onValueChange={setDateFrom}
              className="w-48"
              variant="bordered"
            />
            <Input
              type="date"
              label={t('jobs.bias_date_to', 'To')}
              value={dateTo}
              onValueChange={setDateTo}
              className="w-48"
              variant="bordered"
            />
            <Input
              type="number"
              label={t('jobs.bias_job_id', 'Job ID (optional)')}
              value={jobId}
              onValueChange={setJobId}
              className="w-48"
              variant="bordered"
              placeholder="All jobs"
            />
            <Button
              color="primary"
              onPress={fetchReport}
              isLoading={loading}
              startContent={!loading ? <Filter className="w-4 h-4" /> : undefined}
            >
              {t('jobs.bias_apply_filters', 'Apply')}
            </Button>
            <Button
              variant="flat"
              onPress={() => {
                setDateFrom('');
                setDateTo('');
                setJobId('');
              }}
              startContent={<RefreshCw className="w-4 h-4" />}
            >
              {t('jobs.bias_reset', 'Reset')}
            </Button>
          </div>
        </CardBody>
      </Card>

      {loading ? (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      ) : !report ? (
        <Card>
          <CardBody>
            <p className="text-center text-default-500 py-8">
              {t('jobs.bias_no_data', 'No data available for the selected period.')}
            </p>
          </CardBody>
        </Card>
      ) : (
        <>
          {/* Period indicator */}
          {report.period.from && report.period.to && (
            <div className="flex items-center gap-2 text-sm text-default-500">
              <Clock className="w-4 h-4" />
              <span>
                {t('jobs.bias_period', 'Period')}: {report.period.from} — {report.period.to}
              </span>
            </div>
          )}

          {/* Stats Row */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard
              label={t('jobs.bias_total_applications', 'Total Applications')}
              value={report.total_applications}
              icon={Users}
              color="primary"
            />
            <StatCard
              label={t('jobs.bias_hiring_velocity', 'Hiring Velocity')}
              value={
                report.hiring_velocity_days !== null
                  ? `${report.hiring_velocity_days}d`
                  : 'N/A'
              }
              icon={Clock}
              color="secondary"
              description={t('jobs.bias_avg_days_to_hire', 'Avg days to hire')}
            />
            <StatCard
              label={t('jobs.bias_skills_accepted', 'Skills Match (Accepted)')}
              value={`${(report.skills_match_correlation.accepted_avg * 100).toFixed(1)}%`}
              icon={Target}
              color="success"
            />
            <StatCard
              label={t('jobs.bias_skills_rejected', 'Skills Match (Rejected)')}
              value={`${(report.skills_match_correlation.rejected_avg * 100).toFixed(1)}%`}
              icon={Target}
              color="danger"
            />
          </div>

          {/* Hiring Funnel */}
          <Card>
            <CardHeader className="flex items-center gap-2">
              <BarChart3 className="w-5 h-5 text-primary" />
              <h3 className="text-lg font-semibold">
                {t('jobs.bias_hiring_funnel', 'Hiring Funnel')}
              </h3>
            </CardHeader>
            <Divider />
            <CardBody className="space-y-3 py-6">
              {FUNNEL_STAGES.map((stage) => {
                const count = report.funnel[stage] ?? 0;
                const pct = maxFunnel > 0 ? (count / maxFunnel) * 100 : 0;
                return (
                  <div key={stage} className="flex items-center gap-4">
                    <span className="w-28 text-sm font-medium text-right text-default-700">
                      {formatStage(stage)}
                    </span>
                    <div className="flex-1 h-8 bg-default-100 rounded-lg overflow-hidden relative">
                      <div
                        className={`h-full ${FUNNEL_COLORS[stage] ?? 'bg-primary'} rounded-lg transition-all duration-500`}
                        style={{ width: `${Math.max(pct, 1)}%` }}
                      />
                      <span className="absolute inset-0 flex items-center px-3 text-sm font-medium">
                        {count}
                      </span>
                    </div>
                    <span className="w-16 text-sm text-default-500 text-right">
                      {pct.toFixed(1)}%
                    </span>
                  </div>
                );
              })}
            </CardBody>
          </Card>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Rejection Rates */}
            <Card>
              <CardHeader className="flex items-center gap-2">
                <TrendingDown className="w-5 h-5 text-danger" />
                <h3 className="text-lg font-semibold">
                  {t('jobs.bias_rejection_rates', 'Rejection Rates by Stage')}
                </h3>
              </CardHeader>
              <Divider />
              <CardBody>
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-default-500 border-b border-default-200">
                      <th className="py-2 pr-4">{t('jobs.bias_stage', 'Stage')}</th>
                      <th className="py-2 pr-4 text-right">{t('jobs.bias_rejected', 'Rejected')}</th>
                      <th className="py-2 pr-4 text-right">{t('jobs.bias_total', 'Total')}</th>
                      <th className="py-2 text-right">{t('jobs.bias_rate', 'Rate')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {Object.entries(report.rejection_rates).map(([stage, data]) => (
                      <tr key={stage} className="border-b border-default-100">
                        <td className="py-2 pr-4 font-medium">{formatStage(stage)}</td>
                        <td className="py-2 pr-4 text-right">{data.rejected}</td>
                        <td className="py-2 pr-4 text-right">{data.total}</td>
                        <td className="py-2 text-right">
                          <Chip
                            size="sm"
                            variant="flat"
                            color={data.rate > 50 ? 'danger' : data.rate > 30 ? 'warning' : 'success'}
                          >
                            {data.rate.toFixed(1)}%
                          </Chip>
                        </td>
                      </tr>
                    ))}
                    {Object.keys(report.rejection_rates).length === 0 && (
                      <tr>
                        <td colSpan={4} className="py-4 text-center text-default-400">
                          {t('jobs.bias_no_rejection_data', 'No rejection data available')}
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </CardBody>
            </Card>

            {/* Average Time in Stage */}
            <Card>
              <CardHeader className="flex items-center gap-2">
                <Clock className="w-5 h-5 text-warning" />
                <h3 className="text-lg font-semibold">
                  {t('jobs.bias_avg_time_stage', 'Average Time in Stage')}
                </h3>
              </CardHeader>
              <Divider />
              <CardBody>
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-default-500 border-b border-default-200">
                      <th className="py-2 pr-4">{t('jobs.bias_stage', 'Stage')}</th>
                      <th className="py-2 text-right">{t('jobs.bias_days', 'Days')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {Object.entries(report.avg_time_in_stage).map(([stage, days]) => (
                      <tr key={stage} className="border-b border-default-100">
                        <td className="py-2 pr-4 font-medium">{formatStage(stage)}</td>
                        <td className="py-2 text-right">
                          <Chip
                            size="sm"
                            variant="flat"
                            color={days > 14 ? 'danger' : days > 7 ? 'warning' : 'success'}
                          >
                            {days.toFixed(1)}
                          </Chip>
                        </td>
                      </tr>
                    ))}
                    {Object.keys(report.avg_time_in_stage).length === 0 && (
                      <tr>
                        <td colSpan={2} className="py-4 text-center text-default-400">
                          {t('jobs.bias_no_time_data', 'No time data available')}
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </CardBody>
            </Card>
          </div>

          {/* Source Effectiveness */}
          <Card>
            <CardHeader className="flex items-center gap-2">
              <TrendingUp className="w-5 h-5 text-success" />
              <h3 className="text-lg font-semibold">
                {t('jobs.bias_source_effectiveness', 'Source Effectiveness')}
              </h3>
            </CardHeader>
            <Divider />
            <CardBody>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                {/* Direct */}
                <div className="p-4 rounded-lg bg-default-50 border border-default-200 space-y-3">
                  <div className="flex items-center gap-2">
                    <Briefcase className="w-5 h-5 text-primary" />
                    <span className="text-base font-semibold">
                      {t('jobs.bias_source_direct', 'Direct Applications')}
                    </span>
                  </div>
                  <div className="grid grid-cols-3 gap-2 text-center">
                    <div>
                      <p className="text-2xl font-bold text-primary">
                        {report.source_effectiveness.direct.applications}
                      </p>
                      <p className="text-xs text-default-500">
                        {t('jobs.bias_applications', 'Applications')}
                      </p>
                    </div>
                    <div>
                      <p className="text-2xl font-bold text-success">
                        {report.source_effectiveness.direct.accepted}
                      </p>
                      <p className="text-xs text-default-500">
                        {t('jobs.bias_accepted', 'Accepted')}
                      </p>
                    </div>
                    <div>
                      <p className="text-2xl font-bold">
                        {report.source_effectiveness.direct.rate.toFixed(1)}%
                      </p>
                      <p className="text-xs text-default-500">
                        {t('jobs.bias_rate', 'Rate')}
                      </p>
                    </div>
                  </div>
                </div>

                {/* Referral */}
                <div className="p-4 rounded-lg bg-default-50 border border-default-200 space-y-3">
                  <div className="flex items-center gap-2">
                    <Users className="w-5 h-5 text-secondary" />
                    <span className="text-base font-semibold">
                      {t('jobs.bias_source_referral', 'Referral Applications')}
                    </span>
                  </div>
                  <div className="grid grid-cols-3 gap-2 text-center">
                    <div>
                      <p className="text-2xl font-bold text-primary">
                        {report.source_effectiveness.referral.applications}
                      </p>
                      <p className="text-xs text-default-500">
                        {t('jobs.bias_applications', 'Applications')}
                      </p>
                    </div>
                    <div>
                      <p className="text-2xl font-bold text-success">
                        {report.source_effectiveness.referral.accepted}
                      </p>
                      <p className="text-xs text-default-500">
                        {t('jobs.bias_accepted', 'Accepted')}
                      </p>
                    </div>
                    <div>
                      <p className="text-2xl font-bold">
                        {report.source_effectiveness.referral.rate.toFixed(1)}%
                      </p>
                      <p className="text-xs text-default-500">
                        {t('jobs.bias_rate', 'Rate')}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </CardBody>
          </Card>
        </>
      )}
    </div>
  );
}

export default JobBiasAudit;
