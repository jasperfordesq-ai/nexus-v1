/**
 * Federation Hub Page - Main landing page for federation features
 *
 * Sections (opted-out state):
 *   1. Hero with CTA to enable federation
 *   2. How It Works cards
 *
 * Sections (opted-in state):
 *   1. Stats row (partners, messages, transactions, status)
 *   2. Quick navigation links
 *   3. Partner communities preview
 *   4. Recent federation activity
 *   5. Opt-out footer link
 */

import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { Button, Chip, Avatar, Spinner } from '@heroui/react';
import {
  Globe,
  Users,
  MessageSquare,
  ArrowRightLeft,
  Search,
  Settings,
  Calendar,
  ListTodo,
  ArrowRight,
  AlertTriangle,
  RefreshCw,
  Activity,
  Network,
  Handshake,
  Shield,
  Zap,
  ChevronRight,
  UserPlus,
  Send,
  CheckCircle,
  XCircle,
  Clock,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs, type BreadcrumbItem } from '@/components/navigation';
import { useTenant, useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, formatRelativeTime } from '@/lib/helpers';
import type {
  FederationStatus,
  FederationPartner,
  FederationActivityItem,
} from '@/types/api';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface FederationDashboardData {
  status: FederationStatus | null;
  partners: FederationPartner[];
  activity: FederationActivityItem[];
  stats: {
    partners_count: number;
    messages_count: number;
    transactions_count: number;
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

// Breadcrumbs are constructed inside the component to use tenantPath()
// const breadcrumbs — see FederationHubPage body

const howItWorksCards = [
  {
    icon: Search,
    title: 'Discover Partners',
    description:
      'Browse partner timebanks in the federation network and see what their communities offer.',
    gradient: 'from-indigo-500 to-blue-500',
  },
  {
    icon: Users,
    title: 'Connect with Members',
    description:
      'Find members across partner communities with the skills you need, or offer yours to a wider audience.',
    gradient: 'from-purple-500 to-pink-500',
  },
  {
    icon: ArrowRightLeft,
    title: 'Exchange Across Communities',
    description:
      'Send messages, request services, and complete time credit exchanges with members from any partner timebank.',
    gradient: 'from-cyan-500 to-teal-500',
  },
];

const quickLinks = [
  {
    icon: Globe,
    title: 'Partner Communities',
    description: 'Browse all partner timebanks',
    href: '/federation/partners',
    gradient: 'from-indigo-500 to-blue-500',
  },
  {
    icon: Users,
    title: 'Federated Members',
    description: 'Search members across communities',
    href: '/federation/members',
    gradient: 'from-purple-500 to-pink-500',
  },
  {
    icon: MessageSquare,
    title: 'Federated Messages',
    description: 'Cross-community conversations',
    href: '/federation/messages',
    gradient: 'from-cyan-500 to-teal-500',
  },
  {
    icon: ListTodo,
    title: 'Federated Listings',
    description: 'Services from partner communities',
    href: '/federation/listings',
    gradient: 'from-amber-500 to-orange-500',
  },
  {
    icon: Calendar,
    title: 'Federated Events',
    description: 'Events across the network',
    href: '/federation/events',
    gradient: 'from-rose-500 to-pink-500',
  },
  {
    icon: Settings,
    title: 'Federation Settings',
    description: 'Manage your federation preferences',
    href: '/settings',
    gradient: 'from-gray-500 to-slate-500',
  },
];

const activityIcons: Record<FederationActivityItem['type'], typeof Activity> = {
  message_received: MessageSquare,
  message_sent: Send,
  transaction_received: ArrowRightLeft,
  transaction_sent: ArrowRightLeft,
  partnership_approved: Handshake,
  member_joined: UserPlus,
};

const federationLevelColors: Record<number, 'default' | 'primary' | 'secondary' | 'success' | 'warning'> = {
  1: 'default',
  2: 'primary',
  3: 'secondary',
  4: 'success',
};

// ─────────────────────────────────────────────────────────────────────────────
// Animation variants
// ─────────────────────────────────────────────────────────────────────────────

const containerVariants = {
  hidden: { opacity: 0 },
  visible: {
    opacity: 1,
    transition: { staggerChildren: 0.08 },
  },
};

const itemVariants = {
  hidden: { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0, transition: { duration: 0.4 } },
};

// ─────────────────────────────────────────────────────────────────────────────
// Hero Section (Not opted in)
// ─────────────────────────────────────────────────────────────────────────────

function FederationHero({ onOptIn, isOptingIn }: { onOptIn: () => void; isOptingIn: boolean }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 30 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.6 }}
      className="text-center"
    >
      {/* Hero banner */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500 p-8 md:p-12 lg:p-16 text-white mb-8">
        {/* Background decoration */}
        <div className="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
          <div className="absolute -top-24 -right-24 w-72 h-72 bg-white/10 rounded-full blur-3xl" />
          <div className="absolute -bottom-16 -left-16 w-56 h-56 bg-white/10 rounded-full blur-2xl" />
          <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-white/5 rounded-full blur-3xl" />
        </div>

        <div className="relative z-10 max-w-3xl mx-auto">
          <motion.div
            initial={{ scale: 0.8, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            transition={{ delay: 0.2, duration: 0.5 }}
            className="mx-auto mb-6 w-20 h-20 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center"
          >
            <Network className="w-10 h-10 text-white" aria-hidden="true" />
          </motion.div>

          <h1 className="text-3xl md:text-4xl lg:text-5xl font-bold mb-4">
            Connect Beyond Your Community
          </h1>
          <p className="text-lg md:text-xl text-white/80 mb-8 max-w-2xl mx-auto">
            Federation lets you discover members, listings, and events from partner
            timebanks in your network.
          </p>

          <Button
            size="lg"
            className="bg-white text-indigo-700 font-semibold hover:bg-white/90 px-8 py-6 text-lg"
            onPress={onOptIn}
            isLoading={isOptingIn}
            startContent={!isOptingIn ? <Globe className="w-5 h-5" aria-hidden="true" /> : undefined}
          >
            Enable Federation
          </Button>
        </div>
      </div>

      {/* How It Works */}
      <div className="mb-8">
        <h2 className="text-2xl font-bold text-foreground mb-6">How It Works</h2>
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="grid grid-cols-1 md:grid-cols-3 gap-6"
        >
          {howItWorksCards.map((card, index) => {
            const Icon = card.icon;
            return (
              <motion.div key={card.title} variants={itemVariants}>
                <GlassCard className="p-6 h-full text-center">
                  <div className="relative mx-auto mb-4">
                    <div
                      className={`w-14 h-14 rounded-xl bg-gradient-to-br ${card.gradient} flex items-center justify-center mx-auto`}
                    >
                      <Icon className="w-7 h-7 text-white" aria-hidden="true" />
                    </div>
                    <span
                      className="absolute -top-2 -right-2 w-7 h-7 rounded-full bg-foreground text-background text-xs font-bold flex items-center justify-center"
                      aria-hidden="true"
                    >
                      {index + 1}
                    </span>
                  </div>
                  <h3 className="text-lg font-semibold text-foreground mb-2">{card.title}</h3>
                  <p className="text-sm text-default-500">{card.description}</p>
                </GlassCard>
              </motion.div>
            );
          })}
        </motion.div>
      </div>

      {/* Feature highlights */}
      <GlassCard className="p-6">
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-6 text-center">
          <div>
            <Shield className="w-8 h-8 text-emerald-500 mx-auto mb-2" aria-hidden="true" />
            <h4 className="font-semibold text-foreground mb-1">Privacy First</h4>
            <p className="text-sm text-default-500">
              You control exactly what information is shared with other communities.
            </p>
          </div>
          <div>
            <Zap className="w-8 h-8 text-amber-500 mx-auto mb-2" aria-hidden="true" />
            <h4 className="font-semibold text-foreground mb-1">Instant Setup</h4>
            <p className="text-sm text-default-500">
              Enable federation with one click. Customize your settings any time.
            </p>
          </div>
          <div>
            <Handshake className="w-8 h-8 text-indigo-500 mx-auto mb-2" aria-hidden="true" />
            <h4 className="font-semibold text-foreground mb-1">Trusted Network</h4>
            <p className="text-sm text-default-500">
              Only verified partner communities with broker oversight can participate.
            </p>
          </div>
        </div>
      </GlassCard>
    </motion.div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Stats Row
// ─────────────────────────────────────────────────────────────────────────────

function StatsRow({ stats, enabled }: { stats: FederationDashboardData['stats']; enabled: boolean }) {
  const statCards = [
    {
      label: 'Partner Communities',
      value: stats.partners_count,
      icon: Globe,
      gradient: 'from-indigo-500 to-blue-500',
    },
    {
      label: 'Federated Messages',
      value: stats.messages_count,
      icon: MessageSquare,
      gradient: 'from-purple-500 to-pink-500',
    },
    {
      label: 'Cross-Community Exchanges',
      value: stats.transactions_count,
      icon: ArrowRightLeft,
      gradient: 'from-cyan-500 to-teal-500',
    },
    {
      label: 'Federation Status',
      value: null,
      icon: Activity,
      gradient: 'from-emerald-500 to-green-500',
      chipContent: enabled ? 'Active' : 'Inactive',
      chipColor: enabled ? ('success' as const) : ('default' as const),
    },
  ];

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8"
    >
      {statCards.map((card) => {
        const Icon = card.icon;
        return (
          <motion.div key={card.label} variants={itemVariants}>
            <GlassCard className="p-5">
              <div className="flex items-start justify-between mb-3">
                <div
                  className={`w-10 h-10 rounded-lg bg-gradient-to-br ${card.gradient} flex items-center justify-center flex-shrink-0`}
                >
                  <Icon className="w-5 h-5 text-white" aria-hidden="true" />
                </div>
              </div>
              {card.value !== null ? (
                <p className="text-2xl font-bold text-foreground">{card.value}</p>
              ) : (
                <Chip
                  size="sm"
                  color={card.chipColor}
                  variant="flat"
                  startContent={
                    card.chipColor === 'success' ? (
                      <CheckCircle className="w-3 h-3" aria-hidden="true" />
                    ) : (
                      <XCircle className="w-3 h-3" aria-hidden="true" />
                    )
                  }
                >
                  {card.chipContent}
                </Chip>
              )}
              <p className="text-xs text-default-500 mt-1">{card.label}</p>
            </GlassCard>
          </motion.div>
        );
      })}
    </motion.div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Quick Links
// ─────────────────────────────────────────────────────────────────────────────

function QuickLinksSection() {
  const { tenantPath } = useTenant();
  return (
    <div className="mb-8">
      <h2 className="text-xl font-bold text-foreground mb-4">Explore the Network</h2>
      <motion.div
        variants={containerVariants}
        initial="hidden"
        animate="visible"
        className="grid grid-cols-2 md:grid-cols-3 gap-4"
      >
        {quickLinks.map((link) => {
          const Icon = link.icon;
          return (
            <motion.div key={link.href} variants={itemVariants}>
              <Link to={tenantPath(link.href)} className="block group">
                <GlassCard className="p-4 h-full transition-all duration-200 group-hover:shadow-lg group-hover:scale-[1.02]">
                  <div className="flex items-start gap-3">
                    <div
                      className={`w-10 h-10 rounded-lg bg-gradient-to-br ${link.gradient} flex items-center justify-center flex-shrink-0`}
                    >
                      <Icon className="w-5 h-5 text-white" aria-hidden="true" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <h3 className="font-semibold text-foreground text-sm leading-tight mb-1 group-hover:text-primary transition-colors">
                        {link.title}
                      </h3>
                      <p className="text-xs text-default-500 line-clamp-2">{link.description}</p>
                    </div>
                  </div>
                </GlassCard>
              </Link>
            </motion.div>
          );
        })}
      </motion.div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Partner Communities Section
// ─────────────────────────────────────────────────────────────────────────────

function PartnerCommunitiesSection({ partners }: { partners: FederationPartner[] }) {
  const { tenantPath } = useTenant();
  if (partners.length === 0) {
    return (
      <div className="mb-8">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-xl font-bold text-foreground">Partner Communities</h2>
        </div>
        <GlassCard className="p-8 text-center">
          <Globe className="w-12 h-12 text-default-300 mx-auto mb-3" aria-hidden="true" />
          <p className="text-default-500 mb-1">No partner communities yet</p>
          <p className="text-sm text-default-400">
            Your community will appear here once partnerships are established.
          </p>
        </GlassCard>
      </div>
    );
  }

  const displayPartners = partners.slice(0, 4);

  return (
    <div className="mb-8">
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-xl font-bold text-foreground">Partner Communities</h2>
        {partners.length > 4 && (
          <Link
            to={tenantPath("/federation/partners")}
            className="text-sm text-primary hover:text-primary-600 flex items-center gap-1 transition-colors"
          >
            View All ({partners.length})
            <ChevronRight className="w-4 h-4" aria-hidden="true" />
          </Link>
        )}
      </div>
      <motion.div
        variants={containerVariants}
        initial="hidden"
        animate="visible"
        className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"
      >
        {displayPartners.map((partner) => (
          <motion.div key={partner.id} variants={itemVariants}>
            <GlassCard className="p-5 h-full flex flex-col">
              <div className="flex items-center gap-3 mb-3">
                <Avatar
                  src={partner.logo ? resolveAvatarUrl(partner.logo) : undefined}
                  name={partner.name}
                  size="md"
                  className="flex-shrink-0"
                />
                <div className="min-w-0 flex-1">
                  <h3 className="font-semibold text-foreground text-sm truncate">{partner.name}</h3>
                  {partner.location && (
                    <p className="text-xs text-default-500 truncate">{partner.location}</p>
                  )}
                </div>
              </div>

              {partner.tagline && (
                <p className="text-xs text-default-500 mb-3 line-clamp-2">{partner.tagline}</p>
              )}

              <div className="flex items-center gap-2 mb-3 mt-auto">
                <Chip size="sm" variant="flat" color={federationLevelColors[partner.federation_level] || 'default'}>
                  {partner.federation_level_name}
                </Chip>
                <span className="text-xs text-default-400">
                  {partner.member_count} {partner.member_count === 1 ? 'member' : 'members'}
                </span>
              </div>

              <Button
                as={Link}
                to={tenantPath(`/federation/partners/${partner.id}`)}
                size="sm"
                variant="flat"
                color="primary"
                className="w-full"
                endContent={<ArrowRight className="w-3.5 h-3.5" aria-hidden="true" />}
              >
                View Community
              </Button>
            </GlassCard>
          </motion.div>
        ))}
      </motion.div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Recent Activity Section
// ─────────────────────────────────────────────────────────────────────────────

function RecentActivitySection({ activity }: { activity: FederationActivityItem[] }) {
  if (activity.length === 0) {
    return (
      <div className="mb-8">
        <h2 className="text-xl font-bold text-foreground mb-4">Recent Activity</h2>
        <GlassCard className="p-8 text-center">
          <Activity className="w-12 h-12 text-default-300 mx-auto mb-3" aria-hidden="true" />
          <p className="text-default-500 mb-1">No federation activity yet</p>
          <p className="text-sm text-default-400">
            Your cross-community interactions will appear here.
          </p>
        </GlassCard>
      </div>
    );
  }

  return (
    <div className="mb-8">
      <h2 className="text-xl font-bold text-foreground mb-4">Recent Activity</h2>
      <GlassCard className="divide-y divide-default-200">
        <AnimatePresence>
          {activity.slice(0, 5).map((item, index) => {
            const Icon = activityIcons[item.type] || Activity;
            return (
              <motion.div
                key={item.id}
                initial={{ opacity: 0, x: -10 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: index * 0.05 }}
                className="flex items-start gap-3 p-4"
              >
                <div className="w-9 h-9 rounded-lg bg-default-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                  <Icon className="w-4.5 h-4.5 text-default-600" aria-hidden="true" />
                </div>
                <div className="min-w-0 flex-1">
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                      <p className="text-sm font-medium text-foreground">{item.title}</p>
                      <p className="text-xs text-default-500 mt-0.5 line-clamp-1">
                        {item.description}
                      </p>
                    </div>
                    <span className="text-xs text-default-400 whitespace-nowrap flex-shrink-0 flex items-center gap-1">
                      <Clock className="w-3 h-3" aria-hidden="true" />
                      {formatRelativeTime(item.created_at)}
                    </span>
                  </div>
                  {item.actor && (
                    <div className="flex items-center gap-1.5 mt-1.5">
                      <Avatar
                        src={resolveAvatarUrl(item.actor.avatar)}
                        name={item.actor.name}
                        size="sm"
                        className="w-5 h-5"
                      />
                      <span className="text-xs text-default-500">
                        {item.actor.name}
                        {item.actor.tenant_name && (
                          <span className="text-default-400"> from {item.actor.tenant_name}</span>
                        )}
                      </span>
                    </div>
                  )}
                </div>
              </motion.div>
            );
          })}
        </AnimatePresence>
      </GlassCard>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Page Component
// ─────────────────────────────────────────────────────────────────────────────

export default function FederationHubPage() {
  usePageTitle('Federation');

  const { tenant, tenantPath } = useTenant();
  const toast = useToast();

  const breadcrumbs: BreadcrumbItem[] = [
    { label: 'Dashboard', href: tenantPath('/dashboard') },
    { label: 'Federation' },
  ];

  const [isLoading, setIsLoading] = useState(true);
  const [isOptingIn, setIsOptingIn] = useState(false);
  const [isOptingOut, setIsOptingOut] = useState(false);
  const [showOptOutConfirm, setShowOptOutConfirm] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [data, setData] = useState<FederationDashboardData>({
    status: null,
    partners: [],
    activity: [],
    stats: { partners_count: 0, messages_count: 0, transactions_count: 0 },
  });

  const isOptedIn = data.status?.enabled ?? false;

  // ─── Load federation data ───

  const loadData = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      // Fetch status first to determine if opted in
      const statusRes = await api.get<FederationStatus>('/v2/federation/status');

      if (!statusRes.success || !statusRes.data) {
        setError(statusRes.error || 'Failed to load federation status.');
        setIsLoading(false);
        return;
      }

      const status = statusRes.data;
      const newData: FederationDashboardData = {
        status,
        partners: [],
        activity: [],
        stats: {
          partners_count: status.partnerships_count || 0,
          messages_count: 0,
          transactions_count: 0,
        },
      };

      // If opted in, load additional data in parallel
      if (status.enabled) {
        const [partnersRes, activityRes] = await Promise.all([
          api.get<{ data: FederationPartner[]; meta?: { total?: number } }>('/v2/federation/partners'),
          api.get<{ data: FederationActivityItem[] }>('/v2/federation/activity'),
        ]);

        if (partnersRes.success && partnersRes.data) {
          const partnersData = Array.isArray(partnersRes.data)
            ? partnersRes.data
            : [];
          newData.partners = partnersData;
          newData.stats.partners_count = partnersData.length;
        }

        if (activityRes.success && activityRes.data) {
          const activityData = Array.isArray(activityRes.data)
            ? activityRes.data
            : [];
          newData.activity = activityData;

          // Derive message and transaction counts from activity
          newData.stats.messages_count = activityData.filter(
            (a) => a.type === 'message_received' || a.type === 'message_sent'
          ).length;
          newData.stats.transactions_count = activityData.filter(
            (a) => a.type === 'transaction_received' || a.type === 'transaction_sent'
          ).length;
        }
      }

      setData(newData);
    } catch (err) {
      logError('FederationHubPage: Failed to load data', err);
      setError('Something went wrong. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // ─── Opt in ───

  const handleOptIn = useCallback(async () => {
    setIsOptingIn(true);
    try {
      const res = await api.post<{ success: boolean }>('/v2/federation/opt-in');
      if (res.success) {
        toast.success('Federation Enabled', 'You are now part of the federation network.');
        await loadData();
      } else {
        toast.error('Could not enable federation', res.error || 'Please try again.');
      }
    } catch (err) {
      logError('FederationHubPage: Opt-in failed', err);
      toast.error('Error', 'Something went wrong while enabling federation.');
    } finally {
      setIsOptingIn(false);
    }
  }, [toast, loadData]);

  // ─── Opt out ───

  const handleOptOut = useCallback(async () => {
    setIsOptingOut(true);
    try {
      const res = await api.post<{ success: boolean }>('/v2/federation/opt-out');
      if (res.success) {
        toast.info('Federation Disabled', 'You have left the federation network.');
        setShowOptOutConfirm(false);
        await loadData();
      } else {
        toast.error('Could not disable federation', res.error || 'Please try again.');
      }
    } catch (err) {
      logError('FederationHubPage: Opt-out failed', err);
      toast.error('Error', 'Something went wrong while disabling federation.');
    } finally {
      setIsOptingOut(false);
    }
  }, [toast, loadData]);

  // ─── Loading state ───

  if (isLoading) {
    return (
      <div className="max-w-6xl mx-auto px-4 py-8">
        <Breadcrumbs items={breadcrumbs} />
        <div className="flex flex-col items-center justify-center py-24">
          <Spinner size="lg" color="primary" />
          <p className="text-default-500 mt-4">Loading federation data...</p>
        </div>
      </div>
    );
  }

  // ─── Error state ───

  if (error) {
    return (
      <div className="max-w-6xl mx-auto px-4 py-8">
        <Breadcrumbs items={breadcrumbs} />
        <GlassCard className="p-12 text-center mt-6">
          <AlertTriangle className="w-12 h-12 text-warning mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-xl font-semibold text-foreground mb-2">Unable to Load</h2>
          <p className="text-default-500 mb-6">{error}</p>
          <Button
            color="primary"
            variant="flat"
            onPress={loadData}
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
          >
            Try Again
          </Button>
        </GlassCard>
      </div>
    );
  }

  // ─── Tenant federation not enabled ───

  if (data.status && !data.status.tenant_federation_enabled) {
    return (
      <div className="max-w-6xl mx-auto px-4 py-8">
        <Breadcrumbs items={breadcrumbs} />
        <GlassCard className="p-12 text-center mt-6">
          <Network className="w-16 h-16 text-default-300 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-2xl font-bold text-foreground mb-2">Federation Not Available</h2>
          <p className="text-default-500 mb-2">
            Federation is not currently enabled for{' '}
            <span className="font-medium">{tenant?.name || 'your community'}</span>.
          </p>
          <p className="text-sm text-default-400">
            Contact your community administrator to learn more about joining the federation network.
          </p>
        </GlassCard>
      </div>
    );
  }

  // ─── Render ───

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">
      <Breadcrumbs items={breadcrumbs} />

      {/* Page header */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.3 }}
        className="mt-4 mb-8"
      >
        <div className="flex items-center gap-3 mb-1">
          <Network className="w-7 h-7 text-primary" aria-hidden="true" />
          <h1 className="text-2xl md:text-3xl font-bold text-foreground">Federation Hub</h1>
        </div>
        <p className="text-default-500 ml-10">
          {isOptedIn
            ? 'Manage your cross-community connections and explore the federation network.'
            : 'Join the federation network to connect with partner communities.'}
        </p>
      </motion.div>

      {/* Not opted in: show hero */}
      {!isOptedIn && <FederationHero onOptIn={handleOptIn} isOptingIn={isOptingIn} />}

      {/* Opted in: show dashboard */}
      {isOptedIn && (
        <>
          <StatsRow stats={data.stats} enabled={isOptedIn} />
          <QuickLinksSection />
          <PartnerCommunitiesSection partners={data.partners} />
          <RecentActivitySection activity={data.activity} />

          {/* Opt-out footer */}
          <div className="border-t border-default-200 pt-6 mt-4 text-center">
            {!showOptOutConfirm ? (
              <Button
                variant="light"
                color="default"
                size="sm"
                onPress={() => setShowOptOutConfirm(true)}
                className="text-default-400 hover:text-danger"
              >
                Disable Federation
              </Button>
            ) : (
              <motion.div
                initial={{ opacity: 0, y: 5 }}
                animate={{ opacity: 1, y: 0 }}
                className="inline-flex flex-col items-center gap-3"
              >
                <p className="text-sm text-default-500">
                  Are you sure? This will remove your profile from the federation network.
                </p>
                <div className="flex items-center gap-3">
                  <Button
                    variant="flat"
                    color="danger"
                    size="sm"
                    onPress={handleOptOut}
                    isLoading={isOptingOut}
                  >
                    Yes, Disable
                  </Button>
                  <Button
                    variant="flat"
                    size="sm"
                    onPress={() => setShowOptOutConfirm(false)}
                  >
                    Cancel
                  </Button>
                </div>
              </motion.div>
            )}
          </div>
        </>
      )}
    </div>
  );
}
