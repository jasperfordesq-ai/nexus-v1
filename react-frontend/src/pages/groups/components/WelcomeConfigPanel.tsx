// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button } from '@/components/ui/Button';
import { GlassCard } from '@/components/ui/GlassCard';
import { Spinner } from '@/components/ui/Spinner';
import { Switch } from '@/components/ui/Switch';
import { Textarea } from '@/components/ui/Textarea';
/**
 * Welcome Config Panel
 * Admin panel for configuring group welcome messages sent to new members.
 */

import { useState, useEffect } from 'react';

import HandHeart from 'lucide-react/icons/hand-heart';
import Save from 'lucide-react/icons/save';
import { useToast } from '@/contexts';
import { useTranslation } from 'react-i18next';
import {
  getGroupWelcomeConfig,
  updateGroupWelcomeConfig,
  type GroupWelcomeConfig,
} from '../api';

interface WelcomeConfigPanelProps {
  groupId: number;
  isAdmin: boolean;
}

const DEFAULT_WELCOME_CONFIG: GroupWelcomeConfig = {
  enabled: false,
  message: '',
};

export function WelcomeConfigPanel({ groupId, isAdmin }: WelcomeConfigPanelProps) {
  const { t } = useTranslation('groups');
  const toast = useToast();

  const [config, setConfig] = useState<GroupWelcomeConfig>(DEFAULT_WELCOME_CONFIG);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!isAdmin) return;
    const controller = new AbortController();

    async function loadConfig() {
      setLoading(true);
      setConfig(DEFAULT_WELCOME_CONFIG);
      try {
        const data = await getGroupWelcomeConfig(groupId, { signal: controller.signal });
        if (controller.signal.aborted) return;
        setConfig(data);
      } catch {
        // Defaults are fine if no config exists yet
      } finally {
        if (!controller.signal.aborted) setLoading(false);
      }
    }

    loadConfig();
    return () => controller.abort();
  }, [groupId, isAdmin]);

  const handleSave = async () => {
    setSaving(true);
    try {
      await updateGroupWelcomeConfig(groupId, config);
      toast.success(t('welcome.saved'));
    } catch {
      toast.error(t('welcome.save_failed'));
    } finally {
      setSaving(false);
    }
  };

  if (!isAdmin) return null;

  if (loading) {
    return (
      <GlassCard className="p-5">
        <div role="status" aria-busy="true" aria-label={t('loading', { ns: 'common' })} className="flex items-center justify-center py-8">
          <Spinner size="md" />
        </div>
      </GlassCard>
    );
  }

  return (
    <GlassCard className="p-5 space-y-5">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <HandHeart size={18} className="text-accent" aria-hidden="true" />
          <h3 className="text-base font-semibold text-foreground">
            {t('welcome.title')}
          </h3>
        </div>

        <Switch
          isSelected={config.enabled}
          onValueChange={(checked) =>
            setConfig((prev) => ({ ...prev, enabled: checked }))
          }
          size="sm"
          aria-label={t('welcome.toggle_label')}
        />
      </div>

      <Textarea
        label={t('welcome.message_label')}
        placeholder={t('welcome.message_placeholder')}
        value={config.message}
        onValueChange={(value) =>
          setConfig((prev) => ({ ...prev, message: value }))
        }
        minRows={4}
        maxRows={8}
        variant="bordered"
        isDisabled={!config.enabled}
        description={t('welcome.variables_hint')}
      />

      <div className="flex justify-end">
        <Button
          color="primary"
          startContent={<Save size={16} aria-hidden="true" />}
          onPress={handleSave}
          isLoading={saving}
        >
          {t('common:save')}
        </Button>
      </div>
    </GlassCard>
  );
}

export default WelcomeConfigPanel;
