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
import Download from 'lucide-react/icons/download';
import FileText from 'lucide-react/icons/file-text';
import HeartHandshake from 'lucide-react/icons/heart-handshake';
import Pause from 'lucide-react/icons/pause';
import Plus from 'lucide-react/icons/plus';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Save from 'lucide-react/icons/save';
import Search from 'lucide-react/icons/search';
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

type MemberStatement = {
  user: {
    id: number;
    name: string;
    email: string;
    current_balance: number;
  };
  period: {
    start: string;
    end: string;
    statement_day: number;
  };
  summary: {
    approved_support_hours: number;
    pending_support_hours: number;
    wallet_hours_earned: number;
    wallet_hours_spent: number;
    wallet_net_change: number;
    current_balance: number;
    estimated_social_value_chf: number;
  };
  support_hours_by_organisation: Array<{
    organisation_name: string;
    approved_hours: number;
    pending_hours: number;
    log_count: number;
  }>;
};

type MemberStatementCsv = {
  filename: string;
  csv: string;
  statement: MemberStatement;
};

type SupportRelationship = {
  id: number;
  supporter: { id: number; name: string };
  recipient: { id: number; name: string };
  coordinator: { id: number; name: string } | null;
  organization_name: string;
  category_name: string;
  title: string;
  description: string;
  frequency: 'weekly' | 'fortnightly' | 'monthly' | 'ad_hoc';
  expected_hours: number;
  start_date: string;
  end_date: string | null;
  status: 'active' | 'paused' | 'completed' | 'cancelled';
  last_logged_at: string | null;
  next_check_in_at: string | null;
};

type SupportRelationshipList = {
  stats: {
    active_count: number;
    paused_count: number;
    check_ins_due: number;
    expected_active_hours: number;
  };
  items: SupportRelationship[];
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
const relationshipFrequencies = ['weekly', 'fortnightly', 'monthly', 'ad_hoc'] as const;

function isWorkflowSummary(value: unknown): value is WorkflowSummary {
  return Boolean(value && typeof value === 'object' && 'stats' in value && 'pending_reviews' in value);
}

function isSupportRelationshipList(value: unknown): value is SupportRelationshipList {
  return Boolean(value && typeof value === 'object' && 'stats' in value && 'items' in value);
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
  const [statementMemberId, setStatementMemberId] = useState('');
  const [statementStartDate, setStatementStartDate] = useState('');
  const [statementEndDate, setStatementEndDate] = useState('');
  const [memberStatement, setMemberStatement] = useState<MemberStatement | null>(null);
  const [loadingStatement, setLoadingStatement] = useState(false);
  const [relationships, setRelationships] = useState<SupportRelationshipList | null>(null);
  const [loadingRelationships, setLoadingRelationships] = useState(false);
  const [savingRelationship, setSavingRelationship] = useState(false);
  const [relationshipSupporterId, setRelationshipSupporterId] = useState('');
  const [relationshipRecipientId, setRelationshipRecipientId] = useState('');
  const [relationshipTitle, setRelationshipTitle] = useState('');
  const [relationshipFrequency, setRelationshipFrequency] = useState<SupportRelationship['frequency']>('weekly');
  const [relationshipExpectedHours, setRelationshipExpectedHours] = useState('1');
  const [relationshipStartDate, setRelationshipStartDate] = useState('');
  const [updatingRelationshipId, setUpdatingRelationshipId] = useState<number | null>(null);
  const [loggingRelationshipId, setLoggingRelationshipId] = useState<number | null>(null);
  const [relationshipLogDate, setRelationshipLogDate] = useState('');
  const [relationshipLogHours, setRelationshipLogHours] = useState('');
  const [relationshipLogDescription, setRelationshipLogDescription] = useState('');

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

  const loadSupportRelationships = useCallback(async () => {
    setLoadingRelationships(true);
    try {
      const res = await api.get<SupportRelationshipList>('/v2/admin/caring-community/support-relationships?status=all');
      if (isSupportRelationshipList(res.data)) setRelationships(res.data);
    } catch {
      toast.error(t('caring_workflow.support_relationships.load_failed'));
    } finally {
      setLoadingRelationships(false);
    }
  }, [t, toast]);

  useEffect(() => {
    loadSupportRelationships();
  }, [loadSupportRelationships]);

  const stats = summary?.stats;
  const signals = summary?.coordinator_signals;
  const rolePack = summary?.role_pack;
  const formatHours = (value: number) => t('municipal_reports.values.hours', { count: Number(value.toFixed(1)) });
  const formatChf = (value: number) => t('caring_workflow.member_statement.chf_value', { value: value.toLocaleString(undefined, { maximumFractionDigits: 0 }) });

  const roleCountLabel = useMemo(() => (
    rolePack
      ? t('caring_workflow.roles.installed_count', { installed: rolePack.installed_count, total: rolePack.total_count })
      : t('caring_workflow.stats.role_presets_count', { count: rolePresets.length })
  ), [rolePack, t]);

  const roleStatusByKey = useMemo(() => {
    const statuses = rolePack?.presets ?? [];
    return new Map(statuses.map((status) => [status.key, status]));
  }, [rolePack]);
  const coordinatorOptions = useMemo(() => [
    { id: 'unassigned', label: t('caring_workflow.review_queue.unassigned_coordinator') },
    ...(summary?.coordinators ?? []).map((coordinator) => ({ id: String(coordinator.id), label: coordinator.name })),
  ], [summary?.coordinators, t]);
  const frequencyOptions = useMemo(() => relationshipFrequencies.map((frequency) => ({
    id: frequency,
    label: t(`caring_workflow.support_relationships.frequencies.${frequency}`),
  })), [t]);

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

  const statementQuery = useCallback((format?: 'csv') => {
    const params = new URLSearchParams();
    if (statementStartDate) params.set('start_date', statementStartDate);
    if (statementEndDate) params.set('end_date', statementEndDate);
    if (format) params.set('format', format);
    const query = params.toString();
    return query ? `?${query}` : '';
  }, [statementEndDate, statementStartDate]);

  const loadMemberStatement = useCallback(async () => {
    const memberId = Number(statementMemberId);
    if (!Number.isInteger(memberId) || memberId <= 0) {
      toast.error(t('caring_workflow.member_statement.invalid_member'));
      return;
    }

    setLoadingStatement(true);
    try {
      const res = await api.get<MemberStatement>(`/v2/admin/caring-community/member-statements/${memberId}${statementQuery()}`);
      setMemberStatement(res.data ?? null);
    } catch {
      toast.error(t('caring_workflow.member_statement.load_failed'));
    } finally {
      setLoadingStatement(false);
    }
  }, [statementMemberId, statementQuery, t, toast]);

  const exportMemberStatement = useCallback(async () => {
    const memberId = Number(statementMemberId);
    if (!Number.isInteger(memberId) || memberId <= 0) {
      toast.error(t('caring_workflow.member_statement.invalid_member'));
      return;
    }

    setLoadingStatement(true);
    try {
      const res = await api.get<MemberStatementCsv>(`/v2/admin/caring-community/member-statements/${memberId}${statementQuery('csv')}`);
      if (!res.data) throw new Error('Missing member statement CSV payload');
      setMemberStatement(res.data.statement);
      const blob = new Blob([res.data.csv], { type: 'text/csv;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = res.data.filename;
      anchor.click();
      URL.revokeObjectURL(url);
      toast.success(t('caring_workflow.member_statement.export_success'));
    } catch {
      toast.error(t('caring_workflow.member_statement.export_failed'));
    } finally {
      setLoadingStatement(false);
    }
  }, [statementMemberId, statementQuery, t, toast]);

  const createSupportRelationship = useCallback(async () => {
    const supporterId = Number(relationshipSupporterId);
    const recipientId = Number(relationshipRecipientId);
    if (!Number.isInteger(supporterId) || !Number.isInteger(recipientId) || supporterId <= 0 || recipientId <= 0 || supporterId === recipientId) {
      toast.error(t('caring_workflow.support_relationships.invalid_members'));
      return;
    }

    setSavingRelationship(true);
    try {
      await api.post<SupportRelationship>('/v2/admin/caring-community/support-relationships', {
        supporter_id: supporterId,
        recipient_id: recipientId,
        title: relationshipTitle || undefined,
        frequency: relationshipFrequency,
        expected_hours: Number(relationshipExpectedHours || 1),
        start_date: relationshipStartDate || undefined,
      });
      setRelationshipSupporterId('');
      setRelationshipRecipientId('');
      setRelationshipTitle('');
      setRelationshipExpectedHours('1');
      setRelationshipStartDate('');
      toast.success(t('caring_workflow.support_relationships.create_success'));
      loadSupportRelationships();
    } catch {
      toast.error(t('caring_workflow.support_relationships.create_failed'));
    } finally {
      setSavingRelationship(false);
    }
  }, [
    loadSupportRelationships,
    relationshipExpectedHours,
    relationshipFrequency,
    relationshipRecipientId,
    relationshipStartDate,
    relationshipSupporterId,
    relationshipTitle,
    t,
    toast,
  ]);

  const updateSupportRelationshipStatus = useCallback(async (relationship: SupportRelationship, status: SupportRelationship['status']) => {
    setUpdatingRelationshipId(relationship.id);
    try {
      await api.put<SupportRelationship>(`/v2/admin/caring-community/support-relationships/${relationship.id}`, { status });
      toast.success(t('caring_workflow.support_relationships.update_success'));
      loadSupportRelationships();
    } catch {
      toast.error(t('caring_workflow.support_relationships.update_failed'));
    } finally {
      setUpdatingRelationshipId(null);
    }
  }, [loadSupportRelationships, t, toast]);

  const logSupportRelationshipHours = useCallback(async (relationship: SupportRelationship) => {
    const hours = Number(relationshipLogHours || relationship.expected_hours);
    if (!relationshipLogDate || !Number.isFinite(hours) || hours <= 0 || hours > 24) {
      toast.error(t('caring_workflow.support_relationships.invalid_log'));
      return;
    }

    setLoggingRelationshipId(relationship.id);
    try {
      await api.post(`/v2/admin/caring-community/support-relationships/${relationship.id}/hours`, {
        date: relationshipLogDate,
        hours,
        description: relationshipLogDescription || undefined,
      });
      toast.success(t('caring_workflow.support_relationships.log_success'));
      setRelationshipLogDate('');
      setRelationshipLogHours('');
      setRelationshipLogDescription('');
      loadSupportRelationships();
      loadWorkflow();
    } catch {
      toast.error(t('caring_workflow.support_relationships.log_failed'));
    } finally {
      setLoggingRelationshipId(null);
    }
  }, [
    loadSupportRelationships,
    loadWorkflow,
    relationshipLogDate,
    relationshipLogDescription,
    relationshipLogHours,
    t,
    toast,
  ]);

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
        <div className="space-y-6">
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
                      items={coordinatorOptions}
                    >
                      {(item) => <SelectItem key={item.id}>{item.label}</SelectItem>}
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

          <Card shadow="sm">
            <CardHeader className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-semibold">{t('caring_workflow.support_relationships.title')}</h2>
                <p className="mt-1 text-sm text-default-500">{t('caring_workflow.support_relationships.description')}</p>
              </div>
              <Button
                size="sm"
                variant="flat"
                startContent={<RefreshCw size={16} />}
                isLoading={loadingRelationships}
                onPress={loadSupportRelationships}
              >
                {t('caring_workflow.actions.refresh')}
              </Button>
            </CardHeader>
            <Divider />
            <CardBody className="gap-4">
              <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <SignalRow label={t('caring_workflow.support_relationships.active')} value={relationships?.stats.active_count ?? 0} />
                <SignalRow label={t('caring_workflow.support_relationships.paused')} value={relationships?.stats.paused_count ?? 0} />
                <SignalRow label={t('caring_workflow.support_relationships.check_ins_due')} value={relationships?.stats.check_ins_due ?? 0} />
                <SignalRow label={t('caring_workflow.support_relationships.expected_hours')} value={relationships?.stats.expected_active_hours ?? 0} />
              </div>
              <div className="rounded-lg border border-default-200 p-3">
                <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                  <Input type="number" min={1} label={t('caring_workflow.support_relationships.supporter_id')} value={relationshipSupporterId} onValueChange={setRelationshipSupporterId} />
                  <Input type="number" min={1} label={t('caring_workflow.support_relationships.recipient_id')} value={relationshipRecipientId} onValueChange={setRelationshipRecipientId} />
                  <Input label={t('caring_workflow.support_relationships.relationship_title')} value={relationshipTitle} onValueChange={setRelationshipTitle} />
                  <Select
                    label={t('caring_workflow.support_relationships.frequency')}
                    selectedKeys={[relationshipFrequency]}
                    onSelectionChange={(keys) => setRelationshipFrequency((Array.from(keys)[0]?.toString() as SupportRelationship['frequency']) || 'weekly')}
                    items={frequencyOptions}
                  >
                    {(item) => <SelectItem key={item.id}>{item.label}</SelectItem>}
                  </Select>
                  <Input type="number" min={0.25} step={0.25} label={t('caring_workflow.support_relationships.expected_hours_input')} value={relationshipExpectedHours} onValueChange={setRelationshipExpectedHours} />
                  <Input type="date" label={t('caring_workflow.support_relationships.start_date')} value={relationshipStartDate} onValueChange={setRelationshipStartDate} />
                </div>
                <div className="mt-3">
                  <Button color="primary" variant="flat" startContent={<Plus size={16} />} isLoading={savingRelationship} onPress={createSupportRelationship}>
                    {t('caring_workflow.support_relationships.create')}
                  </Button>
                </div>
              </div>
              {(relationships?.items.length ?? 0) === 0 ? (
                <div className="rounded-lg bg-default-100 p-4 text-sm text-default-500">
                  {t('caring_workflow.support_relationships.empty')}
                </div>
              ) : relationships?.items.map((relationship) => (
                <div key={relationship.id} className="rounded-lg border border-default-200 p-4">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-default-900">{relationship.title}</p>
                      <p className="mt-1 text-sm text-default-500">
                        {t('caring_workflow.support_relationships.pair', {
                          supporter: relationship.supporter.name,
                          recipient: relationship.recipient.name,
                        })}
                      </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                      <Chip size="sm" color={relationship.status === 'active' ? 'success' : 'warning'} variant="flat">
                        {t(`caring_workflow.support_relationships.status.${relationship.status}`)}
                      </Chip>
                      <Chip size="sm" color="primary" variant="flat">{formatHours(relationship.expected_hours)}</Chip>
                    </div>
                  </div>
                  <div className="mt-3 flex flex-wrap gap-2 text-xs text-default-500">
                    <span>{t(`caring_workflow.support_relationships.frequencies.${relationship.frequency}`)}</span>
                    <span>{t('caring_workflow.support_relationships.started', { date: relationship.start_date })}</span>
                    {relationship.next_check_in_at && <span>{t('caring_workflow.support_relationships.next_check_in', { date: relationship.next_check_in_at })}</span>}
                    {relationship.organization_name && <span>{relationship.organization_name}</span>}
                  </div>
                  <div className="mt-3 flex flex-wrap gap-2">
                    <Button
                      size="sm"
                      variant="flat"
                      color={relationship.status === 'active' ? 'warning' : 'success'}
                      startContent={relationship.status === 'active' ? <Pause size={16} /> : <CheckCircle2 size={16} />}
                      isLoading={updatingRelationshipId === relationship.id}
                      onPress={() => updateSupportRelationshipStatus(relationship, relationship.status === 'active' ? 'paused' : 'active')}
                    >
                      {relationship.status === 'active' ? t('caring_workflow.support_relationships.pause') : t('caring_workflow.support_relationships.resume')}
                    </Button>
                  </div>
                  {relationship.status === 'active' && (
                    <div className="mt-4 rounded-lg bg-default-50 p-3">
                      <div className="grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_2fr_auto]">
                        <Input
                          type="date"
                          size="sm"
                          label={t('caring_workflow.support_relationships.log_date')}
                          value={relationshipLogDate}
                          onValueChange={setRelationshipLogDate}
                        />
                        <Input
                          type="number"
                          size="sm"
                          min={0.25}
                          max={24}
                          step={0.25}
                          label={t('caring_workflow.support_relationships.log_hours')}
                          placeholder={String(relationship.expected_hours)}
                          value={relationshipLogHours}
                          onValueChange={setRelationshipLogHours}
                        />
                        <Input
                          size="sm"
                          label={t('caring_workflow.support_relationships.log_note')}
                          value={relationshipLogDescription}
                          onValueChange={setRelationshipLogDescription}
                        />
                        <Button
                          size="sm"
                          color="primary"
                          variant="flat"
                          className="self-end"
                          startContent={<ClipboardCheck size={16} />}
                          isLoading={loggingRelationshipId === relationship.id}
                          onPress={() => logSupportRelationshipHours(relationship)}
                        >
                          {t('caring_workflow.support_relationships.log_hours_action')}
                        </Button>
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </CardBody>
          </Card>
        </div>

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
                <h2 className="text-lg font-semibold">{t('caring_workflow.member_statement.title')}</h2>
                <p className="mt-1 text-sm text-default-500">{t('caring_workflow.member_statement.description')}</p>
              </div>
            </CardHeader>
            <Divider />
            <CardBody className="gap-4">
              <Input
                type="number"
                min={1}
                label={t('caring_workflow.member_statement.member_id')}
                value={statementMemberId}
                onValueChange={setStatementMemberId}
              />
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <Input
                  type="date"
                  label={t('caring_workflow.member_statement.start_date')}
                  value={statementStartDate}
                  onValueChange={setStatementStartDate}
                />
                <Input
                  type="date"
                  label={t('caring_workflow.member_statement.end_date')}
                  value={statementEndDate}
                  onValueChange={setStatementEndDate}
                />
              </div>
              <div className="flex flex-wrap gap-2">
                <Button
                  color="primary"
                  variant="flat"
                  startContent={<Search size={16} />}
                  isLoading={loadingStatement}
                  onPress={loadMemberStatement}
                >
                  {t('caring_workflow.member_statement.preview')}
                </Button>
                <Button
                  variant="flat"
                  startContent={<Download size={16} />}
                  isLoading={loadingStatement}
                  onPress={exportMemberStatement}
                >
                  {t('caring_workflow.member_statement.export_csv')}
                </Button>
              </div>
              {memberStatement ? (
                <div className="rounded-lg border border-default-200 p-3">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                      <p className="text-sm font-semibold text-default-900">{memberStatement.user.name}</p>
                      <p className="text-xs text-default-500">{memberStatement.user.email}</p>
                    </div>
                    <Chip size="sm" color="success" variant="flat">
                      {formatHours(memberStatement.summary.current_balance)}
                    </Chip>
                  </div>
                  <div className="mt-3 grid grid-cols-2 gap-2">
                    <SignalRow label={t('caring_workflow.member_statement.approved_hours')} value={memberStatement.summary.approved_support_hours} />
                    <SignalRow label={t('caring_workflow.member_statement.pending_hours')} value={memberStatement.summary.pending_support_hours} />
                    <SignalRow label={t('caring_workflow.member_statement.earned')} value={memberStatement.summary.wallet_hours_earned} />
                    <SignalRow label={t('caring_workflow.member_statement.spent')} value={memberStatement.summary.wallet_hours_spent} />
                  </div>
                  <div className="mt-3 rounded-lg bg-default-100 px-3 py-2 text-sm text-default-700">
                    {formatChf(memberStatement.summary.estimated_social_value_chf)}
                  </div>
                  {memberStatement.support_hours_by_organisation.length > 0 && (
                    <div className="mt-3 space-y-2">
                      {memberStatement.support_hours_by_organisation.slice(0, 3).map((organisation) => (
                        <div key={organisation.organisation_name} className="flex items-center justify-between gap-3 text-xs">
                          <span className="truncate text-default-600">{organisation.organisation_name}</span>
                          <span className="font-semibold text-default-900">{formatHours(organisation.approved_hours)}</span>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              ) : (
                <div className="rounded-lg bg-default-100 p-3 text-sm text-default-500">
                  {t('caring_workflow.member_statement.empty')}
                </div>
              )}
            </CardBody>
          </Card>

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
