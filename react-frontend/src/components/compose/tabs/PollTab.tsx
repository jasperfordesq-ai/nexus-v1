// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PollTab — poll creation form with question, options, and end date.
 */

import { useState } from 'react';
import { Button, Input, Avatar, DatePicker } from '@heroui/react';
import type { DateInputValue } from '@heroui/react';
import { Plus, X } from 'lucide-react';
import { useAuth, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { TabSubmitProps } from '../types';

export function PollTab({ onSuccess, onClose, groupId }: TabSubmitProps) {
  const { user } = useAuth();
  const toast = useToast();
  const [question, setQuestion] = useState('');
  const [options, setOptions] = useState<string[]>(['', '']);
  const [expiresAt, setExpiresAt] = useState<DateInputValue | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const validOptions = options.filter((o) => o.trim().length > 0);
  const canSubmit = question.trim().length > 0 && validOptions.length >= 2;

  const addOption = () => {
    if (options.length < 6) setOptions([...options, '']);
  };

  const updateOption = (index: number, value: string) => {
    const updated = [...options];
    updated[index] = value;
    setOptions(updated);
  };

  const removeOption = (index: number) => {
    if (options.length > 2) setOptions(options.filter((_, i) => i !== index));
  };

  const handleSubmit = async () => {
    if (!question.trim()) {
      toast.error('Please enter a question');
      return;
    }
    if (validOptions.length < 2) {
      toast.error('Add at least 2 options');
      return;
    }

    setIsSubmitting(true);
    try {
      const payload: Record<string, unknown> = {
        question: question.trim(),
        options: validOptions.map((o) => o.trim()),
      };

      if (expiresAt) {
        payload.expires_at = expiresAt.toString();
      }
      if (groupId) {
        payload.group_id = groupId;
      }

      const res = await api.post('/v2/feed/polls', payload);
      if (res.success) {
        toast.success('Poll created!');
        onClose();
        onSuccess('poll');
      }
    } catch (err) {
      logError('Failed to create poll', err);
      toast.error('Failed to create poll');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-start gap-3">
        <Avatar
          name={user?.first_name || 'You'}
          src={resolveAvatarUrl(user?.avatar)}
          size="sm"
          className="mt-1"
          isBordered
        />
        <Input
          placeholder="Ask a question..."
          value={question}
          onChange={(e) => setQuestion(e.target.value)}
          classNames={{
            input: 'bg-transparent text-[var(--text-primary)]',
            inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)] hover:border-[var(--color-primary)]/40',
          }}
        />
      </div>

      <div className="space-y-2 pl-0 sm:pl-11">
        {options.map((opt, index) => (
          <div key={index} className="flex items-center gap-2">
            <div className="w-5 h-5 rounded-full border-2 border-[var(--border-default)] flex-shrink-0 flex items-center justify-center text-[10px] text-[var(--text-subtle)] font-medium">
              {index + 1}
            </div>
            <Input
              placeholder={`Option ${index + 1}`}
              value={opt}
              onChange={(e) => updateOption(index, e.target.value)}
              size="sm"
              classNames={{
                input: 'bg-transparent text-[var(--text-primary)]',
                inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)] hover:border-[var(--color-primary)]/40',
              }}
            />
            {options.length > 2 && (
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className="text-[var(--text-muted)] min-w-0 hover:text-danger"
                onPress={() => removeOption(index)}
                aria-label={`Remove option ${index + 1}`}
              >
                <X className="w-4 h-4" />
              </Button>
            )}
          </div>
        ))}

        {options.length < 6 && (
          <Button
            size="sm"
            variant="flat"
            className="bg-[var(--surface-elevated)] text-[var(--color-primary)]"
            startContent={<Plus className="w-3 h-3" aria-hidden="true" />}
            onPress={addOption}
          >
            Add Option
          </Button>
        )}

        <DatePicker
          label="End date (optional)"
          value={expiresAt}
          onChange={setExpiresAt}
          granularity="day"
          classNames={{
            inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)]',
          }}
          description="Leave empty for no deadline"
        />
      </div>

      <div className="flex flex-col-reverse sm:flex-row justify-end gap-2 pt-2">
        <Button
          variant="flat"
          onPress={onClose}
          className="text-[var(--text-muted)]"
        >
          Cancel
        </Button>
        <Button
          className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/20"
          onPress={handleSubmit}
          isLoading={isSubmitting}
          isDisabled={!canSubmit}
        >
          Create Poll
        </Button>
      </div>
    </div>
  );
}
