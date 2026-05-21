// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ReactNode } from 'react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Select,
  SelectItem,
  Spinner,
  Switch,
  Textarea,
  useDisclosure,
} from '@heroui/react';
import { Link } from 'react-router-dom';
import BarChart3 from 'lucide-react/icons/chart-column';
import Info from 'lucide-react/icons/info';
import Clock from 'lucide-react/icons/clock';
import Download from 'lucide-react/icons/download';
import FileText from 'lucide-react/icons/file-text';
import Heart from 'lucide-react/icons/heart';
import Users from 'lucide-react/icons/users';
import Building2 from 'lucide-react/icons/building-2';
import ShieldCheck from 'lucide-react/icons/shield-check';
import Layers from 'lucide-react/icons/layers';
import Plus from 'lucide-react/icons/plus';
import Save from 'lucide-react/icons/save';
import Trash2 from 'lucide-react/icons/trash-2';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { api, API_BASE, tokenManager } from '@/lib/api';
import { PageHeader, StatCard, Abbr } from '../../components';
import { VerifiedMunicipalityBadge } from '@/components/badges/VerifiedMunicipalityBadge';

const reportCards = [
  { key: 'verified_hours', icon: Clock, href: '/admin/reports/hours', statKey: 'verified_hours' },
  { key: 'active_members', icon: Users, href: '/admin/reports/members', statKey: 'active_members' },
  { key: 'organisations', icon: Building2, href: '/admin/volunteering/organizations', statKey: 'trusted_organisations' },
  { key: 'trust_pack', icon: ShieldCheck, href: '/admin/safeguarding', statKey: 'pending_hours' },
] as const;

type CantonVariant = {
  aggregate_municipalities_count: number;
  multi_node_total_hours: number;
  est_cost_avoidance_chf: number;
  cost_avoidance_multiplier: number;
  yoy_change_percent: number | null;
  yoy_prior_period: { from: string; to: string };
  yoy_prior_hours: number;
};

type MunicipalityVariant = {
  partner_organisations: Array<{ id: number; name: string; hours: number; log_count: number }>;
  partner_organisations_count: number;
  recipients_reached_count: number;
  geographic_distribution: Array<{ name: string; hours: number; count: number }>;
  trusted_organisations_total: number;
};

type CooperativeVariant = {
  member_retention_rate: number;
  retained_members_count: number;
  reciprocity_rate: number;
  reciprocal_members_count: number;
  tandem_count: number;
  coordinator_load_avg: number;
  pending_reviews_total: number;
  coordinator_count: number;
  future_care_credit_pool: number;
  active_members_total: number;
};

type AudienceMode = 'canton' | 'municipality' | 'cooperative';

type MunicipalImpactSummary = {
  period: { from: string; to: string };
  currency: string;
  hour_value: number;
  social_multiplier: number;
  policy?: {
    default_period: string;
    include_social_value_estimate: boolean;
    default_hour_value_chf: number;
  };
  stats: Record<string, number>;
  categories: Array<{ name: string; hours: number; count: number }>;
  trends: Array<{ period: string; verified_hours: number; activities: number; participants: number }>;
  report_context?: {
    audience: ReportTemplate['audience'];
    template_name: string | null;
    sections: string[];
  };
  readiness_signals?: Array<{
    key: 'municipal_value' | 'participation' | 'partner_network' | 'local_exchange';
    status: 'ready' | 'needs_data';
    value: number;
  }>;
  canton_variant?: CantonVariant;
  municipality_variant?: MunicipalityVariant;
  cooperative_variant?: CooperativeVariant;
};

type ReportTemplate = {
  id: number;
  name: string;
  description: string | null;
  audience: 'municipality' | 'canton' | 'cooperative' | 'foundation';
  date_preset: 'last_30_days' | 'last_90_days' | 'year_to_date' | 'previous_quarter';
  include_social_value: boolean;
  hour_value_chf: number | null;
  sections: string[];
};

type TemplatesResponse = {
  templates: ReportTemplate[];
};

const READINESS_HELP: Record<string, { whatKey: string; fixKey: string }> = {
  municipal_value: {
    whatKey: 'municipal_reports.readiness_help.municipal_value.what',
    fixKey: 'municipal_reports.readiness_help.municipal_value.fix',
  },
  participation: {
    whatKey: 'municipal_reports.readiness_help.participation.what',
    fixKey: 'municipal_reports.readiness_help.participation.fix',
  },
  partner_network: {
    whatKey: 'municipal_reports.readiness_help.partner_network.what',
    fixKey: 'municipal_reports.readiness_help.partner_network.fix',
  },
  local_exchange: {
    whatKey: 'municipal_reports.readiness_help.local_exchange.what',
    fixKey: 'municipal_reports.readiness_help.local_exchange.fix',
  },
};

const defaultTemplateForm = {
  name: '',
  description: '',
  audience: 'municipality' as ReportTemplate['audience'],
  date_preset: 'last_90_days' as ReportTemplate['date_preset'],
  include_social_value: true,
  hour_value_chf: '',
  sections: ['summary', 'hours', 'members', 'organisations', 'categories', 'trends', 'trust'],
};

function isMunicipalImpactSummary(value: unknown): value is MunicipalImpactSummary {
  return Boolean(value && typeof value === 'object' && 'period' in value && 'stats' in value);
}

type ReportDateFilters = {
  dateFrom: string;
  dateTo: string;
};

function municipalReportParams(templateId: number | null, filters: ReportDateFilters, audience?: AudienceMode | null) {
  const params = new URLSearchParams();
  if (templateId) params.set('template_id', String(templateId));
  if (filters.dateFrom) params.set('date_from', filters.dateFrom);
  if (filters.dateTo) params.set('date_to', filters.dateTo);
  if (audience) params.set('audience', audience);
  return params;
}

async function downloadMunicipalExport(format: 'csv' | 'pdf', filename: string, templateId: number | null, filters: ReportDateFilters, audience: AudienceMode) {
  const headers: Record<string, string> = {};
  const token = tokenManager.getAccessToken();
  const tenantId = tokenManager.getTenantId();
  if (token) headers.Authorization = `Bearer ${token}`;
  if (tenantId) headers['X-Tenant-ID'] = tenantId;

  const params = municipalReportParams(templateId, filters, audience);
  params.set('format', format);

  const res = await fetch(`${API_BASE}/v2/admin/reports/municipal_impact/export?${params.toString()}`, {
    headers,
    credentials: 'include',
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);

  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

export default function MunicipalImpactReportsPage() {
  const { t } = useTranslation('admin');
  const { tenantPath } = useTenant();
  const toast = useToast();
  const { isOpen, onOpen, onClose } = useDisclosure();
  usePageTitle(t('municipal_reports.meta.title'));
  const [summary, setSummary] = useState<MunicipalImpactSummary | null>(null);
  const [templates, setTemplates] = useState<ReportTemplate[]>([]);
  const [selectedTemplateId, setSelectedTemplateId] = useState<number | null>(null);
  const [templateForm, setTemplateForm] = useState(defaultTemplateForm);
  const [dateFilters, setDateFilters] = useState<ReportDateFilters>({ dateFrom: '', dateTo: '' });
  const [loading, setLoading] = useState(true);
  const [templatesLoading, setTemplatesLoading] = useState(true);
  const [savingTemplate, setSavingTemplate] = useState(false);
  const [deletingTemplateId, setDeletingTemplateId] = useState<number | null>(null);
  const [exporting, setExporting] = useState<'csv' | 'pdf' | null>(null);
  const [audienceMode, setAudienceMode] = useState<AudienceMode>('municipality');
  const [verification, setVerification] = useState<{
    verified: boolean;
    active: { domain: string | null; verified_at: string | null } | null;
  } | null>(null);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ verified: boolean; active: { domain: string | null; verified_at: string | null } | null }>(
        '/v2/admin/reports/municipal-impact/verification',
      )
      .then((res) => {
        if (cancelled) return;
        if (res.success && res.data) setVerification(res.data);
      })
      .catch(() => {
        // 503 = service unavailable; treat as not verified
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const loadSummary = useCallback(async () => {
    setLoading(true);
    try {
      const params = municipalReportParams(selectedTemplateId, dateFilters, audienceMode);
      const endpoint = `/v2/admin/reports/municipal-impact?${params.toString()}`;
      const res = await api.get<MunicipalImpactSummary>(endpoint);
      if (isMunicipalImpactSummary(res.data)) setSummary(res.data);
    } catch {
      toast.error(t('municipal_reports.toast.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [audienceMode, dateFilters, selectedTemplateId, t, toast]);

  const loadTemplates = useCallback(async () => {
    setTemplatesLoading(true);
    try {
      const res = await api.get<TemplatesResponse>('/v2/admin/reports/municipal-impact/templates');
      setTemplates(res.data?.templates ?? []);
    } catch {
      toast.error(t('municipal_reports.toast.templates_load_failed'));
    } finally {
      setTemplatesLoading(false);
    }
  }, [t, toast]);

  useEffect(() => {
    loadSummary();
  }, [loadSummary]);

  useEffect(() => {
    loadTemplates();
  }, [loadTemplates]);

  const stats = summary?.stats ?? {};
  const currencyFormatter = useMemo(() => new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: summary?.currency ?? 'CHF',
    maximumFractionDigits: 0,
  }), [summary?.currency]);

  const metric = (key: string) => stats[key] ?? 0;
  const formatHours = (value: number) => t('municipal_reports.values.hours', { count: Number(value.toFixed(1)) });
  const templateOptions = useMemo(() => [
    { id: 'default', label: t('municipal_reports.templates.default_policy') },
    ...templates.map((template) => ({ id: String(template.id), label: template.name })),
  ], [templates, t]);

  const handleExport = async (format: 'csv' | 'pdf') => {
    setExporting(format);
    try {
      await downloadMunicipalExport(
        format,
        `municipal-impact-pack-${audienceMode}.${format}`,
        selectedTemplateId,
        dateFilters,
        audienceMode,
      );
      toast.success(t('municipal_reports.toast.export_started'));
    } catch {
      toast.error(t('municipal_reports.toast.export_failed'));
    } finally {
      setExporting(null);
    }
  };

  // Sync the audience mode with the selected template's audience when one is chosen.
  useEffect(() => {
    const tplAudience = templates.find((tpl) => tpl.id === selectedTemplateId)?.audience;
    if (tplAudience === 'canton' || tplAudience === 'municipality' || tplAudience === 'cooperative') {
      setAudienceMode(tplAudience);
    }
  }, [selectedTemplateId, templates]);

  const selectedTemplate = templates.find((template) => template.id === selectedTemplateId) ?? null;
  const reportAudience = summary?.report_context?.audience ?? selectedTemplate?.audience ?? 'municipality';

  const openTemplateModal = () => {
    setTemplateForm(defaultTemplateForm);
    onOpen();
  };

  const saveTemplate = async () => {
    setSavingTemplate(true);
    try {
      const payload = {
        ...templateForm,
        hour_value_chf: templateForm.hour_value_chf === '' ? null : Number(templateForm.hour_value_chf),
      };
      const res = await api.post<{ template: ReportTemplate }>('/v2/admin/reports/municipal-impact/templates', payload);
      const template = res.data?.template;
      await loadTemplates();
      if (template) setSelectedTemplateId(template.id);
      toast.success(t('municipal_reports.toast.template_saved'));
      onClose();
    } catch {
      toast.error(t('municipal_reports.toast.template_save_failed'));
    } finally {
      setSavingTemplate(false);
    }
  };

  const deleteTemplate = async (templateId: number) => {
    setDeletingTemplateId(templateId);
    try {
      await api.delete(`/v2/admin/reports/municipal-impact/templates/${templateId}`);
      if (selectedTemplateId === templateId) setSelectedTemplateId(null);
      await loadTemplates();
      toast.success(t('municipal_reports.toast.template_deleted'));
    } catch {
      toast.error(t('municipal_reports.toast.template_delete_failed'));
    } finally {
      setDeletingTemplateId(null);
    }
  };

  return (
    <div className="mx-auto max-w-7xl px-4 pb-8">
      {verification?.verified && (
        <div className="mb-3">
          <VerifiedMunicipalityBadge
            domain={verification.active?.domain ?? null}
            verifiedAt={verification.active?.verified_at ?? null}
          />
        </div>
      )}
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
            <Button
              variant="solid"
              color="primary"
              size="sm"
              isLoading={exporting === 'csv'}
              startContent={<Download size={16} />}
              onPress={() => handleExport('csv')}
            >
              {t('municipal_reports.actions.export_csv')}
            </Button>
            <Button
              variant="flat"
              size="sm"
              isLoading={exporting === 'pdf'}
              startContent={<FileText size={16} />}
              onPress={() => handleExport('pdf')}
            >
              {t('municipal_reports.actions.export_pdf')}
            </Button>
          </div>
        }
      />

      <p className="mb-2 text-xs text-default-400">
        <strong>{t('municipal_reports.export_note.pdf_label')}</strong>{t('municipal_reports.export_note.pdf_text')}{' '}
        <strong>{t('municipal_reports.export_note.csv_label')}</strong>{t('municipal_reports.export_note.csv_text')}
      </p>

      <Card className="mb-4 border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">{t('municipal_reports.about.title')}</p>
              <p className="text-default-600">
                {t('municipal_reports.about.body_1_before_kiss')} <Abbr term={t('municipal_reports.about.kiss_abbr')}>{t('municipal_reports.about.kiss_abbr')}</Abbr>
                {t('municipal_reports.about.body_1_after_kiss')} <Abbr term={t('municipal_reports.about.chf_abbr')}>{t('municipal_reports.about.chf_abbr')}</Abbr>
                {t('municipal_reports.about.body_1_after_chf')}
              </p>
              <p className="text-default-500">
                {t('municipal_reports.about.body_2_prefix')} <strong>{t('municipal_reports.audience_selector.title')}</strong>
                {t('municipal_reports.about.body_2_after_audience')} <strong>{t('municipal_reports.templates.audiences.canton')}</strong>
                {t('municipal_reports.about.body_2_after_canton')}{' '}
                <strong>{t('municipal_reports.templates.audiences.municipality')}</strong>{t('municipal_reports.about.body_2_after_municipality')}{' '}
                <strong>{t('municipal_reports.templates.audiences.cooperative')}</strong>{t('municipal_reports.about.body_2_after_cooperative')}
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      <Card className="mb-4" shadow="sm">
        <CardBody className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
          <div>
            <p className="text-xs font-medium uppercase text-default-500">{t('municipal_reports.audience_selector.title')}</p>
            <p className="text-sm text-default-600">
              {t('municipal_reports.audience_selector.description')}
            </p>
          </div>
          <div className="inline-flex rounded-lg border border-default-200 bg-default-50 p-1">
            {(['canton', 'municipality', 'cooperative'] as const).map((mode) => {
              const active = audienceMode === mode;
              return (
                <Button
                  key={mode}
                  size="sm"
                  color={active ? 'primary' : 'default'}
                  variant={active ? 'solid' : 'light'}
                  aria-pressed={active}
                  onPress={() => setAudienceMode(mode)}
                >
                  {t(`municipal_reports.templates.audiences.${mode}`)}
                </Button>
              );
            })}
          </div>
        </CardBody>
      </Card>

      <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
        <StatCard label={t('municipal_reports.stats.verified_hours')} value={formatHours(metric('verified_hours'))} icon={Heart} color="success" />
        <StatCard label={t('municipal_reports.stats.participants')} value={metric('participating_members').toLocaleString()} icon={Users} color="warning" />
        <StatCard label={t('municipal_reports.stats.organisations')} value={metric('trusted_organisations').toLocaleString()} icon={Building2} color="primary" />
        <StatCard label={t('municipal_reports.stats.total_value')} value={currencyFormatter.format(metric('total_value'))} icon={Download} color="secondary" />
      </div>

      {loading && (
        <div className="flex min-h-64 items-center justify-center">
          <Spinner label={t('municipal_reports.loading')} />
        </div>
      )}

      {!loading && summary && (
        <Card className="mb-6" shadow="sm">
          <CardBody className="grid grid-cols-1 gap-4 md:grid-cols-5">
            <div>
              <p className="text-xs font-medium uppercase text-default-500">{t('municipal_reports.period')}</p>
              <p className="mt-1 text-sm font-semibold text-default-800">{summary.period.from} - {summary.period.to}</p>
            </div>
            <div>
              <p className="text-xs font-medium uppercase text-default-500">{t('municipal_reports.audience')}</p>
              <p className="mt-1 text-sm font-semibold text-default-800">
                {t(`municipal_reports.templates.audiences.${reportAudience}`)}
              </p>
            </div>
            <div>
              <p className="text-xs font-medium uppercase text-default-500">{t('municipal_reports.hour_value')}</p>
              <p className="mt-1 text-sm font-semibold text-default-800">{currencyFormatter.format(summary.hour_value)}</p>
            </div>
            <div>
              <p className="text-xs font-medium uppercase text-default-500">{t('municipal_reports.multiplier')}</p>
              <p className="mt-1 text-sm font-semibold text-default-800">
                {summary.policy?.include_social_value_estimate === false
                  ? t('municipal_reports.values.disabled')
                  : `${summary.social_multiplier}x`}
              </p>
            </div>
            <div>
              <p className="text-xs font-medium uppercase text-default-500">{t('municipal_reports.policy_default')}</p>
              <p className="mt-1 text-sm font-semibold text-default-800">
                {t(`caring_workflow.policy.periods.${summary.policy?.default_period ?? 'last_90_days'}`)}
              </p>
            </div>
          </CardBody>
        </Card>
      )}

      <Card className="mb-6" shadow="sm">
        <CardHeader className="flex flex-col items-start gap-1">
          <h2 className="text-base font-semibold">{t('municipal_reports.filters.title')}</h2>
          <p className="text-sm text-default-500">{t('municipal_reports.filters.description')}</p>
        </CardHeader>
        <Divider />
        <CardBody className="grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_auto_auto]">
          <Input
            type="date"
            label={t('municipal_reports.filters.date_from')}
            value={dateFilters.dateFrom}
            onValueChange={(dateFrom) => setDateFilters((filters) => ({ ...filters, dateFrom }))}
          />
          <Input
            type="date"
            label={t('municipal_reports.filters.date_to')}
            value={dateFilters.dateTo}
            onValueChange={(dateTo) => setDateFilters((filters) => ({ ...filters, dateTo }))}
          />
          <Button variant="flat" onPress={loadSummary} isLoading={loading}>
            {t('municipal_reports.filters.apply')}
          </Button>
          <Button
            variant="light"
            onPress={() => setDateFilters({ dateFrom: '', dateTo: '' })}
            isDisabled={!dateFilters.dateFrom && !dateFilters.dateTo}
          >
            {t('municipal_reports.filters.clear')}
          </Button>
        </CardBody>
      </Card>

      <Card className="mb-6" shadow="sm">
        <CardHeader className="flex flex-col items-start gap-3 md:flex-row md:items-center">
          <div className="flex items-start gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-secondary/10 text-secondary">
              <Layers size={20} />
            </div>
            <div>
              <h2 className="text-base font-semibold">{t('municipal_reports.templates.title')}</h2>
              <p className="mt-1 text-sm text-default-500">{t('municipal_reports.templates.description')}</p>
            </div>
          </div>
          <Button
            className="md:ml-auto"
            color="primary"
            variant="flat"
            size="sm"
            startContent={<Plus size={16} />}
            onPress={openTemplateModal}
          >
            {t('municipal_reports.templates.create')}
          </Button>
        </CardHeader>
        <Divider />
        <CardBody className="gap-4">
          <div className="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
            <Select
              label={t('municipal_reports.templates.select_label')}
              selectedKeys={[selectedTemplateId ? String(selectedTemplateId) : 'default']}
              isLoading={templatesLoading}
              onSelectionChange={(keys) => {
                const value = Array.from(keys)[0];
                setSelectedTemplateId(value && value !== 'default' ? Number(value) : null);
              }}
              items={templateOptions}
            >
              {(item) => <SelectItem key={item.id}>{item.label}</SelectItem>}
            </Select>
            <Button
              variant="flat"
              startContent={<Save size={16} />}
              onPress={loadSummary}
              isLoading={loading}
            >
              {t('municipal_reports.templates.apply')}
            </Button>
          </div>

          {selectedTemplate && (
            <div className="grid grid-cols-1 gap-3 rounded-lg border border-default-200 p-3 md:grid-cols-[minmax(0,1fr)_auto]">
              <div>
                <div className="flex flex-wrap items-center gap-2">
                  <p className="text-sm font-semibold text-default-800">{selectedTemplate.name}</p>
                  <Chip size="sm" variant="flat" color="primary">
                    {t(`municipal_reports.templates.audiences.${selectedTemplate.audience}`)}
                  </Chip>
                  <Chip size="sm" variant="flat" color="secondary">
                    {t(`caring_workflow.policy.periods.${selectedTemplate.date_preset}`)}
                  </Chip>
                  {selectedTemplate.hour_value_chf !== null && (
                    <Chip size="sm" variant="flat" color="success">
                      {t('municipal_reports.templates.hour_value_chip', { value: selectedTemplate.hour_value_chf })}
                    </Chip>
                  )}
                </div>
                {selectedTemplate.description && (
                  <p className="mt-2 text-sm text-default-500">{selectedTemplate.description}</p>
                )}
              </div>
              <Button
                color="danger"
                variant="light"
                size="sm"
                startContent={<Trash2 size={16} />}
                isLoading={deletingTemplateId === selectedTemplate.id}
                onPress={() => deleteTemplate(selectedTemplate.id)}
              >
                {t('municipal_reports.templates.delete')}
              </Button>
            </div>
          )}
        </CardBody>
      </Card>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {reportCards.map((report) => {
          const Icon = report.icon;
          const value = report.statKey === 'pending_hours' ? formatHours(metric(report.statKey)) : metric(report.statKey).toLocaleString();
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
                <Chip className="ml-auto" size="sm" variant="flat" color="primary">{value}</Chip>
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

      {!loading && summary && (
        <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
          <Card shadow="sm" className="lg:col-span-2">
            <CardHeader className="flex flex-col items-start gap-1">
              <h2 className="text-base font-semibold">{t('municipal_reports.sections.readiness')}</h2>
              <p className="text-xs text-default-500">
                {t('municipal_reports.readiness_intro.prefix')}
                <strong className="text-success-600"> {t('municipal_reports.readiness.status.ready')}</strong>
                {t('municipal_reports.readiness_intro.ready_suffix')}
                <strong className="text-warning-600"> {t('municipal_reports.readiness.status.needs_data')}</strong>
                {t('municipal_reports.readiness_intro.needs_data_suffix')}
              </p>
            </CardHeader>
            <Divider />
            <CardBody className="grid grid-cols-1 gap-3 md:grid-cols-4">
              {(summary.readiness_signals ?? []).map((signal) => {
                const help = READINESS_HELP[signal.key];
                return (
                  <div
                    key={signal.key}
                    className="rounded-lg border border-default-200 p-3"
                    title={help ? t(signal.status === 'needs_data' ? help.fixKey : help.whatKey) : undefined}
                  >
                    <div className="flex items-center justify-between gap-2">
                      <p className="text-sm font-medium text-default-800">{t(`municipal_reports.readiness.${signal.key}`)}</p>
                      <Chip size="sm" color={signal.status === 'ready' ? 'success' : 'warning'} variant="flat">
                        {t(`municipal_reports.readiness.status.${signal.status}`)}
                      </Chip>
                    </div>
                    <p className="mt-2 text-2xl font-semibold text-default-900">{signal.value.toLocaleString(undefined, { maximumFractionDigits: 1 })}</p>
                    {help && (
                      <p className="mt-1 text-xs text-default-400">
                        {t(signal.status === 'needs_data' ? help.fixKey : help.whatKey)}
                      </p>
                    )}
                  </div>
                );
              })}
            </CardBody>
          </Card>

          <Card shadow="sm">
            <CardHeader>
              <h2 className="text-base font-semibold">{t('municipal_reports.sections.categories')}</h2>
            </CardHeader>
            <Divider />
            <CardBody className="gap-3">
              {summary.categories.length === 0 ? (
                <p className="text-sm text-default-500">{t('municipal_reports.empty.categories')}</p>
              ) : summary.categories.map((category) => (
                <div key={category.name} className="flex items-center justify-between gap-3">
                  <div>
                    <p className="text-sm font-medium text-default-800">{category.name}</p>
                    <p className="text-xs text-default-500">{t('municipal_reports.values.activities', { count: category.count })}</p>
                  </div>
                  <Chip size="sm" variant="flat" color="success">{formatHours(category.hours)}</Chip>
                </div>
              ))}
            </CardBody>
          </Card>

          <Card shadow="sm">
            <CardHeader>
              <h2 className="text-base font-semibold">{t('municipal_reports.sections.trends')}</h2>
            </CardHeader>
            <Divider />
            <CardBody className="gap-3">
              {summary.trends.length === 0 ? (
                <p className="text-sm text-default-500">{t('municipal_reports.empty.trends')}</p>
              ) : summary.trends.slice(-6).map((trend) => (
                <div key={trend.period} className="flex items-center justify-between gap-3">
                  <div>
                    <p className="text-sm font-medium text-default-800">{trend.period}</p>
                    <p className="text-xs text-default-500">
                      {t('municipal_reports.values.trend_detail', { participants: trend.participants, activities: trend.activities })}
                    </p>
                  </div>
                  <Chip size="sm" variant="flat" color="secondary">{formatHours(trend.verified_hours)}</Chip>
                </div>
              ))}
            </CardBody>
          </Card>
        </div>
      )}

      {!loading && summary && (
        <div className="mt-6 space-y-4">
          <CantonNarrativeSection
            isActive={audienceMode === 'canton'}
            variant={summary.canton_variant}
            currency={summary.currency}
            tenantName={summary.report_context?.template_name ?? null}
            period={summary.period}
          />
          <MunicipalityNarrativeSection
            isActive={audienceMode === 'municipality'}
            variant={summary.municipality_variant}
            period={summary.period}
          />
          <CooperativeNarrativeSection
            isActive={audienceMode === 'cooperative'}
            variant={summary.cooperative_variant}
            period={summary.period}
          />
        </div>
      )}

      <Modal isOpen={isOpen} onClose={onClose} size="2xl">
        <ModalContent>
          <ModalHeader>{t('municipal_reports.templates.modal_title')}</ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label={t('municipal_reports.templates.fields.name')}
              placeholder={t('municipal_reports.templates.placeholders.name')}
              value={templateForm.name}
              onValueChange={(name) => setTemplateForm((form) => ({ ...form, name }))}
              isRequired
            />
            <Textarea
              label={t('municipal_reports.templates.fields.description')}
              placeholder={t('municipal_reports.templates.placeholders.description')}
              value={templateForm.description}
              onValueChange={(description) => setTemplateForm((form) => ({ ...form, description }))}
              minRows={2}
            />
            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
              <Select
                label={t('municipal_reports.templates.fields.audience')}
                description={t('municipal_reports.templates.descriptions.audience')}
                selectedKeys={[templateForm.audience]}
                onSelectionChange={(keys) => {
                  const value = Array.from(keys)[0] as ReportTemplate['audience'] | undefined;
                  if (value) setTemplateForm((form) => ({ ...form, audience: value }));
                }}
              >
                {(['municipality', 'canton', 'cooperative', 'foundation'] as const).map((audience) => (
                  <SelectItem key={audience}>{t(`municipal_reports.templates.audiences.${audience}`)}</SelectItem>
                ))}
              </Select>
              <Select
                label={t('municipal_reports.templates.fields.period')}
                description={t('municipal_reports.templates.descriptions.period')}
                selectedKeys={[templateForm.date_preset]}
                onSelectionChange={(keys) => {
                  const value = Array.from(keys)[0] as ReportTemplate['date_preset'] | undefined;
                  if (value) setTemplateForm((form) => ({ ...form, date_preset: value }));
                }}
              >
                {(['last_30_days', 'last_90_days', 'year_to_date', 'previous_quarter'] as const).map((period) => (
                  <SelectItem key={period}>{t(`caring_workflow.policy.periods.${period}`)}</SelectItem>
                ))}
              </Select>
            </div>
            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
              <Input
                label={t('municipal_reports.templates.fields.hour_value')}
                description={t('municipal_reports.templates.descriptions.hour_value')}
                type="number"
                min={0}
                max={500}
                value={templateForm.hour_value_chf}
                onValueChange={(hour_value_chf) => setTemplateForm((form) => ({ ...form, hour_value_chf }))}
              />
              <div className="flex items-center rounded-lg border border-default-200 px-3">
                <Switch
                  isSelected={templateForm.include_social_value}
                  onValueChange={(include_social_value) => setTemplateForm((form) => ({ ...form, include_social_value }))}
                >
                  {t('municipal_reports.templates.fields.include_social_value')}
                </Switch>
              </div>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose} isDisabled={savingTemplate}>
              {t('municipal_reports.templates.cancel')}
            </Button>
            <Button color="primary" startContent={<Save size={16} />} onPress={saveTemplate} isLoading={savingTemplate}>
              {t('municipal_reports.templates.save')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

// ============================================================================
// Audience-specific narrative sections (admin English-only)
// ============================================================================

type Period = { from: string; to: string };

function NarrativeShell({
  isActive,
  title,
  description,
  children,
}: {
  isActive: boolean;
  title: string;
  description: string;
  children: ReactNode;
}) {
  const { t } = useTranslation('admin');

  return (
    <Card shadow="sm" className={isActive ? 'border-2 border-primary/40' : 'opacity-70'}>
      <CardHeader className="flex flex-col items-start gap-1">
        <div className="flex items-center gap-2">
          <h2 className="text-base font-semibold">{title}</h2>
          {isActive && (
            <Chip size="sm" color="primary" variant="flat">
              {t('municipal_reports.narrative.in_export')}
            </Chip>
          )}
        </div>
        <p className="text-sm text-default-500">{description}</p>
      </CardHeader>
      <Divider />
      <CardBody className="gap-3">{children}</CardBody>
    </Card>
  );
}

function CantonNarrativeSection({
  isActive,
  variant,
  currency,
  tenantName,
  period,
}: {
  isActive: boolean;
  variant?: CantonVariant;
  currency: string;
  tenantName: string | null;
  period: Period;
}) {
  const { t } = useTranslation('admin');
  const formatter = useMemo(
    () => new Intl.NumberFormat(undefined, { style: 'currency', currency, maximumFractionDigits: 0 }),
    [currency],
  );

  if (!variant) {
    return (
      <NarrativeShell
        isActive={isActive}
        title={t('municipal_reports.narrative.canton.empty_title')}
        description={t('municipal_reports.narrative.canton.empty_description')}
      >
        <p className="text-sm text-default-500">
          {t('municipal_reports.narrative.canton.empty_body')}
        </p>
      </NarrativeShell>
    );
  }

  const yoy = variant.yoy_change_percent;
  const yoyChip =
    yoy === null ? (
      <Chip size="sm" variant="flat">{t('municipal_reports.narrative.no_prior_data')}</Chip>
    ) : (
      <Chip size="sm" color={yoy >= 0 ? 'success' : 'warning'} variant="flat">
        {yoy >= 0 ? '+' : ''}
        {yoy.toFixed(1)}% YoY
      </Chip>
    );

  return (
    <NarrativeShell
      isActive={isActive}
      title={t('municipal_reports.narrative.canton.title', {
        tenant: tenantName ? ` - ${tenantName}` : '',
        from: period.from,
        to: period.to,
      })}
      description={t('municipal_reports.narrative.canton.description')}
    >
      <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
        <div className="rounded-lg border border-default-200 p-3">
          <p className="text-xs uppercase text-default-500">{t('municipal_reports.narrative.canton.municipalities_reporting')}</p>
          <p className="mt-1 text-2xl font-semibold">{variant.aggregate_municipalities_count.toLocaleString()}</p>
          <p className="text-xs text-default-500 mt-1">
            {t('municipal_reports.narrative.canton.municipalities_reporting_desc')}
          </p>
        </div>
        <div className="rounded-lg border border-default-200 p-3">
          <p className="text-xs uppercase text-default-500">{t('municipal_reports.narrative.canton.multi_node_total_hours')}</p>
          <p className="mt-1 text-2xl font-semibold">
            {variant.multi_node_total_hours.toLocaleString(undefined, { maximumFractionDigits: 1 })}
          </p>
          <p className="text-xs text-default-500 mt-1">{t('municipal_reports.narrative.canton.multi_node_total_hours_desc')}</p>
        </div>
        <div className="rounded-lg border border-default-200 p-3">
          <p className="text-xs uppercase text-default-500">{t('municipal_reports.narrative.canton.estimated_cost_avoidance')}</p>
          <p className="mt-1 text-2xl font-semibold">{formatter.format(variant.est_cost_avoidance_chf)}</p>
          <p className="text-xs text-default-500 mt-1">
            {t('municipal_reports.narrative.canton.estimated_cost_avoidance_desc', {
              multiplier: variant.cost_avoidance_multiplier.toFixed(1),
            })}
          </p>
        </div>
      </div>
      <div className="rounded-lg border border-default-200 p-3">
        <div className="flex items-center justify-between">
          <p className="text-sm font-semibold">{t('municipal_reports.narrative.canton.yoy_change')}</p>
          {yoyChip}
        </div>
        <p className="mt-1 text-xs text-default-500">
          {t('municipal_reports.narrative.canton.prior_period_detail', {
            from: variant.yoy_prior_period.from,
            to: variant.yoy_prior_period.to,
            hours: variant.yoy_prior_hours.toLocaleString(undefined, { maximumFractionDigits: 1 }),
          })}
        </p>
      </div>
    </NarrativeShell>
  );
}

function MunicipalityNarrativeSection({
  isActive,
  variant,
  period,
}: {
  isActive: boolean;
  variant?: MunicipalityVariant;
  period: Period;
}) {
  const { t } = useTranslation('admin');

  if (!variant) {
    return (
      <NarrativeShell
        isActive={isActive}
        title={t('municipal_reports.narrative.municipality.empty_title')}
        description={t('municipal_reports.narrative.municipality.empty_description')}
      >
        <p className="text-sm text-default-500">
          {t('municipal_reports.narrative.municipality.empty_body')}
        </p>
      </NarrativeShell>
    );
  }

  return (
    <NarrativeShell
      isActive={isActive}
      title={t('municipal_reports.narrative.municipality.title', { from: period.from, to: period.to })}
      description={t('municipal_reports.narrative.municipality.description')}
    >
      <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
        <div className="rounded-lg border border-default-200 p-3">
          <p className="text-xs uppercase text-default-500">{t('municipal_reports.narrative.municipality.partner_organisations')}</p>
          <p className="mt-1 text-2xl font-semibold">{variant.partner_organisations_count}</p>
          <p className="text-xs text-default-500 mt-1">
            {t('municipal_reports.narrative.municipality.trusted_total', { count: variant.trusted_organisations_total })}
          </p>
        </div>
        <div className="rounded-lg border border-default-200 p-3">
          <p className="text-xs uppercase text-default-500">{t('municipal_reports.narrative.municipality.recipients_reached')}</p>
          <p className="mt-1 text-2xl font-semibold">{variant.recipients_reached_count.toLocaleString()}</p>
          <p className="text-xs text-default-500 mt-1">{t('municipal_reports.narrative.municipality.recipients_reached_desc')}</p>
        </div>
        <div className="rounded-lg border border-default-200 p-3">
          <p className="text-xs uppercase text-default-500">{t('municipal_reports.narrative.municipality.top_categories')}</p>
          <p className="mt-1 text-2xl font-semibold">{variant.geographic_distribution.length}</p>
          <p className="text-xs text-default-500 mt-1">{t('municipal_reports.narrative.municipality.top_categories_desc')}</p>
        </div>
      </div>

      <div>
        <p className="text-sm font-semibold mb-2">{t('municipal_reports.narrative.municipality.partner_organisations_by_hours')}</p>
        {variant.partner_organisations.length === 0 ? (
          <p className="text-sm text-default-500">{t('municipal_reports.narrative.municipality.no_partner_activity')}</p>
        ) : (
          <div className="space-y-1">
            {variant.partner_organisations.map((org) => (
              <div key={org.id} className="flex items-center justify-between rounded border border-default-200 px-3 py-2">
                <div>
                  <p className="text-sm font-medium">{org.name}</p>
                  <p className="text-xs text-default-500">{t('municipal_reports.narrative.logs_count', { count: org.log_count })}</p>
                </div>
                <Chip size="sm" variant="flat" color="success">
                  {t('municipal_reports.values.hours', { count: org.hours.toLocaleString(undefined, { maximumFractionDigits: 1 }) })}
                </Chip>
              </div>
            ))}
          </div>
        )}
      </div>

      <div>
        <p className="text-sm font-semibold mb-2">{t('municipal_reports.narrative.municipality.top_5_categories')}</p>
        {variant.geographic_distribution.length === 0 ? (
          <p className="text-sm text-default-500">{t('municipal_reports.narrative.municipality.no_category_data')}</p>
        ) : (
          <div className="space-y-1">
            {variant.geographic_distribution.map((cat) => (
              <div key={cat.name} className="flex items-center justify-between rounded border border-default-200 px-3 py-2">
                <div>
                  <p className="text-sm font-medium">{cat.name}</p>
                  <p className="text-xs text-default-500">{t('municipal_reports.values.activities', { count: cat.count })}</p>
                </div>
                <Chip size="sm" variant="flat" color="primary">
                  {t('municipal_reports.values.hours', { count: cat.hours.toLocaleString(undefined, { maximumFractionDigits: 1 }) })}
                </Chip>
              </div>
            ))}
          </div>
        )}
      </div>
    </NarrativeShell>
  );
}

function CooperativeNarrativeSection({
  isActive,
  variant,
  period,
}: {
  isActive: boolean;
  variant?: CooperativeVariant;
  period: Period;
}) {
  const { t } = useTranslation('admin');

  if (!variant) {
    return (
      <NarrativeShell
        isActive={isActive}
        title={t('municipal_reports.narrative.cooperative.empty_title')}
        description={t('municipal_reports.narrative.cooperative.empty_description')}
      >
        <p className="text-sm text-default-500">
          {t('municipal_reports.narrative.cooperative.empty_body')}
        </p>
      </NarrativeShell>
    );
  }

  const retentionPct = (variant.member_retention_rate * 100).toFixed(1);
  const reciprocityPct = (variant.reciprocity_rate * 100).toFixed(1);

  return (
    <NarrativeShell
      isActive={isActive}
      title={t('municipal_reports.narrative.cooperative.title', { from: period.from, to: period.to })}
      description={t('municipal_reports.narrative.cooperative.description')}
    >
      <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
        <div className="rounded-lg border border-default-200 p-3">
          <p className="text-xs uppercase text-default-500">{t('municipal_reports.narrative.cooperative.member_retention')}</p>
          <p className="mt-1 text-2xl font-semibold">{retentionPct}%</p>
          <p className="text-xs text-default-500 mt-1">
            {t('municipal_reports.narrative.cooperative.member_retention_desc', { count: variant.retained_members_count })}
          </p>
        </div>
        <div className="rounded-lg border border-default-200 p-3">
          <p className="text-xs uppercase text-default-500">{t('municipal_reports.narrative.cooperative.reciprocity_rate')}</p>
          <p className="mt-1 text-2xl font-semibold">{reciprocityPct}%</p>
          <p className="text-xs text-default-500 mt-1">
            {t('municipal_reports.narrative.cooperative.reciprocity_rate_desc', { count: variant.reciprocal_members_count })}
          </p>
        </div>
        <div className="rounded-lg border border-default-200 p-3">
          <p className="text-xs uppercase text-default-500">{t('municipal_reports.narrative.cooperative.active_tandems')}</p>
          <p className="mt-1 text-2xl font-semibold">{variant.tandem_count}</p>
          <p className="text-xs text-default-500 mt-1">{t('municipal_reports.narrative.cooperative.active_tandems_desc')}</p>
        </div>
        <div className="rounded-lg border border-default-200 p-3">
          <p className="text-xs uppercase text-default-500">{t('municipal_reports.narrative.cooperative.coordinator_load')}</p>
          <p className="mt-1 text-2xl font-semibold">{variant.coordinator_load_avg.toFixed(1)}</p>
          <p className="text-xs text-default-500 mt-1">
            {t('municipal_reports.narrative.cooperative.coordinator_load_desc', {
              pending: variant.pending_reviews_total,
              coordinators: variant.coordinator_count,
            })}
          </p>
        </div>
        <div className="rounded-lg border border-default-200 p-3 md:col-span-2">
          <p className="text-xs uppercase text-default-500">{t('municipal_reports.narrative.cooperative.future_care_credit_pool')}</p>
          <p className="mt-1 text-2xl font-semibold">
            {t('municipal_reports.values.hours', {
              count: variant.future_care_credit_pool.toLocaleString(undefined, { maximumFractionDigits: 1 }),
            })}
          </p>
          <p className="text-xs text-default-500 mt-1">
            {t('municipal_reports.narrative.cooperative.future_care_credit_pool_desc', {
              count: variant.active_members_total.toLocaleString(),
            })}
          </p>
        </div>
      </div>
    </NarrativeShell>
  );
}
