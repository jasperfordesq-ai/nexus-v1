// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Wallet Page - Time credit balance and transactions
 */

import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import Papa from 'papaparse';
import { useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Tabs, Tab, Skeleton } from '@heroui/react';
import Wallet from 'lucide-react/icons/wallet';
import ArrowUpRight from 'lucide-react/icons/arrow-up-right';
import ArrowDownLeft from 'lucide-react/icons/arrow-down-left';
import Clock from 'lucide-react/icons/clock';
import TrendingUp from 'lucide-react/icons/trending-up';
import Calendar from 'lucide-react/icons/calendar';
import User from 'lucide-react/icons/user';
import Download from 'lucide-react/icons/download';
import Send from 'lucide-react/icons/send';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { EmptyState } from '@/components/feedback';
import { TransferModal, DonateModal, CommunityFundCard } from '@/components/wallet';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import type { WalletBalance, Transaction } from '@/types/api';

type TransactionFilter = 'all' | 'earned' | 'spent' | 'pending';

export function WalletPage() {
  const { t } = useTranslation('wallet');
  usePageTitle(t('title'));
  const [searchParams, setSearchParams] = useSearchParams();
  const [balance, setBalance] = useState<WalletBalance | null>(null);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMoreTransactions, setHasMoreTransactions] = useState(true);
  const [txCursor, setTxCursor] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [filter, setFilter] = useState<TransactionFilter>('all');
  const [isTransferModalOpen, setIsTransferModalOpen] = useState(false);
  const [isDonateModalOpen, setIsDonateModalOpen] = useState(false);
  const toast = useToast();

  const abortRef = useRef<AbortController | null>(null);
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;
  // Check for ?to=userId URL param to auto-open transfer modal
  const [savedRecipientId, setSavedRecipientId] = useState<number | null>(
    searchParams.get('to') ? parseInt(searchParams.get('to')!, 10) : null
  );

  const loadWalletData = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      setIsLoading(true);
      setError(null);
      setTxCursor(null);
      const [balanceRes, transactionsRes] = await Promise.all([
        api.get<WalletBalance>('/v2/wallet/balance'),
        api.get<Transaction[]>('/v2/wallet/transactions?per_page=50'),
      ]);

      if (controller.signal.aborted) return;

      if (balanceRes.success && balanceRes.data) {
        setBalance(balanceRes.data);
      } else {
        setError(balanceRes.code === 'SESSION_EXPIRED'
          ? tRef.current('error.session_expired', 'Your session has expired. Please log in again.')
          : tRef.current('error.load_balance'));
        return;
      }
      if (transactionsRes.success && transactionsRes.data) {
        setTransactions(transactionsRes.data);
        setTxCursor(transactionsRes.meta?.cursor ?? null);
        setHasMoreTransactions(transactionsRes.meta?.has_more ?? transactionsRes.data.length >= 50);
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load wallet data', err);
      setError(tRef.current('error.load_wallet'));
    } finally {
      if (!controller.signal.aborted) {
        setIsLoading(false);
      }
    }
  }, []);

  const loadWalletDataRef = useRef(loadWalletData);
  loadWalletDataRef.current = loadWalletData;

  useEffect(() => {
    loadWalletDataRef.current();
    return () => {
      abortRef.current?.abort();
    };
  }, []);

  // Auto-open transfer modal when ?to=userId is present and data is loaded
  useEffect(() => {
    if (savedRecipientId && !isLoading && balance && !isTransferModalOpen) {
      setIsTransferModalOpen(true);
      // Clear the URL param so it doesn't reopen on subsequent renders
      const newParams = new URLSearchParams(searchParams);
      newParams.delete('to');
      setSearchParams(newParams, { replace: true });
    }
  }, [savedRecipientId, isLoading, balance]); // eslint-disable-line react-hooks/exhaustive-deps -- sync from URL params; searchParams/setSearchParams excluded to avoid loop

  // Load more transactions (cursor-based pagination)
  const loadMoreTransactions = useCallback(async () => {
    if (isLoadingMore || !hasMoreTransactions || !txCursor) return;

    try {
      setIsLoadingMore(true);
      const response = await api.get<Transaction[]>(`/v2/wallet/transactions?per_page=50&cursor=${encodeURIComponent(txCursor)}`);

      if (response.success && response.data) {
        if (response.data.length > 0) {
          setTransactions((prev) => [...prev, ...response.data!]);
        }
        setTxCursor(response.meta?.cursor ?? null);
        setHasMoreTransactions(response.meta?.has_more ?? false);
      }
    } catch (err) {
      logError('Failed to load more transactions', err);
      toastRef.current.error(tRef.current('toast.load_error'));
    } finally {
      setIsLoadingMore(false);
    }
  }, [isLoadingMore, hasMoreTransactions, txCursor]);

  // Handle successful transfer
  function handleTransferComplete(transaction: Transaction) {
    // Add the new transaction to the list
    setTransactions((prev) => [transaction, ...prev]);

    // Update balance
    if (balance) {
      setBalance({
        ...balance,
        balance: balance.balance - transaction.amount,
        total_spent: balance.total_spent + transaction.amount,
      });
    }

    toast.success(
      t('toast.transfer_success'),
      t('toast.transfer_desc', { amount: transaction.amount, recipient: transaction.other_user?.name || 'recipient' })
    );
  }

  const filteredTransactions = useMemo(() => {
    return transactions.filter((tx) => {
      if (filter === 'all') return true;
      if (filter === 'earned') return tx.type === 'credit';
      if (filter === 'spent') return tx.type === 'debit';
      if (filter === 'pending') return tx.status === 'pending';
      return true;
    });
  }, [transactions, filter]);

  const stats = useMemo(() => ({
    earned: balance?.total_earned ?? 0,
    spent: balance?.total_spent ?? 0,
    pending: (balance?.pending_in ?? balance?.pending_incoming ?? 0)
           + (balance?.pending_out ?? balance?.pending_outgoing ?? 0),
  }), [balance]);

  // Export transactions to CSV using papaparse
  function handleExport() {
    if (transactions.length === 0) {
      toast.info(t('toast.no_data'));
      return;
    }

    const data = transactions.map((tx) => ({
      [t('csv.date')]: new Date(tx.created_at).toLocaleDateString(),
      [t('csv.type')]: tx.type === 'credit' ? t('csv.received') : t('csv.sent'),
      [t('csv.amount')]: tx.amount,
      [t('csv.description')]: tx.description || '',
      [t('csv.other_party')]: tx.other_user?.name || tx.other_party?.name || '',
      [t('csv.status')]: tx.status,
    }));

    const csvContent = Papa.unparse(data);

    // Create and download file
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `transactions_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    toast.success(t('toast.exported'), t('toast.exported_desc'));
  }

  return (
    <>
    <PageMeta
      title={t("title")}
      description={t("subtitle")}
      noIndex
    />
    <div className="max-w-4xl mx-auto space-y-6">
      {/* Header */}
      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.3 }}>
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <Wallet className="w-7 h-7 text-amber-400" />
          {t('title')}
        </h1>
        <p className="text-theme-muted mt-1">{t('subtitle')}</p>
      </motion.div>

      {/* Error State */}
      {error && (
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.3 }}>
          <GlassCard className="p-8 text-center">
            <AlertTriangle className="w-12 h-12 text-[var(--color-warning)] mx-auto mb-4" aria-hidden="true" />
            <h3 className="text-lg font-semibold text-theme-primary mb-2">{t('unable_to_load')}</h3>
            <p className="text-theme-muted mb-4">{error}</p>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" />}
              onPress={() => loadWalletData()}
            >
              {t('try_again')}
            </Button>
          </GlassCard>
        </motion.div>
      )}

      {/* Balance Card */}
      {!error && (
      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.3 }}>
        <GlassCard className="p-8 text-center relative overflow-hidden">
          {/* Background decoration */}
          <div className="absolute inset-0 opacity-10">
            <div className="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-full blur-3xl" />
            <div className="absolute bottom-0 left-0 w-64 h-64 bg-gradient-to-tr from-emerald-500 to-teal-500 rounded-full blur-3xl" />
          </div>

          <div className="relative z-10">
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-4">
              <Wallet className="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
            </div>

            <p className="text-theme-muted text-sm mb-2">{t('your_balance')}</p>

            {isLoading ? (
              <Skeleton className="rounded-lg mx-auto">
                <div className="h-12 w-32 rounded-lg bg-default-300 mx-auto" />
              </Skeleton>
            ) : (
              <h1 className="text-3xl sm:text-5xl font-bold text-theme-primary mb-2">
                {balance?.balance ?? 0}
                <span className="text-2xl text-theme-muted ml-2">{t('hours')}</span>
              </h1>
            )}

            <p className="text-theme-subtle text-sm mb-4">
              {(balance?.pending_in || balance?.pending_incoming) ? t('pending_in', { count: balance?.pending_in ?? balance?.pending_incoming ?? 0 }) : t('no_pending')}
            </p>

            {/* Action Buttons */}
            <div className="flex gap-3 justify-center flex-wrap">
              <Button
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium px-8"
                size="lg"
                startContent={<Send className="w-5 h-5" />}
                onPress={() => setIsTransferModalOpen(true)}
                isDisabled={isLoading || !balance || balance.balance <= 0}
              >
                {t('send_credits')}
              </Button>
              <Button
                variant="flat"
                size="lg"
                className="bg-rose-500/10 text-rose-400 font-medium px-6"
                startContent={<ArrowDownLeft className="w-5 h-5" />}
                onPress={() => setIsDonateModalOpen(true)}
                isDisabled={isLoading || !balance || balance.balance <= 0}
              >
                {t('donate')}
              </Button>
            </div>
          </div>
        </GlassCard>
      </motion.div>
      )}

      {/* Community Fund */}
      {!error && (
      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.3 }}>
        <CommunityFundCard
          compact
          onDonateClick={() => setIsDonateModalOpen(true)}
        />
      </motion.div>
      )}

      {/* Stats Grid */}
      {!error && (
      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.3 }} className="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <StatCard
          icon={<ArrowDownLeft className="w-5 h-5" aria-hidden="true" />}
          label={t('stats.earned')}
          value={`+${stats.earned}h`}
          color="emerald"
          isLoading={isLoading}
        />
        <StatCard
          icon={<ArrowUpRight className="w-5 h-5" aria-hidden="true" />}
          label={t('stats.spent')}
          value={`-${stats.spent}h`}
          color="rose"
          isLoading={isLoading}
        />
        <StatCard
          icon={<Clock className="w-5 h-5" aria-hidden="true" />}
          label={t('stats.pending')}
          value={`${stats.pending}h`}
          color="amber"
          isLoading={isLoading}
        />
      </motion.div>
      )}

      {/* Transactions */}
      {!error && (
      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.3 }}>
        <GlassCard className="p-6">
          <div className="flex flex-col gap-3 mb-6 sm:flex-row sm:items-center sm:justify-between">
            <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2">
              <TrendingUp className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
              {t('history')}
            </h2>

            <Button
              variant="flat"
              size="sm"
              className="bg-theme-elevated text-theme-muted"
              startContent={<Download className="w-4 h-4" aria-hidden="true" />}
              onPress={handleExport}
              isDisabled={transactions.length === 0}
              aria-label={t('aria.export_csv')}
            >
              {t('export')}
            </Button>
          </div>

          {/* Filter Tabs */}
          <Tabs
            selectedKey={filter}
            onSelectionChange={(key) => { setFilter(key as TransactionFilter); setTxCursor(null); setHasMoreTransactions(true); }}
            classNames={{
              base: 'w-full max-w-full overflow-x-auto',
              tabList: 'bg-theme-elevated p-1 rounded-lg min-w-max flex-nowrap',
              cursor: 'bg-theme-hover',
              tab: 'shrink-0 text-theme-muted data-[selected=true]:text-theme-primary',
            }}
          >
            <Tab key="all" title={t('filter.all')} />
            <Tab key="earned" title={t('filter.earned')} />
            <Tab key="spent" title={t('filter.spent')} />
            <Tab key="pending" title={t('filter.pending')} />
          </Tabs>

          {/* Transactions List */}
          <div className="mt-6 space-y-3">
            {isLoading ? (
              <div aria-label={t('aria.loading_transactions')} aria-busy="true" className="space-y-3">
                {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="p-4 rounded-lg bg-theme-elevated">
                  <div className="flex items-center gap-4">
                    <Skeleton className="rounded-full flex-shrink-0"><div className="w-10 h-10 rounded-full bg-default-300" /></Skeleton>
                    <div className="flex-1 space-y-2">
                      <Skeleton className="rounded-lg"><div className="h-4 rounded-lg bg-default-300 w-1/3" /></Skeleton>
                      <Skeleton className="rounded-lg"><div className="h-3 rounded-lg bg-default-200 w-1/4" /></Skeleton>
                    </div>
                  </div>
                </div>
                ))}
              </div>
              ) : filteredTransactions.length === 0 ? (
              <EmptyState
                icon={<Wallet className="w-12 h-12" />}
                title={t('no_transactions')}
                description={t('no_transactions_desc')}
              />
            ) : (
              <>
                {filteredTransactions.map((transaction) => (
                  <TransactionCard key={transaction.id} transaction={transaction} />
                ))}
                {/* Load More Button */}
                {hasMoreTransactions && (
                  <div className="pt-4 text-center">
                    <Button
                      variant="flat"
                      className="bg-theme-elevated text-theme-muted"
                      onPress={loadMoreTransactions}
                      isLoading={isLoadingMore}
                    >
                      {t('load_more')}
                    </Button>
                  </div>
                )}
              </>
            )}
          </div>
        </GlassCard>
      </motion.div>
      )}

      {/* Transfer Modal */}
      <TransferModal
        isOpen={isTransferModalOpen}
        onClose={() => { setIsTransferModalOpen(false); setSavedRecipientId(null); }}
        currentBalance={balance?.balance ?? 0}
        onTransferComplete={handleTransferComplete}
        initialRecipientId={savedRecipientId}
      />

      {/* Donate Modal */}
      <DonateModal
        isOpen={isDonateModalOpen}
        onClose={() => setIsDonateModalOpen(false)}
        currentBalance={balance?.balance ?? 0}
        onDonationComplete={() => loadWalletData()}
      />
    </div>
    </>
  );
}

interface StatCardProps {
  icon: React.ReactNode;
  label: string;
  value: string;
  color: 'emerald' | 'rose' | 'amber';
  isLoading?: boolean;
}

function StatCard({ icon, label, value, color, isLoading }: StatCardProps) {
  const colorClasses = {
    emerald: 'from-emerald-500/20 to-teal-500/20 text-emerald-400',
    rose: 'from-rose-500/20 to-pink-500/20 text-rose-400',
    amber: 'from-amber-500/20 to-orange-500/20 text-amber-400',
  };

  return (
    <GlassCard className="p-4 text-center">
      <div className={`inline-flex p-2 rounded-lg bg-gradient-to-br ${colorClasses[color]} mb-2`}>
        {icon}
      </div>
      <div className="text-theme-subtle text-xs mb-1">{label}</div>
      {isLoading ? (
        <Skeleton className="rounded-lg mx-auto"><div className="h-6 w-12 rounded-lg bg-default-300 mx-auto" /></Skeleton>
      ) : (
        <div className="text-xl font-bold text-theme-primary">{value}</div>
      )}
    </GlassCard>
  );
}

interface TransactionCardProps {
  transaction: Transaction;
}

function TransactionCard({ transaction }: TransactionCardProps) {
  const { t } = useTranslation('wallet');
  const isCredit = transaction.type === 'credit';
  const otherPartyName = transaction.other_user?.name || transaction.other_party?.name;

  return (
    <article
      className="p-4 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors"
      aria-label={t('aria.transaction_detail', { direction: isCredit ? t('csv.received') : t('csv.sent'), amount: transaction.amount, name: otherPartyName || '' })}
    >
      <div className="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
        <div className={`
          p-2.5 rounded-full self-start shrink-0
          ${isCredit ? 'bg-emerald-500/20' : 'bg-rose-500/20'}
        `}>
          {isCredit ? (
            <ArrowDownLeft className="w-5 h-5 text-emerald-400" aria-hidden="true" />
          ) : (
            <ArrowUpRight className="w-5 h-5 text-rose-400" aria-hidden="true" />
          )}
        </div>

        <div className="flex-1 min-w-0">
          <div className="flex min-w-0 flex-wrap items-center gap-2">
            <h4 className="font-medium text-theme-primary truncate">{transaction.description}</h4>
            {transaction.status === 'pending' && (
              <span className="text-xs px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400">
                {t('filter.pending')}
              </span>
            )}
          </div>
          <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-theme-subtle mt-1">
            {transaction.other_user && (
              <span className="flex min-w-0 items-center gap-1">
                <User className="w-3 h-3 shrink-0" aria-hidden="true" />
                <span className="truncate">{transaction.other_user.name}</span>
              </span>
            )}
            <span className="flex items-center gap-1">
              <Calendar className="w-3 h-3 shrink-0" aria-hidden="true" />
              <time dateTime={transaction.created_at}>
                {new Date(transaction.created_at).toLocaleDateString()}
              </time>
            </span>
          </div>
        </div>

        <div className={`shrink-0 text-lg font-semibold sm:text-right ${isCredit ? 'text-emerald-400' : 'text-rose-400'}`}>
          {isCredit ? '+' : '-'}{transaction.amount}h
        </div>
      </div>
    </article>
  );
}

export default WalletPage;
