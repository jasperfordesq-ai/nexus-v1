// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Page Builder
 * Create or edit a CMS page with real API integration.
 * Wired to adminPages.get/create/update.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Input, Textarea, Select, SelectItem, Button, Spinner } from '@heroui/react';
import { FileText, ArrowLeft, Save } from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminPages } from '../../api/adminApi';
import { PageHeader } from '../../components';

interface PageFormData {
  title: string;
  slug: string;
  content: string;
  meta_description: string;
  status: string;
}

export function PageBuilder() {
  const { id } = useParams<{ id: string }>();
  const isEdit = id !== undefined && id !== 'new';
  usePageTitle(`Admin - ${isEdit ? 'Edit' : 'Create'} Page`);
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [formData, setFormData] = useState<PageFormData>({
    title: '',
    slug: '',
    content: '',
    meta_description: '',
    status: 'draft',
  });
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (isEdit) {
      adminPages.get(Number(id))
        .then((res) => {
          if (res.success && res.data) {
            const page = res.data as Record<string, unknown>;
            setFormData({
              title: (page.title as string) || '',
              slug: (page.slug as string) || '',
              content: (page.content as string) || '',
              meta_description: (page.meta_description as string) || '',
              status: (page.status as string) || 'draft',
            });
          }
        })
        .catch(() => toast.error('Failed to load page'))
        .finally(() => setLoading(false));
    }
  }, [id, isEdit]);

  const handleChange = (field: keyof PageFormData, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleSave = async () => {
    if (!formData.title.trim()) {
      toast.warning('Page title is required');
      return;
    }
    setSaving(true);
    try {
      if (isEdit) {
        const res = await adminPages.update(Number(id), formData as unknown as Record<string, unknown>);
        if (res?.success) {
          toast.success('Page updated successfully');
          navigate(tenantPath('/admin/pages'));
        } else {
          toast.error('Failed to update page');
        }
      } else {
        const res = await adminPages.create(formData);
        if (res?.success) {
          toast.success('Page created successfully');
          navigate(tenantPath('/admin/pages'));
        } else {
          toast.error('Failed to create page');
        }
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div>
        <PageHeader title={isEdit ? 'Edit Page' : 'Page Builder'} description="Loading page..." />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={isEdit ? 'Edit Page' : 'Page Builder'}
        description="Create or edit a CMS page"
        actions={<Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/pages'))}>Back</Button>}
      />

      <Card shadow="sm">
        <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><FileText size={20} /> Page Content</h3></CardHeader>
        <CardBody className="gap-4">
          <Input
            label="Page Title"
            placeholder="e.g., About Us"
            isRequired
            variant="bordered"
            value={formData.title}
            onValueChange={(v) => handleChange('title', v)}
          />
          <Input
            label="URL Slug"
            placeholder="e.g., about-us"
            variant="bordered"
            description="The URL path for this page"
            value={formData.slug}
            onValueChange={(v) => handleChange('slug', v)}
          />
          <Textarea
            label="Page Content"
            placeholder="Write your page content here..."
            variant="bordered"
            minRows={10}
            value={formData.content}
            onValueChange={(v) => handleChange('content', v)}
          />
          <Input
            label="Meta Description"
            placeholder="SEO meta description..."
            variant="bordered"
            value={formData.meta_description}
            onValueChange={(v) => handleChange('meta_description', v)}
          />
          <Select
            label="Status"
            variant="bordered"
            selectedKeys={[formData.status]}
            onSelectionChange={(keys) => {
              const selected = Array.from(keys)[0] as string;
              if (selected) handleChange('status', selected);
            }}
          >
            <SelectItem key="draft">Draft</SelectItem>
            <SelectItem key="published">Published</SelectItem>
          </Select>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="flat" onPress={() => navigate(tenantPath('/admin/pages'))}>Cancel</Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
            >
              {isEdit ? 'Update' : 'Save'} Page
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default PageBuilder;
