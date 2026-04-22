// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * OrgWalletTab - Organisation wallet balance, deposit, auto-pay toggle, and transaction history
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { motion } from 'framer-motion';
import {
  Button,
  Chip,
  Spinner,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  Switch,
  useDisclosure,
} from '@heroui/react';
import Wallet from 'lucide-react/icons/wallet';
import ArrowDownToLine from 'lucide-react/icons/arrow-down-to-line';
import ArrowUpFromLine from 'lucide-react/icons/arrow-up-from-line';
import Clock from 'lucide-react/icons/clock';
import ChevronDown from 'lucide-react/icons/chevron-down';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import User from 'lucide-react/icons/user';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

/* ───────────────────────── Types ───────────────────────── */

interface OrgWalletTabProps {
  orgId: number;
  balance: number;
  autoPay: boolean;
  onBalanceChange: () => void;
}

interface WalletTransaction {
  id: number;
  type: 'deposit' | 'withdrawal' | 'volunteer_payment' | 'admin_adjustment';
  amount: number;
  balance_after: number;
  description: string | null;
  created_at: string;
  vol_log_id: number | null;
  user: { id: number; name: string; avatar_url: string | null } | null;
}

interface TransactionsResponse {
  items: WalletTransaction[];
  cursor: string | null;
  has_more: boolean;
}

/* ───────────────────────── Constants ───────────────────────── */

const TYPE_COLOR: Record<WalletTransaction['type'], 'success' | 'warning' | 'danger' | 'primary'> = {
  deposit: 'success',
  volunteer_payment: 'warning',
  withdrawal: 'danger',
  admin_adjustment: 'primary',
};

const TYPE_LABELS: Record<WalletTransaction['type'], string> = {
  deposit: 'Deposit',
  volunteer_payment: 'Volunteer Payment',
  withdrawal: 'Withdrawal',
  admin_adjustment: 'Admin Adjustment',
};

/* ───────────────────────── Component ───────────────────────── */

export function OrgWalletTab({ orgId, balance, autoPay, onBalanceChange }: OrgWalletTabProps) {
  const { t } = useTranslation('volunteering');
  const toast = useToast();
  const { isOpen, onOpen, onOpenChange } = useDisclosure();

  // Transaction state
  const [transactions, setTransactions] = useState<WalletTransaction[]>([]);
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Auto-pay toggle state
  const [autoPayEnabled, setAutoPayEnabled] = useState(autoPay);
  const [isTogglingAutoPay, setIsTogglingAutoPay] = useState(false);

  // Deposit form state
  const [depositAmount, setDepositAmount] = useState('');
  const [depositNote, setDepositNote] = useState('');
  const [isDepositing, setIsDepositing] = useState(false);

  // Stable refs
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;
  const abortRef = useRef<AbortController | null>(null);

  // Sync autoPay prop
  useEffect(() => {
    setAutoPayEnabled(autoPay);
  }, [autoPay]);

  /* ───────────────────────── Data fetching ───────────────────────── */

  const loadTransactions = useCallback(async (nextCursor: string | null = null) => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    const isAppend = nextCursor !== null;

    try {
      if (isAppend) {
        setIsLoadingMore(true);
      } else {
        setIsLoading(true);
        setError(null);
      }

      const params = new URLSearchParams();
      if (nextCursor) params.set('cursor', nextCursor);

      const url = `/v2/volunteering/organisations/${orgId}/wallet/transactions${params.toString() ? `?${params.toString()}` : ''}`;
      const response = await api.get<TransactionsResponse>(url);

      if (controller.signal.aborted) return;

      if (response.success && response.data) {
        // api.get() already unwraps { data: [...], meta: {...} } → response.data = [...], response.meta = {...}
        const items = Array.isArray(response.data) ? response.data : [];
        const newCursor = response.meta?.cursor ?? null;
        const hasMoreData = response.meta?.has_more ?? false;

        if (isAppend) {
          setTransactions((prev) => [...prev, ...items]);
        } else {
          setTransactions(items);
        }
        setCursor(newCursor);
        setHasMore(hasMoreData);
      } else {
        setError(tRef.current('org_wallet.load_error', 'Unable to load transactions.'));
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load transactions', err);
      setError(tRef.current('org_wallet.load_error', 'Unable to load transactions.'));
    } finally {
      if (!controller.signal.aborted) {
        setIsLoading(false);
        setIsLoadingMore(false);
      }
    }
  }, [orgId]);

  const loadRef = useRef(loadTransactions);
  loadRef.current = loadTransactions;

  useEffect(() => {
    loadRef.current();
    return () => { abortRef.current?.abort(); };
  }, []);

  /* ───────────────────────── Auto-pay toggle ───────────────────────── */

  const handleAutoPayToggle = async (enabled: boolean) => {
    try {
      setIsTogglingAutoPay(true);
      setAutoPayEnabled(enabled);

      const response = await api.put(`/v2/volunteering/organisations/${orgId}/wallet/auto-pay`, {
        enabled,
      });

      if (response.success) {
        toastRef.current.success(
          enabled
            ? tRef.current('org_wallet.auto_pay_enabled', 'Auto-pay enabled.')
            : tRef.current('org_wallet.auto_pay_disabled', 'Auto-pay disabled.')
        );
        onBalanceChange();
      } else {
        // Revert on failure
        setAutoPayEnabled(!enabled);
        toastRef.current.error(response.error || tRef.current('org_wallet.auto_pay_error', 'Failed to update auto-pay.'));
      }
    } catch (err) {
      setAutoPayEnabled(!enabled);
      logError('Failed to toggle auto-pay', err);
      toastRef.current.error(tRef.current('org_wallet.auto_pay_error', 'Failed to update auto-pay.'));
    } finally {
      setIsTogglingAutoPay(false);
    }
  };

  /* ───────────────────────── Deposit ───────────────────────── */

  const resetDepositForm = () => {
    setDepositAmount('');
    setDepositNote('');
  };

  const handleDeposit = async (onClose: () => void) => {
    if (isDepositing) return;

    const amount = parseFloat(depositAmount);
    if (!depositAmount || isNaN(amount) || amount <= 0) {
      toastRef.current.error(tRef.current('org_wallet.invalid_amount', 'Please enter a valid amount.'));
      return;
    }
    if (amount > 9999) {
      toastRef.current.error(tRef.current('org_wallet.deposit_amount_too_large', 'Deposit amount cannot exceed 9,999 hours.'));
      return;
    }

    try {
      setIsDepositing(true);

      const response = await api.post(`/v2/volunteering/organisations/${orgId}/wallet/deposit`, {
        amount,
        note: depositNote || null,
      });

      if (response.success) {
        toastRef.current.success(tRef.current('org_wallet.deposit_success', 'Credits deposited successfully.'));
        resetDepositForm();
        onClose();
        onBalanceChange();
        loadTransactions();
      } else {
        toastRef.current.error(response.error || tRef.current('org_wallet.deposit_error', 'Failed to deposit credits.'));
      }
    } catch (err) {
      logError('Failed to deposit credits', err);
      toastRef.current.error(tRef.current('org_wallet.deposit_error', 'Failed to deposit credits.'));
    } finally {
      setIsDepositing(false);
    }
  };

  /* ───────────────────────── Helpers ───────────────────────── */

  const getBalanceColor = () => {
    if (balance <= 0) return 'text-red-500';
    if (balance < 5) return 'text-amber-500';
    return 'text-emerald-500';
  };

  const formatAmount = (amount: number) => {
    if (amount > 0) return `+${amount}`;
    return `${amount}`;
  };

  const getAmountColor = (amount: number) => {
    return amount >= 0 ? 'text-emerald-500' : 'text-red-500';
  };

  /* ───────────────────────── Animation variants ───────────────────────── */

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
      {/* Balance Card */}
      <GlassCard className="p-6 space-y-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Wallet className="w-5 h-5 text-emerald-400" aria-hidden="true" />
            <h2 className="text-lg font-semibold text-theme-primary">
              {t('org_wallet.heading', 'Organisation Wallet')}
            </h2>
          </div>
          <Button
            size="sm"
            variant="flat"
            className="bg-theme-elevated text-theme-muted"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => {
              onBalanceChange();
              loadTransactions();
            }}
            isDisabled={isLoading}
          >
            {t('org_wallet.refresh', 'Refresh')}
          </Button>
        </div>

        <div className="text-center py-4">
          <p className={`text-5xl font-bold ${getBalanceColor()}`}>{balance}</p>
          <p className="text-sm text-theme-muted mt-1">
            {t('org_wallet.hours_label', 'hours')}
          </p>
        </div>

        <Button
          className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white w-full"
          startContent={<ArrowDownToLine className="w-4 h-4" aria-hidden="true" />}
          onPress={onOpen}
        >
          {t('org_wallet.deposit', 'Deposit Credits')}
        </Button>
      </GlassCard>

      {/* Auto-pay Card */}
      <GlassCard className="p-6 space-y-4">
        <div className="flex items-center justify-between gap-4">
          <div className="flex-1 min-w-0">
            <h3 className="font-semibold text-theme-primary">
              {t('org_wallet.auto_pay_title', 'Auto-pay Volunteers')}
            </h3>
            <p className="text-sm text-theme-muted mt-1">
              {t(
                'org_wallet.auto_pay_description',
                'Automatically pay volunteers from this wallet when their hours are approved.'
              )}
            </p>
          </div>
          <Switch
            isSelected={autoPayEnabled}
            onValueChange={handleAutoPayToggle}
            isDisabled={isTogglingAutoPay}
            aria-label={t('org_wallet.auto_pay_toggle', 'Toggle auto-pay')}
          />
        </div>
      </GlassCard>

      {/* Transaction History */}
      <div className="space-y-4">
        <div className="flex items-center gap-2">
          <Clock className="w-5 h-5 text-theme-muted" aria-hidden="true" />
          <h3 className="text-sm font-semibold text-theme-secondary uppercase tracking-wide">
            {t('org_wallet.transaction_history', 'Transaction History')}
          </h3>
        </div>

        {/* Error */}
        {error && !isLoading && (
          <GlassCard className="p-8 text-center">
            <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
            <p className="text-theme-muted mb-4">{error}</p>
            <Button
              className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white"
              onPress={() => loadTransactions()}
            >
              {t('org_wallet.try_again', 'Try Again')}
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
        {!error && !isLoading && transactions.length === 0 && (
          <EmptyState
            icon={<Wallet className="w-12 h-12" aria-hidden="true" />}
            title={t('org_wallet.empty_title', 'No transactions yet')}
            description={t(
              'org_wallet.empty_description',
              'Transactions will appear here when credits are deposited, withdrawn, or paid to volunteers.'
            )}
          />
        )}

        {/* Transaction List */}
        {!error && !isLoading && transactions.length > 0 && (
          <motion.div
            variants={containerVariants}
            initial="hidden"
            animate="visible"
            className="space-y-3"
          >
            {transactions.map((tx) => (
              <motion.div key={tx.id} variants={itemVariants}>
                <GlassCard className="p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 mb-1 flex-wrap">
                        <Chip size="sm" variant="flat" color={TYPE_COLOR[tx.type]}>
                          {t(`org_wallet.types.${tx.type}`, TYPE_LABELS[tx.type])}
                        </Chip>
                        <span className={`font-bold text-lg ${getAmountColor(tx.amount)}`}>
                          {formatAmount(tx.amount)}
                        </span>
                      </div>
                      {tx.description && (
                        <p className="text-sm text-theme-secondary mb-1">{tx.description}</p>
                      )}
                      <div className="flex flex-wrap items-center gap-3 text-xs text-theme-subtle">
                        {tx.user && (
                          <span className="flex items-center gap-1">
                            <User className="w-3 h-3" aria-hidden="true" />
                            {tx.user.name}
                          </span>
                        )}
                        <span className="flex items-center gap-1">
                          <Clock className="w-3 h-3" aria-hidden="true" />
                          {new Date(tx.created_at).toLocaleDateString()}
                        </span>
                        <span className="text-theme-muted">
                          {t('org_wallet.balance_after', 'Balance: {{balance}}', {
                            balance: tx.balance_after,
                          })}
                        </span>
                      </div>
                    </div>
                    <div className="flex-shrink-0">
                      {tx.amount >= 0 ? (
                        <ArrowDownToLine className="w-5 h-5 text-emerald-500" aria-hidden="true" />
                      ) : (
                        <ArrowUpFromLine className="w-5 h-5 text-red-500" aria-hidden="true" />
                      )}
                    </div>
                  </div>
                </GlassCard>
              </motion.div>
            ))}

            {/* Load More */}
            {hasMore && (
              <div className="flex justify-center pt-2">
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-muted"
                  startContent={
                    isLoadingMore ? (
                      <Spinner size="sm" />
                    ) : (
                      <ChevronDown className="w-4 h-4" aria-hidden="true" />
                    )
                  }
                  onPress={() => loadTransactions(cursor)}
                  isDisabled={isLoadingMore}
                >
                  {isLoadingMore
                    ? t('org_wallet.loading_more', 'Loading...')
                    : t('org_wallet.load_more', 'Load More')}
                </Button>
              </div>
            )}
          </motion.div>
        )}
      </div>

      {/* Deposit Modal */}
      <Modal
        isOpen={isOpen}
        onOpenChange={(open) => {
          if (!open) resetDepositForm();
          onOpenChange();
        }}
        classNames={{
          base: 'border border-theme-default bg-theme-surface',
          header: 'border-b border-theme-default',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="text-theme-primary">
                {t('org_wallet.deposit_modal_title', 'Deposit Credits')}
              </ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label={t('org_wallet.form.amount', 'Amount (hours)')}
                  type="number"
                  min="0.25"
                  max="9999"
                  step="0.25"
                  value={depositAmount}
                  onValueChange={setDepositAmount}
                  variant="bordered"
                  classNames={{ inputWrapper: 'bg-theme-elevated' }}
                  startContent={<Wallet className="w-4 h-4 text-theme-subtle" />}
                  isRequired
                />
                <Textarea
                  label={t('org_wallet.form.note', 'Note (optional)')}
                  value={depositNote}
                  onValueChange={setDepositNote}
                  variant="bordered"
                  classNames={{ inputWrapper: 'bg-theme-elevated' }}
                  minRows={2}
                  maxRows={4}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  {t('org_wallet.cancel', 'Cancel')}
                </Button>
                <Button
                  className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white"
                  onPress={() => handleDeposit(onClose)}
                  isLoading={isDepositing}
                >
                  {t('org_wallet.deposit_button', 'Deposit')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default OrgWalletTab;
