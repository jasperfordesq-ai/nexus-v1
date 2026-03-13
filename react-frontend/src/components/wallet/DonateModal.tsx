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
import { useTranslation } from 'react-i18next';
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
  const { t } = useTranslation('wallet');
  const toast = useToast();
  const [recipientType, setRecipientType] = useState<'community_fund' | 'user'>('community_fund');
  const [recipientId, setRecipientId] = useState('');
  const [amount, setAmount] = useState('');
  const [message, setMessage] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleDonate() {
    const parsedAmount = parseFloat(amount);
    if (isNaN(parsedAmount) || parsedAmount <= 0) {
      toast.error(t('donate_invalid_amount'), t('donate_invalid_amount_desc'));
      return;
    }

    if (parsedAmount > currentBalance) {
      toast.error(t('donate_insufficient'), t('donate_insufficient_desc'));
      return;
    }

    if (recipientType === 'user' && !recipientId) {
      toast.error(t('donate_recipient_required'), t('donate_recipient_required_desc'));
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
        toast.success(t('donate_success'), t('donate_success_desc'));
        setAmount('');
        setMessage('');
        setRecipientId('');
        onClose();
        onDonationComplete?.();
      } else {
        toast.error(t('donate_failed'), response.error || t('donate_error'));
      }
    } catch (err) {
      logError('Donation failed', err);
      toast.error(t('donate_failed'), t('donate_error'));
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
          {t('donate_credits')}
        </ModalHeader>
        <ModalBody>
          <RadioGroup
            label={t('donate_to')}
            value={recipientType}
            onValueChange={(v) => setRecipientType(v as 'community_fund' | 'user')}
            classNames={{ label: 'text-theme-muted' }}
          >
            <Radio
              value="community_fund"
              description={t('donate_community_desc')}
            >
              <span className="flex items-center gap-2">
                <Users className="w-4 h-4" />
                {t('donate_community_fund')}
              </span>
            </Radio>
            <Radio
              value="user"
              description={t('donate_member_desc')}
            >
              <span className="flex items-center gap-2">
                <User className="w-4 h-4" />
                {t('donate_member')}
              </span>
            </Radio>
          </RadioGroup>

          {recipientType === 'user' && (
            <Input
              label={t('donate_recipient_id')}
              placeholder={t('donate_recipient_placeholder')}
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
            label={t('donate_amount')}
            placeholder="1"
            value={amount}
            onChange={(e) => setAmount(e.target.value)}
            type="number"
            min="0.25"
            step="0.25"
            endContent={<span className="text-theme-muted text-sm">hours</span>}
            description={t('donate_balance_info', { balance: currentBalance })}
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default',
            }}
          />

          <Textarea
            label={t('donate_message')}
            placeholder={t('donate_message_placeholder')}
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
            {t('cancel')}
          </Button>
          <Button
            color="danger"
            onPress={handleDonate}
            isLoading={isSubmitting}
            startContent={<Heart className="w-4 h-4" />}
          >
            {t('donate_confirm')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}
