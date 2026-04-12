// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AiReplySuggestion -- AI-powered reply suggestion for marketplace sellers.
 *
 * Shown to sellers when they receive a message about a listing.
 * Generates a contextual AI reply based on listing details and the buyer's question.
 * The seller can edit the suggestion before sending.
 *
 * NEXUS differentiator: AI-assisted seller communication.
 */

import { useState, useCallback } from 'react';
import { Button, Textarea, Card, CardBody } from '@heroui/react';
import { Sparkles, Copy, Check, RefreshCw } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface AiReplySuggestionProps {
  /** The listing ID for context */
  listingId: number;
  /** The buyer's message to reply to */
  buyerMessage: string;
  /** Called when the seller wants to use the generated reply */
  onUseReply: (reply: string) => void;
}

export function AiReplySuggestion({
  listingId,
  buyerMessage,
  onUseReply,
}: AiReplySuggestionProps) {
  const { t } = useTranslation('marketplace');

  const [reply, setReply] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [generated, setGenerated] = useState(false);
  const [copied, setCopied] = useState(false);

  const generateReply = useCallback(async () => {
    if (!buyerMessage.trim()) return;

    setLoading(true);
    setError(null);

    try {
      const response = await api.post<{ reply?: string }>(
        `/v2/marketplace/listings/${listingId}/auto-reply`,
        { message: buyerMessage },
      );
      setReply(response.data?.reply ?? '');
      setGenerated(true);
    } catch (err) {
      logError('Failed to generate AI reply', err);
      setError(t('ai_reply.error', 'Failed to generate a reply. Please try again.'));
    } finally {
      setLoading(false);
    }
  }, [listingId, buyerMessage, t]);

  const handleUseReply = () => {
    if (reply.trim()) {
      onUseReply(reply.trim());
    }
  };

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(reply);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // Fallback: select the textarea content
    }
  };

  // Not yet generated: show the "Suggest Reply" button
  if (!generated) {
    return (
      <div className="flex items-center gap-2">
        <Button
          size="sm"
          variant="flat"
          color="secondary"
          startContent={<Sparkles className="w-3.5 h-3.5" />}
          isLoading={loading}
          onPress={generateReply}
          isDisabled={!buyerMessage.trim()}
        >
          {t('ai_reply.suggest', 'Suggest Reply')}
        </Button>
        {error && (
          <span className="text-xs text-danger">{error}</span>
        )}
      </div>
    );
  }

  // Generated: show the editable reply
  return (
    <Card className="border border-secondary/20 bg-secondary/5">
      <CardBody className="gap-3 p-3">
        <div className="flex items-center gap-2">
          <Sparkles className="w-4 h-4 text-secondary" />
          <span className="text-xs font-medium text-secondary">
            {t('ai_reply.suggested_reply', 'AI Suggested Reply')}
          </span>
        </div>

        <Textarea
          value={reply}
          onValueChange={setReply}
          variant="bordered"
          minRows={2}
          maxRows={6}
          classNames={{
            input: 'text-sm',
          }}
        />

        <div className="flex items-center gap-2 justify-end">
          <Button
            size="sm"
            variant="light"
            startContent={<RefreshCw className="w-3.5 h-3.5" />}
            isLoading={loading}
            onPress={generateReply}
          >
            {t('ai_reply.regenerate', 'Regenerate')}
          </Button>
          <Button
            size="sm"
            variant="light"
            startContent={copied ? <Check className="w-3.5 h-3.5" /> : <Copy className="w-3.5 h-3.5" />}
            onPress={handleCopy}
          >
            {copied ? t('ai_reply.copied', 'Copied') : t('ai_reply.copy', 'Copy')}
          </Button>
          <Button
            size="sm"
            color="primary"
            onPress={handleUseReply}
            isDisabled={!reply.trim()}
          >
            {t('ai_reply.use_reply', 'Use This Reply')}
          </Button>
        </div>
      </CardBody>
    </Card>
  );
}

export default AiReplySuggestion;
