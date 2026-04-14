// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button } from '@heroui/react';
import { Users, ChevronRight, RefreshCw } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { ApplicationCard } from './ApplicationCard';
import type { Application, JobVacancy } from './JobDetailTypes';

interface JobApplicationsListProps {
  vacancy: JobVacancy;
  applications: Application[];
  isLoadingApps: boolean;
  showApplications: boolean;
  onToggleShow: () => void;
  onUpdateStatus: (applicationId: number, status: string) => void;
  onRefresh: () => void;
  tenantPath: (path: string) => string;
  navigateFn: (path: string) => void;
}

export function JobApplicationsList({
  vacancy,
  applications,
  isLoadingApps,
  showApplications,
  onToggleShow,
  onUpdateStatus,
  onRefresh,
  tenantPath,
  navigateFn,
}: JobApplicationsListProps) {
  const { t } = useTranslation('jobs');

  return (
    <div id="applications">
      <GlassCard className="p-6">
        <Button
          variant="light"
          onPress={onToggleShow}
          className="flex items-center gap-2 w-full text-left justify-start h-auto p-0"
          startContent={<Users className="w-5 h-5 text-theme-subtle" aria-hidden="true" />}
          endContent={<ChevronRight className={`w-4 h-4 ml-auto text-theme-subtle transition-transform ${showApplications ? 'rotate-90' : ''}`} aria-hidden="true" />}
        >
          <h2 className="text-lg font-semibold text-theme-primary">
            {t('detail.applications_tab')} ({vacancy.applications_count})
          </h2>
        </Button>

        {showApplications && (
          <div className="mt-4 space-y-4">
            {isLoadingApps ? (
              <div className="space-y-3">
                {[1, 2].map((i) => (
                  <div key={i} className="p-4 rounded-lg bg-theme-elevated animate-pulse">
                    <div className="h-4 bg-theme-hover rounded w-1/3 mb-2" />
                    <div className="h-3 bg-theme-hover rounded w-2/3" />
                  </div>
                ))}
              </div>
            ) : applications.length === 0 ? (
              <div className="text-center py-8 space-y-3">
                <div className="w-14 h-14 rounded-full bg-theme-elevated flex items-center justify-center mx-auto">
                  <Users className="w-7 h-7 text-theme-subtle" aria-hidden="true" />
                </div>
                <p className="font-medium text-theme-primary">{t('detail.no_applications', 'No applications yet')}</p>
                <p className="text-sm text-theme-muted">{t('detail.no_applications_desc', "Share your vacancy to attract candidates. When someone applies, they'll appear here.")}</p>
                <div className="flex gap-2 justify-center">
                  <Button
                    size="sm"
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    startContent={<RefreshCw className="w-3.5 h-3.5" aria-hidden="true" />}
                    onPress={onRefresh}
                  >
                    {t('detail.refresh', 'Refresh')}
                  </Button>
                </div>
              </div>
            ) : (
              applications.map((app) => (
                <ApplicationCard
                  key={app.id}
                  application={app}
                  onUpdateStatus={onUpdateStatus}
                  tenantPathFn={tenantPath}
                  navigateFn={navigateFn}
                />
              ))
            )}
          </div>
        )}
      </GlassCard>
    </div>
  );
}
