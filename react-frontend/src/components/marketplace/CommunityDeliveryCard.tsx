// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CommunityDeliveryCard - Community-powered delivery option for marketplace.
 *
 * Shown on listing detail when delivery_method includes 'community_delivery'.
 * Explains the concept, shows existing delivery offers, and provides an
 * "Offer to Deliver" button for community members.
 *
 * NEXUS differentiator: peer-to-peer delivery for time credits.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card,
  CardBody,
  Button,
  Input,
  Textarea,
  Avatar,
  Chip,
  Tooltip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Spinner,
  useDisclosure,
} from '@heroui/react';
import Truck from 'lucide-react/icons/truck';
import Clock from 'lucide-react/icons/clock';
import Users from 'lucide-react/icons/users';
import HelpCircle from 'lucide-react/icons/circle-help';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { useAuth, useToast } from '@/contexts';

interface DeliveryOffer {
  id: number;
  order_id: number;
  deliverer_id: number;
  time_credits: number;
  estimated_minutes: number | null;
  notes: string | null;
  status: 'pending' | 'accepted' | 'declined' | 'completed' | 'cancelled';
  accepted_at: string | null;
  completed_at: string | null;
  created_at: string;
  deliverer?: {
    id: number;
    name: string;
    avatar_url: string | null;
    is_verified: boolean;
  };
}

interface CommunityDeliveryCardProps {
  /** The marketplace order ID (if an order exists) */
  orderId?: number | null;
  /** Whether the current user is the listing owner */
  isOwner?: boolean;
  /** Whether this is just informational (no order yet, shown on listing detail) */
  informational?: boolean;
}

export function CommunityDeliveryCard({
  orderId,
  isOwner = false,
  informational = false,
}: CommunityDeliveryCardProps) {
  const { t } = useTranslation('marketplace');
  const { isAuthenticated } = useAuth();
  const toast = useToast();
  const { isOpen, onOpen, onClose } = useDisclosure();

  const [offers, setOffers] = useState<DeliveryOffer[]>([]);
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [timeCredits, setTimeCredits] = useState('1');
  const [estimatedMinutes, setEstimatedMinutes] = useState('');
  const [notes, setNotes] = useState('');

  interface DeliveryOffersResponse {
    data?: DeliveryOffer[];
  }
  const loadOffers = useCallback(async () => {
    if (!orderId) return;
    setLoading(true);
    try {
      const response = await api.get<DeliveryOffersResponse | DeliveryOffer[]>(`/v2/marketplace/orders/${orderId}/delivery-offers`);
      const raw = response.data;
      const list = raw && !Array.isArray(raw) && 'data' in raw ? raw.data : raw;
      setOffers(Array.isArray(list) ? list : []);
    } catch (err) {
      logError('Failed to load delivery offers', err);
    } finally {
      setLoading(false);
    }
  }, [orderId]);

  useEffect(() => {
    if (orderId && !informational) {
      loadOffers();
    }
  }, [orderId, informational, loadOffers]);

  const handleSubmitOffer = async () => {
    if (!orderId) return;
    setSubmitting(true);
    try {
      await api.post(`/v2/marketplace/orders/${orderId}/delivery-offers`, {
        time_credits: parseFloat(timeCredits),
        estimated_minutes: estimatedMinutes ? parseInt(estimatedMinutes, 10) : undefined,
        notes: notes || undefined,
      });
      toast.success(t('community_delivery.offer_sent'));
      onClose();
      setTimeCredits('1');
      setEstimatedMinutes('');
      setNotes('');
      loadOffers();
    } catch (err) {
      logError('Failed to submit delivery offer', err);
      toast.error(t('community_delivery.offer_failed'));
    } finally {
      setSubmitting(false);
    }
  };

  const handleAcceptOffer = async (delivererId: number) => {
    if (!orderId) return;
    try {
      await api.put(`/v2/marketplace/orders/${orderId}/delivery-offers/${delivererId}/accept`);
      toast.success(t('community_delivery.offer_accepted'));
      loadOffers();
    } catch (err) {
      logError('Failed to accept delivery offer', err);
      toast.error(t('community_delivery.accept_failed'));
    }
  };

  const handleConfirmDelivery = async (delivererId: number) => {
    if (!orderId) return;
    try {
      await api.put(`/v2/marketplace/orders/${orderId}/delivery-offers/${delivererId}/confirm`);
      toast.success(t('community_delivery.delivery_confirmed'));
      loadOffers();
    } catch (err) {
      logError('Failed to confirm delivery', err);
      toast.error(t('community_delivery.confirm_failed'));
    }
  };

  const statusColor = (status: string) => {
    switch (status) {
      case 'pending': return 'warning';
      case 'accepted': return 'primary';
      case 'completed': return 'success';
      case 'declined': return 'danger';
      default: return 'default';
    }
  };

  return (
    <>
      <Card className="border border-primary/20 bg-primary/5 shadow-sm">
        <CardBody className="gap-4">
          {/* Header */}
          <div className="flex items-start gap-3">
            <div className="p-2 rounded-lg bg-primary/10">
              <Truck className="w-5 h-5 text-primary" />
            </div>
            <div className="flex-1">
              <h4 className="text-sm font-semibold text-theme-primary">
                {t('community_delivery.title')}
              </h4>
              <p className="text-xs text-theme-muted mt-0.5">
                {t('community_delivery.description')}
              </p>
            </div>
            <Tooltip
              content={t('community_delivery.tooltip')}
            >
              <HelpCircle className="w-4 h-4 text-theme-muted cursor-help flex-shrink-0" />
            </Tooltip>
          </div>

          {/* How it works (informational mode) */}
          {informational && (
            <div className="space-y-2 text-xs text-theme-muted">
              <div className="flex items-center gap-2">
                <Users className="w-3.5 h-3.5 text-primary flex-shrink-0" />
                <span>{t('community_delivery.step1')}</span>
              </div>
              <div className="flex items-center gap-2">
                <Clock className="w-3.5 h-3.5 text-primary flex-shrink-0" />
                <span>{t('community_delivery.step2')}</span>
              </div>
              <div className="flex items-center gap-2">
                <CheckCircle className="w-3.5 h-3.5 text-primary flex-shrink-0" />
                <span>{t('community_delivery.step3')}</span>
              </div>
            </div>
          )}

          {/* Existing offers */}
          {!informational && offers.length > 0 && (
            <div className="space-y-2">
              <h5 className="text-xs font-medium text-theme-muted uppercase tracking-wider">
                {t('community_delivery.offers_title_count', { count: offers.length })}
              </h5>
              {offers.map(offer => (
                <div
                  key={offer.id}
                  className="flex flex-col gap-3 rounded-lg border border-default-200 bg-content1 p-3 shadow-sm sm:flex-row sm:items-center"
                >
                  {offer.deliverer && (
                    <Avatar
                      size="sm"
                      src={offer.deliverer.avatar_url ?? undefined}
                      name={offer.deliverer.name}
                    />
                  )}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-medium text-theme-primary truncate">
                        {offer.deliverer?.name ?? t('community_delivery.unknown')}
                      </span>
                      <Chip size="sm" color={statusColor(offer.status)} variant="flat">
                        {t(`community_delivery.status.${offer.status}`)}
                      </Chip>
                    </div>
                    <div className="flex items-center gap-2 mt-0.5">
                      <Clock className="w-3 h-3 text-primary" />
                      <span className="text-xs text-theme-muted">
                        {t('community_delivery.time_credits_value', { count: offer.time_credits })}
                        {offer.estimated_minutes && ` - ${t('community_delivery.estimated_minutes', { count: offer.estimated_minutes })}`}
                      </span>
                    </div>
                    {offer.notes && (
                      <p className="text-xs text-theme-muted mt-1 truncate">{offer.notes}</p>
                    )}
                  </div>

                  {/* Action buttons for owner */}
                  {isOwner && offer.status === 'pending' && (
                    <Button
                      size="sm"
                      color="primary"
                      variant="flat"
                      onPress={() => handleAcceptOffer(offer.deliverer_id)}
                    >
                      {t('community_delivery.accept')}
                    </Button>
                  )}
                  {isOwner && offer.status === 'accepted' && (
                    <Button
                      size="sm"
                      color="success"
                      variant="flat"
                      onPress={() => handleConfirmDelivery(offer.deliverer_id)}
                    >
                      {t('community_delivery.confirm')}
                    </Button>
                  )}
                </div>
              ))}
            </div>
          )}

          {loading && (
            <div className="flex justify-center py-4">
              <Spinner size="sm" />
            </div>
          )}

          {/* Offer to deliver button (for non-owners) */}
          {!informational && !isOwner && isAuthenticated && orderId && (
            <Button
              color="primary"
              variant="flat"
              startContent={<Truck className="w-4 h-4" />}
              onPress={onOpen}
              className="w-full"
            >
              {t('community_delivery.offer_to_deliver')}
            </Button>
          )}
        </CardBody>
      </Card>

      {/* Offer Modal */}
      <Modal isOpen={isOpen} onClose={onClose} size="md">
        <ModalContent>
          <ModalHeader>
            {t('community_delivery.offer_modal_title')}
          </ModalHeader>
          <ModalBody className="gap-4">
            <p className="text-sm text-theme-muted">
              {t('community_delivery.offer_modal_description')}
            </p>
            <Input
              type="number"
              label={t('community_delivery.time_credits_label')}
              placeholder={t('community_delivery.time_credits_placeholder')}
              value={timeCredits}
              onValueChange={setTimeCredits}
              min={0.25}
              max={100}
              step={0.25}
              startContent={<Clock className="w-4 h-4 text-theme-muted" />}
              description={t('community_delivery.time_credits_hint')}
            />
            <Input
              type="number"
              label={t('community_delivery.estimated_time_label')}
              placeholder={t('community_delivery.estimated_time_placeholder')}
              value={estimatedMinutes}
              onValueChange={setEstimatedMinutes}
              min={5}
              max={1440}
            />
            <Textarea
              label={t('community_delivery.notes_label')}
              placeholder={t('community_delivery.notes_placeholder')}
              value={notes}
              onValueChange={setNotes}
              maxLength={500}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose}>
              {t('community_delivery.cancel')}
            </Button>
            <Button
              color="primary"
              isLoading={submitting}
              onPress={handleSubmitOffer}
              isDisabled={!timeCredits || parseFloat(timeCredits) <= 0}
            >
              {t('community_delivery.send_offer')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}

export default CommunityDeliveryCard;
