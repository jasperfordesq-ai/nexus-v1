// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MyOffersPage — View and manage marketplace offers as buyer/seller.
 *
 * Features:
 * - Tabs: Sent (as buyer), Received (as seller)
 * - Uses shared OfferCard component with perspective-aware action buttons
 * - Counter-offer inline form with amount + message
 * - Accept, Decline, Withdraw, Accept Counter actions
 * - Cursor-based pagination
 * - Requires authentication
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Button,
  Input,
  Textarea,
  Spinner,
  Tab,
  Tabs,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@heroui/react';
import Send from 'lucide-react/icons/send';
import Inbox from 'lucide-react/icons/inbox';
import HandCoins from 'lucide-react/icons/hand-coins';
import RotateCcw from 'lucide-react/icons/rotate-ccw';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/feedback';
import { OfferCard } from '@/components/marketplace';
import type { MarketplaceOffer } from '@/types/marketplace';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo/PageMeta';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

type OfferTab = 'sent' | 'received';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const ITEMS_PER_PAGE = 20;

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function MyOffersPage() {
  const navigate = useNavigate();
  const { t } = useTranslation('marketplace');
  usePageTitle(t('my_offers.page_title', 'My Offers - Marketplace'));
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  // State
  const [activeTab, setActiveTab] = useState<OfferTab>('sent');
  const [offers, setOffers] = useState<MarketplaceOffer[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMore, setHasMore] = useState(true);
  const cursorRef = useRef<string | null>(null);

  // Counter-offer modal state
  const { isOpen: isCounterOpen, onOpen: onCounterOpen, onClose: onCounterClose } = useDisclosure();
  const [counterTargetId, setCounterTargetId] = useState<number | null>(null);
  const [counterAmount, setCounterAmount] = useState('');
  const [counterMessage, setCounterMessage] = useState('');
  const [isSubmittingCounter, setIsSubmittingCounter] = useState(false);

  // Redirect if not authenticated
  useEffect(() => {
    if (!isAuthenticated) {
      navigate(tenantPath('/login'), { replace: true });
    }
  }, [isAuthenticated, navigate, tenantPath]);

  // Load offers
  const loadOffers = useCallback(async (append = false) => {
    try {
      if (!append) {
        setIsLoading(true);
      } else {
        setIsLoadingMore(true);
      }

      const endpoint = activeTab === 'sent'
        ? '/v2/marketplace/my-offers/sent'
        : '/v2/marketplace/my-offers/received';

      const params = new URLSearchParams();
      params.set('limit', String(ITEMS_PER_PAGE));
      if (append && cursorRef.current) {
        params.set('cursor', cursorRef.current);
      }

      const response = await api.get<MarketplaceOffer[]>(`${endpoint}?${params}`);

      if (response.success && response.data) {
        if (append) {
          setOffers((prev) => [...prev, ...response.data!]);
        } else {
          setOffers(response.data);
        }
        cursorRef.current = response.meta?.cursor ?? response.meta?.next_cursor ?? null;
        setHasMore(response.meta?.has_more ?? response.data.length >= ITEMS_PER_PAGE);
      } else if (!append) {
        setOffers([]);
      }
    } catch (err) {
      logError('Failed to load offers', err);
      if (!append) {
        toast.error(t('my_offers.load_error', 'Failed to load offers'));
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
    loadOffers();
  }, [activeTab, isAuthenticated]); // eslint-disable-line react-hooks/exhaustive-deps

  // Update offer in local list
  const updateOfferLocally = useCallback((offerId: number, updates: Partial<MarketplaceOffer>) => {
    setOffers((prev) =>
      prev.map((o) => (o.id === offerId ? { ...o, ...updates } : o))
    );
  }, []);

  const removeOfferLocally = useCallback((offerId: number) => {
    setOffers((prev) => prev.filter((o) => o.id !== offerId));
  }, []);

  // Accept offer (seller)
  const handleAccept = useCallback(async (offerId: number) => {
    try {
      const response = await api.put(`/v2/marketplace/offers/${offerId}/accept`);
      if (response.success) {
        toast.success(t('my_offers.accepted_success', 'Offer accepted'));
        updateOfferLocally(offerId, { status: 'accepted' });
      } else {
        toast.error(response.error || t('my_offers.action_error', 'Action failed'));
      }
    } catch (err) {
      logError('Failed to accept offer', err);
      toast.error(t('my_offers.action_error', 'Action failed'));
    }
  }, [toast, updateOfferLocally, t])

  // Decline offer (seller or buyer declining counter)
  const handleDecline = useCallback(async (offerId: number) => {
    try {
      const response = await api.put(`/v2/marketplace/offers/${offerId}/decline`);
      if (response.success) {
        toast.success(t('my_offers.declined_success', 'Offer declined'));
        updateOfferLocally(offerId, { status: 'declined' });
      } else {
        toast.error(response.error || t('my_offers.action_error', 'Action failed'));
      }
    } catch (err) {
      logError('Failed to decline offer', err);
      toast.error(t('my_offers.action_error', 'Action failed'));
    }
  }, [toast, updateOfferLocally, t])

  // Withdraw offer (buyer)
  const handleWithdraw = useCallback(async (offerId: number) => {
    try {
      const response = await api.delete(`/v2/marketplace/offers/${offerId}`);
      if (response.success) {
        toast.success(t('my_offers.withdrawn_success', 'Offer withdrawn'));
        removeOfferLocally(offerId);
      } else {
        toast.error(response.error || t('my_offers.action_error', 'Action failed'));
      }
    } catch (err) {
      logError('Failed to withdraw offer', err);
      toast.error(t('my_offers.action_error', 'Action failed'));
    }
  }, [toast, removeOfferLocally, t])

  // Accept counter-offer (buyer)
  const handleAcceptCounter = useCallback(async (offerId: number) => {
    try {
      const response = await api.put(`/v2/marketplace/offers/${offerId}/accept-counter`);
      if (response.success) {
        toast.success(t('my_offers.counter_accepted_success', 'Counter-offer accepted'));
        updateOfferLocally(offerId, { status: 'accepted' });
      } else {
        toast.error(response.error || t('my_offers.action_error', 'Action failed'));
      }
    } catch (err) {
      logError('Failed to accept counter-offer', err);
      toast.error(t('my_offers.action_error', 'Action failed'));
    }
  }, [toast, updateOfferLocally, t])

  // Open counter-offer modal (seller)
  const openCounterModal = useCallback((offerId: number) => {
    setCounterTargetId(offerId);
    setCounterAmount('');
    setCounterMessage('');
    onCounterOpen();
  }, [onCounterOpen]);

  // Submit counter-offer
  const handleSubmitCounter = useCallback(async () => {
    if (counterTargetId == null) return;

    const amount = parseFloat(counterAmount);
    if (!counterAmount || isNaN(amount) || amount <= 0) {
      toast.error(t('my_offers.counter_amount_required', 'Please enter a valid counter amount'));
      return;
    }

    setIsSubmittingCounter(true);
    try {
      const response = await api.put(`/v2/marketplace/offers/${counterTargetId}/counter`, {
        amount,
        message: counterMessage.trim() || undefined,
      });
      if (response.success) {
        toast.success(t('my_offers.counter_sent_success', 'Counter-offer sent'));
        updateOfferLocally(counterTargetId, {
          status: 'countered',
          counter_amount: amount,
          counter_message: counterMessage.trim() || undefined,
        });
        onCounterClose();
      } else {
        toast.error(response.error || t('my_offers.action_error', 'Action failed'));
      }
    } catch (err) {
      logError('Failed to send counter-offer', err);
      toast.error(t('my_offers.action_error', 'Action failed'));
    } finally {
      setIsSubmittingCounter(false);
    }
  }, [counterTargetId, counterAmount, counterMessage, toast, updateOfferLocally, onCounterClose, t])

  if (!isAuthenticated) return null;

  const perspective: 'buyer' | 'seller' = activeTab === 'sent' ? 'buyer' : 'seller';

  return (
    <>
      <PageMeta title={t('my_offers.page_title', 'My Offers - Marketplace')} noIndex={true} />

      <div className="max-w-3xl mx-auto px-4 py-6 space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-2xl font-bold text-foreground flex items-center gap-2">
            <HandCoins className="w-7 h-7 text-primary" />
            {t('my_offers.title', 'My Offers')}
          </h1>
          <p className="text-default-500 text-sm mt-1">
            {t('my_offers.subtitle', 'Track offers you\'ve sent and received')}
          </p>
        </div>

        {/* Tabs */}
        <Tabs
          selectedKey={activeTab}
          onSelectionChange={(key) => setActiveTab(key as OfferTab)}
          color="primary"
          variant="underlined"
          classNames={{ tabList: 'gap-4' }}
        >
          <Tab
            key="sent"
            title={
              <div className="flex items-center gap-1.5">
                <Send className="w-4 h-4" />
                <span>{t('my_offers.tab_sent', 'Sent')}</span>
              </div>
            }
          />
          <Tab
            key="received"
            title={
              <div className="flex items-center gap-1.5">
                <Inbox className="w-4 h-4" />
                <span>{t('my_offers.tab_received', 'Received')}</span>
              </div>
            }
          />
        </Tabs>

        {/* Offers list */}
        {isLoading ? (
          <div className="flex justify-center py-16">
            <Spinner size="lg" color="primary" />
          </div>
        ) : offers.length === 0 ? (
          <EmptyState
            icon={activeTab === 'sent' ? <Send className="w-8 h-8" /> : <Inbox className="w-8 h-8" />}
            title={
              activeTab === 'sent'
                ? t('my_offers.empty_sent_title', 'No Offers Sent')
                : t('my_offers.empty_received_title', 'No Offers Received')
            }
            description={
              activeTab === 'sent'
                ? t('my_offers.empty_sent_description', 'You haven\'t made any offers yet. Browse the marketplace to find items you like.')
                : t('my_offers.empty_received_description', 'You haven\'t received any offers yet. List items for sale to start receiving offers.')
            }
          />
        ) : (
          <div className="space-y-3">
            {offers.map((offer) => (
              <OfferCard
                key={offer.id}
                offer={offer}
                perspective={perspective}
                onAccept={handleAccept}
                onDecline={handleDecline}
                onCounter={openCounterModal}
                onWithdraw={handleWithdraw}
                onAcceptCounter={handleAcceptCounter}
              />
            ))}

            {/* Load more */}
            {hasMore && (
              <div className="flex justify-center mt-6">
                <Button
                  variant="flat"
                  color="primary"
                  onPress={() => loadOffers(true)}
                  isLoading={isLoadingMore}
                >
                  {t('common.load_more', 'Load More')}
                </Button>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Counter-offer modal */}
      <Modal isOpen={isCounterOpen} onClose={onCounterClose} size="md">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <RotateCcw className="w-5 h-5 text-secondary" />
            {t('my_offers.counter_modal_title', 'Counter Offer')}
          </ModalHeader>
          <ModalBody>
            <Input
              label={t('my_offers.counter_amount_label', 'Counter Amount')}
              placeholder="0.00"
              type="number"
              min={0}
              step={0.01}
              value={counterAmount}
              onValueChange={setCounterAmount}
              isRequired
            />
            <Textarea
              label={t('my_offers.counter_message_label', 'Message (optional)')}
              placeholder={t('my_offers.counter_message_placeholder', 'Explain your counter-offer...')}
              value={counterMessage}
              onValueChange={setCounterMessage}
              minRows={2}
              maxRows={5}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onCounterClose}>
              {t('common.cancel', 'Cancel')}
            </Button>
            <Button
              color="secondary"
              onPress={handleSubmitCounter}
              isLoading={isSubmittingCounter}
              startContent={!isSubmittingCounter ? <RotateCcw className="w-4 h-4" /> : undefined}
            >
              {t('my_offers.send_counter', 'Send Counter')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}

export default MyOffersPage;
