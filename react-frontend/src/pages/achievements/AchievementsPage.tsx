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
  Skeleton,
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
import { useTranslation } from 'react-i18next';
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
  const { t } = useTranslation('gamification');
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
        toast.success(t('achievements.daily_reward.claimed_title'), t('achievements.daily_reward.claimed_message', { xp: status?.reward_xp ?? 0 }));
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
        toast.error(t('achievements.daily_reward.claim_failed'), res.error ?? t('achievements.daily_reward.claim_failed_desc'));
      }
    } catch (err) {
      logError('Failed to claim daily reward', err);
      toast.error(t('achievements.daily_reward.claim_failed'), t('achievements.daily_reward.claim_error'));
    } finally {
      setIsClaiming(false);
    }
  };

  if (isLoading) {
    return (
      <GlassCard className="p-4" aria-label="Loading daily reward" aria-busy="true">
        <div className="flex items-center gap-4">
          <Skeleton className="rounded-full flex-shrink-0"><div className="w-12 h-12 rounded-full bg-default-300" /></Skeleton>
          <div className="flex-1 space-y-2">
            <Skeleton className="rounded-lg"><div className="h-4 rounded-lg bg-default-300 w-1/3" /></Skeleton>
            <Skeleton className="rounded-lg"><div className="h-3 rounded-lg bg-default-200 w-1/2" /></Skeleton>
          </div>
          <Skeleton className="rounded-lg"><div className="h-10 w-28 rounded-lg bg-default-300" /></Skeleton>
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
              <h3 className="font-semibold text-theme-primary">{t('achievements.daily_reward.title')}</h3>
              {status.claimed_today ? (
                <p className="text-sm text-theme-muted">
                  {t('achievements.daily_reward.come_back_tomorrow', { xp: status.next_reward_xp })}
                  {status.current_streak > 1 && (
                    <span className="ml-2">
                      <Zap className="w-3 h-3 inline text-amber-400" aria-hidden="true" /> {t('achievements.daily_reward.day_streak', { count: status.current_streak })}
                    </span>
                  )}
                </p>
              ) : (
                <p className="text-sm text-theme-muted">
                  {t('achievements.daily_reward.claim_today', { xp: status.reward_xp })}
                  {status.current_streak > 0 && (
                    <span className="ml-2">
                      <Zap className="w-3 h-3 inline text-amber-400" aria-hidden="true" /> {t('achievements.daily_reward.day_streak', { count: status.current_streak })}
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
                  {isClaiming ? t('achievements.daily_reward.claiming') : t('achievements.daily_reward.claim_reward')}
                </Button>
              </motion.div>
            ) : (
              <Chip
                color="success"
                variant="flat"
                startContent={<CheckCircle className="w-3 h-3" aria-hidden="true" />}
              >
                {t('achievements.daily_reward.claimed')}
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
  const { t } = useTranslation('gamification');
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
      toast.error(t('achievements.challenges.load_failed'), t('achievements.challenges.load_failed_desc'));
    } finally {
      setIsLoading(false);
    }
  }, [toast, t]);

  useEffect(() => {
    loadChallenges();
  }, [loadChallenges]);

  const claimReward = async (challengeId: number) => {
    try {
      setClaimingId(challengeId);
      const res = await api.post('/v2/gamification/challenges/' + challengeId + '/claim');
      if (res.success) {
        toast.success(t('achievements.challenges.reward_claimed'), t('achievements.challenges.reward_claimed_desc'));
        setChallenges((prev) =>
          prev.map((c) => (c.id === challengeId ? { ...c, status: 'claimed' as const } : c))
        );
      } else {
        toast.error(t('achievements.challenges.claim_failed'), res.error ?? t('achievements.challenges.claim_failed_desc'));
      }
    } catch (err) {
      logError('Failed to claim challenge reward', err);
      toast.error(t('achievements.challenges.claim_failed'), t('achievements.challenges.claim_error'));
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
        {Array.from({ length: 3 }).map((_, i) => (
          <GlassCard key={i} className="p-5">
            <div className="flex items-start gap-4">
              <Skeleton className="rounded-lg flex-shrink-0"><div className="w-10 h-10 rounded-lg bg-default-300" /></Skeleton>
              <div className="flex-1 space-y-2">
                <Skeleton className="rounded-lg"><div className="h-4 rounded-lg bg-default-300 w-1/3" /></Skeleton>
                <Skeleton className="rounded-lg"><div className="h-3 rounded-lg bg-default-200 w-2/3" /></Skeleton>
                <Skeleton className="rounded-lg"><div className="h-2 rounded-lg bg-default-200 w-full" /></Skeleton>
                <Skeleton className="rounded-lg"><div className="h-3 rounded-lg bg-default-200 w-1/4" /></Skeleton>
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
          title={t('achievements.challenges.empty_title')}
          description={t('achievements.challenges.empty_description')}
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
            {t('achievements.challenges.active_challenges')}
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
            {t('achievements.challenges.completed')}
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
                            {t('achievements.challenges.claim_xp', { xp: challenge.reward_xp })}
                          </Button>
                        ) : (
                          <Chip size="sm" color="success" variant="flat">
                            <CheckCircle className="w-3 h-3 inline mr-1" aria-hidden="true" />
                            {t('achievements.challenges.claimed')}
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
  const { t } = useTranslation('gamification');
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
      toast.error(t('achievements.collections.load_failed'), t('achievements.collections.load_failed_desc'));
    } finally {
      setIsLoading(false);
    }
  }, [toast, t]);

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
        {Array.from({ length: 3 }).map((_, i) => (
          <GlassCard key={i} className="p-5">
            <div className="space-y-2 mb-3">
              <Skeleton className="rounded-lg"><div className="h-4 rounded-lg bg-default-300 w-1/3" /></Skeleton>
              <Skeleton className="rounded-lg"><div className="h-3 rounded-lg bg-default-200 w-2/3" /></Skeleton>
              <Skeleton className="rounded-lg"><div className="h-2 rounded-lg bg-default-200 w-full" /></Skeleton>
            </div>
            <div className="flex gap-2">
              {Array.from({ length: 4 }).map((_, j) => (
                <Skeleton key={j} className="rounded-full"><div className="w-8 h-8 rounded-full bg-default-300" /></Skeleton>
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
          title={t('achievements.collections.empty_title')}
          description={t('achievements.collections.empty_description')}
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
                      <Chip size="sm" color="success" variant="flat">{t('achievements.collections.complete')}</Chip>
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
                {t('achievements.collections.badges_collected', { earned: collection.earned_count, total: collection.total_count })}
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
  const { t } = useTranslation('gamification');
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
      toast.error(t('achievements.shop.load_failed'), t('achievements.shop.load_failed_desc'));
    } finally {
      setIsLoading(false);
    }
  }, [toast, t]);

  useEffect(() => {
    loadShop();
  }, [loadShop]);

  const purchaseItem = async (item: ShopItem) => {
    if (currentXp < item.cost_xp) {
      toast.warning(t('achievements.shop.not_enough_xp'), t('achievements.shop.not_enough_xp_desc', { xp: item.cost_xp - currentXp }));
      return;
    }

    try {
      setPurchasingId(item.id);
      const res = await api.post('/v2/gamification/shop/purchase', { item_id: item.id });
      if (res.success) {
        toast.success(t('achievements.shop.purchase_complete'), t('achievements.shop.purchase_complete_desc', { name: item.name }));
        setItems((prev) =>
          prev.map((i) => (i.id === item.id ? { ...i, owned: true } : i))
        );
        setCurrentXp((prev) => prev - item.cost_xp);
      } else {
        toast.error(t('achievements.shop.purchase_failed'), res.error ?? t('achievements.shop.purchase_failed_desc'));
      }
    } catch (err) {
      logError('Failed to purchase shop item', err);
      toast.error(t('achievements.shop.purchase_failed'), t('achievements.shop.purchase_error'));
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
        <GlassCard className="p-4">
          <Skeleton className="rounded-lg"><div className="h-6 rounded-lg bg-default-300 w-1/4" /></Skeleton>
        </GlassCard>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {Array.from({ length: 6 }).map((_, i) => (
            <GlassCard key={i} className="p-5 text-center">
              <Skeleton className="rounded-lg mx-auto mb-3"><div className="w-12 h-12 rounded-lg bg-default-300 mx-auto" /></Skeleton>
              <div className="space-y-2">
              <Skeleton className="rounded-lg"><div className="h-4 rounded-lg bg-default-300 w-2/3 mx-auto" /></Skeleton>
              <Skeleton className="rounded-lg"><div className="h-3 rounded-lg bg-default-200 w-full" /></Skeleton>
              <Skeleton className="rounded-lg"><div className="h-8 rounded-lg bg-default-300 w-1/2 mx-auto" /></Skeleton>
              </div>
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
            {t('achievements.shop.your_balance')}: <strong className="text-indigo-400">{currentXp.toLocaleString()} XP</strong>
          </span>
        </div>
      </GlassCard>

      {items.length === 0 ? (
        <EmptyState
          icon={<ShoppingBag className="w-12 h-12" aria-hidden="true" />}
          title={t('achievements.shop.empty_title')}
          description={t('achievements.shop.empty_description')}
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
                      <p className="text-xs text-theme-subtle mt-1">{t('achievements.shop.stock_left', { count: item.stock })}</p>
                    )}
                  </div>

                  {/* Action */}
                  {item.owned ? (
                    <Chip color="success" variant="flat">
                      <CheckCircle className="w-3 h-3 inline mr-1" aria-hidden="true" />
                      {t('achievements.shop.owned')}
                    </Chip>
                  ) : !isAvailable ? (
                    <Chip color="default" variant="flat">{t('achievements.shop.unavailable')}</Chip>
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
                      {purchasingId === item.id ? t('achievements.shop.buying') : t('achievements.shop.purchase')}
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
  const { t } = useTranslation('gamification');
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
          {t('achievements.showcase.manage_title')}
        </ModalHeader>
        <ModalBody>
          <p className="text-sm text-theme-muted mb-4">
            {t('achievements.showcase.select_badges', { count: selectedKeys.size })}
          </p>
          {earnedBadges.length === 0 ? (
            <div className="text-center py-8">
              <Medal className="w-10 h-10 text-theme-subtle mx-auto mb-2" aria-hidden="true" />
              <p className="text-theme-muted">{t('achievements.showcase.no_badges_earned')}</p>
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
                    aria-label={t('achievements.showcase.showcase_badge', { name: badge.name })}
                  >
                    <Checkbox
                      isSelected={isSelected}
                      isDisabled={isDisabled}
                      onValueChange={() => toggleBadge(badge.badge_key)}
                      aria-label={t('achievements.showcase.showcase_badge', { name: badge.name })}
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
            {t('achievements.showcase.cancel')}
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
            {isSaving ? t('achievements.showcase.saving') : t('achievements.showcase.save')}
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
  const { t } = useTranslation('gamification');
  usePageTitle(t('achievements.page_title'));
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
        toast.success(t('achievements.showcase.updated'), t('achievements.showcase.updated_desc'));
        // Update badge showcase state
        setBadges((prev) =>
          prev.map((b) => ({
            ...b,
            is_showcased: badgeKeys.includes(b.badge_key),
          }))
        );
        setIsShowcaseOpen(false);
      } else {
        toast.error(t('achievements.showcase.save_failed'), res.error ?? t('achievements.showcase.save_failed_desc'));
      }
    } catch (err) {
      logError('Failed to save showcase', err);
      toast.error(t('achievements.showcase.save_failed'), t('achievements.showcase.save_error'));
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
          {t('achievements.title')}
        </h1>
        <p className="text-theme-muted mt-1">{t('achievements.subtitle')}</p>
      </div>

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('achievements.unable_to_load')}</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={loadData}
          >
            {t('achievements.try_again')}
          </Button>
        </GlassCard>
      )}

      {!error && (
        <>
          {isLoading ? (
            <div aria-label="Loading achievements" aria-busy="true" className="space-y-6">
              {/* Daily reward skeleton */}
              <GlassCard className="p-4">
                <div className="flex items-center gap-4">
                  <Skeleton className="rounded-full flex-shrink-0"><div className="w-12 h-12 rounded-full bg-default-300" /></Skeleton>
                  <div className="flex-1 space-y-2">
                    <Skeleton className="rounded-lg"><div className="h-4 rounded-lg bg-default-300 w-1/3" /></Skeleton>
                    <Skeleton className="rounded-lg"><div className="h-3 rounded-lg bg-default-200 w-1/2" /></Skeleton>
                  </div>
                </div>
              </GlassCard>
              {/* Profile skeleton */}
              <GlassCard className="p-6">
                <div className="flex items-center gap-4">
                  <Skeleton className="rounded-full flex-shrink-0"><div className="w-16 h-16 rounded-full bg-default-300" /></Skeleton>
                  <div className="flex-1 space-y-2">
                    <Skeleton className="rounded-lg"><div className="h-5 rounded-lg bg-default-300 w-1/3" /></Skeleton>
                    <Skeleton className="rounded-lg"><div className="h-4 rounded-lg bg-default-200 w-full" /></Skeleton>
                    <Skeleton className="rounded-lg"><div className="h-3 rounded-lg bg-default-200 w-1/4" /></Skeleton>
                  </div>
                </div>
              </GlassCard>
              {/* Badge grid skeleton */}
              <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                {Array.from({ length: 6 }).map((_, i) => (
                  <GlassCard key={i} className="p-4 text-center">
                    <Skeleton className="rounded-full mx-auto mb-3"><div className="w-12 h-12 rounded-full bg-default-300 mx-auto" /></Skeleton>
                    <div className="space-y-2">
                    <Skeleton className="rounded-lg"><div className="h-4 rounded-lg bg-default-300 w-2/3 mx-auto" /></Skeleton>
                    <Skeleton className="rounded-lg"><div className="h-3 rounded-lg bg-default-200 w-full" /></Skeleton>
                    </div>
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
                        <h2 className="text-lg font-bold text-theme-primary">{t('achievements.level', { level: profile.level })}</h2>
                        <p className="text-sm text-theme-muted">
                          {t('achievements.xp_total', { xp: profile.xp.toLocaleString() })}
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
                            aria-label={t('achievements.level_progress_aria')}
                          />
                          <p className="text-xs text-theme-subtle mt-1">
                            {t('achievements.xp_to_next_level', { current: profile.level_progress.current_xp, next: profile.level_progress.xp_for_next_level })}
                          </p>
                        </div>
                      </div>
                    </div>
                    <div className="flex gap-4">
                      <div className="text-center">
                        <p className="text-2xl font-bold text-amber-400">{profile.badges_count}</p>
                        <p className="text-xs text-theme-subtle">{t('achievements.badges_label')}</p>
                      </div>
                    </div>
                  </div>
                </GlassCard>
              )}

              {/* Tabs */}
              <Tabs
                selectedKey={activeTab}
                onSelectionChange={(key) => setActiveTab(key as string)}
                aria-label={t('achievements.sections_aria')}
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
                      <span>{t('achievements.tab_badges')}</span>
                    </div>
                  }
                />
                <Tab
                  key="challenges"
                  title={
                    <div className="flex items-center gap-2">
                      <Target className="w-4 h-4" aria-hidden="true" />
                      <span>{t('achievements.tab_challenges')}</span>
                    </div>
                  }
                />
                <Tab
                  key="collections"
                  title={
                    <div className="flex items-center gap-2">
                      <Layers className="w-4 h-4" aria-hidden="true" />
                      <span>{t('achievements.tab_collections')}</span>
                    </div>
                  }
                />
                <Tab
                  key="shop"
                  title={
                    <div className="flex items-center gap-2">
                      <ShoppingBag className="w-4 h-4" aria-hidden="true" />
                      <span>{t('achievements.tab_shop')}</span>
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
                            placeholder={t('achievements.filter_by_type')}
                            aria-label={t('achievements.filter_badges_aria')}
                            selectedKeys={[filterType]}
                            onChange={(e) => setFilterType(e.target.value || 'all')}
                            className="w-48"
                            classNames={{
                              trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                              value: 'text-theme-primary',
                            }}
                            items={[
                              { key: 'all', label: t('achievements.all_types') },
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
                      {t('achievements.manage_showcase')}
                    </Button>
                  </div>

                  {/* Badges Grid */}
                  {filteredBadges.length === 0 ? (
                    <EmptyState
                      icon={<Medal className="w-12 h-12" aria-hidden="true" />}
                      title={t('achievements.empty_title')}
                      description={t('achievements.empty_description')}
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
                                {t('achievements.showcased')}
                              </Chip>
                            )}
                            {badge.earned_at && (
                              <p className="text-xs text-theme-subtle mt-2">
                                {t('achievements.earned_date', { date: new Date(badge.earned_at).toLocaleDateString() })}
                              </p>
                            )}
                            {!badge.earned_at && badge.earned === false && (
                              <div className="flex items-center justify-center gap-1 mt-2 text-xs text-theme-subtle">
                                <Lock className="w-3 h-3" aria-hidden="true" />
                                {t('achievements.locked')}
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
