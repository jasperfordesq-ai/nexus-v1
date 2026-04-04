// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Safeguarding Page
 * Flagged messages, guardian assignments, and member safeguarding preferences.
 */

import { useState, useEffect, useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
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
import {
  Shield,
  Flag,
  AlertTriangle,
  Eye,
  Users,
  Heart,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
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

function SeverityChip({ severity }: { severity: string }) {
  const colorMap: Record<string, 'default' | 'warning' | 'danger'> = {
    low: 'default',
    medium: 'warning',
    high: 'danger',
    critical: 'danger',
  };
  const color = colorMap[severity] || 'default';
  const variant = severity === 'critical' ? 'solid' : 'flat';
  return (
    <Chip size="sm" color={color} variant={variant} className="capitalize">
      {severity}
    </Chip>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function SafeguardingPage() {
  usePageTitle('Safeguarding - Broker');
  const { t } = useTranslation('broker');
  const toast = useToast();

  // ── Dashboard stats ──────────────────────────────────────────────────────
  const [stats, setStats] = useState<SafeguardingDashboard | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);

  // ── Tab: Flagged Messages ────────────────────────────────────────────────
  const [flaggedMessages, setFlaggedMessages] = useState<FlaggedMessage[]>([]);
  const [flaggedLoading, setFlaggedLoading] = useState(true);
  const [flaggedPage, setFlaggedPage] = useState(1);
  const [flaggedTotal, setFlaggedTotal] = useState(0);

  // ── Tab: Guardian Assignments ────────────────────────────────────────────
  const [assignments, setAssignments] = useState<GuardianAssignment[]>([]);
  const [assignmentsLoading, setAssignmentsLoading] = useState(false);

  // ── Tab: Member Preferences ──────────────────────────────────────────────
  const [preferences, setPreferences] = useState<MemberPreference[]>([]);
  const [preferencesLoading, setPreferencesLoading] = useState(false);

  // ── Review modal ─────────────────────────────────────────────────────────
  const [reviewTarget, setReviewTarget] = useState<FlaggedMessage | null>(null);
  const [reviewNotes, setReviewNotes] = useState('');
  const [reviewLoading, setReviewLoading] = useState(false);

  // ── Active tab ───────────────────────────────────────────────────────────
  const [activeTab, setActiveTab] = useState('flagged');

  // ── Data fetchers ────────────────────────────────────────────────────────

  const fetchStats = useCallback(async () => {
    setStatsLoading(true);
    try {
      const res = await api.get<SafeguardingDashboard>('/v2/admin/safeguarding/dashboard');
      const payload = res.data as SafeguardingDashboard;
      setStats(payload);
    } catch {
      // stats are non-critical — silently fail
    } finally {
      setStatsLoading(false);
    }
  }, []);

  const fetchFlagged = useCallback(async (page = 1) => {
    setFlaggedLoading(true);
    try {
      const res = await api.get<unknown>(`/v2/admin/safeguarding/flagged-messages?page=${page}&reviewed=0`);
      const payload = res.data as unknown;
      if (Array.isArray(payload)) {
        setFlaggedMessages(payload as FlaggedMessage[]);
        setFlaggedTotal(payload.length);
      } else if (payload && typeof payload === 'object') {
        const paged = payload as { data: FlaggedMessage[]; meta?: { total: number } };
        setFlaggedMessages(paged.data || []);
        setFlaggedTotal(paged.meta?.total ?? 0);
      }
    } catch {
      setFlaggedMessages([]);
    } finally {
      setFlaggedLoading(false);
    }
  }, []);

  const fetchAssignments = useCallback(async () => {
    setAssignmentsLoading(true);
    try {
      const res = await api.get<unknown>('/v2/admin/safeguarding/assignments?status=active');
      const payload = res.data as unknown;
      if (Array.isArray(payload)) {
        setAssignments(payload as GuardianAssignment[]);
      } else if (payload && typeof payload === 'object') {
        const paged = payload as { data: GuardianAssignment[] };
        setAssignments(paged.data || []);
      }
    } catch {
      setAssignments([]);
    } finally {
      setAssignmentsLoading(false);
    }
  }, []);

  const fetchPreferences = useCallback(async () => {
    setPreferencesLoading(true);
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
    } finally {
      setPreferencesLoading(false);
    }
  }, []);

  // ── Initial load ─────────────────────────────────────────────────────────

  useEffect(() => {
    void fetchStats();
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
      render: (item) => <SeverityChip severity={item.severity || item.flag_severity || 'low'} />,
    },
    {
      key: 'created_at',
      label: t('safeguarding.col_date'),
      render: (item) => new Date(item.created_at).toLocaleDateString(),
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
      render: (item) =>
        item.consent_given_at ? new Date(item.consent_given_at).toLocaleDateString() : '—',
    },
    {
      key: 'created_at',
      label: t('safeguarding.col_assigned'),
      render: (item) => new Date(item.created_at).toLocaleDateString(),
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
      label: 'Has Triggers',
      render: (item) =>
        item.has_triggers ? (
          <Chip size="sm" color="danger" variant="flat">Yes</Chip>
        ) : (
          <Chip size="sm" color="success" variant="flat">No</Chip>
        ),
    },
    {
      key: 'created_at',
      label: t('safeguarding.col_date'),
      render: (item) => new Date(item.created_at).toLocaleDateString(),
    },
  ], [t]);

  // ── Render ───────────────────────────────────────────────────────────────

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('safeguarding.title')}
        description={t('safeguarding.description')}
      />

      {/* Stat cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard
          label="Flags This Month"
          value={stats?.total_flags_this_month ?? 0}
          icon={Flag}
          color="warning"
          loading={statsLoading}
        />
        <StatCard
          label="Critical Flags"
          value={stats?.critical_flags ?? 0}
          icon={AlertTriangle}
          color="danger"
          loading={statsLoading}
        />
        <StatCard
          label="Unreviewed"
          value={stats?.unreviewed_flags ?? 0}
          icon={Eye}
          color="primary"
          loading={statsLoading}
        />
      </div>

      {/* Tabs */}
      <Tabs
        selectedKey={activeTab}
        onSelectionChange={(key) => setActiveTab(String(key))}
        aria-label="Safeguarding tabs"
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
                    {reviewTarget.sender_name} &rarr; {reviewTarget.receiver_name}
                  </p>
                  <p className="text-default-500">{reviewTarget.message_content || reviewTarget.message_body || ''}</p>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-sm text-default-500">Severity:</span>
                  <SeverityChip severity={reviewTarget.severity || reviewTarget.flag_severity || 'low'} />
                </div>
                <Textarea
                  label="Review Notes"
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
