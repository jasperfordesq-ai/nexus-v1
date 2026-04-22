// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Divider } from '@heroui/react';
import MapPin from 'lucide-react/icons/map-pin';
import Clock from 'lucide-react/icons/clock';
import DollarSign from 'lucide-react/icons/dollar-sign';
import Mail from 'lucide-react/icons/mail';
import Phone from 'lucide-react/icons/phone';
import Timer from 'lucide-react/icons/timer';
import Tag from 'lucide-react/icons/tag';
import TrendingUp from 'lucide-react/icons/trending-up';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import type { JobVacancy } from './JobDetailTypes';

interface SalaryBenchmark {
  role_keyword: string;
  salary_min: number;
  salary_max: number;
  salary_median: number;
  salary_type: string;
  currency: string;
}

interface JobMetadataSidebarProps {
  vacancy: JobVacancy;
  isOwner: boolean;
  benchmark: SalaryBenchmark | null;
  formatSalary: () => string | null;
}

export function JobMetadataSidebar({
  vacancy,
  isOwner,
  benchmark,
  formatSalary,
}: JobMetadataSidebarProps) {
  const { t } = useTranslation('jobs');
  const salaryDisplay = formatSalary();

  return (
    <GlassCard className="p-6 space-y-4">
      {vacancy.category && (
        <div className="flex items-center gap-3">
          <Tag className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
          <div>
            <p className="text-xs text-theme-subtle">{t('detail.category_label')}</p>
            <p className="text-sm text-theme-primary">{vacancy.category}</p>
          </div>
        </div>
      )}

      {vacancy.location && !vacancy.is_remote && (
        <div className="flex items-center gap-3">
          <MapPin className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
          <div>
            <p className="text-xs text-theme-subtle">{t('detail.location_label', 'Location')}</p>
            <p className="text-sm text-theme-primary">{vacancy.location}</p>
          </div>
        </div>
      )}

      {vacancy.hours_per_week !== null && (
        <div className="flex items-center gap-3">
          <Clock className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
          <div>
            <p className="text-xs text-theme-subtle">{t('detail.hours_label')}</p>
            <p className="text-sm text-theme-primary">{t('hours_per_week', { count: vacancy.hours_per_week })}</p>
          </div>
        </div>
      )}

      {vacancy.time_credits !== null && (
        <div className="flex items-center gap-3">
          <Timer className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
          <div>
            <p className="text-xs text-theme-subtle">{t('detail.time_credits_label')}</p>
            <p className="text-sm text-theme-primary">{t('time_credits_label', { count: vacancy.time_credits })}</p>
          </div>
        </div>
      )}

      {/* Salary — EU Pay Transparency */}
      <div className="flex items-center gap-3">
        <DollarSign className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
        <div>
          <p className="text-xs text-theme-subtle">{t('salary.label')}</p>
          {salaryDisplay ? (
            <p className="text-sm text-theme-primary font-medium">{salaryDisplay}</p>
          ) : !vacancy.salary_negotiable ? (
            <p className="text-sm text-theme-muted">{t('salary_not_specified')}</p>
          ) : null}
          {vacancy.salary_negotiable && (
            <p className="text-xs text-success">{t('salary.negotiable')}</p>
          )}
        </div>
      </div>

      {/* Salary benchmark (owners only) */}
      {isOwner && benchmark && (
        <div className="flex items-start gap-2 bg-primary/5 rounded-lg p-2.5">
          <TrendingUp className="w-4 h-4 text-primary shrink-0 mt-0.5" aria-hidden="true" />
          <p className="text-xs text-theme-primary">
            {t('benchmark.market_rate', {
              role: benchmark.role_keyword,
              currency: benchmark.currency,
              min: (benchmark.salary_min ?? 0).toLocaleString(),
              max: (benchmark.salary_max ?? 0).toLocaleString(),
              type: benchmark.salary_type,
              median: (benchmark.salary_median ?? 0).toLocaleString(),
              defaultValue: `Market rate for "${benchmark.role_keyword}": ${benchmark.currency}${(benchmark.salary_min ?? 0).toLocaleString()} – ${benchmark.currency}${(benchmark.salary_max ?? 0).toLocaleString()} / ${benchmark.salary_type} (median: ${benchmark.currency}${(benchmark.salary_median ?? 0).toLocaleString()})`,
            })}
          </p>
        </div>
      )}

      {(vacancy.contact_email || vacancy.contact_phone) && (
        <>
          <Divider />
          <h3 className="text-sm font-semibold text-theme-primary">{t('detail.contact_label')}</h3>

          {vacancy.contact_email && (
            <div className="flex items-center gap-3">
              <Mail className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
              <a href={`mailto:${vacancy.contact_email}`} className="text-sm text-primary hover:underline">
                {vacancy.contact_email}
              </a>
            </div>
          )}

          {vacancy.contact_phone && (
            <div className="flex items-center gap-3">
              <Phone className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
              <a href={`tel:${vacancy.contact_phone}`} className="text-sm text-primary hover:underline">
                {vacancy.contact_phone}
              </a>
            </div>
          )}
        </>
      )}
    </GlassCard>
  );
}
