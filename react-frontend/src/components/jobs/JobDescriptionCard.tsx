// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import { Button, Chip } from '@heroui/react';
import {
  Target,
  Sparkles,
  Building2,
  ChevronUp,
  ChevronDown,
  Check,
  X,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { SafeHtml } from '@/components/ui/SafeHtml';
import type { JobVacancy, MatchResult, QualificationData } from './JobDetailTypes';

interface JobDescriptionCardProps {
  vacancy: JobVacancy;
  isOwner: boolean;
  isAuthenticated: boolean;
  matchResult: MatchResult | null;
  qualificationData: QualificationData | null;
  onCheckQualification: () => void;
}

export function JobDescriptionCard({
  vacancy,
  isOwner,
  isAuthenticated,
  matchResult,
  qualificationData,
  onCheckQualification,
}: JobDescriptionCardProps) {
  const { t } = useTranslation('jobs');
  const [qualOpen, setQualOpen] = useState(false);

  return (
    <>
      {/* Description */}
      <GlassCard className="p-6">
        <h2 className="text-lg font-semibold text-theme-primary mb-4">{t('detail.about')}</h2>
        <SafeHtml content={vacancy.description} className="text-theme-secondary whitespace-pre-wrap" as="div" />
      </GlassCard>

      {/* Match Explanation Card — "Why You Match" */}
      {qualificationData && !isOwner && (
        <GlassCard className="p-4 mt-4">
          <button
            className="w-full flex items-center justify-between text-left"
            onClick={() => setQualOpen(v => !v)}
            aria-expanded={qualOpen}
          >
            <span className="font-semibold flex items-center gap-2">
              <Sparkles size={16} className={qualificationData.percentage >= 70 ? 'text-success' : 'text-warning'} aria-hidden="true" />
              {t('match.why_you_match', 'Why you match')} — {qualificationData.percentage}%
            </span>
            {qualOpen ? <ChevronUp size={16} aria-hidden="true" /> : <ChevronDown size={16} aria-hidden="true" />}
          </button>
          {qualOpen && (
            <div className="mt-3 space-y-3">
              <p className="text-sm text-theme-secondary italic">{qualificationData.ai_summary}</p>
              {qualificationData.dimensions.length > 0 && (
                <div className="grid grid-cols-2 gap-2">
                  {qualificationData.dimensions.map((d, i) => (
                    <div key={i} className="bg-white/5 rounded-lg p-2">
                      <div className="text-xs font-medium text-theme-primary">{d.label}</div>
                      <div className="text-xs text-theme-muted">{d.detail}</div>
                    </div>
                  ))}
                </div>
              )}
              {qualificationData.matched_skills.length > 0 && (
                <div>
                  <p className="text-xs font-medium text-success mb-1">{t('match.you_have', 'You have:')}</p>
                  <div className="flex flex-wrap gap-1">
                    {qualificationData.matched_skills.map((s, i) => (
                      <Chip key={i} size="sm" color="success" variant="flat">{s}</Chip>
                    ))}
                  </div>
                </div>
              )}
              {qualificationData.missing_skills.length > 0 && (
                <div>
                  <p className="text-xs font-medium text-warning mb-1">{t('match.to_develop', 'Skills to develop:')}</p>
                  <div className="flex flex-wrap gap-1">
                    {qualificationData.missing_skills.map((s, i) => (
                      <Chip key={i} size="sm" color="warning" variant="flat">{s}</Chip>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}
        </GlassCard>
      )}

      {/* Employer Branding */}
      {(vacancy.tagline || vacancy.video_url || (vacancy.benefits && vacancy.benefits.length > 0)) && (
        <GlassCard className="p-5 mt-4">
          <h2 className="text-base font-semibold mb-3 flex items-center gap-2">
            <Building2 size={16} aria-hidden="true" />
            {t('branding.about_company', 'About the Company')}
          </h2>
          {vacancy.tagline && (
            <p className="text-sm text-theme-secondary italic mb-3">&ldquo;{vacancy.tagline}&rdquo;</p>
          )}
          {vacancy.video_url && (
            <div className="aspect-video rounded-lg overflow-hidden mb-3">
              <iframe
                src={vacancy.video_url
                  .replace('watch?v=', 'embed/')
                  .replace('youtu.be/', 'youtube.com/embed/')}
                className="w-full h-full"
                allowFullScreen
                title={t('branding.video_label')}
              />
            </div>
          )}
          {vacancy.benefits && vacancy.benefits.length > 0 && (
            <div className="flex flex-wrap gap-2">
              {vacancy.benefits.map((b: string, i: number) => (
                <Chip key={i} size="sm" variant="flat" color="success">{b}</Chip>
              ))}
            </div>
          )}
        </GlassCard>
      )}

      {/* Skills */}
      {vacancy.skills.length > 0 && (
        <GlassCard className="p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-semibold text-theme-primary">{t('detail.skills_required')}</h2>
            {isAuthenticated && !isOwner && (
              <Button
                size="sm"
                variant="flat"
                className="bg-theme-elevated text-theme-muted"
                startContent={<Target className="w-4 h-4" aria-hidden="true" />}
                onPress={onCheckQualification}
              >
                {t('detail.check_qualification')}
              </Button>
            )}
          </div>
          <div className="flex flex-wrap gap-2">
            {(vacancy.skills ?? []).map((skill, idx) => {
              const isMatched = matchResult?.matched?.includes(skill.toLowerCase());
              const isMissing = matchResult?.missing?.includes(skill.toLowerCase());
              return (
                <Chip
                  key={idx}
                  variant="flat"
                  color={isMatched ? 'success' : isMissing ? 'danger' : 'primary'}
                  className={isMatched ? 'bg-success/10 text-success' : isMissing ? 'bg-danger/10 text-danger' : 'bg-primary/10 text-primary'}
                  startContent={isMatched ? <Check className="w-3 h-3" /> : isMissing ? <X className="w-3 h-3" /> : undefined}
                >
                  {skill}
                </Chip>
              );
            })}
          </div>
        </GlassCard>
      )}
    </>
  );
}
