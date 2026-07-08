import { Card, CardBody, CardHeader, Input, Button, Spinner, Switch } from '@/components/ui';
import { useState, useEffect } from 'react';

import Image from 'lucide-react/icons/image';
import Save from 'lucide-react/icons/save';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components/PageHeader';
import { adminSettings } from '../../api/adminApi';
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Image Settings
 * Configure image upload limits, dimensions, and processing options.
 */


export function ImageSettings() {
  const { t } = useTranslation('admin_system');
  usePageTitle(t('system.page_title'));
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [formData, setFormData] = useState<Record<string, unknown>>({
    max_file_size: '5',
    max_width: '2048',
    max_height: '2048',
    allowed_formats: 'jpg, jpeg, png, gif, webp',
    auto_resize: true,
    auto_webp: false,
    strip_exif: true,
    generate_thumbnails: true,
  });

  useEffect(() => {
    adminSettings.getImageSettings()
      .then(res => {
        if (res.data) {
          setFormData(prev => ({ ...prev, ...res.data }));
        }
      })
      .catch(() => toast.error(t('system.failed_to_load_image_settings')))
      .finally(() => setLoading(false));
  // eslint-disable-next-line react-hooks/exhaustive-deps -- load once on mount
  }, []);

  const handleSave = async () => {
    setSaving(true);
    try {
      const res = await adminSettings.updateImageSettings(formData);
      if (res.success) {
        toast.success(t('system.image_settings_saved_successfully'));
      } else {
        toast.error(res.error || t('system.failed_to_save_image_settings'));
      }
    } catch {
      toast.error(t('system.failed_to_save_image_settings'));
    } finally {
      setSaving(false);
    }
  };

  const updateField = (key: string, value: unknown) => {
    setFormData(prev => ({ ...prev, [key]: value }));
  };

  if (loading) {
    return (
      <div role="status" aria-busy="true" aria-label={t('common.loading')} className="flex h-64 items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div>
      <PageHeader title={t('system.image_settings_title')} description={t('system.image_settings_desc')} />

      <div className="space-y-4">
        <Card >
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Image size={20} /> {t('system.upload_limits')}</h3></CardHeader>
          <CardBody className="gap-4">
            <Input
              label={t('system.label_max_file_size')}
              type="number"
              variant="secondary"
              value={String(formData.max_file_size || '')}
              onValueChange={(v) => updateField('max_file_size', v)}
            />
            <Input
              label={t('system.label_max_width')}
              type="number"
              variant="secondary"
              value={String(formData.max_width || '')}
              onValueChange={(v) => updateField('max_width', v)}
            />
            <Input
              label={t('system.label_max_height')}
              type="number"
              variant="secondary"
              value={String(formData.max_height || '')}
              onValueChange={(v) => updateField('max_height', v)}
            />
            <Input
              label={t('system.label_allowed_formats')}
              variant="secondary"
              isReadOnly
              value={String(formData.allowed_formats || 'jpg, jpeg, png, gif, webp')}
            />
          </CardBody>
        </Card>

        <Card >
          <CardHeader><h3 className="text-lg font-semibold">{t('system.processing_heading')}</h3></CardHeader>
          <CardBody className="space-y-3">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('system.auto_resize')}</p>
                <p className="text-sm text-muted">{t('system.auto_resize_desc')}</p>
              </div>
              <Switch isSelected={!!formData.auto_resize} onValueChange={(v) => updateField('auto_resize', v)} aria-label={t('system.auto_resize')} />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('system.auto_convert_webp')}</p>
                <p className="text-sm text-muted">{t('system.auto_convert_webp_desc')}</p>
              </div>
              <Switch isSelected={!!formData.auto_webp} onValueChange={(v) => updateField('auto_webp', v)} aria-label={t('system.label_auto_web_p')} />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('system.strip_exif')}</p>
                <p className="text-sm text-muted">{t('system.strip_exif_desc')}</p>
              </div>
              <Switch isSelected={!!formData.strip_exif} onValueChange={(v) => updateField('strip_exif', v)} aria-label={t('system.label_strip_e_x_i_f')} />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('system.generate_thumbnails')}</p>
                <p className="text-sm text-muted">{t('system.generate_thumbnails_desc')}</p>
              </div>
              <Switch isSelected={!!formData.generate_thumbnails} onValueChange={(v) => updateField('generate_thumbnails', v)} aria-label={t('system.label_thumbnails')} />
            </div>
          </CardBody>
        </Card>

        <div className="flex justify-end">
          <Button startContent={<Save size={16} />} onPress={handleSave} isLoading={saving}>{t('system.save_settings')}</Button>
        </div>
      </div>
    </div>
  );
}

export default ImageSettings;
