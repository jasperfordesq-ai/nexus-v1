/**
 * Federation Members Page
 *
 * Browse and search members from partner communities.
 * Route: /federation/members
 */

import { useState, useEffect, useCallback, useRef, memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Input,
  Select,
  SelectItem,
  Chip,
  Avatar,
  Button,
  Spinner,
} from '@heroui/react';
import {
  Search,
  Globe,
  MapPin,
  MessageSquare,
  User,
  AlertTriangle,
  RefreshCw,
  Users,
  Compass,
  Car,
  Home,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { EmptyState } from '@/components/feedback';
import { useAuth, useTenant, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { resolveAvatarUrl } from '@/lib/helpers';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import type { FederatedMember, FederationPartner } from '@/types/api';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const ITEMS_PER_PAGE = 20;
const SEARCH_DEBOUNCE_MS = 300;

type ServiceReachFilter = 'all' | 'local_only' | 'remote_ok' | 'travel_ok';

const SERVICE_REACH_OPTIONS: { key: ServiceReachFilter; label: string; icon: typeof Home }[] = [
  { key: 'all', label: 'All', icon: Users },
  { key: 'local_only', label: 'Local Only', icon: Home },
  { key: 'remote_ok', label: 'Remote OK', icon: Compass },
  { key: 'travel_ok', label: 'Will Travel', icon: Car },
];

const SERVICE_REACH_LABELS: Record<string, string> = {
  local_only: 'Local Only',
  remote_ok: 'Remote OK',
  travel_ok: 'Will Travel',
};

const SERVICE_REACH_ICONS: Record<string, typeof Home> = {
  local_only: Home,
  remote_ok: Compass,
  travel_ok: Car,
};

// ─────────────────────────────────────────────────────────────────────────────
// Page Component
// ─────────────────────────────────────────────────────────────────────────────

export function FederationMembersPage() {
  usePageTitle('Federated Members');
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const { hasFeature, tenantPath } = useTenant();
  const toast = useToast();

  // Redirect if federation not enabled
  const federationEnabled = hasFeature('federation');
  useEffect(() => {
    if (!federationEnabled) {
      toast.warning('Federation is not enabled for your community.');
      navigate(tenantPath('/federation'), { replace: true });
    }
  }, [federationEnabled, navigate, toast]);

  // Data state
  const [members, setMembers] = useState<FederatedMember[]>([]);
  const [partners, setPartners] = useState<FederationPartner[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(true);
  const [totalCount, setTotalCount] = useState<number | null>(null);
  const [cursor, setCursor] = useState<string | undefined>(undefined);

  // Filter state
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const [selectedPartner, setSelectedPartner] = useState<string>('');
  const [serviceReach, setServiceReach] = useState<ServiceReachFilter>('all');
  const [skillsFilter, setSkillsFilter] = useState('');

  // Debounce ref
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Debounce search input
  useEffect(() => {
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }
    searchTimeoutRef.current = setTimeout(() => {
      setDebouncedQuery(searchQuery);
    }, SEARCH_DEBOUNCE_MS);

    return () => {
      if (searchTimeoutRef.current) {
        clearTimeout(searchTimeoutRef.current);
      }
    };
  }, [searchQuery]);

  // Load partners for dropdown
  useEffect(() => {
    if (!federationEnabled) return;

    async function fetchPartners() {
      try {
        const response = await api.get<FederationPartner[]>('/v2/federation/partners');
        if (response.success && response.data) {
          setPartners(response.data);
        }
      } catch (err) {
        logError('Failed to load federation partners', err);
      }
    }

    fetchPartners();
  }, [federationEnabled]);

  // Load members
  const loadMembers = useCallback(async (append = false) => {
    if (!federationEnabled) return;

    try {
      if (!append) {
        setIsLoading(true);
        setError(null);
      } else {
        setIsLoadingMore(true);
      }

      const params = new URLSearchParams();
      if (debouncedQuery) params.set('q', debouncedQuery);
      if (selectedPartner) params.set('partner_id', selectedPartner);
      if (serviceReach !== 'all') params.set('service_reach', serviceReach);
      if (skillsFilter.trim()) params.set('skills', skillsFilter.trim());
      params.set('per_page', ITEMS_PER_PAGE.toString());

      if (append && cursor) {
        params.set('cursor', cursor);
      }

      const response = await api.get<FederatedMember[]>(
        `/v2/federation/members?${params}`
      );

      if (response.success && response.data) {
        if (append) {
          setMembers((prev) => [...prev, ...response.data!]);
        } else {
          setMembers(response.data);
        }
        setHasMore(response.meta?.has_more ?? response.data.length >= ITEMS_PER_PAGE);
        setCursor(response.meta?.cursor ?? undefined);

        if (response.meta?.total_items !== undefined) {
          setTotalCount(response.meta.total_items);
        }
      } else {
        if (!append) {
          setError('Failed to load federated members. Please try again.');
        } else {
          toast.error('Failed to load more members');
        }
      }
    } catch (err) {
      logError('Failed to load federated members', err);
      if (!append) {
        setError('Failed to load federated members. Please try again.');
      } else {
        toast.error('Failed to load more members');
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [federationEnabled, debouncedQuery, selectedPartner, serviceReach, skillsFilter, cursor, toast]);

  // Load on mount and when filters change
  useEffect(() => {
    setCursor(undefined);
    setHasMore(true);
    loadMembers();
  }, [debouncedQuery, selectedPartner, serviceReach, skillsFilter]); // eslint-disable-line react-hooks/exhaustive-deps

  // Load more handler
  const handleLoadMore = useCallback(() => {
    if (isLoadingMore || !hasMore) return;
    loadMembers(true);
  }, [isLoadingMore, hasMore, loadMembers]);

  // Navigation handlers
  const handleViewProfile = useCallback((member: FederatedMember) => {
    navigate(tenantPath(`/federation/members/${member.id}`));
  }, [navigate]);

  const handleSendMessage = useCallback((member: FederatedMember) => {
    navigate(
      tenantPath(`/federation/messages?compose=true&to_user=${member.id}&to_tenant=${member.timebank.id}`)
    );
  }, [navigate, tenantPath]);

  // Animation variants
  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.06 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 12, scale: 0.97 },
    visible: { opacity: 1, y: 0, scale: 1 },
  };

  // Don't render if federation is disabled (redirect in progress)
  if (!federationEnabled) {
    return null;
  }

  return (
    <div className="space-y-6">
      {/* Breadcrumbs */}
      <Breadcrumbs
        items={[
          { label: 'Federation', href: tenantPath('/federation') },
          { label: 'Members' },
        ]}
      />

      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <Globe className="w-7 h-7 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
          Federated Members
        </h1>
        <p className="text-theme-muted mt-1">
          Discover members from partner communities
        </p>
      </div>

      {/* Filter Bar */}
      <GlassCard className="p-4">
        <div className="flex flex-col gap-4">
          {/* Row 1: Search + Partner dropdown */}
          <div className="flex flex-col lg:flex-row gap-4">
            <div className="flex-1">
              <Input
                placeholder="Search by name, skills, or location..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                aria-label="Search federated members"
                classNames={{
                  input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                  inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                }}
              />
            </div>

            <Select
              placeholder="All Communities"
              selectedKeys={selectedPartner ? [selectedPartner] : []}
              onChange={(e) => setSelectedPartner(e.target.value)}
              className="w-full lg:w-56"
              aria-label="Filter by partner community"
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                value: 'text-theme-primary',
              }}
              startContent={<Globe className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
            >
              {partners.map((partner) => (
                <SelectItem key={String(partner.id)}>{partner.name}</SelectItem>
              ))}
            </Select>
          </div>

          {/* Row 2: Service reach chips + Skills filter */}
          <div className="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
            <div className="flex flex-wrap gap-2" role="group" aria-label="Service reach filter">
              {SERVICE_REACH_OPTIONS.map((option) => {
                const isSelected = serviceReach === option.key;
                const Icon = option.icon;
                return (
                  <Chip
                    key={option.key}
                    variant={isSelected ? 'solid' : 'flat'}
                    className={
                      isSelected
                        ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white cursor-pointer'
                        : 'bg-theme-elevated text-theme-muted cursor-pointer hover:bg-theme-hover'
                    }
                    startContent={<Icon className="w-3.5 h-3.5" aria-hidden="true" />}
                    onClick={() => setServiceReach(option.key)}
                    aria-pressed={isSelected}
                  >
                    {option.label}
                  </Chip>
                );
              })}
            </div>

            <div className="flex-1 w-full sm:w-auto">
              <Input
                placeholder="Filter by skills (comma separated)"
                value={skillsFilter}
                onChange={(e) => setSkillsFilter(e.target.value)}
                size="sm"
                aria-label="Filter by skills"
                classNames={{
                  input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                  inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                }}
              />
            </div>
          </div>
        </div>
      </GlassCard>

      {/* Results Count */}
      {!isLoading && !error && totalCount !== null && (
        <div className="flex items-center justify-between">
          <Chip
            variant="flat"
            size="sm"
            className="bg-theme-elevated text-theme-muted"
          >
            Showing {members.length.toLocaleString()} of {totalCount.toLocaleString()} members
          </Chip>
        </div>
      )}

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h3 className="text-lg font-semibold text-theme-primary mb-2">
            Unable to Load Federated Members
          </h3>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadMembers()}
          >
            Try Again
          </Button>
        </GlassCard>
      )}

      {/* Loading State */}
      {isLoading && (
        <div className="flex items-center justify-center py-20">
          <Spinner size="lg" label="Loading federated members..." />
        </div>
      )}

      {/* Empty States */}
      {!isLoading && !error && members.length === 0 && (
        <>
          {partners.length === 0 ? (
            <EmptyState
              icon={<Globe className="w-12 h-12 text-theme-subtle" />}
              title="No Partner Communities"
              description="Your community hasn't connected with any partner timebanks yet."
            />
          ) : (
            <EmptyState
              icon={<Users className="w-12 h-12 text-theme-subtle" />}
              title="No Federated Members Found"
              description={
                debouncedQuery || skillsFilter || selectedPartner || serviceReach !== 'all'
                  ? 'No federated members match your search. Try adjusting your filters.'
                  : 'No federated members are available at this time.'
              }
            />
          )}
        </>
      )}

      {/* Members Grid */}
      {!isLoading && !error && members.length > 0 && (
        <>
          <motion.div
            variants={containerVariants}
            initial="hidden"
            animate="visible"
            className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4"
          >
            {members.map((member) => (
              <motion.div key={member.id} variants={itemVariants}>
                <FederatedMemberCard
                  member={member}
                  isAuthenticated={isAuthenticated}
                  onViewProfile={handleViewProfile}
                  onSendMessage={handleSendMessage}
                />
              </motion.div>
            ))}
          </motion.div>

          {/* Load More */}
          {hasMore && (
            <div className="pt-4 text-center">
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-muted"
                onPress={handleLoadMore}
                isLoading={isLoadingMore}
              >
                Load More
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Member Card Component
// ─────────────────────────────────────────────────────────────────────────────

interface FederatedMemberCardProps {
  member: FederatedMember;
  isAuthenticated: boolean;
  onViewProfile: (member: FederatedMember) => void;
  onSendMessage: (member: FederatedMember) => void;
}

const FederatedMemberCard = memo(function FederatedMemberCard({
  member,
  isAuthenticated,
  onViewProfile,
  onSendMessage,
}: FederatedMemberCardProps) {
  const displayName =
    member.name?.trim() ||
    `${member.first_name || ''} ${member.last_name || ''}`.trim() ||
    'Member';

  const skills = member.skills ?? [];
  const visibleSkills = skills.slice(0, 5);
  const remainingSkillsCount = skills.length - visibleSkills.length;

  const reachKey = member.service_reach ?? 'local_only';
  const ReachIcon = SERVICE_REACH_ICONS[reachKey] ?? Home;
  const reachLabel = SERVICE_REACH_LABELS[reachKey] ?? 'Local Only';

  return (
    <GlassCard className="p-5 flex flex-col h-full">
      {/* Avatar + Community Badge */}
      <div className="flex items-start gap-4 mb-3">
        <div className="relative flex-shrink-0">
          <Avatar
            src={resolveAvatarUrl(member.avatar)}
            name={displayName}
            className="w-14 h-14 ring-2 ring-theme-muted/20"
          />
          {/* Community badge overlay */}
          <div
            className="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-indigo-500 flex items-center justify-center ring-2 ring-white dark:ring-gray-900"
            title={member.timebank.name}
          >
            <Globe className="w-3 h-3 text-white" aria-hidden="true" />
          </div>
        </div>

        <div className="flex-1 min-w-0">
          <h3 className="font-semibold text-theme-primary text-lg leading-tight truncate">
            {displayName}
          </h3>
          <Chip
            size="sm"
            variant="flat"
            className="mt-1 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
            startContent={<Globe className="w-3 h-3" aria-hidden="true" />}
          >
            {member.timebank.name}
          </Chip>
        </div>
      </div>

      {/* Bio */}
      {member.bio && (
        <p className="text-sm text-theme-muted line-clamp-2 mb-3">
          {member.bio}
        </p>
      )}

      {/* Skills */}
      {visibleSkills.length > 0 && (
        <div className="flex flex-wrap gap-1.5 mb-3">
          {visibleSkills.map((skill) => (
            <Chip
              key={skill}
              size="sm"
              variant="flat"
              className="bg-theme-elevated text-theme-subtle text-xs"
            >
              {skill}
            </Chip>
          ))}
          {remainingSkillsCount > 0 && (
            <Chip
              size="sm"
              variant="flat"
              className="bg-theme-elevated text-theme-subtle text-xs"
            >
              +{remainingSkillsCount} more
            </Chip>
          )}
        </div>
      )}

      {/* Location + Service Reach */}
      <div className="flex flex-wrap items-center gap-3 text-sm text-theme-subtle mb-4 mt-auto">
        {member.location && (
          <span className="flex items-center gap-1">
            <MapPin className="w-3.5 h-3.5" aria-hidden="true" />
            <span className="truncate max-w-[140px]">{member.location}</span>
          </span>
        )}
        <span className="flex items-center gap-1">
          <ReachIcon className="w-3.5 h-3.5" aria-hidden="true" />
          <span>{reachLabel}</span>
        </span>
      </div>

      {/* Action Buttons */}
      <div className="flex gap-2 pt-2 border-t border-theme-default">
        <Button
          size="sm"
          variant="flat"
          className="flex-1 bg-theme-elevated text-theme-primary hover:bg-theme-hover"
          startContent={<User className="w-3.5 h-3.5" aria-hidden="true" />}
          onPress={() => onViewProfile(member)}
        >
          View Profile
        </Button>
        {isAuthenticated && member.messaging_enabled && (
          <Button
            size="sm"
            className="flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<MessageSquare className="w-3.5 h-3.5" aria-hidden="true" />}
            onPress={() => onSendMessage(member)}
          >
            Send Message
          </Button>
        )}
      </div>
    </GlassCard>
  );
});

export default FederationMembersPage;
