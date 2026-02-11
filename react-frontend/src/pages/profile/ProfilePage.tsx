/**
 * Profile Page - User profile view
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Avatar, Tabs, Tab } from '@heroui/react';
import {
  User,
  MapPin,
  Calendar,
  UserPlus,
  UserCheck,
  MessageSquare,
  Edit,
  Star,
  Clock,
  ListTodo,
  Award,
  Settings,
  ArrowLeft,
  RefreshCw,
  AlertTriangle,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { useAuth, useFeature, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { User as UserType, Listing } from '@/types/api';

type ConnectionStatus = 'none' | 'pending_sent' | 'pending_received' | 'connected';

export function ProfilePage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user: currentUser, isAuthenticated } = useAuth();
  const hasGamification = useFeature('gamification');
  const toast = useToast();

  const [profile, setProfile] = useState<UserType | null>(null);
  const [listings, setListings] = useState<Listing[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState('about');
  const [connectionStatus, setConnectionStatus] = useState<ConnectionStatus>('none');
  const [connectionId, setConnectionId] = useState<number | null>(null);
  const [isConnecting, setIsConnecting] = useState(false);

  const isOwnProfile = !id || (currentUser && id === currentUser.id.toString());
  const profileId = id || currentUser?.id?.toString();

  const loadProfile = useCallback(async () => {
    if (!profileId) return;

    try {
      setIsLoading(true);
      setError(null);

      const requests: Promise<unknown>[] = [
        api.get<UserType>(`/v2/users/${profileId}`),
        api.get<Listing[]>(`/v2/users/${profileId}/listings?limit=6`),
      ];

      // Check connection status if viewing another user's profile
      if (isAuthenticated && currentUser && profileId !== currentUser.id.toString()) {
        requests.push(
          api.get<{ status: ConnectionStatus; connection_id?: number }>(`/v2/connections/status/${profileId}`)
        );
      }

      const results = await Promise.all(requests);
      const [profileRes, listingsRes, connectionRes] = results as [
        { success: boolean; data?: UserType },
        { success: boolean; data?: Listing[] },
        { success: boolean; data?: { status: ConnectionStatus; connection_id?: number } }?
      ];

      if (profileRes.success && profileRes.data) {
        setProfile(profileRes.data);
      } else {
        setError('Profile not found');
        return;
      }
      if (listingsRes.success && listingsRes.data) {
        setListings(listingsRes.data);
      }
      if (connectionRes?.success && connectionRes.data) {
        setConnectionStatus(connectionRes.data.status);
        setConnectionId(connectionRes.data.connection_id ?? null);
      }
    } catch (err) {
      logError('Failed to load profile', err);
      setError('Failed to load profile. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, [profileId, isAuthenticated, currentUser]);

  useEffect(() => {
    loadProfile();
  }, [loadProfile]);

  const handleConnect = useCallback(async () => {
    if (!profile?.id) return;

    try {
      setIsConnecting(true);

      if (connectionStatus === 'none') {
        // Send connection request
        const response = await api.post<{ connection_id: number }>('/v2/connections/request', {
          user_id: profile.id,
        });
        if (response.success) {
          setConnectionStatus('pending_sent');
          setConnectionId(response.data?.connection_id ?? null);
          toast.success('Request sent', 'Connection request sent successfully');
        } else {
          toast.error('Failed', response.error || 'Failed to send request');
        }
      } else if (connectionStatus === 'pending_received' && connectionId) {
        // Accept connection request
        const response = await api.post(`/v2/connections/${connectionId}/accept`);
        if (response.success) {
          setConnectionStatus('connected');
          toast.success('Connected', 'You are now connected');
        } else {
          toast.error('Failed', response.error || 'Failed to accept request');
        }
      } else if ((connectionStatus === 'pending_sent' || connectionStatus === 'connected') && connectionId) {
        // Cancel/Remove connection
        const response = await api.delete(`/v2/connections/${connectionId}`);
        if (response.success) {
          setConnectionStatus('none');
          setConnectionId(null);
          toast.info('Removed', 'Connection removed');
        } else {
          toast.error('Failed', response.error || 'Failed to remove connection');
        }
      }
    } catch (error) {
      logError('Connection action failed', error);
      toast.error('Error', 'Something went wrong');
    } finally {
      setIsConnecting(false);
    }
  }, [profile?.id, connectionStatus, connectionId, toast]);

  if (isLoading) {
    return <LoadingScreen message="Loading profile..." />;
  }

  // Error state with retry
  if (error && !profile) {
    return (
      <div className="max-w-4xl mx-auto">
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Profile</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <div className="flex justify-center gap-3">
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
              onPress={() => navigate(-1)}
            >
              Go Back
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={() => loadProfile()}
            >
              Try Again
            </Button>
          </div>
        </GlassCard>
      </div>
    );
  }

  // Profile not found
  if (!profile) {
    return (
      <EmptyState
        icon={<User className="w-12 h-12" aria-hidden="true" />}
        title="Profile not found"
        description="This user profile does not exist or has been removed"
        action={
          <Button
            variant="flat"
            className="bg-theme-elevated text-theme-primary"
            startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
            onPress={() => navigate(-1)}
          >
            Go Back
          </Button>
        }
      />
    );
  }

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.1 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="max-w-4xl mx-auto space-y-6"
    >
      {/* Profile Header */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <div className="flex flex-col sm:flex-row items-center sm:items-start gap-6">
            {/* Avatar */}
            <div className="relative">
              <Avatar
                src={resolveAvatarUrl(profile.avatar_url || profile.avatar)}
                name={profile.name}
                className="w-24 h-24 sm:w-32 sm:h-32 ring-4 ring-theme-default"
              />
              {hasGamification && profile.level && (
                <div className="absolute -bottom-2 -right-2 px-2 py-1 rounded-full bg-gradient-to-r from-amber-500 to-orange-500 text-white text-xs font-bold">
                  Lvl {profile.level}
                </div>
              )}
            </div>

            {/* Info */}
            <div className="flex-1 text-center sm:text-left">
              <h1 className="text-2xl sm:text-3xl font-bold text-theme-primary">{profile.name}</h1>
              {profile.tagline && (
                <p className="text-theme-muted mt-1">{profile.tagline}</p>
              )}

              {/* Meta */}
              <div className="flex flex-wrap justify-center sm:justify-start gap-4 mt-4 text-sm text-theme-subtle">
                {profile.location && (
                  <span className="flex items-center gap-1">
                    <MapPin className="w-4 h-4" aria-hidden="true" />
                    {profile.location}
                  </span>
                )}
                {profile.created_at && (
                  <span className="flex items-center gap-1">
                    <Calendar className="w-4 h-4" aria-hidden="true" />
                    Joined{' '}
                    <time dateTime={profile.created_at}>
                      {new Date(profile.created_at).toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}
                    </time>
                  </span>
                )}
                {profile.rating && (
                  <span className="flex items-center gap-1" aria-label={`Rating: ${profile.rating.toFixed(1)} out of 5`}>
                    <Star className="w-4 h-4 text-amber-400" aria-hidden="true" />
                    <span aria-hidden="true">{profile.rating.toFixed(1)}</span>
                  </span>
                )}
              </div>

              {/* Actions */}
              <div className="flex flex-wrap justify-center sm:justify-start gap-3 mt-6">
                {isOwnProfile ? (
                  <>
                    <Link to="/settings">
                      <Button
                        className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                        startContent={<Edit className="w-4 h-4" aria-hidden="true" />}
                      >
                        Edit Profile
                      </Button>
                    </Link>
                    <Link to="/settings">
                      <Button
                        variant="flat"
                        className="bg-theme-elevated text-theme-primary"
                        startContent={<Settings className="w-4 h-4" aria-hidden="true" />}
                      >
                        Settings
                      </Button>
                    </Link>
                  </>
                ) : (
                  <>
                    <Link to={`/messages?to=${profile.id}`}>
                      <Button
                        className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                        startContent={<MessageSquare className="w-4 h-4" aria-hidden="true" />}
                      >
                        Send Message
                      </Button>
                    </Link>
                    {isAuthenticated && (
                      <Button
                        variant="flat"
                        className={
                          connectionStatus === 'connected'
                            ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400'
                            : connectionStatus === 'pending_sent'
                            ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
                            : connectionStatus === 'pending_received'
                            ? 'bg-indigo-500/20 text-indigo-600 dark:text-indigo-400'
                            : 'bg-theme-elevated text-theme-primary'
                        }
                        startContent={
                          connectionStatus === 'connected' ? (
                            <UserCheck className="w-4 h-4" aria-hidden="true" />
                          ) : connectionStatus === 'pending_sent' ? (
                            <Clock className="w-4 h-4" aria-hidden="true" />
                          ) : connectionStatus === 'pending_received' ? (
                            <UserPlus className="w-4 h-4" aria-hidden="true" />
                          ) : (
                            <UserPlus className="w-4 h-4" aria-hidden="true" />
                          )
                        }
                        onPress={handleConnect}
                        isLoading={isConnecting}
                      >
                        {connectionStatus === 'connected'
                          ? 'Connected'
                          : connectionStatus === 'pending_sent'
                          ? 'Pending'
                          : connectionStatus === 'pending_received'
                          ? 'Accept'
                          : 'Connect'}
                      </Button>
                    )}
                  </>
                )}
              </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-3 gap-4 sm:gap-6">
              <div className="text-center">
                <div className="text-2xl font-bold text-theme-primary">{profile.total_hours_given ?? 0}</div>
                <div className="text-xs text-theme-subtle">Hours Given</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-theme-primary">{profile.total_hours_received ?? 0}</div>
                <div className="text-xs text-theme-subtle">Hours Received</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-theme-primary">{listings.length}</div>
                <div className="text-xs text-theme-subtle">Listings</div>
              </div>
            </div>
          </div>
        </GlassCard>
      </motion.div>

      {/* Tabs Content */}
      <motion.div variants={itemVariants}>
        <Tabs
          selectedKey={activeTab}
          onSelectionChange={(key) => setActiveTab(key as string)}
          aria-label="Profile sections"
          classNames={{
            tabList: 'bg-theme-elevated p-1 rounded-lg',
            cursor: 'bg-theme-hover',
            tab: 'text-theme-muted data-[selected=true]:text-theme-primary',
          }}
        >
          <Tab
            key="about"
            aria-label="About this user"
            title={
              <span className="flex items-center gap-2">
                <User className="w-4 h-4" aria-hidden="true" />
                About
              </span>
            }
          />
          <Tab
            key="listings"
            aria-label="User listings"
            title={
              <span className="flex items-center gap-2">
                <ListTodo className="w-4 h-4" aria-hidden="true" />
                Listings
              </span>
            }
          />
          {hasGamification && (
            <Tab
              key="achievements"
              aria-label="User achievements"
              title={
                <span className="flex items-center gap-2">
                  <Award className="w-4 h-4" aria-hidden="true" />
                  Achievements
                </span>
              }
            />
          )}
        </Tabs>

        <div className="mt-6">
          {activeTab === 'about' && (
            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-theme-primary mb-4">About</h2>
              {profile.bio ? (
                <p className="text-theme-muted whitespace-pre-wrap">{profile.bio}</p>
              ) : (
                <p className="text-theme-subtle italic">No bio added yet.</p>
              )}

              {profile.skills && profile.skills.length > 0 && (
                <div className="mt-6">
                  <h3 className="text-sm font-medium text-theme-muted mb-3">Skills</h3>
                  <div className="flex flex-wrap gap-2">
                    {profile.skills.map((skill, index) => (
                      <span
                        key={index}
                        className="px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-600 dark:text-indigo-300 text-sm"
                      >
                        {skill}
                      </span>
                    ))}
                  </div>
                </div>
              )}
            </GlassCard>
          )}

          {activeTab === 'listings' && (
            <div className="grid sm:grid-cols-2 gap-4" role="list" aria-label="User listings">
              {listings.length > 0 ? (
                listings.map((listing) => (
                  <Link
                    key={listing.id}
                    to={`/listings/${listing.id}`}
                    aria-label={`${listing.type === 'offer' ? 'Offering' : 'Requesting'}: ${listing.title}`}
                  >
                    <article role="listitem">
                      <GlassCard className="p-5 hover:scale-[1.02] transition-transform h-full">
                        <div className="flex items-center gap-2 mb-2">
                          <span className={`
                            text-xs px-2 py-1 rounded-full
                            ${listing.type === 'offer' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-amber-500/20 text-amber-600 dark:text-amber-400'}
                          `}>
                            {listing.type === 'offer' ? 'Offering' : 'Requesting'}
                          </span>
                        </div>
                        <h3 className="font-medium text-theme-primary mb-1">{listing.title}</h3>
                        <p className="text-sm text-theme-subtle line-clamp-2">{listing.description}</p>
                        <div className="flex items-center gap-2 mt-3 text-xs text-theme-subtle">
                          <Clock className="w-3 h-3" aria-hidden="true" />
                          {listing.hours_estimate ?? listing.estimated_hours ?? '—'}h
                        </div>
                      </GlassCard>
                    </article>
                  </Link>
                ))
              ) : (
                <div className="col-span-2">
                  <EmptyState
                    icon={<ListTodo className="w-12 h-12" aria-hidden="true" />}
                    title="No listings"
                    description={isOwnProfile ? "You haven't created any listings yet" : "This user hasn't created any listings"}
                    action={
                      isOwnProfile && (
                        <Link to="/listings/create">
                          <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                            Create Listing
                          </Button>
                        </Link>
                      )
                    }
                  />
                </div>
              )}
            </div>
          )}

          {activeTab === 'achievements' && (
            <GlassCard className="p-6">
              <EmptyState
                icon={<Award className="w-12 h-12" aria-hidden="true" />}
                title="Achievements coming soon"
                description="Check back later to see badges and achievements"
              />
            </GlassCard>
          )}
        </div>
      </motion.div>
    </motion.div>
  );
}

export default ProfilePage;
