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
import { Button, Tabs, Tab } from '@heroui/react';
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
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { TransferModal } from '@/components/wallet';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import type { WalletBalance, Transaction } from '@/types/api';

type TransactionFilter = 'all' | 'earned' | 'spent' | 'pending';

export function WalletPage() {
  usePageTitle('Wallet');
  const [searchParams, setSearchParams] = useSearchParams();
  const [balance, setBalance] = useState<WalletBalance | null>(null);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMoreTransactions, setHasMoreTransactions] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filter, setFilter] = useState<TransactionFilter>('all');
  const [isTransferModalOpen, setIsTransferModalOpen] = useState(false);
  const toast = useToast();

  // Check for ?to=userId URL param to auto-open transfer modal
  const [savedRecipientId, setSavedRecipientId] = useState<number | null>(
    searchParams.get('to') ? parseInt(searchParams.get('to')!, 10) : null
  );

  const loadWalletData = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);
      const [balanceRes, transactionsRes] = await Promise.all([
        api.get<WalletBalance>('/v2/wallet/balance'),
        api.get<Transaction[]>('/v2/wallet/transactions?limit=50'),
      ]);

      if (balanceRes.success && balanceRes.data) {
        setBalance(balanceRes.data);
      } else {
        setError('Failed to load wallet balance');
        return;
      }
      if (transactionsRes.success && transactionsRes.data) {
        setTransactions(transactionsRes.data);
        // If we got fewer than 50, there are no more to load
        setHasMoreTransactions(transactionsRes.data.length >= 50);
      }
    } catch (err) {
      logError('Failed to load wallet data', err);
      setError('Failed to load wallet data. Please try again.');
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

  // Load more transactions (pagination)
  const loadMoreTransactions = useCallback(async () => {
    if (isLoadingMore || !hasMoreTransactions || transactions.length === 0) return;

    try {
      setIsLoadingMore(true);
      const offset = transactions.length;
      const response = await api.get<Transaction[]>(`/v2/wallet/transactions?limit=50&offset=${offset}`);

      if (response.success && response.data) {
        if (response.data.length > 0) {
          setTransactions((prev) => [...prev, ...response.data!]);
        }
        setHasMoreTransactions(response.data.length >= 50);
      }
    } catch (err) {
      logError('Failed to load more transactions', err);
      toast.error('Error', 'Failed to load more transactions');
    } finally {
      setIsLoadingMore(false);
    }
  }, [isLoadingMore, hasMoreTransactions, transactions.length, toast]);

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
      'Transfer successful',
      `Sent ${transaction.amount} hours to ${transaction.other_user?.name || 'recipient'}`
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
      toast.info('No data', 'No transactions to export');
      return;
    }

    // Create CSV content
    const headers = ['Date', 'Type', 'Amount', 'Description', 'Other Party', 'Status'];
    const rows = transactions.map((tx) => [
      new Date(tx.created_at).toLocaleDateString(),
      tx.type === 'credit' ? 'Received' : 'Sent',
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

    toast.success('Exported', 'Transactions exported to CSV');
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
          Wallet
        </h1>
        <p className="text-theme-muted mt-1">Track your time credits and transactions</p>
      </motion.div>

      {/* Error State */}
      {error && (
        <motion.div variants={itemVariants}>
          <GlassCard className="p-8 text-center">
            <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" />
            <h3 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Wallet</h3>
            <p className="text-theme-muted mb-4">{error}</p>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" />}
              onPress={() => loadWalletData()}
            >
              Try Again
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

            <p className="text-theme-muted text-sm mb-2">Your Balance</p>

            {isLoading ? (
              <div className="h-12 w-32 mx-auto bg-theme-elevated rounded animate-pulse" />
            ) : (
              <h1 className="text-3xl sm:text-5xl font-bold text-theme-primary mb-2">
                {balance?.balance ?? 0}
                <span className="text-2xl text-theme-muted ml-2">hours</span>
              </h1>
            )}

            <p className="text-theme-subtle text-sm mb-4">
              {balance?.pending_in ? `+${balance.pending_in}h pending` : 'No pending transactions'}
            </p>

            {/* Send Credits Button */}
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium px-8"
              size="lg"
              startContent={<Send className="w-5 h-5" />}
              onClick={() => setIsTransferModalOpen(true)}
              isDisabled={isLoading || !balance || balance.balance <= 0}
            >
              Send Credits
            </Button>
          </div>
        </GlassCard>
      </motion.div>
      )}

      {/* Stats Grid */}
      {!error && (
      <motion.div variants={itemVariants} className="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <StatCard
          icon={<ArrowDownLeft className="w-5 h-5" aria-hidden="true" />}
          label="Earned"
          value={`+${stats.earned}h`}
          color="emerald"
          isLoading={isLoading}
        />
        <StatCard
          icon={<ArrowUpRight className="w-5 h-5" aria-hidden="true" />}
          label="Spent"
          value={`-${stats.spent}h`}
          color="rose"
          isLoading={isLoading}
        />
        <StatCard
          icon={<Clock className="w-5 h-5" aria-hidden="true" />}
          label="Pending"
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
              Transaction History
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
              Export
            </Button>
          </div>

          {/* Filter Tabs */}
          <Tabs
            selectedKey={filter}
            onSelectionChange={(key) => setFilter(key as TransactionFilter)}
            classNames={{
              tabList: 'bg-theme-elevated p-1 rounded-lg',
              cursor: 'bg-theme-hover',
              tab: 'text-theme-muted data-[selected=true]:text-theme-primary',
            }}
          >
            <Tab key="all" title="All" />
            <Tab key="earned" title="Earned" />
            <Tab key="spent" title="Spent" />
            <Tab key="pending" title="Pending" />
          </Tabs>

          {/* Transactions List */}
          <div className="mt-6 space-y-3">
            {isLoading ? (
              [1, 2, 3, 4, 5].map((i) => (
                <div key={i} className="animate-pulse p-4 rounded-lg bg-theme-elevated">
                  <div className="flex items-center gap-4">
                    <div className="w-10 h-10 rounded-full bg-theme-hover" />
                    <div className="flex-1">
                      <div className="h-4 bg-theme-hover rounded w-1/3 mb-2" />
                      <div className="h-3 bg-theme-hover rounded w-1/4" />
                    </div>
                  </div>
                </div>
              ))
            ) : filteredTransactions.length === 0 ? (
              <EmptyState
                icon={<Wallet className="w-12 h-12" />}
                title="No transactions"
                description="Your transaction history will appear here"
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
                      Load More
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
    </motion.div>
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
        <div className="h-6 w-12 mx-auto bg-theme-hover rounded animate-pulse" />
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
  const isCredit = transaction.type === 'credit';
  const otherPartyName = transaction.other_user?.name || transaction.other_party?.name;

  return (
    <article
      className="p-4 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors"
      aria-label={`${isCredit ? 'Received' : 'Sent'} ${transaction.amount} hours ${otherPartyName ? (isCredit ? 'from' : 'to') + ' ' + otherPartyName : ''}`}
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
                Pending
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
