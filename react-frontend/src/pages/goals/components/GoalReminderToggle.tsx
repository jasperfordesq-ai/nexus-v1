// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * G4 - Goal Reminder Toggle
 *
 * Shows a bell icon with reminder status and a popover to
 * configure reminder frequency for a goal.
 *
 * API: GET /api/v2/goals/{id}/reminder
 *      PUT /api/v2/goals/{id}/reminder
 *      DELETE /api/v2/goals/{id}/reminder
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Popover,
  PopoverTrigger,
  PopoverContent,
  Divider,
} from '@heroui/react';
import { Bell, BellOff, BellRing, Check } from 'lucide-react';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

import { useTranslation } from 'react-i18next';
/* ───────────────────────── Types ───────────────────────── */

interface GoalReminder {
  id: number;
  goal_id: number;
  frequency: 'daily' | 'weekly' | 'biweekly' | 'monthly';
  enabled: boolean;
  next_reminder_at: string | null;
}

interface GoalReminderToggleProps {
  goalId: number;
  className?: string;
}

/* ───────────────────────── Frequency Options ───────────────────────── */

const FREQUENCIES = [
  { value: 'daily', label: 'Daily' },
  { value: 'weekly', label: 'Weekly' },
  { value: 'biweekly', label: 'Every 2 weeks' },
  { value: 'monthly', label: 'Monthly' },
] as const;

/* ───────────────────────── Component ───────────────────────── */

export function GoalReminderToggle({ goalId, className = '' }: GoalReminderToggleProps) {
  const toast = useToast();
  const { t } = useTranslation('goals');
  const [reminder, setReminder] = useState<GoalReminder | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [isPopoverOpen, setIsPopoverOpen] = useState(false);

  const loadReminder = useCallback(async () => {
    try {
      setIsLoading(true);
      const response = await api.get<GoalReminder>(`/v2/goals/${goalId}/reminder`);
      if (response.success && response.data) {
        setReminder(response.data);
      } else {
        setReminder(null);
      }
    } catch {
      // No reminder set — that's OK
      setReminder(null);
    } finally {
      setIsLoading(false);
    }
  }, [goalId]);

  useEffect(() => {
    loadReminder();
  }, [loadReminder]);

  const handleSetReminder = async (frequency: string) => {
    try {
      setIsSaving(true);
      const response = await api.put(`/v2/goals/${goalId}/reminder`, { frequency });
      if (response.success) {
        toast.success(`Reminder set: ${frequency}`);
        await loadReminder();
        setIsPopoverOpen(false);
      } else {
        toast.error(t('reminder_set_failed'));
      }
    } catch (err) {
      logError('Failed to set goal reminder', err);
      toast.error(t('reminder_set_failed'));
    } finally {
      setIsSaving(false);
    }
  };

  const handleDeleteReminder = async () => {
    try {
      setIsSaving(true);
      const response = await api.delete(`/v2/goals/${goalId}/reminder`);
      if (response.success) {
        toast.success(t('reminder_removed'));
        setReminder(null);
        setIsPopoverOpen(false);
      } else {
        toast.error(t('reminder_remove_failed'));
      }
    } catch (err) {
      logError('Failed to delete goal reminder', err);
      toast.error(t('reminder_remove_failed'));
    } finally {
      setIsSaving(false);
    }
  };

  const hasReminder = reminder?.enabled;

  return (
    <Popover
      placement="bottom"
      isOpen={isPopoverOpen}
      onOpenChange={setIsPopoverOpen}
    >
      <PopoverTrigger>
        <Button
          isIconOnly
          size="sm"
          variant="flat"
          className={`${hasReminder ? 'bg-indigo-500/10 text-indigo-400' : 'bg-theme-elevated text-theme-muted'} ${className}`}
          isLoading={isLoading}
          aria-label={hasReminder ? 'Reminder active' : 'Set reminder'}
        >
          {hasReminder ? (
            <BellRing className="w-4 h-4" />
          ) : (
            <Bell className="w-4 h-4" />
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="bg-content1 border border-theme-default p-3 w-52">
        <div className="space-y-2">
          <p className="text-sm font-semibold text-theme-primary">
            {hasReminder ? 'Reminder Active' : 'Set Reminder'}
          </p>

          {hasReminder && reminder && (
            <p className="text-xs text-theme-subtle">
              Currently: {FREQUENCIES.find((f) => f.value === reminder.frequency)?.label || reminder.frequency}
            </p>
          )}

          <Divider />

          <div className="space-y-1">
            {FREQUENCIES.map((freq) => (
              <Button
                key={freq.value}
                size="sm"
                variant="flat"
                className={`w-full justify-start text-sm ${
                  reminder?.frequency === freq.value
                    ? 'bg-indigo-500/10 text-indigo-400'
                    : 'bg-transparent text-theme-muted hover:bg-theme-hover'
                }`}
                onPress={() => handleSetReminder(freq.value)}
                isDisabled={isSaving}
                endContent={reminder?.frequency === freq.value ? <Check className="w-3.5 h-3.5" /> : undefined}
              >
                {freq.label}
              </Button>
            ))}
          </div>

          {hasReminder && (
            <>
              <Divider />
              <Button
                size="sm"
                variant="flat"
                className="w-full justify-start text-sm text-danger hover:bg-danger/10"
                startContent={<BellOff className="w-3.5 h-3.5" aria-hidden="true" />}
                onPress={handleDeleteReminder}
                isDisabled={isSaving}
              >
                Remove Reminder
              </Button>
            </>
          )}
        </div>
      </PopoverContent>
    </Popover>
  );
}

export default GoalReminderToggle;
