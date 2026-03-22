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
 * - Salary display on cards (J9)
 */

import { useState, useEffect, useCallback, useRef, memo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Chip, Tabs, Tab, Spinner, Select, SelectItem } from '@heroui/react';
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
  Bookmark,
  BookmarkCheck,
  Bell,
  Star,
  Edit,
  Sparkles,
  MapPinOff,
  X,
  SlidersHorizontal,
  ChevronDown,
  ChevronUp,
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
  is_saved: boolean;
  is_featured: boolean;
  salary_min: number | null;
  salary_max: number | null;
  salary_type: string | null;
  salary_currency: string | null;
  salary_negotiable: boolean;
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
  const { tenantPath, hasFeature } = useTenant();
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

  const [activeTab, setActiveTab] = useState<string>(searchParams.get('tab') || 'browse');
  const [savedJobs, setSavedJobs] = useState<JobVacancy[]>([]);
  const [isLoadingSaved, setIsLoadingSaved] = useState(false);
  const [myPostings, setMyPostings] = useState<JobVacancy[]>([]);
  const [isLoadingMyPostings, setIsLoadingMyPostings] = useState(false);

  // Feature 4: AI Recommendations
  const [recommendedJobs, setRecommendedJobs] = useState<JobVacancy[]>([]);
  const [isLoadingRecommended, setIsLoadingRecommended] = useState(false);

  // Advanced Search panel
  const [advancedOpen, setAdvancedOpen] = useState(false);
  const [salaryMin, setSalaryMin] = useState('');
  const [salaryMax, setSalaryMax] = useState('');
  const [companySizeFilter, setCompanySizeFilter] = useState('');
  const [benefitFilter, setBenefitFilter] = useState('');
  const [booleanMode, setBooleanMode] = useState<'AND' | 'OR' | 'NOT'>('AND');

  // Feature 5: Geolocation radius search
  const [geoActive, setGeoActive] = useState(false);
  const [geoLoading, setGeoLoading] = useState(false);
  const [userLat, setUserLat] = useState<number | null>(null);
  const [userLng, setUserLng] = useState<number | null>(null);
  const [radiusKm, setRadiusKm] = useState('25');

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
      if (append && cursorRef.current) {
        params.set('cursor', cursorRef.current);
      }
      // Advanced search params
      if (salaryMin) params.set('salary_min', salaryMin);
      if (salaryMax) params.set('salary_max', salaryMax);
      if (companySizeFilter) params.set('company_size', companySizeFilter);
      if (benefitFilter) params.set('benefits', benefitFilter);
      // Feature 5: Geo params
      if (geoActive && userLat != null && userLng != null) {
        params.set('latitude', String(userLat));
        params.set('longitude', String(userLng));
        params.set('radius_km', radiusKm);
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
  }, [debouncedQuery, selectedType, selectedCommitment, t, toast, geoActive, userLat, userLng, radiusKm, salaryMin, salaryMax, companySizeFilter, benefitFilter]);

  // Load vacancies when filters change (fresh load)
  // loadVacancies is intentionally excluded from deps — it depends on cursorRef,
  // which would cause infinite loops. We reset cursor and call it directly on filter change.
  useEffect(() => {
    if (activeTab === 'browse') {
      cursorRef.current = null;
      setHasMore(true);
      loadVacancies();
    }
  }, [debouncedQuery, selectedType, selectedCommitment, activeTab, geoActive, userLat, userLng, radiusKm, salaryMin, salaryMax, companySizeFilter, benefitFilter]); // eslint-disable-line react-hooks/exhaustive-deps

  // J1: Load saved jobs
  useEffect(() => {
    if (activeTab === 'saved' && isAuthenticated) {
      const loadSaved = async () => {
        setIsLoadingSaved(true);
        try {
          const response = await api.get<JobVacancy[]>('/v2/jobs/saved');
          if (response.success && response.data) {
            setSavedJobs(response.data);
          }
        } catch (err) {
          logError('Failed to load saved jobs', err);
        } finally {
          setIsLoadingSaved(false);
        }
      };
      loadSaved();
    }
  }, [activeTab, isAuthenticated]);

  // Feature 4: Load AI recommended jobs
  useEffect(() => {
    if (activeTab === 'recommended' && isAuthenticated) {
      const loadRecommended = async () => {
        setIsLoadingRecommended(true);
        try {
          const response = await api.get<JobVacancy[]>('/v2/jobs/recommended');
          if (response.success && response.data) {
            setRecommendedJobs(response.data);
          }
        } catch (err) {
          logError('Failed to load recommended jobs', err);
        } finally {
          setIsLoadingRecommended(false);
        }
      };
      loadRecommended();
    }
  }, [activeTab, isAuthenticated]);

  // Load my postings
  useEffect(() => {
    if (activeTab === 'my-postings' && isAuthenticated) {
      const loadMyPostings = async () => {
        setIsLoadingMyPostings(true);
        try {
          const response = await api.get<JobVacancy[]>('/v2/jobs/my-postings');
          if (response.success && response.data) {
            setMyPostings(response.data);
          }
        } catch (err) {
          logError('Failed to load my postings', err);
        } finally {
          setIsLoadingMyPostings(false);
        }
      };
      loadMyPostings();
    }
  }, [activeTab, isAuthenticated]);


  // Update URL params
  useEffect(() => {
    const params = new URLSearchParams();
    if (searchQuery) params.set('q', searchQuery);
    if (selectedType !== 'all') params.set('type', selectedType);
    if (selectedCommitment !== 'all') params.set('commitment', selectedCommitment);
    if (activeTab !== 'browse') params.set('tab', activeTab);
    setSearchParams(params, { replace: true });
  }, [searchQuery, selectedType, selectedCommitment, activeTab, setSearchParams]);

  const loadMore = useCallback(() => {
    if (isLoadingMore || !hasMore) return;
    loadVacancies(true);
  }, [isLoadingMore, hasMore, loadVacancies]);

  // Feature 5: Geo toggle handler
  const handleGeoToggle = useCallback(() => {
    if (geoActive) {
      setGeoActive(false);
      setUserLat(null);
      setUserLng(null);
      return;
    }
    if (!navigator.geolocation) {
      toast.error(t('geo.not_supported', 'Geolocation is not supported by your browser'));
      return;
    }
    setGeoLoading(true);
    navigator.geolocation.getCurrentPosition(
      (position) => {
        setUserLat(position.coords.latitude);
        setUserLng(position.coords.longitude);
        setGeoActive(true);
        setGeoLoading(false);
      },
      () => {
        toast.error(t('geo.permission_denied', 'Location access denied. Please allow location access to use this feature.'));
        setGeoLoading(false);
      },
      { timeout: 10000 }
    );
  }, [geoActive, t, toast]);

  if (!hasFeature('job_vacancies')) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] px-6 py-16 text-center">
        <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-100 to-cyan-100 dark:from-blue-900/30 dark:to-cyan-900/30 flex items-center justify-center mb-4">
          <Briefcase className="w-8 h-8 text-blue-500" aria-hidden="true" />
        </div>
        <h2 className="text-xl font-semibold text-[var(--color-text)] mb-2">{t('feature_not_available', 'Jobs Not Available')}</h2>
        <p className="text-[var(--color-text-muted)] max-w-sm">
          {t('feature_not_available_desc', 'The jobs feature is not enabled for this community. Contact your timebank administrator to learn more.')}
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
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Briefcase className="w-7 h-7 text-blue-400" aria-hidden="true" />
            {t('title')}
          </h1>
          <p className="text-theme-muted mt-1">{t('subtitle')}</p>
        </div>
        <div className="flex gap-2">
          {isAuthenticated && (
            <>
              <Link to={tenantPath('/jobs/my-applications')}>
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-muted"
                  startContent={<FileText className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('my_applications.title')}
                </Button>
              </Link>
              <Link to={tenantPath('/jobs/alerts')}>
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-muted"
                  startContent={<Bell className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('alerts.title')}
                </Button>
              </Link>
              <Link to={tenantPath('/jobs/create')}>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('create_vacancy')}
                </Button>
              </Link>
            </>
          )}
        </div>
      </div>

      {/* J1: Tabs for browse/saved */}
      {isAuthenticated && (
        <Tabs
          selectedKey={activeTab}
          onSelectionChange={(key) => setActiveTab(key as string)}
          variant="underlined"
          classNames={{
            tab: 'text-theme-muted data-[selected=true]:text-theme-primary',
            cursor: 'bg-primary',
          }}
        >
          <Tab
            key="browse"
            title={
              <span className="flex items-center gap-2">
                <Briefcase className="w-4 h-4" aria-hidden="true" />
                {t('title')}
              </span>
            }
          />
          <Tab
            key="saved"
            title={
              <span className="flex items-center gap-2">
                <Bookmark className="w-4 h-4" aria-hidden="true" />
                {t('saved.title')}
              </span>
            }
          />
          <Tab
            key="my-postings"
            title={
              <span className="flex items-center gap-2">
                <Briefcase className="w-4 h-4" aria-hidden="true" />
                {t('my_postings.title', 'My Postings')}
              </span>
            }
          />
          {/* Feature 4: Recommended tab */}
          <Tab
            key="recommended"
            title={
              <span className="flex items-center gap-2">
                <Sparkles className="w-4 h-4" aria-hidden="true" />
                {t('recommended.title', 'Recommended')}
              </span>
            }
          />
        </Tabs>
      )}

      {/* Browse tab content */}
      {activeTab === 'browse' && (
        <>
          {/* Search */}
          <div role="search">
          <GlassCard className="p-4 space-y-3">
            <Input
              placeholder={t('search_placeholder')}
              aria-label={t('search.label', 'Search job vacancies')}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              classNames={{
                input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
            {/* Advanced Search toggle */}
            <button
              type="button"
              className="flex items-center gap-1.5 text-xs text-theme-muted hover:text-theme-primary transition-colors"
              onClick={() => setAdvancedOpen((o) => !o)}
              aria-expanded={advancedOpen}
            >
              <SlidersHorizontal className="w-3.5 h-3.5" aria-hidden="true" />
              {t('search.advanced', 'Advanced Search')}
              {advancedOpen
                ? <ChevronUp className="w-3 h-3" aria-hidden="true" />
                : <ChevronDown className="w-3 h-3" aria-hidden="true" />}
            </button>

            {advancedOpen && (
              <div className="pt-2 space-y-4 border-t border-theme-default">
                {/* Boolean mode chips */}
                <div className="space-y-1.5">
                  <p className="text-xs text-theme-subtle">{t('search.boolean_hint', 'e.g. "developer | engineer", "react -junior"')}</p>
                  <div className="flex gap-2 flex-wrap" role="group" aria-label="Boolean search mode">
                    {(['AND', 'OR', 'NOT'] as const).map((mode) => (
                      <Chip
                        key={mode}
                        variant={booleanMode === mode ? 'solid' : 'flat'}
                        color={booleanMode === mode ? 'primary' : 'default'}
                        className={
                          booleanMode === mode
                            ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white cursor-pointer text-xs'
                            : 'bg-theme-elevated text-theme-muted cursor-pointer hover:bg-theme-hover text-xs'
                        }
                        onClick={() => setBooleanMode(mode)}
                        aria-pressed={booleanMode === mode}
                      >
                        {mode === 'AND'
                          ? t('search.bool_and', 'AND (all words)')
                          : mode === 'OR'
                          ? t('search.bool_or', 'OR (any word)')
                          : t('search.bool_not', 'NOT (exclude)')}
                      </Chip>
                    ))}
                  </div>
                </div>

                {/* Salary range */}
                <div className="grid grid-cols-2 gap-3">
                  <Input
                    type="number"
                    label={t('search.salary_min', 'Min salary')}
                    placeholder="0"
                    value={salaryMin}
                    onChange={(e) => setSalaryMin(e.target.value)}
                    min="0"
                    step="1000"
                    size="sm"
                    classNames={{
                      input: 'bg-transparent text-theme-primary',
                      inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                    }}
                  />
                  <Input
                    type="number"
                    label={t('search.salary_max', 'Max salary')}
                    placeholder="0"
                    value={salaryMax}
                    onChange={(e) => setSalaryMax(e.target.value)}
                    min="0"
                    step="1000"
                    size="sm"
                    classNames={{
                      input: 'bg-transparent text-theme-primary',
                      inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                    }}
                  />
                </div>

                {/* Company size filter */}
                <Select
                  label={t('search.company_size_filter', 'Company size')}
                  selectedKeys={companySizeFilter ? [companySizeFilter] : []}
                  onChange={(e) => setCompanySizeFilter(e.target.value)}
                  size="sm"
                  classNames={{
                    trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                    value: 'text-theme-primary',
                  }}
                >
                  <SelectItem key="">Any size</SelectItem>
                  <SelectItem key="1-10">1–10</SelectItem>
                  <SelectItem key="11-50">11–50</SelectItem>
                  <SelectItem key="51-200">51–200</SelectItem>
                  <SelectItem key="201-500">201–500</SelectItem>
                  <SelectItem key="500+">500+</SelectItem>
                </Select>

                {/* Benefits keyword */}
                <Input
                  label={t('search.benefits_filter', 'Benefits keyword')}
                  placeholder={t('search.benefits_placeholder', 'e.g. remote, health, pension')}
                  value={benefitFilter}
                  onChange={(e) => setBenefitFilter(e.target.value)}
                  size="sm"
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                  }}
                />
              </div>
            )}
          </GlassCard>
          </div>

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

          {/* Feature 5: Geolocation radius filter */}
          <div className="flex flex-wrap items-center gap-3">
            <Button
              size="sm"
              variant={geoActive ? 'solid' : 'flat'}
              color={geoActive ? 'primary' : 'default'}
              className={geoActive ? '' : 'bg-theme-elevated text-theme-muted'}
              startContent={
                geoLoading
                  ? <Spinner size="sm" />
                  : geoActive
                    ? <MapPin className="w-3.5 h-3.5" aria-hidden="true" />
                    : <MapPinOff className="w-3.5 h-3.5" aria-hidden="true" />
              }
              onPress={handleGeoToggle}
              isDisabled={geoLoading}
              aria-pressed={geoActive}
            >
              {t('geo.near_me', 'Near me')}
            </Button>

            {geoActive && (
              <>
                <Select
                  size="sm"
                  selectedKeys={[radiusKm]}
                  onChange={(e) => setRadiusKm(e.target.value)}
                  aria-label={t('geo.radius_label', 'Search radius')}
                  className="w-32"
                  classNames={{
                    trigger: 'bg-theme-elevated border-theme-default',
                    value: 'text-theme-primary text-sm',
                  }}
                >
                  <SelectItem key="5">{t('geo.radius_5', '5 km')}</SelectItem>
                  <SelectItem key="10">{t('geo.radius_10', '10 km')}</SelectItem>
                  <SelectItem key="25">{t('geo.radius_25', '25 km')}</SelectItem>
                  <SelectItem key="50">{t('geo.radius_50', '50 km')}</SelectItem>
                  <SelectItem key="100">{t('geo.radius_100', '100 km')}</SelectItem>
                </Select>
                <Chip
                  size="sm"
                  variant="flat"
                  color="primary"
                  startContent={<MapPin className="w-3 h-3" aria-hidden="true" />}
                  endContent={
                    <button
                      onClick={() => { setGeoActive(false); setUserLat(null); setUserLng(null); }}
                      className="ml-1"
                      aria-label={t('geo.clear', 'Clear location filter')}
                    >
                      <X className="w-3 h-3" />
                    </button>
                  }
                >
                  {t('geo.within', 'Within {{radius}}km', { radius: radiusKm })}
                </Chip>
              </>
            )}
          </div>

          {/* Commitment Filter Chips */}
          <div className="flex flex-wrap gap-2" role="group" aria-label={t('filter_by_commitment', 'Filter by commitment')}>
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

          {/* Screen reader live region for results count */}
          <div aria-live="polite" aria-atomic="true" className="sr-only">
            {!isLoading && !error && `${vacancies.length} ${t('title', 'vacancies')} ${t('found', 'found')}`}
          </div>

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
        </>
      )}

      {/* J1: Saved tab content */}
      {activeTab === 'saved' && (
        <>
          {isLoadingSaved ? (
            <div className="space-y-4">
              {[1, 2].map((i) => (
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
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
              >
                {t('create_vacancy')}
              </Button>
            </Link>
          </div>
          {isLoadingMyPostings ? (
            <div className="space-y-4">
              {[1, 2].map((i) => (
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
          ) : myPostings.length === 0 ? (
            <EmptyState
              icon={<Briefcase className="w-12 h-12" aria-hidden="true" />}
              title={t('my_postings.empty_title', 'No postings yet')}
              description={t('my_postings.empty_description', 'Post a vacancy to start receiving applications from your community.')}
              action={
                <Link to={tenantPath('/jobs/create')}>
                  <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
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

      {/* Feature 4: Recommended tab content */}
      {activeTab === 'recommended' && (
        <>
          {isLoadingRecommended ? (
            <div className="flex justify-center py-12">
              <Spinner size="lg" />
            </div>
          ) : recommendedJobs.length === 0 ? (
            <EmptyState
              icon={<Sparkles className="w-12 h-12" aria-hidden="true" />}
              title={t('recommended.empty_title', 'No recommendations yet')}
              description={t('recommended.empty_description', 'Complete your profile skills to get matched with relevant opportunities')}
            />
          ) : (
            <motion.div
              variants={containerVariants}
              initial="hidden"
              animate="visible"
              className="space-y-4"
            >
              {recommendedJobs.map((vacancy) => (
                <motion.div key={vacancy.id} variants={itemVariants}>
                  <JobCard vacancy={vacancy} />
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

  // J9: Format salary
  const salaryDisplay = (() => {
    if (!vacancy.salary_min && !vacancy.salary_max) return null;
    const currency = vacancy.salary_currency || '';
    if (vacancy.salary_min && vacancy.salary_max) {
      return `${currency} ${vacancy.salary_min.toLocaleString()} - ${vacancy.salary_max.toLocaleString()}`;
    }
    if (vacancy.salary_min) return `${currency} ${vacancy.salary_min.toLocaleString()}+`;
    if (vacancy.salary_max) return `${t('salary.max_only', { max: `${currency} ${vacancy.salary_max.toLocaleString()}` })}`;
    return null;
  })();

  return (
    <Link to={tenantPath(`/jobs/${vacancy.id}`)} aria-label={vacancy.title}>
      <article>
        <GlassCard className="p-5 hover:scale-[1.01] transition-transform">
          <div className="flex gap-3 sm:gap-4">
            {/* Icon */}
            <div className="flex-shrink-0">
              <div className="w-12 h-12 sm:w-14 sm:h-14 rounded-lg bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center relative">
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
                  <Chip size="sm" variant="flat" color="warning" className="text-xs">
                    {t('featured')}
                  </Chip>
                )}
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
                {/* J1: Saved indicator */}
                {vacancy.is_saved && (
                  <BookmarkCheck className="w-4 h-4 text-warning" aria-label={t('saved.saved')} />
                )}
              </div>

              <p className="text-theme-muted text-sm mt-1">
                {vacancy.organization?.name || vacancy.creator?.name || t('unknown')}
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

                {/* J9: Salary on card */}
                {salaryDisplay && (
                  <span className="flex items-center gap-1 font-medium text-theme-primary">
                    <DollarSign className="w-4 h-4" aria-hidden="true" />
                    {salaryDisplay}
                    {vacancy.salary_negotiable && (
                      <span className="text-xs text-theme-subtle">({t('salary.negotiable')})</span>
                    )}
                  </span>
                )}

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

const MY_POSTING_STATUS_COLORS: Record<string, 'success' | 'danger' | 'warning' | 'default' | 'primary' | 'secondary'> = {
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
          <div className="w-12 h-12 rounded-lg bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center">
            <Briefcase className="w-6 h-6 text-indigo-400" aria-hidden="true" />
          </div>
        </div>

        {/* Details */}
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <h3 className="font-semibold text-theme-primary text-lg">{vacancy.title}</h3>
            {/* Status chip — prominent */}
            <Chip
              size="sm"
              variant="flat"
              color={MY_POSTING_STATUS_COLORS[vacancy.status] ?? 'default'}
              className="font-medium"
            >
              {t(`status.${vacancy.status}`, vacancy.status)}
            </Chip>
            <Chip size="sm" variant="flat" color={TYPE_CHIP_COLORS[vacancy.type] ?? 'default'} className="text-xs">
              {t(`type.${vacancy.type}`)}
            </Chip>
            <Chip size="sm" variant="flat" color="default" className="text-xs">
              {t(`commitment.${vacancy.commitment}`)}
            </Chip>
          </div>

          {/* Stats row */}
          <div className="flex flex-wrap items-center gap-4 mt-2 text-sm text-theme-subtle">
            <span className="flex items-center gap-1">
              <Eye className="w-4 h-4" aria-hidden="true" />
              {t('views', { count: vacancy.views_count })}
            </span>
            <span className={`flex items-center gap-1 ${vacancy.applications_count > 0 ? 'text-primary font-medium' : ''}`}>
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
                {t('my_postings.view_applicants', 'View Applicants')} ({vacancy.applications_count})
              </Button>
            </Link>
            <Link to={tenantPath(`/jobs/${vacancy.id}/edit`)}>
              <Button
                size="sm"
                variant="flat"
                className="bg-theme-elevated text-theme-muted"
                startContent={<Edit className="w-3.5 h-3.5" aria-hidden="true" />}
              >
                {t('my_postings.edit', 'Edit')}
              </Button>
            </Link>
          </div>
        </div>

        {/* Arrow */}
        <div className="flex-shrink-0 self-center">
          <Link to={tenantPath(`/jobs/${vacancy.id}`)} aria-label={t('jobs.view_details', 'View job details')}>
            <ChevronRight className="w-5 h-5 text-theme-subtle" aria-hidden="true" />
          </Link>
        </div>
      </div>
    </GlassCard>
  );
});

export default JobsPage;
