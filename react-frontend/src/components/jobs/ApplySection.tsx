// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Link, useNavigate } from 'react-router-dom';
import { Button, Chip } from '@heroui/react';
import { CheckCircle, MessageCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { JobVacancy } from './JobDetailTypes';

interface SavedProfile {
  cv_filename?: string;
  cover_text?: string;
}

interface ApplySectionProps {
  vacancy: JobVacancy;
  isAuthenticated: boolean;
  isOwner: boolean;
  isSubmitting: boolean;
  savedProfile: SavedProfile | null;
  tenantPath: (path: string) => string;
  onApplyOpen: () => void;
  onQuickApplySuccess: () => void;
  onQuickApplyError: (msg: string) => void;
  setIsSubmitting: (v: boolean) => void;
}

export function ApplySection({
  vacancy,
  isAuthenticated,
  isOwner,
  isSubmitting,
  savedProfile,
  tenantPath,
  onApplyOpen,
  onQuickApplySuccess,
  onQuickApplyError,
  setIsSubmitting,
}: ApplySectionProps) {
  const { t } = useTranslation('jobs');
  const navigate = useNavigate();

  const handleQuickApply = async () => {
    if (!vacancy.id || isSubmitting) return;
    setIsSubmitting(true);
    try {
      const response = await api.post(`/v2/jobs/${vacancy.id}/apply`, {
        message: savedProfile?.cover_text ?? '',
      });
      if (response.success) {
        onQuickApplySuccess();
      } else {
        onQuickApplyError((response as { error?: string }).error || t('apply.error'));
      }
    } catch (err) {
      logError('Quick apply failed', err);
      onQuickApplyError(t('apply.error'));
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <GlassCard className="p-6">
      {!isAuthenticated ? (
        <div className="text-center">
          <p className="text-theme-muted mb-3">{t('apply.login_required')}</p>
          <Link to={tenantPath('/login')}>
            <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white w-full">
              {t('apply.button')}
            </Button>
          </Link>
        </div>
      ) : isOwner ? (
        <div className="text-center">
          <Chip variant="flat" color="default" className="text-sm">
            {t('apply.own_vacancy')}
          </Chip>
        </div>
      ) : vacancy.status !== 'open' ? (
        <div className="text-center">
          <Chip variant="flat" color="warning" className="text-sm">
            {t('apply.closed')}
          </Chip>
        </div>
      ) : vacancy.has_applied ? (
        <div className="space-y-3">
          <div className="text-center space-y-2">
            <Button
              isDisabled
              className="w-full"
              variant="flat"
              color="success"
              startContent={<CheckCircle className="w-4 h-4" aria-hidden="true" />}
            >
              {t('already_applied')}
            </Button>
            <p className="text-xs text-theme-muted">
              {t('application_status_label')}: {t(`application_status.${vacancy.application_stage ?? vacancy.application_status ?? 'applied'}`)}
            </p>
          </div>
          <Button
            variant="flat"
            startContent={<MessageCircle size={16} aria-hidden="true" />}
            className="w-full bg-theme-elevated text-theme-muted"
            onPress={() => navigate(tenantPath(`/messages?user=${vacancy.creator?.id}&context=job&context_id=${vacancy.id}`))}
          >
            {t('apply.message_employer', 'Message Employer')}
          </Button>
        </div>
      ) : (
        <div className="flex flex-col gap-2">
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white w-full"
            size="lg"
            onPress={onApplyOpen}
            aria-label={t('apply.button_label', 'Apply for {{title}}', { title: vacancy.title ?? 'this job' })}
          >
            {t('apply.button')}
          </Button>
          {savedProfile?.cover_text && (
            <Button
              variant="flat"
              color="primary"
              size="sm"
              className="w-full"
              isLoading={isSubmitting}
              onPress={handleQuickApply}
            >
              {t('apply.quick_apply', 'Quick Apply with Saved Profile')}
            </Button>
          )}
        </div>
      )}
    </GlassCard>
  );
}
