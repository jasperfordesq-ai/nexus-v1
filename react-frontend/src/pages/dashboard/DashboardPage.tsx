/**
 * Dashboard Page - Main user dashboard
 */

import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button } from '@heroui/react';
import {
  Clock,
  ListTodo,
  MessageSquare,
  Wallet,
  TrendingUp,
  Users,
  Calendar,
  Bell,
  ArrowRight,
  Plus,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { useAuth, useTenant, useFeature } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { WalletBalance, Listing } from '@/types/api';

interface DashboardStats {
  walletBalance: WalletBalance | null;
  recentListings: Listing[];
  activeListingsCount: number;
  unreadMessages: number;
  pendingTransactions: number;
}

export function DashboardPage() {
  const { user } = useAuth();
  const { branding } = useTenant();
  const hasGamification = useFeature('gamification');
  const hasEvents = useFeature('events');

  const [stats, setStats] = useState<DashboardStats>({
    walletBalance: null,
    recentListings: [],
    activeListingsCount: 0,
    unreadMessages: 0,
    pendingTransactions: 0,
  });
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    loadDashboardData();
  }, []);

  async function loadDashboardData() {
    try {
      const [walletRes, listingsRes] = await Promise.all([
        api.get<WalletBalance>('/v2/wallet/balance').catch(() => null),
        api.get<Listing[]>('/v2/listings?limit=5&sort=-created_at').catch(() => null),
      ]);

      // Get total count from meta if available, otherwise use data length
      const listingsCount = (listingsRes as { meta?: { total_items?: number } })?.meta?.total_items
        ?? listingsRes?.data?.length
        ?? 0;

      setStats({
        walletBalance: walletRes?.success ? walletRes.data ?? null : null,
        recentListings: listingsRes?.success ? listingsRes.data ?? [] : [],
        activeListingsCount: listingsCount,
        unreadMessages: 0, // TODO: fetch from messages endpoint
        pendingTransactions: 0,
      });
    } catch (error) {
      logError('Failed to load dashboard data', error);
    } finally {
      setIsLoading(false);
    }
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
        title="Dashboard"
        description="Your personal dashboard. View your balance, listings, and activity."
        noIndex
      />
      <motion.div
        variants={containerVariants}
        initial="hidden"
        animate="visible"
        className="space-y-6"
      >
        {/* Welcome Header */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h1 className="text-2xl font-bold text-white">
                Welcome back, {user?.first_name || user?.name?.split(' ')[0] || 'there'}!
              </h1>
              <p className="text-white/60 mt-1">
                Here's what's happening in your {branding.name} community
              </p>
            </div>
            <div className="flex gap-3">
              <Link to="/listings/create">
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  startContent={<Plus className="w-4 h-4" />}
                >
                  New Listing
                </Button>
              </Link>
            </div>
          </div>
        </GlassCard>
      </motion.div>

      {/* Stats Grid */}
      <motion.div
        variants={itemVariants}
        className="grid grid-cols-2 lg:grid-cols-4 gap-4"
      >
        <StatCard
          icon={<Wallet className="w-5 h-5" />}
          label="Balance"
          value={stats.walletBalance ? `${stats.walletBalance.balance}h` : '—'}
          color="indigo"
          href="/wallet"
          isLoading={isLoading}
        />
        <StatCard
          icon={<ListTodo className="w-5 h-5" />}
          label="Active Listings"
          value={stats.activeListingsCount.toString()}
          color="emerald"
          href="/listings"
          isLoading={isLoading}
        />
        <StatCard
          icon={<MessageSquare className="w-5 h-5" />}
          label="Messages"
          value={stats.unreadMessages > 0 ? stats.unreadMessages.toString() : '0'}
          color="amber"
          href="/messages"
          isLoading={isLoading}
        />
        <StatCard
          icon={<Clock className="w-5 h-5" />}
          label="Pending"
          value={stats.pendingTransactions.toString()}
          color="rose"
          href="/wallet"
          isLoading={isLoading}
        />
      </motion.div>

      {/* Main Content Grid */}
      <div className="grid lg:grid-cols-3 gap-6">
        {/* Recent Listings */}
        <motion.div variants={itemVariants} className="lg:col-span-2">
          <GlassCard className="p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-semibold text-white flex items-center gap-2">
                <ListTodo className="w-5 h-5 text-indigo-400" />
                Recent Listings
              </h2>
              <Link to="/listings" className="text-indigo-400 hover:text-indigo-300 text-sm flex items-center gap-1">
                View all <ArrowRight className="w-4 h-4" />
              </Link>
            </div>

            {isLoading ? (
              <div className="space-y-3">
                {[1, 2, 3].map((i) => (
                  <div key={i} className="animate-pulse">
                    <div className="h-16 bg-white/5 rounded-lg" />
                  </div>
                ))}
              </div>
            ) : stats.recentListings.length > 0 ? (
              <div className="space-y-3">
                {stats.recentListings.map((listing) => (
                  <Link
                    key={listing.id}
                    to={`/listings/${listing.id}`}
                    className="block p-4 rounded-lg bg-white/5 hover:bg-white/10 transition-colors"
                  >
                    <div className="flex items-start justify-between gap-4">
                      <div>
                        <h3 className="font-medium text-white">{listing.title}</h3>
                        <p className="text-sm text-white/60 line-clamp-1">{listing.description}</p>
                      </div>
                      <span className={`
                        text-xs px-2 py-1 rounded-full whitespace-nowrap
                        ${listing.type === 'offer' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'}
                      `}>
                        {listing.type === 'offer' ? 'Offering' : 'Requesting'}
                      </span>
                    </div>
                  </Link>
                ))}
              </div>
            ) : (
              <div className="text-center py-8 text-white/40">
                <ListTodo className="w-12 h-12 mx-auto mb-3 opacity-50" />
                <p>No recent listings</p>
                <Link to="/listings/create" className="text-indigo-400 hover:underline text-sm mt-2 inline-block">
                  Create your first listing
                </Link>
              </div>
            )}
          </GlassCard>
        </motion.div>

        {/* Quick Actions Sidebar */}
        <motion.div variants={itemVariants} className="space-y-6">
          {/* Quick Actions */}
          <GlassCard className="p-6">
            <h2 className="text-lg font-semibold text-white mb-4">Quick Actions</h2>
            <div className="space-y-2">
              <QuickActionLink to="/listings/create" icon={<Plus />} label="Create Listing" />
              <QuickActionLink to="/messages" icon={<MessageSquare />} label="Messages" />
              <QuickActionLink to="/wallet" icon={<Wallet />} label="View Wallet" />
              <QuickActionLink to="/members" icon={<Users />} label="Find Members" />
              {hasEvents && (
                <QuickActionLink to="/events" icon={<Calendar />} label="Browse Events" />
              )}
              <QuickActionLink to="/notifications" icon={<Bell />} label="Notifications" />
            </div>
          </GlassCard>

          {/* Gamification Preview */}
          {hasGamification && (
            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <TrendingUp className="w-5 h-5 text-amber-400" />
                Your Progress
              </h2>
              <div className="space-y-4">
                <div>
                  <div className="flex justify-between text-sm mb-1">
                    <span className="text-white/60">Level Progress</span>
                    <span className="text-white">75%</span>
                  </div>
                  <div className="h-2 bg-white/10 rounded-full overflow-hidden">
                    <div className="h-full w-3/4 bg-gradient-to-r from-amber-500 to-orange-500 rounded-full" />
                  </div>
                </div>
                <Link
                  to="/achievements"
                  className="block text-center text-indigo-400 hover:text-indigo-300 text-sm"
                >
                  View Achievements →
                </Link>
              </div>
            </GlassCard>
          )}
        </motion.div>
      </div>
      </motion.div>
    </>
  );
}

interface StatCardProps {
  icon: React.ReactNode;
  label: string;
  value: string;
  color: 'indigo' | 'emerald' | 'amber' | 'rose';
  href: string;
  isLoading?: boolean;
}

function StatCard({ icon, label, value, color, href, isLoading }: StatCardProps) {
  const colorClasses = {
    indigo: 'from-indigo-500/20 to-purple-500/20 text-indigo-400',
    emerald: 'from-emerald-500/20 to-teal-500/20 text-emerald-400',
    amber: 'from-amber-500/20 to-orange-500/20 text-amber-400',
    rose: 'from-rose-500/20 to-pink-500/20 text-rose-400',
  };

  return (
    <Link to={href}>
      <GlassCard className="p-4 hover:scale-[1.02] transition-transform">
        <div className={`inline-flex p-2 rounded-lg bg-gradient-to-br ${colorClasses[color]} mb-3`}>
          {icon}
        </div>
        <div className="text-white/60 text-sm">{label}</div>
        {isLoading ? (
          <div className="h-8 w-16 bg-white/10 rounded animate-pulse mt-1" />
        ) : (
          <div className="text-2xl font-bold text-white">{value}</div>
        )}
      </GlassCard>
    </Link>
  );
}

interface QuickActionLinkProps {
  to: string;
  icon: React.ReactNode;
  label: string;
}

function QuickActionLink({ to, icon, label }: QuickActionLinkProps) {
  return (
    <Link
      to={to}
      className="flex items-center gap-3 p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-colors text-white/80 hover:text-white"
    >
      <span className="text-indigo-400">{icon}</span>
      <span>{label}</span>
    </Link>
  );
}

export default DashboardPage;
