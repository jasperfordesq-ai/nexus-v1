// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Achievements Page - Badge showcase, challenges, collections, XP shop, daily rewards
 */

import { useState, useEffect, useCallback } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Progress,
  Chip,
  Select,
  SelectItem,
  Tabs,
  Tab,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Checkbox,
  Spinner,
} from '@heroui/react';
import {
  Trophy,
  Medal,
  Star,
  Sparkles,
  RefreshCw,
  AlertTriangle,
  Lock,
  Filter,
  Target,
  Clock,
  Gift,
  ShoppingBag,
  CheckCircle,
  Crown,
  Gem,
  Layers,
  Zap,
  Package,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts/ToastContext';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface GamificationProfile {
  user: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
  xp: number;
  level: number;
  level_progress: {
    current_xp: number;
    xp_for_current_level: number;
    xp_for_next_level: number;
    progress_percentage: number;
  };
  badges_count: number;
  showcased_badges: BadgeEntry[];
  is_own_profile: boolean;
}

interface BadgeEntry {
  badge_key: string;
  name: string;
  description: string;
  icon: string;
  type: string;
  created_at: string;
  is_showcased: boolean;
  earned?: boolean;
  earned_at?: string | null;
}

interface Challenge {
  id: number;
  title: string;
  description: string;
  reward_xp: number;
  progress: number;
  target: number;
  deadline: string | null;
  status: 'active' | 'completed' | 'claimed' | 'expired';
  type: string;
}

interface BadgeCollection {
  id: number;
  name: string;
  description: string;
  badges: {
    badge_key: string;
    name: string;
    icon: string;
    earned: boolean;
  }[];
  earned_count: number;
  total_count: number;
  reward_xp: number;
  completed: boolean;
}

interface ShopItem {
  id: number;
  name: string;
  description: string;
  cost_xp: number;
  type: string;
  image_url: string | null;
  available: boolean;
  owned: boolean;
  stock: number | null;
}

interface DailyRewardStatus {
  claimed_today: boolean;
  current_streak: number;
  reward_xp: number;
  next_reward_xp: number;
  next_claim_at: string | null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Daily Reward Widget
// ─────────────────────────────────────────────────────────────────────────────

function DailyRewardWidget() {
  const toast = useToast();
  const [status, setStatus] = useState<DailyRewardStatus | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isClaiming, setIsClaiming] = useState(false);
  const [justClaimed, setJustClaimed] = useState(false);

  const loadStatus = useCallback(async () => {
    try {
      setIsLoading(true);
      const res = await api.get<DailyRewardStatus>('/v2/gamification/daily-reward');
      if (res.success && res.data) {
        setStatus(res.data as unknown as DailyRewardStatus);
      }
    } catch (err) {
      logError('Failed to load daily reward status', err);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadStatus();
  }, [loadStatus]);

  const claimReward = async () => {
    try {
      setIsClaiming(true);
      const res = await api.post<{ xp_earned: number; new_streak: number }>('/v2/gamification/daily-reward');
      if (res.success) {
        setJustClaimed(true);
        toast.success('Daily Reward Claimed!', `You earned ${status?.reward_xp ?? 0} XP!`);
        // Update status
        setStatus((prev) =>
          prev
            ? {
                ...prev,
                claimed_today: true,
                current_streak: (res.data as unknown as { new_streak: number })?.new_streak ?? prev.current_streak + 1,
              }
            : prev
        );
        // Reset animation after 2s
        setTimeout(() => setJustClaimed(false), 2000);
      } else {
        toast.error('Claim Failed', res.error ?? 'Could not claim daily reward.');
      }
    } catch (err) {
      logError('Failed to claim daily reward', err);
      toast.error('Claim Failed', 'Something went wrong. Please try again.');
    } finally {
      setIsClaiming(false);
    }
  };

  if (isLoading) {
    return (
      <GlassCard className="p-4 animate-pulse">
        <div className="flex items-center gap-4">
          <div className="w-12 h-12 rounded-full bg-theme-hover" />
          <div className="flex-1">
            <div className="h-4 bg-theme-hover rounded w-1/3 mb-2" />
            <div className="h-3 bg-theme-hover rounded w-1/2" />
          </div>
          <div className="h-10 w-28 bg-theme-hover rounded-lg" />
        </div>
      </GlassCard>
    );
  }

  if (!status) return null;

  return (
    <AnimatePresence>
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.4, ease: 'easeOut' }}
      >
        <GlassCard className="p-4 border-l-4 border-amber-400 overflow-hidden relative">
          {/* Sparkle background effect when claiming */}
          {justClaimed && (
            <motion.div
              className="absolute inset-0 pointer-events-none"
              initial={{ opacity: 0 }}
              animate={{ opacity: [0, 1, 0] }}
              transition={{ duration: 1.5 }}
            >
              <div className="absolute inset-0 bg-gradient-to-r from-amber-500/10 via-yellow-400/20 to-amber-500/10" />
              {[...Array(6)].map((_, i) => (
                <motion.div
                  key={i}
                  className="absolute w-2 h-2 bg-amber-400 rounded-full"
                  initial={{
                    x: '50%',
                    y: '50%',
                    scale: 0,
                    opacity: 1,
                  }}
                  animate={{
                    x: `${20 + Math.random() * 60}%`,
                    y: `${10 + Math.random() * 80}%`,
                    scale: [0, 1.5, 0],
                    opacity: [1, 1, 0],
                  }}
                  transition={{
                    duration: 1,
                    delay: i * 0.1,
                    ease: 'easeOut',
                  }}
                />
              ))}
            </motion.div>
          )}

          <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4 relative z-10">
            <motion.div
              className="w-12 h-12 rounded-full bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center flex-shrink-0"
              animate={
                justClaimed
                  ? { scale: [1, 1.3, 1], rotate: [0, 10, -10, 0] }
                  : {}
              }
              transition={{ duration: 0.6 }}
            >
              <Gift className="w-6 h-6 text-white" aria-hidden="true" />
            </motion.div>

            <div className="flex-1 min-w-0">
              <h3 className="font-semibold text-theme-primary">Daily Reward</h3>
              {status.claimed_today ? (
                <p className="text-sm text-theme-muted">
                  Come back tomorrow for <strong className="text-amber-400">{status.next_reward_xp} XP</strong>!
                  {status.current_streak > 1 && (
                    <span className="ml-2">
                      <Zap className="w-3 h-3 inline text-amber-400" aria-hidden="true" /> {status.current_streak} day streak
                    </span>
                  )}
                </p>
              ) : (
                <p className="text-sm text-theme-muted">
                  Claim <strong className="text-amber-400">{status.reward_xp} XP</strong> today!
                  {status.current_streak > 0 && (
                    <span className="ml-2">
                      <Zap className="w-3 h-3 inline text-amber-400" aria-hidden="true" /> {status.current_streak} day streak
                    </span>
                  )}
                </p>
              )}
            </div>

            {!status.claimed_today ? (
              <motion.div whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }}>
                <Button
                  className="bg-gradient-to-r from-amber-400 to-orange-500 text-white font-semibold shadow-lg"
                  startContent={
                    isClaiming ? (
                      <Spinner size="sm" color="white" />
                    ) : (
                      <Sparkles className="w-4 h-4" aria-hidden="true" />
                    )
                  }
                  onPress={claimReward}
                  isDisabled={isClaiming}
                >
                  {isClaiming ? 'Claiming...' : 'Claim Reward'}
                </Button>
              </motion.div>
            ) : (
              <Chip
                color="success"
                variant="flat"
                startContent={<CheckCircle className="w-3 h-3" aria-hidden="true" />}
              >
                Claimed
              </Chip>
            )}
          </div>
        </GlassCard>
      </motion.div>
    </AnimatePresence>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Challenges Tab Content
// ─────────────────────────────────────────────────────────────────────────────

function ChallengesTab() {
  const toast = useToast();
  const [challenges, setChallenges] = useState<Challenge[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [claimingId, setClaimingId] = useState<number | null>(null);

  const loadChallenges = useCallback(async () => {
    try {
      setIsLoading(true);
      const res = await api.get<Challenge[]>('/v2/gamification/challenges');
      if (res.success && res.data) {
        setChallenges(Array.isArray(res.data) ? res.data : []);
      }
    } catch (err) {
      logError('Failed to load challenges', err);
      toast.error('Load Failed', 'Could not load challenges.');
    } finally {
      setIsLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadChallenges();
  }, [loadChallenges]);

  const claimReward = async (challengeId: number) => {
    try {
      setClaimingId(challengeId);
      const res = await api.post('/v2/gamification/challenges/' + challengeId + '/claim');
      if (res.success) {
        toast.success('Reward Claimed!', 'Challenge reward has been added to your XP.');
        setChallenges((prev) =>
          prev.map((c) => (c.id === challengeId ? { ...c, status: 'claimed' as const } : c))
        );
      } else {
        toast.error('Claim Failed', res.error ?? 'Could not claim reward.');
      }
    } catch (err) {
      logError('Failed to claim challenge reward', err);
      toast.error('Claim Failed', 'Something went wrong.');
    } finally {
      setClaimingId(null);
    }
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.06 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 15 },
    visible: { opacity: 1, y: 0 },
  };

  if (isLoading) {
    return (
      <div className="space-y-4 mt-4">
        {[1, 2, 3].map((i) => (
          <GlassCard key={i} className="p-5 animate-pulse">
            <div className="flex items-start gap-4">
              <div className="w-10 h-10 rounded-lg bg-theme-hover" />
              <div className="flex-1">
                <div className="h-4 bg-theme-hover rounded w-1/3 mb-2" />
                <div className="h-3 bg-theme-hover rounded w-2/3 mb-3" />
                <div className="h-2 bg-theme-hover rounded w-full mb-2" />
                <div className="h-3 bg-theme-hover rounded w-1/4" />
              </div>
            </div>
          </GlassCard>
        ))}
      </div>
    );
  }

  if (challenges.length === 0) {
    return (
      <div className="mt-4">
        <EmptyState
          icon={<Target className="w-12 h-12" aria-hidden="true" />}
          title="No active challenges"
          description="Check back soon for new challenges to complete!"
        />
      </div>
    );
  }

  const activeChallenges = challenges.filter((c) => c.status === 'active');
  const completedChallenges = challenges.filter((c) => c.status === 'completed' || c.status === 'claimed');

  return (
    <div className="space-y-6 mt-4">
      {/* Active Challenges */}
      {activeChallenges.length > 0 && (
        <div>
          <h3 className="text-lg font-semibold text-theme-primary mb-3 flex items-center gap-2">
            <Target className="w-5 h-5 text-indigo-400" aria-hidden="true" />
            Active Challenges
          </h3>
          <motion.div
            variants={containerVariants}
            initial="hidden"
            animate="visible"
            className="space-y-3"
          >
            {activeChallenges.map((challenge) => {
              const progressPct = challenge.target > 0
                ? Math.min(100, Math.round((challenge.progress / challenge.target) * 100))
                : 0;

              return (
                <motion.div key={challenge.id} variants={itemVariants}>
                  <GlassCard className="p-5" hoverable>
                    <div className="flex items-start gap-4">
                      <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center flex-shrink-0">
                        <Target className="w-5 h-5 text-indigo-400" aria-hidden="true" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-start justify-between gap-2 mb-1">
                          <h4 className="font-semibold text-theme-primary">{challenge.title}</h4>
                          <Chip size="sm" color="warning" variant="flat">
                            <Gem className="w-3 h-3 inline mr-1" aria-hidden="true" />
                            {challenge.reward_xp} XP
                          </Chip>
                        </div>
                        <p className="text-sm text-theme-muted mb-3">{challenge.description}</p>
                        <Progress
                          value={progressPct}
                          className="mb-2"
                          classNames={{
                            indicator: 'bg-gradient-to-r from-indigo-500 to-purple-600',
                            track: 'bg-theme-hover',
                          }}
                          size="sm"
                          aria-label={`Challenge progress: ${progressPct}%`}
                        />
                        <div className="flex items-center justify-between text-xs text-theme-subtle">
                          <span>{challenge.progress} / {challenge.target}</span>
                          {challenge.deadline && (
                            <span className="flex items-center gap-1">
                              <Clock className="w-3 h-3" aria-hidden="true" />
                              {new Date(challenge.deadline).toLocaleDateString()}
                            </span>
                          )}
                        </div>
                      </div>
                    </div>
                  </GlassCard>
                </motion.div>
              );
            })}
          </motion.div>
        </div>
      )}

      {/* Completed Challenges */}
      {completedChallenges.length > 0 && (
        <div>
          <h3 className="text-lg font-semibold text-theme-primary mb-3 flex items-center gap-2">
            <CheckCircle className="w-5 h-5 text-emerald-400" aria-hidden="true" />
            Completed
          </h3>
          <motion.div
            variants={containerVariants}
            initial="hidden"
            animate="visible"
            className="space-y-3"
          >
            {completedChallenges.map((challenge) => (
              <motion.div key={challenge.id} variants={itemVariants}>
                <GlassCard className="p-5">
                  <div className="flex items-start gap-4">
                    <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-emerald-500/20 to-green-500/20 flex items-center justify-center flex-shrink-0">
                      <CheckCircle className="w-5 h-5 text-emerald-400" aria-hidden="true" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-start justify-between gap-2 mb-1">
                        <h4 className="font-semibold text-theme-primary">{challenge.title}</h4>
                        {challenge.status === 'completed' ? (
                          <Button
                            size="sm"
                            className="bg-gradient-to-r from-emerald-500 to-green-600 text-white"
                            startContent={
                              claimingId === challenge.id ? (
                                <Spinner size="sm" color="white" />
                              ) : (
                                <Gift className="w-3 h-3" aria-hidden="true" />
                              )
                            }
                            onPress={() => claimReward(challenge.id)}
                            isDisabled={claimingId === challenge.id}
                          >
                            Claim {challenge.reward_xp} XP
                          </Button>
                        ) : (
                          <Chip size="sm" color="success" variant="flat">
                            <CheckCircle className="w-3 h-3 inline mr-1" aria-hidden="true" />
                            Claimed
                          </Chip>
                        )}
                      </div>
                      <p className="text-sm text-theme-muted">{challenge.description}</p>
                    </div>
                  </div>
                </GlassCard>
              </motion.div>
            ))}
          </motion.div>
        </div>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Collections Tab Content
// ─────────────────────────────────────────────────────────────────────────────

function CollectionsTab() {
  const toast = useToast();
  const [collections, setCollections] = useState<BadgeCollection[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const loadCollections = useCallback(async () => {
    try {
      setIsLoading(true);
      const res = await api.get<BadgeCollection[]>('/v2/gamification/collections');
      if (res.success && res.data) {
        setCollections(Array.isArray(res.data) ? res.data : []);
      }
    } catch (err) {
      logError('Failed to load collections', err);
      toast.error('Load Failed', 'Could not load collections.');
    } finally {
      setIsLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadCollections();
  }, [loadCollections]);

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.06 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 15 },
    visible: { opacity: 1, y: 0 },
  };

  if (isLoading) {
    return (
      <div className="space-y-4 mt-4">
        {[1, 2, 3].map((i) => (
          <GlassCard key={i} className="p-5 animate-pulse">
            <div className="h-4 bg-theme-hover rounded w-1/3 mb-2" />
            <div className="h-3 bg-theme-hover rounded w-2/3 mb-4" />
            <div className="h-2 bg-theme-hover rounded w-full mb-3" />
            <div className="flex gap-2">
              {[1, 2, 3, 4].map((j) => (
                <div key={j} className="w-8 h-8 rounded-full bg-theme-hover" />
              ))}
            </div>
          </GlassCard>
        ))}
      </div>
    );
  }

  if (collections.length === 0) {
    return (
      <div className="mt-4">
        <EmptyState
          icon={<Layers className="w-12 h-12" aria-hidden="true" />}
          title="No collections available"
          description="Badge collections will appear here as they become available."
        />
      </div>
    );
  }

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="space-y-4 mt-4"
    >
      {collections.map((collection) => {
        const progressPct = collection.total_count > 0
          ? Math.round((collection.earned_count / collection.total_count) * 100)
          : 0;

        return (
          <motion.div key={collection.id} variants={itemVariants}>
            <GlassCard className={`p-5 ${collection.completed ? 'border-l-4 border-emerald-400' : ''}`} hoverable>
              <div className="flex items-start justify-between gap-3 mb-2">
                <div>
                  <h4 className="font-semibold text-theme-primary flex items-center gap-2">
                    <Layers className="w-4 h-4 text-indigo-400" aria-hidden="true" />
                    {collection.name}
                    {collection.completed && (
                      <Chip size="sm" color="success" variant="flat">Complete</Chip>
                    )}
                  </h4>
                  <p className="text-sm text-theme-muted mt-1">{collection.description}</p>
                </div>
                {collection.reward_xp > 0 && (
                  <Chip size="sm" color="warning" variant="flat" className="flex-shrink-0">
                    <Gem className="w-3 h-3 inline mr-1" aria-hidden="true" />
                    {collection.reward_xp} XP
                  </Chip>
                )}
              </div>

              <Progress
                value={progressPct}
                className="mb-2"
                classNames={{
                  indicator: collection.completed
                    ? 'bg-gradient-to-r from-emerald-500 to-green-500'
                    : 'bg-gradient-to-r from-indigo-500 to-purple-600',
                  track: 'bg-theme-hover',
                }}
                size="sm"
                aria-label={`Collection progress: ${progressPct}%`}
              />
              <p className="text-xs text-theme-subtle mb-3">
                {collection.earned_count} / {collection.total_count} badges collected
              </p>

              {/* Badge thumbnails */}
              <div className="flex flex-wrap gap-2">
                {collection.badges.map((badge) => (
                  <div
                    key={badge.badge_key}
                    className={`w-9 h-9 rounded-full flex items-center justify-center text-sm ${
                      badge.earned
                        ? 'bg-gradient-to-br from-amber-500/20 to-orange-500/20'
                        : 'bg-theme-hover opacity-40'
                    }`}
                    title={badge.name}
                  >
                    {badge.icon || (
                      badge.earned
                        ? <Medal className="w-4 h-4 text-amber-400" aria-hidden="true" />
                        : <Lock className="w-3 h-3 text-theme-subtle" aria-hidden="true" />
                    )}
                  </div>
                ))}
              </div>
            </GlassCard>
          </motion.div>
        );
      })}
    </motion.div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// XP Shop Tab Content
// ─────────────────────────────────────────────────────────────────────────────

function XpShopTab({ userXp }: { userXp: number }) {
  const toast = useToast();
  const [items, setItems] = useState<ShopItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [purchasingId, setPurchasingId] = useState<number | null>(null);
  const [currentXp, setCurrentXp] = useState(userXp);

  useEffect(() => {
    setCurrentXp(userXp);
  }, [userXp]);

  const loadShop = useCallback(async () => {
    try {
      setIsLoading(true);
      const res = await api.get<ShopItem[]>('/v2/gamification/shop');
      if (res.success && res.data) {
        setItems(Array.isArray(res.data) ? res.data : []);
      }
    } catch (err) {
      logError('Failed to load shop', err);
      toast.error('Load Failed', 'Could not load the XP shop.');
    } finally {
      setIsLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadShop();
  }, [loadShop]);

  const purchaseItem = async (item: ShopItem) => {
    if (currentXp < item.cost_xp) {
      toast.warning('Not Enough XP', `You need ${item.cost_xp - currentXp} more XP to purchase this item.`);
      return;
    }

    try {
      setPurchasingId(item.id);
      const res = await api.post('/v2/gamification/shop/purchase', { item_id: item.id });
      if (res.success) {
        toast.success('Purchase Complete!', `You purchased "${item.name}".`);
        setItems((prev) =>
          prev.map((i) => (i.id === item.id ? { ...i, owned: true } : i))
        );
        setCurrentXp((prev) => prev - item.cost_xp);
      } else {
        toast.error('Purchase Failed', res.error ?? 'Could not complete purchase.');
      }
    } catch (err) {
      logError('Failed to purchase shop item', err);
      toast.error('Purchase Failed', 'Something went wrong.');
    } finally {
      setPurchasingId(null);
    }
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.05 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, scale: 0.9 },
    visible: { opacity: 1, scale: 1 },
  };

  if (isLoading) {
    return (
      <div className="space-y-4 mt-4">
        <GlassCard className="p-4 animate-pulse">
          <div className="h-6 bg-theme-hover rounded w-1/4" />
        </GlassCard>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <GlassCard key={i} className="p-5 animate-pulse">
              <div className="w-12 h-12 rounded-lg bg-theme-hover mx-auto mb-3" />
              <div className="h-4 bg-theme-hover rounded w-2/3 mx-auto mb-2" />
              <div className="h-3 bg-theme-hover rounded w-full mb-3" />
              <div className="h-8 bg-theme-hover rounded w-1/2 mx-auto" />
            </GlassCard>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4 mt-4">
      {/* XP Balance */}
      <GlassCard className="p-4 border-l-4 border-indigo-500">
        <div className="flex items-center gap-3">
          <Gem className="w-5 h-5 text-indigo-400" aria-hidden="true" />
          <span className="text-theme-primary font-medium">
            Your balance: <strong className="text-indigo-400">{currentXp.toLocaleString()} XP</strong>
          </span>
        </div>
      </GlassCard>

      {items.length === 0 ? (
        <EmptyState
          icon={<ShoppingBag className="w-12 h-12" aria-hidden="true" />}
          title="Shop is empty"
          description="No items available in the XP shop right now. Check back later!"
        />
      ) : (
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
        >
          {items.map((item) => {
            const canAfford = currentXp >= item.cost_xp;
            const isAvailable = item.available && !item.owned && (item.stock === null || item.stock > 0);

            return (
              <motion.div key={item.id} variants={itemVariants}>
                <GlassCard
                  className={`p-5 text-center ${item.owned ? 'opacity-70' : ''}`}
                  hoverable={!item.owned}
                >
                  {/* Item icon */}
                  <div className="w-14 h-14 mx-auto mb-3 rounded-lg bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center">
                    {item.type === 'badge' ? (
                      <Medal className="w-7 h-7 text-amber-400" aria-hidden="true" />
                    ) : item.type === 'title' ? (
                      <Crown className="w-7 h-7 text-purple-400" aria-hidden="true" />
                    ) : item.type === 'theme' ? (
                      <Sparkles className="w-7 h-7 text-indigo-400" aria-hidden="true" />
                    ) : (
                      <Package className="w-7 h-7 text-indigo-400" aria-hidden="true" />
                    )}
                  </div>

                  <h4 className="font-semibold text-theme-primary text-sm mb-1">{item.name}</h4>
                  <p className="text-xs text-theme-muted mb-3 line-clamp-2">{item.description}</p>

                  {/* Price & stock */}
                  <div className="mb-3">
                    <Chip
                      size="sm"
                      color={canAfford ? 'primary' : 'danger'}
                      variant="flat"
                    >
                      <Gem className="w-3 h-3 inline mr-1" aria-hidden="true" />
                      {item.cost_xp.toLocaleString()} XP
                    </Chip>
                    {item.stock !== null && !item.owned && (
                      <p className="text-xs text-theme-subtle mt-1">{item.stock} left in stock</p>
                    )}
                  </div>

                  {/* Action */}
                  {item.owned ? (
                    <Chip color="success" variant="flat">
                      <CheckCircle className="w-3 h-3 inline mr-1" aria-hidden="true" />
                      Owned
                    </Chip>
                  ) : !isAvailable ? (
                    <Chip color="default" variant="flat">Unavailable</Chip>
                  ) : (
                    <Button
                      size="sm"
                      className={
                        canAfford
                          ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white'
                          : 'bg-theme-hover text-theme-subtle'
                      }
                      startContent={
                        purchasingId === item.id ? (
                          <Spinner size="sm" color="white" />
                        ) : (
                          <ShoppingBag className="w-3 h-3" aria-hidden="true" />
                        )
                      }
                      onPress={() => purchaseItem(item)}
                      isDisabled={!canAfford || purchasingId === item.id}
                    >
                      {purchasingId === item.id ? 'Buying...' : 'Purchase'}
                    </Button>
                  )}
                </GlassCard>
              </motion.div>
            );
          })}
        </motion.div>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Badge Showcase Modal
// ─────────────────────────────────────────────────────────────────────────────

interface ShowcaseModalProps {
  isOpen: boolean;
  onClose: () => void;
  badges: BadgeEntry[];
  onSave: (keys: string[]) => void;
  isSaving: boolean;
}

function ShowcaseModal({ isOpen, onClose, badges, onSave, isSaving }: ShowcaseModalProps) {
  const earnedBadges = badges.filter((b) => b.earned_at || b.earned !== false);
  const [selectedKeys, setSelectedKeys] = useState<Set<string>>(
    new Set(badges.filter((b) => b.is_showcased).map((b) => b.badge_key))
  );

  // Sync when modal opens
  useEffect(() => {
    if (isOpen) {
      setSelectedKeys(new Set(badges.filter((b) => b.is_showcased).map((b) => b.badge_key)));
    }
  }, [isOpen, badges]);

  const toggleBadge = (key: string) => {
    setSelectedKeys((prev) => {
      const next = new Set(prev);
      if (next.has(key)) {
        next.delete(key);
      } else if (next.size < 5) {
        next.add(key);
      }
      return next;
    });
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size="2xl"
      classNames={{
        base: 'bg-content1 border border-white/10',
        header: 'border-b border-white/10',
        body: 'py-4',
        footer: 'border-t border-white/10',
      }}
    >
      <ModalContent>
        <ModalHeader className="flex items-center gap-2">
          <Star className="w-5 h-5 text-amber-400" aria-hidden="true" />
          Manage Badge Showcase
        </ModalHeader>
        <ModalBody>
          <p className="text-sm text-theme-muted mb-4">
            Select up to <strong>5 badges</strong> to showcase on your profile. ({selectedKeys.size}/5 selected)
          </p>
          {earnedBadges.length === 0 ? (
            <div className="text-center py-8">
              <Medal className="w-10 h-10 text-theme-subtle mx-auto mb-2" aria-hidden="true" />
              <p className="text-theme-muted">You haven&apos;t earned any badges yet.</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-80 overflow-y-auto pr-1">
              {earnedBadges.map((badge) => {
                const isSelected = selectedKeys.has(badge.badge_key);
                const isDisabled = !isSelected && selectedKeys.size >= 5;

                return (
                  <div
                    key={badge.badge_key}
                    className={`flex items-center gap-3 p-3 rounded-lg border transition-colors cursor-pointer ${
                      isSelected
                        ? 'border-amber-400/50 bg-amber-500/10'
                        : isDisabled
                          ? 'border-white/5 bg-theme-hover opacity-50 cursor-not-allowed'
                          : 'border-white/10 bg-theme-elevated hover:bg-theme-hover'
                    }`}
                    onClick={() => !isDisabled && toggleBadge(badge.badge_key)}
                    role="button"
                    tabIndex={0}
                    onKeyDown={(e) => {
                      if ((e.key === 'Enter' || e.key === ' ') && !isDisabled) {
                        e.preventDefault();
                        toggleBadge(badge.badge_key);
                      }
                    }}
                    aria-pressed={isSelected}
                    aria-disabled={isDisabled}
                  >
                    <Checkbox
                      isSelected={isSelected}
                      isDisabled={isDisabled}
                      onValueChange={() => toggleBadge(badge.badge_key)}
                      aria-label={`Showcase ${badge.name}`}
                      classNames={{
                        wrapper: 'flex-shrink-0',
                      }}
                    />
                    <div className="w-8 h-8 rounded-full bg-gradient-to-br from-amber-500/20 to-orange-500/20 flex items-center justify-center text-sm flex-shrink-0">
                      {badge.icon || <Medal className="w-4 h-4 text-amber-400" aria-hidden="true" />}
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-theme-primary truncate">
                        {badge.name}
                        {isSelected && (
                          <Star className="w-3 h-3 inline ml-1 text-amber-400" aria-hidden="true" />
                        )}
                      </p>
                      <p className="text-xs text-theme-subtle truncate">{badge.description}</p>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </ModalBody>
        <ModalFooter>
          <Button variant="flat" onPress={onClose} className="text-theme-muted">
            Cancel
          </Button>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={
              isSaving ? (
                <Spinner size="sm" color="white" />
              ) : (
                <Star className="w-4 h-4" aria-hidden="true" />
              )
            }
            onPress={() => onSave(Array.from(selectedKeys))}
            isDisabled={isSaving}
          >
            {isSaving ? 'Saving...' : 'Save Showcase'}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Achievements Page
// ─────────────────────────────────────────────────────────────────────────────

export function AchievementsPage() {
  usePageTitle('Achievements');
  const toast = useToast();
  const [profile, setProfile] = useState<GamificationProfile | null>(null);
  const [badges, setBadges] = useState<BadgeEntry[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filterType, setFilterType] = useState<string>('all');
  const [availableTypes, setAvailableTypes] = useState<string[]>([]);
  const [activeTab, setActiveTab] = useState<string>('badges');
  const [isShowcaseOpen, setIsShowcaseOpen] = useState(false);
  const [isSavingShowcase, setIsSavingShowcase] = useState(false);

  const loadData = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);

      // Load profile and badges in parallel
      const [profileRes, badgesRes] = await Promise.all([
        api.get<GamificationProfile>('/v2/gamification/profile'),
        api.get<{ data: BadgeEntry[]; meta: { total: number; available_types: string[] } }>('/v2/gamification/badges'),
      ]);

      if (profileRes.success && profileRes.data) {
        const profileData = profileRes.data as unknown as GamificationProfile;
        setProfile(profileData);
      }

      if (badgesRes.success && badgesRes.data) {
        setBadges(Array.isArray(badgesRes.data) ? badgesRes.data : []);
        setAvailableTypes((badgesRes.meta?.available_types as string[]) ?? []);
      }
    } catch (err) {
      logError('Failed to load achievements', err);
      setError('Failed to load achievements. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleSaveShowcase = async (badgeKeys: string[]) => {
    try {
      setIsSavingShowcase(true);
      const res = await api.put('/v2/gamification/showcase', { badge_keys: badgeKeys });
      if (res.success) {
        toast.success('Showcase Updated', 'Your badge showcase has been saved.');
        // Update badge showcase state
        setBadges((prev) =>
          prev.map((b) => ({
            ...b,
            is_showcased: badgeKeys.includes(b.badge_key),
          }))
        );
        setIsShowcaseOpen(false);
      } else {
        toast.error('Save Failed', res.error ?? 'Could not update showcase.');
      }
    } catch (err) {
      logError('Failed to save showcase', err);
      toast.error('Save Failed', 'Something went wrong.');
    } finally {
      setIsSavingShowcase(false);
    }
  };

  const filteredBadges = filterType === 'all'
    ? badges
    : badges.filter((b) => b.type === filterType);

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.05 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, scale: 0.9 },
    visible: { opacity: 1, scale: 1 },
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <Trophy className="w-7 h-7 text-amber-400" aria-hidden="true" />
          Achievements
        </h1>
        <p className="text-theme-muted mt-1">Track your badges, XP, and progress</p>
      </div>

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Achievements</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={loadData}
          >
            Try Again
          </Button>
        </GlassCard>
      )}

      {!error && (
        <>
          {isLoading ? (
            <div className="space-y-6">
              {/* Daily reward skeleton */}
              <GlassCard className="p-4 animate-pulse">
                <div className="flex items-center gap-4">
                  <div className="w-12 h-12 rounded-full bg-theme-hover" />
                  <div className="flex-1">
                    <div className="h-4 bg-theme-hover rounded w-1/3 mb-2" />
                    <div className="h-3 bg-theme-hover rounded w-1/2" />
                  </div>
                </div>
              </GlassCard>
              {/* Profile skeleton */}
              <GlassCard className="p-6 animate-pulse">
                <div className="flex items-center gap-4">
                  <div className="w-16 h-16 rounded-full bg-theme-hover" />
                  <div className="flex-1">
                    <div className="h-5 bg-theme-hover rounded w-1/3 mb-2" />
                    <div className="h-4 bg-theme-hover rounded w-full mb-2" />
                    <div className="h-3 bg-theme-hover rounded w-1/4" />
                  </div>
                </div>
              </GlassCard>
              {/* Badge grid skeleton */}
              <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                {[1, 2, 3, 4, 5, 6].map((i) => (
                  <GlassCard key={i} className="p-4 animate-pulse text-center">
                    <div className="w-12 h-12 rounded-full bg-theme-hover mx-auto mb-3" />
                    <div className="h-4 bg-theme-hover rounded w-2/3 mx-auto mb-2" />
                    <div className="h-3 bg-theme-hover rounded w-full" />
                  </GlassCard>
                ))}
              </div>
            </div>
          ) : (
            <>
              {/* Daily Reward Widget */}
              <DailyRewardWidget />

              {/* XP Profile Card */}
              {profile && (
                <GlassCard className="p-6">
                  <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div className="flex items-center gap-4 flex-1">
                      <div className="relative">
                        <div className="w-16 h-16 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                          <span className="text-white text-xl font-bold">{profile.level}</span>
                        </div>
                        <Sparkles className="w-5 h-5 text-amber-400 absolute -top-1 -right-1" aria-hidden="true" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <h2 className="text-lg font-bold text-theme-primary">Level {profile.level}</h2>
                        <p className="text-sm text-theme-muted">
                          {profile.xp.toLocaleString()} XP total
                        </p>
                        <div className="mt-2">
                          <Progress
                            value={profile.level_progress.progress_percentage}
                            className="max-w-md"
                            classNames={{
                              indicator: 'bg-gradient-to-r from-indigo-500 to-purple-600',
                              track: 'bg-theme-hover',
                            }}
                            size="sm"
                            aria-label="Level progress"
                          />
                          <p className="text-xs text-theme-subtle mt-1">
                            {profile.level_progress.current_xp} / {profile.level_progress.xp_for_next_level} XP to next level
                          </p>
                        </div>
                      </div>
                    </div>
                    <div className="flex gap-4">
                      <div className="text-center">
                        <p className="text-2xl font-bold text-amber-400">{profile.badges_count}</p>
                        <p className="text-xs text-theme-subtle">Badges</p>
                      </div>
                    </div>
                  </div>
                </GlassCard>
              )}

              {/* Tabs */}
              <Tabs
                selectedKey={activeTab}
                onSelectionChange={(key) => setActiveTab(key as string)}
                aria-label="Achievement sections"
                classNames={{
                  tabList: 'bg-theme-elevated border border-white/10 rounded-lg p-1',
                  cursor: 'bg-gradient-to-r from-indigo-500 to-purple-600',
                  tab: 'text-theme-muted data-[selected=true]:text-white',
                  tabContent: 'group-data-[selected=true]:text-white',
                }}
                fullWidth
              >
                <Tab
                  key="badges"
                  title={
                    <div className="flex items-center gap-2">
                      <Medal className="w-4 h-4" aria-hidden="true" />
                      <span>Badges</span>
                    </div>
                  }
                />
                <Tab
                  key="challenges"
                  title={
                    <div className="flex items-center gap-2">
                      <Target className="w-4 h-4" aria-hidden="true" />
                      <span>Challenges</span>
                    </div>
                  }
                />
                <Tab
                  key="collections"
                  title={
                    <div className="flex items-center gap-2">
                      <Layers className="w-4 h-4" aria-hidden="true" />
                      <span>Collections</span>
                    </div>
                  }
                />
                <Tab
                  key="shop"
                  title={
                    <div className="flex items-center gap-2">
                      <ShoppingBag className="w-4 h-4" aria-hidden="true" />
                      <span>XP Shop</span>
                    </div>
                  }
                />
              </Tabs>

              {/* Tab Content */}
              {activeTab === 'badges' && (
                <>
                  {/* Manage Showcase + Filter row */}
                  <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                      {availableTypes.length > 0 && (
                        <>
                          <Filter className="w-4 h-4 text-theme-subtle" aria-hidden="true" />
                          <Select
                            placeholder="Filter by type"
                            aria-label="Filter badges by type"
                            selectedKeys={[filterType]}
                            onChange={(e) => setFilterType(e.target.value || 'all')}
                            className="w-48"
                            classNames={{
                              trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                              value: 'text-theme-primary',
                            }}
                            items={[
                              { key: 'all', label: 'All Types' },
                              ...availableTypes.map((t) => ({
                                key: t,
                                label: t.charAt(0).toUpperCase() + t.slice(1),
                              })),
                            ]}
                          >
                            {(item) => <SelectItem key={item.key}>{item.label}</SelectItem>}
                          </Select>
                        </>
                      )}
                    </div>

                    <Button
                      variant="flat"
                      className="text-amber-400 bg-amber-500/10 hover:bg-amber-500/20"
                      startContent={<Star className="w-4 h-4" aria-hidden="true" />}
                      onPress={() => setIsShowcaseOpen(true)}
                    >
                      Manage Showcase
                    </Button>
                  </div>

                  {/* Badges Grid */}
                  {filteredBadges.length === 0 ? (
                    <EmptyState
                      icon={<Medal className="w-12 h-12" aria-hidden="true" />}
                      title="No badges yet"
                      description="Complete activities and challenges to earn badges!"
                    />
                  ) : (
                    <motion.div
                      variants={containerVariants}
                      initial="hidden"
                      animate="visible"
                      className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"
                    >
                      {filteredBadges.map((badge) => (
                        <motion.div key={badge.badge_key} variants={itemVariants}>
                          <GlassCard className={`p-4 text-center hover:scale-105 transition-transform ${
                            !badge.earned_at && badge.earned === false ? 'opacity-40' : ''
                          }`}>
                            <div className="w-14 h-14 mx-auto mb-3 rounded-full bg-gradient-to-br from-amber-500/20 to-orange-500/20 flex items-center justify-center text-2xl">
                              {badge.icon || <Medal className="w-7 h-7 text-amber-400" aria-hidden="true" />}
                            </div>
                            <h3 className="font-semibold text-theme-primary text-sm mb-1 truncate">{badge.name}</h3>
                            <p className="text-xs text-theme-muted line-clamp-2">{badge.description}</p>
                            {badge.is_showcased && (
                              <Chip size="sm" color="warning" variant="flat" className="mt-2">
                                <Star className="w-3 h-3 inline mr-1" aria-hidden="true" />
                                Showcased
                              </Chip>
                            )}
                            {badge.earned_at && (
                              <p className="text-xs text-theme-subtle mt-2">
                                Earned {new Date(badge.earned_at).toLocaleDateString()}
                              </p>
                            )}
                            {!badge.earned_at && badge.earned === false && (
                              <div className="flex items-center justify-center gap-1 mt-2 text-xs text-theme-subtle">
                                <Lock className="w-3 h-3" aria-hidden="true" />
                                Locked
                              </div>
                            )}
                          </GlassCard>
                        </motion.div>
                      ))}
                    </motion.div>
                  )}

                  {/* Showcase Modal */}
                  <ShowcaseModal
                    isOpen={isShowcaseOpen}
                    onClose={() => setIsShowcaseOpen(false)}
                    badges={badges}
                    onSave={handleSaveShowcase}
                    isSaving={isSavingShowcase}
                  />
                </>
              )}

              {activeTab === 'challenges' && <ChallengesTab />}
              {activeTab === 'collections' && <CollectionsTab />}
              {activeTab === 'shop' && <XpShopTab userXp={profile?.xp ?? 0} />}
            </>
          )}
        </>
      )}
    </div>
  );
}

export default AchievementsPage;
