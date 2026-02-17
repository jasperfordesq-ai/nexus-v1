/**
 * Group Detail Page - Single group view
 * Full discussions UI, admin features, events tab, member management
 */

import { useState, useEffect, useCallback, Fragment } from 'react';
import { useParams, Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
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
  Chip,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Spinner,
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
  ChevronRight,
  RefreshCw,
  ArrowLeft,
  Plus,
  Send,
  Trash2,
  Clock,
  MapPin,
  MoreVertical,
  Shield,
  ShieldCheck,
  UserX,
  CheckCircle,
  XCircle,
  FileText,
  ChevronDown,
  ChevronUp,
  Upload,
  Image,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, formatRelativeTime } from '@/lib/helpers';
import type { Group, User, FeedPost, Event } from '@/types/api';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface GroupDetails extends Group {
  members?: User[];
  recent_posts?: FeedPost[];
}

interface Discussion {
  id: number;
  title: string;
  content: string;
  author: {
    id: number;
    name: string;
    avatar?: string | null;
  };
  reply_count: number;
  last_activity_at: string;
  created_at: string;
}

interface DiscussionMessage {
  id: number;
  content: string;
  author: {
    id: number;
    name: string;
    avatar?: string | null;
  };
  created_at: string;
}

interface DiscussionDetail extends Discussion {
  messages: DiscussionMessage[];
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

interface GroupMember extends User {
  role?: 'member' | 'admin' | 'moderator';
  joined_at?: string;
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
  usePageTitle('Group');
  const { id } = useParams<{ id: string }>();
  const { user: currentUser, isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  // Core state
  const [group, setGroup] = useState<GroupDetails | null>(null);
  const [activeTab, setActiveTab] = useState('discussion');
  const [isLoading, setIsLoading] = useState(true);
  const [isJoining, setIsJoining] = useState(false);
  const [error, setError] = useState<string | null>(null);

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
        setError('Group not found or has been removed');
      }
    } catch (err) {
      logError('Failed to load group', err);
      setError('Failed to load group. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadGroup();
  }, [loadGroup]);

  // ─────────────────────────────────────────────────────────────────────────
  // Load Discussions
  // ─────────────────────────────────────────────────────────────────────────

  const loadDiscussions = useCallback(async (append = false) => {
    if (!id || discussionsLoading) return;

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
        toast.error('Failed to load discussions');
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
      const response = await api.get<Event[]>(`/v2/events?group_id=${id}&limit=20`);
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

    try {
      setIsJoining(true);
      const memberCount = getMemberCount(group);
      if (isMember(group)) {
        const response = await api.delete(`/v2/groups/${group.id}/membership`);
        if (response.success) {
          setGroup((prev) => prev ? {
            ...prev,
            is_member: false,
            viewer_membership: prev.viewer_membership ? { ...prev.viewer_membership, status: 'none' } : undefined,
            member_count: memberCount - 1,
            members_count: memberCount - 1,
          } : null);
          toast.success('Left the group');
        } else {
          toast.error('Failed to leave group');
        }
      } else {
        const response = await api.post(`/v2/groups/${group.id}/join`);
        if (response.success) {
          setGroup((prev) => prev ? {
            ...prev,
            is_member: true,
            viewer_membership: prev.viewer_membership ? { ...prev.viewer_membership, status: 'active' } : { status: 'active', role: 'member', is_admin: false },
            member_count: memberCount + 1,
            members_count: memberCount + 1,
          } : null);
          toast.success('Joined the group!');
        } else {
          toast.error('Failed to join group');
        }
      }
    } catch (err) {
      logError('Failed to update membership', err);
      toast.error('Something went wrong');
    } finally {
      setIsJoining(false);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Create Discussion
  // ─────────────────────────────────────────────────────────────────────────

  async function handleCreateDiscussion() {
    if (!id || !newDiscussionTitle.trim() || !newDiscussionContent.trim()) return;

    try {
      setCreatingDiscussion(true);
      const response = await api.post<Discussion>(`/v2/groups/${id}/discussions`, {
        title: newDiscussionTitle.trim(),
        content: newDiscussionContent.trim(),
      });
      if (response.success && response.data) {
        setDiscussions((prev) => [response.data!, ...prev]);
        setNewDiscussionTitle('');
        setNewDiscussionContent('');
        setShowNewDiscussion(false);
        toast.success('Discussion created');
      } else {
        toast.error(response.error || 'Failed to create discussion');
      }
    } catch (err) {
      logError('Failed to create discussion', err);
      toast.error('Something went wrong');
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

    try {
      setExpandedDiscussionId(discussionId);
      setExpandedLoading(true);
      setExpandedDiscussion(null);
      const response = await api.get<DiscussionDetail>(
        `/v2/groups/${id}/discussions/${discussionId}`
      );
      if (response.success && response.data) {
        setExpandedDiscussion(response.data);
      } else {
        toast.error('Failed to load discussion');
        setExpandedDiscussionId(null);
      }
    } catch (err) {
      logError('Failed to load discussion', err);
      toast.error('Failed to load discussion');
      setExpandedDiscussionId(null);
    } finally {
      setExpandedLoading(false);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Reply to Discussion
  // ─────────────────────────────────────────────────────────────────────────

  async function handleReply() {
    if (!id || !expandedDiscussionId || !replyContent.trim()) return;

    try {
      setSendingReply(true);
      const response = await api.post<DiscussionMessage>(
        `/v2/groups/${id}/discussions/${expandedDiscussionId}/messages`,
        { content: replyContent.trim() }
      );
      if (response.success && response.data) {
        setExpandedDiscussion((prev) =>
          prev ? { ...prev, messages: [...prev.messages, response.data!] } : null
        );
        // Update reply count in the discussions list
        setDiscussions((prev) =>
          prev.map((d) =>
            d.id === expandedDiscussionId
              ? { ...d, reply_count: d.reply_count + 1, last_activity_at: new Date().toISOString() }
              : d
          )
        );
        setReplyContent('');
        toast.success('Reply sent');
      } else {
        toast.error(response.error || 'Failed to send reply');
      }
    } catch (err) {
      logError('Failed to send reply', err);
      toast.error('Something went wrong');
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
    setSettingsLocation((group as any).location || '');
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
        const url = (response.data as any)?.url || (response.data as any)?.image_url;
        setGroup((prev) => prev ? {
          ...prev,
          ...(type === 'avatar' ? { image_url: url } : { cover_image_url: url }),
        } : null);
        toast.success(`Group ${type === 'avatar' ? 'image' : 'cover'} updated`);
      } else {
        toast.error(response.error || 'Failed to upload image');
      }
    } catch (err) {
      toast.error('Failed to upload image');
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
        is_private: settingsPrivate,
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
        toast.success('Group settings updated');
      } else {
        toast.error(response.error || 'Failed to update settings');
      }
    } catch (err) {
      logError('Failed to update group settings', err);
      toast.error('Something went wrong');
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
        toast.success('Group deleted');
        // Navigate away after deletion
        window.location.href = tenantPath('/groups');
      } else {
        toast.error(response.error || 'Failed to delete group');
      }
    } catch (err) {
      logError('Failed to delete group', err);
      toast.error('Something went wrong');
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
        toast.success(action === 'accept' ? 'Request accepted' : 'Request rejected');
        if (action === 'accept') {
          setGroup((prev) => prev ? {
            ...prev,
            member_count: (prev.member_count ?? prev.members_count ?? 0) + 1,
            members_count: (prev.member_count ?? prev.members_count ?? 0) + 1,
          } : null);
          // Reload members if already loaded
          if (membersLoaded) {
            setMembersLoaded(false);
          }
        }
      } else {
        toast.error(response.error || `Failed to ${action} request`);
      }
    } catch (err) {
      logError(`Failed to ${action} join request`, err);
      toast.error('Something went wrong');
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
        toast.success(`Role updated to ${newRole}`);
      } else {
        toast.error(response.error || 'Failed to update role');
      }
    } catch (err) {
      logError('Failed to update member role', err);
      toast.error('Something went wrong');
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
        toast.success('Member removed');
      } else {
        toast.error(response.error || 'Failed to remove member');
      }
    } catch (err) {
      logError('Failed to remove member', err);
      toast.error('Something went wrong');
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
    return <LoadingScreen message="Loading group..." />;
  }

  if (error || !group) {
    return (
      <div className="max-w-4xl mx-auto">
        <GlassCard className="p-8 text-center">
          <AlertCircle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Group</h2>
          <p className="text-theme-muted mb-4">{error || 'The group you are looking for does not exist'}</p>
          <div className="flex justify-center gap-3">
            <Link to={tenantPath("/groups")}>
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
              >
                Browse Groups
              </Button>
            </Link>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={() => loadGroup()}
            >
              Try Again
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
        { label: 'Groups', href: tenantPath('/groups') },
        { label: group?.name || 'Group' },
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
                {getMemberCount(group)} members
                {group.location && (
                  <>
                    {' '}<span aria-hidden="true">&#183;</span>{' '}
                    <MapPin className="w-3.5 h-3.5 inline" aria-hidden="true" /> {group.location}
                  </>
                )}
                {' '}<span aria-hidden="true">&#183;</span>{' '}Created{' '}
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
                  Settings
                </Button>
                <Button
                  variant="flat"
                  className="bg-red-500/10 text-red-500 hover:bg-red-500/20"
                  startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                  onPress={() => setShowDeleteModal(true)}
                >
                  Delete
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
                {userIsMember ? 'Leave Group' : 'Join Group'}
              </Button>
            )}
          </div>
        </div>

        {/* Description */}
        <p className="text-theme-muted mb-6">
          {group.description || 'No description provided for this group.'}
        </p>

        {/* Quick Stats */}
        <div className="flex flex-wrap gap-6">
          <div className="flex items-center gap-2 text-theme-muted">
            <Users className="w-5 h-5" aria-hidden="true" />
            <span>{getMemberCount(group)} members</span>
          </div>
          {group.posts_count !== undefined && (
            <div className="flex items-center gap-2 text-theme-muted">
              <MessageSquare className="w-5 h-5" aria-hidden="true" />
              <span>{group.posts_count} posts</span>
            </div>
          )}
          <div className="flex items-center gap-2 text-theme-muted">
            <Calendar className="w-5 h-5" aria-hidden="true" />
            <span>Created <time dateTime={group.created_at}>{new Date(group.created_at).toLocaleDateString()}</time></span>
          </div>
        </div>

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
              View Pending Join Requests
            </Button>
          </div>
        )}

        {/* Admin: Pending Requests Section */}
        {userIsAdmin && requestsLoaded && (
          <div className="mt-4 pt-4 border-t border-theme-default">
            <h3 className="text-sm font-semibold text-theme-primary mb-3 flex items-center gap-2">
              <UserPlus className="w-4 h-4" aria-hidden="true" />
              Pending Join Requests ({joinRequests.length})
            </h3>
            {requestsLoading ? (
              <div className="flex justify-center py-4">
                <Spinner size="sm" />
              </div>
            ) : joinRequests.length === 0 ? (
              <p className="text-sm text-theme-subtle">No pending requests</p>
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
                        Accept
                      </Button>
                      <Button
                        size="sm"
                        variant="flat"
                        className="bg-red-500/10 text-red-500"
                        startContent={<XCircle className="w-3.5 h-3.5" aria-hidden="true" />}
                        isLoading={processingRequest === request.user_id}
                        onPress={() => handleJoinRequest(request.user_id, 'reject')}
                      >
                        Reject
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}
      </GlassCard>

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
          key="discussion"
          title={
            <span className="flex items-center gap-2">
              <MessageSquare className="w-4 h-4" aria-hidden="true" />
              Discussion
            </span>
          }
        />
        <Tab
          key="members"
          title={
            <span className="flex items-center gap-2">
              <Users className="w-4 h-4" aria-hidden="true" />
              Members
            </span>
          }
        />
        <Tab
          key="events"
          title={
            <span className="flex items-center gap-2">
              <Calendar className="w-4 h-4" aria-hidden="true" />
              Events
            </span>
          }
        />
        {hasSubGroups && (
          <Tab
            key="subgroups"
            title={
              <span className="flex items-center gap-2">
                <FolderTree className="w-4 h-4" aria-hidden="true" />
                Subgroups ({group.sub_groups?.length})
              </span>
            }
          />
        )}
      </Tabs>

      {/* Tab Content */}
      <div>
        {/* ─── Discussion Tab ─── */}
        {activeTab === 'discussion' && (
          <GlassCard className="p-6">
            {userIsMember ? (
              <div className="space-y-4">
                {/* New Discussion Button */}
                <div className="flex justify-between items-center">
                  <h2 className="text-lg font-semibold text-theme-primary">Discussions</h2>
                  <Button
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    size="sm"
                    startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
                    onPress={() => setShowNewDiscussion(true)}
                  >
                    New Discussion
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
                    title="No discussions yet"
                    description="Be the first to start a discussion in this group"
                    action={
                      <Button
                        className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                        onPress={() => setShowNewDiscussion(true)}
                        startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
                      >
                        Start a Discussion
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
                          onClick={() => handleExpandDiscussion(discussion.id)}
                          role="button"
                          tabIndex={0}
                          aria-expanded={expandedDiscussionId === discussion.id}
                          onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleExpandDiscussion(discussion.id); } }}
                        >
                          <div className="p-4">
                            <div className="flex items-start gap-3">
                              <Avatar
                                src={resolveAvatarUrl(discussion.author.avatar)}
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
                                    {discussion.reply_count} {discussion.reply_count === 1 ? 'reply' : 'replies'}
                                  </span>
                                  <span className="flex items-center gap-1">
                                    <Clock className="w-3 h-3" aria-hidden="true" />
                                    {formatRelativeTime(discussion.last_activity_at || discussion.created_at)}
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
                              <div className="ml-6 pl-6 border-l-2 border-theme-default space-y-4 pb-2">
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
                                              src={resolveAvatarUrl(msg.author.avatar)}
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
                                    <div className="flex gap-2 items-end">
                                      <Textarea
                                        placeholder="Write a reply..."
                                        aria-label="Reply to discussion"
                                        value={replyContent}
                                        onChange={(e) => setReplyContent(e.target.value)}
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
                                        aria-label="Send reply"
                                        isLoading={sendingReply}
                                        isDisabled={!replyContent.trim()}
                                        onPress={handleReply}
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
                          onPress={() => loadDiscussions(true)}
                        >
                          Load More Discussions
                        </Button>
                      </div>
                    )}
                  </div>
                )}
              </div>
            ) : (
              <EmptyState
                icon={<Lock className="w-12 h-12" aria-hidden="true" />}
                title="Join to see discussion"
                description="You need to be a member to view and participate in discussions"
                action={
                  isAuthenticated && (
                    <Button
                      className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                      onPress={handleJoinLeave}
                      isLoading={isJoining}
                    >
                      Join Group
                    </Button>
                  )
                }
              />
            )}
          </GlassCard>
        )}

        {/* ─── Members Tab ─── */}
        {activeTab === 'members' && (
          <GlassCard className="p-6">
            {membersLoading ? (
              <div className="flex justify-center py-8">
                <Spinner size="lg" />
              </div>
            ) : members.length > 0 ? (
              <div className="grid sm:grid-cols-2 gap-4">
                {members.map((member) => {
                  const memberIsOwner = member.id === group.owner?.id;
                  const memberIsAdmin = member.role === 'admin' || member.id === group.admins?.[0]?.id || memberIsOwner;
                  const canManage = userIsAdmin && !memberIsOwner && member.id !== currentUser?.id;

                  return (
                    <div key={member.id} className="flex items-center gap-4 p-4 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors">
                      <Link to={tenantPath(`/profile/${member.id}`)} className="flex items-center gap-4 flex-1 min-w-0">
                        <Avatar
                          src={resolveAvatarUrl(member.avatar_url || member.avatar)}
                          name={member.name}
                          size="md"
                          className="ring-2 ring-white/20 flex-shrink-0"
                        />
                        <div className="min-w-0">
                          <p className="font-medium text-theme-primary truncate">{member.name}</p>
                          {member.tagline && (
                            <p className="text-sm text-theme-subtle truncate">{member.tagline}</p>
                          )}
                          <div className="flex items-center gap-2 mt-1">
                            {memberIsOwner && (
                              <Chip size="sm" variant="flat" className="bg-amber-500/20 text-amber-600 dark:text-amber-400" startContent={<ShieldCheck className="w-3 h-3" />}>
                                Owner
                              </Chip>
                            )}
                            {memberIsAdmin && !memberIsOwner && (
                              <Chip size="sm" variant="flat" className="bg-purple-500/20 text-purple-600 dark:text-purple-400" startContent={<Shield className="w-3 h-3" />}>
                                Admin
                              </Chip>
                            )}
                          </div>
                        </div>
                      </Link>

                      {/* Admin: Role Management */}
                      {canManage && (
                        <Dropdown>
                          <DropdownTrigger>
                            <Button
                              isIconOnly
                              variant="light"
                              size="sm"
                              aria-label={`Manage ${member.name}`}
                              isLoading={updatingMember === member.id}
                            >
                              <MoreVertical className="w-4 h-4" />
                            </Button>
                          </DropdownTrigger>
                          <DropdownMenu aria-label="Member actions">
                            {memberIsAdmin ? (
                              <DropdownItem
                                key="demote"
                                startContent={<Users className="w-4 h-4" />}
                                onPress={() => handleUpdateMemberRole(member.id, 'member')}
                              >
                                Demote to Member
                              </DropdownItem>
                            ) : (
                              <DropdownItem
                                key="promote"
                                startContent={<Shield className="w-4 h-4" />}
                                onPress={() => handleUpdateMemberRole(member.id, 'admin')}
                              >
                                Promote to Admin
                              </DropdownItem>
                            )}
                            <DropdownItem
                              key="remove"
                              className="text-danger"
                              color="danger"
                              startContent={<UserX className="w-4 h-4" />}
                              onPress={() => handleRemoveMember(member.id)}
                            >
                              Remove from Group
                            </DropdownItem>
                          </DropdownMenu>
                        </Dropdown>
                      )}
                    </div>
                  );
                })}
              </div>
            ) : (
              <EmptyState
                icon={<Users className="w-12 h-12" aria-hidden="true" />}
                title="No members yet"
                description="Be the first to join this group"
              />
            )}
          </GlassCard>
        )}

        {/* ─── Events Tab ─── */}
        {activeTab === 'events' && (
          <GlassCard className="p-6">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-lg font-semibold text-theme-primary">Group Events</h2>
              {userIsMember && isAuthenticated && (
                <Link to={tenantPath(`/events/create?group_id=${group.id}`)}>
                  <Button
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    size="sm"
                    startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
                  >
                    Create Event
                  </Button>
                </Link>
              )}
            </div>

            {eventsLoading ? (
              <div className="flex justify-center py-8">
                <Spinner size="lg" />
              </div>
            ) : events.length === 0 ? (
              <EmptyState
                icon={<Calendar className="w-12 h-12" aria-hidden="true" />}
                title="No events yet"
                description="Create an event to bring this group together"
                action={
                  userIsMember && isAuthenticated && (
                    <Link to={tenantPath(`/events/create?group_id=${group.id}`)}>
                      <Button
                        className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                        startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
                      >
                        Create Event
                      </Button>
                    </Link>
                  )
                }
              />
            ) : (
              <div className="space-y-3">
                {events.map((event) => {
                  const eventDate = new Date(event.start_date);
                  const isPast = eventDate < new Date();

                  return (
                    <Link key={event.id} to={tenantPath(`/events/${event.id}`)}>
                      <div className={`flex items-center gap-4 p-4 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors ${isPast ? 'opacity-60' : ''}`}>
                        {/* Date Badge */}
                        <div className="flex-shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex flex-col items-center justify-center text-center">
                          <span className="text-xs font-medium text-indigo-400 uppercase">
                            {eventDate.toLocaleDateString('en-US', { month: 'short' })}
                          </span>
                          <span className="text-lg font-bold text-theme-primary leading-none">
                            {eventDate.getDate()}
                          </span>
                        </div>

                        <div className="flex-1 min-w-0">
                          <h3 className="font-medium text-theme-primary truncate">{event.title}</h3>
                          <div className="flex items-center gap-3 mt-1 text-xs text-theme-subtle">
                            <span className="flex items-center gap-1">
                              <Clock className="w-3 h-3" aria-hidden="true" />
                              {eventDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
                            </span>
                            {event.location && (
                              <span className="flex items-center gap-1 truncate">
                                <MapPin className="w-3 h-3 flex-shrink-0" aria-hidden="true" />
                                {event.location}
                              </span>
                            )}
                            <span className="flex items-center gap-1">
                              <Users className="w-3 h-3" aria-hidden="true" />
                              {event.attendees_count} attending
                            </span>
                          </div>
                        </div>

                        {isPast && (
                          <Chip size="sm" variant="flat" className="bg-theme-hover text-theme-subtle">
                            Past
                          </Chip>
                        )}

                        <ChevronRight className="w-5 h-5 text-theme-subtle flex-shrink-0" aria-hidden="true" />
                      </div>
                    </Link>
                  );
                })}
              </div>
            )}
          </GlassCard>
        )}

        {/* ─── Subgroups Tab ─── */}
        {activeTab === 'subgroups' && hasSubGroups && (
          <GlassCard className="p-6">
            <div className="space-y-3">
              {group.sub_groups?.map((subGroup) => (
                <Link key={subGroup.id} to={tenantPath(`/groups/${subGroup.id}`)}>
                  <div className="flex items-center justify-between p-4 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors">
                    <div className="flex items-center gap-4">
                      <div className="p-3 rounded-xl bg-gradient-to-br from-purple-500/20 to-indigo-500/20">
                        <Users className="w-5 h-5 text-purple-400" aria-hidden="true" />
                      </div>
                      <div>
                        <p className="font-medium text-theme-primary">{subGroup.name}</p>
                        <p className="text-sm text-theme-subtle">
                          {subGroup.member_count} members
                        </p>
                      </div>
                    </div>
                    <ChevronRight className="w-5 h-5 text-theme-subtle" aria-hidden="true" />
                  </div>
                </Link>
              ))}
            </div>
          </GlassCard>
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
                New Discussion
              </ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label="Title"
                  placeholder="What do you want to discuss?"
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
                  label="Content"
                  placeholder="Share your thoughts..."
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
                  Cancel
                </Button>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  isLoading={creatingDiscussion}
                  isDisabled={!newDiscussionTitle.trim() || !newDiscussionContent.trim()}
                  onPress={handleCreateDiscussion}
                >
                  Create Discussion
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
                Group Settings
              </ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label="Group Name"
                  placeholder="Enter group name"
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
                  label="Description"
                  placeholder="Describe your group..."
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
                  label="Location"
                  placeholder="e.g. Skibbereen, Co Cork"
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
                <div className="grid grid-cols-2 gap-3">
                  <div className="p-3 rounded-lg bg-theme-elevated border border-theme-default">
                    <p className="text-sm font-medium text-theme-primary mb-2 flex items-center gap-1.5">
                      <Image className="w-4 h-4" aria-hidden="true" />
                      Group Image
                    </p>
                    {group?.image_url && (
                      <img src={group.image_url} alt="Group" className="w-12 h-12 rounded-full object-cover mb-2" />
                    )}
                    <label className="flex items-center gap-1.5 text-xs text-primary cursor-pointer hover:underline">
                      <Upload className="w-3 h-3" aria-hidden="true" />
                      {uploadingImage ? 'Uploading...' : 'Upload image'}
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
                      Cover Image
                    </p>
                    {(group as any)?.cover_image_url && (
                      <img src={(group as any).cover_image_url} alt="Cover" className="w-full h-10 rounded object-cover mb-2" />
                    )}
                    <label className="flex items-center gap-1.5 text-xs text-primary cursor-pointer hover:underline">
                      <Upload className="w-3 h-3" aria-hidden="true" />
                      {uploadingImage ? 'Uploading...' : 'Upload cover'}
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
                          {settingsPrivate ? 'Private Group' : 'Public Group'}
                        </p>
                        <p className="text-sm text-theme-subtle">
                          {settingsPrivate
                            ? 'Only approved members can see posts and join'
                            : 'Anyone can see posts and join this group'}
                        </p>
                      </div>
                    </div>
                    <Switch
                      aria-label={settingsPrivate ? 'Make group public' : 'Make group private'}
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
                  Cancel
                </Button>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  isLoading={savingSettings}
                  isDisabled={!settingsName.trim()}
                  onPress={handleSaveSettings}
                >
                  Save Changes
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
                Delete Group
              </ModalHeader>
              <ModalBody>
                <div className="text-center py-4">
                  <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-red-500/10 flex items-center justify-center">
                    <AlertCircle className="w-8 h-8 text-red-500" aria-hidden="true" />
                  </div>
                  <p className="text-theme-primary font-medium mb-2">
                    Are you sure you want to delete &ldquo;{group.name}&rdquo;?
                  </p>
                  <p className="text-sm text-theme-muted">
                    This action cannot be undone. All discussions, members, and group data will be permanently removed.
                  </p>
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={onClose}>
                  Cancel
                </Button>
                <Button
                  className="bg-red-500 text-white"
                  isLoading={deletingGroup}
                  onPress={handleDeleteGroup}
                  startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                >
                  Delete Group
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </motion.div>
  );
}

export default GroupDetailPage;
