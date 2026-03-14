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
 * Mobile: Full-screen portal overlay with slide-up animation.
 * Desktop: HeroUI Modal with underlined tabs and glass background.
 */

import { useState, useMemo, useCallback, useEffect } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  Tabs,
  Tab,
  Divider,
} from '@heroui/react';
import {
  BarChart3,
  ShoppingBag,
  Calendar,
  Target,
  FileText,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { ComposeSubmitProvider } from './ComposeSubmitContext';
import { MobileComposeOverlay } from './MobileComposeOverlay';
import { PostTab } from './tabs/PostTab';
import { PollTab } from './tabs/PollTab';
import { ListingTab } from './tabs/ListingTab';
import { EventTab } from './tabs/EventTab';
import { GoalTab } from './tabs/GoalTab';
import { GroupSelector } from './shared/GroupSelector';
import { TemplatePicker } from './shared/TemplatePicker';
import type { ComposeHubProps, ComposeTab, ComposeTabConfig } from './types';

const ALL_TABS: ComposeTabConfig[] = [
  { key: 'listing', label: 'Listing', icon: ShoppingBag, gate: { type: 'module', key: 'listings' } },
  { key: 'post', label: 'Post', icon: FileText },
  { key: 'event', label: 'Event', icon: Calendar, gate: { type: 'feature', key: 'events' } },
  { key: 'goal', label: 'Goal', icon: Target, gate: { type: 'feature', key: 'goals' } },
  { key: 'poll', label: 'Poll', icon: BarChart3, gate: { type: 'feature', key: 'polls' } },
];

const TABS_WITH_GROUPS: ComposeTab[] = ['post', 'poll', 'event'];

export function ComposeHub({
  isOpen,
  onClose,
  defaultTab = 'listing',
  onSuccess,
  groupId,
}: ComposeHubProps) {
  const { t } = useTranslation('feed');
  const { hasFeature, hasModule } = useTenant();
  const [activeTab, setActiveTab] = useState<ComposeTab>(defaultTab);
  const [sharedGroupId, setSharedGroupId] = useState<number | null>(groupId ?? null);
  const [templateData, setTemplateData] = useState<{ title?: string; content: string } | null>(null);
  // Sync activeTab when defaultTab prop changes (e.g. user clicks different quick-action)
  useEffect(() => {
    setActiveTab(defaultTab);
  }, [defaultTab]);

  const isMobile = useMediaQuery('(max-width: 639px)');

  const handleTemplateSelect = useCallback((data: { title?: string; content: string }) => {
    setTemplateData(data);
    // Clear after a tick so tabs can consume it
    setTimeout(() => setTemplateData(null), 0);
  }, []);

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
  const ActiveIcon = activeConfig?.icon ?? ShoppingBag;

  const tabProps = {
    onSuccess: handleSuccess,
    onClose: handleClose,
    groupId: sharedGroupId,
    templateData,
  };

  /** Body content — group selector + active tab */
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

      {activeTab === 'listing' && <ListingTab {...tabProps} />}
      {activeTab === 'post' && <PostTab {...tabProps} />}
      {activeTab === 'poll' && <PollTab {...tabProps} />}
      {activeTab === 'event' && <EventTab {...tabProps} />}
      {activeTab === 'goal' && <GoalTab {...tabProps} />}
    </>
  );

  /* ── Mobile: Full-screen portal overlay ── */
  if (isMobile) {
    return (
      <ComposeSubmitProvider>
        <MobileComposeOverlay
          isOpen={isOpen}
          onClose={handleClose}
          activeTab={activeTab}
          onTabChange={setActiveTab}
          tabs={tabs}
          headerTitle={t('compose.create_title', { type: t(`compose.tab_${activeTab}`) })}
          templatePicker={<TemplatePicker tab={activeTab} onSelect={handleTemplateSelect} />}
        >
          {bodyContent}
        </MobileComposeOverlay>
      </ComposeSubmitProvider>
    );
  }

  /* ── Desktop: HeroUI Modal with underlined tabs ── */
  return (
    <ComposeSubmitProvider>
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
            <div className="flex flex-col gap-3 w-full">
              {/* Title row */}
              <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center flex-shrink-0">
                  <ActiveIcon className="w-4 h-4 text-white" aria-hidden="true" />
                </div>
                <span className="font-semibold flex-1">
                  {t('compose.create_title', { type: t(`compose.tab_${activeTab}`) })}
                </span>
                <TemplatePicker tab={activeTab} onSelect={handleTemplateSelect} />
              </div>

              {/* Underlined tabs */}
              <Tabs
                selectedKey={activeTab}
                onSelectionChange={(key) => setActiveTab(key as ComposeTab)}
                variant="underlined"
                size="sm"
                classNames={{
                  tabList: 'gap-2 p-0 border-b border-[var(--border-default)]',
                  tab: 'min-h-[44px] px-3 text-[var(--text-muted)] data-[selected=true]:text-[var(--text-primary)]',
                  cursor: 'bg-gradient-to-r from-indigo-500 to-purple-600 h-[2px] rounded-full',
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
          </ModalHeader>

          <Divider className="bg-[var(--border-default)]" />

          <ModalBody className="pt-4 pb-4">
            {bodyContent}
          </ModalBody>
        </ModalContent>
      </Modal>
    </ComposeSubmitProvider>
  );
}
