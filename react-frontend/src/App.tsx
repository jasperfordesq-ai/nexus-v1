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

import { Suspense, lazy } from 'react';
import { BrowserRouter, Routes, Route, useNavigate } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';
import { HelmetProvider } from 'react-helmet-async';

// Contexts (app-wide only — tenant-scoped contexts are inside TenantShell)
import { ToastProvider, ThemeProvider } from '@/contexts';

// Layout Components
import { Layout, AuthLayout } from '@/components/layout';
import { ProtectedRoute, FeatureGate, ScrollToTop, TenantShell } from '@/components/routing';
import { LoadingScreen, ErrorBoundary, FeatureErrorBoundary } from '@/components/feedback';

// Auth Pages (not lazy loaded - critical path)
import { LoginPage, RegisterPage, ForgotPasswordPage, ResetPasswordPage } from '@/pages/auth';

// Admin Panel (lazy-loaded — keeps recharts, jsPDF, admin sidebar/header out of main bundle)
const AdminApp = lazy(() => import('@/admin/AdminApp'));

// Lazy-loaded Pages
const HomePage = lazy(() => import('@/pages/public/HomePage'));
const DashboardPage = lazy(() => import('@/pages/dashboard/DashboardPage'));
const ListingsPage = lazy(() => import('@/pages/listings/ListingsPage'));
const ListingDetailPage = lazy(() => import('@/pages/listings/ListingDetailPage'));
const CreateListingPage = lazy(() => import('@/pages/listings/CreateListingPage'));
const MessagesPage = lazy(() => import('@/pages/messages/MessagesPage'));
const ConversationPage = lazy(() => import('@/pages/messages/ConversationPage'));
const WalletPage = lazy(() => import('@/pages/wallet/WalletPage'));
const ProfilePage = lazy(() => import('@/pages/profile/ProfilePage'));
const SettingsPage = lazy(() => import('@/pages/settings/SettingsPage'));
const SearchPage = lazy(() => import('@/pages/search/SearchPage'));
const NotificationsPage = lazy(() => import('@/pages/notifications/NotificationsPage'));
const MembersPage = lazy(() => import('@/pages/members/MembersPage'));
const EventsPage = lazy(() => import('@/pages/events/EventsPage'));
const EventDetailPage = lazy(() => import('@/pages/events/EventDetailPage'));
const CreateEventPage = lazy(() => import('@/pages/events/CreateEventPage'));
const GroupsPage = lazy(() => import('@/pages/groups/GroupsPage'));
const GroupDetailPage = lazy(() => import('@/pages/groups/GroupDetailPage'));
const CreateGroupPage = lazy(() => import('@/pages/groups/CreateGroupPage'));
const NotFoundPage = lazy(() => import('@/pages/errors/NotFoundPage'));
const ComingSoonPage = lazy(() => import('@/pages/errors/ComingSoonPage'));
const ExchangesPage = lazy(() => import('@/pages/exchanges/ExchangesPage'));
const ExchangeDetailPage = lazy(() => import('@/pages/exchanges/ExchangeDetailPage'));
const RequestExchangePage = lazy(() => import('@/pages/exchanges/RequestExchangePage'));
const LeaderboardPage = lazy(() => import('@/pages/leaderboard/LeaderboardPage'));
const AchievementsPage = lazy(() => import('@/pages/achievements/AchievementsPage'));
const GoalsPage = lazy(() => import('@/pages/goals/GoalsPage'));
const VolunteeringPage = lazy(() => import('@/pages/volunteering/VolunteeringPage'));
const OrganisationsPage = lazy(() => import('@/pages/organisations/OrganisationsPage'));
const OrganisationDetailPage = lazy(() => import('@/pages/organisations/OrganisationDetailPage'));
const FeedPage = lazy(() => import('@/pages/feed/FeedPage'));
const BlogPage = lazy(() => import('@/pages/blog/BlogPage'));
const BlogPostPage = lazy(() => import('@/pages/blog/BlogPostPage'));
const ResourcesPage = lazy(() => import('@/pages/resources/ResourcesPage'));
const FederationHubPage = lazy(() => import('@/pages/federation/FederationHubPage'));
const FederationPartnersPage = lazy(() => import('@/pages/federation/FederationPartnersPage'));
const FederationMembersPage = lazy(() => import('@/pages/federation/FederationMembersPage'));
const FederationMemberProfilePage = lazy(() => import('@/pages/federation/FederationMemberProfilePage'));
const FederationMessagesPage = lazy(() => import('@/pages/federation/FederationMessagesPage'));
const FederationListingsPage = lazy(() => import('@/pages/federation/FederationListingsPage'));
const FederationEventsPage = lazy(() => import('@/pages/federation/FederationEventsPage'));
const FederationSettingsPage = lazy(() => import('@/pages/federation/FederationSettingsPage'));
const FederationOnboardingPage = lazy(() => import('@/pages/federation/FederationOnboardingPage'));
const OnboardingPage = lazy(() => import('@/pages/onboarding/OnboardingPage'));
const GroupExchangesPage = lazy(() => import('@/pages/group-exchanges/GroupExchangesPage'));
const CreateGroupExchangePage = lazy(() => import('@/pages/group-exchanges/CreateGroupExchangePage'));
const GroupExchangeDetailPage = lazy(() => import('@/pages/group-exchanges/GroupExchangeDetailPage'));

// Static Pages
const AboutPage = lazy(() => import('@/pages/public/AboutPage'));
const ContactPage = lazy(() => import('@/pages/public/ContactPage'));
const TermsPage = lazy(() => import('@/pages/public/TermsPage'));
const PrivacyPage = lazy(() => import('@/pages/public/PrivacyPage'));
const AccessibilityPage = lazy(() => import('@/pages/public/AccessibilityPage'));
const CookiesPage = lazy(() => import('@/pages/public/CookiesPage'));
const LegalHubPage = lazy(() => import('@/pages/public/LegalHubPage'));
const LegalVersionHistoryPage = lazy(() => import('@/pages/public/LegalVersionHistoryPage'));
const FaqPage = lazy(() => import('@/pages/public/FaqPage'));
const HelpCenterPage = lazy(() => import('@/pages/help/HelpCenterPage'));

// About Sub-Pages
const TimebankingGuidePage = lazy(() => import('@/pages/about/TimebankingGuidePage'));
const PartnerPage = lazy(() => import('@/pages/about/PartnerPage'));
const SocialPrescribingPage = lazy(() => import('@/pages/about/SocialPrescribingPage'));
const ImpactSummaryPage = lazy(() => import('@/pages/about/ImpactSummaryPage'));
const ImpactReportPage = lazy(() => import('@/pages/about/ImpactReportPage'));
const StrategicPlanPage = lazy(() => import('@/pages/about/StrategicPlanPage'));

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
      </Route>

      {/* Main Routes (with navbar/footer) */}
      <Route element={<Layout />}>
        {/* Public Routes */}
        <Route index element={<HomePage />} />
        <Route path="about" element={<AboutPage />} />
        <Route path="faq" element={<FaqPage />} />
        <Route path="contact" element={<ContactPage />} />
        <Route path="help" element={<HelpCenterPage />} />
        <Route path="terms" element={<TermsPage />} />
        <Route path="terms/versions" element={<LegalVersionHistoryPage />} />
        <Route path="privacy" element={<PrivacyPage />} />
        <Route path="privacy/versions" element={<LegalVersionHistoryPage />} />
        <Route path="accessibility" element={<AccessibilityPage />} />
        <Route path="accessibility/versions" element={<LegalVersionHistoryPage />} />
        <Route path="cookies" element={<CookiesPage />} />
        <Route path="cookies/versions" element={<LegalVersionHistoryPage />} />
        <Route path="legal" element={<LegalHubPage />} />
        <Route path="timebanking-guide" element={<TimebankingGuidePage />} />
        <Route path="partner" element={<PartnerPage />} />
        <Route path="social-prescribing" element={<SocialPrescribingPage />} />
        <Route path="impact-summary" element={<ImpactSummaryPage />} />
        <Route path="impact-report" element={<ImpactReportPage />} />
        <Route path="strategic-plan" element={<StrategicPlanPage />} />

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
            <ListingsPage />
          </FeatureGate>
        } />
        <Route path="listings/:id" element={
          <FeatureGate module="listings" redirect="/">
            <ListingDetailPage />
          </FeatureGate>
        } />

        {/* Protected Routes */}
        <Route element={<ProtectedRoute />}>
          {/* Core Features (module-gated) */}
          <Route path="dashboard" element={
            <FeatureGate module="dashboard" redirect="/">
              <DashboardPage />
            </FeatureGate>
          } />
          <Route path="listings/create" element={
            <FeatureGate module="listings" redirect="/">
              <CreateListingPage />
            </FeatureGate>
          } />
          <Route path="listings/edit/:id" element={
            <FeatureGate module="listings" redirect="/">
              <CreateListingPage />
            </FeatureGate>
          } />
          <Route path="messages" element={
            <FeatureGate module="messages" redirect="/dashboard">
              <MessagesPage />
            </FeatureGate>
          } />
          <Route path="messages/new/:userId" element={
            <FeatureGate module="messages" redirect="/dashboard">
              <ConversationPage />
            </FeatureGate>
          } />
          <Route path="messages/:id" element={
            <FeatureGate module="messages" redirect="/dashboard">
              <ConversationPage />
            </FeatureGate>
          } />
          <Route path="wallet" element={
            <FeatureGate module="wallet" redirect="/dashboard">
              <WalletPage />
            </FeatureGate>
          } />
          <Route path="profile" element={
            <FeatureGate module="profile" redirect="/dashboard">
              <ProfilePage />
            </FeatureGate>
          } />
          <Route path="profile/:id" element={
            <FeatureGate module="profile" redirect="/dashboard">
              <ProfilePage />
            </FeatureGate>
          } />
          <Route path="settings" element={
            <FeatureGate module="settings" redirect="/dashboard">
              <SettingsPage />
            </FeatureGate>
          } />
          <Route path="search" element={
            <FeatureGate feature="search" redirect="/dashboard">
              <SearchPage />
            </FeatureGate>
          } />
          <Route path="notifications" element={
            <FeatureGate module="notifications" redirect="/dashboard">
              <NotificationsPage />
            </FeatureGate>
          } />

          {/* Onboarding Wizard */}
          <Route path="onboarding" element={<OnboardingPage />} />

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

          {/* Feature-gated: Goals */}
          <Route path="goals" element={
            <FeatureGate feature="goals" fallback={<ComingSoonPage feature="Goals" />}>
              <FeatureErrorBoundary featureName="Goals">
                <GoalsPage />
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
          <Route path="organisations" element={
            <FeatureGate feature="volunteering" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Volunteering">
                <OrganisationsPage />
              </FeatureErrorBoundary>
            </FeatureGate>
          } />
          <Route path="organisations/:id" element={
            <FeatureGate feature="volunteering" redirect="/dashboard">
              <FeatureErrorBoundary featureName="Volunteering">
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

          {/* Feature-gated: Resources */}
          <Route path="resources" element={
            <FeatureGate feature="resources" fallback={<ComingSoonPage feature="Resources" />}>
              <FeatureErrorBoundary featureName="Resources">
                <ResourcesPage />
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
          <BrowserRouter>
            <HeroUIRouterProvider>
              <ScrollToTop />
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
            </HeroUIRouterProvider>
          </BrowserRouter>
        </ThemeProvider>
      </HelmetProvider>
    </ErrorBoundary>
  );
}

export default App;
