// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Safeguarding Page
 * Flagged messages, guardian assignments, and member safeguarding preferences.
 */

import { useState, useEffect, useCallback, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import type { TFunction } from 'i18next';
import {
  Tabs,
  Tab,
  Chip,
  Button,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
} from '@heroui/react';

import Shield from 'lucide-react/icons/shield';
import Flag from 'lucide-react/icons/flag';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Eye from 'lucide-react/icons/eye';
import Users from 'lucide-react/icons/users';
import Heart from 'lucide-react/icons/heart';
import UserCheck from 'lucide-react/icons/user-check';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { formatServerDate } from '@/lib/serverTime';
import { PageHeader, DataTable, StatCard, EmptyState } from '@/admin/components';
import type { Column } from '@/admin/components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface SafeguardingDashboard {
  active_assignments: number;
  unreviewed_flags: number;
  consented_wards: number;
  total_flags_this_month: number;
  critical_flags: number;
}

interface FlaggedMessage {
  id: number;
  sender?: { id: number; name: string; avatar_url?: string };
  recipient?: { id: number; name: string; avatar_url?: string };
  sender_name?: string;
  receiver_name?: string;
  message_content?: string;
  message_body?: string;
  copy_reason?: string;
  flag_reason?: string;
  severity?: string;
  flag_severity?: string;
  is_reviewed?: boolean;
  reviewed_at?: string | null;
  reviewed_by?: string | null;
  review_notes?: string | null;
  created_at: string;
}

interface GuardianAssignment {
  id: number;
  ward_name?: string;
  ward?: { name: string };
  guardian_name?: string;
  guardian?: { name: string };
  status: 'active' | 'pending' | 'revoked';
  consent_given_at: string | null;
  created_at: string;
}

interface MemberPreference {
  id: number;
  user_name?: string;
  user?: { name: string };
  options: string[];
  has_triggers: boolean;
  created_at: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Severity Chip Helper
// ─────────────────────────────────────────────────────────────────────────────

function SeverityChip({ severity, t }: { severity: string; t: TFunction }) {
  const colorMap: Record<string, 'default' | 'warning' | 'danger'> = {
    low: 'default',
    medium: 'warning',
    high: 'danger',
    critical: 'danger',
  };
  const color = colorMap[severity] || 'default';
  const variant = severity === 'critical' ? 'solid' : 'flat';
  // Reuse the existing broker.status namespace which already has
  // low/medium/high/critical translated across all 11 languages —
  // avoids a parallel severity.* keyset that would drift.
  return (
    <Chip size="sm" color={color} variant={variant}>
      {t(`status.${severity}`, { defaultValue: severity })}
    </Chip>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function SafeguardingPage() {
  const { t } = useTranslation('broker');
  usePageTitle(t('safeguarding.title'));
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  // ── Dashboard stats ──────────────────────────────────────────────────────
  const [stats, setStats] = useState<SafeguardingDashboard | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);
  const [statsError, setStatsError] = useState(false);

  // ── Tab: Flagged Messages ────────────────────────────────────────────────
  const [flaggedMessages, setFlaggedMessages] = useState<FlaggedMessage[]>([]);
  const [flaggedLoading, setFlaggedLoading] = useState(true);
  const [flaggedError, setFlaggedError] = useState(false);
  const [flaggedPage, setFlaggedPage] = useState(1);
  const [flaggedTotal, setFlaggedTotal] = useState(0);

  // ── Tab: Guardian Assignments ────────────────────────────────────────────
  const [assignments, setAssignments] = useState<GuardianAssignment[]>([]);
  const [assignmentsLoading, setAssignmentsLoading] = useState(false);
  const [assignmentsError, setAssignmentsError] = useState(false);

  // ── Tab: Member Preferences ──────────────────────────────────────────────
  const [preferences, setPreferences] = useState<MemberPreference[]>([]);
  const [preferencesLoading, setPreferencesLoading] = useState(false);
  const [preferencesError, setPreferencesError] = useState(false);

  // ── Review modal ─────────────────────────────────────────────────────────
  const [reviewTarget, setReviewTarget] = useState<FlaggedMessage | null>(null);
  const [reviewNotes, setReviewNotes] = useState('');
  const [reviewLoading, setReviewLoading] = useState(false);

  // ── Active tab + severity filter (URL-driven) ────────────────────────────
  // The active tab and severity filter are mirrored to the `?tab=` and
  // `?filter=` URL params so dashboard tiles can deep-link directly into a
  // pre-filtered view AND browser back/forward round-trips correctly.
  const ALLOWED_TABS = ['flagged', 'guardians', 'preferences'] as const;
  type TabKey = (typeof ALLOWED_TABS)[number];
  const urlTab = searchParams.get('tab') as TabKey | null;
  const activeTab: TabKey = urlTab && ALLOWED_TABS.includes(urlTab) ? urlTab : 'flagged';
  const setActiveTab = useCallback(
    (next: TabKey) => {
      setSearchParams(
        (prev) => {
          const params = new URLSearchParams(prev);
          if (next === 'flagged') {
            params.delete('tab');
          } else {
            params.set('tab', next);
          }
          return params;
        },
        { replace: true }
      );
    },
    [setSearchParams]
  );
  const severityFilter = searchParams.get('filter') || '';

  // ── Data fetchers ────────────────────────────────────────────────────────

  const fetchStats = useCallback(async () => {
    setStatsLoading(true);
    setStatsError(false);
    try {
      const res = await api.get<SafeguardingDashboard>('/v2/admin/safeguarding/dashboard');
      const payload = res.data as SafeguardingDashboard;
      setStats(payload);
    } catch {
      // Surface the failure rather than silently zeroing safeguarding
      // metrics — same lesson as the broker dashboard's _partial flag.
      setStatsError(true);
    } finally {
      setStatsLoading(false);
    }
  }, []);

  const fetchFlagged = useCallback(async (page = 1) => {
    setFlaggedLoading(true);
    setFlaggedError(false);
    try {
      const params = new URLSearchParams({ page: String(page), reviewed: '0' });
      if (severityFilter) params.set('severity', severityFilter);
      const res = await api.get<unknown>(`/v2/admin/safeguarding/flagged-messages?${params.toString()}`);
      const payload = res.data as unknown;
      if (Array.isArray(payload)) {
        setFlaggedMessages(payload as FlaggedMessage[]);
        setFlaggedTotal(res.meta?.total ?? payload.length);
      } else if (payload && typeof payload === 'object') {
        const paged = payload as { data: FlaggedMessage[]; meta?: { total: number } };
        setFlaggedMessages(paged.data || []);
        setFlaggedTotal(paged.meta?.total ?? 0);
      }
    } catch {
      setFlaggedMessages([]);
      setFlaggedError(true);
    } finally {
      setFlaggedLoading(false);
    }
  }, [severityFilter]);

  const fetchAssignments = useCallback(async () => {
    setAssignmentsLoading(true);
    setAssignmentsError(false);
    try {
      // No `?status=active` filter: the backend doesn't honour it (it
      // returns all assignments and computes status from
      // revoked_at/consent_given_at) and the table here intentionally
      // shows every assignment with a colored status chip. Sending the
      // param was misleading — it implied filtering that wasn't happening.
      const res = await api.get<unknown>('/v2/admin/safeguarding/assignments');
      const payload = res.data as unknown;
      if (Array.isArray(payload)) {
        setAssignments(payload as GuardianAssignment[]);
      } else if (payload && typeof payload === 'object') {
        const paged = payload as { data: GuardianAssignment[] };
        setAssignments(paged.data || []);
      }
    } catch {
      setAssignments([]);
      setAssignmentsError(true);
    } finally {
      setAssignmentsLoading(false);
    }
  }, []);

  const fetchPreferences = useCallback(async () => {
    setPreferencesLoading(true);
    setPreferencesError(false);
    try {
      const res = await api.get<unknown>('/v2/admin/safeguarding/member-preferences');
      const payload = res.data as unknown;
      if (Array.isArray(payload)) {
        setPreferences(payload as MemberPreference[]);
      } else if (payload && typeof payload === 'object') {
        const paged = payload as { data: MemberPreference[] };
        setPreferences(paged.data || []);
      }
    } catch {
      setPreferences([]);
      setPreferencesError(true);
    } finally {
      setPreferencesLoading(false);
    }
  }, []);

  // ── Initial load ─────────────────────────────────────────────────────────

  useEffect(() => {
    void fetchStats();
    // Reset to page 1 whenever the severity filter (from URL) changes so
    // pagination meta stays consistent with the new data set.
    setFlaggedPage(1);
    void fetchFlagged(1);
  }, [fetchStats, fetchFlagged]);

  useEffect(() => {
    if (activeTab === 'guardians') void fetchAssignments();
    if (activeTab === 'preferences') void fetchPreferences();
  }, [activeTab, fetchAssignments, fetchPreferences]);

  // ── Review handler ───────────────────────────────────────────────────────

  const handleReview = useCallback(async () => {
    if (!reviewTarget) return;
    setReviewLoading(true);
    try {
      await api.post(`/v2/admin/safeguarding/flagged-messages/${reviewTarget.id}/review`, {
        notes: reviewNotes || undefined,
      });
      toast.success(t('safeguarding.reviewed_success'));
      setReviewTarget(null);
      setReviewNotes('');
      void fetchFlagged(flaggedPage);
      void fetchStats();
    } catch {
      toast.error(t('common.error'));
    } finally {
      setReviewLoading(false);
    }
  }, [reviewTarget, reviewNotes, flaggedPage, fetchFlagged, fetchStats, toast, t]);

  // ── Column definitions ───────────────────────────────────────────────────

  const flaggedColumns: Column<FlaggedMessage>[] = useMemo(() => [
    {
      key: 'sender',
      label: t('safeguarding.col_sender'),
      render: (item) => item.sender?.name || item.sender_name || '—',
    },
    {
      key: 'recipient',
      label: t('safeguarding.col_receiver'),
      render: (item) => item.recipient?.name || item.receiver_name || '—',
    },
    {
      key: 'flag_reason',
      label: t('safeguarding.col_reason'),
      render: (item) => item.flag_reason || item.copy_reason || '—',
    },
    {
      key: 'severity',
      label: t('safeguarding.col_severity'),
      render: (item) => <SeverityChip severity={item.severity || item.flag_severity || 'low'} t={t} />,
    },
    {
      key: 'created_at',
      label: t('safeguarding.col_date'),
      render: (item) => formatServerDate(item.created_at),
    },
    {
      key: 'is_reviewed',
      label: t('safeguarding.col_status'),
      render: (item) =>
        (item.is_reviewed || item.reviewed_at) ? (
          <Chip size="sm" color="success" variant="flat">{t('status.reviewed')}</Chip>
        ) : (
          <Chip size="sm" color="warning" variant="flat">{t('status.unreviewed')}</Chip>
        ),
    },
    {
      key: 'actions',
      label: '',
      render: (item) =>
        !(item.is_reviewed || item.reviewed_at) ? (
          <Button
            size="sm"
            color="primary"
            variant="flat"
            startContent={<Eye size={14} />}
            onPress={() => setReviewTarget(item)}
          >
            {t('safeguarding.mark_reviewed')}
          </Button>
        ) : null,
    },
  ], [t]);

  const assignmentColumns: Column<GuardianAssignment>[] = useMemo(() => [
    {
      key: 'ward_name',
      label: t('safeguarding.col_ward'),
      render: (item) => item.ward_name || item.ward?.name || '—',
    },
    {
      key: 'guardian_name',
      label: t('safeguarding.col_guardian'),
      render: (item) => item.guardian_name || item.guardian?.name || '—',
    },
    {
      key: 'status',
      label: t('safeguarding.col_status'),
      render: (item) => {
        const colorMap: Record<string, 'success' | 'warning' | 'danger'> = {
          active: 'success',
          pending: 'warning',
          revoked: 'danger',
        };
        return (
          <Chip size="sm" color={colorMap[item.status] || 'default'} variant="flat" className="capitalize">
            {t(`status.${item.status}`)}
          </Chip>
        );
      },
    },
    {
      key: 'consent_given_at',
      label: t('safeguarding.col_consent'),
      render: (item) => formatServerDate(item.consent_given_at),
    },
    {
      key: 'created_at',
      label: t('safeguarding.col_assigned'),
      render: (item) => formatServerDate(item.created_at),
    },
  ], [t]);

  const preferenceColumns: Column<MemberPreference>[] = useMemo(() => [
    {
      key: 'user_name',
      label: t('safeguarding.col_member'),
      render: (item) => item.user_name || item.user?.name || '—',
    },
    {
      key: 'options',
      label: t('safeguarding.col_options'),
      render: (item) =>
        item.options && item.options.length > 0
          ? item.options.join(', ')
          : '—',
    },
    {
      key: 'has_triggers',
      label: t('safeguarding.col_has_triggers'),
      render: (item) =>
        item.has_triggers ? (
          <Chip size="sm" color="danger" variant="flat">{t('safeguarding.yes')}</Chip>
        ) : (
          <Chip size="sm" color="success" variant="flat">{t('safeguarding.no')}</Chip>
        ),
    },
    {
      key: 'created_at',
      label: t('safeguarding.col_date'),
      render: (item) => formatServerDate(item.created_at),
    },
  ], [t]);

  // ── Render ───────────────────────────────────────────────────────────────

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('safeguarding.title')}
        description={t('safeguarding.description')}
      />

      {/* Surface load failures explicitly — silent zeros on a safeguarding
          tile are dangerous in the same way the broker-dashboard
          safeguarding_alerts bug was. The retry button gives the user a
          path forward without forcing a full page reload. */}
      {(statsError || flaggedError || assignmentsError || preferencesError) && (
        <div className="rounded-lg border border-warning-200 bg-warning-50/50 p-3 flex items-start gap-3">
          <AlertTriangle size={20} className="text-warning shrink-0 mt-0.5" />
          <div className="flex-1 text-sm">
            <p className="font-medium text-warning-700">{t('safeguarding.load_error_title')}</p>
            <p className="text-default-600">{t('safeguarding.load_error_body')}</p>
          </div>
          <Button
            size="sm"
            variant="flat"
            color="warning"
            onPress={() => {
              if (statsError) void fetchStats();
              if (flaggedError) void fetchFlagged(flaggedPage);
              if (assignmentsError) void fetchAssignments();
              if (preferencesError) void fetchPreferences();
            }}
          >
            {t('safeguarding.retry')}
          </Button>
        </div>
      )}

      {/* Stat cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <StatCard
          label={t('safeguarding.stat_flags_month')}
          value={stats?.total_flags_this_month ?? 0}
          icon={Flag}
          color="warning"
          loading={statsLoading}
        />
        <StatCard
          label={t('safeguarding.stat_critical')}
          value={stats?.critical_flags ?? 0}
          icon={AlertTriangle}
          color="danger"
          loading={statsLoading}
        />
        <StatCard
          label={t('safeguarding.stat_unreviewed')}
          value={stats?.unreviewed_flags ?? 0}
          icon={Eye}
          color="primary"
          loading={statsLoading}
        />
        <StatCard
          label={t('safeguarding.stat_assignments')}
          value={stats?.active_assignments ?? 0}
          icon={UserCheck}
          color="success"
          loading={statsLoading}
        />
        <StatCard
          label={t('safeguarding.stat_consented')}
          value={stats?.consented_wards ?? 0}
          icon={Users}
          color="default"
          loading={statsLoading}
        />
      </div>

      {/* Tabs */}
      <Tabs
        selectedKey={activeTab}
        onSelectionChange={(key) => {
          const k = String(key);
          if ((ALLOWED_TABS as readonly string[]).includes(k)) {
            setActiveTab(k as TabKey);
          }
        }}
        aria-label={t('safeguarding.tabs_aria')}
        color="primary"
        variant="underlined"
      >
        {/* Tab 1: Flagged Messages */}
        <Tab
          key="flagged"
          title={
            <div className="flex items-center gap-2">
              <Shield size={16} />
              <span>{t('safeguarding.tab_flagged')}</span>
            </div>
          }
        >
          <div className="mt-4">
            <DataTable
              columns={flaggedColumns}
              data={flaggedMessages}
              isLoading={flaggedLoading}
              totalItems={flaggedTotal}
              page={flaggedPage}
              pageSize={20}
              onPageChange={(p) => {
                setFlaggedPage(p);
                void fetchFlagged(p);
              }}
              onRefresh={() => void fetchFlagged(flaggedPage)}
              searchable={false}
              emptyContent={
                <EmptyState
                  icon={Shield}
                  title={t('safeguarding.no_flagged')}
                />
              }
            />
          </div>
        </Tab>

        {/* Tab 2: Guardian Assignments */}
        <Tab
          key="guardians"
          title={
            <div className="flex items-center gap-2">
              <Users size={16} />
              <span>{t('safeguarding.tab_guardians')}</span>
            </div>
          }
        >
          <div className="mt-4">
            <DataTable
              columns={assignmentColumns}
              data={assignments}
              isLoading={assignmentsLoading}
              searchable={false}
              onRefresh={() => void fetchAssignments()}
              emptyContent={
                <EmptyState
                  icon={Users}
                  title={t('safeguarding.no_assignments')}
                />
              }
            />
          </div>
        </Tab>

        {/* Tab 3: Member Preferences */}
        <Tab
          key="preferences"
          title={
            <div className="flex items-center gap-2">
              <Heart size={16} />
              <span>{t('safeguarding.tab_preferences')}</span>
            </div>
          }
        >
          <div className="mt-4">
            <DataTable
              columns={preferenceColumns}
              data={preferences}
              isLoading={preferencesLoading}
              searchable={false}
              onRefresh={() => void fetchPreferences()}
              emptyContent={
                <EmptyState
                  icon={Heart}
                  title={t('safeguarding.no_preferences')}
                />
              }
            />
          </div>
        </Tab>
      </Tabs>

      {/* Review Modal */}
      <Modal isOpen={!!reviewTarget} onClose={() => setReviewTarget(null)} size="md">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Eye size={20} className="text-primary" />
            {t('safeguarding.mark_reviewed')}
          </ModalHeader>
          <ModalBody>
            {reviewTarget && (
              <div className="space-y-3">
                <div className="rounded-lg bg-default-100 p-3 text-sm text-default-700">
                  <p className="mb-1 font-medium">
                    {reviewTarget.sender?.name || reviewTarget.sender_name || '—'} &rarr; {reviewTarget.recipient?.name || reviewTarget.receiver_name || '—'}
                  </p>
                  <p className="text-default-500">{reviewTarget.message_content || reviewTarget.message_body || ''}</p>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-sm text-default-500">{t('safeguarding.severity_label')}</span>
                  <SeverityChip severity={reviewTarget.severity || reviewTarget.flag_severity || 'low'} t={t} />
                </div>
                <Textarea
                  label={t('safeguarding.review_notes_label')}
                  placeholder={t('safeguarding.review_notes_placeholder')}
                  value={reviewNotes}
                  onValueChange={setReviewNotes}
                  minRows={2}
                  variant="bordered"
                />
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={() => setReviewTarget(null)}
              isDisabled={reviewLoading}
            >
              {t('common.cancel')}
            </Button>
            <Button
              color="primary"
              onPress={() => void handleReview()}
              isLoading={reviewLoading}
              isDisabled={reviewLoading}
            >
              {t('safeguarding.mark_reviewed')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
