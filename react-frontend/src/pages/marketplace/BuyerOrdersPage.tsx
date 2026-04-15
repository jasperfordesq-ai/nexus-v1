// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BuyerOrdersPage — View and manage marketplace purchase orders.
 *
 * Features:
 * - Tabs: All, Active (paid/shipped), Completed, Cancelled/Refunded
 * - Order cards with listing image, seller, price, status, date
 * - Context-aware action buttons per order status
 * - Rating modal for completed orders
 * - Tracking info display for shipped orders
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
  useDisclosure,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from '@heroui/react';
import {
  ShoppingBag,
  Package,
  Truck,
  Star,
  ExternalLink,
  CheckCircle2,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { OrderStatusBadge, RatingModal } from '@/components/marketplace';
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
// Order Card
// ─────────────────────────────────────────────────────────────────────────────

function OrderCard({
  order,
  onRequestConfirmDelivery,
  onRate,
}: {
  order: MarketplaceOrderItem;
  onRequestConfirmDelivery: (orderId: number) => void;
  onRate: (orderId: number) => void;
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
              <ShoppingBag className="w-8 h-8 text-default-300" />
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
                  src={order.seller.avatar_url || undefined}
                  name={order.seller.name}
                  size="sm"
                  className="w-5 h-5"
                />
                <span className="text-xs text-default-500">{order.seller.name}</span>
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
              {new Date(order.created_at).toLocaleDateString()}
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
              <Button size="sm" variant="flat" isDisabled startContent={<Package className="w-3.5 h-3.5" />}>
                {t('orders.buyer.waiting_shipment', 'Waiting for Shipment')}
              </Button>
            )}
            {order.status === 'shipped' && (
              <Button
                size="sm"
                color="primary"
                onPress={() => onRequestConfirmDelivery(order.id)}
                startContent={<CheckCircle2 className="w-3.5 h-3.5" />}
              >
                {t('orders.buyer.confirm_delivery', 'Confirm Delivery')}
              </Button>
            )}
            {order.status === 'delivered' && !hasRating && (
              <Button
                size="sm"
                color="warning"
                variant="flat"
                onPress={() => onRate(order.id)}
                startContent={<Star className="w-3.5 h-3.5" />}
              >
                {t('orders.buyer.rate_order', 'Rate Order')}
              </Button>
            )}
            {order.status === 'completed' && !hasRating && (
              <Button
                size="sm"
                color="warning"
                variant="flat"
                onPress={() => onRate(order.id)}
                startContent={<Star className="w-3.5 h-3.5" />}
              >
                {t('orders.buyer.leave_rating', 'Leave Rating')}
              </Button>
            )}
            {order.status === 'completed' && hasRating && (
              <span className="text-xs text-success flex items-center gap-1">
                <Star className="w-3 h-3 fill-success" />
                {t('orders.buyer.rated', 'Rated')}
              </span>
            )}
            {order.status === 'disputed' && (
              <Button size="sm" variant="flat" color="danger" isDisabled>
                {t('orders.buyer.dispute_open', 'Dispute Open')}
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

export function BuyerOrdersPage() {
  const navigate = useNavigate();
  const { t } = useTranslation('marketplace');
  usePageTitle(t('orders.buyer.page_title', 'My Orders - Marketplace'));
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

  // Rating modal
  const ratingModal = useDisclosure();
  const [ratingOrderId, setRatingOrderId] = useState<number | null>(null);

  // Confirm delivery modal
  const [confirmDeliveryId, setConfirmDeliveryId] = useState<number | null>(null);

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
        `/v2/marketplace/orders/purchases?${params}`,
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
      logError('Failed to load buyer orders', err);
      if (!append) {
        toast.error(t('orders.load_error', 'Failed to load orders'));
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [activeTab, toast, t])

  // Reload on tab change
  useEffect(() => {
    if (!isAuthenticated) return;
    cursorRef.current = null;
    setHasMore(true);
    loadOrders();
  }, [activeTab, isAuthenticated]); // eslint-disable-line react-hooks/exhaustive-deps

  // Confirm delivery
  const handleConfirmDelivery = useCallback(async (orderId: number) => {
    try {
      const response = await api.put(`/v2/marketplace/orders/${orderId}/confirm-delivery`);
      if (response.success) {
        toast.success(t('orders.buyer.delivery_confirmed', 'Delivery confirmed!'));
        setOrders((prev) =>
          prev.map((o) => (o.id === orderId ? { ...o, status: 'delivered' } : o)),
        );
      } else {
        toast.error(response.error || t('orders.action_error', 'Action failed'));
      }
    } catch (err) {
      logError('Failed to confirm delivery', err);
      toast.error(t('orders.action_error', 'Action failed'));
    }
  }, [toast, t])

  // Open rating modal
  const handleOpenRating = useCallback((orderId: number) => {
    setRatingOrderId(orderId);
    ratingModal.onOpen();
  }, [ratingModal]);

  // Rating success
  const handleRatingSuccess = useCallback(() => {
    loadOrders();
  }, [loadOrders]);

  if (!isAuthenticated) return null;

  return (
    <>
      <PageMeta title={t('orders.buyer.page_title', 'My Orders - Marketplace')} noIndex={true} />

      <div className="max-w-3xl mx-auto px-4 py-6 space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-2xl font-bold text-foreground flex items-center gap-2">
            <ShoppingBag className="w-7 h-7 text-primary" />
            {t('orders.buyer.title', 'My Orders')}
          </h1>
          <p className="text-default-500 text-sm mt-1">
            {t('orders.buyer.subtitle', 'Track your marketplace purchases')}
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
            icon={<ShoppingBag className="w-10 h-10 text-default-400" />}
            title={t('orders.buyer.empty_title', 'No Orders Yet')}
            description={t('orders.buyer.empty_description', 'You haven\'t purchased anything yet. Browse the marketplace to find items you like.')}
            action={{
              label: t('orders.buyer.browse_marketplace', 'Browse Marketplace'),
              onClick: () => navigate(tenantPath('/marketplace')),
            }}
          />
        ) : (
          <div className="space-y-3">
            {orders.map((order) => (
              <OrderCard
                key={order.id}
                order={order}
                onRequestConfirmDelivery={(id) => setConfirmDeliveryId(id)}
                onRate={handleOpenRating}
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

      {/* Confirm Delivery Modal */}
      <Modal
        isOpen={confirmDeliveryId !== null}
        onOpenChange={(open) => { if (!open) setConfirmDeliveryId(null); }}
        placement="center"
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>{t('orders.buyer.confirm_delivery_modal_title', 'Confirm Delivery')}</ModalHeader>
              <ModalBody>
                <p className="text-sm text-default-600">
                  {t('orders.buyer.confirm_delivery_modal_body', 'Mark this order as delivered? This cannot be undone.')}
                </p>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  {t('common.cancel', 'Cancel')}
                </Button>
                <Button
                  color="primary"
                  onPress={() => {
                    if (confirmDeliveryId !== null) {
                      handleConfirmDelivery(confirmDeliveryId);
                    }
                    setConfirmDeliveryId(null);
                  }}
                >
                  {t('orders.buyer.confirm_delivery', 'Confirm Delivery')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Rating modal */}
      {ratingOrderId !== null && (
        <RatingModal
          orderId={ratingOrderId}
          isOpen={ratingModal.isOpen}
          onClose={ratingModal.onClose}
          onSuccess={handleRatingSuccess}
        />
      )}
    </>
  );
}

export default BuyerOrdersPage;
