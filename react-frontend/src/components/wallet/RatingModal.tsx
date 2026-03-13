// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * RatingModal - Modal for rating a completed exchange (W10)
 */

import { useState } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Textarea,
} from '@heroui/react';
import { Star } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { useToast } from '@/contexts';

interface RatingModalProps {
  isOpen: boolean;
  onClose: () => void;
  exchangeId: number;
  otherPartyName?: string;
  onRatingComplete?: () => void;
}

export function RatingModal({ isOpen, onClose, exchangeId, otherPartyName, onRatingComplete }: RatingModalProps) {
  const toast = useToast();
  const { t } = useTranslation('wallet');
  const [rating, setRating] = useState(0);
  const [hoveredRating, setHoveredRating] = useState(0);
  const [comment, setComment] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit() {
    if (rating === 0) {
      toast.error(t('toast.rating_required'), t('toast.rating_required_desc'));
      return;
    }

    try {
      setIsSubmitting(true);
      const response = await api.post(`/v2/exchanges/${exchangeId}/rate`, {
        rating,
        comment: comment || undefined,
      });

      if (response.success) {
        toast.success(t('toast.rating_submitted'), t('toast.rating_submitted_desc'));
        setRating(0);
        setComment('');
        onClose();
        onRatingComplete?.();
      } else {
        toast.error(t('toast.submit_failed'), response.error || t('toast.try_again'));
      }
    } catch (err) {
      logError('Rating submission failed', err);
      toast.error(t('toast.submit_failed'), t('toast.submit_error_desc'));
    } finally {
      setIsSubmitting(false);
    }
  }

  const displayRating = hoveredRating || rating;

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      classNames={{
        base: 'bg-content1 border border-theme-default',
        header: 'border-b border-theme-default',
        body: 'py-6',
        footer: 'border-t border-theme-default',
      }}
    >
      <ModalContent>
        <ModalHeader className="text-theme-primary">
          Rate Your Exchange
        </ModalHeader>
        <ModalBody>
          <p className="text-theme-muted mb-4">
            How was your exchange{otherPartyName ? ` with ${otherPartyName}` : ''}?
          </p>

          {/* Star Rating */}
          <div className="flex justify-center gap-2 mb-6">
            {[1, 2, 3, 4, 5].map((star) => (
              <Button
                key={star}
                isIconOnly
                size="sm"
                variant="light"
                onPress={() => setRating(star)}
                onMouseEnter={() => setHoveredRating(star)}
                onMouseLeave={() => setHoveredRating(0)}
                className="p-1 transition-transform hover:scale-110 w-auto h-auto min-w-0"
                aria-label={`${star} star${star > 1 ? 's' : ''}`}
              >
                <Star
                  className={`w-10 h-10 transition-colors ${
                    star <= displayRating
                      ? 'text-amber-400 fill-amber-400'
                      : 'text-theme-muted'
                  }`}
                />
              </Button>
            ))}
          </div>

          {displayRating > 0 && (
            <p className="text-center text-sm text-theme-muted mb-4">
              {displayRating === 1 && 'Poor'}
              {displayRating === 2 && 'Fair'}
              {displayRating === 3 && 'Good'}
              {displayRating === 4 && 'Very Good'}
              {displayRating === 5 && 'Excellent'}
            </p>
          )}

          <Textarea
            label="Comment (optional)"
            placeholder="Share your experience..."
            value={comment}
            onChange={(e) => setComment(e.target.value)}
            maxLength={500}
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default',
            }}
          />
        </ModalBody>
        <ModalFooter>
          <Button
            variant="flat"
            onPress={onClose}
            className="bg-theme-elevated text-theme-primary"
          >
            Skip
          </Button>
          <Button
            color="warning"
            onPress={handleSubmit}
            isLoading={isSubmitting}
            isDisabled={rating === 0}
            startContent={<Star className="w-4 h-4" />}
          >
            Submit Rating
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}
