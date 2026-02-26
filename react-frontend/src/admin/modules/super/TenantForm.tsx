// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tenant Create/Edit Form
 * Multi-tab form for tenant management: Details, Contact, SEO, Location, Social, Features.
 */

import { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import {
  Card,
  CardBody,
  Button,
  Input,
  Textarea,
  Switch,
  Tabs,
  Tab,
  Select,
  SelectItem,
  Spinner,
  Checkbox,
} from '@heroui/react';
import { Building2, Save, ArrowLeft } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { SuperAdminTenant, SuperAdminTenantDetail } from '../../api/types';

const FEATURE_OPTIONS = [
  'events', 'groups', 'gamification', 'goals', 'blog', 'resources',
  'volunteering', 'exchange_workflow', 'federation', 'organisations',
  'listings', 'wallet', 'messages', 'dashboard', 'feed',
];

const COUNTRY_CODES = [
  { code: 'IE', label: 'Ireland' },
  { code: 'GB', label: 'United Kingdom' },
  { code: 'US', label: 'United States' },
  { code: 'CA', label: 'Canada' },
  { code: 'AU', label: 'Australia' },
  { code: 'NZ', label: 'New Zealand' },
  { code: 'DE', label: 'Germany' },
  { code: 'FR', label: 'France' },
  { code: 'ES', label: 'Spain' },
  { code: 'IT', label: 'Italy' },
  { code: 'NL', label: 'Netherlands' },
  { code: 'BE', label: 'Belgium' },
  { code: 'PT', label: 'Portugal' },
  { code: 'SE', label: 'Sweden' },
  { code: 'NO', label: 'Norway' },
  { code: 'DK', label: 'Denmark' },
  { code: 'FI', label: 'Finland' },
  { code: 'PL', label: 'Poland' },
  { code: 'AT', label: 'Austria' },
  { code: 'CH', label: 'Switzerland' },
];

const SERVICE_AREAS = [
  { key: 'local', label: 'Local' },
  { key: 'regional', label: 'Regional' },
  { key: 'national', label: 'National' },
  { key: 'international', label: 'International' },
];

const PLATFORM_LANGUAGES = [
  { code: 'en', label: 'English', short: 'EN' },
  { code: 'ga', label: 'Gaeilge', short: 'GA' },
  { code: 'de', label: 'Deutsch', short: 'DE' },
  { code: 'fr', label: 'Français', short: 'FR' },
  { code: 'it', label: 'Italiano', short: 'IT' },
];

export function TenantForm() {
  const { id } = useParams<{ id: string }>();
  const isEdit = !!id;
  usePageTitle(isEdit ? 'Super Admin - Edit Tenant' : 'Super Admin - Create Tenant');
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [parentTenants, setParentTenants] = useState<SuperAdminTenant[]>([]);

  // Form state
  const [form, setForm] = useState({
    name: '',
    slug: '',
    domain: '',
    tagline: '',
    description: '',
    is_active: true,
    allows_subtenants: false,
    max_depth: 3,
    parent_id: '' as string,
    // Contact
    contact_email: '',
    contact_phone: '',
    address: '',
    // SEO
    meta_title: '',
    meta_description: '',
    h1_headline: '',
    hero_intro: '',
    og_image_url: '',
    robots_directive: '',
    // Location
    location_name: '',
    country_code: '',
    service_area: '',
    latitude: '',
    longitude: '',
    // Social
    social_facebook: '',
    social_twitter: '',
    social_instagram: '',
    social_linkedin: '',
    social_youtube: '',
    // Features
    features: {} as Record<string, boolean>,
    // Languages
    default_language: 'en',
    supported_languages: ['en', 'ga', 'de', 'fr', 'it'] as string[],
  });

  const updateField = (field: string, value: unknown) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  const loadTenant = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    try {
      const res = await adminSuper.getTenant(Number(id));
      if (res.success && res.data) {
        const tenant = res.data as SuperAdminTenantDetail;
        setForm({
          name: tenant.name || '',
          slug: tenant.slug || '',
          domain: tenant.domain || '',
          tagline: tenant.tagline || '',
          description: tenant.description || '',
          is_active: tenant.is_active ?? true,
          allows_subtenants: tenant.allows_subtenants ?? false,
          max_depth: tenant.max_depth ?? 3,
          parent_id: tenant.parent_id ? String(tenant.parent_id) : '',
          contact_email: tenant.contact_email || '',
          contact_phone: tenant.contact_phone || '',
          address: tenant.address || '',
          meta_title: tenant.meta_title || '',
          meta_description: tenant.meta_description || '',
          h1_headline: tenant.h1_headline || '',
          hero_intro: tenant.hero_intro || '',
          og_image_url: tenant.og_image_url || '',
          robots_directive: tenant.robots_directive || '',
          location_name: tenant.location_name || '',
          country_code: tenant.country_code || '',
          service_area: tenant.service_area || '',
          latitude: tenant.latitude || '',
          longitude: tenant.longitude || '',
          social_facebook: tenant.social_facebook || '',
          social_twitter: tenant.social_twitter || '',
          social_instagram: tenant.social_instagram || '',
          social_linkedin: tenant.social_linkedin || '',
          social_youtube: tenant.social_youtube || '',
          features: tenant.features || {},
          default_language: (tenant.configuration as Record<string, unknown>)?.default_language as string || 'en',
          supported_languages: (tenant.configuration as Record<string, unknown>)?.supported_languages as string[] || ['en'],
        });
      }
    } catch {
      toast.error('Failed to load tenant');
    }
    setLoading(false);
  }, [id, toast]);

  const loadParentTenants = useCallback(async () => {
    try {
      const res = await adminSuper.listTenants({ hub: true });
      if (res.success && res.data) {
        setParentTenants(Array.isArray(res.data) ? res.data : []);
      }
    } catch {
      // Non-critical
    }
  }, []);

  useEffect(() => {
    loadParentTenants();
    if (isEdit) loadTenant();
  }, [isEdit, loadTenant, loadParentTenants]);

  const handleSubmit = async () => {
    if (!form.name.trim()) {
      toast.error('Tenant name is required');
      return;
    }
    if (!isEdit && !form.slug.trim()) {
      toast.error('Tenant slug is required');
      return;
    }

    setSaving(true);
    try {
      const payload: Record<string, unknown> = { ...form };
      if (form.parent_id) {
        payload.parent_id = Number(form.parent_id);
      } else {
        delete payload.parent_id;
      }

      // Merge language settings into configuration JSON
      const existingConfig = (payload.configuration ?? {}) as Record<string, unknown>;
      payload.configuration = {
        ...existingConfig,
        default_language: form.default_language,
        supported_languages: form.supported_languages,
      };
      delete payload.default_language;
      delete payload.supported_languages;

      let res;
      if (isEdit) {
        res = await adminSuper.updateTenant(Number(id), payload);
      } else {
        res = await adminSuper.createTenant(payload as never);
      }

      if (res.success) {
        toast.success(`Tenant ${isEdit ? 'updated' : 'created'} successfully`);
        if (isEdit) {
          navigate(tenantPath(`/admin/super/tenants/${id}`));
        } else {
          // Navigate to the new tenant's show page if ID is returned, otherwise go to list
          const newId = (res as { data?: { tenant_id?: number } }).data?.tenant_id;
          navigate(tenantPath(newId ? `/admin/super/tenants/${newId}` : '/admin/super/tenants'));
        }
      } else {
        toast.error(res.error || `Failed to ${isEdit ? 'update' : 'create'} tenant`);
      }
    } catch {
      toast.error('An error occurred');
    }
    setSaving(false);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div>
      <nav className="flex items-center gap-1 text-sm text-default-500 mb-1">
        <Link to={tenantPath('/admin/super')} className="hover:text-primary">Super Admin</Link>
        <span>/</span>
        <Link to={tenantPath('/admin/super/tenants')} className="hover:text-primary">Tenants</Link>
        <span>/</span>
        <span className="text-foreground">{isEdit ? 'Edit' : 'Create'}</span>
      </nav>
      <PageHeader
        title={isEdit ? `Edit Tenant: ${form.name}` : 'Create Tenant'}
        description={isEdit ? 'Update tenant configuration' : 'Set up a new community tenant'}
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath(isEdit ? `/admin/super/tenants/${id}` : '/admin/super/tenants'))}
            >
              Back
            </Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSubmit}
              isLoading={saving}
            >
              {isEdit ? 'Save Changes' : 'Create Tenant'}
            </Button>
          </div>
        }
      />

      <Tabs variant="underlined" className="mb-4">
        <Tab key="details" title="Details">
          <Card shadow="sm">
            <CardBody className="space-y-4 p-6">
              <Input
                label="Tenant Name"
                placeholder="My Community"
                value={form.name}
                onValueChange={(v) => updateField('name', v)}
                isRequired
                startContent={<Building2 size={16} className="text-default-400" />}
              />
              <Input
                label="Slug"
                placeholder="my-community"
                value={form.slug}
                onValueChange={(v) => updateField('slug', v)}
                isRequired={!isEdit}
                isDisabled={isEdit}
                description={isEdit ? 'Slug cannot be changed after creation' : 'URL-safe identifier'}
              />
              <Input
                label="Domain"
                placeholder="community.example.com"
                value={form.domain}
                onValueChange={(v) => updateField('domain', v)}
              />
              <Input
                label="Tagline"
                placeholder="A short tagline"
                value={form.tagline}
                onValueChange={(v) => updateField('tagline', v)}
              />
              <Textarea
                label="Description"
                placeholder="Describe this community..."
                value={form.description}
                onValueChange={(v) => updateField('description', v)}
                minRows={3}
              />
              <Select
                label="Parent Tenant"
                placeholder="None (top-level)"
                selectedKeys={form.parent_id ? [form.parent_id] : []}
                onSelectionChange={(keys) => {
                  const arr = Array.from(keys);
                  updateField('parent_id', arr.length > 0 ? String(arr[0]) : '');
                }}
              >
                {parentTenants
                  .filter((t) => String(t.id) !== id)
                  .map((t) => (
                    <SelectItem key={String(t.id)}>{t.name}</SelectItem>
                  ))}
              </Select>
              <div className="flex items-center gap-8">
                <Switch
                  isSelected={form.is_active}
                  onValueChange={(v) => updateField('is_active', v)}
                >
                  Active
                </Switch>
                <Switch
                  isSelected={form.allows_subtenants}
                  onValueChange={(v) => updateField('allows_subtenants', v)}
                >
                  Allows Sub-tenants (Hub)
                </Switch>
              </div>
              {form.allows_subtenants && (
                <Input
                  type="number"
                  label="Max Depth"
                  value={String(form.max_depth)}
                  onValueChange={(v) => updateField('max_depth', Number(v) || 3)}
                  className="max-w-xs"
                />
              )}
            </CardBody>
          </Card>
        </Tab>

        <Tab key="contact" title="Contact">
          <Card shadow="sm">
            <CardBody className="space-y-4 p-6">
              <Input
                label="Contact Email"
                type="email"
                placeholder="admin@example.com"
                value={form.contact_email}
                onValueChange={(v) => updateField('contact_email', v)}
              />
              <Input
                label="Contact Phone"
                placeholder="+1 555 123 4567"
                value={form.contact_phone}
                onValueChange={(v) => updateField('contact_phone', v)}
              />
              <Textarea
                label="Address"
                placeholder="Full address..."
                value={form.address}
                onValueChange={(v) => updateField('address', v)}
                minRows={2}
              />
            </CardBody>
          </Card>
        </Tab>

        <Tab key="seo" title="SEO">
          <Card shadow="sm">
            <CardBody className="space-y-4 p-6">
              <Input
                label="Meta Title"
                placeholder="Page title for search engines"
                value={form.meta_title}
                onValueChange={(v) => updateField('meta_title', v)}
              />
              <Textarea
                label="Meta Description"
                placeholder="Description for search engines..."
                value={form.meta_description}
                onValueChange={(v) => updateField('meta_description', v)}
                minRows={2}
              />
              <Input
                label="H1 Headline"
                placeholder="Main page heading"
                value={form.h1_headline}
                onValueChange={(v) => updateField('h1_headline', v)}
              />
              <Textarea
                label="Hero Introduction"
                placeholder="Introduction text for the hero section..."
                value={form.hero_intro}
                onValueChange={(v) => updateField('hero_intro', v)}
                minRows={2}
              />
              <Input
                label="OG Image URL"
                placeholder="https://example.com/image.jpg"
                value={form.og_image_url}
                onValueChange={(v) => updateField('og_image_url', v)}
              />
              <Input
                label="Robots Directive"
                placeholder="index, follow"
                value={form.robots_directive}
                onValueChange={(v) => updateField('robots_directive', v)}
              />
            </CardBody>
          </Card>
        </Tab>

        <Tab key="location" title="Location">
          <Card shadow="sm">
            <CardBody className="space-y-4 p-6">
              <Input
                label="Location Name"
                placeholder="City, Region"
                value={form.location_name}
                onValueChange={(v) => updateField('location_name', v)}
              />
              <Select
                label="Country"
                placeholder="Select country"
                selectedKeys={form.country_code ? [form.country_code] : []}
                onSelectionChange={(keys) => {
                  const arr = Array.from(keys);
                  updateField('country_code', arr.length > 0 ? String(arr[0]) : '');
                }}
                className="max-w-xs"
              >
                {COUNTRY_CODES.map((c) => (
                  <SelectItem key={c.code}>{c.label} ({c.code})</SelectItem>
                ))}
              </Select>
              <Select
                label="Service Area"
                placeholder="Select service area"
                selectedKeys={form.service_area ? [form.service_area] : []}
                onSelectionChange={(keys) => {
                  const arr = Array.from(keys);
                  updateField('service_area', arr.length > 0 ? String(arr[0]) : '');
                }}
                className="max-w-xs"
              >
                {SERVICE_AREAS.map((s) => (
                  <SelectItem key={s.key}>{s.label}</SelectItem>
                ))}
              </Select>
              <div className="grid grid-cols-2 gap-4">
                <Input
                  label="Latitude"
                  placeholder="53.3498"
                  value={form.latitude}
                  onValueChange={(v) => updateField('latitude', v)}
                />
                <Input
                  label="Longitude"
                  placeholder="-6.2603"
                  value={form.longitude}
                  onValueChange={(v) => updateField('longitude', v)}
                />
              </div>
            </CardBody>
          </Card>
        </Tab>

        <Tab key="social" title="Social">
          <Card shadow="sm">
            <CardBody className="space-y-4 p-6">
              <Input
                label="Facebook"
                placeholder="https://facebook.com/..."
                value={form.social_facebook}
                onValueChange={(v) => updateField('social_facebook', v)}
              />
              <Input
                label="Twitter / X"
                placeholder="https://twitter.com/..."
                value={form.social_twitter}
                onValueChange={(v) => updateField('social_twitter', v)}
              />
              <Input
                label="Instagram"
                placeholder="https://instagram.com/..."
                value={form.social_instagram}
                onValueChange={(v) => updateField('social_instagram', v)}
              />
              <Input
                label="LinkedIn"
                placeholder="https://linkedin.com/..."
                value={form.social_linkedin}
                onValueChange={(v) => updateField('social_linkedin', v)}
              />
              <Input
                label="YouTube"
                placeholder="https://youtube.com/..."
                value={form.social_youtube}
                onValueChange={(v) => updateField('social_youtube', v)}
              />
            </CardBody>
          </Card>
        </Tab>

        <Tab key="languages" title="Languages">
          <Card shadow="sm">
            <CardBody className="space-y-4 p-6">
              <Select
                label="Default Language"
                description="Shown to new visitors without a preference"
                selectedKeys={[form.default_language]}
                onSelectionChange={(keys) => {
                  const val = Array.from(keys)[0] as string;
                  if (val) updateField('default_language', val);
                }}
                className="max-w-xs"
              >
                {PLATFORM_LANGUAGES.filter((l) =>
                  form.supported_languages.includes(l.code)
                ).map((lang) => (
                  <SelectItem key={lang.code}>
                    {lang.label} ({lang.short})
                  </SelectItem>
                ))}
              </Select>
              <div>
                <p className="text-sm font-medium mb-1">Available Languages</p>
                <p className="text-xs text-default-400 mb-3">
                  Languages shown in the language switcher
                </p>
                <div className="space-y-2">
                  {PLATFORM_LANGUAGES.map((lang) => (
                    <Checkbox
                      key={lang.code}
                      isSelected={form.supported_languages.includes(lang.code)}
                      isDisabled={lang.code === 'en'}
                      onValueChange={(checked) => {
                        const updated = checked
                          ? [...form.supported_languages, lang.code]
                          : form.supported_languages.filter((c) => c !== lang.code);
                        updateField('supported_languages', updated);
                        // Reset default to English if it was unchecked
                        if (!checked && form.default_language === lang.code) {
                          updateField('default_language', 'en');
                        }
                      }}
                    >
                      <span className="text-sm">
                        {lang.label} ({lang.short})
                        {lang.code === 'en' && (
                          <span className="ml-2 text-xs text-default-400">always enabled</span>
                        )}
                      </span>
                    </Checkbox>
                  ))}
                </div>
              </div>
            </CardBody>
          </Card>
        </Tab>

        <Tab key="features" title="Features">
          <Card shadow="sm">
            <CardBody className="p-6">
              <p className="text-sm text-default-500 mb-4">
                Toggle platform features for this tenant. Changes take effect immediately after save.
              </p>
              <div className="space-y-3">
                {FEATURE_OPTIONS.map((feature) => (
                  <div key={feature} className="flex items-center justify-between">
                    <span className="font-medium capitalize">
                      {feature.replace(/_/g, ' ')}
                    </span>
                    <Switch
                      isSelected={form.features[feature] ?? false}
                      onValueChange={(v) =>
                        updateField('features', { ...form.features, [feature]: v })
                      }
                      aria-label={`Toggle ${feature}`}
                    />
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>
        </Tab>
      </Tabs>
    </div>
  );
}

export default TenantForm;
