// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState, type ComponentProps } from 'react';
import { Alert, Image, Linking, Modal, ScrollView, Share, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useLocalSearchParams, type Href } from 'expo-router';
import { ResizeMode, Video } from 'expo-av';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Surface, Text } from 'heroui-native';
import { useTranslation } from 'react-i18next';
import * as Haptics from '@/lib/haptics';

import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import { formatMarketplacePrice } from '@/components/marketplace/MarketplaceListingCard';
import {
  addMarketplaceCollectionItem,
  createMarketplaceOrder,
  createMarketplacePaymentIntent,
  getMarketplaceListingPickupSlots,
  getMarketplaceSellerListings,
  getMarketplaceCollections,
  getMarketplaceListing,
  makeMarketplaceOffer,
  reportMarketplaceListing,
  reserveMarketplacePickup,
  saveMarketplaceListing,
  unsaveMarketplaceListing,
  validateMarketplaceCoupon,
  type MarketplaceCollection,
  type MarketplaceListingDetail,
  type MarketplaceListingItem,
  type MarketplacePickupSlotOption,
} from '@/lib/api/marketplace';
import { APP_URL } from '@/lib/constants';
import { usePrimaryColor, useTenant } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { useAuth } from '@/lib/hooks/useAuth';
import { withAlpha } from '@/lib/utils/color';
import { resolveImageUrl } from '@/lib/utils/resolveImageUrl';

export default function MarketplaceDetailRoute() {
  return (
    <ModalErrorBoundary>
      <MarketplaceDetailScreen />
    </ModalErrorBoundary>
  );
}

type ReportReason = 'counterfeit' | 'illegal' | 'unsafe' | 'misleading' | 'discrimination' | 'ip_violation' | 'other';
const REPORT_REASONS: ReportReason[] = ['counterfeit', 'illegal', 'unsafe', 'misleading', 'discrimination', 'ip_violation', 'other'];

function MarketplaceDetailScreen() {
  const { t } = useTranslation(['marketplace', 'common']);
  const params = useLocalSearchParams<{ id?: string }>();
  const primary = usePrimaryColor();
  const { hasFeature } = useTenant();
  const theme = useTheme();
  const { user } = useAuth();
  const listingId = Number(params.id);
  const safeId = Number.isFinite(listingId) && listingId > 0 ? listingId : 0;
  const [listing, setListing] = useState<MarketplaceListingDetail | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isActionLoading, setIsActionLoading] = useState(false);
  const [activeImage, setActiveImage] = useState(0);
  const [offerOpen, setOfferOpen] = useState(false);
  const [collectionOpen, setCollectionOpen] = useState(false);
  const [reportOpen, setReportOpen] = useState(false);
  const [collections, setCollections] = useState<MarketplaceCollection[]>([]);
  const [isCollectionLoading, setIsCollectionLoading] = useState(false);
  const [pickupSlots, setPickupSlots] = useState<MarketplacePickupSlotOption[]>([]);
  const [sellerListings, setSellerListings] = useState<MarketplaceListingItem[]>([]);
  const [selectedSlotId, setSelectedSlotId] = useState<number | null>(null);
  const [couponCode, setCouponCode] = useState('');
  const [couponApplied, setCouponApplied] = useState(false);
  const [offerAmount, setOfferAmount] = useState('');
  const [offerMessage, setOfferMessage] = useState('');
  const [reportReason, setReportReason] = useState<ReportReason>('misleading');
  const [reportDescription, setReportDescription] = useState('');

  useEffect(() => {
    void loadListing();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [safeId]);

  useEffect(() => {
    if (!listing || listing.is_own || (user?.id && listing.user?.id === user.id)) return;
    let mounted = true;
    getMarketplaceListingPickupSlots(listing.id)
      .then((response) => {
        if (mounted) setPickupSlots(response.data);
      })
      .catch(() => {
        if (mounted) setPickupSlots([]);
      });
    return () => {
      mounted = false;
    };
  }, [listing, user?.id]);

  useEffect(() => {
    const sellerId = listing?.user?.id;
    if (!listing || !sellerId) {
      setSellerListings([]);
      return undefined;
    }

    let mounted = true;
    getMarketplaceSellerListings(sellerId, null, 4)
      .then((response) => {
        if (mounted) setSellerListings(response.data.filter((item) => item.id !== listing.id).slice(0, 3));
      })
      .catch(() => {
        if (mounted) setSellerListings([]);
      });
    return () => {
      mounted = false;
    };
  }, [listing]);

  async function loadListing() {
    if (!safeId) {
      setIsLoading(false);
      return;
    }
    setIsLoading(true);
    try {
      const response = await getMarketplaceListing(safeId);
      setListing(response.data);
    } catch {
      setListing(null);
    } finally {
      setIsLoading(false);
    }
  }

  if (!safeId) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('detail.title')} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace' as Href} />
        <EmptyState icon="bag-handle-outline" title={t('detail.invalid')} subtitle={t('detail.invalidHint')} />
      </SafeAreaView>
    );
  }

  if (isLoading) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('detail.title')} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace' as Href} />
        <View className="flex-1 items-center justify-center">
          <LoadingSpinner />
        </View>
      </SafeAreaView>
    );
  }

  if (!listing) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('detail.title')} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace' as Href} />
        <EmptyState
          icon="bag-handle-outline"
          title={t('detail.notFound')}
          subtitle={t('detail.notFoundHint')}
          actionLabel={t('actions.browse')}
          onAction={() => router.replace('/(modals)/marketplace' as Href)}
        />
      </SafeAreaView>
    );
  }

  const images = listing.images?.length ? listing.images : listing.image ? [listing.image] : [];
  const activeImageUrl = resolveImageUrl(images[activeImage]?.url ?? images[activeImage]?.thumbnail_url);
  const videoUrl = resolveImageUrl(listing.video_url);
  const accent = listing.price_type === 'free' ? theme.success : listing.is_promoted ? theme.warning : primary;
  const priceLabel = formatMarketplacePrice(listing.price, listing.price_type, listing.price_currency, t('common.free'));
  const isOwner = Boolean(listing.is_own || (user?.id && listing.user?.id === user.id));
  const canBuy = !isOwner && listing.status === 'active' && listing.price_type !== 'contact' && listing.price_type !== 'auction';
  const couponsEnabled = hasFeature('merchant_coupons');
  const templateEntries = getListingTemplateEntries(listing.template_data);

  async function handleToggleSave() {
    if (!listing) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    const previous = listing;
    setListing({ ...listing, is_saved: !listing.is_saved });
    try {
      if (listing.is_saved) await unsaveMarketplaceListing(listing.id);
      else await saveMarketplaceListing(listing.id);
    } catch {
      setListing(previous);
      Alert.alert(t('common:errors.alertTitle'), t('common.save_failed'));
    }
  }

  async function handleBuyNow() {
    if (!listing || isActionLoading) return;
    setIsActionLoading(true);
    try {
      const response = await createMarketplaceOrder({
        listing_id: listing.id,
        quantity: 1,
        coupon_code: couponApplied && couponCode.trim() ? couponCode.trim().toUpperCase() : undefined,
      });
      const orderId = response.data.id;
      const orderNumber = response.data.order_number;
      if (selectedSlotId) {
        try {
          await reserveMarketplacePickup(orderId, selectedSlotId);
        } catch {
          setSelectedSlotId(null);
        }
      }
      let payment;
      try {
        payment = await createMarketplacePaymentIntent(orderId);
      } catch {
        Alert.alert(t('checkout.paymentRecoveryTitle'), t('checkout.paymentRecoveryHint', { order: orderNumber }));
        return;
      }
      await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      if (payment.data.checkout_url) {
        await Linking.openURL(payment.data.checkout_url);
        return;
      }
      if (payment.data.client_secret) {
        Alert.alert(t('checkout.openedTitle'), t('checkout.clientSecretHint'));
        return;
      }
      Alert.alert(t('detail.orderCreated'), t('detail.orderCreatedHint', { order: response.data.order_number }));
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('detail.orderFailed'));
    } finally {
      setIsActionLoading(false);
    }
  }

  async function applyCoupon() {
    if (!listing || !couponCode.trim()) return;
    setIsActionLoading(true);
    try {
      await validateMarketplaceCoupon({
        code: couponCode.trim().toUpperCase(),
        order_total_cents: Math.round(Number(listing.price ?? 0) * 100),
        listing_id: listing.id,
      });
      setCouponApplied(true);
      Alert.alert(t('checkout.couponAppliedTitle'), t('checkout.couponAppliedHint'));
    } catch {
      setCouponApplied(false);
      Alert.alert(t('common:errors.alertTitle'), t('checkout.invalidCoupon'));
    } finally {
      setIsActionLoading(false);
    }
  }

  function togglePickupSlot(slotId: number) {
    setSelectedSlotId((current) => current === slotId ? null : slotId);
  }

  async function handleSubmitOffer() {
    if (!listing || isActionLoading) return;
    const amount = Number(offerAmount.replace(/[,\s]/g, ''));
    if (!Number.isFinite(amount) || amount <= 0) {
      Alert.alert(t('forms.validation'), t('offers.amountRequired'));
      return;
    }
    setIsActionLoading(true);
    try {
      await makeMarketplaceOffer(listing.id, { amount, message: offerMessage.trim() || null });
      await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      setOfferOpen(false);
      setOfferAmount('');
      setOfferMessage('');
      Alert.alert(t('offers.sent'), t('offers.sentHint'));
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('offers.failed'));
    } finally {
      setIsActionLoading(false);
    }
  }

  async function handleSubmitReport() {
    if (!listing || isActionLoading) return;
    if (!reportDescription.trim()) {
      Alert.alert(t('forms.validation'), t('detail.reportRequired'));
      return;
    }
    setIsActionLoading(true);
    try {
      await reportMarketplaceListing(listing.id, {
        reason: reportReason,
        description: reportDescription.trim(),
      });
      await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      setReportOpen(false);
      setReportReason('misleading');
      setReportDescription('');
      Alert.alert(t('detail.reportSubmittedTitle'), t('detail.reportSubmittedHint'));
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('detail.reportFailed'));
    } finally {
      setIsActionLoading(false);
    }
  }

  async function openCollections() {
    setCollectionOpen(true);
    if (collections.length > 0 || isCollectionLoading) return;
    setIsCollectionLoading(true);
    try {
      const response = await getMarketplaceCollections();
      setCollections(response.data);
    } catch {
      Alert.alert(t('common:errors.alertTitle'), t('collections.unableToLoad'));
    } finally {
      setIsCollectionLoading(false);
    }
  }

  async function addToCollection(collection: MarketplaceCollection) {
    if (!listing) return;
    setIsActionLoading(true);
    try {
      await addMarketplaceCollectionItem(collection.id, listing.id);
      await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      setCollectionOpen(false);
      Alert.alert(t('collections.addedTitle'), t('collections.addedHint', { name: collection.name }));
    } catch {
      Alert.alert(t('common:errors.alertTitle'), t('collections.addFailed'));
    } finally {
      setIsActionLoading(false);
    }
  }

  async function handleShare() {
    try {
      await Share.share({
        title: listing!.title,
        message: `${listing!.title}\n\n${APP_URL}/marketplace/${listing!.id}`,
        url: `${APP_URL}/marketplace/${listing!.id}`,
      });
    } catch {
      // Share dismissed.
    }
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar
        title={t('detail.title')}
        backLabel={t('common:back')}
        fallbackHref={'/(modals)/marketplace' as Href}
        rightAction={{ accessibilityLabel: t('detail.share'), icon: 'share-outline', onPress: handleShare }}
      />

      <ScrollView contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 142 }}>
        <HeroCard className="mb-3 overflow-hidden rounded-panel p-0">
          <View className="h-1.5" style={{ backgroundColor: accent }} />
          <HeroCard.Body className="gap-4 p-4">
            {videoUrl ? (
              <Surface variant="secondary" className="aspect-video overflow-hidden rounded-panel-inner bg-black p-0">
                <Video
                  accessibilityLabel={t('detail.video')}
                  resizeMode={ResizeMode.CONTAIN}
                  shouldPlay={false}
                  source={{ uri: videoUrl }}
                  style={{ width: '100%', height: '100%' }}
                  useNativeControls
                />
              </Surface>
            ) : null}

            {activeImageUrl || !videoUrl ? (
              <Surface variant="secondary" className="aspect-[4/3] items-center justify-center overflow-hidden rounded-panel-inner p-0">
                {activeImageUrl ? (
                  <Image source={{ uri: activeImageUrl }} className="h-full w-full" resizeMode="cover" />
                ) : (
                  <View className="items-center gap-2">
                    <Ionicons name="bag-handle-outline" size={44} color={accent} />
                    <Text className="text-sm" style={{ color: theme.textSecondary }}>{t('detail.noImages')}</Text>
                  </View>
                )}
              </Surface>
            ) : null}

            {images.length > 1 ? (
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
                {images.map((image, index) => {
                  const thumb = resolveImageUrl(image.thumbnail_url ?? image.url);
                  return (
                    <HeroButton
                      key={`${image.id ?? index}`}
                      isIconOnly
                      variant={activeImage === index ? 'primary' : 'secondary'}
                      onPress={() => setActiveImage(index)}
                      style={activeImage === index ? { backgroundColor: primary } : undefined}
                    >
                      {thumb ? <Image source={{ uri: thumb }} className="size-9 rounded-xl" resizeMode="cover" /> : <Ionicons name="image-outline" size={17} color={activeImage === index ? '#fff' : primary} />}
                    </HeroButton>
                  );
                })}
              </ScrollView>
            ) : null}

            <View className="gap-2">
              <View className="flex-row flex-wrap gap-2">
                <Chip size="sm" variant="secondary">
                  <Ionicons name={listing.price_type === 'free' ? 'gift-outline' : 'pricetag-outline'} size={12} color={accent} />
                  <Chip.Label>{priceLabel}</Chip.Label>
                </Chip>
                <Chip size="sm" variant="secondary">
                  <Chip.Label>{t(`priceType.${listing.price_type}`)}</Chip.Label>
                </Chip>
                {listing.condition ? (
                  <Chip size="sm" variant="secondary">
                    <Chip.Label>{t(`condition.${listing.condition}`)}</Chip.Label>
                  </Chip>
                ) : null}
              </View>
              <Text className="text-2xl font-bold leading-8" style={{ color: theme.text }}>{listing.title}</Text>
              {listing.tagline ? <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{listing.tagline}</Text> : null}
            </View>
          </HeroCard.Body>
        </HeroCard>

        <HeroCard className="mb-3 rounded-panel p-0">
          <HeroCard.Body className="gap-3 p-4">
            <Text className="text-base font-bold" style={{ color: theme.text }}>{t('detail.seller')}</Text>
            <Surface variant="secondary" className="flex-row items-center gap-3 rounded-panel-inner p-3">
              <Avatar uri={listing.user?.avatar_url} name={listing.user?.name} size={44} />
              <View className="min-w-0 flex-1">
                <Text className="text-base font-semibold" style={{ color: theme.text }} numberOfLines={1}>
                  {listing.user?.name ?? t('common.seller')}
                </Text>
                <Text className="text-xs" style={{ color: theme.textSecondary }} numberOfLines={1}>
                  {listing.user?.is_verified ? t('detail.verifiedSeller') : t('detail.communitySeller')}
                </Text>
              </View>
              {listing.user?.is_verified ? <Ionicons name="shield-checkmark-outline" size={20} color={theme.success} /> : null}
            </Surface>
            {listing.user?.id ? (
              <HeroButton variant="secondary" onPress={() => router.push({ pathname: '/(modals)/marketplace-seller', params: { id: String(listing.user?.id) } } as unknown as Href)}>
                <Ionicons name="storefront-outline" size={16} color={primary} />
                <HeroButton.Label>{t('detail.viewSeller')}</HeroButton.Label>
              </HeroButton>
            ) : null}
          </HeroCard.Body>
        </HeroCard>

        <HeroCard className="mb-3 rounded-panel p-0">
          <HeroCard.Body className="gap-3 p-4">
            <Text className="text-base font-bold" style={{ color: theme.text }}>{t('detail.description')}</Text>
            <Text className="text-sm leading-6" style={{ color: theme.textSecondary }}>{listing.description}</Text>
            {templateEntries.length > 0 ? (
              <View className="gap-3 border-t pt-3" style={{ borderColor: theme.border }}>
                <Text className="text-sm font-bold" style={{ color: theme.text }}>{t('detail.additionalDetails')}</Text>
                <View className="gap-2">
                  {templateEntries.map((entry) => (
                    <Surface key={entry.key} variant="secondary" className="gap-1 rounded-panel-inner p-3">
                      <Text className="text-[11px] font-bold uppercase" style={{ color: theme.textMuted }}>
                        {t('detail.templateFieldLabel', { field: entry.label })}
                      </Text>
                      <Text className="text-sm font-semibold" style={{ color: theme.text }}>{entry.value}</Text>
                    </Surface>
                  ))}
                </View>
              </View>
            ) : null}
            <View className="flex-row flex-wrap gap-2">
              {listing.location ? (
                <Chip size="sm" variant="secondary">
                  <Ionicons name="location-outline" size={12} color={primary} />
                  <Chip.Label>{listing.location}</Chip.Label>
                </Chip>
              ) : null}
              <Chip size="sm" variant="secondary">
                <Ionicons name="cube-outline" size={12} color={theme.textSecondary} />
                <Chip.Label>{t('detail.quantity', { count: listing.quantity })}</Chip.Label>
              </Chip>
              <Chip size="sm" variant="secondary">
                <Ionicons name="eye-outline" size={12} color={theme.textSecondary} />
                <Chip.Label>{t('detail.views', { count: listing.views_count })}</Chip.Label>
              </Chip>
              <Chip size="sm" variant="secondary">
                <Ionicons name="car-outline" size={12} color={theme.textSecondary} />
                <Chip.Label>{t(`delivery_method.${listing.delivery_method || 'other'}`)}</Chip.Label>
              </Chip>
            </View>
          </HeroCard.Body>
        </HeroCard>

        {listing.delivery_method === 'community_delivery' ? (
          <CommunityDeliveryInfoCard primary={primary} theme={theme} />
        ) : null}

        {sellerListings.length > 0 ? (
          <MoreFromSellerCard
            listings={sellerListings}
            sellerName={listing.user?.name ?? t('common.seller')}
            primary={primary}
            theme={theme}
          />
        ) : null}

        {isOwner ? (
          <HeroCard className="rounded-panel p-0">
            <HeroCard.Body className="gap-3 p-4">
              <Text className="text-base font-bold" style={{ color: theme.text }}>{t('owner.title')}</Text>
              <View className="flex-row gap-2">
                <HeroButton className="flex-1" variant="primary" onPress={() => router.push({ pathname: '/(modals)/edit-marketplace-listing', params: { id: String(listing.id) } } as unknown as Href)} style={{ backgroundColor: primary }}>
                  <Ionicons name="create-outline" size={16} color="#fff" />
                  <HeroButton.Label>{t('owner.edit')}</HeroButton.Label>
                </HeroButton>
                <HeroButton className="flex-1" variant="secondary" onPress={() => router.push('/(modals)/marketplace-my-listings' as Href)}>
                  <Ionicons name="stats-chart-outline" size={16} color={primary} />
                  <HeroButton.Label>{t('owner.manage')}</HeroButton.Label>
                </HeroButton>
              </View>
            </HeroCard.Body>
          </HeroCard>
        ) : null}

        {canBuy ? (
          <HeroCard className="mb-3 rounded-panel p-0">
            <HeroCard.Body className="gap-3 p-4">
              <Text className="text-base font-bold" style={{ color: theme.text }}>{t('checkout.title')}</Text>
              {pickupSlots.length > 0 ? (
                <View className="gap-2">
                  <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('pickup.chooseSlot')}</Text>
                  <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
                    {pickupSlots.map((slot) => (
                      <HeroButton
                        key={slot.id}
                        size="sm"
                        variant={selectedSlotId === slot.id ? 'primary' : 'secondary'}
                        onPress={() => togglePickupSlot(slot.id)}
                        accessibilityLabel={formatPickupSlot(slot, t('pickup.slotFallback', { id: slot.id }))}
                        accessibilityState={{ selected: selectedSlotId === slot.id }}
                        testID={`marketplace-pickup-slot-${slot.id}`}
                        style={selectedSlotId === slot.id ? { backgroundColor: primary } : undefined}
                      >
                        <HeroButton.Label onPress={() => togglePickupSlot(slot.id)}>{formatPickupSlot(slot, t('pickup.slotFallback', { id: slot.id }))}</HeroButton.Label>
                      </HeroButton>
                    ))}
                  </ScrollView>
                </View>
              ) : null}
              {couponsEnabled ? (
                <View className="flex-row gap-2">
                  <View className="min-w-0 flex-1">
                    <FormInput label={t('checkout.coupon')} value={couponCode} onChangeText={(value) => { setCouponCode(value); setCouponApplied(false); }} placeholder={t('checkout.couponPlaceholder')} />
                  </View>
                  <HeroButton className="self-end" variant={couponApplied ? 'primary' : 'secondary'} onPress={() => void applyCoupon()} isDisabled={isActionLoading || !couponCode.trim()} style={couponApplied ? { backgroundColor: theme.success } : undefined}>
                    <HeroButton.Label>{couponApplied ? t('checkout.applied') : t('checkout.apply')}</HeroButton.Label>
                  </HeroButton>
                </View>
              ) : null}
            </HeroCard.Body>
          </HeroCard>
        ) : null}
      </ScrollView>

      {!isOwner ? (
        <Surface variant="default" className="border-t border-border/50 px-4 pt-3 pb-4">
          <View className="gap-2">
            <View className="flex-row gap-2">
              <HeroButton className="flex-1" variant="secondary" onPress={handleToggleSave}>
                <Ionicons name={listing.is_saved ? 'heart' : 'heart-outline'} size={17} color={primary} />
                <HeroButton.Label>{listing.is_saved ? t('detail.saved') : t('detail.save')}</HeroButton.Label>
              </HeroButton>
              <HeroButton className="flex-1" variant="secondary" onPress={() => void openCollections()}>
                <Ionicons name="folder-open-outline" size={17} color={primary} />
                <HeroButton.Label>{t('detail.addToCollection')}</HeroButton.Label>
              </HeroButton>
            </View>
            <View className="flex-row gap-2">
              <HeroButton className="flex-1" variant="secondary" onPress={() => setOfferOpen(true)}>
                <Ionicons name="hand-left-outline" size={17} color={primary} />
                <HeroButton.Label>{t('detail.makeOffer')}</HeroButton.Label>
              </HeroButton>
              {canBuy ? (
                <HeroButton className="flex-1" variant="primary" onPress={handleBuyNow} isDisabled={isActionLoading} style={{ backgroundColor: primary }}>
                  <Ionicons name="card-outline" size={17} color="#fff" />
                  <HeroButton.Label>{t('detail.buyNow')}</HeroButton.Label>
                </HeroButton>
              ) : null}
            </View>
            <HeroButton variant="secondary" onPress={() => setReportOpen(true)}>
              <Ionicons name="flag-outline" size={17} color={theme.error} />
              <HeroButton.Label>{t('detail.reportListing')}</HeroButton.Label>
            </HeroButton>
          </View>
        </Surface>
      ) : null}

      <Modal visible={collectionOpen} transparent animationType="slide" onRequestClose={() => setCollectionOpen(false)}>
        <View className="flex-1 justify-end bg-black/40">
          <Surface variant="default" className="max-h-[72%] rounded-t-[28px] p-4">
            <View className="mb-4 flex-row items-center justify-between">
              <Text className="text-lg font-bold" style={{ color: theme.text }}>{t('collections.addTitle')}</Text>
              <HeroButton isIconOnly variant="secondary" onPress={() => setCollectionOpen(false)}>
                <Ionicons name="close-outline" size={20} color={primary} />
              </HeroButton>
            </View>
            {isCollectionLoading ? (
              <View className="py-8"><LoadingSpinner /></View>
            ) : collections.length === 0 ? (
              <View className="gap-3">
                <EmptyState icon="folder-open-outline" title={t('collections.empty')} subtitle={t('collections.emptyHint')} />
                <HeroButton variant="primary" onPress={() => { setCollectionOpen(false); router.push('/(modals)/marketplace-tools' as Href); }} style={{ backgroundColor: primary }}>
                  <Ionicons name="add-outline" size={17} color="#fff" />
                  <HeroButton.Label>{t('collections.manage')}</HeroButton.Label>
                </HeroButton>
              </View>
            ) : (
              <ScrollView contentContainerStyle={{ gap: 10 }}>
                {collections.map((collection) => (
                  <HeroButton key={collection.id} variant="secondary" onPress={() => void addToCollection(collection)} isDisabled={isActionLoading}>
                    <Ionicons name="folder-outline" size={17} color={primary} />
                    <HeroButton.Label>{collection.name}</HeroButton.Label>
                  </HeroButton>
                ))}
              </ScrollView>
            )}
          </Surface>
        </View>
      </Modal>

      <Modal visible={offerOpen} transparent animationType="slide" onRequestClose={() => setOfferOpen(false)}>
        <View className="flex-1 justify-end bg-black/40">
          <Surface variant="default" className="rounded-t-[28px] p-4">
            <View className="mb-4 flex-row items-center justify-between">
              <Text className="text-lg font-bold" style={{ color: theme.text }}>{t('offers.makeTitle')}</Text>
              <HeroButton isIconOnly variant="secondary" onPress={() => setOfferOpen(false)}>
                <Ionicons name="close-outline" size={20} color={primary} />
              </HeroButton>
            </View>
            <View className="gap-3">
              <FormInput label={t('offers.amount')} value={offerAmount} onChangeText={setOfferAmount} placeholder={t('offers.amountPlaceholder')} keyboardType="decimal-pad" />
              <FormInput label={t('offers.message')} value={offerMessage} onChangeText={setOfferMessage} placeholder={t('offers.messagePlaceholder')} multiline />
              <HeroButton variant="primary" onPress={handleSubmitOffer} isDisabled={isActionLoading} style={{ backgroundColor: primary }}>
                <Ionicons name="send-outline" size={17} color="#fff" />
                <HeroButton.Label>{t('offers.submit')}</HeroButton.Label>
              </HeroButton>
            </View>
          </Surface>
        </View>
      </Modal>

      <Modal visible={reportOpen} transparent animationType="slide" onRequestClose={() => setReportOpen(false)}>
        <View className="flex-1 justify-end bg-black/40">
          <Surface variant="default" className="max-h-[86%] rounded-t-[28px] p-4">
            <View className="mb-4 flex-row items-center justify-between">
              <Text className="text-lg font-bold" style={{ color: theme.text }}>{t('detail.reportTitle')}</Text>
              <HeroButton isIconOnly variant="secondary" onPress={() => setReportOpen(false)}>
                <Ionicons name="close-outline" size={20} color={primary} />
              </HeroButton>
            </View>
            <ScrollView contentContainerStyle={{ gap: 12 }} showsVerticalScrollIndicator={false}>
              <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('detail.reportHint')}</Text>
              <View className="gap-2">
                <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('detail.reportReason')}</Text>
                <View className="flex-row flex-wrap gap-2">
                  {REPORT_REASONS.map((reason) => (
                    <HeroButton
                      key={reason}
                      size="sm"
                      variant={reportReason === reason ? 'primary' : 'secondary'}
                      onPress={() => setReportReason(reason)}
                      style={reportReason === reason ? { backgroundColor: primary } : { minWidth: '46%' }}
                    >
                      <HeroButton.Label>{t(`detail.reportReasons.${reason}`)}</HeroButton.Label>
                    </HeroButton>
                  ))}
                </View>
              </View>
              <FormInput label={t('detail.reportDescription')} value={reportDescription} onChangeText={setReportDescription} placeholder={t('detail.reportDescriptionPlaceholder')} multiline />
              <HeroButton variant="danger" onPress={handleSubmitReport} isDisabled={isActionLoading}>
                <Ionicons name="flag-outline" size={17} color="#fff" />
                <HeroButton.Label>{t('detail.reportSubmit')}</HeroButton.Label>
              </HeroButton>
            </ScrollView>
          </Surface>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

function CommunityDeliveryInfoCard({ primary, theme }: { primary: string; theme: ReturnType<typeof useTheme> }) {
  const { t } = useTranslation('marketplace');
  const steps: Array<{ icon: ComponentProps<typeof Ionicons>['name']; label: string }> = [
    { icon: 'people-outline', label: t('communityDelivery.step1') },
    { icon: 'time-outline', label: t('communityDelivery.step2') },
    { icon: 'checkmark-circle-outline', label: t('communityDelivery.step3') },
  ];

  return (
    <HeroCard className="mb-3 overflow-hidden rounded-panel p-0">
      <View className="h-1.5" style={{ backgroundColor: theme.success }} />
      <HeroCard.Body className="gap-4 p-4">
        <View className="flex-row items-start gap-3">
          <View className="size-12 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(theme.success, 0.14) }}>
            <Ionicons name="car-outline" size={23} color={theme.success} />
          </View>
          <View className="min-w-0 flex-1 gap-1">
            <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>
              {t('communityDelivery.eyebrow')}
            </Text>
            <Text className="text-base font-bold" style={{ color: theme.text }}>
              {t('communityDelivery.title')}
            </Text>
            <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>
              {t('communityDelivery.description')}
            </Text>
          </View>
        </View>
        <View className="gap-2">
          {steps.map((step) => (
            <Surface key={step.icon} variant="secondary" className="flex-row items-center gap-3 rounded-panel-inner px-3 py-2.5">
              <View className="size-8 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(primary, 0.12) }}>
                <Ionicons name={step.icon} size={15} color={primary} />
              </View>
              <Text className="min-w-0 flex-1 text-sm leading-5" style={{ color: theme.textSecondary }}>
                {step.label}
              </Text>
            </Surface>
          ))}
        </View>
        <Text className="text-xs leading-5" style={{ color: theme.textMuted }}>
          {t('communityDelivery.orderManagedHint')}
        </Text>
      </HeroCard.Body>
    </HeroCard>
  );
}

function MoreFromSellerCard({
  listings,
  sellerName,
  primary,
  theme,
}: {
  listings: MarketplaceListingItem[];
  sellerName: string;
  primary: string;
  theme: ReturnType<typeof useTheme>;
}) {
  const { t } = useTranslation('marketplace');
  return (
    <HeroCard className="mb-3 rounded-panel p-0">
      <HeroCard.Body className="gap-3 p-4">
        <View className="flex-row items-center justify-between gap-3">
          <Text className="min-w-0 flex-1 text-base font-bold" style={{ color: theme.text }} numberOfLines={2}>
            {t('detail.moreFromSeller', { name: sellerName })}
          </Text>
          <Ionicons name="storefront-outline" size={18} color={primary} />
        </View>
        <View className="gap-2">
          {listings.map((item) => (
            <HeroButton
              key={item.id}
              className="justify-start"
              variant="secondary"
              onPress={() => router.push({ pathname: '/(modals)/marketplace-detail', params: { id: String(item.id) } } as unknown as Href)}
            >
              <Ionicons name="bag-handle-outline" size={16} color={primary} />
              <HeroButton.Label>{item.title}</HeroButton.Label>
            </HeroButton>
          ))}
        </View>
      </HeroCard.Body>
    </HeroCard>
  );
}

function formatPickupSlot(slot: MarketplacePickupSlotOption, fallback: string) {
  try {
    if (!slot.slot_start) return fallback;
    const remaining = Number.isFinite(Number(slot.remaining)) ? ` (${slot.remaining})` : '';
    return `${new Date(slot.slot_start).toLocaleString()}${remaining}`;
  } catch {
    return fallback;
  }
}

function getListingTemplateEntries(templateData?: Record<string, unknown> | null): Array<{ key: string; label: string; value: string }> {
  if (!templateData) return [];
  return Object.entries(templateData).flatMap(([key, value]) => {
    if (value === null || value === undefined) return [];
    const text = Array.isArray(value) ? value.map((item) => String(item)).join(', ') : String(value);
    if (!text.trim()) return [];
    return [{ key, label: formatTemplateFieldLabel(key), value: text }];
  });
}

function formatTemplateFieldLabel(key: string): string {
  const label = key
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
  return label ? label.charAt(0).toUpperCase() + label.slice(1) : key;
}

function FormInput({
  label,
  value,
  onChangeText,
  placeholder,
  keyboardType = 'default',
  multiline = false,
}: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  placeholder: string;
  keyboardType?: 'default' | 'decimal-pad';
  multiline?: boolean;
}) {
  const theme = useTheme();
  return (
    <View className="gap-2">
      <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{label}</Text>
      <TextInput
        className={`${multiline ? 'min-h-24 py-3' : 'min-h-12'} rounded-panel-inner border px-3 text-sm`}
        style={{ borderColor: theme.border, color: theme.text, backgroundColor: theme.bg, textAlignVertical: multiline ? 'top' : 'center' }}
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor={theme.textMuted}
        keyboardType={keyboardType}
        multiline={multiline}
      />
    </View>
  );
}
