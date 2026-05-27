// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { Alert, Image, ScrollView, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useLocalSearchParams, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Text } from 'heroui-native';
import * as ImagePicker from 'expo-image-picker';
import { useTranslation } from 'react-i18next';
import * as Haptics from '@/lib/haptics';

import AppTopBar from '@/components/ui/AppTopBar';
import FormActionFooter from '@/components/ui/FormActionFooter';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import {
  createMarketplaceListing,
  getMarketplaceCategories,
  getMarketplaceListing,
  updateMarketplaceListing,
  uploadMarketplaceImages,
  type MarketplaceCategory,
  type MarketplaceCondition,
  type MarketplaceDeliveryMethod,
  type MarketplaceListingPayload,
  type MarketplacePriceType,
} from '@/lib/api/marketplace';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';

const PRICE_TYPES: MarketplacePriceType[] = ['fixed', 'negotiable', 'free', 'contact'];
const CONDITIONS: MarketplaceCondition[] = ['new', 'like_new', 'good', 'fair', 'poor'];
const DELIVERY: MarketplaceDeliveryMethod[] = ['pickup', 'shipping', 'both', 'community_delivery'];

function toNumber(value: string): number | null {
  const parsed = Number(value.replace(/[,\s]/g, ''));
  return Number.isFinite(parsed) && value.trim() ? parsed : null;
}

export default function NewMarketplaceListingRoute() {
  return (
    <ModalErrorBoundary>
      <MarketplaceListingForm />
    </ModalErrorBoundary>
  );
}

export function MarketplaceListingForm() {
  const { t } = useTranslation(['marketplace', 'common']);
  const params = useLocalSearchParams<{ id?: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const listingId = Number(params.id);
  const isEditing = Number.isFinite(listingId) && listingId > 0;
  const [categories, setCategories] = useState<MarketplaceCategory[]>([]);
  const [title, setTitle] = useState('');
  const [tagline, setTagline] = useState('');
  const [description, setDescription] = useState('');
  const [price, setPrice] = useState('');
  const [timeCredits, setTimeCredits] = useState('');
  const [priceType, setPriceType] = useState<MarketplacePriceType>('fixed');
  const [condition, setCondition] = useState<MarketplaceCondition>('good');
  const [categoryId, setCategoryId] = useState<number | null>(null);
  const [quantity, setQuantity] = useState('1');
  const [location, setLocation] = useState('');
  const [deliveryMethod, setDeliveryMethod] = useState<MarketplaceDeliveryMethod>('pickup');
  const [sellerType, setSellerType] = useState<'private' | 'business'>('private');
  const [imageUris, setImageUris] = useState<string[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [hydrated, setHydrated] = useState(false);

  useEffect(() => {
    let mounted = true;
    getMarketplaceCategories()
      .then((response) => {
        if (mounted) setCategories(response.data);
      })
      .catch(() => {
        if (mounted) setCategories([]);
      });
    return () => {
      mounted = false;
    };
  }, []);

  useEffect(() => {
    if (!isEditing || hydrated) return;
    let mounted = true;
    getMarketplaceListing(listingId)
      .then((response) => {
        if (!mounted) return;
        const listing = response.data;
        setTitle(listing.title ?? '');
        setTagline(listing.tagline ?? '');
        setDescription(listing.description ?? '');
        setPrice(listing.price !== null && listing.price !== undefined ? String(listing.price) : '');
        setTimeCredits(listing.time_credit_price !== null && listing.time_credit_price !== undefined ? String(listing.time_credit_price) : '');
        setPriceType(listing.price_type ?? 'fixed');
        setCondition(listing.condition ?? 'good');
        setCategoryId(listing.category?.id ?? null);
        setQuantity(String(listing.quantity ?? 1));
        setLocation(listing.location ?? '');
        setDeliveryMethod((listing.delivery_method as MarketplaceDeliveryMethod) ?? 'pickup');
        setSellerType(listing.seller_type === 'business' ? 'business' : 'private');
        setHydrated(true);
      })
      .catch(() => Alert.alert(t('common:errors.alertTitle'), t('forms.loadFailed')));
    return () => {
      mounted = false;
    };
  }, [hydrated, isEditing, listingId, t]);

  async function submit() {
    if (!title.trim() || !description.trim()) {
      Alert.alert(t('forms.validation'), t('forms.required'));
      return;
    }

    setIsSubmitting(true);
    try {
      const payload: MarketplaceListingPayload = {
        title: title.trim(),
        tagline: tagline.trim() || null,
        description: description.trim(),
        price: priceType === 'free' ? 0 : toNumber(price),
        price_currency: 'EUR',
        price_type: priceType,
        time_credit_price: toNumber(timeCredits),
        category_id: categoryId,
        condition,
        quantity: toNumber(quantity) ?? 1,
        location: location.trim() || null,
        delivery_method: deliveryMethod,
        shipping_available: deliveryMethod === 'shipping' || deliveryMethod === 'both',
        local_pickup: deliveryMethod === 'pickup' || deliveryMethod === 'both' || deliveryMethod === 'community_delivery',
        seller_type: sellerType,
        status: 'active',
      };
      const response = isEditing
        ? await updateMarketplaceListing(listingId, payload)
        : await createMarketplaceListing(payload);
      if (imageUris.length > 0) {
        await uploadMarketplaceImages(response.data.id, imageUris);
      }
      await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      router.replace({ pathname: '/(modals)/marketplace-detail', params: { id: String(response.data.id) } } as unknown as Href);
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('forms.saveFailed'));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function pickImages() {
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      Alert.alert(t('forms.permissionTitle'), t('forms.permissionMessage'));
      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsMultipleSelection: true,
      quality: 0.82,
      selectionLimit: 8,
    });
    if (result.canceled) return;
    const nextUris = result.assets.map((asset) => asset.uri).filter(Boolean);
    setImageUris((current) => [...current, ...nextUris].slice(0, 8));
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={isEditing ? t('forms.editTitle') : t('forms.createTitle')} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace' as Href} />
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 132 }}>
        <HeroCard className="mb-4 overflow-hidden rounded-panel p-0">
          <View className="h-1.5" style={{ backgroundColor: primary }} />
          <HeroCard.Body className="gap-4 p-4">
            <View className="flex-row items-start gap-3">
              <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                <Ionicons name="bag-add-outline" size={25} color={primary} />
              </View>
              <View className="min-w-0 flex-1">
                <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('forms.eyebrow')}</Text>
                <Text className="text-2xl font-bold" style={{ color: theme.text }}>{isEditing ? t('forms.editTitle') : t('forms.createTitle')}</Text>
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('forms.subtitle')}</Text>
              </View>
            </View>
          </HeroCard.Body>
        </HeroCard>

        <HeroCard className="rounded-panel p-0">
          <HeroCard.Body className="gap-4 p-4">
            <FormField label={t('forms.title')} value={title} onChangeText={setTitle} placeholder={t('forms.titlePlaceholder')} />
            <FormField label={t('forms.tagline')} value={tagline} onChangeText={setTagline} placeholder={t('forms.taglinePlaceholder')} />
            <FormField label={t('forms.description')} value={description} onChangeText={setDescription} placeholder={t('forms.descriptionPlaceholder')} multiline />
            <ButtonGroup label={t('forms.priceType')} values={PRICE_TYPES} selected={priceType} onSelect={setPriceType} labelFor={(value) => t(`priceType.${value}`)} primary={primary} />
            {priceType !== 'free' && priceType !== 'contact' ? (
              <FormField label={t('forms.price')} value={price} onChangeText={setPrice} placeholder={t('forms.pricePlaceholder')} keyboardType="decimal-pad" />
            ) : null}
            <FormField label={t('forms.timeCredits')} value={timeCredits} onChangeText={setTimeCredits} placeholder={t('forms.timeCreditsPlaceholder')} keyboardType="decimal-pad" />
            <ButtonGroup label={t('forms.condition')} values={CONDITIONS} selected={condition} onSelect={setCondition} labelFor={(value) => t(`condition.${value}`)} primary={primary} />
            <CategoryGroup categories={categories} selected={categoryId} onSelect={setCategoryId} primary={primary} />
            <FormField label={t('forms.quantity')} value={quantity} onChangeText={setQuantity} placeholder="1" keyboardType="decimal-pad" />
            <FormField label={t('forms.location')} value={location} onChangeText={setLocation} placeholder={t('forms.locationPlaceholder')} />
            <ButtonGroup label={t('forms.delivery')} values={DELIVERY} selected={deliveryMethod} onSelect={setDeliveryMethod} labelFor={(value) => t(`delivery_method.${value}`)} primary={primary} />
            <ButtonGroup label={t('forms.sellerType')} values={['private', 'business'] as const} selected={sellerType} onSelect={setSellerType} labelFor={(value) => t(`sellerType.${value}`)} primary={primary} />
            <View className="gap-3">
              <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('forms.media')}</Text>
              <HeroButton variant="secondary" onPress={pickImages}>
                <Ionicons name="images-outline" size={16} color={primary} />
                <HeroButton.Label>{t('forms.addImages')}</HeroButton.Label>
              </HeroButton>
              {imageUris.length > 0 ? (
                <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
                  {imageUris.map((uri, index) => (
                    <View key={`${uri}-${index}`} className="relative">
                      <Image source={{ uri }} className="size-20 rounded-panel-inner" resizeMode="cover" />
                      <HeroButton
                        isIconOnly
                        size="sm"
                        variant="danger"
                        className="absolute right-1 top-1"
                        onPress={() => setImageUris((current) => current.filter((_, itemIndex) => itemIndex !== index))}
                      >
                        <Ionicons name="close-outline" size={14} color="#fff" />
                      </HeroButton>
                    </View>
                  ))}
                </ScrollView>
              ) : null}
              <Text className="text-xs leading-5" style={{ color: theme.textMuted }}>{t('forms.mediaHint')}</Text>
            </View>
          </HeroCard.Body>
        </HeroCard>
      </ScrollView>
      <FormActionFooter
        title={isEditing ? t('forms.footerEditTitle') : t('forms.footerCreateTitle')}
        subtitle={t('forms.footerSubtitle')}
        submitLabel={isEditing ? t('forms.update') : t('forms.publish')}
        primary={primary}
        isSubmitting={isSubmitting}
        onSubmit={submit}
      />
    </SafeAreaView>
  );
}

function ButtonGroup<T extends string>({
  label,
  values,
  selected,
  onSelect,
  labelFor,
  primary,
}: {
  label: string;
  values: readonly T[];
  selected: T;
  onSelect: (value: T) => void;
  labelFor: (value: T) => string;
  primary: string;
}) {
  const theme = useTheme();
  return (
    <View className="gap-2">
      <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{label}</Text>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
        {values.map((value) => (
          <HeroButton key={value} size="sm" variant={selected === value ? 'primary' : 'secondary'} onPress={() => onSelect(value)} style={selected === value ? { backgroundColor: primary } : undefined}>
            <HeroButton.Label>{labelFor(value)}</HeroButton.Label>
          </HeroButton>
        ))}
      </ScrollView>
    </View>
  );
}

function CategoryGroup({ categories, selected, onSelect, primary }: { categories: MarketplaceCategory[]; selected: number | null; onSelect: (value: number | null) => void; primary: string }) {
  const { t } = useTranslation('marketplace');
  const theme = useTheme();
  return (
    <View className="gap-2">
      <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('forms.category')}</Text>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
        <HeroButton size="sm" variant={selected === null ? 'primary' : 'secondary'} onPress={() => onSelect(null)} style={selected === null ? { backgroundColor: primary } : undefined}>
          <HeroButton.Label>{t('filters.noCategory')}</HeroButton.Label>
        </HeroButton>
        {categories.map((category) => (
          <HeroButton key={category.id} size="sm" variant={selected === category.id ? 'primary' : 'secondary'} onPress={() => onSelect(category.id)} style={selected === category.id ? { backgroundColor: primary } : undefined}>
            <HeroButton.Label>{category.name}</HeroButton.Label>
          </HeroButton>
        ))}
      </ScrollView>
    </View>
  );
}

function FormField({
  label,
  value,
  onChangeText,
  placeholder,
  multiline = false,
  keyboardType,
}: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  placeholder: string;
  multiline?: boolean;
  keyboardType?: 'default' | 'decimal-pad';
}) {
  const theme = useTheme();
  return (
    <View className="gap-2">
      <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{label}</Text>
      <TextInput
        className={`${multiline ? 'min-h-28 py-3' : 'min-h-12'} rounded-panel-inner border px-3 text-sm`}
        style={{ borderColor: theme.border, color: theme.text, backgroundColor: theme.bg, textAlignVertical: multiline ? 'top' : 'center' }}
        placeholder={placeholder}
        placeholderTextColor={theme.textMuted}
        value={value}
        onChangeText={onChangeText}
        multiline={multiline}
        keyboardType={keyboardType}
      />
    </View>
  );
}
