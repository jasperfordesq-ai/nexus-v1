// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Safeguarding Dashboard (MS2)
 * Admin page for reviewing flagged messages, managing guardian assignments,
 * and monitoring safeguarding of vulnerable users (wards).
 */

import { useState, useEffect, useCallback } from 'react';
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
import {
  Shield,
  ShieldAlert,
  ShieldCheck,
  AlertTriangle,
  Eye,
  Users,
  MessageSquare,
  CheckCircle,
  XCircle,
  RefreshCw,
  Search,
  UserPlus,
  UserMinus,
  Clock,
  Flag,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';
import { PageHeader } from '../../components';
import { StatCard } from '../../components';

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
  usePageTitle('Admin - Safeguarding');
  const toast = useToast();

  const [activeTab, setActiveTab] = useState('flagged');
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [flaggedMessages, setFlaggedMessages] = useState<FlaggedMessage[]>([]);
  const [assignments, setAssignments] = useState<GuardianAssignment[]>([]);
  const [searchQuery, setSearchQuery] = useState('');

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
      const [statsRes, flagsRes, assignmentsRes] = await Promise.all([
        api.get('/v2/admin/safeguarding/dashboard'),
        api.get('/v2/admin/safeguarding/flagged-messages'),
        api.get('/v2/admin/safeguarding/assignments'),
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
    } catch (err) {
      logError('SafeguardingDashboard.load', err);
      toast.error('Failed to load safeguarding data');
    }
    setLoading(false);
  }, [toast]);

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
        toast.success('Message reviewed');
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
      toast.error('Failed to review message');
    }
    setReviewing(false);
  }, [reviewTarget, reviewNotes, toast, reviewModal, loadData]);

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
        toast.success('Guardian assignment created');
        setWardEmail('');
        setGuardianEmail('');
        assignModal.onClose();
        loadData();
      }
    } catch (err) {
      logError('SafeguardingDashboard.createAssignment', err);
      toast.error('Failed to create assignment');
    }
    setCreating(false);
  }, [wardEmail, guardianEmail, toast, assignModal, loadData]);

  // ─── Revoke assignment ───
  const handleRevokeAssignment = useCallback(async (assignmentId: number) => {
    try {
      await api.delete(`/v2/admin/safeguarding/assignments/${assignmentId}`);
      toast.success('Assignment revoked');
      setAssignments((prev) =>
        prev.map((a) => a.id === assignmentId ? { ...a, status: 'revoked' as const } : a)
      );
    } catch (err) {
      logError('SafeguardingDashboard.revoke', err);
      toast.error('Failed to revoke assignment');
    }
  }, [toast]);

  // ─── Filtered items ───
  const filteredFlags = searchQuery
    ? flaggedMessages.filter((m) =>
        m.message_content.toLowerCase().includes(searchQuery.toLowerCase()) ||
        m.sender.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        m.recipient.name.toLowerCase().includes(searchQuery.toLowerCase())
      )
    : flaggedMessages;

  // ─── Render ───
  if (loading) {
    return (
      <div>
        <PageHeader title="Safeguarding" description="Monitor and manage safeguarding of vulnerable users" />
        <div className="flex h-64 items-center justify-center">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Safeguarding"
        description="Monitor flagged messages and manage guardian assignments"
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              size="sm"
              startContent={<RefreshCw size={16} />}
              onPress={() => loadData()}
            >
              Refresh
            </Button>
            <Button
              color="primary"
              size="sm"
              startContent={<UserPlus size={16} />}
              onPress={assignModal.onOpen}
            >
              New Assignment
            </Button>
          </div>
        }
      />

      {/* Stats */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
          <StatCard
            label="Unreviewed Flags"
            value={stats.unreviewed_flags}
            icon={ShieldAlert}
            color={stats.unreviewed_flags > 0 ? 'danger' : 'success'}
          />
          <StatCard label="Critical Flags" value={stats.critical_flags} icon={AlertTriangle} color="warning" />
          <StatCard label="Active Assignments" value={stats.active_assignments} icon={Shield} color="primary" />
          <StatCard label="Consented Wards" value={stats.consented_wards} icon={ShieldCheck} color="success" />
          <StatCard label="Flags This Month" value={stats.total_flags_this_month} icon={Flag} color="secondary" />
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
              Flagged Messages ({flaggedMessages.filter((m) => !m.is_reviewed).length})
            </span>
          }
        />
        <Tab
          key="assignments"
          title={
            <span className="flex items-center gap-2">
              <Users size={16} />
              Guardian Assignments ({assignments.filter((a) => a.status === 'active').length})
            </span>
          }
        />
      </Tabs>

      {/* Flagged Messages Tab */}
      {activeTab === 'flagged' && (
        <Card shadow="sm">
          <CardHeader className="flex justify-between items-center">
            <h3 className="text-lg font-semibold">Flagged Messages</h3>
            <Input
              placeholder="Search messages..."
              aria-label="Search safeguarding messages"
              size="sm"
              variant="bordered"
              className="max-w-xs"
              startContent={<Search size={14} />}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </CardHeader>
          <CardBody>
            <Table aria-label="Flagged messages" removeWrapper>
              <TableHeader>
                <TableColumn>SENDER</TableColumn>
                <TableColumn>RECIPIENT</TableColumn>
                <TableColumn>MESSAGE</TableColumn>
                <TableColumn>SEVERITY</TableColumn>
                <TableColumn>REASON</TableColumn>
                <TableColumn>DATE</TableColumn>
                <TableColumn>STATUS</TableColumn>
                <TableColumn>ACTIONS</TableColumn>
              </TableHeader>
              <TableBody emptyContent="No flagged messages found.">
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
                          Reviewed
                        </Chip>
                      ) : (
                        <Chip size="sm" color="warning" variant="flat" startContent={<Clock size={12} />}>
                          Pending
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
                          Review
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
            <h3 className="text-lg font-semibold">Guardian Assignments</h3>
            <Button
              size="sm"
              color="primary"
              startContent={<UserPlus size={14} />}
              onPress={assignModal.onOpen}
            >
              New Assignment
            </Button>
          </CardHeader>
          <CardBody>
            <Table aria-label="Guardian assignments" removeWrapper>
              <TableHeader>
                <TableColumn>WARD</TableColumn>
                <TableColumn>GUARDIAN</TableColumn>
                <TableColumn>STATUS</TableColumn>
                <TableColumn>CONSENT</TableColumn>
                <TableColumn>CREATED</TableColumn>
                <TableColumn>EXPIRES</TableColumn>
                <TableColumn>ACTIONS</TableColumn>
              </TableHeader>
              <TableBody emptyContent="No guardian assignments found.">
                {assignments.map((assignment) => (
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
                        {assignment.expires_at ? formatRelativeTime(assignment.expires_at) : 'Never'}
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
                          Revoke
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
                Review Flagged Message
              </ModalHeader>
              <ModalBody className="gap-4">
                {reviewTarget && (
                  <>
                    <div className="p-4 rounded-lg bg-default-100">
                      <div className="flex items-center gap-2 mb-2">
                        <span className="text-sm font-medium">From:</span>
                        <span className="text-sm">{reviewTarget.sender.name}</span>
                        <span className="text-sm text-default-400 mx-1">to</span>
                        <span className="text-sm">{reviewTarget.recipient.name}</span>
                      </div>
                      <Divider className="my-2" />
                      <p className="text-sm text-default-700 whitespace-pre-wrap">
                        {reviewTarget.message_content}
                      </p>
                    </div>

                    <div className="flex items-center gap-4">
                      <div>
                        <span className="text-sm text-default-500">Severity:</span>{' '}
                        <Chip size="sm" color={SEVERITY_COLORS[reviewTarget.severity]} variant="flat">
                          {reviewTarget.severity}
                        </Chip>
                      </div>
                      <div>
                        <span className="text-sm text-default-500">Reason:</span>{' '}
                        <span className="text-sm">{reviewTarget.flag_reason}</span>
                      </div>
                    </div>

                    {reviewTarget.ward_name && (
                      <div className="flex items-center gap-2 text-sm">
                        <Shield size={14} className="text-primary" />
                        <span className="text-default-500">Ward:</span>
                        <span>{reviewTarget.ward_name}</span>
                        {reviewTarget.guardian_name && (
                          <>
                            <span className="text-default-400 mx-1">|</span>
                            <span className="text-default-500">Guardian:</span>
                            <span>{reviewTarget.guardian_name}</span>
                          </>
                        )}
                      </div>
                    )}

                    <Textarea
                      label="Review Notes"
                      placeholder="Add notes about your review (action taken, severity assessment, etc.)"
                      value={reviewNotes}
                      onChange={(e) => setReviewNotes(e.target.value)}
                      minRows={3}
                    />
                  </>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>Cancel</Button>
                <Button
                  color="primary"
                  isLoading={reviewing}
                  startContent={<CheckCircle size={16} />}
                  onPress={handleReview}
                >
                  Mark as Reviewed
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
                Create Guardian Assignment
              </ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label="Ward Email"
                  placeholder="ward@example.com"
                  value={wardEmail}
                  onChange={(e) => setWardEmail(e.target.value)}
                  description="The vulnerable user who needs oversight"
                />
                <Input
                  label="Guardian Email"
                  placeholder="guardian@example.com"
                  value={guardianEmail}
                  onChange={(e) => setGuardianEmail(e.target.value)}
                  description="The user who will monitor the ward's messages"
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>Cancel</Button>
                <Button
                  color="primary"
                  isLoading={creating}
                  isDisabled={!wardEmail.trim() || !guardianEmail.trim()}
                  onPress={handleCreateAssignment}
                >
                  Create Assignment
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
