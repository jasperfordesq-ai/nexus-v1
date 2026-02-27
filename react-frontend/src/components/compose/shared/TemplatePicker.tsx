// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * TemplatePicker — dropdown that pre-fills compose form fields with
 * template content. Templates are hardcoded per tab and use i18n for labels.
 */

import {
  Button,
  Dropdown,
  DropdownItem,
  DropdownMenu,
  DropdownTrigger,
} from '@heroui/react';
import { FileText } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import type { ComposeTab } from '../types';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface Template {
  key: string;
  labelKey: string;
  title?: string;
  content: string;
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
      content: '\uD83C\uDF89 I just completed...',
    },
    {
      key: 'help',
      labelKey: 'compose.template_post_help',
      content: '\uD83D\uDC4B Hi everyone! I\'m looking for help with...',
    },
    {
      key: 'recommend',
      labelKey: 'compose.template_post_recommend',
      content: '\u2B50 I want to recommend @... for their amazing...',
    },
  ],
  listing: [
    {
      key: 'offer',
      labelKey: 'compose.template_listing_offer',
      title: 'I can help with...',
      content:
        'I have experience in... and would love to help someone who needs...',
    },
    {
      key: 'request',
      labelKey: 'compose.template_listing_request',
      title: 'Looking for help with...',
      content:
        'I need someone who can help me with... Estimated time: about X hours.',
    },
  ],
  event: [
    {
      key: 'gathering',
      labelKey: 'compose.template_event_gathering',
      title: 'Community Gathering',
      content: 'Join us for a friendly community get-together! All welcome.',
    },
    {
      key: 'workshop',
      labelKey: 'compose.template_event_workshop',
      title: 'Workshop: ',
      content:
        'Learn about... in this hands-on workshop.\n\nWhat to bring:\n- \n\nSuitable for: beginners/all levels',
    },
    {
      key: 'social',
      labelKey: 'compose.template_event_social',
      title: 'Social Event: ',
      content:
        'Come along for a relaxed social event. A great chance to meet new people and have fun!',
    },
  ],
  goal: [
    {
      key: 'hours',
      labelKey: 'compose.template_goal_hours',
      title: 'Give X hours this month',
      content:
        'My goal is to contribute X hours of my time to the community this month.',
    },
    {
      key: 'skills',
      labelKey: 'compose.template_goal_skills',
      title: 'Learn a new skill',
      content:
        'I want to learn... by connecting with community members who have this expertise.',
    },
  ],
  poll: [
    {
      key: 'opinion',
      labelKey: 'compose.template_poll_opinion',
      content: 'What do you think about...?',
    },
    {
      key: 'preference',
      labelKey: 'compose.template_poll_preference',
      content: 'Which do you prefer?',
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
        title: template.title,
        content: template.content,
      });
    }
  };

  return (
    <Dropdown>
      <DropdownTrigger>
        <Button
          size="sm"
          variant="flat"
          className="bg-[var(--surface-elevated)] text-[var(--text-muted)] border border-[var(--border-default)] hover:border-[var(--color-primary)]/40 hover:text-[var(--color-primary)] transition-colors"
          startContent={
            <FileText className="w-3.5 h-3.5" aria-hidden="true" />
          }
        >
          {t('compose.template_button', 'Template')}
        </Button>
      </DropdownTrigger>
      <DropdownMenu
        aria-label={t('compose.template_button', 'Template')}
        onAction={handleAction}
      >
        {templates.map((tpl) => (
          <DropdownItem key={tpl.key}>
            {t(tpl.labelKey, tpl.key)}
          </DropdownItem>
        ))}
      </DropdownMenu>
    </Dropdown>
  );
}
