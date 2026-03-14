// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * FeedModeToggle — toggle between "For You" (EdgeRank) and "Recent" (chronological) feed modes.
 */

import { Tabs, Tab } from '@heroui/react';
import { Sparkles, Clock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { Key } from 'react';

interface FeedModeToggleProps {
  mode: 'ranking' | 'recent';
  onModeChange: (mode: 'ranking' | 'recent') => void;
}

export function FeedModeToggle({ mode, onModeChange }: FeedModeToggleProps) {
  const { t } = useTranslation('feed');

  const handleSelectionChange = (key: Key) => {
    onModeChange(key as 'ranking' | 'recent');
  };

  return (
    <Tabs
      selectedKey={mode}
      onSelectionChange={handleSelectionChange}
      variant="underlined"
      size="sm"
      classNames={{
        tabList: 'gap-4 p-0',
        cursor: 'bg-gradient-to-r from-indigo-500 to-purple-500',
        tab: 'px-0 h-8',
        tabContent: 'group-data-[selected=true]:text-[var(--text-primary)] text-[var(--text-muted)]',
      }}
      aria-label={t('mode.label', 'Feed mode')}
    >
      <Tab
        key="ranking"
        title={
          <div className="flex items-center gap-1.5">
            <Sparkles className="w-3.5 h-3.5" />
            <span>{t('mode.for_you', 'For You')}</span>
          </div>
        }
      />
      <Tab
        key="recent"
        title={
          <div className="flex items-center gap-1.5">
            <Clock className="w-3.5 h-3.5" />
            <span>{t('mode.recent', 'Recent')}</span>
          </div>
        }
      />
    </Tabs>
  );
}

export default FeedModeToggle;
