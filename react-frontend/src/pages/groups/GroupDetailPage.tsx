// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Detail Page - Single group view
 * Full discussions UI, admin features, events tab, member management
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Helmet } from 'react-helmet-async';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, useDisclosure } from '@heroui/react';
import AlertCircle from 'lucide-react/icons/circle-alert';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo/PageMeta';
import { Breadcrumbs } from '@/components/navigation';
import { ComposeHub } from '@/components/compose';
import { LoadingScreen } from '@/components/feedback';
import { useTranslation } from 'react-i18next';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { applyFeedSyncToItem, dispatchFeedSync, FEED_SYNC_EVENT, type FeedSyncPayload } from '@/lib/feedSync';
import type { FeedItem } from '@/components/feed/types';
import type { ReactionType } from '@/components/social';
import type { Group, User, FeedPost, Event } from '@/types/api';

// Tab types
import type { Discussion, DiscussionDetail, DiscussionMessage } from './tabs/GroupDiscussionTab';
import type { GroupMember } from './tabs/GroupMembersTab';

// Sub-components
import { GroupHeader } from './components/GroupHeader';
import type { JoinRequest } from './components/GroupHeader';
import { GroupTabNav } from './components/GroupTabNav';
import { GroupTabContent } from './components/GroupTabContent';
import { PinnedAnnouncementsBanner } from './components/PinnedAnnouncementsBanner';
import { GroupNotificationPrefs } from './components/GroupNotificationPrefs';
import {
  NewDiscussionModal,
  GroupSettingsModal,
  GroupLeaveModal,
  GroupDeleteModal,
  GroupInviteModal,
  GroupReportModal,
} from './components/GroupModals';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface GroupDetails extends Group {
  members?: User[];
  recent_posts?: FeedPost[];
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function getMemberCount(group: GroupDetails): number {
  return group.member_count ?? group.members_count ?? 0;
}

function isMember(group: GroupDetails): boolean {
  if (group.viewer_membership) {
    return group.viewer_membership.status === 'active';
  }
  return group.is_member ?? false;
}

function isGroupAdmin(group: GroupDetails): boolean {
  if (group.viewer_membership) {
    return group.viewer_membership.is_admin;
  }
  return group.is_admin ?? false;
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function GroupDetailPage() {
  const { t } = useTranslation('groups');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user: currentUser, isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  // AbortController ref to cancel stale requests
  const abortRef = useRef<AbortController | null>(null);

  // Stable refs for t/toast — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

  // Core state
  const [group, setGroup] = useState<GroupDetails | null>(null);
  const [activeTab, setActiveTab] = useState('feed');
  const [isLoading, setIsLoading] = useState(true);
  const [isJoining, setIsJoining] = useState(false);
  const [error, setError] = useState<string | null>(null);

  usePageTitle(group?.name ?? t('title'));

  // Feed state
  const [feedItems, setFeedItems] = useState<FeedItem[]>([]);
  const [feedLoading, setFeedLoading] = useState(false);
  const [feedLoaded, setFeedLoaded] = useState(false);
  const [feedHasMore, setFeedHasMore] = useState(false);
  const [feedLoadingMore, setFeedLoadingMore] = useState(false);
  const feedCursorRef = useRef<string | undefined>();

  // Compose Hub for group posts
  const { isOpen: isComposeOpen, onOpen: onComposeOpen, onClose: onComposeClose } = useDisclosure();

  // Report modal
  const { isOpen: isReportOpen, onOpen: onReportOpen, onClose: onReportClose } = useDisclosure();
  const [reportPostId, setReportPostId] = useState<number | null>(null);
  const [reportReason, setReportReason] = useState('');
  const [isReporting, setIsReporting] = useState(false);

  // Members state
  const [members, setMembers] = useState<GroupMember[]>([]);
  const [membersLoading, setMembersLoading] = useState(false);
  const [membersLoaded, setMembersLoaded] = useState(false);

  // Discussions state
  const [discussions, setDiscussions] = useState<Discussion[]>([]);
  const [discussionsLoading, setDiscussionsLoading] = useState(false);
  const [discussionsLoaded, setDiscussionsLoaded] = useState(false);
  const [discussionsCursor, setDiscussionsCursor] = useState<string | undefined>();
  const [discussionsHasMore, setDiscussionsHasMore] = useState(true);
  const [showNewDiscussion, setShowNewDiscussion] = useState(false);
  const [newDiscussionTitle, setNewDiscussionTitle] = useState('');
  const [newDiscussionContent, setNewDiscussionContent] = useState('');
  const [creatingDiscussion, setCreatingDiscussion] = useState(false);

  // Invite modal state
  const [showInviteModal, setShowInviteModal] = useState(false);
  const [inviteEmails, setInviteEmails] = useState('');
  const [inviteMessage, setInviteMessage] = useState('');
  const [inviteLink, setInviteLink] = useState<string | null>(null);
  const [sendingInvites, setSendingInvites] = useState(false);

  // Tags state
  const [groupTags, setGroupTags] = useState<Array<{ id: number; name: string; color?: string }>>([]);

  // Notification preferences modal
  const [showNotifPrefs, setShowNotifPrefs] = useState(false);

  // Expanded discussion state
  const [expandedDiscussionId, setExpandedDiscussionId] = useState<number | null>(null);
  const [expandedDiscussion, setExpandedDiscussion] = useState<DiscussionDetail | null>(null);
  const [expandedLoading, setExpandedLoading] = useState(false);
  const [replyContent, setReplyContent] = useState('');
  const [sendingReply, setSendingReply] = useState(false);

  // Admin state
  const [showSettingsModal, setShowSettingsModal] = useState(false);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [settingsName, setSettingsName] = useState('');
  const [settingsDescription, setSettingsDescription] = useState('');
  const [settingsPrivate, setSettingsPrivate] = useState(false);
  const [settingsLocation, setSettingsLocation] = useState('');
  const [uploadingImage, setUploadingImage] = useState(false);
  const [savingSettings, setSavingSettings] = useState(false);
  const [deletingGroup, setDeletingGroup] = useState(false);

  // Pending requests state
  const [joinRequests, setJoinRequests] = useState<JoinRequest[]>([]);
  const [requestsLoading, setRequestsLoading] = useState(false);
  const [requestsLoaded, setRequestsLoaded] = useState(false);
  const [processingRequest, setProcessingRequest] = useState<number | null>(null);

  // Member management state
  const [updatingMember, setUpdatingMember] = useState<number | null>(null);

  // Leave confirmation state
  const [showLeaveConfirm, setShowLeaveConfirm] = useState(false);

  // Events state
  const [events, setEvents] = useState<Event[]>([]);
  const [eventsLoading, setEventsLoading] = useState(false);
  const [eventsLoaded, setEventsLoaded] = useState(false);

  // ─────────────────────────────────────────────────────────────────────────
  // Load Group
  // ─────────────────────────────────────────────────────────────────────────

  const loadGroup = useCallback(async () => {
    if (!id) return;
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<GroupDetails>(`/v2/groups/${id}`);
      if (controller.signal.aborted) return;
      if (response.success && response.data) {
        setGroup(response.data);
        if (response.data.sub_groups && response.data.sub_groups.length > 0) {
          setActiveTab('subgroups');
        }
      } else {
        setError(tRef.current('detail.not_found_desc'));
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load group', err);
      setError(tRef.current('detail.error_load_failed'));
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadGroup();
  }, [loadGroup]);

  // Load tags for the group (requires auth)
  useEffect(() => {
    if (!id || !isAuthenticated) return;
    let cancelled = false;
    api.get(`/v2/groups/${id}/tags`)
      .then((resp) => {
        if (cancelled) return;
        setGroupTags((resp.data ?? []) as Array<{ id: number; name: string; color?: string }>);
      })
      .catch((err) => { logError('GroupDetailPage.loadTags', err); });
    return () => { cancelled = true; };
  }, [id, isAuthenticated]);

  // Invite handlers
  const handleGenerateInviteLink = async () => {
    if (!id) return;
    try {
      const resp = await api.post<{ invite_url: string }>(`/v2/groups/${id}/invites/link`);
      setInviteLink(resp.data?.invite_url || null);
    } catch (err) {
      logError('GroupDetailPage.generateInviteLink', err);
      toastRef.current.error(t('detail.invite_link_error', 'Failed to generate invite link'));
    }
  };

  const handleSendInvites = async () => {
    if (!id || !inviteEmails.trim()) return;
    setSendingInvites(true);
    try {
      const emails = inviteEmails.split(',').map((e: string) => e.trim()).filter(Boolean);
      await api.post(`/v2/groups/${id}/invites/email`, { emails, message: inviteMessage });
      toast.success(t('detail.invites_sent', 'Invitations sent!'));
      setInviteEmails('');
      setInviteMessage('');
      setShowInviteModal(false);
    } catch (err) {
      logError('GroupDetailPage.sendInvites', err);
      toast.error(t('detail.invites_failed', 'Failed to send invitations'));
    } finally {
      setSendingInvites(false);
    }
  };

  // ─────────────────────────────────────────────────────────────────────────
  // Load Group Feed
  // ─────────────────────────────────────────────────────────────────────────

  const loadGroupFeed = useCallback(async (append = false) => {
    if (!id) return;
    if (append && !feedCursorRef.current) return;

    try {
      if (append) {
        setFeedLoadingMore(true);
      } else {
        setFeedLoading(true);
      }

      const params = new URLSearchParams();
      params.set('group_id', id);
      params.set('per_page', '20');
      if (append && feedCursorRef.current) params.set('cursor', feedCursorRef.current);

      const response = await api.get<FeedItem[]>(`/v2/feed?${params}`);
      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : [];
        if (append) {
          setFeedItems((prev) => [...prev, ...items]);
        } else {
          setFeedItems(items);
        }
        setFeedHasMore(response.meta?.has_more ?? false);
        feedCursorRef.current = response.meta?.cursor ?? undefined;
      }
    } catch (err) {
      logError('Failed to load group feed', err);
      if (!append) {
        toastRef.current.error(tRef.current('toast.feed_load_failed'));
      }
    } finally {
      setFeedLoading(false);
      setFeedLoadingMore(false);
      setFeedLoaded(true);
    }
  }, [id]);

  useEffect(() => {
    const handler = (event: globalThis.Event) => {
      const payload = (event as unknown as CustomEvent<FeedSyncPayload>).detail;
      setFeedItems((prev) => prev.map((item) => applyFeedSyncToItem(item, payload)));
    };

    window.addEventListener(FEED_SYNC_EVENT, handler as EventListener);
    return () => window.removeEventListener(FEED_SYNC_EVENT, handler as EventListener);
  }, []);

  useEffect(() => {
    if (activeTab === 'feed' && !feedLoaded && group && isMember(group)) {
      loadGroupFeed();
    }
  }, [activeTab, feedLoaded, group]); // eslint-disable-line react-hooks/exhaustive-deps -- lazy load feed tab; loadGroupFeed excluded to avoid loop

  // ─────────────────────────────────────────────────────────────────────────
  // Feed Actions (like, hide, mute, report, delete, poll vote)
  // ─────────────────────────────────────────────────────────────────────────

  const handleFeedToggleLike = async (item: FeedItem) => {
    const newIsLiked = !item.is_liked;
    const newLikesCount = newIsLiked ? item.likes_count + 1 : item.likes_count - 1;
    setFeedItems((prev) =>
      prev.map((fi) =>
        fi.id === item.id && fi.type === item.type
          ? { ...fi, is_liked: newIsLiked, likes_count: newLikesCount }
          : fi
      )
    );
    try {
      const response = await api.post<{ action?: string; likes_count: number }>('/v2/feed/like', { target_type: item.type, target_id: item.id });
      if (response.success && response.data) {
        const serverLiked = response.data.action === 'liked'
          ? true
          : response.data.action === 'unliked'
            ? false
            : newIsLiked;
        const serverLikesCount = response.data.likes_count;
        setFeedItems((prev) =>
          prev.map((fi) =>
            fi.id === item.id && fi.type === item.type
              ? { ...fi, is_liked: serverLiked, likes_count: serverLikesCount }
              : fi
          )
        );
        dispatchFeedSync({ targetType: item.type, targetId: item.id, patch: { is_liked: serverLiked, likes_count: serverLikesCount } });
      }
    } catch (err) {
      logError('Failed to toggle like', err);
      setFeedItems((prev) =>
        prev.map((fi) =>
          fi.id === item.id && fi.type === item.type
            ? { ...fi, is_liked: item.is_liked, likes_count: item.likes_count }
            : fi
        )
      );
    }
  };

  const handleFeedReact = async (item: FeedItem, reactionType: ReactionType) => {
    const previousReactions = item.reactions ?? { counts: {}, total: 0, user_reaction: null, top_reactors: [] };
    try {
      const response = await api.post<{ reactions: FeedItem['reactions'] }>('/v2/reactions', {
        target_type: item.type,
        target_id: item.id,
        reaction_type: reactionType,
      });
      if (response.success && response.data?.reactions) {
        const reactions = response.data.reactions;
        setFeedItems((prev) =>
          prev.map((fi) =>
            fi.id === item.id && fi.type === item.type
              ? { ...fi, reactions }
              : fi
          )
        );
        dispatchFeedSync({ targetType: item.type, targetId: item.id, patch: { reactions } });
      }
    } catch (err) {
      logError('Failed to react', err);
      setFeedItems((prev) =>
        prev.map((fi) =>
          fi.id === item.id && fi.type === item.type
            ? { ...fi, reactions: previousReactions }
            : fi
        )
      );
    }
  };

  const handleFeedHidePost = async (item: FeedItem) => {
    try {
      await api.post(`/v2/feed/posts/${item.id}/hide`, { type: item.type });
      setFeedItems((prev) => prev.filter((fi) => !(fi.id === item.id && fi.type === item.type)));
      toastRef.current.success(tRef.current('toast.post_hidden'));
    } catch (err) {
      logError('Failed to hide post', err);
      toastRef.current.error(tRef.current('toast.hide_failed'));
    }
  };

  const handleFeedMuteUser = async (userId: number) => {
    try {
      await api.post(`/v2/feed/users/${userId}/mute`);
      setFeedItems((prev) => prev.filter((fi) => {
        const author = fi.author ?? (fi as unknown as Record<string, unknown>).user as FeedItem['author'];
        return !author || author.id !== userId;
      }));
      toastRef.current.success(tRef.current('toast.user_muted'));
    } catch (err) {
      logError('Failed to mute user', err);
      toastRef.current.error(tRef.current('toast.mute_failed'));
    }
  };

  const openFeedReportModal = (postId: number) => {
    setReportPostId(postId);
    setReportReason('');
    onReportOpen();
  };

  const handleFeedReport = async () => {
    if (!reportPostId || !reportReason.trim()) {
      toastRef.current.error(tRef.current('toast.provide_reason'));
      return;
    }
    try {
      setIsReporting(true);
      await api.post(`/v2/feed/posts/${reportPostId}/report`, { reason: reportReason.trim() });
      onReportClose();
      setReportPostId(null);
      setReportReason('');
      toastRef.current.success(tRef.current('toast.reported'));
    } catch (err) {
      logError('Failed to report post', err);
      toastRef.current.error(tRef.current('toast.report_failed'));
    } finally {
      setIsReporting(false);
    }
  };

  const handleFeedDeletePost = async (item: FeedItem) => {
    try {
      await api.post(`/v2/feed/posts/${item.id}/delete`);
      setFeedItems((prev) => prev.filter((fi) => !(fi.id === item.id && fi.type === item.type)));
      toastRef.current.success(tRef.current('toast.post_deleted'));
    } catch (err) {
      logError('Failed to delete post', err);
      toastRef.current.error(tRef.current('toast.post_delete_failed'));
    }
  };

  const handleFeedVotePoll = async (pollId: number, optionId: number) => {
    try {
      const response = await api.post<{ id: number; question: string; options: Array<{ id: number; text: string; vote_count: number; percentage: number }>; total_votes: number; user_vote_option_id: number | null; is_active: boolean }>(`/v2/feed/polls/${pollId}/vote`, { option_id: optionId });
      if (response.success && response.data) {
        setFeedItems((prev) =>
          prev.map((fi) =>
            fi.id === pollId && fi.type === 'poll'
              ? { ...fi, poll_data: response.data as FeedItem['poll_data'] }
              : fi
          )
        );
      }
    } catch (err) {
      logError('Failed to vote', err);
      toastRef.current.error(tRef.current('toast.vote_failed'));
    }
  };

  // ─────────────────────────────────────────────────────────────────────────
  // Load Discussions
  // ─────────────────────────────────────────────────────────────────────────

  const loadDiscussions = useCallback(async (append = false) => {
    if (!id || discussionsLoading) return;
    if (append && !discussionsCursor) return;

    try {
      setDiscussionsLoading(true);
      const params = new URLSearchParams();
      if (append && discussionsCursor) {
        params.set('cursor', discussionsCursor);
      }
      params.set('per_page', '15');

      const response = await api.get<Discussion[]>(
        `/v2/groups/${id}/discussions?${params}`
      );
      if (response.success && response.data) {
        if (append) {
          setDiscussions((prev) => [...prev, ...response.data!]);
        } else {
          setDiscussions(response.data);
        }
        setDiscussionsCursor(response.meta?.cursor || undefined);
        setDiscussionsHasMore(response.meta?.has_more ?? response.data.length >= 15);
      }
    } catch (err) {
      logError('Failed to load discussions', err);
      if (!append) {
        toastRef.current.error(tRef.current('toast.discussions_load_failed'));
      }
    } finally {
      setDiscussionsLoading(false);
      setDiscussionsLoaded(true);
    }
  }, [id, discussionsCursor, discussionsLoading]);

  // Load discussions when tab changes to discussion
  useEffect(() => {
    if (activeTab === 'discussion' && !discussionsLoaded && group && isMember(group)) {
      loadDiscussions();
    }
  }, [activeTab, discussionsLoaded, group]); // eslint-disable-line react-hooks/exhaustive-deps -- lazy load discussion tab; loadDiscussions excluded to avoid loop

  // ─────────────────────────────────────────────────────────────────────────
  // Load Members
  // ─────────────────────────────────────────────────────────────────────────

  const loadMembers = useCallback(async () => {
    if (!id || membersLoaded || membersLoading) return;

    try {
      setMembersLoading(true);
      const response = await api.get<GroupMember[]>(`/v2/groups/${id}/members`);
      if (response.success && response.data) {
        setMembers(response.data);
      }
    } catch (err) {
      logError('Failed to load group members', err);
    } finally {
      setMembersLoading(false);
      setMembersLoaded(true);
    }
  }, [id, membersLoaded, membersLoading]);

  useEffect(() => {
    if (activeTab === 'members' && !membersLoaded) {
      loadMembers();
    }
  }, [activeTab, membersLoaded, loadMembers]);

  // ─────────────────────────────────────────────────────────────────────────
  // Load Events
  // ─────────────────────────────────────────────────────────────────────────

  const loadEvents = useCallback(async () => {
    if (!id || eventsLoaded || eventsLoading) return;

    try {
      setEventsLoading(true);
      const response = await api.get<Event[]>(`/v2/events?group_id=${id}&per_page=20`);
      if (response.success && response.data) {
        setEvents(response.data);
      }
    } catch (err) {
      logError('Failed to load group events', err);
    } finally {
      setEventsLoading(false);
      setEventsLoaded(true);
    }
  }, [id, eventsLoaded, eventsLoading]);

  useEffect(() => {
    if (activeTab === 'events' && !eventsLoaded) {
      loadEvents();
    }
  }, [activeTab, eventsLoaded, loadEvents]);

  // ─────────────────────────────────────────────────────────────────────────
  // Load Pending Join Requests (Admin)
  // ─────────────────────────────────────────────────────────────────────────

  const loadJoinRequests = useCallback(async () => {
    if (!id || requestsLoaded || requestsLoading) return;

    try {
      setRequestsLoading(true);
      const response = await api.get<JoinRequest[]>(`/v2/groups/${id}/requests`);
      if (response.success && response.data) {
        setJoinRequests(response.data);
      }
    } catch (err) {
      logError('Failed to load join requests', err);
    } finally {
      setRequestsLoading(false);
      setRequestsLoaded(true);
    }
  }, [id, requestsLoaded, requestsLoading]);

  // ─────────────────────────────────────────────────────────────────────────
  // Join / Leave
  // ─────────────────────────────────────────────────────────────────────────

  async function handleJoinLeave() {
    if (!group || !isAuthenticated) return;

    if (isMember(group)) {
      setShowLeaveConfirm(true);
      return;
    }

    try {
      setIsJoining(true);
      const memberCount = getMemberCount(group);
      const response = await api.post<{ status?: string }>(`/v2/groups/${group.id}/join`);
      if (response.success) {
        const joinStatus = response.data?.status ?? 'active';
        setGroup((prev) => prev ? {
          ...prev,
          is_member: joinStatus === 'active',
          viewer_membership: { status: joinStatus as 'active' | 'pending' | 'none', role: 'member', is_admin: false },
          member_count: joinStatus === 'active' ? memberCount + 1 : memberCount,
          members_count: joinStatus === 'active' ? memberCount + 1 : memberCount,
        } : null);
        toastRef.current.success(
          joinStatus === 'active'
            ? tRef.current('toast.joined')
            : tRef.current('toast.join_requested', 'Join request submitted')
        );
      } else if (response.code === 'JOIN_FAILED' && response.error?.toLowerCase().includes('already')) {
        // 409 — already a member but UI was stale; refresh to sync state
        toastRef.current.success(tRef.current('toast.joined'));
      } else {
        toastRef.current.error(tRef.current('toast.join_failed'));
      }
    } catch (err) {
      logError('Failed to join group', err);
      toastRef.current.error(tRef.current('toast.something_wrong'));
    } finally {
      setIsJoining(false);
    }

    // Always refresh from server to get authoritative membership state.
    try {
      const fresh = await api.get<GroupDetails>(`/v2/groups/${group.id}`);
      if (fresh.success && fresh.data) {
        setGroup(fresh.data);
        if (isMember(fresh.data) && !feedLoaded) {
          loadGroupFeed();
        }
      }
    } catch {
      // Silent — optimistic state is already set
    }
  }

  async function handleConfirmLeave() {
    if (!group) return;

    try {
      setIsJoining(true);
      const memberCount = getMemberCount(group);
      const response = await api.delete(`/v2/groups/${group.id}/membership`);
      if (response.success) {
        setGroup((prev) => prev ? {
          ...prev,
          is_member: false,
          viewer_membership: prev.viewer_membership ? { ...prev.viewer_membership, status: 'none' } : undefined,
          member_count: memberCount - 1,
          members_count: memberCount - 1,
        } : null);
        toastRef.current.success(tRef.current('toast.left'));
      } else {
        toastRef.current.error(tRef.current('toast.leave_failed'));
      }
    } catch (err) {
      logError('Failed to leave group', err);
      toastRef.current.error(tRef.current('toast.something_wrong'));
    } finally {
      setIsJoining(false);
      setShowLeaveConfirm(false);
    }

    // Refresh from server to get authoritative state
    try {
      const fresh = await api.get<GroupDetails>(`/v2/groups/${group.id}`);
      if (fresh.success && fresh.data) {
        setGroup(fresh.data);
      }
    } catch {
      // Silent — optimistic state is already set
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Create Discussion
  // ─────────────────────────────────────────────────────────────────────────

  async function handleCreateDiscussion() {
    const title = newDiscussionTitle.trim();
    const content = newDiscussionContent.trim();
    if (!id || !title || !content) return;

    try {
      setCreatingDiscussion(true);
      const response = await api.post<Discussion>(`/v2/groups/${id}/discussions`, {
        title,
        content,
      });
      if (response.success && response.data) {
        setDiscussions((prev) => [response.data!, ...prev]);
        setNewDiscussionTitle('');
        setNewDiscussionContent('');
        setShowNewDiscussion(false);
        toastRef.current.success(tRef.current('toast.discussion_created'));
      } else {
        toastRef.current.error(response.error || tRef.current('toast.discussion_failed'));
      }
    } catch (err) {
      logError('Failed to create discussion', err);
      toastRef.current.error(tRef.current('toast.something_wrong'));
    } finally {
      setCreatingDiscussion(false);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Expand Discussion & Load Messages
  // ─────────────────────────────────────────────────────────────────────────

  async function handleExpandDiscussion(discussionId: number) {
    if (expandedDiscussionId === discussionId) {
      setExpandedDiscussionId(null);
      setExpandedDiscussion(null);
      return;
    }

    if (!id) return;

    try {
      setExpandedDiscussionId(discussionId);
      setExpandedLoading(true);
      setExpandedDiscussion(null);
      setReplyContent('');
      const response = await api.get<{ discussion: Discussion; messages: DiscussionMessage[] }>(
        `/v2/groups/${id}/discussions/${discussionId}`
      );
      if (response.success && response.data) {
        const { discussion: disc, messages } = response.data;
        setExpandedDiscussion({ ...disc, messages });
      } else {
        toastRef.current.error(tRef.current('toast.discussion_load_failed'));
        setExpandedDiscussionId(null);
      }
    } catch (err) {
      logError('Failed to load discussion', err);
      toastRef.current.error(tRef.current('toast.discussion_load_failed'));
      setExpandedDiscussionId(null);
    } finally {
      setExpandedLoading(false);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Reply to Discussion
  // ─────────────────────────────────────────────────────────────────────────

  async function handleReply() {
    const content = replyContent.trim();
    if (!id || !expandedDiscussionId || !content) return;

    try {
      setSendingReply(true);
      const response = await api.post<DiscussionMessage>(
        `/v2/groups/${id}/discussions/${expandedDiscussionId}/messages`,
        { content }
      );
      if (response.success && response.data) {
        const newMessage = response.data;
        setExpandedDiscussion((prev) =>
          prev ? { ...prev, messages: [...prev.messages, newMessage] } : null
        );
        setDiscussions((prev) =>
          prev.map((d) =>
            d.id === expandedDiscussionId
              ? { ...d, reply_count: d.reply_count + 1, last_reply_at: newMessage.created_at }
              : d
          )
        );
        setReplyContent('');
        toastRef.current.success(tRef.current('toast.reply_sent'));
      } else {
        toastRef.current.error(response.error || tRef.current('toast.reply_failed'));
      }
    } catch (err) {
      logError('Failed to send reply', err);
      toastRef.current.error(tRef.current('toast.something_wrong'));
    } finally {
      setSendingReply(false);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Admin: Save Settings
  // ─────────────────────────────────────────────────────────────────────────

  function openSettingsModal() {
    if (!group) return;
    setSettingsName(group.name);
    setSettingsDescription(group.description || '');
    setSettingsPrivate(group.visibility === 'private' || group.visibility === 'secret');
    setSettingsLocation(group.location || '');
    setShowSettingsModal(true);
  }

  async function handleImageUpload(e: React.ChangeEvent<HTMLInputElement>, type: 'avatar' | 'cover') {
    const file = e.target.files?.[0];
    if (!file || !id) return;
    setUploadingImage(true);
    try {
      const formData = new FormData();
      formData.append('image', file);
      formData.append('type', type);
      const response = await api.upload(`/v2/groups/${id}/image`, formData);
      if (response.success) {
        const data = response.data as Record<string, unknown> | undefined;
        const url = (data?.url as string) || (data?.image_url as string) || '';
        if (!url) {
          toastRef.current.error(tRef.current('toast.image_upload_failed'));
        } else {
          setGroup((prev) => prev ? {
            ...prev,
            ...(type === 'avatar' ? { image_url: url } : { cover_image_url: url }),
          } : null);
          toastRef.current.success(type === 'avatar' ? tRef.current('toast.image_avatar_updated') : tRef.current('toast.image_cover_updated'));
        }
      } else {
        toastRef.current.error(response.error || tRef.current('toast.image_upload_failed'));
      }
    } catch {
      toastRef.current.error(tRef.current('toast.image_upload_failed'));
    } finally {
      setUploadingImage(false);
      e.target.value = '';
    }
  }

  async function handleSaveSettings() {
    if (!id || !settingsName.trim()) return;

    try {
      setSavingSettings(true);
      const response = await api.put(`/v2/groups/${id}`, {
        name: settingsName.trim(),
        description: settingsDescription.trim(),
        visibility: settingsPrivate ? 'private' : 'public',
        location: settingsLocation.trim(),
      });
      if (response.success) {
        setGroup((prev) => prev ? {
          ...prev,
          name: settingsName.trim(),
          description: settingsDescription.trim(),
          visibility: settingsPrivate ? 'private' : 'public',
          ...(settingsLocation.trim() ? { location: settingsLocation.trim() } : {}),
        } : null);
        setShowSettingsModal(false);
        toastRef.current.success(tRef.current('toast.settings_updated'));
      } else {
        toastRef.current.error(response.error || tRef.current('toast.settings_failed'));
      }
    } catch (err) {
      logError('Failed to update group settings', err);
      toastRef.current.error(tRef.current('toast.something_wrong'));
    } finally {
      setSavingSettings(false);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Admin: Delete Group
  // ─────────────────────────────────────────────────────────────────────────

  async function handleDeleteGroup() {
    if (!id) return;

    try {
      setDeletingGroup(true);
      const response = await api.delete(`/v2/groups/${id}`);
      if (response.success) {
        toastRef.current.success(tRef.current('toast.deleted'));
        navigate(tenantPath('/groups'));
      } else {
        toastRef.current.error(response.error || tRef.current('toast.delete_failed'));
      }
    } catch (err) {
      logError('Failed to delete group', err);
      toastRef.current.error(tRef.current('toast.something_wrong'));
    } finally {
      setDeletingGroup(false);
      setShowDeleteModal(false);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Admin: Handle Join Request
  // ─────────────────────────────────────────────────────────────────────────

  async function handleJoinRequest(userId: number, action: 'accept' | 'reject') {
    if (!id) return;

    try {
      setProcessingRequest(userId);
      const response = await api.post(`/v2/groups/${id}/requests/${userId}`, { action });
      if (response.success) {
        setJoinRequests((prev) => prev.filter((r) => r.user_id !== userId));
        toastRef.current.success(action === 'accept' ? tRef.current('toast.request_accepted') : tRef.current('toast.request_rejected'));
        if (action === 'accept') {
          // Re-fetch the group to get accurate member counts (avoids stale counts from rapid approvals)
          loadGroup();
          if (membersLoaded) {
            setMembersLoaded(false);
          }
        }
      } else {
        toastRef.current.error(response.error || tRef.current('toast.request_failed', { action }));
      }
    } catch (err) {
      logError(`Failed to ${action} join request`, err);
      toastRef.current.error(tRef.current('toast.something_wrong'));
    } finally {
      setProcessingRequest(null);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Admin: Update Member Role
  // ─────────────────────────────────────────────────────────────────────────

  async function handleUpdateMemberRole(userId: number, newRole: 'member' | 'admin') {
    if (!id) return;

    try {
      setUpdatingMember(userId);
      const response = await api.put(`/v2/groups/${id}/members/${userId}`, { role: newRole });
      if (response.success) {
        setMembers((prev) =>
          prev.map((m) => (m.id === userId ? { ...m, role: newRole } : m))
        );
        toastRef.current.success(tRef.current('toast.role_updated', { role: newRole }));
      } else {
        toastRef.current.error(response.error || tRef.current('toast.role_update_failed'));
      }
    } catch (err) {
      logError('Failed to update member role', err);
      toastRef.current.error(tRef.current('toast.something_wrong'));
    } finally {
      setUpdatingMember(null);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Admin: Remove Member
  // ─────────────────────────────────────────────────────────────────────────

  async function handleRemoveMember(userId: number) {
    if (!id) return;

    try {
      setUpdatingMember(userId);
      const response = await api.delete(`/v2/groups/${id}/members/${userId}`);
      if (response.success) {
        setMembers((prev) => prev.filter((m) => m.id !== userId));
        setGroup((prev) => prev ? {
          ...prev,
          member_count: Math.max(0, (prev.member_count ?? prev.members_count ?? 0) - 1),
          members_count: Math.max(0, (prev.member_count ?? prev.members_count ?? 0) - 1),
        } : null);
        toastRef.current.success(tRef.current('toast.member_removed'));
      } else {
        toastRef.current.error(response.error || tRef.current('toast.member_remove_failed'));
      }
    } catch (err) {
      logError('Failed to remove member', err);
      toastRef.current.error(tRef.current('toast.something_wrong'));
    } finally {
      setUpdatingMember(null);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Computed
  // ─────────────────────────────────────────────────────────────────────────

  const userIsMember = group ? isMember(group) : false;
  const userIsAdmin = group ? isGroupAdmin(group) : false;
  const hasSubGroups = group?.sub_groups && group.sub_groups.length > 0;
  const isPrivateGroup = group?.visibility === 'private' || group?.visibility === 'secret';

  // ─────────────────────────────────────────────────────────────────────────
  // Loading / Error States
  // ─────────────────────────────────────────────────────────────────────────

  if (isLoading) {
    return <LoadingScreen message={t('detail.loading')} />;
  }

  if (error || !group) {
    return (
      <div className="max-w-4xl mx-auto">
        <GlassCard className="p-8 text-center">
          <AlertCircle className="w-12 h-12 text-[var(--color-warning)] mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('detail.unable_to_load')}</h2>
          <p className="text-theme-muted mb-4">{error || t('detail.not_found_desc')}</p>
          <div className="flex justify-center gap-3">
            <Link to={tenantPath("/groups")}>
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
              >
                {t('detail.browse_groups')}
              </Button>
            </Link>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={() => loadGroup()}
            >
              {t('detail.try_again')}
            </Button>
          </div>
        </GlassCard>
      </div>
    );
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Render
  // ─────────────────────────────────────────────────────────────────────────

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="max-w-4xl mx-auto space-y-6"
    >
      <PageMeta
        title={group?.name}
        description={group?.description?.substring(0, 160)}
        image={group?.image_url || group?.cover_image_url || undefined}
      />
      <Helmet>
        <script type="application/ld+json">
          {JSON.stringify({
            '@context': 'https://schema.org',
            '@type': 'Organization',
            name: group?.name,
            ...(group?.description ? { description: group.description.substring(0, 300) } : {}),
            ...(group?.image_url || group?.cover_image_url ? { image: group.image_url || group.cover_image_url } : {}),
            ...(group?.member_count ? { numberOfEmployees: { '@type': 'QuantitativeValue', value: group.member_count } } : {}),
          })}
        </script>
      </Helmet>

      {/* Breadcrumbs */}
      <Breadcrumbs items={[
        { label: t('title'), href: '/groups' },
        { label: group?.name || t('title') },
      ]} />

      {/* Group Header */}
      <GroupHeader
        group={group}
        groupTags={groupTags}
        userIsMember={userIsMember}
        userIsAdmin={userIsAdmin}
        isAuthenticated={isAuthenticated}
        isJoining={isJoining}
        isPrivateGroup={isPrivateGroup}
        joinRequests={joinRequests}
        requestsLoading={requestsLoading}
        requestsLoaded={requestsLoaded}
        processingRequest={processingRequest}
        getMemberCount={getMemberCount}
        onJoinLeave={handleJoinLeave}
        onOpenSettings={openSettingsModal}
        onOpenDelete={() => setShowDeleteModal(true)}
        onOpenInvite={() => setShowInviteModal(true)}
        onOpenNotifPrefs={() => setShowNotifPrefs(true)}
        onLoadJoinRequests={loadJoinRequests}
        onJoinRequest={handleJoinRequest}
      />

      {/* Pinned Announcements Banner */}
      <PinnedAnnouncementsBanner groupId={group.id} isMember={userIsMember} />

      {/* Tab Navigation */}
      <GroupTabNav
        activeTab={activeTab}
        userIsAdmin={userIsAdmin}
        hasSubGroups={!!hasSubGroups}
        subGroupCount={group.sub_groups?.length ?? 0}
        onTabChange={setActiveTab}
      />

      {/* Tab Content */}
      <GroupTabContent
        activeTab={activeTab}
        groupId={group.id}
        userIsMember={userIsMember}
        userIsAdmin={userIsAdmin}
        isJoining={isJoining}
        currentUserId={currentUser?.id}
        groupOwnerId={group.owner?.id}
        groupAdminIds={group.admins?.map((a) => a.id)}
        hasSubGroups={!!hasSubGroups}
        subGroups={group.sub_groups}
        feedItems={feedItems}
        feedLoading={feedLoading}
        feedHasMore={feedHasMore}
        feedLoadingMore={feedLoadingMore}
        onComposeOpen={onComposeOpen}
        onLoadMoreFeed={() => loadGroupFeed(true)}
        onRefreshFeed={() => { feedCursorRef.current = undefined; return loadGroupFeed(false); }}
        onToggleLike={handleFeedToggleLike}
        onReact={handleFeedReact}
        onHidePost={handleFeedHidePost}
        onMuteUser={handleFeedMuteUser}
        onReportPost={openFeedReportModal}
        onDeletePost={handleFeedDeletePost}
        onVotePoll={handleFeedVotePoll}
        discussions={discussions}
        discussionsLoading={discussionsLoading}
        discussionsHasMore={discussionsHasMore}
        expandedDiscussionId={expandedDiscussionId}
        expandedDiscussion={expandedDiscussion}
        expandedLoading={expandedLoading}
        replyContent={replyContent}
        sendingReply={sendingReply}
        onShowNewDiscussion={() => setShowNewDiscussion(true)}
        onExpandDiscussion={handleExpandDiscussion}
        onLoadMoreDiscussions={() => loadDiscussions(true)}
        onReplyContentChange={setReplyContent}
        onSendReply={handleReply}
        members={members}
        membersLoading={membersLoading}
        updatingMember={updatingMember}
        onUpdateMemberRole={handleUpdateMemberRole}
        onRemoveMember={handleRemoveMember}
        events={events}
        eventsLoading={eventsLoading}
        onJoinLeave={handleJoinLeave}
      />

      {/* ─── New Discussion Modal ─── */}
      <NewDiscussionModal
        isOpen={showNewDiscussion}
        onOpenChange={setShowNewDiscussion}
        newDiscussionTitle={newDiscussionTitle}
        newDiscussionContent={newDiscussionContent}
        creatingDiscussion={creatingDiscussion}
        onTitleChange={setNewDiscussionTitle}
        onContentChange={setNewDiscussionContent}
        onSubmit={handleCreateDiscussion}
      />

      {/* ─── Settings Modal ─── */}
      <GroupSettingsModal
        isOpen={showSettingsModal}
        onOpenChange={setShowSettingsModal}
        group={group}
        settingsName={settingsName}
        settingsDescription={settingsDescription}
        settingsPrivate={settingsPrivate}
        settingsLocation={settingsLocation}
        uploadingImage={uploadingImage}
        savingSettings={savingSettings}
        onNameChange={setSettingsName}
        onDescriptionChange={setSettingsDescription}
        onPrivateChange={setSettingsPrivate}
        onLocationChange={setSettingsLocation}
        onImageUpload={handleImageUpload}
        onSave={handleSaveSettings}
      />

      {/* ─── Leave Group Confirmation Modal ─── */}
      <GroupLeaveModal
        isOpen={showLeaveConfirm}
        onOpenChange={setShowLeaveConfirm}
        groupName={group.name}
        isLoading={isJoining}
        onConfirm={handleConfirmLeave}
      />

      {/* ─── Delete Confirmation Modal ─── */}
      <GroupDeleteModal
        isOpen={showDeleteModal}
        onOpenChange={setShowDeleteModal}
        groupName={group.name}
        isLoading={deletingGroup}
        onConfirm={handleDeleteGroup}
      />

      {/* ─── Compose Hub (Group Feed) ─── */}
      <ComposeHub
        isOpen={isComposeOpen}
        onClose={onComposeClose}
        groupId={group?.id ? Number(group.id) : undefined}
        onSuccess={() => { feedCursorRef.current = undefined; setFeedLoaded(false); loadGroupFeed(); }}
      />

      {/* ─── Report Post Modal ─── */}
      <GroupReportModal
        isOpen={isReportOpen}
        onClose={onReportClose}
        reportReason={reportReason}
        isReporting={isReporting}
        onReasonChange={setReportReason}
        onSubmit={handleFeedReport}
      />

      {/* ─── Notification Preferences Modal ─── */}
      {showNotifPrefs && group && (
        <GroupNotificationPrefs
          groupId={group.id}
          isOpen={showNotifPrefs}
          onClose={() => setShowNotifPrefs(false)}
        />
      )}

      {/* ─── Invite Modal ─── */}
      <GroupInviteModal
        isOpen={showInviteModal}
        onOpenChange={setShowInviteModal}
        inviteLink={inviteLink}
        inviteEmails={inviteEmails}
        inviteMessage={inviteMessage}
        sendingInvites={sendingInvites}
        onGenerateLink={handleGenerateInviteLink}
        onEmailsChange={setInviteEmails}
        onMessageChange={setInviteMessage}
        onSendInvites={handleSendInvites}
        onCopyLink={(link) => { navigator.clipboard.writeText(link); toast.success(t('detail.link_copied', 'Link copied!')); }}
      />
    </motion.div>
  );
}

export default GroupDetailPage;
