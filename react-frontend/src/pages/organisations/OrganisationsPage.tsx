// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Organisations Page - Browse volunteer organisations
 *
 * Uses V2 API: GET /api/v2/volunteering/organisations
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Avatar } from '@heroui/react';
import {
  Building2,
  Search,
  RefreshCw,
  AlertTriangle,
  MapPin,
  Globe,
  Heart,
  Clock,
  Star,
  Users,
  Plus,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { Breadcrumbs } from '@/components/navigation';
import { useAuth, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

/* ───────────────────────── Types ───────────────────────── */

interface Organisation {
  id: number;
  name: string;
  description: string | null;
  logo_url: string | null;
  website: string | null;
  contact_email: string | null;
  location: string | null;
  opportunity_count: number;
  total_hours: number;
  volunteer_count: number;
  average_rating: number | null;
  created_at: string;
}

const ITEMS_PER_PAGE = 20;
const SEARCH_DEBOUNCE_MS = 300;

/* ───────────────────────── Main Component ───────────────────────── */

export function OrganisationsPage() {
  const { t } = useTranslation('community');
  usePageTitle(t('organisations.page_title'));
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();

  const [organisations, setOrganisations] = useState<Organisation[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [cursor, setCursor] = useState<string | undefined>();
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const [isLoadingMore, setIsLoadingMore] = useState(false);

  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Debounce search
  useEffect(() => {
    if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    searchTimeoutRef.current = setTimeout(() => {
      setDebouncedQuery(searchQuery);
    }, SEARCH_DEBOUNCE_MS);
    return () => {
      if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    };
  }, [searchQuery]);

  const loadOrganisations = useCallback(async (append = false) => {
    try {
      if (!append) {
        setIsLoading(true);
        setError(null);
      } else {
        setIsLoadingMore(true);
      }

      const params = new URLSearchParams();
      params.set('per_page', String(ITEMS_PER_PAGE));
      if (append && cursor) params.set('cursor', cursor);
      if (debouncedQuery.trim()) params.set('search', debouncedQuery.trim());

      const response = await api.get<{ data: Organisation[]; meta: { cursor: string | null; has_more: boolean } }>(
        `/v2/volunteering/organisations?${params}`
      );

      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : [];

        if (append) {
          setOrganisations((prev) => [...prev, ...items]);
        } else {
          setOrganisations(items);
        }
        setHasMore(response.meta?.has_more ?? false);
        setCursor(response.meta?.cursor ?? undefined);
      } else {
        if (!append) setError(t('organisations.error_load'));
      }
    } catch (err) {
      logError('Failed to load organisations', err);
      if (!append) setError(t('organisations.error_load_retry'));
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [cursor, debouncedQuery]);

  // Reset on search change
  useEffect(() => {
    setCursor(undefined);
    loadOrganisations();
  }, [debouncedQuery]); // eslint-disable-line react-hooks/exhaustive-deps

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.05 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="space-y-6">
      {/* Breadcrumbs */}
      <Breadcrumbs items={[
        { label: t('organisations.breadcrumb_volunteering'), href: '/volunteering' },
        { label: t('organisations.breadcrumb_organisations') },
      ]} />

      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Building2 className="w-7 h-7 text-indigo-400" aria-hidden="true" />
            {t('organisations.heading')}
          </h1>
          <p className="text-theme-muted mt-1">{t('organisations.subtitle')}</p>
        </div>
        {isAuthenticated && (
          <Link to={tenantPath('/organisations/register')}>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<Plus className="w-4 h-4" />}
            >
              {t('organisations.register_button')}
            </Button>
          </Link>
        )}
      </div>

      {/* Search */}
      <div className="w-full sm:max-w-md">
        <Input
          placeholder={t('organisations.search_placeholder')}
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          startContent={<Search className="w-4 h-4 text-theme-muted" aria-hidden="true" />}
          aria-label={t('organisations.search_placeholder')}
          classNames={{
            input: 'bg-transparent text-theme-primary',
            inputWrapper: 'bg-theme-elevated border-theme-default',
          }}
        />
      </div>

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('organisations.unable_to_load')}</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadOrganisations()}
          >
            {t('organisations.try_again')}
          </Button>
        </GlassCard>
      )}

      {/* Organisations Grid */}
      {!error && (
        <>
          {isLoading ? (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {[1, 2, 3, 4, 5, 6].map((i) => (
                <GlassCard key={i} className="p-5 animate-pulse">
                  <div className="flex items-center gap-3 mb-3">
                    <div className="w-12 h-12 bg-theme-hover rounded-xl" />
                    <div className="flex-1">
                      <div className="h-4 bg-theme-hover rounded w-3/4 mb-2" />
                      <div className="h-3 bg-theme-hover rounded w-1/2" />
                    </div>
                  </div>
                  <div className="h-3 bg-theme-hover rounded w-full mb-2" />
                  <div className="h-3 bg-theme-hover rounded w-2/3" />
                </GlassCard>
              ))}
            </div>
          ) : organisations.length === 0 ? (
            <EmptyState
              icon={<Building2 className="w-12 h-12" aria-hidden="true" />}
              title={t('organisations.no_organisations_found')}
              description={debouncedQuery ? t('organisations.try_different_search') : t('organisations.no_organisations_available')}
            />
          ) : (
            <>
              <motion.div
                variants={containerVariants}
                initial="hidden"
                animate="visible"
                className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"
              >
                {organisations.map((org) => (
                  <motion.div key={org.id} variants={itemVariants}>
                    <OrganisationCard organisation={org} />
                  </motion.div>
                ))}
              </motion.div>

              {hasMore && (
                <div className="pt-4 text-center">
                  <Button
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    onPress={() => loadOrganisations(true)}
                    isLoading={isLoadingMore}
                  >
                    {t('organisations.load_more')}
                  </Button>
                </div>
              )}
            </>
          )}
        </>
      )}
    </div>
  );
}

/* ───────────────────────── Organisation Card ───────────────────────── */

interface OrganisationCardProps {
  organisation: Organisation;
}

function OrganisationCard({ organisation }: OrganisationCardProps) {
  const { t } = useTranslation('community');
  const { tenantPath } = useTenant();
  return (
    <Link to={tenantPath(`/organisations/${organisation.id}`)}>
      <GlassCard hoverable className="p-5 h-full">
        <div className="flex items-center gap-3 mb-3">
          <Avatar
            name={organisation.name}
            src={organisation.logo_url ?? undefined}
            size="lg"
            className="flex-shrink-0"
          />
          <div className="min-w-0 flex-1">
            <h3 className="font-semibold text-theme-primary truncate">{organisation.name}</h3>
            {organisation.location && (
              <p className="text-xs text-theme-subtle flex items-center gap-1 mt-0.5">
                <MapPin className="w-3 h-3" aria-hidden="true" />
                {organisation.location}
              </p>
            )}
          </div>
        </div>

        {organisation.description && (
          <p className="text-sm text-theme-muted mb-3 line-clamp-2">{organisation.description}</p>
        )}

        {/* Stats */}
        <div className="flex flex-wrap items-center gap-3 text-xs text-theme-subtle">
          {organisation.opportunity_count > 0 && (
            <span className="flex items-center gap-1">
              <Heart className="w-3 h-3 text-rose-400" aria-hidden="true" />
              {t('organisations.opportunity_count', { count: organisation.opportunity_count })}
            </span>
          )}
          {organisation.volunteer_count > 0 && (
            <span className="flex items-center gap-1">
              <Users className="w-3 h-3 text-indigo-400" aria-hidden="true" />
              {t('organisations.volunteer_count', { count: organisation.volunteer_count })}
            </span>
          )}
          {organisation.total_hours > 0 && (
            <span className="flex items-center gap-1">
              <Clock className="w-3 h-3 text-emerald-400" aria-hidden="true" />
              {t('organisations.hours_logged', { hours: organisation.total_hours })}
            </span>
          )}
          {organisation.average_rating && organisation.average_rating > 0 && (
            <span className="flex items-center gap-1">
              <Star className="w-3 h-3 text-amber-400 fill-amber-400" aria-hidden="true" />
              {organisation.average_rating.toFixed(1)}
            </span>
          )}
          {organisation.website && (
            <span className="flex items-center gap-1">
              <Globe className="w-3 h-3 text-blue-400" aria-hidden="true" />
              {t('organisations.website')}
            </span>
          )}
        </div>
      </GlassCard>
    </Link>
  );
}

export default OrganisationsPage;
