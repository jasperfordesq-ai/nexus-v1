// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button, Card, CardBody, CardHeader, Chip, Divider, Input, Select, SelectItem, Spinner, Switch } from '@heroui/react';
import { Link } from 'react-router-dom';
import Building2 from 'lucide-react/icons/building-2';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import ClipboardCheck from 'lucide-react/icons/clipboard-check';
import Clock from 'lucide-react/icons/clock';
import FileText from 'lucide-react/icons/file-text';
import HeartHandshake from 'lucide-react/icons/heart-handshake';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Save from 'lucide-react/icons/save';
import ShieldCheck from 'lucide-react/icons/shield-check';
import TriangleAlert from 'lucide-react/icons/triangle-alert';
import UserPlus from 'lucide-react/icons/user-plus';
import Users from 'lucide-react/icons/users';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader, StatCard } from '../../components';

type PendingReview = {
  id: number;
  member_name: string;
  organisation_name: string;
  opportunity_title: string;
  hours: number;
  date_logged: string;
  created_at: string;
  age_days: number;
  is_overdue: boolean;
  is_escalated: boolean;
  assigned_to: number | null;
  assigned_name: string | null;
  assigned_at: string | null;
  escalated_at: string | null;
  escalation_note: string | null;
};

type RecentDecision = {
  id: number;
  member_name: string;
  organisation_name: string;
  hours: number;
  status: 'approved' | 'declined';
  decided_at: string;
};

type WorkflowSummary = {
  stats: {
    pending_count: number;
    pending_hours: number;
    overdue_count: number;
    escalated_count: number;
    approved_30d_hours: number;
    declined_30d_count: number;
    coordinator_count: number;
  };
  pending_reviews: PendingReview[];
  recent_decisions: RecentDecision[];
  coordinator_signals: {
    active_requests: number;
    active_offers: number;
    trusted_organisations: number;
  };
  coordinators: Coordinator[];
  role_pack?: RolePack;
  policy?: WorkflowPolicy;
};

type Coordinator = {
  id: number;
  name: string;
  role: string;
};

type RolePack = {
  available: boolean;
  installed_count: number;
  total_count: number;
  presets: RolePresetStatus[];
};

type RolePresetStatus = {
  key: string;
  role_name: string;
  role_id: number | null;
  installed: boolean;
  permission_count: number;
  installed_permissions: number;
};

type WorkflowPolicy = {
  approval_required: boolean;
  auto_approve_trusted_reviewers: boolean;
  review_sla_days: number;
  escalation_sla_days: number;
  allow_member_self_log: boolean;
  require_organisation_for_partner_hours: boolean;
  monthly_statement_day: number;
  municipal_report_default_period: string;
  include_social_value_estimate: boolean;
  default_hour_value_chf: number;
};

const workflowStages = [
  { key: 'intake', icon: HeartHandshake },
  { key: 'match', icon: Users },
  { key: 'log', icon: Clock },
  { key: 'verify', icon: ClipboardCheck },
  { key: 'statement', icon: FileText },
] as const;

const rolePresets = [
  { key: 'national_admin', icon: ShieldCheck },
  { key: 'canton_admin', icon: Building2 },
  { key: 'municipality_admin', icon: Building2 },
  { key: 'cooperative_coordinator', icon: HeartHandshake },
  { key: 'organisation_coordinator', icon: Users },
  { key: 'trusted_reviewer', icon: ClipboardCheck },
] as const;

const reportPeriods = ['last_30_days', 'last_90_days', 'year_to_date', 'previous_quarter'] as const;

function isWorkflowSummary(value: unknown): value is WorkflowSummary {
  return Boolean(value && typeof value === 'object' && 'stats' in value && 'pending_reviews' in value);
}

export default function CaringCommunityWorkflowPage() {
  const { t } = useTranslation('admin');
  const { tenantPath } = useTenant();
  const toast = useToast();
  usePageTitle(t('caring_workflow.meta.title'));

  const [summary, setSummary] = useState<WorkflowSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [installingRoles, setInstallingRoles] = useState(false);
  const [savingPolicy, setSavingPolicy] = useState(false);
  const [assigningReviewId, setAssigningReviewId] = useState<number | null>(null);
  const [escalatingReviewId, setEscalatingReviewId] = useState<number | null>(null);

  const loadWorkflow = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<WorkflowSummary>('/v2/admin/caring-community/workflow');
      if (isWorkflowSummary(res.data)) setSummary(res.data);
    } catch {
      toast.error(t('caring_workflow.toast.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [t, toast]);

  useEffect(() => {
    loadWorkflow();
  }, [loadWorkflow]);

  const stats = summary?.stats;
  const signals = summary?.coordinator_signals;
  const rolePack = summary?.role_pack;
  const formatHours = (value: number) => t('municipal_reports.values.hours', { count: value.toLocaleString(undefined, { maximumFractionDigits: 1 }) });

  const roleCountLabel = useMemo(() => (
    rolePack
      ? t('caring_workflow.roles.installed_count', { installed: rolePack.installed_count, total: rolePack.total_count })
      : t('caring_workflow.stats.role_presets_count', { count: rolePresets.length })
  ), [rolePack, t]);

  const roleStatusByKey = useMemo(() => {
    const statuses = rolePack?.presets ?? [];
    return new Map(statuses.map((status) => [status.key, status]));
  }, [rolePack]);

  const replaceReview = useCallback((review: PendingReview) => {
    setSummary((current) => {
      if (!current) return current;
      return {
        ...current,
        pending_reviews: current.pending_reviews.map((item) => item.id === review.id ? review : item),
      };
    });
  }, []);

  const updatePolicyField = useCallback(<K extends keyof WorkflowPolicy>(key: K, value: WorkflowPolicy[K]) => {
    setSummary((current) => {
      if (!current?.policy) return current;
      return { ...current, policy: { ...current.policy, [key]: value } };
    });
  }, []);

  const installRolePack = useCallback(async () => {
    setInstallingRoles(true);
    try {
      const res = await api.post<RolePack>('/v2/admin/caring-community/role-presets/install', {});
      setSummary((current) => current ? { ...current, role_pack: res.data } : current);
      toast.success(t('caring_workflow.roles.install_success'));
    } catch {
      toast.error(t('caring_workflow.roles.install_failed'));
    } finally {
      setInstallingRoles(false);
    }
  }, [t, toast]);

  const savePolicy = useCallback(async () => {
    if (!summary?.policy) return;

    setSavingPolicy(true);
    try {
      const res = await api.put<WorkflowPolicy>('/v2/admin/caring-community/workflow/policy', summary.policy);
      setSummary((current) => current ? { ...current, policy: res.data } : current);
      toast.success(t('caring_workflow.policy.save_success'));
    } catch {
      toast.error(t('caring_workflow.policy.save_failed'));
    } finally {
      setSavingPolicy(false);
    }
  }, [summary?.policy, t, toast]);

  const assignReview = useCallback(async (reviewId: number, assignedTo: number | null) => {
    setAssigningReviewId(reviewId);
    try {
      const res = await api.put<{ review: PendingReview }>(`/v2/admin/caring-community/workflow/reviews/${reviewId}/assign`, {
        assigned_to: assignedTo,
      });
      if (res.data?.review) replaceReview(res.data.review);
      toast.success(t('caring_workflow.review_queue.assign_success'));
    } catch {
      toast.error(t('caring_workflow.review_queue.assign_failed'));
    } finally {
      setAssigningReviewId(null);
    }
  }, [replaceReview, t, toast]);

  const escalateReview = useCallback(async (review: PendingReview) => {
    setEscalatingReviewId(review.id);
    try {
      const res = await api.put<{ review: PendingReview }>(`/v2/admin/caring-community/workflow/reviews/${review.id}/escalate`, {
        note: t('caring_workflow.review_queue.manual_escalation_note', { count: review.age_days }),
      });
      if (res.data?.review) replaceReview(res.data.review);
      toast.success(t('caring_workflow.review_queue.escalate_success'));
    } catch {
      toast.error(t('caring_workflow.review_queue.escalate_failed'));
    } finally {
      setEscalatingReviewId(null);
    }
  }, [replaceReview, t, toast]);

  if (loading) {
    return (
      <div className="flex min-h-[400px] items-center justify-center">
        <Spinner size="lg" label={t('caring_workflow.loading')} />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-7xl px-4 pb-8">
      <PageHeader
        title={t('caring_workflow.meta.title')}
        description={t('caring_workflow.meta.description')}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Button
              as={Link}
              to={tenantPath('/admin/volunteering/hours')}
              variant="flat"
              size="sm"
              startContent={<ClipboardCheck size={16} />}
            >
              {t('caring_workflow.actions.open_hour_review')}
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/reports/municipal-impact')}
              variant="flat"
              size="sm"
              startContent={<FileText size={16} />}
            >
              {t('caring_workflow.actions.open_report_pack')}
            </Button>
            <Button
              variant="flat"
              size="sm"
              startContent={<RefreshCw size={16} />}
              onPress={loadWorkflow}
            >
              {t('caring_workflow.actions.refresh')}
            </Button>
          </div>
        }
      />

      <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
        <StatCard label={t('caring_workflow.stats.pending_reviews')} value={(stats?.pending_count ?? 0).toLocaleString()} icon={ClipboardCheck} color="warning" />
        <StatCard label={t('caring_workflow.stats.pending_hours')} value={formatHours(stats?.pending_hours ?? 0)} icon={Clock} color="primary" />
        <StatCard label={t('caring_workflow.stats.approved_30d')} value={formatHours(stats?.approved_30d_hours ?? 0)} icon={CheckCircle2} color="success" />
        <StatCard label={t('caring_workflow.stats.coordinators')} value={(stats?.coordinator_count ?? 0).toLocaleString()} icon={Users} color="secondary" />
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
        <Card shadow="sm">
          <CardHeader className="flex items-start justify-between gap-4">
            <div>
              <h2 className="text-lg font-semibold">{t('caring_workflow.review_queue.title')}</h2>
              <p className="mt-1 text-sm text-default-500">{t('caring_workflow.review_queue.description')}</p>
            </div>
            {(stats?.overdue_count ?? 0) > 0 && (
              <div className="flex flex-wrap gap-2">
                {(stats?.escalated_count ?? 0) > 0 && (
                  <Chip color="danger" variant="flat">{t('caring_workflow.review_queue.escalated', { count: stats?.escalated_count ?? 0 })}</Chip>
                )}
                <Chip color="warning" variant="flat">{t('caring_workflow.review_queue.overdue', { count: stats?.overdue_count ?? 0 })}</Chip>
              </div>
            )}
          </CardHeader>
          <Divider />
          <CardBody className="gap-3">
            {summary?.pending_reviews.length === 0 ? (
              <div className="rounded-lg bg-success/10 p-4 text-sm text-success-700">
                {t('caring_workflow.review_queue.empty')}
              </div>
            ) : summary?.pending_reviews.map((review) => (
              <div key={review.id} className="rounded-lg border border-default-200 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-default-900">{review.member_name}</p>
                    <p className="mt-1 text-sm text-default-500">
                      {review.organisation_name || review.opportunity_title || t('caring_workflow.review_queue.unassigned')}
                    </p>
                  </div>
                  <div className="flex items-center gap-2">
                    {review.is_escalated && <Chip size="sm" color="danger" variant="flat">{t('caring_workflow.review_queue.escalate_now')}</Chip>}
                    {!review.is_escalated && review.is_overdue && <Chip size="sm" color="warning" variant="flat">{t('caring_workflow.review_queue.needs_review')}</Chip>}
                    <Chip size="sm" color="primary" variant="flat">{formatHours(review.hours)}</Chip>
                  </div>
                </div>
                <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-default-500">
                  <span>{t('caring_workflow.review_queue.logged_on', { date: review.date_logged })}</span>
                  <span>{t('caring_workflow.review_queue.submitted_on', { date: review.created_at })}</span>
                  <span>{t('caring_workflow.review_queue.age_days', { count: review.age_days })}</span>
                  {review.assigned_name && <span>{t('caring_workflow.review_queue.assigned_to', { name: review.assigned_name })}</span>}
                  {review.escalated_at && <span>{t('caring_workflow.review_queue.escalated_on', { date: review.escalated_at })}</span>}
                </div>
                <div className="mt-3 grid grid-cols-1 gap-2 md:grid-cols-[minmax(0,1fr)_auto]">
                  <Select
                    size="sm"
                    label={t('caring_workflow.review_queue.assign_label')}
                    selectedKeys={[review.assigned_to ? String(review.assigned_to) : 'unassigned']}
                    isDisabled={assigningReviewId === review.id}
                    onSelectionChange={(keys) => {
                      const selected = Array.from(keys)[0];
                      assignReview(review.id, selected && selected !== 'unassigned' ? Number(selected) : null);
                    }}
                  >
                    <SelectItem key="unassigned">{t('caring_workflow.review_queue.unassigned_coordinator')}</SelectItem>
                    {(summary?.coordinators ?? []).map((coordinator) => (
                      <SelectItem key={String(coordinator.id)}>{coordinator.name}</SelectItem>
                    ))}
                  </Select>
                  <Button
                    size="sm"
                    variant="flat"
                    color={review.is_escalated ? 'danger' : 'warning'}
                    startContent={review.is_escalated ? <TriangleAlert size={16} /> : <UserPlus size={16} />}
                    isLoading={escalatingReviewId === review.id}
                    onPress={() => escalateReview(review)}
                  >
                    {review.is_escalated ? t('caring_workflow.review_queue.re_escalate') : t('caring_workflow.review_queue.escalate')}
                  </Button>
                </div>
              </div>
            ))}
          </CardBody>
        </Card>

        <div className="space-y-6">
          {summary?.policy && (
            <Card shadow="sm">
              <CardHeader className="flex items-start justify-between gap-3">
                <div>
                  <h2 className="text-lg font-semibold">{t('caring_workflow.policy.title')}</h2>
                  <p className="mt-1 text-sm text-default-500">{t('caring_workflow.policy.description')}</p>
                </div>
                <Button
                  color="primary"
                  size="sm"
                  startContent={<Save size={16} />}
                  isLoading={savingPolicy}
                  onPress={savePolicy}
                >
                  {t('caring_workflow.policy.save')}
                </Button>
              </CardHeader>
              <Divider />
              <CardBody className="gap-4">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <Input
                    type="number"
                    min={1}
                    max={30}
                    label={t('caring_workflow.policy.review_sla_days')}
                    value={String(summary.policy.review_sla_days)}
                    onValueChange={(value) => updatePolicyField('review_sla_days', Number(value || 1))}
                  />
                  <Input
                    type="number"
                    min={1}
                    max={60}
                    label={t('caring_workflow.policy.escalation_sla_days')}
                    value={String(summary.policy.escalation_sla_days)}
                    onValueChange={(value) => updatePolicyField('escalation_sla_days', Number(value || 1))}
                  />
                  <Input
                    type="number"
                    min={1}
                    max={28}
                    label={t('caring_workflow.policy.monthly_statement_day')}
                    value={String(summary.policy.monthly_statement_day)}
                    onValueChange={(value) => updatePolicyField('monthly_statement_day', Number(value || 1))}
                  />
                  <Input
                    type="number"
                    min={0}
                    max={500}
                    label={t('caring_workflow.policy.default_hour_value_chf')}
                    value={String(summary.policy.default_hour_value_chf)}
                    onValueChange={(value) => updatePolicyField('default_hour_value_chf', Number(value || 0))}
                  />
                </div>
                <Select
                  label={t('caring_workflow.policy.municipal_report_default_period')}
                  selectedKeys={[summary.policy.municipal_report_default_period]}
                  onSelectionChange={(keys) => updatePolicyField('municipal_report_default_period', Array.from(keys)[0]?.toString() ?? 'last_90_days')}
                >
                  {reportPeriods.map((period) => (
                    <SelectItem key={period}>{t(`caring_workflow.policy.periods.${period}`)}</SelectItem>
                  ))}
                </Select>
                <div className="grid grid-cols-1 gap-3">
                  <PolicySwitch
                    label={t('caring_workflow.policy.approval_required')}
                    description={t('caring_workflow.policy.approval_required_description')}
                    value={summary.policy.approval_required}
                    onChange={(value) => updatePolicyField('approval_required', value)}
                  />
                  <PolicySwitch
                    label={t('caring_workflow.policy.auto_approve_trusted_reviewers')}
                    description={t('caring_workflow.policy.auto_approve_trusted_reviewers_description')}
                    value={summary.policy.auto_approve_trusted_reviewers}
                    onChange={(value) => updatePolicyField('auto_approve_trusted_reviewers', value)}
                  />
                  <PolicySwitch
                    label={t('caring_workflow.policy.allow_member_self_log')}
                    description={t('caring_workflow.policy.allow_member_self_log_description')}
                    value={summary.policy.allow_member_self_log}
                    onChange={(value) => updatePolicyField('allow_member_self_log', value)}
                  />
                  <PolicySwitch
                    label={t('caring_workflow.policy.require_organisation_for_partner_hours')}
                    description={t('caring_workflow.policy.require_organisation_for_partner_hours_description')}
                    value={summary.policy.require_organisation_for_partner_hours}
                    onChange={(value) => updatePolicyField('require_organisation_for_partner_hours', value)}
                  />
                  <PolicySwitch
                    label={t('caring_workflow.policy.include_social_value_estimate')}
                    description={t('caring_workflow.policy.include_social_value_estimate_description')}
                    value={summary.policy.include_social_value_estimate}
                    onChange={(value) => updatePolicyField('include_social_value_estimate', value)}
                  />
                </div>
              </CardBody>
            </Card>
          )}

          <Card shadow="sm">
            <CardHeader>
              <div>
                <h2 className="text-lg font-semibold">{t('caring_workflow.signals.title')}</h2>
                <p className="mt-1 text-sm text-default-500">{t('caring_workflow.signals.description')}</p>
              </div>
            </CardHeader>
            <Divider />
            <CardBody className="gap-3">
              <SignalRow label={t('caring_workflow.signals.active_requests')} value={signals?.active_requests ?? 0} />
              <SignalRow label={t('caring_workflow.signals.active_offers')} value={signals?.active_offers ?? 0} />
              <SignalRow label={t('caring_workflow.signals.trusted_organisations')} value={signals?.trusted_organisations ?? 0} />
              <SignalRow label={t('caring_workflow.signals.declined_30d')} value={stats?.declined_30d_count ?? 0} />
            </CardBody>
          </Card>

          <Card shadow="sm">
            <CardHeader className="flex items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-semibold">{t('caring_workflow.roles.title')}</h2>
                <p className="mt-1 text-sm text-default-500">{roleCountLabel}</p>
              </div>
              <Button
                color="primary"
                size="sm"
                variant="flat"
                isLoading={installingRoles}
                onPress={installRolePack}
              >
                {installingRoles ? t('caring_workflow.roles.installing') : t('caring_workflow.roles.install_pack')}
              </Button>
            </CardHeader>
            <Divider />
            <CardBody className="gap-3">
              {rolePresets.map((role) => {
                const Icon = role.icon;
                const status = roleStatusByKey.get(role.key);
                return (
                  <div key={role.key} className="flex items-start gap-3 rounded-lg border border-default-200 p-3">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                      <Icon size={18} />
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="text-sm font-semibold text-default-900">{t(`caring_workflow.roles.${role.key}.title`)}</p>
                        <Chip size="sm" color={status?.installed ? 'success' : 'default'} variant="flat">
                          {status?.installed ? t('caring_workflow.roles.installed_chip') : t('caring_workflow.roles.not_installed_chip')}
                        </Chip>
                      </div>
                      <p className="mt-1 text-xs text-default-500">{t(`caring_workflow.roles.${role.key}.description`)}</p>
                      {status && (
                        <p className="mt-2 text-xs text-default-400">
                          {t('caring_workflow.roles.permissions_status', {
                            installed: status.installed_permissions,
                            total: status.permission_count,
                          })}
                        </p>
                      )}
                    </div>
                  </div>
                );
              })}
            </CardBody>
          </Card>
        </div>
      </div>

      <Card className="mt-6" shadow="sm">
        <CardHeader>
          <div>
            <h2 className="text-lg font-semibold">{t('caring_workflow.stages.title')}</h2>
            <p className="mt-1 text-sm text-default-500">{t('caring_workflow.stages.description')}</p>
          </div>
        </CardHeader>
        <Divider />
        <CardBody className="grid grid-cols-1 gap-3 md:grid-cols-5">
          {workflowStages.map((stage, index) => {
            const Icon = stage.icon;
            return (
              <div key={stage.key} className="rounded-lg border border-default-200 p-3">
                <div className="mb-3 flex items-center justify-between">
                  <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-secondary/10 text-secondary">
                    <Icon size={18} />
                  </div>
                  <Chip size="sm" variant="flat">{index + 1}</Chip>
                </div>
                <p className="text-sm font-semibold text-default-900">{t(`caring_workflow.stages.${stage.key}.title`)}</p>
                <p className="mt-1 text-xs text-default-500">{t(`caring_workflow.stages.${stage.key}.description`)}</p>
              </div>
            );
          })}
        </CardBody>
      </Card>
    </div>
  );
}

function SignalRow({ label, value }: { label: string; value: number }) {
  return (
    <div className="flex items-center justify-between gap-3 rounded-lg bg-default-100 px-3 py-2">
      <span className="text-sm text-default-600">{label}</span>
      <span className="text-sm font-semibold text-default-900">{value.toLocaleString()}</span>
    </div>
  );
}

function PolicySwitch({ label, description, value, onChange }: { label: string; description: string; value: boolean; onChange: (value: boolean) => void }) {
  return (
    <div className="flex items-center justify-between gap-4 rounded-lg border border-default-200 p-3">
      <div>
        <p className="text-sm font-semibold text-default-900">{label}</p>
        <p className="mt-1 text-xs text-default-500">{description}</p>
      </div>
      <Switch isSelected={value} onValueChange={onChange} aria-label={label} />
    </div>
  );
}
