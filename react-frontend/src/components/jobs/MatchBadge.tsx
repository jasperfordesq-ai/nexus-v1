// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Chip } from '@heroui/react';
import Target from 'lucide-react/icons/target';
import { useTranslation } from 'react-i18next';
import type { MatchResult } from './JobDetailTypes';

interface MatchBadgeProps {
  match: MatchResult;
}

export function MatchBadge({ match }: MatchBadgeProps) {
  const { t } = useTranslation('jobs');

  if (match.required_skills.length === 0) return null;

  const color = match.percentage >= 80 ? 'success' : match.percentage >= 60 ? 'primary' : match.percentage >= 40 ? 'warning' : 'danger';
  const label = match.percentage >= 80 ? t('match.excellent') : match.percentage >= 60 ? t('match.good') : match.percentage >= 40 ? t('match.moderate') : t('match.low');

  return (
    <div className="flex items-center gap-3">
      <Chip
        variant="flat"
        color={color}
        startContent={<Target className="w-3.5 h-3.5" aria-hidden="true" />}
        className="text-sm"
      >
        {match.percentage}% {t('match.title')}
      </Chip>
      <span className="text-xs text-theme-subtle">{label}</span>
    </div>
  );
}
