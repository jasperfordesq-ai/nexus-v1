// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * QuotePostModal — modal for creating a quote repost.
 * Shows a text area for user commentary with an embedded preview of the quoted post.
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
import { Send } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { QuotedPostEmbed, type QuotedPostData } from './QuotedPostEmbed';
import type { FeedItem } from './types';
import { getAuthor } from './types';

interface QuotePostModalProps {
  isOpen: boolean;
  onClose: () => void;
  post: FeedItem;
  onSuccess?: () => void;
}

export function QuotePostModal({ isOpen, onClose, post, onSuccess }: QuotePostModalProps) {
  const { t } = useTranslation('feed');
  const toast = useToast();
  const [content, setContent] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const author = getAuthor(post);

  const quotedPostData: QuotedPostData = {
    id: post.id,
    content: post.content,
    content_truncated: post.content_truncated,
    image_url: post.image_url,
    created_at: post.created_at,
    author: {
      id: author.id,
      name: author.name,
      avatar_url: author.avatar,
    },
    media: post.media?.map((m) => ({
      id: m.id,
      media_type: m.media_type,
      file_url: m.file_url,
      thumbnail_url: m.thumbnail_url,
      alt_text: m.alt_text,
    })),
  };

  const handleSubmit = async () => {
    if (!content.trim()) {
      toast.error(t('share.quote_content_required', 'Please add your commentary'));
      return;
    }

    try {
      setIsSubmitting(true);
      const response = await api.post('/v2/feed/posts', {
        content: content.trim(),
        quoted_post_id: post.id,
      });

      if (response.success) {
        toast.success(t('share.quote_posted', 'Quote post created'));
        setContent('');
        onClose();
        onSuccess?.();
      } else {
        toast.error(response.error || t('share.quote_failed', 'Failed to create quote post'));
      }
    } catch (err) {
      logError('Failed to create quote post', err);
      toast.error(t('share.quote_failed', 'Failed to create quote post'));
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size="lg"
      classNames={{
        base: 'bg-[var(--color-surface)] border border-[var(--border-default)]',
        header: 'border-b border-[var(--border-default)]',
        footer: 'border-t border-[var(--border-default)]',
      }}
    >
      <ModalContent>
        <ModalHeader className="text-[var(--text-primary)]">
          {t('share.quote_title', 'Quote Post')}
        </ModalHeader>
        <ModalBody className="gap-4">
          <Textarea
            placeholder={t('share.quote_placeholder', 'Add your thoughts...')}
            value={content}
            onChange={(e) => setContent(e.target.value)}
            minRows={3}
            maxRows={8}
            classNames={{
              input: 'bg-transparent text-[var(--text-primary)]',
              inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)]',
            }}
            autoFocus
          />
          <QuotedPostEmbed post={quotedPostData} isPreview />
        </ModalBody>
        <ModalFooter>
          <Button
            variant="light"
            onPress={onClose}
            className="text-[var(--text-muted)]"
          >
            {t('compose.cancel', 'Cancel')}
          </Button>
          <Button
            color="primary"
            onPress={handleSubmit}
            isLoading={isSubmitting}
            isDisabled={!content.trim()}
            startContent={!isSubmitting ? <Send className="w-4 h-4" /> : undefined}
          >
            {t('share.quote_submit', 'Post')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default QuotePostModal;
