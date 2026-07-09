// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import FileText from 'lucide-react/icons/file-text';
import { useTranslation } from 'react-i18next';

import type { ComposeTab } from '../types';

import { Dropdown, DropdownTrigger, DropdownMenu, DropdownItem, Button } from '@/components/ui';
// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface Template {
  key: string;
  labelKey: string;
  /** Translation key for the pre-filled title (optional). */
  titleKey?: string;
  /** Translation key for the pre-filled body content. */
  contentKey: string;
}

interface TemplatePickerProps {
  tab: ComposeTab;
  onSelect: (template: { title?: string; content: string }) => void;
}

// ─────────────────────────────────────────────────────────────────────────────
// Template definitions
// ─────────────────────────────────────────────────────────────────────────────

const TEMPLATES: Record<string, Template[]> = {
  post: [
    {
      key: 'achievement',
      labelKey: 'compose.template_post_achievement',
      contentKey: 'compose.template_post_achievement_content',
    },
    {
      key: 'help',
      labelKey: 'compose.template_post_help',
      contentKey: 'compose.template_post_help_content',
    },
    {
      key: 'recommend',
      labelKey: 'compose.template_post_recommend',
      contentKey: 'compose.template_post_recommend_content',
    },
  ],
  listing: [
    {
      key: 'offer',
      labelKey: 'compose.template_listing_offer',
      titleKey: 'compose.template_listing_offer_title',
      contentKey: 'compose.template_listing_offer_content',
    },
    {
      key: 'request',
      labelKey: 'compose.template_listing_request',
      titleKey: 'compose.template_listing_request_title',
      contentKey: 'compose.template_listing_request_content',
    },
  ],
  event: [
    {
      key: 'gathering',
      labelKey: 'compose.template_event_gathering',
      titleKey: 'compose.template_event_gathering_title',
      contentKey: 'compose.template_event_gathering_content',
    },
    {
      key: 'workshop',
      labelKey: 'compose.template_event_workshop',
      titleKey: 'compose.template_event_workshop_title',
      contentKey: 'compose.template_event_workshop_content',
    },
    {
      key: 'social',
      labelKey: 'compose.template_event_social',
      titleKey: 'compose.template_event_social_title',
      contentKey: 'compose.template_event_social_content',
    },
  ],
  goal: [
    {
      key: 'hours',
      labelKey: 'compose.template_goal_hours',
      titleKey: 'compose.template_goal_hours_title',
      contentKey: 'compose.template_goal_hours_content',
    },
    {
      key: 'skills',
      labelKey: 'compose.template_goal_skills',
      titleKey: 'compose.template_goal_skills_title',
      contentKey: 'compose.template_goal_skills_content',
    },
  ],
  poll: [
    {
      key: 'opinion',
      labelKey: 'compose.template_poll_opinion',
      contentKey: 'compose.template_poll_opinion_content',
    },
    {
      key: 'preference',
      labelKey: 'compose.template_poll_preference',
      contentKey: 'compose.template_poll_preference_content',
    },
  ],
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function TemplatePicker({ tab, onSelect }: TemplatePickerProps) {
  const { t } = useTranslation('feed');

  const templates = TEMPLATES[tab];

  // If no templates for this tab, don't render
  if (!templates || templates.length === 0) {
    return null;
  }

  const handleAction = (key: React.Key) => {
    const template = templates.find((tpl) => tpl.key === String(key));
    if (template) {
      onSelect({
        title: template.titleKey ? t(template.titleKey) : undefined,
        content: t(template.contentKey),
      });
    }
  };

  return (
    <Dropdown>
      <DropdownTrigger>
        <Button
          size="sm"
          variant="secondary"
          className="bg-[var(--surface-elevated)] text-[var(--text-muted)] border border-[var(--border-default)] hover:border-[var(--color-primary)]/40 hover:text-[var(--color-primary)] transition-colors"
          startContent={
            <FileText className="w-3.5 h-3.5" aria-hidden="true" />
          }
        >
          {t('compose.template_button')}
        </Button>
      </DropdownTrigger>
      <DropdownMenu
        aria-label={t('compose.template_button')}
        onAction={handleAction}
      >
        {templates.map((tpl) => (
          <DropdownItem key={tpl.key} id={tpl.key}>
            {t(tpl.labelKey, tpl.key)}
          </DropdownItem>
        ))}
      </DropdownMenu>
    </Dropdown>
  );
}
