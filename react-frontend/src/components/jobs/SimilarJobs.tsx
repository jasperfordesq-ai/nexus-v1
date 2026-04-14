// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Link } from 'react-router-dom';
import { Chip } from '@heroui/react';
import {
  Briefcase,
  MapPin,
  DollarSign,
  Heart,
  Timer,
  Globe,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import type { JobVacancy } from './JobDetailTypes';
import { TYPE_CHIP_COLORS } from './JobDetailTypes';

const TYPE_ICONS: Record<string, typeof DollarSign> = {
  paid: DollarSign,
  volunteer: Heart,
  timebank: Timer,
};

interface SimilarJobsProps {
  jobs: JobVacancy[];
  tenantPath: (path: string) => string;
}

export function SimilarJobs({ jobs, tenantPath }: SimilarJobsProps) {
  const { t } = useTranslation('jobs');

  if (jobs.length === 0) return null;

  return (
    <div className="mt-6">
      <h2 className="text-lg font-semibold text-theme-primary mb-4">{t('similar_jobs')}</h2>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {jobs.map((sj) => {
          const SjTypeIcon = TYPE_ICONS[sj.type] ?? Briefcase;
          return (
            <Link key={sj.id} to={tenantPath(`/jobs/${sj.id}`)}>
              <GlassCard className="p-4 hover:scale-[1.02] transition-transform h-full">
                <div className="flex items-center gap-2 mb-2">
                  <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center flex-shrink-0">
                    <Briefcase className="w-4 h-4 text-blue-400" aria-hidden="true" />
                  </div>
                  <h3 className="font-medium text-theme-primary text-sm line-clamp-2">{sj.title}</h3>
                </div>
                <p className="text-xs text-theme-muted mb-2">
                  {sj.organization?.name ?? sj.creator?.name}
                </p>
                <div className="flex flex-wrap gap-1">
                  <Chip size="sm" variant="flat" color={TYPE_CHIP_COLORS[sj.type] ?? 'default'} className="text-xs">
                    <span className="flex items-center gap-0.5">
                      <SjTypeIcon className="w-3 h-3" aria-hidden="true" />
                      {t(`type.${sj.type}`)}
                    </span>
                  </Chip>
                  {sj.is_remote ? (
                    <Chip size="sm" variant="flat" color="primary" className="text-xs">
                      <span className="flex items-center gap-0.5">
                        <Globe className="w-3 h-3" aria-hidden="true" />
                        {t('remote')}
                      </span>
                    </Chip>
                  ) : sj.location ? (
                    <Chip size="sm" variant="flat" color="default" className="text-xs">
                      <span className="flex items-center gap-0.5">
                        <MapPin className="w-3 h-3" aria-hidden="true" />
                        {sj.location}
                      </span>
                    </Chip>
                  ) : null}
                </div>
              </GlassCard>
            </Link>
          );
        })}
      </div>
    </div>
  );
}
