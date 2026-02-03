/**
 * App - Root component with routing
 *
 * Route Structure:
 * - AppShell wraps all routes (header, footer, mobile nav)
 * - Public routes: home, listings, events, groups, about, contact, login
 * - Protected routes: dashboard, messages, wallet, profile, settings
 * - Feature-gated routes only render if tenant.features[x] is true
 */

import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

import { useTenantBootstrap, TenantProvider, useFeature } from './tenant';
import { AuthProvider } from './auth';
import { LoadingScreen, ErrorScreen, AppShell, ProtectedRoute } from './components';
import {
  HomePage,
  LoginPage,
  ListingsPage,
  ListingDetailPage,
  NotFoundPage,
  // Protected pages
  DashboardPage,
  MessagesPage,
  WalletPage,
  ProfilePage,
  SettingsPage,
  // Public feature-gated pages
  EventsPage,
  GroupsPage,
  VolunteeringPage,
  // Static pages
  AboutPage,
  ContactPage,
  HelpPage,
  PrivacyPage,
  TermsPage,
} from './pages';

/**
 * Feature-gated route - only renders if feature is enabled
 */
function FeatureRoute({
  feature,
  children,
}: {
  feature: string;
  children: React.ReactNode;
}) {
  const isEnabled = useFeature(feature as keyof ReturnType<typeof useFeature>);

  if (!isEnabled) {
    return <NotFoundPage />;
  }

  return <>{children}</>;
}

function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<AppShell />}>
        {/* Public routes */}
        <Route index element={<HomePage />} />
        <Route path="login" element={<LoginPage />} />

        {/* Feature-gated public routes */}
        <Route
          path="listings"
          element={
            <FeatureRoute feature="listings">
              <ListingsPage />
            </FeatureRoute>
          }
        />
        <Route
          path="listings/:id"
          element={
            <FeatureRoute feature="listings">
              <ListingDetailPage />
            </FeatureRoute>
          }
        />
        <Route
          path="events"
          element={
            <FeatureRoute feature="events">
              <EventsPage />
            </FeatureRoute>
          }
        />
        <Route
          path="groups"
          element={
            <FeatureRoute feature="groups">
              <GroupsPage />
            </FeatureRoute>
          }
        />
        <Route
          path="volunteering"
          element={
            <FeatureRoute feature="volunteering">
              <VolunteeringPage />
            </FeatureRoute>
          }
        />

        {/* Static public pages */}
        <Route path="about" element={<AboutPage />} />
        <Route path="contact" element={<ContactPage />} />
        <Route path="help" element={<HelpPage />} />
        <Route path="privacy" element={<PrivacyPage />} />
        <Route path="terms" element={<TermsPage />} />

        {/* Protected routes (require authentication) */}
        <Route
          path="dashboard"
          element={
            <ProtectedRoute>
              <DashboardPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="messages"
          element={
            <ProtectedRoute>
              <FeatureRoute feature="messages">
                <MessagesPage />
              </FeatureRoute>
            </ProtectedRoute>
          }
        />
        <Route
          path="wallet"
          element={
            <ProtectedRoute>
              <FeatureRoute feature="wallet">
                <WalletPage />
              </FeatureRoute>
            </ProtectedRoute>
          }
        />
        <Route
          path="profile"
          element={
            <ProtectedRoute>
              <ProfilePage />
            </ProtectedRoute>
          }
        />
        <Route
          path="settings"
          element={
            <ProtectedRoute>
              <SettingsPage />
            </ProtectedRoute>
          }
        />

        {/* Catch-all for 404 */}
        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
  );
}

function AppWithTenant() {
  const { tenant, loading, error, statusCode, retry } = useTenantBootstrap();

  // Loading state
  if (loading) {
    return <LoadingScreen />;
  }

  // Error state
  if (error || !tenant) {
    return (
      <ErrorScreen
        message={error || 'Failed to load tenant configuration'}
        statusCode={statusCode}
        onRetry={retry}
      />
    );
  }

  // Success - render app with tenant context
  return (
    <TenantProvider value={tenant}>
      <AuthProvider>
        <AppRoutes />
      </AuthProvider>
    </TenantProvider>
  );
}

export default function App() {
  return (
    <BrowserRouter>
      <HeroUIProvider>
        <AppWithTenant />
      </HeroUIProvider>
    </BrowserRouter>
  );
}
