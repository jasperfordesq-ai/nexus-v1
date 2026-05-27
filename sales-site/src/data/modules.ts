// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export interface NexusModule {
  id: string;
  name: string;
  group: string;
  description: string;
}

export const nexusModules: NexusModule[] = [
  { id: 'timebanking-engine', name: 'Timebanking Engine', group: 'Core Platform', description: 'Wallet, transactions, broker controls, and equal-time exchange.' },
  { id: 'multi-tenancy', name: 'Multi-Tenancy', group: 'Core Platform', description: 'Unlimited communities with branding, configuration, and parent-child hierarchy.' },
  { id: 'smart-matching', name: 'Smart Matching', group: 'Core Platform', description: 'AI-assisted matching with embeddings and collaborative filtering.' },
  { id: 'real-time-messaging', name: 'Real-Time Messaging', group: 'Core Platform', description: 'Private conversations with WebSocket delivery.' },
  { id: 'pwa-native-mobile', name: 'PWA + Native Mobile App', group: 'Core Platform', description: 'Installable PWA plus Capacitor shells for iOS and Android.' },
  { id: 'federation-api', name: 'Federation API', group: 'Core Platform', description: 'Nexus, Komunitin, Credit Commons / CEN, and TimeOverflow interoperability.' },
  { id: 'service-listings', name: 'Service Listings', group: 'Member Experience', description: 'Offers, requests, browsing, categories, and exchange discovery.' },
  { id: 'marketplace', name: 'Marketplace', group: 'Member Experience', description: 'Classifieds, Stripe Connect payouts, and AI reply suggestions.' },
  { id: 'exchange-workflow', name: 'Exchange Workflow', group: 'Member Experience', description: 'Structured exchange lifecycle with broker approval.' },
  { id: 'group-exchanges', name: 'Group Exchanges', group: 'Member Experience', description: 'Bulk community service exchange workflows.' },
  { id: 'donations', name: 'Donations', group: 'Member Experience', description: 'One-off and recurring donations with Stripe and organisation dashboards.' },
  { id: 'social-feed', name: 'Social Feed', group: 'Member Experience', description: 'Posts, comments, polls, hashtags, voice messages, media, previews, and mentions.' },
  { id: 'stories', name: 'Stories', group: 'Member Experience', description: 'Ephemeral photo and video stories with reactions, polls, and highlights.' },
  { id: 'presence-system', name: 'Presence System', group: 'Member Experience', description: 'Online/offline presence with privacy controls.' },
  { id: 'events-groups', name: 'Events & Groups', group: 'Member Experience', description: 'Community gatherings and interest-based groups.' },
  { id: 'connections', name: 'Connections', group: 'Member Experience', description: 'Follow and connect with community members.' },
  { id: 'members-directory', name: 'Members Directory', group: 'Member Experience', description: 'Browse, filter, and discover members.' },
  { id: 'availability-scheduling', name: 'Availability Scheduling', group: 'Member Experience', description: 'Timezone-aware time slots for matching and exchange planning.' },
  { id: 'gamification', name: 'Gamification', group: 'Member Experience', description: 'Badges, journeys, XP, leaderboards, streaks, XP shop, and competitions.' },
  { id: 'identity-verification', name: 'Identity Verification', group: 'Member Experience', description: 'Optional Stripe Identity verification and verified-member badges.' },
  { id: 'goals-impact', name: 'Goals & Impact', group: 'Member Experience', description: 'Personal goals, deliverables, community impact, and mentoring.' },
  { id: 'ideation-challenges', name: 'Ideation Challenges', group: 'Member Experience', description: 'Campaigns, ideas, voting, and outcomes.' },
  { id: 'volunteering', name: 'Volunteering', group: 'Member Experience', description: 'Opportunities, hours, check-ins, expenses, certificates, wellbeing, alerts, and organisation wallets.' },
  { id: 'job-vacancies', name: 'Job Vacancies', group: 'Member Experience', description: 'Recruitment module with alerts, analytics, and RSS/JSON syndication.' },
  { id: 'organisations', name: 'Organisations', group: 'Member Experience', description: 'Organisation profiles, sub-accounts, and dedicated wallets.' },
  { id: 'sub-accounts', name: 'Sub-Accounts / Family Accounts', group: 'Member Experience', description: 'Parent-child account relationships.' },
  { id: 'reviews-polls', name: 'Reviews, Endorsements & Polls', group: 'Member Experience', description: 'Community trust and feedback workflows.' },
  { id: 'blog', name: 'Blog', group: 'Content & Communication', description: 'Tenant content management and community news.' },
  { id: 'resources-kb', name: 'Resources & Knowledge Base', group: 'Content & Communication', description: 'Structured articles and shared libraries.' },
  { id: 'help-center', name: 'Help Center', group: 'Content & Communication', description: 'Documentation hub and FAQ.' },
  { id: 'newsletter-system', name: 'Newsletter System', group: 'Content & Communication', description: 'Campaigns, A/B tests, smart segments, recurring sends, targeting, and analytics.' },
  { id: 'ai-chat', name: 'AI Chat', group: 'Content & Communication', description: 'OpenAI-powered assistant for platform guidance.' },
  { id: 'legal-hub', name: 'Legal Hub', group: 'Content & Communication', description: 'Versioned legal documents, acceptance gates, and audit trail.' },
  { id: 'semantic-search', name: 'Semantic Search', group: 'AI & Recommendations', description: 'Meilisearch with synonyms and tenant isolation.' },
  { id: 'collaborative-filtering', name: 'Collaborative Filtering', group: 'AI & Recommendations', description: 'Recommendations from real interaction data.' },
  { id: 'semantic-embeddings', name: 'Semantic Embeddings', group: 'AI & Recommendations', description: 'OpenAI-powered listing and member matching.' },
  { id: 'edgerank-feed', name: 'EdgeRank Feed', group: 'AI & Recommendations', description: 'Time-decay, affinity, and engagement-weighted feed ranking.' },
  { id: 'match-community-rank', name: 'MatchRank & CommunityRank', group: 'AI & Recommendations', description: 'Bayesian quality scoring with Wilson confidence intervals.' },
  { id: 'group-recommendations', name: 'Group Recommendations', group: 'AI & Recommendations', description: 'Trending and affinity-based group discovery.' },
  { id: 'match-learning', name: 'Match Learning', group: 'AI & Recommendations', description: 'Feedback loop that improves recommendations over time.' },
  { id: 'algorithm-health', name: 'Algorithm Health Dashboard', group: 'AI & Recommendations', description: 'Admin monitoring and tuning for ranking systems.' },
  { id: 'enterprise-security', name: 'Enterprise Security', group: 'Operations & Trust', description: 'CSRF, rate limiting, TOTP, WebAuthn, CSP, CORS, validation, and invite controls.' },
  { id: 'gdpr-suite', name: 'GDPR Compliance Suite', group: 'Operations & Trust', description: 'Data requests, cookie consent, breach tracking, and audit log.' },
  { id: 'safeguarding', name: 'Safeguarding Module', group: 'Operations & Trust', description: 'Flagged content review, incident reporting, and safety dashboards.' },
  { id: 'enterprise-rbac', name: 'Enterprise RBAC', group: 'Operations & Trust', description: 'Role-based access control across platform modules.' },
  { id: 'crm', name: 'CRM', group: 'Operations & Trust', description: 'Admin contact management, notes, tasks, tags, and activity timelines.' },
  { id: 'fraud-abuse', name: 'Fraud & Abuse Detection', group: 'Operations & Trust', description: 'Suspicious activity alerts and moderation support.' },
  { id: 'insurance-tracking', name: 'Insurance Certificate Tracking', group: 'Operations & Trust', description: 'Volunteer insurance management and verification.' },
  { id: 'wcag-accessibility', name: 'WCAG 2.1 AA', group: 'Operations & Trust', description: 'Accessible UI targets and ongoing audit workflow.' },
  { id: 'eleven-languages', name: '11 Languages', group: 'Operations & Trust', description: 'English, Irish, German, French, Italian, Portuguese, Spanish, Dutch, Polish, Japanese, and Arabic RTL.' },
  { id: 'seo-prerendering', name: 'Self-Hosted SEO Pre-Rendering', group: 'Operations & Trust', description: 'Playwright pre-rendering, structured data, sitemaps, geo tags, freshness, and soft-404 prevention.' },
  { id: 'onboarding-wizard', name: 'Onboarding Wizard', group: 'Operations & Trust', description: 'Guided new member experience.' },
  { id: 'admin-panel', name: 'Admin Panel', group: 'Operations & Trust', description: 'Algorithm controls, cron monitoring, and email deliverability.' },
  { id: 'tenant-hierarchy', name: 'Tenant Hierarchy', group: 'Operations & Trust', description: 'Parent-child relationships and per-tenant feature toggling.' },
  { id: 'openapi', name: 'OpenAPI 3.0', group: 'Operations & Trust', description: 'Full API specification and Swagger UI docs.' },
  { id: 'dockerized', name: 'Fully Dockerized', group: 'Operations & Trust', description: 'Repeatable local development and production deployment.' },
];

export const nexusModuleGroups = Array.from(new Set(nexusModules.map((module) => module.group)));
