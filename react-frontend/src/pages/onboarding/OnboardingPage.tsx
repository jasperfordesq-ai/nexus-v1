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
 *  3. Interests    - Select categories you're interested in
 *  4. Skills       - Mark which categories you can offer / need help with
 *  5. Confirm      - Summary + auto-create listings
 *
 * Route: /onboarding
 */

import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Progress,
  Spinner,
  Chip,
  Avatar,
  Textarea,
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

  // ── Redirect if fully completed (onboarding done + has photo + has bio) ───
  // Prevents showing Step 1 again after completion (component remount from
  // AuthContext change causes fresh state). Also handles browser back button.

  useEffect(() => {
    if (user?.onboarding_completed === true && user?.avatar_url && user?.bio) {
      navigate(tenantPath('/dashboard'), { replace: true });
    }
  }, [user?.onboarding_completed, user?.avatar_url, user?.bio, navigate, tenantPath]);

  // ── State ──────────────────────────────────────────────────────────────────

  const [currentStep, setCurrentStep] = useState(1);
  const [slideDirection, setSlideDirection] = useState(1);

  // Step 2: Profile photo + bio
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [isUploadingAvatar, setIsUploadingAvatar] = useState(false);
  const [isSavingProfile, setIsSavingProfile] = useState(false);
  const [bio, setBio] = useState(user?.bio || '');

  // Categories loaded from API
  const [categories, setCategories] = useState<Category[]>([]);
  const [categoriesLoading, setCategoriesLoading] = useState(false);

  // Step 3: Selected interest category IDs
  const [selectedInterests, setSelectedInterests] = useState<number[]>([]);

  // Step 4: Skill offers and needs
  const [skillOffers, setSkillOffers] = useState<number[]>([]);
  const [skillNeeds, setSkillNeeds] = useState<number[]>([]);

  // Submission state
  const [isSubmitting, setIsSubmitting] = useState(false);

  // ── Pre-populate bio from user context if available ──────────────────────

  useEffect(() => {
    if (user?.bio && !bio) {
      setBio(user.bio);
    }
  }, [user?.bio]);

  // ── If user has photo+bio but onboarding_completed is false, skip to step 3 ─

  useEffect(() => {
    if (user?.avatar_url && user?.bio && user?.bio.trim().length >= MIN_BIO_LENGTH && currentStep <= 2) {
      setCurrentStep(3);
    }
  }, []);

  // ── Load categories when reaching step 3 (was step 2) ───────────────────

  useEffect(() => {
    if (currentStep === 3 && categories.length === 0) {
      loadCategories();
    }
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

  const goNext = useCallback(() => {
    setCurrentStep((s) => Math.min(s + 1, TOTAL_STEPS));
  }, []);

  const goBack = useCallback(() => {
    setCurrentStep((s) => Math.max(s - 1, 1));
  }, []);

  const goNextAnimated = useCallback(() => {
    setSlideDirection(1);
    goNext();
  }, [goNext]);

  const goBackAnimated = useCallback(() => {
    setSlideDirection(-1);
    goBack();
  }, [goBack]);

  // ── Avatar upload handler (Step 2) ───────────────────────────────────────

  const handleAvatarUpload = useCallback(async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
      toast.error('Invalid file type', 'Please upload an image file (JPG, PNG or GIF)');
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      toast.error('File too large', 'Please upload an image smaller than 5MB');
      return;
    }

    try {
      setIsUploadingAvatar(true);
      const formData = new FormData();
      formData.append('avatar', file);

      const response = await api.upload<{ avatar_url: string }>('/v2/users/me/avatar', formData);

      if (response.success && response.data) {
        await refreshUser();
        toast.success('Photo uploaded', 'Your profile photo has been set');
      } else {
        toast.error('Upload failed', 'Failed to upload photo. Please try again.');
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
        offers: skillOffers,
        needs: skillNeeds,
      });

      if (!response.success) {
        toast.error('Setup failed', response.error || 'Something went wrong. Please try again.');
        return;
      }

      const listingsCreated = (response.data as { listings_created?: number })?.listings_created ?? 0;

      if (listingsCreated > 0) {
        toast.success(
          'Welcome aboard!',
          `${listingsCreated} listing${listingsCreated === 1 ? '' : 's'} created for you.`
        );
      } else {
        toast.success('Welcome aboard!', 'Your profile is all set up.');
      }

      // Refresh user state so ProtectedRoute sees onboarding_completed = true
      // This prevents redirect loop back to /onboarding
      await refreshUser();

      navigate(tenantPath('/dashboard'));
    } catch (error) {
      logError('Failed to complete onboarding', error);
      toast.error('Setup failed', 'Something went wrong. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  }, [skillOffers, skillNeeds, toast, navigate, tenantPath, refreshUser]);

  // ── Skip handler (skips interests/skills only — photo+bio already done) ──

  const handleSkip = useCallback(async () => {
    try {
      // Mark onboarding complete even when skipping interests/skills
      await api.post('/v2/onboarding/complete', { offers: [], needs: [] });
      // Refresh user state so ProtectedRoute sees onboarding_completed = true
      await refreshUser();
    } catch {
      // Non-critical, continue navigation
    }
    navigate(tenantPath('/dashboard'));
  }, [navigate, tenantPath, refreshUser]);

  // ── Animation variants ───────────────────────────────────────────────────

  const slideVariants = {
    enter: (direction: number) => ({
      x: direction > 0 ? 80 : -80,
      opacity: 0,
    }),
    center: { x: 0, opacity: 1 },
    exit: (direction: number) => ({
      x: direction < 0 ? 80 : -80,
      opacity: 0,
    }),
  };

  // ── Step labels ──────────────────────────────────────────────────────────

  const stepLabel = (step: number) => {
    switch (step) {
      case 1: return 'Welcome';
      case 2: return 'Your Profile';
      case 3: return 'Interests';
      case 4: return 'Skills';
      case 5: return 'Confirm';
      default: return '';
    }
  };

  // ── Derived state for Step 2 validation ──────────────────────────────────

  const hasAvatar = !!user?.avatar_url;
  const hasBio = bio.trim().length >= MIN_BIO_LENGTH;
  const profileStepComplete = hasAvatar && hasBio;

  // ── Render ───────────────────────────────────────────────────────────────

  // Don't render wizard if fully completed (redirect is pending)
  if (user?.onboarding_completed === true && user?.avatar_url && user?.bio) {
    return null;
  }

  return (
    <div className="max-w-2xl mx-auto py-6 space-y-6">
      {/* Title */}
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        className="text-center"
      >
        <h1 className="text-2xl font-bold text-theme-primary">Get Started</h1>
        <p className="text-theme-muted mt-1">
          Set up your profile in a few easy steps
        </p>
      </motion.div>

      {/* Progress Bar */}
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ delay: 0.1 }}
      >
        <div className="flex items-center justify-between mb-2">
          <span className="text-sm text-theme-subtle">
            Step {currentStep} of {TOTAL_STEPS}
          </span>
          <span className="text-sm text-theme-subtle">
            {stepLabel(currentStep)}
          </span>
        </div>
        <Progress
          value={(currentStep / TOTAL_STEPS) * 100}
          classNames={{
            indicator: 'bg-gradient-to-r from-emerald-500 to-teal-600',
            track: 'bg-theme-elevated',
          }}
          aria-label={`Step ${currentStep} of ${TOTAL_STEPS}`}
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
          transition={{ duration: 0.25, ease: 'easeInOut' }}
        >
          {/* ─── Step 1: Welcome ─── */}
          {currentStep === 1 && (
            <div className="space-y-6">
              {/* Hero */}
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

              {/* Benefit cards */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <BenefitCard
                  icon={<HandHeart className="w-6 h-6 text-emerald-500" />}
                  title="Find Help"
                  description="Discover community members offering the skills you need"
                />
                <BenefitCard
                  icon={<Heart className="w-6 h-6 text-rose-500" />}
                  title="Share Skills"
                  description="Offer your talents and earn time credits in return"
                />
                <BenefitCard
                  icon={<Sparkles className="w-6 h-6 text-amber-500" />}
                  title="Build Community"
                  description="Connect with neighbours and strengthen local bonds"
                />
              </div>

              {/* CTA */}
              <div className="flex justify-center">
                <Button
                  size="lg"
                  className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white px-8"
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
                <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
                  <UserCircle
                    className="w-5 h-5 text-emerald-600 dark:text-emerald-400"
                    aria-hidden="true"
                  />
                  Your Profile
                </h2>
                <p className="text-theme-muted text-sm mb-6">
                  Add a profile photo and tell the community a bit about yourself.
                  This helps other members recognise and trust you.
                </p>

                {/* Avatar upload */}
                <div className="flex flex-col items-center gap-4 mb-6">
                  <div className="relative">
                    <Avatar
                      src={resolveAvatarUrl(user?.avatar_url)}
                      name={user?.first_name || user?.name}
                      className="w-24 h-24 ring-4 ring-theme-default"
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
                      className="absolute bottom-0 right-0 rounded-full bg-emerald-500 text-white hover:bg-emerald-600 min-w-0 w-8 h-8"
                      onPress={() => fileInputRef.current?.click()}
                      isDisabled={isUploadingAvatar}
                      isLoading={isUploadingAvatar}
                      aria-label="Upload profile photo"
                    >
                      <Camera className="w-4 h-4" aria-hidden="true" />
                    </Button>
                  </div>

                  <div className="text-center">
                    {hasAvatar ? (
                      <p className="text-sm text-emerald-600 dark:text-emerald-400 flex items-center gap-1 justify-center">
                        <CheckCircle className="w-4 h-4" aria-hidden="true" />
                        Photo uploaded
                      </p>
                    ) : (
                      <p className="text-sm text-theme-muted">
                        Upload a profile photo (JPG, PNG or GIF, max 5MB)
                      </p>
                    )}
                  </div>
                </div>

                {/* Bio textarea */}
                <div className="space-y-2">
                  <Textarea
                    label="About you"
                    placeholder="Tell the community about yourself — your interests, skills, or what you're hoping to get from timebanking..."
                    value={bio}
                    onValueChange={setBio}
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

                {/* Validation summary */}
                <div className="mt-4 p-3 rounded-lg bg-theme-elevated">
                  <p className="text-xs font-medium text-theme-muted mb-2">
                    Required to continue:
                  </p>
                  <div className="space-y-1">
                    <div className="flex items-center gap-2 text-sm">
                      <CheckCircle
                        className={`w-4 h-4 ${hasAvatar ? 'text-emerald-500' : 'text-theme-subtle'}`}
                        aria-hidden="true"
                      />
                      <span className={hasAvatar ? 'text-theme-primary' : 'text-theme-subtle'}>
                        Profile photo
                      </span>
                    </div>
                    <div className="flex items-center gap-2 text-sm">
                      <CheckCircle
                        className={`w-4 h-4 ${hasBio ? 'text-emerald-500' : 'text-theme-subtle'}`}
                        aria-hidden="true"
                      />
                      <span className={hasBio ? 'text-theme-primary' : 'text-theme-subtle'}>
                        Bio ({MIN_BIO_LENGTH}+ characters)
                      </span>
                    </div>
                  </div>
                </div>
              </GlassCard>

              {/* Navigation — Next saves bio then proceeds */}
              <div className="flex items-center justify-between">
                <Button
                  variant="light"
                  className="text-theme-muted"
                  onPress={goBackAnimated}
                  startContent={
                    <ArrowLeft className="w-4 h-4" aria-hidden="true" />
                  }
                >
                  Back
                </Button>
                <Button
                  className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white"
                  endContent={
                    <ArrowRight className="w-4 h-4" aria-hidden="true" />
                  }
                  onPress={handleSaveProfileAndProceed}
                  isLoading={isSavingProfile}
                  isDisabled={!profileStepComplete || isSavingProfile}
                >
                  Next
                </Button>
              </div>
            </div>
          )}

          {/* ─── Step 3: Select Interests ─── */}
          {currentStep === 3 && (
            <div className="space-y-6">
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
                  <HelpCircle
                    className="w-5 h-5 text-emerald-600 dark:text-emerald-400"
                    aria-hidden="true"
                  />
                  What are you interested in?
                </h2>
                <p className="text-theme-muted text-sm mb-6">
                  Select the categories that interest you. This helps us
                  personalise your experience and suggest relevant listings.
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
                      No categories available. You can skip this step.
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
                          className="cursor-pointer transition-all"
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

                {/* Selection count / helper text */}
                {categories.length > 0 && (
                  <p className="text-xs text-theme-subtle mt-4">
                    {selectedInterests.length === 0
                      ? 'Select at least one category to continue'
                      : `${selectedInterests.length} selected`}
                  </p>
                )}
              </GlassCard>

              <StepNavigation
                onBack={goBackAnimated}
                onNext={goNextAnimated}
                nextDisabled={selectedInterests.length === 0}
              />
            </div>
          )}

          {/* ─── Step 4: Your Skills ─── */}
          {currentStep === 4 && (
            <div className="space-y-6">
              {/* Offers section */}
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
                  <HandHeart
                    className="w-5 h-5 text-emerald-600 dark:text-emerald-400"
                    aria-hidden="true"
                  />
                  I can offer
                </h2>
                <p className="text-theme-muted text-sm mb-4">
                  Which of your interests can you help others with?
                  We'll create offer listings for you.
                </p>

                <div className="flex flex-wrap gap-2">
                  {selectedInterests.length > 0 ? (
                    selectedInterests.map((catId) => {
                      const isOffer = skillOffers.includes(catId);
                      return (
                        <Chip
                          key={catId}
                          variant={isOffer ? 'solid' : 'bordered'}
                          color={isOffer ? 'success' : 'default'}
                          className="cursor-pointer transition-all"
                          onClick={() => toggleOffer(catId)}
                          aria-pressed={isOffer}
                          role="button"
                          tabIndex={0}
                          onKeyDown={(e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                              e.preventDefault();
                              toggleOffer(catId);
                            }
                          }}
                        >
                          {getCategoryName(catId)}
                        </Chip>
                      );
                    })
                  ) : (
                    <p className="text-theme-subtle text-sm">
                      No interests selected. Go back to pick some categories first.
                    </p>
                  )}
                </div>

                {skillOffers.length > 0 && (
                  <p className="text-xs text-theme-subtle mt-3">
                    {skillOffers.length} skill{skillOffers.length !== 1 ? 's' : ''} to offer
                  </p>
                )}
              </GlassCard>

              {/* Needs section */}
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
                  <HelpCircle
                    className="w-5 h-5 text-amber-600 dark:text-amber-400"
                    aria-hidden="true"
                  />
                  I need help with
                </h2>
                <p className="text-theme-muted text-sm mb-4">
                  Which categories do you need help with?
                  We'll create request listings for you.
                </p>

                <div className="flex flex-wrap gap-2">
                  {selectedInterests.length > 0 ? (
                    selectedInterests.map((catId) => {
                      const isNeed = skillNeeds.includes(catId);
                      return (
                        <Chip
                          key={catId}
                          variant={isNeed ? 'solid' : 'bordered'}
                          color={isNeed ? 'warning' : 'default'}
                          className="cursor-pointer transition-all"
                          onClick={() => toggleNeed(catId)}
                          aria-pressed={isNeed}
                          role="button"
                          tabIndex={0}
                          onKeyDown={(e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                              e.preventDefault();
                              toggleNeed(catId);
                            }
                          }}
                        >
                          {getCategoryName(catId)}
                        </Chip>
                      );
                    })
                  ) : (
                    <p className="text-theme-subtle text-sm">
                      No interests selected. Go back to pick some categories first.
                    </p>
                  )}
                </div>

                {skillNeeds.length > 0 && (
                  <p className="text-xs text-theme-subtle mt-3">
                    {skillNeeds.length} skill{skillNeeds.length !== 1 ? 's' : ''} needed
                  </p>
                )}
              </GlassCard>

              <StepNavigation
                onBack={goBackAnimated}
                onNext={goNextAnimated}
              />
            </div>
          )}

          {/* ─── Step 5: Confirm + Create ─── */}
          {currentStep === 5 && (
            <div className="space-y-6">
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
                  <CheckCircle
                    className="w-5 h-5 text-emerald-600 dark:text-emerald-400"
                    aria-hidden="true"
                  />
                  Review Your Setup
                </h2>
                <p className="text-theme-muted text-sm mb-6">
                  Here is a summary of your selections. We'll create listings
                  to get you started right away.
                </p>

                <div className="space-y-4">
                  {/* Interests summary */}
                  <div>
                    <h3 className="text-sm font-medium text-theme-muted mb-2 flex items-center gap-2">
                      <Sparkles className="w-4 h-4" aria-hidden="true" />
                      Your Interests
                    </h3>
                    <div className="flex flex-wrap gap-2">
                      {selectedInterests.length > 0 ? (
                        selectedInterests.map((catId) => (
                          <Chip
                            key={catId}
                            size="sm"
                            variant="flat"
                            color="primary"
                          >
                            {getCategoryName(catId)}
                          </Chip>
                        ))
                      ) : (
                        <p className="text-theme-subtle text-sm">None selected</p>
                      )}
                    </div>
                  </div>

                  <div className="border-t border-theme-default" />

                  {/* Offers summary */}
                  <div>
                    <h3 className="text-sm font-medium text-theme-muted mb-2 flex items-center gap-2">
                      <HandHeart className="w-4 h-4" aria-hidden="true" />
                      Skills You Can Offer
                    </h3>
                    <div className="flex flex-wrap gap-2">
                      {skillOffers.length > 0 ? (
                        skillOffers.map((catId) => (
                          <Chip
                            key={catId}
                            size="sm"
                            variant="flat"
                            color="success"
                          >
                            {getCategoryName(catId)}
                          </Chip>
                        ))
                      ) : (
                        <p className="text-theme-subtle text-sm">None selected</p>
                      )}
                    </div>
                  </div>

                  <div className="border-t border-theme-default" />

                  {/* Needs summary */}
                  <div>
                    <h3 className="text-sm font-medium text-theme-muted mb-2 flex items-center gap-2">
                      <HelpCircle className="w-4 h-4" aria-hidden="true" />
                      Skills You Need
                    </h3>
                    <div className="flex flex-wrap gap-2">
                      {skillNeeds.length > 0 ? (
                        skillNeeds.map((catId) => (
                          <Chip
                            key={catId}
                            size="sm"
                            variant="flat"
                            color="warning"
                          >
                            {getCategoryName(catId)}
                          </Chip>
                        ))
                      ) : (
                        <p className="text-theme-subtle text-sm">None selected</p>
                      )}
                    </div>
                  </div>
                </div>
              </GlassCard>

              {/* Listings preview */}
              {totalListingsToCreate > 0 && (
                <GlassCard className="p-6">
                  <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
                    <ListChecks
                      className="w-5 h-5 text-emerald-600 dark:text-emerald-400"
                      aria-hidden="true"
                    />
                    Listings We'll Create
                  </h2>
                  <p className="text-theme-muted text-sm mb-4">
                    We'll create {totalListingsToCreate} listing{totalListingsToCreate !== 1 ? 's' : ''} for you:
                  </p>
                  <div className="space-y-2">
                    {skillOffers.map((catId) => (
                      <div
                        key={`offer-${catId}`}
                        className="flex items-center gap-3 p-3 rounded-lg bg-theme-elevated"
                      >
                        <div className="p-2 rounded-lg bg-emerald-500/20 flex-shrink-0">
                          <HandHeart
                            className="w-4 h-4 text-emerald-500"
                            aria-hidden="true"
                          />
                        </div>
                        <div className="min-w-0">
                          <p className="font-medium text-theme-primary text-sm">
                            I can help with {getCategoryName(catId)}
                          </p>
                          <p className="text-xs text-theme-subtle">Offer listing</p>
                        </div>
                      </div>
                    ))}
                    {skillNeeds.map((catId) => (
                      <div
                        key={`need-${catId}`}
                        className="flex items-center gap-3 p-3 rounded-lg bg-theme-elevated"
                      >
                        <div className="p-2 rounded-lg bg-amber-500/20 flex-shrink-0">
                          <HelpCircle
                            className="w-4 h-4 text-amber-500"
                            aria-hidden="true"
                          />
                        </div>
                        <div className="min-w-0">
                          <p className="font-medium text-theme-primary text-sm">
                            Looking for help with {getCategoryName(catId)}
                          </p>
                          <p className="text-xs text-theme-subtle">Request listing</p>
                        </div>
                      </div>
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
                  startContent={
                    <ArrowLeft className="w-4 h-4" aria-hidden="true" />
                  }
                >
                  Back
                </Button>

                <div className="flex items-center gap-3">
                  <Button
                    variant="light"
                    className="text-theme-subtle"
                    onPress={handleSkip}
                  >
                    Skip for now
                  </Button>
                  <Button
                    size="lg"
                    className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white"
                    onPress={handleComplete}
                    isLoading={isSubmitting}
                    startContent={
                      !isSubmitting && (
                        <Rocket className="w-5 h-5" aria-hidden="true" />
                      )
                    }
                  >
                    Complete Setup
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
// Sub-components
// ─────────────────────────────────────────────────────────────────────────────

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
      <p className="text-xs text-theme-subtle">{description}</p>
    </GlassCard>
  );
}

interface StepNavigationProps {
  onBack: () => void;
  onNext: () => void;
  nextLabel?: string;
  nextDisabled?: boolean;
  isLoading?: boolean;
}

function StepNavigation({
  onBack,
  onNext,
  nextLabel = 'Next',
  nextDisabled = false,
  isLoading,
}: StepNavigationProps) {
  return (
    <div className="flex items-center justify-between">
      <Button
        variant="light"
        className="text-theme-muted"
        onPress={onBack}
        startContent={
          <ArrowLeft className="w-4 h-4" aria-hidden="true" />
        }
      >
        Back
      </Button>
      <Button
        className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white"
        endContent={
          <ArrowRight className="w-4 h-4" aria-hidden="true" />
        }
        onPress={onNext}
        isLoading={isLoading}
        isDisabled={nextDisabled}
      >
        {nextLabel}
      </Button>
    </div>
  );
}

export default OnboardingPage;
