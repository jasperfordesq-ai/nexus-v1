// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useTranslation } from 'react-i18next';
import { Button } from '@heroui/react';
import Lock from 'lucide-react/icons/lock';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth } from '@/contexts';
import type { Event } from '@/types/api';
import type { FeedItem } from '@/components/feed/types';

// Tab components
import { GroupFeedTab } from '../tabs/GroupFeedTab';
import { GroupDiscussionTab } from '../tabs/GroupDiscussionTab';
import type { Discussion, DiscussionDetail } from '../tabs/GroupDiscussionTab';
import { GroupMembersTab } from '../tabs/GroupMembersTab';
import type { GroupMember } from '../tabs/GroupMembersTab';
import { GroupEventsTab } from '../tabs/GroupEventsTab';
import { GroupFilesTab } from '../tabs/GroupFilesTab';
import { GroupAnnouncementsTab } from '../tabs/GroupAnnouncementsTab';
import { GroupChatroomsTab } from '../tabs/GroupChatroomsTab';
import { GroupTasksTab } from '../tabs/GroupTasksTab';
import { GroupSubgroupsTab } from '../tabs/GroupSubgroupsTab';
import { GroupQATab } from '../tabs/GroupQATab';
import { GroupWikiTab } from '../tabs/GroupWikiTab';
import { GroupMediaTab } from '../tabs/GroupMediaTab';
import { GroupAnalyticsTab } from '../tabs/GroupAnalyticsTab';
import { GroupChallengesTab } from '../tabs/GroupChallengesTab';

interface GroupTabContentProps {
  activeTab: string;
  groupId: number;
  userIsMember: boolean;
  userIsAdmin: boolean;
  isJoining: boolean;
  currentUserId?: number;
  groupOwnerId?: number;
  groupAdminIds?: number[];
  hasSubGroups: boolean;
  subGroups?: Array<{ id: number; name: string; member_count: number }>;

  // Feed
  feedItems: FeedItem[];
  feedLoading: boolean;
  feedHasMore: boolean;
  feedLoadingMore: boolean;
  onComposeOpen: () => void;
  onLoadMoreFeed: () => void;
  onRefreshFeed?: () => void | Promise<void>;
  onToggleLike: (item: FeedItem) => void;
  onHidePost: (item: FeedItem) => void;
  onMuteUser: (userId: number) => void;
  onReportPost: (postId: number) => void;
  onDeletePost: (item: FeedItem) => void;
  onVotePoll: (pollId: number, optionId: number) => void;

  // Discussions
  discussions: Discussion[];
  discussionsLoading: boolean;
  discussionsHasMore: boolean;
  expandedDiscussionId: number | null;
  expandedDiscussion: DiscussionDetail | null;
  expandedLoading: boolean;
  replyContent: string;
  sendingReply: boolean;
  onShowNewDiscussion: () => void;
  onExpandDiscussion: (id: number) => void;
  onLoadMoreDiscussions: () => void;
  onReplyContentChange: (value: string) => void;
  onSendReply: () => void;

  // Members
  members: GroupMember[];
  membersLoading: boolean;
  updatingMember: number | null;
  onUpdateMemberRole: (userId: number, role: 'member' | 'admin') => void;
  onRemoveMember: (userId: number) => void;

  // Events
  events: Event[];
  eventsLoading: boolean;

  // Join/leave
  onJoinLeave: () => void;
}

export function GroupTabContent({
  activeTab,
  groupId,
  userIsMember,
  userIsAdmin,
  isJoining,
  currentUserId,
  groupOwnerId,
  groupAdminIds,
  hasSubGroups,
  subGroups,
  feedItems,
  feedLoading,
  feedHasMore,
  feedLoadingMore,
  onComposeOpen,
  onLoadMoreFeed,
  onRefreshFeed,
  onToggleLike,
  onHidePost,
  onMuteUser,
  onReportPost,
  onDeletePost,
  onVotePoll,
  discussions,
  discussionsLoading,
  discussionsHasMore,
  expandedDiscussionId,
  expandedDiscussion,
  expandedLoading,
  replyContent,
  sendingReply,
  onShowNewDiscussion,
  onExpandDiscussion,
  onLoadMoreDiscussions,
  onReplyContentChange,
  onSendReply,
  members,
  membersLoading,
  updatingMember,
  onUpdateMemberRole,
  onRemoveMember,
  events,
  eventsLoading,
  onJoinLeave,
}: GroupTabContentProps) {
  const { t } = useTranslation('groups');
  const { isAuthenticated } = useAuth();

  const MembersOnlyFallback = (
    <GlassCard className="p-6">
      <EmptyState
        icon={<Lock className="w-12 h-12" aria-hidden="true" />}
        title={t('detail.join_to_access_title', 'Members Only')}
        description={t('detail.join_to_access_desc', 'Join this group to access this feature.')}
        action={
          isAuthenticated ? (
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={onJoinLeave}
              isLoading={isJoining}
            >
              {t('detail.join_group')}
            </Button>
          ) : undefined
        }
      />
    </GlassCard>
  );

  return (
    <div>
      {activeTab === 'feed' && (
        <GroupFeedTab
          isMember={userIsMember}
          isJoining={isJoining}
          feedItems={feedItems}
          feedLoading={feedLoading}
          feedHasMore={feedHasMore}
          feedLoadingMore={feedLoadingMore}
          onJoinLeave={onJoinLeave}
          onComposeOpen={onComposeOpen}
          onLoadMore={onLoadMoreFeed}
          onRefresh={onRefreshFeed}
          onToggleLike={onToggleLike}
          onHidePost={onHidePost}
          onMuteUser={onMuteUser}
          onReportPost={onReportPost}
          onDeletePost={onDeletePost}
          onVotePoll={onVotePoll}
        />
      )}

      {activeTab === 'discussion' && (
        <GroupDiscussionTab
          isMember={userIsMember}
          isJoining={isJoining}
          discussions={discussions}
          discussionsLoading={discussionsLoading}
          discussionsHasMore={discussionsHasMore}
          expandedDiscussionId={expandedDiscussionId}
          expandedDiscussion={expandedDiscussion}
          expandedLoading={expandedLoading}
          replyContent={replyContent}
          sendingReply={sendingReply}
          onJoinLeave={onJoinLeave}
          onShowNewDiscussion={onShowNewDiscussion}
          onExpandDiscussion={onExpandDiscussion}
          onLoadMoreDiscussions={onLoadMoreDiscussions}
          onReplyContentChange={onReplyContentChange}
          onSendReply={onSendReply}
        />
      )}

      {activeTab === 'members' && (
        <GroupMembersTab
          members={members}
          membersLoading={membersLoading}
          userIsAdmin={userIsAdmin}
          currentUserId={currentUserId}
          groupOwnerId={groupOwnerId}
          groupAdminIds={groupAdminIds}
          updatingMember={updatingMember}
          onUpdateMemberRole={onUpdateMemberRole}
          onRemoveMember={onRemoveMember}
        />
      )}

      {activeTab === 'events' && (
        <GroupEventsTab
          groupId={groupId}
          events={events}
          eventsLoading={eventsLoading}
          isMember={userIsMember}
        />
      )}

      {activeTab === 'files' && (userIsMember ? (
        <GroupFilesTab
          groupId={groupId}
          isAdmin={userIsAdmin}
          isMember={userIsMember}
          currentUserId={currentUserId}
        />
      ) : MembersOnlyFallback)}

      {activeTab === 'announcements' && (userIsMember ? (
        <GroupAnnouncementsTab
          groupId={groupId}
          isAdmin={userIsAdmin}
          isMember={userIsMember}
        />
      ) : MembersOnlyFallback)}

      {activeTab === 'chatrooms' && (userIsMember ? (
        <GroupChatroomsTab
          groupId={groupId}
          isGroupAdmin={userIsAdmin}
        />
      ) : MembersOnlyFallback)}

      {activeTab === 'tasks' && (userIsMember ? (
        <GroupTasksTab
          groupId={groupId}
          isGroupAdmin={userIsAdmin}
          members={members}
        />
      ) : MembersOnlyFallback)}

      {activeTab === 'qa' && (
        <GroupQATab groupId={groupId} isAdmin={userIsAdmin} isMember={userIsMember} />
      )}

      {activeTab === 'wiki' && (
        <GroupWikiTab groupId={groupId} isAdmin={userIsAdmin} isMember={userIsMember} />
      )}

      {activeTab === 'media' && (
        <GroupMediaTab groupId={groupId} isAdmin={userIsAdmin} isMember={userIsMember} />
      )}

      {activeTab === 'challenges' && (
        <GroupChallengesTab groupId={groupId} isAdmin={userIsAdmin} isMember={userIsMember} />
      )}

      {activeTab === 'analytics' && userIsAdmin && (
        <GroupAnalyticsTab groupId={groupId} isAdmin={userIsAdmin} />
      )}

      {activeTab === 'subgroups' && hasSubGroups && subGroups && (
        <GroupSubgroupsTab subGroups={subGroups} />
      )}
    </div>
  );
}
