// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Listing Detail Page - View single listing
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Helmet } from 'react-helmet-async';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import { Button, Avatar, Chip, useDisclosure, Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, RadioGroup, Radio, Textarea } from '@heroui/react';
import Clock from 'lucide-react/icons/clock';
import MapPin from 'lucide-react/icons/map-pin';
import Calendar from 'lucide-react/icons/calendar';
import User from 'lucide-react/icons/user';
import Tag from 'lucide-react/icons/tag';
import MessageSquare from 'lucide-react/icons/message-square';
import Heart from 'lucide-react/icons/heart';
import Edit from 'lucide-react/icons/square-pen';
import Trash2 from 'lucide-react/icons/trash-2';
import AlertCircle from 'lucide-react/icons/circle-alert';
import ArrowRightLeft from 'lucide-react/icons/arrow-right-left';
import Bookmark from 'lucide-react/icons/bookmark';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import BarChart3 from 'lucide-react/icons/chart-column';
import Flag from 'lucide-react/icons/flag';
import Monitor from 'lucide-react/icons/monitor';
import HelpCircle from 'lucide-react/icons/circle-help';
import Star from 'lucide-react/icons/star';
import { GlassCard, ImagePlaceholder } from '@/components/ui';
import { SafeHtml } from '@/components/ui/SafeHtml';
import { Breadcrumbs } from '@/components/navigation';
import { LoadingScreen, EmptyState, ErrorBoundary } from '@/components/feedback';
import { PageMeta } from '@/components/seo/PageMeta';
import { LocationMapCard } from '@/components/location';
import { CommentsSection, LikersModal, ShareButton } from '@/components/social';
import { ListingAnalyticsPanel } from '@/components/listings/ListingAnalyticsPanel';
import { FeaturedBadge } from '@/components/listings/FeaturedBadge';
import { VerificationBadgeRow } from '@/components/verification/VerificationBadge';
import { TranslateButton } from '@/components/i18n/TranslateButton';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle, useSocialInteractions } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, resolveAssetUrl } from '@/lib/helpers';
import type { Listing, ListingDetail, ExchangeConfig } from '@/types/api';

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
  const [showFullDesc, setShowFullDesc] = useState(false);
  const [translatedDesc, setTranslatedDesc] = useState<string | null>(null);
  const [isReported, setIsReported] = useState(false);
  const [isReporting, setIsReporting] = useState(false);
  const [imageError, setImageError] = useState(false);
  const [reportReason, setReportReason] = useState<string>('');
  const [reportDetails, setReportDetails] = useState('');
  const { isOpen: isReportOpen, onOpen: onReportOpen, onClose: onReportClose } = useDisclosure();
  const { isOpen: isDeleteOpen, onOpen: onDeleteOpen, onClose: onDeleteClose } = useDisclosure();

  // AbortController ref to cancel stale requests
  const abortRef = useRef<AbortController | null>(null);
  // Dedicated abort ref for checkActiveExchange — keeps it isolated from loadListing's controller
  const exchangeAbortRef = useRef<AbortController | null>(null);

  // Stable refs for t/toast — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

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
      if (abortRef.current?.signal.aborted) return;
      if (response.success && response.data) {
        setExchangeConfig(response.data);
      }
    } catch {
      // Exchange workflow may not be enabled
    }
  }, []);

  const checkActiveExchange = useCallback(async () => {
    if (!id || !isAuthenticated) return;
    exchangeAbortRef.current?.abort();
    const controller = new AbortController();
    exchangeAbortRef.current = controller;
    try {
      const response = await api.get<{ id: number; status: string; role: string; proposed_hours: number } | null>(`/v2/exchanges/check?listing_id=${id}`);
      if (controller.signal.aborted) return;
      if (response.success && response.data) {
        setActiveExchange(response.data);
      }
    } catch {
      // Not critical
    }
  }, [id, isAuthenticated]);

  const loadListing = useCallback(async () => {
    if (!id) return;

    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<ListingDetail>(`/v2/listings/${id}`);
      if (controller.signal.aborted) return;
      if (response.success && response.data) {
        setListing(response.data);
        setIsSaved(response.data.is_favorited ?? false);
        setSocialInit({
          liked: response.data.is_liked ?? false,
          likes: response.data.likes_count ?? 0,
          comments: response.data.comments_count ?? 0,
        });
      } else {
        setError(tRef.current('not_found_error'));
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load listing', err);
      setError(tRef.current('not_found_error'));
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadListing();
    loadExchangeConfig();
    checkActiveExchange();
    return () => { abortRef.current?.abort(); exchangeAbortRef.current?.abort(); };
  }, [loadListing, loadExchangeConfig, checkActiveExchange]);

  // Reset image error state when navigating to a different listing
  useEffect(() => { setImageError(false); }, [id]);

  async function handleDelete() {
    if (!listing) return;

    try {
      setIsDeleting(true);
      await api.delete(`/v2/listings/${listing.id}`);
      toastRef.current.success(tRef.current('delete_success_title'));
      navigate(tenantPath('/listings'), { replace: true });
    } catch (err) {
      logError('Failed to delete listing', err);
      toastRef.current.error(tRef.current('delete_error_title'), tRef.current('error_retry'));
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
        toastRef.current.info(tRef.current('unsave_title'));
      } else {
        await api.post(`/v2/listings/${id}/save`, {});
        toastRef.current.success(tRef.current('save_success_title'), tRef.current('save_success_subtitle'));
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
        toastRef.current.success(tRef.current('renew_success'));
        loadListing(); // Reload to get updated data
      }
    } catch (err) {
      logError('Failed to renew listing', err);
      toastRef.current.error(tRef.current('renew_error'));
    } finally {
      setIsRenewing(false);
    }
  };

  const handleReport = async () => {
    if (!listing || isReporting || !reportReason) return;
    setIsReporting(true);
    try {
      const payload: { reason: string; details?: string } = { reason: reportReason };
      if (reportDetails.trim()) {
        payload.details = reportDetails.trim();
      }
      const response = await api.post(`/v2/listings/${listing.id}/report`, payload);
      if (response.success) {
        setIsReported(true);
        onReportClose();
        setReportReason('');
        setReportDetails('');
        toastRef.current.success(tRef.current('report_success', 'Thank you for your report. Our team will review it.'));
      } else if (response.code === 'ALREADY_REPORTED') {
        setIsReported(true);
        onReportClose();
        toastRef.current.info(tRef.current('report_already', 'You have already reported this listing.'));
      } else {
        toastRef.current.error(tRef.current('report_error', 'Failed to submit report. Please try again.'));
      }
    } catch (err) {
      logError('Failed to report listing', err);
      toastRef.current.error(tRef.current('report_error', 'Failed to submit report. Please try again.'));
    } finally {
      setIsReporting(false);
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
      <PageMeta title={listing?.title} description={listing?.description?.substring(0, 160)} image={listing?.image_url || undefined} />
      <Helmet>
        <script type="application/ld+json">
          {JSON.stringify({
            '@context': 'https://schema.org',
            '@type': 'Service',
            name: listing?.title,
            ...(listing?.description ? { description: listing.description.substring(0, 160) } : {}),
            url: `${window.location.origin}${window.location.pathname}`,
            ...(listing?.image_url ? { image: resolveAssetUrl(listing.image_url) } : {}),
            ...(listing?.category?.name || listing?.category_name ? { category: listing.category?.name || listing.category_name } : {}),
            ...(listing?.location ? { areaServed: listing.location } : {}),
            provider: {
              '@type': 'Person',
              name: listing?.user?.name || `${listing?.user?.first_name ?? ''} ${listing?.user?.last_name ?? ''}`.trim() || t('community_member', 'Community Member'),
              ...(listing?.user?.id ? { url: `${window.location.origin}${tenantPath(`/profile/${listing.user.id}`)}` } : {}),
            },
          })}
        </script>
      </Helmet>
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
                onPress={onDeleteOpen}
                isLoading={isDeleting}
              >
                {t('detail_delete')}
              </Button>
            </div>
          )}
        </div>

        {/* Listing Image */}
        <div className="mb-6 overflow-hidden rounded-xl">
          {listing.image_url && !imageError ? (
            <img
              src={resolveAssetUrl(listing.image_url)}
              alt={listing.title}
              className="w-full max-h-[28rem] object-cover"
              loading="lazy"
              decoding="async"
              width={800}
              height={448}
              onError={() => setImageError(true)}
            />
          ) : (
            <ImagePlaceholder size="lg" />
          )}
        </div>

        {/* Title */}
        <h1 className="text-2xl sm:text-3xl font-bold text-theme-primary mb-4 break-words leading-tight">{listing.title}</h1>

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

          {listing.service_type && (
            <div className="flex items-center gap-3 text-theme-muted">
              <div className={`p-2 rounded-lg ${
                listing.service_type === 'remote_only' ? 'bg-blue-500/20' :
                listing.service_type === 'hybrid' ? 'bg-teal-500/20' :
                listing.service_type === 'physical_only' ? 'bg-emerald-500/20' :
                'bg-gray-500/20'
              }`} aria-hidden="true">
                {listing.service_type === 'remote_only' && <Monitor className="w-5 h-5 text-blue-600 dark:text-blue-400" />}
                {listing.service_type === 'hybrid' && <ArrowRightLeft className="w-5 h-5 text-teal-600 dark:text-teal-400" />}
                {listing.service_type === 'physical_only' && <MapPin className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />}
                {listing.service_type === 'location_dependent' && <HelpCircle className="w-5 h-5 text-gray-600 dark:text-gray-400" />}
              </div>
              <div>
                <div className="text-xs text-theme-subtle">{t('delivery_mode')}</div>
                <div className="text-theme-primary">
                  {listing.service_type === 'physical_only' && t('service_type_physical_only')}
                  {listing.service_type === 'remote_only' && t('service_type_remote_only')}
                  {listing.service_type === 'hybrid' && t('service_type_hybrid')}
                  {listing.service_type === 'location_dependent' && t('service_type_location_dependent')}
                </div>
              </div>
            </div>
          )}
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
            {(() => {
              const isLongDesc = (listing.description?.length ?? 0) > 500;
              return (
                <>
                  <div className={isLongDesc && !showFullDesc ? 'line-clamp-6 overflow-hidden' : ''}>
                    <SafeHtml content={translatedDesc ?? listing.description} className="text-theme-muted whitespace-pre-wrap wrap-break-word" as="div" />
                  </div>
                  {listing.description && (
                    <TranslateButton
                      contentType="listing"
                      contentId={listing.id}
                      sourceText={listing.description}
                      sourceLocale={(listing as { locale?: string | null }).locale ?? null}
                      onTextChange={(text, isTranslated) => setTranslatedDesc(isTranslated ? text : null)}
                      className="mt-1"
                    />
                  )}
                  {isLongDesc && (
                    <Button
                      variant="light"
                      size="sm"
                      className="mt-2 text-theme-accent px-0"
                      onPress={() => setShowFullDesc((prev) => !prev)}
                    >
                      {showFullDesc ? t('show_less', 'Show less') : t('show_more', 'Show more')}
                    </Button>
                  )}
                </>
              );
            })()}
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
            <Button
              variant="flat"
              className={`flex-1 sm:flex-none ${isReported ? 'bg-orange-500/20 text-orange-500' : 'bg-theme-elevated text-theme-primary'}`}
              startContent={<Flag className="w-4 h-4" aria-hidden="true" />}
              onPress={onReportOpen}
              isDisabled={isReported}
            >
              {isReported ? t('detail_reported', 'Reported') : t('detail_report', 'Report')}
            </Button>
          </div>
        )}
      </GlassCard>

      {/* Delete Confirmation Modal */}
      <Modal isOpen={isDeleteOpen} onClose={onDeleteClose} size="sm">
        <ModalContent>
          <ModalHeader>{t('delete_confirm_title', 'Delete listing')}</ModalHeader>
          <ModalBody>
            <p className="text-theme-secondary">{t('delete_confirm_body', 'Are you sure you want to delete this listing? This cannot be undone.')}</p>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={onDeleteClose}>{t('cancel', 'Cancel')}</Button>
            <Button color="danger" onPress={() => { onDeleteClose(); void handleDelete(); }} isLoading={isDeleting}>
              {t('delete_confirm_button', 'Delete listing')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Report Listing Modal */}
      <Modal isOpen={isReportOpen} onClose={onReportClose} size="md">
        <ModalContent>
          <ModalHeader className="text-theme-primary">{t('report_title', 'Report this listing')}</ModalHeader>
          <ModalBody>
            <RadioGroup
              label={t('report_reason_label', 'Why are you reporting this listing?')}
              value={reportReason}
              onValueChange={setReportReason}
              classNames={{ label: 'text-theme-secondary' }}
            >
              <Radio value="inappropriate" autoFocus>{t('report_reason_inappropriate', 'Inappropriate content')}</Radio>
              <Radio value="safety_concern">{t('report_reason_safety', 'Safety concern')}</Radio>
              <Radio value="misleading">{t('report_reason_misleading', 'Misleading description')}</Radio>
              <Radio value="spam">{t('report_reason_spam', 'Spam or scam')}</Radio>
              <Radio value="not_timebank_service">{t('report_reason_not_timebank', 'Not a timebank service')}</Radio>
              <Radio value="other">{t('report_reason_other', 'Other')}</Radio>
            </RadioGroup>
            <Textarea
              label={t('report_details_label', 'Additional details')}
              placeholder={t('report_details_placeholder', 'Tell us more (optional)')}
              value={reportDetails}
              onValueChange={setReportDetails}
              maxLength={500}
              variant="bordered"
              description={`${reportDetails.length}/500`}
              classNames={{ label: 'text-theme-secondary' }}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={onReportClose}>
              {t('cancel', 'Cancel')}
            </Button>
            <Button
              color="danger"
              onPress={() => void handleReport()}
              isDisabled={!reportReason || isReporting}
              isLoading={isReporting}
            >
              {t('report_submit', 'Submit Report')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

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

            {userId ? (
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
                  <div className="flex items-center gap-2">
                    <h3 className="font-semibold text-theme-primary group-hover:text-indigo-400 transition-colors">
                      {userName}
                    </h3>
                    {listing.user?.id && <VerificationBadgeRow userId={listing.user.id} size="sm" />}
                  </div>
                  {listing.user?.tagline && (
                    <p className="text-theme-muted text-sm truncate">{listing.user.tagline}</p>
                  )}
                  <div className="flex items-center gap-3 mt-1 flex-wrap">
                    {listing.author_rating != null && listing.author_rating > 0 && (
                      <span className="flex items-center gap-1 text-xs text-theme-muted">
                        <Star className="w-3.5 h-3.5 fill-amber-500 text-[var(--color-warning)]" aria-hidden="true" />
                        <span className="font-medium text-theme-primary">{listing.author_rating.toFixed(1)}</span>
                        {listing.author_reviews_count != null && listing.author_reviews_count > 0 && (
                          <span>({listing.author_reviews_count} {listing.author_reviews_count === 1 ? t('review', 'review') : t('reviews', 'reviews')})</span>
                        )}
                      </span>
                    )}
                    {listing.author_exchanges_count != null && listing.author_exchanges_count > 0 && (
                      <span className="flex items-center gap-1 text-xs text-theme-muted">
                        <ArrowRightLeft className="w-3.5 h-3.5" aria-hidden="true" />
                        {listing.author_exchanges_count} {listing.author_exchanges_count === 1 ? t('exchange', 'exchange completed') : t('exchanges_completed', 'exchanges completed')}
                      </span>
                    )}
                  </div>
                  <p className="text-xs text-theme-subtle mt-1">{t('detail_view_profile')}</p>
                </div>
              </Link>
            ) : (
              <span className="flex items-center gap-4 rounded-lg p-2 -m-2">
                <Avatar
                  src={userAvatar}
                  name={userName}
                  size="lg"
                  className="ring-2 ring-white/20"
                />
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    <h3 className="font-semibold text-theme-primary">{userName}</h3>
                  </div>
                  {listing.user?.tagline && (
                    <p className="text-theme-muted text-sm truncate">{listing.user.tagline}</p>
                  )}
                  <div className="flex items-center gap-3 mt-1 flex-wrap">
                    {listing.author_rating != null && listing.author_rating > 0 && (
                      <span className="flex items-center gap-1 text-xs text-theme-muted">
                        <Star className="w-3.5 h-3.5 fill-amber-500 text-[var(--color-warning)]" aria-hidden="true" />
                        <span className="font-medium text-theme-primary">{listing.author_rating.toFixed(1)}</span>
                        {listing.author_reviews_count != null && listing.author_reviews_count > 0 && (
                          <span>({listing.author_reviews_count} {listing.author_reviews_count === 1 ? t('review', 'review') : t('reviews', 'reviews')})</span>
                        )}
                      </span>
                    )}
                    {listing.author_exchanges_count != null && listing.author_exchanges_count > 0 && (
                      <span className="flex items-center gap-1 text-xs text-theme-muted">
                        <ArrowRightLeft className="w-3.5 h-3.5" aria-hidden="true" />
                        {listing.author_exchanges_count} {listing.author_exchanges_count === 1 ? t('exchange', 'exchange completed') : t('exchanges_completed', 'exchanges completed')}
                      </span>
                    )}
                  </div>
                </div>
              </span>
            )}
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

      {/* Member's Other Listings (Reciprocity) */}
      {((listing.member_offers?.length ?? 0) > 0 || (listing.member_requests?.length ?? 0) > 0) && (
        <GlassCard className="p-6">
          <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <ArrowRightLeft className="w-5 h-5 text-emerald-500" aria-hidden="true" />
            {t('detail_more_from', { name: listing.author_name || t('detail_this_member', 'this member'), defaultValue: 'More from {{name}}' })}
          </h2>
          {(listing.member_offers?.length ?? 0) > 0 && (
            <div className="mb-3">
              <h3 className="text-sm font-medium text-emerald-500 mb-2">{t('detail_also_offers', 'Also offers:')}</h3>
              <div className="flex flex-wrap gap-2">
                {listing.member_offers!.map((l) => (
                  <Link key={l.id} to={tenantPath(`/listings/${l.id}`)}>
                    <Chip variant="flat" className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 cursor-pointer hover:bg-emerald-500/20">
                      {l.title}
                    </Chip>
                  </Link>
                ))}
              </div>
            </div>
          )}
          {(listing.member_requests?.length ?? 0) > 0 && (
            <div>
              <h3 className="text-sm font-medium text-[var(--color-warning)] mb-2">{t('detail_looking_for', 'Looking for:')}</h3>
              <div className="flex flex-wrap gap-2">
                {listing.member_requests!.map((l) => (
                  <Link key={l.id} to={tenantPath(`/listings/${l.id}`)}>
                    <Chip variant="flat" className="bg-amber-500/10 text-amber-600 dark:text-amber-400 cursor-pointer hover:bg-amber-500/20">
                      {l.title}
                    </Chip>
                  </Link>
                ))}
              </div>
            </div>
          )}
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
        <ErrorBoundary fallback={
          <GlassCard className="p-6 text-center text-theme-muted">
            {t('comments_error', 'Comments could not be loaded.')}
          </GlassCard>
        }>
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
        </ErrorBoundary>
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
