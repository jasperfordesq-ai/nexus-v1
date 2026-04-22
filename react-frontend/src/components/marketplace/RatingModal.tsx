// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * RatingModal — Modal for rating a completed marketplace order.
 *
 * Features:
 * - 5 clickable stars (filled/unfilled)
 * - Comment textarea (max 1000 chars)
 * - Anonymous toggle
 * - Submit calls POST /v2/marketplace/orders/{id}/rate
 * - Loading/error states
 */

import { useState, useCallback } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Textarea,
  Checkbox,
} from '@heroui/react';
import Star from 'lucide-react/icons/star';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface RatingModalProps {
  orderId: number;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

// ─────────────────────────────────────────────────────────────────────────────
// Star Rating Component
// ─────────────────────────────────────────────────────────────────────────────

function StarRating({
  value,
  onChange,
}: {
  value: number;
  onChange: (v: number) => void;
}) {
  const { t } = useTranslation('marketplace');
  const [hovered, setHovered] = useState(0);

  return (
    <div className="flex items-center gap-1" role="radiogroup" aria-label={t('orders.rating.stars_label', 'Rating')}>
      {[1, 2, 3, 4, 5].map((star) => {
        const filled = star <= (hovered || value);
        return (
          <Button
            key={star}
            isIconOnly
            variant="light"
            size="sm"
            onPress={() => onChange(star)}
            onMouseEnter={() => setHovered(star)}
            onMouseLeave={() => setHovered(0)}
            className="p-0.5 transition-transform hover:scale-110"
            aria-label={t('orders.rating.star_n', '{{n}} stars', { n: star })}
            role="radio"
            aria-checked={star === value}
          >
            <Star
              className={`w-8 h-8 transition-colors ${
                filled
                  ? 'fill-warning text-warning'
                  : 'text-default-300'
              }`}
            />
          </Button>
        );
      })}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function RatingModal({ orderId, isOpen, onClose, onSuccess }: RatingModalProps) {
  const { t } = useTranslation('marketplace');
  const toast = useToast();

  const [rating, setRating] = useState(0);
  const [comment, setComment] = useState('');
  const [isAnonymous, setIsAnonymous] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = useCallback(async () => {
    if (rating < 1 || rating > 5) {
      toast.error(t('orders.rating.select_rating', 'Please select a rating'));
      return;
    }

    setIsSubmitting(true);
    try {
      const response = await api.post(`/v2/marketplace/orders/${orderId}/rate`, {
        rating,
        comment: comment.trim() || undefined,
        is_anonymous: isAnonymous,
      });

      if (response.success) {
        toast.success(t('orders.rating.success', 'Rating submitted successfully!'));
        setRating(0);
        setComment('');
        setIsAnonymous(false);
        onSuccess();
        onClose();
      } else {
        toast.error(response.error || t('orders.rating.error', 'Failed to submit rating'));
      }
    } catch (err) {
      logError('Failed to submit order rating', err);
      toast.error(t('orders.rating.error', 'Failed to submit rating'));
    } finally {
      setIsSubmitting(false);
    }
  }, [orderId, rating, comment, isAnonymous, toast, onSuccess, onClose, t])

  const handleClose = useCallback(() => {
    if (!isSubmitting) {
      setRating(0);
      setComment('');
      setIsAnonymous(false);
      onClose();
    }
  }, [isSubmitting, onClose]);

  return (
    <Modal isOpen={isOpen} onClose={handleClose} size="md" placement="center">
      <ModalContent>
        <ModalHeader className="flex items-center gap-2">
          <Star className="w-5 h-5 text-warning" />
          {t('orders.rating.title', 'Rate Your Order')}
        </ModalHeader>

        <ModalBody className="space-y-4">
          <div>
            <p className="text-sm text-default-500 mb-2">
              {t('orders.rating.how_was_experience', 'How was your experience?')}
            </p>
            <StarRating value={rating} onChange={setRating} />
          </div>

          <Textarea
            label={t('orders.rating.comment_label', 'Comment (optional)')}
            placeholder={t('orders.rating.comment_placeholder', 'Share details about your experience...')}
            value={comment}
            onValueChange={setComment}
            maxLength={1000}
            minRows={3}
            maxRows={6}
            description={`${comment.length}/1000`}
          />

          <Checkbox
            isSelected={isAnonymous}
            onValueChange={setIsAnonymous}
            size="sm"
          >
            {t('orders.rating.anonymous', 'Submit anonymously')}
          </Checkbox>
        </ModalBody>

        <ModalFooter>
          <Button variant="flat" onPress={handleClose} isDisabled={isSubmitting}>
            {t('common.cancel', 'Cancel')}
          </Button>
          <Button
            color="primary"
            onPress={handleSubmit}
            isLoading={isSubmitting}
            isDisabled={rating < 1}
          >
            {t('orders.rating.submit', 'Submit Rating')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default RatingModal;
