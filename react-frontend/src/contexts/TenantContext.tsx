// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * NEXUS Tenant Context
 *
 * Provides:
 * - Tenant bootstrap data
 * - Feature flags
 * - Tenant branding
 * - Module configuration
 * - Automatic tenant detection from URL (subdomain or path slug)
 * - Tenant slug for URL-scoped navigation
 *
 * Implements TRS-001 resolution rules R1-R4.
 * @see docs/TRS-001-TENANT-RESOLUTION-SPEC.md
 */

import {
  createContext,
  useContext,
  useState,
  useEffect,
  useMemo,
  useCallback,
  type ReactNode,
} from 'react';
import i18n from '@/i18n';
import { api, tokenManager, fetchCsrfToken } from '@/lib/api';
import { detectTenantFromUrl, tenantPath as buildTenantPath } from '@/lib/tenant-routing';
import { validateResponseIfPresent } from '@/lib/api-validation';
import { tenantBootstrapSchema } from '@/lib/api-schemas';
import { setSentryTenant } from '@/lib/sentry';
import type { TenantConfig, TenantFeatures, TenantModules, TenantBranding, GroupTabConfig, ListingConfig, VolunteeringConfig, JobConfig } from '@/types';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface TenantState {
  tenant: TenantConfig | null;
  isLoading: boolean;
  error: string | null;
  /** Slug that was NOT found (bootstrap returned error). Used for soft 404. */
  notFoundSlug: string | null;
}

interface TenantContextValue extends TenantState {
  features: TenantFeatures;
  modules: TenantModules;
  branding: TenantBranding;
  groupTabs: GroupTabConfig;
  listingConfig: ListingConfig;
  volunteeringConfig: VolunteeringConfig;
  jobConfig: JobConfig;
  hasFeature: (feature: keyof TenantFeatures) => boolean;
  hasModule: (module: keyof TenantModules) => boolean;
  hasGroupTab: (tab: keyof GroupTabConfig) => boolean;
  refreshTenant: () => Promise<void>;
  /** The current tenant slug from URL (path or subdomain). Null if no slug in URL. */
  tenantSlug: string | null;
  /** Build a path with the tenant slug prefix (if present). */
  tenantPath: (path: string) => string;
  /** Language codes supported by this tenant (e.g. ['en', 'ga'] or ['de', 'fr', 'it', 'en']) */
  supportedLanguages: string[];
  /** Default language for this tenant (e.g. 'de' for Swiss tenants) */
  defaultLanguage: string;
}

// Default features — synced with PHP TenantFeatureConfig::FEATURE_DEFAULTS
const defaultFeatures: TenantFeatures = {
  events: true,
  groups: true,
  gamification: true,
  goals: true,
  blog: true,
  resources: true,
  volunteering: true,
  exchange_workflow: true,
  organisations: true,
  federation: true,
  connections: true,
  reviews: true,
  polls: true,
  job_vacancies: true,
  ideation_challenges: true,
  direct_messaging: true,
  group_exchanges: true,
  search: true,
  ai_chat: true,
};

// Default modules (all enabled)
const defaultModules: TenantModules = {
  feed: true,
  listings: true,
  messages: true,
  wallet: true,
  notifications: true,
  profile: true,
  settings: true,
  dashboard: true,
};

// Default group tab visibility — all enabled
const defaultGroupTabs: GroupTabConfig = {
  tab_feed: true,
  tab_discussion: true,
  tab_members: true,
  tab_events: true,
  tab_files: true,
  tab_announcements: true,
  tab_qa: true,
  tab_wiki: true,
  tab_media: true,
  tab_chatrooms: true,
  tab_tasks: true,
  tab_challenges: true,
  tab_analytics: true,
  tab_subgroups: true,
};

// Default listing config — all features enabled, sensible limits
const defaultListingConfig: ListingConfig = {
  'listing.moderation_enabled': false,
  'listing.auto_approve_trusted': false,
  'listing.max_per_user': 50,
  'listing.max_images': 5,
  'listing.max_image_size_mb': 8,
  'listing.require_image': false,
  'listing.min_title_length': 5,
  'listing.min_description_length': 20,
  'listing.allow_offers': true,
  'listing.allow_requests': true,
  'listing.require_category': true,
  'listing.require_location': false,
  'listing.require_hours_estimate': false,
  'listing.enable_skill_tags': true,
  'listing.enable_service_type': true,
  'listing.auto_expire_days': 0,
  'listing.max_renewals': 12,
  'listing.renewal_days': 30,
  'listing.expiry_reminders': true,
  'listing.enable_featured': true,
  'listing.featured_duration_days': 7,
  'listing.enable_ai_descriptions': true,
  'listing.enable_reporting': true,
  'listing.enable_favourites': true,
  'listing.enable_map_view': true,
  'listing.enable_reciprocity': true,
};

// Default volunteering config — all tabs enabled, sensible defaults
const defaultVolunteeringConfig: VolunteeringConfig = {
  'volunteering.tab_opportunities': true,
  'volunteering.tab_applications': true,
  'volunteering.tab_hours': true,
  'volunteering.tab_recommended': true,
  'volunteering.tab_certificates': true,
  'volunteering.tab_alerts': true,
  'volunteering.tab_wellbeing': true,
  'volunteering.tab_credentials': true,
  'volunteering.tab_waitlist': true,
  'volunteering.tab_swaps': true,
  'volunteering.tab_group_signups': true,
  'volunteering.tab_hours_review': true,
  'volunteering.tab_expenses': true,
  'volunteering.tab_safeguarding': true,
  'volunteering.tab_community_projects': true,
  'volunteering.tab_donations': true,
  'volunteering.tab_accessibility': true,
  'volunteering.swap_requires_admin': false,
  'volunteering.auto_approve_applications': false,
  'volunteering.require_org_note_on_decline': false,
  'volunteering.cancellation_deadline_hours': 24,
  'volunteering.max_hours_per_shift': 8,
  'volunteering.hours_require_verification': true,
  'volunteering.min_hours_for_certificate': 1,
  'volunteering.alert_default_expiry_hours': 24,
  'volunteering.alert_skill_matching': true,
  'volunteering.expenses_enabled': true,
  'volunteering.expense_require_receipt': false,
  'volunteering.expense_max_amount': 500,
  'volunteering.burnout_detection': true,
  'volunteering.guardian_consent_required': false,
  'volunteering.enable_qr_checkin': true,
  'volunteering.enable_recurring_shifts': true,
  'volunteering.enable_reviews': true,
  'volunteering.enable_matching': true,
};

// Default job config
const defaultJobConfig: JobConfig = {
  'jobs.tab_browse': true,
  'jobs.tab_saved': true,
  'jobs.tab_my_postings': true,
  'jobs.page_kanban': true,
  'jobs.page_analytics': true,
  'jobs.page_bias_audit': true,
  'jobs.page_talent_search': true,
  'jobs.page_alerts': true,
  'jobs.allow_paid': true,
  'jobs.allow_volunteer': true,
  'jobs.allow_timebank': true,
  'jobs.require_salary': false,
  'jobs.default_currency': 'EUR',
  'jobs.max_postings_per_user': 20,
  'jobs.default_deadline_days': 30,
  'jobs.moderation_enabled': false,
  'jobs.spam_detection': true,
  'jobs.auto_approve_trusted': false,
  'jobs.enable_cv_upload': true,
  'jobs.require_cover_message': false,
  'jobs.enable_interview_scheduling': true,
  'jobs.enable_offers': true,
  'jobs.enable_scorecards': true,
  'jobs.enable_pipeline_rules': true,
  'jobs.enable_blind_hiring': false,
  'jobs.enable_featured': true,
  'jobs.featured_duration_days': 7,
  'jobs.enable_ai_descriptions': true,
  'jobs.enable_skills_matching': true,
  'jobs.enable_referrals': true,
  'jobs.enable_templates': true,
  'jobs.enable_rss_feed': true,
  'jobs.enable_saved_profiles': true,
  'jobs.enable_employer_branding': true,
};

// Default branding
const defaultBranding: TenantBranding = {
  name: 'NEXUS',
  tagline: 'Time Banking Platform',
  logo: undefined,
  favicon: undefined,
  primaryColor: '#6366f1',
  secondaryColor: '#a855f7',
};

// ─────────────────────────────────────────────────────────────────────────────
// Context
// ─────────────────────────────────────────────────────────────────────────────

const TenantContext = createContext<TenantContextValue | null>(null);

// ─────────────────────────────────────────────────────────────────────────────
// Provider
// ─────────────────────────────────────────────────────────────────────────────

interface TenantProviderProps {
  children: ReactNode;
  /** Tenant slug from route param (/:tenantSlug prefix). Takes priority over URL detection. */
  tenantSlug?: string;
}

export function TenantProvider({ children, tenantSlug }: TenantProviderProps) {
  const [state, setState] = useState<TenantState>({
    tenant: null,
    isLoading: true,
    error: null,
    notFoundSlug: null,
  });

  // Determine effective slug: route param > URL detection > stored slug (localStorage fallback)
  // The stored slug fallback prevents losing the tenant when a redirect/error strips
  // the slug prefix from the URL (e.g., /hour-timebank/listings → /listings).
  const detected = useMemo(() => detectTenantFromUrl(), []);
  const storedSlug = useMemo(() => tokenManager.getTenantSlug(), []);
  const effectiveTenantSlug = tenantSlug || detected.slug || storedSlug;

  /**
   * Fetch tenant bootstrap data
   */
  const refreshTenant = useCallback(async () => {
    setState((prev) => ({ ...prev, isLoading: true, error: null, notFoundSlug: null }));

    try {
      // Build endpoint with optional tenant slug
      // Per TRS-001: NO ?domain= parameter. Slug only.
      let endpoint = '/v2/tenant/bootstrap';
      if (effectiveTenantSlug) {
        endpoint += `?slug=${encodeURIComponent(effectiveTenantSlug)}`;
      }

      const response = await api.get<TenantConfig>(endpoint, { skipAuth: true, skipTenant: true });

      if (response.success && response.data) {
        const tenant = response.data;

        // Dev-only: validate tenant bootstrap response shape
        validateResponseIfPresent(tenantBootstrapSchema, tenant, `GET ${endpoint}`);

        // TRS-001: Stale localStorage override
        // If URL resolved a tenant and it differs from stored value, override.
        const storedTenantId = tokenManager.getTenantId();
        if (tenant.id) {
          if (storedTenantId && String(storedTenantId) !== String(tenant.id)) {
            console.warn(
              `[TenantContext] Overriding stale localStorage tenant_id. ` +
              `Stored: ${storedTenantId}, URL-resolved: ${tenant.id}`
            );
            // Clear cached API requests when tenant switches to prevent stale cross-tenant data
            api.clearInflightRequests();
          }
          tokenManager.setTenantId(tenant.id);
        }
        if (tenant.slug) {
          tokenManager.setTenantSlug(tenant.slug);
        }

        // Fetch CSRF token for form submissions
        await fetchCsrfToken();

        // Set Sentry tenant context
        if (tenant.id && tenant.name && tenant.slug) {
          setSentryTenant({
            id: tenant.id,
            name: tenant.name,
            slug: tenant.slug,
          });
        }

        setState({
          tenant,
          isLoading: false,
          error: null,
          notFoundSlug: null,
        });
      } else if (response.code === 'SERVICE_UNAVAILABLE') {
        // API is in maintenance mode — synthesise a tenant that triggers MaintenancePage
        setSentryTenant(null);
        setState({
          tenant: { settings: { maintenance_mode: true } } as unknown as TenantConfig,
          isLoading: false,
          error: null,
          notFoundSlug: null,
        });
      } else {
        // Bootstrap failed — if we had a slug, this is an unknown tenant (soft 404)
        setSentryTenant(null);
        setState({
          tenant: null,
          isLoading: false,
          error: response.error ?? 'Failed to load tenant configuration',
          notFoundSlug: effectiveTenantSlug || null,
        });
      }
    } catch (err) {
      setState({
        tenant: null,
        isLoading: false,
        error: err instanceof Error ? err.message : 'Failed to load tenant configuration',
        notFoundSlug: null,
      });
    }
  }, [effectiveTenantSlug]);

  // Fetch tenant on mount or when slug changes
  useEffect(() => {
    refreshTenant();
  }, [refreshTenant]);

  /**
   * Get features with fallback to defaults
   */
  const features = useMemo<TenantFeatures>(() => {
    if (!state.tenant?.features) {
      return defaultFeatures;
    }
    return { ...defaultFeatures, ...state.tenant.features };
  }, [state.tenant?.features]);

  /**
   * Get modules with fallback to defaults
   */
  const modules = useMemo<TenantModules>(() => {
    if (!state.tenant?.modules) {
      return defaultModules;
    }
    return { ...defaultModules, ...state.tenant.modules };
  }, [state.tenant?.modules]);

  /**
   * Get branding with fallback to defaults.
   * Normalises backend snake_case fields (logo_url, favicon_url, primary_color)
   * to the camelCase aliases expected by components (logo, favicon, primaryColor).
   * Also pulls name/tagline from the top-level tenant response since the backend
   * returns them there rather than inside the branding sub-object.
   */
  const branding = useMemo<TenantBranding>(() => {
    const raw = state.tenant?.branding ?? {};
    const logo = raw.logo ?? raw.logo_url ?? undefined;
    const favicon = raw.favicon ?? raw.favicon_url ?? undefined;
    const primaryColor = raw.primaryColor ?? raw.primary_color ?? defaultBranding.primaryColor;
    return {
      ...defaultBranding,
      ...raw,
      // Top-level tenant fields take precedence for name/tagline
      name: state.tenant?.name ?? raw.name ?? defaultBranding.name,
      tagline: state.tenant?.tagline ?? raw.tagline ?? defaultBranding.tagline,
      logo,
      favicon,
      primaryColor,
    };
  }, [state.tenant]);

  /**
   * Get listing config with fallback to defaults
   */
  const listingConfig = useMemo<ListingConfig>(() => {
    if (!state.tenant?.listing_config) {
      return defaultListingConfig;
    }
    return { ...defaultListingConfig, ...state.tenant.listing_config };
  }, [state.tenant?.listing_config]);

  /**
   * Get job config with fallback to defaults
   */
  const jobConfig = useMemo<JobConfig>(() => {
    if (!state.tenant?.job_config) {
      return defaultJobConfig;
    }
    return { ...defaultJobConfig, ...state.tenant.job_config };
  }, [state.tenant?.job_config]);

  /**
   * Get volunteering config with fallback to defaults
   */
  const volunteeringConfig = useMemo<VolunteeringConfig>(() => {
    if (!state.tenant?.volunteering_config) {
      return defaultVolunteeringConfig;
    }
    return { ...defaultVolunteeringConfig, ...state.tenant.volunteering_config };
  }, [state.tenant?.volunteering_config]);

  /**
   * Get group tab config with fallback to defaults (all enabled)
   */
  const groupTabs = useMemo<GroupTabConfig>(() => {
    if (!state.tenant?.group_tabs) {
      return defaultGroupTabs;
    }
    return { ...defaultGroupTabs, ...state.tenant.group_tabs };
  }, [state.tenant?.group_tabs]);

  /**
   * Check if feature is enabled
   */
  const hasFeature = useCallback((feature: keyof TenantFeatures): boolean => {
    return features[feature] ?? false;
  }, [features]);

  /**
   * Check if module is enabled
   */
  const hasModule = useCallback((module: keyof TenantModules): boolean => {
    return modules[module] ?? false;
  }, [modules]);

  /**
   * Check if a group tab is enabled
   */
  const hasGroupTab = useCallback((tab: keyof GroupTabConfig): boolean => {
    return groupTabs[tab] ?? true;
  }, [groupTabs]);

  /**
   * Build a path with the current tenant slug prefix.
   * Preserves the slug in all internal navigation.
   */
  const tenantPath = useCallback((path: string): string => {
    return buildTenantPath(path, effectiveTenantSlug);
  }, [effectiveTenantSlug]);

  const supportedLanguages = useMemo<string[]>(
    () => state.tenant?.supported_languages ?? ['en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar'],
    [state.tenant]
  );

  const defaultLanguage = useMemo<string>(
    () => state.tenant?.default_language ?? 'en',
    [state.tenant]
  );

  // After tenant data loads, apply tenant default language if user hasn't
  // explicitly chosen one via the language switcher.
  // Note: We check 'nexus_language_user_chosen' (set by LanguageSwitcher), NOT
  // 'nexus_language' (auto-written by i18next's caches:['localStorage'] on init).
  // Without this distinction, the browser-detected language gets auto-cached and
  // the tenant default would never apply.
  useEffect(() => {
    if (!state.tenant) return;

    const tenantDefault = state.tenant.default_language ?? 'en';
    const userExplicitlyChose = localStorage.getItem('nexus_language_user_chosen');

    if (!userExplicitlyChose && i18n.language !== tenantDefault) {
      i18n.changeLanguage(tenantDefault);
    }
  }, [state.tenant]);

  const value = useMemo<TenantContextValue>(
    () => ({
      ...state,
      features,
      modules,
      branding,
      groupTabs,
      listingConfig,
      volunteeringConfig,
      jobConfig,
      hasFeature,
      hasModule,
      hasGroupTab,
      refreshTenant,
      tenantSlug: effectiveTenantSlug || null,
      tenantPath,
      supportedLanguages,
      defaultLanguage,
    }),
    [state, features, modules, branding, groupTabs, listingConfig, volunteeringConfig, jobConfig, hasFeature, hasModule, hasGroupTab, refreshTenant, effectiveTenantSlug, tenantPath, supportedLanguages, defaultLanguage]
  );

  return (
    <TenantContext.Provider value={value}>{children}</TenantContext.Provider>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Hooks
// ─────────────────────────────────────────────────────────────────────────────

export function useTenant(): TenantContextValue {
  const context = useContext(TenantContext);

  if (!context) {
    throw new Error('useTenant must be used within a TenantProvider');
  }

  return context;
}

/**
 * Convenience hook for feature checking
 */
export function useFeature(feature: keyof TenantFeatures): boolean {
  const { hasFeature } = useTenant();
  return hasFeature(feature);
}

/**
 * Convenience hook for module checking
 */
export function useModule(module: keyof TenantModules): boolean {
  const { hasModule } = useTenant();
  return hasModule(module);
}

/**
 * Convenience hook for tenant-supported languages
 */
export function useTenantLanguages(): string[] {
  return useTenant().supportedLanguages;
}

/**
 * Convenience hook for tenant default language
 */
export function useTenantDefaultLanguage(): string {
  return useTenant().defaultLanguage;
}

export default TenantContext;
