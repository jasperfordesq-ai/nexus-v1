// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Employer Brand Page - View an employer's open jobs, branding, and culture.
 * Route: /jobs/employers/:userId
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Chip, Avatar, Textarea, Slider, Progress } from '@heroui/react';
import { Briefcase, MapPin, Wifi, Building2, ChevronRight, Star, MessageSquare } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { LoadingScreen } from '@/components/feedback';
import { Breadcrumbs } from '@/components/navigation';
import { useTenant, useAuth } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface EmployerProfile {
  id: number;
  name: string;
  avatar_url: string | null;
  tagline: string | null;
  company_size: string | null;
  open_jobs_count: number;
}

interface EmployerJob {
  id: number;
  title: string;
  type: string;
  commitment: string;
  location: string | null;
  is_remote: boolean;
  salary_min: number | null;
  salary_max: number | null;
  salary_currency: string | null;
  salary_negotiable: boolean;
  deadline: string | null;
  benefits: string[] | null;
  created_at: string;
}

interface ReviewerInfo {
  id: number;
  name: string;
  avatar_url: string | null;
}

interface EmployerReview {
  id: number;
  rating: number;
  comment: string;
  dimensions: { respect: number; communication: number; flexibility: number; impact: number } | null;
  reviewer: ReviewerInfo | null;
  created_at: string;
}

interface ReviewStats {
  average_rating: number | null;
  total_reviews: number;
  distribution: Record<number, number>;
}

/** Render 1-5 stars with filled/unfilled state */
function StarRating({ rating, size = 16, interactive, onChange }: {
  rating: number;
  size?: number;
  interactive?: boolean;
  onChange?: (r: number) => void;
}) {
  return (
    <span className="inline-flex gap-0.5">
      {[1, 2, 3, 4, 5].map((v) => (
        <Star
          key={v}
          size={size}
          className={`${v <= rating ? 'text-warning fill-warning' : 'text-default-300'} ${interactive ? 'cursor-pointer hover:scale-110 transition-transform' : ''}`}
          onClick={interactive && onChange ? () => onChange(v) : undefined}
          aria-label={interactive ? `${v} star${v > 1 ? 's' : ''}` : undefined}
        />
      ))}
    </span>
  );
}

export function EmployerBrandPage() {
  const { t } = useTranslation('jobs');
  const { userId } = useParams<{ userId: string }>();
  const { tenantPath } = useTenant();
  const { user, isAuthenticated } = useAuth();
  const abortRef = useRef<AbortController | null>(null);

  const [employer, setEmployer] = useState<EmployerProfile | null>(null);
  const [jobs, setJobs] = useState<EmployerJob[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Reviews state
  const [reviews, setReviews] = useState<EmployerReview[]>([]);
  const [reviewStats, setReviewStats] = useState<ReviewStats | null>(null);
  const [showReviewForm, setShowReviewForm] = useState(false);
  const [reviewRating, setReviewRating] = useState(0);
  const [reviewComment, setReviewComment] = useState('');
  const [dimRespect, setDimRespect] = useState(3);
  const [dimCommunication, setDimCommunication] = useState(3);
  const [dimFlexibility, setDimFlexibility] = useState(3);
  const [dimImpact, setDimImpact] = useState(3);
  const [submittingReview, setSubmittingReview] = useState(false);
  const [reviewError, setReviewError] = useState<string | null>(null);
  const [hasReviewed, setHasReviewed] = useState(false);

  usePageTitle(employer?.name ?? t('employer.page_title', 'Employer Profile'));

  const loadReviews = useCallback(async () => {
    if (!userId) return;
    try {
      const res = await api.get<{ reviews: EmployerReview[]; stats: ReviewStats }>(`/v2/jobs/employer-reviews/${userId}`);
      if (res.success && res.data) {
        const payload = 'data' in (res.data as Record<string, unknown>) && !Array.isArray(res.data)
          ? (res.data as unknown as { data: { reviews: EmployerReview[]; stats: ReviewStats } }).data
          : res.data;
        setReviews(payload.reviews ?? []);
        setReviewStats(payload.stats ?? null);
        // Check if current user already reviewed
        if (user?.id && payload.reviews?.some((r: EmployerReview) => r.reviewer?.id === user.id)) {
          setHasReviewed(true);
        }
      }
    } catch (err) {
      logError('EmployerBrandPage: reviews load failed', err);
    }
  }, [userId, user?.id]);

  const submitReview = useCallback(async () => {
    if (!userId || reviewRating < 1) return;
    setSubmittingReview(true);
    setReviewError(null);
    try {
      const res = await api.post('/v2/jobs/employer-reviews', {
        employer_id: parseInt(userId),
        rating: reviewRating,
        comment: reviewComment,
        respect: dimRespect,
        communication: dimCommunication,
        flexibility: dimFlexibility,
        impact: dimImpact,
      });
      if (res.success) {
        setShowReviewForm(false);
        setHasReviewed(true);
        setReviewRating(0);
        setReviewComment('');
        loadReviews();
      } else {
        setReviewError((res as { error?: string }).error ?? t('employer.review_submit_failed', { defaultValue: 'Failed to submit review' }));
      }
    } catch (err) {
      logError('EmployerBrandPage: review submit failed', err);
      setReviewError(t('employer.review_submit_failed', { defaultValue: 'Failed to submit review' }));
    } finally {
      setSubmittingReview(false);
    }
  }, [userId, reviewRating, reviewComment, dimRespect, dimCommunication, dimFlexibility, dimImpact, loadReviews]);

  const loadData = useCallback(async () => {
    if (!userId) return;
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      setIsLoading(true);
      setError(null);

      const jobsRes = await api.get<{ data: EmployerJob[] }>(`/v2/jobs?user_id=${userId}&status=open&per_page=50`);

      if (controller.signal.aborted) return;

      const jobList = jobsRes.success && jobsRes.data
        ? (Array.isArray(jobsRes.data) ? jobsRes.data as EmployerJob[] : ((jobsRes.data as { data: EmployerJob[] }).data ?? []))
        : [];

      setJobs(jobList);

      if (jobList.length > 0) {
        setEmployer({
          id: parseInt(userId),
          name: t('employer.employer_jobs', 'Employer Jobs'),
          avatar_url: null,
          tagline: null,
          company_size: null,
          open_jobs_count: jobList.length,
        });
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('EmployerBrandPage: load failed', err);
      setError(t('employer.error', 'Unable to load employer profile'));
    } finally {
      setIsLoading(false);
    }
  }, [userId, t]);

  useEffect(() => {
    loadData();
    loadReviews();
    return () => abortRef.current?.abort();
  }, [loadData, loadReviews]);

  if (isLoading) return <LoadingScreen />;
  if (error) {
    return (
      <div className="max-w-4xl mx-auto px-4 py-8">
        <GlassCard className="p-8 text-center">
          <p className="text-theme-muted">{error}</p>
        </GlassCard>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto px-4 py-6 space-y-4">
      <PageMeta title={t('page_meta.employer_brand.title')} noIndex />
      <Breadcrumbs items={[
        { label: t('nav.jobs', 'Jobs'), href: '/jobs' },
        { label: employer?.name ?? t('employer.page_title', 'Employer') },
      ]} />

      {/* Employer header */}
      <GlassCard className="p-6">
        <div className="flex items-start gap-4">
          <Avatar
            src={employer?.avatar_url ?? undefined}
            name={employer?.name}
            size="lg"
            className="shrink-0"
          />
          <div className="flex-1 min-w-0">
            <h1 className="text-xl font-bold text-theme-primary">{employer?.name}</h1>
            {employer?.tagline && (
              <p className="text-sm text-theme-secondary italic mt-1">&ldquo;{employer.tagline}&rdquo;</p>
            )}
            <div className="flex flex-wrap gap-2 mt-2">
              {employer?.company_size && (
                <Chip size="sm" variant="flat" startContent={<Building2 size={12} />}>
                  {t('employer.employees', '{{size}} employees', { size: employer.company_size })}
                </Chip>
              )}
              <Chip size="sm" variant="flat" color="primary" startContent={<Briefcase size={12} />}>
                {jobs.length} {t('employer.open_roles', 'open roles')}
              </Chip>
            </div>
          </div>
        </div>
      </GlassCard>

      {/* Job listings */}
      <div className="space-y-3">
        <h2 className="text-base font-semibold text-theme-primary px-1">
          {t('employer.open_roles_heading', 'Open Roles')}
        </h2>
        {jobs.length === 0 ? (
          <GlassCard className="p-8 text-center">
            <Briefcase size={32} className="mx-auto text-theme-muted mb-2" />
            <p className="text-theme-muted">{t('employer.no_roles', 'No open roles at this time')}</p>
          </GlassCard>
        ) : (
          <motion.div
            initial="hidden"
            animate="visible"
            variants={{ hidden: {}, visible: { transition: { staggerChildren: 0.05 } } }}
            className="space-y-3"
          >
            {jobs.map((job) => (
              <motion.div
                key={job.id}
                variants={{ hidden: { opacity: 0, y: 16 }, visible: { opacity: 1, y: 0 } }}
              >
                <GlassCard className="p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex-1 min-w-0">
                      <Link
                        to={tenantPath(`/jobs/${job.id}`)}
                        className="font-semibold text-theme-primary hover:text-primary transition-colors line-clamp-1"
                      >
                        {job.title}
                      </Link>
                      <div className="flex flex-wrap items-center gap-2 mt-1 text-xs text-theme-muted">
                        {job.is_remote ? (
                          <span className="flex items-center gap-1">
                            <Wifi size={11} aria-hidden="true" />
                            {t('remote', 'Remote')}
                          </span>
                        ) : job.location ? (
                          <span className="flex items-center gap-1">
                            <MapPin size={11} aria-hidden="true" />
                            {job.location}
                          </span>
                        ) : null}
                        <Chip size="sm" variant="flat">
                          {t(`commitment.${job.commitment}`, job.commitment)}
                        </Chip>
                        <Chip
                          size="sm"
                          variant="flat"
                          color={job.type === 'paid' ? 'primary' : job.type === 'volunteer' ? 'success' : 'secondary'}
                        >
                          {t(`type.${job.type}`, job.type)}
                        </Chip>
                        {(job.salary_min || job.salary_max) && (
                          <span>
                            {job.salary_currency ?? '\u20ac'}
                            {job.salary_min?.toLocaleString()}
                            {job.salary_max ? ` \u2013 ${job.salary_max.toLocaleString()}` : '+'}
                          </span>
                        )}
                        {job.salary_negotiable && !job.salary_min && (
                          <span>{t('salary.negotiable', 'Negotiable')}</span>
                        )}
                      </div>
                      {job.benefits && job.benefits.length > 0 && (
                        <div className="flex flex-wrap gap-1 mt-2">
                          {job.benefits.slice(0, 4).map((b, i) => (
                            <Chip key={i} size="sm" variant="dot" color="success">{b}</Chip>
                          ))}
                        </div>
                      )}
                    </div>
                    <Button
                      as={Link}
                      to={tenantPath(`/jobs/${job.id}`)}
                      size="sm"
                      variant="flat"
                      color="primary"
                      endContent={<ChevronRight size={14} />}
                    >
                      {t('apply.view', 'View')}
                    </Button>
                  </div>
                </GlassCard>
              </motion.div>
            ))}
          </motion.div>
        )}
      </div>

      {/* Employer Reviews Section */}
      <div className="space-y-3">
        <div className="flex items-center justify-between px-1">
          <h2 className="text-base font-semibold text-theme-primary">
            {t('employer.reviews_heading', 'Employer Reviews')}
          </h2>
          {isAuthenticated && !hasReviewed && !showReviewForm && (
            <Button
              size="sm"
              color="primary"
              variant="flat"
              startContent={<MessageSquare size={14} />}
              onPress={() => setShowReviewForm(true)}
            >
              {t('employer.leave_review', 'Leave a Review')}
            </Button>
          )}
        </div>

        {/* Stats card */}
        {reviewStats && reviewStats.total_reviews > 0 && (
          <GlassCard className="p-5">
            <div className="flex flex-col sm:flex-row gap-6">
              {/* Average rating */}
              <div className="flex flex-col items-center gap-1 min-w-[100px]">
                <span className="text-3xl font-bold text-theme-primary">
                  {reviewStats.average_rating ?? '—'}
                </span>
                <StarRating rating={Math.round(reviewStats.average_rating ?? 0)} size={18} />
                <span className="text-xs text-theme-muted">
                  {reviewStats.total_reviews} {t('employer.reviews_count', 'reviews')}
                </span>
              </div>
              {/* Distribution */}
              <div className="flex-1 space-y-1.5">
                {[5, 4, 3, 2, 1].map((star) => {
                  const count = reviewStats.distribution[star] ?? 0;
                  const pct = reviewStats.total_reviews > 0 ? (count / reviewStats.total_reviews) * 100 : 0;
                  return (
                    <div key={star} className="flex items-center gap-2 text-xs">
                      <span className="w-3 text-right text-theme-secondary">{star}</span>
                      <Star size={11} className="text-warning fill-warning shrink-0" />
                      <Progress
                        size="sm"
                        value={pct}
                        color="warning"
                        className="flex-1"
                        aria-label={`${star} star${star > 1 ? 's' : ''}: ${count}`}
                      />
                      <span className="w-6 text-right text-theme-muted">{count}</span>
                    </div>
                  );
                })}
              </div>
            </div>
          </GlassCard>
        )}

        {/* Review form */}
        {showReviewForm && (
          <GlassCard className="p-5 space-y-4">
            <h3 className="text-sm font-semibold text-theme-primary">
              {t('employer.write_review', 'Write a Review')}
            </h3>

            {reviewError && (
              <div className="text-sm text-danger bg-danger-50 rounded-lg p-3">{reviewError}</div>
            )}

            {/* Overall rating */}
            <div>
              <label className="text-xs font-medium text-theme-secondary mb-1 block">
                {t('employer.overall_rating', 'Overall Rating')}
              </label>
              <StarRating rating={reviewRating} size={24} interactive onChange={setReviewRating} />
            </div>

            {/* Dimension sliders */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Slider
                label={t('employer.dim_respect', 'Respect')}
                step={1}
                minValue={1}
                maxValue={5}
                value={dimRespect}
                onChange={(v) => setDimRespect(v as number)}
                size="sm"
                color="primary"
                showSteps
                marks={[{ value: 1, label: '1' }, { value: 3, label: '3' }, { value: 5, label: '5' }]}
              />
              <Slider
                label={t('employer.dim_communication', 'Communication')}
                step={1}
                minValue={1}
                maxValue={5}
                value={dimCommunication}
                onChange={(v) => setDimCommunication(v as number)}
                size="sm"
                color="primary"
                showSteps
                marks={[{ value: 1, label: '1' }, { value: 3, label: '3' }, { value: 5, label: '5' }]}
              />
              <Slider
                label={t('employer.dim_flexibility', 'Flexibility')}
                step={1}
                minValue={1}
                maxValue={5}
                value={dimFlexibility}
                onChange={(v) => setDimFlexibility(v as number)}
                size="sm"
                color="primary"
                showSteps
                marks={[{ value: 1, label: '1' }, { value: 3, label: '3' }, { value: 5, label: '5' }]}
              />
              <Slider
                label={t('employer.dim_impact', 'Community Impact')}
                step={1}
                minValue={1}
                maxValue={5}
                value={dimImpact}
                onChange={(v) => setDimImpact(v as number)}
                size="sm"
                color="primary"
                showSteps
                marks={[{ value: 1, label: '1' }, { value: 3, label: '3' }, { value: 5, label: '5' }]}
              />
            </div>

            {/* Comment */}
            <Textarea
              label={t('employer.review_comment', 'Comment')}
              placeholder={t('employer.review_comment_placeholder', 'Share your experience working with this employer...')}
              value={reviewComment}
              onValueChange={setReviewComment}
              variant="bordered"
              minRows={3}
              maxRows={6}
            />

            {/* Actions */}
            <div className="flex gap-2 justify-end">
              <Button
                size="sm"
                variant="flat"
                onPress={() => { setShowReviewForm(false); setReviewError(null); }}
              >
                {t('common:cancel', 'Cancel')}
              </Button>
              <Button
                size="sm"
                color="primary"
                isDisabled={reviewRating < 1}
                isLoading={submittingReview}
                onPress={submitReview}
              >
                {t('employer.submit_review', 'Submit Review')}
              </Button>
            </div>
          </GlassCard>
        )}

        {/* Review cards */}
        {reviews.length === 0 && !showReviewForm ? (
          <GlassCard className="p-8 text-center">
            <Star size={32} className="mx-auto text-theme-muted mb-2" />
            <p className="text-theme-muted">{t('employer.no_reviews', 'No reviews yet')}</p>
          </GlassCard>
        ) : (
          <motion.div
            initial="hidden"
            animate="visible"
            variants={{ hidden: {}, visible: { transition: { staggerChildren: 0.05 } } }}
            className="space-y-3"
          >
            {reviews.map((review) => (
              <motion.div
                key={review.id}
                variants={{ hidden: { opacity: 0, y: 16 }, visible: { opacity: 1, y: 0 } }}
              >
                <GlassCard className="p-4">
                  <div className="flex items-start gap-3">
                    <Avatar
                      src={review.reviewer?.avatar_url ?? undefined}
                      name={review.reviewer?.name}
                      size="sm"
                      className="shrink-0"
                    />
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-sm font-semibold text-theme-primary">
                          {review.reviewer?.name ?? t('employer.anonymous', 'Anonymous')}
                        </span>
                        <StarRating rating={review.rating} size={13} />
                        <span className="text-xs text-theme-muted">
                          {review.created_at ? new Date(review.created_at).toLocaleDateString() : ''}
                        </span>
                      </div>
                      {review.comment && (
                        <p className="text-sm text-theme-secondary mt-1">{review.comment}</p>
                      )}
                      {review.dimensions && (
                        <div className="flex flex-wrap gap-2 mt-2">
                          {Object.entries(review.dimensions).map(([key, val]) => (
                            <Chip key={key} size="sm" variant="flat">
                              {t(`employer.dim_${key}`, key)}: {val}/5
                            </Chip>
                          ))}
                        </div>
                      )}
                    </div>
                  </div>
                </GlassCard>
              </motion.div>
            ))}
          </motion.div>
        )}
      </div>
    </div>
  );
}

export default EmployerBrandPage;
