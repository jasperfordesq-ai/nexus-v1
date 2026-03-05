// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ShiftSwapsTab - View and manage shift swap requests (V2)
 */

import { useState, useEffect, useCallback } from 'react';
import { motion } from 'framer-motion';
import {
  Button,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from '@heroui/react';
import {
  ArrowLeftRight,
  Check,
  X,
  Calendar,
  Building2,
  AlertTriangle,
  RefreshCw,
  Send,
  Inbox,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

/* ───────────────────────── Types ───────────────────────── */

interface SwapShift {
  id: number;
  start_time: string;
  end_time: string;
  opportunity_title: string;
  organization_name: string;
}

interface ShiftSwap {
  id: number;
  status: 'pending' | 'accepted' | 'rejected';
  direction: 'sent' | 'received';
  requester: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
  recipient: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
  original_shift: SwapShift;
  proposed_shift: SwapShift;
  message: string | null;
  created_at: string;
}

/* ───────────────────────── Component ───────────────────────── */

export function ShiftSwapsTab() {
  const { t } = useTranslation('community');
  const toast = useToast();
  const [swaps, setSwaps] = useState<ShiftSwap[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actioningId, setActioningId] = useState<number | null>(null);
  const [rejectTarget, setRejectTarget] = useState<number | null>(null);
  const [view, setView] = useState<'all' | 'sent' | 'received'>('all');

  const load = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);

      const response = await api.get<{ swaps?: ShiftSwap[] }>(
        '/v2/volunteering/swaps'
      );

      if (response.success && response.data) {
        // Backend returns {swaps: [...]} wrapper
        const raw = response.data as Record<string, unknown>;
        const items = Array.isArray(raw.swaps) ? raw.swaps as ShiftSwap[]
          : Array.isArray(response.data) ? response.data as ShiftSwap[]
          : [];
        setSwaps(items);
      } else {
        setError(t('swaps.load_error', 'Unable to load shift swap requests. Please try again.'));
      }
    } catch (err) {
      logError('Failed to load shift swaps', err);
      setError(t('swaps.load_error', 'Unable to load shift swap requests. Please try again.'));
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const handleAccept = async (swapId: number) => {
    try {
      setActioningId(swapId);
      const response = await api.put(`/v2/volunteering/swaps/${swapId}`, { action: 'accept' });
      if (response.success) {
        setSwaps((prev) =>
          prev.map((s) => (s.id === swapId ? { ...s, status: 'accepted' as const } : s))
        );
      } else {
        toast.error(t('swaps.accept_failed', 'Failed to accept swap request.'));
      }
    } catch (err) {
      logError('Failed to accept shift swap', err);
      toast.error(t('swaps.accept_failed', 'Failed to accept swap request.'));
    } finally {
      setActioningId(null);
    }
  };

  const handleReject = async () => {
    if (!rejectTarget) return;

    try {
      setActioningId(rejectTarget);
      const response = await api.put(`/v2/volunteering/swaps/${rejectTarget}`, { action: 'reject' });
      if (response.success) {
        setSwaps((prev) =>
          prev.map((s) => (s.id === rejectTarget ? { ...s, status: 'rejected' as const } : s))
        );
      } else {
        toast.error(t('swaps.reject_failed', 'Failed to reject swap request.'));
      }
    } catch (err) {
      logError('Failed to reject shift swap', err);
      toast.error(t('swaps.reject_failed', 'Failed to reject swap request.'));
    } finally {
      setActioningId(null);
      setRejectTarget(null);
    }
  };

  const statusColor = (status: string) => {
    switch (status) {
      case 'accepted': return 'success';
      case 'rejected': return 'danger';
      default: return 'warning';
    }
  };

  const filteredSwaps = swaps.filter((s) => {
    if (view === 'sent') return s.direction === 'sent';
    if (view === 'received') return s.direction === 'received';
    return true;
  });

  const sentCount = swaps.filter((s) => s.direction === 'sent').length;
  const receivedCount = swaps.filter((s) => s.direction === 'received').length;

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.05 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  const formatShiftTime = (shift: SwapShift) => {
    const start = new Date(shift.start_time);
    const end = new Date(shift.end_time);
    return `${start.toLocaleDateString()} ${start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} - ${end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <ArrowLeftRight className="w-5 h-5 text-indigo-400" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary">{t('swaps.heading', 'Shift Swaps')}</h2>
        </div>
        <Button
          size="sm"
          variant="flat"
          className="bg-theme-elevated text-theme-muted"
          startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
          onPress={load}
          isDisabled={isLoading}
        >
          {t('swaps.refresh', 'Refresh')}
        </Button>
      </div>

      {/* View Filter */}
      <div className="flex gap-2 flex-wrap">
        <Button
          size="sm"
          variant={view === 'all' ? 'solid' : 'flat'}
          className={view === 'all' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
          onPress={() => setView('all')}
        >
          {t('swaps.all', 'All')} ({swaps.length})
        </Button>
        <Button
          size="sm"
          variant={view === 'sent' ? 'solid' : 'flat'}
          className={view === 'sent' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
          onPress={() => setView('sent')}
          startContent={<Send className="w-3 h-3" aria-hidden="true" />}
        >
          {t('swaps.sent', 'Sent')} ({sentCount})
        </Button>
        <Button
          size="sm"
          variant={view === 'received' ? 'solid' : 'flat'}
          className={view === 'received' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
          onPress={() => setView('received')}
          startContent={<Inbox className="w-3 h-3" aria-hidden="true" />}
        >
          {t('swaps.received', 'Received')} ({receivedCount})
        </Button>
      </div>

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{error}</p>
          <Button className="bg-gradient-to-r from-rose-500 to-pink-600 text-white" onPress={load}>
            {t('swaps.try_again', 'Try Again')}
          </Button>
        </GlassCard>
      )}

      {/* Loading */}
      {!error && isLoading && (
        <div className="space-y-4">
          {[1, 2, 3].map((i) => (
            <GlassCard key={i} className="p-5 animate-pulse">
              <div className="h-5 bg-theme-hover rounded w-1/3 mb-3" />
              <div className="h-3 bg-theme-hover rounded w-2/3 mb-3" />
              <div className="h-3 bg-theme-hover rounded w-1/4" />
            </GlassCard>
          ))}
        </div>
      )}

      {/* Empty */}
      {!error && !isLoading && filteredSwaps.length === 0 && (
        <EmptyState
          icon={<ArrowLeftRight className="w-12 h-12" aria-hidden="true" />}
          title={t('swaps.no_swaps_title', 'No swap requests')}
          description={
            view === 'sent'
              ? t('swaps.no_sent', 'You have not sent any shift swap requests yet.')
              : view === 'received'
                ? t('swaps.no_received', 'You have not received any shift swap requests.')
                : t('swaps.no_swaps_desc', 'No shift swap requests found. You can request a swap from the shift details page.')
          }
        />
      )}

      {/* Swap List */}
      {!error && !isLoading && filteredSwaps.length > 0 && (
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="space-y-4"
        >
          {filteredSwaps.map((swap) => (
            <motion.div key={swap.id} variants={itemVariants}>
              <GlassCard className="p-5">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    {/* Header with status */}
                    <div className="flex items-center gap-2 mb-3 flex-wrap">
                      <Chip
                        size="sm"
                        variant="flat"
                        color={swap.direction === 'sent' ? 'primary' : 'secondary'}
                        startContent={swap.direction === 'sent'
                          ? <Send className="w-3 h-3" />
                          : <Inbox className="w-3 h-3" />
                        }
                      >
                        {swap.direction === 'sent' ? t('swaps.sent', 'Sent') : t('swaps.received', 'Received')}
                      </Chip>
                      <Chip
                        size="sm"
                        variant="flat"
                        color={statusColor(swap.status)}
                      >
                        {swap.status.charAt(0).toUpperCase() + swap.status.slice(1)}
                      </Chip>
                      {swap.direction === 'sent' && (
                        <span className="text-xs text-theme-subtle">
                          To: {swap.recipient.name}
                        </span>
                      )}
                      {swap.direction === 'received' && (
                        <span className="text-xs text-theme-subtle">
                          From: {swap.requester.name}
                        </span>
                      )}
                    </div>

                    {/* Shift details: original -> proposed */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                      {/* Original shift */}
                      <div className="rounded-lg bg-theme-hover/50 p-3">
                        <p className="text-xs font-medium text-theme-muted mb-1">{t('swaps.your_shift', 'Your Shift')}</p>
                        <p className="text-sm font-semibold text-theme-primary">
                          {swap.original_shift.opportunity_title}
                        </p>
                        <div className="flex flex-wrap items-center gap-2 mt-1 text-xs text-theme-subtle">
                          <span className="flex items-center gap-1">
                            <Building2 className="w-3 h-3" aria-hidden="true" />
                            {swap.original_shift.organization_name}
                          </span>
                          <span className="flex items-center gap-1">
                            <Calendar className="w-3 h-3" aria-hidden="true" />
                            {formatShiftTime(swap.original_shift)}
                          </span>
                        </div>
                      </div>

                      {/* Proposed shift */}
                      <div className="rounded-lg bg-theme-hover/50 p-3">
                        <p className="text-xs font-medium text-theme-muted mb-1">{t('swaps.proposed_shift', 'Proposed Shift')}</p>
                        <p className="text-sm font-semibold text-theme-primary">
                          {swap.proposed_shift.opportunity_title}
                        </p>
                        <div className="flex flex-wrap items-center gap-2 mt-1 text-xs text-theme-subtle">
                          <span className="flex items-center gap-1">
                            <Building2 className="w-3 h-3" aria-hidden="true" />
                            {swap.proposed_shift.organization_name}
                          </span>
                          <span className="flex items-center gap-1">
                            <Calendar className="w-3 h-3" aria-hidden="true" />
                            {formatShiftTime(swap.proposed_shift)}
                          </span>
                        </div>
                      </div>
                    </div>

                    {swap.message && (
                      <p className="text-sm text-theme-muted italic mb-2">
                        &quot;{swap.message}&quot;
                      </p>
                    )}

                    <p className="text-xs text-theme-subtle">
                      {t('swaps.requested', 'Requested')} {new Date(swap.created_at).toLocaleDateString()}
                    </p>
                  </div>

                  {/* Action buttons for received pending swaps */}
                  {swap.direction === 'received' && swap.status === 'pending' && (
                    <div className="flex flex-col gap-2 flex-shrink-0">
                      <Button
                        size="sm"
                        className="bg-gradient-to-r from-emerald-500 to-green-600 text-white"
                        startContent={<Check className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => handleAccept(swap.id)}
                        isLoading={actioningId === swap.id}
                      >
                        {t('swaps.accept', 'Accept')}
                      </Button>
                      <Button
                        size="sm"
                        variant="flat"
                        color="danger"
                        startContent={<X className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => setRejectTarget(swap.id)}
                        isLoading={actioningId === swap.id}
                      >
                        {t('swaps.reject', 'Reject')}
                      </Button>
                    </div>
                  )}
                </div>
              </GlassCard>
            </motion.div>
          ))}
        </motion.div>
      )}
      {/* Reject Confirmation Modal */}
      <Modal
        isOpen={rejectTarget !== null}
        onOpenChange={(open) => !open && setRejectTarget(null)}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="text-theme-primary">{t('swaps.reject', 'Reject')}</ModalHeader>
              <ModalBody>
                <p className="text-theme-secondary">
                  {t('swaps.reject_confirm', 'Are you sure you want to reject this swap request?')}
                </p>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>{t('volunteering.cancel', 'Cancel')}</Button>
                <Button color="danger" onPress={handleReject}>{t('swaps.reject', 'Reject')}</Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default ShiftSwapsTab;
