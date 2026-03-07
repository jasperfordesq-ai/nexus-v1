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
import { SuperAdminRoute } from './SuperAdminRoute';

// Lazy-loaded admin pages
const AdminDashboard = lazy(() => import('./modules/dashboard/AdminDashboard'));
const UserList = lazy(() => import('./modules/users/UserList'));
const TenantFeatures = lazy(() => import('./modules/config/TenantFeatures'));
const UserCreate = lazy(() => import('./modules/users/UserCreate'));
const UserEdit = lazy(() => import('./modules/users/UserEdit'));
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
const BrokerDashboard = lazy(() => import('./modules/broker/BrokerDashboard'));
const ExchangeManagement = lazy(() => import('./modules/broker/ExchangeManagement'));
const RiskTags = lazy(() => import('./modules/broker/RiskTags'));
const MessageReview = lazy(() => import('./modules/broker/MessageReview'));
const UserMonitoring = lazy(() => import('./modules/broker/UserMonitoring'));
const VettingRecords = lazy(() => import('./modules/broker/VettingRecords'));
const InsuranceCertificates = lazy(() => import('./modules/broker/InsuranceCertificates'));
const BrokerConfiguration = lazy(() => import('./modules/broker/BrokerConfiguration'));
const ExchangeDetail = lazy(() => import('./modules/broker/ExchangeDetail'));
const MessageDetail = lazy(() => import('./modules/broker/MessageDetail'));
const ReviewArchive = lazy(() => import('./modules/broker/ReviewArchive'));
const ArchiveDetail = lazy(() => import('./modules/broker/ArchiveDetail'));
const GamificationHub = lazy(() => import('./modules/gamification/GamificationHub'));
const CampaignList = lazy(() => import('./modules/gamification/CampaignList'));
const CampaignForm = lazy(() => import('./modules/gamification/CampaignForm'));
const GamificationAnalytics = lazy(() => import('./modules/gamification/GamificationAnalytics'));
const CustomBadges = lazy(() => import('./modules/gamification/CustomBadges'));
const CreateBadge = lazy(() => import('./modules/gamification/CreateBadge'));
const GroupList = lazy(() => import('./modules/groups/GroupList'));
const GroupAnalytics = lazy(() => import('./modules/groups/GroupAnalytics'));
const GroupApprovals = lazy(() => import('./modules/groups/GroupApprovals'));
const GroupModeration = lazy(() => import('./modules/groups/GroupModeration'));
const GroupTypes = lazy(() => import('./modules/groups/GroupTypes'));
const GroupDetail = lazy(() => import('./modules/groups/GroupDetail'));
const GroupRecommendations = lazy(() => import('./modules/groups/GroupRecommendations'));
const GroupRanking = lazy(() => import('./modules/groups/GroupRanking'));

// Enterprise module
const EnterpriseDashboard = lazy(() => import('./modules/enterprise/EnterpriseDashboard'));
const RoleList = lazy(() => import('./modules/enterprise/RoleList'));
const RoleForm = lazy(() => import('./modules/enterprise/RoleForm'));
const PermissionBrowser = lazy(() => import('./modules/enterprise/PermissionBrowser'));
const GdprDashboard = lazy(() => import('./modules/enterprise/GdprDashboard'));
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

// Events module
const EventsAdmin = lazy(() => import('./modules/events/EventsAdmin'));

// Polls module
const PollsAdmin = lazy(() => import('./modules/polls/PollsAdmin'));

// Goals module
const GoalsAdmin = lazy(() => import('./modules/goals/GoalsAdmin'));

// Resources / Knowledge Base module
const ResourcesAdmin = lazy(() => import('./modules/resources/ResourcesAdmin'));

// Jobs module
const JobsAdmin = lazy(() => import('./modules/jobs/JobsAdmin'));

// Ideation / Challenges module
const IdeationAdmin = lazy(() => import('./modules/ideation/IdeationAdmin'));

// Federation module
const FederationSettings = lazy(() => import('./modules/federation/FederationSettings'));
const Partnerships = lazy(() => import('./modules/federation/Partnerships'));
const PartnerDirectory = lazy(() => import('./modules/federation/PartnerDirectory'));
const MyProfile = lazy(() => import('./modules/federation/MyProfile'));
const FederationAnalytics = lazy(() => import('./modules/federation/FederationAnalytics'));
const ApiKeys = lazy(() => import('./modules/federation/ApiKeys'));
const CreateApiKey = lazy(() => import('./modules/federation/CreateApiKey'));
const DataManagement = lazy(() => import('./modules/federation/DataManagement'));
const CreditAgreements = lazy(() => import('./modules/federation/CreditAgreements'));
const Neighborhoods = lazy(() => import('./modules/federation/Neighborhoods'));

// Safeguarding module
const SafeguardingDashboard = lazy(() => import('./modules/safeguarding/SafeguardingDashboard'));

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
const DeliverabilityAnalytics = lazy(() => import('./modules/deliverability/DeliverabilityAnalytics'));

// Diagnostics module
const MatchingDiagnostic = lazy(() => import('./modules/diagnostics/MatchingDiagnostic'));
const NexusScoreAnalytics = lazy(() => import('./modules/diagnostics/NexusScoreAnalytics'));

// Analytics & Reporting
const CommunityAnalytics = lazy(() => import('./modules/analytics/CommunityAnalytics'));
const ImpactReport = lazy(() => import('./modules/impact/ImpactReport'));

// Admin Reports & Moderation Queue (A1-A5, A7)
const SocialValuePage = lazy(() => import('./modules/reports/SocialValuePage'));
const MemberReportsPage = lazy(() => import('./modules/reports/MemberReportsPage'));
const HoursReportsPage = lazy(() => import('./modules/reports/HoursReportsPage'));
const InactiveMembersPage = lazy(() => import('./modules/reports/InactiveMembersPage'));
const ModerationQueuePage = lazy(() => import('./modules/reports/ModerationQueuePage'));

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
const FederationSystemControls = lazy(() => import('./modules/super/FederationSystemControls'));
const FederationWhitelist = lazy(() => import('./modules/super/FederationWhitelist'));
const SuperPartnerships = lazy(() => import('./modules/super/SuperPartnerships'));
const FederationAuditLog = lazy(() => import('./modules/super/FederationAuditLog'));
const FederationTenantFeatures = lazy(() => import('./modules/super/FederationTenantFeatures'));

// Content module
const PagesAdmin = lazy(() => import('./modules/content/PagesAdmin'));
const PageBuilder = lazy(() => import('./modules/content/PageBuilder'));
const MenusAdmin = lazy(() => import('./modules/content/MenusAdmin'));
const MenuBuilder = lazy(() => import('./modules/content/MenuBuilder'));
const AttributesAdmin = lazy(() => import('./modules/content/AttributesAdmin'));
const PlansAdmin = lazy(() => import('./modules/content/PlansAdmin'));
const PlanForm = lazy(() => import('./modules/content/PlanForm'));
const SubscriptionsAdmin = lazy(() => import('./modules/content/Subscriptions'));

// Wrap lazy components in Suspense
function Lazy({ children }: { children: React.ReactNode }) {
  return <Suspense fallback={<LoadingScreen message="Loading..." />}>{children}</Suspense>;
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
      <Route path="users/:id/permissions" element={<Lazy><PermissionBrowser /></Lazy>} />

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

      {/* ─── ENGAGEMENT ─── */}
      <Route path="gamification" element={<Lazy><GamificationHub /></Lazy>} />
      <Route path="gamification/campaigns" element={<Lazy><CampaignList /></Lazy>} />
      <Route path="gamification/campaigns/create" element={<Lazy><CampaignForm /></Lazy>} />
      <Route path="gamification/campaigns/edit/:id" element={<Lazy><CampaignForm /></Lazy>} />
      <Route path="gamification/analytics" element={<Lazy><GamificationAnalytics /></Lazy>} />
      <Route path="custom-badges" element={<Lazy><CustomBadges /></Lazy>} />
      <Route path="custom-badges/create" element={<Lazy><CreateBadge /></Lazy>} />

      {/* ─── MATCHING & BROKER ─── */}
      <Route path="smart-matching" element={<Lazy><SmartMatchingOverview /></Lazy>} />
      <Route path="smart-matching/analytics" element={<Lazy><MatchingAnalytics /></Lazy>} />
      <Route path="smart-matching/configuration" element={<Lazy><MatchingConfig /></Lazy>} />
      <Route path="match-approvals" element={<Lazy><MatchApprovals /></Lazy>} />
      <Route path="match-approvals/:id" element={<Lazy><MatchDetail /></Lazy>} />
      <Route path="broker-controls" element={<Lazy><BrokerDashboard /></Lazy>} />
      <Route path="broker-controls/exchanges" element={<Lazy><ExchangeManagement /></Lazy>} />
      <Route path="broker-controls/risk-tags" element={<Lazy><RiskTags /></Lazy>} />
      <Route path="broker-controls/messages" element={<Lazy><MessageReview /></Lazy>} />
      <Route path="broker-controls/monitoring" element={<Lazy><UserMonitoring /></Lazy>} />
      <Route path="broker-controls/vetting" element={<Lazy><VettingRecords /></Lazy>} />
      <Route path="broker-controls/insurance" element={<Lazy><InsuranceCertificates /></Lazy>} />
      <Route path="broker-controls/configuration" element={<Lazy><BrokerConfiguration /></Lazy>} />
      <Route path="broker-controls/exchanges/:id" element={<Lazy><ExchangeDetail /></Lazy>} />
      <Route path="broker-controls/messages/:id" element={<Lazy><MessageDetail /></Lazy>} />
      <Route path="broker-controls/archives" element={<Lazy><ReviewArchive /></Lazy>} />
      <Route path="broker-controls/archives/:id" element={<Lazy><ArchiveDetail /></Lazy>} />

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
      <Route path="feed-algorithm" element={<Navigate to="/admin/algorithm-settings" replace />} />
      <Route path="algorithm-settings" element={<Lazy><AlgorithmSettings /></Lazy>} />
      <Route path="seo" element={<Lazy><SeoOverview /></Lazy>} />
      <Route path="seo/audit" element={<Lazy><SeoAudit /></Lazy>} />
      <Route path="seo/redirects" element={<Lazy><Redirects /></Lazy>} />
      <Route path="404-errors" element={<Lazy><Error404Tracking /></Lazy>} />
      <Route path="match-debug" element={<Lazy><MatchDebugPanel /></Lazy>} />

      {/* ─── FINANCIAL ─── */}
      <Route path="timebanking" element={<Lazy><TimebankingDashboard /></Lazy>} />
      <Route path="timebanking/alerts" element={<Lazy><FraudAlerts /></Lazy>} />
      <Route path="timebanking/user-report" element={<Lazy><UserReport /></Lazy>} />
      <Route path="timebanking/user-report/:id" element={<Lazy><UserReport /></Lazy>} />
      <Route path="timebanking/org-wallets" element={<Lazy><OrgWallets /></Lazy>} />
      <Route path="timebanking/create-org" element={<Lazy><OrgWallets /></Lazy>} />
      <Route path="timebanking/starting-balances" element={<Lazy><StartingBalances /></Lazy>} />
      <Route path="plans" element={<Lazy><PlansAdmin /></Lazy>} />
      <Route path="plans/create" element={<Lazy><PlanForm /></Lazy>} />
      <Route path="plans/edit/:id" element={<Lazy><PlanForm /></Lazy>} />
      <Route path="plans/subscriptions" element={<Lazy><SubscriptionsAdmin /></Lazy>} />

      {/* ─── ENTERPRISE ─── */}
      <Route path="enterprise" element={<Lazy><EnterpriseDashboard /></Lazy>} />
      <Route path="enterprise/roles" element={<Lazy><RoleList /></Lazy>} />
      <Route path="enterprise/roles/create" element={<Lazy><RoleForm /></Lazy>} />
      <Route path="enterprise/roles/:id" element={<Lazy><RoleForm /></Lazy>} />
      <Route path="enterprise/roles/:id/edit" element={<Lazy><RoleForm /></Lazy>} />
      <Route path="enterprise/permissions" element={<Lazy><PermissionBrowser /></Lazy>} />
      <Route path="enterprise/gdpr" element={<Lazy><GdprDashboard /></Lazy>} />
      <Route path="enterprise/gdpr/requests" element={<Lazy><GdprRequests /></Lazy>} />
      <Route path="enterprise/gdpr/consents" element={<Lazy><GdprConsents /></Lazy>} />
      <Route path="enterprise/gdpr/breaches" element={<Lazy><GdprBreaches /></Lazy>} />
      <Route path="enterprise/gdpr/audit" element={<Lazy><GdprAuditLog /></Lazy>} />
      <Route path="enterprise/monitoring" element={<Lazy><SystemMonitoring /></Lazy>} />
      <Route path="enterprise/monitoring/health" element={<Lazy><HealthCheck /></Lazy>} />
      <Route path="enterprise/monitoring/logs" element={<Lazy><ErrorLogs /></Lazy>} />
      <Route path="enterprise/config" element={<Lazy><SystemConfig /></Lazy>} />
      <Route path="enterprise/config/secrets" element={<Lazy><SecretsVault /></Lazy>} />

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

      {/* ─── SAFEGUARDING ─── */}
      <Route path="safeguarding" element={<Lazy><SafeguardingDashboard /></Lazy>} />

      {/* ─── SYSTEM ─── */}
      <Route path="settings" element={<Lazy><AdminSettings /></Lazy>} />
      <Route path="settings/registration-policy" element={<Lazy><RegistrationPolicySettings /></Lazy>} />
      <Route path="tenant-features" element={<Lazy><TenantFeatures /></Lazy>} />
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
      <Route path="groups/recommendations" element={<Lazy><GroupRecommendations /></Lazy>} />
      <Route path="groups/ranking" element={<Lazy><GroupRanking /></Lazy>} />
      <Route path="group-types" element={<Lazy><GroupList /></Lazy>} />
      <Route path="group-ranking" element={<Lazy><GroupList /></Lazy>} />
      <Route path="group-locations" element={<Lazy><GroupList /></Lazy>} />
      <Route path="geocode-groups" element={<Lazy><GroupList /></Lazy>} />
      <Route path="smart-match-users" element={<Lazy><SmartMatchUsers /></Lazy>} />
      <Route path="smart-match-monitoring" element={<Lazy><SmartMatchMonitoring /></Lazy>} />
      <Route path="volunteering" element={<Lazy><VolunteeringOverview /></Lazy>} />
      <Route path="volunteering/approvals" element={<Lazy><VolunteerApprovals /></Lazy>} />
      <Route path="volunteering/organizations" element={<Lazy><VolunteerOrganizations /></Lazy>} />

      {/* ─── EVENTS ─── */}
      <Route path="events" element={<Lazy><EventsAdmin /></Lazy>} />

      {/* ─── POLLS ─── */}
      <Route path="polls" element={<Lazy><PollsAdmin /></Lazy>} />

      {/* ─── GOALS ─── */}
      <Route path="goals" element={<Lazy><GoalsAdmin /></Lazy>} />

      {/* ─── RESOURCES / KNOWLEDGE BASE ─── */}
      <Route path="resources" element={<Lazy><ResourcesAdmin /></Lazy>} />

      {/* ─── JOBS ─── */}
      <Route path="jobs" element={<Lazy><JobsAdmin /></Lazy>} />

      {/* ─── IDEATION / CHALLENGES ─── */}
      <Route path="ideation" element={<Lazy><IdeationAdmin /></Lazy>} />

      {/* ─── DELIVERABILITY ─── */}
      <Route path="deliverability" element={<Lazy><DeliverabilityDashboard /></Lazy>} />
      <Route path="deliverability/list" element={<Lazy><DeliverablesList /></Lazy>} />
      <Route path="deliverability/create" element={<Lazy><CreateDeliverable /></Lazy>} />
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
        <Route path="federation/system-controls" element={<Lazy><FederationSystemControls /></Lazy>} />
        <Route path="federation/whitelist" element={<Lazy><FederationWhitelist /></Lazy>} />
        <Route path="federation/partnerships" element={<Lazy><SuperPartnerships /></Lazy>} />
        <Route path="federation/audit" element={<Lazy><FederationAuditLog /></Lazy>} />
        <Route path="federation/tenant/:tenantId/features" element={<Lazy><FederationTenantFeatures /></Lazy>} />
      </Route>

      {/* ─── ANALYTICS & REPORTING ─── */}
      <Route path="community-analytics" element={<Lazy><CommunityAnalytics /></Lazy>} />
      <Route path="impact-report" element={<Lazy><ImpactReport /></Lazy>} />
      <Route path="reports/social-value" element={<Lazy><SocialValuePage /></Lazy>} />
      <Route path="reports/members" element={<Lazy><MemberReportsPage /></Lazy>} />
      <Route path="reports/hours" element={<Lazy><HoursReportsPage /></Lazy>} />
      <Route path="reports/inactive-members" element={<Lazy><InactiveMembersPage /></Lazy>} />
      <Route path="moderation/queue" element={<Lazy><ModerationQueuePage /></Lazy>} />

      {/* ─── REDIRECT: /admin/login → main login page ─── */}
      <Route path="login" element={<Navigate to="/login" replace />} />

      {/* ─── 404 CATCH-ALL ─── */}
      <Route path="*" element={<Lazy><AdminNotFound /></Lazy>} />
    </>
  );
}

export default AdminRoutes;
