/**
 * Group Detail Page - Single group view
 */

import { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Avatar, Tabs, Tab } from '@heroui/react';
import {
  ArrowLeft,
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
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { useAuth } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { Group, User, FeedPost } from '@/types/api';

interface GroupDetails extends Group {
  members?: User[];
  recent_posts?: FeedPost[];
}

// Helper to get member count (backend uses member_count, frontend type has both)
function getMemberCount(group: GroupDetails): number {
  return group.member_count ?? group.members_count ?? 0;
}

// Helper to check if user is a member
function isMember(group: GroupDetails): boolean {
  // Check viewer_membership from backend
  if (group.viewer_membership) {
    return group.viewer_membership.status === 'active';
  }
  // Fallback to is_member flag
  return group.is_member ?? false;
}

// Helper to check if user is admin
function isGroupAdmin(group: GroupDetails): boolean {
  if (group.viewer_membership) {
    return group.viewer_membership.is_admin;
  }
  return group.is_admin ?? false;
}

export function GroupDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();

  const [group, setGroup] = useState<GroupDetails | null>(null);
  const [members, setMembers] = useState<User[]>([]);
  const [membersLoading, setMembersLoading] = useState(false);
  const [membersLoaded, setMembersLoaded] = useState(false);
  const [activeTab, setActiveTab] = useState('discussion');
  const [isLoading, setIsLoading] = useState(true);
  const [isJoining, setIsJoining] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadGroup();
  }, [id]);

  async function loadGroup() {
    if (!id) return;

    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<GroupDetails>(`/v2/groups/${id}`);
      if (response.success && response.data) {
        setGroup(response.data);
        // Default to subgroups tab if group has subgroups
        if (response.data.sub_groups && response.data.sub_groups.length > 0) {
          setActiveTab('subgroups');
        }
      } else {
        setError('Group not found or has been removed');
      }
    } catch (err) {
      setError('Group not found or has been removed');
    } finally {
      setIsLoading(false);
    }
  }

  async function loadMembers() {
    if (!id || membersLoaded || membersLoading) return;

    try {
      setMembersLoading(true);
      const response = await api.get<User[]>(`/v2/groups/${id}/members`);
      if (response.success && response.data) {
        setMembers(response.data);
      }
    } catch (err) {
      logError('Failed to load group members', err);
    } finally {
      setMembersLoading(false);
      setMembersLoaded(true);
    }
  }

  // Load members when tab changes to members
  useEffect(() => {
    if (activeTab === 'members' && !membersLoaded) {
      loadMembers();
    }
  }, [activeTab, membersLoaded]);

  async function handleJoinLeave() {
    if (!group || !isAuthenticated) return;

    try {
      setIsJoining(true);
      const memberCount = getMemberCount(group);
      if (isMember(group)) {
        await api.delete(`/v2/groups/${group.id}/membership`);
        setGroup((prev) => prev ? {
          ...prev,
          is_member: false,
          viewer_membership: prev.viewer_membership ? { ...prev.viewer_membership, status: 'none' } : undefined,
          member_count: memberCount - 1,
          members_count: memberCount - 1,
        } : null);
      } else {
        await api.post(`/v2/groups/${group.id}/join`);
        setGroup((prev) => prev ? {
          ...prev,
          is_member: true,
          viewer_membership: prev.viewer_membership ? { ...prev.viewer_membership, status: 'active' } : { status: 'active', role: 'member', is_admin: false },
          member_count: memberCount + 1,
          members_count: memberCount + 1,
        } : null);
      }
    } catch (err) {
      logError('Failed to update membership', err);
    } finally {
      setIsJoining(false);
    }
  }

  const userIsMember = group ? isMember(group) : false;
  const userIsAdmin = group ? isGroupAdmin(group) : false;
  const hasSubGroups = group?.sub_groups && group.sub_groups.length > 0;

  if (isLoading) {
    return <LoadingScreen message="Loading group..." />;
  }

  if (error || !group) {
    return (
      <EmptyState
        icon={<AlertCircle className="w-12 h-12" />}
        title="Group Not Found"
        description={error || 'The group you are looking for does not exist'}
        action={
          <Link to="/groups">
            <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
              Browse Groups
            </Button>
          </Link>
        }
      />
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="max-w-4xl mx-auto space-y-6"
    >
      {/* Back Button */}
      <button
        onClick={() => navigate(-1)}
        className="flex items-center gap-2 text-theme-muted hover:text-theme-primary transition-colors"
      >
        <ArrowLeft className="w-4 h-4" />
        Back to groups
      </button>

      {/* Group Header */}
      <GlassCard className="p-6 sm:p-8">
        <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
          <div className="flex items-center gap-4">
            <div className="p-4 rounded-2xl bg-gradient-to-br from-purple-500/20 to-indigo-500/20">
              <Users className="w-8 h-8 text-purple-400" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-2xl font-bold text-theme-primary">{group.name}</h1>
                {group.visibility === 'private' ? (
                  <Lock className="w-5 h-5 text-amber-400" />
                ) : (
                  <Globe className="w-5 h-5 text-emerald-400" />
                )}
              </div>
              <p className="text-theme-muted text-sm mt-1">
                {getMemberCount(group)} members • Created {new Date(group.created_at).toLocaleDateString()}
              </p>
            </div>
          </div>

          <div className="flex gap-2">
            {userIsAdmin && (
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                startContent={<Settings className="w-4 h-4" />}
              >
                Settings
              </Button>
            )}
            {isAuthenticated && (
              <Button
                className={userIsMember
                  ? 'bg-theme-hover text-theme-primary'
                  : 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white'
                }
                startContent={userIsMember ? <UserMinus className="w-4 h-4" /> : <UserPlus className="w-4 h-4" />}
                onClick={handleJoinLeave}
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
            <Users className="w-5 h-5" />
            <span>{getMemberCount(group)} members</span>
          </div>
          {group.posts_count !== undefined && (
            <div className="flex items-center gap-2 text-theme-muted">
              <MessageSquare className="w-5 h-5" />
              <span>{group.posts_count} posts</span>
            </div>
          )}
          <div className="flex items-center gap-2 text-theme-muted">
            <Calendar className="w-5 h-5" />
            <span>Created {new Date(group.created_at).toLocaleDateString()}</span>
          </div>
        </div>
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
              <MessageSquare className="w-4 h-4" />
              Discussion
            </span>
          }
        />
        <Tab
          key="members"
          title={
            <span className="flex items-center gap-2">
              <Users className="w-4 h-4" />
              Members
            </span>
          }
        />
        {hasSubGroups && (
          <Tab
            key="subgroups"
            title={
              <span className="flex items-center gap-2">
                <FolderTree className="w-4 h-4" />
                Subgroups ({group.sub_groups?.length})
              </span>
            }
          />
        )}
      </Tabs>

      {/* Tab Content */}
      <div>
        {activeTab === 'discussion' && (
          <GlassCard className="p-6">
            {userIsMember ? (
              group.recent_posts && group.recent_posts.length > 0 ? (
                <div className="space-y-4">
                  {group.recent_posts.map((post) => (
                    <div key={post.id} className="p-4 rounded-lg bg-theme-elevated">
                      <div className="flex items-center gap-3 mb-3">
                        <Avatar
                          src={resolveAvatarUrl(post.author?.avatar)}
                          name={post.author?.name}
                          size="sm"
                        />
                        <div>
                          <p className="font-medium text-theme-primary">{post.author?.name}</p>
                          <p className="text-xs text-theme-subtle">
                            {new Date(post.created_at).toLocaleDateString()}
                          </p>
                        </div>
                      </div>
                      <p className="text-theme-muted">{post.content}</p>
                    </div>
                  ))}
                </div>
              ) : (
                <EmptyState
                  icon={<MessageSquare className="w-12 h-12" />}
                  title="No posts yet"
                  description="Be the first to start a discussion in this group"
                />
              )
            ) : (
              <EmptyState
                icon={<Lock className="w-12 h-12" />}
                title="Join to see discussion"
                description="You need to be a member to view and participate in discussions"
                action={
                  isAuthenticated && (
                    <Button
                      className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                      onClick={handleJoinLeave}
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

        {activeTab === 'members' && (
          <GlassCard className="p-6">
            {membersLoading ? (
              <div className="flex justify-center py-8">
                <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-purple-500"></div>
              </div>
            ) : members.length > 0 ? (
              <div className="grid sm:grid-cols-2 gap-4">
                {members.map((member) => (
                  <Link key={member.id} to={`/profile/${member.id}`}>
                    <div className="flex items-center gap-4 p-4 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors">
                      <Avatar
                        src={resolveAvatarUrl(member.avatar_url || member.avatar)}
                        name={member.name}
                        size="md"
                        className="ring-2 ring-white/20"
                      />
                      <div>
                        <p className="font-medium text-theme-primary">{member.name}</p>
                        {member.tagline && (
                          <p className="text-sm text-theme-subtle">{member.tagline}</p>
                        )}
                      </div>
                      {(member.id === group.owner?.id || member.id === group.admins?.[0]?.id) && (
                        <span className="ml-auto text-xs px-2 py-1 rounded-full bg-purple-500/20 text-purple-400">
                          Admin
                        </span>
                      )}
                    </div>
                  </Link>
                ))}
              </div>
            ) : (
              <EmptyState
                icon={<Users className="w-12 h-12" />}
                title="No members yet"
                description="Be the first to join this group"
              />
            )}
          </GlassCard>
        )}

        {activeTab === 'subgroups' && hasSubGroups && (
          <GlassCard className="p-6">
            <div className="space-y-3">
              {group.sub_groups?.map((subGroup) => (
                <Link key={subGroup.id} to={`/groups/${subGroup.id}`}>
                  <div className="flex items-center justify-between p-4 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors">
                    <div className="flex items-center gap-4">
                      <div className="p-3 rounded-xl bg-gradient-to-br from-purple-500/20 to-indigo-500/20">
                        <Users className="w-5 h-5 text-purple-400" />
                      </div>
                      <div>
                        <p className="font-medium text-theme-primary">{subGroup.name}</p>
                        <p className="text-sm text-theme-subtle">
                          {subGroup.member_count} members
                        </p>
                      </div>
                    </div>
                    <ChevronRight className="w-5 h-5 text-theme-subtle" />
                  </div>
                </Link>
              ))}
            </div>
          </GlassCard>
        )}
      </div>
    </motion.div>
  );
}

export default GroupDetailPage;
