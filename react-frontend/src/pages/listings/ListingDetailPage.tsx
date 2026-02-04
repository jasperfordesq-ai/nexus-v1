/**
 * Listing Detail Page - View single listing
 */

import { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Avatar } from '@heroui/react';
import {
  ArrowLeft,
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
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { useAuth } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { Listing } from '@/types/api';

export function ListingDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user, isAuthenticated } = useAuth();

  const [listing, setListing] = useState<Listing | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  useEffect(() => {
    loadListing();
  }, [id]);

  async function loadListing() {
    if (!id) return;

    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<Listing>(`/v2/listings/${id}`);
      if (response.success && response.data) {
        setListing(response.data);
      } else {
        setError('Listing not found or has been removed');
      }
    } catch (err) {
      setError('Listing not found or has been removed');
    } finally {
      setIsLoading(false);
    }
  }

  async function handleDelete() {
    if (!listing || !window.confirm('Are you sure you want to delete this listing?')) return;

    try {
      setIsDeleting(true);
      await api.delete(`/v2/listings/${listing.id}`);
      navigate('/listings', { replace: true });
    } catch (err) {
      logError('Failed to delete listing', err);
    } finally {
      setIsDeleting(false);
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
          <Link to="/listings">
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
      {/* Back Button */}
      <button
        onClick={() => navigate(-1)}
        className="flex items-center gap-2 text-white/60 hover:text-white transition-colors"
      >
        <ArrowLeft className="w-4 h-4" />
        Back to listings
      </button>

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
              <span className="text-sm px-3 py-1.5 rounded-full bg-white/10 text-white/60 flex items-center gap-1">
                <Tag className="w-3 h-3" />
                {listing.category?.name || listing.category_name}
              </span>
            )}
          </div>

          {isOwner && (
            <div className="flex gap-2">
              <Link to={`/listings/edit/${listing.id}`}>
                <Button
                  size="sm"
                  variant="flat"
                  className="bg-white/5 text-white"
                  startContent={<Edit className="w-4 h-4" />}
                >
                  Edit
                </Button>
              </Link>
              <Button
                size="sm"
                variant="flat"
                className="bg-red-500/10 text-red-400"
                startContent={<Trash2 className="w-4 h-4" />}
                onClick={handleDelete}
                isLoading={isDeleting}
              >
                Delete
              </Button>
            </div>
          )}
        </div>

        {/* Title */}
        <h1 className="text-3xl font-bold text-white mb-4">{listing.title}</h1>

        {/* Meta Grid */}
        <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
          <div className="flex items-center gap-3 text-white/60">
            <div className="p-2 rounded-lg bg-indigo-500/20">
              <Clock className="w-5 h-5 text-indigo-400" />
            </div>
            <div>
              <div className="text-xs text-white/40">Duration</div>
              <div className="text-white">{listing.hours_estimate ?? listing.estimated_hours ?? '—'} hours</div>
            </div>
          </div>

          {listing.location && (
            <div className="flex items-center gap-3 text-white/60">
              <div className="p-2 rounded-lg bg-emerald-500/20">
                <MapPin className="w-5 h-5 text-emerald-400" />
              </div>
              <div>
                <div className="text-xs text-white/40">Location</div>
                <div className="text-white truncate">{listing.location}</div>
              </div>
            </div>
          )}

          <div className="flex items-center gap-3 text-white/60">
            <div className="p-2 rounded-lg bg-amber-500/20">
              <Calendar className="w-5 h-5 text-amber-400" />
            </div>
            <div>
              <div className="text-xs text-white/40">Posted</div>
              <div className="text-white">
                {new Date(listing.created_at).toLocaleDateString()}
              </div>
            </div>
          </div>

          <div className="flex items-center gap-3 text-white/60">
            <div className="p-2 rounded-lg bg-purple-500/20">
              <Tag className="w-5 h-5 text-purple-400" />
            </div>
            <div>
              <div className="text-xs text-white/40">Status</div>
              <div className="text-white capitalize">{listing.status}</div>
            </div>
          </div>
        </div>

        {/* Description */}
        <div className="mb-8">
          <h2 className="text-lg font-semibold text-white mb-3">Description</h2>
          <div className="prose prose-invert max-w-none">
            <p className="text-white/70 whitespace-pre-wrap">{listing.description}</p>
          </div>
        </div>

        {/* Action Buttons */}
        {isAuthenticated && !isOwner && (
          <div className="flex flex-wrap gap-3 pt-6 border-t border-white/10">
            <Link to={`/messages?to=${listing.user_id}&listing=${listing.id}`} className="flex-1 sm:flex-none">
              <Button
                className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<MessageSquare className="w-4 h-4" />}
              >
                Send Message
              </Button>
            </Link>
            <Button
              variant="flat"
              className="flex-1 sm:flex-none bg-white/5 text-white"
              startContent={<Heart className="w-4 h-4" />}
            >
              Save
            </Button>
            <Button
              variant="flat"
              className="flex-1 sm:flex-none bg-white/5 text-white"
              startContent={<Share2 className="w-4 h-4" />}
            >
              Share
            </Button>
          </div>
        )}
      </GlassCard>

      {/* User Card */}
      {(listing.user || listing.author_name) && (
        <GlassCard className="p-6">
          <h2 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
            <User className="w-5 h-5 text-indigo-400" />
            {listing.type === 'offer' ? 'Offered by' : 'Requested by'}
          </h2>

          <div className="flex items-center gap-4">
            <Avatar
              src={listing.user?.avatar || listing.author_avatar || undefined}
              name={listing.user?.name || listing.author_name || `${listing.user?.first_name ?? ''} ${listing.user?.last_name ?? ''}`.trim()}
              size="lg"
              className="ring-2 ring-white/20"
            />
            <div className="flex-1">
              <h3 className="font-semibold text-white">
                {listing.user?.name || listing.author_name || `${listing.user?.first_name ?? ''} ${listing.user?.last_name ?? ''}`.trim()}
              </h3>
              {listing.user?.tagline && (
                <p className="text-white/60 text-sm">{listing.user.tagline}</p>
              )}
            </div>
            {listing.user && (
              <Link to={`/profile/${listing.user.id}`}>
                <Button
                  variant="flat"
                  className="bg-white/5 text-white"
                >
                  View Profile
                </Button>
              </Link>
            )}
          </div>
        </GlassCard>
      )}
    </motion.div>
  );
}

export default ListingDetailPage;
