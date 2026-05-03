// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AppreciationModal — SOC14 "Say thanks" modal.
 *
 * HeroUI Modal with: message Textarea (500 char), public/private toggle, [Send].
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
  Switch,
} from '@heroui/react';
import MessageCircleHeart from 'lucide-react/icons/message-circle-heart';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { useToast } from '@/contexts';
import { logError } from '@/lib/logger';

interface AppreciationModalProps {
  isOpen: boolean;
  onClose: () => void;
  receiverId: number;
  receiverName?: string;
  contextType?: 'vol_log' | 'listing_completion' | 'general' | 'event_help';
  contextId?: number;
  onSent?: () => void;
}

const MAX_LEN = 500;

export function AppreciationModal({
  isOpen,
  onClose,
  receiverId,
  receiverName,
  contextType = 'general',
  contextId,
  onSent,
}: AppreciationModalProps) {
  const { t } = useTranslation('common');
  const toast = useToast();
  const [message, setMessage] = useState('');
  const [isPublic, setIsPublic] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  const handleClose = useCallback(() => {
    if (submitting) return;
    setMessage('');
    setIsPublic(true);
    onClose();
  }, [submitting, onClose]);

  const handleSend = useCallback(async () => {
    const text = message.trim();
    if (!text) return;
    if (text.length > MAX_LEN) return;
    setSubmitting(true);
    try {
      const res = await api.post('/v2/appreciations', {
        receiver_id: receiverId,
        message: text,
        context_type: contextType,
        context_id: contextId,
        is_public: isPublic,
      });
      if (res.success) {
        toast.success(t('appreciations.sent_success'));
        onSent?.();
        setMessage('');
        onClose();
      } else {
        const err = (res as unknown as { error?: string }).error || '';
        if (err.includes('rate_limit')) {
          toast.error(t('appreciations.rate_limited'));
        } else {
          toast.error(t('appreciations.send_failed'));
        }
      }
    } catch (err) {
      logError('AppreciationModal: send failed', err);
      toast.error(t('appreciations.send_failed'));
    } finally {
      setSubmitting(false);
    }
  }, [message, receiverId, contextType, contextId, isPublic, onSent, onClose, toast, t]);

  const remaining = MAX_LEN - message.length;
  const titleStr = receiverName
    ? t('appreciations.title_to', { name: receiverName })
    : t('appreciations.title');

  return (
    <Modal isOpen={isOpen} onClose={handleClose} placement="center" size="md">
      <ModalContent>
        <ModalHeader className="flex items-center gap-2">
          <MessageCircleHeart className="w-5 h-5 text-[var(--color-primary)]" />
          {titleStr}
        </ModalHeader>
        <ModalBody>
          <Textarea
            label={t('appreciations.message_label')}
            placeholder={t('appreciations.message_placeholder')}
            value={message}
            onValueChange={setMessage}
            maxLength={MAX_LEN}
            minRows={4}
            variant="bordered"
            description={t('appreciations.chars_remaining', { n: remaining })}
          />
          <div className="flex items-center justify-between pt-2">
            <span className="text-sm text-[var(--text-muted)]">
              {isPublic
                ? t('appreciations.public_hint')
                : t('appreciations.private_hint')}
            </span>
            <Switch isSelected={isPublic} onValueChange={setIsPublic} size="sm">
              {t('appreciations.public_toggle')}
            </Switch>
          </div>
        </ModalBody>
        <ModalFooter>
          <Button variant="light" onPress={handleClose} isDisabled={submitting}>
            {t('common.cancel')}
          </Button>
          <Button
            color="primary"
            onPress={handleSend}
            isLoading={submitting}
            isDisabled={!message.trim() || message.length > MAX_LEN}
          >
            {t('appreciations.send')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default AppreciationModal;
