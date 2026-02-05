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

export function GroupDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user, isAuthenticated } = useAuth();

  const [group, setGroup] = useState<GroupDetails | null>(null);
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
      } else {
        setError('Group not found or has been removed');
      }
    } catch (err) {
      setError('Group not found or has been removed');
    } finally {
      setIsLoading(false);
    }
  }

  async function handleJoinLeave() {
    if (!group || !isAuthenticated) return;

    try {
      setIsJoining(true);
      if (group.is_member) {
        await api.delete(`/v2/groups/${group.id}/membership`);
        setGroup((prev) => prev ? { ...prev, is_member: false, members_count: (prev.members_count) - 1 } : null);
      } else {
        await api.post(`/v2/groups/${group.id}/join`);
        setGroup((prev) => prev ? { ...prev, is_member: true, members_count: (prev.members_count) + 1 } : null);
      }
    } catch (err) {
      logError('Failed to update membership', err);
    } finally {
      setIsJoining(false);
    }
  }

  const isAdmin = user && group && group.admins?.[0]?.id === user.id;

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
        className="flex items-center gap-2 text-white/60 hover:text-white transition-colors"
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
                <h1 className="text-2xl font-bold text-white">{group.name}</h1>
                {group.visibility === 'private' ? (
                  <Lock className="w-5 h-5 text-amber-400" />
                ) : (
                  <Globe className="w-5 h-5 text-emerald-400" />
                )}
              </div>
              <p className="text-white/60 text-sm mt-1">
                {group.members_count} members • Created {new Date(group.created_at).toLocaleDateString()}
              </p>
            </div>
          </div>

          <div className="flex gap-2">
            {isAdmin && (
              <Button
                variant="flat"
                className="bg-white/5 text-white"
                startContent={<Settings className="w-4 h-4" />}
              >
                Settings
              </Button>
            )}
            {isAuthenticated && (
              <Button
                className={group.is_member
                  ? 'bg-white/10 text-white'
                  : 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white'
                }
                startContent={group.is_member ? <UserMinus className="w-4 h-4" /> : <UserPlus className="w-4 h-4" />}
                onClick={handleJoinLeave}
                isLoading={isJoining}
              >
                {group.is_member ? 'Leave Group' : 'Join Group'}
              </Button>
            )}
          </div>
        </div>

        {/* Description */}
        <p className="text-white/70 mb-6">
          {group.description || 'No description provided for this group.'}
        </p>

        {/* Quick Stats */}
        <div className="flex flex-wrap gap-6">
          <div className="flex items-center gap-2 text-white/60">
            <Users className="w-5 h-5" />
            <span>{group.members_count} members</span>
          </div>
          {group.posts_count !== undefined && (
            <div className="flex items-center gap-2 text-white/60">
              <MessageSquare className="w-5 h-5" />
              <span>{group.posts_count} posts</span>
            </div>
          )}
          <div className="flex items-center gap-2 text-white/60">
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
          tabList: 'bg-white/5 p-1 rounded-lg',
          cursor: 'bg-white/10',
          tab: 'text-white/60 data-[selected=true]:text-white',
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
      </Tabs>

      {/* Tab Content */}
      <div>
        {activeTab === 'discussion' && (
          <GlassCard className="p-6">
            {group.is_member ? (
              group.recent_posts && group.recent_posts.length > 0 ? (
                <div className="space-y-4">
                  {group.recent_posts.map((post) => (
                    <div key={post.id} className="p-4 rounded-lg bg-white/5">
                      <div className="flex items-center gap-3 mb-3">
                        <Avatar
                          src={resolveAvatarUrl(post.author?.avatar)}
                          name={post.author?.name}
                          size="sm"
                        />
                        <div>
                          <p className="font-medium text-white">{post.author?.name}</p>
                          <p className="text-xs text-white/40">
                            {new Date(post.created_at).toLocaleDateString()}
                          </p>
                        </div>
                      </div>
                      <p className="text-white/70">{post.content}</p>
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
                  <Button
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    onClick={handleJoinLeave}
                    isLoading={isJoining}
                  >
                    Join Group
                  </Button>
                }
              />
            )}
          </GlassCard>
        )}

        {activeTab === 'members' && (
          <GlassCard className="p-6">
            {group.members && group.members.length > 0 ? (
              <div className="grid sm:grid-cols-2 gap-4">
                {group.members.map((member) => (
                  <Link key={member.id} to={`/profile/${member.id}`}>
                    <div className="flex items-center gap-4 p-4 rounded-lg bg-white/5 hover:bg-white/10 transition-colors">
                      <Avatar
                        src={resolveAvatarUrl(member.avatar)}
                        name={member.name}
                        size="md"
                        className="ring-2 ring-white/20"
                      />
                      <div>
                        <p className="font-medium text-white">{member.name}</p>
                        {member.tagline && (
                          <p className="text-sm text-white/50">{member.tagline}</p>
                        )}
                      </div>
                      {member.id === group.admins?.[0]?.id && (
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
      </div>
    </motion.div>
  );
}

export default GroupDetailPage;
