// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
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
  cancelMarketplaceOrder,
  confirmMarketplaceOrderDelivery,
  disputeMarketplaceOrder,
  getMarketplaceOrders,
  marketplaceHasMore,
  marketplaceNextCursor,
  rateMarketplaceOrder,
  shipMarketplaceOrder,
  type MarketplaceOrder,
} from '@/lib/api/marketplace';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';

type OrderMode = 'purchases' | 'sales';
type OrderStatusTab = 'all' | 'active' | 'completed' | 'cancelled';
type DisputeReason = 'not_received' | 'not_as_described' | 'damaged' | 'wrong_item' | 'other';
const SHIPPING_METHODS = ['standard', 'express', 'tracked', 'hand_delivery', 'other'];
const DISPUTE_REASONS: DisputeReason[] = ['not_received', 'not_as_described', 'damaged', 'wrong_item', 'other'];
const ORDER_STATUS_FILTERS: Record<OrderStatusTab, string | null> = {
  all: null,
  active: 'paid,processing,shipped',
  completed: 'delivered,completed',
  cancelled: 'cancelled,refunded,disputed',
};

export default function MarketplaceOrdersRoute() {
  return (
    <ModalErrorBoundary>
      <MarketplaceOrdersScreen />
    </ModalErrorBoundary>
  );
}

function MarketplaceOrdersScreen() {
  const { t } = useTranslation(['marketplace', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [mode, setMode] = useState<OrderMode>('purchases');
  const [statusTab, setStatusTab] = useState<OrderStatusTab>('all');
  const [shipOrder, setShipOrder] = useState<MarketplaceOrder | null>(null);
  const [cancelOrder, setCancelOrder] = useState<MarketplaceOrder | null>(null);
  const [rateOrder, setRateOrder] = useState<MarketplaceOrder | null>(null);
  const [disputeOrder, setDisputeOrder] = useState<MarketplaceOrder | null>(null);
  const [trackingNumber, setTrackingNumber] = useState('');
  const [trackingUrl, setTrackingUrl] = useState('');
  const [shippingMethod, setShippingMethod] = useState('standard');
  const [cancelReason, setCancelReason] = useState('');
  const [rating, setRating] = useState(5);
  const [ratingComment, setRatingComment] = useState('');
  const [isAnonymousRating, setIsAnonymousRating] = useState(false);
  const [disputeReason, setDisputeReason] = useState<DisputeReason>('not_received');
  const [disputeDescription, setDisputeDescription] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const orders = usePaginatedApi<MarketplaceOrder, Awaited<ReturnType<typeof getMarketplaceOrders>>>(
    (cursor) => getMarketplaceOrders(mode, cursor, ORDER_STATUS_FILTERS[statusTab]),
    (response) => ({
      items: response.data,
      cursor: marketplaceNextCursor(response),
      hasMore: marketplaceHasMore(response),
    }),
    [mode, statusTab],
  );

  function openShipModal(order: MarketplaceOrder) {
    setShipOrder(order);
    setTrackingNumber(order.tracking_number ?? '');
    setTrackingUrl(order.tracking_url ?? '');
    setShippingMethod(order.shipping_method ?? 'standard');
  }

  function openCancelModal(order: MarketplaceOrder) {
    setCancelOrder(order);
    setCancelReason('');
  }

  function openRateModal(order: MarketplaceOrder) {
    setRateOrder(order);
    setRating(5);
    setRatingComment('');
    setIsAnonymousRating(false);
  }

  function openDisputeModal(order: MarketplaceOrder) {
    setDisputeOrder(order);
    setDisputeReason('not_received');
    setDisputeDescription('');
  }

  async function submitShipment() {
    if (!shipOrder) return;
    setIsSubmitting(true);
    try {
      await shipMarketplaceOrder(shipOrder.id, {
        tracking_number: trackingNumber.trim() || null,
        tracking_url: trackingUrl.trim() || null,
        shipping_method: shippingMethod,
      });
      setShipOrder(null);
      orders.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('orders.actionFailed'));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function confirmDelivery(order: MarketplaceOrder) {
    setIsSubmitting(true);
    try {
      await confirmMarketplaceOrderDelivery(order.id);
      orders.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('orders.actionFailed'));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function submitCancel() {
    if (!cancelOrder || !cancelReason.trim()) {
      Alert.alert(t('common:errors.alertTitle'), t('orders.cancelReasonRequired'));
      return;
    }
    setIsSubmitting(true);
    try {
      await cancelMarketplaceOrder(cancelOrder.id, cancelReason.trim());
      setCancelOrder(null);
      setCancelReason('');
      orders.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('orders.actionFailed'));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function submitRating() {
    if (!rateOrder || rating < 1) {
      Alert.alert(t('common:errors.alertTitle'), t('orders.ratingRequired'));
      return;
    }
    setIsSubmitting(true);
    try {
      await rateMarketplaceOrder(rateOrder.id, {
        rating,
        comment: ratingComment.trim() || null,
        is_anonymous: isAnonymousRating,
      });
      setRateOrder(null);
      setRatingComment('');
      orders.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('orders.actionFailed'));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function submitDispute() {
    if (!disputeOrder || !disputeDescription.trim()) {
      Alert.alert(t('common:errors.alertTitle'), t('orders.disputeDescriptionRequired'));
      return;
    }
    setIsSubmitting(true);
    try {
      await disputeMarketplaceOrder(disputeOrder.id, {
        reason: disputeReason,
        description: disputeDescription.trim(),
      });
      setDisputeOrder(null);
      setDisputeDescription('');
      orders.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('orders.actionFailed'));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={t('orders.title')} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace' as Href} />
      <FlatList
        data={orders.items}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 132 }}
        ListHeaderComponent={
          <HeroCard className="mb-3 overflow-hidden rounded-panel p-0">
            <View className="h-1.5" style={{ backgroundColor: primary }} />
            <HeroCard.Body className="gap-4 p-4">
              <View className="flex-row items-start gap-3">
                <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                  <Ionicons name="receipt-outline" size={25} color={primary} />
                </View>
                <View className="min-w-0 flex-1">
                  <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('orders.eyebrow')}</Text>
                  <Text className="text-2xl font-bold" style={{ color: theme.text }}>{t('orders.title')}</Text>
                  <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('orders.subtitle')}</Text>
                </View>
              </View>
              <View className="flex-row gap-2">
                <HeroButton className="flex-1" variant={mode === 'purchases' ? 'primary' : 'secondary'} onPress={() => setMode('purchases')} style={mode === 'purchases' ? { backgroundColor: primary } : undefined}>
                  <HeroButton.Label>{t('orders.purchases')}</HeroButton.Label>
                </HeroButton>
                <HeroButton className="flex-1" variant={mode === 'sales' ? 'primary' : 'secondary'} onPress={() => setMode('sales')} style={mode === 'sales' ? { backgroundColor: primary } : undefined}>
                  <HeroButton.Label>{t('orders.sales')}</HeroButton.Label>
                </HeroButton>
              </View>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
                {(['all', 'active', 'completed', 'cancelled'] as OrderStatusTab[]).map((tab) => (
                  <HeroButton
                    key={tab}
                    size="sm"
                    variant={statusTab === tab ? 'primary' : 'secondary'}
                    onPress={() => setStatusTab(tab)}
                    style={statusTab === tab ? { backgroundColor: primary } : undefined}
                  >
                    <HeroButton.Label>{t(`orders.tabs.${tab}`)}</HeroButton.Label>
                  </HeroButton>
                ))}
              </ScrollView>
            </HeroCard.Body>
          </HeroCard>
        }
        renderItem={({ item }) => (
          <OrderCard
            item={item}
            mode={mode}
            isSubmitting={isSubmitting}
            onShip={() => openShipModal(item)}
            onConfirmDelivery={() => void confirmDelivery(item)}
            onCancel={() => openCancelModal(item)}
            onRate={() => openRateModal(item)}
            onDispute={() => openDisputeModal(item)}
          />
        )}
        ListEmptyComponent={
          orders.isLoading ? (
            <View className="py-16"><LoadingSpinner /></View>
          ) : (
            <EmptyState icon="receipt-outline" title={orders.error ?? t('orders.empty')} subtitle={t('orders.emptyHint')} />
          )
        }
        ListFooterComponent={
          orders.isLoadingMore ? (
            <LoadingSpinner />
          ) : orders.hasMore ? (
            <HeroButton variant="secondary" onPress={orders.loadMore}>
              <HeroButton.Label>{t('loadMore')}</HeroButton.Label>
            </HeroButton>
          ) : null
        }
        onEndReached={orders.loadMore}
        onEndReachedThreshold={0.35}
      />

      <Modal visible={Boolean(shipOrder)} transparent animationType="slide" onRequestClose={() => setShipOrder(null)}>
        <View className="flex-1 justify-end bg-black/40">
          <Surface variant="default" className="rounded-t-[28px] p-4">
            <View className="mb-4 flex-row items-center justify-between">
              <Text className="text-lg font-bold" style={{ color: theme.text }}>{t('orders.shipTitle')}</Text>
              <HeroButton isIconOnly variant="secondary" onPress={() => setShipOrder(null)}>
                <Ionicons name="close-outline" size={20} color={primary} />
              </HeroButton>
            </View>
            <View className="gap-3">
              <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('orders.shipHint')}</Text>
              <OrderInput label={t('orders.trackingNumber')} value={trackingNumber} onChangeText={setTrackingNumber} placeholder={t('orders.trackingNumberPlaceholder')} />
              <OrderInput label={t('orders.trackingUrl')} value={trackingUrl} onChangeText={setTrackingUrl} placeholder={t('orders.trackingUrlPlaceholder')} />
              <View className="gap-2">
                <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('orders.shippingMethod')}</Text>
                <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
                  {SHIPPING_METHODS.map((method) => (
                    <HeroButton key={method} size="sm" variant={shippingMethod === method ? 'primary' : 'secondary'} onPress={() => setShippingMethod(method)} style={shippingMethod === method ? { backgroundColor: primary } : undefined}>
                      <HeroButton.Label>{t(`orders.shippingMethods.${method}`)}</HeroButton.Label>
                    </HeroButton>
                  ))}
                </ScrollView>
              </View>
              <HeroButton variant="primary" isDisabled={isSubmitting} onPress={() => void submitShipment()} style={{ backgroundColor: primary }}>
                <Ionicons name="car-outline" size={17} color="#fff" />
                <HeroButton.Label>{t('orders.confirmShipped')}</HeroButton.Label>
              </HeroButton>
            </View>
          </Surface>
        </View>
      </Modal>

      <Modal visible={Boolean(cancelOrder)} transparent animationType="slide" onRequestClose={() => setCancelOrder(null)}>
        <View className="flex-1 justify-end bg-black/40">
          <Surface variant="default" className="rounded-t-[28px] p-4">
            <View className="mb-4 flex-row items-center justify-between">
              <Text className="text-lg font-bold" style={{ color: theme.text }}>{t('orders.cancelTitle')}</Text>
              <HeroButton isIconOnly variant="secondary" onPress={() => setCancelOrder(null)}>
                <Ionicons name="close-outline" size={20} color={primary} />
              </HeroButton>
            </View>
            <View className="gap-3">
              <OrderInput label={t('orders.cancelReason')} value={cancelReason} onChangeText={setCancelReason} placeholder={t('orders.cancelReasonPlaceholder')} multiline />
              <HeroButton variant="danger" isDisabled={isSubmitting} onPress={() => void submitCancel()}>
                <Ionicons name="close-circle-outline" size={17} color="#fff" />
                <HeroButton.Label>{t('orders.confirmCancel')}</HeroButton.Label>
              </HeroButton>
            </View>
          </Surface>
        </View>
      </Modal>

      <Modal visible={Boolean(rateOrder)} transparent animationType="slide" onRequestClose={() => setRateOrder(null)}>
        <View className="flex-1 justify-end bg-black/40">
          <Surface variant="default" className="rounded-t-[28px] p-4">
            <View className="mb-4 flex-row items-center justify-between">
              <Text className="text-lg font-bold" style={{ color: theme.text }}>{t('orders.rateTitle')}</Text>
              <HeroButton isIconOnly variant="secondary" onPress={() => setRateOrder(null)}>
                <Ionicons name="close-outline" size={20} color={primary} />
              </HeroButton>
            </View>
            <View className="gap-3">
              <View className="gap-2">
                <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('orders.rating')}</Text>
                <View className="flex-row gap-2">
                  {[1, 2, 3, 4, 5].map((value) => (
                    <HeroButton
                      key={value}
                      isIconOnly
                      size="sm"
                      variant={rating >= value ? 'primary' : 'secondary'}
                      onPress={() => setRating(value)}
                      style={rating >= value ? { backgroundColor: theme.warning } : undefined}
                    >
                      <Ionicons name={rating >= value ? 'star' : 'star-outline'} size={17} color={rating >= value ? '#111827' : primary} />
                    </HeroButton>
                  ))}
                </View>
              </View>
              <OrderInput label={t('orders.ratingComment')} value={ratingComment} onChangeText={setRatingComment} placeholder={t('orders.ratingCommentPlaceholder')} multiline />
              <HeroButton variant={isAnonymousRating ? 'primary' : 'secondary'} onPress={() => setIsAnonymousRating((value) => !value)} style={isAnonymousRating ? { backgroundColor: primary } : undefined}>
                <Ionicons name={isAnonymousRating ? 'eye-off-outline' : 'eye-outline'} size={17} color={isAnonymousRating ? '#fff' : primary} />
                <HeroButton.Label>{t('orders.anonymousRating')}</HeroButton.Label>
              </HeroButton>
              <HeroButton variant="primary" isDisabled={isSubmitting} onPress={() => void submitRating()} style={{ backgroundColor: primary }}>
                <Ionicons name="star-outline" size={17} color="#fff" />
                <HeroButton.Label>{t('orders.submitRating')}</HeroButton.Label>
              </HeroButton>
            </View>
          </Surface>
        </View>
      </Modal>

      <Modal visible={Boolean(disputeOrder)} transparent animationType="slide" onRequestClose={() => setDisputeOrder(null)}>
        <View className="flex-1 justify-end bg-black/40">
          <Surface variant="default" className="max-h-[86%] rounded-t-[28px] p-4">
            <View className="mb-4 flex-row items-center justify-between">
              <Text className="text-lg font-bold" style={{ color: theme.text }}>{t('orders.disputeTitle')}</Text>
              <HeroButton isIconOnly variant="secondary" onPress={() => setDisputeOrder(null)}>
                <Ionicons name="close-outline" size={20} color={primary} />
              </HeroButton>
            </View>
            <ScrollView contentContainerStyle={{ gap: 12 }} showsVerticalScrollIndicator={false}>
              <View className="gap-2">
                <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('orders.disputeReason')}</Text>
                <View className="flex-row flex-wrap gap-2">
                  {DISPUTE_REASONS.map((reason) => (
                    <HeroButton
                      key={reason}
                      size="sm"
                      variant={disputeReason === reason ? 'primary' : 'secondary'}
                      onPress={() => setDisputeReason(reason)}
                      style={disputeReason === reason ? { backgroundColor: primary } : { minWidth: '46%' }}
                    >
                      <HeroButton.Label>{t(`orders.disputeReasons.${reason}`)}</HeroButton.Label>
                    </HeroButton>
                  ))}
                </View>
              </View>
              <OrderInput label={t('orders.disputeDescription')} value={disputeDescription} onChangeText={setDisputeDescription} placeholder={t('orders.disputeDescriptionPlaceholder')} multiline />
              <HeroButton variant="danger" isDisabled={isSubmitting} onPress={() => void submitDispute()}>
                <Ionicons name="alert-circle-outline" size={17} color="#fff" />
                <HeroButton.Label>{t('orders.submitDispute')}</HeroButton.Label>
              </HeroButton>
            </ScrollView>
          </Surface>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

function OrderCard({
  item,
  mode,
  isSubmitting,
  onShip,
  onConfirmDelivery,
  onCancel,
  onRate,
  onDispute,
}: {
  item: MarketplaceOrder;
  mode: OrderMode;
  isSubmitting: boolean;
  onShip: () => void;
  onConfirmDelivery: () => void;
  onCancel: () => void;
  onRate: () => void;
  onDispute: () => void;
}) {
  const { t } = useTranslation('marketplace');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const total = `${item.currency || 'EUR'} ${Number(item.total_price).toLocaleString()}`;
  return (
    <HeroCard className="mb-3 rounded-panel p-0">
      <HeroCard.Body className="gap-3 p-4">
        <View className="flex-row items-start justify-between gap-3">
          <View className="min-w-0 flex-1">
            <Text className="text-base font-bold" style={{ color: theme.text }} numberOfLines={2}>{item.listing?.title ?? item.order_number}</Text>
            <Text className="text-sm" style={{ color: theme.textSecondary }}>{total}</Text>
          </View>
          <Chip size="sm" variant="secondary"><Chip.Label>{t(`orders.status.${item.status}`, { defaultValue: item.status })}</Chip.Label></Chip>
        </View>
        <Text className="text-xs" style={{ color: theme.textMuted }}>{t('orders.number', { number: item.order_number })}</Text>
        {item.tracking_number ? (
          <Text className="text-xs" style={{ color: theme.textSecondary }}>
            {t('orders.tracking', { number: item.tracking_number })}
          </Text>
        ) : null}
        {item.listing?.id ? (
          <HeroButton size="sm" variant="secondary" onPress={() => router.push({ pathname: '/(modals)/marketplace-detail', params: { id: String(item.listing?.id) } } as unknown as Href)}>
            <Ionicons name="open-outline" size={14} color={primary} />
            <HeroButton.Label>{t('actions.view')}</HeroButton.Label>
          </HeroButton>
        ) : null}
        <View className="flex-row flex-wrap gap-2">
          {mode === 'sales' && item.status === 'paid' ? (
            <HeroButton className="flex-1" size="sm" variant="primary" isDisabled={isSubmitting} onPress={onShip} style={{ minWidth: '46%', backgroundColor: primary }}>
              <Ionicons name="car-outline" size={14} color="#fff" />
              <HeroButton.Label>{t('orders.markShipped')}</HeroButton.Label>
            </HeroButton>
          ) : null}
          {mode === 'purchases' && item.status === 'shipped' ? (
            <HeroButton className="flex-1" size="sm" variant="primary" isDisabled={isSubmitting} onPress={onConfirmDelivery} style={{ minWidth: '46%', backgroundColor: theme.success }}>
              <Ionicons name="checkmark-circle-outline" size={14} color="#fff" />
              <HeroButton.Label>{t('orders.confirmDelivery')}</HeroButton.Label>
            </HeroButton>
          ) : null}
          {['pending', 'paid', 'processing'].includes(item.status) ? (
            <HeroButton className="flex-1" size="sm" variant="danger" isDisabled={isSubmitting} onPress={onCancel} style={{ minWidth: '46%' }}>
              <Ionicons name="close-circle-outline" size={14} color="#fff" />
              <HeroButton.Label>{t('orders.cancel')}</HeroButton.Label>
            </HeroButton>
          ) : null}
          {item.status === 'completed' ? (
            <HeroButton className="flex-1" size="sm" variant="secondary" isDisabled={isSubmitting} onPress={onRate} style={{ minWidth: '46%' }}>
              <Ionicons name="star-outline" size={14} color={primary} />
              <HeroButton.Label>{t('orders.rate')}</HeroButton.Label>
            </HeroButton>
          ) : null}
          {mode === 'purchases' && ['paid', 'processing', 'shipped', 'delivered'].includes(item.status) ? (
            <HeroButton className="flex-1" size="sm" variant="secondary" isDisabled={isSubmitting} onPress={onDispute} style={{ minWidth: '46%' }}>
              <Ionicons name="alert-circle-outline" size={14} color={theme.error} />
              <HeroButton.Label>{t('orders.dispute')}</HeroButton.Label>
            </HeroButton>
          ) : null}
        </View>
      </HeroCard.Body>
    </HeroCard>
  );
}

function OrderInput({
  label,
  value,
  onChangeText,
  placeholder,
  multiline = false,
}: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  placeholder: string;
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
        multiline={multiline}
      />
    </View>
  );
}
