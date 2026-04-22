// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Page Builder
 * Create or edit a CMS page with real API integration.
 * Wired to adminPages.get/create/update.
 */

import { useState, useEffect, lazy, Suspense } from 'react';
import { Card, CardBody, CardHeader, Input, Select, SelectItem, Button, Spinner, Switch } from '@heroui/react';
import FileText from 'lucide-react/icons/file-text';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Save from 'lucide-react/icons/save';
import Menu from 'lucide-react/icons/menu';
import ExternalLink from 'lucide-react/icons/external-link';
import { useNavigate, useParams } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast, useAuth } from '@/contexts';
import { adminPages } from '../../api/adminApi';
import { PageHeader } from '../../components';
import { useTranslation } from 'react-i18next';

const RichTextEditor = lazy(() =>
  import('../../components/RichTextEditor').then((m) => ({ default: m.RichTextEditor })),
);

interface PageFormData {
  title: string;
  slug: string;
  content: string;
  meta_description: string;
  status: string;
  show_in_menu: boolean;
  menu_location: string;
  menu_order: number;
}

export function PageBuilder() {
  const { t } = useTranslation('admin');
  const { id } = useParams<{ id: string }>();
  const isEdit = id !== undefined && id !== 'new';
  usePageTitle(`Admin - ${isEdit ? "Edit" : "Create"} ${"Pages"}`);
  const navigate = useNavigate();
  const { tenantPath, tenant, refreshTenant } = useTenant();
  const { user } = useAuth();
  const toast = useToast();

  const [formData, setFormData] = useState<PageFormData>({
    title: '',
    slug: '',
    content: '',
    meta_description: '',
    status: 'draft',
    show_in_menu: false,
    menu_location: 'about',
    menu_order: 0,
  });
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [slugTouched, setSlugTouched] = useState(isEdit);

  const toSlug = (text: string): string =>
    text
      .toLowerCase()
      .trim()
      .replace(/[^\w\s-]/g, '')
      .replace(/[\s_]+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '');

  // Slugs that conflict with built-in React routes — cannot be used as page slugs
  const RESERVED_SLUGS = new Set([
    'login', 'register', 'password', 'logout', 'dashboard', 'listings',
    'events', 'groups', 'messages', 'notifications', 'wallet', 'feed',
    'search', 'members', 'profile', 'settings', 'exchanges', 'achievements',
    'leaderboard', 'goals', 'volunteering', 'blog', 'resources',
    'organisations', 'federation', 'onboarding', 'group-exchanges', 'matches', 'newsletter',
    'help', 'contact', 'about', 'faq', 'legal', 'terms',
    'privacy', 'accessibility', 'cookies', 'development-status',
    'timebanking-guide', 'partner', 'social-prescribing', 'impact-summary', 'impact-report', 'strategic-plan',
    'admin', 'admin-legacy', 'super-admin', 'api', 'assets',
    'uploads', 'classic', 'health', 'page',
  ]);
  const isReservedSlug = RESERVED_SLUGS.has(formData.slug.toLowerCase());

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
              show_in_menu: !!(page.show_in_menu),
              menu_location: (page.menu_location as string) || 'about',
              menu_order: Number(page.menu_order) || 0,
            });
          }
        })
        .catch(() => toast.error("Failed to load pages"))
        .finally(() => setLoading(false));
    }
  }, [id, isEdit, toast])


  const handleChange = (field: keyof PageFormData, value: string | boolean | number) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleSave = async () => {
    if (!formData.title.trim()) {
      toast.warning(t('content.page_title_required', 'Page title is required'));
      return;
    }
    if (!formData.slug.trim() || formData.slug !== toSlug(formData.slug)) {
      toast.warning(t('content.slug_must_be_url_safe', 'Slug must be URL-safe (lowercase letters, numbers, and hyphens only)'));
      return;
    }
    if (isReservedSlug) {
      toast.warning(t('content.slug_reserved', `The slug "${formData.slug}" is reserved by the system. Please choose a different slug.`));
      return;
    }
    setSaving(true);
    try {
      const payload = {
        ...formData,
        show_in_menu: formData.show_in_menu ? 1 : 0,
      };
      if (isEdit) {
        const res = await adminPages.update(Number(id), payload as Record<string, unknown>);
        if (res?.success) {
          toast.success(t('content.page_updated', 'Page updated successfully'));
          refreshTenant();
          navigate(tenantPath('/admin/pages'));
        } else {
          toast.error(res?.error || "An unexpected error occurred");
        }
      } else {
        const res = await adminPages.create(payload);
        if (res?.success) {
          toast.success(t('content.page_created', 'Page created successfully'));
          refreshTenant();
          navigate(tenantPath('/admin/pages'));
        } else {
          toast.error(res?.error || "An unexpected error occurred");
        }
      }
    } catch {
      toast.error("An unexpected error occurred");
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div>
        <PageHeader title={isEdit ? `${"Edit"} ${"Pages"}` : "Pages Admin"} description={"Loading federation..."} />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={isEdit ? `${"Edit"} ${"Pages"}` : "Pages Admin"}
        description={"Create and manage custom pages for your platform"}
        actions={
          <div className="flex gap-2">
            <Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/pages'))}>{"Back"}</Button>
            {isEdit && formData.slug && (
              <Button
                variant="flat"
                color="primary"
                startContent={<ExternalLink size={16} />}
                onPress={() => {
                  // Use tenant.slug (from TenantContext) or user.tenant_slug (from auth JWT)
                  // so the preview URL always includes the tenant prefix, even when the admin
                  // panel is accessed directly via /admin without a tenant slug in the URL.
                  const slug = tenant?.slug || user?.tenant_slug;
                  const previewUrl = slug
                    ? `/${slug}/page/${formData.slug}`
                    : `/page/${formData.slug}`;
                  window.open(previewUrl, '_blank');
                }}
              >
                {t('content.preview', 'Preview')}
              </Button>
            )}
          </div>
        }
      />

      <div className="flex flex-col gap-4">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><FileText size={20} /> {"Content"}</h3></CardHeader>
          <CardBody className="gap-4">
            <Input
              label={"Name"}
              placeholder={t('content.placeholder_eg_skill_level', 'e.g., About Us')}
              isRequired
              variant="bordered"
              value={formData.title}
              onValueChange={(v) => {
                handleChange('title', v);
                if (!slugTouched) handleChange('slug', toSlug(v));
              }}
            />
            <Input
              label={t('content.label_url_slug', 'URL Slug')}
              placeholder={t('content.placeholder_slug', 'e.g., about-us')}
              variant="bordered"
              description={isReservedSlug ? undefined : t('content.slug_description', 'The URL path for this page (e.g. /page/about-us). Auto-generated from title.')}
              isInvalid={isReservedSlug}
              errorMessage={isReservedSlug ? t('content.slug_reserved_error', `"${formData.slug}" is a reserved system route. Choose a different slug.`) : undefined}
              value={formData.slug}
              onValueChange={(v) => {
                setSlugTouched(true);
                handleChange('slug', toSlug(v));
              }}
            />
            <Suspense fallback={<Spinner size="sm" className="m-4" />}>
              <RichTextEditor
                label={"Content"}
                placeholder={t('content.placeholder_content', 'Write your page content here...')}
                value={formData.content}
                onChange={(html) => handleChange('content', html)}
                isDisabled={saving}
              />
            </Suspense>
            <Input
              label={t('content.label_meta_description', 'Meta Description')}
              placeholder={t('content.placeholder_meta_description', 'SEO meta description...')}
              variant="bordered"
              value={formData.meta_description}
              onValueChange={(v) => handleChange('meta_description', v)}
            />
            <Select
              label={"Status"}
              variant="bordered"
              selectedKeys={[formData.status]}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0] as string;
                if (selected) handleChange('status', selected);
              }}
            >
              <SelectItem key="draft">{t('content.draft', 'Draft')}</SelectItem>
              <SelectItem key="published">{t('content.published', 'Published')}</SelectItem>
            </Select>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Menu size={20} /> {t('content.navigation_settings', 'Navigation Settings')}</h3></CardHeader>
          <CardBody className="gap-4">
            <Switch
              isSelected={formData.show_in_menu}
              onValueChange={(v) => handleChange('show_in_menu', v)}
            >
              {t('content.show_in_menu', 'Show in navigation menu')}
            </Switch>
            {formData.show_in_menu && (
              <>
                <Select
                  label={"Location"}
                  variant="bordered"
                  description={t('content.menu_location_desc', 'Where this page appears in the navigation')}
                  selectedKeys={[formData.menu_location]}
                  onSelectionChange={(keys) => {
                    const selected = Array.from(keys)[0] as string;
                    if (selected) handleChange('menu_location', selected);
                  }}
                >
                  <SelectItem key="about">{t('content.about_section', 'About section (More dropdown)')}</SelectItem>
                  <SelectItem key="footer">{t('content.footer', 'Footer')}</SelectItem>
                </Select>
                <Input
                  type="number"
                  label={t('content.menu_order', 'Menu Order')}
                  variant="bordered"
                  description={t('content.menu_order_desc', 'Lower numbers appear first (0 = default)')}
                  value={String(formData.menu_order)}
                  onValueChange={(v) => handleChange('menu_order', parseInt(v, 10) || 0)}
                />
              </>
            )}
          </CardBody>
        </Card>

        <div className="flex justify-end gap-2">
          <Button variant="flat" onPress={() => navigate(tenantPath('/admin/pages'))}>{"Cancel"}</Button>
          <Button
            color="primary"
            startContent={<Save size={16} />}
            onPress={handleSave}
            isLoading={saving}
          >
            {isEdit ? "Save Changes" : `${"Create"} ${"Pages"}`}
          </Button>
        </div>
      </div>
    </div>
  );
}

export default PageBuilder;
