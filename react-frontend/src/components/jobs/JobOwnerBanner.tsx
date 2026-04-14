// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Link } from 'react-router-dom';
import { Button } from '@heroui/react';
import {
  Briefcase,
  Edit3,
  BarChart3,
  Users,
  CalendarClock,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { JobVacancy } from './JobDetailTypes';

interface JobOwnerBannerProps {
  vacancy: JobVacancy;
  tenantPath: (path: string) => string;
  onVacancyUpdated: () => void;
}

export function JobOwnerBanner({ vacancy, tenantPath, onVacancyUpdated }: JobOwnerBannerProps) {
  const { t } = useTranslation('jobs');

  const handleCloseVacancy = async () => {
    try {
      const res = await api.put(`/v2/jobs/${vacancy.id}`, { status: 'closed' });
      if (res.success) {
        onVacancyUpdated();
      }
    } catch (err) {
      logError('Failed to close vacancy', err);
    }
  };

  const handleReopenVacancy = async () => {
    try {
      const res = await api.put(`/v2/jobs/${vacancy.id}`, { status: 'open' });
      if (res.success) {
        onVacancyUpdated();
      }
    } catch (err) {
      logError('Failed to reopen vacancy', err);
    }
  };

  return (
    <GlassCard className="p-4 bg-gradient-to-r from-indigo-500/10 to-purple-500/10 border border-indigo-500/20">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-lg bg-indigo-500/20 flex items-center justify-center">
            <Briefcase className="w-5 h-5 text-indigo-400" aria-hidden="true" />
          </div>
          <div>
            <p className="font-semibold text-theme-primary">{t('detail.owner_banner_title', 'You posted this vacancy')}</p>
            <p className="text-sm text-theme-muted">
              {vacancy.applications_count > 0
                ? t('detail.owner_has_applicants', '{{count}} applicant(s) — scroll down to review', { count: vacancy.applications_count })
                : t('detail.owner_no_applicants', 'No applicants yet — share this listing to get more visibility')}
            </p>
          </div>
        </div>
        <div className="flex gap-2 flex-wrap">
          <Link to={tenantPath(`/jobs/${vacancy.id}/edit`)}>
            <Button size="sm" variant="flat" className="bg-theme-elevated text-theme-muted" startContent={<Edit3 className="w-4 h-4" aria-hidden="true" />}>
              {t('detail.edit')}
            </Button>
          </Link>
          <Link to={tenantPath(`/jobs/${vacancy.id}/analytics`)}>
            <Button size="sm" variant="flat" className="bg-theme-elevated text-theme-muted" startContent={<BarChart3 className="w-4 h-4" aria-hidden="true" />}>
              {t('detail.analytics')}
            </Button>
          </Link>
          <Link to={tenantPath(`/jobs/${vacancy.id}/kanban`)}>
            <Button size="sm" variant="flat" color="primary" startContent={<Users className="w-4 h-4" aria-hidden="true" />}>
              {t('detail.kanban_board', 'Kanban Board')}
            </Button>
          </Link>
          <Button
            size="sm"
            variant="flat"
            color="secondary"
            startContent={<CalendarClock className="w-4 h-4" aria-hidden="true" />}
            onPress={() => {
              const el = document.getElementById('interview-slots-section');
              if (el) el.scrollIntoView({ behavior: 'smooth' });
            }}
          >
            {t('self_scheduling.manage_slots', 'Interview Slots')}
          </Button>
          {vacancy.status === 'open' && (
            <Button size="sm" color="warning" variant="flat" onPress={handleCloseVacancy}>
              {t('detail.close_vacancy', 'Close Vacancy')}
            </Button>
          )}
          {vacancy.status !== 'open' && (
            <Button size="sm" color="success" variant="flat" onPress={handleReopenVacancy}>
              {t('detail.reopen_vacancy', 'Reopen')}
            </Button>
          )}
        </div>
      </div>
    </GlassCard>
  );
}
