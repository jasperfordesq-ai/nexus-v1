// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export type BillingCycle = 'monthly' | 'annual';
export type ProductLine = 'community-timebanking' | 'full-platform';
export type DeploymentMode = 'shared-platform' | 'dedicated-managed-server';

export interface HostingPlan {
  id: string;
  name: string;
  activeMemberLabel: string;
  activeMemberLimit: number | null;
  monthlyEur: number;
  setupEur: number;
  isCustom?: boolean;
  infrastructure: string;
  tenants: string;
  storage: string;
  email: string;
  p1Response: string;
  bestFor: string;
}

export interface CommunityTimebankPlan extends HostingPlan {
  annualMonthlyEur: number;
  annualEur: number;
  summary: string;
  included: string[];
  heldBack: string[];
  fairUse: string[];
  upgradeTrigger: string;
}

export interface RecurringOption {
  id: string;
  label: string;
  monthlyEur: number;
  description: string;
}

export interface OneOffOption {
  id: string;
  label: string;
  fixedEur: number;
  description: string;
}

export interface DeploymentModeOption {
  id: DeploymentMode;
  label: string;
  shortLabel: string;
  monthlyEur: number;
  setupEur: number;
  setupLabel: string;
  priceLabel: string;
  startsFromMonthlyEur?: number;
  requiresCustomQuote?: boolean;
  description: string;
  bestFor: string;
}

export interface CompetitorBenchmark {
  segment: string;
  examples: string;
  typicalPricing: string;
  featurePattern: string;
  nexusPosition: string;
}

export const deploymentModes: DeploymentModeOption[] = [
  {
    id: 'shared-platform',
    label: 'Shared managed platform',
    shortLabel: 'Shared platform',
    monthlyEur: 0,
    setupEur: 0,
    setupLabel: 'Shared platform provisioning',
    priceLabel: 'Included',
    description:
      'Included on published full-platform tiers: your tenant runs on the main Project NEXUS production platform with tenant isolation, monitoring, backups, patching, and managed upgrades.',
    bestFor: 'most pilots, charities, local programmes, and funded community services that want the strongest price-to-capability ratio',
  },
  {
    id: 'dedicated-managed-server',
    label: 'Dedicated managed server',
    shortLabel: 'Dedicated server',
    monthlyEur: 0,
    setupEur: 0,
    setupLabel: 'Dedicated infrastructure discovery',
    priceLabel: 'From €650/mo',
    startsFromMonthlyEur: 650,
    requiresCustomQuote: true,
    description:
      'Dedicated server hosting starts from €650/month for an entry-level setup. Final pricing depends on server size, traffic levels, storage, bandwidth, redundancy, security, management, and whether the project needs a single dedicated server, multiple servers, load balancing, database separation, storage scaling, backup systems, security hardening, monitoring, or a full server cluster.',
    bestFor:
      'projects expecting around 100, 1,000, 10,000, 100,000, or 500,000+ users, including heavy-traffic platforms, apps, SaaS products, communities, membership sites, and enterprise workloads',
  },
];

export const communityTimebankPlans: CommunityTimebankPlan[] = [
  {
    id: 'community-edition',
    name: 'Community Edition',
    activeMemberLabel: 'Up to 150 active members',
    activeMemberLimit: 150,
    monthlyEur: 39,
    annualMonthlyEur: 29,
    annualEur: 348,
    setupEur: 0,
    infrastructure: 'Shared NEXUS community cluster',
    tenants: '1 timebank tenant',
    storage: '2 GB',
    email: '1k emails/month',
    p1Response: 'Best-effort triage within 3 business days',
    bestFor: 'new timebanks, unfunded pilots, mutual aid groups, and small neighbourhood projects',
    summary: 'A deliberately lean timebank: exchange, members, groups, events, messaging, admin basics, PWA, backups, and upgrades.',
    included: [
      'Offers, requests, and service listings',
      'Time credit wallet, hour logging, and exchange history',
      'Member directory, invitations, and basic profiles',
      'Groups, simple events, and basic messaging',
      'One standard NEXUS subdomain',
      'Basic branding using logo, colour, and welcome copy',
      'Core admin dashboard, moderation, backups, and quarterly upgrades',
    ],
    heldBack: [
      'Federation, multi-tenant networks, custom domains, and dedicated staging',
      'AI chat, semantic search, recommendations, advanced gamification, and automations',
      'Volunteering programmes, job board, donations, payments, SSO, and custom reports',
    ],
    fairUse: ['150 active members', '2 GB storage', '1,000 outbound emails per month', 'Fair-use moderation and support queue'],
    upgradeTrigger: 'Move to Community Plus when you need reports, a custom domain, donations, or more than 150 active members.',
  },
  {
    id: 'community-plus',
    name: 'Community Plus',
    activeMemberLabel: 'Up to 500 active members',
    activeMemberLimit: 500,
    monthlyEur: 69,
    annualMonthlyEur: 59,
    annualEur: 708,
    setupEur: 150,
    infrastructure: 'Shared NEXUS community cluster',
    tenants: '1 timebank tenant',
    storage: '10 GB',
    email: '5k emails/month',
    p1Response: 'Best-effort triage within 2 business days',
    bestFor: 'funded local timebanks that need reporting, donations, and stronger launch support',
    summary: 'Everything in Community Edition plus practical reporting and the features most funded community teams ask for first.',
    included: [
      'All Community Edition features',
      'Recorded-time exports and monthly coordinator report',
      'Rewards and donation-ready configuration',
      'Custom domain connection',
      'Expanded admin roles and launch checklist',
      'Basic resource library and public landing content',
    ],
    heldBack: [
      'Federation, multi-tenant networks, advanced volunteering, SSO, AI modules, dedicated infrastructure, and bespoke integrations',
      'Mobile app store submission and full compliance evidence packs',
    ],
    fairUse: ['500 active members', '10 GB storage', '5,000 outbound emails per month', 'Two admin users included'],
    upgradeTrigger: 'Move to Community Pro when you need an annual impact pack, multiple embedded timebanks, or a bigger audience.',
  },
  {
    id: 'community-pro',
    name: 'Community Pro',
    activeMemberLabel: 'Up to 1,500 active members',
    activeMemberLimit: 1500,
    monthlyEur: 99,
    annualMonthlyEur: 89,
    annualEur: 1068,
    setupEur: 250,
    infrastructure: 'Shared NEXUS community cluster with reserved capacity',
    tenants: '1 tenant plus public landing space',
    storage: '25 GB',
    email: '20k emails/month',
    p1Response: 'Best-effort triage within 1 business day',
    bestFor: 'larger timebanks that want a serious public presence without buying the full civic platform',
    summary: 'The strongest timebank-only package before a buyer should graduate into the full Project NEXUS platform.',
    included: [
      'All Community Plus features',
      'Public landing page, embedded timebank widgets, and annual impact pack',
      'Advanced exports, retention views, and coordinator summary metrics',
      'Priority launch review and quarterly roadmap call',
      'Room to grow before full platform procurement',
    ],
    heldBack: [
      'Full federation, multi-tenant hierarchy, AI matching, advanced volunteering, marketplace payments, SSO, and custom development',
      'Dedicated VM, custom fork maintenance, and high-touch managed operations',
    ],
    fairUse: ['1,500 active members', '25 GB storage', '20,000 outbound emails per month', 'Quarterly service review'],
    upgradeTrigger: 'Move to full platform hosting when you need multiple communities, federation, AI, volunteering, or bespoke modules.',
  },
];

export const hostingPlans: HostingPlan[] = [
  {
    id: 'spark',
    name: 'Spark',
    activeMemberLabel: 'Up to 100 active members',
    activeMemberLimit: 100,
    monthlyEur: 99,
    setupEur: 0,
    infrastructure: 'Shared container',
    tenants: '1 tenant',
    storage: '5 GB',
    email: '5k emails/month',
    p1Response: 'Best-effort first reply by next business day',
    bestFor: 'small full-platform pilots that need the whole NEXUS module set',
  },
  {
    id: 'community',
    name: 'Community',
    activeMemberLabel: 'Up to 1,000 active members',
    activeMemberLimit: 1000,
    monthlyEur: 299,
    setupEur: 250,
    infrastructure: 'Shared container',
    tenants: 'Up to 3 tenants',
    storage: '25 GB',
    email: '50k emails/month',
    p1Response: 'Target first reply within 1 business day',
    bestFor: 'funded local communities and established nonprofit networks',
  },
  {
    id: 'growth',
    name: 'Growth',
    activeMemberLabel: '1,001 to 10,000 active members',
    activeMemberLimit: 10000,
    monthlyEur: 799,
    setupEur: 500,
    infrastructure: 'Dedicated container',
    tenants: 'Up to 10 tenants',
    storage: '100 GB',
    email: '250k emails/month',
    p1Response: 'Target first reply within 1 business day during agreed business hours',
    bestFor: 'county, city, regional, and multi-programme deployments',
  },
  {
    id: 'scale',
    name: 'Scale',
    activeMemberLabel: '10,001 to 30,000 active members',
    activeMemberLimit: 30000,
    monthlyEur: 1999,
    setupEur: 1000,
    infrastructure: 'Dedicated VM',
    tenants: 'Up to 25 tenants',
    storage: '500 GB',
    email: '1M emails/month',
    p1Response: 'Contracted incident window required for same-day cover',
    bestFor: 'public-sector, civic, and larger multi-community programmes',
  },
  {
    id: 'network',
    name: 'Network',
    activeMemberLabel: '30,001 to 100,000 active members',
    activeMemberLimit: 100000,
    monthlyEur: 4499,
    setupEur: 2000,
    infrastructure: 'VM cluster',
    tenants: 'Up to 100 tenants',
    storage: '2 TB',
    email: '5M emails/month',
    p1Response: 'Major-client support retainer required for P1 cover',
    bestFor: 'national networks, consortia, and serious institutional programmes',
  },
  {
    id: 'enterprise-custom',
    name: 'Enterprise Custom',
    activeMemberLabel: 'Over 100,000 active members or high-traffic network',
    activeMemberLimit: null,
    monthlyEur: 0,
    setupEur: 0,
    isCustom: true,
    infrastructure: 'Bespoke architecture',
    tenants: 'Custom',
    storage: 'Custom',
    email: 'Custom',
    p1Response: 'Custom SLA with contract-funded support cover',
    bestFor: 'national operators, unusually large communities, million-user scenarios, and high-traffic federation networks',
  },
];

export const supportTiers: RecurringOption[] = [
  {
    id: 'standard',
    label: 'Standard support',
    monthlyEur: 0,
    description: 'Async email and GitHub issue support. Solo-led by default; response targets are best-effort unless written order terms say otherwise.',
  },
  {
    id: 'priority',
    label: 'Priority async support',
    monthlyEur: 399,
    description: 'Priority queue, shared channel, monthly office-hours review, and planned-business-day responses during agreed hours.',
  },
  {
    id: 'managed',
    label: 'Managed support retainer',
    monthlyEur: 1499,
    description: 'Named technical lead, regular ops review, release planning, proactive checks, and room to contract specialist help for larger clients.',
  },
  {
    id: 'mission-critical',
    label: 'Major-client support retainer',
    monthlyEur: 4999,
    description: 'Contract-funded support cover for critical services, including agreed escalation windows and external incident partner capacity where required.',
  },
];

export const competitorBenchmarks: CompetitorBenchmark[] = [
  {
    segment: 'Timebanking tools',
    examples: 'hOurworld, TimeOverflow, TOL2, Komunitin',
    typicalPricing: 'Free, community-supported, or association/service-fee funded',
    featurePattern: 'Strong timebanking core, but usually narrower hosting, governance, federation, support, AI, and civic module coverage.',
    nexusPosition: 'Community Edition is not the cheapest possible path; it is a low-cost managed path with backups, upgrades, admin basics, and a clean upgrade route.',
  },
  {
    segment: 'Volunteer management',
    examples: 'Timecounts, GivePulse, VolunteerLocal, Better Impact',
    typicalPricing: 'Free to a few hundred per month, with advanced features and enterprise support commonly gated.',
    featurePattern: 'Good for volunteer scheduling and forms; less complete for time credits, multi-community exchange, federation, civic content, and member social tools.',
    nexusPosition: 'Community Plus and Pro are competitive for timebank-first organisations; the full platform costs more because it includes a much wider civic stack.',
  },
  {
    segment: 'Managed civic/community platforms',
    examples: 'Made Open and bespoke community-platform providers',
    typicalPricing: 'Often several thousand per year to five figures or custom procurement.',
    featurePattern: 'Higher-touch implementation and support, but pricing usually moves quickly into quote-led territory.',
    nexusPosition: 'NEXUS is cheap-to-middle here: transparent entry prices, full platform tiers from EUR99/mo, and custom pricing only when scale or support cover needs discovery.',
  },
  {
    segment: 'Creator and B2B communities',
    examples: 'Circle, Mighty Networks, BetterMode, Hivebrite',
    typicalPricing: 'Common public tiers around low hundreds per month; B2B and enterprise community plans move into high monthly or custom pricing.',
    featurePattern: 'Community spaces, content, and memberships are strong; enterprise features such as SSO, APIs, branded apps, analytics, and premium support are often higher-tier gates.',
    nexusPosition: 'NEXUS is not trying to be a creator community tool. It is cheaper than many B2B community platforms for multi-tenant civic infrastructure and far broader for timebanking.',
  },
];

export const maintenancePlans: RecurringOption[] = [
  {
    id: 'track-latest',
    label: 'Track Latest maintenance',
    monthlyEur: 0,
    description: 'Quarterly upgrade to the latest stable release.',
  },
  {
    id: 'pinned-release',
    label: 'Pinned Release maintenance',
    monthlyEur: 199,
    description: 'Hold a chosen release for up to 12 months with security backports.',
  },
  {
    id: 'custom-fork',
    label: 'Custom Fork maintenance',
    monthlyEur: 599,
    description: 'Monthly upstream rebase and test-suite run for a custom fork.',
  },
  {
    id: 'lts-lock',
    label: 'Long-Term Support lock',
    monthlyEur: 999,
    description: 'Specific version held for 24 months, security patches only.',
  },
];

export const onboardingPackages: OneOffOption[] = [
  {
    id: 'none',
    label: 'Self-start setup',
    fixedEur: 0,
    description: 'Tenant provision, DNS checklist, and light guidance.',
  },
  {
    id: 'quick-start',
    label: 'Quick-start launch',
    fixedEur: 750,
    description: 'Domain, branding, admin training, and one seeded tenant.',
  },
  {
    id: 'standard-launch',
    label: 'Standard launch',
    fixedEur: 2500,
    description: 'Branding, up to three tenants, CSV import, admin training, and post-launch support.',
  },
  {
    id: 'enterprise-launch',
    label: 'Enterprise launch',
    fixedEur: 7500,
    description: 'Discovery, migration, staff training, communications plan, soft launch, and go-live.',
  },
];

export const communityOnboardingPackages: OneOffOption[] = [
  {
    id: 'community-self-start',
    label: 'Self-start community setup',
    fixedEur: 0,
    description: 'Tenant provision, launch checklist, and a short admin handover note.',
  },
  {
    id: 'community-assisted-launch',
    label: 'Assisted community launch',
    fixedEur: 250,
    description: 'One launch clinic, branding setup, member import template, and coordinator checklist.',
  },
  {
    id: 'community-import-launch',
    label: 'Community import launch',
    fixedEur: 650,
    description: 'CSV member import, starter content, coordinator training, and first-month launch check.',
  },
];

export const recurringAddOns: RecurringOption[] = [
  {
    id: 'extra-storage-100gb',
    label: 'Additional 100 GB storage',
    monthlyEur: 25,
    description: 'Extra file and media storage for growing communities.',
  },
  {
    id: 'additional-sub-tenant',
    label: 'Additional sub-tenant slot',
    monthlyEur: 49,
    description: 'One extra community tenant beyond the plan allowance.',
  },
  {
    id: 'dedicated-search',
    label: 'Dedicated Meilisearch instance',
    monthlyEur: 99,
    description: 'Dedicated search infrastructure on Spark or Community.',
  },
  {
    id: 'dedicated-db-schema',
    label: 'Dedicated database schema',
    monthlyEur: 149,
    description: 'Dedicated schema isolation on Spark or Community.',
  },
  {
    id: 'multi-region-replica',
    label: 'Multi-region read replica',
    monthlyEur: 399,
    description: 'Read replica for resilience and geographic performance.',
  },
  {
    id: 'data-residency',
    label: 'Geographic data residency',
    monthlyEur: 199,
    description: 'EU, UK, or US residency choice.',
  },
  {
    id: 'extra-email-250k',
    label: 'Extra outbound email tier',
    monthlyEur: 89,
    description: 'Adds 250k outbound emails per month.',
  },
  {
    id: 'accessible-domain',
    label: 'Accessible frontend custom domain',
    monthlyEur: 25,
    description: 'Host the accessible frontend at accessible.yourdomain.',
  },
  {
    id: 'white-label-attribution-placement',
    label: 'White-label attribution placement',
    monthlyEur: 99,
    description: 'Visual placement options while preserving AGPL Section 7(b) attribution.',
  },
  {
    id: 'bring-your-own-keys',
    label: 'Bring-your-own service keys',
    monthlyEur: -50,
    description: 'Use your own Stripe, OpenAI, Pusher, SendGrid, and FCM keys.',
  },
  {
    id: 'compliance-pack',
    label: 'Compliance pack',
    monthlyEur: 299,
    description: 'SOC 2 evidence pack, GDPR DPIA template, and DPA support.',
  },
  {
    id: 'dedicated-staging',
    label: 'Dedicated staging environment',
    monthlyEur: 199,
    description: 'Separate staging stack for release testing and training.',
  },
];

export const oneOffServices: OneOffOption[] = [
  {
    id: 'federation-onboarding',
    label: 'Federation partner onboarding',
    fixedEur: 1500,
    description: 'Connect one external protocol partner such as Komunitin, CCP, or TimeOverflow.',
  },
  {
    id: 'accessible-rollout',
    label: 'Accessible frontend rollout',
    fixedEur: 1800,
    description: 'Content review, WCAG audit sign-off, and accessibility lead training.',
  },
  {
    id: 'branding-theme-pack',
    label: 'Branding and theme pack',
    fixedEur: 950,
    description: 'Tenant theme, mobile app icons, splash screens, and light/dark tokens.',
  },
  {
    id: 'mobile-app-store-submission',
    label: 'Mobile app store submission',
    fixedEur: 1500,
    description: 'iOS App Store or Google Play submission on your developer account.',
  },
  {
    id: 'small-feature',
    label: 'Small custom feature',
    fixedEur: 1800,
    description: 'One module change, tests, and translation-ready implementation.',
  },
  {
    id: 'custom-federation-adapter',
    label: 'Custom federation adapter',
    fixedEur: 6500,
    description: 'Bespoke protocol adapter, tests, and admin UI.',
  },
  {
    id: 'data-migration',
    label: 'Data migration from existing platform',
    fixedEur: 2500,
    description: 'Migration from CSV exports, spreadsheets, legacy databases, or a custom source system.',
  },
  {
    id: 'sso-saml',
    label: 'SSO or SAML integration',
    fixedEur: 3500,
    description: 'Integration with one identity provider such as council Active Directory.',
  },
];
