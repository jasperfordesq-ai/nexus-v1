// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Onboarding Page - Post-registration 5-step wizard
 *
 * Steps:
 *  1. Welcome      - Benefits overview, community introduction
 *  2. Your Profile - Upload profile photo + write bio (MANDATORY — cannot skip)
 *  3. Interests    - Select categories you're interested in (optional — can skip)
 *  4. Skills       - Mark which categories you can offer / need help with (optional)
 *  5. Confirm      - Profile preview + summary + auto-create listings
 *
 * Route: /onboarding
 */

import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Spinner,
  Chip,
  Avatar,
  Textarea,
  Divider,
} from '@heroui/react';
import {
  Sparkles,
  ArrowRight,
  ArrowLeft,
  CheckCircle,
  Heart,
  HandHeart,
  HelpCircle,
  ListChecks,
  Rocket,
  Camera,
  UserCircle,
  Upload,
  SkipForward,
  PartyPopper,
  ImagePlus,
  Clock,
  Users,
  Star,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant, useAuth } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface Category {
  id: number;
  name: string;
  slug: string | null;
  icon: string | null;
  color: string | null;
}

const TOTAL_STEPS = 5;
const MIN_BIO_LENGTH = 10;

const STEP_LABEL_KEYS = ['step_welcome', 'step_profile', 'step_interests', 'step_skills', 'step_confirm'];

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function OnboardingPage() {
  const { t } = useTranslation('onboarding');
  usePageTitle(t('page_title'));
  const navigate = useNavigate();
  const { tenantPath, tenant } = useTenant();
  const toast = useToast();
  const { user, refreshUser } = useAuth();

  const tenantName = tenant?.branding?.name || tenant?.name || 'our community';

  // ── State ──────────────────────────────────────────────────────────────────

  const [currentStep, setCurrentStep] = useState(1);
  const [slideDirection, setSlideDirection] = useState(1);

  // Step 2: Profile photo + bio
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [isUploadingAvatar, setIsUploadingAvatar] = useState(false);
  const [isSavingProfile, setIsSavingProfile] = useState(false);
  const [bio, setBio] = useState(user?.bio || '');
  const [isDraggingOver, setIsDraggingOver] = useState(false);

  // Categories loaded from API
  const [categories, setCategories] = useState<Category[]>([]);
  const [categoriesLoading, setCategoriesLoading] = useState(false);

  // Step 3: Selected interest category IDs
  const [selectedInterests, setSelectedInterests] = useState<number[]>([]);

  // Step 4: Skill offers and needs
  const [skillOffers, setSkillOffers] = useState<number[]>([]);
  const [skillNeeds, setSkillNeeds] = useState<number[]>([]);

  // Step 5: Submission + completion
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isComplete, setIsComplete] = useState(false);

  // Track which steps have been visited (for stepper)
  const [visitedSteps, setVisitedSteps] = useState<Set<number>>(new Set([1]));

  // ── Redirect if fully completed (onboarding done + has photo + has bio) ───
  // Skip redirect when isComplete is true so the celebration animation plays
  // fully before the setTimeout navigates to dashboard.

  useEffect(() => {
    if (isComplete) return; // Let celebration animation play out
    if (user?.onboarding_completed === true) {
      navigate(tenantPath('/dashboard'), { replace: true });
    }
  }, [user?.onboarding_completed, navigate, tenantPath, isComplete]);

  // ── Pre-populate bio from user context if available ──────────────────────

  useEffect(() => {
    if (user?.bio && !bio) {
      setBio(user.bio);
    }
    // Only run on mount and when user.bio changes
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user?.bio]);

  // ── If user already has photo+bio but onboarding_completed is false,
  //    skip ahead to step 3 so they don't re-do profile setup ────────────

  const initialSkipDone = useRef(false);
  useEffect(() => {
    if (
      !initialSkipDone.current &&
      user?.avatar_url &&
      user?.bio &&
      user.bio.trim().length >= MIN_BIO_LENGTH &&
      currentStep <= 2
    ) {
      initialSkipDone.current = true;
      setCurrentStep(3);
      setVisitedSteps(new Set([1, 2, 3]));
    }
  }, [user?.avatar_url, user?.bio, currentStep]);

  // ── Load categories when reaching step 3 ───────────────────────────────

  useEffect(() => {
    if (currentStep >= 3 && categories.length === 0 && !categoriesLoading) {
      loadCategories();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentStep]);

  const loadCategories = useCallback(async () => {
    try {
      setCategoriesLoading(true);
      const response = await api.get<Category[]>('/v2/onboarding/categories');
      if (response.success && response.data) {
        setCategories(Array.isArray(response.data) ? response.data : []);
      }
    } catch (error) {
      logError('Failed to load onboarding categories', error);
      toast.error(t('toast_categories_failed'), t('toast_try_again'));
    } finally {
      setCategoriesLoading(false);
    }
  }, [toast]);

  // ── Navigation ─────────────────────────────────────────────────────────────

  const goToStep = useCallback((step: number) => {
    setSlideDirection(step > currentStep ? 1 : -1);
    setCurrentStep(step);
    setVisitedSteps((prev) => new Set([...prev, step]));
  }, [currentStep]);

  const goNextAnimated = useCallback(() => {
    const next = Math.min(currentStep + 1, TOTAL_STEPS);
    goToStep(next);
  }, [currentStep, goToStep]);

  const goBackAnimated = useCallback(() => {
    const prev = Math.max(currentStep - 1, 1);
    goToStep(prev);
  }, [currentStep, goToStep]);

  // ── Avatar upload handler (Step 2) ───────────────────────────────────────

  const processAvatarFile = useCallback(async (file: File) => {
    if (!file.type.startsWith('image/')) {
      toast.error(t('toast_invalid_file_type'), t('toast_invalid_file_type_desc'));
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      toast.error(t('toast_file_too_large'), t('toast_file_too_large_desc'));
      return;
    }

    try {
      setIsUploadingAvatar(true);
      const formData = new FormData();
      formData.append('avatar', file);

      const response = await api.upload<{ avatar_url: string }>('/v2/users/me/avatar', formData);

      if (response.success && response.data) {
        await refreshUser();
        toast.success(t('toast_photo_uploaded'), t('toast_photo_uploaded_desc'));
      } else {
        toast.error(t('toast_upload_failed'), response.error || t('toast_upload_failed_desc'));
      }
    } catch (error) {
      logError('Failed to upload avatar during onboarding', error);
      toast.error(t('toast_upload_failed'), t('toast_upload_failed_desc'));
    } finally {
      setIsUploadingAvatar(false);
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  }, [toast, refreshUser]);

  const handleAvatarUpload = useCallback((event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (file) processAvatarFile(file);
  }, [processAvatarFile]);

  // Drag-and-drop handlers for photo upload
  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingOver(true);
  }, []);

  const handleDragLeave = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingOver(false);
  }, []);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDraggingOver(false);
    const file = e.dataTransfer.files?.[0];
    if (file) processAvatarFile(file);
  }, [processAvatarFile]);

  // ── Save bio + proceed handler (Step 2) ──────────────────────────────────

  const handleSaveProfileAndProceed = useCallback(async () => {
    if (!user?.avatar_url) {
      toast.error(t('toast_photo_required'), t('toast_photo_required_desc'));
      return;
    }
    if (bio.trim().length < MIN_BIO_LENGTH) {
      toast.error(t('toast_bio_required'), t('toast_bio_required_desc', { min: MIN_BIO_LENGTH }));
      return;
    }

    try {
      setIsSavingProfile(true);
      const response = await api.put('/v2/users/me', { bio: bio.trim() });

      if (response.success) {
        await refreshUser();
        goNextAnimated();
      } else {
        toast.error(t('toast_save_failed'), t('toast_save_failed_desc'));
      }
    } catch (error) {
      logError('Failed to save bio during onboarding', error);
      toast.error(t('toast_save_failed'), t('toast_save_failed_desc'));
    } finally {
      setIsSavingProfile(false);
    }
  }, [bio, user?.avatar_url, toast, refreshUser, goNextAnimated]);

  // ── Save interests + proceed handler (Step 3) ──────────────────────────

  const handleSaveInterestsAndProceed = useCallback(async () => {
    // Interests are saved atomically with skills in /v2/onboarding/complete.
    // Just advance — no separate API call needed.
    goNextAnimated();
  }, [goNextAnimated]);

  // ── Interest toggling (Step 3) ───────────────────────────────────────────

  const toggleInterest = useCallback((categoryId: number) => {
    setSelectedInterests((prev) =>
      prev.includes(categoryId)
        ? prev.filter((id) => id !== categoryId)
        : [...prev, categoryId]
    );
  }, []);

  // ── Skill toggling (Step 4) ──────────────────────────────────────────────

  const toggleOffer = useCallback((categoryId: number) => {
    setSkillOffers((prev) =>
      prev.includes(categoryId)
        ? prev.filter((id) => id !== categoryId)
        : [...prev, categoryId]
    );
  }, []);

  const toggleNeed = useCallback((categoryId: number) => {
    setSkillNeeds((prev) =>
      prev.includes(categoryId)
        ? prev.filter((id) => id !== categoryId)
        : [...prev, categoryId]
    );
  }, []);

  // ── Category name lookup ─────────────────────────────────────────────────

  const categoryMap = useMemo(() => {
    const map = new Map<number, string>();
    for (const cat of categories) {
      map.set(cat.id, cat.name);
    }
    return map;
  }, [categories]);

  const getCategoryName = useCallback(
    (id: number) => categoryMap.get(id) || 'Unknown',
    [categoryMap]
  );

  // ── Completion handler ───────────────────────────────────────────────────

  const totalListingsToCreate = skillOffers.length + skillNeeds.length;

  const handleComplete = useCallback(async () => {
    try {
      setIsSubmitting(true);

      const response = await api.post('/v2/onboarding/complete', {
        interests: selectedInterests,
        offers: skillOffers,
        needs: skillNeeds,
      });

      if (!response.success) {
        // Surface the backend error clearly
        const message = response.error || t('toast_something_went_wrong');
        // If it's a profile-related issue, guide back to step 2
        if (message.toLowerCase().includes('photo') || message.toLowerCase().includes('bio')) {
          toast.error(t('toast_profile_incomplete'), message);
          goToStep(2);
        } else {
          toast.error(t('toast_setup_failed'), message);
        }
        return;
      }

      const listingsCreated = (response.data as { listings_created?: number })?.listings_created ?? 0;

      // Show completion celebration
      setIsComplete(true);

      // Refresh user state so ProtectedRoute sees onboarding_completed = true
      await refreshUser();

      // Brief delay for the celebration animation, then navigate
      setTimeout(() => {
        if (listingsCreated > 0) {
          toast.success(
            t('toast_welcome_aboard'),
            t('toast_listings_created', { count: listingsCreated })
          );
        } else {
          toast.success(t('toast_welcome_aboard'), t('toast_profile_all_set'));
        }
        navigate(tenantPath('/dashboard'));
      }, 1800);
    } catch (error) {
      logError('Failed to complete onboarding', error);
      toast.error(t('toast_setup_failed'), t('toast_something_went_wrong'));
    } finally {
      setIsSubmitting(false);
    }
  }, [skillOffers, skillNeeds, toast, navigate, tenantPath, refreshUser, goToStep]);

  // ── Skip handler (skips interests/skills — photo+bio already done) ──────

  const handleSkip = useCallback(async () => {
    try {
      setIsSubmitting(true);
      const response = await api.post('/v2/onboarding/complete', { interests: [], offers: [], needs: [] });

      if (!response.success) {
        const message = response.error || t('toast_something_went_wrong');
        if (message.toLowerCase().includes('photo') || message.toLowerCase().includes('bio')) {
          toast.error(t('toast_profile_incomplete'), message);
          goToStep(2);
        } else {
          toast.error(t('toast_setup_failed'), message);
        }
        return;
      }

      setIsComplete(true);
      await refreshUser();

      setTimeout(() => {
        toast.success(t('toast_welcome_aboard'), t('toast_profile_all_set'));
        navigate(tenantPath('/dashboard'));
      }, 1800);
    } catch (error) {
      logError('Failed to skip onboarding', error);
      toast.error(t('toast_setup_failed'), t('toast_something_went_wrong'));
    } finally {
      setIsSubmitting(false);
    }
  }, [navigate, tenantPath, refreshUser, toast, goToStep]);

  // ── Animation variants ─────────────────────────────────────────────────

  const slideVariants = {
    enter: (direction: number) => ({
      x: direction > 0 ? 60 : -60,
      opacity: 0,
    }),
    center: { x: 0, opacity: 1 },
    exit: (direction: number) => ({
      x: direction < 0 ? 60 : -60,
      opacity: 0,
    }),
  };

  // ── Derived state for Step 2 validation ──────────────────────────────────

  const hasAvatar = !!user?.avatar_url;
  const hasBio = bio.trim().length >= MIN_BIO_LENGTH;
  const profileStepComplete = hasAvatar && hasBio;

  // ── Completion celebration overlay (checked BEFORE redirect guard so the
  //    celebration screen actually renders — refreshUser() sets
  //    onboarding_completed=true which would otherwise cause return null) ───

  if (isComplete) {
    return (
      <div className="max-w-2xl mx-auto py-12">
        <motion.div
          initial={{ scale: 0.8, opacity: 0 }}
          animate={{ scale: 1, opacity: 1 }}
          transition={{ duration: 0.5, ease: 'easeOut' }}
          className="text-center space-y-6"
        >
          <motion.div
            animate={{
              rotate: [0, -10, 10, -10, 10, 0],
              scale: [1, 1.2, 1],
            }}
            transition={{ duration: 0.8, delay: 0.3 }}
            className="inline-flex p-6 rounded-full bg-gradient-to-br from-emerald-500/20 to-teal-500/20"
          >
            <PartyPopper className="w-16 h-16 text-emerald-500" />
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.5, duration: 0.4 }}
          >
            <h1 className="text-3xl font-bold text-theme-primary mb-2">
              {t('complete_all_set')}
            </h1>
            <p className="text-theme-muted text-lg">
              {t('complete_welcome_to', { name: tenantName })}
            </p>
          </motion.div>

          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: 1.0 }}
          >
            <Spinner size="sm" color="success" />
            <p className="text-sm text-theme-subtle mt-2">{t('complete_redirecting')}</p>
          </motion.div>
        </motion.div>
      </div>
    );
  }

  // ── Don't render wizard if fully completed (redirect is pending) ─────────

  if (user?.onboarding_completed === true) {
    return null;
  }

  // ── Main Render ────────────────────────────────────────────────────────────

  return (
    <div className="max-w-2xl mx-auto py-6 px-4 space-y-6">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        className="text-center"
      >
        <h1 className="text-2xl font-bold text-theme-primary">{t('page_title')}</h1>
        <p className="text-theme-muted mt-1 text-sm">
          {t('subtitle')}
        </p>
      </motion.div>

      {/* Step Indicator */}
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ delay: 0.1 }}
      >
        <StepIndicator
          currentStep={currentStep}
          totalSteps={TOTAL_STEPS}
          visitedSteps={visitedSteps}
          completedSteps={getCompletedSteps({
            hasAvatar,
            hasBio,
            selectedInterests,
            skillOffers,
            skillNeeds,
          })}
          onStepClick={(step) => {
            // Only allow clicking to visited steps or the current step
            // Never allow skipping step 2 (profile) if incomplete
            if (step <= currentStep || visitedSteps.has(step)) {
              if (step > 2 && !profileStepComplete) {
                toast.error(t('toast_complete_profile_first'), t('toast_photo_bio_required'));
                goToStep(2);
                return;
              }
              goToStep(step);
            }
          }}
        />
      </motion.div>

      {/* Step Content */}
      <AnimatePresence mode="wait" custom={slideDirection}>
        <motion.div
          key={currentStep}
          custom={slideDirection}
          variants={slideVariants}
          initial="enter"
          animate="center"
          exit="exit"
          transition={{ duration: 0.2, ease: 'easeInOut' }}
        >
          {/* ─── Step 1: Welcome ─── */}
          {currentStep === 1 && (
            <div className="space-y-6">
              <GlassCard className="p-8 text-center">
                <div className="flex items-center justify-center gap-4 mb-6">
                  <motion.div
                    animate={{ y: [0, -6, 0] }}
                    transition={{ repeat: Infinity, duration: 3, ease: 'easeInOut' }}
                    className="p-4 rounded-2xl bg-emerald-500/20"
                  >
                    <HandHeart
                      className="w-10 h-10 text-emerald-600 dark:text-emerald-400"
                      aria-hidden="true"
                    />
                  </motion.div>
                  <motion.div
                    animate={{ y: [0, -6, 0] }}
                    transition={{ repeat: Infinity, duration: 3, ease: 'easeInOut', delay: 0.4 }}
                    className="p-4 rounded-2xl bg-rose-500/20"
                  >
                    <Heart
                      className="w-10 h-10 text-rose-600 dark:text-rose-400"
                      aria-hidden="true"
                    />
                  </motion.div>
                  <motion.div
                    animate={{ y: [0, -6, 0] }}
                    transition={{ repeat: Infinity, duration: 3, ease: 'easeInOut', delay: 0.8 }}
                    className="p-4 rounded-2xl bg-amber-500/20"
                  >
                    <Sparkles
                      className="w-10 h-10 text-amber-600 dark:text-amber-400"
                      aria-hidden="true"
                    />
                  </motion.div>
                </div>

                <h2 className="text-xl font-bold text-theme-primary mb-2">
                  {t('welcome_title', { name: tenantName })}
                </h2>
                <p className="text-theme-muted max-w-md mx-auto">
                  {t('welcome_description')}
                </p>
              </GlassCard>

              {/* How it works cards */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <BenefitCard
                  icon={<Clock className="w-6 h-6 text-emerald-500" />}
                  title={t('benefit_earn_title')}
                  description={t('benefit_earn_desc')}
                />
                <BenefitCard
                  icon={<Users className="w-6 h-6 text-rose-500" />}
                  title={t('benefit_community_title')}
                  description={t('benefit_community_desc')}
                />
                <BenefitCard
                  icon={<Star className="w-6 h-6 text-amber-500" />}
                  title={t('benefit_skills_title')}
                  description={t('benefit_skills_desc')}
                />
              </div>

              <div className="flex justify-center pt-2">
                <Button
                  size="lg"
                  className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white px-10 font-semibold shadow-lg shadow-emerald-500/20"
                  endContent={<ArrowRight className="w-5 h-5" aria-hidden="true" />}
                  onPress={goNextAnimated}
                >
                  {t('lets_get_started')}
                </Button>
              </div>
            </div>
          )}

          {/* ─── Step 2: Your Profile (MANDATORY — photo + bio) ─── */}
          {currentStep === 2 && (
            <div className="space-y-6">
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-1 flex items-center gap-2">
                  <UserCircle
                    className="w-5 h-5 text-emerald-600 dark:text-emerald-400"
                    aria-hidden="true"
                  />
                  {t('profile_title')}
                </h2>
                <p className="text-theme-muted text-sm mb-6">
                  {t('profile_description')}
                </p>

                {/* Avatar upload zone */}
                <div
                  className={`
                    flex flex-col items-center gap-4 mb-6 p-6 rounded-xl border-2 border-dashed transition-all duration-200
                    ${isDraggingOver
                      ? 'border-emerald-500 bg-emerald-500/10'
                      : hasAvatar
                        ? 'border-emerald-500/30 bg-emerald-500/5'
                        : 'border-theme-default bg-theme-elevated/50 hover:border-emerald-500/50'
                    }
                  `}
                  onDragOver={handleDragOver}
                  onDragLeave={handleDragLeave}
                  onDrop={handleDrop}
                >
                  <div className="relative group">
                    <Avatar
                      src={resolveAvatarUrl(user?.avatar_url)}
                      name={user?.first_name || user?.name}
                      className="w-28 h-28 ring-4 ring-theme-default group-hover:ring-emerald-500/30 transition-all"
                      isBordered={hasAvatar}
                      color={hasAvatar ? 'success' : 'default'}
                    />
                    <input
                      ref={fileInputRef}
                      type="file"
                      accept="image/*"
                      onChange={handleAvatarUpload}
                      className="hidden"
                      aria-label={t('aria_upload_photo')}
                    />
                    <Button
                      isIconOnly
                      size="sm"
                      className="absolute bottom-1 right-1 rounded-full bg-emerald-500 text-white hover:bg-emerald-600 shadow-lg min-w-0 w-9 h-9"
                      onPress={() => fileInputRef.current?.click()}
                      isDisabled={isUploadingAvatar}
                      isLoading={isUploadingAvatar}
                      aria-label={t('aria_upload_photo')}
                    >
                      {hasAvatar ? (
                        <Camera className="w-4 h-4" aria-hidden="true" />
                      ) : (
                        <ImagePlus className="w-4 h-4" aria-hidden="true" />
                      )}
                    </Button>
                  </div>

                  {isUploadingAvatar ? (
                    <p className="text-sm text-theme-muted flex items-center gap-2">
                      <Spinner size="sm" /> {t('uploading')}
                    </p>
                  ) : hasAvatar ? (
                    <div className="text-center">
                      <p className="text-sm text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5 justify-center font-medium">
                        <CheckCircle className="w-4 h-4" aria-hidden="true" />
                        {t('photo_uploaded')}
                      </p>
                      <Button
                        variant="light"
                        size="sm"
                        onPress={() => fileInputRef.current?.click()}
                        className="text-xs text-theme-subtle hover:text-theme-muted underline mt-1 h-auto p-0 min-w-0"
                      >
                        {t('change_photo')}
                      </Button>
                    </div>
                  ) : (
                    <div className="text-center">
                      <Button
                        variant="flat"
                        size="sm"
                        className="mb-2"
                        startContent={<Upload className="w-4 h-4" />}
                        onPress={() => fileInputRef.current?.click()}
                      >
                        {t('choose_photo')}
                      </Button>
                      <p className="text-xs text-theme-subtle">
                        {t('photo_drag_hint')}
                      </p>
                    </div>
                  )}
                </div>

                {/* Bio textarea */}
                <div className="space-y-2">
                  <Textarea
                    label={t('bio_label')}
                    placeholder={t('bio_placeholder')}
                    value={bio}
                    onValueChange={setBio}
                    isDisabled={isSavingProfile}
                    minRows={3}
                    maxRows={6}
                    maxLength={5000}
                    description={
                      bio.trim().length < MIN_BIO_LENGTH
                        ? t('bio_min_chars', { min: MIN_BIO_LENGTH, current: bio.trim().length })
                        : t('bio_char_count', { count: bio.trim().length })
                    }
                    classNames={{
                      inputWrapper: 'bg-theme-elevated',
                    }}
                  />
                </div>

                {/* Validation checklist */}
                <div className="mt-4 p-3 rounded-lg bg-theme-elevated">
                  <p className="text-xs font-medium text-theme-muted mb-2">
                    {t('required_to_continue')}
                  </p>
                  <div className="flex flex-col gap-1.5">
                    <ValidationItem checked={hasAvatar} label={t('validation_photo')} />
                    <ValidationItem checked={hasBio} label={t('validation_bio', { min: MIN_BIO_LENGTH })} />
                  </div>
                </div>
              </GlassCard>

              {/* Navigation */}
              <div className="flex items-center justify-between">
                <Button
                  variant="light"
                  className="text-theme-muted"
                  onPress={goBackAnimated}
                  startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('back')}
                </Button>
                <Button
                  className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-medium"
                  endContent={<ArrowRight className="w-4 h-4" aria-hidden="true" />}
                  onPress={handleSaveProfileAndProceed}
                  isLoading={isSavingProfile}
                  isDisabled={!profileStepComplete || isSavingProfile}
                >
                  {t('next')}
                </Button>
              </div>
            </div>
          )}

          {/* ─── Step 3: Select Interests (OPTIONAL) ─── */}
          {currentStep === 3 && (
            <div className="space-y-6">
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-1 flex items-center gap-2">
                  <Heart
                    className="w-5 h-5 text-rose-500"
                    aria-hidden="true"
                  />
                  {t('interests_title')}
                </h2>
                <p className="text-theme-muted text-sm mb-6">
                  {t('interests_description')}
                </p>

                {categoriesLoading ? (
                  <div className="flex items-center justify-center py-12">
                    <Spinner size="lg" />
                  </div>
                ) : categories.length === 0 ? (
                  <div className="text-center py-8">
                    <HelpCircle
                      className="w-10 h-10 text-theme-subtle mx-auto mb-2"
                      aria-hidden="true"
                    />
                    <p className="text-theme-muted text-sm">
                      {t('no_categories_available')}
                    </p>
                  </div>
                ) : (
                  <div className="flex flex-wrap gap-2">
                    {categories.map((cat) => {
                      const isSelected = selectedInterests.includes(cat.id);
                      return (
                        <Chip
                          key={cat.id}
                          variant={isSelected ? 'solid' : 'bordered'}
                          color={isSelected ? 'success' : 'default'}
                          className="cursor-pointer transition-all hover:scale-105"
                          onClick={() => toggleInterest(cat.id)}
                          aria-pressed={isSelected}
                          role="button"
                          tabIndex={0}
                          onKeyDown={(e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                              e.preventDefault();
                              toggleInterest(cat.id);
                            }
                          }}
                        >
                          {cat.name}
                        </Chip>
                      );
                    })}
                  </div>
                )}

                {categories.length > 0 && (
                  <p className="text-xs text-theme-subtle mt-4">
                    {selectedInterests.length === 0
                      ? t('select_or_skip')
                      : t('count_selected', { count: selectedInterests.length })}
                  </p>
                )}
              </GlassCard>

              <div className="flex items-center justify-between">
                <Button
                  variant="light"
                  className="text-theme-muted"
                  onPress={goBackAnimated}
                  startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('back')}
                </Button>
                <div className="flex items-center gap-2">
                  <Button
                    variant="light"
                    className="text-theme-subtle"
                    onPress={goNextAnimated}
                    endContent={<SkipForward className="w-4 h-4" aria-hidden="true" />}
                  >
                    {t('skip')}
                  </Button>
                  <Button
                    className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-medium"
                    endContent={<ArrowRight className="w-4 h-4" aria-hidden="true" />}
                    onPress={handleSaveInterestsAndProceed}
                    isDisabled={selectedInterests.length === 0}
                  >
                    {t('next')}
                  </Button>
                </div>
              </div>
            </div>
          )}

          {/* ─── Step 4: Your Skills (OPTIONAL — shows ALL categories) ─── */}
          {currentStep === 4 && (
            <div className="space-y-6">
              {/* Offers section */}
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-1 flex items-center gap-2">
                  <HandHeart
                    className="w-5 h-5 text-emerald-600 dark:text-emerald-400"
                    aria-hidden="true"
                  />
                  {t('skills_offer_title')}
                </h2>
                <p className="text-theme-muted text-sm mb-4">
                  {t('skills_offer_description')}
                </p>

                {categoriesLoading ? (
                  <div className="flex items-center justify-center py-8">
                    <Spinner size="md" />
                  </div>
                ) : categories.length === 0 ? (
                  <p className="text-theme-subtle text-sm py-4">
                    {t('no_categories_skip')}
                  </p>
                ) : (
                  <div className="flex flex-wrap gap-2">
                    {categories.map((cat) => {
                      const isOffer = skillOffers.includes(cat.id);
                      return (
                        <Chip
                          key={cat.id}
                          variant={isOffer ? 'solid' : 'bordered'}
                          color={isOffer ? 'success' : 'default'}
                          className="cursor-pointer transition-all hover:scale-105"
                          onClick={() => toggleOffer(cat.id)}
                          aria-pressed={isOffer}
                          role="button"
                          tabIndex={0}
                          onKeyDown={(e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                              e.preventDefault();
                              toggleOffer(cat.id);
                            }
                          }}
                        >
                          {cat.name}
                        </Chip>
                      );
                    })}
                  </div>
                )}

                {skillOffers.length > 0 && (
                  <p className="text-xs text-emerald-600 dark:text-emerald-400 mt-3 font-medium">
                    {t('skills_to_offer_count', { count: skillOffers.length })}
                  </p>
                )}
              </GlassCard>

              {/* Needs section */}
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-1 flex items-center gap-2">
                  <HelpCircle
                    className="w-5 h-5 text-amber-600 dark:text-amber-400"
                    aria-hidden="true"
                  />
                  {t('skills_need_title')}
                </h2>
                <p className="text-theme-muted text-sm mb-4">
                  {t('skills_need_description')}
                </p>

                {categoriesLoading ? (
                  <div className="flex items-center justify-center py-8">
                    <Spinner size="md" />
                  </div>
                ) : categories.length === 0 ? (
                  <p className="text-theme-subtle text-sm py-4">
                    {t('no_categories_skip')}
                  </p>
                ) : (
                  <div className="flex flex-wrap gap-2">
                    {categories.map((cat) => {
                      const isNeed = skillNeeds.includes(cat.id);
                      return (
                        <Chip
                          key={cat.id}
                          variant={isNeed ? 'solid' : 'bordered'}
                          color={isNeed ? 'warning' : 'default'}
                          className="cursor-pointer transition-all hover:scale-105"
                          onClick={() => toggleNeed(cat.id)}
                          aria-pressed={isNeed}
                          role="button"
                          tabIndex={0}
                          onKeyDown={(e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                              e.preventDefault();
                              toggleNeed(cat.id);
                            }
                          }}
                        >
                          {cat.name}
                        </Chip>
                      );
                    })}
                  </div>
                )}

                {skillNeeds.length > 0 && (
                  <p className="text-xs text-amber-600 dark:text-amber-400 mt-3 font-medium">
                    {t('skills_needed_count', { count: skillNeeds.length })}
                  </p>
                )}
              </GlassCard>

              <div className="flex items-center justify-between">
                <Button
                  variant="light"
                  className="text-theme-muted"
                  onPress={goBackAnimated}
                  startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('back')}
                </Button>
                <div className="flex items-center gap-2">
                  <Button
                    variant="light"
                    className="text-theme-subtle"
                    onPress={goNextAnimated}
                    endContent={<SkipForward className="w-4 h-4" aria-hidden="true" />}
                  >
                    {t('skip')}
                  </Button>
                  <Button
                    className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-medium"
                    endContent={<ArrowRight className="w-4 h-4" aria-hidden="true" />}
                    onPress={goNextAnimated}
                  >
                    {t('next')}
                  </Button>
                </div>
              </div>
            </div>
          )}

          {/* ─── Step 5: Confirm + Create ─── */}
          {currentStep === 5 && (
            <div className="space-y-6">
              {/* Profile preview card */}
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
                  <CheckCircle
                    className="w-5 h-5 text-emerald-600 dark:text-emerald-400"
                    aria-hidden="true"
                  />
                  {t('confirm_title')}
                </h2>

                {/* Mini profile card */}
                <div className="flex items-start gap-4 p-4 rounded-xl bg-theme-elevated mb-6">
                  <Avatar
                    src={resolveAvatarUrl(user?.avatar_url)}
                    name={user?.first_name || user?.name}
                    className="w-16 h-16 flex-shrink-0"
                    isBordered
                    color="success"
                  />
                  <div className="min-w-0 flex-1">
                    <p className="font-semibold text-theme-primary text-base">
                      {user?.first_name} {user?.last_name}
                    </p>
                    <p className="text-sm text-theme-muted line-clamp-2 mt-0.5">
                      {bio || user?.bio || t('no_bio_yet')}
                    </p>
                    <Button
                      variant="light"
                      size="sm"
                      onPress={() => goToStep(2)}
                      className="text-xs text-emerald-600 dark:text-emerald-400 hover:underline mt-1 h-auto p-0 min-w-0"
                    >
                      {t('edit_profile')}
                    </Button>
                  </div>
                </div>

                <Divider className="my-4" />

                {/* Interests summary */}
                <div className="space-y-4">
                  <SummarySection
                    icon={<Heart className="w-4 h-4 text-rose-500" />}
                    title={t('summary_interests')}
                    items={selectedInterests}
                    getCategoryName={getCategoryName}
                    chipColor="primary"
                    emptyText={t('none_selected')}
                    onEdit={() => goToStep(3)}
                  />

                  <Divider />

                  <SummarySection
                    icon={<HandHeart className="w-4 h-4 text-emerald-500" />}
                    title={t('summary_offers')}
                    items={skillOffers}
                    getCategoryName={getCategoryName}
                    chipColor="success"
                    emptyText={t('none_selected')}
                    onEdit={() => goToStep(4)}
                  />

                  <Divider />

                  <SummarySection
                    icon={<HelpCircle className="w-4 h-4 text-amber-500" />}
                    title={t('summary_needs')}
                    items={skillNeeds}
                    getCategoryName={getCategoryName}
                    chipColor="warning"
                    emptyText={t('none_selected')}
                    onEdit={() => goToStep(4)}
                  />
                </div>
              </GlassCard>

              {/* Listings preview */}
              {totalListingsToCreate > 0 && (
                <GlassCard className="p-6">
                  <h2 className="text-base font-semibold text-theme-primary mb-3 flex items-center gap-2">
                    <ListChecks
                      className="w-5 h-5 text-emerald-600 dark:text-emerald-400"
                      aria-hidden="true"
                    />
                    {t('listings_to_create', { count: totalListingsToCreate })}
                  </h2>
                  <div className="space-y-2">
                    {skillOffers.map((catId) => (
                      <ListingPreviewItem
                        key={`offer-${catId}`}
                        type="offer"
                        name={getCategoryName(catId)}
                      />
                    ))}
                    {skillNeeds.map((catId) => (
                      <ListingPreviewItem
                        key={`need-${catId}`}
                        type="need"
                        name={getCategoryName(catId)}
                      />
                    ))}
                  </div>
                </GlassCard>
              )}

              {/* Action buttons */}
              <div className="flex items-center justify-between gap-3">
                <Button
                  variant="light"
                  className="text-theme-muted"
                  onPress={goBackAnimated}
                  startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('back')}
                </Button>

                <div className="flex items-center gap-3">
                  {totalListingsToCreate === 0 && (
                    <Button
                      variant="light"
                      className="text-theme-subtle"
                      onPress={handleSkip}
                      isDisabled={isSubmitting}
                      endContent={<SkipForward className="w-4 h-4" aria-hidden="true" />}
                    >
                      {t('skip_for_now')}
                    </Button>
                  )}
                  <Button
                    size="lg"
                    className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-semibold shadow-lg shadow-emerald-500/20"
                    onPress={handleComplete}
                    isLoading={isSubmitting}
                    startContent={
                      !isSubmitting && (
                        <Rocket className="w-5 h-5" aria-hidden="true" />
                      )
                    }
                  >
                    {totalListingsToCreate > 0 ? t('complete_setup') : t('finish')}
                  </Button>
                </div>
              </div>
            </div>
          )}
        </motion.div>
      </AnimatePresence>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: determine which steps are "completed" for the stepper
// ─────────────────────────────────────────────────────────────────────────────

function getCompletedSteps(state: {
  hasAvatar: boolean;
  hasBio: boolean;
  selectedInterests: number[];
  skillOffers: number[];
  skillNeeds: number[];
}): Set<number> {
  const completed = new Set<number>();
  completed.add(1); // Welcome is always "done" once visited
  if (state.hasAvatar && state.hasBio) completed.add(2);
  if (state.selectedInterests.length > 0) completed.add(3);
  if (state.skillOffers.length > 0 || state.skillNeeds.length > 0) completed.add(4);
  return completed;
}

// ─────────────────────────────────────────────────────────────────────────────
// Sub-components
// ─────────────────────────────────────────────────────────────────────────────

// ── Step Indicator (dots + labels) ───────────────────────────────────────────

interface StepIndicatorProps {
  currentStep: number;
  totalSteps: number;
  visitedSteps: Set<number>;
  completedSteps: Set<number>;
  onStepClick: (step: number) => void;
}

function StepIndicator({ currentStep, totalSteps, visitedSteps, completedSteps, onStepClick }: StepIndicatorProps) {
  const { t } = useTranslation('onboarding');
  return (
    <div className="flex items-center justify-between">
      {Array.from({ length: totalSteps }, (_, i) => {
        const step = i + 1;
        const isCurrent = step === currentStep;
        const isCompleted = completedSteps.has(step) && !isCurrent;
        const isVisited = visitedSteps.has(step);
        const isClickable = isVisited || step <= currentStep;

        return (
          <div key={step} className="flex items-center flex-1 last:flex-initial">
            {/* Step dot + label */}
            <Button
              variant="light"
              onPress={() => isClickable && onStepClick(step)}
              aria-label={t('aria_step', { step, label: t(STEP_LABEL_KEYS[i]), status: isCompleted ? t('aria_completed') : isCurrent ? t('aria_current') : '' })}
              aria-current={isCurrent ? 'step' : undefined}
              isDisabled={!isClickable}
              className={`
                flex flex-col items-center gap-1 transition-all h-auto min-w-0 p-0
                ${isClickable ? 'cursor-pointer' : 'cursor-default'}
              `}
            >
              <div
                className={`
                  w-9 h-9 rounded-full flex items-center justify-center text-sm font-semibold transition-all duration-300
                  ${isCurrent
                    ? 'bg-gradient-to-r from-emerald-500 to-teal-600 text-white shadow-md shadow-emerald-500/30 scale-110'
                    : isCompleted
                      ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400'
                      : 'bg-theme-elevated text-theme-subtle'
                  }
                `}
              >
                {isCompleted ? (
                  <CheckCircle className="w-4 h-4" />
                ) : (
                  <span>{step}</span>
                )}
              </div>
              <span
                className={`
                  text-[10px] font-medium hidden sm:block
                  ${isCurrent ? 'text-emerald-600 dark:text-emerald-400' : 'text-theme-subtle'}
                `}
              >
                {t(STEP_LABEL_KEYS[i])}
              </span>
            </Button>

            {/* Connector line */}
            {step < totalSteps && (
              <div className="flex-1 mx-1.5 sm:mx-2">
                <div
                  className={`
                    h-0.5 rounded-full transition-all duration-500
                    ${completedSteps.has(step) && (completedSteps.has(step + 1) || step + 1 === currentStep)
                      ? 'bg-emerald-500'
                      : 'bg-theme-elevated'
                    }
                  `}
                />
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}

// ── Benefit Card ─────────────────────────────────────────────────────────────

interface BenefitCardProps {
  icon: React.ReactNode;
  title: string;
  description: string;
}

function BenefitCard({ icon, title, description }: BenefitCardProps) {
  return (
    <GlassCard className="p-5 text-center">
      <div className="flex justify-center mb-3" aria-hidden="true">
        {icon}
      </div>
      <h3 className="font-semibold text-theme-primary text-sm mb-1">{title}</h3>
      <p className="text-xs text-theme-subtle leading-relaxed">{description}</p>
    </GlassCard>
  );
}

// ── Validation Item (checklist) ──────────────────────────────────────────────

function ValidationItem({ checked, label }: { checked: boolean; label: string }) {
  return (
    <div className="flex items-center gap-2 text-sm">
      <CheckCircle
        className={`w-4 h-4 transition-colors ${checked ? 'text-emerald-500' : 'text-theme-subtle'}`}
        aria-hidden="true"
      />
      <span className={checked ? 'text-theme-primary' : 'text-theme-subtle'}>
        {label}
      </span>
    </div>
  );
}

// ── Summary Section (Step 5) ─────────────────────────────────────────────────

interface SummarySectionProps {
  icon: React.ReactNode;
  title: string;
  items: number[];
  getCategoryName: (id: number) => string;
  chipColor: 'primary' | 'success' | 'warning';
  emptyText: string;
  onEdit: () => void;
}

function SummarySection({ icon, title, items, getCategoryName, chipColor, emptyText, onEdit }: SummarySectionProps) {
  const { t } = useTranslation('onboarding');
  return (
    <div>
      <div className="flex items-center justify-between mb-2">
        <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
          {icon}
          {title}
        </h3>
        <Button
          variant="light"
          size="sm"
          onPress={onEdit}
          className="text-xs text-emerald-600 dark:text-emerald-400 hover:underline h-auto p-0 min-w-0"
        >
          {t('edit')}
        </Button>
      </div>
      <div className="flex flex-wrap gap-1.5">
        {items.length > 0 ? (
          items.map((catId) => (
            <Chip key={catId} size="sm" variant="flat" color={chipColor}>
              {getCategoryName(catId)}
            </Chip>
          ))
        ) : (
          <p className="text-theme-subtle text-sm italic">{emptyText}</p>
        )}
      </div>
    </div>
  );
}

// ── Listing Preview Item (Step 5) ────────────────────────────────────────────

function ListingPreviewItem({ type, name }: { type: 'offer' | 'need'; name: string }) {
  const { t } = useTranslation('onboarding');
  const isOffer = type === 'offer';
  return (
    <div className="flex items-center gap-3 p-3 rounded-lg bg-theme-elevated">
      <div className={`p-2 rounded-lg flex-shrink-0 ${isOffer ? 'bg-emerald-500/20' : 'bg-amber-500/20'}`}>
        {isOffer ? (
          <HandHeart className="w-4 h-4 text-emerald-500" aria-hidden="true" />
        ) : (
          <HelpCircle className="w-4 h-4 text-amber-500" aria-hidden="true" />
        )}
      </div>
      <div className="min-w-0">
        <p className="font-medium text-theme-primary text-sm">
          {isOffer ? t('listing_offer_help', { name }) : t('listing_need_help', { name })}
        </p>
        <p className="text-xs text-theme-subtle">
          {isOffer ? t('listing_type_offer') : t('listing_type_request')}
        </p>
      </div>
    </div>
  );
}

export default OnboardingPage;
