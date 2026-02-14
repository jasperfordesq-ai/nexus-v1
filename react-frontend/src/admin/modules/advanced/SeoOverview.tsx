/**
 * SEO Overview
 * Dashboard for search engine optimization metrics and configuration.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Switch, Input, Button, Spinner } from '@heroui/react';
import { Search, Globe, FileText, Save } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader, StatCard } from '../../components';
import { adminSettings } from '../../api/adminApi';

export function SeoOverview() {
  usePageTitle('Admin - SEO Overview');
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [formData, setFormData] = useState<Record<string, unknown>>({
    title_suffix: '',
    meta_description: '',
    meta_keywords: '',
    auto_sitemap: true,
    canonical_urls: true,
    open_graph: true,
    indexed_pages: '--',
    sitemap_urls: '--',
    errors_30d: '--',
    redirects: '--',
  });

  useEffect(() => {
    adminSettings.getSeoSettings()
      .then(res => {
        if (res.data) {
          setFormData(prev => ({ ...prev, ...res.data }));
        }
      })
      .catch(() => toast.error('Failed to load SEO settings'))
      .finally(() => setLoading(false));
  }, []);

  const handleSave = async () => {
    setSaving(true);
    try {
      await adminSettings.updateSeoSettings(formData);
      toast.success('SEO settings saved successfully');
    } catch {
      toast.error('Failed to save SEO settings');
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
      <PageHeader title="SEO Overview" description="Search engine optimization configuration and metrics" />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard label="Indexed Pages" value={String(formData.indexed_pages ?? '--')} icon={FileText} color="primary" />
        <StatCard label="Sitemap URLs" value={String(formData.sitemap_urls ?? '--')} icon={Globe} color="success" />
        <StatCard label="404 Errors (30d)" value={String(formData.errors_30d ?? '--')} icon={Search} color="warning" />
        <StatCard label="Redirects" value={String(formData.redirects ?? '--')} icon={Globe} color="secondary" />
      </div>

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Meta Tags</h3></CardHeader>
          <CardBody className="gap-4">
            <Input
              label="Default Title Suffix"
              placeholder=" - Project NEXUS"
              variant="bordered"
              value={String(formData.title_suffix || '')}
              onValueChange={(v) => updateField('title_suffix', v)}
            />
            <Input
              label="Meta Description"
              placeholder="Community timebanking platform..."
              variant="bordered"
              value={String(formData.meta_description || '')}
              onValueChange={(v) => updateField('meta_description', v)}
            />
            <Input
              label="Meta Keywords"
              placeholder="timebanking, community, exchange"
              variant="bordered"
              value={String(formData.meta_keywords || '')}
              onValueChange={(v) => updateField('meta_keywords', v)}
            />
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">SEO Features</h3></CardHeader>
          <CardBody className="space-y-3">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Auto-Generate Sitemap</p>
                <p className="text-sm text-default-500">Automatically generate and update sitemap.xml</p>
              </div>
              <Switch isSelected={!!formData.auto_sitemap} onValueChange={(v) => updateField('auto_sitemap', v)} aria-label="Auto sitemap" />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Canonical URLs</p>
                <p className="text-sm text-default-500">Add canonical URL tags to prevent duplicate content</p>
              </div>
              <Switch isSelected={!!formData.canonical_urls} onValueChange={(v) => updateField('canonical_urls', v)} aria-label="Canonical URLs" />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Open Graph Tags</p>
                <p className="text-sm text-default-500">Add Open Graph meta tags for social sharing</p>
              </div>
              <Switch isSelected={!!formData.open_graph} onValueChange={(v) => updateField('open_graph', v)} aria-label="Open Graph" />
            </div>
          </CardBody>
        </Card>

        <div className="flex justify-end">
          <Button color="primary" startContent={<Save size={16} />} onPress={handleSave} isLoading={saving}>Save SEO Settings</Button>
        </div>
      </div>
    </div>
  );
}

export default SeoOverview;
