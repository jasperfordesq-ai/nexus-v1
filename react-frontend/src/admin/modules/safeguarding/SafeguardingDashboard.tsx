// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Safeguarding Dashboard (MS2)
 * Admin page for reviewing flagged messages, managing guardian assignments,
 * and monitoring safeguarding of vulnerable users (wards).
 */

import { useState, useEffect, useCallback, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Spinner,
  Chip,
  Tabs,
  Tab,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
  Input,
  Avatar,
  Divider,
  useDisclosure,
} from '@heroui/react';
import Shield from 'lucide-react/icons/shield';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import ShieldCheck from 'lucide-react/icons/shield-check';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Eye from 'lucide-react/icons/eye';
import Users from 'lucide-react/icons/users';
import MessageSquare from 'lucide-react/icons/message-square';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import XCircle from 'lucide-react/icons/circle-x';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Search from 'lucide-react/icons/search';
import UserPlus from 'lucide-react/icons/user-plus';
import UserMinus from 'lucide-react/icons/user-minus';
import Clock from 'lucide-react/icons/clock';
import Flag from 'lucide-react/icons/flag';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';
import { PageHeader } from '../../components';
import { StatCard } from '../../components';
import { SafeguardingHelp } from './SafeguardingHelp';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface FlaggedMessage {
  id: number;
  message_id: number;
  message_content: string;
  sender: {
    id: number;
    name: string;
    avatar_url?: string | null;
  };
  recipient: {
    id: number;
    name: string;
    avatar_url?: string | null;
  };
  severity: 'low' | 'medium' | 'high' | 'critical';
  flag_reason: string;
  flag_categories?: string[];
  ward_name?: string;
  guardian_name?: string;
  is_reviewed: boolean;
  reviewed_by?: string;
  review_notes?: string;
  reviewed_at?: string;
  created_at: string;
}

interface GuardianAssignment {
  id: number;
  ward: {
    id: number;
    name: string;
    avatar_url?: string | null;
  };
  guardian: {
    id: number;
    name: string;
    avatar_url?: string | null;
  };
  status: 'active' | 'revoked' | 'expired';
  consent_given: boolean;
  created_at: string;
  expires_at?: string;
}

interface DashboardStats {
  active_assignments: number;
  unreviewed_flags: number;
  consented_wards: number;
  total_flags_this_month: number;
  critical_flags: number;
}

interface MemberSafeguardingEntry {
  user_id: number;
  user_name: string;
  user_avatar?: string | null;
  options: { option_key: string; label: string; is_declination: boolean; }[];
  consent_given_at: string;
  has_triggers: boolean;
  is_declination_only: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

const SEVERITY_COLORS: Record<string, 'default' | 'primary' | 'warning' | 'danger'> = {
  low: 'default',
  medium: 'primary',
  high: 'warning',
  critical: 'danger',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function SafeguardingDashboard() {
  const { t } = useTranslation('admin');
  usePageTitle(t('safeguarding.page_title'));
  const toast = useToast();
  const { tenantPath } = useTenant();
  const [searchParams, setSearchParams] = useSearchParams();

  // Tab is driven by the URL so stat cards can deep-link into a specific view
  // and browser back/forward works intuitively. Valid tab keys are the three
  // sections rendered below.
  const rawTab = searchParams.get('tab');
  const activeTab = rawTab === 'assignments' || rawTab === 'preferences' ? rawTab : 'flagged';
  const setActiveTab = useCallback(
    (next: string) => {
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
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [flaggedMessages, setFlaggedMessages] = useState<FlaggedMessage[]>([]);
  const [assignments, setAssignments] = useState<GuardianAssignment[]>([]);
  const [searchQuery, setSearchQuery] = useState('');

  // Member safeguarding preferences from onboarding
  const [memberPreferences, setMemberPreferences] = useState<MemberSafeguardingEntry[]>([]);

  // Review modal
  const reviewModal = useDisclosure();
  const [reviewTarget, setReviewTarget] = useState<FlaggedMessage | null>(null);
  const [reviewNotes, setReviewNotes] = useState('');
  const [reviewing, setReviewing] = useState(false);

  // Assignment modal
  const assignModal = useDisclosure();
  const [wardEmail, setWardEmail] = useState('');
  const [guardianEmail, setGuardianEmail] = useState('');
  const [creating, setCreating] = useState(false);

  // ─── Load data ───
  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [statsRes, flagsRes, assignmentsRes, prefsRes] = await Promise.all([
        api.get('/v2/admin/safeguarding/dashboard'),
        api.get('/v2/admin/safeguarding/flagged-messages'),
        api.get('/v2/admin/safeguarding/assignments'),
        api.get<MemberSafeguardingEntry[]>('/v2/admin/safeguarding/member-preferences'),
      ]);

      if (statsRes.success) {
        const payload = statsRes.data as DashboardStats | { data: DashboardStats };
        setStats('data' in payload ? payload.data : payload);
      }

      if (flagsRes.success) {
        const payload = flagsRes.data;
        setFlaggedMessages(
          Array.isArray(payload) ? payload : (payload as { messages?: FlaggedMessage[] })?.messages ?? []
        );
      }

      if (assignmentsRes.success) {
        const payload = assignmentsRes.data;
        setAssignments(
          Array.isArray(payload) ? payload : (payload as { assignments?: GuardianAssignment[] })?.assignments ?? []
        );
      }

      if (prefsRes.success) {
        const payload = prefsRes.data;
        setMemberPreferences(Array.isArray(payload) ? payload : []);
      }
    } catch (err) {
      logError('SafeguardingDashboard.load', err);
      toast.error(t('safeguarding.failed_to_load_safeguarding_data'));
    }
    setLoading(false);
  }, [toast, t])


  useEffect(() => { loadData(); }, [loadData]);

  // ─── Review flagged message ───
  const handleReview = useCallback(async () => {
    if (!reviewTarget) return;
    setReviewing(true);
    try {
      const res = await api.post(`/v2/admin/safeguarding/flagged-messages/${reviewTarget.id}/review`, {
        notes: reviewNotes,
      });
      if (res.success) {
        toast.success(t('safeguarding.message_reviewed'));
        setFlaggedMessages((prev) =>
          prev.map((m) => m.id === reviewTarget.id ? { ...m, is_reviewed: true, review_notes: reviewNotes } : m)
        );
        setReviewTarget(null);
        setReviewNotes('');
        reviewModal.onClose();
        // Refresh stats
        loadData();
      }
    } catch (err) {
      logError('SafeguardingDashboard.review', err);
      toast.error(t('safeguarding.failed_to_review_message'));
    }
    setReviewing(false);
  }, [reviewTarget, reviewNotes, toast, reviewModal, loadData, t])


  // ─── Create guardian assignment ───
  const handleCreateAssignment = useCallback(async () => {
    if (!wardEmail.trim() || !guardianEmail.trim()) return;
    setCreating(true);
    try {
      const res = await api.post('/v2/admin/safeguarding/assignments', {
        ward_email: wardEmail.trim(),
        guardian_email: guardianEmail.trim(),
      });
      if (res.success) {
        toast.success(t('safeguarding.guardian_assignment_created'));
        setWardEmail('');
        setGuardianEmail('');
        assignModal.onClose();
        loadData();
      }
    } catch (err) {
      logError('SafeguardingDashboard.createAssignment', err);
      toast.error(t('safeguarding.failed_to_create_assignment'));
    }
    setCreating(false);
  }, [wardEmail, guardianEmail, toast, assignModal, loadData, t])


  // ─── Revoke assignment ───
  const handleRevokeAssignment = useCallback(async (assignmentId: number) => {
    try {
      await api.delete(`/v2/admin/safeguarding/assignments/${assignmentId}`);
      toast.success(t('safeguarding.assignment_revoked'));
      setAssignments((prev) =>
        prev.map((a) => a.id === assignmentId ? { ...a, status: 'revoked' as const } : a)
      );
    } catch (err) {
      logError('SafeguardingDashboard.revoke', err);
      toast.error(t('safeguarding.failed_to_revoke_assignment'));
    }
  }, [toast, t])


  // ─── Filtered items ───
  const flaggedFilter = searchParams.get('filter'); // unreviewed | reviewed | critical | null
  const filteredFlags = useMemo(() => {
    let list = flaggedMessages;
    if (flaggedFilter === 'unreviewed') {
      list = list.filter((m) => !m.is_reviewed);
    } else if (flaggedFilter === 'reviewed') {
      list = list.filter((m) => m.is_reviewed);
    } else if (flaggedFilter === 'critical') {
      list = list.filter((m) => !m.is_reviewed && (m.severity === 'critical' || m.severity === 'high'));
    }
    if (searchQuery) {
      const q = searchQuery.toLowerCase();
      list = list.filter(
        (m) =>
          m.message_content.toLowerCase().includes(q) ||
          m.sender.name.toLowerCase().includes(q) ||
          m.recipient.name.toLowerCase().includes(q)
      );
    }
    return list;
  }, [flaggedMessages, flaggedFilter, searchQuery]);

  const assignmentFilter = searchParams.get('filter'); // active | consented | null (assignments tab)
  const filteredAssignments = useMemo(() => {
    if (activeTab !== 'assignments') return assignments;
    if (assignmentFilter === 'active') return assignments.filter((a) => a.status === 'active');
    if (assignmentFilter === 'consented') {
      return assignments.filter((a) => a.status === 'active' && a.consent_given);
    }
    return assignments;
  }, [assignments, assignmentFilter, activeTab]);

  // Friendly label for the active drill-down filter, shown above the table so
  // the admin knows which stat they clicked (and can clear it).
  const activeFilterLabel = useMemo(() => {
    if (activeTab === 'flagged') {
      if (flaggedFilter === 'unreviewed') return t('safeguarding.filter_label_unreviewed');
      if (flaggedFilter === 'critical') return t('safeguarding.filter_label_critical');
      if (flaggedFilter === 'reviewed') return t('safeguarding.filter_label_reviewed');
    }
    if (activeTab === 'assignments') {
      if (assignmentFilter === 'active') return t('safeguarding.filter_label_active');
      if (assignmentFilter === 'consented') return t('safeguarding.filter_label_consented');
    }
    return null;
  }, [activeTab, flaggedFilter, assignmentFilter, t]);


  const clearFilter = useCallback(() => {
    setSearchParams(
      (prev) => {
        const params = new URLSearchParams(prev);
        params.delete('filter');
        return params;
      },
      { replace: true }
    );
  }, [setSearchParams]);

  // ─── Render ───
  if (loading) {
    return (
      <div>
        <PageHeader title={t('safeguarding.safeguarding_dashboard_title')} description={t('safeguarding.safeguarding_dashboard_desc')} />
        <div className="flex h-64 items-center justify-center">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('safeguarding.safeguarding_dashboard_title')}
        description={t('safeguarding.safeguarding_dashboard_desc')}
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              size="sm"
              startContent={<RefreshCw size={16} />}
              onPress={() => loadData()}
            >
              {t('safeguarding.refresh')}
            </Button>
            <Button
              color="primary"
              size="sm"
              startContent={<UserPlus size={16} />}
              onPress={assignModal.onOpen}
            >
              {t('safeguarding.new_assignment')}
            </Button>
          </div>
        }
      />

      {/* Stats — each card deep-links to the matching tab/filter combination */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
          <StatCard
            label={t('safeguarding.label_unreviewed_flags')}
            value={stats.unreviewed_flags}
            icon={ShieldAlert}
            color={stats.unreviewed_flags > 0 ? 'danger' : 'success'}
            to={tenantPath('/admin/safeguarding?filter=unreviewed')}
            linkAriaLabel={t('safeguarding.cta_view_unreviewed')}
          />
          <StatCard
            label={t('safeguarding.label_critical_flags')}
            value={stats.critical_flags}
            icon={AlertTriangle}
            color="warning"
            to={tenantPath('/admin/safeguarding?filter=critical')}
            linkAriaLabel={t('safeguarding.cta_view_critical')}
          />
          <StatCard
            label={t('safeguarding.label_active_assignments')}
            value={stats.active_assignments}
            icon={Shield}
            color="primary"
            to={tenantPath('/admin/safeguarding?tab=assignments&filter=active')}
            linkAriaLabel={t('safeguarding.cta_view_active_assignments')}
          />
          <StatCard
            label={t('safeguarding.label_consented_wards')}
            value={stats.consented_wards}
            icon={ShieldCheck}
            color="success"
            to={tenantPath('/admin/safeguarding?tab=assignments&filter=consented')}
            linkAriaLabel={t('safeguarding.cta_view_consented')}
          />
          <StatCard
            label={t('safeguarding.label_flags_this_month')}
            value={stats.total_flags_this_month}
            icon={Flag}
            color="secondary"
            to={tenantPath('/admin/safeguarding?filter=unreviewed')}
            linkAriaLabel={t('safeguarding.cta_view_month_flags')}
          />
        </div>
      )}

      {/* Active filter banner — tells the admin what they drilled into */}
      {activeFilterLabel && (
        <div className="flex items-center justify-between rounded-lg border border-primary/20 bg-primary/5 px-4 py-2">
          <div className="flex items-center gap-2 text-sm">
            <Flag size={14} className="text-primary" />
            <span className="text-default-600">
              {t('safeguarding.filter_showing')} <strong className="text-foreground">{activeFilterLabel}</strong>
            </span>
          </div>
          <Button size="sm" variant="light" onPress={clearFilter}>
            {t('safeguarding.filter_clear')}
          </Button>
        </div>
      )}

      {/* Tabs */}
      <Tabs
        selectedKey={activeTab}
        onSelectionChange={(key) => setActiveTab(key as string)}
      >
        <Tab
          key="flagged"
          title={
            <span className="flex items-center gap-2">
              <MessageSquare size={16} />
              {t('safeguarding.tab_flagged_messages')}
            </span>
          }
        />
        <Tab
          key="assignments"
          title={
            <span className="flex items-center gap-2">
              <Users size={16} />
              {t('safeguarding.tab_guardian_assignments')}
            </span>
          }
        />
        <Tab
          key="preferences"
          title={
            <span className="flex items-center gap-2">
              <Shield size={16} />
              {t('safeguarding.tab_member_preferences')}
            </span>
          }
        />
      </Tabs>

      {/* Flagged Messages Tab */}
      {activeTab === 'flagged' && (
        <Card shadow="sm">
          <CardHeader className="flex justify-between items-center">
            <h3 className="text-lg font-semibold">{t('safeguarding.flagged_messages')}</h3>
            <Input
              placeholder={t('safeguarding.placeholder_search_messages')}
              aria-label={t('safeguarding.label_search_safeguarding_messages')}
              size="sm"
              variant="bordered"
              className="max-w-xs"
              startContent={<Search size={14} />}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </CardHeader>
          <CardBody>
            <Table aria-label={t('safeguarding.flagged_messages')} removeWrapper>
              <TableHeader>
                <TableColumn>{t('safeguarding.col_sender')}</TableColumn>
                <TableColumn>{t('safeguarding.col_recipient')}</TableColumn>
                <TableColumn>{t('safeguarding.col_message')}</TableColumn>
                <TableColumn>{t('safeguarding.col_severity')}</TableColumn>
                <TableColumn>{t('safeguarding.col_reason')}</TableColumn>
                <TableColumn>{t('safeguarding.col_date')}</TableColumn>
                <TableColumn>{t('safeguarding.col_status')}</TableColumn>
                <TableColumn>{t('safeguarding.col_actions')}</TableColumn>
              </TableHeader>
              <TableBody emptyContent={t('safeguarding.no_flagged_messages')}>
                {filteredFlags.map((flag) => (
                  <TableRow key={flag.id}>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Avatar size="sm" name={flag.sender.name} className="w-6 h-6" />
                        <span className="text-sm">{flag.sender.name}</span>
                      </div>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Avatar size="sm" name={flag.recipient.name} className="w-6 h-6" />
                        <span className="text-sm">{flag.recipient.name}</span>
                      </div>
                    </TableCell>
                    <TableCell>
                      <p className="text-sm text-default-600 max-w-[200px] truncate">
                        {flag.message_content}
                      </p>
                    </TableCell>
                    <TableCell>
                      <Chip size="sm" color={SEVERITY_COLORS[flag.severity] || 'default'} variant="flat">
                        {flag.severity}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm text-default-500">{flag.flag_reason}</span>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm text-default-400">{formatRelativeTime(flag.created_at)}</span>
                    </TableCell>
                    <TableCell>
                      {flag.is_reviewed ? (
                        <Chip size="sm" color="success" variant="flat" startContent={<CheckCircle size={12} />}>
                          {t('safeguarding.reviewed')}
                        </Chip>
                      ) : (
                        <Chip size="sm" color="warning" variant="flat" startContent={<Clock size={12} />}>
                          {t('safeguarding.pending')}
                        </Chip>
                      )}
                    </TableCell>
                    <TableCell>
                      {!flag.is_reviewed && (
                        <Button
                          size="sm"
                          variant="flat"
                          color="primary"
                          startContent={<Eye size={14} />}
                          onPress={() => {
                            setReviewTarget(flag);
                            reviewModal.onOpen();
                          }}
                        >
                          {t('safeguarding.review')}
                        </Button>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardBody>
        </Card>
      )}

      {/* Guardian Assignments Tab */}
      {activeTab === 'assignments' && (
        <Card shadow="sm">
          <CardHeader className="flex justify-between items-center">
            <h3 className="text-lg font-semibold">{t('safeguarding.guardian_assignments')}</h3>
            <Button
              size="sm"
              color="primary"
              startContent={<UserPlus size={14} />}
              onPress={assignModal.onOpen}
            >
              {t('safeguarding.new_assignment')}
            </Button>
          </CardHeader>
          <CardBody>
            <Table aria-label={t('safeguarding.guardian_assignments')} removeWrapper>
              <TableHeader>
                <TableColumn>{t('safeguarding.col_ward')}</TableColumn>
                <TableColumn>{t('safeguarding.col_guardian')}</TableColumn>
                <TableColumn>{t('safeguarding.col_status')}</TableColumn>
                <TableColumn>{t('safeguarding.col_consent')}</TableColumn>
                <TableColumn>{t('safeguarding.col_created')}</TableColumn>
                <TableColumn>{t('safeguarding.col_expires')}</TableColumn>
                <TableColumn>{t('safeguarding.col_actions')}</TableColumn>
              </TableHeader>
              <TableBody emptyContent={t('safeguarding.no_guardian_assignments')}>
                {filteredAssignments.map((assignment) => (
                  <TableRow key={assignment.id}>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Avatar size="sm" name={assignment.ward.name} className="w-6 h-6" />
                        <span className="text-sm">{assignment.ward.name}</span>
                      </div>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Avatar size="sm" name={assignment.guardian.name} className="w-6 h-6" />
                        <span className="text-sm">{assignment.guardian.name}</span>
                      </div>
                    </TableCell>
                    <TableCell>
                      <Chip
                        size="sm"
                        variant="flat"
                        color={assignment.status === 'active' ? 'success' : assignment.status === 'revoked' ? 'danger' : 'default'}
                      >
                        {assignment.status}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      {assignment.consent_given ? (
                        <CheckCircle size={16} className="text-success" />
                      ) : (
                        <XCircle size={16} className="text-danger" />
                      )}
                    </TableCell>
                    <TableCell>
                      <span className="text-sm text-default-400">{formatRelativeTime(assignment.created_at)}</span>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm text-default-400">
                        {assignment.expires_at ? formatRelativeTime(assignment.expires_at) : t('safeguarding.never')}
                      </span>
                    </TableCell>
                    <TableCell>
                      {assignment.status === 'active' && (
                        <Button
                          size="sm"
                          variant="flat"
                          color="danger"
                          startContent={<UserMinus size={14} />}
                          onPress={() => handleRevokeAssignment(assignment.id)}
                        >
                          {t('safeguarding.revoke')}
                        </Button>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardBody>
        </Card>
      )}

      {/* Member Safeguarding Preferences Tab */}
      {activeTab === 'preferences' && (
        <Card shadow="sm">
          <CardHeader className="flex justify-between items-center">
            <div>
              <h3 className="text-lg font-semibold">{t('safeguarding.member_safeguarding_preferences')}</h3>
              <p className="text-sm text-default-500">{t('safeguarding.member_preferences_desc')}</p>
            </div>
          </CardHeader>
          <CardBody>
            {memberPreferences.length === 0 ? (
              <div className="text-center py-8 text-default-400">
                <Shield size={40} className="mx-auto mb-2 opacity-40" />
                <p>{t('safeguarding.no_member_preferences')}</p>
                <p className="text-sm mt-1">{t('safeguarding.no_member_preferences_desc')}</p>
              </div>
            ) : (
              <Table aria-label={t('safeguarding.member_safeguarding_preferences')}>
                <TableHeader>
                  <TableColumn>{t('safeguarding.col_member')}</TableColumn>
                  <TableColumn>{t('safeguarding.col_selected_options')}</TableColumn>
                  <TableColumn>{t('safeguarding.col_triggers')}</TableColumn>
                  <TableColumn>{t('safeguarding.col_date')}</TableColumn>
                </TableHeader>
                <TableBody>
                  {memberPreferences.map((entry) => (
                    <TableRow key={entry.user_id}>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          <Avatar
                            src={entry.user_avatar || undefined}
                            name={entry.user_name}
                            size="sm"
                          />
                          <span className="font-medium text-sm">{entry.user_name}</span>
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-wrap gap-1">
                          {entry.is_declination_only ? (
                            <Chip size="sm" variant="flat" color="default">{t('safeguarding.declined_none_apply')}</Chip>
                          ) : (
                            entry.options
                              .filter(opt => !opt.is_declination)
                              .map((opt) => (
                                <Chip key={opt.option_key} size="sm" variant="flat" color="primary">
                                  {opt.label}
                                </Chip>
                              ))
                          )}
                        </div>
                      </TableCell>
                      <TableCell>
                        {entry.is_declination_only ? (
                          <Chip size="sm" variant="flat" color="default">{t('safeguarding.declined')}</Chip>
                        ) : entry.has_triggers ? (
                          <Chip size="sm" variant="flat" color="warning">{t('safeguarding.active')}</Chip>
                        ) : (
                          <Chip size="sm" variant="flat" color="default">{t('safeguarding.none')}</Chip>
                        )}
                      </TableCell>
                      <TableCell>
                        <span className="text-sm text-default-500">
                          {formatRelativeTime(entry.consent_given_at)}
                        </span>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardBody>
        </Card>
      )}

      {/* Collapsible guidance panel — title always visible, body in accordion sections */}
      <SafeguardingHelp />

      {/* Review Modal */}
      <Modal
        isOpen={reviewModal.isOpen}
        onOpenChange={reviewModal.onOpenChange}
        size="lg"
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex items-center gap-2">
                <Eye size={20} />
                {t('safeguarding.review_flagged_message')}
              </ModalHeader>
              <ModalBody className="gap-4">
                {reviewTarget && (
                  <>
                    <div className="p-4 rounded-lg bg-default-100">
                      <div className="flex items-center gap-2 mb-2">
                        <span className="text-sm font-medium">{t('safeguarding.from')}:</span>
                        <span className="text-sm">{reviewTarget.sender.name}</span>
                        <span className="text-sm text-default-400 mx-1">{t('safeguarding.to')}</span>
                        <span className="text-sm">{reviewTarget.recipient.name}</span>
                      </div>
                      <Divider className="my-2" />
                      <p className="text-sm text-default-700 whitespace-pre-wrap">
                        {reviewTarget.message_content}
                      </p>
                    </div>

                    <div className="flex items-center gap-4">
                      <div>
                        <span className="text-sm text-default-500">{t('safeguarding.severity')}:</span>{' '}
                        <Chip size="sm" color={SEVERITY_COLORS[reviewTarget.severity]} variant="flat">
                          {reviewTarget.severity}
                        </Chip>
                      </div>
                      <div>
                        <span className="text-sm text-default-500">{t('safeguarding.reason')}:</span>{' '}
                        <span className="text-sm">{reviewTarget.flag_reason}</span>
                      </div>
                    </div>

                    {reviewTarget.ward_name && (
                      <div className="flex items-center gap-2 text-sm">
                        <Shield size={14} className="text-primary" />
                        <span className="text-default-500">{t('safeguarding.ward')}:</span>
                        <span>{reviewTarget.ward_name}</span>
                        {reviewTarget.guardian_name && (
                          <>
                            <span className="text-default-400 mx-1">|</span>
                            <span className="text-default-500">{t('safeguarding.guardian')}:</span>
                            <span>{reviewTarget.guardian_name}</span>
                          </>
                        )}
                      </div>
                    )}

                    <Textarea
                      label={t('safeguarding.label_review_notes')}
                      placeholder={t('safeguarding.placeholder_review_notes')}
                      value={reviewNotes}
                      onChange={(e) => setReviewNotes(e.target.value)}
                      minRows={3}
                    />
                  </>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>{t('safeguarding.cancel')}</Button>
                <Button
                  color="primary"
                  isLoading={reviewing}
                  startContent={<CheckCircle size={16} />}
                  onPress={handleReview}
                >
                  {t('safeguarding.mark_as_reviewed')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Create Assignment Modal */}
      <Modal
        isOpen={assignModal.isOpen}
        onOpenChange={assignModal.onOpenChange}
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex items-center gap-2">
                <UserPlus size={20} />
                {t('safeguarding.create_guardian_assignment')}
              </ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label={t('safeguarding.label_ward_email')}
                  placeholder={t('safeguarding.placeholder_ward_email')}
                  value={wardEmail}
                  onChange={(e) => setWardEmail(e.target.value)}
                  description={t('safeguarding.desc_the_vulnerable_user_who_needs_oversight')}
                />
                <Input
                  label={t('safeguarding.label_guardian_email')}
                  placeholder={t('safeguarding.placeholder_guardian_email')}
                  value={guardianEmail}
                  onChange={(e) => setGuardianEmail(e.target.value)}
                  description={t('safeguarding.desc_guardian_monitors_messages')}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>{t('safeguarding.cancel')}</Button>
                <Button
                  color="primary"
                  isLoading={creating}
                  isDisabled={!wardEmail.trim() || !guardianEmail.trim()}
                  onPress={handleCreateAssignment}
                >
                  {t('safeguarding.create_assignment')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default SafeguardingDashboard;
