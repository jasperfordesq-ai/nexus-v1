// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MarketplaceListingPage — Detail page for a single marketplace listing.
 *
 * Features:
 * - Image gallery (grid on desktop, swipeable on mobile)
 * - Price display with currency/free/negotiable badges
 * - Seller card with avatar, rating, response time
 * - Action buttons: Make Offer, Message Seller, Save
 * - "More from this seller" section
 * - View count increment on mount
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Chip,
  Avatar,
  Divider,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  useDisclosure,
} from '@heroui/react';
import {
  Heart,
  MessageCircle,
  MapPin,
  Eye,
  Clock,
  ShoppingBag,
  ArrowLeft,
  Share2,
  Star,
  ChevronLeft,
  ChevronRight,
  DollarSign,
  Package,
  User,
  ExternalLink,
  Flag,
  CheckCircle,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { BuyNowButton, MarketplaceListingDetailSkeleton } from '@/components/marketplace';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo/PageMeta';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface ListingDetail {
  id: number;
  title: string;
  description: string;
  tagline?: string;
  price: number | null;
  price_currency: string;
  price_type: 'fixed' | 'negotiable' | 'free' | 'auction' | 'contact';
  time_credit_price?: number | null;
  condition: string;
  quantity: number;
  category?: { id: number; name: string; slug: string; icon?: string } | null;
  location: string | null;
  latitude: number | null;
  longitude: number | null;
  shipping_available: boolean;
  local_pickup: boolean;
  delivery_method: string | null;
  seller_type: string;
  images: { id: number; url: string; thumbnail_url?: string; alt_text?: string; is_primary?: boolean }[];
  video_url?: string | null;
  user: {
    id: number;
    name: string;
    avatar_url: string | null;
    is_verified?: boolean;
    member_since?: string;
  } | null;
  template_data: Record<string, unknown> | null;
  views_count: number;
  saves_count: number;
  is_saved: boolean;
  is_own: boolean;
  is_promoted: boolean;
  status: string;
  expires_at?: string;
  created_at: string;
  updated_at: string;
}

interface SellerListing {
  id: number;
  title: string;
  price: number | null;
  price_type: string;
  price_currency: string;
  images: { url: string; thumbnail_url: string }[];
}

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const CONDITION_LABELS: Record<string, string> = {
  new: 'New',
  like_new: 'Like New',
  good: 'Good',
  fair: 'Fair',
  poor: 'Poor',
};

const CONDITION_COLORS: Record<string, 'success' | 'primary' | 'warning' | 'danger' | 'default'> = {
  new: 'success',
  like_new: 'primary',
  good: 'warning',
  fair: 'danger',
  poor: 'default',
};

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function formatPrice(price: number | null, priceType: string, currency: string): string {
  if (priceType === 'free' || price === null || price === 0) return 'Free';
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: currency || 'EUR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(price);
}

// ─────────────────────────────────────────────────────────────────────────────
// JSON-LD structured data
// ─────────────────────────────────────────────────────────────────────────────

const AVAILABILITY_MAP: Record<string, string> = {
  active: 'https://schema.org/InStock',
  sold: 'https://schema.org/SoldOut',
  reserved: 'https://schema.org/LimitedAvailability',
  expired: 'https://schema.org/Discontinued',
};

const CONDITION_MAP: Record<string, string> = {
  new: 'https://schema.org/NewCondition',
  like_new: 'https://schema.org/UsedCondition',
  good: 'https://schema.org/UsedCondition',
  fair: 'https://schema.org/UsedCondition',
  poor: 'https://schema.org/DamagedCondition',
};

function buildProductSchema(
  listing: ListingDetail,
  tenantPath: (p: string) => string,
  tenantName: string,
): string {
  const base = window.location.origin;
  const canonicalUrl = base + tenantPath(`/marketplace/${listing.id}`);

  const schema: Record<string, unknown> = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    'name': listing.title,
    'description': listing.description ?? '',
    'sku': String(listing.id),
    'brand': {
      '@type': 'Organization',
      'name': tenantName,
    },
    'offers': {
      '@type': 'Offer',
      'url': canonicalUrl,
      'priceCurrency': listing.price_currency || 'EUR',
      'price': listing.price_type === 'free' ? '0' : String(listing.price ?? 0),
      'availability': AVAILABILITY_MAP[listing.status] ?? 'https://schema.org/InStock',
      'itemCondition': listing.condition
        ? (CONDITION_MAP[listing.condition] ?? 'https://schema.org/UsedCondition')
        : undefined,
      'seller': {
        '@type': 'Person',
        'name': listing.user?.name || '',
      },
    },
  };

  // Image URLs
  if (listing.images.length > 0) {
    schema['image'] = listing.images.map((img) => img.url);
  }

  return JSON.stringify(schema);
}

// ─────────────────────────────────────────────────────────────────────────────
// Image Gallery
// ─────────────────────────────────────────────────────────────────────────────

function ImageGallery({ images, videoUrl }: { images: ListingDetail['images']; videoUrl?: string | null }) {
  const { t } = useTranslation('marketplace');
  const [activeIndex, setActiveIndex] = useState(0);

  if (images.length === 0 && !videoUrl) {
    return (
      <div className="aspect-video bg-default-100 rounded-xl flex items-center justify-center">
        <ShoppingBag className="w-16 h-16 text-default-300" />
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {/* Video player — shown above images if available */}
      {videoUrl && (
        <div className="relative aspect-video bg-black rounded-xl overflow-hidden">
          <video
            src={videoUrl}
            controls
            preload="metadata"
            className="w-full h-full object-contain"
            aria-label={t('listing.video', 'Listing video')}
          >
            {t('listing.video_unsupported', 'Your browser does not support the video tag.')}
          </video>
        </div>
      )}

      {images.length === 0 ? null : (
      <>
      {/* Main image */}
      <div className="relative aspect-video bg-default-100 rounded-xl overflow-hidden">
        <AnimatePresence mode="wait">
          <motion.img
            key={activeIndex}
            src={images[activeIndex].url}
            alt={t('listing.image_alt', 'Image {{number}}', { number: activeIndex + 1 })}
            className="w-full h-full object-contain"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.2 }}
          />
        </AnimatePresence>

        {images.length > 1 && (
          <>
            <button
              onClick={() => setActiveIndex((i) => (i - 1 + images.length) % images.length)}
              className="absolute left-2 top-1/2 -translate-y-1/2 p-2 rounded-full bg-background/80 backdrop-blur-sm hover:bg-background transition-colors"
              aria-label={t('listing.previous_image', 'Previous image')}
            >
              <ChevronLeft className="w-5 h-5" />
            </button>
            <button
              onClick={() => setActiveIndex((i) => (i + 1) % images.length)}
              className="absolute right-2 top-1/2 -translate-y-1/2 p-2 rounded-full bg-background/80 backdrop-blur-sm hover:bg-background transition-colors"
              aria-label={t('listing.next_image', 'Next image')}
            >
              <ChevronRight className="w-5 h-5" />
            </button>
            <div className="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5">
              {images.map((_, idx) => (
                <button
                  key={idx}
                  onClick={() => setActiveIndex(idx)}
                  className={`w-2 h-2 rounded-full transition-colors ${
                    idx === activeIndex ? 'bg-primary' : 'bg-white/50'
                  }`}
                  aria-label={t('listing.view_image', 'View image {{number}}', { number: idx + 1 })}
                />
              ))}
            </div>
          </>
        )}
      </div>

      {/* Thumbnails */}
      {images.length > 1 && (
        <div className="flex gap-2 overflow-x-auto pb-1 scrollbar-hide">
          {images.map((img, idx) => (
            <button
              key={img.id || idx}
              onClick={() => setActiveIndex(idx)}
              className={`shrink-0 w-16 h-16 rounded-lg overflow-hidden border-2 transition-colors ${
                idx === activeIndex ? 'border-primary' : 'border-transparent'
              }`}
            >
              <img
                src={img.thumbnail_url || img.url}
                alt={t('listing.thumbnail_alt', 'Thumbnail {{number}}', { number: idx + 1 })}
                className="w-full h-full object-cover"
                loading="lazy"
              />
            </button>
          ))}
        </div>
      )}
      </>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function MarketplaceListingPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation('marketplace');
  usePageTitle(t('page_title', 'Marketplace'));
  const { isAuthenticated } = useAuth();
  const { tenantPath, branding } = useTenant();
  const toast = useToast();
  const offerModal = useDisclosure();

  // State
  const [listing, setListing] = useState<ListingDetail | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [sellerListings, setSellerListings] = useState<SellerListing[]>([]);
  const [offerAmount, setOfferAmount] = useState('');
  const [offerMessage, setOfferMessage] = useState('');
  const [isSubmittingOffer, setIsSubmittingOffer] = useState(false);

  // Load listing
  useEffect(() => {
    if (!id) return;
    let cancelled = false;

    const load = async () => {
      setIsLoading(true);
      setError(null);
      try {
        const response = await api.get<ListingDetail>(`/v2/marketplace/listings/${id}`);
        if (cancelled) return;
        if (response.success && response.data) {
          setListing(response.data);
        } else {
          setError(response.error || t('listing.not_found_title', 'Listing not found'));
        }
      } catch (err) {
        if (!cancelled) {
          logError('Failed to load marketplace listing', err);
          setError(t('hub.unable_to_load', 'Unable to load listing'));
        }
      } finally {
        if (!cancelled) setIsLoading(false);
      }
    };

    load();
    return () => { cancelled = true; };
  }, [id]);

  // Update page title when listing loads
  useEffect(() => {
    if (listing?.title) {
      document.title = `${listing.title} - ${t('page_title', 'Marketplace')}`;
    }
  }, [listing?.title]);

  // JSON-LD structured data — inject via textContent (XSS-safe, avoids dangerouslySetInnerHTML)
  // Must be before any early returns to satisfy Rules of Hooks
  useEffect(() => {
    if (!listing) return;
    const script = document.createElement('script');
    script.type = 'application/ld+json';
    script.textContent = buildProductSchema(listing, tenantPath, branding.name);
    document.head.appendChild(script);
    return () => { script.remove(); };
  }, [listing, tenantPath, branding.name]);

  // Load seller listings
  useEffect(() => {
    if (!listing?.user?.id) return;
    let cancelled = false;

    const load = async () => {
      try {
        const response = await api.get<SellerListing[]>(
          `/v2/marketplace/sellers/${listing.user.id}/listings?limit=4`
        );
        if (!cancelled && response.success && response.data) {
          setSellerListings(response.data.filter((l) => l.id !== listing.id));
        }
      } catch (err) {
        logError('Failed to load seller listings', err);
      }
    };

    load();
    return () => { cancelled = true; };
  }, [listing?.user?.id, listing?.id]);

  // Toggle save
  const handleToggleSave = useCallback(async () => {
    if (!listing || !isAuthenticated) {
      toast.error(t('common.sign_in_to_save', 'Please sign in to save listings'));
      return;
    }
    try {
      if (listing.is_saved) {
        await api.delete(`/v2/marketplace/listings/${listing.id}/save`);
      } else {
        await api.post(`/v2/marketplace/listings/${listing.id}/save`);
      }
      setListing((prev) => prev ? { ...prev, is_saved: !prev.is_saved } : prev);
      toast.success(listing.is_saved ? t('common.removed_from_saved', 'Removed from saved') : t('common.saved_for_later', 'Saved for later'));
    } catch (err) {
      logError('Failed to toggle save', err);
      toast.error(t('common.save_failed', 'Failed to update saved status'));
    }
  }, [listing, isAuthenticated, toast]);

  // Share
  const handleShare = useCallback(async () => {
    const url = window.location.href;
    if (navigator.share) {
      try {
        await navigator.share({ title: listing?.title, url });
      } catch {
        // User cancelled share
      }
    } else {
      await navigator.clipboard.writeText(url);
      toast.success(t('listing.link_copied', 'Link copied to clipboard'));
    }
  }, [listing?.title, toast]);

  // Make offer
  const handleMakeOffer = useCallback(async () => {
    if (!listing || !offerAmount) return;
    setIsSubmittingOffer(true);
    try {
      await api.post(`/v2/marketplace/listings/${listing.id}/offers`, {
        amount: parseFloat(offerAmount),
        message: offerMessage || undefined,
      });
      toast.success(t('offer.sent_success', 'Offer sent successfully!'));
      offerModal.onClose();
      setOfferAmount('');
      setOfferMessage('');
    } catch (err) {
      logError('Failed to make offer', err);
      toast.error(t('offer.sent_error', 'Failed to send offer'));
    } finally {
      setIsSubmittingOffer(false);
    }
  }, [listing, offerAmount, offerMessage, toast, offerModal]);

  // Loading state — skeleton instead of spinner
  if (isLoading) {
    return <MarketplaceListingDetailSkeleton />;
  }

  // Error state
  if (error || !listing) {
    return (
      <div className="max-w-3xl mx-auto px-4 py-12">
        <EmptyState
          icon={<ShoppingBag className="w-8 h-8" />}
          title={t('listing.not_found_title', 'Listing Not Found')}
          description={error || t('listing.not_found_description', 'This listing may have been removed or is no longer available.')}
          action={{ label: t('listing.back_to_marketplace', 'Back to Marketplace'), onClick: () => navigate(tenantPath('/marketplace')) }}
        />
      </div>
    );
  }

  const priceDisplay = formatPrice(listing.price, listing.price_type, listing.price_currency);

  return (
    <>
      <PageMeta
        title={`${listing.title} - ${t('page_title', 'Marketplace')}`}
        description={listing.description?.slice(0, 160)}
      />

      <div className="max-w-6xl mx-auto px-4 py-6 space-y-6">
        {/* Breadcrumb / Back */}
        <div className="flex items-center gap-2 text-sm">
          <Button
            as={Link}
            to={tenantPath('/marketplace')}
            variant="light"
            size="sm"
            startContent={<ArrowLeft className="w-4 h-4" />}
          >
            {t('listing.marketplace', 'Marketplace')}
          </Button>
          {listing.category?.name && listing.category?.slug && (
            <>
              <span className="text-default-300">/</span>
              <Link
                to={tenantPath(`/marketplace/category/${listing.category?.slug}`)}
                className="text-default-500 hover:text-primary transition-colors"
              >
                {listing.category?.name}
              </Link>
            </>
          )}
        </div>

        {/* Main content */}
        <div className="grid grid-cols-1 lg:grid-cols-5 gap-6">
          {/* Image gallery — 3 cols */}
          <div className="lg:col-span-3">
            <ImageGallery images={listing.images} videoUrl={listing.video_url} />
          </div>

          {/* Details sidebar — 2 cols */}
          <div className="lg:col-span-2 space-y-4">
            {/* Price */}
            <GlassCard className="p-5 space-y-3">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <span className="text-3xl font-bold text-foreground">{priceDisplay}</span>
                  {listing.price_type === 'negotiable' && (
                    <Chip size="sm" color="warning" variant="flat" className="ml-2">
                      {t('listing.negotiable', 'Negotiable')}
                    </Chip>
                  )}
                  {listing.price_type === 'free' && (
                    <Chip size="sm" color="success" variant="flat" className="ml-2">
                      {t('listing.free', 'Free')}
                    </Chip>
                  )}
                </div>
                <div className="flex gap-1.5 shrink-0">
                  <Button
                    isIconOnly
                    variant="flat"
                    size="sm"
                    onPress={handleShare}
                    aria-label={t('listing.share_aria', 'Share')}
                  >
                    <Share2 className="w-4 h-4" />
                  </Button>
                  <Button
                    isIconOnly
                    variant="flat"
                    size="sm"
                    color={listing.is_saved ? 'danger' : 'default'}
                    onPress={handleToggleSave}
                    aria-label={listing.is_saved ? t('listing.unsave_aria', 'Unsave') : t('listing.save_aria', 'Save')}
                  >
                    <Heart className={`w-4 h-4 ${listing.is_saved ? 'fill-current' : ''}`} />
                  </Button>
                </div>
              </div>

              <h1 className="text-xl font-semibold text-foreground">{listing.title}</h1>

              <div className="flex items-center gap-2 flex-wrap">
                {listing.condition && (
                  <Chip
                    size="sm"
                    variant="flat"
                    color={CONDITION_COLORS[listing.condition] || 'default'}
                  >
                    {t(`condition.${listing.condition}`, CONDITION_LABELS[listing.condition] || listing.condition)}
                  </Chip>
                )}
                {listing.quantity > 1 && (
                  <Chip size="sm" variant="flat" startContent={<Package className="w-3 h-3" />}>
                    {t('listing.available_count', '{{count}} available', { count: listing.quantity })}
                  </Chip>
                )}
                {listing.is_promoted && (
                  <Chip size="sm" color="warning" variant="flat" startContent={<Star className="w-3 h-3" />}>
                    {t('listing.featured', 'Featured')}
                  </Chip>
                )}
              </div>

              <div className="flex items-center gap-4 text-xs text-default-400">
                {listing.location && (
                  <span className="flex items-center gap-1">
                    <MapPin className="w-3 h-3" />
                    {listing.location}
                  </span>
                )}
                <span className="flex items-center gap-1">
                  <Eye className="w-3 h-3" />
                  {t('listing.views_count', '{{count}} views', { count: listing.views_count })}
                </span>
                <span className="flex items-center gap-1">
                  <Clock className="w-3 h-3" />
                  {new Date(listing.created_at).toLocaleDateString()}
                </span>
              </div>

              {/* Action buttons */}
              <div className="flex flex-col gap-2 pt-2">
                {listing.price_type === 'fixed' && listing.price != null && listing.price > 0 && (
                  <BuyNowButton
                    listingId={listing.id}
                    listingTitle={listing.title}
                    price={listing.price}
                    currency={listing.price_currency}
                    sellerId={listing.user?.id ?? 0}
                    onSuccess={() => {
                      toast.success(t('listing.order_created', 'Order created!'));
                    }}
                  />
                )}
                <div className="flex gap-2">
                  {listing.price_type !== 'free' && (
                    <Button
                      color="primary"
                      fullWidth
                      startContent={<DollarSign className="w-4 h-4" />}
                      onPress={offerModal.onOpen}
                      isDisabled={!isAuthenticated}
                    >
                      {t('listing.make_offer', 'Make Offer')}
                    </Button>
                  )}
                  <Button
                    variant="bordered"
                    fullWidth
                    startContent={<MessageCircle className="w-4 h-4" />}
                    as={listing.user ? Link : undefined}
                    to={listing.user ? tenantPath(`/messages?to=${listing.user.id}&ref=marketplace&listing_id=${listing.id}&listing_title=${encodeURIComponent(listing.title)}&body=${encodeURIComponent(t('listing.message_template', 'Hi, I\'m interested in your listing: {{title}}', { title: listing.title }))}`) : undefined}
                    isDisabled={!isAuthenticated || !listing.user}
                  >
                    {t('listing.message_seller', 'Message Seller')}
                  </Button>
                </div>
              </div>

              {!isAuthenticated && (
                <p className="text-xs text-default-400 text-center">
                  <Link to={tenantPath('/auth/login')} className="text-primary hover:underline">
                    {t('listing.sign_in', 'Sign in')}
                  </Link>
                  {' '}{t('listing.sign_in_to_contact', 'to contact the seller')}
                </p>
              )}
            </GlassCard>

            {/* Seller card */}
            {listing.user && (
            <GlassCard className="p-5 space-y-3">
              <h3 className="text-sm font-semibold text-default-500 uppercase tracking-wide">
                {t('listing.user', 'Seller')}
              </h3>
              <div className="flex items-center gap-3">
                <Avatar
                  src={listing.user.avatar_url || undefined}
                  name={listing.user.name}
                  size="lg"
                />
                <div className="min-w-0 flex-1">
                  <Link
                    to={tenantPath(`/marketplace/seller/${listing.user.id}`)}
                    className="font-semibold text-foreground hover:text-primary transition-colors"
                  >
                    {listing.user.name}
                  </Link>
                  <div className="flex items-center gap-3 text-xs text-default-400 mt-0.5">
                    {listing.user.is_verified && (
                      <span className="flex items-center gap-0.5 text-primary">
                        <CheckCircle className="w-3 h-3" />
                        {t('listing.verified', 'Verified')}
                      </span>
                    )}
                    {listing.user.member_since && (
                      <span>{t('listing.member_since', 'Member since {{date}}', { date: new Date(listing.user.member_since).getFullYear() })}</span>
                    )}
                  </div>
                  {listing.seller_type === 'business' && (
                    <Chip size="sm" variant="flat" color="secondary" className="mt-1">
                      {t('listing.seller_type_business', 'Business')}
                    </Chip>
                  )}
                </div>
              </div>
              <Button
                as={Link}
                to={tenantPath(`/marketplace/seller/${listing.user.id}`)}
                variant="flat"
                fullWidth
                size="sm"
                endContent={<ExternalLink className="w-3.5 h-3.5" />}
              >
                {t('listing.view_profile', 'View Profile')}
              </Button>
            </GlassCard>
            )}

            {/* Delivery */}
            {listing.delivery_method && (
              <GlassCard className="p-5">
                <h3 className="text-sm font-semibold text-default-500 uppercase tracking-wide mb-2">
                  {t('listing.delivery', 'Delivery')}
                </h3>
                <p className="text-sm text-foreground capitalize">
                  {listing.delivery_method.replace(/_/g, ' ')}
                </p>
              </GlassCard>
            )}
          </div>
        </div>

        {/* Description */}
        <GlassCard className="p-6 space-y-4">
          <h2 className="text-lg font-semibold text-foreground">{t('listing.description', 'Description')}</h2>
          <div className="prose prose-sm dark:prose-invert max-w-none text-foreground/90 whitespace-pre-wrap">
            {listing.description}
          </div>

          {/* Template fields */}
          {listing.template_data && Object.keys(listing.template_data).length > 0 && (
            <>
              <Divider />
              <h3 className="text-md font-semibold text-foreground">{t('listing.details', 'Details')}</h3>
              <div className="grid grid-cols-2 gap-3">
                {Object.entries(listing.template_data).map(([key, value]) => (
                  <div key={key} className="space-y-0.5">
                    <span className="text-xs text-default-400 capitalize">
                      {key.replace(/_/g, ' ')}
                    </span>
                    <p className="text-sm text-foreground">{value}</p>
                  </div>
                ))}
              </div>
            </>
          )}
        </GlassCard>

        {/* More from this seller */}
        {sellerListings.length > 0 && (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-semibold text-foreground flex items-center gap-2">
                <User className="w-5 h-5 text-default-400" />
                {t('listing.more_from_seller', 'More from {{name}}', { name: listing.user?.name ?? '' })}
              </h2>
              <Button
                as={Link}
                to={tenantPath(`/marketplace/seller/${listing.user?.id}`)}
                variant="light"
                size="sm"
                endContent={<ChevronRight className="w-4 h-4" />}
              >
                {t('listing.view_all', 'View All')}
              </Button>
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
              {sellerListings.map((item) => (
                <Link
                  key={item.id}
                  to={tenantPath(`/marketplace/${item.id}`)}
                  className="block group"
                >
                  <GlassCard hoverable className="overflow-hidden">
                    <div className="aspect-square bg-default-100 overflow-hidden rounded-t-xl">
                      {item.images?.[0] ? (
                        <img
                          src={item.images[0].thumbnail_url || item.images[0].url}
                          alt={item.title}
                          className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                          loading="lazy"
                        />
                      ) : (
                        <div className="w-full h-full flex items-center justify-center">
                          <ShoppingBag className="w-8 h-8 text-default-300" />
                        </div>
                      )}
                    </div>
                    <div className="p-2.5">
                      <p className="text-sm font-medium line-clamp-1 text-foreground group-hover:text-primary transition-colors">
                        {item.title}
                      </p>
                      <p className="text-sm font-semibold text-primary mt-0.5">
                        {formatPrice(item.price, item.price_type, item.price_currency)}
                      </p>
                    </div>
                  </GlassCard>
                </Link>
              ))}
            </div>
          </div>
        )}

        {/* Report link */}
        <div className="flex justify-center pb-4">
          <Button
            variant="light"
            size="sm"
            color="danger"
            startContent={<Flag className="w-3.5 h-3.5" />}
            onPress={async () => {
              if (!isAuthenticated) { toast.error(t('listing.sign_in_to_report', 'Sign in to report')); return; }
              const reason = window.prompt(t('listing.report_reason_prompt', 'Why are you reporting this listing?'));
              if (!reason) return;
              try {
                await api.post(`/v2/marketplace/listings/${listing.id}/report`, { reason: 'other', description: reason });
                toast.success(t('listing.report_submitted', 'Report submitted. Thank you.'));
              } catch { toast.error(t('listing.report_error', 'Failed to submit report')); }
            }}
          >
            {t('listing.report_listing', 'Report this listing')}
          </Button>
        </div>
      </div>

      {/* Make Offer Modal */}
      <Modal isOpen={offerModal.isOpen} onOpenChange={offerModal.onOpenChange} placement="center">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>{t('offer.modal_title', 'Make an Offer')}</ModalHeader>
              <ModalBody className="space-y-4">
                <div>
                  <p className="text-sm text-default-500 mb-3">
                    {t('offer.asking_price', 'Asking price:')}{' '}
                    <span className="font-semibold text-foreground">{priceDisplay}</span>
                  </p>
                  <Input
                    label={t('offer.your_offer', 'Your Offer')}
                    placeholder="0.00"
                    type="number"
                    min={0}
                    step={0.01}
                    value={offerAmount}
                    onValueChange={setOfferAmount}
                    startContent={
                      <span className="text-default-400 text-sm">
                        {listing.price_currency || 'EUR'}
                      </span>
                    }
                    isRequired
                  />
                </div>
                <Textarea
                  label={t('offer.message_label', 'Message (optional)')}
                  placeholder={t('offer.message_placeholder', 'Add a message for the seller...')}
                  value={offerMessage}
                  onValueChange={setOfferMessage}
                  minRows={2}
                  maxRows={4}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  {t('offer.cancel', 'Cancel')}
                </Button>
                <Button
                  color="primary"
                  onPress={handleMakeOffer}
                  isLoading={isSubmittingOffer}
                  isDisabled={!offerAmount || parseFloat(offerAmount) <= 0}
                >
                  {t('offer.send', 'Send Offer')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </>
  );
}

export default MarketplaceListingPage;
