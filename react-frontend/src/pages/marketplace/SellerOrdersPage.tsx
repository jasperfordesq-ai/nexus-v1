// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SellerOrdersPage — View and manage marketplace sales orders from the seller perspective.
 *
 * Features:
 * - Tabs: All, Active (paid/shipped), Completed, Cancelled/Refunded
 * - Order cards with listing image, buyer, price, status, date
 * - "Mark Shipped" modal with tracking info inputs
 * - Status-aware action buttons
 * - Cursor-based pagination
 * - Auth required
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import {
  Button,
  Spinner,
  Tab,
  Tabs,
  Avatar,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Select,
  SelectItem,
  useDisclosure,
} from '@heroui/react';
import {
  Store,
  Package,
  Truck,
  Star,
  ExternalLink,
  Clock,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { OrderStatusBadge } from '@/components/marketplace';
import type { MarketplaceOrderItem } from '@/types/marketplace';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo/PageMeta';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

type OrderTab = 'all' | 'active' | 'completed' | 'cancelled';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const ITEMS_PER_PAGE = 20;

const TAB_STATUS_MAP: Record<OrderTab, string | undefined> = {
  all: undefined,
  active: 'paid,shipped',
  completed: 'completed,delivered',
  cancelled: 'cancelled,refunded',
};

const SHIPPING_METHODS = [
  { key: 'standard', label: 'Standard Post' },
  { key: 'express', label: 'Express / Courier' },
  { key: 'tracked', label: 'Tracked Post' },
  { key: 'hand_delivery', label: 'Hand Delivery' },
  { key: 'other', label: 'Other' },
];

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function formatPrice(price: number, currency: string): string {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: currency || 'EUR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(price);
}

// ─────────────────────────────────────────────────────────────────────────────
// Seller Order Card
// ─────────────────────────────────────────────────────────────────────────────

function SellerOrderCard({
  order,
  onMarkShipped,
}: {
  order: MarketplaceOrderItem;
  onMarkShipped: (orderId: number) => void;
}) {
  const { t } = useTranslation('marketplace');
  const { tenantPath } = useTenant();

  const hasRating = order.ratings && order.ratings.some((r) => r.rater_role === 'buyer');

  return (
    <GlassCard className="p-4">
      <div className="flex gap-4">
        {/* Listing image */}
        <Link
          to={tenantPath(`/marketplace/${order.listing.id}`)}
          className="shrink-0 w-20 h-20 rounded-lg overflow-hidden bg-default-100"
        >
          {order.listing.image?.url ? (
            <img
              src={order.listing.image.url}
              alt={order.listing.title}
              className="w-full h-full object-cover"
              loading="lazy"
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center">
              <Package className="w-8 h-8 text-default-300" />
            </div>
          )}
        </Link>

        {/* Order details */}
        <div className="flex-1 min-w-0">
          <div className="flex items-start justify-between gap-2">
            <div className="min-w-0">
              <Link
                to={tenantPath(`/marketplace/${order.listing.id}`)}
                className="font-semibold text-foreground hover:text-primary transition-colors line-clamp-1"
              >
                {order.listing.title}
              </Link>
              <div className="flex items-center gap-2 mt-1">
                <Avatar
                  src={order.buyer.avatar_url || undefined}
                  name={order.buyer.name}
                  size="sm"
                  className="w-5 h-5"
                />
                <span className="text-xs text-default-500">
                  {t('orders.seller.buyer_label', 'Buyer:')} {order.buyer.name}
                </span>
              </div>
            </div>
            <OrderStatusBadge status={order.status} />
          </div>

          <div className="flex items-center justify-between mt-2">
            <div>
              <span className="text-lg font-bold text-foreground">
                {formatPrice(order.total_price, order.currency)}
              </span>
              {order.quantity > 1 && (
                <span className="text-xs text-default-400 ml-1">
                  x{order.quantity}
                </span>
              )}
            </div>
            <span className="text-xs text-default-400">
              #{order.order_number} - {new Date(order.created_at).toLocaleDateString()}
            </span>
          </div>

          {/* Tracking info */}
          {order.tracking_number && (order.status === 'shipped' || order.status === 'delivered') && (
            <div className="flex items-center gap-2 mt-2 text-xs text-default-500">
              <Truck className="w-3.5 h-3.5" />
              <span>{t('orders.tracking_number', 'Tracking:')} {order.tracking_number}</span>
              {order.tracking_url && (
                <a
                  href={order.tracking_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-primary hover:underline inline-flex items-center gap-0.5"
                >
                  {t('orders.track', 'Track')}
                  <ExternalLink className="w-3 h-3" />
                </a>
              )}
            </div>
          )}

          {/* Action buttons */}
          <div className="flex items-center gap-2 mt-3">
            {order.status === 'paid' && (
              <Button
                size="sm"
                color="primary"
                onPress={() => onMarkShipped(order.id)}
                startContent={<Truck className="w-3.5 h-3.5" />}
              >
                {t('orders.seller.mark_shipped', 'Mark Shipped')}
              </Button>
            )}
            {order.status === 'shipped' && (
              <Button size="sm" variant="flat" isDisabled startContent={<Clock className="w-3.5 h-3.5" />}>
                {t('orders.seller.awaiting_confirmation', 'Awaiting Buyer Confirmation')}
              </Button>
            )}
            {order.status === 'delivered' && (
              <Button size="sm" variant="flat" isDisabled startContent={<Clock className="w-3.5 h-3.5" />}>
                {t('orders.seller.awaiting_completion', 'Awaiting Auto-Complete')}
              </Button>
            )}
            {order.status === 'completed' && hasRating && (
              <span className="text-xs text-success flex items-center gap-1">
                <Star className="w-3 h-3 fill-success" />
                {t('orders.seller.buyer_rated', 'Buyer Rated')}
              </span>
            )}
            {order.status === 'disputed' && (
              <Button size="sm" variant="flat" color="danger" isDisabled>
                {t('orders.seller.dispute_open', 'Dispute Open')}
              </Button>
            )}
          </div>
        </div>
      </div>
    </GlassCard>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function SellerOrdersPage() {
  const navigate = useNavigate();
  const { t } = useTranslation('marketplace');
  usePageTitle(t('orders.seller.page_title', 'My Sales - Marketplace'));
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  // State
  const [activeTab, setActiveTab] = useState<OrderTab>('all');
  const [orders, setOrders] = useState<MarketplaceOrderItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMore, setHasMore] = useState(true);
  const cursorRef = useRef<string | null>(null);

  // Ship modal state
  const shipModal = useDisclosure();
  const [shipOrderId, setShipOrderId] = useState<number | null>(null);
  const [trackingNumber, setTrackingNumber] = useState('');
  const [trackingUrl, setTrackingUrl] = useState('');
  const [shippingMethod, setShippingMethod] = useState('standard');
  const [isSubmittingShip, setIsSubmittingShip] = useState(false);

  // Redirect if not authenticated
  useEffect(() => {
    if (!isAuthenticated) {
      navigate(tenantPath('/auth/login'), { replace: true });
    }
  }, [isAuthenticated, navigate, tenantPath]);

  // Load orders
  const loadOrders = useCallback(async (append = false) => {
    try {
      if (!append) {
        setIsLoading(true);
      } else {
        setIsLoadingMore(true);
      }

      const params = new URLSearchParams();
      params.set('limit', String(ITEMS_PER_PAGE));
      if (append && cursorRef.current) {
        params.set('cursor', cursorRef.current);
      }
      const statuses = TAB_STATUS_MAP[activeTab];
      if (statuses) {
        params.set('status', statuses);
      }

      const response = await api.get<MarketplaceOrderItem[]>(
        `/v2/marketplace/orders/sales?${params}`,
      );

      if (response.success && response.data) {
        if (append) {
          setOrders((prev) => [...prev, ...response.data!]);
        } else {
          setOrders(response.data);
        }
        cursorRef.current = response.meta?.cursor ?? response.meta?.next_cursor ?? null;
        setHasMore(response.meta?.has_more ?? response.data.length >= ITEMS_PER_PAGE);
      } else if (!append) {
        setOrders([]);
      }
    } catch (err) {
      logError('Failed to load seller orders', err);
      if (!append) {
        toast.error(t('orders.load_error', 'Failed to load orders'));
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [activeTab, toast]);

  // Reload on tab change
  useEffect(() => {
    if (!isAuthenticated) return;
    cursorRef.current = null;
    setHasMore(true);
    loadOrders();
  }, [activeTab, isAuthenticated]); // eslint-disable-line react-hooks/exhaustive-deps

  // Open ship modal
  const handleOpenShipModal = useCallback((orderId: number) => {
    setShipOrderId(orderId);
    setTrackingNumber('');
    setTrackingUrl('');
    setShippingMethod('standard');
    shipModal.onOpen();
  }, [shipModal]);

  // Submit shipment
  const handleSubmitShipment = useCallback(async () => {
    if (shipOrderId == null) return;

    setIsSubmittingShip(true);
    try {
      const response = await api.put(`/v2/marketplace/orders/${shipOrderId}/ship`, {
        tracking_number: trackingNumber.trim() || undefined,
        tracking_url: trackingUrl.trim() || undefined,
        shipping_method: shippingMethod,
      });

      if (response.success) {
        toast.success(t('orders.seller.shipped_success', 'Order marked as shipped!'));
        setOrders((prev) =>
          prev.map((o) =>
            o.id === shipOrderId
              ? {
                  ...o,
                  status: 'shipped',
                  tracking_number: trackingNumber.trim() || undefined,
                  tracking_url: trackingUrl.trim() || undefined,
                  shipping_method: shippingMethod,
                }
              : o,
          ),
        );
        shipModal.onClose();
      } else {
        toast.error(response.error || t('orders.action_error', 'Action failed'));
      }
    } catch (err) {
      logError('Failed to mark order as shipped', err);
      toast.error(t('orders.action_error', 'Action failed'));
    } finally {
      setIsSubmittingShip(false);
    }
  }, [shipOrderId, trackingNumber, trackingUrl, shippingMethod, toast, shipModal]);

  if (!isAuthenticated) return null;

  return (
    <>
      <PageMeta title={t('orders.seller.page_title', 'My Sales - Marketplace')} />

      <div className="max-w-3xl mx-auto px-4 py-6 space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-2xl font-bold text-foreground flex items-center gap-2">
            <Store className="w-7 h-7 text-primary" />
            {t('orders.seller.title', 'My Sales')}
          </h1>
          <p className="text-default-500 text-sm mt-1">
            {t('orders.seller.subtitle', 'Manage orders from your marketplace sales')}
          </p>
        </div>

        {/* Tabs */}
        <Tabs
          selectedKey={activeTab}
          onSelectionChange={(key) => setActiveTab(key as OrderTab)}
          color="primary"
          variant="underlined"
          classNames={{ tabList: 'gap-4' }}
        >
          <Tab key="all" title={t('orders.tab_all', 'All')} />
          <Tab key="active" title={t('orders.tab_active', 'Active')} />
          <Tab key="completed" title={t('orders.tab_completed', 'Completed')} />
          <Tab key="cancelled" title={t('orders.tab_cancelled', 'Cancelled / Refunded')} />
        </Tabs>

        {/* Orders list */}
        {isLoading ? (
          <div className="flex justify-center py-16">
            <Spinner size="lg" color="primary" />
          </div>
        ) : orders.length === 0 ? (
          <EmptyState
            icon={<Store className="w-10 h-10 text-default-400" />}
            title={t('orders.seller.empty_title', 'No Sales Yet')}
            description={t('orders.seller.empty_description', 'You haven\'t received any orders yet. List items for sale to start selling.')}
            action={{
              label: t('orders.seller.create_listing', 'Create Listing'),
              onClick: () => navigate(tenantPath('/marketplace/sell')),
            }}
          />
        ) : (
          <div className="space-y-3">
            {orders.map((order) => (
              <SellerOrderCard
                key={order.id}
                order={order}
                onMarkShipped={handleOpenShipModal}
              />
            ))}

            {/* Load more */}
            {hasMore && (
              <div className="flex justify-center mt-6">
                <Button
                  variant="flat"
                  color="primary"
                  onPress={() => loadOrders(true)}
                  isLoading={isLoadingMore}
                >
                  {t('common.load_more', 'Load More')}
                </Button>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Ship Order Modal */}
      <Modal isOpen={shipModal.isOpen} onClose={shipModal.onClose} size="md" placement="center">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Truck className="w-5 h-5 text-primary" />
            {t('orders.seller.ship_modal_title', 'Mark as Shipped')}
          </ModalHeader>
          <ModalBody className="space-y-4">
            <p className="text-sm text-default-500">
              {t('orders.seller.ship_modal_description', 'Enter shipping details for this order. Tracking information will be shared with the buyer.')}
            </p>
            <Select
              label={t('orders.seller.shipping_method', 'Shipping Method')}
              selectedKeys={[shippingMethod]}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0] as string;
                if (selected) setShippingMethod(selected);
              }}
            >
              {SHIPPING_METHODS.map((method) => (
                <SelectItem key={method.key}>
                  {t(`orders.seller.shipping_method_${method.key}`, method.label)}
                </SelectItem>
              ))}
            </Select>
            <Input
              label={t('orders.seller.tracking_number', 'Tracking Number (optional)')}
              placeholder={t('orders.seller.tracking_number_placeholder', 'e.g. 1Z999AA10123456784')}
              value={trackingNumber}
              onValueChange={setTrackingNumber}
            />
            <Input
              label={t('orders.seller.tracking_url_label', 'Tracking URL (optional)')}
              placeholder={t('orders.seller.tracking_url_placeholder', 'e.g. https://track.example.com/...')}
              value={trackingUrl}
              onValueChange={setTrackingUrl}
              type="url"
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={shipModal.onClose} isDisabled={isSubmittingShip}>
              {t('common.cancel', 'Cancel')}
            </Button>
            <Button
              color="primary"
              onPress={handleSubmitShipment}
              isLoading={isSubmittingShip}
              startContent={!isSubmittingShip ? <Truck className="w-4 h-4" /> : undefined}
            >
              {t('orders.seller.confirm_shipped', 'Confirm Shipped')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}

export default SellerOrdersPage;
