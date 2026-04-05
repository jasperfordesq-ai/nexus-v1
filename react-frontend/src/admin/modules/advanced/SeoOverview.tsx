// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SEO Overview — Admin dashboard for search engine optimization.
 *
 * Sections:
 * 1. Tenant Meta (stored on tenants table: title, description, H1, hero intro)
 * 2. OG Image (default social sharing image for all pages)
 * 3. Meta Tags (title suffix, global description, keywords)
 * 4. SEO Features (sitemap, canonical URLs, Open Graph, Twitter Cards toggles)
 * 5. Verification & Robots (Google/Bing verification, custom robots.txt)
 * 6. Sitemap Stats (live stats from SitemapService, cache clear button)
 * 7. SEO Health Check (quick audit of common issues)
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card, CardBody, CardHeader, Switch, Input, Button, Spinner,
  Chip, Divider, Textarea,
} from '@heroui/react';
import {
  Save, RefreshCw, ExternalLink, FileText, Globe, Image,
  Search, Share2, CheckCircle, AlertTriangle, XCircle, BarChart3,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSettings, adminTools } from '../../api/adminApi';
import { useTranslation } from 'react-i18next';

// Keys that map to the backend's seo_* tenant_settings rows
const SEO_KEYS = [
  'seo_title_suffix', 'seo_meta_description', 'seo_meta_keywords',
  'seo_og_image_url',
  'seo_auto_sitemap', 'seo_canonical_urls', 'seo_open_graph',
  'seo_twitter_cards', 'seo_robots_txt', 'seo_google_verification', 'seo_bing_verification',
] as const;

// Keys stored directly on the tenants table
const TENANT_KEYS = [
  'tenant_meta_title', 'tenant_meta_description', 'tenant_h1_headline', 'tenant_hero_intro',
] as const;

interface SitemapStats {
  sitemap_url: string;
  total_urls: number;
  content_types: Record<string, number>;
}

interface SeoCheckResult {
  name: string;
  status: 'pass' | 'warning' | 'fail';
  detail: string;
}

interface ServerAuditResult {
  checks: Array<{ name: string; description: string; status: 'pass' | 'warning' | 'fail'; details?: string }>;
  last_run_at: string | null;
}

export function SeoOverview() {
  const { t } = useTranslation('admin');
  usePageTitle(t('advanced.page_title'));
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [sitemapStats, setSitemapStats] = useState<SitemapStats | null>(null);
  const [sitemapLoading, setSitemapLoading] = useState(false);
  const [clearingCache, setClearingCache] = useState(false);
  const [serverAudit, setServerAudit] = useState<ServerAuditResult | null>(null);
  const [auditRunning, setAuditRunning] = useState(false);
  const [formData, setFormData] = useState<Record<string, unknown>>({
    seo_title_suffix: '',
    seo_meta_description: '',
    seo_meta_keywords: '',
    seo_og_image_url: '',
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
          const raw = res.data as Record<string, unknown>;
          const seo = (raw.seo ?? raw) as Record<string, unknown>;
          setFormData(prev => ({ ...prev, ...seo }));
        }
      })
      .catch(() => toast.error(t('advanced.failed_to_load_s_e_o_settings')))
      .finally(() => setLoading(false));
  }, []); // eslint-disable-line react-hooks/exhaustive-deps -- load once on mount

  const loadSitemapStats = useCallback(() => {
    setSitemapLoading(true);
    adminSettings.getSitemapStats()
      .then(res => {
        if (res.data) {
          setSitemapStats(res.data as unknown as SitemapStats);
        }
      })
      .catch(() => toast.error(t('advanced.failed_to_load_sitemap_stats')))
      .finally(() => setSitemapLoading(false));
  }, [toast]);

  useEffect(() => {
    loadSitemapStats();
  }, [loadSitemapStats]);

  // Load last server-side SEO audit on mount
  useEffect(() => {
    adminTools.getSeoAudit()
      .then(res => {
        if (res.data) {
          const raw = res.data as unknown as ServerAuditResult;
          if (raw?.checks) setServerAudit(raw);
        }
      })
      .catch(() => { /* silently fail — audit is optional */ });
  }, []);

  const handleRunAudit = async () => {
    setAuditRunning(true);
    try {
      const res = await adminTools.runSeoAudit();
      if (res.data) {
        // The run endpoint returns results directly; re-fetch to get full structure
        const freshRes = await adminTools.getSeoAudit();
        if (freshRes.data) {
          setServerAudit(freshRes.data as unknown as ServerAuditResult);
        }
        toast.success(t('advanced.seo_audit_completed'));
      }
    } catch {
      toast.error(t('advanced.failed_to_run_seo_audit'));
    } finally {
      setAuditRunning(false);
    }
  };

  const handleClearSitemapCache = async () => {
    setClearingCache(true);
    try {
      await adminSettings.clearSitemapCache();
      toast.success(t('advanced.sitemap_cache_cleared'));
      loadSitemapStats();
    } catch {
      toast.error(t('advanced.failed_to_clear_sitemap_cache'));
    } finally {
      setClearingCache(false);
    }
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      const payload: Record<string, unknown> = {};
      for (const key of [...SEO_KEYS, ...TENANT_KEYS]) {
        if (key in formData) payload[key] = formData[key];
      }

      const res = await adminSettings.updateSeoSettings(payload);

      if (res.success) {
        toast.success(t('advanced.s_e_o_settings_saved_successfully'));
      } else {
        const error = (res as { error?: string }).error || t('advanced.save_failed');
        toast.error(error);
      }
    } catch (err) {
      toast.error(t('advanced.failed_to_save_s_e_o_settings'));
      console.error('SEO settings save error:', err);
    } finally {
      setSaving(false);
    }
  };

  const updateField = (key: string, value: unknown) => {
    setFormData(prev => ({ ...prev, [key]: value }));
  };

  // Run a client-side SEO health check based on current settings
  const runHealthCheck = (): SeoCheckResult[] => {
    const checks: SeoCheckResult[] = [];

    // Title suffix
    const suffix = String(formData.seo_title_suffix || '');
    checks.push({
      name: 'Title Suffix',
      status: suffix.length > 0 ? 'pass' : 'warning',
      detail: suffix.length > 0
        ? `Configured: "${suffix}"`
        : 'No title suffix set — pages will show "Page | NEXUS" as default',
    });

    // Meta description
    const desc = String(formData.seo_meta_description || formData.tenant_meta_description || '');
    checks.push({
      name: 'Meta Description',
      status: desc.length >= 120 ? 'pass' : desc.length >= 50 ? 'warning' : 'fail',
      detail: desc.length > 0
        ? `${desc.length} characters (ideal: 150–160)`
        : 'No default meta description set',
    });

    // OG Image
    const ogImage = String(formData.seo_og_image_url || '');
    checks.push({
      name: 'Default OG Image',
      status: ogImage.length > 0 ? 'pass' : 'warning',
      detail: ogImage.length > 0
        ? 'Default social sharing image is configured'
        : 'No default OG image — social shares will use fallback',
    });

    // Open Graph
    checks.push({
      name: 'Open Graph Tags',
      status: formData.seo_open_graph ? 'pass' : 'fail',
      detail: formData.seo_open_graph ? 'Enabled' : 'Disabled — social media previews won\'t work',
    });

    // Twitter Cards
    checks.push({
      name: 'Twitter Cards',
      status: formData.seo_twitter_cards ? 'pass' : 'fail',
      detail: formData.seo_twitter_cards ? 'Enabled' : 'Disabled — X/Twitter previews won\'t work',
    });

    // Canonical URLs
    checks.push({
      name: 'Canonical URLs',
      status: formData.seo_canonical_urls ? 'pass' : 'warning',
      detail: formData.seo_canonical_urls
        ? 'Enabled — duplicate content prevented'
        : 'Disabled — risk of duplicate content in search results',
    });

    // Sitemap
    checks.push({
      name: 'Auto Sitemap',
      status: formData.seo_auto_sitemap ? 'pass' : 'fail',
      detail: formData.seo_auto_sitemap
        ? `Active${sitemapStats ? ` — ${sitemapStats.total_urls} URLs indexed` : ''}`
        : 'Disabled — search engines can\'t discover your pages',
    });

    // Google verification
    const gv = String(formData.seo_google_verification || '');
    checks.push({
      name: 'Google Search Console',
      status: gv.length > 0 ? 'pass' : 'warning',
      detail: gv.length > 0 ? 'Verification tag configured' : 'Not configured — recommended for search insights',
    });

    // Tenant meta title
    const mt = String(formData.tenant_meta_title || '');
    checks.push({
      name: 'Tenant Title',
      status: mt.length > 0 ? 'pass' : 'warning',
      detail: mt.length > 0 ? `Set: "${mt}"` : 'Not set — using default "NEXUS"',
    });

    // H1 headline
    const h1 = String(formData.tenant_h1_headline || '');
    checks.push({
      name: 'Homepage H1',
      status: h1.length > 0 ? 'pass' : 'warning',
      detail: h1.length > 0 ? `Set: "${h1}"` : 'Not set — homepage may be missing an H1 tag',
    });

    return checks;
  };

  const healthChecks = loading ? [] : runHealthCheck();
  const passCount = healthChecks.filter(c => c.status === 'pass').length;
  const warnCount = healthChecks.filter(c => c.status === 'warning').length;
  const failCount = healthChecks.filter(c => c.status === 'fail').length;

  if (loading) {
    return (
      <div className="flex h-64 items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div>
      <PageHeader title={t('advanced.seo_overview_title')} description={t('advanced.seo_overview_desc')} />

      <div className="space-y-4">
        {/* SEO Health Score */}
        <Card shadow="sm">
          <CardHeader className="flex items-center justify-between">
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <BarChart3 size={20} />
              {t('advanced.seo_health_check_heading')}
            </h3>
            <div className="flex items-center gap-2">
              <Chip color="success" variant="flat" size="sm">{passCount} {t('advanced.chip_pass')}</Chip>
              {warnCount > 0 && <Chip color="warning" variant="flat" size="sm">{warnCount} {t('advanced.chip_warning')}</Chip>}
              {failCount > 0 && <Chip color="danger" variant="flat" size="sm">{failCount} {t('advanced.chip_fail')}</Chip>}
            </div>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              {healthChecks.map((check) => (
                <div key={check.name} className="flex items-start gap-3 py-1">
                  {check.status === 'pass' && <CheckCircle size={18} className="text-success mt-0.5 shrink-0" />}
                  {check.status === 'warning' && <AlertTriangle size={18} className="text-warning mt-0.5 shrink-0" />}
                  {check.status === 'fail' && <XCircle size={18} className="text-danger mt-0.5 shrink-0" />}
                  <div className="min-w-0">
                    <p className="font-medium text-sm">{check.name}</p>
                    <p className="text-xs text-default-500">{check.detail}</p>
                  </div>
                </div>
              ))}
            </div>
          </CardBody>
        </Card>

        {/* Tenant-level meta (stored directly on tenants table) */}
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Globe size={20} />
              {t('advanced.tenant_meta_heading')}
            </h3>
          </CardHeader>
          <CardBody className="gap-4">
            <Input
              label={t('advanced.label_meta_title')}
              placeholder={t('advanced.placeholder_your_timebank_name')}
              variant="bordered"
              value={String(formData.tenant_meta_title || '')}
              onValueChange={(v) => updateField('tenant_meta_title', v)}
              description={t('advanced.desc_meta_title')}
            />
            <Input
              label={t('advanced.label_meta_description')}
              placeholder={t('advanced.placeholder_a_short_description_of_your_timebank')}
              variant="bordered"
              value={String(formData.tenant_meta_description || '')}
              onValueChange={(v) => updateField('tenant_meta_description', v)}
              description={`${String(formData.tenant_meta_description || '').length}/160 ${t('advanced.desc_meta_description_chars')}`}
            />
            <Input
              label={t('advanced.label_h1_headline')}
              placeholder={t('advanced.placeholder_welcome_to_our_community')}
              variant="bordered"
              value={String(formData.tenant_h1_headline || '')}
              onValueChange={(v) => updateField('tenant_h1_headline', v)}
              description={t('advanced.desc_h1_headline')}
            />
            <Input
              label={t('advanced.label_hero_intro_text')}
              placeholder={t('advanced.placeholder_exchange_skills_and_time_with_your_community')}
              variant="bordered"
              value={String(formData.tenant_hero_intro || '')}
              onValueChange={(v) => updateField('tenant_hero_intro', v)}
            />
          </CardBody>
        </Card>

        {/* Default OG Image */}
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Image size={20} />
              {t('advanced.social_sharing_image_heading')}
            </h3>
          </CardHeader>
          <CardBody className="gap-4">
            <Input
              label={t('advanced.label_og_image_url')}
              placeholder="https://your-domain.com/images/og-default.png"
              variant="bordered"
              value={String(formData.seo_og_image_url || '')}
              onValueChange={(v) => updateField('seo_og_image_url', v)}
              description={t('advanced.desc_og_image_url')}
            />
            {String(formData.seo_og_image_url || '') && (
              <div className="border border-default-200 rounded-lg p-3">
                <p className="text-xs text-default-500 mb-2">{t('advanced.og_image_preview')}:</p>
                <img
                  src={String(formData.seo_og_image_url)}
                  alt={t('advanced.og_image_preview')}
                  className="max-w-xs rounded-md"
                  onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
                />
              </div>
            )}
          </CardBody>
        </Card>

        {/* Global SEO settings (stored in tenant_settings) */}
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Search size={20} />
              {t('advanced.meta_tags_heading')}
            </h3>
          </CardHeader>
          <CardBody className="gap-4">
            <Input
              label={t('advanced.label_default_title_suffix')}
              placeholder=" | My Timebank"
              variant="bordered"
              value={String(formData.seo_title_suffix || '')}
              onValueChange={(v) => updateField('seo_title_suffix', v)}
              description={t('advanced.desc_title_suffix')}
            />
            <Input
              label={t('advanced.label_global_meta_description')}
              placeholder={t('advanced.placeholder_community_timebanking_platform')}
              variant="bordered"
              value={String(formData.seo_meta_description || '')}
              onValueChange={(v) => updateField('seo_meta_description', v)}
              description={`${String(formData.seo_meta_description || '').length}/160 ${t('advanced.desc_global_meta_description_chars')}`}
            />
            <Input
              label={t('advanced.label_meta_keywords')}
              placeholder="timebanking, community, exchange"
              variant="bordered"
              value={String(formData.seo_meta_keywords || '')}
              onValueChange={(v) => updateField('seo_meta_keywords', v)}
              description={t('advanced.desc_meta_keywords')}
            />
          </CardBody>
        </Card>

        {/* SEO Feature Toggles */}
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Share2 size={20} />
              {t('advanced.seo_features_heading')}
            </h3>
          </CardHeader>
          <CardBody className="space-y-3">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('advanced.auto_generate_sitemap')}</p>
                <p className="text-sm text-default-500">{t('advanced.auto_generate_sitemap_desc')}</p>
              </div>
              <Switch isSelected={!!formData.seo_auto_sitemap} onValueChange={(v) => updateField('seo_auto_sitemap', v)} aria-label={t('advanced.label_auto_sitemap')} />
            </div>
            <Divider />
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('advanced.canonical_urls')}</p>
                <p className="text-sm text-default-500">{t('advanced.canonical_urls_desc')}</p>
              </div>
              <Switch isSelected={!!formData.seo_canonical_urls} onValueChange={(v) => updateField('seo_canonical_urls', v)} aria-label={t('advanced.label_canonical_u_r_ls')} />
            </div>
            <Divider />
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('advanced.open_graph_tags')}</p>
                <p className="text-sm text-default-500">{t('advanced.open_graph_tags_desc')}</p>
              </div>
              <Switch isSelected={!!formData.seo_open_graph} onValueChange={(v) => updateField('seo_open_graph', v)} aria-label={t('advanced.label_open_graph')} />
            </div>
            <Divider />
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('advanced.twitter_cards')}</p>
                <p className="text-sm text-default-500">{t('advanced.twitter_cards_desc')}</p>
              </div>
              <Switch isSelected={!!formData.seo_twitter_cards} onValueChange={(v) => updateField('seo_twitter_cards', v)} aria-label={t('advanced.label_twitter_cards')} />
            </div>
          </CardBody>
        </Card>

        {/* Verification & Robots */}
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <FileText size={20} />
              {t('advanced.verification_robots_heading')}
            </h3>
          </CardHeader>
          <CardBody className="gap-4">
            <Input
              label={t('advanced.label_google_search_console_verification')}
              placeholder="google-site-verification=..."
              variant="bordered"
              value={String(formData.seo_google_verification || '')}
              onValueChange={(v) => updateField('seo_google_verification', v)}
            />
            <Input
              label={t('advanced.label_bing_webmaster_verification')}
              placeholder="msvalidate.01=..."
              variant="bordered"
              value={String(formData.seo_bing_verification || '')}
              onValueChange={(v) => updateField('seo_bing_verification', v)}
            />
            <Textarea
              label={t('advanced.custom_robots_txt')}
              placeholder={"User-agent: *\nDisallow: /admin/"}
              variant="bordered"
              minRows={4}
              value={String(formData.seo_robots_txt || '')}
              onValueChange={(v) => updateField('seo_robots_txt', v)}
              description={t('advanced.desc_custom_robots_txt')}
            />
          </CardBody>
        </Card>

        {/* Sitemap Stats */}
        <Card shadow="sm">
          <CardHeader className="flex items-center justify-between">
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <FileText size={20} />
              {t('advanced.sitemap_heading')}
            </h3>
            <div className="flex items-center gap-2">
              <Button
                size="sm"
                variant="flat"
                startContent={<RefreshCw size={14} />}
                onPress={loadSitemapStats}
                isLoading={sitemapLoading}
              >
                {t('advanced.btn_refresh')}
              </Button>
              <Button
                size="sm"
                variant="flat"
                color="warning"
                onPress={handleClearSitemapCache}
                isLoading={clearingCache}
              >
                {t('advanced.btn_clear_cache')}
              </Button>
            </div>
          </CardHeader>
          <CardBody>
            {sitemapLoading && !sitemapStats ? (
              <div className="flex justify-center py-4"><Spinner size="sm" /></div>
            ) : sitemapStats ? (
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">{t('advanced.sitemap_url_label')}</span>
                  <a
                    href={sitemapStats.sitemap_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-sm text-primary flex items-center gap-1 hover:underline"
                  >
                    {sitemapStats.sitemap_url}
                    <ExternalLink size={12} />
                  </a>
                </div>
                <Divider />
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">{t('advanced.sitemap_total_urls')}</span>
                  <Chip color="primary" variant="flat">{sitemapStats.total_urls}</Chip>
                </div>
                <Divider />
                <div>
                  <p className="text-sm font-medium mb-2">{t('advanced.sitemap_urls_by_type')}</p>
                  <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    {Object.entries(sitemapStats.content_types).map(([type, count]) => (
                      <div key={type} className="flex items-center justify-between bg-default-100 rounded-lg px-3 py-2">
                        <span className="text-xs capitalize">{type.replace(/_/g, ' ')}</span>
                        <Chip size="sm" variant="flat">{count}</Chip>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            ) : (
              <p className="text-sm text-default-500">{t('advanced.unable_to_load_sitemap_stats')}</p>
            )}
          </CardBody>
        </Card>

        {/* Server-Side SEO Audit */}
        <Card shadow="sm">
          <CardHeader className="flex items-center justify-between">
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Search size={20} />
              {t('advanced.server_seo_audit_heading')}
            </h3>
            <div className="flex items-center gap-2">
              {serverAudit?.last_run_at && (
                <span className="text-xs text-default-400">
                  {t('advanced.last_run_label')}: {new Date(serverAudit.last_run_at).toLocaleDateString()}
                </span>
              )}
              <Button
                size="sm"
                color="primary"
                variant="flat"
                onPress={handleRunAudit}
                isLoading={auditRunning}
              >
                {t('advanced.run_audit')}
              </Button>
            </div>
          </CardHeader>
          <CardBody>
            {serverAudit?.checks && serverAudit.checks.length > 0 ? (
              <div className="space-y-2">
                {serverAudit.checks.map((check, idx) => (
                  <div key={idx} className="flex items-start gap-3 py-1">
                    {check.status === 'pass' && <CheckCircle size={18} className="text-success mt-0.5 shrink-0" />}
                    {check.status === 'warning' && <AlertTriangle size={18} className="text-warning mt-0.5 shrink-0" />}
                    {check.status === 'fail' && <XCircle size={18} className="text-danger mt-0.5 shrink-0" />}
                    <div className="min-w-0">
                      <p className="font-medium text-sm">{check.name}</p>
                      <p className="text-xs text-default-500">{check.description}</p>
                      {check.details && <p className="text-xs text-default-400 mt-0.5">{check.details}</p>}
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-default-500">
                {auditRunning ? t('advanced.running_audit') : t('advanced.no_audit_results_prompt')}
              </p>
            )}
          </CardBody>
        </Card>

        {/* Save Button */}
        <div className="flex justify-end">
          <Button
            color="primary"
            size="lg"
            startContent={<Save size={18} />}
            onPress={handleSave}
            isLoading={saving}
            isDisabled={saving}
          >
            {t('advanced.save_seo_settings')}
          </Button>
        </div>
      </div>
    </div>
  );
}

export default SeoOverview;
