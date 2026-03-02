// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Event Reminder Settings (E4)
 *
 * Lets users configure how and when they receive reminders
 * for events they have RSVP'd to. Can be embedded in the
 * event detail page or within the user settings page.
 */

import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import { Button, Select, SelectItem, Switch, Skeleton } from '@heroui/react';
import { Bell, Mail, Clock, Save, Smartphone, AlertCircle } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { EventReminderPreferences } from '@/types/api';

/** Pre-defined reminder intervals (in minutes) */
const REMINDER_OPTIONS = [
  { key: '15', label: '15 minutes before' },
  { key: '30', label: '30 minutes before' },
  { key: '60', label: '1 hour before' },
  { key: '120', label: '2 hours before' },
  { key: '1440', label: '1 day before' },
  { key: '2880', label: '2 days before' },
  { key: '10080', label: '1 week before' },
] as const;

const defaultPreferences: EventReminderPreferences = {
  remind_before_minutes: 60,
  email_enabled: true,
  push_enabled: true,
};

export function EventReminderSettings() {
  const { t } = useTranslation('events');
  const toast = useToast();

  const [preferences, setPreferences] = useState<EventReminderPreferences>(defaultPreferences);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [hasChanges, setHasChanges] = useState(false);

  // Track original for dirty checking
  const [original, setOriginal] = useState<EventReminderPreferences>(defaultPreferences);

  const loadPreferences = useCallback(async () => {
    try {
      setIsLoading(true);
      setLoadError(null);
      const response = await api.get<EventReminderPreferences>('/v2/events/reminder-preferences');
      if (response.success && response.data) {
        setPreferences(response.data);
        setOriginal(response.data);
      }
    } catch (err) {
      logError('Failed to load reminder preferences', err);
      setLoadError('Unable to load your reminder preferences.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadPreferences();
  }, [loadPreferences]);

  // Dirty checking
  useEffect(() => {
    const changed =
      preferences.remind_before_minutes !== original.remind_before_minutes ||
      preferences.email_enabled !== original.email_enabled ||
      preferences.push_enabled !== original.push_enabled;
    setHasChanges(changed);
  }, [preferences, original]);

  async function handleSave() {
    try {
      setIsSaving(true);
      const response = await api.put('/v2/events/reminder-preferences', preferences);
      if (response.success) {
        setOriginal({ ...preferences });
        setHasChanges(false);
        toast.success(
          t('reminder.toast_saved', { defaultValue: 'Reminder preferences saved' })
        );
      } else {
        toast.error(
          t('reminder.toast_error', { defaultValue: 'Failed to save preferences' })
        );
      }
    } catch (err) {
      logError('Failed to save reminder preferences', err);
      toast.error(
        t('reminder.toast_error', { defaultValue: 'Failed to save preferences' })
      );
    } finally {
      setIsSaving(false);
    }
  }

  if (isLoading) {
    return (
      <GlassCard className="p-6 space-y-4">
        <Skeleton className="w-48 h-6 rounded-lg" />
        <Skeleton className="w-full h-12 rounded-lg" />
        <Skeleton className="w-full h-12 rounded-lg" />
        <Skeleton className="w-full h-12 rounded-lg" />
      </GlassCard>
    );
  }

  if (loadError) {
    return (
      <GlassCard className="p-6 text-center">
        <AlertCircle className="w-10 h-10 text-amber-500 mx-auto mb-3" aria-hidden="true" />
        <p className="text-theme-muted mb-3">{loadError}</p>
        <Button
          size="sm"
          variant="flat"
          className="bg-theme-elevated text-theme-primary"
          onPress={loadPreferences}
        >
          Try again
        </Button>
      </GlassCard>
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
    >
      <GlassCard className="p-6 space-y-6">
        {/* Header */}
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-amber-500/20">
            <Bell className="w-5 h-5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
          </div>
          <div>
            <h2 className="text-lg font-semibold text-theme-primary">
              {t('reminder.title', { defaultValue: 'Event Reminders' })}
            </h2>
            <p className="text-sm text-theme-subtle">
              {t('reminder.subtitle', { defaultValue: 'Get reminded before events you RSVP to' })}
            </p>
          </div>
        </div>

        {/* Reminder Timing */}
        <div>
          <Select
            label={t('reminder.timing_label', { defaultValue: 'Remind me' })}
            aria-label="Reminder timing"
            selectedKeys={[String(preferences.remind_before_minutes)]}
            onChange={(e) =>
              setPreferences((prev) => ({
                ...prev,
                remind_before_minutes: parseInt(e.target.value) || 60,
              }))
            }
            startContent={<Clock className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
            classNames={{
              trigger: 'bg-theme-elevated border-theme-default',
              value: 'text-theme-primary',
              label: 'text-theme-muted',
            }}
          >
            {REMINDER_OPTIONS.map((opt) => (
              <SelectItem key={opt.key}>{opt.label}</SelectItem>
            ))}
          </Select>
        </div>

        {/* Notification Channels */}
        <div className="space-y-3">
          <label className="block text-sm font-medium text-theme-muted">
            {t('reminder.channels_label', { defaultValue: 'Notification channels' })}
          </label>

          {/* Email Toggle */}
          <div className="flex items-center justify-between p-3 rounded-xl bg-theme-elevated border border-theme-default">
            <div className="flex items-center gap-3">
              <div className="p-1.5 rounded-lg bg-indigo-500/20">
                <Mail className="w-4 h-4 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
              </div>
              <div>
                <p className="text-sm font-medium text-theme-primary">
                  {t('reminder.email_label', { defaultValue: 'Email notifications' })}
                </p>
                <p className="text-xs text-theme-subtle">
                  {t('reminder.email_desc', { defaultValue: 'Receive an email reminder before events' })}
                </p>
              </div>
            </div>
            <Switch
              aria-label="Toggle email reminders"
              isSelected={preferences.email_enabled}
              onValueChange={(checked) =>
                setPreferences((prev) => ({ ...prev, email_enabled: checked }))
              }
              classNames={{
                wrapper: 'group-data-[selected=true]:bg-indigo-500',
              }}
            />
          </div>

          {/* Push Notification Toggle */}
          <div className="flex items-center justify-between p-3 rounded-xl bg-theme-elevated border border-theme-default">
            <div className="flex items-center gap-3">
              <div className="p-1.5 rounded-lg bg-emerald-500/20">
                <Smartphone className="w-4 h-4 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
              </div>
              <div>
                <p className="text-sm font-medium text-theme-primary">
                  {t('reminder.push_label', { defaultValue: 'Push notifications' })}
                </p>
                <p className="text-xs text-theme-subtle">
                  {t('reminder.push_desc', { defaultValue: 'Receive a push notification on your device' })}
                </p>
              </div>
            </div>
            <Switch
              aria-label="Toggle push reminders"
              isSelected={preferences.push_enabled}
              onValueChange={(checked) =>
                setPreferences((prev) => ({ ...prev, push_enabled: checked }))
              }
              classNames={{
                wrapper: 'group-data-[selected=true]:bg-emerald-500',
              }}
            />
          </div>

          {/* Warning if both are off */}
          {!preferences.email_enabled && !preferences.push_enabled && (
            <div className="flex items-center gap-2 p-3 rounded-lg bg-amber-500/10 border border-amber-500/30">
              <AlertCircle className="w-4 h-4 text-amber-400 flex-shrink-0" aria-hidden="true" />
              <p className="text-sm text-amber-400">
                {t('reminder.no_channels_warning', {
                  defaultValue: 'You won\'t receive any event reminders with both channels disabled.',
                })}
              </p>
            </div>
          )}
        </div>

        {/* Save Button */}
        <div className="pt-2">
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white w-full sm:w-auto"
            startContent={<Save className="w-4 h-4" aria-hidden="true" />}
            onPress={handleSave}
            isLoading={isSaving}
            isDisabled={!hasChanges}
          >
            {t('reminder.save_btn', { defaultValue: 'Save Preferences' })}
          </Button>
        </div>
      </GlassCard>
    </motion.div>
  );
}

export default EventReminderSettings;
