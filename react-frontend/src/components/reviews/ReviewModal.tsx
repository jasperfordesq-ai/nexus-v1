// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ReviewModal - Modal for creating/submitting user reviews
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
  Avatar,
} from '@heroui/react';
import { Star } from 'lucide-react';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { resolveAvatarUrl } from '@/lib/helpers';

interface ReviewModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess?: () => void;
  receiverId: number;
  receiverName: string;
  receiverAvatar?: string | null;
  transactionId?: number;
}

export function ReviewModal({
  isOpen,
  onClose,
  onSuccess,
  receiverId,
  receiverName,
  receiverAvatar,
  transactionId,
}: ReviewModalProps) {
  const toast = useToast();
  const [rating, setRating] = useState(0);
  const [hoverRating, setHoverRating] = useState(0);
  const [comment, setComment] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async () => {
    if (rating === 0) {
      toast.error('Please select a rating');
      return;
    }

    try {
      setIsSubmitting(true);

      const response = await api.post('/v2/reviews', {
        receiver_id: receiverId,
        rating,
        comment: comment.trim() || undefined,
        transaction_id: transactionId || undefined,
      });

      if (response.success) {
        toast.success('Review submitted successfully!');
        handleClose();
        onSuccess?.();
      } else {
        toast.error(response.error || 'Failed to submit review');
      }
    } catch (error) {
      toast.error('Failed to submit review. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleClose = () => {
    setRating(0);
    setHoverRating(0);
    setComment('');
    onClose();
  };

  return (
    <Modal isOpen={isOpen} onClose={handleClose} size="lg">
      <ModalContent>
        <ModalHeader className="flex flex-col gap-1">
          <h2 className="text-xl font-bold text-theme-primary">Write a Review</h2>
          <p className="text-sm text-theme-subtle font-normal">
            Share your experience with {receiverName}
          </p>
        </ModalHeader>

        <ModalBody>
          {/* Receiver Info */}
          <div className="flex items-center gap-3 p-3 rounded-lg bg-theme-elevated">
            <Avatar
              src={resolveAvatarUrl(receiverAvatar)}
              name={receiverName}
              size="md"
              className="ring-2 ring-theme-muted/20"
            />
            <div>
              <p className="font-medium text-theme-primary">{receiverName}</p>
              <p className="text-xs text-theme-subtle">
                {transactionId ? 'Transaction Review' : 'General Review'}
              </p>
            </div>
          </div>

          {/* Rating Stars */}
          <div className="space-y-2">
            <label className="text-sm font-medium text-theme-primary">
              Rating <span className="text-red-500">*</span>
            </label>
            <div className="flex items-center gap-1">
              {[1, 2, 3, 4, 5].map((star) => (
                <button
                  key={star}
                  type="button"
                  onClick={() => setRating(star)}
                  onMouseEnter={() => setHoverRating(star)}
                  onMouseLeave={() => setHoverRating(0)}
                  className="focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded"
                  aria-label={`Rate ${star} out of 5 stars`}
                >
                  <Star
                    className={`w-8 h-8 transition-colors ${
                      star <= (hoverRating || rating)
                        ? 'text-amber-400 fill-amber-400'
                        : 'text-theme-subtle'
                    }`}
                  />
                </button>
              ))}
              {rating > 0 && (
                <span className="ml-2 text-sm text-theme-muted">
                  {rating === 1 && 'Poor'}
                  {rating === 2 && 'Fair'}
                  {rating === 3 && 'Good'}
                  {rating === 4 && 'Very Good'}
                  {rating === 5 && 'Excellent'}
                </span>
              )}
            </div>
          </div>

          {/* Comment */}
          <div className="space-y-2">
            <label htmlFor="review-comment" className="text-sm font-medium text-theme-primary">
              Comment (Optional)
            </label>
            <Textarea
              id="review-comment"
              placeholder="Share details about your experience..."
              value={comment}
              onChange={(e) => setComment(e.target.value)}
              minRows={4}
              maxRows={8}
              maxLength={2000}
              classNames={{
                input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
            <p className="text-xs text-theme-subtle text-right">
              {comment.length}/2000 characters
            </p>
          </div>
        </ModalBody>

        <ModalFooter>
          <Button
            variant="flat"
            onPress={handleClose}
            className="bg-theme-elevated text-theme-muted"
          >
            Cancel
          </Button>
          <Button
            onPress={handleSubmit}
            isLoading={isSubmitting}
            isDisabled={rating === 0}
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
          >
            Submit Review
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}
