// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Avatar, Button, Checkbox, Chip, Input, Spinner } from '@heroui/react';
import {
  ClipboardList,
  CheckCircle,
  XCircle,
  Search,
  MessageSquare,
  Clock,
  ChevronDown,
  Users,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';

/* ------------------------------------------------------------------ */
/*  Types                                                             */
/* ------------------------------------------------------------------ */

interface OrgApplicationsTabProps {
  orgId: number;
}

interface OrgApplication {
  id: number;
  status: 'pending' | 'approved' | 'declined';
  message: string | null;
  org_note: string | null;
  created_at: string;
  user: { id: number; name: string; avatar_url: string | null; email: string };
  opportunity: { id: number; title: string };
  shift: { start_time: string; end_time: string } | null;
}

interface ApplicationsResponse {
  items: OrgApplication[];
  cursor: string | null;
  has_more: boolean;
}

type StatusFilter = 'all' | 'pending' | 'approved' | 'declined';

/* ------------------------------------------------------------------ */
/*  Helpers                                                           */
/* ------------------------------------------------------------------ */

function statusColor(status: string): 'warning' | 'success' | 'danger' | 'default' {
  switch (status) {
    case 'pending': return 'warning';
    case 'approved': return 'success';
    case 'declined': return 'danger';
    default: return 'default';
  }
}

function formatDate(d: string) { return new Date(d).toLocaleDateString(); }

/* ------------------------------------------------------------------ */
/*  Component                                                         */
/* ------------------------------------------------------------------ */

function OrgApplicationsTab({ orgId }: OrgApplicationsTabProps) {
  const toast = useToast();
  const { t } = useTranslation('volunteering');

  const [applications, setApplications] = useState<OrgApplication[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
  const [actionLoading, setActionLoading] = useState<Record<number, boolean>>({});
  const [nameSearch, setNameSearch] = useState('');
  const [selected, setSelected] = useState<Set<number>>(new Set());

  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;
  const abortRef = useRef<AbortController | null>(null);

  /* ---- Data fetching ---- */

  const loadApplications = useCallback(
    async (filter: StatusFilter, nextCursor: string | null = null) => {
      abortRef.current?.abort();
      const controller = new AbortController();
      abortRef.current = controller;

      try {
        if (nextCursor) setIsLoadingMore(true);
        else setIsLoading(true);

        const params = new URLSearchParams({ per_page: '20' });
        if (filter !== 'all') params.set('status', filter);
        if (nextCursor) params.set('cursor', nextCursor);

        const response = await api.get<ApplicationsResponse>(
          `/v2/volunteering/organisations/${orgId}/applications?${params}`,
        );

        if (controller.signal.aborted) return;

        if (response.success && response.data) {
          const { items, cursor: newCursor, has_more } = response.data;
          setApplications((prev) => (nextCursor ? [...prev, ...items] : items));
          setCursor(newCursor);
          setHasMore(has_more);
        } else {
          toastRef.current.error(
            response.error || tRef.current('applications.load_failed', 'Failed to load applications.'),
          );
        }
      } catch (err) {
        if (controller.signal.aborted) return;
        logError('Failed to load org applications', err);
        toastRef.current.error(tRef.current('applications.load_failed', 'Failed to load applications.'));
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false);
          setIsLoadingMore(false);
        }
      }
    },
    [orgId],
  );

  const loadRef = useRef(loadApplications);
  loadRef.current = loadApplications;

  useEffect(() => {
    setApplications([]);
    setCursor(null);
    setSelected(new Set());
    loadRef.current(statusFilter);
    return () => { abortRef.current?.abort(); };
  }, [statusFilter]);

  /* ---- Single action ---- */

  async function handleAction(applicationId: number, action: 'approve' | 'decline') {
    setActionLoading((prev) => ({ ...prev, [applicationId]: true }));

    // Optimistic update
    const newStatus = action === 'approve' ? 'approved' : 'declined';
    setApplications((prev) =>
      prev.map((a) => (a.id === applicationId ? { ...a, status: newStatus as OrgApplication['status'] } : a)),
    );

    try {
      const response = await api.put(`/v2/volunteering/applications/${applicationId}`, { action });
      if (response.success) {
        toast.success(
          action === 'approve'
            ? t('applications.approved', 'Application approved.')
            : t('applications.declined', 'Application declined.'),
        );
      } else {
        // Revert optimistic update
        setApplications((prev) =>
          prev.map((a) => (a.id === applicationId ? { ...a, status: 'pending' } : a)),
        );
        toast.error(response.error || t('applications.action_failed', `Failed to ${action} application.`));
      }
    } catch (err) {
      // Revert optimistic update
      setApplications((prev) =>
        prev.map((a) => (a.id === applicationId ? { ...a, status: 'pending' } : a)),
      );
      logError(`Failed to ${action} application`, err);
      toast.error(t('something_wrong', 'Something went wrong.'));
    } finally {
      setActionLoading((prev) => ({ ...prev, [applicationId]: false }));
    }
  }

  /* ---- Bulk action ---- */

  async function handleBulkAction(action: 'approve' | 'decline') {
    const ids = Array.from(selected);
    for (const id of ids) {
      await handleAction(id, action);
    }
    setSelected(new Set());
    loadApplications(statusFilter);
  }

  /* ---- Derived state ---- */

  const filters: { key: StatusFilter; label: string }[] = [
    { key: 'all', label: t('applications.filter_all', 'All') },
    { key: 'pending', label: t('applications.filter_pending', 'Pending') },
    { key: 'approved', label: t('applications.filter_approved', 'Approved') },
    { key: 'declined', label: t('applications.filter_declined', 'Declined') },
  ];

  const pendingCount = applications.filter((a) => a.status === 'pending').length;

  const filteredApplications = nameSearch.trim()
    ? applications.filter((a) => a.user.name.toLowerCase().includes(nameSearch.toLowerCase()))
    : applications;

  const pendingFiltered = filteredApplications.filter((a) => a.status === 'pending');
  const allPendingSelected =
    pendingFiltered.length > 0 && pendingFiltered.every((a) => selected.has(a.id));
  const somePendingSelected =
    pendingFiltered.some((a) => selected.has(a.id)) && !allPendingSelected;

  /* ---- Render ---- */

  return (
    <GlassCard className="p-6 space-y-4">
      {/* Header */}
      <div className="flex items-center gap-3 flex-wrap">
        <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2">
          <ClipboardList className="w-5 h-5 text-indigo-400" aria-hidden="true" />
          {t('applications.heading', 'Applications')}
          {pendingCount > 0 && (
            <Chip size="sm" color="warning" variant="flat">
              {t('applications.pending_count', '{{count}} pending', { count: pendingCount })}
            </Chip>
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
            aria-label={t('applications.aria_select_all', 'Select all visible pending applications')}
          >
            <span className="text-xs text-theme-muted">{t('applications.select_all', 'Select all')}</span>
          </Checkbox>
        )}
      </div>

      {/* Status filter buttons */}
      <div className="flex flex-wrap gap-2">
        {filters.map((f) => (
          <Button
            key={f.key}
            size="sm"
            variant={statusFilter === f.key ? 'solid' : 'flat'}
            className={
              statusFilter === f.key
                ? 'bg-gradient-to-r from-indigo-500 to-violet-600 text-white'
                : 'bg-theme-elevated text-theme-muted'
            }
            onPress={() => setStatusFilter(f.key)}
          >
            {f.label}
          </Button>
        ))}
      </div>

      {/* Search by name */}
      <Input
        size="sm"
        placeholder={t('applications.search_placeholder', 'Search by name...')}
        value={nameSearch}
        onValueChange={setNameSearch}
        startContent={<Search className="w-3.5 h-3.5 text-theme-subtle" />}
        aria-label={t('applications.aria_search_volunteers', 'Search volunteers by name')}
        classNames={{ base: 'max-w-xs', inputWrapper: 'bg-theme-elevated' }}
      />

      {/* Bulk action bar */}
      {selected.size > 0 && (
        <div className="flex items-center gap-3 p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/30">
          <span className="text-sm text-indigo-400 font-medium">
            {t('applications.selected_count', '{{count}} selected', { count: selected.size })}
          </span>
          <Button
            size="sm"
            color="success"
            variant="flat"
            startContent={<CheckCircle className="w-3.5 h-3.5" />}
            onPress={() => handleBulkAction('approve')}
          >
            {t('applications.approve_all', 'Approve All')}
          </Button>
          <Button
            size="sm"
            color="danger"
            variant="flat"
            startContent={<XCircle className="w-3.5 h-3.5" />}
            onPress={() => handleBulkAction('decline')}
          >
            {t('applications.decline_all', 'Decline All')}
          </Button>
        </div>
      )}

      {/* Loading state */}
      {isLoading && (
        <div className="flex justify-center py-8">
          <Spinner size="lg" />
        </div>
      )}

      {/* Empty state */}
      {!isLoading && filteredApplications.length === 0 && (
        <div className="flex flex-col items-center gap-2 py-8 text-theme-muted">
          <Users className="w-10 h-10 opacity-40" />
          <p className="text-sm">{t('applications.empty', 'No applications found.')}</p>
        </div>
      )}

      {/* Application list */}
      {!isLoading && filteredApplications.length > 0 && (
        <div className="space-y-3">
          {filteredApplications.map((app) => (
            <div
              key={app.id}
              className="flex flex-col sm:flex-row sm:items-start gap-3 p-4 rounded-xl bg-theme-elevated border border-theme-default"
            >
              {/* Checkbox for pending */}
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
                  aria-label={t('applications.aria_select_application', 'Select application from {{name}}', {
                    name: app.user.name,
                  })}
                  className="mt-1"
                />
              )}

              {/* Avatar */}
              <Avatar
                src={resolveAvatarUrl(app.user.avatar_url)}
                name={app.user.name}
                size="md"
                className="shrink-0"
              />

              {/* Info */}
              <div className="flex-1 min-w-0 space-y-1">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="font-medium text-theme-primary">{app.user.name}</span>
                  <span className="text-xs text-theme-muted">{app.user.email}</span>
                  <Chip size="sm" color={statusColor(app.status)} variant="flat" className="capitalize">
                    {app.status}
                  </Chip>
                </div>

                {/* Opportunity title */}
                <p className="text-sm text-theme-secondary">
                  {t('applications.for_opportunity', 'For: {{title}}', { title: app.opportunity.title })}
                </p>

                {/* Application message */}
                {app.message && (
                  <div className="flex items-start gap-1.5 text-sm text-theme-muted">
                    <MessageSquare className="w-3.5 h-3.5 mt-0.5 shrink-0" aria-hidden="true" />
                    <span className="line-clamp-2">{app.message}</span>
                  </div>
                )}

                {/* Shift info */}
                {app.shift && (
                  <div className="flex items-center gap-1.5 text-xs text-theme-muted">
                    <Clock className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                    <span>
                      {formatDate(app.shift.start_time)} &ndash; {formatDate(app.shift.end_time)}
                    </span>
                  </div>
                )}

                {/* Applied date */}
                <p className="text-xs text-theme-subtle">
                  {t('applications.applied_on', 'Applied {{date}}', { date: formatDate(app.created_at) })}
                </p>
              </div>

              {/* Actions */}
              {app.status === 'pending' && (
                <div className="flex gap-2 sm:flex-col sm:items-end shrink-0">
                  <Button
                    size="sm"
                    color="success"
                    variant="flat"
                    isLoading={!!actionLoading[app.id]}
                    startContent={!actionLoading[app.id] ? <CheckCircle className="w-3.5 h-3.5" /> : undefined}
                    onPress={() => handleAction(app.id, 'approve')}
                  >
                    {t('applications.approve', 'Approve')}
                  </Button>
                  <Button
                    size="sm"
                    color="danger"
                    variant="flat"
                    isLoading={!!actionLoading[app.id]}
                    startContent={!actionLoading[app.id] ? <XCircle className="w-3.5 h-3.5" /> : undefined}
                    onPress={() => handleAction(app.id, 'decline')}
                  >
                    {t('applications.decline', 'Decline')}
                  </Button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Load more */}
      {hasMore && !isLoading && (
        <div className="flex justify-center pt-2">
          <Button
            size="sm"
            variant="flat"
            className="bg-theme-elevated text-theme-muted"
            isLoading={isLoadingMore}
            startContent={!isLoadingMore ? <ChevronDown className="w-4 h-4" /> : undefined}
            onPress={() => loadApplications(statusFilter, cursor)}
          >
            {t('applications.load_more', 'Load More')}
          </Button>
        </div>
      )}
    </GlassCard>
  );
}

export default OrgApplicationsTab;
