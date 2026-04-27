// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ClubsPage — AG15: Verein (club/association) directory
 *
 * Displays all vol_organizations where org_type = 'club' for the current tenant.
 * Public page — no authentication required.
 *
 * API: GET /api/v2/clubs
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { motion } from 'framer-motion';
import { Avatar, Button, Input } from '@heroui/react';
import Users from 'lucide-react/icons/users';
import Search from 'lucide-react/icons/search';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Calendar from 'lucide-react/icons/calendar';
import Globe from 'lucide-react/icons/globe';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { Breadcrumbs } from '@/components/navigation';
import { PageMeta } from '@/components/seo/PageMeta';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

/* ───────────────────────── Types ───────────────────────── */

interface Club {
  id: number;
  name: string;
  description: string | null;
  logo_url: string | null;
  contact_email: string | null;
  website: string | null;
  meeting_schedule: string | null;
  member_count: number;
  created_at: string;
}

const ITEMS_PER_PAGE = 20;
const SEARCH_DEBOUNCE_MS = 300;

/* ───────────────────────── Main Component ───────────────────────── */

export function ClubsPage() {
  const { t } = useTranslation('common');
  usePageTitle(t('clubs.meta.title'));

  const [clubs, setClubs] = useState<Club[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(0);
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const [isLoadingMore, setIsLoadingMore] = useState(false);

  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    searchTimeoutRef.current = setTimeout(() => {
      setDebouncedQuery(searchQuery);
      setPage(1);
    }, SEARCH_DEBOUNCE_MS);
    return () => {
      if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    };
  }, [searchQuery]);

  const loadClubs = useCallback(async (pageNum = 1, append = false) => {
    try {
      if (!append) {
        setIsLoading(true);
        setError(null);
      } else {
        setIsLoadingMore(true);
      }

      const params = new URLSearchParams();
      params.set('per_page', String(ITEMS_PER_PAGE));
      params.set('page', String(pageNum));
      if (debouncedQuery.trim()) params.set('search', debouncedQuery.trim());

      const response = await api.get<Club[]>(
        `/v2/clubs?${params}`
      );

      if (response.success) {
        const items = Array.isArray(response.data) ? response.data : [];
        if (append) {
          setClubs((prev) => [...prev, ...items]);
        } else {
          setClubs(items);
        }
        const meta = response.meta as Record<string, number> | undefined;
        setTotalPages(meta?.total_pages ?? 1);
      } else {
        if (!append) setError(t('clubs.errors.load_failed'));
      }
    } catch (err) {
      logError('Failed to load clubs', err);
      if (!append) setError(t('clubs.errors.load_failed'));
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [debouncedQuery, t]);

  useEffect(() => {
    loadClubs(1);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debouncedQuery]);

  const handleLoadMore = () => {
    const nextPage = page + 1;
    setPage(nextPage);
    loadClubs(nextPage, true);
  };

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
      <PageMeta
        title={t('clubs.meta.title')}
        description={t('clubs.meta.description')}
      />

      <Breadcrumbs items={[{ label: t('clubs.meta.title') }]} />

      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <Users className="w-7 h-7 text-indigo-400" aria-hidden="true" />
          {t('clubs.meta.title')}
        </h1>
        <p className="text-theme-muted mt-1">{t('clubs.subtitle')}</p>
      </div>

      {/* Search */}
      <div className="w-full sm:max-w-md">
        <Input
          placeholder={t('clubs.search_placeholder')}
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          startContent={<Search className="w-4 h-4 text-theme-muted" aria-hidden="true" />}
          aria-label={t('clubs.search_placeholder')}
          classNames={{
            input: 'bg-transparent text-theme-primary',
            inputWrapper: 'bg-theme-elevated border-theme-default',
          }}
        />
      </div>

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-[var(--color-warning)] mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{error}</h2>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white mt-4"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadClubs(1)}
          >
            {t('accessibility.close', 'Retry')}
          </Button>
        </GlassCard>
      )}

      {/* Club Grid */}
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
          ) : clubs.length === 0 ? (
            <EmptyState
              icon={<Users className="w-12 h-12" aria-hidden="true" />}
              title={t('clubs.empty.title')}
              description={t('clubs.empty.body')}
            />
          ) : (
            <>
              <motion.div
                variants={containerVariants}
                initial="hidden"
                animate="visible"
                className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"
              >
                {clubs.map((club) => (
                  <motion.div key={club.id} variants={itemVariants}>
                    <ClubCard club={club} />
                  </motion.div>
                ))}
              </motion.div>

              {page < totalPages && (
                <div className="pt-4 text-center">
                  <Button
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    onPress={handleLoadMore}
                    isLoading={isLoadingMore}
                  >
                    {t('clubs.view', 'Load more')}
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

/* ───────────────────────── Club Card ───────────────────────── */

function ClubCard({ club }: { club: Club }) {
  const { t } = useTranslation('common');

  return (
    <GlassCard hoverable className="p-5 h-full flex flex-col gap-3">
      {/* Header */}
      <div className="flex items-center gap-3">
        <Avatar
          name={club.name}
          src={club.logo_url ?? undefined}
          size="lg"
          className="flex-shrink-0"
        />
        <div className="min-w-0 flex-1">
          <h3 className="font-semibold text-theme-primary truncate">{club.name}</h3>
          {club.member_count > 0 && (
            <p className="text-xs text-theme-subtle flex items-center gap-1 mt-0.5">
              <Users className="w-3 h-3" aria-hidden="true" />
              {t('clubs.member_count', { count: club.member_count })}
            </p>
          )}
        </div>
      </div>

      {/* Description */}
      {club.description && (
        <p className="text-sm text-theme-muted line-clamp-2 flex-1">{club.description}</p>
      )}

      {/* Meta */}
      <div className="flex flex-wrap items-center gap-3 text-xs text-theme-subtle mt-auto">
        {club.meeting_schedule && (
          <span className="flex items-center gap-1">
            <Calendar className="w-3 h-3 text-indigo-400" aria-hidden="true" />
            {t('clubs.meeting_schedule', { schedule: club.meeting_schedule })}
          </span>
        )}
        {club.website && (
          <a
            href={club.website}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-1 text-blue-400 hover:underline"
          >
            <Globe className="w-3 h-3" aria-hidden="true" />
            {club.website.replace(/^https?:\/\//, '').replace(/\/$/, '')}
          </a>
        )}
      </div>
    </GlassCard>
  );
}

export default ClubsPage;
