// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * DonateModal - Modal for donating credits to community fund or another member
 */

import { useState } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Input,
  Textarea,
  RadioGroup,
  Radio,
} from '@heroui/react';
import { Heart, Users, User } from 'lucide-react';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { useToast } from '@/contexts';

interface DonateModalProps {
  isOpen: boolean;
  onClose: () => void;
  currentBalance: number;
  onDonationComplete?: () => void;
}

export function DonateModal({ isOpen, onClose, currentBalance, onDonationComplete }: DonateModalProps) {
  const toast = useToast();
  const [recipientType, setRecipientType] = useState<'community_fund' | 'user'>('community_fund');
  const [recipientId, setRecipientId] = useState('');
  const [amount, setAmount] = useState('');
  const [message, setMessage] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleDonate() {
    const parsedAmount = parseFloat(amount);
    if (isNaN(parsedAmount) || parsedAmount <= 0) {
      toast.error('Invalid amount', 'Please enter an amount greater than 0');
      return;
    }

    if (parsedAmount > currentBalance) {
      toast.error('Insufficient balance', 'You do not have enough credits');
      return;
    }

    if (recipientType === 'user' && !recipientId) {
      toast.error('Recipient required', 'Please enter a recipient ID');
      return;
    }

    try {
      setIsSubmitting(true);
      const response = await api.post('/v2/wallet/donate', {
        recipient_type: recipientType,
        recipient_id: recipientType === 'user' ? parseInt(recipientId, 10) : undefined,
        amount: parsedAmount,
        message,
      });

      if (response.success) {
        toast.success('Donation sent!', 'Thank you for your generosity.');
        setAmount('');
        setMessage('');
        setRecipientId('');
        onClose();
        onDonationComplete?.();
      } else {
        toast.error('Donation failed', response.error || 'Please try again');
      }
    } catch (err) {
      logError('Donation failed', err);
      toast.error('Donation failed', 'An error occurred. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  }

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
        <ModalHeader className="text-theme-primary flex items-center gap-2">
          <Heart className="w-5 h-5 text-rose-400" />
          Donate Credits
        </ModalHeader>
        <ModalBody>
          <RadioGroup
            label="Donate to"
            value={recipientType}
            onValueChange={(v) => setRecipientType(v as 'community_fund' | 'user')}
            classNames={{ label: 'text-theme-muted' }}
          >
            <Radio
              value="community_fund"
              description="Support your community's time credit pool"
            >
              <span className="flex items-center gap-2">
                <Users className="w-4 h-4" />
                Community Fund
              </span>
            </Radio>
            <Radio
              value="user"
              description="Gift credits directly to another member"
            >
              <span className="flex items-center gap-2">
                <User className="w-4 h-4" />
                Another Member
              </span>
            </Radio>
          </RadioGroup>

          {recipientType === 'user' && (
            <Input
              label="Recipient ID"
              placeholder="Enter member ID"
              value={recipientId}
              onChange={(e) => setRecipientId(e.target.value)}
              type="number"
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          )}

          <Input
            label="Amount (hours)"
            placeholder="1"
            value={amount}
            onChange={(e) => setAmount(e.target.value)}
            type="number"
            min="0.25"
            step="0.25"
            endContent={<span className="text-theme-muted text-sm">hours</span>}
            description={`Your balance: ${currentBalance} hours`}
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default',
            }}
          />

          <Textarea
            label="Message (optional)"
            placeholder="Add a note with your donation..."
            value={message}
            onChange={(e) => setMessage(e.target.value)}
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
            Cancel
          </Button>
          <Button
            color="danger"
            onPress={handleDonate}
            isLoading={isSubmitting}
            startContent={<Heart className="w-4 h-4" />}
          >
            Donate
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}
