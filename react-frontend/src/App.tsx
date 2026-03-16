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
          console.warn('[NEXUS] Chunk load error detected but user is typing — deferring reload');
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

// Google Maps Provider (loads API key, enables PlaceAutocompleteInput)
import { GoogleMapsProvider } from '@/components/location';

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

// Admin Panel (lazy-loaded — keeps recharts, jsPDF, admin sidebar/header out of main bundle)
const AdminApp = lazyWithRetry(() => import('@/admin/AdminApp'));

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
const SettingsPage = lazyWithRetry(() => import('@/pages/settings/SettingsPage'));
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
const PollsPage = lazyWithRetry(() => import('@/pages/polls/PollsPage'));
const JobsPage = lazyWithRetry(() => import('@/pages/jobs/JobsPage'));
const JobDetailPage = lazyWithRetry(() => import('@/pages/jobs/JobDetailPage'));
const CreateJobPage = lazyWithRetry(() => import('@/pages/jobs/CreateJobPage'));
const JobAnalyticsPage = lazyWithRetry(() => import('@/pages/jobs/JobAnalyticsPage'));
const JobAlertsPage = lazyWithRetry(() => import('@/pages/jobs/JobAlertsPage'));
const MyApplicationsPage = lazyWithRetry(() => import('@/pages/jobs/MyApplicationsPage'));
const IdeationPage = lazyWithRetry(() => import('@/pages/ideation/IdeationPage'));
const ChallengeDetailPage = lazyWithRetry(() => import('@/pages/ideation/ChallengeDetailPage'));
const IdeaDetailPage = lazyWithRetry(() => import('@/pages/ideation/IdeaDetailPage'));
const CreateChallengePage = lazyWithRetry(() => import('@/pages/ideation/CreateChallengePage'));
const CampaignsPage = lazyWithRetry(() => import('@/pages/ideation/CampaignsPage'));
const CampaignDetailPage = lazyWithRetry(() => import('@/pages/ideation/CampaignDetailPage'));
const OutcomesDashboardPage = lazyWithRetry(() => import('@/pages/ideation/OutcomesDashboardPage'));
const VolunteeringPage = lazyWithRetry(() => import('@/pages/volunteering/VolunteeringPage'));
const CreateOpportunityPage = lazyWithRetry(() => import('@/pages/volunteering/CreateOpportunityPage'));
const OpportunityDetailPage = lazyWithRetry(() => import('@/pages/volunteering/OpportunityDetailPage'));
const OrganisationsPage = lazyWithRetry(() => import('@/pages/organisations/OrganisationsPage'));
const OrganisationDetailPage = lazyWithRetry(() => import('@/pages/organisations/OrganisationDetailPage'));
const RegisterOrganisationPage = lazyWithRetry(() => import('@/pages/organisations/RegisterOrganisationPage'));
const FeedPage = lazyWithRetry(() => import('@/pages/feed/FeedPage'));
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
const FederationSettingsPage = lazyWithRetry(() => import('@/pages/federation/FederationSettingsPage'));
const FederationOnboardingPage = lazyWithRetry(() => import('@/pages/federation/FederationOnboardingPage'));
const FederationConnectionsPage = lazyWithRetry(() => import('@/pages/federation/FederationConnectionsPage'));
const OnboardingPage = lazyWithRetry(() => import('@/pages/onboarding/OnboardingPage'));
const GroupExchangesPage = lazyWithRetry(() => import('@/pages/group-exchanges/GroupExchangesPage'));
const CreateGroupExchangePage = lazyWithRetry(() => import('@/pages/group-exchanges/CreateGroupExchangePage'));
const GroupExchangeDetailPage = lazyWithRetry(() => import('@/pages/group-exchanges/GroupExchangeDetailPage'));
const MatchesPage = lazyWithRetry(() => import('@/pages/matches/MatchesPage'));
const NewsletterUnsubscribePage = lazyWithRetry(() => import('@/pages/newsletter/NewsletterUnsubscribePage'));
const AiChatPage = lazyWithRetry(() => import('@/pages/chat/AiChatPage'));
const ConnectionsPage = lazyWithRetry(() => import('@/pages/connections/ConnectionsPage'));
const SkillsBrowsePage = lazyWithRetry(() => import('@/pages/skills/SkillsBrowsePage'));
const ActivityDashboardPage = lazyWithRetry(() => import('@/pages/activity/ActivityDashboardPage'));
const HashtagPage = lazyWithRetry(() => import('@/pages/feed/HashtagPage'));
const HashtagsDiscoveryPage = lazyWithRetry(() => import('@/pages/feed/HashtagsDiscoveryPage'));

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

// Platform Legal Pages (provider-level, distinct from tenant legal docs)
const PlatformTermsPage = lazyWithRetry(() => import('@/pages/platform/PlatformTermsPage'));
const PlatformPrivacyPage = lazyWithRetry(() => import('@/pages/platform/PlatformPrivacyPage'));
const PlatformDisclaimerPage = lazyWithRetry(() => import('@/pages/platform/PlatformDisclaimerPage'));
const CustomPage = lazyWithRetry(() => import('@/pages/public/CustomPage'));

// About Sub-Pages
const TimebankingGuidePage = lazyWithRetry(() => import('@/pages/about/TimebankingGuidePage'));
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
      </Route>

      {/* Main Routes (with navbar/footer) */}
      <Route element={<Layout />}>
        {/* Public Routes */}
        <Route index element={<ErrorBoundary><HomePage /></ErrorBoundary>} />
        <Route path="development-status" element={<ErrorBoundary><DevelopmentStatusPage /></ErrorBoundary>} />
        <Route path="about" element={<ErrorBoundary><AboutPage /></ErrorBoundary>} />
        <Route path="faq" element={<ErrorBoundary><FaqPage /></ErrorBoundary>} />
        <Route path="contact" element={<ErrorBoundary><ContactPage /></ErrorBoundary>} />
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

        {/* Newsletter unsubscribe — public, no auth, token-based */}
        <Route path="newsletter/unsubscribe" element={<ErrorBoundary><NewsletterUnsubscribePage /></ErrorBoundary>} />

        {/* Matches — cross-module matches page (MA1) */}
        <Route path="matches" element={<ErrorBoundary><MatchesPage /></ErrorBoundary>} />
        <Route path="matches/preferences" element={<Navigate to="settings" replace />} />

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
          <Route path="settings" element={
            <FeatureGate module="settings" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Settings">
                <SettingsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
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

          {/* Feature-gated: Events */}
          <Route path="events" element={
            <FeatureGate feature="events" fallback={<ComingSoonPage feature="Events" />}>
              <FeatureErrorBoundary featureName="Events">
                <EventsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
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
          <Route path="events/:id" element={
            <FeatureGate feature="events" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Events">
                <EventDetailPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Feature-gated: Groups */}
          <Route path="groups" element={
            <FeatureGate feature="groups" fallback={<ComingSoonPage feature="Groups" />}>
              <FeatureErrorBoundary featureName="Groups">
                <GroupsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
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
          <Route path="groups/:id" element={
            <FeatureGate feature="groups" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Groups">
                <GroupDetailPage />
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

          {/* Feature-gated: Polls */}
          <Route path="polls" element={
            <FeatureGate feature="polls" fallback={<ComingSoonPage feature="Polls" />}>
              <FeatureErrorBoundary featureName="Polls">
                <PollsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Feature-gated: Job Vacancies */}
          <Route path="jobs" element={
            <FeatureGate feature="job_vacancies" fallback={<ComingSoonPage feature="Job Vacancies" />}>
              <FeatureErrorBoundary featureName="Job Vacancies">
                <JobsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
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
          <Route path="jobs/:id" element={
            <FeatureGate feature="job_vacancies" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Job Vacancies">
                <JobDetailPage />
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

          {/* Feature-gated: Ideation Challenges */}
          <Route path="ideation" element={
            <FeatureGate feature="ideation_challenges" fallback={<ComingSoonPage feature="Ideation Challenges" />}>
              <FeatureErrorBoundary featureName="Ideation Challenges">
                <IdeationPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
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
          <Route path="ideation/:id" element={
            <FeatureGate feature="ideation_challenges" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Ideation Challenges">
                <ChallengeDetailPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="ideation/:challengeId/ideas/:id" element={
            <FeatureGate feature="ideation_challenges" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Ideation Challenges">
                <IdeaDetailPage />
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

          {/* Feature-gated: Volunteering */}
          <Route path="volunteering" element={
            <FeatureGate feature="volunteering" fallback={<ComingSoonPage feature="Volunteering" />}>
              <FeatureErrorBoundary featureName="Volunteering">
                <VolunteeringPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="volunteering/create" element={
            <FeatureGate feature="volunteering" fallback={<ComingSoonPage feature="Volunteering" />}>
              <FeatureErrorBoundary featureName="Volunteering">
                <CreateOpportunityPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="volunteering/opportunities/:id" element={
            <FeatureGate feature="volunteering" fallback={<ComingSoonPage feature="Volunteering" />}>
              <FeatureErrorBoundary featureName="Volunteering">
                <OpportunityDetailPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="volunteering/my-applications" element={<Navigate to="../volunteering?tab=applications" replace />} />
          <Route path="organisations/register" element={
            <FeatureGate feature="organisations" fallback={<ComingSoonPage feature="Organisations" />}>
              <FeatureErrorBoundary featureName="Organisations">
                <RegisterOrganisationPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="organisations" element={
            <FeatureGate feature="organisations" fallback={<ComingSoonPage feature="Organisations" />}>
              <FeatureErrorBoundary featureName="Organisations">
                <OrganisationsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="organisations/:id" element={
            <FeatureGate feature="organisations" fallback={<ComingSoonPage feature="Organisations" />}>
              <FeatureErrorBoundary featureName="Organisations">
                <OrganisationDetailPage />
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

          {/* Feature-gated: Resources */}
          <Route path="resources" element={
            <FeatureGate feature="resources" fallback={<ComingSoonPage feature="Resources" />}>
              <FeatureErrorBoundary featureName="Resources">
                <ResourcesPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />

          {/* Feature-gated: Knowledge Base (R4) */}
          <Route path="kb" element={
            <FeatureGate feature="resources" fallback={<ComingSoonPage feature="Knowledge Base" />}>
              <FeatureErrorBoundary featureName="Knowledge Base">
                <KnowledgeBasePage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="kb/:id" element={
            <FeatureGate feature="resources" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Knowledge Base">
                <KBArticlePage />
              </FeatureErrorBoundary>
            </FeatureGate>
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
        </Route>
      </Route>

      {/* Admin Panel (separate layout, no main navbar/footer) — fully lazy-loaded */}
      <Route path="admin/*" element={<AdminApp />} />

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
          <GoogleMapsProvider>
            <BrowserRouter>
              <HeroUIRouterProvider>
                <ScrollToTop />
                <CookieConsentProvider>
                  <ToastProvider>
                    <Suspense fallback={<LoadingScreen message="Loading..." />}>
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
          </GoogleMapsProvider>
        </ThemeProvider>
      </HelmetProvider>
    </ErrorBoundary>
  );
}

export default App;
