// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * FeedModeToggle — toggle between "For You" (EdgeRank) and "Recent" (chronological) feed modes.
 */


import Sparkles from 'lucide-react/icons/sparkles';
import { ToggleButton, ToggleButtonGroup } from '@heroui/react';
import Clock from 'lucide-react/icons/clock';
import { useTranslation } from 'react-i18next';
import type { Key } from '@heroui/react';

interface FeedModeToggleProps {
  mode: 'ranking' | 'recent';
  onModeChange: (mode: 'ranking' | 'recent') => void;
}

export function FeedModeToggle({ mode, onModeChange }: FeedModeToggleProps) {
  const { t } = useTranslation('feed');
  const selectedKeys = new Set<Key>([mode]);

  const handleSelectionChange = (keys: Set<Key>) => {
    const [key] = Array.from(keys);
    if (key) {
      onModeChange(key as 'ranking' | 'recent');
    }
  };

  return (
    <ToggleButtonGroup
      aria-label={t('mode.label')}
      className="gap-4 p-0"
      selectedKeys={selectedKeys}
      onSelectionChange={handleSelectionChange}
      selectionMode="single"
      disallowEmptySelection
      isDetached
      size="sm"
    >
      <ToggleButton
        id="ranking"
        variant="ghost"
        className="h-8 px-0 text-[var(--text-muted)] data-[selected=true]:text-[var(--text-primary)]"
      >
        <Sparkles className="w-3.5 h-3.5" aria-hidden="true" />
        <span>{t('mode.for_you')}</span>
      </ToggleButton>
      <ToggleButton
        id="recent"
        variant="ghost"
        className="h-8 px-0 text-[var(--text-muted)] data-[selected=true]:text-[var(--text-primary)]"
      >
        <Clock className="w-3.5 h-3.5" aria-hidden="true" />
        <span>{t('mode.recent')}</span>
      </ToggleButton>
    </ToggleButtonGroup>
  );
}

export default FeedModeToggle;
