// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Opportunity Detail Page — view a single volunteering opportunity,
 * its shifts, and apply.
 *
 * API: GET /api/v2/volunteering/opportunities/{id}
 *      POST /api/v2/volunteering/opportunities/{id}/apply
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Avatar,
  Checkbox,
  Chip,
  Input,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
  useDisclosure,
  Spinner,
} from '@heroui/react';
import {
  MapPin,
  Calendar,
  Clock,
  Briefcase,
  Users,
  Building2,
  Wifi,
  Tag,
  ChevronRight,
  AlertTriangle,
  RefreshCw,
  Search,
  Send,
  CheckCircle,
  XCircle,
  ClipboardList,
  MessageSquare,
  ChevronDown,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { LoadingScreen } from '@/components/feedback';
import { Breadcrumbs } from '@/components/navigation';
import { useAuth, useTenant } from '@/contexts';
import { useToast } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

import { useTranslation } from 'react-i18next';
/* ───────────────────────── Types ───────────────────────── */

interface Shift {
  id: number;
  start_time: string;
  end_time: string;
  capacity: number | null;
  signup_count: number;
  spots_available: number | null;
}

interface Application {
  id: number;
  status: string;
  message: string | null;
  created_at: string;
}

interface OpportunityDetail {
  id: number;
  title: string;
  description: string;
  location: string;
  skills_needed: string;
  start_date: string | null;
  end_date: string | null;
  is_active: boolean;
  is_remote: boolean;
  category: string | null;
  organization: { id: number; name: string; logo_url: string | null };
  created_at: string;
  shifts: Shift[];
  has_applied?: boolean;
  application?: Application | null;
  is_owner?: boolean;
}

interface OppApplicationItem {
  id: number;
  status: 'pending' | 'approved' | 'declined';
  message: string | null;
  created_at: string;
  user: {
    id: number;
    name: string;
    email: string;
    avatar_url: string | null;
  };
  shift: {
    id: number;
    start_time: string;
    end_time: string;
  } | null;
}

type AppStatusFilter = 'all' | 'pending' | 'approved' | 'declined';

/* ───────────────────────── Helpers ───────────────────────── */

function formatDate(d: string) {
  return new Date(d).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
}

function formatShortDate(d: string) {
  return new Date(d).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
}

function formatTime(d: string) {
  return new Date(d).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function statusColor(status: string): 'warning' | 'success' | 'danger' | 'default' {
  if (status === 'pending') return 'warning';
  if (status === 'approved') return 'success';
  if (status === 'declined') return 'danger';
  return 'default';
}

/* ─────────────────── Applications Panel ─────────────────── */

interface ApplicationsPanelProps {
  opportunityId: number;
}

function ApplicationsPanel({ opportunityId }: ApplicationsPanelProps) {
  const toast = useToast();
  const { t } = useTranslation('volunteering');
  const [applications, setApplications] = useState<OppApplicationItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [statusFilter, setStatusFilter] = useState<AppStatusFilter>('all');
  const [actionLoading, setActionLoading] = useState<Record<number, boolean>>({});
  const [nameSearch, setNameSearch] = useState('');
  const [selected, setSelected] = useState<Set<number>>(new Set());

  const loadApplications = useCallback(async (filter: AppStatusFilter, nextCursor: string | null = null) => {
    try {
      if (nextCursor) setIsLoadingMore(true);
      else setIsLoading(true);

      const params = new URLSearchParams({ per_page: '20' });
      if (filter !== 'all') params.set('status', filter);
      if (nextCursor) params.set('cursor', nextCursor);

      const response = await api.get<{ items: OppApplicationItem[]; cursor: string | null; has_more: boolean }>(
        `/v2/volunteering/opportunities/${opportunityId}/applications?${params}`
      );

      if (response.success && response.data) {
        const { items, cursor: newCursor, has_more } = response.data;
        setApplications((prev) => nextCursor ? [...prev, ...items] : items);
        setCursor(newCursor);
        setHasMore(has_more);
      } else {
        toast.error(response.error || 'Failed to load applications.');
      }
    } catch (err) {
      logError('Failed to load applications', err);
      toast.error(t('applications_load_failed'));
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [opportunityId, toast]);

  useEffect(() => {
    setApplications([]);
    setCursor(null);
    loadApplications(statusFilter);
  }, [statusFilter, loadApplications]);

  async function handleAction(applicationId: number, action: 'approve' | 'decline') {
    setActionLoading((prev) => ({ ...prev, [applicationId]: true }));
    try {
      const response = await api.put(`/v2/volunteering/applications/${applicationId}`, { action });
      if (response.success) {
        toast.success(action === 'approve' ? 'Application approved.' : 'Application declined.');
        setApplications((prev) =>
          prev.map((a) =>
            a.id === applicationId
              ? { ...a, status: action === 'approve' ? 'approved' : 'declined' }
              : a
          )
        );
      } else {
        toast.error(response.error || `Failed to ${action} application.`);
      }
    } catch (err) {
      logError(`Failed to ${action} application`, err);
      toast.error(t('something_wrong'));
    } finally {
      setActionLoading((prev) => ({ ...prev, [applicationId]: false }));
    }
  }

  const filters: { key: AppStatusFilter; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'pending', label: 'Pending' },
    { key: 'approved', label: 'Approved' },
    { key: 'declined', label: 'Declined' },
  ];

  const pendingCount = applications.filter((a) => a.status === 'pending').length;

  const filteredApplications = nameSearch.trim()
    ? applications.filter((a) =>
        a.user.name.toLowerCase().includes(nameSearch.toLowerCase())
      )
    : applications;

  const pendingFiltered = filteredApplications.filter((a) => a.status === 'pending');
  const allPendingSelected =
    pendingFiltered.length > 0 && pendingFiltered.every((a) => selected.has(a.id));
  const somePendingSelected =
    pendingFiltered.some((a) => selected.has(a.id)) && !allPendingSelected;

  async function handleBulkAction(action: 'approve' | 'decline') {
    const ids = Array.from(selected);
    for (const id of ids) {
      await handleAction(id, action);
    }
    setSelected(new Set());
    loadApplications(statusFilter);
  }

  return (
    <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.2 }}>
      <GlassCard className="p-6 space-y-4">
        <div className="flex items-center gap-3 flex-wrap">
          <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2">
            <ClipboardList className="w-5 h-5 text-indigo-400" aria-hidden="true" />
            Applications
            {pendingCount > 0 && statusFilter === 'all' && (
              <Chip size="sm" color="warning" variant="flat">{pendingCount} pending</Chip>
            )}
          </h2>
          {pendingFiltered.length > 0 && (
            <Checkbox
              size="sm"
              isIndeterminate={somePendingSelected}
              isSelected={allPendingSelected}
              onValueChange={(checked) => {
                if (checked) {
                  setSelected(new Set(pendingFiltered.map((a) => a.id)));
                } else {
                  setSelected(new Set());
                }
              }}
              aria-label="Select all visible pending applications"
            >
              <span className="text-xs text-theme-muted">Select all</span>
            </Checkbox>
          )}
        </div>

        <div className="flex flex-wrap gap-2">
          {filters.map((f) => (
            <Button
              key={f.key}
              size="sm"
              variant={statusFilter === f.key ? 'solid' : 'flat'}
              className={statusFilter === f.key
                ? 'bg-gradient-to-r from-indigo-500 to-violet-600 text-white'
                : 'bg-theme-elevated text-theme-muted'
              }
              onPress={() => setStatusFilter(f.key)}
            >
              {f.label}
            </Button>
          ))}
        </div>

        <Input
          size="sm"
          placeholder="Search by name..."
          value={nameSearch}
          onValueChange={setNameSearch}
          startContent={<Search className="w-3.5 h-3.5 text-theme-subtle" />}
          aria-label="Search volunteers by name"
          classNames={{ base: 'max-w-xs', inputWrapper: 'bg-theme-elevated' }}
        />

        {selected.size > 0 && (
          <div className="flex items-center gap-3 p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/30">
            <span className="text-sm text-indigo-400 font-medium">{selected.size} selected</span>
            <Button
              size="sm"
              color="success"
              variant="flat"
              startContent={<CheckCircle className="w-3.5 h-3.5" />}
              onPress={() => handleBulkAction('approve')}
            >
              Approve All
            </Button>
            <Button
              size="sm"
              color="danger"
              variant="flat"
              startContent={<XCircle className="w-3.5 h-3.5" />}
              onPress={() => handleBulkAction('decline')}
            >
              Decline All
            </Button>
            <Button size="sm" variant="light" onPress={() => setSelected(new Set())}>
              Clear
            </Button>
          </div>
        )}

        {isLoading ? (
          <div className="flex justify-center py-8">
            <Spinner size="md" />
          </div>
        ) : applications.length === 0 ? (
          <div className="text-center py-8">
            <Users className="w-10 h-10 text-theme-subtle mx-auto mb-3" aria-hidden="true" />
            <p className="text-sm text-theme-muted">
              {statusFilter === 'all' ? 'No applications yet.' : `No ${statusFilter} applications.`}
            </p>
          </div>
        ) : (
          <div className="space-y-3">
            {filteredApplications.map((app) => (
              <div
                key={app.id}
                className="flex flex-col sm:flex-row sm:items-start gap-3 p-4 rounded-xl bg-theme-elevated border border-theme-default"
              >
                {app.status === 'pending' && (
                  <Checkbox
                    size="sm"
                    isSelected={selected.has(app.id)}
                    onValueChange={(checked) => {
                      setSelected((prev) => {
                        const next = new Set(prev);
                        if (checked) next.add(app.id);
                        else next.delete(app.id);
                        return next;
                      });
                    }}
                    aria-label={`Select application from ${app.user.name}`}
                    className="flex-shrink-0 self-center"
                  />
                )}
                <Avatar
                  src={resolveAvatarUrl(app.user.avatar_url) || undefined}
                  name={app.user.name}
                  size="md"
                  className="flex-shrink-0"
                />
                <div className="flex-1 min-w-0 space-y-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium text-theme-primary text-sm">{app.user.name}</span>
                    <Chip size="sm" variant="flat" color={statusColor(app.status)}>
                      {app.status}
                    </Chip>
                  </div>
                  {app.message && (
                    <p className="text-xs text-theme-muted flex items-start gap-1">
                      <MessageSquare className="w-3.5 h-3.5 flex-shrink-0 mt-0.5" aria-hidden="true" />
                      <span className="line-clamp-2">{app.message}</span>
                    </p>
                  )}
                  {app.shift && (
                    <p className="text-xs text-theme-subtle flex items-center gap-1">
                      <Clock className="w-3.5 h-3.5 flex-shrink-0" aria-hidden="true" />
                      {formatShortDate(app.shift.start_time)} · {formatTime(app.shift.start_time)}–{formatTime(app.shift.end_time)}
                    </p>
                  )}
                  <p className="text-xs text-theme-subtle">Applied {formatDate(app.created_at)}</p>
                </div>

                {app.status === 'pending' && (
                  <div className="flex gap-2 sm:flex-shrink-0">
                    <Button
                      size="sm"
                      color="success"
                      variant="flat"
                      startContent={<CheckCircle className="w-3.5 h-3.5" aria-hidden="true" />}
                      isLoading={actionLoading[app.id]}
                      onPress={() => handleAction(app.id, 'approve')}
                    >
                      Approve
                    </Button>
                    <Button
                      size="sm"
                      color="danger"
                      variant="flat"
                      startContent={<XCircle className="w-3.5 h-3.5" aria-hidden="true" />}
                      isLoading={actionLoading[app.id]}
                      onPress={() => handleAction(app.id, 'decline')}
                    >
                      Decline
                    </Button>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}

        {hasMore && (
          <div className="flex justify-center pt-2">
            <Button
              size="sm"
              variant="flat"
              className="bg-theme-elevated text-theme-muted"
              startContent={isLoadingMore ? <Spinner size="sm" /> : <ChevronDown className="w-4 h-4" aria-hidden="true" />}
              isDisabled={isLoadingMore}
              onPress={() => loadApplications(statusFilter, cursor)}
            >
              Load more
            </Button>
          </div>
        )}
      </GlassCard>
    </motion.div>
  );
}

/* ───────────────────────── Component ───────────────────────── */

export function OpportunityDetailPage() {
  const { id } = useParams<{ id: string }>();
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const { t } = useTranslation('volunteering');

  usePageTitle('Opportunity Details');

  const [opportunity, setOpportunity] = useState<OpportunityDetail | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Apply modal
  const applyModal = useDisclosure();
  const [applyMessage, setApplyMessage] = useState('');
  const [isApplying, setIsApplying] = useState(false);
  const [selectedShiftId, setSelectedShiftId] = useState<number | null>(null);

  const load = useCallback(async () => {
    if (!id) return;
    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<OpportunityDetail>(`/v2/volunteering/opportunities/${id}`);
      if (response.success && response.data) {
        setOpportunity(response.data);
      } else {
        setError('Opportunity not found.');
      }
    } catch (err) {
      logError('Failed to load opportunity', err);
      setError('Unable to load this opportunity. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    load();
  }, [load]);

  async function handleApply() {
    if (!id) return;
    try {
      setIsApplying(true);
      const body: Record<string, unknown> = { message: applyMessage };
      if (selectedShiftId) body.shift_id = selectedShiftId;

      const response = await api.post(`/v2/volunteering/opportunities/${id}/apply`, body);
      if (response.success) {
        toast.success(t('application_submitted'));
        applyModal.onClose();
        setApplyMessage('');
        setSelectedShiftId(null);
        load(); // Refresh to show applied state
      } else {
        toast.error(response.error || 'Failed to apply.');
      }
    } catch (err) {
      logError('Failed to apply', err);
      toast.error(t('something_wrong'));
    } finally {
      setIsApplying(false);
    }
  }


  if (isLoading) return <LoadingScreen />;

  if (error || !opportunity) {
    return (
      <div className="max-w-3xl mx-auto px-4 py-8">
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{error || 'Opportunity not found.'}</p>
          <Button
            className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={load}
          >
            Try Again
          </Button>
        </GlassCard>
      </div>
    );
  }

  const opp = opportunity;
  const upcomingShifts = (opp.shifts || []).filter((s) => new Date(s.start_time) >= new Date());

  return (
    <div className="max-w-4xl mx-auto px-4 py-6 space-y-6">
      <Breadcrumbs
        items={[
          { label: 'Volunteering', href: '/volunteering' },
          { label: opp.title },
        ]}
      />

      <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }}>
        {/* Header Card */}
        <GlassCard className="p-6 space-y-5">
          <div className="flex items-start gap-4">
            <Avatar
              src={opp.organization.logo_url || undefined}
              name={opp.organization.name}
              size="lg"
              className="flex-shrink-0"
            />
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-bold text-theme-primary">{opp.title}</h1>
              <Link
                to={tenantPath(`/organisations/${opp.organization.id}`)}
                className="text-indigo-500 hover:underline text-sm flex items-center gap-1 mt-1"
              >
                <Building2 className="w-3.5 h-3.5" aria-hidden="true" />
                {opp.organization.name}
                <ChevronRight className="w-3 h-3" aria-hidden="true" />
              </Link>
            </div>
          </div>

          {/* Status Chips */}
          <div className="flex flex-wrap gap-2">
            <Chip
              size="sm"
              variant="flat"
              color={opp.is_active ? 'success' : 'danger'}
            >
              {opp.is_active ? 'Active' : 'Closed'}
            </Chip>
            {opp.is_remote && (
              <Chip size="sm" variant="flat" color="secondary" startContent={<Wifi className="w-3 h-3" />}>
                Remote
              </Chip>
            )}
            {opp.category && (
              <Chip size="sm" variant="flat" color="primary" startContent={<Tag className="w-3 h-3" />}>
                {opp.category}
              </Chip>
            )}
            {opp.has_applied && (
              <Chip size="sm" variant="flat" color="success" startContent={<CheckCircle className="w-3 h-3" />}>
                Applied
              </Chip>
            )}
            {opp.is_owner && (
              <Chip size="sm" variant="flat" color="secondary" startContent={<ClipboardList className="w-3 h-3" />}>
                Your opportunity
              </Chip>
            )}
          </div>

          {/* Details Grid */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {opp.location && (
              <div className="flex items-center gap-2 text-sm text-theme-muted">
                <MapPin className="w-4 h-4 flex-shrink-0" aria-hidden="true" />
                {opp.location}
              </div>
            )}
            {opp.start_date && (
              <div className="flex items-center gap-2 text-sm text-theme-muted">
                <Calendar className="w-4 h-4 flex-shrink-0" aria-hidden="true" />
                {formatDate(opp.start_date)}
                {opp.end_date && ` — ${formatDate(opp.end_date)}`}
              </div>
            )}
            {opp.skills_needed && (
              <div className="flex items-center gap-2 text-sm text-theme-muted sm:col-span-2">
                <Briefcase className="w-4 h-4 flex-shrink-0" aria-hidden="true" />
                {opp.skills_needed}
              </div>
            )}
          </div>

          {/* Description */}
          {opp.description && (
            <div className="prose prose-sm dark:prose-invert max-w-none">
              <p className="text-theme-secondary whitespace-pre-wrap">{opp.description}</p>
            </div>
          )}

          {/* Apply button */}
          {isAuthenticated && opp.is_active && !opp.has_applied && !opp.is_owner && (
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              startContent={<Send className="w-4 h-4" aria-hidden="true" />}
              onPress={applyModal.onOpen}
            >
              Apply Now
            </Button>
          )}

          {opp.has_applied && opp.application && (
            <div className="flex items-center gap-2 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30">
              <CheckCircle className="w-5 h-5 text-emerald-400" aria-hidden="true" />
              <div>
                <p className="text-sm font-medium text-emerald-400">
                  You have applied
                </p>
                <p className="text-xs text-theme-subtle">
                  Status: {opp.application.status} &middot; Applied {formatDate(opp.application.created_at)}
                </p>
              </div>
            </div>
          )}
        </GlassCard>
      </motion.div>

      {/* Shifts */}
      {upcomingShifts.length > 0 && (
        <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }}>
          <GlassCard className="p-6 space-y-4">
            <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2">
              <Clock className="w-5 h-5 text-indigo-400" aria-hidden="true" />
              Upcoming Shifts
            </h2>
            <div className="space-y-2">
              {upcomingShifts.map((shift) => (
                <div
                  key={shift.id}
                  className="flex items-center justify-between p-3 rounded-xl bg-theme-elevated border border-theme-default"
                >
                  <div className="flex items-center gap-3">
                    <Calendar className="w-4 h-4 text-theme-subtle" aria-hidden="true" />
                    <div>
                      <p className="text-sm font-medium text-theme-primary">
                        {formatShortDate(shift.start_time)}
                      </p>
                      <p className="text-xs text-theme-subtle">
                        {formatTime(shift.start_time)} — {formatTime(shift.end_time)}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <Users className="w-4 h-4 text-theme-subtle" aria-hidden="true" />
                    <span className="text-xs text-theme-muted">
                      {shift.signup_count}{shift.capacity ? `/${shift.capacity}` : ''}
                    </span>
                    {(shift.spots_available === null || shift.spots_available > 0) ? (
                      <Chip size="sm" variant="flat" color="success">Open</Chip>
                    ) : (
                      <Chip size="sm" variant="flat" color="danger">Full</Chip>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </GlassCard>
        </motion.div>
      )}

      {/* Applications management — owner only */}
      {opp.is_owner && <ApplicationsPanel opportunityId={opp.id} />}

      {/* Apply Modal */}
      <Modal isOpen={applyModal.isOpen} onOpenChange={applyModal.onOpenChange}>
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>Apply to {opp.title}</ModalHeader>
              <ModalBody>
                <Textarea
                  label="Message (optional)"
                  placeholder="Tell the organiser why you'd like to volunteer..."
                  value={applyMessage}
                  onValueChange={setApplyMessage}
                  minRows={3}
                />
                {upcomingShifts.length > 0 && (
                  <div className="space-y-2">
                    <p className="text-sm font-medium text-theme-muted">Select a shift (optional)</p>
                    {upcomingShifts.filter((s) => s.spots_available === null || s.spots_available > 0).map((shift) => (
                      <Button
                        key={shift.id}
                        size="sm"
                        variant={selectedShiftId === shift.id ? 'solid' : 'flat'}
                        className={selectedShiftId === shift.id
                          ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white w-full justify-start'
                          : 'bg-theme-elevated text-theme-muted w-full justify-start'
                        }
                        onPress={() => setSelectedShiftId(
                          selectedShiftId === shift.id ? null : shift.id
                        )}
                      >
                        {formatShortDate(shift.start_time)} &middot; {formatTime(shift.start_time)} — {formatTime(shift.end_time)}
                      </Button>
                    ))}
                  </div>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>Cancel</Button>
                <Button
                  className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
                  onPress={handleApply}
                  isLoading={isApplying}
                >
                  Submit Application
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default OpportunityDetailPage;
