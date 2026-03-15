// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Wallet Page - Time credit balance and transactions
 */

import { useState, useEffect, useCallback, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Tabs, Tab, Skeleton } from '@heroui/react';
import {
  Wallet,
  ArrowUpRight,
  ArrowDownLeft,
  Clock,
  TrendingUp,
  Calendar,
  User,
  Download,
  Send,
  AlertTriangle,
  RefreshCw,
} from 'lucide-react';
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

  // Check for ?to=userId URL param to auto-open transfer modal
  const [savedRecipientId, setSavedRecipientId] = useState<number | null>(
    searchParams.get('to') ? parseInt(searchParams.get('to')!, 10) : null
  );

  const loadWalletData = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);
      setTxCursor(null);
      const [balanceRes, transactionsRes] = await Promise.all([
        api.get<WalletBalance>('/v2/wallet/balance'),
        api.get<Transaction[]>('/v2/wallet/transactions?per_page=50'),
      ]);

      if (balanceRes.success && balanceRes.data) {
        setBalance(balanceRes.data);
      } else {
        setError(balanceRes.code === 'SESSION_EXPIRED'
          ? t('error.session_expired', 'Your session has expired. Please log in again.')
          : t('error.load_balance'));
        return;
      }
      if (transactionsRes.success && transactionsRes.data) {
        setTransactions(transactionsRes.data);
        setTxCursor(transactionsRes.meta?.cursor ?? null);
        setHasMoreTransactions(transactionsRes.meta?.has_more ?? transactionsRes.data.length >= 50);
      }
    } catch (err) {
      logError('Failed to load wallet data', err);
      setError(t('error.load_wallet'));
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadWalletData();
  }, [loadWalletData]);

  // Auto-open transfer modal when ?to=userId is present and data is loaded
  useEffect(() => {
    if (savedRecipientId && !isLoading && balance && !isTransferModalOpen) {
      setIsTransferModalOpen(true);
      // Clear the URL param so it doesn't reopen on subsequent renders
      const newParams = new URLSearchParams(searchParams);
      newParams.delete('to');
      setSearchParams(newParams, { replace: true });
    }
  }, [savedRecipientId, isLoading, balance]); // eslint-disable-line react-hooks/exhaustive-deps

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
      toast.error(t('toast.load_error'));
    } finally {
      setIsLoadingMore(false);
    }
  }, [isLoadingMore, hasMoreTransactions, txCursor, toast]);

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
    earned: transactions
      .filter((tx) => tx.type === 'credit' && tx.status === 'completed')
      .reduce((sum, tx) => sum + tx.amount, 0),
    spent: transactions
      .filter((tx) => tx.type === 'debit' && tx.status === 'completed')
      .reduce((sum, tx) => sum + tx.amount, 0),
    pending: transactions
      .filter((tx) => tx.status === 'pending')
      .reduce((sum, tx) => sum + tx.amount, 0),
  }), [transactions]);

  /**
   * Sanitize cell value to prevent CSV injection
   * Prefixes dangerous characters with a single quote
   */
  function sanitizeCsvCell(value: string): string {
    // If the cell starts with =, +, -, @, tab, or carriage return, prefix with single quote
    if (/^[=+\-@\t\r]/.test(value)) {
      return `'${value}`;
    }
    return value;
  }

  // Export transactions to CSV
  function handleExport() {
    if (transactions.length === 0) {
      toast.info(t('toast.no_data'));
      return;
    }

    // Create CSV content
    const headers = [t('csv.date'), t('csv.type'), t('csv.amount'), t('csv.description'), t('csv.other_party'), t('csv.status')];
    const rows = transactions.map((tx) => [
      new Date(tx.created_at).toLocaleDateString(),
      tx.type === 'credit' ? t('csv.received') : t('csv.sent'),
      tx.amount.toString(),
      sanitizeCsvCell(tx.description || ''),
      sanitizeCsvCell(tx.other_user?.name || tx.other_party?.name || ''),
      tx.status,
    ]);

    const csvContent = [
      headers.join(','),
      ...rows.map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(',')),
    ].join('\n');

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

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.1 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <>
    <PageMeta
      title={t("title")}
      description={t("subtitle")}
      noIndex
    />
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="max-w-4xl mx-auto space-y-6"
    >
      {/* Header */}
      <motion.div variants={itemVariants}>
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <Wallet className="w-7 h-7 text-amber-400" />
          {t('title')}
        </h1>
        <p className="text-theme-muted mt-1">{t('subtitle')}</p>
      </motion.div>

      {/* Error State */}
      {error && (
        <motion.div variants={itemVariants}>
          <GlassCard className="p-8 text-center">
            <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" />
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
      <motion.div variants={itemVariants}>
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
              {balance?.pending_in ? t('pending_in', { count: balance.pending_in }) : t('no_pending')}
            </p>

            {/* Action Buttons */}
            <div className="flex gap-3 justify-center flex-wrap">
              <Button
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium px-8"
                size="lg"
                startContent={<Send className="w-5 h-5" />}
                onClick={() => setIsTransferModalOpen(true)}
                isDisabled={isLoading || !balance || balance.balance <= 0}
              >
                {t('send_credits')}
              </Button>
              <Button
                variant="flat"
                size="lg"
                className="bg-rose-500/10 text-rose-400 font-medium px-6"
                startContent={<ArrowDownLeft className="w-5 h-5" />}
                onClick={() => setIsDonateModalOpen(true)}
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
      <motion.div variants={itemVariants}>
        <CommunityFundCard
          compact
          onDonateClick={() => setIsDonateModalOpen(true)}
        />
      </motion.div>
      )}

      {/* Stats Grid */}
      {!error && (
      <motion.div variants={itemVariants} className="grid grid-cols-2 sm:grid-cols-3 gap-3">
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
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6">
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2">
              <TrendingUp className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
              {t('history')}
            </h2>

            <Button
              variant="flat"
              size="sm"
              className="bg-theme-elevated text-theme-muted"
              startContent={<Download className="w-4 h-4" aria-hidden="true" />}
              onClick={handleExport}
              isDisabled={transactions.length === 0}
              aria-label="Export transactions to CSV"
            >
              {t('export')}
            </Button>
          </div>

          {/* Filter Tabs */}
          <Tabs
            selectedKey={filter}
            onSelectionChange={(key) => { setFilter(key as TransactionFilter); setTxCursor(null); }}
            classNames={{
              tabList: 'bg-theme-elevated p-1 rounded-lg',
              cursor: 'bg-theme-hover',
              tab: 'text-theme-muted data-[selected=true]:text-theme-primary',
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
              <div aria-label="Loading transactions" aria-busy="true" className="space-y-3">
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
                {hasMoreTransactions && filter === 'all' && (
                  <div className="pt-4 text-center">
                    <Button
                      variant="flat"
                      className="bg-theme-elevated text-theme-muted"
                      onClick={loadMoreTransactions}
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
    </motion.div>
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
      aria-label={`${isCredit ? t('csv.received') : t('csv.sent')} ${transaction.amount} hours ${otherPartyName ? (isCredit ? 'from' : 'to') + ' ' + otherPartyName : ''}`}
    >
      <div className="flex items-center gap-4">
        <div className={`
          p-2.5 rounded-full
          ${isCredit ? 'bg-emerald-500/20' : 'bg-rose-500/20'}
        `}>
          {isCredit ? (
            <ArrowDownLeft className="w-5 h-5 text-emerald-400" aria-hidden="true" />
          ) : (
            <ArrowUpRight className="w-5 h-5 text-rose-400" aria-hidden="true" />
          )}
        </div>

        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2">
            <h4 className="font-medium text-theme-primary truncate">{transaction.description}</h4>
            {transaction.status === 'pending' && (
              <span className="text-xs px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400">
                {t('filter.pending')}
              </span>
            )}
          </div>
          <div className="flex items-center gap-3 text-sm text-theme-subtle mt-1">
            {transaction.other_user && (
              <span className="flex items-center gap-1">
                <User className="w-3 h-3" aria-hidden="true" />
                {transaction.other_user.name}
              </span>
            )}
            <span className="flex items-center gap-1">
              <Calendar className="w-3 h-3" aria-hidden="true" />
              <time dateTime={transaction.created_at}>
                {new Date(transaction.created_at).toLocaleDateString()}
              </time>
            </span>
          </div>
        </div>

        <div className={`text-lg font-semibold ${isCredit ? 'text-emerald-400' : 'text-rose-400'}`}>
          {isCredit ? '+' : '-'}{transaction.amount}h
        </div>
      </div>
    </article>
  );
}

export default WalletPage;
