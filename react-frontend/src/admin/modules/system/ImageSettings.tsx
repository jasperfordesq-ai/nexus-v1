/**
 * Image Settings
 * Configure image upload limits, dimensions, and processing options.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Input, Switch, Button, Spinner } from '@heroui/react';
import { Image, Save } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSettings } from '../../api/adminApi';

export function ImageSettings() {
  usePageTitle('Admin - Image Settings');
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
      .catch(() => toast.error('Failed to load image settings'))
      .finally(() => setLoading(false));
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleSave = async () => {
    setSaving(true);
    try {
      await adminSettings.updateImageSettings(formData);
      toast.success('Image settings saved successfully');
    } catch {
      toast.error('Failed to save image settings');
    } finally {
      setSaving(false);
    }
  };

  const updateField = (key: string, value: unknown) => {
    setFormData(prev => ({ ...prev, [key]: value }));
  };

  if (loading) {
    return (
      <div className="flex h-64 items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div>
      <PageHeader title="Image Settings" description="Configure image upload and processing options" />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Image size={20} /> Upload Limits</h3></CardHeader>
          <CardBody className="gap-4">
            <Input
              label="Max File Size (MB)"
              type="number"
              variant="bordered"
              value={String(formData.max_file_size || '')}
              onValueChange={(v) => updateField('max_file_size', v)}
            />
            <Input
              label="Max Width (px)"
              type="number"
              variant="bordered"
              value={String(formData.max_width || '')}
              onValueChange={(v) => updateField('max_width', v)}
            />
            <Input
              label="Max Height (px)"
              type="number"
              variant="bordered"
              value={String(formData.max_height || '')}
              onValueChange={(v) => updateField('max_height', v)}
            />
            <Input
              label="Allowed Formats"
              variant="bordered"
              isReadOnly
              value={String(formData.allowed_formats || 'jpg, jpeg, png, gif, webp')}
            />
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Processing</h3></CardHeader>
          <CardBody className="space-y-3">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Auto-Resize</p>
                <p className="text-sm text-default-500">Automatically resize images exceeding max dimensions</p>
              </div>
              <Switch isSelected={!!formData.auto_resize} onValueChange={(v) => updateField('auto_resize', v)} aria-label="Auto resize" />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Auto-Convert to WebP</p>
                <p className="text-sm text-default-500">Convert uploaded images to WebP format</p>
              </div>
              <Switch isSelected={!!formData.auto_webp} onValueChange={(v) => updateField('auto_webp', v)} aria-label="Auto WebP" />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Strip EXIF Data</p>
                <p className="text-sm text-default-500">Remove metadata from uploaded images for privacy</p>
              </div>
              <Switch isSelected={!!formData.strip_exif} onValueChange={(v) => updateField('strip_exif', v)} aria-label="Strip EXIF" />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Generate Thumbnails</p>
                <p className="text-sm text-default-500">Create thumbnail versions for listing cards</p>
              </div>
              <Switch isSelected={!!formData.generate_thumbnails} onValueChange={(v) => updateField('generate_thumbnails', v)} aria-label="Thumbnails" />
            </div>
          </CardBody>
        </Card>

        <div className="flex justify-end">
          <Button color="primary" startContent={<Save size={16} />} onPress={handleSave} isLoading={saving}>Save Settings</Button>
        </div>
      </div>
    </div>
  );
}

export default ImageSettings;
