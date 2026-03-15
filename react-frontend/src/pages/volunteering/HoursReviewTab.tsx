// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import { Avatar, Button, Chip, Spinner } from '@heroui/react';
import { Clock, CheckCircle, XCircle, Building2, ChevronDown } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';

import { useTranslation } from 'react-i18next';
interface HourLogUser {
  id: number;
  name: string;
  avatar_url: string | null;
}

interface HourLogOrganization {
  id: number;
  name: string;
  logo_url: string | null;
}

interface HourLogOpportunity {
  id: number;
  title: string;
}

interface HourLogEntry {
  id: number;
  hours: number;
  date: string;
  description: string;
  status: 'pending' | 'approved' | 'declined';
  created_at: string;
  user: HourLogUser;
  organization: HourLogOrganization;
  opportunity: HourLogOpportunity | null;
}

interface PendingHoursResponse {
  items: HourLogEntry[];
  cursor: string | null;
  has_more: boolean;
}

export function HoursReviewTab() {
  const toast = useToast();
  const { t } = useTranslation('volunteering');
  const [entries, setEntries] = useState<HourLogEntry[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMore, setHasMore] = useState(false);
  const [cursor, setCursor] = useState<string | null>(null);
  const [actionInFlight, setActionInFlight] = useState<Set<number>>(new Set());

  const loadEntries = useCallback(async (append = false) => {
    try {
      if (append) {
        setIsLoadingMore(true);
      } else {
        setIsLoading(true);
      }

      const params = new URLSearchParams();
      params.set('per_page', '20');
      if (append && cursor) params.set('cursor', cursor);

      const response = await api.get<PendingHoursResponse>(
        `/v2/volunteering/hours/pending-review?${params}`,
      );

      if (response.success && response.data) {
        const { items, cursor: nextCursor, has_more } = response.data;
        if (append) {
          setEntries((prev) => [...prev, ...items]);
        } else {
          setEntries(items);
        }
        setCursor(nextCursor);
        setHasMore(has_more);
      }
    } catch (err) {
      logError('Failed to load pending hours', err);
      if (!append) {
        toast.error(t('hours_load_failed'));
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cursor]);

  useEffect(() => {
    loadEntries();
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleAction = async (entryId: number, action: 'approve' | 'decline') => {
    setActionInFlight((prev) => new Set(prev).add(entryId));

    setEntries((prev) =>
      prev.map((e) =>
        e.id === entryId
          ? { ...e, status: action === 'approve' ? 'approved' : 'declined' }
          : e,
      ),
    );

    try {
      const response = await api.put(`/v2/volunteering/hours/${entryId}/verify`, { action });

      if (response.success) {
        toast.success(action === 'approve' ? 'Hours approved.' : 'Hours declined.');
        setTimeout(() => {
          setEntries((prev) => prev.filter((e) => e.id !== entryId));
        }, 800);
      } else {
        setEntries((prev) =>
          prev.map((e) => (e.id === entryId ? { ...e, status: 'pending' } : e)),
        );
        toast.error(response.error ?? 'Action failed. Please try again.');
      }
    } catch (err) {
      logError('Failed to verify hours', err);
      setEntries((prev) =>
        prev.map((e) => (e.id === entryId ? { ...e, status: 'pending' } : e)),
      );
      toast.error(t('something_wrong'));
    } finally {
      setActionInFlight((prev) => {
        const next = new Set(prev);
        next.delete(entryId);
        return next;
      });
    }
  };

  const formatDate = (dateStr: string) => {
    try {
      return new Date(dateStr).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
    } catch {
      return dateStr;
    }
  };

  const statusColor = (status: HourLogEntry['status']): 'success' | 'danger' | 'warning' => {
    switch (status) {
      case 'approved': return 'success';
      case 'declined': return 'danger';
      default: return 'warning';
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-16">
        <Spinner size="lg" color="primary" />
      </div>
    );
  }

  if (entries.length === 0) {
    return (
      <GlassCard className="flex flex-col items-center justify-center py-16 text-center gap-3">
        <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-rose-100 to-pink-100 dark:from-rose-900/30 dark:to-pink-900/30 flex items-center justify-center">
          <Clock className="w-7 h-7 text-rose-400" aria-hidden="true" />
        </div>
        <p className="text-[var(--color-text)] font-semibold text-lg">No hours pending review.</p>
        <p className="text-[var(--color-text-muted)] text-sm max-w-xs">
          All volunteer hour submissions have been reviewed.
        </p>
      </GlassCard>
    );
  }

  return (
    <div className="space-y-4">
      {entries.map((entry) => {
        const inFlight = actionInFlight.has(entry.id);
        const isActioned = entry.status !== 'pending';

        return (
          <GlassCard key={entry.id} className="flex flex-col sm:flex-row sm:items-start gap-4 p-4">
            <Avatar
              src={resolveAvatarUrl(entry.user.avatar_url) || undefined}
              name={entry.user.name}
              size="md"
              className="shrink-0"
            />

            <div className="flex-1 min-w-0 space-y-1">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-semibold text-[var(--color-text)]">{entry.user.name}</span>
                <Chip size="sm" color={statusColor(entry.status)} variant="flat">
                  {entry.status === 'pending'
                    ? 'Pending'
                    : entry.status === 'approved'
                    ? 'Approved'
                    : 'Declined'}
                </Chip>
              </div>

              <div className="flex flex-wrap items-center gap-3 text-sm text-[var(--color-text-muted)]">
                <span className="flex items-center gap-1">
                  <Building2 className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                  {entry.organization.name}
                </span>
                {entry.opportunity && (
                  <span>· {entry.opportunity.title}</span>
                )}
              </div>

              <div className="flex flex-wrap items-center gap-3 text-sm">
                <span className="flex items-center gap-1 text-rose-500 font-medium">
                  <Clock className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                  {entry.hours} {entry.hours === 1 ? 'hour' : 'hours'}
                </span>
                <span className="text-[var(--color-text-muted)]">{formatDate(entry.date)}</span>
              </div>

              {entry.description && (
                <p className="text-sm text-[var(--color-text-muted)] line-clamp-2 mt-1">
                  {entry.description}
                </p>
              )}
            </div>

            <div className="flex gap-2 shrink-0 sm:flex-col">
              <Button
                size="sm"
                color="success"
                variant="flat"
                isDisabled={inFlight || isActioned}
                isLoading={inFlight && entry.status === 'approved'}
                startContent={!inFlight ? <CheckCircle className="w-4 h-4" aria-hidden="true" /> : undefined}
                onPress={() => handleAction(entry.id, 'approve')}
                aria-label={'Approve hours for ' + entry.user.name}
              >
                Approve
              </Button>
              <Button
                size="sm"
                color="danger"
                variant="flat"
                isDisabled={inFlight || isActioned}
                isLoading={inFlight && entry.status === 'declined'}
                startContent={!inFlight ? <XCircle className="w-4 h-4" aria-hidden="true" /> : undefined}
                onPress={() => handleAction(entry.id, 'decline')}
                aria-label={'Decline hours for ' + entry.user.name}
              >
                Decline
              </Button>
            </div>
          </GlassCard>
        );
      })}

      {hasMore && (
        <div className="flex justify-center pt-2">
          <Button
            variant="flat"
            className="bg-theme-elevated text-theme-muted"
            isLoading={isLoadingMore}
            startContent={!isLoadingMore ? <ChevronDown className="w-4 h-4" aria-hidden="true" /> : undefined}
            onPress={() => loadEntries(true)}
          >
            Load more
          </Button>
        </div>
      )}
    </div>
  );
}
