// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Listing Detail Page - View single listing
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import { Button, Avatar, Chip, useDisclosure } from '@heroui/react';
import {
  Clock,
  MapPin,
  Calendar,
  User,
  Tag,
  MessageSquare,
  Heart,
  Edit,
  Trash2,
  AlertCircle,
  ArrowRightLeft,
  Bookmark,
  RefreshCw,
  BarChart3,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { LocationMapCard } from '@/components/location';
import { CommentsSection, LikersModal, ShareButton } from '@/components/social';
import { ListingAnalyticsPanel } from '@/components/listings/ListingAnalyticsPanel';
import { FeaturedBadge } from '@/components/listings/FeaturedBadge';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle, useSocialInteractions } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, resolveAssetUrl } from '@/lib/helpers';
import type { Listing, ExchangeConfig } from '@/types/api';

export function ListingDetailPage() {
  const { t } = useTranslation('listings');
  usePageTitle(t('title'));
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
  const [isSaving, setIsSaving] = useState(false);
  const [exchangeConfig, setExchangeConfig] = useState<ExchangeConfig | null>(null);
  const [activeExchange, setActiveExchange] = useState<{ id: number; status: string; role: string; proposed_hours: number } | null>(null);
  const [showComments, setShowComments] = useState(false);
  const [isRenewing, setIsRenewing] = useState(false);
  const [showAnalytics, setShowAnalytics] = useState(false);

  // Social fields from API response (set after listing loads)
  const [socialInit, setSocialInit] = useState<{ liked: boolean; likes: number; comments: number }>({ liked: false, likes: 0, comments: 0 });

  // Social interactions hook
  const social = useSocialInteractions({
    targetType: 'listing',
    targetId: Number(id) || 0,
    initialLiked: socialInit.liked,
    initialLikesCount: socialInit.likes,
    initialCommentsCount: socialInit.comments,
  });

  // Likers modal
  const { isOpen: isLikersOpen, onOpen: onLikersOpen, onClose: onLikersClose } = useDisclosure();

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

  const checkActiveExchange = useCallback(async () => {
    if (!id || !isAuthenticated) return;
    try {
      const response = await api.get<{ id: number; status: string; role: string; proposed_hours: number } | null>(`/v2/exchanges/check?listing_id=${id}`);
      if (response.success && response.data) {
        setActiveExchange(response.data);
      }
    } catch {
      // Not critical
    }
  }, [id, isAuthenticated]);

  const loadListing = useCallback(async () => {
    if (!id) return;

    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<Listing>(`/v2/listings/${id}`);
      if (response.success && response.data) {
        setListing(response.data);
        setIsSaved(response.data.is_favorited ?? false);
        // Social fields come from the API but aren't on the Listing type
        const data = response.data as unknown as { is_liked?: boolean; likes_count?: number; comments_count?: number };
        setSocialInit({
          liked: data.is_liked ?? false,
          likes: data.likes_count ?? 0,
          comments: data.comments_count ?? 0,
        });
      } else {
        setError(t('not_found_error'));
      }
    } catch (err) {
      logError('Failed to load listing', err);
      setError(t('not_found_error'));
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadListing();
    loadExchangeConfig();
    checkActiveExchange();
  }, [loadListing, loadExchangeConfig, checkActiveExchange]);

  async function handleDelete() {
    if (!listing || !window.confirm(t('delete_confirm'))) return;

    try {
      setIsDeleting(true);
      await api.delete(`/v2/listings/${listing.id}`);
      toast.success(t('delete_success_title'));
      navigate(tenantPath('/listings'), { replace: true });
    } catch (err) {
      logError('Failed to delete listing', err);
      toast.error(t('delete_error_title'), t('error_retry'));
    } finally {
      setIsDeleting(false);
    }
  }

  const handleSave = async () => {
    if (!id || isSaving) return;
    const wasAlreadySaved = isSaved;
    setIsSaved(!wasAlreadySaved); // optimistic update
    setIsSaving(true);
    try {
      if (wasAlreadySaved) {
        await api.delete(`/v2/listings/${id}/save`);
        toast.info(t('unsave_title'));
      } else {
        await api.post(`/v2/listings/${id}/save`, {});
        toast.success(t('save_success_title'), t('save_success_subtitle'));
      }
    } catch (err) {
      logError('Failed to save listing', err);
      setIsSaved(wasAlreadySaved); // rollback on failure
    } finally {
      setIsSaving(false);
    }
  };

  const handleRenew = async () => {
    if (!listing || isRenewing) return;
    setIsRenewing(true);
    try {
      const response = await api.post<{ renewed: boolean; new_expires_at: string }>(`/v2/listings/${listing.id}/renew`, {});
      if (response.success) {
        toast.success(t('renew_success'));
        loadListing(); // Reload to get updated data
      }
    } catch (err) {
      logError('Failed to renew listing', err);
      toast.error(t('renew_error'));
    } finally {
      setIsRenewing(false);
    }
  };

  function toggleComments() {
    setShowComments((prev) => !prev);
    if (!social.commentsLoaded) {
      void social.loadComments();
    }
  }

  const isOwner = user && listing && user.id === listing.user_id;

  if (isLoading) {
    return <LoadingScreen message={t('loading')} />;
  }

  if (error || !listing) {
    return (
      <EmptyState
        icon={<AlertCircle className="w-12 h-12" />}
        title={t('not_found_title')}
        description={error || t('not_found_fallback')}
        action={
          <Link to={tenantPath('/listings')}>
            <Button className="bg-linear-to-r from-indigo-500 to-purple-600 text-white">
              {t('browse')}
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
        { label: t('title'), href: tenantPath('/listings') },
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
              {listing.type === 'offer' ? t('offering') : t('requesting')}
            </span>
            {listing.is_featured && <FeaturedBadge size="md" />}
            {(listing.category || listing.category_name) && (
              <span className="text-sm px-3 py-1.5 rounded-full bg-theme-hover text-theme-muted flex items-center gap-1">
                <Tag className="w-3 h-3" aria-hidden="true" />
                {listing.category?.name || listing.category_name}
              </span>
            )}
          </div>

          {isOwner && (
            <div className="flex flex-wrap gap-2">
              <Link to={tenantPath(`/listings/edit/${listing.id}`)}>
                <Button
                  size="sm"
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                  startContent={<Edit className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('detail_edit')}
                </Button>
              </Link>
              <Button
                size="sm"
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                startContent={<BarChart3 className="w-4 h-4" aria-hidden="true" />}
                onPress={() => setShowAnalytics(!showAnalytics)}
              >
                {t('detail_analytics')}
              </Button>
              {(listing.status === 'expired' || listing.expires_at) && (
                <Button
                  size="sm"
                  variant="flat"
                  className="bg-emerald-500/10 text-emerald-500"
                  startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
                  onPress={handleRenew}
                  isLoading={isRenewing}
                >
                  {listing.status === 'expired' ? t('detail_renew') : t('detail_extend')}
                </Button>
              )}
              <Button
                size="sm"
                variant="flat"
                className="bg-red-500/10 text-red-400"
                startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                onPress={handleDelete}
                isLoading={isDeleting}
              >
                {t('detail_delete')}
              </Button>
            </div>
          )}
        </div>

        {/* Listing Image */}
        {listing.image_url && (
          <div className="mb-6 -mx-6 sm:-mx-8 -mt-2">
            <img
              src={resolveAssetUrl(listing.image_url)}
              alt={listing.title}
              className="w-full h-48 sm:h-64 object-cover"
              loading="lazy"
            />
          </div>
        )}

        {/* Title */}
        <h1 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-4">{listing.title}</h1>

        {/* Meta Grid - Top Row */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-4">
          <div className="flex items-center gap-3 text-theme-muted">
            <div className="p-2 rounded-lg bg-indigo-500/20" aria-hidden="true">
              <Clock className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
            </div>
            <div>
              <div className="text-xs text-theme-subtle">{t('detail_duration')}</div>
              <div className="text-theme-primary">
                {(listing.hours_estimate ?? listing.estimated_hours)
                  ? t('detail_hours', { count: listing.hours_estimate ?? listing.estimated_hours })
                  : t('detail_flexible')}
              </div>
            </div>
          </div>

          <div className="flex items-center gap-3 text-theme-muted">
            <div className="p-2 rounded-lg bg-amber-500/20" aria-hidden="true">
              <Calendar className="w-5 h-5 text-amber-600 dark:text-amber-400" />
            </div>
            <div>
              <div className="text-xs text-theme-subtle">{t('detail_posted')}</div>
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
              <div className="text-xs text-theme-subtle">{t('detail_status')}</div>
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
              <div className="text-xs text-theme-subtle">{t('detail_location')}</div>
              <div className="text-theme-primary">{listing.location}</div>
            </div>
          </div>
        )}

        {/* Location Map */}
        {listing.location && listing.latitude && listing.longitude && (
          <LocationMapCard
            title={t('detail_service_location')}
            locationText={listing.location}
            markers={[{
              id: listing.id,
              lat: Number(listing.latitude),
              lng: Number(listing.longitude),
              title: listing.title,
            }]}
            center={{ lat: Number(listing.latitude), lng: Number(listing.longitude) }}
            mapHeight="250px"
            zoom={15}
            className="mt-6"
          />
        )}

        {/* Spacer if no location */}
        {!listing.location && <div className="mb-4" />}

        {/* Description */}
        <div className="mb-8">
          <h2 className="text-lg font-semibold text-theme-primary mb-3">{t('detail_description')}</h2>
          <div className="prose prose-invert max-w-none">
            <p className="text-theme-muted whitespace-pre-wrap wrap-break-word">{listing.description}</p>
          </div>
        </div>

        {/* Social Proof */}
        {(social.likesCount > 0 || social.commentsCount > 0) && (
          <div className="flex gap-4 text-sm text-theme-subtle mb-4">
            {social.likesCount > 0 && (
              <Button
                variant="light"
                size="sm"
                onPress={onLikersOpen}
                className="flex items-center gap-1.5 hover:text-theme-primary transition-colors h-auto p-0 min-w-0"
              >
                <span className="w-4 h-4 rounded-full bg-gradient-to-br from-rose-500 to-pink-500 flex items-center justify-center">
                  <Heart className="w-2.5 h-2.5 text-white fill-white" aria-hidden="true" />
                </span>
                {social.likesCount} {social.likesCount === 1 ? t('like', 'like') : t('likes', 'likes')}
              </Button>
            )}
            {social.commentsCount > 0 && (
              <Button
                variant="light"
                size="sm"
                onPress={toggleComments}
                className="hover:text-theme-primary transition-colors h-auto p-0 min-w-0"
              >
                {social.commentsCount} {social.commentsCount === 1 ? t('comment', 'comment') : t('comments', 'comments')}
              </Button>
            )}
          </div>
        )}

        {/* Action Buttons */}
        {isAuthenticated && !isOwner && (
          <div className="flex flex-wrap gap-2 sm:gap-3 pt-6 border-t border-theme-default">
            {exchangeConfig?.exchange_workflow_enabled ? (
              activeExchange ? (
                <Link to={tenantPath(`/exchanges/${activeExchange.id}`)} className="flex-1 sm:flex-none">
                  <Button
                    className="w-full bg-theme-elevated text-theme-primary"
                    startContent={<ArrowRightLeft className="w-4 h-4" aria-hidden="true" />}
                    endContent={
                      <Chip size="sm" variant="flat" color={
                        activeExchange.status === 'accepted' || activeExchange.status === 'in_progress' ? 'success' :
                        activeExchange.status === 'pending_provider' || activeExchange.status === 'pending_broker' ? 'warning' :
                        activeExchange.status === 'pending_confirmation' ? 'primary' :
                        'default'
                      }>
                        {activeExchange.status === 'pending_provider' ? t('exchange_status_pending_provider') :
                         activeExchange.status === 'pending_broker' ? t('exchange_status_pending_broker') :
                         activeExchange.status === 'accepted' ? t('exchange_status_accepted') :
                         activeExchange.status === 'in_progress' ? t('exchange_status_in_progress') :
                         activeExchange.status === 'pending_confirmation' ? t('exchange_status_pending_confirmation') :
                         activeExchange.status === 'disputed' ? t('exchange_status_disputed') :
                         activeExchange.status.replace(/_/g, ' ')}
                      </Chip>
                    }
                  >
                    {t('detail_exchange')}
                  </Button>
                </Link>
              ) : (
                <Link to={tenantPath(`/listings/${listing.id}/request-exchange`)} className="flex-1 sm:flex-none">
                  <Button
                    className="w-full bg-linear-to-r from-emerald-500 to-teal-600 text-white"
                    startContent={<ArrowRightLeft className="w-4 h-4" aria-hidden="true" />}
                  >
                    {t('detail_request_exchange')}
                  </Button>
                </Link>
              )
            ) : (
              <Link to={tenantPath(`/messages?to=${listing.user_id}&listing=${listing.id}`)} className="flex-1 sm:flex-none">
                <Button
                  className="w-full bg-linear-to-r from-indigo-500 to-purple-600 text-white"
                  startContent={<MessageSquare className="w-4 h-4" aria-hidden="true" />}
                >
                  {t('detail_send_message')}
                </Button>
              </Link>
            )}
            <Button
              variant="flat"
              className={`flex-1 sm:flex-none ${social.isLiked ? 'bg-rose-500/20 text-rose-500' : 'bg-theme-elevated text-theme-primary'}`}
              startContent={<Heart className={`w-4 h-4 ${social.isLiked ? 'fill-current' : ''}`} aria-hidden="true" />}
              onPress={() => void social.toggleLike()}
              isDisabled={social.isLiking}
            >
              {social.likesCount > 0 ? `${social.likesCount} ` : ''}{social.isLiked ? t('detail_liked', 'Liked') : t('detail_like', 'Like')}
            </Button>
            <Button
              variant="flat"
              className={`flex-1 sm:flex-none ${showComments ? 'bg-indigo-500/20 text-indigo-400' : 'bg-theme-elevated text-theme-primary'}`}
              startContent={<MessageSquare className="w-4 h-4" aria-hidden="true" />}
              onPress={toggleComments}
            >
              {social.commentsCount > 0 ? `${social.commentsCount} ` : ''}{t('detail_comments', 'Comments')}
            </Button>
            <Button
              variant="flat"
              className={`flex-1 sm:flex-none ${isSaved ? 'bg-indigo-500/20 text-indigo-400' : 'bg-theme-elevated text-theme-primary'}`}
              startContent={isSaved ? <Bookmark className="w-4 h-4 fill-current" aria-hidden="true" /> : <Bookmark className="w-4 h-4" aria-hidden="true" />}
              onPress={() => void handleSave()}
              isDisabled={isSaving}
            >
              {isSaved ? t('detail_saved') : t('detail_save')}
            </Button>
            <ShareButton
              shareToFeed={social.shareToFeed}
              title={listing.title}
              description={listing.description}
              isAuthenticated={isAuthenticated}
              className="flex-1 sm:flex-none"
            />
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
              {listing.type === 'offer' ? t('detail_offered_by') : t('detail_requested_by')}
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
                <p className="text-xs text-theme-subtle mt-1">{t('detail_view_profile')}</p>
              </div>
            </Link>
          </GlassCard>
        );
      })()}

      {/* Skill Tags */}
      {listing.skill_tags && listing.skill_tags.length > 0 && (
        <GlassCard className="p-4">
          <div className="flex items-center gap-2 mb-3">
            <Tag className="w-4 h-4 text-theme-muted" aria-hidden="true" />
            <span className="text-sm font-medium text-theme-muted">{t('detail_skills')}</span>
          </div>
          <div className="flex flex-wrap gap-2">
            {listing.skill_tags.map((tag) => (
              <span
                key={tag}
                className="text-xs px-2.5 py-1 rounded-full bg-primary/10 text-primary font-medium"
              >
                {tag}
              </span>
            ))}
          </div>
        </GlassCard>
      )}

      {/* Listing Analytics (owner only) */}
      {isOwner && showAnalytics && (
        <ListingAnalyticsPanel listingId={listing.id} />
      )}

      {/* Expiry / Renewal Info */}
      {listing.expires_at && (
        <GlassCard className="p-4">
          <div className="flex items-center gap-2 text-sm">
            <Calendar className="w-4 h-4 text-theme-muted" aria-hidden="true" />
            <span className="text-theme-muted">
              {listing.status === 'expired'
                ? t('detail_expired_on', { date: new Date(listing.expires_at).toLocaleDateString() })
                : t('detail_expires_on', { date: new Date(listing.expires_at).toLocaleDateString() })}
            </span>
            {listing.renewal_count !== undefined && listing.renewal_count > 0 && (
              <span className="text-xs text-theme-subtle">
                ({t('detail_renewed_count', { count: listing.renewal_count })})
              </span>
            )}
          </div>
        </GlassCard>
      )}

      {/* Comments Section — threaded with reactions, replies, edit, delete */}
      {showComments && (
        <GlassCard className="p-6">
          <CommentsSection
            comments={social.comments}
            commentsCount={social.commentsCount}
            commentsLoading={social.commentsLoading}
            commentsLoaded={social.commentsLoaded}
            loadComments={social.loadComments}
            submitComment={social.submitComment}
            editComment={social.editComment}
            deleteComment={social.deleteComment}
            toggleReaction={social.toggleReaction}
            searchMentions={social.searchMentions}
            isAuthenticated={isAuthenticated}
            currentUserId={user?.id}
            currentUserAvatar={user?.avatar ?? undefined}
            currentUserName={user?.first_name || user?.name}
          />
        </GlassCard>
      )}

      {/* Likers Modal */}
      <LikersModal
        isOpen={isLikersOpen}
        onClose={onLikersClose}
        loadLikers={social.loadLikers}
        likesCount={social.likesCount}
      />
    </motion.div>
  );
}

export default ListingDetailPage;
