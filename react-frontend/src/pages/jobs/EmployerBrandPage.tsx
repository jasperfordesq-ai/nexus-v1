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
import { Button, Chip, Avatar } from '@heroui/react';
import { Briefcase, MapPin, Wifi, Building2, ChevronRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { LoadingScreen } from '@/components/feedback';
import { Breadcrumbs } from '@/components/navigation';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
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

export function EmployerBrandPage() {
  const { t } = useTranslation('jobs');
  const { userId } = useParams<{ userId: string }>();
  const { tenantPath } = useTenant();
  const abortRef = useRef<AbortController | null>(null);

  const [employer, setEmployer] = useState<EmployerProfile | null>(null);
  const [jobs, setJobs] = useState<EmployerJob[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  usePageTitle(employer?.name ?? t('employer.page_title', 'Employer Profile'));

  const loadData = useCallback(async () => {
    if (!userId) return;
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      setIsLoading(true);
      setError(null);

      const jobsRes = await api.get<{ data: EmployerJob[] }>(`/v2/jobs?user_id=${userId}&status=open&limit=50`);

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
    return () => abortRef.current?.abort();
  }, [loadData]);

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
                  {employer.company_size} employees
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
    </div>
  );
}

export default EmployerBrandPage;
