// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GoalTab — personal/community goal creation form for the Compose Hub.
 */

import { useState } from 'react';
import { Button, Input, Textarea, Switch } from '@heroui/react';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { TabSubmitProps } from '../types';

const inputClasses = {
  input: 'bg-transparent text-[var(--text-primary)]',
  inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)] hover:border-[var(--color-primary)]/40',
};

export function GoalTab({ onSuccess, onClose }: TabSubmitProps) {
  const toast = useToast();
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [targetValue, setTargetValue] = useState('');
  const [deadline, setDeadline] = useState('');
  const [isPublic, setIsPublic] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const canSubmit = title.trim().length > 0;

  const handleSubmit = async () => {
    if (!canSubmit) return;

    setIsSubmitting(true);
    try {
      const payload: Record<string, unknown> = {
        title: title.trim(),
      };
      if (description.trim()) payload.description = description.trim();
      if (targetValue) payload.target_value = parseFloat(targetValue) || 0;
      if (deadline) payload.deadline = deadline;
      payload.is_public = isPublic;

      const res = await api.post('/v2/goals', payload);
      if (res.success) {
        toast.success('Goal created!');
        onClose();
        onSuccess('goal');
      }
    } catch (err) {
      logError('Failed to create goal', err);
      toast.error('Failed to create goal');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="space-y-4">
      <Input
        label="Goal Title"
        placeholder="e.g., Give 10 hours this month"
        value={title}
        onChange={(e) => setTitle(e.target.value)}
        isRequired
        classNames={inputClasses}
      />

      <Textarea
        label="Description"
        placeholder="Describe your goal..."
        value={description}
        onChange={(e) => setDescription(e.target.value)}
        minRows={2}
        maxRows={5}
        classNames={inputClasses}
      />

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Input
          type="number"
          label="Target Value"
          placeholder="e.g., 10"
          value={targetValue}
          onChange={(e) => setTargetValue(e.target.value)}
          classNames={inputClasses}
        />
        <Input
          type="date"
          label="Deadline (optional)"
          value={deadline}
          onChange={(e) => setDeadline(e.target.value)}
          classNames={inputClasses}
        />
      </div>

      <div className="flex items-center justify-between p-3 rounded-lg bg-[var(--surface-elevated)] border border-[var(--border-default)]">
        <div>
          <p className="text-sm font-medium text-[var(--text-primary)]">Make Public</p>
          <p className="text-xs text-[var(--text-muted)]">Others can see and support your goal</p>
        </div>
        <Switch
          isSelected={isPublic}
          onValueChange={setIsPublic}
          size="sm"
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
          Create Goal
        </Button>
      </div>
    </div>
  );
}
