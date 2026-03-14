// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Event Reminder Settings (E4)
 *
 * Placeholder for global event reminder preferences.
 * The backend currently supports per-event reminders (POST /v2/events/{id}/reminders)
 * but does not yet have a global reminder-preferences endpoint.
 * This component will be fully implemented when the backend API is ready.
 */

import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import { Card, CardBody } from '@heroui/react';
import { Bell, Clock } from 'lucide-react';
import { usePageTitle } from '@/hooks/usePageTitle';

export function EventReminderSettings() {
  const { t } = useTranslation('events');
  usePageTitle(t('reminder_settings', 'Event Reminder Settings'));

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
    >
      <Card className="bg-theme-card border border-theme-default shadow-sm">
        <CardBody className="p-6 text-center space-y-4">
          <div className="flex justify-center">
            <div className="relative p-3 rounded-2xl bg-amber-500/10">
              <Bell className="w-8 h-8 text-amber-500" aria-hidden="true" />
              <Clock
                className="w-4 h-4 text-amber-400 absolute -bottom-0.5 -right-0.5"
                aria-hidden="true"
              />
            </div>
          </div>

          <div>
            <h2 className="text-lg font-semibold text-theme-primary">
              {t('reminder.title', { defaultValue: 'Event Reminders' })}
            </h2>
            <p className="text-sm text-theme-subtle mt-1 max-w-md mx-auto">
              {t('reminder.coming_soon_desc', {
                defaultValue:
                  'Global reminder preferences are coming soon. In the meantime, you can set reminders on individual events from their detail page.',
              })}
            </p>
          </div>

          <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 text-xs font-medium">
            <Clock className="w-3.5 h-3.5" aria-hidden="true" />
            {t('common:coming_soon', { defaultValue: 'Coming Soon' })}
          </div>
        </CardBody>
      </Card>
    </motion.div>
  );
}

export default EventReminderSettings;
