// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Avatar, Chip, Skeleton } from '@heroui/react';
import AlertCircle from 'lucide-react/icons/alert-circle';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import CalendarClock from 'lucide-react/icons/calendar-clock';
import Clock from 'lucide-react/icons/clock';
import Heart from 'lucide-react/icons/heart';
import Users from 'lucide-react/icons/users';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { useTenant } from '@/contexts';
import { useApi } from '@/hooks/useApi';
import { usePageTitle } from '@/hooks';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface RecentLog {
  date: string;
  hours: number;
  status: string;
}

interface Partner {
  id: number;
  name: string;
  avatar_url: string | null;
}

interface SupportRelationship {
  id: number;
  title: string;
  description: string;
  frequency: 'weekly' | 'fortnightly' | 'monthly' | 'ad_hoc';
  expected_hours: number;
  status: 'active' | 'paused' | 'completed' | 'cancelled';
  start_date: string;
  end_date: string | null;
  last_logged_at: string | null;
  next_check_in_at: string | null;
  role: 'supporter' | 'recipient';
  partner: Partner;
  recent_logs: RecentLog[];
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function isOverdue(nextCheckInAt: string | null): boolean {
  if (!nextCheckInAt) return false;
  return new Date(nextCheckInAt) < new Date();
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

// ---------------------------------------------------------------------------
// Sub-components
// ---------------------------------------------------------------------------

function RelationshipCardSkeleton() {
  return (
    <GlassCard className="p-5">
      <div className="flex items-start gap-4">
        <Skeleton className="h-10 w-10 rounded-full" />
        <div className="flex-1 space-y-2">
          <Skeleton className="h-4 w-1/3 rounded-lg" />
          <Skeleton className="h-3 w-2/3 rounded-lg" />
          <Skeleton className="h-3 w-1/2 rounded-lg" />
        </div>
      </div>
    </GlassCard>
  );
}

interface RelationshipCardProps {
  relationship: SupportRelationship;
  t: (key: string, opts?: Record<string, unknown>) => string;
}

function RelationshipCard({ relationship, t }: RelationshipCardProps) {
  const overdue = relationship.status === 'active' && isOverdue(relationship.next_check_in_at);

  const statusColor =
    relationship.status === 'active'
      ? 'success'
      : relationship.status === 'paused'
        ? 'warning'
        : 'default';

  const roleColor = relationship.role === 'supporter' ? 'primary' : 'secondary';

  return (
    <GlassCard className="p-5">
      {/* Header row */}
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex items-center gap-3">
          <Avatar
            src={relationship.partner.avatar_url ?? undefined}
            name={relationship.partner.name}
            size="md"
            className="shrink-0"
          />
          <div>
            <p className="font-semibold text-theme-primary">{relationship.partner.name}</p>
            <p className="text-sm text-theme-muted">{relationship.title}</p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Chip size="sm" color={roleColor} variant="flat">
            {t(`my_support_relationships.role.${relationship.role}`)}
          </Chip>
          <Chip size="sm" color={statusColor} variant="flat">
            {t(`my_support_relationships.status.${relationship.status}`)}
          </Chip>
        </div>
      </div>

      {/* Details row */}
      <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-sm text-theme-muted">
        <span className="flex items-center gap-1.5">
          <Clock className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
          {t(`my_support_relationships.frequency.${relationship.frequency}`)}
          {' · '}
          {t('my_support_relationships.expected_hours', { hours: relationship.expected_hours })}
        </span>

        {relationship.status === 'active' && relationship.next_check_in_at && (
          <span
            className={`flex items-center gap-1.5 ${overdue ? 'font-medium text-danger' : ''}`}
          >
            <CalendarClock className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
            {overdue
              ? t('my_support_relationships.overdue')
              : `${t('my_support_relationships.next_check_in')}: ${formatDate(relationship.next_check_in_at)}`}
          </span>
        )}
      </div>

      {/* Description */}
      {relationship.description && (
        <p className="mt-2 text-sm leading-6 text-theme-muted">{relationship.description}</p>
      )}

      {/* Recent logs */}
      <div className="mt-4 rounded-lg border border-theme-default bg-theme-elevated p-3">
        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-theme-muted">
          {t('my_support_relationships.recent_logs')}
        </p>
        {relationship.recent_logs.length === 0 ? (
          <p className="text-sm text-theme-muted">
            {t('my_support_relationships.no_recent_logs')}
          </p>
        ) : (
          <ul className="space-y-1.5">
            {relationship.recent_logs.map((log, i) => (
              <li key={i} className="flex items-center justify-between gap-3 text-sm">
                <span className="text-theme-muted">{formatDate(log.date)}</span>
                <span className="font-medium text-theme-primary">{log.hours}h</span>
                <Chip
                  size="sm"
                  color={
                    log.status === 'approved'
                      ? 'success'
                      : log.status === 'pending'
                        ? 'warning'
                        : 'default'
                  }
                  variant="flat"
                >
                  {log.status}
                </Chip>
              </li>
            ))}
          </ul>
        )}
      </div>
    </GlassCard>
  );
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export function MySupportRelationshipsPage() {
  const { t } = useTranslation('common');
  const { hasFeature, tenantPath } = useTenant();
  const navigate = useNavigate();
  usePageTitle(t('my_support_relationships.meta.title'));

  const { data: relationships, isLoading, error } = useApi<SupportRelationship[]>(
    '/api/v2/caring-community/my-relationships',
    { immediate: true },
  );

  // Redirect if feature is disabled
  useEffect(() => {
    if (!hasFeature('caring_community')) {
      void navigate(tenantPath('/caring-community'), { replace: true });
    }
  }, [hasFeature, navigate, tenantPath]);

  return (
    <>
      <PageMeta
        title={t('my_support_relationships.meta.title')}
        description={t('my_support_relationships.meta.description')}
        noIndex
      />

      <div className="space-y-6">
        {/* Back link */}
        <Link
          to={tenantPath('/caring-community')}
          className="inline-flex items-center gap-1.5 text-sm font-medium text-[var(--color-primary)] hover:underline"
        >
          <ArrowLeft className="h-4 w-4" aria-hidden="true" />
          {t('my_support_relationships.back')}
        </Link>

        {/* Page header */}
        <GlassCard className="p-6 sm:p-8">
          <div className="flex items-start gap-4">
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/15">
              <Users className="h-6 w-6 text-[var(--color-primary)]" aria-hidden="true" />
            </div>
            <div>
              <h1 className="text-2xl font-bold leading-tight text-theme-primary sm:text-3xl">
                {t('my_support_relationships.meta.title')}
              </h1>
              <p className="mt-2 text-base leading-8 text-theme-muted">
                {t('my_support_relationships.subtitle')}
              </p>
            </div>
          </div>
        </GlassCard>

        {/* Error state */}
        {error && !isLoading && (
          <GlassCard className="p-6">
            <div className="flex items-center gap-3 text-danger">
              <AlertCircle className="h-5 w-5 shrink-0" aria-hidden="true" />
              <p className="font-medium">{t('my_support_relationships.errors.load_failed')}</p>
            </div>
          </GlassCard>
        )}

        {/* Loading skeletons */}
        {isLoading && (
          <div className="space-y-4">
            <p className="text-center text-base text-theme-muted">{t('my_support_relationships.loading')}</p>
            {[0, 1, 2].map((i) => (
              <RelationshipCardSkeleton key={i} />
            ))}
          </div>
        )}

        {/* Empty state */}
        {!isLoading && !error && relationships !== null && relationships.length === 0 && (
          <GlassCard className="p-8 text-center">
            <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-rose-500/10">
              <Heart className="h-7 w-7 text-rose-500" aria-hidden="true" />
            </div>
            <h2 className="text-lg font-semibold text-theme-primary">
              {t('my_support_relationships.empty.title')}
            </h2>
            <p className="mt-2 text-sm text-theme-muted">
              {t('my_support_relationships.empty.body')}
            </p>
          </GlassCard>
        )}

        {/* Relationship cards */}
        {!isLoading && !error && relationships && relationships.length > 0 && (
          <div className="space-y-4">
            {relationships.map((rel) => (
              <RelationshipCard key={rel.id} relationship={rel} t={t} />
            ))}
          </div>
        )}
      </div>
    </>
  );
}

export default MySupportRelationshipsPage;
