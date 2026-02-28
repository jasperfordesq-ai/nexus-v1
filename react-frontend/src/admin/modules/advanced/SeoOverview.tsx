// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SEO Overview
 * Dashboard for search engine optimization metrics and configuration.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Switch, Input, Button, Spinner } from '@heroui/react';
import { Save } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSettings } from '../../api/adminApi';

// Keys that map to the backend's seo_* tenant_settings rows
const SEO_KEYS = [
  'seo_title_suffix', 'seo_meta_description', 'seo_meta_keywords',
  'seo_auto_sitemap', 'seo_canonical_urls', 'seo_open_graph',
  'seo_twitter_cards', 'seo_robots_txt', 'seo_google_verification', 'seo_bing_verification',
] as const;

// Keys stored directly on the tenants table
const TENANT_KEYS = [
  'tenant_meta_title', 'tenant_meta_description', 'tenant_h1_headline', 'tenant_hero_intro',
] as const;

export function SeoOverview() {
  usePageTitle('Admin - SEO Overview');
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [formData, setFormData] = useState<Record<string, unknown>>({
    seo_title_suffix: '',
    seo_meta_description: '',
    seo_meta_keywords: '',
    seo_auto_sitemap: true,
    seo_canonical_urls: true,
    seo_open_graph: true,
    seo_twitter_cards: true,
    seo_robots_txt: '',
    seo_google_verification: '',
    seo_bing_verification: '',
    tenant_meta_title: '',
    tenant_meta_description: '',
    tenant_h1_headline: '',
    tenant_hero_intro: '',
  });

  useEffect(() => {
    adminSettings.getSeoSettings()
      .then(res => {
        if (res.data) {
          // API returns { tenant_id, seo: { seo_title_suffix: ..., tenant_meta_title: ... } }
          const raw = res.data as Record<string, unknown>;
          const seo = (raw.seo ?? raw) as Record<string, unknown>;
          setFormData(prev => ({ ...prev, ...seo }));
        }
      })
      .catch(() => toast.error('Failed to load SEO settings'))
      .finally(() => setLoading(false));
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const handleSave = async () => {
    setSaving(true);
    try {
      // Only send recognized keys to the backend
      const payload: Record<string, unknown> = {};
      for (const key of [...SEO_KEYS, ...TENANT_KEYS]) {
        if (key in formData) payload[key] = formData[key];
      }

      const res = await adminSettings.updateSeoSettings(payload);

      if (res.success) {
        toast.success('SEO settings saved successfully');
      } else {
        const error = (res as { error?: string }).error || 'Save failed';
        toast.error(error);
      }
    } catch (err) {
      toast.error('Failed to save SEO settings');
      console.error('SEO settings save error:', err);
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

      <div className="space-y-4">
        {/* Tenant-level meta (stored directly on tenants table) */}
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Tenant Meta</h3></CardHeader>
          <CardBody className="gap-4">
            <Input
              label="Meta Title"
              placeholder="Your Timebank Name"
              variant="bordered"
              value={String(formData.tenant_meta_title || '')}
              onValueChange={(v) => updateField('tenant_meta_title', v)}
            />
            <Input
              label="Meta Description"
              placeholder="A short description of your timebank..."
              variant="bordered"
              value={String(formData.tenant_meta_description || '')}
              onValueChange={(v) => updateField('tenant_meta_description', v)}
            />
            <Input
              label="H1 Headline"
              placeholder="Welcome to our community"
              variant="bordered"
              value={String(formData.tenant_h1_headline || '')}
              onValueChange={(v) => updateField('tenant_h1_headline', v)}
            />
            <Input
              label="Hero Intro Text"
              placeholder="Exchange skills and time with your community"
              variant="bordered"
              value={String(formData.tenant_hero_intro || '')}
              onValueChange={(v) => updateField('tenant_hero_intro', v)}
            />
          </CardBody>
        </Card>

        {/* Global SEO settings (stored in tenant_settings) */}
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Meta Tags</h3></CardHeader>
          <CardBody className="gap-4">
            <Input
              label="Default Title Suffix"
              placeholder=" | My Timebank"
              variant="bordered"
              value={String(formData.seo_title_suffix || '')}
              onValueChange={(v) => updateField('seo_title_suffix', v)}
            />
            <Input
              label="Global Meta Description"
              placeholder="Community timebanking platform..."
              variant="bordered"
              value={String(formData.seo_meta_description || '')}
              onValueChange={(v) => updateField('seo_meta_description', v)}
            />
            <Input
              label="Meta Keywords"
              placeholder="timebanking, community, exchange"
              variant="bordered"
              value={String(formData.seo_meta_keywords || '')}
              onValueChange={(v) => updateField('seo_meta_keywords', v)}
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
              <Switch isSelected={!!formData.seo_auto_sitemap} onValueChange={(v) => updateField('seo_auto_sitemap', v)} aria-label="Auto sitemap" />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Canonical URLs</p>
                <p className="text-sm text-default-500">Add canonical URL tags to prevent duplicate content</p>
              </div>
              <Switch isSelected={!!formData.seo_canonical_urls} onValueChange={(v) => updateField('seo_canonical_urls', v)} aria-label="Canonical URLs" />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Open Graph Tags</p>
                <p className="text-sm text-default-500">Add Open Graph meta tags for social sharing</p>
              </div>
              <Switch isSelected={!!formData.seo_open_graph} onValueChange={(v) => updateField('seo_open_graph', v)} aria-label="Open Graph" />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Twitter Cards</p>
                <p className="text-sm text-default-500">Add Twitter Card meta tags for sharing on X/Twitter</p>
              </div>
              <Switch isSelected={!!formData.seo_twitter_cards} onValueChange={(v) => updateField('seo_twitter_cards', v)} aria-label="Twitter Cards" />
            </div>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Verification &amp; Robots</h3></CardHeader>
          <CardBody className="gap-4">
            <Input
              label="Google Search Console Verification"
              placeholder="google-site-verification=..."
              variant="bordered"
              value={String(formData.seo_google_verification || '')}
              onValueChange={(v) => updateField('seo_google_verification', v)}
            />
            <Input
              label="Bing Webmaster Verification"
              placeholder="msvalidate.01=..."
              variant="bordered"
              value={String(formData.seo_bing_verification || '')}
              onValueChange={(v) => updateField('seo_bing_verification', v)}
            />
            <Input
              label="Custom robots.txt Content"
              placeholder="User-agent: *&#10;Disallow: /admin/"
              variant="bordered"
              value={String(formData.seo_robots_txt || '')}
              onValueChange={(v) => updateField('seo_robots_txt', v)}
            />
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
