/**
 * Profile Page - User profile view
 */

import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Avatar, Tabs, Tab } from '@heroui/react';
import {
  User,
  MapPin,
  Calendar,
  Mail,
  MessageSquare,
  Edit,
  Star,
  Clock,
  ListTodo,
  Award,
  Settings,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { useAuth, useFeature } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { User as UserType, Listing } from '@/types/api';

export function ProfilePage() {
  const { id } = useParams<{ id: string }>();
  const { user: currentUser } = useAuth();
  const hasGamification = useFeature('gamification');

  const [profile, setProfile] = useState<UserType | null>(null);
  const [listings, setListings] = useState<Listing[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [activeTab, setActiveTab] = useState('about');

  const isOwnProfile = !id || (currentUser && id === currentUser.id.toString());
  const profileId = id || currentUser?.id?.toString();

  useEffect(() => {
    if (profileId) {
      loadProfile(profileId);
    }
  }, [profileId]);

  async function loadProfile(userId: string) {
    try {
      setIsLoading(true);
      const [profileRes, listingsRes] = await Promise.all([
        api.get<UserType>(`/v2/users/${userId}`),
        api.get<Listing[]>(`/v2/users/${userId}/listings?limit=6`),
      ]);

      if (profileRes.success && profileRes.data) {
        setProfile(profileRes.data);
      }
      if (listingsRes.success && listingsRes.data) {
        setListings(listingsRes.data);
      }
    } catch (error) {
      logError('Failed to load profile', error);
    } finally {
      setIsLoading(false);
    }
  }

  if (isLoading) {
    return <LoadingScreen message="Loading profile..." />;
  }

  if (!profile) {
    return (
      <EmptyState
        icon={<User className="w-12 h-12" />}
        title="Profile not found"
        description="This user profile does not exist or has been removed"
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
                src={profile.avatar || undefined}
                name={profile.name}
                className="w-24 h-24 sm:w-32 sm:h-32 ring-4 ring-white/20"
              />
              {hasGamification && profile.level && (
                <div className="absolute -bottom-2 -right-2 px-2 py-1 rounded-full bg-gradient-to-r from-amber-500 to-orange-500 text-white text-xs font-bold">
                  Lvl {profile.level}
                </div>
              )}
            </div>

            {/* Info */}
            <div className="flex-1 text-center sm:text-left">
              <h1 className="text-2xl sm:text-3xl font-bold text-white">{profile.name}</h1>
              {profile.tagline && (
                <p className="text-white/60 mt-1">{profile.tagline}</p>
              )}

              {/* Meta */}
              <div className="flex flex-wrap justify-center sm:justify-start gap-4 mt-4 text-sm text-white/50">
                {profile.location && (
                  <span className="flex items-center gap-1">
                    <MapPin className="w-4 h-4" />
                    {profile.location}
                  </span>
                )}
                {profile.created_at && (
                  <span className="flex items-center gap-1">
                    <Calendar className="w-4 h-4" />
                    Joined {new Date(profile.created_at).toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}
                  </span>
                )}
                {profile.rating && (
                  <span className="flex items-center gap-1">
                    <Star className="w-4 h-4 text-amber-400" />
                    {profile.rating.toFixed(1)}
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
                        startContent={<Edit className="w-4 h-4" />}
                      >
                        Edit Profile
                      </Button>
                    </Link>
                    <Link to="/settings">
                      <Button
                        variant="flat"
                        className="bg-white/5 text-white"
                        startContent={<Settings className="w-4 h-4" />}
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
                        startContent={<MessageSquare className="w-4 h-4" />}
                      >
                        Send Message
                      </Button>
                    </Link>
                    <Button
                      variant="flat"
                      className="bg-white/5 text-white"
                      startContent={<Mail className="w-4 h-4" />}
                    >
                      Connect
                    </Button>
                  </>
                )}
              </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-3 gap-4 sm:gap-6">
              <div className="text-center">
                <div className="text-2xl font-bold text-white">{profile.total_hours_given ?? 0}</div>
                <div className="text-xs text-white/50">Hours Given</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-white">{profile.total_hours_received ?? 0}</div>
                <div className="text-xs text-white/50">Hours Received</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-white">{listings.length}</div>
                <div className="text-xs text-white/50">Listings</div>
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
          classNames={{
            tabList: 'bg-white/5 p-1 rounded-lg',
            cursor: 'bg-white/10',
            tab: 'text-white/60 data-[selected=true]:text-white',
          }}
        >
          <Tab
            key="about"
            title={
              <span className="flex items-center gap-2">
                <User className="w-4 h-4" />
                About
              </span>
            }
          />
          <Tab
            key="listings"
            title={
              <span className="flex items-center gap-2">
                <ListTodo className="w-4 h-4" />
                Listings
              </span>
            }
          />
          {hasGamification && (
            <Tab
              key="achievements"
              title={
                <span className="flex items-center gap-2">
                  <Award className="w-4 h-4" />
                  Achievements
                </span>
              }
            />
          )}
        </Tabs>

        <div className="mt-6">
          {activeTab === 'about' && (
            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-white mb-4">About</h2>
              {profile.bio ? (
                <p className="text-white/70 whitespace-pre-wrap">{profile.bio}</p>
              ) : (
                <p className="text-white/40 italic">No bio added yet.</p>
              )}

              {profile.skills && profile.skills.length > 0 && (
                <div className="mt-6">
                  <h3 className="text-sm font-medium text-white/80 mb-3">Skills</h3>
                  <div className="flex flex-wrap gap-2">
                    {profile.skills.map((skill, index) => (
                      <span
                        key={index}
                        className="px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-300 text-sm"
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
            <div className="grid sm:grid-cols-2 gap-4">
              {listings.length > 0 ? (
                listings.map((listing) => (
                  <Link key={listing.id} to={`/listings/${listing.id}`}>
                    <GlassCard className="p-5 hover:scale-[1.02] transition-transform h-full">
                      <div className="flex items-center gap-2 mb-2">
                        <span className={`
                          text-xs px-2 py-1 rounded-full
                          ${listing.type === 'offer' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'}
                        `}>
                          {listing.type === 'offer' ? 'Offering' : 'Requesting'}
                        </span>
                      </div>
                      <h3 className="font-medium text-white mb-1">{listing.title}</h3>
                      <p className="text-sm text-white/50 line-clamp-2">{listing.description}</p>
                      <div className="flex items-center gap-2 mt-3 text-xs text-white/40">
                        <Clock className="w-3 h-3" />
                        {listing.hours_estimate ?? listing.estimated_hours ?? '—'}h
                      </div>
                    </GlassCard>
                  </Link>
                ))
              ) : (
                <div className="col-span-2">
                  <EmptyState
                    icon={<ListTodo className="w-12 h-12" />}
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
                icon={<Award className="w-12 h-12" />}
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
