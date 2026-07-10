// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button } from '@/components/ui/Button';
import { Modal, ModalContent, ModalHeader, ModalBody, ModalFooter } from '@/components/ui/Modal';
import { StarRating } from '@/components/ui/StarRating';
import { Textarea } from '@/components/ui/Textarea';
/**
 * RatingModal - Modal for rating a completed exchange (W10)
 */

import { useState } from 'react';

import Star from 'lucide-react/icons/star';
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
  const [comment, setComment] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit() {
    if (isSubmitting) return;

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

  const ratingDescriptions = [
    t('rating.poor'),
    t('rating.fair'),
    t('rating.good'),
    t('rating.very_good'),
    t('rating.excellent'),
  ];
  const ratingPrompt = otherPartyName
    ? t('rating.prompt_with_name', { name: otherPartyName })
    : t('rating.prompt');

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      classNames={{
        base: 'bg-overlay border border-theme-default',
        header: 'border-b border-theme-default',
        body: 'py-6',
        footer: 'border-t border-theme-default',
      }}
    >
      <ModalContent>
        <ModalHeader className="text-theme-primary">
          {t('rating.title')}
        </ModalHeader>
        <ModalBody>
          <StarRating
            value={rating}
            onChange={setRating}
            label={ratingPrompt}
            getOptionLabel={(star) => ratingDescriptions[star - 1] ?? String(star)}
            getValueDescription={(star) => ratingDescriptions[star - 1]}
            labelClassName="mb-3 basis-full text-left text-sm text-theme-muted"
            descriptionClassName="mb-4 mt-1 text-center"
            className="mb-2 justify-center"
            isRequired
          />

          <Textarea
            label={t('rating.comment_label')}
            placeholder={t('rating.comment_placeholder')}
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
            variant="tertiary"
            onPress={onClose}
            className="bg-theme-elevated text-theme-primary"
          >
            {t('rating.skip')}
          </Button>
          <Button
            variant="secondary"
            onPress={handleSubmit}
            isLoading={isSubmitting}
            isDisabled={rating === 0}
            startContent={<Star className="w-4 h-4" />}
          >
            {t('rating.submit')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}
