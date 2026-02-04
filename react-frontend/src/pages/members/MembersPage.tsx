/**
 * Members Page - Community member directory
 */

import { useState, useEffect, useCallback, memo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Input, Select, SelectItem, Avatar } from '@heroui/react';
import {
  Search,
  Users,
  MapPin,
  Star,
  Clock,
  Filter,
  Grid,
  List,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { User } from '@/types/api';

type SortOption = 'name' | 'joined' | 'rating' | 'hours_given';
type ViewMode = 'grid' | 'list';

export function MembersPage() {
  const [searchParams, setSearchParams] = useSearchParams();

  const [members, setMembers] = useState<User[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [sortBy, setSortBy] = useState<SortOption>('name');
  const [viewMode, setViewMode] = useState<ViewMode>('grid');

  const loadMembers = useCallback(async () => {
    try {
      setIsLoading(true);
      const params = new URLSearchParams();
      if (searchQuery) params.set('q', searchQuery);
      params.set('sort', sortBy);
      params.set('limit', '50');

      const response = await api.get<User[]>(`/v2/users?${params}`);
      if (response.success && response.data) {
        setMembers(response.data);
      }
    } catch (error) {
      logError('Failed to load members', error);
    } finally {
      setIsLoading(false);
    }
  }, [searchQuery, sortBy]);

  useEffect(() => {
    loadMembers();

    // Update URL params
    const params = new URLSearchParams();
    if (searchQuery) params.set('q', searchQuery);
    setSearchParams(params, { replace: true });
  }, [searchQuery, sortBy]);

  function handleSearch(e: React.FormEvent) {
    e.preventDefault();
    loadMembers();
  }

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.05 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, scale: 0.95 },
    visible: { opacity: 1, scale: 1 },
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-white flex items-center gap-3">
          <Users className="w-7 h-7 text-indigo-400" />
          Members
        </h1>
        <p className="text-white/60 mt-1">Connect with members of the community</p>
      </div>

      {/* Filters */}
      <GlassCard className="p-4">
        <form onSubmit={handleSearch} className="flex flex-col lg:flex-row gap-4">
          <div className="flex-1">
            <Input
              placeholder="Search members..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              startContent={<Search className="w-4 h-4 text-white/40" />}
              classNames={{
                input: 'bg-transparent text-white placeholder:text-white/40',
                inputWrapper: 'bg-white/5 border-white/10 hover:bg-white/10',
              }}
            />
          </div>

          <div className="flex gap-3">
            <Select
              placeholder="Sort by"
              selectedKeys={[sortBy]}
              onChange={(e) => setSortBy(e.target.value as SortOption)}
              className="w-44"
              classNames={{
                trigger: 'bg-white/5 border-white/10 hover:bg-white/10',
                value: 'text-white',
              }}
              startContent={<Filter className="w-4 h-4 text-white/40" />}
            >
              <SelectItem key="name">Name</SelectItem>
              <SelectItem key="joined">Newest</SelectItem>
              <SelectItem key="rating">Highest Rated</SelectItem>
              <SelectItem key="hours_given">Most Active</SelectItem>
            </Select>

            <div className="flex rounded-lg overflow-hidden border border-white/10">
              <button
                type="button"
                onClick={() => setViewMode('grid')}
                className={`p-2 ${viewMode === 'grid' ? 'bg-white/10' : 'bg-white/5 hover:bg-white/10'}`}
              >
                <Grid className="w-4 h-4 text-white" />
              </button>
              <button
                type="button"
                onClick={() => setViewMode('list')}
                className={`p-2 ${viewMode === 'list' ? 'bg-white/10' : 'bg-white/5 hover:bg-white/10'}`}
              >
                <List className="w-4 h-4 text-white" />
              </button>
            </div>
          </div>
        </form>
      </GlassCard>

      {/* Members Grid/List */}
      {isLoading ? (
        <div className={viewMode === 'grid' ? 'grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4' : 'space-y-3'}>
          {[1, 2, 3, 4, 5, 6, 7, 8].map((i) => (
            <GlassCard key={i} className="p-5 animate-pulse">
              <div className="flex flex-col items-center">
                <div className="w-16 h-16 rounded-full bg-white/10 mb-3" />
                <div className="h-4 bg-white/10 rounded w-2/3 mb-2" />
                <div className="h-3 bg-white/10 rounded w-1/2" />
              </div>
            </GlassCard>
          ))}
        </div>
      ) : members.length === 0 ? (
        <EmptyState
          icon={<Users className="w-12 h-12" />}
          title="No members found"
          description="Try a different search term"
        />
      ) : (
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className={viewMode === 'grid' ? 'grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4' : 'space-y-3'}
        >
          {members.map((member) => (
            <motion.div key={member.id} variants={itemVariants}>
              <MemberCard member={member} viewMode={viewMode} />
            </motion.div>
          ))}
        </motion.div>
      )}
    </div>
  );
}

interface MemberCardProps {
  member: User;
  viewMode: ViewMode;
}

const MemberCard = memo(function MemberCard({ member, viewMode }: MemberCardProps) {
  if (viewMode === 'list') {
    return (
      <Link to={`/profile/${member.id}`}>
        <GlassCard className="p-4 hover:bg-white/10 transition-colors">
          <div className="flex items-center gap-4">
            <Avatar
              src={member.avatar || undefined}
              name={member.name}
              size="lg"
              className="ring-2 ring-white/20"
            />
            <div className="flex-1 min-w-0">
              <h3 className="font-semibold text-white">{member.name}</h3>
              {member.tagline && (
                <p className="text-sm text-white/50 truncate">{member.tagline}</p>
              )}
            </div>
            <div className="flex items-center gap-6 text-sm text-white/50">
              {member.location && (
                <span className="flex items-center gap-1">
                  <MapPin className="w-4 h-4" />
                  {member.location}
                </span>
              )}
              {member.rating && (
                <span className="flex items-center gap-1">
                  <Star className="w-4 h-4 text-amber-400" />
                  {member.rating.toFixed(1)}
                </span>
              )}
              <span className="flex items-center gap-1">
                <Clock className="w-4 h-4" />
                {member.total_hours_given ?? 0}h
              </span>
            </div>
          </div>
        </GlassCard>
      </Link>
    );
  }

  return (
    <Link to={`/profile/${member.id}`}>
      <GlassCard className="p-5 hover:scale-[1.02] transition-transform text-center">
        <Avatar
          src={member.avatar || undefined}
          name={member.name}
          className="w-16 h-16 mx-auto ring-2 ring-white/20 mb-3"
        />
        <h3 className="font-semibold text-white">{member.name}</h3>
        {member.tagline && (
          <p className="text-sm text-white/50 line-clamp-1 mt-1">{member.tagline}</p>
        )}

        <div className="flex items-center justify-center gap-4 mt-4 text-xs text-white/40">
          {member.rating && (
            <span className="flex items-center gap-1">
              <Star className="w-3 h-3 text-amber-400" />
              {member.rating.toFixed(1)}
            </span>
          )}
          <span className="flex items-center gap-1">
            <Clock className="w-3 h-3" />
            {member.total_hours_given ?? 0}h
          </span>
        </div>

        {member.location && (
          <p className="text-xs text-white/40 mt-2 flex items-center justify-center gap-1">
            <MapPin className="w-3 h-3" />
            {member.location}
          </p>
        )}
      </GlassCard>
    </Link>
  );
});

export default MembersPage;
