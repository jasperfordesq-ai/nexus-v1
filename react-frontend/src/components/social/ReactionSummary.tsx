// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ReactionSummary — Shows grouped reaction counts below posts/comments.
 *
 * Displays reaction emojis with counts (e.g. "heart 5  laugh 3  clap 2")
 * plus a text summary ("John, Mary, and 8 others").
 * Clicking opens a HeroUI Modal with tabs per reaction type showing user avatars.
 */

import React, { useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  Avatar,
  Button,
  Spinner,
  Tabs,
  Tab,
} from '@heroui/react';
import Clock from 'lucide-react/icons/clock';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import { resolveAvatarUrl, formatRelativeTime } from '@/lib/helpers';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import {
  REACTION_EMOJI_MAP,
  REACTION_LABEL_MAP,
  type ReactionType,
} from './ReactionPicker';

/* ───────────────────────── Types ───────────────────────── */

export interface ReactorUser {
  id: number;
  name: string;
  avatar_url: string | null;
  reacted_at: string;
}

export interface ReactionSummaryProps {
  /** Reaction counts by type, e.g. { love: 5, laugh: 3 } */
  counts: Record<string, number>;
  /** Total reaction count */
  total: number;
  /** Names of a few top reactors for the summary text */
  topReactors?: Array<{ id: number; name: string; avatar_url?: string | null }>;
  /** Entity type — any polymorphic feed item type (post, comment, listing, event, etc.) */
  entityType: string;
  /** Entity ID for loading reactor details */
  entityId: number;
}

/* ───────────────────────── Component ───────────────────────── */

export function ReactionSummary({
  counts,
  total,
  topReactors = [],
  entityType,
  entityId,
}: ReactionSummaryProps) {
  const { t } = useTranslation('feed');
  const { tenantPath } = useTenant();
  const tr = (key: string, fallback: string) => {
    const value = t(key);
    return value === key ? fallback : value;
  };
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedTab, setSelectedTab] = useState<string>('all');
  const [reactors, setReactors] = useState<ReactorUser[]>([]);
  const [isLoadingReactors, setIsLoadingReactors] = useState(false);
  const [reactorPage, setReactorPage] = useState(1);
  const [hasMoreReactors, setHasMoreReactors] = useState(false);

  // Load reactors for the modal
  const loadReactors = useCallback(
    async (type: string, page = 1, append = false) => {
      setIsLoadingReactors(true);
      try {
        // Use the polymorphic /v2/reactions/{type}/{id} endpoints. The legacy
        // /v2/{entityType}s/{entityId}/reactions form only had post + comment
        // routes registered, so listing/event/etc. reactor lookups were 404ing.
        const endpoint =
          type === 'all'
            ? `/v2/reactions/${entityType}/${entityId}`
            : `/v2/reactions/${entityType}/${entityId}/users/${type}?page=${page}&per_page=20`;

        if (type === 'all') {
          // For "all" tab, we show the summary data (top reactors from existing data)
          // Re-fetch to get fresh top_reactors
          const res = await api.get<{
            counts: Record<string, number>;
            total: number;
            top_reactors: Array<{ id: number; name: string; avatar_url: string | null }>;
          }>(endpoint);
          if (res.success && res.data) {
            const allReactors = (res.data.top_reactors || []).map((r) => ({
              ...r,
              reacted_at: new Date().toISOString(),
            }));
            setReactors(allReactors);
            setHasMoreReactors(false);
          }
        } else {
          const res = await api.get<ReactorUser[]>(endpoint);
          if (res.success && res.data) {
            const users = Array.isArray(res.data) ? res.data : [];
            if (append) {
              setReactors((prev) => [...prev, ...users]);
            } else {
              setReactors(users);
            }
            setHasMoreReactors(res.meta?.has_more ?? false);
          }
        }
      } catch (err) {
        logError('Failed to load reactors', err);
      } finally {
        setIsLoadingReactors(false);
      }
    },
    [entityType, entityId]
  );

  const handleOpenModal = useCallback(() => {
    setIsModalOpen(true);
    setSelectedTab('all');
    setReactorPage(1);
    void loadReactors('all', 1);
  }, [loadReactors]);

  const handleTabChange = useCallback(
    (key: React.Key) => {
      const tabKey = String(key);
      setSelectedTab(tabKey);
      setReactorPage(1);
      setReactors([]);
      void loadReactors(tabKey, 1);
    },
    [loadReactors]
  );

  const handleLoadMore = useCallback(() => {
    const nextPage = reactorPage + 1;
    setReactorPage(nextPage);
    void loadReactors(selectedTab, nextPage, true);
  }, [reactorPage, selectedTab, loadReactors]);

  // Sort reaction types by count descending
  const sortedTypes = Object.entries(counts)
    .filter(([, count]) => count > 0)
    .sort(([, a], [, b]) => b - a);

  if (total === 0 || sortedTypes.length === 0) {
    return null;
  }

  // Build summary text
  const summaryText = (() => {
    if (topReactors.length === 0) {
      return `${total} ${total === 1 ? tr('card.reaction', 'reaction') : tr('card.reactions', 'reactions')}`;
    }
    const names = topReactors.map((r) => r.name);
    const remaining = total - names.length;
    if (remaining <= 0) {
      return names.join(', ');
    }
    if (names.length === 1) {
      return `${names[0]} ${tr('card.and', 'and')} ${remaining} ${remaining === 1 ? tr('card.other', 'other') : tr('card.others', 'others')}`;
    }
    return `${names.join(', ')}, ${tr('card.and', 'and')} ${remaining} ${remaining === 1 ? tr('card.other', 'other') : tr('card.others', 'others')}`;
  })();
  const viewReactionsLabel = tr('card.view_reactions_aria', 'View reactions: {{summary}}').replace(
    '{{summary}}',
    summaryText
  );

  /* ───── Render inline summary ───── */

  const renderEmoji = (type: string) => {
    if (type === 'time_credit') {
      return <Clock className="w-3 h-3 text-purple-400" aria-hidden="true" />;
    }
    return (
      <span role="img" aria-label={REACTION_LABEL_MAP[type as ReactionType] ?? type}>
        {REACTION_EMOJI_MAP[type as ReactionType] ?? type}
      </span>
    );
  };

  return (
    <>
      {/* Inline summary row */}
      <Button
        variant="light"
        size="sm"
        onPress={handleOpenModal}
        className="flex items-center gap-1.5 text-xs text-[var(--text-subtle)] hover:text-[var(--text-primary)] transition-colors h-auto p-0 min-w-0"
        aria-label={viewReactionsLabel}
      >
        {/* Emoji badges */}
        <span className="flex items-center -space-x-0.5">
          {sortedTypes.slice(0, 3).map(([type]) => (
            <span
              key={type}
              className="w-5 h-5 rounded-full bg-[var(--surface-elevated)] border border-[var(--border-default)] flex items-center justify-center text-xs"
            >
              {renderEmoji(type)}
            </span>
          ))}
        </span>
        <span>{summaryText}</span>
      </Button>

      {/* Reactors Modal */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        size="md"
        scrollBehavior="inside"
        classNames={{
          base: 'bg-[var(--glass-bg)] backdrop-blur-xl border border-[var(--glass-border)]',
          backdrop: 'bg-black/60 backdrop-blur-sm',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-[var(--text-primary)] pb-0">
            <div className="flex items-center gap-3">
              <div className="flex -space-x-1">
                {sortedTypes.slice(0, 3).map(([type]) => (
                  <span
                    key={type}
                    className="w-7 h-7 rounded-full bg-[var(--surface-elevated)] border-2 border-[var(--glass-bg)] flex items-center justify-center text-sm"
                  >
                    {renderEmoji(type)}
                  </span>
                ))}
              </div>
              {tr('card.reactions_title', 'Reactions')} ({total})
            </div>
          </ModalHeader>
          <ModalBody className="pb-4 pt-2">
            {/* Tabs for each reaction type */}
            <Tabs
              selectedKey={selectedTab}
              onSelectionChange={handleTabChange}
              variant="light"
              size="sm"
              classNames={{
                tabList: 'gap-1 flex-wrap',
                tab: 'text-xs px-2',
              }}
            >
              <Tab key="all" title={`${tr('card.all', 'All')} ${total}`} />
              {sortedTypes.map(([type, count]) => (
                <Tab
                  key={type}
                  title={
                    <span className="flex items-center gap-1">
                      {renderEmoji(type)} {count}
                    </span>
                  }
                />
              ))}
            </Tabs>

            {/* Reactor list */}
            <div className="mt-3">
              {isLoadingReactors && reactors.length === 0 ? (
                <div className="flex justify-center py-8">
                  <Spinner size="md" />
                </div>
              ) : reactors.length === 0 ? (
                <p className="text-sm text-[var(--text-subtle)] text-center py-6 italic">
                  {tr('card.no_reactions', 'No reactions yet')}
                </p>
              ) : (
                <div className="space-y-1">
                  {reactors.map((reactor) => (
                    <Link
                      key={reactor.id}
                      to={tenantPath(`/profile/${reactor.id}`)}
                      onClick={() => setIsModalOpen(false)}
                      className="flex items-center gap-3 p-2 rounded-lg hover:bg-[var(--surface-hover)] transition-colors"
                    >
                      <Avatar
                        name={reactor.name}
                        src={resolveAvatarUrl(reactor.avatar_url)}
                        size="sm"
                        className="ring-2 ring-white/10"
                      />
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-[var(--text-primary)] truncate">
                          {reactor.name}
                        </p>
                        {reactor.reacted_at && (
                          <p className="text-xs text-[var(--text-subtle)]">
                            {formatRelativeTime(reactor.reacted_at)}
                          </p>
                        )}
                      </div>
                    </Link>
                  ))}

                  {hasMoreReactors && (
                    <div className="pt-2 text-center">
                      <Button
                        size="sm"
                        variant="flat"
                        className="bg-[var(--surface-elevated)] text-[var(--text-muted)]"
                        onPress={handleLoadMore}
                        isLoading={isLoadingReactors}
                      >
                        {tr('card.load_more', 'Load More')}
                      </Button>
                    </div>
                  )}
                </div>
              )}
            </div>
          </ModalBody>
        </ModalContent>
      </Modal>
    </>
  );
}

export default ReactionSummary;
