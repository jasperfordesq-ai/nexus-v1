// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Welcome Config Panel
 * Admin panel for configuring group welcome messages sent to new members.
 */

import { useState, useEffect } from 'react';
import { Button, Switch, Textarea, Spinner } from '@heroui/react';
import { HandHeart, Save } from 'lucide-react';
import { api } from '@/lib/api';
import { useToast } from '@/contexts';
import { GlassCard } from '@/components/ui';
import { useTranslation } from 'react-i18next';

interface WelcomeConfigPanelProps {
  groupId: number;
  isAdmin: boolean;
}

interface WelcomeConfig {
  enabled: boolean;
  message: string;
}

export function WelcomeConfigPanel({ groupId, isAdmin }: WelcomeConfigPanelProps) {
  const { t } = useTranslation('groups');
  const toast = useToast();

  const [config, setConfig] = useState<WelcomeConfig>({
    enabled: false,
    message: '',
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    async function loadConfig() {
      setLoading(true);
      try {
        const res = await api.get(`/v2/groups/${groupId}/welcome`);
        if (res.success && res.data) {
          const data = res.data as WelcomeConfig;
          setConfig({
            enabled: data.enabled ?? false,
            message: data.message ?? '',
          });
        }
      } catch {
        // Defaults are fine if no config exists yet
      } finally {
        setLoading(false);
      }
    }

    loadConfig();
  }, [groupId]);

  const handleSave = async () => {
    setSaving(true);
    try {
      const res = await api.put(`/v2/groups/${groupId}/welcome`, config);
      if (res.success) {
        toast.success(t('welcome.saved', 'Welcome message saved'));
      } else {
        toast.error(t('welcome.save_failed', 'Failed to save welcome message'));
      }
    } catch {
      toast.error(t('welcome.save_failed', 'Failed to save welcome message'));
    } finally {
      setSaving(false);
    }
  };

  if (!isAdmin) return null;

  if (loading) {
    return (
      <GlassCard className="p-5">
        <div className="flex items-center justify-center py-8">
          <Spinner size="md" />
        </div>
      </GlassCard>
    );
  }

  return (
    <GlassCard className="p-5 space-y-5">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <HandHeart size={18} className="text-primary" />
          <h3 className="text-base font-semibold text-foreground">
            {t('welcome.title', 'Welcome Message')}
          </h3>
        </div>

        <Switch
          isSelected={config.enabled}
          onValueChange={(checked) =>
            setConfig((prev) => ({ ...prev, enabled: checked }))
          }
          size="sm"
          aria-label={t('welcome.toggle_label', 'Enable welcome message')}
        />
      </div>

      <Textarea
        label={t('welcome.message_label', 'Message Template')}
        placeholder={t(
          'welcome.message_placeholder',
          'Welcome to our group, {member_name}! We are glad to have you.'
        )}
        value={config.message}
        onValueChange={(value) =>
          setConfig((prev) => ({ ...prev, message: value }))
        }
        minRows={4}
        maxRows={8}
        variant="bordered"
        isDisabled={!config.enabled}
        description={t(
          'welcome.variables_hint',
          'Available variables: {member_name}, {group_name}, {admin_name}'
        )}
      />

      <div className="flex justify-end">
        <Button
          color="primary"
          startContent={<Save size={16} />}
          onPress={handleSave}
          isLoading={saving}
        >
          {t('common:save', 'Save')}
        </Button>
      </div>
    </GlassCard>
  );
}

export default WelcomeConfigPanel;
