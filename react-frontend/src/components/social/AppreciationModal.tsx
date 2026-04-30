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
  const { showToast } = useToast();
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
        showToast(t('appreciations.sent_success', 'Thank-you sent!'), 'success');
        onSent?.();
        setMessage('');
        onClose();
      } else {
        const err = (res as unknown as { error?: string }).error || '';
        if (err.includes('rate_limit')) {
          showToast(t('appreciations.rate_limited', 'Daily thank-you limit reached'), 'error');
        } else {
          showToast(t('appreciations.send_failed', 'Could not send thank-you'), 'error');
        }
      }
    } catch (err) {
      logError('AppreciationModal: send failed', err);
      showToast(t('appreciations.send_failed', 'Could not send thank-you'), 'error');
    } finally {
      setSubmitting(false);
    }
  }, [message, receiverId, contextType, contextId, isPublic, onSent, onClose, showToast, t]);

  const remaining = MAX_LEN - message.length;
  const titleStr = receiverName
    ? t('appreciations.title_to', 'Thank {{name}}', { name: receiverName })
    : t('appreciations.title', 'Send a thank-you');

  return (
    <Modal isOpen={isOpen} onClose={handleClose} placement="center" size="md">
      <ModalContent>
        <ModalHeader className="flex items-center gap-2">
          <MessageCircleHeart className="w-5 h-5 text-[var(--color-primary)]" />
          {titleStr}
        </ModalHeader>
        <ModalBody>
          <Textarea
            label={t('appreciations.message_label', 'Your message')}
            placeholder={t('appreciations.message_placeholder', 'What are you thanking them for?')}
            value={message}
            onValueChange={setMessage}
            maxLength={MAX_LEN}
            minRows={4}
            variant="bordered"
            description={t('appreciations.chars_remaining', '{{n}} characters left', { n: remaining })}
          />
          <div className="flex items-center justify-between pt-2">
            <span className="text-sm text-[var(--text-muted)]">
              {isPublic
                ? t('appreciations.public_hint', 'Visible on their profile')
                : t('appreciations.private_hint', 'Only they can see this')}
            </span>
            <Switch isSelected={isPublic} onValueChange={setIsPublic} size="sm">
              {t('appreciations.public_toggle', 'Public')}
            </Switch>
          </div>
        </ModalBody>
        <ModalFooter>
          <Button variant="light" onPress={handleClose} isDisabled={submitting}>
            {t('common.cancel', 'Cancel')}
          </Button>
          <Button
            color="primary"
            onPress={handleSend}
            isLoading={submitting}
            isDisabled={!message.trim() || message.length > MAX_LEN}
          >
            {t('appreciations.send', 'Send thanks')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default AppreciationModal;
