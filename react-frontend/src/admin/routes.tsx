// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Routes Definition
 * Maps all admin URL paths to their React components.
 * Uses lazy loading for all module pages.
 */

import { Suspense, lazy } from 'react';
import { Route, Navigate } from 'react-router-dom';
import { LoadingScreen } from '@/components/feedback';
import { useTenant } from '@/contexts';
import { SuperAdminRoute } from './SuperAdminRoute';
import type { TenantFeatures } from '@/types';

/** Small wrapper so Navigate targets can use tenantPath() inside Route elements. */
function TenantRedirect({ to }: { to: string }) {
  const { tenantPath } = useTenant();
  return <Navigate to={tenantPath(to)} replace />;
}

/**
 * Route element that renders children only when a feature is enabled.
 * When disabled, redirects to the admin 404 page so the URL resolves
 * cleanly without leaking module UI to tenants that lack the feature.
 */
function FeatureGatedElement({
  feature,
  children,
}: {
  feature: keyof TenantFeatures;
  children: React.ReactNode;
}) {
  const { hasFeature, tenantPath } = useTenant();
  if (!hasFeature(feature)) {
    return <Navigate to={tenantPath('/admin/not-found')} replace />;
  }
  return <>{children}</>;
}

// Lazy-loaded admin pages
const AdminDashboard = lazy(() => import('./modules/dashboard/AdminDashboard'));
const UserList = lazy(() => import('./modules/users/UserList'));
const TenantFeatures = lazy(() => import('./modules/config/TenantFeatures'));
const ModuleConfiguration = lazy(() => import('./modules/config/ModuleConfiguration'));
const UserCreate = lazy(() => import('./modules/users/UserCreate'));
const UserEdit = lazy(() => import('./modules/users/UserEdit'));
const UserPermissions = lazy(() => import('./modules/users/UserPermissions'));
const ListingsAdmin = lazy(() => import('./modules/listings/ListingsAdmin'));
const ActivityLog = lazy(() => import('./modules/system/ActivityLog'));
const CategoriesAdmin = lazy(() => import('./modules/categories/CategoriesAdmin'));
const CronJobs = lazy(() => import('./modules/system/CronJobs'));
const CronJobLogs = lazy(() => import('./modules/system/CronJobLogs'));
const CronJobSettings = lazy(() => import('./modules/system/CronJobSettings'));
const CronJobSetup = lazy(() => import('./modules/system/CronJobSetup'));
const BlogAdmin = lazy(() => import('./modules/blog/BlogAdmin'));
const BlogPostForm = lazy(() => import('./modules/blog/BlogPostForm'));
const SmartMatchingOverview = lazy(() => import('./modules/matching/SmartMatchingOverview'));
const MatchingConfig = lazy(() => import('./modules/matching/MatchingConfig'));
const MatchingAnalytics = lazy(() => import('./modules/matching/MatchingAnalytics'));
const MatchApprovals = lazy(() => import('./modules/matching/MatchApprovals'));
const MatchDetail = lazy(() => import('./modules/matching/MatchDetail'));
const TimebankingDashboard = lazy(() => import('./modules/timebanking/TimebankingDashboard'));
const FraudAlerts = lazy(() => import('./modules/timebanking/FraudAlerts'));
const OrgWallets = lazy(() => import('./modules/timebanking/OrgWallets'));
const UserReport = lazy(() => import('./modules/timebanking/UserReport'));
const StartingBalances = lazy(() => import('./modules/timebanking/StartingBalances'));
// admin/modules/broker/* retired — broker control panel lives at /broker/*
// (see react-frontend/src/broker/pages/). Legacy /admin/broker-controls/*
// URLs redirect via the TenantRedirect Route below.
const GamificationHub = lazy(() => import('./modules/gamification/GamificationHub'));
const CampaignList = lazy(() => import('./modules/gamification/CampaignList'));
const CampaignForm = lazy(() => import('./modules/gamification/CampaignForm'));
const GamificationAnalytics = lazy(() => import('./modules/gamification/GamificationAnalytics'));
const CustomBadges = lazy(() => import('./modules/gamification/CustomBadges'));
const BadgeConfiguration = lazy(() => import('./modules/gamification/BadgeConfiguration'));
const CreateBadge = lazy(() => import('./modules/gamification/CreateBadge'));
const GroupList = lazy(() => import('./modules/groups/GroupList'));
const GroupAnalytics = lazy(() => import('./modules/groups/GroupAnalytics'));
const GroupApprovals = lazy(() => import('./modules/groups/GroupApprovals'));
const GroupModeration = lazy(() => import('./modules/groups/GroupModeration'));
const GroupTypes = lazy(() => import('./modules/groups/GroupTypes'));
const GroupDetail = lazy(() => import('./modules/groups/GroupDetail'));
const GroupEdit = lazy(() => import('./modules/groups/GroupEdit'));
const GroupRecommendations = lazy(() => import('./modules/groups/GroupRecommendations'));
const GroupRanking = lazy(() => import('./modules/groups/GroupRanking'));
const GroupGeocode = lazy(() => import('./modules/groups/GroupGeocode'));

// Enterprise module
const EnterpriseDashboard = lazy(() => import('./modules/enterprise/EnterpriseDashboard'));
const RoleList = lazy(() => import('./modules/enterprise/RoleList'));
const RoleForm = lazy(() => import('./modules/enterprise/RoleForm'));
const PermissionBrowser = lazy(() => import('./modules/enterprise/PermissionBrowser'));
const GdprDashboard = lazy(() => import('./modules/enterprise/GdprDashboard'));
const FadpAdminPage = lazy(() => import('./modules/legal/FadpAdminPage'));
const GdprRequests = lazy(() => import('./modules/enterprise/GdprRequests'));
const GdprConsents = lazy(() => import('./modules/enterprise/GdprConsents'));
const GdprBreaches = lazy(() => import('./modules/enterprise/GdprBreaches'));
const GdprAuditLog = lazy(() => import('./modules/enterprise/GdprAuditLog'));
const SystemMonitoring = lazy(() => import('./modules/enterprise/SystemMonitoring'));
const HealthCheck = lazy(() => import('./modules/enterprise/HealthCheck'));
const ErrorLogs = lazy(() => import('./modules/enterprise/ErrorLogs'));
const SystemConfig = lazy(() => import('./modules/enterprise/SystemConfig'));
const SecretsVault = lazy(() => import('./modules/enterprise/SecretsVault'));
const LegalDocList = lazy(() => import('./modules/enterprise/LegalDocList'));
const LegalDocForm = lazy(() => import('./modules/enterprise/LegalDocForm'));
const LegalDocVersionList = lazy(() => import('./modules/enterprise/LegalDocVersionList'));
const LegalDocComplianceDashboard = lazy(() => import('./modules/enterprise/LegalDocComplianceDashboard'));
const GdprRequestDetail = lazy(() => import('./modules/enterprise/GdprRequestDetail'));
const GdprRequestCreate = lazy(() => import('./modules/enterprise/GdprRequestCreate'));
const GdprConsentTypes = lazy(() => import('./modules/enterprise/GdprConsentTypes'));
const GdprBreachDetail = lazy(() => import('./modules/enterprise/GdprBreachDetail'));
const LogFiles = lazy(() => import('./modules/enterprise/LogFiles'));
const LogFileViewer = lazy(() => import('./modules/enterprise/LogFileViewer'));
const SystemRequirements = lazy(() => import('./modules/enterprise/SystemRequirements'));
const FeatureFlags = lazy(() => import('./modules/enterprise/FeatureFlags'));

// Performance module
const PerformanceDashboard = lazy(() => import('./modules/performance/PerformanceDashboard'));

// Newsletter module
const NewsletterList = lazy(() => import('./modules/newsletters/NewsletterList'));
const NewsletterForm = lazy(() => import('./modules/newsletters/NewsletterForm'));
const Subscribers = lazy(() => import('./modules/newsletters/Subscribers'));
const Segments = lazy(() => import('./modules/newsletters/Segments'));
const SegmentForm = lazy(() => import('./modules/newsletters/SegmentForm'));
const Templates = lazy(() => import('./modules/newsletters/Templates'));
const TemplateForm = lazy(() => import('./modules/newsletters/TemplateForm'));
const NewsletterAnalytics = lazy(() => import('./modules/newsletters/NewsletterAnalytics'));
const NewsletterBounces = lazy(() => import('./modules/newsletters/NewsletterBounces'));
const NewsletterSendTimeOptimizer = lazy(() => import('./modules/newsletters/NewsletterSendTimeOptimizer'));
const NewsletterDiagnostics = lazy(() => import('./modules/newsletters/NewsletterDiagnostics'));
const NewsletterStats = lazy(() => import('./modules/newsletters/NewsletterStats'));
const NewsletterActivity = lazy(() => import('./modules/newsletters/NewsletterActivity'));

// Volunteering module
const VolunteeringOverview = lazy(() => import('./modules/volunteering/VolunteeringOverview'));
const VolunteerApprovals = lazy(() => import('./modules/volunteering/VolunteerApprovals'));
const VolunteerOrganizations = lazy(() => import('./modules/volunteering/VolunteerOrganizations'));
const VolunteerExpenses = lazy(() => import('./modules/volunteering/VolunteerExpenses'));
const VolunteerTraining = lazy(() => import('./modules/volunteering/VolunteerTraining'));
const VolunteerSafeguarding = lazy(() => import('./modules/volunteering/VolunteerSafeguarding'));
const VolunteerHoursAudit = lazy(() => import('./modules/volunteering/VolunteerHoursAudit'));
const VolunteerGivingDays = lazy(() => import('./modules/volunteering/VolunteerGivingDays'));
const VolunteerConsents = lazy(() => import('./modules/volunteering/VolunteerConsents'));
const VolunteerProjects = lazy(() => import('./modules/volunteering/VolunteerProjects'));
const VolunteerConfig = lazy(() => import('./modules/volunteering/VolunteerConfig'));
const CaringCommunityAdmin = lazy(() => import('./modules/caring-community/CaringCommunityAdmin'));
const CaringCommunityWorkflowPage = lazy(() => import('./modules/caring-community/CaringCommunityWorkflowPage'));
const LoyaltyAdminPage = lazy(() => import('./modules/caring-community/LoyaltyAdminPage'));
const HourTransferAdminPage = lazy(() => import('./modules/caring-community/HourTransferAdminPage'));
const SafeguardingReportsAdminPage = lazy(() => import('./modules/caring-community/SafeguardingReportsAdminPage'));
const CareProviderAdminPage = lazy(() => import('./modules/caring-community/CareProviderAdminPage'));
const TrustTierAdminPage = lazy(() => import('./modules/caring-community/TrustTierAdminPage'));
const KpiBaselineAdminPage = lazy(() => import('./modules/caring-community/KpiBaselineAdminPage'));
const EmergencyAlertAdminPage = lazy(() => import('./modules/caring-community/EmergencyAlertAdminPage'));
const MunicipalSurveyAdminPage = lazy(() => import('./modules/caring-community/MunicipalSurveyAdminPage'));
const ProjectAnnouncementsAdminPage = lazy(() => import('./modules/caring-community/ProjectAnnouncementsAdminPage'));
const RegionalPointsAdminPage = lazy(() => import('./modules/caring-community/RegionalPointsAdminPage'));
const MunicipalVerificationAdminPage = lazy(() => import('./modules/caring-community/MunicipalVerificationAdminPage'));
const SmartNudgesAdminPage = lazy(() => import('./modules/caring-community/SmartNudgesAdminPage'));
const ResearchPartnershipsAdminPage = lazy(() => import('./modules/caring-community/ResearchPartnershipsAdminPage'));
const WarmthPassAdminPage = lazy(() => import('./modules/caring-community/WarmthPassAdminPage'));
const CareRecipientCirclePage = lazy(() => import('./modules/caring-community/CareRecipientCirclePage'));
const MunicipalRoiAdminPage = lazy(() => import('./modules/caring-community/MunicipalRoiAdminPage'));
const SubRegionsAdminPage = lazy(() => import('./modules/caring-community/SubRegionsAdminPage'));
const PilotScoreboardAdminPage = lazy(() => import('./modules/caring-community/PilotScoreboardAdminPage'));
const OperatingPolicyAdminPage = lazy(() => import('./modules/caring-community/OperatingPolicyAdminPage'));
const DisclosurePackAdminPage = lazy(() => import('./modules/caring-community/DisclosurePackAdminPage'));

// Advertising module
const AdCampaignAdminPage = lazy(() => import('./modules/advertising/AdCampaignAdminPage'));
const PushCampaignAdminPage = lazy(() => import('./modules/advertising/PushCampaignAdminPage'));

// AI / KI-Agents module
const KiAgentAdminPage = lazy(() => import('./modules/ai/KiAgentAdminPage'));

// AG61 — KI-Agenten new framework (definitions, proposals, runs)
const AgentsAdminPage = lazy(() => import('./modules/agents/AgentsAdminPage'));
const AgentProposalsPage = lazy(() => import('./modules/agents/AgentProposalsPage'));
const AgentRunsPage = lazy(() => import('./modules/agents/AgentRunsPage'));

// Regional analytics
const RegionalAnalyticsPage = lazy(() => import('./modules/analytics/RegionalAnalyticsPage'));

// Platform — pilot inquiries (super-admin)
const PilotInquiryAdminPage = lazy(() => import('./modules/super/PilotInquiryAdminPage'));

// AG44 — Tenant provisioning queue (super-admin)
const ProvisioningRequestsPage = lazy(() => import('./modules/provisioning/ProvisioningRequestsPage'));

// Events module
const EventsAdmin = lazy(() => import('./modules/events/EventsAdmin'));

// Polls module
const PollsAdmin = lazy(() => import('./modules/polls/PollsAdmin'));

// Goals module
const GoalsAdmin = lazy(() => import('./modules/goals/GoalsAdmin'));

// Resources / Knowledge Base module
const ResourcesAdmin = lazy(() => import('./modules/resources/ResourcesAdmin'));
const KBArticleForm = lazy(() => import('./modules/resources/KBArticleForm'));
const ResourceCategoriesAdmin = lazy(() => import('./modules/resources/ResourceCategoriesAdmin'));

// Jobs module
const JobsAdmin = lazy(() => import('./modules/jobs/JobsAdmin'));
const JobModerationQueue = lazy(() => import('./modules/jobs/JobModerationQueue'));
const JobBiasAudit = lazy(() => import('./modules/jobs/JobBiasAudit'));
const JobPipelineOverview = lazy(() => import('./modules/jobs/JobPipelineOverview'));
const JobTemplatesAdmin = lazy(() => import('./modules/jobs/JobTemplatesAdmin'));

// Translation config module
const TranslationConfig = lazy(() => import('./modules/config/TranslationConfig'));

// Marketplace module
const MarketplaceAdmin = lazy(() => import('./modules/marketplace/MarketplaceAdmin'));
const MarketplaceModerationPage = lazy(() => import('./modules/marketplace/MarketplaceModerationPage'));
const MarketplaceSellerAdmin = lazy(() => import('./modules/marketplace/MarketplaceSellerAdmin'));
const AdminCouponsPage = lazy(() => import('./modules/marketplace/AdminCouponsPage'));

// Ideation / Challenges module
const IdeationAdmin = lazy(() => import('./modules/ideation/IdeationAdmin'));

// Federation module
const FederationSettings = lazy(() => import('./modules/federation/FederationSettings'));
const FederationAggregatesPage = lazy(() => import('./modules/federation/FederationAggregatesPage'));
const Partnerships = lazy(() => import('./modules/federation/Partnerships'));
const PartnerDirectory = lazy(() => import('./modules/federation/PartnerDirectory'));
const MyProfile = lazy(() => import('./modules/federation/MyProfile'));
const FederationAnalytics = lazy(() => import('./modules/federation/FederationAnalytics'));
const ApiKeys = lazy(() => import('./modules/federation/ApiKeys'));
const CreateApiKey = lazy(() => import('./modules/federation/CreateApiKey'));
const DataManagement = lazy(() => import('./modules/federation/DataManagement'));
const CreditAgreements = lazy(() => import('./modules/federation/CreditAgreements'));
const Neighborhoods = lazy(() => import('./modules/federation/Neighborhoods'));
const ExternalPartners = lazy(() => import('./modules/federation/ExternalPartners'));
const Webhooks = lazy(() => import('./modules/federation/Webhooks'));
const ApiDocumentation = lazy(() => import('./modules/federation/ApiDocumentation'));
const FederationActivityFeed = lazy(() => import('./modules/federation/ActivityFeed'));
const CreditCommonsConfig = lazy(() => import('./modules/federation/CreditCommonsConfig'));

// Safeguarding module
const SafeguardingDashboard = lazy(() => import('./modules/safeguarding/SafeguardingDashboard'));
const SafeguardingOptionsAdmin = lazy(() => import('./modules/safeguarding/SafeguardingOptionsAdmin'));

// Onboarding module
const OnboardingSettings = lazy(() => import('./modules/system/OnboardingSettings'));

// Advanced/SEO module
const AiSettings = lazy(() => import('./modules/advanced/AiSettings'));
const EmailSettings = lazy(() => import('./modules/advanced/EmailSettings'));
const AlgorithmSettings = lazy(() => import('./modules/advanced/AlgorithmSettings'));
const SeoOverview = lazy(() => import('./modules/advanced/SeoOverview'));
const SeoAudit = lazy(() => import('./modules/advanced/SeoAudit'));
const Redirects = lazy(() => import('./modules/advanced/Redirects'));
const Error404Tracking = lazy(() => import('./modules/advanced/Error404Tracking'));
const MatchDebugPanel = lazy(() => import('./modules/advanced/MatchDebugPanel'));

// CRM module
const CrmDashboard = lazy(() => import('./modules/crm/CrmDashboard'));
const MemberNotes = lazy(() => import('./modules/crm/MemberNotes'));
const CoordinatorTasks = lazy(() => import('./modules/crm/CoordinatorTasks'));
const OnboardingFunnel = lazy(() => import('./modules/crm/OnboardingFunnel'));
const MemberTags = lazy(() => import('./modules/crm/MemberTags'));
const ActivityTimeline = lazy(() => import('./modules/crm/ActivityTimeline'));

// System tools
const AdminSettings = lazy(() => import('./modules/system/AdminSettings'));
const RegistrationPolicySettings = lazy(() => import('./modules/system/RegistrationPolicySettings'));
const TestRunner = lazy(() => import('./modules/system/TestRunner'));
const SeedGenerator = lazy(() => import('./modules/system/SeedGenerator'));
const WebpConverter = lazy(() => import('./modules/system/WebpConverter'));
const ImageSettings = lazy(() => import('./modules/system/ImageSettings'));
const NativeApp = lazy(() => import('./modules/system/NativeApp'));
const BlogRestore = lazy(() => import('./modules/system/BlogRestore'));

// Community tools
const SmartMatchUsers = lazy(() => import('./modules/community/SmartMatchUsers'));
const SmartMatchMonitoring = lazy(() => import('./modules/community/SmartMatchMonitoring'));

// Deliverability module
const DeliverabilityDashboard = lazy(() => import('./modules/deliverability/DeliverabilityDashboard'));
const DeliverablesList = lazy(() => import('./modules/deliverability/DeliverablesList'));
const CreateDeliverable = lazy(() => import('./modules/deliverability/CreateDeliverable'));
const EditDeliverable = lazy(() => import('./modules/deliverability/EditDeliverable'));
const DeliverabilityAnalytics = lazy(() => import('./modules/deliverability/DeliverabilityAnalytics'));

// Diagnostics module
const MatchingDiagnostic = lazy(() => import('./modules/diagnostics/MatchingDiagnostic'));
const NexusScoreAnalytics = lazy(() => import('./modules/diagnostics/NexusScoreAnalytics'));

// Analytics & Reporting
const CommunityAnalytics = lazy(() => import('./modules/analytics/CommunityAnalytics'));
const ImpactReport = lazy(() => import('./modules/impact/ImpactReport'));

// Admin Reports & Moderation Queue (A1-A5, A7)
const MemberReportsPage = lazy(() => import('./modules/reports/MemberReportsPage'));
const HoursReportsPage = lazy(() => import('./modules/reports/HoursReportsPage'));
const InactiveMembersPage = lazy(() => import('./modules/reports/InactiveMembersPage'));
const ModerationQueuePage = lazy(() => import('./modules/reports/ModerationQueuePage'));
const MunicipalImpactReportsPage = lazy(() => import('./modules/reports/MunicipalImpactReportsPage'));

// National (KISS Foundation) module
const NationalKissDashboardPage = lazy(() => import('./modules/national/NationalKissDashboardPage'));

// Admin 404
const AdminNotFound = lazy(() => import('./modules/AdminNotFound'));

// Moderation module
const FeedModeration = lazy(() => import('./modules/moderation/FeedModeration'));
const CommentsModeration = lazy(() => import('./modules/moderation/CommentsModeration'));
const ReviewsModeration = lazy(() => import('./modules/moderation/ReviewsModeration'));
const ReportsManagement = lazy(() => import('./modules/moderation/ReportsManagement'));

// Super Admin module — all implementations live in modules/super/
const SuperDashboard = lazy(() => import('./modules/super/SuperDashboard'));
const TenantListAdmin = lazy(() => import('./modules/super/TenantList'));
const TenantForm = lazy(() => import('./modules/super/TenantForm'));
const TenantShow = lazy(() => import('./modules/super/TenantShow'));
const TenantHierarchy = lazy(() => import('./modules/super/TenantHierarchy'));
const SuperUserList = lazy(() => import('./modules/super/SuperUserList'));
const SuperUserForm = lazy(() => import('./modules/super/SuperUserForm'));
const UserShow = lazy(() => import('./modules/super/UserShow'));
const BulkOperations = lazy(() => import('./modules/super/BulkOperations'));
const SuperAuditLog = lazy(() => import('./modules/super/SuperAuditLog'));
const FederationControls = lazy(() => import('./modules/super/FederationControls'));
const FederationWhitelist = lazy(() => import('./modules/super/FederationWhitelist'));
const SuperPartnerships = lazy(() => import('./modules/super/SuperPartnerships'));
const FederationAuditLog = lazy(() => import('./modules/super/FederationAuditLog'));
const FederationTenantFeatures = lazy(() => import('./modules/super/FederationTenantFeatures'));

// AG58 — Member Premium admin
const MemberPremiumAdminPage = lazy(() => import('./modules/premium/MemberPremiumAdminPage'));
const MemberPremiumSubscribersPage = lazy(() => import('./modules/premium/MemberPremiumSubscribersPage'));

// Billing module
const BillingPage = lazy(() => import('./modules/billing/BillingPage'));
const PlanSelector = lazy(() => import('./modules/billing/PlanSelector'));
const InvoiceHistory = lazy(() => import('./modules/billing/InvoiceHistory'));
const CheckoutReturn = lazy(() => import('./modules/billing/CheckoutReturn'));
const BillingControl = lazy(() => import('./modules/billing/BillingControl'));
const RevenueDashboard = lazy(() => import('./modules/billing/RevenueDashboard'));

// Content module
const PagesAdmin = lazy(() => import('./modules/content/PagesAdmin'));
const PageBuilder = lazy(() => import('./modules/content/PageBuilder'));
const MenusAdmin = lazy(() => import('./modules/content/MenusAdmin'));
const MenuBuilder = lazy(() => import('./modules/content/MenuBuilder'));
const AttributesAdmin = lazy(() => import('./modules/content/AttributesAdmin'));
const PlansAdmin = lazy(() => import('./modules/content/PlansAdmin'));
const PlanForm = lazy(() => import('./modules/content/PlanForm'));
const SubscriptionsAdmin = lazy(() => import('./modules/content/Subscriptions'));
const LandingPageBuilder = lazy(() => import('./modules/content/LandingPageBuilder'));
// AG60 — API Partners admin (Partner API integration management)
const ApiPartnersAdminPage = lazy(() => import('./modules/api-partners/ApiPartnersAdminPage'));
// AG59 — Paid Regional Analytics admin (super-admin only)
const RegionalAnalyticsAdminPage = lazy(() => import('./modules/regional-analytics/RegionalAnalyticsAdminPage'));

// Wrap lazy components in Suspense
function Lazy({ children }: { children: React.ReactNode }) {
  return <Suspense fallback={<LoadingScreen />}>{children}</Suspense>;
}

/**
 * All admin routes — rendered inside AdminLayout + AdminRoute guard.
 * Matches the PHP admin navigation structure exactly.
 */
export function AdminRoutes() {
  return (
    <>
      {/* Dashboard */}
      <Route index element={<Lazy><AdminDashboard /></Lazy>} />

      {/* ─── USERS ─── */}
      <Route path="users" element={<Lazy><UserList /></Lazy>} />
      <Route path="users/create" element={<Lazy><UserCreate /></Lazy>} />
      <Route path="users/:id/edit" element={<Lazy><UserEdit /></Lazy>} />
      <Route path="users/:id/permissions" element={<Lazy><UserPermissions /></Lazy>} />

      {/* ─── CRM ─── */}
      <Route path="crm" element={<Lazy><CrmDashboard /></Lazy>} />
      <Route path="crm/notes" element={<Lazy><MemberNotes /></Lazy>} />
      <Route path="crm/tasks" element={<Lazy><CoordinatorTasks /></Lazy>} />
      <Route path="crm/tags" element={<Lazy><MemberTags /></Lazy>} />
      <Route path="crm/timeline" element={<Lazy><ActivityTimeline /></Lazy>} />
      <Route path="crm/funnel" element={<Lazy><OnboardingFunnel /></Lazy>} />

      {/* ─── LISTINGS ─── */}
      <Route path="listings" element={<Lazy><ListingsAdmin /></Lazy>} />

      {/* ─── CONTENT ─── */}
      <Route path="blog" element={<Lazy><BlogAdmin /></Lazy>} />
      <Route path="blog/create" element={<Lazy><BlogPostForm /></Lazy>} />
      <Route path="blog/edit/:id" element={<Lazy><BlogPostForm /></Lazy>} />
      <Route path="pages" element={<Lazy><PagesAdmin /></Lazy>} />
      <Route path="pages/builder/:id" element={<Lazy><PageBuilder /></Lazy>} />
      <Route path="menus" element={<Lazy><MenusAdmin /></Lazy>} />
      <Route path="menus/builder/:id" element={<Lazy><MenuBuilder /></Lazy>} />
      <Route path="categories" element={<Lazy><CategoriesAdmin /></Lazy>} />
      <Route path="categories/create" element={<Lazy><CategoriesAdmin /></Lazy>} />
      <Route path="categories/edit/:id" element={<Lazy><CategoriesAdmin /></Lazy>} />
      <Route path="attributes" element={<Lazy><AttributesAdmin /></Lazy>} />
      <Route path="landing-page" element={<Lazy><LandingPageBuilder /></Lazy>} />

      {/* ─── ENGAGEMENT ─── */}
      <Route path="gamification" element={<Lazy><GamificationHub /></Lazy>} />
      <Route path="gamification/campaigns" element={<Lazy><CampaignList /></Lazy>} />
      <Route path="gamification/campaigns/create" element={<Lazy><CampaignForm /></Lazy>} />
      <Route path="gamification/campaigns/edit/:id" element={<Lazy><CampaignForm /></Lazy>} />
      <Route path="gamification/analytics" element={<Lazy><GamificationAnalytics /></Lazy>} />
      <Route path="gamification/badge-config" element={<Lazy><BadgeConfiguration /></Lazy>} />
      <Route path="custom-badges" element={<Lazy><CustomBadges /></Lazy>} />
      <Route path="custom-badges/create" element={<Lazy><CreateBadge /></Lazy>} />

      {/* ─── MATCHING & BROKER ─── */}
      <Route path="smart-matching" element={<Lazy><SmartMatchingOverview /></Lazy>} />
      <Route path="smart-matching/analytics" element={<Lazy><MatchingAnalytics /></Lazy>} />
      <Route path="smart-matching/configuration" element={<Lazy><MatchingConfig /></Lazy>} />
      <Route path="match-approvals" element={<Lazy><MatchApprovals /></Lazy>} />
      <Route path="match-approvals/:id" element={<Lazy><MatchDetail /></Lazy>} />
      {/* /admin/broker-controls/* retired — broker control panel lives at
          /broker/* (see react-frontend/src/broker/). Anyone landing on a
          legacy bookmark is redirected to the new home, preserving the
          tenant slug via TenantRedirect. */}
      <Route path="broker-controls" element={<TenantRedirect to="/broker" />} />
      <Route path="broker-controls/*" element={<TenantRedirect to="/broker" />} />

      {/* ─── MODERATION ─── */}
      <Route path="moderation/feed" element={<Lazy><FeedModeration /></Lazy>} />
      <Route path="moderation/comments" element={<Lazy><CommentsModeration /></Lazy>} />
      <Route path="moderation/reviews" element={<Lazy><ReviewsModeration /></Lazy>} />
      <Route path="moderation/reports" element={<Lazy><ReportsManagement /></Lazy>} />

      {/* ─── MARKETING ─── */}
      <Route path="newsletters" element={<Lazy><NewsletterList /></Lazy>} />
      <Route path="newsletters/create" element={<Lazy><NewsletterForm /></Lazy>} />
      <Route path="newsletters/edit/:id" element={<Lazy><NewsletterForm /></Lazy>} />
      <Route path="newsletters/subscribers" element={<Lazy><Subscribers /></Lazy>} />
      <Route path="newsletters/segments/create" element={<Lazy><SegmentForm /></Lazy>} />
      <Route path="newsletters/segments/edit/:id" element={<Lazy><SegmentForm /></Lazy>} />
      <Route path="newsletters/segments" element={<Lazy><Segments /></Lazy>} />
      <Route path="newsletters/templates/create" element={<Lazy><TemplateForm /></Lazy>} />
      <Route path="newsletters/templates/edit/:id" element={<Lazy><TemplateForm /></Lazy>} />
      <Route path="newsletters/templates" element={<Lazy><Templates /></Lazy>} />
      <Route path="newsletters/analytics" element={<Lazy><NewsletterAnalytics /></Lazy>} />
      <Route path="newsletters/bounces" element={<Lazy><NewsletterBounces /></Lazy>} />
      <Route path="newsletters/send-time-optimizer" element={<Lazy><NewsletterSendTimeOptimizer /></Lazy>} />
      <Route path="newsletters/diagnostics" element={<Lazy><NewsletterDiagnostics /></Lazy>} />
      <Route path="newsletters/:id/stats" element={<Lazy><NewsletterStats /></Lazy>} />
      <Route path="newsletters/:id/activity" element={<Lazy><NewsletterActivity /></Lazy>} />

      {/* ─── ADVANCED ─── */}
      <Route path="ai-settings" element={<Lazy><AiSettings /></Lazy>} />
      <Route path="email-settings" element={<Lazy><EmailSettings /></Lazy>} />
      <Route path="feed-algorithm" element={<TenantRedirect to="/admin/algorithm-settings" />} />
      <Route path="algorithm-settings" element={<Lazy><AlgorithmSettings /></Lazy>} />
      <Route path="seo" element={<Lazy><SeoOverview /></Lazy>} />
      <Route path="seo/audit" element={<Lazy><SeoAudit /></Lazy>} />
      <Route path="seo/redirects" element={<Lazy><Redirects /></Lazy>} />
      <Route path="404-errors" element={<Lazy><Error404Tracking /></Lazy>} />
      <Route path="match-debug" element={<Lazy><MatchDebugPanel /></Lazy>} />

      {/* ─── BILLING ─── */}
      <Route path="member-premium" element={
        <FeatureGatedElement feature="member_premium">
          <Lazy><MemberPremiumAdminPage /></Lazy>
        </FeatureGatedElement>
      } />
      <Route path="member-premium/subscribers" element={
        <FeatureGatedElement feature="member_premium">
          <Lazy><MemberPremiumSubscribersPage /></Lazy>
        </FeatureGatedElement>
      } />
      <Route path="billing" element={<Lazy><BillingPage /></Lazy>} />
      <Route path="billing/plans" element={<Lazy><PlanSelector /></Lazy>} />
      <Route path="billing/invoices" element={<Lazy><InvoiceHistory /></Lazy>} />
      <Route path="billing/checkout-return" element={<Lazy><CheckoutReturn /></Lazy>} />

      {/* ─── FINANCIAL ─── */}
      <Route path="timebanking" element={<Lazy><TimebankingDashboard /></Lazy>} />
      <Route path="timebanking/alerts" element={<Lazy><FraudAlerts /></Lazy>} />
      <Route path="timebanking/user-report" element={<Lazy><UserReport /></Lazy>} />
      <Route path="timebanking/user-report/:id" element={<Lazy><UserReport /></Lazy>} />
      <Route path="timebanking/org-wallets" element={<Lazy><OrgWallets /></Lazy>} />
      <Route path="timebanking/create-org" element={<Lazy><OrgWallets /></Lazy>} />
      <Route path="timebanking/starting-balances" element={<Lazy><StartingBalances /></Lazy>} />
      <Route path="plans" element={<Lazy><PlansAdmin /></Lazy>} />
      <Route path="plans/subscriptions" element={<Lazy><SubscriptionsAdmin /></Lazy>} />
      <Route element={<SuperAdminRoute />}>
        <Route path="plans/create" element={<Lazy><PlanForm /></Lazy>} />
        <Route path="plans/edit/:id" element={<Lazy><PlanForm /></Lazy>} />
      </Route>

      {/* ─── ENTERPRISE ─── */}
      <Route path="enterprise" element={<Lazy><EnterpriseDashboard /></Lazy>} />
      <Route path="enterprise/roles" element={<Lazy><RoleList /></Lazy>} />
      <Route path="enterprise/roles/create" element={<Lazy><RoleForm /></Lazy>} />
      <Route path="enterprise/roles/:id" element={<Lazy><RoleForm /></Lazy>} />
      <Route path="enterprise/roles/:id/edit" element={<Lazy><RoleForm /></Lazy>} />
      <Route path="enterprise/permissions" element={<Lazy><PermissionBrowser /></Lazy>} />
      <Route path="enterprise/gdpr" element={<Lazy><GdprDashboard /></Lazy>} />
      <Route path="enterprise/gdpr/requests" element={<Lazy><GdprRequests /></Lazy>} />
      <Route path="enterprise/gdpr/requests/create" element={<Lazy><GdprRequestCreate /></Lazy>} />
      <Route path="enterprise/gdpr/requests/:id" element={<Lazy><GdprRequestDetail /></Lazy>} />
      <Route path="enterprise/gdpr/consents" element={<Lazy><GdprConsents /></Lazy>} />
      <Route path="enterprise/gdpr/consent-types" element={<Lazy><GdprConsentTypes /></Lazy>} />
      <Route path="enterprise/gdpr/breaches" element={<Lazy><GdprBreaches /></Lazy>} />
      <Route path="enterprise/gdpr/breaches/:id" element={<Lazy><GdprBreachDetail /></Lazy>} />
      <Route path="enterprise/gdpr/audit" element={<Lazy><GdprAuditLog /></Lazy>} />

      {/* AG42 — Swiss FADP compliance */}
      <Route path="enterprise/fadp" element={<Lazy><FadpAdminPage /></Lazy>} />
      <Route path="enterprise/monitoring" element={<Lazy><SystemMonitoring /></Lazy>} />
      <Route path="enterprise/monitoring/health" element={<Lazy><HealthCheck /></Lazy>} />
      <Route path="enterprise/monitoring/logs" element={<Lazy><ErrorLogs /></Lazy>} />
      <Route path="enterprise/monitoring/log-files" element={<Lazy><LogFiles /></Lazy>} />
      <Route path="enterprise/monitoring/log-files/:filename" element={<Lazy><LogFileViewer /></Lazy>} />
      <Route path="enterprise/monitoring/requirements" element={<Lazy><SystemRequirements /></Lazy>} />
      <Route path="enterprise/config" element={<Lazy><SystemConfig /></Lazy>} />
      <Route path="enterprise/config/secrets" element={<Lazy><SecretsVault /></Lazy>} />
      <Route path="enterprise/config/features" element={<Lazy><FeatureFlags /></Lazy>} />

      {/* ─── PERFORMANCE ─── */}
      <Route path="performance" element={<Lazy><PerformanceDashboard /></Lazy>} />
      <Route path="legal-documents" element={<Lazy><LegalDocList /></Lazy>} />
      <Route path="legal-documents/create" element={<Lazy><LegalDocForm /></Lazy>} />
      <Route path="legal-documents/compliance" element={<Lazy><LegalDocComplianceDashboard /></Lazy>} />
      <Route path="legal-documents/:id" element={<Lazy><LegalDocForm /></Lazy>} />
      <Route path="legal-documents/:id/edit" element={<Lazy><LegalDocForm /></Lazy>} />
      <Route path="legal-documents/:id/versions" element={<Lazy><LegalDocVersionList /></Lazy>} />

      {/* ─── FEDERATION ─── */}
      <Route path="federation" element={<Lazy><FederationSettings /></Lazy>} />
      <Route path="federation/partnerships" element={<Lazy><Partnerships /></Lazy>} />
      <Route path="federation/directory" element={<Lazy><PartnerDirectory /></Lazy>} />
      <Route path="federation/directory/profile" element={<Lazy><MyProfile /></Lazy>} />
      <Route path="federation/analytics" element={<Lazy><FederationAnalytics /></Lazy>} />
      <Route path="federation/api-keys" element={<Lazy><ApiKeys /></Lazy>} />
      <Route path="federation/api-keys/create" element={<Lazy><CreateApiKey /></Lazy>} />
      <Route path="federation/data" element={<Lazy><DataManagement /></Lazy>} />
      <Route path="federation/credit-agreements" element={<Lazy><CreditAgreements /></Lazy>} />
      <Route path="federation/neighborhoods" element={<Lazy><Neighborhoods /></Lazy>} />
      <Route path="federation/external-partners" element={<Lazy><ExternalPartners /></Lazy>} />
      <Route path="federation/webhooks" element={<Lazy><Webhooks /></Lazy>} />
      <Route path="federation/api-docs" element={<Lazy><ApiDocumentation /></Lazy>} />
      <Route path="federation/activity" element={<Lazy><FederationActivityFeed /></Lazy>} />
      <Route path="federation/cc-config" element={<Lazy><CreditCommonsConfig /></Lazy>} />
      <Route path="federation/aggregates" element={<Lazy><FederationAggregatesPage /></Lazy>} />

      {/* ─── SAFEGUARDING ─── */}
      <Route path="safeguarding" element={<Lazy><SafeguardingDashboard /></Lazy>} />
      <Route path="safeguarding-options" element={<Lazy><SafeguardingOptionsAdmin /></Lazy>} />

      {/* ─── SYSTEM ─── */}
      <Route path="settings" element={<Lazy><AdminSettings /></Lazy>} />
      <Route path="settings/registration-policy" element={<Lazy><RegistrationPolicySettings /></Lazy>} />
      <Route path="onboarding-settings" element={<Lazy><OnboardingSettings /></Lazy>} />
      <Route path="tenant-features" element={<Lazy><TenantFeatures /></Lazy>} />
      <Route path="module-configuration" element={<Lazy><ModuleConfiguration /></Lazy>} />
      <Route path="translation-config" element={<Lazy><TranslationConfig /></Lazy>} />
      <Route path="cron-jobs" element={<Lazy><CronJobs /></Lazy>} />
      <Route path="cron-jobs/logs" element={<Lazy><CronJobLogs /></Lazy>} />
      <Route path="cron-jobs/settings" element={<Lazy><CronJobSettings /></Lazy>} />
      <Route path="cron-jobs/setup" element={<Lazy><CronJobSetup /></Lazy>} />
      <Route path="activity-log" element={<Lazy><ActivityLog /></Lazy>} />
      <Route path="tests" element={<Lazy><TestRunner /></Lazy>} />
      <Route path="seed-generator" element={<Lazy><SeedGenerator /></Lazy>} />
      <Route path="webp-converter" element={<Lazy><WebpConverter /></Lazy>} />
      <Route path="image-settings" element={<Lazy><ImageSettings /></Lazy>} />
      <Route path="native-app" element={<Lazy><NativeApp /></Lazy>} />
      <Route path="blog-restore" element={<Lazy><BlogRestore /></Lazy>} />

      {/* ─── COMMUNITY TOOLS ─── */}
      <Route path="groups" element={<Lazy><GroupList /></Lazy>} />
      <Route path="groups/analytics" element={<Lazy><GroupAnalytics /></Lazy>} />
      <Route path="groups/approvals" element={<Lazy><GroupApprovals /></Lazy>} />
      <Route path="groups/moderation" element={<Lazy><GroupModeration /></Lazy>} />
      <Route path="groups/types" element={<Lazy><GroupTypes /></Lazy>} />
      <Route path="groups/:id/detail" element={<Lazy><GroupDetail /></Lazy>} />
      <Route path="groups/:id/edit" element={<Lazy><GroupEdit /></Lazy>} />
      <Route path="groups/recommendations" element={<Lazy><GroupRecommendations /></Lazy>} />
      <Route path="groups/ranking" element={<Lazy><GroupRanking /></Lazy>} />
      <Route path="group-types" element={<Lazy><GroupList /></Lazy>} />
      <Route path="group-ranking" element={<Lazy><GroupList /></Lazy>} />
      <Route path="group-locations" element={<Lazy><GroupGeocode /></Lazy>} />
      <Route path="geocode-groups" element={<Lazy><GroupGeocode /></Lazy>} />
      <Route path="smart-match-users" element={<Lazy><SmartMatchUsers /></Lazy>} />
      <Route path="smart-match-monitoring" element={<Lazy><SmartMatchMonitoring /></Lazy>} />
      <Route path="volunteering" element={<Lazy><VolunteeringOverview /></Lazy>} />
      <Route path="volunteering/approvals" element={<Lazy><VolunteerApprovals /></Lazy>} />
      <Route path="volunteering/organizations" element={<Lazy><VolunteerOrganizations /></Lazy>} />
      <Route path="volunteering/expenses" element={<Lazy><VolunteerExpenses /></Lazy>} />
      <Route path="volunteering/training" element={<Lazy><VolunteerTraining /></Lazy>} />
      <Route path="volunteering/safeguarding" element={<Lazy><VolunteerSafeguarding /></Lazy>} />
      <Route path="volunteering/hours" element={<Lazy><VolunteerHoursAudit /></Lazy>} />
      <Route path="volunteering/giving-days" element={<Lazy><VolunteerGivingDays /></Lazy>} />
      <Route path="volunteering/consents" element={<Lazy><VolunteerConsents /></Lazy>} />
      <Route path="volunteering/projects" element={<Lazy><VolunteerProjects /></Lazy>} />
      <Route path="volunteering/config" element={<Lazy><VolunteerConfig /></Lazy>} />
      {/* ─── CARING COMMUNITY (feature-gated) ─── */}
      <Route
        path="caring-community"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><CaringCommunityAdmin /></Lazy>
          </FeatureGatedElement>
        }
      />
      <Route
        path="caring-community/workflow"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><CaringCommunityWorkflowPage /></Lazy>
          </FeatureGatedElement>
        }
      />
      <Route
        path="caring-community/loyalty"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><LoyaltyAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />
      <Route
        path="caring-community/hour-transfers"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><HourTransferAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />
      <Route
        path="caring-community/safeguarding"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><SafeguardingReportsAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* AG64 — Care-provider directory */}
      <Route
        path="caring-community/providers"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><CareProviderAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* AG67 — Trust tier config */}
      <Route
        path="caring-community/trust-tier"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><TrustTierAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* AG66 — KPI baselines */}
      <Route
        path="caring-community/kpi-baselines"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><KpiBaselineAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* AG70 — Emergency alerts */}
      <Route
        path="caring-community/emergency-alerts"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><EmergencyAlertAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* AG62 — Municipality surveys */}
      <Route
        path="caring-community/surveys"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><MunicipalSurveyAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* AG69 — Multi-stage project announcements */}
      <Route
        path="caring-community/projects"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><ProjectAnnouncementsAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* AG28 — Regional points (third currency) */}
      <Route
        path="regional-points"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><RegionalPointsAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* AG29 — Municipal verification */}
      <Route
        path="caring-community/verification"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><MunicipalVerificationAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* AG31 — Smart member nudges */}
      <Route
        path="caring-community/nudges"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><SmartNudgesAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* AG65 — Research partnerships */}
      <Route
        path="caring-community/research"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><ResearchPartnershipsAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* Warmth Pass */}
      <Route
        path="caring-community/warmth-pass"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><WarmthPassAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />
      <Route
        path="caring-community/warmth-pass/:userId"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><WarmthPassAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* Care Recipient Circle */}
      <Route
        path="caring-community/recipient-circle"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><CareRecipientCirclePage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* Municipal ROI / B2G impact dashboard */}
      <Route
        path="caring-community/municipal-roi"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><MunicipalRoiAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* AG77 — Sub-regional geographic units */}
      <Route
        path="caring-community/sub-regions"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><SubRegionsAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* AG83 — Pilot Success Scoreboard */}
      <Route
        path="caring-community/pilot-scoreboard"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><PilotScoreboardAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* AG81 — Operating Policy */}
      <Route
        path="caring-community/operating-policy"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><OperatingPolicyAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* AG80 — FADP/nDSG Disclosure Pack */}
      <Route
        path="caring-community/disclosure-pack"
        element={
          <FeatureGatedElement feature="caring_community">
            <Lazy><DisclosurePackAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* AG56 — Local advertising campaigns */}
      <Route path="advertising/campaigns" element={<Lazy><AdCampaignAdminPage /></Lazy>} />

      {/* AG57 — Paid push campaigns */}
      <Route path="advertising/push-campaigns" element={<Lazy><PushCampaignAdminPage /></Lazy>} />

      {/* AG61 — KI-Agenten autonomous framework */}
      <Route path="ai/ki-agents" element={<Lazy><KiAgentAdminPage /></Lazy>} />

      {/* AG61 — new KI-Agenten admin (definitions, proposals, runs) */}
      <Route path="agents" element={<Lazy><AgentsAdminPage /></Lazy>} />
      <Route path="agents/proposals" element={<Lazy><AgentProposalsPage /></Lazy>} />
      <Route path="agents/runs" element={<Lazy><AgentRunsPage /></Lazy>} />

      {/* AG59 — Regional analytics product */}
      <Route path="analytics/regional" element={<Lazy><RegionalAnalyticsPage /></Lazy>} />

      {/* AG71 — Pilot region inquiry funnel */}
      <Route path="platform/pilot-inquiries" element={<Lazy><PilotInquiryAdminPage /></Lazy>} />

      {/* AG44 — Self-service tenant provisioning queue (super-admin) */}
      <Route element={<SuperAdminRoute />}>
        <Route path="provisioning-requests" element={<Lazy><ProvisioningRequestsPage /></Lazy>} />
      </Route>

      {/* ─── EVENTS ─── */}
      <Route path="events" element={<Lazy><EventsAdmin /></Lazy>} />

      {/* ─── POLLS ─── */}
      <Route path="polls" element={<Lazy><PollsAdmin /></Lazy>} />

      {/* ─── GOALS ─── */}
      <Route path="goals" element={<Lazy><GoalsAdmin /></Lazy>} />

      {/* ─── RESOURCES / KNOWLEDGE BASE ─── */}
      <Route path="resources" element={<Lazy><ResourcesAdmin /></Lazy>} />
      <Route path="resources/create" element={<Lazy><KBArticleForm /></Lazy>} />
      <Route path="resources/edit/:id" element={<Lazy><KBArticleForm /></Lazy>} />
      <Route path="resources/categories" element={<Lazy><ResourceCategoriesAdmin /></Lazy>} />

      {/* ─── JOBS ─── */}
      <Route path="jobs" element={<Lazy><JobsAdmin /></Lazy>} />
      <Route path="jobs/moderation" element={<Lazy><JobModerationQueue /></Lazy>} />
      <Route path="jobs/bias-audit" element={<Lazy><JobBiasAudit /></Lazy>} />
      <Route path="jobs/pipeline" element={<Lazy><JobPipelineOverview /></Lazy>} />
      <Route path="jobs/templates" element={<Lazy><JobTemplatesAdmin /></Lazy>} />

      {/* ─── MARKETPLACE ─── */}
      <Route path="marketplace" element={<Lazy><MarketplaceAdmin /></Lazy>} />
      <Route path="marketplace/moderation" element={<Lazy><MarketplaceModerationPage /></Lazy>} />
      <Route path="marketplace/sellers" element={<Lazy><MarketplaceSellerAdmin /></Lazy>} />
      <Route path="marketplace/coupons" element={<Lazy><AdminCouponsPage /></Lazy>} />

      {/* ─── IDEATION / CHALLENGES ─── */}
      <Route path="ideation" element={<Lazy><IdeationAdmin /></Lazy>} />

      {/* ─── DELIVERABILITY ─── */}
      <Route path="deliverability" element={<Lazy><DeliverabilityDashboard /></Lazy>} />
      <Route path="deliverability/list" element={<Lazy><DeliverablesList /></Lazy>} />
      <Route path="deliverability/create" element={<Lazy><CreateDeliverable /></Lazy>} />
      <Route path="deliverability/edit/:id" element={<Lazy><EditDeliverable /></Lazy>} />
      <Route path="deliverability/analytics" element={<Lazy><DeliverabilityAnalytics /></Lazy>} />

      {/* ─── MATCHING DIAGNOSTIC ─── */}
      <Route path="matching-diagnostic" element={<Lazy><MatchingDiagnostic /></Lazy>} />

      {/* ─── NEXUS SCORE ─── */}
      <Route path="nexus-score/analytics" element={<Lazy><NexusScoreAnalytics /></Lazy>} />

      {/* ─── SUPER ADMIN (requires super admin role) ─── */}
      <Route path="super" element={<SuperAdminRoute />}>
        <Route index element={<Lazy><SuperDashboard /></Lazy>} />
        <Route path="tenants" element={<Lazy><TenantListAdmin /></Lazy>} />
        <Route path="tenants/create" element={<Lazy><TenantForm /></Lazy>} />
        <Route path="tenants/hierarchy" element={<Lazy><TenantHierarchy /></Lazy>} />
        <Route path="tenants/:id" element={<Lazy><TenantShow /></Lazy>} />
        <Route path="tenants/:id/edit" element={<Lazy><TenantForm /></Lazy>} />
        <Route path="users" element={<Lazy><SuperUserList /></Lazy>} />
        <Route path="users/create" element={<Lazy><SuperUserForm /></Lazy>} />
        <Route path="users/:id" element={<Lazy><UserShow /></Lazy>} />
        <Route path="users/:id/edit" element={<Lazy><SuperUserForm /></Lazy>} />
        <Route path="bulk" element={<Lazy><BulkOperations /></Lazy>} />
        <Route path="audit" element={<Lazy><SuperAuditLog /></Lazy>} />
        <Route path="federation" element={<Lazy><FederationControls /></Lazy>} />
        <Route path="federation/whitelist" element={<Lazy><FederationWhitelist /></Lazy>} />
        <Route path="federation/partnerships" element={<Lazy><SuperPartnerships /></Lazy>} />
        <Route path="federation/audit" element={<Lazy><FederationAuditLog /></Lazy>} />
        <Route path="federation/tenant/:tenantId/features" element={<Lazy><FederationTenantFeatures /></Lazy>} />
        <Route path="billing" element={<Lazy><BillingControl /></Lazy>} />
        <Route path="billing/revenue" element={<Lazy><RevenueDashboard /></Lazy>} />
      </Route>

      {/* ─── NATIONAL KISS FOUNDATION DASHBOARD (super-admin / national_admin) ─── */}
      <Route path="national/kiss" element={<Lazy><NationalKissDashboardPage /></Lazy>} />

      {/* ─── ANALYTICS & REPORTING ─── */}
      <Route path="community-analytics" element={<Lazy><CommunityAnalytics /></Lazy>} />
      <Route path="impact-report" element={<Lazy><ImpactReport /></Lazy>} />
      <Route path="reports/social-value" element={<TenantRedirect to="/admin/impact-report" />} />
      <Route path="reports/members" element={<Lazy><MemberReportsPage /></Lazy>} />
      <Route path="reports/hours" element={<Lazy><HoursReportsPage /></Lazy>} />
      <Route path="reports/municipal-impact" element={<Lazy><MunicipalImpactReportsPage /></Lazy>} />
      <Route path="reports/inactive-members" element={<Lazy><InactiveMembersPage /></Lazy>} />
      <Route path="moderation/queue" element={<Lazy><ModerationQueuePage /></Lazy>} />

      {/* ─── SELLABLE PRODUCTS — Regional Analytics (AG59) ─── */}
      <Route path="regional-analytics/subscriptions" element={<Lazy><RegionalAnalyticsAdminPage /></Lazy>} />

      {/* ─── INTEGRATIONS — API Partners (AG60) ─── */}
      <Route
        path="api-partners"
        element={
          <FeatureGatedElement feature="partner_api">
            <Lazy><ApiPartnersAdminPage /></Lazy>
          </FeatureGatedElement>
        }
      />

      {/* ─── REDIRECT: /admin/login → main login page ─── */}
      <Route path="login" element={<TenantRedirect to="/login" />} />

      {/* ─── 404 CATCH-ALL ─── */}
      <Route path="*" element={<Lazy><AdminNotFound /></Lazy>} />
    </>
  );
}

export default AdminRoutes;
