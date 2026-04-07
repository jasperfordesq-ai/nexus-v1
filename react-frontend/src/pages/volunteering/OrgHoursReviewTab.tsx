// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback, useRef } from 'react';
import { Avatar, Button, Chip, Spinner } from '@heroui/react';
import { Clock, CheckCircle, XCircle, Wallet, AlertTriangle, ChevronDown } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { useTranslation } from 'react-i18next';

interface OrgHoursReviewTabProps {
  orgId: number;
  balance: number;
  autoPay: boolean;
  onBalanceChange: () => void;
}

interface PendingHourEntry {
  id: number;
  hours: number;
  date: string;
  description: string | null;
  status: 'pending';
  created_at: string;
  user: { id: number; name: string; avatar_url: string | null };
  opportunity: { id: number; title: string } | null;
}

interface PendingHoursResponse {
  items: PendingHourEntry[];
  cursor: string | null;
  has_more: boolean;
}

function OrgHoursReviewTab({ orgId, balance, autoPay, onBalanceChange }: OrgHoursReviewTabProps) {
  const toast = useToast();
  const { t } = useTranslation('volunteering');
  const [entries, setEntries] = useState<PendingHourEntry[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMore, setHasMore] = useState(false);
  const cursorRef = useRef<string | null>(null);
  const [actionInFlight, setActionInFlight] = useState<Set<number>>(new Set());

  const abortRef = useRef<AbortController | null>(null);

  // Stable refs for t/toast — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

  const totalPendingHours = entries.reduce((sum, e) => sum + e.hours, 0);
  const isBalanceLow = autoPay && balance < totalPendingHours;

  const loadEntries = useCallback(async (append = false) => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      if (append) {
        setIsLoadingMore(true);
      } else {
        setIsLoading(true);
      }

      const params = new URLSearchParams();
      params.set('per_page', '20');
      if (append && cursorRef.current) params.set('cursor', cursorRef.current);

      const response = await api.get<PendingHoursResponse>(
        `/v2/volunteering/organisations/${orgId}/hours/pending?${params}`,
      );

      if (controller.signal.aborted) return;
      if (response.success && response.data) {
        // api.get() already unwraps { data: [...], meta: {...} } → response.data = [...], response.meta = {...}
        const items = Array.isArray(response.data) ? response.data : [];
        const nextCursor = response.meta?.cursor ?? null;
        const has_more = response.meta?.has_more ?? false;
        if (append) {
          setEntries((prev) => [...prev, ...items]);
        } else {
          setEntries(items);
        }
        cursorRef.current = nextCursor;
        setHasMore(has_more);
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load pending org hours', err);
      if (!append) {
        toastRef.current.error(tRef.current('hours_load_failed'));
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps -- orgId is stable for the tab lifetime
  }, [orgId]);

  useEffect(() => {
    loadEntries();
  // eslint-disable-next-line react-hooks/exhaustive-deps -- load once on mount
  }, []);

  useEffect(() => {
    return () => {
      abortRef.current?.abort();
    };
  }, []);

  const handleAction = async (entryId: number, action: 'approve' | 'decline') => {
    setActionInFlight((prev) => new Set(prev).add(entryId));

    // Optimistic update
    const previousEntries = [...entries];
    setEntries((prev) => prev.filter((e) => e.id !== entryId));

    try {
      const response = await api.put(`/v2/volunteering/hours/${entryId}/verify`, { action });

      if (response.success) {
        if (action === 'approve') {
          if (autoPay) {
            toastRef.current.success(
              tRef.current('org_hours_approved_paid', 'Hours approved — time credits paid to volunteer'),
            );
          } else {
            toastRef.current.success(
              tRef.current('org_hours_approved', 'Hours approved'),
            );
          }
          onBalanceChange();
        } else {
          toastRef.current.success(
            tRef.current('org_hours_declined', 'Hours declined'),
          );
        }
      } else {
        // Rollback on failure
        setEntries(previousEntries);
        toastRef.current.error(response.error ?? tRef.current('something_wrong'));
      }
    } catch (err) {
      logError('Failed to verify org hours', err);
      setEntries(previousEntries);
      toastRef.current.error(tRef.current('something_wrong'));
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
        <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-100 to-teal-100 dark:from-emerald-900/30 dark:to-teal-900/30 flex items-center justify-center">
          <Clock className="w-7 h-7 text-emerald-400" aria-hidden="true" />
        </div>
        <p className="text-theme-primary font-semibold text-lg">
          {t('no_pending_hours', 'No pending hours')}
        </p>
        <p className="text-theme-muted text-sm max-w-xs">
          {t('org_all_hours_reviewed', 'All volunteer hours have been reviewed.')}
        </p>
      </GlassCard>
    );
  }

  return (
    <div className="space-y-4">
      {/* Wallet balance info bar */}
      <GlassCard
        className={`p-4 rounded-xl border ${
          isBalanceLow
            ? 'bg-amber-500/10 border-amber-500/30'
            : 'bg-emerald-500/10 border-emerald-500/30'
        }`}
      >
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex items-center gap-2">
            <Wallet
              className={`w-5 h-5 ${isBalanceLow ? 'text-amber-500' : 'text-emerald-500'}`}
              aria-hidden="true"
            />
            <span className="text-theme-primary font-semibold">
              {t('org_wallet_balance', 'Wallet balance:')}
            </span>
            <span className={`font-bold text-lg ${isBalanceLow ? 'text-amber-500' : 'text-emerald-500'}`}>
              {balance} {balance === 1 ? t('hour', 'hour') : t('hours', 'hours')}
            </span>
          </div>

          <Chip
            size="sm"
            variant="flat"
            color={autoPay ? 'success' : 'default'}
          >
            {autoPay
              ? t('auto_pay_on', 'Auto-pay ON')
              : t('auto_pay_off', 'Auto-pay OFF')}
          </Chip>

          {isBalanceLow && (
            <div className="flex items-center gap-1.5 text-amber-600 dark:text-amber-400 text-sm">
              <AlertTriangle className="w-4 h-4 shrink-0" aria-hidden="true" />
              <span>
                {t(
                  'org_balance_low_warning',
                  'Balance may be insufficient for {{count}} pending hours',
                  { count: totalPendingHours },
                )}
              </span>
            </div>
          )}
        </div>
      </GlassCard>

      {/* Pending hours entries */}
      {entries.map((entry) => {
        const inFlight = actionInFlight.has(entry.id);

        return (
          <div
            key={entry.id}
            className="p-4 rounded-xl bg-theme-elevated border border-theme-default flex flex-col sm:flex-row sm:items-start gap-4 transition-all duration-200"
          >
            <Avatar
              src={resolveAvatarUrl(entry.user.avatar_url) || undefined}
              name={entry.user.name}
              size="md"
              className="shrink-0"
            />

            <div className="flex-1 min-w-0 space-y-1">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-semibold text-theme-primary">{entry.user.name}</span>
              </div>

              <div className="flex items-center gap-1.5">
                <Clock className="w-4 h-4 text-emerald-500 shrink-0" aria-hidden="true" />
                <span className="text-xl font-bold text-theme-primary">
                  {entry.hours} {entry.hours === 1 ? t('hour', 'hour') : t('hours', 'hours')}
                </span>
              </div>

              <div className="flex flex-wrap items-center gap-3 text-sm text-theme-muted">
                <span>{formatDate(entry.date)}</span>
                {entry.opportunity && (
                  <Chip size="sm" variant="flat" color="primary">
                    {entry.opportunity.title}
                  </Chip>
                )}
              </div>

              {entry.description && (
                <p className="text-sm text-theme-subtle line-clamp-2 mt-1">
                  {entry.description}
                </p>
              )}
            </div>

            <div className="flex gap-2 shrink-0 sm:flex-col">
              <Button
                size="sm"
                color="success"
                variant="flat"
                isDisabled={inFlight}
                isLoading={inFlight}
                startContent={!inFlight ? <CheckCircle className="w-4 h-4" aria-hidden="true" /> : undefined}
                onPress={() => handleAction(entry.id, 'approve')}
                aria-label={t('hours_review.approve_aria', 'Approve hours for {{name}}', { name: entry.user.name })}
              >
                {t('hours_review.approve', 'Approve')}
              </Button>
              <Button
                size="sm"
                color="danger"
                variant="flat"
                isDisabled={inFlight}
                isLoading={inFlight}
                startContent={!inFlight ? <XCircle className="w-4 h-4" aria-hidden="true" /> : undefined}
                onPress={() => handleAction(entry.id, 'decline')}
                aria-label={t('hours_review.decline_aria', 'Decline hours for {{name}}', { name: entry.user.name })}
              >
                {t('hours_review.decline', 'Decline')}
              </Button>
            </div>
          </div>
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
            {t('load_more', 'Load more')}
          </Button>
        </div>
      )}
    </div>
  );
}

export default OrgHoursReviewTab;
