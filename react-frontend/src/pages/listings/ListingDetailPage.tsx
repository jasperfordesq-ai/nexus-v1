/**
 * Listing Detail Page - View single listing
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Avatar } from '@heroui/react';
import {
  Clock,
  MapPin,
  Calendar,
  User,
  Tag,
  MessageSquare,
  Heart,
  Share2,
  Edit,
  Trash2,
  AlertCircle,
  ArrowRightLeft,
  Bookmark,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { LocationMapCard } from '@/components/location';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { Listing, ExchangeConfig } from '@/types/api';

export function ListingDetailPage() {
  usePageTitle('Listing');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user, isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [listing, setListing] = useState<Listing | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [isSaved, setIsSaved] = useState(false);
  const [exchangeConfig, setExchangeConfig] = useState<ExchangeConfig | null>(null);

  const loadExchangeConfig = useCallback(async () => {
    try {
      const response = await api.get<ExchangeConfig>('/v2/exchanges/config');
      if (response.success && response.data) {
        setExchangeConfig(response.data);
      }
    } catch {
      // Exchange workflow may not be enabled
    }
  }, []);

  const loadListing = useCallback(async () => {
    if (!id) return;

    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<Listing>(`/v2/listings/${id}`);
      if (response.success && response.data) {
        setListing(response.data);
        setIsSaved(response.data.is_favorited ?? false);
      } else {
        setError('Listing not found or has been removed');
      }
    } catch (err) {
      logError('Failed to load listing', err);
      setError('Listing not found or has been removed');
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadListing();
    loadExchangeConfig();
  }, [loadListing, loadExchangeConfig]);

  async function handleDelete() {
    if (!listing || !window.confirm('Are you sure you want to delete this listing?')) return;

    try {
      setIsDeleting(true);
      await api.delete(`/v2/listings/${listing.id}`);
      toast.success('Listing deleted');
      navigate(tenantPath('/listings'), { replace: true });
    } catch (err) {
      logError('Failed to delete listing', err);
      toast.error('Failed to delete', 'Please try again later');
    } finally {
      setIsDeleting(false);
    }
  }

  function handleSave() {
    // Toggle saved state locally (API endpoint not yet implemented)
    setIsSaved(!isSaved);
    if (!isSaved) {
      toast.success('Listing saved', 'You can find it in your saved items');
    } else {
      toast.info('Removed from saved');
    }
  }

  async function handleShare() {
    if (!listing) return;

    const shareData = {
      title: listing.title,
      text: listing.description?.slice(0, 100) + (listing.description && listing.description.length > 100 ? '...' : ''),
      url: window.location.href,
    };

    // Try native Web Share API first (mobile/PWA)
    if (navigator.share && navigator.canShare?.(shareData)) {
      try {
        await navigator.share(shareData);
      } catch (err) {
        // User cancelled or share failed - that's ok
        if ((err as Error).name !== 'AbortError') {
          logError('Share failed', err);
        }
      }
    } else {
      // Fallback: copy link to clipboard
      try {
        await navigator.clipboard.writeText(window.location.href);
        toast.success('Link copied', 'Share this listing with anyone');
      } catch {
        toast.error('Could not copy link');
      }
    }
  }

  const isOwner = user && listing && user.id === listing.user_id;

  if (isLoading) {
    return <LoadingScreen message="Loading listing..." />;
  }

  if (error || !listing) {
    return (
      <EmptyState
        icon={<AlertCircle className="w-12 h-12" />}
        title="Listing Not Found"
        description={error || 'The listing you are looking for does not exist'}
        action={
          <Link to={tenantPath('/listings')}>
            <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
              Browse Listings
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
      {/* Breadcrumbs */}
      <Breadcrumbs items={[
        { label: 'Listings', href: '/listings' },
        { label: listing?.title || 'Listing' },
      ]} />

      {/* Main Content */}
      <GlassCard className="p-6 sm:p-8">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
          <div className="flex items-center gap-3">
            <span className={`
              text-sm px-3 py-1.5 rounded-full font-medium
              ${listing.type === 'offer' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'}
            `}>
              {listing.type === 'offer' ? 'Offering' : 'Requesting'}
            </span>
            {(listing.category || listing.category_name) && (
              <span className="text-sm px-3 py-1.5 rounded-full bg-theme-hover text-theme-muted flex items-center gap-1">
                <Tag className="w-3 h-3" aria-hidden="true" />
                {listing.category?.name || listing.category_name}
              </span>
            )}
          </div>

          {isOwner && (
            <div className="flex gap-2">
              <Link to={tenantPath(`/listings/edit/${listing.id}`)}>
                <Button
                  size="sm"
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                  startContent={<Edit className="w-4 h-4" aria-hidden="true" />}
                >
                  Edit
                </Button>
              </Link>
              <Button
                size="sm"
                variant="flat"
                className="bg-red-500/10 text-red-400"
                startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                onClick={handleDelete}
                isLoading={isDeleting}
              >
                Delete
              </Button>
            </div>
          )}
        </div>

        {/* Title */}
        <h1 className="text-3xl font-bold text-theme-primary mb-4">{listing.title}</h1>

        {/* Meta Grid - Top Row */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
          <div className="flex items-center gap-3 text-theme-muted">
            <div className="p-2 rounded-lg bg-indigo-500/20" aria-hidden="true">
              <Clock className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
            </div>
            <div>
              <div className="text-xs text-theme-subtle">Duration</div>
              <div className="text-theme-primary">
                {(listing.hours_estimate ?? listing.estimated_hours)
                  ? `${listing.hours_estimate ?? listing.estimated_hours} hours`
                  : 'Flexible'}
              </div>
            </div>
          </div>

          <div className="flex items-center gap-3 text-theme-muted">
            <div className="p-2 rounded-lg bg-amber-500/20" aria-hidden="true">
              <Calendar className="w-5 h-5 text-amber-600 dark:text-amber-400" />
            </div>
            <div>
              <div className="text-xs text-theme-subtle">Posted</div>
              <div className="text-theme-primary">
                {new Date(listing.created_at).toLocaleDateString()}
              </div>
            </div>
          </div>

          <div className="flex items-center gap-3 text-theme-muted">
            <div className="p-2 rounded-lg bg-purple-500/20" aria-hidden="true">
              <Tag className="w-5 h-5 text-purple-600 dark:text-purple-400" />
            </div>
            <div>
              <div className="text-xs text-theme-subtle">Status</div>
              <div className="text-theme-primary capitalize">{listing.status}</div>
            </div>
          </div>
        </div>

        {/* Location - Separate Row to prevent text bleeding */}
        {listing.location && (
          <div className="flex items-center gap-3 text-theme-muted mb-8">
            <div className="p-2 rounded-lg bg-emerald-500/20" aria-hidden="true">
              <MapPin className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div className="min-w-0 flex-1">
              <div className="text-xs text-theme-subtle">Location</div>
              <div className="text-theme-primary">{listing.location}</div>
            </div>
          </div>
        )}

        {/* Location Map */}
        {listing.location && listing.latitude && listing.longitude && (
          <LocationMapCard
            title="Service Location"
            locationText={listing.location}
            markers={[{
              id: listing.id,
              lat: listing.latitude,
              lng: listing.longitude,
              title: listing.title,
            }]}
            center={{ lat: listing.latitude, lng: listing.longitude }}
            mapHeight="250px"
            zoom={15}
            className="mt-6"
          />
        )}

        {/* Spacer if no location */}
        {!listing.location && <div className="mb-4" />}

        {/* Description */}
        <div className="mb-8">
          <h2 className="text-lg font-semibold text-theme-primary mb-3">Description</h2>
          <div className="prose prose-invert max-w-none">
            <p className="text-theme-muted whitespace-pre-wrap">{listing.description}</p>
          </div>
        </div>

        {/* Action Buttons */}
        {isAuthenticated && !isOwner && (
          <div className="flex flex-wrap gap-3 pt-6 border-t border-theme-default">
            {exchangeConfig?.exchange_workflow_enabled ? (
              <Link to={tenantPath(`/listings/${listing.id}/request-exchange`)} className="flex-1 sm:flex-none">
                <Button
                  className="w-full bg-gradient-to-r from-emerald-500 to-teal-600 text-white"
                  startContent={<ArrowRightLeft className="w-4 h-4" aria-hidden="true" />}
                >
                  Request Exchange
                </Button>
              </Link>
            ) : (
              <Link to={tenantPath(`/messages?to=${listing.user_id}&listing=${listing.id}`)} className="flex-1 sm:flex-none">
                <Button
                  className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  startContent={<MessageSquare className="w-4 h-4" aria-hidden="true" />}
                >
                  Send Message
                </Button>
              </Link>
            )}
            <Button
              variant="flat"
              className={`flex-1 sm:flex-none ${isSaved ? 'bg-indigo-500/20 text-indigo-400' : 'bg-theme-elevated text-theme-primary'}`}
              startContent={isSaved ? <Bookmark className="w-4 h-4 fill-current" aria-hidden="true" /> : <Heart className="w-4 h-4" aria-hidden="true" />}
              onClick={handleSave}
            >
              {isSaved ? 'Saved' : 'Save'}
            </Button>
            <Button
              variant="flat"
              className="flex-1 sm:flex-none bg-theme-elevated text-theme-primary"
              startContent={<Share2 className="w-4 h-4" aria-hidden="true" />}
              onClick={handleShare}
            >
              Share
            </Button>
          </div>
        )}
      </GlassCard>

      {/* User Card */}
      {(listing.user || listing.author_name) && (() => {
        const userName = listing.user?.name || listing.author_name || `${listing.user?.first_name ?? ''} ${listing.user?.last_name ?? ''}`.trim();
        const userId = listing.user?.id || listing.user_id;
        const userAvatar = resolveAvatarUrl(listing.user?.avatar || listing.author_avatar);

        return (
          <GlassCard className="p-6">
            <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
              <User className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
              {listing.type === 'offer' ? 'Offered by' : 'Requested by'}
            </h2>

            <Link
              to={tenantPath(`/profile/${userId}`)}
              className="flex items-center gap-4 group hover:bg-theme-hover rounded-lg p-2 -m-2 transition-colors"
            >
              <Avatar
                src={userAvatar}
                name={userName}
                size="lg"
                className="ring-2 ring-white/20 group-hover:ring-indigo-500/50 transition-all"
              />
              <div className="flex-1 min-w-0">
                <h3 className="font-semibold text-theme-primary group-hover:text-indigo-400 transition-colors">
                  {userName}
                </h3>
                {listing.user?.tagline && (
                  <p className="text-theme-muted text-sm truncate">{listing.user.tagline}</p>
                )}
                <p className="text-xs text-theme-subtle mt-1">Click to view profile</p>
              </div>
            </Link>
          </GlassCard>
        );
      })()}
    </motion.div>
  );
}

export default ListingDetailPage;
