// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button } from '@/components/ui/Button';
import { Modal, ModalContent, ModalHeader, ModalBody, ModalFooter } from '@/components/ui/Modal';
import { RadioGroup, Radio } from '@/components/ui/Radio';
import { Spinner } from '@/components/ui/Spinner';
import { Switch } from '@/components/ui/Switch';
/**
 * Group Notification Preferences
 * Modal for per-group notification settings (frequency, email, push).
 */

import { useState, useEffect } from 'react';

import Bell from 'lucide-react/icons/bell';
import { logError } from '@/lib/logger';
import { useToast } from '@/contexts';
import { useTranslation } from 'react-i18next';
import {
  getGroupNotificationPreferences,
  updateGroupNotificationPreferences,
  type GroupNotificationPreferences,
} from '../api';

interface GroupNotificationPrefsProps {
  groupId: number;
  isOpen: boolean;
  onClose: () => void;
}

const DEFAULT_NOTIFICATION_PREFERENCES: GroupNotificationPreferences = {
  frequency: 'instant',
  email_enabled: true,
  push_enabled: true,
  updated_at: null,
};

export function GroupNotificationPrefs({ groupId, isOpen, onClose }: GroupNotificationPrefsProps) {
  const { t } = useTranslation('groups');
  const toast = useToast();

  const [prefs, setPrefs] = useState<GroupNotificationPreferences>(DEFAULT_NOTIFICATION_PREFERENCES);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);
  const [loadAttempt, setLoadAttempt] = useState(0);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!isOpen) return;
    const controller = new AbortController();

    async function loadPrefs() {
      setLoading(true);
      setLoadError(false);
      setPrefs(DEFAULT_NOTIFICATION_PREFERENCES);
      try {
        const data = await getGroupNotificationPreferences(groupId, { signal: controller.signal });
        if (controller.signal.aborted) return;
        setPrefs(data);
      } catch (err) {
        if (controller.signal.aborted) return;
        logError('GroupNotificationPrefs.loadPrefs', err);
        setLoadError(true);
      } finally {
        if (!controller.signal.aborted) setLoading(false);
      }
    }

    loadPrefs();
    return () => controller.abort();
  }, [groupId, isOpen, loadAttempt]);

  const handleSave = async () => {
    setSaving(true);
    try {
      const saved = await updateGroupNotificationPreferences(groupId, prefs);
      setPrefs(saved.preferences);
      toast.success(t('notifications.prefs_saved'));
      onClose();
    } catch (err) {
      logError('GroupNotificationPrefs.handleSave', err);
      toast.error(t('notifications.prefs_save_failed'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="md">
      <ModalContent>
        <ModalHeader className="flex items-center gap-2">
          <Bell size={20} className="text-accent" aria-hidden="true" />
          {t('notifications.prefs_title')}
        </ModalHeader>

        <ModalBody>
          {loading ? (
            <div role="status" aria-busy="true" aria-label={t('loading', { ns: 'common' })} className="flex items-center justify-center py-8">
              <Spinner size="md" />
            </div>
          ) : loadError ? (
            <div role="alert" className="space-y-3 py-6 text-center">
              <p className="text-sm text-theme-muted">{t('form.error_load_failed')}</p>
              <Button variant="flat" onPress={() => setLoadAttempt((attempt) => attempt + 1)}>
                {t('form.try_again')}
              </Button>
            </div>
          ) : (
            <div className="space-y-6">
              <RadioGroup
                label={t('notifications.frequency_label')}
                value={prefs.frequency}
                onValueChange={(value) =>
                  setPrefs((prev) => ({ ...prev, frequency: value as GroupNotificationPreferences['frequency'] }))
                }
              >
                <Radio value="instant">
                  {t('notifications.frequency_instant')}
                </Radio>
                <Radio value="digest">
                  {t('notifications.frequency_digest')}
                </Radio>
                <Radio value="muted">
                  {t('notifications.frequency_muted')}
                </Radio>
              </RadioGroup>

              <div className="space-y-3">
                <Switch
                  isSelected={prefs.email_enabled}
                  isDisabled={prefs.frequency === 'muted'}
                  onValueChange={(checked) =>
                    setPrefs((prev) => ({ ...prev, email_enabled: checked }))
                  }
                >
                  {t('notifications.email_enabled')}
                </Switch>

                <Switch
                  isSelected={prefs.push_enabled}
                  isDisabled={prefs.frequency === 'muted'}
                  onValueChange={(checked) =>
                    setPrefs((prev) => ({ ...prev, push_enabled: checked }))
                  }
                >
                  {t('notifications.push_enabled')}
                </Switch>
              </div>
            </div>
          )}
        </ModalBody>

        <ModalFooter>
          <Button variant="flat" onPress={onClose}>
            {t('common:cancel')}
          </Button>
          <Button
            color="primary"
            onPress={handleSave}
            isLoading={saving}
            isDisabled={loading || loadError}
          >
            {t('common:save')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default GroupNotificationPrefs;
