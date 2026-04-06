// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import { Button, Input, Textarea } from '@heroui/react';
import { Save, Building2 } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { useTranslation } from 'react-i18next';

interface OrgSettingsTabProps {
  orgId: number;
  orgData: {
    name: string;
    description: string | null;
    contact_email: string | null;
    website: string | null;
  };
  onOrgUpdate: () => void;
}

export default function OrgSettingsTab({ orgId, orgData, onOrgUpdate }: OrgSettingsTabProps) {
  const { t } = useTranslation('volunteering');
  const toast = useToast();
  const [name, setName] = useState(orgData.name);
  const [description, setDescription] = useState(orgData.description ?? '');
  const [contactEmail, setContactEmail] = useState(orgData.contact_email ?? '');
  const [website, setWebsite] = useState(orgData.website ?? '');
  const [isSaving, setIsSaving] = useState(false);

  async function handleSave() {
    if (!name.trim()) {
      toast.error(t('org_settings.name_required', 'Organization name is required.'));
      return;
    }
    setIsSaving(true);
    try {
      const response = await api.put(`/v2/volunteering/organisations/${orgId}`, {
        name: name.trim(),
        description: description.trim(),
        contact_email: contactEmail.trim(),
        website: website.trim(),
      });
      if (response.success) {
        toast.success(t('org_settings.saved', 'Organization settings saved.'));
        onOrgUpdate();
      } else {
        toast.error(response.error || t('org_settings.save_failed', 'Failed to save settings.'));
      }
    } catch (err) {
      logError('Failed to save org settings', err);
      toast.error(t('org_settings.save_failed', 'Failed to save settings.'));
    } finally {
      setIsSaving(false);
    }
  }

  return (
    <div className="space-y-6 max-w-2xl">
      <GlassCard className="p-6 space-y-5">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-500 to-gray-600 flex items-center justify-center">
            <Building2 className="w-5 h-5 text-white" aria-hidden="true" />
          </div>
          <h2 className="text-lg font-semibold text-theme-primary">
            {t('org_settings.heading', 'Organization Settings')}
          </h2>
        </div>

        <Input
          label={t('org_settings.name', 'Organization Name')}
          value={name}
          onValueChange={setName}
          isRequired
          variant="bordered"
          classNames={{ inputWrapper: 'bg-theme-elevated' }}
        />

        <Textarea
          label={t('org_settings.description', 'Description')}
          value={description}
          onValueChange={setDescription}
          variant="bordered"
          minRows={3}
          maxRows={8}
          classNames={{ inputWrapper: 'bg-theme-elevated' }}
        />

        <Input
          label={t('org_settings.contact_email', 'Contact Email')}
          value={contactEmail}
          onValueChange={setContactEmail}
          type="email"
          variant="bordered"
          classNames={{ inputWrapper: 'bg-theme-elevated' }}
        />

        <Input
          label={t('org_settings.website', 'Website')}
          value={website}
          onValueChange={setWebsite}
          type="url"
          variant="bordered"
          placeholder="https://"
          classNames={{ inputWrapper: 'bg-theme-elevated' }}
        />

        <div className="flex justify-end pt-2">
          <Button
            color="primary"
            isLoading={isSaving}
            startContent={!isSaving ? <Save className="w-4 h-4" /> : undefined}
            onPress={handleSave}
          >
            {t('org_settings.save', 'Save Changes')}
          </Button>
        </div>
      </GlassCard>
    </div>
  );
}
