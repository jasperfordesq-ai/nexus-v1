// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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
import { PageHeader, StatCard } from '../../components';

const reportCards = [
  { key: 'verified_hours', icon: Clock, href: '/admin/reports/hours', statKey: 'verified_hours' },
  { key: 'active_members', icon: Users, href: '/admin/reports/members', statKey: 'active_members' },
  { key: 'organisations', icon: Building2, href: '/admin/volunteering/organizations', statKey: 'trusted_organisations' },
  { key: 'trust_pack', icon: ShieldCheck, href: '/admin/safeguarding', statKey: 'pending_hours' },
] as const;

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

async function downloadMunicipalExport(format: 'csv' | 'pdf', filename: string, templateId?: number) {
  const headers: Record<string, string> = {};
  const token = tokenManager.getAccessToken();
  const tenantId = tokenManager.getTenantId();
  if (token) headers.Authorization = `Bearer ${token}`;
  if (tenantId) headers['X-Tenant-ID'] = tenantId;

  const params = new URLSearchParams({ format });
  if (templateId) params.set('template_id', String(templateId));

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
  const [loading, setLoading] = useState(true);
  const [templatesLoading, setTemplatesLoading] = useState(true);
  const [savingTemplate, setSavingTemplate] = useState(false);
  const [deletingTemplateId, setDeletingTemplateId] = useState<number | null>(null);
  const [exporting, setExporting] = useState<'csv' | 'pdf' | null>(null);

  const loadSummary = useCallback(async () => {
    setLoading(true);
    try {
      const endpoint = selectedTemplateId
        ? `/v2/admin/reports/municipal-impact?template_id=${selectedTemplateId}`
        : '/v2/admin/reports/municipal-impact';
      const res = await api.get<MunicipalImpactSummary>(endpoint);
      if (isMunicipalImpactSummary(res.data)) setSummary(res.data);
    } catch {
      toast.error(t('municipal_reports.toast.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [selectedTemplateId, t, toast]);

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
  const formatHours = (value: number) => t('municipal_reports.values.hours', { count: value.toLocaleString(undefined, { maximumFractionDigits: 1 }) });

  const handleExport = async (format: 'csv' | 'pdf') => {
    setExporting(format);
    try {
      await downloadMunicipalExport(format, `municipal-impact-pack.${format}`, selectedTemplateId ?? undefined);
      toast.success(t('municipal_reports.toast.export_started'));
    } catch {
      toast.error(t('municipal_reports.toast.export_failed'));
    } finally {
      setExporting(null);
    }
  };

  const selectedTemplate = templates.find((template) => template.id === selectedTemplateId) ?? null;

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
          <CardBody className="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div>
              <p className="text-xs font-medium uppercase text-default-500">{t('municipal_reports.period')}</p>
              <p className="mt-1 text-sm font-semibold text-default-800">{summary.period.from} - {summary.period.to}</p>
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
            >
              <SelectItem key="default">{t('municipal_reports.templates.default_policy')}</SelectItem>
              {templates.map((template) => (
                <SelectItem key={String(template.id)}>
                  {template.name}
                </SelectItem>
              ))}
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

      <Modal isOpen={isOpen} onClose={onClose} size="2xl">
        <ModalContent>
          <ModalHeader>{t('municipal_reports.templates.modal_title')}</ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label={t('municipal_reports.templates.fields.name')}
              value={templateForm.name}
              onValueChange={(name) => setTemplateForm((form) => ({ ...form, name }))}
              isRequired
            />
            <Textarea
              label={t('municipal_reports.templates.fields.description')}
              value={templateForm.description}
              onValueChange={(description) => setTemplateForm((form) => ({ ...form, description }))}
              minRows={2}
            />
            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
              <Select
                label={t('municipal_reports.templates.fields.audience')}
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
