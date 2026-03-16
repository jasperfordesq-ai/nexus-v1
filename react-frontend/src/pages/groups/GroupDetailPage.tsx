// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Detail Page - Single group view
 * Full discussions UI, admin features, events tab, member management
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Avatar,
  Tabs,
  Tab,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  Switch,
  Spinner,
  useDisclosure,
} from '@heroui/react';
import {
  Users,
  MessageSquare,
  Settings,
  Lock,
  Globe,
  UserPlus,
  UserMinus,
  Calendar,
  AlertCircle,
  FolderTree,
  RefreshCw,
  ArrowLeft,
  Trash2,
  MapPin,
  CheckCircle,
  XCircle,
  FileText,
  Upload,
  Image,
  Newspaper,
  Flag,
  FolderOpen,
  Megaphone,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { ComposeHub } from '@/components/compose';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { LocationMapCard } from '@/components/location';
import { useTranslation } from 'react-i18next';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, formatRelativeTime } from '@/lib/helpers';
import type { FeedItem } from '@/components/feed/types';
import type { Group, User, FeedPost, Event } from '@/types/api';

// Tab components
import { GroupFeedTab } from './tabs/GroupFeedTab';
import { GroupDiscussionTab } from './tabs/GroupDiscussionTab';
import type { Discussion, DiscussionDetail, DiscussionMessage } from './tabs/GroupDiscussionTab';
import { GroupMembersTab } from './tabs/GroupMembersTab';
import type { GroupMember } from './tabs/GroupMembersTab';
import { GroupEventsTab } from './tabs/GroupEventsTab';
import { GroupFilesTab } from './tabs/GroupFilesTab';
import { GroupAnnouncementsTab } from './tabs/GroupAnnouncementsTab';
import { GroupChatroomsTab } from './tabs/GroupChatroomsTab';
import { GroupTasksTab } from './tabs/GroupTasksTab';
import { GroupSubgroupsTab } from './tabs/GroupSubgroupsTab';
import { PinnedAnnouncementsBanner } from './components/PinnedAnnouncementsBanner';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface GroupDetails extends Group {
  members?: User[];
  recent_posts?: FeedPost[];
}

interface JoinRequest {
  user_id: number;
  user: {
    id: number;
    name: string;
    avatar?: string | null;
  };
  created_at: string;
  message?: string;
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

    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<GroupDetails>(`/v2/groups/${id}`);
      if (response.success && response.data) {
        setGroup(response.data);
        if (response.data.sub_groups && response.data.sub_groups.length > 0) {
          setActiveTab('subgroups');
        }
      } else {
        setError(t('detail.not_found_desc'));
      }
    } catch (err) {
      logError('Failed to load group', err);
      setError(t('detail.error_load_failed'));
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadGroup();
  }, [loadGroup]);

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
        toast.error(t('toast.feed_load_failed', 'Failed to load feed'));
      }
    } finally {
      setFeedLoading(false);
      setFeedLoadingMore(false);
      setFeedLoaded(true);
    }
  }, [id, toast, t]);

  useEffect(() => {
    if (activeTab === 'feed' && !feedLoaded && group && isMember(group)) {
      loadGroupFeed();
    }
  }, [activeTab, feedLoaded, group]); // eslint-disable-line react-hooks/exhaustive-deps

  // ─────────────────────────────────────────────────────────────────────────
  // Feed Actions (like, hide, mute, report, delete, poll vote)
  // ─────────────────────────────────────────────────────────────────────────

  const handleFeedToggleLike = async (item: FeedItem) => {
    setFeedItems((prev) =>
      prev.map((fi) =>
        fi.id === item.id && fi.type === item.type
          ? { ...fi, is_liked: !fi.is_liked, likes_count: fi.is_liked ? fi.likes_count - 1 : fi.likes_count + 1 }
          : fi
      )
    );
    try {
      await api.post('/v2/feed/like', { target_type: item.type, target_id: item.id });
    } catch (err) {
      logError('Failed to toggle like', err);
      setFeedItems((prev) =>
        prev.map((fi) =>
          fi.id === item.id && fi.type === item.type
            ? { ...fi, is_liked: !fi.is_liked, likes_count: fi.is_liked ? fi.likes_count - 1 : fi.likes_count + 1 }
            : fi
        )
      );
    }
  };

  const handleFeedHidePost = async (postId: number) => {
    try {
      await api.post(`/v2/feed/posts/${postId}/hide`);
      setFeedItems((prev) => prev.filter((fi) => !(fi.id === postId && fi.type === 'post')));
      toast.success(t('toast.post_hidden', 'Post hidden'));
    } catch (err) {
      logError('Failed to hide post', err);
      toast.error(t('toast.hide_failed', 'Failed to hide post'));
    }
  };

  const handleFeedMuteUser = async (userId: number) => {
    try {
      await api.post(`/v2/feed/users/${userId}/mute`);
      setFeedItems((prev) => prev.filter((fi) => {
        const author = fi.author ?? (fi as unknown as Record<string, unknown>).user as FeedItem['author'];
        return !author || author.id !== userId;
      }));
      toast.success(t('toast.user_muted', 'User muted'));
    } catch (err) {
      logError('Failed to mute user', err);
      toast.error(t('toast.mute_failed', 'Failed to mute user'));
    }
  };

  const openFeedReportModal = (postId: number) => {
    setReportPostId(postId);
    setReportReason('');
    onReportOpen();
  };

  const handleFeedReport = async () => {
    if (!reportPostId || !reportReason.trim()) {
      toast.error(t('toast.provide_reason', 'Please provide a reason'));
      return;
    }
    try {
      setIsReporting(true);
      await api.post(`/v2/feed/posts/${reportPostId}/report`, { reason: reportReason.trim() });
      onReportClose();
      setReportPostId(null);
      setReportReason('');
      toast.success(t('toast.reported', 'Post reported'));
    } catch (err) {
      logError('Failed to report post', err);
      toast.error(t('toast.report_failed', 'Failed to report post'));
    } finally {
      setIsReporting(false);
    }
  };

  const handleFeedDeletePost = async (item: FeedItem) => {
    try {
      await api.post(`/v2/feed/posts/${item.id}/delete`);
      setFeedItems((prev) => prev.filter((fi) => !(fi.id === item.id && fi.type === item.type)));
      toast.success(t('toast.deleted', 'Post deleted'));
    } catch (err) {
      logError('Failed to delete post', err);
      toast.error(t('toast.delete_failed', 'Failed to delete post'));
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
      toast.error(t('toast.vote_failed', 'Failed to vote'));
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
        toast.error(t('toast.discussions_load_failed'));
      }
    } finally {
      setDiscussionsLoading(false);
      setDiscussionsLoaded(true);
    }
  }, [id, discussionsCursor, discussionsLoading, toast]);

  // Load discussions when tab changes to discussion
  useEffect(() => {
    if (activeTab === 'discussion' && !discussionsLoaded && group && isMember(group)) {
      loadDiscussions();
    }
  }, [activeTab, discussionsLoaded, group]); // eslint-disable-line react-hooks/exhaustive-deps

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
      const response = await api.post(`/v2/groups/${group.id}/join`);
      if (response.success) {
        setGroup((prev) => prev ? {
          ...prev,
          is_member: true,
          viewer_membership: prev.viewer_membership ? { ...prev.viewer_membership, status: 'active' } : { status: 'active', role: 'member', is_admin: false },
          member_count: memberCount + 1,
          members_count: memberCount + 1,
        } : null);
        toast.success(t('toast.joined'));
      } else {
        toast.error(t('toast.join_failed'));
      }
    } catch (err) {
      logError('Failed to join group', err);
      toast.error(t('toast.something_wrong'));
    } finally {
      setIsJoining(false);
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
        toast.success(t('toast.left'));
      } else {
        toast.error(t('toast.leave_failed'));
      }
    } catch (err) {
      logError('Failed to leave group', err);
      toast.error(t('toast.something_wrong'));
    } finally {
      setIsJoining(false);
      setShowLeaveConfirm(false);
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
        toast.success(t('toast.discussion_created'));
      } else {
        toast.error(response.error || t('toast.discussion_failed'));
      }
    } catch (err) {
      logError('Failed to create discussion', err);
      toast.error(t('toast.something_wrong'));
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
        toast.error(t('toast.discussion_load_failed'));
        setExpandedDiscussionId(null);
      }
    } catch (err) {
      logError('Failed to load discussion', err);
      toast.error(t('toast.discussion_load_failed'));
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
        toast.success(t('toast.reply_sent'));
      } else {
        toast.error(response.error || t('toast.reply_failed'));
      }
    } catch (err) {
      logError('Failed to send reply', err);
      toast.error(t('toast.something_wrong'));
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
        setGroup((prev) => prev ? {
          ...prev,
          ...(type === 'avatar' ? { image_url: url } : { cover_image_url: url }),
        } : null);
        toast.success(type === 'avatar' ? t('toast.image_avatar_updated') : t('toast.image_cover_updated'));
      } else {
        toast.error(response.error || t('toast.image_upload_failed'));
      }
    } catch {
      toast.error(t('toast.image_upload_failed'));
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
        toast.success(t('toast.settings_updated'));
      } else {
        toast.error(response.error || t('toast.settings_failed'));
      }
    } catch (err) {
      logError('Failed to update group settings', err);
      toast.error(t('toast.something_wrong'));
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
        toast.success(t('toast.deleted'));
        navigate(tenantPath('/groups'));
      } else {
        toast.error(response.error || t('toast.delete_failed'));
      }
    } catch (err) {
      logError('Failed to delete group', err);
      toast.error(t('toast.something_wrong'));
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
        toast.success(action === 'accept' ? t('toast.request_accepted') : t('toast.request_rejected'));
        if (action === 'accept') {
          setGroup((prev) => prev ? {
            ...prev,
            member_count: (prev.member_count ?? prev.members_count ?? 0) + 1,
            members_count: (prev.member_count ?? prev.members_count ?? 0) + 1,
          } : null);
          if (membersLoaded) {
            setMembersLoaded(false);
          }
        }
      } else {
        toast.error(response.error || t('toast.request_failed', { action }));
      }
    } catch (err) {
      logError(`Failed to ${action} join request`, err);
      toast.error(t('toast.something_wrong'));
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
        toast.success(t('toast.role_updated', { role: newRole }));
      } else {
        toast.error(response.error || t('toast.role_update_failed'));
      }
    } catch (err) {
      logError('Failed to update member role', err);
      toast.error(t('toast.something_wrong'));
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
        toast.success(t('toast.member_removed'));
      } else {
        toast.error(response.error || t('toast.member_remove_failed'));
      }
    } catch (err) {
      logError('Failed to remove member', err);
      toast.error(t('toast.something_wrong'));
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
          <AlertCircle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
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
      {/* Breadcrumbs */}
      <Breadcrumbs items={[
        { label: t('title'), href: tenantPath('/groups') },
        { label: group?.name || t('title') },
      ]} />

      {/* Group Header */}
      <GlassCard className="p-6 sm:p-8">
        <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
          <div className="flex items-center gap-4">
            <div className="p-4 rounded-2xl bg-gradient-to-br from-purple-500/20 to-indigo-500/20">
              <Users className="w-8 h-8 text-purple-400" aria-hidden="true" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-2xl font-bold text-theme-primary">{group.name}</h1>
                {group.visibility === 'private' ? (
                  <Lock className="w-5 h-5 text-amber-400" aria-hidden="true" />
                ) : (
                  <Globe className="w-5 h-5 text-emerald-400" aria-hidden="true" />
                )}
              </div>
              <p className="text-theme-muted text-sm mt-1">
                {t('detail.members_count', { count: getMemberCount(group) })}
                {group.location && (
                  <>
                    {' '}<span aria-hidden="true">&#183;</span>{' '}
                    <MapPin className="w-3.5 h-3.5 inline" aria-hidden="true" /> {group.location}
                  </>
                )}
                {' '}<span aria-hidden="true">&#183;</span>{' '}{t('detail.created')}{' '}
                <time dateTime={group.created_at}>{new Date(group.created_at).toLocaleDateString()}</time>
              </p>
            </div>
          </div>

          <div className="flex gap-2 flex-wrap">
            {userIsAdmin && (
              <>
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                  startContent={<Settings className="w-4 h-4" aria-hidden="true" />}
                  onPress={openSettingsModal}
                >
                  {t('detail.settings')}
                </Button>
                <Button
                  variant="flat"
                  className="bg-red-500/10 text-red-500 hover:bg-red-500/20"
                  startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                  onPress={() => setShowDeleteModal(true)}
                >
                  {t('detail.delete')}
                </Button>
              </>
            )}
            {isAuthenticated && (
              <Button
                className={userIsMember
                  ? 'bg-theme-hover text-theme-primary'
                  : 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white'
                }
                startContent={userIsMember ? <UserMinus className="w-4 h-4" aria-hidden="true" /> : <UserPlus className="w-4 h-4" aria-hidden="true" />}
                onPress={handleJoinLeave}
                isLoading={isJoining}
              >
                {userIsMember ? t('detail.leave_group') : t('detail.join_group')}
              </Button>
            )}
          </div>
        </div>

        {/* Description */}
        <p className="text-theme-muted mb-6">
          {group.description || t('detail.no_description')}
        </p>

        {/* Quick Stats */}
        <div className="flex flex-wrap gap-3 sm:gap-6">
          <div className="flex items-center gap-2 text-theme-muted">
            <Users className="w-5 h-5" aria-hidden="true" />
            <span>{t('detail.members_count', { count: getMemberCount(group) })}</span>
          </div>
          {group.posts_count !== undefined && (
            <div className="flex items-center gap-2 text-theme-muted">
              <MessageSquare className="w-5 h-5" aria-hidden="true" />
              <span>{t('detail.posts_count', { count: group.posts_count })}</span>
            </div>
          )}
          <div className="flex items-center gap-2 text-theme-muted">
            <Calendar className="w-5 h-5" aria-hidden="true" />
            <span>{t('detail.created')} <time dateTime={group.created_at}>{new Date(group.created_at).toLocaleDateString()}</time></span>
          </div>
        </div>

        {/* Location Map */}
        {group.location && group.latitude && group.longitude && (
          <LocationMapCard
            title={t('detail.location_title')}
            locationText={group.location}
            markers={[{
              id: group.id,
              lat: Number(group.latitude),
              lng: Number(group.longitude),
              title: group.name,
            }]}
            center={{ lat: Number(group.latitude), lng: Number(group.longitude) }}
            mapHeight="250px"
            zoom={14}
            className="mt-6"
          />
        )}

        {/* Admin: Pending Requests Banner */}
        {userIsAdmin && isPrivateGroup && !requestsLoaded && (
          <div className="mt-4 pt-4 border-t border-theme-default">
            <Button
              variant="flat"
              size="sm"
              className="bg-amber-500/10 text-amber-600 dark:text-amber-400"
              startContent={<UserPlus className="w-4 h-4" aria-hidden="true" />}
              onPress={() => { loadJoinRequests(); }}
            >
              {t('detail.view_pending_requests')}
            </Button>
          </div>
        )}

        {/* Admin: Pending Requests Section */}
        {userIsAdmin && requestsLoaded && (
          <div className="mt-4 pt-4 border-t border-theme-default">
            <h3 className="text-sm font-semibold text-theme-primary mb-3 flex items-center gap-2">
              <UserPlus className="w-4 h-4" aria-hidden="true" />
              {t('detail.pending_requests_title')} ({joinRequests.length})
            </h3>
            {requestsLoading ? (
              <div className="flex justify-center py-4">
                <Spinner size="sm" />
              </div>
            ) : joinRequests.length === 0 ? (
              <p className="text-sm text-theme-subtle">{t('detail.no_pending_requests')}</p>
            ) : (
              <div className="space-y-2">
                {joinRequests.map((request) => (
                  <div key={request.user_id} className="flex items-center justify-between p-3 rounded-lg bg-theme-elevated">
                    <div className="flex items-center gap-3">
                      <Avatar
                        src={resolveAvatarUrl(request.user.avatar)}
                        name={request.user.name}
                        size="sm"
                      />
                      <div>
                        <p className="text-sm font-medium text-theme-primary">{request.user.name}</p>
                        <time className="text-xs text-theme-subtle" dateTime={request.created_at}>
                          {formatRelativeTime(request.created_at)}
                        </time>
                      </div>
                    </div>
                    <div className="flex gap-2">
                      <Button
                        size="sm"
                        className="bg-emerald-500/20 text-emerald-600 dark:text-emerald-400"
                        startContent={<CheckCircle className="w-3.5 h-3.5" aria-hidden="true" />}
                        isLoading={processingRequest === request.user_id}
                        onPress={() => handleJoinRequest(request.user_id, 'accept')}
                      >
                        {t('detail.accept')}
                      </Button>
                      <Button
                        size="sm"
                        variant="flat"
                        className="bg-red-500/10 text-red-500"
                        startContent={<XCircle className="w-3.5 h-3.5" aria-hidden="true" />}
                        isLoading={processingRequest === request.user_id}
                        onPress={() => handleJoinRequest(request.user_id, 'reject')}
                      >
                        {t('detail.reject')}
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}
      </GlassCard>

      {/* Pinned Announcements Banner (GR3) */}
      <PinnedAnnouncementsBanner groupId={group.id} />

      {/* Tabs */}
      <Tabs
        selectedKey={activeTab}
        onSelectionChange={(key) => setActiveTab(key as string)}
        classNames={{
          tabList: 'bg-theme-elevated p-1 rounded-lg',
          cursor: 'bg-theme-hover',
          tab: 'text-theme-muted data-[selected=true]:text-theme-primary',
        }}
      >
        <Tab
          key="feed"
          title={
            <span className="flex items-center gap-2">
              <Newspaper className="w-4 h-4" aria-hidden="true" />
              {t('detail.tab_feed', 'Feed')}
            </span>
          }
        />
        <Tab
          key="discussion"
          title={
            <span className="flex items-center gap-2">
              <MessageSquare className="w-4 h-4" aria-hidden="true" />
              {t('detail.tab_discussion')}
            </span>
          }
        />
        <Tab
          key="members"
          title={
            <span className="flex items-center gap-2">
              <Users className="w-4 h-4" aria-hidden="true" />
              {t('detail.tab_members')}
            </span>
          }
        />
        <Tab
          key="events"
          title={
            <span className="flex items-center gap-2">
              <Calendar className="w-4 h-4" aria-hidden="true" />
              {t('detail.tab_events')}
            </span>
          }
        />
        <Tab
          key="files"
          title={
            <span className="flex items-center gap-2">
              <FolderOpen className="w-4 h-4" aria-hidden="true" />
              {t('detail.tab_files', 'Files')}
            </span>
          }
        />
        <Tab
          key="announcements"
          title={
            <span className="flex items-center gap-2">
              <Megaphone className="w-4 h-4" aria-hidden="true" />
              {t('detail.tab_announcements', 'Announcements')}
            </span>
          }
        />
        <Tab
          key="chatrooms"
          title={
            <span className="flex items-center gap-2">
              <MessageSquare className="w-4 h-4" aria-hidden="true" />
              {t('detail.tab_channels', 'Channels')}
            </span>
          }
        />
        <Tab
          key="tasks"
          title={
            <span className="flex items-center gap-2">
              <CheckCircle className="w-4 h-4" aria-hidden="true" />
              {t('detail.tab_tasks', 'Tasks')}
            </span>
          }
        />
        {hasSubGroups && (
          <Tab
            key="subgroups"
            title={
              <span className="flex items-center gap-2">
                <FolderTree className="w-4 h-4" aria-hidden="true" />
                {t('detail.tab_subgroups')} ({group.sub_groups?.length})
              </span>
            }
          />
        )}
      </Tabs>

      {/* Tab Content */}
      <div>
        {activeTab === 'feed' && (
          <GroupFeedTab
            isMember={userIsMember}
            isJoining={isJoining}
            feedItems={feedItems}
            feedLoading={feedLoading}
            feedHasMore={feedHasMore}
            feedLoadingMore={feedLoadingMore}
            onJoinLeave={handleJoinLeave}
            onComposeOpen={onComposeOpen}
            onLoadMore={() => loadGroupFeed(true)}
            onToggleLike={handleFeedToggleLike}
            onHidePost={handleFeedHidePost}
            onMuteUser={handleFeedMuteUser}
            onReportPost={openFeedReportModal}
            onDeletePost={handleFeedDeletePost}
            onVotePoll={handleFeedVotePoll}
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
            onJoinLeave={handleJoinLeave}
            onShowNewDiscussion={() => setShowNewDiscussion(true)}
            onExpandDiscussion={handleExpandDiscussion}
            onLoadMoreDiscussions={() => loadDiscussions(true)}
            onReplyContentChange={setReplyContent}
            onSendReply={handleReply}
          />
        )}

        {activeTab === 'members' && (
          <GroupMembersTab
            members={members}
            membersLoading={membersLoading}
            userIsAdmin={userIsAdmin}
            currentUserId={currentUser?.id}
            groupOwnerId={group.owner?.id}
            groupAdminIds={group.admins?.map((a) => a.id)}
            updatingMember={updatingMember}
            onUpdateMemberRole={handleUpdateMemberRole}
            onRemoveMember={handleRemoveMember}
          />
        )}

        {activeTab === 'events' && (
          <GroupEventsTab
            groupId={group.id}
            events={events}
            eventsLoading={eventsLoading}
            isMember={userIsMember}
          />
        )}

        {activeTab === 'files' && (
          <GroupFilesTab
            groupId={group.id}
            isAdmin={userIsAdmin}
            isMember={userIsMember}
          />
        )}

        {activeTab === 'announcements' && (
          <GroupAnnouncementsTab
            groupId={group.id}
            isAdmin={userIsAdmin}
            isMember={userIsMember}
          />
        )}

        {activeTab === 'chatrooms' && (userIsMember ? (
          <GroupChatroomsTab
            groupId={group.id}
            isGroupAdmin={userIsAdmin}
          />
        ) : (
          <GlassCard className="p-6">
            <EmptyState
              icon={<Lock className="w-12 h-12" aria-hidden="true" />}
              title={t('detail.join_to_access_title', 'Members Only')}
              description={t('detail.join_to_access_desc', 'Join this group to access this feature.')}
              action={
                isAuthenticated && (
                  <Button
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    onPress={handleJoinLeave}
                    isLoading={isJoining}
                  >
                    {t('detail.join_group')}
                  </Button>
                )
              }
            />
          </GlassCard>
        ))}
        {activeTab === 'tasks' && (userIsMember ? (
          <GroupTasksTab
            groupId={group.id}
            isGroupAdmin={userIsAdmin}
            members={members}
          />
        ) : (
          <GlassCard className="p-6">
            <EmptyState
              icon={<Lock className="w-12 h-12" aria-hidden="true" />}
              title={t('detail.join_to_access_title', 'Members Only')}
              description={t('detail.join_to_access_desc', 'Join this group to access this feature.')}
              action={
                isAuthenticated && (
                  <Button
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    onPress={handleJoinLeave}
                    isLoading={isJoining}
                  >
                    {t('detail.join_group')}
                  </Button>
                )
              }
            />
          </GlassCard>
        ))}
        {activeTab === 'subgroups' && hasSubGroups && (
          <GroupSubgroupsTab subGroups={group.sub_groups!} />
        )}
      </div>

      {/* ─── New Discussion Modal ─── */}
      <Modal
        isOpen={showNewDiscussion}
        onOpenChange={setShowNewDiscussion}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="text-theme-primary flex items-center gap-2">
                <MessageSquare className="w-5 h-5 text-purple-400" aria-hidden="true" />
                {t('detail.new_discussion_modal_title')}
              </ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label={t('detail.discussion_title_label')}
                  placeholder={t('detail.discussion_title_placeholder')}
                  value={newDiscussionTitle}
                  onChange={(e) => setNewDiscussionTitle(e.target.value)}
                  startContent={<FileText className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                    label: 'text-theme-muted',
                  }}
                />
                <Textarea
                  label={t('detail.discussion_content_label')}
                  placeholder={t('detail.discussion_content_placeholder')}
                  value={newDiscussionContent}
                  onChange={(e) => setNewDiscussionContent(e.target.value)}
                  minRows={4}
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                    label: 'text-theme-muted',
                  }}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={onClose}>
                  {t('detail.cancel')}
                </Button>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  isLoading={creatingDiscussion}
                  isDisabled={!newDiscussionTitle.trim() || !newDiscussionContent.trim()}
                  onPress={handleCreateDiscussion}
                >
                  {t('detail.create_discussion_btn')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* ─── Settings Modal ─── */}
      <Modal
        isOpen={showSettingsModal}
        onOpenChange={setShowSettingsModal}
        size="lg"
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="text-theme-primary flex items-center gap-2">
                <Settings className="w-5 h-5 text-purple-400" aria-hidden="true" />
                {t('detail.settings_modal_title')}
              </ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label={t('detail.settings_name_label')}
                  placeholder={t('detail.settings_name_placeholder')}
                  value={settingsName}
                  onChange={(e) => setSettingsName(e.target.value)}
                  startContent={<FileText className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                    label: 'text-theme-muted',
                  }}
                />
                <Textarea
                  label={t('detail.settings_desc_label')}
                  placeholder={t('detail.settings_desc_placeholder')}
                  value={settingsDescription}
                  onChange={(e) => setSettingsDescription(e.target.value)}
                  minRows={3}
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                    label: 'text-theme-muted',
                  }}
                />
                <Input
                  label={t('detail.settings_location_label')}
                  placeholder={t('detail.settings_location_placeholder')}
                  value={settingsLocation}
                  onChange={(e) => setSettingsLocation(e.target.value)}
                  startContent={<MapPin className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                    label: 'text-theme-muted',
                  }}
                />
                {/* Images */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div className="p-3 rounded-lg bg-theme-elevated border border-theme-default">
                    <p className="text-sm font-medium text-theme-primary mb-2 flex items-center gap-1.5">
                      <Image className="w-4 h-4" aria-hidden="true" />
                      {t('detail.settings_image_label')}
                    </p>
                    {group?.image_url && (
                      <img src={group.image_url} alt="Group" className="w-12 h-12 rounded-full object-cover mb-2" width={48} height={48} loading="lazy" />
                    )}
                    <label className="flex items-center gap-1.5 text-xs text-primary cursor-pointer hover:underline">
                      <Upload className="w-3 h-3" aria-hidden="true" />
                      {uploadingImage ? t('detail.uploading') : t('detail.upload_image')}
                      <input
                        type="file"
                        accept="image/*"
                        className="hidden"
                        disabled={uploadingImage}
                        onChange={(e) => handleImageUpload(e, 'avatar')}
                      />
                    </label>
                  </div>
                  <div className="p-3 rounded-lg bg-theme-elevated border border-theme-default">
                    <p className="text-sm font-medium text-theme-primary mb-2 flex items-center gap-1.5">
                      <Image className="w-4 h-4" aria-hidden="true" />
                      {t('detail.settings_cover_label')}
                    </p>
                    {group?.cover_image_url && (
                      <img src={group.cover_image_url} alt="Cover" className="w-full h-10 rounded object-cover mb-2" width={400} height={40} loading="lazy" />
                    )}
                    <label className="flex items-center gap-1.5 text-xs text-primary cursor-pointer hover:underline">
                      <Upload className="w-3 h-3" aria-hidden="true" />
                      {uploadingImage ? t('detail.uploading') : t('detail.upload_cover')}
                      <input
                        type="file"
                        accept="image/*"
                        className="hidden"
                        disabled={uploadingImage}
                        onChange={(e) => handleImageUpload(e, 'cover')}
                      />
                    </label>
                  </div>
                </div>
                <div className="p-4 rounded-lg bg-theme-elevated border border-theme-default">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      {settingsPrivate ? (
                        <Lock className="w-5 h-5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                      ) : (
                        <Globe className="w-5 h-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                      )}
                      <div>
                        <p className="font-medium text-theme-primary">
                          {settingsPrivate ? t('detail.private_group') : t('detail.public_group')}
                        </p>
                        <p className="text-sm text-theme-subtle">
                          {settingsPrivate
                            ? t('detail.private_desc')
                            : t('detail.public_desc')}
                        </p>
                      </div>
                    </div>
                    <Switch
                      aria-label={settingsPrivate ? t('detail.make_public_aria') : t('detail.make_private_aria')}
                      isSelected={settingsPrivate}
                      onValueChange={setSettingsPrivate}
                      classNames={{
                        wrapper: 'group-data-[selected=true]:bg-amber-500',
                      }}
                    />
                  </div>
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={onClose}>
                  {t('detail.cancel')}
                </Button>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  isLoading={savingSettings}
                  isDisabled={!settingsName.trim()}
                  onPress={handleSaveSettings}
                >
                  {t('detail.save_changes')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* ─── Leave Group Confirmation Modal ─── */}
      <Modal
        isOpen={showLeaveConfirm}
        onOpenChange={setShowLeaveConfirm}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="text-theme-primary flex items-center gap-2">
                <UserMinus className="w-5 h-5" aria-hidden="true" />
                {t('detail.leave_group_title', 'Leave Group')}
              </ModalHeader>
              <ModalBody>
                <p className="text-theme-secondary">
                  {t('detail.leave_group_confirm', 'Are you sure you want to leave {{name}}? You will lose access to group discussions and files.', { name: group?.name })}
                </p>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={onClose}>
                  {t('detail.cancel')}
                </Button>
                <Button
                  color="danger"
                  isLoading={isJoining}
                  onPress={handleConfirmLeave}
                >
                  {t('detail.leave_group')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* ─── Delete Confirmation Modal ─── */}
      <Modal
        isOpen={showDeleteModal}
        onOpenChange={setShowDeleteModal}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="text-red-500 flex items-center gap-2">
                <Trash2 className="w-5 h-5" aria-hidden="true" />
                {t('detail.delete_modal_title')}
              </ModalHeader>
              <ModalBody>
                <div className="text-center py-4">
                  <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-red-500/10 flex items-center justify-center">
                    <AlertCircle className="w-8 h-8 text-red-500" aria-hidden="true" />
                  </div>
                  <p className="text-theme-primary font-medium mb-2">
                    {t('detail.delete_confirm', { name: group.name })}
                  </p>
                  <p className="text-sm text-theme-muted">
                    {t('detail.delete_desc')}
                  </p>
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={onClose}>
                  {t('detail.cancel')}
                </Button>
                <Button
                  className="bg-red-500 text-white"
                  isLoading={deletingGroup}
                  onPress={handleDeleteGroup}
                  startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('detail.delete_btn')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* ─── Compose Hub (Group Feed) ─── */}
      <ComposeHub
        isOpen={isComposeOpen}
        onClose={onComposeClose}
        groupId={group?.id ? Number(group.id) : undefined}
        onSuccess={() => { feedCursorRef.current = undefined; setFeedLoaded(false); loadGroupFeed(); }}
      />

      {/* ─── Report Post Modal ─── */}
      <Modal
        isOpen={isReportOpen}
        onClose={onReportClose}
        classNames={{
          base: 'bg-[var(--glass-bg)] backdrop-blur-xl border border-[var(--glass-border)]',
          backdrop: 'bg-black/60 backdrop-blur-sm',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-[var(--text-primary)]">
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-lg bg-danger/10 flex items-center justify-center">
                <Flag className="w-4 h-4 text-danger" aria-hidden="true" />
              </div>
              {t('detail.report_title', 'Report Post')}
            </div>
          </ModalHeader>
          <ModalBody>
            <p className="text-sm text-[var(--text-muted)] mb-3">
              {t('detail.report_description', 'Help us understand why you are reporting this post.')}
            </p>
            <Textarea
              label={t('detail.report_reason_label', 'Reason')}
              placeholder={t('detail.report_reason_placeholder', 'Describe why this post is inappropriate...')}
              value={reportReason}
              onChange={(e) => setReportReason(e.target.value)}
              minRows={3}
              classNames={{
                input: 'bg-transparent text-[var(--text-primary)]',
                inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)]',
              }}
              autoFocus
            />
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={onReportClose}
              className="text-[var(--text-muted)]"
            >
              {t('detail.cancel')}
            </Button>
            <Button
              color="danger"
              variant="flat"
              onPress={handleFeedReport}
              isLoading={isReporting}
              isDisabled={!reportReason.trim()}
              className="font-medium"
            >
              {t('detail.report_submit', 'Report')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </motion.div>
  );
}

export default GroupDetailPage;
