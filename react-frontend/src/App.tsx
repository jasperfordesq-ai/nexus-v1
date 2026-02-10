/**
 * NEXUS React Frontend - Main App Component
 *
 * Routes structure:
 * - Public routes (no auth required)
 * - Protected routes (auth required)
 * - Feature-gated routes (based on tenant config)
 */

import { Suspense, lazy } from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';
import { HelmetProvider } from 'react-helmet-async';

// Contexts
import { AuthProvider, TenantProvider, ToastProvider, NotificationsProvider, ThemeProvider, PusherProvider } from '@/contexts';

// Layout Components
import { Layout, AuthLayout } from '@/components/layout';
import { ProtectedRoute, FeatureGate } from '@/components/routing';
import { LoadingScreen, ErrorBoundary } from '@/components/feedback';

// Auth Pages (not lazy loaded - critical path)
import { LoginPage, RegisterPage, ForgotPasswordPage, ResetPasswordPage } from '@/pages/auth';

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

// Static Pages
const AboutPage = lazy(() => import('@/pages/public/AboutPage'));
const ContactPage = lazy(() => import('@/pages/public/ContactPage'));
const TermsPage = lazy(() => import('@/pages/public/TermsPage'));
const PrivacyPage = lazy(() => import('@/pages/public/PrivacyPage'));

function App() {
  return (
    <ErrorBoundary>
      <HelmetProvider>
        <ThemeProvider>
          <HeroUIProvider>
            <BrowserRouter>
              <ToastProvider>
                <TenantProvider>
                  <AuthProvider>
                    <NotificationsProvider>
                      <PusherProvider>
              <Suspense fallback={<LoadingScreen message="Loading..." />}>
                <Routes>
                  {/* Auth Routes (no navbar/footer) */}
                  <Route element={<AuthLayout />}>
                    <Route path="/login" element={<LoginPage />} />
                    <Route path="/register" element={<RegisterPage />} />
                    <Route path="/password/forgot" element={<ForgotPasswordPage />} />
                    <Route path="/password/reset" element={<ResetPasswordPage />} />
                  </Route>

                  {/* Main Routes (with navbar/footer) */}
                  <Route element={<Layout />}>
                    {/* Public Routes */}
                    <Route path="/" element={<HomePage />} />
                    <Route path="/about" element={<AboutPage />} />
                    <Route path="/contact" element={<ContactPage />} />
                    <Route path="/terms" element={<TermsPage />} />
                    <Route path="/privacy" element={<PrivacyPage />} />

                    {/* Public but can show auth-specific content */}
                    <Route path="/listings" element={<ListingsPage />} />
                    <Route path="/listings/:id" element={<ListingDetailPage />} />

                    {/* Protected Routes */}
                    <Route element={<ProtectedRoute />}>
                      {/* Core Features */}
                      <Route path="/dashboard" element={<DashboardPage />} />
                      <Route path="/listings/create" element={<CreateListingPage />} />
                      <Route path="/listings/edit/:id" element={<CreateListingPage />} />
                      <Route path="/messages" element={<MessagesPage />} />
                      <Route path="/messages/:id" element={<ConversationPage />} />
                      <Route path="/wallet" element={<WalletPage />} />
                      <Route path="/profile" element={<ProfilePage />} />
                      <Route path="/profile/:id" element={<ProfilePage />} />
                      <Route path="/settings" element={<SettingsPage />} />
                      <Route path="/search" element={<SearchPage />} />
                      <Route path="/notifications" element={<NotificationsPage />} />
                      <Route path="/exchanges" element={<ExchangesPage />} />
                      <Route path="/exchanges/:id" element={<ExchangeDetailPage />} />
                      <Route path="/listings/:id/request-exchange" element={<RequestExchangePage />} />

                      {/* Feature-gated: Members/Connections */}
                      <Route
                        path="/members"
                        element={
                          <FeatureGate feature="connections" fallback={<ComingSoonPage feature="Members Directory" />}>
                            <MembersPage />
                          </FeatureGate>
                        }
                      />

                      {/* Feature-gated: Events */}
                      <Route
                        path="/events"
                        element={
                          <FeatureGate feature="events" fallback={<ComingSoonPage feature="Events" />}>
                            <EventsPage />
                          </FeatureGate>
                        }
                      />
                      <Route
                        path="/events/create"
                        element={
                          <FeatureGate feature="events" redirect="/dashboard">
                            <CreateEventPage />
                          </FeatureGate>
                        }
                      />
                      <Route
                        path="/events/edit/:id"
                        element={
                          <FeatureGate feature="events" redirect="/dashboard">
                            <CreateEventPage />
                          </FeatureGate>
                        }
                      />
                      <Route
                        path="/events/:id"
                        element={
                          <FeatureGate feature="events" redirect="/dashboard">
                            <EventDetailPage />
                          </FeatureGate>
                        }
                      />

                      {/* Feature-gated: Groups */}
                      <Route
                        path="/groups"
                        element={
                          <FeatureGate feature="groups" fallback={<ComingSoonPage feature="Groups" />}>
                            <GroupsPage />
                          </FeatureGate>
                        }
                      />
                      <Route
                        path="/groups/create"
                        element={
                          <FeatureGate feature="groups" redirect="/dashboard">
                            <CreateGroupPage />
                          </FeatureGate>
                        }
                      />
                      <Route
                        path="/groups/edit/:id"
                        element={
                          <FeatureGate feature="groups" redirect="/dashboard">
                            <CreateGroupPage />
                          </FeatureGate>
                        }
                      />
                      <Route
                        path="/groups/:id"
                        element={
                          <FeatureGate feature="groups" redirect="/dashboard">
                            <GroupDetailPage />
                          </FeatureGate>
                        }
                      />

                      {/* Feature-gated: Gamification (placeholder routes) */}
                      <Route
                        path="/achievements"
                        element={
                          <FeatureGate feature="gamification" fallback={<ComingSoonPage feature="Achievements" />}>
                            <ComingSoonPage feature="Achievements" />
                          </FeatureGate>
                        }
                      />
                      <Route
                        path="/leaderboard"
                        element={
                          <FeatureGate feature="gamification" fallback={<ComingSoonPage feature="Leaderboard" />}>
                            <ComingSoonPage feature="Leaderboard" />
                          </FeatureGate>
                        }
                      />

                      {/* Feature-gated: Goals */}
                      <Route
                        path="/goals"
                        element={
                          <FeatureGate feature="goals" fallback={<ComingSoonPage feature="Goals" />}>
                            <ComingSoonPage feature="Goals" />
                          </FeatureGate>
                        }
                      />

                      {/* Feature-gated: Volunteering */}
                      <Route
                        path="/volunteering"
                        element={
                          <FeatureGate feature="volunteering" fallback={<ComingSoonPage feature="Volunteering" />}>
                            <ComingSoonPage feature="Volunteering" />
                          </FeatureGate>
                        }
                      />
                    </Route>

                    {/* 404 Fallback */}
                    <Route path="*" element={<NotFoundPage />} />
                  </Route>
                </Routes>
              </Suspense>
                      </PusherProvider>
                    </NotificationsProvider>
                  </AuthProvider>
                </TenantProvider>
              </ToastProvider>
            </BrowserRouter>
          </HeroUIProvider>
        </ThemeProvider>
      </HelmetProvider>
    </ErrorBoundary>
  );
}

export default App;
