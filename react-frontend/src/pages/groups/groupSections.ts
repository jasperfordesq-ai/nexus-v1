// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { GroupTabConfig } from '@/types/api';

export type GroupSectionKey =
  | 'feed'
  | 'subgroups'
  | 'discussion'
  | 'members'
  | 'events'
  | 'files'
  | 'announcements'
  | 'qa'
  | 'wiki'
  | 'media'
  | 'chatrooms'
  | 'tasks'
  | 'challenges'
  | 'analytics'
  | 'automation';

interface GroupSectionRule {
  key: GroupSectionKey;
  configKey?: keyof GroupTabConfig;
  requiresAdmin?: boolean;
  requiresMembership?: boolean;
  requiresSubgroups?: boolean;
  requiresEventsFeature?: boolean;
}

const GROUP_SECTION_RULES: readonly GroupSectionRule[] = [
  { key: 'feed', configKey: 'tab_feed' },
  { key: 'subgroups', configKey: 'tab_subgroups', requiresSubgroups: true },
  { key: 'discussion', configKey: 'tab_discussion' },
  { key: 'members', configKey: 'tab_members', requiresMembership: true },
  { key: 'events', configKey: 'tab_events', requiresMembership: true, requiresEventsFeature: true },
  { key: 'files', configKey: 'tab_files', requiresMembership: true },
  { key: 'announcements', configKey: 'tab_announcements', requiresMembership: true },
  { key: 'qa', configKey: 'tab_qa', requiresMembership: true },
  { key: 'wiki', configKey: 'tab_wiki', requiresMembership: true },
  { key: 'media', configKey: 'tab_media', requiresMembership: true },
  { key: 'chatrooms', configKey: 'tab_chatrooms', requiresMembership: true },
  { key: 'tasks', configKey: 'tab_tasks', requiresMembership: true },
  { key: 'challenges', configKey: 'tab_challenges', requiresMembership: true },
  { key: 'analytics', configKey: 'tab_analytics', requiresAdmin: true },
  { key: 'automation', requiresAdmin: true },
];

export interface AvailableGroupSectionsInput {
  hasGroupTab: (key: keyof GroupTabConfig) => boolean;
  hasSubgroups: boolean;
  userIsAdmin: boolean;
  userIsMember: boolean;
  hasEventsFeature?: boolean;
}

export function getAvailableGroupSections({
  hasGroupTab,
  hasSubgroups,
  userIsAdmin,
  userIsMember,
  hasEventsFeature = true,
}: AvailableGroupSectionsInput): GroupSectionKey[] {
  return GROUP_SECTION_RULES
    .filter((section) => section.configKey === undefined || hasGroupTab(section.configKey))
    .filter((section) => !section.requiresSubgroups || hasSubgroups)
    .filter((section) => !section.requiresAdmin || userIsAdmin)
    .filter((section) => !section.requiresMembership || userIsMember || userIsAdmin)
    .filter((section) => !section.requiresEventsFeature || hasEventsFeature)
    .map((section) => section.key);
}

export function isGroupSectionKey(value: string | null): value is GroupSectionKey {
  return GROUP_SECTION_RULES.some((section) => section.key === value);
}
