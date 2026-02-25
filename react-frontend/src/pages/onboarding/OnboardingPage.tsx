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

const STEP_LABELS = ['Welcome', 'Profile', 'Interests', 'Skills', 'Confirm'];

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function OnboardingPage() {
  usePageTitle('Get Started');
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
    if (user?.onboarding_completed === true && user?.avatar_url && user?.bio) {
      navigate(tenantPath('/dashboard'), { replace: true });
    }
  }, [user?.onboarding_completed, user?.avatar_url, user?.bio, navigate, tenantPath, isComplete]);

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
      toast.error('Failed to load categories', 'Please try again.');
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
      toast.error('Invalid file type', 'Please upload an image file (JPG, PNG or GIF).');
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      toast.error('File too large', 'Please upload an image smaller than 5 MB.');
      return;
    }

    try {
      setIsUploadingAvatar(true);
      const formData = new FormData();
      formData.append('avatar', file);

      const response = await api.upload<{ avatar_url: string }>('/v2/users/me/avatar', formData);

      if (response.success && response.data) {
        await refreshUser();
        toast.success('Photo uploaded', 'Looking great!');
      } else {
        toast.error('Upload failed', response.error || 'Failed to upload photo. Please try again.');
      }
    } catch (error) {
      logError('Failed to upload avatar during onboarding', error);
      toast.error('Upload failed', 'Failed to upload photo. Please try again.');
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
      toast.error('Photo required', 'Please upload a profile photo to continue.');
      return;
    }
    if (bio.trim().length < MIN_BIO_LENGTH) {
      toast.error('Bio required', `Please write at least ${MIN_BIO_LENGTH} characters about yourself.`);
      return;
    }

    try {
      setIsSavingProfile(true);
      const response = await api.put('/v2/users/me', { bio: bio.trim() });

      if (response.success) {
        await refreshUser();
        goNextAnimated();
      } else {
        toast.error('Save failed', 'Failed to save your bio. Please try again.');
      }
    } catch (error) {
      logError('Failed to save bio during onboarding', error);
      toast.error('Save failed', 'Failed to save your bio. Please try again.');
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
        const message = response.error || 'Something went wrong. Please try again.';
        // If it's a profile-related issue, guide back to step 2
        if (message.toLowerCase().includes('photo') || message.toLowerCase().includes('bio')) {
          toast.error('Profile incomplete', message);
          goToStep(2);
        } else {
          toast.error('Setup failed', message);
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
            'Welcome aboard!',
            `${listingsCreated} listing${listingsCreated === 1 ? '' : 's'} created for you.`
          );
        } else {
          toast.success('Welcome aboard!', 'Your profile is all set.');
        }
        navigate(tenantPath('/dashboard'));
      }, 1800);
    } catch (error) {
      logError('Failed to complete onboarding', error);
      toast.error('Setup failed', 'Something went wrong. Please try again.');
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
        const message = response.error || 'Something went wrong.';
        if (message.toLowerCase().includes('photo') || message.toLowerCase().includes('bio')) {
          toast.error('Profile incomplete', message);
          goToStep(2);
        } else {
          toast.error('Setup failed', message);
        }
        return;
      }

      setIsComplete(true);
      await refreshUser();

      setTimeout(() => {
        toast.success('Welcome aboard!', 'Your profile is all set.');
        navigate(tenantPath('/dashboard'));
      }, 1800);
    } catch (error) {
      logError('Failed to skip onboarding', error);
      toast.error('Setup failed', 'Something went wrong. Please try again.');
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
              You're all set!
            </h1>
            <p className="text-theme-muted text-lg">
              Welcome to {tenantName}
            </p>
          </motion.div>

          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: 1.0 }}
          >
            <Spinner size="sm" color="success" />
            <p className="text-sm text-theme-subtle mt-2">Taking you to your dashboard...</p>
          </motion.div>
        </motion.div>
      </div>
    );
  }

  // ── Don't render wizard if fully completed (redirect is pending) ─────────

  if (user?.onboarding_completed === true && user?.avatar_url && user?.bio) {
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
        <h1 className="text-2xl font-bold text-theme-primary">Get Started</h1>
        <p className="text-theme-muted mt-1 text-sm">
          Set up your profile in a few easy steps
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
                toast.error('Complete your profile first', 'Photo and bio are required.');
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
                  Welcome to {tenantName}!
                </h2>
                <p className="text-theme-muted max-w-md mx-auto">
                  Let's set up your profile so you can start connecting with your
                  community and exchanging time credits.
                </p>
              </GlassCard>

              {/* How it works cards */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <BenefitCard
                  icon={<Clock className="w-6 h-6 text-emerald-500" />}
                  title="Earn Time Credits"
                  description="Help others and earn credits you can spend on services you need"
                />
                <BenefitCard
                  icon={<Users className="w-6 h-6 text-rose-500" />}
                  title="Build Community"
                  description="Connect with neighbours and strengthen local bonds"
                />
                <BenefitCard
                  icon={<Star className="w-6 h-6 text-amber-500" />}
                  title="Share Your Skills"
                  description="Offer your talents and discover what your community can do"
                />
              </div>

              <div className="flex justify-center pt-2">
                <Button
                  size="lg"
                  className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white px-10 font-semibold shadow-lg shadow-emerald-500/20"
                  endContent={<ArrowRight className="w-5 h-5" aria-hidden="true" />}
                  onPress={goNextAnimated}
                >
                  Let's Get Started
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
                  Your Profile
                </h2>
                <p className="text-theme-muted text-sm mb-6">
                  Add a photo and tell the community about yourself. This helps
                  members recognise and trust you.
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
                      aria-label="Upload profile photo"
                    />
                    <Button
                      isIconOnly
                      size="sm"
                      className="absolute bottom-1 right-1 rounded-full bg-emerald-500 text-white hover:bg-emerald-600 shadow-lg min-w-0 w-9 h-9"
                      onPress={() => fileInputRef.current?.click()}
                      isDisabled={isUploadingAvatar}
                      isLoading={isUploadingAvatar}
                      aria-label="Upload profile photo"
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
                      <Spinner size="sm" /> Uploading...
                    </p>
                  ) : hasAvatar ? (
                    <div className="text-center">
                      <p className="text-sm text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5 justify-center font-medium">
                        <CheckCircle className="w-4 h-4" aria-hidden="true" />
                        Photo uploaded
                      </p>
                      <button
                        className="text-xs text-theme-subtle hover:text-theme-muted underline mt-1"
                        onClick={() => fileInputRef.current?.click()}
                        type="button"
                      >
                        Change photo
                      </button>
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
                        Choose a photo
                      </Button>
                      <p className="text-xs text-theme-subtle">
                        or drag and drop — JPG, PNG or GIF, max 5 MB
                      </p>
                    </div>
                  )}
                </div>

                {/* Bio textarea */}
                <div className="space-y-2">
                  <Textarea
                    label="About you"
                    placeholder="Tell the community about yourself — your interests, skills, or what you hope to get from timebanking..."
                    value={bio}
                    onValueChange={setBio}
                    isDisabled={isSavingProfile}
                    minRows={3}
                    maxRows={6}
                    maxLength={5000}
                    description={
                      bio.trim().length < MIN_BIO_LENGTH
                        ? `At least ${MIN_BIO_LENGTH} characters required (${bio.trim().length}/${MIN_BIO_LENGTH})`
                        : `${bio.trim().length} characters`
                    }
                    classNames={{
                      inputWrapper: 'bg-theme-elevated',
                    }}
                  />
                </div>

                {/* Validation checklist */}
                <div className="mt-4 p-3 rounded-lg bg-theme-elevated">
                  <p className="text-xs font-medium text-theme-muted mb-2">
                    Required to continue:
                  </p>
                  <div className="flex flex-col gap-1.5">
                    <ValidationItem checked={hasAvatar} label="Profile photo" />
                    <ValidationItem checked={hasBio} label={`Bio (${MIN_BIO_LENGTH}+ characters)`} />
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
                  Back
                </Button>
                <Button
                  className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-medium"
                  endContent={<ArrowRight className="w-4 h-4" aria-hidden="true" />}
                  onPress={handleSaveProfileAndProceed}
                  isLoading={isSavingProfile}
                  isDisabled={!profileStepComplete || isSavingProfile}
                >
                  Next
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
                  What are you interested in?
                </h2>
                <p className="text-theme-muted text-sm mb-6">
                  Select categories that interest you. This helps us personalise
                  your experience and suggest relevant listings.
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
                      No categories available yet. You can skip this step and
                      set your interests later in Settings.
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
                      ? 'Select one or more, or skip this step'
                      : `${selectedInterests.length} selected`}
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
                  Back
                </Button>
                <div className="flex items-center gap-2">
                  <Button
                    variant="light"
                    className="text-theme-subtle"
                    onPress={goNextAnimated}
                    endContent={<SkipForward className="w-4 h-4" aria-hidden="true" />}
                  >
                    Skip
                  </Button>
                  <Button
                    className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-medium"
                    endContent={<ArrowRight className="w-4 h-4" aria-hidden="true" />}
                    onPress={handleSaveInterestsAndProceed}
                    isDisabled={selectedInterests.length === 0}
                  >
                    Next
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
                  I can offer
                </h2>
                <p className="text-theme-muted text-sm mb-4">
                  Select skills you can offer to others. We'll create listings for you.
                </p>

                {categoriesLoading ? (
                  <div className="flex items-center justify-center py-8">
                    <Spinner size="md" />
                  </div>
                ) : categories.length === 0 ? (
                  <p className="text-theme-subtle text-sm py-4">
                    No categories available. You can skip this step.
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
                    {skillOffers.length} skill{skillOffers.length !== 1 ? 's' : ''} to offer
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
                  I need help with
                </h2>
                <p className="text-theme-muted text-sm mb-4">
                  Select categories you need help with. We'll create request listings for you.
                </p>

                {categoriesLoading ? (
                  <div className="flex items-center justify-center py-8">
                    <Spinner size="md" />
                  </div>
                ) : categories.length === 0 ? (
                  <p className="text-theme-subtle text-sm py-4">
                    No categories available. You can skip this step.
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
                    {skillNeeds.length} skill{skillNeeds.length !== 1 ? 's' : ''} needed
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
                  Back
                </Button>
                <div className="flex items-center gap-2">
                  <Button
                    variant="light"
                    className="text-theme-subtle"
                    onPress={goNextAnimated}
                    endContent={<SkipForward className="w-4 h-4" aria-hidden="true" />}
                  >
                    Skip
                  </Button>
                  <Button
                    className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-medium"
                    endContent={<ArrowRight className="w-4 h-4" aria-hidden="true" />}
                    onPress={goNextAnimated}
                  >
                    Next
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
                  Review Your Setup
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
                      {bio || user?.bio || 'No bio yet'}
                    </p>
                    <button
                      type="button"
                      className="text-xs text-emerald-600 dark:text-emerald-400 hover:underline mt-1"
                      onClick={() => goToStep(2)}
                    >
                      Edit profile
                    </button>
                  </div>
                </div>

                <Divider className="my-4" />

                {/* Interests summary */}
                <div className="space-y-4">
                  <SummarySection
                    icon={<Heart className="w-4 h-4 text-rose-500" />}
                    title="Your Interests"
                    items={selectedInterests}
                    getCategoryName={getCategoryName}
                    chipColor="primary"
                    emptyText="None selected"
                    onEdit={() => goToStep(3)}
                  />

                  <Divider />

                  <SummarySection
                    icon={<HandHeart className="w-4 h-4 text-emerald-500" />}
                    title="Skills You Offer"
                    items={skillOffers}
                    getCategoryName={getCategoryName}
                    chipColor="success"
                    emptyText="None selected"
                    onEdit={() => goToStep(4)}
                  />

                  <Divider />

                  <SummarySection
                    icon={<HelpCircle className="w-4 h-4 text-amber-500" />}
                    title="Skills You Need"
                    items={skillNeeds}
                    getCategoryName={getCategoryName}
                    chipColor="warning"
                    emptyText="None selected"
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
                    We'll create {totalListingsToCreate} listing{totalListingsToCreate !== 1 ? 's' : ''} for you
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
                  Back
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
                      Skip for now
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
                    {totalListingsToCreate > 0 ? 'Complete Setup' : 'Finish'}
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
            <button
              type="button"
              className={`
                flex flex-col items-center gap-1 transition-all
                ${isClickable ? 'cursor-pointer' : 'cursor-default'}
              `}
              onClick={() => isClickable && onStepClick(step)}
              aria-label={`Step ${step}: ${STEP_LABELS[i]}${isCompleted ? ' (completed)' : isCurrent ? ' (current)' : ''}`}
              aria-current={isCurrent ? 'step' : undefined}
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
                {STEP_LABELS[i]}
              </span>
            </button>

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
  return (
    <div>
      <div className="flex items-center justify-between mb-2">
        <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
          {icon}
          {title}
        </h3>
        <button
          type="button"
          className="text-xs text-emerald-600 dark:text-emerald-400 hover:underline"
          onClick={onEdit}
        >
          Edit
        </button>
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
          {isOffer ? `I can help with ${name}` : `Looking for help with ${name}`}
        </p>
        <p className="text-xs text-theme-subtle">
          {isOffer ? 'Offer listing' : 'Request listing'}
        </p>
      </div>
    </div>
  );
}

export default OnboardingPage;
