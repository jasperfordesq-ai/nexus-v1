/**
 * Wallet Page - Time credit balance and transactions
 */

import { useState, useEffect } from 'react';
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
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { WalletBalance, Transaction } from '@/types/api';

type TransactionFilter = 'all' | 'earned' | 'spent' | 'pending';

export function WalletPage() {
  const [balance, setBalance] = useState<WalletBalance | null>(null);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [filter, setFilter] = useState<TransactionFilter>('all');

  useEffect(() => {
    loadWalletData();
  }, []);

  async function loadWalletData() {
    try {
      setIsLoading(true);
      const [balanceRes, transactionsRes] = await Promise.all([
        api.get<WalletBalance>('/v2/wallet/balance'),
        api.get<Transaction[]>('/v2/wallet/transactions?limit=50'),
      ]);

      if (balanceRes.success && balanceRes.data) {
        setBalance(balanceRes.data);
      }
      if (transactionsRes.success && transactionsRes.data) {
        setTransactions(transactionsRes.data);
      }
    } catch (error) {
      logError('Failed to load wallet data', error);
    } finally {
      setIsLoading(false);
    }
  }

  const filteredTransactions = transactions.filter((tx) => {
    if (filter === 'all') return true;
    if (filter === 'earned') return tx.type === 'credit';
    if (filter === 'spent') return tx.type === 'debit';
    if (filter === 'pending') return tx.status === 'pending';
    return true;
  });

  const stats = {
    earned: transactions
      .filter((tx) => tx.type === 'credit' && tx.status === 'completed')
      .reduce((sum, tx) => sum + tx.amount, 0),
    spent: transactions
      .filter((tx) => tx.type === 'debit' && tx.status === 'completed')
      .reduce((sum, tx) => sum + tx.amount, 0),
    pending: transactions
      .filter((tx) => tx.status === 'pending')
      .reduce((sum, tx) => sum + tx.amount, 0),
  };

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
        <h1 className="text-2xl font-bold text-white flex items-center gap-3">
          <Wallet className="w-7 h-7 text-amber-400" />
          Wallet
        </h1>
        <p className="text-white/60 mt-1">Track your time credits and transactions</p>
      </motion.div>

      {/* Balance Card */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-8 text-center relative overflow-hidden">
          {/* Background decoration */}
          <div className="absolute inset-0 opacity-10">
            <div className="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-full blur-3xl" />
            <div className="absolute bottom-0 left-0 w-64 h-64 bg-gradient-to-tr from-emerald-500 to-teal-500 rounded-full blur-3xl" />
          </div>

          <div className="relative z-10">
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-4">
              <Wallet className="w-8 h-8 text-indigo-400" />
            </div>

            <p className="text-white/60 text-sm mb-2">Your Balance</p>

            {isLoading ? (
              <div className="h-12 w-32 mx-auto bg-white/10 rounded animate-pulse" />
            ) : (
              <h1 className="text-5xl font-bold text-white mb-2">
                {balance?.balance ?? 0}
                <span className="text-2xl text-white/60 ml-2">hours</span>
              </h1>
            )}

            <p className="text-white/40 text-sm">
              {balance?.pending_in ? `+${balance.pending_in}h pending` : 'No pending transactions'}
            </p>
          </div>
        </GlassCard>
      </motion.div>

      {/* Stats Grid */}
      <motion.div variants={itemVariants} className="grid grid-cols-3 gap-4">
        <StatCard
          icon={<ArrowDownLeft className="w-5 h-5" />}
          label="Earned"
          value={`+${stats.earned}h`}
          color="emerald"
          isLoading={isLoading}
        />
        <StatCard
          icon={<ArrowUpRight className="w-5 h-5" />}
          label="Spent"
          value={`-${stats.spent}h`}
          color="rose"
          isLoading={isLoading}
        />
        <StatCard
          icon={<Clock className="w-5 h-5" />}
          label="Pending"
          value={`${stats.pending}h`}
          color="amber"
          isLoading={isLoading}
        />
      </motion.div>

      {/* Transactions */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6">
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-lg font-semibold text-white flex items-center gap-2">
              <TrendingUp className="w-5 h-5 text-indigo-400" />
              Transaction History
            </h2>

            <Button
              variant="flat"
              size="sm"
              className="bg-white/5 text-white/60"
              startContent={<Download className="w-4 h-4" />}
            >
              Export
            </Button>
          </div>

          {/* Filter Tabs */}
          <Tabs
            selectedKey={filter}
            onSelectionChange={(key) => setFilter(key as TransactionFilter)}
            classNames={{
              tabList: 'bg-white/5 p-1 rounded-lg',
              cursor: 'bg-white/10',
              tab: 'text-white/60 data-[selected=true]:text-white',
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
                <div key={i} className="animate-pulse p-4 rounded-lg bg-white/5">
                  <div className="flex items-center gap-4">
                    <div className="w-10 h-10 rounded-full bg-white/10" />
                    <div className="flex-1">
                      <div className="h-4 bg-white/10 rounded w-1/3 mb-2" />
                      <div className="h-3 bg-white/10 rounded w-1/4" />
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
              filteredTransactions.map((transaction) => (
                <TransactionCard key={transaction.id} transaction={transaction} />
              ))
            )}
          </div>
        </GlassCard>
      </motion.div>
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
      <div className="text-white/50 text-xs mb-1">{label}</div>
      {isLoading ? (
        <div className="h-6 w-12 mx-auto bg-white/10 rounded animate-pulse" />
      ) : (
        <div className="text-xl font-bold text-white">{value}</div>
      )}
    </GlassCard>
  );
}

interface TransactionCardProps {
  transaction: Transaction;
}

function TransactionCard({ transaction }: TransactionCardProps) {
  const isCredit = transaction.type === 'credit';

  return (
    <div className="p-4 rounded-lg bg-white/5 hover:bg-white/10 transition-colors">
      <div className="flex items-center gap-4">
        <div className={`
          p-2.5 rounded-full
          ${isCredit ? 'bg-emerald-500/20' : 'bg-rose-500/20'}
        `}>
          {isCredit ? (
            <ArrowDownLeft className="w-5 h-5 text-emerald-400" />
          ) : (
            <ArrowUpRight className="w-5 h-5 text-rose-400" />
          )}
        </div>

        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2">
            <h4 className="font-medium text-white truncate">{transaction.description}</h4>
            {transaction.status === 'pending' && (
              <span className="text-xs px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400">
                Pending
              </span>
            )}
          </div>
          <div className="flex items-center gap-3 text-sm text-white/50 mt-1">
            {transaction.other_user && (
              <span className="flex items-center gap-1">
                <User className="w-3 h-3" />
                {transaction.other_user.name}
              </span>
            )}
            <span className="flex items-center gap-1">
              <Calendar className="w-3 h-3" />
              {new Date(transaction.created_at).toLocaleDateString()}
            </span>
          </div>
        </div>

        <div className={`text-lg font-semibold ${isCredit ? 'text-emerald-400' : 'text-rose-400'}`}>
          {isCredit ? '+' : '-'}{transaction.amount}h
        </div>
      </div>
    </div>
  );
}

export default WalletPage;
