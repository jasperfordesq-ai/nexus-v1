// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { Key, ReactNode } from 'react';
import { Dropdown } from '@heroui/react/dropdown';
import { Label } from '@heroui/react/label';
import { Tabs } from '@heroui/react/tabs';
import { useTranslation } from 'react-i18next';
import AlertCircle from 'lucide-react/icons/circle-alert';
import Calendar from 'lucide-react/icons/calendar';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import ChevronDown from 'lucide-react/icons/chevron-down';
import FileText from 'lucide-react/icons/file-text';
import Flag from 'lucide-react/icons/flag';
import FolderOpen from 'lucide-react/icons/folder-open';
import FolderTree from 'lucide-react/icons/folder-tree';
import Image from 'lucide-react/icons/image';
import Megaphone from 'lucide-react/icons/megaphone';
import MessageSquare from 'lucide-react/icons/message-square';
import Newspaper from 'lucide-react/icons/newspaper';
import Users from 'lucide-react/icons/users';
import Wrench from 'lucide-react/icons/wrench';
import { useTenant } from '@/contexts';
import { getAvailableGroupSections, type GroupSectionKey } from '../groupSections';

interface GroupTabNavProps {
  activeTab: GroupSectionKey;
  children: ReactNode;
  userIsAdmin: boolean;
  userIsMember: boolean;
  hasSubGroups: boolean;
  subGroupCount: number;
  onTabChange: (tab: GroupSectionKey) => void;
}

export function GroupTabNav({
  activeTab,
  children,
  userIsAdmin,
  userIsMember,
  hasSubGroups,
  subGroupCount,
  onTabChange,
}: GroupTabNavProps) {
  const { t } = useTranslation('groups');
  const { hasGroupTab, hasFeature } = useTenant();
  const keys = getAvailableGroupSections({
    hasGroupTab,
    hasSubgroups: hasSubGroups,
    userIsAdmin,
    userIsMember,
    hasEventsFeature: hasFeature('events'),
  });
  const labels: Record<GroupSectionKey, string> = {
    feed: t('detail.tab_feed'),
    subgroups: t('detail.tab_subgroups_count', { count: subGroupCount }),
    discussion: t('detail.tab_discussion'),
    members: t('detail.tab_members'),
    events: t('detail.tab_events'),
    files: t('detail.tab_files'),
    announcements: t('detail.tab_announcements'),
    qa: t('detail.tab_qa'),
    wiki: t('detail.tab_wiki'),
    media: t('detail.tab_media'),
    chatrooms: t('detail.tab_channels'),
    tasks: t('detail.tab_tasks'),
    challenges: t('detail.tab_challenges'),
    analytics: t('detail.tab_analytics'),
    automation: t('detail.tab_automation'),
  };
  const icons = {
    feed: Newspaper,
    subgroups: FolderTree,
    discussion: MessageSquare,
    members: Users,
    events: Calendar,
    files: FolderOpen,
    announcements: Megaphone,
    qa: AlertCircle,
    wiki: FileText,
    media: Image,
    chatrooms: MessageSquare,
    tasks: CheckCircle,
    challenges: Flag,
    analytics: Newspaper,
    automation: Wrench,
  } satisfies Record<GroupSectionKey, typeof Newspaper>;
  const sections = keys.map((key) => ({ key, label: labels[key], icon: icons[key] }));
  const activeSection = sections.find((section) => section.key === activeTab) ?? sections[0];

  if (!activeSection) return <>{children}</>;
  const ActiveIcon = activeSection.icon;

  const selectSection = (key: Key) => {
    const next = String(key) as GroupSectionKey;
    if (keys.includes(next)) onTabChange(next);
  };

  return (
    <>
      <div className="sticky top-[var(--app-header-mobile-offset,5rem)] z-20 -mx-1 rounded-xl border border-theme-default bg-surface/95 p-1 shadow-sm backdrop-blur sm:hidden">
        <Dropdown>
          <Dropdown.Trigger
            className="flex h-11 w-full min-w-0 items-center justify-between gap-2 rounded-lg px-3 text-theme-primary"
            aria-label={`${t('detail.tab_nav_aria')}: ${activeSection.label}`}
          >
            <span className="flex min-w-0 items-center gap-2">
              <ActiveIcon className="h-4 w-4 flex-shrink-0" aria-hidden="true" />
              <span className="truncate font-medium">{activeSection.label}</span>
            </span>
            <ChevronDown className="h-4 w-4 flex-shrink-0" aria-hidden="true" />
          </Dropdown.Trigger>
          <Dropdown.Popover>
            <Dropdown.Menu
              aria-label={t('detail.tab_nav_aria')}
              selectionMode="single"
              selectedKeys={new Set([activeSection.key])}
              onAction={selectSection}
            >
              {sections.map((section) => {
                const Icon = section.icon;
                return (
                  <Dropdown.Item key={section.key} id={section.key} textValue={section.label}>
                    <Dropdown.ItemIndicator />
                    <Icon className="h-4 w-4" aria-hidden="true" />
                    <Label>{section.label}</Label>
                  </Dropdown.Item>
                );
              })}
            </Dropdown.Menu>
          </Dropdown.Popover>
        </Dropdown>
      </div>

      <Tabs
        className="w-full min-w-0"
        selectedKey={activeSection.key}
        onSelectionChange={selectSection}
      >
        <div className="sticky top-[calc(var(--app-header-desktop-offset,5.5rem)+0.75rem)] z-20 hidden rounded-xl border border-theme-default bg-surface/95 p-1 shadow-sm backdrop-blur sm:block">
          <Tabs.ListContainer className="max-w-full overflow-x-auto">
            <Tabs.List aria-label={t('detail.tab_nav_aria')} className="min-w-max gap-1">
              {sections.map((section) => {
                const Icon = section.icon;
                return (
                  <Tabs.Tab
                    key={section.key}
                    id={section.key}
                    className="h-10 min-w-fit shrink-0 gap-1.5 whitespace-nowrap rounded-lg px-3 text-sm font-medium data-[selected=true]:bg-theme-hover data-[selected=true]:text-theme-primary data-[selected=true]:shadow-sm"
                  >
                    <Icon className="h-4 w-4 flex-shrink-0" aria-hidden="true" />
                    <span>{section.label}</span>
                  </Tabs.Tab>
                );
              })}
            </Tabs.List>
          </Tabs.ListContainer>
        </div>

        {sections.map((section) => (
          <Tabs.Panel key={section.key} id={section.key} className="pt-4 outline-none sm:pt-5">
            {section.key === activeSection.key ? children : null}
          </Tabs.Panel>
        ))}
      </Tabs>
    </>
  );
}
