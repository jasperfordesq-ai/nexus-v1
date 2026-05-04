// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useTranslation } from 'react-i18next';
import {
  Button,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
} from '@heroui/react';
import Users from 'lucide-react/icons/users';
import MessageSquare from 'lucide-react/icons/message-square';
import Calendar from 'lucide-react/icons/calendar';
import FolderTree from 'lucide-react/icons/folder-tree';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import FileText from 'lucide-react/icons/file-text';
import Image from 'lucide-react/icons/image';
import Newspaper from 'lucide-react/icons/newspaper';
import Flag from 'lucide-react/icons/flag';
import FolderOpen from 'lucide-react/icons/folder-open';
import Megaphone from 'lucide-react/icons/megaphone';
import ChevronDown from 'lucide-react/icons/chevron-down';
import AlertCircle from 'lucide-react/icons/circle-alert';
import { useTenant } from '@/contexts';

interface GroupTabNavProps {
  activeTab: string;
  userIsAdmin: boolean;
  hasSubGroups: boolean;
  subGroupCount: number;
  onTabChange: (tab: string) => void;
}

export function GroupTabNav({
  activeTab,
  userIsAdmin,
  hasSubGroups,
  subGroupCount,
  onTabChange,
}: GroupTabNavProps) {
  const { t } = useTranslation('groups');
  const { hasGroupTab } = useTenant();

  const primaryTabs = [
    { key: 'feed', icon: Newspaper, label: t('detail.tab_feed', 'Feed') },
    // Subgroups shown as a primary tab (not buried in More) so users can drill through hierarchy
    ...(hasSubGroups ? [{ key: 'subgroups', icon: FolderTree, label: `${t('detail.tab_subgroups', 'Subgroups')} (${subGroupCount})` }] : []),
    { key: 'discussion', icon: MessageSquare, label: t('detail.tab_discussion', 'Discussion') },
    { key: 'members', icon: Users, label: t('detail.tab_members', 'Members') },
    { key: 'events', icon: Calendar, label: t('detail.tab_events', 'Events') },
    { key: 'files', icon: FolderOpen, label: t('detail.tab_files', 'Files') },
  ].filter(tab => tab.key === 'subgroups' || hasGroupTab(`tab_${tab.key}` as keyof import('@/types').GroupTabConfig));

  const secondaryTabs = [
    // Content
    { key: 'announcements', icon: Megaphone, label: t('detail.tab_announcements', 'Announcements'), section: t('detail.tab_section_content', 'Content') },
    { key: 'qa', icon: AlertCircle, label: t('detail.tab_qa', 'Q&A'), section: null },
    { key: 'wiki', icon: FileText, label: t('detail.tab_wiki', 'Wiki'), section: null },
    { key: 'media', icon: Image, label: t('detail.tab_media', 'Gallery'), section: null },
    // Collaboration
    { key: 'chatrooms', icon: MessageSquare, label: t('detail.tab_channels', 'Channels'), section: t('detail.tab_section_collab', 'Collaboration') },
    { key: 'tasks', icon: CheckCircle, label: t('detail.tab_tasks', 'Tasks'), section: null },
    { key: 'challenges', icon: Flag, label: t('detail.tab_challenges', 'Challenges'), section: null },
    // Admin (conditional)
    ...(userIsAdmin ? [{ key: 'analytics', icon: Newspaper, label: t('detail.tab_analytics', 'Analytics'), section: t('detail.tab_section_admin', 'Admin') }] : []),
  ].filter(tab => hasGroupTab(`tab_${tab.key}` as keyof import('@/types').GroupTabConfig));

  const isSecondaryActive = secondaryTabs.some((tab) => tab.key === activeTab);
  const activeSecondaryTab = secondaryTabs.find((tab) => tab.key === activeTab);

  return (
    <div className="flex items-center gap-1 bg-theme-elevated p-1 rounded-lg overflow-x-auto scrollbar-hide" role="tablist" aria-label={t('detail.tab_nav_aria', 'Group navigation')}>
      {/* Primary tabs */}
      {primaryTabs.map((tab) => {
        const Icon = tab.icon;
        const isActive = activeTab === tab.key;
        return (
          <Button
            key={tab.key}
            variant="light"
            role="tab"
            aria-selected={isActive}
            aria-label={tab.label}
            onPress={() => onTabChange(tab.key)}
            className={`flex items-center gap-1.5 px-2 sm:px-3 py-2 rounded-md text-sm font-medium transition-all whitespace-nowrap h-auto min-w-0 ${
              isActive
                ? 'bg-theme-hover text-theme-primary shadow-sm'
                : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover/50'
            }`}
          >
            <Icon className="w-4 h-4 flex-shrink-0" aria-hidden="true" />
            <span className="hidden md:inline">{tab.label}</span>
          </Button>
        );
      })}

      {/* Divider */}
      <div className="w-px h-6 bg-theme-default mx-1 flex-shrink-0" aria-hidden="true" />

      {/* "More" dropdown for secondary tabs */}
      <Dropdown>
        <DropdownTrigger>
          <Button
            variant="light"
            className={`flex items-center gap-1.5 px-2 sm:px-3 py-2 rounded-md text-sm font-medium transition-all whitespace-nowrap h-auto min-w-0 ${
              isSecondaryActive
                ? 'bg-theme-hover text-theme-primary shadow-sm'
                : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover/50'
            }`}
            aria-label={t('detail.tab_more', 'More sections')}
          >
            {isSecondaryActive && activeSecondaryTab ? (
              <>
                {(() => { const Icon = activeSecondaryTab.icon; return <Icon className="w-4 h-4 flex-shrink-0" aria-hidden="true" />; })()}
                <span className="hidden md:inline">{activeSecondaryTab.label}</span>
              </>
            ) : (
              <>
                <span className="hidden md:inline">{t('detail.tab_more_label', 'More')}</span>
                <span className="md:hidden text-xs">+</span>
              </>
            )}
            <ChevronDown className="w-3 h-3 flex-shrink-0" aria-hidden="true" />
          </Button>
        </DropdownTrigger>
        <DropdownMenu
          aria-label={t('detail.tab_more_menu', 'More group sections')}
          onAction={(key) => onTabChange(key as string)}
          selectedKeys={new Set([activeTab])}
          selectionMode="single"
        >
          {secondaryTabs.map((tab, idx) => {
            const Icon = tab.icon;
            const showSection = tab.section && (idx === 0 || secondaryTabs[idx - 1]?.section !== tab.section);
            return (
              <DropdownItem
                key={tab.key}
                startContent={<Icon className="w-4 h-4" />}
                className={activeTab === tab.key ? 'bg-primary/10 text-primary' : ''}
              >
                {showSection ? `${tab.section} — ${tab.label}` : tab.label}
              </DropdownItem>
            );
          })}
        </DropdownMenu>
      </Dropdown>
    </div>
  );
}
