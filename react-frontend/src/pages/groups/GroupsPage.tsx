/**
 * Groups Page - Community groups listing
 */

import { useState, useEffect, useCallback, memo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Select, SelectItem, Avatar, AvatarGroup } from '@heroui/react';
import {
  Search,
  Users,
  Plus,
  Filter,
  Lock,
  Globe,
  MessageSquare,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { Group } from '@/types/api';

type GroupFilter = 'all' | 'joined' | 'public' | 'private';

export function GroupsPage() {
  const { isAuthenticated } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();

  const [groups, setGroups] = useState<Group[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [filter, setFilter] = useState<GroupFilter>('all');

  const loadGroups = useCallback(async () => {
    try {
      setIsLoading(true);
      const params = new URLSearchParams();
      if (searchQuery) params.set('q', searchQuery);
      if (filter !== 'all') params.set('filter', filter);
      params.set('limit', '20');

      const response = await api.get<Group[]>(`/v2/groups?${params}`);
      if (response.success && response.data) {
        setGroups(response.data);
      }
    } catch (error) {
      logError('Failed to load groups', error);
    } finally {
      setIsLoading(false);
    }
  }, [searchQuery, filter]);

  useEffect(() => {
    loadGroups();

    const params = new URLSearchParams();
    if (searchQuery) params.set('q', searchQuery);
    setSearchParams(params, { replace: true });
  }, [searchQuery, filter]);

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.05 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-white flex items-center gap-3">
            <Users className="w-7 h-7 text-purple-400" />
            Groups
          </h1>
          <p className="text-white/60 mt-1">Join groups to connect with like-minded community members</p>
        </div>
        {isAuthenticated && (
          <Link to="/groups/create">
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<Plus className="w-4 h-4" />}
            >
              Create Group
            </Button>
          </Link>
        )}
      </div>

      {/* Filters */}
      <GlassCard className="p-4">
        <div className="flex flex-col lg:flex-row gap-4">
          <div className="flex-1">
            <Input
              placeholder="Search groups..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              startContent={<Search className="w-4 h-4 text-white/40" />}
              classNames={{
                input: 'bg-transparent text-white placeholder:text-white/40',
                inputWrapper: 'bg-white/5 border-white/10 hover:bg-white/10',
              }}
            />
          </div>

          <Select
            placeholder="Filter"
            selectedKeys={[filter]}
            onChange={(e) => setFilter(e.target.value as GroupFilter)}
            className="w-40"
            classNames={{
              trigger: 'bg-white/5 border-white/10 hover:bg-white/10',
              value: 'text-white',
            }}
            startContent={<Filter className="w-4 h-4 text-white/40" />}
          >
            <SelectItem key="all">All Groups</SelectItem>
            {isAuthenticated ? <SelectItem key="joined">My Groups</SelectItem> : null}
            <SelectItem key="public">Public</SelectItem>
            <SelectItem key="private">Private</SelectItem>
          </Select>
        </div>
      </GlassCard>

      {/* Groups Grid */}
      {isLoading ? (
        <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <GlassCard key={i} className="p-5 animate-pulse">
              <div className="h-5 bg-white/10 rounded w-2/3 mb-3" />
              <div className="h-4 bg-white/10 rounded w-full mb-2" />
              <div className="h-4 bg-white/10 rounded w-3/4 mb-4" />
              <div className="h-3 bg-white/10 rounded w-1/3" />
            </GlassCard>
          ))}
        </div>
      ) : groups.length === 0 ? (
        <EmptyState
          icon={<Users className="w-12 h-12" />}
          title="No groups found"
          description="Start a new group or try a different search"
          action={
            isAuthenticated && (
              <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                Create Group
              </Button>
            )
          }
        />
      ) : (
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4"
        >
          {groups.map((group) => (
            <motion.div key={group.id} variants={itemVariants}>
              <GroupCard group={group} />
            </motion.div>
          ))}
        </motion.div>
      )}
    </div>
  );
}

interface GroupCardProps {
  group: Group;
}

const GroupCard = memo(function GroupCard({ group }: GroupCardProps) {
  return (
    <Link to={`/groups/${group.id}`}>
      <GlassCard className="p-5 hover:scale-[1.02] transition-transform h-full flex flex-col">
        <div className="flex items-start justify-between gap-3 mb-3">
          <h3 className="font-semibold text-white text-lg">{group.name}</h3>
          {group.visibility === 'private' ? (
            <span className="flex-shrink-0 p-1.5 rounded-full bg-amber-500/20">
              <Lock className="w-4 h-4 text-amber-400" />
            </span>
          ) : (
            <span className="flex-shrink-0 p-1.5 rounded-full bg-emerald-500/20">
              <Globe className="w-4 h-4 text-emerald-400" />
            </span>
          )}
        </div>

        <p className="text-white/60 text-sm line-clamp-2 flex-1 mb-4">
          {group.description || 'No description provided'}
        </p>

        {/* Group Stats */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4 text-sm text-white/50">
            <span className="flex items-center gap-1">
              <Users className="w-4 h-4" />
              {group.members_count}
            </span>
            {group.posts_count !== undefined && (
              <span className="flex items-center gap-1">
                <MessageSquare className="w-4 h-4" />
                {group.posts_count}
              </span>
            )}
          </div>

          {group.recent_members && group.recent_members.length > 0 && (
            <AvatarGroup max={3} size="sm">
              {group.recent_members.map((member) => (
                <Avatar
                  key={member.id}
                  src={member.avatar || undefined}
                  name={member.name || `${member.first_name ?? ''} ${member.last_name ?? ''}`.trim()}
                  className="ring-2 ring-black/50"
                />
              ))}
            </AvatarGroup>
          )}
        </div>

        {/* Member Status */}
        {group.is_member && (
          <div className="mt-4 pt-4 border-t border-white/10">
            <span className="text-xs px-2 py-1 rounded-full bg-indigo-500/20 text-indigo-400">
              Member
            </span>
          </div>
        )}
      </GlassCard>
    </Link>
  );
});

export default GroupsPage;
