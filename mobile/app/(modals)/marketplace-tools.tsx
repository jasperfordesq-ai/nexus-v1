// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useMemo, useState } from 'react';
import { Alert, FlatList, Modal, ScrollView, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Surface, Text } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import AppTopBar from '@/components/ui/AppTopBar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import {
  createMarketplaceCollection,
  createMarketplacePickupSlot,
  createMarketplaceSavedSearch,
  createMerchantCoupon,
  deleteMarketplaceCollection,
  deleteMarketplacePickupSlot,
  deleteMarketplaceSavedSearch,
  deleteMerchantCoupon,
  getMarketplaceCollections,
  getMarketplacePickupSlots,
  getMarketplaceSavedSearches,
  getMerchantCoupons,
  getMerchantCouponRedemptions,
  getMyMarketplacePickups,
  getMyMarketplacePromotions,
  getMyMarketplaceListings,
  marketplaceHasMore,
  marketplaceNextCursor,
  promoteMarketplaceListing,
  scanMarketplacePickup,
  updateMerchantCoupon,
  type MarketplaceCollection,
  type MarketplaceListingItem,
  type MarketplacePickupReservation,
  type MarketplacePickupSlot,
  type MarketplacePromotion,
  type MarketplaceSavedSearch,
  type MerchantCoupon,
  type MerchantCouponRedemption,
} from '@/lib/api/marketplace';
import { useApi } from '@/lib/hooks/useApi';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';

type ToolTab = 'collections' | 'savedSearches' | 'promotions' | 'pickups' | 'coupons';
type CouponDiscountType = 'percent' | 'fixed' | 'bogo';
type CouponStatus = 'draft' | 'active' | 'paused' | 'expired';
type CouponAppliesTo = 'all_listings' | 'listing_ids' | 'category_ids';

interface CouponFormState {
  code: string;
  title: string;
  description: string;
  discountType: CouponDiscountType;
  discountValue: string;
  minOrderCents: string;
  maxUses: string;
  maxUsesPerMember: string;
  validFrom: string;
  validUntil: string;
  status: CouponStatus;
  appliesTo: CouponAppliesTo;
}

const TABS: ToolTab[] = ['collections', 'savedSearches', 'promotions', 'pickups', 'coupons'];
const COUPON_DISCOUNT_TYPES: CouponDiscountType[] = ['percent', 'fixed', 'bogo'];
const COUPON_STATUSES: CouponStatus[] = ['draft', 'active', 'paused', 'expired'];
const COUPON_APPLIES_TO: CouponAppliesTo[] = ['all_listings', 'listing_ids', 'category_ids'];

const emptyCouponForm: CouponFormState = {
  code: '',
  title: '',
  description: '',
  discountType: 'percent',
  discountValue: '10',
  minOrderCents: '',
  maxUses: '',
  maxUsesPerMember: '1',
  validFrom: '',
  validUntil: '',
  status: 'active',
  appliesTo: 'all_listings',
};

export default function MarketplaceToolsRoute() {
  return (
    <ModalErrorBoundary>
      <MarketplaceToolsScreen />
    </ModalErrorBoundary>
  );
}

function MarketplaceToolsScreen() {
  const { t } = useTranslation(['marketplace', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [tab, setTab] = useState<ToolTab>('collections');

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={t('tools.title')} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace' as Href} />
      <FlatList
        data={[tab]}
        keyExtractor={(item) => item}
        contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 132 }}
        ListHeaderComponent={
          <>
            <HeroCard className="mb-3 overflow-hidden rounded-panel p-0">
              <View className="h-1.5" style={{ backgroundColor: primary }} />
              <HeroCard.Body className="gap-4 p-4">
                <View className="flex-row items-start gap-3">
                  <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                    <Ionicons name="construct-outline" size={25} color={primary} />
                  </View>
                  <View className="min-w-0 flex-1">
                    <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('tools.eyebrow')}</Text>
                    <Text className="text-2xl font-bold" style={{ color: theme.text }}>{t('tools.title')}</Text>
                    <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('tools.subtitle')}</Text>
                  </View>
                </View>
                <View className="flex-row gap-2">
                  <HeroButton className="flex-1" variant="secondary" onPress={() => router.push('/(modals)/marketplace-merchant-onboarding' as Href)}>
                    <Ionicons name="storefront-outline" size={16} color={primary} />
                    <HeroButton.Label>{t('tools.onboarding')}</HeroButton.Label>
                  </HeroButton>
                  <HeroButton className="flex-1" variant="secondary" onPress={() => router.push('/(modals)/marketplace-stripe-onboarding' as Href)}>
                    <Ionicons name="card-outline" size={16} color={primary} />
                    <HeroButton.Label>{t('tools.payments')}</HeroButton.Label>
                  </HeroButton>
                </View>
                <HeroButton variant="secondary" onPress={() => router.push('/(modals)/marketplace-shipping-options' as Href)}>
                  <Ionicons name="car-outline" size={16} color={primary} />
                  <HeroButton.Label>{t('tools.shipping')}</HeroButton.Label>
                </HeroButton>
              </HeroCard.Body>
            </HeroCard>

            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8, paddingBottom: 12 }}>
              {TABS.map((item) => (
                <HeroButton key={item} size="sm" variant={tab === item ? 'primary' : 'secondary'} onPress={() => setTab(item)} style={tab === item ? { backgroundColor: primary } : undefined}>
                  <HeroButton.Label>{t(`tools.tabs.${item}`)}</HeroButton.Label>
                </HeroButton>
              ))}
            </ScrollView>
          </>
        }
        renderItem={() => (
          <>
            {tab === 'collections' ? <CollectionsPanel /> : null}
            {tab === 'savedSearches' ? <SavedSearchesPanel /> : null}
            {tab === 'promotions' ? <PromotionsPanel /> : null}
            {tab === 'pickups' ? <PickupsPanel /> : null}
            {tab === 'coupons' ? <CouponsPanel /> : null}
          </>
        )}
      />
    </SafeAreaView>
  );
}

function CollectionsPanel() {
  const { t } = useTranslation(['marketplace', 'common']);
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [isSaving, setIsSaving] = useState(false);
  const collections = useApi(() => getMarketplaceCollections(), [], { enabled: true });

  async function create() {
    if (!name.trim()) return;
    setIsSaving(true);
    try {
      await createMarketplaceCollection({ name: name.trim(), description: description.trim() || null, is_public: false });
      setName('');
      setDescription('');
      collections.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('tools.collections.saveFailed'));
    } finally {
      setIsSaving(false);
    }
  }

  async function remove(item: MarketplaceCollection) {
    try {
      await deleteMarketplaceCollection(item.id);
      collections.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('tools.collections.deleteFailed'));
    }
  }

  return (
    <PanelCard icon="albums-outline" title={t('tools.collections.title')} subtitle={t('tools.collections.subtitle')}>
      <View className="gap-3">
        <FormInput label={t('tools.collections.name')} value={name} onChangeText={setName} placeholder={t('tools.collections.namePlaceholder')} />
        <FormInput label={t('tools.collections.description')} value={description} onChangeText={setDescription} placeholder={t('tools.collections.descriptionPlaceholder')} />
        <HeroButton variant="primary" onPress={create} isDisabled={isSaving || !name.trim()}>
          <HeroButton.Label>{t('tools.collections.create')}</HeroButton.Label>
        </HeroButton>
      </View>
      <PanelList
        isLoading={collections.isLoading}
        items={collections.data?.data ?? []}
        emptyTitle={t('tools.collections.empty')}
        renderItem={(item) => (
          <ToolRow
            key={item.id}
            icon="albums-outline"
            title={item.name}
            subtitle={item.description || t('tools.collections.count', { count: item.item_count })}
            trailing={t('tools.delete')}
            onPress={() => void remove(item)}
          />
        )}
      />
    </PanelCard>
  );
}

function SavedSearchesPanel() {
  const { t } = useTranslation(['marketplace', 'common']);
  const [name, setName] = useState('');
  const [query, setQuery] = useState('');
  const searches = useApi(() => getMarketplaceSavedSearches(), [], { enabled: true });

  async function create() {
    if (!name.trim()) return;
    try {
      await createMarketplaceSavedSearch({ name: name.trim(), search_query: query.trim() || null, alert_frequency: 'daily', alert_channel: 'push' });
      setName('');
      setQuery('');
      searches.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('tools.savedSearches.saveFailed'));
    }
  }

  async function remove(item: MarketplaceSavedSearch) {
    try {
      await deleteMarketplaceSavedSearch(item.id);
      searches.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('tools.savedSearches.deleteFailed'));
    }
  }

  return (
    <PanelCard icon="notifications-outline" title={t('tools.savedSearches.title')} subtitle={t('tools.savedSearches.subtitle')}>
      <View className="gap-3">
        <FormInput label={t('tools.savedSearches.name')} value={name} onChangeText={setName} placeholder={t('tools.savedSearches.namePlaceholder')} />
        <FormInput label={t('tools.savedSearches.query')} value={query} onChangeText={setQuery} placeholder={t('tools.savedSearches.queryPlaceholder')} />
        <HeroButton variant="primary" onPress={create} isDisabled={!name.trim()}>
          <HeroButton.Label>{t('tools.savedSearches.create')}</HeroButton.Label>
        </HeroButton>
      </View>
      <PanelList
        isLoading={searches.isLoading}
        items={searches.data?.data ?? []}
        emptyTitle={t('tools.savedSearches.empty')}
        renderItem={(item) => (
          <ToolRow
            key={item.id}
            icon="search-outline"
            title={item.name}
            subtitle={item.search_query || t(`tools.savedSearches.frequency.${item.alert_frequency}`)}
            trailing={t('tools.delete')}
            onPress={() => void remove(item)}
          />
        )}
      />
    </PanelCard>
  );
}

function PromotionsPanel() {
  const { t } = useTranslation(['marketplace', 'common']);
  const { user } = useAuth();
  const [selectedListing, setSelectedListing] = useState<number | null>(null);
  const [promotionType, setPromotionType] = useState<'bump' | 'featured' | 'top_of_category' | 'homepage_carousel'>('featured');
  const promotions = useApi(() => getMyMarketplacePromotions(), [], { enabled: true });
  const listings = usePaginatedApi<MarketplaceListingItem, Awaited<ReturnType<typeof getMyMarketplaceListings>>>(
    (cursor) => getMyMarketplaceListings(cursor, user?.id),
    (response) => ({ items: response.data, cursor: marketplaceNextCursor(response), hasMore: marketplaceHasMore(response) }),
    [user?.id],
  );

  useEffect(() => {
    if (!selectedListing && listings.items[0]) setSelectedListing(listings.items[0].id);
  }, [listings.items, selectedListing]);

  async function promote() {
    if (!selectedListing) return;
    try {
      await promoteMarketplaceListing(selectedListing, promotionType);
      promotions.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('tools.promotions.failed'));
    }
  }

  return (
    <PanelCard icon="megaphone-outline" title={t('tools.promotions.title')} subtitle={t('tools.promotions.subtitle')}>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
        {listings.items.map((listing) => (
          <HeroButton key={listing.id} size="sm" variant={selectedListing === listing.id ? 'primary' : 'secondary'} onPress={() => setSelectedListing(listing.id)}>
            <HeroButton.Label>{listing.title}</HeroButton.Label>
          </HeroButton>
        ))}
      </ScrollView>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
        {(['bump', 'featured', 'top_of_category', 'homepage_carousel'] as const).map((type) => (
          <HeroButton key={type} size="sm" variant={promotionType === type ? 'primary' : 'secondary'} onPress={() => setPromotionType(type)}>
            <HeroButton.Label>{t(`tools.promotions.types.${type}`)}</HeroButton.Label>
          </HeroButton>
        ))}
      </ScrollView>
      <HeroButton variant="primary" onPress={promote} isDisabled={!selectedListing}>
        <HeroButton.Label>{t('tools.promotions.promote')}</HeroButton.Label>
      </HeroButton>
      <PanelList
        isLoading={promotions.isLoading}
        items={promotions.data?.data ?? []}
        emptyTitle={t('tools.promotions.empty')}
        renderItem={(item: MarketplacePromotion) => (
          <ToolRow
            key={item.id}
            icon="megaphone-outline"
            title={item.listing?.title ?? t(`tools.promotions.types.${item.promotion_type}`, { defaultValue: item.promotion_type })}
            subtitle={t('tools.promotions.metrics', { impressions: item.impressions ?? 0, clicks: item.clicks ?? 0 })}
          />
        )}
      />
    </PanelCard>
  );
}

function PickupsPanel() {
  const { t } = useTranslation(['marketplace', 'common']);
  const [slotStart, setSlotStart] = useState('');
  const [slotEnd, setSlotEnd] = useState('');
  const [qrCode, setQrCode] = useState('');
  const slots = useApi(() => getMarketplacePickupSlots(), [], { enabled: true });
  const reservations = useApi(() => getMyMarketplacePickups(), [], { enabled: true });

  async function createSlot() {
    if (!slotStart.trim() || !slotEnd.trim()) return;
    try {
      await createMarketplacePickupSlot({ slot_start: slotStart.trim(), slot_end: slotEnd.trim(), capacity: 4, is_active: true });
      setSlotStart('');
      setSlotEnd('');
      slots.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('tools.pickups.slotFailed'));
    }
  }

  async function scan() {
    if (!qrCode.trim()) return;
    try {
      await scanMarketplacePickup(qrCode.trim());
      setQrCode('');
      reservations.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('tools.pickups.scanFailed'));
    }
  }

  async function remove(slot: MarketplacePickupSlot) {
    try {
      await deleteMarketplacePickupSlot(slot.id);
      slots.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('tools.pickups.deleteFailed'));
    }
  }

  return (
    <PanelCard icon="calendar-number-outline" title={t('tools.pickups.title')} subtitle={t('tools.pickups.subtitle')}>
      <View className="gap-3">
        <FormInput label={t('tools.pickups.start')} value={slotStart} onChangeText={setSlotStart} placeholder={t('tools.pickups.startPlaceholder')} />
        <FormInput label={t('tools.pickups.end')} value={slotEnd} onChangeText={setSlotEnd} placeholder={t('tools.pickups.endPlaceholder')} />
        <HeroButton variant="primary" onPress={createSlot} isDisabled={!slotStart.trim() || !slotEnd.trim()}>
          <HeroButton.Label>{t('tools.pickups.createSlot')}</HeroButton.Label>
        </HeroButton>
        <FormInput label={t('tools.pickups.qr')} value={qrCode} onChangeText={setQrCode} placeholder={t('tools.pickups.qrPlaceholder')} />
        <HeroButton variant="secondary" onPress={scan} isDisabled={!qrCode.trim()}>
          <HeroButton.Label>{t('tools.pickups.scan')}</HeroButton.Label>
        </HeroButton>
      </View>
      <PanelList
        isLoading={slots.isLoading}
        items={slots.data?.data ?? []}
        emptyTitle={t('tools.pickups.emptySlots')}
        renderItem={(item: MarketplacePickupSlot) => (
          <ToolRow
            key={item.id}
            icon="time-outline"
            title={formatDateTime(item.slot_start)}
            subtitle={t('tools.pickups.capacity', { booked: item.booked_count, capacity: item.capacity })}
            trailing={t('tools.delete')}
            onPress={() => void remove(item)}
          />
        )}
      />
      <PanelList
        isLoading={reservations.isLoading}
        items={reservations.data?.data ?? []}
        emptyTitle={t('tools.pickups.emptyReservations')}
        renderItem={(item: MarketplacePickupReservation) => (
          <ToolRow key={item.id} icon="qr-code-outline" title={t('tools.pickups.order', { order: item.order_id })} subtitle={item.status} />
        )}
      />
    </PanelCard>
  );
}

function CouponsPanel() {
  const { t } = useTranslation(['marketplace', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [form, setForm] = useState<CouponFormState>(emptyCouponForm);
  const [editingCoupon, setEditingCoupon] = useState<MerchantCoupon | null>(null);
  const [redemptionCoupon, setRedemptionCoupon] = useState<MerchantCoupon | null>(null);
  const [redemptions, setRedemptions] = useState<MerchantCouponRedemption[]>([]);
  const [isLoadingRedemptions, setIsLoadingRedemptions] = useState(false);
  const coupons = useApi(() => getMerchantCoupons(), [], { enabled: true });

  function updateForm(key: keyof CouponFormState, value: string) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function openEdit(coupon?: MerchantCoupon) {
    setEditingCoupon(coupon ?? null);
    setForm(coupon ? {
      code: coupon.code ?? '',
      title: coupon.title ?? '',
      description: coupon.description ?? '',
      discountType: coupon.discount_type ?? 'percent',
      discountValue: coupon.discount_value != null ? String(coupon.discount_value) : '',
      minOrderCents: coupon.min_order_cents != null ? String(coupon.min_order_cents) : '',
      maxUses: coupon.max_uses != null ? String(coupon.max_uses) : '',
      maxUsesPerMember: coupon.max_uses_per_member != null ? String(coupon.max_uses_per_member) : '1',
      validFrom: coupon.valid_from ? String(coupon.valid_from).slice(0, 16) : '',
      validUntil: coupon.valid_until ? String(coupon.valid_until).slice(0, 16) : '',
      status: coupon.status ?? 'active',
      appliesTo: coupon.applies_to ?? 'all_listings',
    } : emptyCouponForm);
  }

  async function save() {
    if (!form.title.trim()) return;
    const payload = couponPayload(form);
    try {
      if (editingCoupon) {
        await updateMerchantCoupon(editingCoupon.id, payload);
      } else {
        await createMerchantCoupon(payload);
      }
      setEditingCoupon(null);
      setForm(emptyCouponForm);
      coupons.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('tools.coupons.saveFailed'));
    }
  }

  async function remove(item: MerchantCoupon) {
    try {
      await deleteMerchantCoupon(item.id);
      coupons.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('tools.coupons.deleteFailed'));
    }
  }

  async function openRedemptions(item: MerchantCoupon) {
    setRedemptionCoupon(item);
    setRedemptions([]);
    setIsLoadingRedemptions(true);
    try {
      const response = await getMerchantCouponRedemptions(item.id);
      setRedemptions(response.data.items);
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('tools.coupons.redemptionsFailed'));
    } finally {
      setIsLoadingRedemptions(false);
    }
  }

  return (
    <PanelCard icon="ticket-outline" title={t('tools.coupons.title')} subtitle={t('tools.coupons.subtitle')}>
      <View className="gap-3">
        <FormInput label={t('tools.coupons.code')} value={form.code} onChangeText={(value) => updateForm('code', value.toUpperCase())} placeholder={t('tools.coupons.codePlaceholder')} />
        <FormInput label={t('tools.coupons.name')} value={form.title} onChangeText={(value) => updateForm('title', value)} placeholder={t('tools.coupons.namePlaceholder')} />
        <FormInput label={t('tools.coupons.description')} value={form.description} onChangeText={(value) => updateForm('description', value)} placeholder={t('tools.coupons.descriptionPlaceholder')} multiline />
        <View className="gap-2">
          <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('tools.coupons.discountType')}</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
            {COUPON_DISCOUNT_TYPES.map((type) => (
              <HeroButton key={type} size="sm" variant={form.discountType === type ? 'primary' : 'secondary'} onPress={() => updateForm('discountType', type)} style={form.discountType === type ? { backgroundColor: primary } : undefined}>
                <HeroButton.Label>{t(`tools.coupons.discountTypes.${type}`)}</HeroButton.Label>
              </HeroButton>
            ))}
          </ScrollView>
        </View>
        <FormInput label={t('tools.coupons.value')} value={form.discountValue} onChangeText={(value) => updateForm('discountValue', value)} placeholder={t('tools.coupons.valuePlaceholder')} keyboardType="decimal-pad" />
        <View className="flex-row gap-2">
          <View className="min-w-0 flex-1">
            <FormInput label={t('tools.coupons.maxUses')} value={form.maxUses} onChangeText={(value) => updateForm('maxUses', value)} placeholder={t('tools.coupons.maxUsesPlaceholder')} keyboardType="decimal-pad" />
          </View>
          <View className="min-w-0 flex-1">
            <FormInput label={t('tools.coupons.perMember')} value={form.maxUsesPerMember} onChangeText={(value) => updateForm('maxUsesPerMember', value)} placeholder={t('tools.coupons.perMemberPlaceholder')} keyboardType="decimal-pad" />
          </View>
        </View>
        <View className="flex-row gap-2">
          <View className="min-w-0 flex-1">
            <FormInput label={t('tools.coupons.validFrom')} value={form.validFrom} onChangeText={(value) => updateForm('validFrom', value)} placeholder={t('tools.coupons.datePlaceholder')} />
          </View>
          <View className="min-w-0 flex-1">
            <FormInput label={t('tools.coupons.validUntil')} value={form.validUntil} onChangeText={(value) => updateForm('validUntil', value)} placeholder={t('tools.coupons.datePlaceholder')} />
          </View>
        </View>
        <View className="gap-2">
          <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('tools.coupons.status')}</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
            {COUPON_STATUSES.map((status) => (
              <HeroButton key={status} size="sm" variant={form.status === status ? 'primary' : 'secondary'} onPress={() => updateForm('status', status)} style={form.status === status ? { backgroundColor: primary } : undefined}>
                <HeroButton.Label>{t(`tools.coupons.statuses.${status}`)}</HeroButton.Label>
              </HeroButton>
            ))}
          </ScrollView>
        </View>
        <View className="gap-2">
          <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('tools.coupons.appliesTo')}</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
            {COUPON_APPLIES_TO.map((scope) => (
              <HeroButton key={scope} size="sm" variant={form.appliesTo === scope ? 'primary' : 'secondary'} onPress={() => updateForm('appliesTo', scope)} style={form.appliesTo === scope ? { backgroundColor: primary } : undefined}>
                <HeroButton.Label>{t(`tools.coupons.applies.${scope}`)}</HeroButton.Label>
              </HeroButton>
            ))}
          </ScrollView>
        </View>
        <HeroButton variant="primary" onPress={save} isDisabled={!form.title.trim()}>
          <HeroButton.Label>{editingCoupon ? t('tools.coupons.update') : t('tools.coupons.create')}</HeroButton.Label>
        </HeroButton>
        {editingCoupon ? (
          <HeroButton variant="secondary" onPress={() => openEdit()}>
            <HeroButton.Label>{t('tools.coupons.cancelEdit')}</HeroButton.Label>
          </HeroButton>
        ) : null}
      </View>
      <PanelList
        isLoading={coupons.isLoading}
        items={coupons.data?.data.items ?? []}
        emptyTitle={t('tools.coupons.empty')}
        renderItem={(item: MerchantCoupon) => (
          <ToolRow
            key={item.id}
            icon="ticket-outline"
            title={item.title}
            subtitle={t('tools.coupons.discount', { value: couponDiscountLabel(item), status: t(`tools.coupons.statuses.${item.status}`, { defaultValue: item.status }), count: item.usage_count ?? item.used_count ?? 0 })}
            trailing={t('tools.coupons.edit')}
            onPress={() => openEdit(item)}
          />
        )}
      />
      <PanelList
        isLoading={false}
        items={coupons.data?.data.items ?? []}
        emptyTitle={t('tools.coupons.empty')}
        renderItem={(item: MerchantCoupon) => (
          <View key={`actions-${item.id}`} className="flex-row gap-2">
            <HeroButton className="flex-1" size="sm" variant="secondary" onPress={() => void openRedemptions(item)}>
              <HeroButton.Label>{t('tools.coupons.redemptions')}</HeroButton.Label>
            </HeroButton>
            <HeroButton className="flex-1" size="sm" variant="danger-soft" onPress={() => void remove(item)}>
              <HeroButton.Label>{t('tools.delete')}</HeroButton.Label>
            </HeroButton>
          </View>
        )}
      />

      <Modal visible={Boolean(redemptionCoupon)} transparent animationType="slide" onRequestClose={() => setRedemptionCoupon(null)}>
        <View className="flex-1 justify-end bg-black/40">
          <Surface variant="default" className="max-h-[78%] rounded-t-[28px] p-4">
            <View className="mb-4 flex-row items-center justify-between">
              <View className="min-w-0 flex-1">
                <Text className="text-lg font-bold" style={{ color: theme.text }}>{t('tools.coupons.redemptionsTitle')}</Text>
                <Text className="text-xs" style={{ color: theme.textSecondary }}>{redemptionCoupon?.title ?? ''}</Text>
              </View>
              <HeroButton isIconOnly variant="secondary" onPress={() => setRedemptionCoupon(null)}>
                <Ionicons name="close-outline" size={20} color={primary} />
              </HeroButton>
            </View>
            {isLoadingRedemptions ? (
              <View className="py-10"><LoadingSpinner /></View>
            ) : redemptions.length === 0 ? (
              <EmptyState icon="receipt-outline" title={t('tools.coupons.noRedemptions')} />
            ) : (
              <ScrollView contentContainerStyle={{ gap: 10 }}>
                {redemptions.map((redemption) => (
                  <ToolRow
                    key={redemption.id}
                    icon="receipt-outline"
                    title={t('tools.coupons.redemptionOrder', { order: redemption.order_id ?? '-' })}
                    subtitle={t('tools.coupons.redemptionValue', {
                      value: (redemption.discount_applied_cents / 100).toFixed(2),
                      date: redemption.redeemed_at ? new Date(redemption.redeemed_at).toLocaleDateString() : t('tools.coupons.dateUnknown'),
                    })}
                  />
                ))}
              </ScrollView>
            )}
          </Surface>
        </View>
      </Modal>
    </PanelCard>
  );
}

function couponPayload(form: CouponFormState) {
  return {
    code: form.code.trim() || null,
    title: form.title.trim(),
    description: form.description.trim() || null,
    discount_type: form.discountType,
    discount_value: form.discountType === 'bogo' ? null : Number(form.discountValue) || 0,
    min_order_cents: form.minOrderCents ? Number(form.minOrderCents) : null,
    max_uses: form.maxUses ? Number(form.maxUses) : null,
    max_uses_per_member: form.maxUsesPerMember ? Number(form.maxUsesPerMember) : 1,
    valid_from: form.validFrom.trim() || null,
    valid_until: form.validUntil.trim() || null,
    status: form.status,
    applies_to: form.appliesTo,
  };
}

function couponDiscountLabel(coupon: MerchantCoupon): string {
  if (coupon.discount_type === 'percent') return `${coupon.discount_value ?? 0}%`;
  if (coupon.discount_type === 'fixed') return `${coupon.discount_value ?? 0}`;
  return 'BOGO';
}

function PanelCard({
  icon,
  title,
  subtitle,
  children,
}: {
  icon: React.ComponentProps<typeof Ionicons>['name'];
  title: string;
  subtitle: string;
  children: React.ReactNode;
}) {
  const primary = usePrimaryColor();
  const theme = useTheme();
  return (
    <HeroCard className="rounded-panel p-0">
      <HeroCard.Body className="gap-4 p-4">
        <View className="flex-row items-start gap-3">
          <View className="size-12 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
            <Ionicons name={icon} size={23} color={primary} />
          </View>
          <View className="min-w-0 flex-1">
            <Text className="text-lg font-bold" style={{ color: theme.text }}>{title}</Text>
            <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{subtitle}</Text>
          </View>
        </View>
        {children}
      </HeroCard.Body>
    </HeroCard>
  );
}

function PanelList<T>({
  isLoading,
  items,
  emptyTitle,
  renderItem,
}: {
  isLoading: boolean;
  items: T[];
  emptyTitle: string;
  renderItem: (item: T) => React.ReactNode;
}) {
  if (isLoading) {
    return <LoadingSpinner />;
  }
  if (items.length === 0) {
    return <EmptyState icon="file-tray-outline" title={emptyTitle} />;
  }
  return <View className="gap-2">{items.map(renderItem)}</View>;
}

function ToolRow({
  icon,
  title,
  subtitle,
  trailing,
  onPress,
}: {
  icon: React.ComponentProps<typeof Ionicons>['name'];
  title: string;
  subtitle?: string | null;
  trailing?: string;
  onPress?: () => void;
}) {
  const primary = usePrimaryColor();
  const theme = useTheme();
  return (
    <Surface variant="secondary" className="flex-row items-center gap-3 rounded-panel-inner p-3">
      <View className="size-10 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
        <Ionicons name={icon} size={18} color={primary} />
      </View>
      <View className="min-w-0 flex-1">
        <Text className="text-sm font-semibold" style={{ color: theme.text }} numberOfLines={1}>{title}</Text>
        {subtitle ? <Text className="text-xs leading-4" style={{ color: theme.textSecondary }} numberOfLines={2}>{subtitle}</Text> : null}
      </View>
      {trailing && onPress ? (
        <HeroButton size="sm" variant="secondary" onPress={onPress}>
          <HeroButton.Label>{trailing}</HeroButton.Label>
        </HeroButton>
      ) : null}
    </Surface>
  );
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
        placeholder={placeholder}
        placeholderTextColor={theme.textMuted}
        value={value}
        onChangeText={onChangeText}
        keyboardType={keyboardType}
        multiline={multiline}
      />
    </View>
  );
}

function formatDateTime(value: string): string {
  if (!value) return '';
  return new Date(value).toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
