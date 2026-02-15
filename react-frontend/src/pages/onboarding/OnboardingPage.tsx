/**
 * Onboarding Page - Post-registration 4-step wizard
 *
 * Steps:
 *  1. Welcome     - Benefits overview, community introduction
 *  2. Interests   - Select categories you're interested in
 *  3. Skills      - Mark which categories you can offer / need help with
 *  4. Confirm     - Summary + auto-create listings
 *
 * Route: /onboarding
 */

import { useState, useEffect, useCallback, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Progress,
  Spinner,
  Chip,
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
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant, useAuth } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

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

const TOTAL_STEPS = 4;

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function OnboardingPage() {
  usePageTitle('Get Started');
  const navigate = useNavigate();
  const { tenantPath, tenant } = useTenant();
  const toast = useToast();
  const { refreshUser } = useAuth();

  const tenantName = tenant?.branding?.name || tenant?.name || 'our community';

  // ── State ──────────────────────────────────────────────────────────────────

  const [currentStep, setCurrentStep] = useState(1);
  const [slideDirection, setSlideDirection] = useState(1);

  // Categories loaded from API
  const [categories, setCategories] = useState<Category[]>([]);
  const [categoriesLoading, setCategoriesLoading] = useState(false);

  // Step 2: Selected interest category IDs
  const [selectedInterests, setSelectedInterests] = useState<number[]>([]);

  // Step 3: Skill offers and needs
  const [skillOffers, setSkillOffers] = useState<number[]>([]);
  const [skillNeeds, setSkillNeeds] = useState<number[]>([]);

  // Submission state
  const [isSubmitting, setIsSubmitting] = useState(false);

  // ── Load categories when reaching step 2 ──────────────────────────────────

  useEffect(() => {
    if (currentStep === 2 && categories.length === 0) {
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

  // ── Interest toggling (Step 2) ─────────────────────────────────────────────

  const toggleInterest = useCallback((categoryId: number) => {
    setSelectedInterests((prev) =>
      prev.includes(categoryId)
        ? prev.filter((id) => id !== categoryId)
        : [...prev, categoryId]
    );
  }, []);

  // ── Skill toggling (Step 3) ────────────────────────────────────────────────

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

  // ── Category name lookup ───────────────────────────────────────────────────

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

  // ── Completion handler ─────────────────────────────────────────────────────

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

  // ── Skip handler ───────────────────────────────────────────────────────────

  const handleSkip = useCallback(async () => {
    try {
      // Mark onboarding complete even when skipping
      await api.post('/v2/onboarding/complete', { offers: [], needs: [] });
      // Refresh user state so ProtectedRoute sees onboarding_completed = true
      await refreshUser();
    } catch {
      // Non-critical, continue navigation
    }
    navigate(tenantPath('/dashboard'));
  }, [navigate, tenantPath, refreshUser]);

  // ── Animation variants ─────────────────────────────────────────────────────

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

  // ── Step labels ────────────────────────────────────────────────────────────

  const stepLabel = (step: number) => {
    switch (step) {
      case 1: return 'Welcome';
      case 2: return 'Interests';
      case 3: return 'Skills';
      case 4: return 'Confirm';
      default: return '';
    }
  };

  // ── Render ─────────────────────────────────────────────────────────────────

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

          {/* ─── Step 2: Select Interests ─── */}
          {currentStep === 2 && (
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

          {/* ─── Step 3: Your Skills ─── */}
          {currentStep === 3 && (
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

          {/* ─── Step 4: Confirm + Create ─── */}
          {currentStep === 4 && (
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
