import { Select, SelectItem, GlassCard, Button, SearchField, Switch, Tabs, Tab, CardRowsSkeleton } from '@/components/ui';
import { Chip as HeroChip, ToggleButton, ToggleButtonGroup } from '@/components/ui';
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Jobs Page - Community job vacancies listing with type/commitment filtering
 *
 * Features:
 * - Filter by type (paid/volunteer/timebank) and commitment
 * - Free text search with debounce
 * - Cursor-based pagination
 * - Job cards with type chips, location, deadline, salary, featured badge
 * - Post vacancy button for authenticated users
 * - Saved jobs tab (J1)
 * - Featured jobs appear first (J10)
 * - Salary display on cards with Intl.NumberFormat (J9)
 * - Sort dropdown (newest, deadline, salary)
 * - Remote-only toggle filter
 * - Deadline countdown chips (within 7 days / closed)
 * - Skills match percentage badge
 * - Featured jobs visual distinction (ring + gradient bg)
 */

import { useState, useEffect, useCallback, useRef, memo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from '@/lib/motion';

import Briefcase from 'lucide-react/icons/briefcase';
import Plus from 'lucide-react/icons/plus';
import MapPin from 'lucide-react/icons/map-pin';
import Clock from 'lucide-react/icons/clock';
import Eye from 'lucide-react/icons/eye';
import FileText from 'lucide-react/icons/file-text';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import ChevronRight from 'lucide-react/icons/chevron-right';
import Wifi from 'lucide-react/icons/wifi';
import DollarSign from 'lucide-react/icons/dollar-sign';
import Heart from 'lucide-react/icons/heart';
import Timer from 'lucide-react/icons/timer';
import Bookmark from 'lucide-react/icons/bookmark';
import BookmarkCheck from 'lucide-react/icons/bookmark-check';
import Bell from 'lucide-react/icons/bell';
import Star from 'lucide-react/icons/star';
import Edit from 'lucide-react/icons/square-pen';
import Rocket from 'lucide-react/icons/rocket';
import ArrowUpDown from 'lucide-react/icons/arrow-up-down';
import TrendingUp from 'lucide-react/icons/trending-up';
import { useTranslation } from 'react-i18next';
import { SafeHtml } from '@/components/ui/SafeHtml';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo/PageMeta';
import type { JobConfig } from '@/types';

interface JobVacancy {
  id: number;
  title: string;
  description: string;
  location: string | null;
  is_remote: boolean;
  type: 'paid' | 'volunteer' | 'timebank';
  commitment: 'full_time' | 'part_time' | 'flexible' | 'one_off';
  category: string | null;
  skills: string[];
  hours_per_week: number | null;
  time_credits: number | null;
  deadline: string | null;
  status: string;
  views_count: number;
  applications_count: number;
  created_at: string;
  creator: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
  organization: {
    id: number;
    name: string;
    logo_url: string | null;
  } | null;
  has_applied: boolean;
  application_status: string | null;
  is_saved: boolean;
  is_featured: boolean;
  salary_min: number | null;
  salary_max: number | null;
  salary_type: string | null;
  salary_currency: string | null;
  salary_negotiable: boolean;
  match_percentage?: number | null;
}

const ITEMS_PER_PAGE = 20;
const SEARCH_DEBOUNCE_MS = 300;

const TYPE_FILTERS = [
  { id: 'all', icon: Briefcase, color: 'default' as const },
  { id: 'paid', icon: DollarSign, color: 'success' as const },
  { id: 'volunteer', icon: Heart, color: 'secondary' as const },
  { id: 'timebank', icon: Timer, color: 'primary' as const },
] as const;

const COMMITMENT_FILTERS = [
  { id: 'all' },
  { id: 'full_time' },
  { id: 'part_time' },
  { id: 'flexible' },
  { id: 'one_off' },
] as const;

export function JobsPage() {
  const { t } = useTranslation('jobs');
  usePageTitle(t('title'));
  const { isAuthenticated } = useAuth();
  const { tenantPath, hasFeature, jobConfig: tenantJobConfig } = useTenant();
  const jobConfig: Partial<JobConfig> = tenantJobConfig ?? {};
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  const [vacancies, setVacancies] = useState<JobVacancy[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(true);
  const cursorRef = useRef<string | null>(null);
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [debouncedQuery, setDebouncedQuery] = useState(searchQuery);
  const [selectedType, setSelectedType] = useState(searchParams.get('type') || 'all');
  const [selectedCommitment, setSelectedCommitment] = useState(searchParams.get('commitment') || 'all');
  const [selectedSort, setSelectedSort] = useState(searchParams.get('sort') || 'newest');
  const [remoteOnly, setRemoteOnly] = useState(searchParams.get('remote') === '1');

  const [activeTab, setActiveTab] = useState<string>(searchParams.get('tab') || 'browse');
  const [savedJobs, setSavedJobs] = useState<JobVacancy[]>([]);
  const [isLoadingSaved, setIsLoadingSaved] = useState(false);
  const [myPostings, setMyPostings] = useState<JobVacancy[]>([]);
  const [isLoadingMyPostings, setIsLoadingMyPostings] = useState(false);

  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Debounce search query
  useEffect(() => {
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }
    searchTimeoutRef.current = setTimeout(() => {
      setDebouncedQuery(searchQuery);
    }, SEARCH_DEBOUNCE_MS);

    return () => {
      if (searchTimeoutRef.current) {
        clearTimeout(searchTimeoutRef.current);
      }
    };
  }, [searchQuery]);

  const loadVacancies = useCallback(async (append = false) => {
    try {
      if (!append) {
        setIsLoading(true);
        setError(null);
      } else {
        setIsLoadingMore(true);
      }

      const params = new URLSearchParams();
      if (debouncedQuery) params.set('search', debouncedQuery);
      if (selectedType !== 'all') params.set('type', selectedType);
      if (selectedCommitment !== 'all') params.set('commitment', selectedCommitment);
      if (selectedSort !== 'newest') params.set('sort', selectedSort);
      if (remoteOnly) params.set('is_remote', '1');
      params.set('status', 'open');
      params.set('per_page', String(ITEMS_PER_PAGE));
      if (append && cursorRef.current) {
        params.set('cursor', cursorRef.current);
      }

      const response = await api.get<JobVacancy[]>(`/v2/jobs?${params}`);
      if (response.success && response.data) {
        if (append) {
          setVacancies((prev) => [...prev, ...response.data!]);
        } else {
          setVacancies(response.data);
        }
        const cursor = response.meta?.cursor ?? null;
        cursorRef.current = cursor;
        setHasMore(response.meta?.has_more ?? response.data.length >= ITEMS_PER_PAGE);
      } else {
        if (!append) {
          setError(t('unable_to_load'));
        }
      }
    } catch (err) {
      logError('Failed to load job vacancies', err);
      if (!append) {
        setError(t('unable_to_load'));
      } else {
        toast.error(t('error_load_more'));
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [debouncedQuery, selectedType, selectedCommitment, selectedSort, remoteOnly, t, toast]);

  // Load vacancies when filters change (fresh load)
  // loadVacancies is intentionally excluded from deps — it depends on cursorRef,
  // which would cause infinite loops. We reset cursor and call it directly on filter change.
  useEffect(() => {
    if (activeTab === 'browse') {
      cursorRef.current = null;
      setHasMore(true);
      loadVacancies();
    }
  }, [debouncedQuery, selectedType, selectedCommitment, selectedSort, remoteOnly, activeTab]); // eslint-disable-line react-hooks/exhaustive-deps -- reset on filter change; loadVacancies excluded to avoid loop

  // J1: Load saved jobs
  useEffect(() => {
    if (activeTab !== 'saved' || !isAuthenticated) return;
    let cancelled = false;
    const loadSaved = async () => {
      setIsLoadingSaved(true);
      try {
        const response = await api.get<JobVacancy[]>('/v2/jobs/saved');
        if (cancelled) return;
        if (response.success && response.data) {
          setSavedJobs(response.data);
        }
      } catch (err) {
        if (!cancelled) logError('Failed to load saved jobs', err);
      } finally {
        if (!cancelled) setIsLoadingSaved(false);
      }
    };
    loadSaved();
    return () => { cancelled = true; };
  }, [activeTab, isAuthenticated]);

  // Load my postings (also on browse tab to check if user has posted before — for onboarding banner)
  useEffect(() => {
    if (!((activeTab === 'my-postings' || activeTab === 'browse') && isAuthenticated)) return;
    let cancelled = false;
    const loadMyPostings = async () => {
      setIsLoadingMyPostings(true);
      try {
        const response = await api.get<JobVacancy[]>('/v2/jobs/my-postings');
        if (cancelled) return;
        if (response.success && response.data) {
          setMyPostings(response.data);
        }
      } catch (err) {
        if (!cancelled) logError('Failed to load my postings', err);
      } finally {
        if (!cancelled) setIsLoadingMyPostings(false);
      }
    };
    loadMyPostings();
    return () => { cancelled = true; };
  }, [activeTab, isAuthenticated]);


  // Update URL params
  useEffect(() => {
    const params = new URLSearchParams();
    if (searchQuery) params.set('q', searchQuery);
    if (selectedType !== 'all') params.set('type', selectedType);
    if (selectedCommitment !== 'all') params.set('commitment', selectedCommitment);
    if (selectedSort !== 'newest') params.set('sort', selectedSort);
    if (remoteOnly) params.set('remote', '1');
    if (activeTab !== 'browse') params.set('tab', activeTab);
    setSearchParams(params, { replace: true });
  }, [searchQuery, selectedType, selectedCommitment, selectedSort, remoteOnly, activeTab, setSearchParams]);

  const loadMore = useCallback(() => {
    if (isLoadingMore || !hasMore) return;
    loadVacancies(true);
  }, [isLoadingMore, hasMore, loadVacancies]);

  if (!hasFeature('job_vacancies')) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] px-6 py-16 text-center">
        <div className="w-16 h-16 rounded-2xl bg-linear-to-br from-blue-100 to-cyan-100 dark:from-blue-900/30 dark:to-cyan-900/30 flex items-center justify-center mb-4">
          <Briefcase className="w-8 h-8 text-[var(--color-info)]" aria-hidden="true" />
        </div>
        <h2 className="text-xl font-semibold text-[var(--color-text)] mb-2">{t('feature_not_available')}</h2>
        <p className="text-[var(--color-text-muted)] max-w-sm">
          {t('feature_not_available_desc')}
        </p>
      </div>
    );
  }

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.05 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="space-y-6">
      <PageMeta title={t('page_title')} description={t('page_description')} />
      {/* Hero Banner */}
      <div className="relative overflow-hidden rounded-xl border border-theme-default bg-theme-surface p-5 shadow-sm sm:p-6">
        <div className="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <div className="flex items-center gap-3 mb-2">
              <div className="rounded-lg bg-blue-500/10 p-2 text-blue-600 dark:text-blue-400">
                <Briefcase className="w-5 h-5" aria-hidden="true" />
              </div>
              <h1 className="text-xl font-bold text-theme-primary">{t('title')}</h1>
            </div>
            <p className="text-sm text-theme-muted">{t('subtitle')}</p>
          </div>
          {isAuthenticated && (
            <div className="flex gap-2 flex-wrap shrink-0">
              <Button
                as={Link}
                to={tenantPath('/jobs/my-applications')}
                variant="flat"
                startContent={<FileText className="w-4 h-4" aria-hidden="true" />}
              >
                {t('my_applications.title')}
              </Button>
              <Button
                as={Link}
                to={tenantPath('/jobs/alerts')}
                variant="flat"
                startContent={<Bell className="w-4 h-4" aria-hidden="true" />}
              >
                {t('alerts.title')}
              </Button>
              <Button
                as={Link}
                to={tenantPath('/jobs/create')}
                color="primary"
                startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
              >
                {t('create_vacancy')}
              </Button>
            </div>
          )}
        </div>
      </div>

      {/* J1: Tabs for browse/saved */}
      {isAuthenticated && (
        <Tabs
          aria-label={t('tabs_aria')}
          selectedKey={activeTab}
          onSelectionChange={(key) => setActiveTab(key as string)}
          variant="underlined"
          classNames={{
            tab: 'text-theme-muted data-[selected=true]:text-theme-primary',
            cursor: 'bg-accent',
          }}
        >
          {jobConfig['jobs.tab_browse'] !== false && (
            <Tab
              key="browse"
              title={
                <span className="flex items-center gap-2">
                  <Briefcase className="w-4 h-4" aria-hidden="true" />
                  {t('title')}
                </span>
              }
            />
          )}
          {jobConfig['jobs.tab_saved'] !== false && (
            <Tab
              key="saved"
              title={
                <span className="flex items-center gap-2">
                  <Bookmark className="w-4 h-4" aria-hidden="true" />
                  {t('saved.title')}
                </span>
              }
            />
          )}
          {jobConfig['jobs.tab_my_postings'] !== false && (
            <Tab
              key="my-postings"
              title={
                <span className="flex items-center gap-2">
                  <Briefcase className="w-4 h-4" aria-hidden="true" />
                  {t('my_postings.title')}
                </span>
              }
            />
          )}
        </Tabs>
      )}

      {/* Employer onboarding banner for first-time posters */}
      {isAuthenticated && activeTab === 'browse' && myPostings.length === 0 && !isLoadingMyPostings && (
        <GlassCard className="p-4 bg-linear-to-r from-indigo-500/10 to-purple-500/10 border border-indigo-500/20">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-lg bg-indigo-500/20 flex items-center justify-center flex-shrink-0">
                <Rocket className="w-5 h-5 text-indigo-400" aria-hidden="true" />
              </div>
              <div>
                <p className="font-semibold text-theme-primary">{t('onboarding.first_time_banner')}</p>
                <p className="text-sm text-theme-muted">{t('onboarding.first_time_desc')}</p>
              </div>
            </div>
            <Button
              as={Link}
              to={tenantPath('/jobs/employer-onboarding')}
              size="sm"
              className="bg-linear-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<Rocket className="w-3.5 h-3.5" aria-hidden="true" />}
            >
              {t('onboarding.start_wizard')}
            </Button>
          </div>
        </GlassCard>
      )}

      {/* Browse tab content */}
      {activeTab === 'browse' && (
        <>
          {/* Search */}
          <GlassCard className="p-4">
            <SearchField
              placeholder={t('search_placeholder')}
              aria-label={t('search_aria')}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              classNames={{
                input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
          </GlassCard>

          {/* Sort + Remote toggle */}
          <div className="flex flex-wrap items-center gap-3">
            <Select
              label={t('sort.label')}
              selectedKeys={[selectedSort]}
              onChange={(e) => setSelectedSort(e.target.value || 'newest')}
              className="w-48"
              size="sm"
              disallowEmptySelection
              startContent={<ArrowUpDown className="w-3.5 h-3.5 text-theme-subtle" aria-hidden="true" />}
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default',
                value: 'text-theme-primary',
              }}
              aria-label={t('sort.label')}
            >
              <SelectItem key="newest" id="newest">{t('sort.newest')}</SelectItem>
              <SelectItem key="deadline" id="deadline">{t('sort.deadline')}</SelectItem>
              <SelectItem key="salary_desc" id="salary_desc">{t('sort.salary')}</SelectItem>
            </Select>
            <div className="flex items-center gap-2">
              <Switch
                isSelected={remoteOnly}
                onValueChange={setRemoteOnly}
                size="sm"
                aria-label={t('remote_only')}
              />
              <span className="text-sm text-theme-muted">{t('remote_only')}</span>
            </div>
          </div>

          {/* Type Filter Chips */}
          <ToggleButtonGroup
            selectionMode="single"
            disallowEmptySelection
            selectedKeys={[selectedType]}
            onSelectionChange={(keys) => {
              const nextType = Array.from(keys)[0];
              if (nextType) {
                setSelectedType(String(nextType));
              }
            }}
            isDetached
            size="sm"
            className="flex flex-wrap gap-2"
            aria-label={t('filter_aria')}
          >
            {TYPE_FILTERS.map((filter) => {
              const isSelected = selectedType === filter.id;
              const IconComp = filter.icon;
              return (
                <ToggleButton
                  key={filter.id}
                  id={filter.id}
                  className={
                    isSelected
                      ? 'bg-accent text-white'
                      : 'bg-theme-elevated text-theme-muted hover:bg-theme-hover'
                  }
                >
                  <IconComp className="w-3.5 h-3.5" aria-hidden="true" />
                  {t(`type.${filter.id}`)}
                </ToggleButton>
              );
            })}
          </ToggleButtonGroup>

          {/* Commitment Filter Chips */}
          <ToggleButtonGroup
            selectionMode="single"
            disallowEmptySelection
            selectedKeys={[selectedCommitment]}
            onSelectionChange={(keys) => {
              const nextCommitment = Array.from(keys)[0];
              if (nextCommitment) {
                setSelectedCommitment(String(nextCommitment));
              }
            }}
            isDetached
            size="sm"
            className="flex flex-wrap gap-2"
            aria-label={t('filter_by_commitment')}
          >
            {COMMITMENT_FILTERS.map((filter) => {
              const isSelected = selectedCommitment === filter.id;
              return (
                <ToggleButton
                  key={filter.id}
                  id={filter.id}
                  className={
                    isSelected
                      ? 'bg-accent text-white'
                      : 'bg-theme-elevated text-theme-muted hover:bg-theme-hover'
                  }
                >
                  {t(`commitment.${filter.id}`)}
                </ToggleButton>
              );
            })}
          </ToggleButtonGroup>

          {/* Error State */}
          {error && !isLoading && (
            <GlassCard role="alert" className="p-8 text-center">
              <AlertTriangle className="w-12 h-12 text-[var(--color-warning)] mx-auto mb-4" aria-hidden="true" />
              <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('unable_to_load')}</h2>
              <p className="text-theme-muted mb-4">{error}</p>
              <Button
                className="bg-linear-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
                onPress={() => loadVacancies()}
              >
                {t('try_again')}
              </Button>
            </GlassCard>
          )}

          {/* Vacancies List */}
          {!error && (
            <>
              {isLoading ? (
                <div role="status" className="space-y-4" aria-busy="true" aria-label={t('loading')}>
                  {[1, 2, 3].map((i) => (
                    <CardRowsSkeleton key={i} rows={['w-1/2', 'w-3/4', 'w-1/4']} />
                  ))}
                </div>
              ) : vacancies.length === 0 ? (
                <EmptyState
                  icon={<Briefcase className="w-12 h-12" aria-hidden="true" />}
                  title={t('empty_title')}
                  description={debouncedQuery ? t('empty_search') : t('empty_description')}
                  action={
                    isAuthenticated && (
                      <Link to={tenantPath('/jobs/create')}>
                        <Button className="bg-linear-to-r from-indigo-500 to-purple-600 text-white">
                          {t('create_vacancy')}
                        </Button>
                      </Link>
                    )
                  }
                />
              ) : (
                <motion.div
                  variants={containerVariants}
                  initial="hidden"
                  animate="visible"
                  className="space-y-4"
                >
                  {vacancies.map((vacancy) => (
                    <motion.div key={vacancy.id} variants={itemVariants}>
                      <JobCard vacancy={vacancy} />
                    </motion.div>
                  ))}

                  {/* Load More */}
                  {hasMore && (
                    <div className="pt-4 text-center">
                      <Button
                        variant="flat"
                        className="bg-theme-elevated text-theme-muted"
                        onPress={loadMore}
                        isLoading={isLoadingMore}
                      >
                        {t('load_more')}
                      </Button>
                    </div>
                  )}
                </motion.div>
              )}
            </>
          )}
        </>
      )}

      {/* J1: Saved tab content */}
      {activeTab === 'saved' && (
        <>
          {isLoadingSaved ? (
            <div className="space-y-4" role="status" aria-busy="true" aria-label="Loading">
              {[1, 2].map((i) => (
                <CardRowsSkeleton key={i} rows={['w-1/2', 'w-3/4', 'w-1/4']} />
              ))}
            </div>
          ) : savedJobs.length === 0 ? (
            <EmptyState
              icon={<Bookmark className="w-12 h-12" aria-hidden="true" />}
              title={t('saved.empty_title')}
              description={t('saved.empty_description')}
            />
          ) : (
            <motion.div
              variants={containerVariants}
              initial="hidden"
              animate="visible"
              className="space-y-4"
            >
              {savedJobs.map((vacancy) => (
                <motion.div key={vacancy.id} variants={itemVariants}>
                  <JobCard vacancy={vacancy} />
                </motion.div>
              ))}
            </motion.div>
          )}
        </>
      )}

      {/* My Postings tab content */}
      {activeTab === 'my-postings' && (
        <>
          {/* CTA header */}
          <div className="flex justify-end">
            <Link to={tenantPath('/jobs/create')}>
              <Button
                className="bg-linear-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
              >
                {t('create_vacancy')}
              </Button>
            </Link>
          </div>
          {isLoadingMyPostings ? (
            <div className="space-y-4" role="status" aria-busy="true" aria-label="Loading">
              {[1, 2].map((i) => (
                <CardRowsSkeleton key={i} rows={['w-1/2', 'w-3/4', 'w-1/4']} />
              ))}
            </div>
          ) : myPostings.length === 0 ? (
            <EmptyState
              icon={<Briefcase className="w-12 h-12" aria-hidden="true" />}
              title={t('my_postings.empty_title')}
              description={t('my_postings.empty_description')}
              action={
                <Link to={tenantPath('/jobs/create')}>
                  <Button className="bg-linear-to-r from-indigo-500 to-purple-600 text-white">
                    {t('create_vacancy')}
                  </Button>
                </Link>
              }
            />
          ) : (
            <motion.div
              variants={containerVariants}
              initial="hidden"
              animate="visible"
              className="space-y-4"
            >
              {myPostings.map((vacancy) => (
                <motion.div key={vacancy.id} variants={itemVariants}>
                  <MyPostingCard vacancy={vacancy} />
                </motion.div>
              ))}
            </motion.div>
          )}
        </>
      )}
    </div>
  );
}

interface JobCardProps {
  vacancy: JobVacancy;
}

const TYPE_CHIP_COLORS: Record<string, 'success' | 'default' | 'accent'> = {
  paid: 'success',
  volunteer: 'default',
  timebank: 'accent',
};

const JobCard = memo(function JobCard({ vacancy }: JobCardProps) {
  const { t } = useTranslation('jobs');
  const { tenantPath } = useTenant();

  const deadlineDate = vacancy.deadline ? new Date(vacancy.deadline) : null;
  const isPastDeadline = deadlineDate ? deadlineDate < new Date() : false;

  // J9: Format salary with Intl.NumberFormat
  const formatCurrency = (value: number) => {
    const currency = vacancy.salary_currency || 'EUR';
    try {
      return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    } catch {
      return `${currency} ${value.toLocaleString()}`;
    }
  };

  const salaryDisplay = (() => {
    if (!vacancy.salary_min && !vacancy.salary_max) return null;
    const periodSuffix = vacancy.salary_type === 'hourly' ? `/${t('salary.hourly')}` : vacancy.salary_type === 'annual' ? `/${t('salary.annual')}` : '';
    if (vacancy.salary_min && vacancy.salary_max) {
      return `${formatCurrency(vacancy.salary_min)} – ${formatCurrency(vacancy.salary_max)}${periodSuffix}`;
    }
    if (vacancy.salary_min) return `${formatCurrency(vacancy.salary_min)}+${periodSuffix}`;
    if (vacancy.salary_max) return `${t('salary.max_only', { max: formatCurrency(vacancy.salary_max) })}${periodSuffix}`;
    return null;
  })();

  // Deadline countdown
  const daysUntilDeadline = deadlineDate
    ? Math.ceil((deadlineDate.getTime() - Date.now()) / (1000 * 60 * 60 * 24))
    : null;

  // Skills match
  const matchColor = vacancy.match_percentage != null
    ? vacancy.match_percentage >= 75 ? 'success' as const
    : vacancy.match_percentage >= 50 ? 'warning' as const
    : 'default' as const
    : null;

  return (
    <Link to={tenantPath(`/jobs/${vacancy.id}`)} aria-label={vacancy.title}>
      <article>
        <GlassCard className={`p-5 hover:scale-[1.01] transition-transform ${vacancy.is_featured ? 'ring-2 ring-warning/50 bg-linear-to-r from-amber-500/5 to-orange-500/5' : ''}`}>
          <div className="flex gap-3 sm:gap-4">
            {/* Icon */}
            <div className="flex-shrink-0">
              <div className="w-12 h-12 sm:w-14 sm:h-14 rounded-lg bg-linear-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center relative">
                <Briefcase className="w-6 h-6 text-blue-400" aria-hidden="true" />
                {/* J10: Featured star */}
                {vacancy.is_featured && (
                  <div className="absolute -top-1 -right-1 w-5 h-5 bg-warning rounded-full flex items-center justify-center">
                    <Star className="w-3 h-3 text-white" aria-hidden="true" />
                  </div>
                )}
              </div>
            </div>

            {/* Details */}
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 flex-wrap">
                <h3 className="font-semibold text-theme-primary text-lg">{vacancy.title}</h3>
                {/* J10: Featured chip */}
                {vacancy.is_featured && (
                  <HeroChip size="sm" variant="tertiary" color="warning" className="text-xs">
                    {t('featured')}
                  </HeroChip>
                )}
                <HeroChip size="sm" variant="tertiary" color={TYPE_CHIP_COLORS[vacancy.type] ?? 'default'} className="text-xs">
                  {t(`type.${vacancy.type}`)}
                </HeroChip>
                <HeroChip size="sm" variant="tertiary" color="default" className="text-xs">
                  {t(`commitment.${vacancy.commitment}`)}
                </HeroChip>
                {vacancy.has_applied && (
                  <HeroChip size="sm" variant="tertiary" color="warning" className="text-xs">
                    {t('apply.applied')}
                  </HeroChip>
                )}
                {/* Deadline countdown chip */}
                {daysUntilDeadline != null && daysUntilDeadline <= 0 && (
                  <HeroChip size="sm" variant="tertiary" color="danger" className="text-xs font-medium">
                    {t('deadline_closed')}
                  </HeroChip>
                )}
                {daysUntilDeadline != null && daysUntilDeadline > 0 && daysUntilDeadline <= 7 && (
                  <HeroChip size="sm" variant="tertiary" color="warning" className="text-xs font-medium">
                    {t('deadline_countdown', { count: daysUntilDeadline })}
                  </HeroChip>
                )}
                {/* Salary negotiable chip */}
                {vacancy.salary_negotiable && (vacancy.salary_min || vacancy.salary_max) && (
                  <HeroChip size="sm" variant="tertiary" color="default" className="text-xs">
                    {t('salary_negotiable')}
                  </HeroChip>
                )}
                {/* Skills match badge */}
                {matchColor && vacancy.match_percentage != null && (
                  <HeroChip size="sm" variant="tertiary" color={matchColor} className="text-xs font-medium">
                    <TrendingUp className="w-3 h-3" aria-hidden="true" />
                    {t('match_badge', { count: Math.round(vacancy.match_percentage) })}
                  </HeroChip>
                )}
                {/* J1: Saved indicator */}
                {vacancy.is_saved && (
                  <>
                    <BookmarkCheck className="w-4 h-4 text-warning" aria-hidden="true" />
                    <span className="sr-only">{t('saved.saved')}</span>
                  </>
                )}
              </div>

              <p className="text-theme-muted text-sm mt-1">
                {vacancy.organization?.name || vacancy.creator?.name || t('unknown')}
              </p>

              <SafeHtml content={vacancy.description} className="text-theme-muted text-sm line-clamp-2 mt-1" as="p" />

              <div className="flex flex-wrap items-center gap-4 mt-3 text-sm text-theme-subtle">
                {vacancy.is_remote ? (
                  <span className="flex items-center gap-1">
                    <Wifi className="w-4 h-4" aria-hidden="true" />
                    {t('remote')}
                  </span>
                ) : vacancy.location ? (
                  <span className="flex items-center gap-1">
                    <MapPin className="w-4 h-4" aria-hidden="true" />
                    {vacancy.location}
                  </span>
                ) : null}

                {/* J9: Salary on card */}
                {salaryDisplay && (
                  <span className="flex items-center gap-1 font-medium text-theme-primary">
                    <DollarSign className="w-4 h-4" aria-hidden="true" />
                    {salaryDisplay}
                  </span>
                )}

                {deadlineDate && !isPastDeadline && (
                  <span className="flex items-center gap-1">
                    <Clock className="w-4 h-4" aria-hidden="true" />
                    {`${t('deadline_label')}: ${deadlineDate.toLocaleDateString()}`}
                  </span>
                )}

                <span className="flex items-center gap-1">
                  <Eye className="w-4 h-4" aria-hidden="true" />
                  {t('views', { count: vacancy.views_count })}
                </span>

                <span className="flex items-center gap-1">
                  <FileText className="w-4 h-4" aria-hidden="true" />
                  {t('applications', { count: vacancy.applications_count })}
                </span>
              </div>
            </div>

            {/* Arrow */}
            <div className="flex-shrink-0 self-center">
              <ChevronRight className="w-5 h-5 text-theme-subtle" aria-hidden="true" />
            </div>
          </div>
        </GlassCard>
      </article>
    </Link>
  );
});

const MY_POSTING_STATUS_COLORS: Record<string, 'success' | 'danger' | 'warning' | 'default' | 'accent'> = {
  open: 'success',
  draft: 'default',
  closed: 'danger',
  filled: 'success',
};

interface MyPostingCardProps {
  vacancy: JobVacancy;
}

const MyPostingCard = memo(function MyPostingCard({ vacancy }: MyPostingCardProps) {
  const { t } = useTranslation('jobs');
  const { tenantPath } = useTenant();

  return (
    <GlassCard className="p-5">
      <div className="flex gap-3 sm:gap-4">
        {/* Icon */}
        <div className="flex-shrink-0">
          <div className="w-12 h-12 rounded-lg bg-linear-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center">
            <Briefcase className="w-6 h-6 text-indigo-400" aria-hidden="true" />
          </div>
        </div>

        {/* Details */}
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <h3 className="font-semibold text-theme-primary text-lg">{vacancy.title}</h3>
            {/* Status chip — prominent */}
            <HeroChip
              size="sm"
              variant="tertiary"
              color={MY_POSTING_STATUS_COLORS[vacancy.status] ?? 'default'}
              className="font-medium"
            >
              {t(`status.${vacancy.status}`, vacancy.status)}
            </HeroChip>
            <HeroChip size="sm" variant="tertiary" color={TYPE_CHIP_COLORS[vacancy.type] ?? 'default'} className="text-xs">
              {t(`type.${vacancy.type}`)}
            </HeroChip>
            <HeroChip size="sm" variant="tertiary" color="default" className="text-xs">
              {t(`commitment.${vacancy.commitment}`)}
            </HeroChip>
          </div>

          {/* Stats row */}
          <div className="flex flex-wrap items-center gap-4 mt-2 text-sm text-theme-subtle">
            <span className="flex items-center gap-1">
              <Eye className="w-4 h-4" aria-hidden="true" />
              {t('views', { count: vacancy.views_count })}
            </span>
            <span className={`flex items-center gap-1 ${vacancy.applications_count > 0 ? 'text-accent font-medium' : ''}`}>
              <FileText className="w-4 h-4" aria-hidden="true" />
              {t('applications', { count: vacancy.applications_count })}
            </span>
            <span className="flex items-center gap-1">
              <Clock className="w-4 h-4" aria-hidden="true" />
              {new Date(vacancy.created_at).toLocaleDateString()}
            </span>
          </div>

          {/* Action buttons */}
          <div className="flex flex-wrap gap-2 mt-3">
            <Link to={tenantPath(`/jobs/${vacancy.id}#applications`)}>
              <Button
                size="sm"
                variant="flat"
                color={vacancy.applications_count > 0 ? 'primary' : 'default'}
                className={vacancy.applications_count > 0 ? '' : 'bg-theme-elevated text-theme-muted'}
                startContent={<FileText className="w-3.5 h-3.5" aria-hidden="true" />}
              >
                {t('my_postings.view_applicants')} ({vacancy.applications_count})
              </Button>
            </Link>
            <Link to={tenantPath(`/jobs/${vacancy.id}/edit`)}>
              <Button
                size="sm"
                variant="flat"
                className="bg-theme-elevated text-theme-muted"
                startContent={<Edit className="w-3.5 h-3.5" aria-hidden="true" />}
              >
                {t('my_postings.edit')}
              </Button>
            </Link>
          </div>
        </div>

        {/* Arrow */}
        <div className="flex-shrink-0 self-center">
          <Link to={tenantPath(`/jobs/${vacancy.id}`)} aria-label={t('jobs.view_details')}>
            <ChevronRight className="w-5 h-5 text-theme-subtle" aria-hidden="true" />
          </Link>
        </div>
      </div>
    </GlassCard>
  );
});

export default JobsPage;
