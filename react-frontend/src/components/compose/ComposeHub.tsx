// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ComposeHub — Universal content creation modal with tabbed interface.
 *
 * Supports creating posts, polls, listings, events, and goals from a single
 * modal. Each tab is feature/module-gated by the tenant configuration.
 *
 * Replaces the old inline create-post modal in FeedPage, and mirrors the
 * capabilities of the legacy PHP /compose page.
 */

import { useState, useMemo } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  Drawer,
  DrawerContent,
  DrawerHeader,
  DrawerBody,
  Tabs,
  Tab,
  Divider,
} from '@heroui/react';
import {
  Newspaper,
  BarChart3,
  ShoppingBag,
  Calendar,
  Target,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { PostTab } from './tabs/PostTab';
import { PollTab } from './tabs/PollTab';
import { ListingTab } from './tabs/ListingTab';
import { EventTab } from './tabs/EventTab';
import { GoalTab } from './tabs/GoalTab';
import { GroupSelector } from './shared/GroupSelector';
import type { ComposeHubProps, ComposeTab, ComposeTabConfig } from './types';

const ALL_TABS: ComposeTabConfig[] = [
  { key: 'post', label: 'Post', icon: Newspaper },
  { key: 'poll', label: 'Poll', icon: BarChart3, gate: { type: 'feature', key: 'polls' } },
  { key: 'listing', label: 'Listing', icon: ShoppingBag, gate: { type: 'module', key: 'listings' } },
  { key: 'event', label: 'Event', icon: Calendar, gate: { type: 'feature', key: 'events' } },
  { key: 'goal', label: 'Goal', icon: Target, gate: { type: 'feature', key: 'goals' } },
];

const TABS_WITH_GROUPS: ComposeTab[] = ['post', 'poll', 'event'];

export function ComposeHub({
  isOpen,
  onClose,
  defaultTab = 'post',
  onSuccess,
  groupId,
}: ComposeHubProps) {
  const { t } = useTranslation('feed');
  const { hasFeature, hasModule } = useTenant();
  const [activeTab, setActiveTab] = useState<ComposeTab>(defaultTab);
  const [sharedGroupId, setSharedGroupId] = useState<number | null>(groupId ?? null);
  const isMobile = useMediaQuery('(max-width: 639px)');

  const tabs = useMemo(() => {
    return ALL_TABS.filter((tab) => {
      if (!tab.gate) return true;
      if (tab.gate.type === 'feature') return hasFeature(tab.gate.key);
      return hasModule(tab.gate.key);
    });
  }, [hasFeature, hasModule]);

  const handleClose = () => {
    setActiveTab(defaultTab);
    setSharedGroupId(groupId ?? null);
    onClose();
  };

  const handleSuccess = (type: ComposeTab, id?: number) => {
    onSuccess?.(type, id);
  };

  const activeConfig = ALL_TABS.find((tc) => tc.key === activeTab);
  const ActiveIcon = activeConfig?.icon ?? Newspaper;

  const tabProps = {
    onSuccess: handleSuccess,
    onClose: handleClose,
    groupId: sharedGroupId,
  };

  /** Shared header — title + HeroUI Tabs with proper a11y */
  const headerContent = (
    <div className="flex flex-col gap-3 w-full">
      {/* Grab handle — mobile drawer indicator */}
      {isMobile && (
        <div className="flex justify-center pt-1">
          <div className="w-10 h-1 rounded-full bg-[var(--border-default)]" />
        </div>
      )}

      {/* Title row */}
      <div className="flex items-center gap-3">
        <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center flex-shrink-0">
          <ActiveIcon className="w-4 h-4 text-white" aria-hidden="true" />
        </div>
        <span className="font-semibold">
          {t('compose.create_title', { type: t(`compose.tab_${activeTab}`) })}
        </span>
      </div>

      {/* HeroUI Tabs — semantic role="tab", keyboard arrow-key nav, aria-selected */}
      <Tabs
        selectedKey={activeTab}
        onSelectionChange={(key) => setActiveTab(key as ComposeTab)}
        variant="light"
        size="sm"
        classNames={{
          tabList: 'gap-1 overflow-x-auto sm:flex-wrap scrollbar-hide p-0',
          tab: 'min-h-[44px] px-3 data-[selected=true]:bg-gradient-to-r data-[selected=true]:from-indigo-500 data-[selected=true]:to-purple-600 data-[selected=true]:text-white rounded-full',
          cursor: 'hidden',
        }}
      >
        {tabs.map((tab) => {
          const Icon = tab.icon;
          return (
            <Tab
              key={tab.key}
              title={
                <div className="flex items-center gap-1.5">
                  <Icon className="w-3.5 h-3.5" aria-hidden="true" />
                  <span>{t(`compose.tab_${tab.key}`)}</span>
                </div>
              }
            />
          );
        })}
      </Tabs>
    </div>
  );

  /** Shared body — group selector + active tab content */
  const bodyContent = (
    <>
      {TABS_WITH_GROUPS.includes(activeTab) && (
        <div className="mb-4">
          <GroupSelector
            value={sharedGroupId}
            onChange={setSharedGroupId}
          />
        </div>
      )}

      {activeTab === 'post' && <PostTab {...tabProps} />}
      {activeTab === 'poll' && <PollTab {...tabProps} />}
      {activeTab === 'listing' && <ListingTab {...tabProps} />}
      {activeTab === 'event' && <EventTab {...tabProps} />}
      {activeTab === 'goal' && <GoalTab {...tabProps} />}
    </>
  );

  /* Mobile: HeroUI Drawer as bottom-sheet */
  if (isMobile) {
    return (
      <Drawer
        isOpen={isOpen}
        onClose={handleClose}
        placement="bottom"
        size="full"
        classNames={{
          base: 'bg-[var(--glass-bg)] backdrop-blur-xl border-t border-[var(--glass-border)] max-h-[92vh] rounded-t-2xl',
          backdrop: 'bg-black/60 backdrop-blur-sm',
          body: 'px-4',
          header: 'px-4',
        }}
      >
        <DrawerContent>
          <DrawerHeader className="text-[var(--text-primary)] pb-2">
            {headerContent}
          </DrawerHeader>

          <Divider className="bg-[var(--border-default)]" />

          <DrawerBody className="pt-4 pb-4 overflow-y-auto">
            {bodyContent}
          </DrawerBody>
        </DrawerContent>
      </Drawer>
    );
  }

  /* Desktop: HeroUI Modal centered */
  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      size="2xl"
      scrollBehavior="inside"
      classNames={{
        base: 'bg-[var(--glass-bg)] backdrop-blur-xl border border-[var(--glass-border)] max-h-[85vh]',
        wrapper: 'items-center',
        backdrop: 'bg-black/60 backdrop-blur-sm',
        body: 'px-6',
        header: 'px-6',
      }}
    >
      <ModalContent>
        <ModalHeader className="text-[var(--text-primary)] pb-2">
          {headerContent}
        </ModalHeader>

        <Divider className="bg-[var(--border-default)]" />

        <ModalBody className="pt-4 pb-4">
          {bodyContent}
        </ModalBody>
      </ModalContent>
    </Modal>
  );
}
