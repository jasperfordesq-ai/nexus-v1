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
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';
import { HelmetProvider } from 'react-helmet-async';

// Contexts (app-wide only — tenant-scoped contexts are inside TenantShell)
import { ToastProvider, ThemeProvider } from '@/contexts';

// Layout Components
import { Layout, AuthLayout } from '@/components/layout';
import { ProtectedRoute, FeatureGate, ScrollToTop, TenantShell } from '@/components/routing';
import { LoadingScreen, ErrorBoundary } from '@/components/feedback';

// Auth Pages (not lazy loaded - critical path)
import { LoginPage, RegisterPage, ForgotPasswordPage, ResetPasswordPage } from '@/pages/auth';

// Admin Panel
import { AdminLayout } from '@/admin/AdminLayout';
import { AdminRoute } from '@/admin/AdminRoute';
import { AdminRoutes } from '@/admin/routes';

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
const FederationMessagesPage = lazy(() => import('@/pages/federation/FederationMessagesPage'));
const FederationListingsPage = lazy(() => import('@/pages/federation/FederationListingsPage'));
const FederationEventsPage = lazy(() => import('@/pages/federation/FederationEventsPage'));
const FederationSettingsPage = lazy(() => import('@/pages/federation/FederationSettingsPage'));
const FederationOnboardingPage = lazy(() => import('@/pages/federation/FederationOnboardingPage'));

// Static Pages
const AboutPage = lazy(() => import('@/pages/public/AboutPage'));
const ContactPage = lazy(() => import('@/pages/public/ContactPage'));
const TermsPage = lazy(() => import('@/pages/public/TermsPage'));
const PrivacyPage = lazy(() => import('@/pages/public/PrivacyPage'));
const AccessibilityPage = lazy(() => import('@/pages/public/AccessibilityPage'));
const CookiesPage = lazy(() => import('@/pages/public/CookiesPage'));
const LegalHubPage = lazy(() => import('@/pages/public/LegalHubPage'));
const HelpCenterPage = lazy(() => import('@/pages/help/HelpCenterPage'));

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
        <Route path="contact" element={<ContactPage />} />
        <Route path="help" element={<HelpCenterPage />} />
        <Route path="terms" element={<TermsPage />} />
        <Route path="privacy" element={<PrivacyPage />} />
        <Route path="accessibility" element={<AccessibilityPage />} />
        <Route path="cookies" element={<CookiesPage />} />
        <Route path="legal" element={<LegalHubPage />} />

        {/* Public: Blog (feature-gated) */}
        <Route path="blog" element={
          <FeatureGate feature="blog" redirect="/">
            <BlogPage />
          </FeatureGate>
        } />
        <Route path="blog/:slug" element={
          <FeatureGate feature="blog" redirect="/">
            <BlogPostPage />
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

          {/* Feature-gated: Exchanges */}
          <Route path="exchanges" element={
            <FeatureGate feature="exchange_workflow" fallback={<ComingSoonPage feature="Exchanges" />}>
              <ExchangesPage />
            </FeatureGate>
          } />
          <Route path="exchanges/:id" element={
            <FeatureGate feature="exchange_workflow" redirect="/dashboard">
              <ExchangeDetailPage />
            </FeatureGate>
          } />
          <Route path="listings/:id/request-exchange" element={
            <FeatureGate feature="exchange_workflow" redirect="/dashboard">
              <RequestExchangePage />
            </FeatureGate>
          } />

          {/* Feature-gated: Members/Connections */}
          <Route path="members" element={
            <FeatureGate feature="connections" fallback={<ComingSoonPage feature="Members Directory" />}>
              <MembersPage />
            </FeatureGate>
          } />

          {/* Feature-gated: Events */}
          <Route path="events" element={
            <FeatureGate feature="events" fallback={<ComingSoonPage feature="Events" />}>
              <EventsPage />
            </FeatureGate>
          } />
          <Route path="events/create" element={
            <FeatureGate feature="events" redirect="/dashboard">
              <CreateEventPage />
            </FeatureGate>
          } />
          <Route path="events/edit/:id" element={
            <FeatureGate feature="events" redirect="/dashboard">
              <CreateEventPage />
            </FeatureGate>
          } />
          <Route path="events/:id" element={
            <FeatureGate feature="events" redirect="/dashboard">
              <EventDetailPage />
            </FeatureGate>
          } />

          {/* Feature-gated: Groups */}
          <Route path="groups" element={
            <FeatureGate feature="groups" fallback={<ComingSoonPage feature="Groups" />}>
              <GroupsPage />
            </FeatureGate>
          } />
          <Route path="groups/create" element={
            <FeatureGate feature="groups" redirect="/dashboard">
              <CreateGroupPage />
            </FeatureGate>
          } />
          <Route path="groups/edit/:id" element={
            <FeatureGate feature="groups" redirect="/dashboard">
              <CreateGroupPage />
            </FeatureGate>
          } />
          <Route path="groups/:id" element={
            <FeatureGate feature="groups" redirect="/dashboard">
              <GroupDetailPage />
            </FeatureGate>
          } />

          {/* Feature-gated: Gamification */}
          <Route path="achievements" element={
            <FeatureGate feature="gamification" fallback={<ComingSoonPage feature="Achievements" />}>
              <AchievementsPage />
            </FeatureGate>
          } />
          <Route path="leaderboard" element={
            <FeatureGate feature="gamification" fallback={<ComingSoonPage feature="Leaderboard" />}>
              <LeaderboardPage />
            </FeatureGate>
          } />

          {/* Feature-gated: Goals */}
          <Route path="goals" element={
            <FeatureGate feature="goals" fallback={<ComingSoonPage feature="Goals" />}>
              <GoalsPage />
            </FeatureGate>
          } />

          {/* Feature-gated: Volunteering */}
          <Route path="volunteering" element={
            <FeatureGate feature="volunteering" fallback={<ComingSoonPage feature="Volunteering" />}>
              <VolunteeringPage />
            </FeatureGate>
          } />
          <Route path="organisations" element={
            <FeatureGate feature="volunteering" redirect="/dashboard">
              <OrganisationsPage />
            </FeatureGate>
          } />
          <Route path="organisations/:id" element={
            <FeatureGate feature="volunteering" redirect="/dashboard">
              <OrganisationDetailPage />
            </FeatureGate>
          } />

          {/* Module-gated: Feed */}
          <Route path="feed" element={
            <FeatureGate module="feed" redirect="/dashboard">
              <FeedPage />
            </FeatureGate>
          } />

          {/* Feature-gated: Resources */}
          <Route path="resources" element={
            <FeatureGate feature="resources" fallback={<ComingSoonPage feature="Resources" />}>
              <ResourcesPage />
            </FeatureGate>
          } />

          {/* Feature-gated: Federation */}
          <Route path="federation" element={
            <FeatureGate feature="federation" fallback={<ComingSoonPage feature="Federation" />}>
              <FederationHubPage />
            </FeatureGate>
          } />
          <Route path="federation/partners" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FederationPartnersPage />
            </FeatureGate>
          } />
          <Route path="federation/members" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FederationMembersPage />
            </FeatureGate>
          } />
          <Route path="federation/messages" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FederationMessagesPage />
            </FeatureGate>
          } />
          <Route path="federation/listings" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FederationListingsPage />
            </FeatureGate>
          } />
          <Route path="federation/events" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FederationEventsPage />
            </FeatureGate>
          } />
          <Route path="federation/settings" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FederationSettingsPage />
            </FeatureGate>
          } />
          <Route path="federation/onboarding" element={
            <FeatureGate feature="federation" redirect="/dashboard">
              <FederationOnboardingPage />
            </FeatureGate>
          } />
        </Route>
      </Route>

      {/* Admin Panel (separate layout, no main navbar/footer) */}
      <Route path="admin" element={<AdminRoute />}>
        <Route element={<AdminLayout />}>
          {AdminRoutes()}
        </Route>
      </Route>

      {/* 404 Fallback (must be after admin to avoid catching /admin paths) */}
      <Route element={<Layout />}>
        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </>
  );
}

function App() {
  return (
    <ErrorBoundary>
      <HelmetProvider>
        <ThemeProvider>
          <HeroUIProvider>
            <BrowserRouter>
              <ScrollToTop />
              <ToastProvider>
                <Suspense fallback={<LoadingScreen message="Loading..." />}>
                  <Routes>
                    {/* Slug-prefixed routes: /:tenantSlug/* (Phase 0-1 TRS-001) */}
                    <Route path=":tenantSlug/*" element={<TenantShell />}>
                      {AppRoutes()}
                    </Route>

                    {/* Non-prefixed routes: /* (domain/subdomain resolution, or chooser) */}
                    <Route path="/*" element={<TenantShell />}>
                      {AppRoutes()}
                    </Route>
                  </Routes>
                </Suspense>
              </ToastProvider>
            </BrowserRouter>
          </HeroUIProvider>
        </ThemeProvider>
      </HelmetProvider>
    </ErrorBoundary>
  );
}

export default App;
