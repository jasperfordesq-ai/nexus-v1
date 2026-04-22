// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tenant Create/Edit Form
 * Multi-tab form for tenant management: Details, Contact, SEO, Location, Social, Languages, Features, Legal.
 */

import { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate, useSearchParams, Link } from 'react-router-dom';
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
import {
  Building2, Save, ArrowLeft, Calendar, Users, Trophy, Target, BookOpen, Library,
  Heart, ArrowRightLeft, Network, Building, ShoppingBag, Wallet, MessageCircle,
  LayoutDashboard, Rss, Eye,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { SuperAdminTenant, SuperAdminTenantDetail, CreateTenantPayload } from '../../api/types';

const FEATURE_META: { key: string; labelKey: string; descKey: string; icon: typeof Calendar }[] = [
  { key: 'listings', labelKey: 'tenant_form.feature_listings', descKey: 'tenant_form.feature_listings_desc', icon: ShoppingBag },
  { key: 'groups', labelKey: 'tenant_form.feature_groups', descKey: 'tenant_form.feature_groups_desc', icon: Users },
  { key: 'wallet', labelKey: 'tenant_form.feature_wallet', descKey: 'tenant_form.feature_wallet_desc', icon: Wallet },
  { key: 'events', labelKey: 'tenant_form.feature_events', descKey: 'tenant_form.feature_events_desc', icon: Calendar },
  { key: 'volunteering', labelKey: 'tenant_form.feature_volunteering', descKey: 'tenant_form.feature_volunteering_desc', icon: Heart },
  { key: 'resources', labelKey: 'tenant_form.feature_resources', descKey: 'tenant_form.feature_resources_desc', icon: Library },
  { key: 'gamification', labelKey: 'tenant_form.feature_gamification', descKey: 'tenant_form.feature_gamification_desc', icon: Trophy },
  { key: 'goals', labelKey: 'tenant_form.feature_goals', descKey: 'tenant_form.feature_goals_desc', icon: Target },
  { key: 'blog', labelKey: 'tenant_form.feature_blog', descKey: 'tenant_form.feature_blog_desc', icon: BookOpen },
  { key: 'exchange_workflow', labelKey: 'tenant_form.feature_exchange_workflow', descKey: 'tenant_form.feature_exchange_workflow_desc', icon: ArrowRightLeft },
  { key: 'federation', labelKey: 'tenant_form.feature_federation', descKey: 'tenant_form.feature_federation_desc', icon: Network },
  { key: 'organisations', labelKey: 'tenant_form.feature_organisations', descKey: 'tenant_form.feature_organisations_desc', icon: Building },
  { key: 'messages', labelKey: 'tenant_form.feature_messages', descKey: 'tenant_form.feature_messages_desc', icon: MessageCircle },
  { key: 'dashboard', labelKey: 'tenant_form.feature_dashboard', descKey: 'tenant_form.feature_dashboard_desc', icon: LayoutDashboard },
  { key: 'feed', labelKey: 'tenant_form.feature_feed', descKey: 'tenant_form.feature_feed_desc', icon: Rss },
  { key: 'marketplace', labelKey: 'tenant_form.feature_marketplace', descKey: 'tenant_form.feature_marketplace_desc', icon: ShoppingBag },
];

const COUNTRY_CODES = [
  'IE', 'GB', 'US', 'CA', 'AU', 'NZ', 'DE', 'FR', 'ES', 'IT',
  'NL', 'BE', 'PT', 'SE', 'NO', 'DK', 'FI', 'PL', 'AT', 'CH',
];

const SERVICE_AREAS = ['local', 'regional', 'national', 'international'];

const PLATFORM_LANGUAGES = [
  { code: 'en', label: 'English', short: 'EN' },
  { code: 'ga', label: 'Gaeilge', short: 'GA' },
  { code: 'de', label: 'Deutsch', short: 'DE' },
  { code: 'fr', label: 'Français', short: 'FR' },
  { code: 'it', label: 'Italiano', short: 'IT' },
  { code: 'pt', label: 'Português', short: 'PT' },
  { code: 'es', label: 'Español', short: 'ES' },
];

export function TenantForm() {
  const { t } = useTranslation('admin');
  const { id } = useParams<{ id: string }>();
  const [searchParams] = useSearchParams();
  const isEdit = !!id;
  usePageTitle(isEdit ? "Title Edit" : "Title Create");
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [parentTenants, setParentTenants] = useState<SuperAdminTenant[]>([]);
  // Preserve the full original configuration JSON so save doesn't wipe unmanaged keys
  const [originalConfiguration, setOriginalConfiguration] = useState<Record<string, unknown>>({});

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
    parent_id: '' as string, // Set from ?parent_id query param below
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
    supported_languages: ['en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar'] as string[],
    // Legal documents
    privacy_text: '',
    terms_text: '',
  });

  const [slugAutoGen, setSlugAutoGen] = useState(!isEdit);

  const updateField = (field: string, value: unknown) => {
    setForm((prev) => {
      const updated = { ...prev, [field]: value };
      // Auto-generate slug from name on create
      if (field === 'name' && slugAutoGen && !isEdit) {
        updated.slug = (value as string)
          .toLowerCase()
          .replace(/[^a-z0-9]+/g, '-')
          .replace(/^-|-$/g, '');
      }
      return updated;
    });
  };

  const loadTenant = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    try {
      const res = await adminSuper.getTenant(Number(id));
      if (res.success && res.data) {
        const tenant = res.data as SuperAdminTenantDetail;
        // Preserve the full configuration so save merges rather than overwrites
        const fullConfig = (tenant.configuration ?? {}) as Record<string, unknown>;
        setOriginalConfiguration(fullConfig);
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
          privacy_text: (tenant.configuration as Record<string, unknown>)?.privacy_text as string || '',
          terms_text: (tenant.configuration as Record<string, unknown>)?.terms_text as string || '',
        });
      }
    } catch {
      toast.error("Failed to load tenant");
    }
    setLoading(false);
  }, [id, toast, t])

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

  // Pre-fill parent_id from query param (e.g. ?parent_id=5 from "Create Sub-Tenant")
  useEffect(() => {
    if (!isEdit) {
      const qParent = searchParams.get('parent_id');
      if (qParent) {
        setForm((prev) => ({ ...prev, parent_id: qParent }));
      }
    }
  }, [isEdit, searchParams]);

  useEffect(() => {
    loadParentTenants();
    if (isEdit) loadTenant();
  }, [isEdit, loadTenant, loadParentTenants]);

  const handleSubmit = async () => {
    if (!form.name.trim()) {
      toast.error("Name Required");
      return;
    }
    if (!isEdit && !form.slug.trim()) {
      toast.error("Slug Required");
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

      // Merge language + legal settings into configuration JSON, preserving all existing keys
      // (modules, federation, broker_controls, footer_text, etc.)
      const mergedConfig: Record<string, unknown> = {
        ...originalConfiguration,
        default_language: form.default_language,
        supported_languages: form.supported_languages,
      };
      if (form.privacy_text.trim()) {
        mergedConfig.privacy_text = form.privacy_text;
      } else {
        delete mergedConfig.privacy_text;
      }
      if (form.terms_text.trim()) {
        mergedConfig.terms_text = form.terms_text;
      } else {
        delete mergedConfig.terms_text;
      }
      payload.configuration = mergedConfig;
      delete payload.default_language;
      delete payload.supported_languages;
      delete payload.privacy_text;
      delete payload.terms_text;

      let res;
      if (isEdit) {
        res = await adminSuper.updateTenant(Number(id), payload);
      } else {
        res = await adminSuper.createTenant(payload as unknown as CreateTenantPayload);
      }

      if (res.success) {
        toast.success(isEdit ? "Tenant updated" : "Tenant created");
        if (isEdit) {
          navigate(tenantPath(`/admin/super/tenants/${id}`));
        } else {
          // Navigate to the new tenant's show page if ID is returned, otherwise go to list
          const newId = (res as { data?: { tenant_id?: number } }).data?.tenant_id;
          navigate(tenantPath(newId ? `/admin/super/tenants/${newId}` : '/admin/super/tenants'));
        }
      } else {
        toast.error(res.error || (isEdit ? "Failed to update tenant" : "Failed to create tenant"));
      }
    } catch {
      toast.error("An Error Occurred");
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
        <Link to={tenantPath('/admin/super')} className="hover:text-primary">{"Breadcrumb Super Admin"}</Link>
        <span>/</span>
        <Link to={tenantPath('/admin/super/tenants')} className="hover:text-primary">{"Breadcrumb Tenants"}</Link>
        <span>/</span>
        <span className="text-foreground">{isEdit ? "Breadcrumb Edit" : "Breadcrumb Create"}</span>
      </nav>
      <PageHeader
        title={isEdit ? `Edit` : "Create"}
        description={isEdit ? "Edit." : "Create."}
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath(isEdit ? `/admin/super/tenants/${id}` : '/admin/super/tenants'))}
            >
              {"Back"}
            </Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSubmit}
              isLoading={saving}
            >
              {isEdit ? "Save Changes" : "Create Tenant"}
            </Button>
          </div>
        }
      />

      <Tabs variant="underlined" className="mb-4">
        <Tab key="details" title={"Details"}>
          <Card shadow="sm">
            <CardBody className="space-y-4 p-6">
              <Input
                label={"Name"}
                placeholder={"Enter name..."}
                value={form.name}
                onValueChange={(v) => updateField('name', v)}
                isRequired
                startContent={<Building2 size={16} className="text-default-400" />}
              />
              <Input
                label={"Slug"}
                placeholder={"Enter slug..."}
                value={form.slug}
                onValueChange={(v) => {
                  setSlugAutoGen(false);
                  updateField('slug', v.toLowerCase().replace(/[^a-z0-9-]/g, ''));
                }}
                isRequired
                description={slugAutoGen ? "Slug Auto." : "Slug Manual."}
              />
              <Input
                label={"Domain"}
                placeholder={"Enter domain..."}
                value={form.domain}
                onValueChange={(v) => updateField('domain', v)}
              />
              <Input
                label={"Tagline"}
                placeholder={"Enter tagline..."}
                value={form.tagline}
                onValueChange={(v) => updateField('tagline', v)}
              />
              <Textarea
                label={"Description"}
                placeholder={"Enter description..."}
                value={form.description}
                onValueChange={(v) => updateField('description', v)}
                minRows={3}
              />
              <Select
                label={"Parent Tenant"}
                placeholder={"Enter parent tenant..."}
                selectedKeys={form.parent_id ? [form.parent_id] : []}
                onSelectionChange={(keys) => {
                  const arr = Array.from(keys);
                  updateField('parent_id', arr.length > 0 ? String(arr[0]) : '');
                }}
                isDisabled={isEdit}
                description={isEdit ? "Parent Edit." : undefined}
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
                  {"Active"}
                </Switch>
                <Switch
                  isSelected={form.allows_subtenants}
                  onValueChange={(v) => updateField('allows_subtenants', v)}
                >
                  {"Allows Subtenants"}
                </Switch>
              </div>
              {form.allows_subtenants && (
                <Input
                  type="number"
                  label={"Max Depth"}
                  value={String(form.max_depth)}
                  onValueChange={(v) => updateField('max_depth', Number(v) || 3)}
                  className="max-w-xs"
                />
              )}
            </CardBody>
          </Card>
        </Tab>

        <Tab key="contact" title={"Contact"}>
          <Card shadow="sm">
            <CardBody className="space-y-4 p-6">
              <Input
                label={"Contact Email"}
                type="email"
                placeholder="admin@example.com"
                value={form.contact_email}
                onValueChange={(v) => updateField('contact_email', v)}
              />
              <Input
                label={"Contact Phone"}
                placeholder="+1 555 123 4567"
                value={form.contact_phone}
                onValueChange={(v) => updateField('contact_phone', v)}
              />
              <Textarea
                label={"Address"}
                placeholder={"Enter address..."}
                value={form.address}
                onValueChange={(v) => updateField('address', v)}
                minRows={2}
              />
            </CardBody>
          </Card>
        </Tab>

        <Tab key="seo" title={"SEO"}>
          <Card shadow="sm">
            <CardBody className="space-y-4 p-6">
              {/* Live SERP Preview */}
              <div className="rounded-lg border border-default-200 p-4 bg-white dark:bg-default-50">
                <p className="text-xs font-medium uppercase text-default-400 mb-2 flex items-center gap-1">
                  <Eye size={12} /> {"Google Search Preview"}
                </p>
                <p className="text-lg text-blue-700 dark:text-primary truncate">
                  {form.meta_title || form.name || "Title Fallback"}
                </p>
                <p className="text-sm text-green-700 dark:text-success truncate">
                  {form.domain ? `https://${form.domain}` : `https://${form.slug || 'tenant'}.project-nexus.ie`}
                </p>
                <p className="text-sm text-default-600 line-clamp-2">
                  {form.meta_description || "No description set found"}
                </p>
              </div>
              <Input
                label={"Meta Title"}
                placeholder={"Enter meta title..."}
                value={form.meta_title}
                onValueChange={(v) => updateField('meta_title', v)}
                description={`Characters Count`}
                maxLength={70}
              />
              <Textarea
                label={"Meta Description"}
                placeholder={"Enter meta description..."}
                value={form.meta_description}
                onValueChange={(v) => updateField('meta_description', v)}
                description={`Characters Count`}
                maxLength={180}
                minRows={2}
              />
              <Input
                label={"H1 Headline"}
                placeholder={"Enter h1 headline..."}
                value={form.h1_headline}
                onValueChange={(v) => updateField('h1_headline', v)}
                maxLength={100}
              />
              <Textarea
                label={"Hero Intro"}
                placeholder={"Enter hero intro..."}
                value={form.hero_intro}
                onValueChange={(v) => updateField('hero_intro', v)}
                minRows={2}
              />
              <Input
                label={"OG Image URL"}
                placeholder="https://example.com/image.jpg"
                value={form.og_image_url}
                onValueChange={(v) => updateField('og_image_url', v)}
                description={"OG Image URL."}
              />
              <Select
                label={"Robots Directive"}
                placeholder={"Enter robots directive..."}
                selectedKeys={form.robots_directive ? [form.robots_directive] : []}
                onSelectionChange={(keys) => {
                  const arr = Array.from(keys);
                  updateField('robots_directive', arr.length > 0 ? String(arr[0]) : '');
                }}
                className="max-w-xs"
                description={"Robots."}
              >
                <SelectItem key="index, follow">{"Robots Index Follow"}</SelectItem>
                <SelectItem key="noindex, follow">{"Noindex Follow"}</SelectItem>
                <SelectItem key="index, nofollow">{"Index Nofollow"}</SelectItem>
                <SelectItem key="noindex, nofollow">{"Noindex Nofollow"}</SelectItem>
              </Select>
            </CardBody>
          </Card>
        </Tab>

        <Tab key="location" title={"Location"}>
          <Card shadow="sm">
            <CardBody className="space-y-4 p-6">
              <Input
                label={"Location Name"}
                placeholder={"Enter location name..."}
                value={form.location_name}
                onValueChange={(v) => updateField('location_name', v)}
              />
              <Select
                label={"Country"}
                placeholder={"Enter country..."}
                selectedKeys={form.country_code ? [form.country_code] : []}
                onSelectionChange={(keys) => {
                  const arr = Array.from(keys);
                  updateField('country_code', arr.length > 0 ? String(arr[0]) : '');
                }}
                className="max-w-xs"
              >
                {COUNTRY_CODES.map((code) => (
                  <SelectItem key={code}>{t(`tenant_form.countries.${code}`)} ({code})</SelectItem>
                ))}
              </Select>
              <Select
                label={"Service Area"}
                placeholder={"Enter service area..."}
                selectedKeys={form.service_area ? [form.service_area] : []}
                onSelectionChange={(keys) => {
                  const arr = Array.from(keys);
                  updateField('service_area', arr.length > 0 ? String(arr[0]) : '');
                }}
                className="max-w-xs"
              >
                {SERVICE_AREAS.map((key) => (
                  <SelectItem key={key}>{t(`tenant_form.service_areas.${key}`)}</SelectItem>
                ))}
              </Select>
              <div className="grid grid-cols-2 gap-4">
                <Input
                  label={"Latitude"}
                  placeholder="40.7128"
                  value={form.latitude}
                  onValueChange={(v) => updateField('latitude', v)}
                />
                <Input
                  label={"Longitude"}
                  placeholder="-74.0060"
                  value={form.longitude}
                  onValueChange={(v) => updateField('longitude', v)}
                />
              </div>
            </CardBody>
          </Card>
        </Tab>

        <Tab key="social" title={"Social"}>
          <Card shadow="sm">
            <CardBody className="space-y-4 p-6">
              <Input
                label={"Facebook"}
                placeholder="https://facebook.com/..."
                value={form.social_facebook}
                onValueChange={(v) => updateField('social_facebook', v)}
              />
              <Input
                label={"Twitter"}
                placeholder="https://twitter.com/..."
                value={form.social_twitter}
                onValueChange={(v) => updateField('social_twitter', v)}
              />
              <Input
                label={"Instagram"}
                placeholder="https://instagram.com/..."
                value={form.social_instagram}
                onValueChange={(v) => updateField('social_instagram', v)}
              />
              <Input
                label={"Linkedin"}
                placeholder="https://linkedin.com/..."
                value={form.social_linkedin}
                onValueChange={(v) => updateField('social_linkedin', v)}
              />
              <Input
                label={"Youtube"}
                placeholder="https://youtube.com/..."
                value={form.social_youtube}
                onValueChange={(v) => updateField('social_youtube', v)}
              />
            </CardBody>
          </Card>
        </Tab>

        <Tab key="languages" title={"Languages"}>
          <Card shadow="sm">
            <CardBody className="space-y-4 p-6">
              <Select
                label={"Default Language"}
                description={"Default Language."}
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
                <p className="text-sm font-medium mb-1">{"Available Languages"}</p>
                <p className="text-xs text-default-400 mb-3">
                  {"Available Languages."}
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
                          <span className="ml-2 text-xs text-default-400">{"Always Enabled"}</span>
                        )}
                      </span>
                    </Checkbox>
                  ))}
                </div>
              </div>
            </CardBody>
          </Card>
        </Tab>

        <Tab key="features" title={"Features"}>
          <Card shadow="sm">
            <CardBody className="p-6">
              <p className="text-sm text-default-500 mb-4">
                {"Features."}
              </p>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {FEATURE_META.map(({ key, labelKey, descKey, icon: Icon }) => {
                  const label = t(labelKey);
                  const description = t(descKey);
                  const enabled = form.features[key] ?? false;
                  return (
                    <div
                      key={key}
                      className={`flex items-center gap-3 rounded-lg border p-3 transition-colors ${
                        enabled ? 'border-success-200 bg-success-50 dark:border-success-800 dark:bg-success-50/10' : 'border-default-200'
                      }`}
                    >
                      <Icon size={20} className={enabled ? 'text-success' : 'text-default-400'} />
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium">{label}</p>
                        <p className="text-xs text-default-400 truncate">{description}</p>
                      </div>
                      <Switch
                        size="sm"
                        isSelected={enabled}
                        onValueChange={(v) =>
                          updateField('features', { ...form.features, [key]: v })
                        }
                        aria-label={`Toggle ${label}`}
                      />
                    </div>
                  );
                })}
              </div>
            </CardBody>
          </Card>
        </Tab>
        <Tab key="legal" title={"Legal"}>
          <Card shadow="sm">
            <CardBody className="space-y-4 p-6">
              <p className="text-sm text-default-500 mb-2">
                {"Legal."}
              </p>
              <Textarea
                label={"Privacy Override"}
                placeholder={"Enter privacy override..."}
                value={form.privacy_text}
                onValueChange={(v) => updateField('privacy_text', v)}
                minRows={4}
                description={"Privacy Override."}
              />
              <Textarea
                label={"Terms Override"}
                placeholder={"Enter terms override..."}
                value={form.terms_text}
                onValueChange={(v) => updateField('terms_text', v)}
                minRows={4}
                description={"Terms Override."}
              />
              {(form.privacy_text.trim() || form.terms_text.trim()) && (
                <Button
                  variant="flat"
                  color="warning"
                  size="sm"
                  onPress={() => {
                    updateField('privacy_text', '');
                    updateField('terms_text', '');
                  }}
                >
                  {"Clear All Use Defaults"}
                </Button>
              )}
            </CardBody>
          </Card>
        </Tab>
      </Tabs>
    </div>
  );
}

export default TenantForm;
