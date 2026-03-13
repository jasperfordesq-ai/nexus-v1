// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * EndorseButton - Button to endorse a user's skill
 *
 * Displays next to each skill on member profiles. Shows endorsement
 * count and allows toggling endorsement.
 */

import { useState } from 'react';
import { Button, Tooltip } from '@heroui/react';
import { ThumbsUp } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

export interface EndorseButtonProps {
  memberId: number;
  skillName: string;
  endorsementCount: number;
  isEndorsed: boolean;
  onEndorsementChange?: () => void;
  compact?: boolean;
}

export function EndorseButton({
  memberId,
  skillName,
  endorsementCount,
  isEndorsed,
  onEndorsementChange,
  compact = false,
}: EndorseButtonProps) {
  const toast = useToast();
  const { t } = useTranslation('social');
  const [localCount, setLocalCount] = useState(endorsementCount);
  const [localIsEndorsed, setLocalIsEndorsed] = useState(isEndorsed);
  const [isLoading, setIsLoading] = useState(false);

  const handleToggle = async () => {
    try {
      setIsLoading(true);

      // Optimistic update
      setLocalIsEndorsed(!localIsEndorsed);
      setLocalCount((prev) => (localIsEndorsed ? prev - 1 : prev + 1));

      if (localIsEndorsed) {
        const response = await api.delete(`/v2/members/${memberId}/endorse`, {
          body: JSON.stringify({ skill_name: skillName }),
        });
        if (!response.success) {
          // Revert
          setLocalIsEndorsed(true);
          setLocalCount((prev) => prev + 1);
          toast.error(response.error || 'Failed to remove endorsement');
          return;
        }
      } else {
        const response = await api.post(`/v2/members/${memberId}/endorse`, {
          skill_name: skillName,
        });
        if (!response.success) {
          // Revert
          setLocalIsEndorsed(false);
          setLocalCount((prev) => prev - 1);
          toast.error(response.error || 'Failed to endorse');
          return;
        }
      }

      onEndorsementChange?.();
    } catch (err) {
      logError('Failed to toggle endorsement', err);
      // Revert on error
      setLocalIsEndorsed(isEndorsed);
      setLocalCount(endorsementCount);
      toast.error(t('endorsement_update_failed'));
    } finally {
      setIsLoading(false);
    }
  };

  if (compact) {
    return (
      <Tooltip
        content={localIsEndorsed ? t('endorse.tooltip_remove', { skillName }) : t('endorse.tooltip_endorse', { skillName })}
        delay={300}
        closeDelay={0}
        size="sm"
      >
        <Button
          size="sm"
          variant="flat"
          onPress={handleToggle}
          isDisabled={isLoading}
          className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs transition-all h-auto min-w-0 ${
            localIsEndorsed
              ? 'bg-indigo-500/20 text-indigo-600 dark:text-indigo-400'
              : 'bg-theme-elevated text-theme-subtle hover:bg-indigo-500/10 hover:text-indigo-500'
          }`}
          aria-label={localIsEndorsed ? t('endorse.aria_remove', { skillName }) : t('endorse.aria_endorse', { skillName })}
        >
          <ThumbsUp className={`w-3 h-3 ${localIsEndorsed ? 'fill-current' : ''}`} aria-hidden="true" />
          {localCount > 0 && <span>{localCount}</span>}
        </Button>
      </Tooltip>
    );
  }

  return (
    <Button
      size="sm"
      variant={localIsEndorsed ? 'flat' : 'bordered'}
      className={
        localIsEndorsed
          ? 'bg-indigo-500/20 text-indigo-600 dark:text-indigo-400'
          : 'border-theme-default text-theme-muted hover:border-indigo-500/30 hover:text-indigo-500'
      }
      startContent={
        <ThumbsUp
          className={`w-3.5 h-3.5 ${localIsEndorsed ? 'fill-current' : ''}`}
          aria-hidden="true"
        />
      }
      onPress={handleToggle}
      isLoading={isLoading}
    >
      {localIsEndorsed ? t('endorse.endorsed') : t('endorse.endorse')} {localCount > 0 && `(${localCount})`}
    </Button>
  );
}

export default EndorseButton;
