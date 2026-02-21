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
  Chip,
  Divider,
} from '@heroui/react';
import {
  Newspaper,
  BarChart3,
  ShoppingBag,
  Calendar,
  Target,
} from 'lucide-react';
import { useTenant } from '@/contexts';
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
  const { hasFeature, hasModule } = useTenant();
  const [activeTab, setActiveTab] = useState<ComposeTab>(defaultTab);
  const [sharedGroupId, setSharedGroupId] = useState<number | null>(groupId ?? null);

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

  const activeConfig = ALL_TABS.find((t) => t.key === activeTab);
  const ActiveIcon = activeConfig?.icon ?? Newspaper;

  const tabProps = {
    onSuccess: handleSuccess,
    onClose: handleClose,
    groupId: sharedGroupId,
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      size="2xl"
      scrollBehavior="inside"
      classNames={{
        base: 'bg-[var(--glass-bg)] backdrop-blur-xl border border-[var(--glass-border)] max-h-[90vh] sm:max-h-[85vh]',
        wrapper: 'items-end sm:items-center',
        backdrop: 'bg-black/60 backdrop-blur-sm',
        body: 'px-4 sm:px-6',
        header: 'px-4 sm:px-6',
      }}
    >
      <ModalContent>
        <ModalHeader className="text-[var(--text-primary)] pb-2">
          <div className="flex flex-col gap-3 w-full">
            {/* Title row */}
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                <ActiveIcon className="w-4 h-4 text-white" aria-hidden="true" />
              </div>
              <span className="font-semibold">
                Create {activeConfig?.label || 'Post'}
              </span>
            </div>

            {/* Tab pills — horizontal scroll on mobile, wrap on desktop */}
            <div className="flex gap-1.5 overflow-x-auto sm:flex-wrap sm:overflow-visible pb-1 sm:pb-0 scrollbar-hide -mx-1 px-1">
              {tabs.map((tab) => {
                const Icon = tab.icon;
                const isActive = activeTab === tab.key;
                return (
                  <Chip
                    key={tab.key}
                    size="sm"
                    variant={isActive ? 'solid' : 'flat'}
                    className={`cursor-pointer transition-all flex-shrink-0 ${
                      isActive
                        ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white'
                        : 'bg-[var(--surface-elevated)] text-[var(--text-muted)] hover:bg-[var(--surface-hover)]'
                    }`}
                    onClick={() => setActiveTab(tab.key)}
                    startContent={<Icon className="w-3 h-3" aria-hidden="true" />}
                  >
                    {tab.label}
                  </Chip>
                );
              })}
            </div>
          </div>
        </ModalHeader>

        <Divider className="bg-[var(--border-default)]" />

        <ModalBody className="pt-4 pb-4">
          {/* Group/audience selector for supported tabs */}
          {TABS_WITH_GROUPS.includes(activeTab) && (
            <div className="mb-4">
              <GroupSelector
                value={sharedGroupId}
                onChange={setSharedGroupId}
              />
            </div>
          )}

          {/* Active tab content */}
          {activeTab === 'post' && <PostTab {...tabProps} />}
          {activeTab === 'poll' && <PollTab {...tabProps} />}
          {activeTab === 'listing' && <ListingTab {...tabProps} />}
          {activeTab === 'event' && <EventTab {...tabProps} />}
          {activeTab === 'goal' && <GoalTab {...tabProps} />}
        </ModalBody>
      </ModalContent>
    </Modal>
  );
}
