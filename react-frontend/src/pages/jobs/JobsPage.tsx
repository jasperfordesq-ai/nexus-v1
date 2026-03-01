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
 * - Job cards with type chips, location, deadline
 * - Post vacancy button for authenticated users
 */

import { useState, useEffect, useCallback, useRef, memo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Chip } from '@heroui/react';
import {
  Briefcase,
  Search,
  Plus,
  MapPin,
  Clock,
  Eye,
  FileText,
  RefreshCw,
  AlertTriangle,
  ChevronRight,
  Wifi,
  DollarSign,
  Heart,
  Timer,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';

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
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  const [vacancies, setVacancies] = useState<JobVacancy[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(true);
  const [nextCursor, setNextCursor] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [debouncedQuery, setDebouncedQuery] = useState(searchQuery);
  const [selectedType, setSelectedType] = useState(searchParams.get('type') || 'all');
  const [selectedCommitment, setSelectedCommitment] = useState(searchParams.get('commitment') || 'all');

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
      params.set('status', 'open');
      params.set('per_page', String(ITEMS_PER_PAGE));
      if (append && nextCursor) {
        params.set('cursor', nextCursor);
      }

      const response = await api.get<JobVacancy[]>(`/v2/jobs?${params}`);
      if (response.success && response.data) {
        if (append) {
          setVacancies((prev) => [...prev, ...response.data!]);
        } else {
          setVacancies(response.data);
        }
        const cursor = response.meta?.cursor ?? null;
        setNextCursor(cursor);
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
  }, [debouncedQuery, selectedType, selectedCommitment, nextCursor, t, toast]);

  // Load vacancies when filters change (fresh load)
  useEffect(() => {
    setNextCursor(null);
    loadVacancies();
    setHasMore(true);
  }, [debouncedQuery, selectedType, selectedCommitment]); // eslint-disable-line react-hooks/exhaustive-deps

  // Update URL params
  useEffect(() => {
    const params = new URLSearchParams();
    if (searchQuery) params.set('q', searchQuery);
    if (selectedType !== 'all') params.set('type', selectedType);
    if (selectedCommitment !== 'all') params.set('commitment', selectedCommitment);
    setSearchParams(params, { replace: true });
  }, [searchQuery, selectedType, selectedCommitment, setSearchParams]);

  const loadMore = useCallback(() => {
    if (isLoadingMore || !hasMore) return;
    loadVacancies(true);
  }, [isLoadingMore, hasMore, loadVacancies]);

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
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Briefcase className="w-7 h-7 text-blue-400" aria-hidden="true" />
            {t('title')}
          </h1>
          <p className="text-theme-muted mt-1">{t('subtitle')}</p>
        </div>
        {isAuthenticated && (
          <Link to={tenantPath('/jobs/create')}>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
            >
              {t('create_vacancy')}
            </Button>
          </Link>
        )}
      </div>

      {/* Search */}
      <GlassCard className="p-4">
        <Input
          placeholder={t('search_placeholder')}
          aria-label={t('search_aria')}
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
          classNames={{
            input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
            inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
          }}
        />
      </GlassCard>

      {/* Type Filter Chips */}
      <div className="flex flex-wrap gap-2" role="group" aria-label={t('filter_aria')}>
        {TYPE_FILTERS.map((filter) => {
          const isSelected = selectedType === filter.id;
          const IconComp = filter.icon;
          return (
            <Chip
              key={filter.id}
              variant={isSelected ? 'solid' : 'flat'}
              color={isSelected ? 'primary' : 'default'}
              className={
                isSelected
                  ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white cursor-pointer'
                  : 'bg-theme-elevated text-theme-muted cursor-pointer hover:bg-theme-hover'
              }
              startContent={<IconComp className="w-3.5 h-3.5" aria-hidden="true" />}
              onClick={() => setSelectedType(filter.id)}
              aria-pressed={isSelected}
            >
              {t(`type.${filter.id}`)}
            </Chip>
          );
        })}
      </div>

      {/* Commitment Filter Chips */}
      <div className="flex flex-wrap gap-2" role="group" aria-label="Filter by commitment">
        {COMMITMENT_FILTERS.map((filter) => {
          const isSelected = selectedCommitment === filter.id;
          return (
            <Chip
              key={filter.id}
              variant={isSelected ? 'solid' : 'flat'}
              color={isSelected ? 'secondary' : 'default'}
              className={
                isSelected
                  ? 'bg-gradient-to-r from-violet-500 to-fuchsia-600 text-white cursor-pointer'
                  : 'bg-theme-elevated text-theme-muted cursor-pointer hover:bg-theme-hover'
              }
              onClick={() => setSelectedCommitment(filter.id)}
              aria-pressed={isSelected}
            >
              {t(`commitment.${filter.id}`)}
            </Chip>
          );
        })}
      </div>

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('unable_to_load')}</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
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
            <div className="space-y-4">
              {[1, 2, 3].map((i) => (
                <GlassCard key={i} className="p-5 animate-pulse">
                  <div className="flex gap-4">
                    <div className="flex-1">
                      <div className="h-5 bg-theme-hover rounded w-1/2 mb-2" />
                      <div className="h-4 bg-theme-hover rounded w-3/4 mb-3" />
                      <div className="h-3 bg-theme-hover rounded w-1/4" />
                    </div>
                  </div>
                </GlassCard>
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
                    <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
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
    </div>
  );
}

interface JobCardProps {
  vacancy: JobVacancy;
}

const TYPE_CHIP_COLORS: Record<string, 'success' | 'secondary' | 'primary'> = {
  paid: 'success',
  volunteer: 'secondary',
  timebank: 'primary',
};

const JobCard = memo(function JobCard({ vacancy }: JobCardProps) {
  const { t } = useTranslation('jobs');
  const { tenantPath } = useTenant();

  const deadlineDate = vacancy.deadline ? new Date(vacancy.deadline) : null;
  const isPastDeadline = deadlineDate ? deadlineDate < new Date() : false;

  return (
    <Link to={tenantPath(`/jobs/${vacancy.id}`)} aria-label={vacancy.title}>
      <article>
        <GlassCard className="p-5 hover:scale-[1.01] transition-transform">
          <div className="flex gap-3 sm:gap-4">
            {/* Icon */}
            <div className="flex-shrink-0">
              <div className="w-12 h-12 sm:w-14 sm:h-14 rounded-lg bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center">
                <Briefcase className="w-6 h-6 text-blue-400" aria-hidden="true" />
              </div>
            </div>

            {/* Details */}
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 flex-wrap">
                <h3 className="font-semibold text-theme-primary text-lg">{vacancy.title}</h3>
                <Chip size="sm" variant="flat" color={TYPE_CHIP_COLORS[vacancy.type] ?? 'default'} className="text-xs">
                  {t(`type.${vacancy.type}`)}
                </Chip>
                <Chip size="sm" variant="flat" color="default" className="text-xs">
                  {t(`commitment.${vacancy.commitment}`)}
                </Chip>
                {vacancy.has_applied && (
                  <Chip size="sm" variant="flat" color="warning" className="text-xs">
                    {t('apply.applied')}
                  </Chip>
                )}
              </div>

              <p className="text-theme-muted text-sm mt-1">
                {vacancy.organization ? vacancy.organization.name : vacancy.creator.name}
              </p>

              <p className="text-theme-muted text-sm line-clamp-2 mt-1">
                {vacancy.description}
              </p>

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

                {deadlineDate && (
                  <span className={`flex items-center gap-1 ${isPastDeadline ? 'text-danger' : ''}`}>
                    <Clock className="w-4 h-4" aria-hidden="true" />
                    {isPastDeadline
                      ? t('deadline_passed')
                      : `${t('deadline_label')}: ${deadlineDate.toLocaleDateString()}`}
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

export default JobsPage;
