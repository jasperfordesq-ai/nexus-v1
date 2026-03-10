// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Discussion Tab
 * Thread-based discussions with expand/collapse and inline replies.
 */

import { Fragment } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Avatar,
  Textarea,
  Spinner,
} from '@heroui/react';
import {
  MessageSquare,
  Lock,
  Plus,
  Send,
  Clock,
  ChevronDown,
  ChevronUp,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useTranslation } from 'react-i18next';
import { useAuth } from '@/contexts';
import { resolveAvatarUrl, formatRelativeTime } from '@/lib/helpers';

export interface Discussion {
  id: number;
  title: string;
  content?: string;
  author: {
    id: number;
    name: string;
    avatar_url?: string | null;
  };
  reply_count: number;
  is_pinned?: boolean;
  last_reply_at?: string | null;
  created_at: string;
}

export interface DiscussionMessage {
  id: number;
  content: string;
  author: {
    id: number;
    name: string;
    avatar_url?: string | null;
  };
  is_own?: boolean;
  created_at: string;
}

export interface DiscussionDetail extends Discussion {
  messages: DiscussionMessage[];
}

interface GroupDiscussionTabProps {
  isMember: boolean;
  isJoining: boolean;
  discussions: Discussion[];
  discussionsLoading: boolean;
  discussionsHasMore: boolean;
  expandedDiscussionId: number | null;
  expandedDiscussion: DiscussionDetail | null;
  expandedLoading: boolean;
  replyContent: string;
  sendingReply: boolean;
  onJoinLeave: () => void;
  onShowNewDiscussion: () => void;
  onExpandDiscussion: (id: number) => void;
  onLoadMoreDiscussions: () => void;
  onReplyContentChange: (value: string) => void;
  onSendReply: () => void;
}

export function GroupDiscussionTab({
  isMember,
  isJoining,
  discussions,
  discussionsLoading,
  discussionsHasMore,
  expandedDiscussionId,
  expandedDiscussion,
  expandedLoading,
  replyContent,
  sendingReply,
  onJoinLeave,
  onShowNewDiscussion,
  onExpandDiscussion,
  onLoadMoreDiscussions,
  onReplyContentChange,
  onSendReply,
}: GroupDiscussionTabProps) {
  const { t } = useTranslation('groups');
  const { isAuthenticated } = useAuth();

  if (!isMember) {
    return (
      <GlassCard className="p-6">
        <EmptyState
          icon={<Lock className="w-12 h-12" aria-hidden="true" />}
          title={t('detail.join_to_discuss_title')}
          description={t('detail.join_to_discuss_desc')}
          action={
            isAuthenticated && (
              <Button
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                onPress={onJoinLeave}
                isLoading={isJoining}
              >
                {t('detail.join_group')}
              </Button>
            )
          }
        />
      </GlassCard>
    );
  }

  return (
    <GlassCard className="p-6">
      <div className="space-y-4">
        {/* Header */}
        <div className="flex justify-between items-center">
          <h2 className="text-lg font-semibold text-theme-primary">{t('detail.discussions_heading')}</h2>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            size="sm"
            startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
            onPress={onShowNewDiscussion}
          >
            {t('detail.new_discussion')}
          </Button>
        </div>

        {/* Discussions List */}
        {discussionsLoading && discussions.length === 0 ? (
          <div className="flex justify-center py-8">
            <Spinner size="lg" />
          </div>
        ) : discussions.length === 0 ? (
          <EmptyState
            icon={<MessageSquare className="w-12 h-12" aria-hidden="true" />}
            title={t('detail.no_discussions_title')}
            description={t('detail.no_discussions_desc')}
            action={
              <Button
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                onPress={onShowNewDiscussion}
                startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
              >
                {t('detail.start_discussion')}
              </Button>
            }
          />
        ) : (
          <div className="space-y-3">
            {discussions.map((discussion) => (
              <Fragment key={discussion.id}>
                <motion.div
                  layout
                  className="rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors cursor-pointer overflow-hidden"
                  onClick={() => onExpandDiscussion(discussion.id)}
                  role="button"
                  tabIndex={0}
                  aria-expanded={expandedDiscussionId === discussion.id}
                  onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onExpandDiscussion(discussion.id); } }}
                >
                  <div className="p-4">
                    <div className="flex items-start gap-3">
                      <Avatar
                        src={resolveAvatarUrl(discussion.author.avatar_url)}
                        name={discussion.author.name}
                        size="sm"
                        className="flex-shrink-0 mt-0.5"
                      />
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between gap-2">
                          <h3 className="font-medium text-theme-primary truncate">{discussion.title}</h3>
                          {expandedDiscussionId === discussion.id ? (
                            <ChevronUp className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
                          ) : (
                            <ChevronDown className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
                          )}
                        </div>
                        <div className="flex items-center gap-3 mt-1 text-xs text-theme-subtle">
                          <span>{discussion.author.name}</span>
                          <span className="flex items-center gap-1">
                            <MessageSquare className="w-3 h-3" aria-hidden="true" />
                            {t('detail.reply_count', { count: discussion.reply_count })}
                          </span>
                          <span className="flex items-center gap-1">
                            <Clock className="w-3 h-3" aria-hidden="true" />
                            {formatRelativeTime(discussion.last_reply_at || discussion.created_at)}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </motion.div>

                {/* Expanded Discussion */}
                <AnimatePresence>
                  {expandedDiscussionId === discussion.id && (
                    <motion.div
                      initial={{ height: 0, opacity: 0 }}
                      animate={{ height: 'auto', opacity: 1 }}
                      exit={{ height: 0, opacity: 0 }}
                      transition={{ duration: 0.2 }}
                      className="overflow-hidden"
                    >
                      <div className="ml-3 sm:ml-6 pl-3 sm:pl-6 border-l-2 border-theme-default space-y-4 pb-2">
                        {expandedLoading ? (
                          <div className="flex justify-center py-4">
                            <Spinner size="sm" />
                          </div>
                        ) : expandedDiscussion ? (
                          <>
                            {/* Original discussion content */}
                            <div className="p-3 rounded-lg bg-theme-elevated/50">
                              <p className="text-sm text-theme-muted whitespace-pre-wrap">{expandedDiscussion.content}</p>
                            </div>

                            {/* Messages */}
                            {expandedDiscussion.messages && expandedDiscussion.messages.length > 0 && (
                              <div className="space-y-3">
                                {expandedDiscussion.messages.map((msg) => (
                                  <div key={msg.id} className="flex gap-3 p-3 rounded-lg bg-theme-elevated/30">
                                    <Avatar
                                      src={resolveAvatarUrl(msg.author.avatar_url)}
                                      name={msg.author.name}
                                      size="sm"
                                      className="flex-shrink-0"
                                    />
                                    <div className="flex-1 min-w-0">
                                      <div className="flex items-center gap-2">
                                        <span className="text-sm font-medium text-theme-primary">{msg.author.name}</span>
                                        <time className="text-xs text-theme-subtle" dateTime={msg.created_at}>
                                          {formatRelativeTime(msg.created_at)}
                                        </time>
                                      </div>
                                      <p className="text-sm text-theme-muted mt-1 whitespace-pre-wrap">{msg.content}</p>
                                    </div>
                                  </div>
                                ))}
                              </div>
                            )}

                            {/* Reply Form */}
                            <div className="flex flex-col sm:flex-row gap-2 items-end">
                              <Textarea
                                placeholder={t('detail.reply_placeholder')}
                                aria-label={t('detail.reply_aria')}
                                value={replyContent}
                                onChange={(e) => onReplyContentChange(e.target.value)}
                                minRows={1}
                                maxRows={4}
                                classNames={{
                                  input: 'bg-transparent text-theme-primary',
                                  inputWrapper: 'bg-theme-elevated border-theme-default',
                                }}
                              />
                              <Button
                                isIconOnly
                                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white flex-shrink-0"
                                aria-label={t('detail.send_reply_aria')}
                                isLoading={sendingReply}
                                isDisabled={!replyContent.trim()}
                                onPress={onSendReply}
                              >
                                <Send className="w-4 h-4" />
                              </Button>
                            </div>
                          </>
                        ) : null}
                      </div>
                    </motion.div>
                  )}
                </AnimatePresence>
              </Fragment>
            ))}

            {/* Load More Discussions */}
            {discussionsHasMore && (
              <div className="pt-2 text-center">
                <Button
                  variant="flat"
                  size="sm"
                  className="bg-theme-elevated text-theme-muted"
                  isLoading={discussionsLoading}
                  onPress={onLoadMoreDiscussions}
                >
                  {t('detail.load_more_discussions')}
                </Button>
              </div>
            )}
          </div>
        )}
      </div>
    </GlassCard>
  );
}
