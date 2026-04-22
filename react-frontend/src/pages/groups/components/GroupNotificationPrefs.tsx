// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Notification Preferences
 * Modal for per-group notification settings (frequency, email, push).
 */

import { useState, useEffect } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  RadioGroup,
  Radio,
  Switch,
  Spinner,
} from '@heroui/react';
import Bell from 'lucide-react/icons/bell';
import { api } from '@/lib/api';
import { useToast } from '@/contexts';
import { useTranslation } from 'react-i18next';

interface GroupNotificationPrefsProps {
  groupId: number;
  isOpen: boolean;
  onClose: () => void;
}

interface NotificationPrefs {
  frequency: 'instant' | 'digest' | 'muted';
  email_enabled: boolean;
  push_enabled: boolean;
}

export function GroupNotificationPrefs({ groupId, isOpen, onClose }: GroupNotificationPrefsProps) {
  const { t } = useTranslation('groups');
  const toast = useToast();

  const [prefs, setPrefs] = useState<NotificationPrefs>({
    frequency: 'instant',
    email_enabled: true,
    push_enabled: true,
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!isOpen) return;

    async function loadPrefs() {
      setLoading(true);
      try {
        const res = await api.get(`/v2/groups/${groupId}/notification-prefs`);
        if (res.success && res.data) {
          const data = res.data as NotificationPrefs;
          setPrefs({
            frequency: data.frequency ?? 'instant',
            email_enabled: data.email_enabled ?? true,
            push_enabled: data.push_enabled ?? true,
          });
        }
      } catch {
        // Use defaults if prefs haven't been set yet
      } finally {
        setLoading(false);
      }
    }

    loadPrefs();
  }, [groupId, isOpen]);

  const handleSave = async () => {
    setSaving(true);
    try {
      const res = await api.put(`/v2/groups/${groupId}/notification-prefs`, prefs);
      if (res.success) {
        toast.success(t('notifications.prefs_saved', 'Notification preferences saved'));
        onClose();
      } else {
        toast.error(t('notifications.prefs_save_failed', 'Failed to save preferences'));
      }
    } catch {
      toast.error(t('notifications.prefs_save_failed', 'Failed to save preferences'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="md">
      <ModalContent>
        <ModalHeader className="flex items-center gap-2">
          <Bell size={20} className="text-primary" />
          {t('notifications.prefs_title', 'Notification Preferences')}
        </ModalHeader>

        <ModalBody>
          {loading ? (
            <div className="flex items-center justify-center py-8">
              <Spinner size="md" />
            </div>
          ) : (
            <div className="space-y-6">
              <RadioGroup
                label={t('notifications.frequency_label', 'Notification Frequency')}
                value={prefs.frequency}
                onValueChange={(value) =>
                  setPrefs((prev) => ({ ...prev, frequency: value as NotificationPrefs['frequency'] }))
                }
              >
                <Radio value="instant">
                  {t('notifications.frequency_instant', 'Instant')}
                </Radio>
                <Radio value="digest">
                  {t('notifications.frequency_digest', 'Digest')}
                </Radio>
                <Radio value="muted">
                  {t('notifications.frequency_muted', 'Muted')}
                </Radio>
              </RadioGroup>

              <div className="space-y-3">
                <Switch
                  isSelected={prefs.email_enabled}
                  onValueChange={(checked) =>
                    setPrefs((prev) => ({ ...prev, email_enabled: checked }))
                  }
                >
                  {t('notifications.email_enabled', 'Email notifications')}
                </Switch>

                <Switch
                  isSelected={prefs.push_enabled}
                  onValueChange={(checked) =>
                    setPrefs((prev) => ({ ...prev, push_enabled: checked }))
                  }
                >
                  {t('notifications.push_enabled', 'Push notifications')}
                </Switch>
              </div>
            </div>
          )}
        </ModalBody>

        <ModalFooter>
          <Button variant="flat" onPress={onClose}>
            {t('common:cancel', 'Cancel')}
          </Button>
          <Button
            color="primary"
            onPress={handleSave}
            isLoading={saving}
            isDisabled={loading}
          >
            {t('common:save', 'Save')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default GroupNotificationPrefs;
