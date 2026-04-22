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
  options: { option_key: string; label: string; }[];
  consent_given_at: string;
  has_triggers: boolean;
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
  usePageTitle("Safeguarding");
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
      toast.error("Failed to load safeguarding data");
    }
    setLoading(false);
  }, [toast])


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
        toast.success("Message Reviewed");
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
      toast.error("Failed to review message");
    }
    setReviewing(false);
  }, [reviewTarget, reviewNotes, toast, reviewModal, loadData])


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
        toast.success("Guardian assignment created");
        setWardEmail('');
        setGuardianEmail('');
        assignModal.onClose();
        loadData();
      }
    } catch (err) {
      logError('SafeguardingDashboard.createAssignment', err);
      toast.error("Failed to create assignment");
    }
    setCreating(false);
  }, [wardEmail, guardianEmail, toast, assignModal, loadData])


  // ─── Revoke assignment ───
  const handleRevokeAssignment = useCallback(async (assignmentId: number) => {
    try {
      await api.delete(`/v2/admin/safeguarding/assignments/${assignmentId}`);
      toast.success("Assignment Revoked");
      setAssignments((prev) =>
        prev.map((a) => a.id === assignmentId ? { ...a, status: 'revoked' as const } : a)
      );
    } catch (err) {
      logError('SafeguardingDashboard.revoke', err);
      toast.error("Failed to revoke assignment");
    }
  }, [toast])


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
      return assignments.filter((a) => a.status === 'active' && a.consent_given_at);
    }
    return assignments;
  }, [assignments, assignmentFilter, activeTab]);

  // Friendly label for the active drill-down filter, shown above the table so
  // the admin knows which stat they clicked (and can clear it).
  const activeFilterLabel = useMemo(() => {
    if (activeTab === 'flagged') {
      if (flaggedFilter === 'unreviewed') return "Unreviewed flags";
      if (flaggedFilter === 'critical') return "Critical and high-severity flags";
      if (flaggedFilter === 'reviewed') return "Reviewed flags";
    }
    if (activeTab === 'assignments') {
      if (assignmentFilter === 'active') return "Active assignments";
      if (assignmentFilter === 'consented') return "Assignments with consent given";
    }
    return null;
  }, [activeTab, flaggedFilter, assignmentFilter]);


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
        <PageHeader title={"Safeguarding Dashboard"} description={"Overview of guardian assignments, flagged messages, and safeguarding activity"} />
        <div className="flex h-64 items-center justify-center">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={"Safeguarding Dashboard"}
        description={"Overview of guardian assignments, flagged messages, and safeguarding activity"}
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              size="sm"
              startContent={<RefreshCw size={16} />}
              onPress={() => loadData()}
            >
              {"Refresh"}
            </Button>
            <Button
              color="primary"
              size="sm"
              startContent={<UserPlus size={16} />}
              onPress={assignModal.onOpen}
            >
              {"New Assignment"}
            </Button>
          </div>
        }
      />

      {/* Stats — each card deep-links to the matching tab/filter combination */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
          <StatCard
            label={"Unreviewed Flags"}
            value={stats.unreviewed_flags}
            icon={ShieldAlert}
            color={stats.unreviewed_flags > 0 ? 'danger' : 'success'}
            to={tenantPath('/admin/safeguarding?filter=unreviewed')}
            linkAriaLabel={"View unreviewed flags"}
          />
          <StatCard
            label={"Critical Flags"}
            value={stats.critical_flags}
            icon={AlertTriangle}
            color="warning"
            to={tenantPath('/admin/safeguarding?filter=critical')}
            linkAriaLabel={"View critical and high-severity flags"}
          />
          <StatCard
            label={"Active Assignments"}
            value={stats.active_assignments}
            icon={Shield}
            color="primary"
            to={tenantPath('/admin/safeguarding?tab=assignments&filter=active')}
            linkAriaLabel={"View active guardian assignments"}
          />
          <StatCard
            label={"Consented Wards"}
            value={stats.consented_wards}
            icon={ShieldCheck}
            color="success"
            to={tenantPath('/admin/safeguarding?tab=assignments&filter=consented')}
            linkAriaLabel={"View assignments with consent given"}
          />
          <StatCard
            label={"Flags This Month"}
            value={stats.total_flags_this_month}
            icon={Flag}
            color="secondary"
            to={tenantPath('/admin/safeguarding?filter=unreviewed')}
            linkAriaLabel={"View flags raised this month"}
          />
        </div>
      )}

      {/* Active filter banner — tells the admin what they drilled into */}
      {activeFilterLabel && (
        <div className="flex items-center justify-between rounded-lg border border-primary/20 bg-primary/5 px-4 py-2">
          <div className="flex items-center gap-2 text-sm">
            <Flag size={14} className="text-primary" />
            <span className="text-default-600">
              {"Showing"} <strong className="text-foreground">{activeFilterLabel}</strong>
            </span>
          </div>
          <Button size="sm" variant="light" onPress={clearFilter}>
            {"Clear filter"}
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
              {`Flagged Messages`}
            </span>
          }
        />
        <Tab
          key="assignments"
          title={
            <span className="flex items-center gap-2">
              <Users size={16} />
              {`Guardian Assignments`}
            </span>
          }
        />
        <Tab
          key="preferences"
          title={
            <span className="flex items-center gap-2">
              <Shield size={16} />
              {`Member Preferences`}
            </span>
          }
        />
      </Tabs>

      {/* Flagged Messages Tab */}
      {activeTab === 'flagged' && (
        <Card shadow="sm">
          <CardHeader className="flex justify-between items-center">
            <h3 className="text-lg font-semibold">{"Flagged Messages"}</h3>
            <Input
              placeholder={"Search Messages..."}
              aria-label={"Search Safeguarding Messages"}
              size="sm"
              variant="bordered"
              className="max-w-xs"
              startContent={<Search size={14} />}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </CardHeader>
          <CardBody>
            <Table aria-label={"Flagged Messages"} removeWrapper>
              <TableHeader>
                <TableColumn>{"Sender"}</TableColumn>
                <TableColumn>{"Recipient"}</TableColumn>
                <TableColumn>{"Message"}</TableColumn>
                <TableColumn>{"Severity"}</TableColumn>
                <TableColumn>{"Reason"}</TableColumn>
                <TableColumn>{"Date"}</TableColumn>
                <TableColumn>{"Status"}</TableColumn>
                <TableColumn>{"Actions"}</TableColumn>
              </TableHeader>
              <TableBody emptyContent={"No flagged messages"}>
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
                          {"Reviewed"}
                        </Chip>
                      ) : (
                        <Chip size="sm" color="warning" variant="flat" startContent={<Clock size={12} />}>
                          {"Pending"}
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
                          {"Review"}
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
            <h3 className="text-lg font-semibold">{"Guardian Assignments"}</h3>
            <Button
              size="sm"
              color="primary"
              startContent={<UserPlus size={14} />}
              onPress={assignModal.onOpen}
            >
              {"New Assignment"}
            </Button>
          </CardHeader>
          <CardBody>
            <Table aria-label={"Guardian Assignments"} removeWrapper>
              <TableHeader>
                <TableColumn>{"Ward"}</TableColumn>
                <TableColumn>{"Guardian"}</TableColumn>
                <TableColumn>{"Status"}</TableColumn>
                <TableColumn>{"Consent"}</TableColumn>
                <TableColumn>{"Created"}</TableColumn>
                <TableColumn>{"Expires"}</TableColumn>
                <TableColumn>{"Actions"}</TableColumn>
              </TableHeader>
              <TableBody emptyContent={"No guardian assignments"}>
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
                        {assignment.expires_at ? formatRelativeTime(assignment.expires_at) : "Never"}
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
                          {"Revoke"}
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
              <h3 className="text-lg font-semibold">{"Member Safeguarding Preferences"}</h3>
              <p className="text-sm text-default-500">{"View and manage individual member safeguarding preferences"}</p>
            </div>
          </CardHeader>
          <CardBody>
            {memberPreferences.length === 0 ? (
              <div className="text-center py-8 text-default-400">
                <Shield size={40} className="mx-auto mb-2 opacity-40" />
                <p>{"No member preferences"}</p>
                <p className="text-sm mt-1">{"No members have configured safeguarding preferences yet"}</p>
              </div>
            ) : (
              <Table aria-label={"Member Safeguarding Preferences"}>
                <TableHeader>
                  <TableColumn>{"Member"}</TableColumn>
                  <TableColumn>{"Selected Options"}</TableColumn>
                  <TableColumn>{"Triggers"}</TableColumn>
                  <TableColumn>{"Date"}</TableColumn>
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
                          {entry.options.map((opt) => (
                            <Chip key={opt.option_key} size="sm" variant="flat" color="primary">
                              {opt.label}
                            </Chip>
                          ))}
                        </div>
                      </TableCell>
                      <TableCell>
                        {entry.has_triggers ? (
                          <Chip size="sm" variant="flat" color="warning">{"Active"}</Chip>
                        ) : (
                          <Chip size="sm" variant="flat" color="default">{"None"}</Chip>
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
                {"Review Flagged"}
              </ModalHeader>
              <ModalBody className="gap-4">
                {reviewTarget && (
                  <>
                    <div className="p-4 rounded-lg bg-default-100">
                      <div className="flex items-center gap-2 mb-2">
                        <span className="text-sm font-medium">{"From"}:</span>
                        <span className="text-sm">{reviewTarget.sender.name}</span>
                        <span className="text-sm text-default-400 mx-1">{"To"}</span>
                        <span className="text-sm">{reviewTarget.recipient.name}</span>
                      </div>
                      <Divider className="my-2" />
                      <p className="text-sm text-default-700 whitespace-pre-wrap">
                        {reviewTarget.message_content}
                      </p>
                    </div>

                    <div className="flex items-center gap-4">
                      <div>
                        <span className="text-sm text-default-500">{"Severity"}:</span>{' '}
                        <Chip size="sm" color={SEVERITY_COLORS[reviewTarget.severity]} variant="flat">
                          {reviewTarget.severity}
                        </Chip>
                      </div>
                      <div>
                        <span className="text-sm text-default-500">{"Reason"}:</span>{' '}
                        <span className="text-sm">{reviewTarget.flag_reason}</span>
                      </div>
                    </div>

                    {reviewTarget.ward_name && (
                      <div className="flex items-center gap-2 text-sm">
                        <Shield size={14} className="text-primary" />
                        <span className="text-default-500">{"Ward"}:</span>
                        <span>{reviewTarget.ward_name}</span>
                        {reviewTarget.guardian_name && (
                          <>
                            <span className="text-default-400 mx-1">|</span>
                            <span className="text-default-500">{"Guardian"}:</span>
                            <span>{reviewTarget.guardian_name}</span>
                          </>
                        )}
                      </div>
                    )}

                    <Textarea
                      label={"Review Notes"}
                      placeholder={"Review Notes..."}
                      value={reviewNotes}
                      onChange={(e) => setReviewNotes(e.target.value)}
                      minRows={3}
                    />
                  </>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>{"Cancel"}</Button>
                <Button
                  color="primary"
                  isLoading={reviewing}
                  startContent={<CheckCircle size={16} />}
                  onPress={handleReview}
                >
                  {"Mark as Reviewed"}
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
                {"Create Guardian Assignment"}
              </ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label={"Ward Email"}
                  placeholder="ward@example.com"
                  value={wardEmail}
                  onChange={(e) => setWardEmail(e.target.value)}
                  description={"The member who needs oversight from the assigned guardian"}
                />
                <Input
                  label={"Guardian Email"}
                  placeholder="guardian@example.com"
                  value={guardianEmail}
                  onChange={(e) => setGuardianEmail(e.target.value)}
                  description={"The guardian can view messages sent to and from this member"}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>{"Cancel"}</Button>
                <Button
                  color="primary"
                  isLoading={creating}
                  isDisabled={!wardEmail.trim() || !guardianEmail.trim()}
                  onPress={handleCreateAssignment}
                >
                  {"Create Assignment"}
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
