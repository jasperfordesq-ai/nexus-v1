// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AiAssistButton — generates content descriptions via AI.
 * Calls POST /api/ai/generate/{type} and handles errors gracefully.
 */

import { useState } from 'react';
import { Button, Tooltip } from '@heroui/react';
import { Sparkles } from 'lucide-react';
import { api } from '@/lib/api';
import { useToast } from '@/contexts';

interface AiAssistButtonProps {
  type: 'listing' | 'event';
  title: string;
  context?: Record<string, unknown>;
  onGenerated: (content: string) => void;
}

export function AiAssistButton({ type, title, context, onGenerated }: AiAssistButtonProps) {
  const [isGenerating, setIsGenerating] = useState(false);
  const toast = useToast();

  const handleGenerate = async () => {
    if (!title.trim()) {
      toast.error('Enter a title first so AI can generate a description');
      return;
    }

    setIsGenerating(true);
    try {
      const res = await api.post<{ content: string }>(`/ai/generate/${type}`, {
        title: title.trim(),
        ...(context ? { context } : {}),
      });

      if (res.success && res.data?.content) {
        onGenerated(res.data.content);
        toast.success('AI description generated!');
      } else {
        toast.error('AI could not generate a description');
      }
    } catch (err: unknown) {
      const status = (err as { status?: number })?.status;
      if (status === 403) {
        toast.error('AI assist is not available for your community');
      } else if (status === 429) {
        toast.error('You\'ve used your AI credits — try again later');
      } else {
        toast.error('AI generation failed — please write manually');
      }
    } finally {
      setIsGenerating(false);
    }
  };

  return (
    <Tooltip content="Generate description with AI" placement="top">
      <Button
        size="sm"
        variant="flat"
        className="bg-gradient-to-r from-violet-500/10 to-purple-500/10 text-violet-600 dark:text-violet-400 border border-violet-500/20 hover:border-violet-500/40"
        startContent={<Sparkles className="w-3.5 h-3.5" aria-hidden="true" />}
        onPress={handleGenerate}
        isLoading={isGenerating}
        isDisabled={!title.trim()}
      >
        AI Assist
      </Button>
    </Tooltip>
  );
}
