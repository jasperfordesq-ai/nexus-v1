// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * NEXUS React Frontend - Main App Component
 *
 * Routes structure:
 * - All routes work at both / and /:tenantSlug/ prefix (Phase 0-1 TRS-001)
 * - TenantShell provides TenantProvider + AuthProvider per route group
 * - Public routes (no auth required)
 * - Protected routes (auth required)
 * - Feature-gated routes (based on tenant config)
 *
 * @see docs/TRS-001-TENANT-RESOLUTION-SPEC.md
 */

import { Suspense, lazy, type ComponentType } from 'react';
import { BrowserRouter, Routes, Route, useNavigate, Navigate } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

/**
 * Wrapper around React.lazy() that handles stale chunk errors after deployment.
 * When a new build changes chunk hashes, users with cached index.html will try
 * to load old chunk filenames that no longer exist (404). This catches that error
 * and forces a page reload to fetch the new index.html with correct chunk references.
 * Uses sessionStorage to prevent infinite reload loops.
 *
 * SAFETY: Never reloads while the user has focus in a text input, textarea, or
 * contenteditable field — this prevents losing typed message drafts.
 */
function lazyWithRetry(
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  importFn: () => Promise<{ default: ComponentType<any> }>
) {
  return lazy(() =>
    importFn().catch((error: Error) => {
      // Only reload for chunk load errors (network/404 failures)
      const isChunkError =
        error.message?.includes('Failed to fetch dynamically imported module') ||
        error.message?.includes('error loading dynamically imported module') ||
        error.message?.includes('Loading chunk') ||
        error.message?.includes('Loading CSS chunk') ||
        error.message?.includes('Unable to preload CSS') ||
        error.name === 'ChunkLoadError';

      if (isChunkError) {
        // Never reload while the user is actively typing in an input/textarea
        const active = document.activeElement;
        const isUserTyping = active instanceof HTMLInputElement ||
          active instanceof HTMLTextAreaElement ||
          active?.getAttribute('contenteditable') === 'true';

        if (isUserTyping) {
          // Skip the reload — the error boundary will show a "try again" UI
          // instead of destroying the user's in-progress work
          throw error;
        }

        const reloadKey = `chunk_reload_${window.location.pathname}`;
        const lastReload = sessionStorage.getItem(reloadKey);
        const now = Date.now();

        // Prevent infinite reload loop — only reload once per path per 30 seconds
        if (!lastReload || now - parseInt(lastReload) > 30000) {
          sessionStorage.setItem(reloadKey, now.toString());
          window.location.reload();
        }
      }

      // Re-throw so the error boundary still catches it if reload didn't help
      throw error;
    })
  );
}
import { HelmetProvider } from 'react-helmet-async';

// Contexts (app-wide only — tenant-scoped contexts are inside TenantShell)
import { ToastProvider, ThemeProvider, CookieConsentProvider, useTenant } from '@/contexts';
import { CARING_COMMUNITY_ROUTE } from '@/pages/caring-community/config';


// Layout Components
import { Layout, AuthLayout } from '@/components/layout';
import { ProtectedRoute, FeatureGate, ScrollToTop, TenantShell } from '@/components/routing';
import { LoadingScreen, ErrorBoundary, FeatureErrorBoundary } from '@/components/feedback';

// Auth Pages (critical path - eager loaded)
import { LoginPage, RegisterPage } from '@/pages/auth';

// Auth Pages (rarely used - lazy loaded)
const ForgotPasswordPage = lazyWithRetry(() => import('./pages/auth/ForgotPasswordPage'));
const ResetPasswordPage = lazyWithRetry(() => import('./pages/auth/ResetPasswordPage'));
const VerifyEmailPage = lazyWithRetry(() => import('./pages/auth/VerifyEmailPage'));
const VerifyIdentityPage = lazyWithRetry(() => import('./pages/auth/VerifyIdentityPage'));
const VerifyIdentityOptionalPage = lazyWithRetry(() => import('./pages/settings/VerifyIdentityOptionalPage'));
const OauthCallbackPage = lazyWithRetry(() => import('./pages/auth/OauthCallbackPage'));

// Admin Panel (lazy-loaded — keeps recharts, jsPDF, admin sidebar/header out of main bundle)
const AdminApp = lazyWithRetry(() => import('@/admin/AdminApp'));

// Broker Panel (lazy-loaded — simplified admin interface for brokers)
const BrokerApp = lazyWithRetry(() => import('@/broker/BrokerApp'));

// Community Caring Panel (lazy-loaded — dedicated hub for caring_community module)
const CaringApp = lazyWithRetry(() => import('@/caring/CaringApp'));

// Lazy-loaded Pages (all use lazyWithRetry to handle stale chunk errors after deploys)
const HomePage = lazyWithRetry(() => import('@/pages/public/HomePage'));
const DashboardPage = lazyWithRetry(() => import('@/pages/dashboard/DashboardPage'));
const ListingsPage = lazyWithRetry(() => import('@/pages/listings/ListingsPage'));
const ListingDetailPage = lazyWithRetry(() => import('@/pages/listings/ListingDetailPage'));
const CreateListingPage = lazyWithRetry(() => import('@/pages/listings/CreateListingPage'));
const MessagesPage = lazyWithRetry(() => import('@/pages/messages/MessagesPage'));
const ConversationPage = lazyWithRetry(() => import('@/pages/messages/ConversationPage'));
const WalletPage = lazyWithRetry(() => import('@/pages/wallet/WalletPage'));
const ProfilePage = lazyWithRetry(() => import('@/pages/profile/ProfilePage'));
// SOC10 / SOC14 — collections & appreciations
const MyCollectionsPage = lazyWithRetry(() => import('@/pages/profile/MyCollectionsPage'));
const CollectionDetailPage = lazyWithRetry(() => import('@/pages/profile/CollectionDetailPage'));
const UserCollectionsView = lazyWithRetry(() => import('@/pages/profile/UserCollectionsView'));
const AppreciationWallPage = lazyWithRetry(() => import('@/pages/profile/AppreciationWallPage'));
const SettingsPage = lazyWithRetry(() => import('@/pages/settings/SettingsPage'));
const BlockedUsersPage = lazyWithRetry(() => import('@/pages/settings/BlockedUsersPage'));
const SearchPage = lazyWithRetry(() => import('@/pages/search/SearchPage'));
const NotificationsPage = lazyWithRetry(() => import('@/pages/notifications/NotificationsPage'));
const MembersPage = lazyWithRetry(() => import('@/pages/members/MembersPage'));
const EventsPage = lazyWithRetry(() => import('@/pages/events/EventsPage'));
const EventDetailPage = lazyWithRetry(() => import('@/pages/events/EventDetailPage'));
const CreateEventPage = lazyWithRetry(() => import('@/pages/events/CreateEventPage'));
const GroupsPage = lazyWithRetry(() => import('@/pages/groups/GroupsPage'));
const GroupDetailPage = lazyWithRetry(() => import('@/pages/groups/GroupDetailPage'));
const CreateGroupPage = lazyWithRetry(() => import('@/pages/groups/CreateGroupPage'));
const NotFoundPage = lazyWithRetry(() => import('@/pages/errors/NotFoundPage'));
const ComingSoonPage = lazyWithRetry(() => import('@/pages/errors/ComingSoonPage'));
const ExchangesPage = lazyWithRetry(() => import('@/pages/exchanges/ExchangesPage'));
const ExchangeDetailPage = lazyWithRetry(() => import('@/pages/exchanges/ExchangeDetailPage'));
const RequestExchangePage = lazyWithRetry(() => import('@/pages/exchanges/RequestExchangePage'));
const LeaderboardPage = lazyWithRetry(() => import('@/pages/leaderboard/LeaderboardPage'));
const AchievementsPage = lazyWithRetry(() => import('@/pages/achievements/AchievementsPage'));
const NexusScorePage = lazyWithRetry(() => import('@/pages/nexus-score/NexusScorePage'));
const GoalsPage = lazyWithRetry(() => import('@/pages/goals/GoalsPage'));
const GoalDetailPage = lazyWithRetry(() => import('@/pages/goals/GoalDetailPage'));
const PollsPage = lazyWithRetry(() => import('@/pages/polls/PollsPage'));
const JobsPage = lazyWithRetry(() => import('@/pages/jobs/JobsPage'));
const JobDetailPage = lazyWithRetry(() => import('@/pages/jobs/JobDetailPage'));
const CreateJobPage = lazyWithRetry(() => import('@/pages/jobs/CreateJobPage'));
const JobAnalyticsPage = lazyWithRetry(() => import('@/pages/jobs/JobAnalyticsPage'));
const JobAlertsPage = lazyWithRetry(() => import('@/pages/jobs/JobAlertsPage'));
const MyApplicationsPage = lazyWithRetry(() => import('@/pages/jobs/MyApplicationsPage'));
const JobKanbanPage = lazyWithRetry(() => import('@/pages/jobs/JobKanbanPage'));
const EmployerBrandPage = lazyWithRetry(() => import('@/pages/jobs/EmployerBrandPage'));
const TalentSearchPage = lazyWithRetry(() => import('@/pages/jobs/TalentSearchPage'));
const BiasAuditPage = lazyWithRetry(() => import('@/pages/jobs/BiasAuditPage'));
const EmployerOnboardingPage = lazyWithRetry(() => import('@/pages/jobs/EmployerOnboardingPage'));
const IdeationPage = lazyWithRetry(() => import('@/pages/ideation/IdeationPage'));
const ChallengeDetailPage = lazyWithRetry(() => import('@/pages/ideation/ChallengeDetailPage'));
const IdeaDetailPage = lazyWithRetry(() => import('@/pages/ideation/IdeaDetailPage'));
const CreateChallengePage = lazyWithRetry(() => import('@/pages/ideation/CreateChallengePage'));
const CampaignsPage = lazyWithRetry(() => import('@/pages/ideation/CampaignsPage'));
const CampaignDetailPage = lazyWithRetry(() => import('@/pages/ideation/CampaignDetailPage'));
const OutcomesDashboardPage = lazyWithRetry(() => import('@/pages/ideation/OutcomesDashboardPage'));
const CaringCommunityPage = lazyWithRetry(() => import('@/pages/caring-community/CaringCommunityPage'));
const RequestHelpPage = lazyWithRetry(() => import('@/pages/caring-community/RequestHelpPage'));
const MySupportRelationshipsPage = lazyWithRetry(() => import('@/pages/caring-community/MySupportRelationshipsPage'));
const InviteRedemptionPage = lazyWithRetry(() => import('@/pages/caring-community/InviteRedemptionPage'));
const OfferFavourPage = lazyWithRetry(() => import('@/pages/caring-community/OfferFavourPage'));
const MarktPage = lazyWithRetry(() => import('@/pages/caring-community/MarktPage'));
const LoyaltyHistoryPage = lazyWithRetry(() => import('@/pages/caring-community/LoyaltyHistoryPage'));
const FutureCareFundPage = lazyWithRetry(() => import('@/pages/caring-community/FutureCareFundPage'));
const HourTransferPage = lazyWithRetry(() => import('@/pages/caring-community/HourTransferPage'));
const HourGiftPage = lazyWithRetry(() => import('@/pages/caring-community/HourGiftPage'));
const SafeguardingReportPage = lazyWithRetry(() => import('@/pages/caring-community/SafeguardingReportPage'));
const MySafeguardingReportsPage = lazyWithRetry(() => import('@/pages/caring-community/MySafeguardingReportsPage'));
const CareProviderDirectoryPage = lazyWithRetry(() => import('@/pages/caring-community/CareProviderDirectoryPage'));
const MyTrustTierPage = lazyWithRetry(() => import('@/pages/caring-community/MyTrustTierPage'));
const MyDataExportPage = lazyWithRetry(() => import('@/pages/caring-community/MyDataExportPage'));
const WarmthPassPage = lazyWithRetry(() => import('@/pages/caring-community/WarmthPassPage'));
const CaregiverDashboardPage = lazyWithRetry(() => import('@/pages/caring-community/CaregiverDashboardPage'));
const LinkCareReceiverPage = lazyWithRetry(() => import('@/pages/caring-community/LinkCareReceiverPage'));
const CoverCarePage = lazyWithRetry(() => import('@/pages/caring-community/CoverCarePage'));
const MunicipalSurveyPage = lazyWithRetry(() => import('@/pages/caring-community/MunicipalSurveyPage'));
const ProjectAnnouncementsPage = lazyWithRetry(() => import('@/pages/caring-community/ProjectAnnouncementsPage'));
const CivicDigestPage = lazyWithRetry(() => import('@/pages/caring-community/CivicDigestPage'));
const MunicipalityFeedbackPage = lazyWithRetry(() => import('@/pages/caring-community/MunicipalityFeedbackPage'));
const SuccessStoriesPage = lazyWithRetry(() => import('@/pages/caring-community/SuccessStoriesPage'));
const DataExportPage = lazyWithRetry(() => import('@/pages/settings/DataExportPage'));
const ClubsPage = lazyWithRetry(() => import('@/pages/clubs/ClubsPage'));
const VereinMembersImportPage = lazyWithRetry(() => import('@/pages/clubs/VereinMembersImportPage'));
const MyVereinInvitationsPage = lazyWithRetry(() => import('@/pages/profile/MyVereinInvitationsPage'));
const VereinDuesManagementPage = lazyWithRetry(() => import('@/pages/vereine/VereinDuesManagementPage'));
const MyVereinDuesPage = lazyWithRetry(() => import('@/pages/profile/MyVereinDuesPage'));
const MunicipalityCalendarPage = lazyWithRetry(() => import('@/pages/public/MunicipalityCalendarPage'));
const RegionalPointsPage = lazyWithRetry(() => import('@/pages/wallet/RegionalPointsPage'));
const MyAdCampaignsPage = lazyWithRetry(() => import('@/pages/advertise/MyAdCampaignsPage'));
const MyPushCampaignsPage = lazyWithRetry(() => import('@/pages/advertise/MyPushCampaignsPage'));
const VolunteeringPage = lazyWithRetry(() => import('@/pages/volunteering/VolunteeringPage'));
const CreateOpportunityPage = lazyWithRetry(() => import('@/pages/volunteering/CreateOpportunityPage'));
const OpportunityDetailPage = lazyWithRetry(() => import('@/pages/volunteering/OpportunityDetailPage'));
const VolOrgDashboardPage = lazyWithRetry(() => import('@/pages/volunteering/VolOrgDashboardPage'));
const MyOrganisationsPage = lazyWithRetry(() => import('@/pages/volunteering/MyOrganisationsPage'));
const OrganisationsPage = lazyWithRetry(() => import('@/pages/organisations/OrganisationsPage'));
const OrganisationDetailPage = lazyWithRetry(() => import('@/pages/organisations/OrganisationDetailPage'));
const RegisterOrganisationPage = lazyWithRetry(() => import('@/pages/organisations/RegisterOrganisationPage'));
const FeedPage = lazyWithRetry(() => import('@/pages/feed/FeedPage'));
const BookmarksPage = lazyWithRetry(() => import('@/pages/bookmarks/BookmarksPage'));
const BlogPage = lazyWithRetry(() => import('@/pages/blog/BlogPage'));
const BlogPostPage = lazyWithRetry(() => import('@/pages/blog/BlogPostPage'));
const ResourcesPage = lazyWithRetry(() => import('@/pages/resources/ResourcesPage'));
const KnowledgeBasePage = lazyWithRetry(() => import('@/pages/kb/KnowledgeBasePage'));
const KBArticlePage = lazyWithRetry(() => import('@/pages/kb/KBArticlePage'));
const FederationHubPage = lazyWithRetry(() => import('@/pages/federation/FederationHubPage'));
const FederationPartnersPage = lazyWithRetry(() => import('@/pages/federation/FederationPartnersPage'));
const FederationPartnerDetailPage = lazyWithRetry(() => import('@/pages/federation/FederationPartnerDetailPage'));
const FederationMembersPage = lazyWithRetry(() => import('@/pages/federation/FederationMembersPage'));
const FederationMemberProfilePage = lazyWithRetry(() => import('@/pages/federation/FederationMemberProfilePage'));
const FederationMessagesPage = lazyWithRetry(() => import('@/pages/federation/FederationMessagesPage'));
const FederationListingsPage = lazyWithRetry(() => import('@/pages/federation/FederationListingsPage'));
const FederationEventsPage = lazyWithRetry(() => import('@/pages/federation/FederationEventsPage'));
const FederationGroupsPage = lazyWithRetry(() => import('@/pages/federation/FederationGroupsPage'));
const FederationSettingsPage = lazyWithRetry(() => import('@/pages/federation/FederationSettingsPage'));
const FederationOnboardingPage = lazyWithRetry(() => import('@/pages/federation/FederationOnboardingPage'));
const FederationConnectionsPage = lazyWithRetry(() => import('@/pages/federation/FederationConnectionsPage'));
const OnboardingPage = lazyWithRetry(() => import('@/pages/onboarding/OnboardingPage'));
const GroupExchangesPage = lazyWithRetry(() => import('@/pages/group-exchanges/GroupExchangesPage'));
const CreateGroupExchangePage = lazyWithRetry(() => import('@/pages/group-exchanges/CreateGroupExchangePage'));
const GroupExchangeDetailPage = lazyWithRetry(() => import('@/pages/group-exchanges/GroupExchangeDetailPage'));
const MatchesPage = lazyWithRetry(() => import('@/pages/matches/MatchesPage'));
const ReviewsPage = lazyWithRetry(() => import('@/pages/reviews/ReviewsPage'));
const NewsletterUnsubscribePage = lazyWithRetry(() => import('@/pages/newsletter/NewsletterUnsubscribePage'));
const AiChatPage = lazyWithRetry(() => import('@/pages/chat/AiChatPage'));
const ConnectionsPage = lazyWithRetry(() => import('@/pages/connections/ConnectionsPage'));
const SkillsBrowsePage = lazyWithRetry(() => import('@/pages/skills/SkillsBrowsePage'));
const ActivityDashboardPage = lazyWithRetry(() => import('@/pages/activity/ActivityDashboardPage'));
const HashtagPage = lazyWithRetry(() => import('@/pages/feed/HashtagPage'));
const HashtagsDiscoveryPage = lazyWithRetry(() => import('@/pages/feed/HashtagsDiscoveryPage'));
const PostDetailPage = lazyWithRetry(() => import('@/pages/feed/PostDetailPage'));
const ExplorePage = lazyWithRetry(() => import('@/pages/explore/ExplorePage'));

// Marketplace Pages
const MarketplacePage = lazyWithRetry(() => import('./pages/marketplace/MarketplacePage'));
const MarketplaceListingPage = lazyWithRetry(() => import('./pages/marketplace/MarketplaceListingPage'));
const CreateMarketplaceListingPage = lazyWithRetry(() => import('./pages/marketplace/CreateMarketplaceListingPage'));
const MarketplaceSearchPage = lazyWithRetry(() => import('./pages/marketplace/MarketplaceSearchPage'));
const SellerProfilePage = lazyWithRetry(() => import('./pages/marketplace/SellerProfilePage'));
const MarketplaceCategoryPage = lazyWithRetry(() => import('./pages/marketplace/MarketplaceCategoryPage'));
const EditMarketplaceListingPage = lazyWithRetry(() => import('./pages/marketplace/EditMarketplaceListingPage'));
const MyListingsPage = lazyWithRetry(() => import('./pages/marketplace/MyListingsPage'));
const MyOffersPage = lazyWithRetry(() => import('./pages/marketplace/MyOffersPage'));
const MarketplaceCollectionsPage = lazyWithRetry(() => import('./pages/marketplace/MarketplaceCollectionsPage'));
const FreeItemsPage = lazyWithRetry(() => import('./pages/marketplace/FreeItemsPage'));
const MarketplaceMapSearchPage = lazyWithRetry(() => import('./pages/marketplace/MarketplaceMapSearchPage'));
const BuyerOrdersPage = lazyWithRetry(() => import('./pages/marketplace/BuyerOrdersPage'));
const SellerOrdersPage = lazyWithRetry(() => import('./pages/marketplace/SellerOrdersPage'));
const StripeOnboardingPage = lazyWithRetry(() => import('./pages/marketplace/StripeOnboardingPage'));
const MerchantOnboardingPage = lazyWithRetry(() => import('./pages/marketplace/MerchantOnboardingPage'));
const CouponsPage = lazyWithRetry(() => import('./pages/coupons/CouponsPage'));

// Premium (member tiers — AG58)
const PricingPage = lazyWithRetry(() => import('./pages/premium/PricingPage'));
const SubscriptionReturnPage = lazyWithRetry(() => import('./pages/premium/SubscriptionReturnPage'));
const MySubscriptionPage = lazyWithRetry(() => import('./pages/premium/MySubscriptionPage'));
const CouponDetailPage = lazyWithRetry(() => import('./pages/coupons/CouponDetailPage'));
const SellerCouponsPage = lazyWithRetry(() => import('./pages/marketplace/seller/SellerCouponsPage'));
const SellerCouponEditPage = lazyWithRetry(() => import('./pages/marketplace/seller/SellerCouponEditPage'));
const SellerPickupSlotsPage = lazyWithRetry(() => import('./pages/marketplace/seller/SellerPickupSlotsPage'));
const SellerPickupScanPage = lazyWithRetry(() => import('./pages/marketplace/seller/SellerPickupScanPage'));
const MyPickupsPage = lazyWithRetry(() => import('./pages/marketplace/MyPickupsPage'));

// Static Pages
const DevelopmentStatusPage = lazyWithRetry(() => import('@/pages/public/DevelopmentStatusPage'));
const AboutPage = lazyWithRetry(() => import('@/pages/public/AboutPage'));
const ContactPage = lazyWithRetry(() => import('@/pages/public/ContactPage'));
const TermsPage = lazyWithRetry(() => import('@/pages/public/TermsPage'));
const PrivacyPage = lazyWithRetry(() => import('@/pages/public/PrivacyPage'));
const AccessibilityPage = lazyWithRetry(() => import('@/pages/public/AccessibilityPage'));
const CookiesPage = lazyWithRetry(() => import('@/pages/public/CookiesPage'));
const CommunityGuidelinesPage = lazyWithRetry(() => import('@/pages/public/CommunityGuidelinesPage'));
const AcceptableUsePage = lazyWithRetry(() => import('@/pages/public/AcceptableUsePage'));
const LegalHubPage = lazyWithRetry(() => import('@/pages/public/LegalHubPage'));
const LegalVersionHistoryPage = lazyWithRetry(() => import('@/pages/public/LegalVersionHistoryPage'));
const FaqPage = lazyWithRetry(() => import('@/pages/public/FaqPage'));
const HelpCenterPage = lazyWithRetry(() => import('@/pages/help/HelpCenterPage'));
const PilotInquiryPage = lazyWithRetry(() => import('@/pages/public/PilotInquiryPage'));
const PilotApplyPage = lazyWithRetry(() => import('@/pages/public/PilotApplyPage'));
const PilotApplyStatusPage = lazyWithRetry(() => import('@/pages/public/PilotApplyStatusPage'));

// Platform Legal Pages (provider-level, distinct from tenant legal docs)
const PlatformTermsPage = lazyWithRetry(() => import('@/pages/platform/PlatformTermsPage'));
const PlatformPrivacyPage = lazyWithRetry(() => import('@/pages/platform/PlatformPrivacyPage'));
const PlatformDisclaimerPage = lazyWithRetry(() => import('@/pages/platform/PlatformDisclaimerPage'));
const CustomPage = lazyWithRetry(() => import('@/pages/public/CustomPage'));

// About Sub-Pages
const TimebankingGuidePage = lazyWithRetry(() => import('@/pages/about/TimebankingGuidePage'));
// AG60 — Developers portal (Partner API docs)
const DevelopersHomePage = lazyWithRetry(() => import('@/pages/developers/DevelopersHomePage'));
// AG59 — Paid Regional Analytics product (public landing + partner dashboard)
const RegionalAnalyticsLandingPage = lazyWithRetry(() => import('@/pages/public/RegionalAnalyticsLandingPage'));
const PartnerDashboardPage = lazyWithRetry(() => import('@/pages/partner-analytics/PartnerDashboardPage'));
const DevelopersAuthPage = lazyWithRetry(() => import('@/pages/developers/DevelopersAuthPage'));
const DevelopersEndpointsPage = lazyWithRetry(() => import('@/pages/developers/DevelopersEndpointsPage'));
const DevelopersWebhooksPage = lazyWithRetry(() => import('@/pages/developers/DevelopersWebhooksPage'));
const PartnerPage = lazyWithRetry(() => import('@/pages/about/PartnerPage'));
const SocialPrescribingPage = lazyWithRetry(() => import('@/pages/about/SocialPrescribingPage'));
const ImpactSummaryPage = lazyWithRetry(() => import('@/pages/about/ImpactSummaryPage'));
const ImpactReportPage = lazyWithRetry(() => import('@/pages/about/ImpactReportPage'));
const StrategicPlanPage = lazyWithRetry(() => import('@/pages/about/StrategicPlanPage'));

/**
 * Gate that only renders children for a specific tenant slug.
 * Other tenants see NotFoundPage. Used for tenant-specific content pages
 * (e.g. hOUR Timebank's impact report, strategic plan, etc.)
 */
function TenantSlugGate({ slug, children }: { slug: string; children: React.ReactNode }) {
  const { tenant } = useTenant();
  if (tenant?.slug !== slug) {
    return <Navigate to="about" replace />;
  }
  return <>{children}</>;
}

/**
 * All application routes rendered inside TenantShell.
 * This is rendered identically at both / and /:tenantSlug/ prefixes.
 */
function AppRoutes() {
  return (
    <>
      {/* Auth Routes (no navbar/footer) */}
      <Route element={<AuthLayout />}>
        <Route path="login" element={<LoginPage />} />
        <Route path="register" element={<RegisterPage />} />
        <Route path="password/forgot" element={<ForgotPasswordPage />} />
        <Route path="password/reset" element={<ResetPasswordPage />} />
        <Route path="verify-email" element={<VerifyEmailPage />} />
        <Route path="verify-identity" element={<VerifyIdentityPage />} />
        <Route path="auth/oauth/callback" element={<OauthCallbackPage />} />
      </Route>

      {/* Main Routes (with navbar/footer) */}
      <Route element={<Layout />}>
        {/* Public Routes */}
        <Route index element={<ErrorBoundary><HomePage /></ErrorBoundary>} />
        <Route path="development-status" element={<ErrorBoundary><DevelopmentStatusPage /></ErrorBoundary>} />
        <Route path="about" element={<ErrorBoundary><AboutPage /></ErrorBoundary>} />
        <Route path="faq" element={<ErrorBoundary><FaqPage /></ErrorBoundary>} />
        <Route path="contact" element={<ErrorBoundary><ContactPage /></ErrorBoundary>} />
        <Route path="pilot-inquiry" element={<ErrorBoundary><PilotInquiryPage /></ErrorBoundary>} />
        <Route path="pilot-apply" element={<ErrorBoundary><PilotApplyPage /></ErrorBoundary>} />
        <Route path="pilot-apply/status/:token" element={<ErrorBoundary><PilotApplyStatusPage /></ErrorBoundary>} />
        <Route path="help" element={<ErrorBoundary><HelpCenterPage /></ErrorBoundary>} />
        <Route path="terms" element={<ErrorBoundary><TermsPage /></ErrorBoundary>} />
        <Route path="terms/versions" element={<ErrorBoundary><LegalVersionHistoryPage /></ErrorBoundary>} />
        <Route path="privacy" element={<ErrorBoundary><PrivacyPage /></ErrorBoundary>} />
        <Route path="privacy/versions" element={<ErrorBoundary><LegalVersionHistoryPage /></ErrorBoundary>} />
        <Route path="accessibility" element={<ErrorBoundary><AccessibilityPage /></ErrorBoundary>} />
        <Route path="accessibility/versions" element={<ErrorBoundary><LegalVersionHistoryPage /></ErrorBoundary>} />
        <Route path="cookies" element={<ErrorBoundary><CookiesPage /></ErrorBoundary>} />
        <Route path="cookies/versions" element={<ErrorBoundary><LegalVersionHistoryPage /></ErrorBoundary>} />
        <Route path="community-guidelines" element={<ErrorBoundary><CommunityGuidelinesPage /></ErrorBoundary>} />
        <Route path="community-guidelines/versions" element={<ErrorBoundary><LegalVersionHistoryPage /></ErrorBoundary>} />
        <Route path="acceptable-use" element={<ErrorBoundary><AcceptableUsePage /></ErrorBoundary>} />
        <Route path="acceptable-use/versions" element={<ErrorBoundary><LegalVersionHistoryPage /></ErrorBoundary>} />
        <Route path="legal" element={<ErrorBoundary><LegalHubPage /></ErrorBoundary>} />
        <Route path="platform/terms" element={<ErrorBoundary><PlatformTermsPage /></ErrorBoundary>} />
        <Route path="platform/privacy" element={<ErrorBoundary><PlatformPrivacyPage /></ErrorBoundary>} />
        <Route path="platform/disclaimer" element={<ErrorBoundary><PlatformDisclaimerPage /></ErrorBoundary>} />
        <Route path="timebanking-guide" element={<ErrorBoundary><TimebankingGuidePage /></ErrorBoundary>} />

        {/* AG60 — Developers portal (public docs for the Partner API) */}
        <Route path="developers" element={<ErrorBoundary><DevelopersHomePage /></ErrorBoundary>} />
        <Route path="developers/auth" element={<ErrorBoundary><DevelopersAuthPage /></ErrorBoundary>} />
        <Route path="developers/endpoints" element={<ErrorBoundary><DevelopersEndpointsPage /></ErrorBoundary>} />
        <Route path="developers/webhooks" element={<ErrorBoundary><DevelopersWebhooksPage /></ErrorBoundary>} />

        {/* AG59 — Paid Regional Analytics — public marketing + token-auth partner dashboard */}
        <Route path="regional-analytics" element={<ErrorBoundary><RegionalAnalyticsLandingPage /></ErrorBoundary>} />
        <Route path="partner-analytics/dashboard" element={<ErrorBoundary><PartnerDashboardPage /></ErrorBoundary>} />

        {/* Newsletter unsubscribe — public, no auth, token-based */}
        <Route path="newsletter/unsubscribe" element={<ErrorBoundary><NewsletterUnsubscribePage /></ErrorBoundary>} />

        {/* Explore / Discover — curated discovery page */}
        <Route path="explore" element={<ErrorBoundary><ExplorePage /></ErrorBoundary>} />

        {/* Tenant 2 (hOUR Timebank) specific pages — redirect other tenants to /about */}
        <Route path="partner" element={<ErrorBoundary><TenantSlugGate slug="hour-timebank"><PartnerPage /></TenantSlugGate></ErrorBoundary>} />
        <Route path="social-prescribing" element={<ErrorBoundary><TenantSlugGate slug="hour-timebank"><SocialPrescribingPage /></TenantSlugGate></ErrorBoundary>} />
        <Route path="impact-summary" element={<ErrorBoundary><TenantSlugGate slug="hour-timebank"><ImpactSummaryPage /></TenantSlugGate></ErrorBoundary>} />
        <Route path="impact-report" element={<ErrorBoundary><TenantSlugGate slug="hour-timebank"><ImpactReportPage /></TenantSlugGate></ErrorBoundary>} />
        <Route path="strategic-plan" element={<ErrorBoundary><TenantSlugGate slug="hour-timebank"><StrategicPlanPage /></TenantSlugGate></ErrorBoundary>} />

        {/* Dynamic CMS pages created via admin Page Builder */}
        <Route path="page/:slug" element={<ErrorBoundary><CustomPage /></ErrorBoundary>} />

        {/* Public: Blog (feature-gated) */}
        <Route path="blog" element={
          <FeatureGate feature="blog" redirect="/">
            <FeatureErrorBoundary featureName="Blog">
              <BlogPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="blog/:slug" element={
          <FeatureGate feature="blog" redirect="/">
            <FeatureErrorBoundary featureName="Blog">
              <BlogPostPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Public but can show auth-specific content (module-gated) */}
        <Route path="listings" element={
          <FeatureGate module="listings" redirect="/">
            <FeatureErrorBoundary featureName="Listings">
              <ListingsPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="listings/:id" element={
          <FeatureGate module="listings" redirect="/">
            <FeatureErrorBoundary featureName="Listings">
              <ListingDetailPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Public: Events (feature-gated, view-only) */}
        <Route path="events" element={
          <FeatureGate feature="events" fallback={<ComingSoonPage feature="Events" />}>
            <FeatureErrorBoundary featureName="Events">
              <EventsPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="events/:id" element={
          <FeatureGate feature="events" redirect="/">
            <FeatureErrorBoundary featureName="Events">
              <EventDetailPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Public: Groups (feature-gated, view-only) */}
        <Route path="groups" element={
          <FeatureGate feature="groups" fallback={<ComingSoonPage feature="Groups" />}>
            <FeatureErrorBoundary featureName="Groups">
              <GroupsPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="groups/:id" element={
          <FeatureGate feature="groups" redirect="/">
            <FeatureErrorBoundary featureName="Groups">
              <GroupDetailPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Public: Job Vacancies (feature-gated, view-only) */}
        <Route path="jobs" element={
          <FeatureGate feature="job_vacancies" fallback={<ComingSoonPage feature="Job Vacancies" />}>
            <FeatureErrorBoundary featureName="Job Vacancies">
              <JobsPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="jobs/:id" element={
          <FeatureGate feature="job_vacancies" redirect="/">
            <FeatureErrorBoundary featureName="Job Vacancies">
              <JobDetailPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Public: Marketplace (feature-gated, view-only) */}
        <Route path="marketplace" element={
          <FeatureGate feature="marketplace" fallback={<ComingSoonPage feature="Marketplace" />}>
            <FeatureErrorBoundary featureName="Marketplace">
              <MarketplacePage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="marketplace/search" element={
          <FeatureGate feature="marketplace" redirect="/">
            <FeatureErrorBoundary featureName="Marketplace">
              <MarketplaceSearchPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="marketplace/map" element={
          <FeatureGate feature="marketplace" redirect="/">
            <FeatureErrorBoundary featureName="Marketplace">
              <MarketplaceMapSearchPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="marketplace/seller/:id" element={
          <FeatureGate feature="marketplace" redirect="/">
            <FeatureErrorBoundary featureName="Marketplace">
              <SellerProfilePage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="marketplace/category/:slug" element={
          <FeatureGate feature="marketplace" redirect="/">
            <FeatureErrorBoundary featureName="Marketplace">
              <MarketplaceCategoryPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="marketplace/my-listings" element={
          <FeatureGate feature="marketplace" redirect="/">
            <FeatureErrorBoundary featureName="Marketplace">
              <MyListingsPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="marketplace/my-offers" element={
          <FeatureGate feature="marketplace" redirect="/">
            <FeatureErrorBoundary featureName="Marketplace">
              <MyOffersPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="marketplace/collections" element={
          <FeatureGate feature="marketplace" redirect="/">
            <FeatureErrorBoundary featureName="Marketplace">
              <MarketplaceCollectionsPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="marketplace/free" element={
          <FeatureGate feature="marketplace" fallback={<ComingSoonPage feature="Marketplace" />}>
            <FeatureErrorBoundary featureName="Marketplace">
              <FreeItemsPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="marketplace/seller/coupons" element={
          <FeatureGate feature="merchant_coupons" redirect="/">
            <FeatureErrorBoundary featureName="Merchant Coupons">
              <SellerCouponsPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="marketplace/seller/coupons/new" element={
          <FeatureGate feature="merchant_coupons" redirect="/">
            <FeatureErrorBoundary featureName="Merchant Coupons">
              <SellerCouponEditPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="marketplace/seller/coupons/:id/edit" element={
          <FeatureGate feature="merchant_coupons" redirect="/">
            <FeatureErrorBoundary featureName="Merchant Coupons">
              <SellerCouponEditPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="coupons" element={
          <FeatureGate feature="merchant_coupons" fallback={<ComingSoonPage feature="Coupons" />}>
            <FeatureErrorBoundary featureName="Coupons">
              <CouponsPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="coupons/:id" element={
          <FeatureGate feature="merchant_coupons" redirect="/coupons">
            <FeatureErrorBoundary featureName="Coupons">
              <CouponDetailPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="marketplace/:id/edit" element={
          <FeatureGate feature="marketplace" redirect="/">
            <FeatureErrorBoundary featureName="Marketplace">
              <EditMarketplaceListingPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="marketplace/:id" element={
          <FeatureGate feature="marketplace" redirect="/">
            <FeatureErrorBoundary featureName="Marketplace">
              <MarketplaceListingPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Public: Caring Community (feature-gated hub) */}
        <Route path={CARING_COMMUNITY_ROUTE.path} element={
          <FeatureGate feature={CARING_COMMUNITY_ROUTE.feature} fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <CaringCommunityPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        <Route element={<ProtectedRoute />}>
        {/* Member-facing: Low-friction help request (AG10) */}
        <Route path="caring-community/request-help" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <RequestHelpPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Member-facing: My Support Relationships (AG4) */}
        <Route path="caring-community/my-relationships" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <MySupportRelationshipsPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Caring Community — Offer a Favour (AG11) */}
        <Route path="caring-community/offer-favour" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <OfferFavourPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Caring Community — Unified Marktplatz (AG13) */}
        <Route path="caring-community/markt" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <MarktPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Caring Community — Time-credit ↔ marketplace loyalty redemption history */}
        <Route path="caring-community/loyalty/history" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <LoyaltyHistoryPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Caring Community — Future Care Fund (Zeitvorsorge) (K1) */}
        <Route path="caring-community/future-care-fund" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <FutureCareFundPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Caring Community — Cooperative-to-cooperative hour transfer (K3) */}
        <Route path="caring-community/hour-transfer" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <HourTransferPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Caring Community — Time-credit gifting (K5) */}
        <Route path="caring-community/hour-gift" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <HourGiftPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Caring Community — Safeguarding report submission (K9) */}
        <Route path="caring-community/safeguarding/report" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <SafeguardingReportPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Caring Community — Member's own safeguarding reports (K9) */}
        <Route path="caring-community/safeguarding/my-reports" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <MySafeguardingReportsPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* AG64 — Care-provider directory */}
        <Route path="caring-community/providers" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <CareProviderDirectoryPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* AG67 — Trust tier */}
        <Route path="caring-community/my-trust-tier" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <MyTrustTierPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* E3 — Member-side GDPR/FADP data export */}
        <Route path="caring-community/my-data-export" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <MyDataExportPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        <Route path="caring-community/warmth-pass" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <WarmthPassPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* AG68 — Caregiver dashboard + link flow */}
        <Route path="caring-community/caregiver" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <CaregiverDashboardPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="caring-community/caregiver/link" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <LinkCareReceiverPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="caring-community/caregiver/cover" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <CoverCarePage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="caring-community/surveys" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <MunicipalSurveyPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="caring-community/surveys/:id" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <MunicipalSurveyPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="caring-community/projects" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <ProjectAnnouncementsPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="caring-community/projects/:id" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <ProjectAnnouncementsPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* AG90 — Personalised Civic Digest */}
        <Route path="caring-community/civic-digest" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <CivicDigestPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* AG91 — Success Stories */}
        <Route path="caring-community/success-stories" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <SuccessStoriesPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* AG92 — Two-Way Municipality Feedback */}
        <Route path="caring-community/feedback" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Caring Community">
              <MunicipalityFeedbackPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        </Route>

        {/* GDPR member data export (R3) */}
        <Route path="settings/data-export" element={<ErrorBoundary><DataExportPage /></ErrorBoundary>} />

        {/* Clubs & Associations directory (AG15) — public, no feature gate */}
        <Route path="clubs" element={<ErrorBoundary><ClubsPage /></ErrorBoundary>} />
        <Route path="clubs/:id/admin/import" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Verein Import">
              <VereinMembersImportPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* AG54 — Verein membership dues */}
        <Route path="clubs/:id/admin/dues" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Verein Dues">
              <VereinDuesManagementPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="me/verein-dues" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="My Verein Dues">
              <MyVereinDuesPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* AG55 — Verein-to-Verein federation */}
        <Route path="me/verein-invitations" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Verein Invitations">
              <MyVereinInvitationsPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="municipality-calendar" element={
          <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Caring Community" />}>
            <FeatureErrorBoundary featureName="Municipality Calendar">
              <MunicipalityCalendarPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Advertiser self-serve portal (AG56/AG57) */}
        <Route path="advertise/campaigns" element={
          <ProtectedRoute>
            <FeatureGate feature="local_advertising" redirect="/">
              <ErrorBoundary><MyAdCampaignsPage /></ErrorBoundary>
            </FeatureGate>
          </ProtectedRoute>
        } />
        <Route path="advertise/push-campaigns" element={
          <ProtectedRoute>
            <FeatureGate feature="local_advertising" redirect="/">
              <ErrorBoundary><MyPushCampaignsPage /></ErrorBoundary>
            </FeatureGate>
          </ProtectedRoute>
        } />

        {/* Public: Caring Community invite redemption — no auth, no feature gate needed */}
        <Route path="join/:code" element={<ErrorBoundary><InviteRedemptionPage /></ErrorBoundary>} />

        {/* Public: Volunteering (feature-gated, view-only) */}
        <Route path="volunteering" element={
          <FeatureGate feature="volunteering" fallback={<ComingSoonPage feature="Volunteering" />}>
            <FeatureErrorBoundary featureName="Volunteering">
              <VolunteeringPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="volunteering/opportunities/:id" element={
          <FeatureGate feature="volunteering" redirect="/">
            <FeatureErrorBoundary featureName="Volunteering">
              <OpportunityDetailPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Public: Resources (feature-gated) */}
        <Route path="resources" element={
          <FeatureGate feature="resources" fallback={<ComingSoonPage feature="Resources" />}>
            <FeatureErrorBoundary featureName="Resources">
              <ResourcesPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Public: Knowledge Base (feature-gated) */}
        <Route path="kb" element={
          <FeatureGate feature="resources" fallback={<ComingSoonPage feature="Knowledge Base" />}>
            <FeatureErrorBoundary featureName="Knowledge Base">
              <KnowledgeBasePage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="kb/:id" element={
          <FeatureGate feature="resources" redirect="/">
            <FeatureErrorBoundary featureName="Knowledge Base">
              <KBArticlePage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Public: Organisations (feature-gated, view-only) */}
        <Route path="organisations" element={
          <FeatureGate feature="organisations" fallback={<ComingSoonPage feature="Organisations" />}>
            <FeatureErrorBoundary featureName="Organisations">
              <OrganisationsPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="organisations/:id" element={
          <FeatureGate feature="organisations" redirect="/">
            <FeatureErrorBoundary featureName="Organisations">
              <OrganisationDetailPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Public: Ideation (feature-gated, view-only) */}
        <Route path="ideation" element={
          <FeatureGate feature="ideation_challenges" fallback={<ComingSoonPage feature="Ideation Challenges" />}>
            <FeatureErrorBoundary featureName="Ideation Challenges">
              <IdeationPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="ideation/:id" element={
          <FeatureGate feature="ideation_challenges" redirect="/">
            <FeatureErrorBoundary featureName="Ideation Challenges">
              <ChallengeDetailPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />
        <Route path="ideation/:challengeId/ideas/:id" element={
          <FeatureGate feature="ideation_challenges" redirect="/">
            <FeatureErrorBoundary featureName="Ideation Challenges">
              <IdeaDetailPage />
            </FeatureErrorBoundary>
          </FeatureGate>
        } />

        {/* Protected Routes */}
        <Route element={<ProtectedRoute />}>
          {/* Core Features (module-gated) */}
          <Route path="dashboard" element={
            <FeatureGate module="dashboard" redirect="/">
              <FeatureErrorBoundary featureName="Dashboard">
                <DashboardPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="listings/create" element={
            <FeatureGate module="listings" redirect="/">
              <FeatureErrorBoundary featureName="Listings">
                <CreateListingPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="listings/edit/:id" element={
            <FeatureGate module="listings" redirect="/">
              <FeatureErrorBoundary featureName="Listings">
                <CreateListingPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="messages" element={
            <FeatureGate module="messages" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Messages">
                <MessagesPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="messages/new/:userId" element={
            <FeatureGate module="messages" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Messages">
                <ConversationPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="messages/:id" element={
            <FeatureGate module="messages" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Messages">
                <ConversationPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="wallet" element={
            <FeatureGate module="wallet" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Wallet">
                <WalletPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="wallet/regional-points" element={
            <FeatureGate feature="caring_community" fallback={<ComingSoonPage feature="Regional Points" />}>
              <FeatureErrorBoundary featureName="Regional Points">
                <RegionalPointsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="profile" element={
            <FeatureGate module="profile" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Profile">
                <ProfilePage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="profile/:id" element={
            <FeatureGate module="profile" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Profile">
                <ProfilePage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          {/* SOC10 — Saved collections */}
          <Route path="me/collections" element={
            <FeatureErrorBoundary featureName="My Collections">
              <MyCollectionsPage />
            </FeatureErrorBoundary>
          } />
          <Route path="me/collections/:id" element={
            <FeatureErrorBoundary featureName="Collection">
              <CollectionDetailPage />
            </FeatureErrorBoundary>
          } />
          <Route path="users/:userId/collections" element={
            <FeatureErrorBoundary featureName="Public Collections">
              <UserCollectionsView />
            </FeatureErrorBoundary>
          } />
          {/* SOC14 — Appreciation wall */}
          <Route path="users/:userId/appreciations" element={
            <FeatureErrorBoundary featureName="Appreciations">
              <AppreciationWallPage />
            </FeatureErrorBoundary>
          } />
          <Route path="settings" element={
            <FeatureGate module="settings" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Settings">
                <SettingsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="settings/blocked" element={
            <FeatureErrorBoundary featureName="Blocked Users">
              <BlockedUsersPage />
            </FeatureErrorBoundary>
          } />
          <Route path="verify-identity-optional" element={<VerifyIdentityOptionalPage />} />
          <Route path="verify-identity/callback" element={<VerifyIdentityOptionalPage />} />
          <Route path="search" element={
            <FeatureGate feature="search" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Search">
                <SearchPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="notifications" element={
            <FeatureGate module="notifications" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Notifications">
                <NotificationsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Onboarding Wizard */}
          <Route path="onboarding" element={<FeatureErrorBoundary featureName="Onboarding"><OnboardingPage /></FeatureErrorBoundary>} />

          {/* Feature-gated: Group Exchanges */}
          <Route path="group-exchanges" element={
            <FeatureGate feature="group_exchanges" fallback={<ComingSoonPage feature="Group Exchanges" />}>
              <FeatureErrorBoundary featureName="Group Exchanges">
                <GroupExchangesPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="group-exchanges/create" element={
            <FeatureGate feature="group_exchanges" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Group Exchanges">
                <CreateGroupExchangePage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="group-exchanges/:id" element={
            <FeatureGate feature="group_exchanges" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Group Exchanges">
                <GroupExchangeDetailPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Feature-gated: Exchanges */}
          <Route path="exchanges" element={
            <FeatureGate feature="exchange_workflow" fallback={<ComingSoonPage feature="Exchanges" />}>
              <FeatureErrorBoundary featureName="Exchanges">
                <ExchangesPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="exchanges/:id" element={
            <FeatureGate feature="exchange_workflow" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Exchanges">
                <ExchangeDetailPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="listings/:id/request-exchange" element={
            <FeatureGate feature="exchange_workflow" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Exchanges">
                <RequestExchangePage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Feature-gated: Members/Connections */}
          <Route path="members" element={
            <FeatureGate feature="connections" fallback={<ComingSoonPage feature="Members Directory" />}>
              <FeatureErrorBoundary featureName="Members Directory">
                <MembersPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="connections" element={
            <FeatureGate feature="connections" fallback={<ComingSoonPage feature="Connections" />}>
              <FeatureErrorBoundary featureName="Connections">
                <ConnectionsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Skills Browse */}
          <Route path="skills" element={
            <FeatureErrorBoundary featureName="Skills">
              <SkillsBrowsePage />
            </FeatureErrorBoundary>
          } />

          {/* Activity Dashboard */}
          <Route path="activity" element={
            <FeatureErrorBoundary featureName="Activity Dashboard">
              <ActivityDashboardPage />
            </FeatureErrorBoundary>
          } />

          {/* Feature-gated: AI Chat */}
          <Route path="chat" element={
            <FeatureGate feature="ai_chat" fallback={<ComingSoonPage feature="AI Assistant" />}>
              <FeatureErrorBoundary featureName="AI Assistant">
                <AiChatPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Feature-gated: Events (create/edit only — view routes are public) */}
          <Route path="events/create" element={
            <FeatureGate feature="events" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Events">
                <CreateEventPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="events/edit/:id" element={
            <FeatureGate feature="events" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Events">
                <CreateEventPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Feature-gated: Groups (create/edit only — view routes are public) */}
          <Route path="groups/create" element={
            <FeatureGate feature="groups" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Groups">
                <CreateGroupPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="groups/edit/:id" element={
            <FeatureGate feature="groups" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Groups">
                <CreateGroupPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Feature-gated: Gamification */}
          <Route path="achievements" element={
            <FeatureGate feature="gamification" fallback={<ComingSoonPage feature="Achievements" />}>
              <FeatureErrorBoundary featureName="Achievements">
                <AchievementsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="leaderboard" element={
            <FeatureGate feature="gamification" fallback={<ComingSoonPage feature="Leaderboard" />}>
              <FeatureErrorBoundary featureName="Leaderboard">
                <LeaderboardPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="nexus-score" element={
            <FeatureGate feature="gamification" fallback={<ComingSoonPage feature="NexusScore" />}>
              <FeatureErrorBoundary featureName="NexusScore">
                <NexusScorePage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Feature-gated: Goals */}
          <Route path="goals" element={
            <FeatureGate feature="goals" fallback={<ComingSoonPage feature="Goals" />}>
              <FeatureErrorBoundary featureName="Goals">
                <GoalsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="goals/:id" element={
            <FeatureGate feature="goals" fallback={<ComingSoonPage feature="Goals" />}>
              <FeatureErrorBoundary featureName="Goals">
                <GoalDetailPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Feature-gated: Polls */}
          <Route path="polls" element={
            <FeatureGate feature="polls" fallback={<ComingSoonPage feature="Polls" />}>
              <FeatureErrorBoundary featureName="Polls">
                <PollsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Feature-gated: Job Vacancies (create/edit/manage only — view routes are public) */}
          <Route path="jobs/create" element={
            <FeatureGate feature="job_vacancies" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Job Vacancies">
                <CreateJobPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="jobs/:id/edit" element={
            <FeatureGate feature="job_vacancies" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Job Vacancies">
                <CreateJobPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="jobs/:id/analytics" element={
            <FeatureGate feature="job_vacancies" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Job Vacancies">
                <JobAnalyticsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="jobs/alerts" element={
            <FeatureGate feature="job_vacancies" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Job Vacancies">
                <JobAlertsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="jobs/my-applications" element={
            <FeatureGate feature="job_vacancies" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Job Vacancies">
                <MyApplicationsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="jobs/:id/kanban" element={
            <FeatureGate feature="job_vacancies" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Job Vacancies">
                <JobKanbanPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="jobs/employers/:userId" element={
            <FeatureGate feature="job_vacancies" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Job Vacancies">
                <EmployerBrandPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="jobs/talent-search" element={
            <FeatureGate feature="job_vacancies" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Job Vacancies">
                <TalentSearchPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="jobs/bias-audit" element={
            <FeatureGate feature="job_vacancies" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Job Vacancies">
                <BiasAuditPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="jobs/employer-onboarding" element={
            <FeatureGate feature="job_vacancies" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Job Vacancies">
                <EmployerOnboardingPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Feature-gated: Marketplace (create/sell only — view routes are public) */}
          <Route path="marketplace/sell" element={
            <FeatureGate feature="marketplace" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Marketplace">
                <CreateMarketplaceListingPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="marketplace/orders" element={
            <FeatureGate feature="marketplace" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Marketplace">
                <BuyerOrdersPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="marketplace/orders/sales" element={
            <FeatureGate feature="marketplace" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Marketplace">
                <SellerOrdersPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="marketplace/seller/onboard" element={
            <FeatureGate feature="marketplace" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Marketplace">
                <StripeOnboardingPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="marketplace/become-partner" element={
            <FeatureGate feature="marketplace" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Marketplace">
                <MerchantOnboardingPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          {/* AG48 — alternate canonical path for the wizard */}
          <Route path="marketplace/seller/onboarding" element={
            <FeatureGate feature="marketplace" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Marketplace">
                <MerchantOnboardingPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          {/* AG45 — Click-and-collect */}
          <Route path="marketplace/seller/pickup-slots" element={
            <FeatureGate feature="marketplace" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Marketplace">
                <SellerPickupSlotsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="marketplace/seller/pickup-scan" element={
            <FeatureGate feature="marketplace" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Marketplace">
                <SellerPickupScanPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="marketplace/me/pickups" element={
            <FeatureGate feature="marketplace" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Marketplace">
                <MyPickupsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* AG58 — Member Premium Tiers */}
          <Route path="premium" element={
            <FeatureGate feature="member_premium" redirect="/">
              <FeatureErrorBoundary featureName="Premium">
                <PricingPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="premium/return" element={
            <FeatureGate feature="member_premium" redirect="/">
              <FeatureErrorBoundary featureName="Premium">
                <SubscriptionReturnPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="premium/manage" element={
            <FeatureGate feature="member_premium" redirect="/">
              <FeatureErrorBoundary featureName="Premium">
                <MySubscriptionPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Feature-gated: Ideation Challenges (create/edit/manage only — view routes are public) */}
          <Route path="ideation/create" element={
            <FeatureGate feature="ideation_challenges" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Ideation Challenges">
                <CreateChallengePage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="ideation/:id/edit" element={
            <FeatureGate feature="ideation_challenges" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Ideation Challenges">
                <CreateChallengePage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="ideation/campaigns" element={
            <FeatureGate feature="ideation_challenges" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Ideation Challenges">
                <CampaignsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="ideation/campaigns/:id" element={
            <FeatureGate feature="ideation_challenges" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Ideation Challenges">
                <CampaignDetailPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="ideation/outcomes" element={
            <FeatureGate feature="ideation_challenges" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Ideation Challenges">
                <OutcomesDashboardPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Feature-gated: Volunteering (create/manage only — view routes are public) */}
          <Route path="volunteering/create" element={
            <FeatureGate feature="volunteering" fallback={<ComingSoonPage feature="Volunteering" />}>
              <FeatureErrorBoundary featureName="Volunteering">
                <CreateOpportunityPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="volunteering/org/:orgId/dashboard" element={
            <FeatureGate feature="volunteering" fallback={<ComingSoonPage feature="Volunteering" />}>
              <FeatureErrorBoundary featureName="Volunteering">
                <VolOrgDashboardPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="volunteering/my-organisations" element={
            <FeatureGate feature="volunteering" fallback={<ComingSoonPage feature="Volunteering" />}>
              <FeatureErrorBoundary featureName="Volunteering">
                <MyOrganisationsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="volunteering/my-applications" element={<Navigate to="../volunteering?tab=applications" replace />} />

          {/* Feature-gated: Organisations (register only — view routes are public) */}
          <Route path="organisations/register" element={
            <FeatureGate feature="organisations" fallback={<ComingSoonPage feature="Organisations" />}>
              <FeatureErrorBoundary featureName="Organisations">
                <RegisterOrganisationPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Module-gated: Feed */}
          <Route path="feed" element={
            <FeatureGate module="feed" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Feed">
                <FeedPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="feed/posts/:id" element={
            <FeatureGate module="feed" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Feed">
                <PostDetailPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="feed/item/:type/:id" element={
            <FeatureGate module="feed" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Feed">
                <PostDetailPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="feed/hashtag/:tag" element={
            <FeatureGate module="feed" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Feed">
                <HashtagPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="feed/hashtags" element={
            <FeatureGate module="feed" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Feed">
                <HashtagsDiscoveryPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Bookmarks / Saved Items */}
          <Route path="saved" element={
            <FeatureErrorBoundary featureName="Bookmarks">
              <BookmarksPage />
            </FeatureErrorBoundary>
          } />

          {/* Feature-gated: Federation */}
          <Route path="federation" element={
            <FeatureGate feature="federation" fallback={<ComingSoonPage feature="Federation" />}>
              <FeatureErrorBoundary featureName="Federation">
                <FederationHubPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="federation/partners" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Federation">
                <FederationPartnersPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="federation/partners/:id" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Federation">
                <FederationPartnerDetailPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="federation/members" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Federation">
                <FederationMembersPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="federation/members/:id" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Federation">
                <FederationMemberProfilePage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="federation/messages" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Federation">
                <FederationMessagesPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="federation/listings" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Federation">
                <FederationListingsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="federation/events" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Federation">
                <FederationEventsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="federation/groups" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Federation">
                <FederationGroupsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="federation/settings" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Federation">
                <FederationSettingsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="federation/onboarding" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Federation">
                <FederationOnboardingPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="federation/connections" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Federation">
                <FederationConnectionsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Matches — cross-module matches page (MA1, requires auth) */}
          <Route path="matches" element={<ErrorBoundary><MatchesPage /></ErrorBoundary>} />
          <Route path="matches/preferences" element={<Navigate to="settings" replace />} />

          {/* Reviews — user reviews for completed exchanges */}
          <Route path="reviews" element={<ErrorBoundary><ReviewsPage /></ErrorBoundary>} />
        </Route>
      </Route>

      {/* Admin Panel (separate layout, no main navbar/footer) — fully lazy-loaded */}
      <Route path="admin/*" element={<AdminApp />} />

      {/* Broker Panel (simplified admin for brokers) — fully lazy-loaded */}
      <Route path="broker/*" element={<BrokerApp />} />

      {/* Community Caring Panel — fully lazy-loaded, gated by caring_community feature */}
      <Route path="caring/*" element={<CaringApp />} />

      {/* 404 Fallback (must be after admin to avoid catching /admin paths) */}
      <Route element={<Layout />}>
        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </>
  );
}

/**
 * HeroUIProvider wrapper that lives inside BrowserRouter so it can
 * pass React Router's navigate function to HeroUI components.
 * This enables client-side routing for HeroUI's href prop on
 * DropdownItem, Link, Breadcrumbs, etc.
 */
function HeroUIRouterProvider({ children }: { children: React.ReactNode }) {
  const navigate = useNavigate();
  return (
    <HeroUIProvider navigate={navigate}>
      {children}
    </HeroUIProvider>
  );
}

function App() {
  return (
    <ErrorBoundary>
      <HelmetProvider>
        <ThemeProvider>
          <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
            <HeroUIRouterProvider>
              <ScrollToTop />
              <CookieConsentProvider>
                <ToastProvider>
                  <Suspense fallback={<LoadingScreen />}>
                    <Routes>
                      {/* Single catch-all route — TenantShell detects tenant slug from
                          the first path segment (if it's not reserved like "admin").
                          When a slug IS found, TenantShell renders a nested <Routes>
                          with the slug stripped so child routes match correctly.
                          This avoids the `:tenantSlug/*` dynamic param route which caused
                          React Router v6 to rank `/:tenantSlug/listings` higher than
                          `/admin/*` (splat routes rank lowest in RRv6). */}
                      <Route path="/*" element={<TenantShell appRoutes={AppRoutes} />}>
                        {AppRoutes()}
                      </Route>
                    </Routes>
                  </Suspense>
                </ToastProvider>
              </CookieConsentProvider>
            </HeroUIRouterProvider>
          </BrowserRouter>
        </ThemeProvider>
      </HelmetProvider>
    </ErrorBoundary>
  );
}

export default App;
