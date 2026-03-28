// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * DonationsTab - Active giving days with progress and donation history
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { motion } from 'framer-motion';
import {
  Button,
  Chip,
  Input,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
  Textarea,
  Progress,
} from '@heroui/react';
import {
  Heart,
  Calendar,
  Users,
  CreditCard,
  AlertTriangle,
  RefreshCw,
  Plus,
  Banknote,
  EyeOff,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { useToast } from '@/contexts';
import { DonationCheckout } from '@/components/donations/DonationCheckout';

/* ───────────────────────── Types ───────────────────────── */

interface GivingDay {
  id: number;
  title: string;
  description: string;
  goal_amount: number;
  raised_amount: number;
  donor_count?: number;
  start_date: string;
  end_date: string;
  starts_at?: string;
  ends_at?: string;
  is_active?: boolean;
  status?: 'active' | 'upcoming' | 'ended';
}

interface GivingDayStats {
  total_raised: number;
  total_donors: number;
  active_campaigns: number;
}

interface Donation {
  id: number;
  amount: number;
  currency?: string;
  payment_method: string;
  message: string | null;
  anonymous?: boolean;
  is_anonymous?: boolean;
  status: 'pending' | 'completed' | 'failed' | 'refunded';
  giving_day_id?: number | null;
  giving_day_title?: string;
  created_at: string;
}

interface DonationForm {
  giving_day_id: number | null;
  amount: string;
  payment_method: string;
  message: string;
  anonymous: boolean;
}

/* ───────────────────────── Constants ───────────────────────── */

const PAYMENT_METHODS = ['card', 'bank_transfer', 'paypal'];

const STATUS_COLOR: Record<string, 'success' | 'warning' | 'danger' | 'default'> = {
  completed: 'success',
  pending: 'warning',
  failed: 'danger',
  refunded: 'default',
};

/* ───────────────────────── Component ───────────────────────── */

export function DonationsTab() {
  const { t } = useTranslation('volunteering');
  const [givingDays, setGivingDays] = useState<GivingDay[]>([]);
  const [donations, setDonations] = useState<Donation[]>([]);
  const [stats, setStats] = useState<GivingDayStats | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const { isOpen, onOpen, onOpenChange } = useDisclosure();
  const {
    isOpen: isCheckoutOpen,
    onOpen: onCheckoutOpen,
    onClose: onCheckoutClose,
  } = useDisclosure();
  const [checkoutGivingDayId, setCheckoutGivingDayId] = useState<number | undefined>();
  const toast = useToast();

  // AbortController ref to cancel stale requests
  const abortRef = useRef<AbortController | null>(null);

  // Stable refs for t/toast — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

  const [form, setForm] = useState<DonationForm>({
    giving_day_id: null,
    amount: '',
    payment_method: 'card',
    message: '',
    anonymous: false,
  });

  const load = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      setIsLoading(true);
      setError(null);

      const [daysRes, donationsRes] = await Promise.all([
        api.get<GivingDay[]>('/v2/volunteering/giving-days'),
        api.get<Donation[]>('/v2/volunteering/donations'),
      ]);

      if (controller.signal.aborted) return;

      let days: GivingDay[] = [];
      if (daysRes.success && daysRes.data) {
        days = Array.isArray(daysRes.data) ? daysRes.data : [];
        setGivingDays(days);
      }
      if (donationsRes.success && donationsRes.data) {
        const dPayload = donationsRes.data as unknown as Record<string, unknown>;
        const items = Array.isArray(dPayload.items)
          ? (dPayload.items as Donation[])
          : Array.isArray(donationsRes.data)
            ? (donationsRes.data as unknown as Donation[])
            : [];
        setDonations(items);
      }

      // Compute aggregate stats from giving days data
      const totalRaised = days.reduce((sum, d) => sum + (Number(d.raised_amount) || 0), 0);
      const totalDonors = days.reduce((sum, d) => sum + (Number(d.donor_count) || 0), 0);
      const activeCampaigns = days.filter((d) => d.status === 'active' || d.is_active).length;
      setStats({ total_raised: totalRaised, total_donors: totalDonors, active_campaigns: activeCampaigns });
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load donations data', err);
      setError(tRef.current('donations.load_error', 'Unable to load donations data.'));
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const openStripeCheckout = (dayId?: number) => {
    setCheckoutGivingDayId(dayId);
    onCheckoutOpen();
  };

  const openDonateModal = (dayId?: number) => {
    setForm({
      giving_day_id: dayId ?? null,
      amount: '',
      payment_method: 'card',
      message: '',
      anonymous: false,
    });
    onOpen();
  };

  const handleSubmit = async (onClose: () => void) => {
    if (!form.amount || parseFloat(form.amount) <= 0) {
      toastRef.current.error(tRef.current('donations.invalid_amount', 'Please enter a valid amount.'));
      return;
    }

    try {
      setIsSubmitting(true);

      const response = await api.post('/v2/volunteering/donations', {
        giving_day_id: form.giving_day_id,
        amount: parseFloat(form.amount),
        payment_method: form.payment_method,
        message: form.message || null,
        is_anonymous: form.anonymous,
      });

      if (response.success) {
        toastRef.current.success(tRef.current('donations.submit_success', 'Donation recorded!'));
        onClose();
        load();
      } else {
        toastRef.current.error(response.error || tRef.current('donations.submit_error', 'Failed to record donation.'));
      }
    } catch (err) {
      logError('Failed to submit donation', err);
      toastRef.current.error(tRef.current('donations.submit_error_retry', 'Failed to record donation. Please try again.'));
    } finally {
      setIsSubmitting(false);
    }
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.05 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Heart className="w-5 h-5 text-rose-400" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary">{t('donations.heading', 'Donations')}</h2>
        </div>
        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant="flat"
            className="bg-theme-elevated text-theme-muted"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={load}
            isDisabled={isLoading}
          >
            {t('donations.refresh', 'Refresh')}
          </Button>
          <Button
            size="sm"
            className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
            startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
            onPress={() => openDonateModal()}
          >
            {t('donations.donate', 'Donate')}
          </Button>
          <Button
            size="sm"
            variant="bordered"
            className="border-rose-500 text-rose-500"
            startContent={<CreditCard className="w-4 h-4" aria-hidden="true" />}
            onPress={() => openStripeCheckout()}
          >
            {t('donations.donate_with_card', 'Donate with Card')}
          </Button>
        </div>
      </div>

      {/* Stats */}
      {!error && !isLoading && stats && (
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <GlassCard className="p-3 text-center">
            <p className="text-xs text-theme-muted">{t('donations.stats.total_raised', 'Total Raised')}</p>
            <p className="text-lg font-bold text-theme-primary">
              {stats.total_raised.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </p>
          </GlassCard>
          <GlassCard className="p-3 text-center">
            <p className="text-xs text-theme-muted">{t('donations.stats.total_donors', 'Total Donors')}</p>
            <p className="text-lg font-bold text-rose-500">{stats.total_donors}</p>
          </GlassCard>
          <GlassCard className="p-3 text-center">
            <p className="text-xs text-theme-muted">{t('donations.stats.active_campaigns', 'Active Campaigns')}</p>
            <p className="text-lg font-bold text-blue-500">{stats.active_campaigns}</p>
          </GlassCard>
        </div>
      )}

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{error}</p>
          <Button className="bg-gradient-to-r from-rose-500 to-pink-600 text-white" onPress={load}>
            {t('donations.try_again', 'Try Again')}
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
      {!error && !isLoading && givingDays.length === 0 && donations.length === 0 && (
        <EmptyState
          icon={<Heart className="w-12 h-12" aria-hidden="true" />}
          title={t('donations.empty_title', 'No giving days or donations')}
          description={t('donations.empty_description', 'When a giving day is active, you can make donations to support your community.')}
          action={
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              onPress={() => openDonateModal()}
            >
              {t('donations.make_donation', 'Make a Donation')}
            </Button>
          }
        />
      )}

      {/* Active Giving Days */}
      {!error && !isLoading && givingDays.length > 0 && (
        <div className="space-y-4">
          <h3 className="text-sm font-semibold text-theme-secondary uppercase tracking-wide">
            {t('donations.active_giving_days', 'Active Giving Days')}
          </h3>
          <motion.div
            variants={containerVariants}
            initial="hidden"
            animate="visible"
            className="space-y-4"
          >
            {givingDays.map((day) => {
              const pct = day.goal_amount > 0 ? Math.min(100, (day.raised_amount / day.goal_amount) * 100) : 0;
              return (
                <motion.div key={day.id} variants={itemVariants}>
                  <GlassCard className="p-5">
                    <div className="flex items-start justify-between gap-4">
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-2">
                          <h4 className="font-semibold text-theme-primary text-lg">{day.title}</h4>
                          <Chip size="sm" color={(day.status === 'active' || day.is_active) ? 'success' : 'default'} variant="flat">
                            {t(`donations.day_status.${day.status ?? (day.is_active ? 'active' : 'ended')}`, day.status ?? (day.is_active ? 'Active' : 'Ended'))}
                          </Chip>
                        </div>
                        {day.description && (
                          <p className="text-sm text-theme-muted mb-3 line-clamp-2">{day.description}</p>
                        )}
                        <Progress
                          size="md"
                          value={pct}
                          color="success"
                          className="mb-2"
                          aria-label={`Progress: ${Math.round(pct)}%`}
                        />
                        <div className="flex flex-wrap items-center gap-3 text-xs text-theme-subtle">
                          <span className="flex items-center gap-1">
                            <Banknote className="w-3 h-3" aria-hidden="true" />
                            {day.raised_amount.toLocaleString()} / {day.goal_amount.toLocaleString()}
                          </span>
                          <span className="flex items-center gap-1">
                            <Users className="w-3 h-3" aria-hidden="true" />
                            {t('donations.donors_count', '{{count}} donors', { count: day.donor_count ?? 0 })}
                          </span>
                          <span className="flex items-center gap-1">
                            <Calendar className="w-3 h-3" aria-hidden="true" />
                            {new Date(day.ends_at ?? day.end_date).toLocaleDateString()}
                          </span>
                        </div>
                      </div>
                      <div className="flex flex-col gap-2 flex-shrink-0">
                        <Button
                          size="sm"
                          className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
                          startContent={<Heart className="w-4 h-4" aria-hidden="true" />}
                          onPress={() => openDonateModal(day.id)}
                        >
                          {t('donations.donate', 'Donate')}
                        </Button>
                        <Button
                          size="sm"
                          variant="bordered"
                          className="border-rose-500 text-rose-500"
                          startContent={<CreditCard className="w-4 h-4" aria-hidden="true" />}
                          onPress={() => openStripeCheckout(day.id)}
                        >
                          {t('donations.donate_with_card', 'Donate with Card')}
                        </Button>
                      </div>
                    </div>
                  </GlassCard>
                </motion.div>
              );
            })}
          </motion.div>
        </div>
      )}

      {/* My Donations */}
      {!error && !isLoading && donations.length > 0 && (
        <div className="space-y-4">
          <h3 className="text-sm font-semibold text-theme-secondary uppercase tracking-wide">
            {t('donations.my_donations', 'My Donations')}
          </h3>
          <motion.div
            variants={containerVariants}
            initial="hidden"
            animate="visible"
            className="space-y-3"
          >
            {donations.map((d) => (
              <motion.div key={d.id} variants={itemVariants}>
                <GlassCard className="p-4">
                  <div className="flex items-center justify-between gap-3">
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 mb-1">
                        <span className="font-semibold text-theme-primary">
                          {d.amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </span>
                        <Chip size="sm" color={STATUS_COLOR[d.status] || 'default'} variant="flat">
                          {t(`donations.status.${d.status}`, d.status)}
                        </Chip>
                        {(d.anonymous ?? d.is_anonymous) && (
                          <Chip size="sm" variant="flat" startContent={<EyeOff className="w-3 h-3" />}>
                            {t('donations.anonymous', 'Anonymous')}
                          </Chip>
                        )}
                      </div>
                      <div className="flex flex-wrap items-center gap-3 text-xs text-theme-subtle">
                        {d.giving_day_title && <span>{d.giving_day_title}</span>}
                        <span className="flex items-center gap-1">
                          <CreditCard className="w-3 h-3" aria-hidden="true" />
                          {t(`donations.payment_methods.${d.payment_method}`, d.payment_method.replace('_', ' '))}
                        </span>
                        <span className="flex items-center gap-1">
                          <Calendar className="w-3 h-3" aria-hidden="true" />
                          {new Date(d.created_at).toLocaleDateString()}
                        </span>
                      </div>
                      {d.message && (
                        <p className="text-xs text-theme-muted mt-1 line-clamp-1">{d.message}</p>
                      )}
                    </div>
                  </div>
                </GlassCard>
              </motion.div>
            ))}
          </motion.div>
        </div>
      )}

      {/* Stripe Checkout Modal */}
      <DonationCheckout
        isOpen={isCheckoutOpen}
        onClose={onCheckoutClose}
        givingDayId={checkoutGivingDayId}
        onDonationComplete={load}
      />

      {/* Donate Modal */}
      <Modal
        isOpen={isOpen}
        onOpenChange={onOpenChange}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="text-theme-primary">{t('donations.modal_title', 'Make a Donation')}</ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label={t('donations.form.amount', 'Amount')}
                  type="number"
                  min="1"
                  step="0.01"
                  variant="bordered"
                  value={form.amount}
                  onValueChange={(v) => setForm((f) => ({ ...f, amount: v }))}
                  startContent={<Banknote className="w-4 h-4 text-theme-subtle" />}
                  isRequired
                />
                <div className="flex flex-wrap gap-2">
                  {PAYMENT_METHODS.map((pm) => (
                    <Chip
                      key={pm}
                      variant={form.payment_method === pm ? 'solid' : 'flat'}
                      color={form.payment_method === pm ? 'primary' : 'default'}
                      className="cursor-pointer"
                      onClick={() => setForm((f) => ({ ...f, payment_method: pm }))}
                    >
                      {t(`donations.payment_methods.${pm}`, pm.replace('_', ' '))}
                    </Chip>
                  ))}
                </div>
                <Textarea
                  label={t('donations.form.message', 'Message (optional)')}
                  variant="bordered"
                  value={form.message}
                  onValueChange={(v) => setForm((f) => ({ ...f, message: v }))}
                  maxRows={3}
                />
                <Button
                  variant={form.anonymous ? 'solid' : 'flat'}
                  color={form.anonymous ? 'secondary' : 'default'}
                  size="sm"
                  startContent={<EyeOff className="w-4 h-4" aria-hidden="true" />}
                  onPress={() => setForm((f) => ({ ...f, anonymous: !f.anonymous }))}
                >
                  {form.anonymous ? t('donations.anonymous_toggle_on', 'Donating anonymously') : t('donations.anonymous_toggle_off', 'Donate anonymously')}
                </Button>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>{t('donations.cancel', 'Cancel')}</Button>
                <Button
                  className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
                  onPress={() => handleSubmit(onClose)}
                  isLoading={isSubmitting}
                >
                  {t('donations.confirm', 'Confirm Donation')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default DonationsTab;
