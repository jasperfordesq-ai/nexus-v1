// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import '@/global.css'; // Tailwind v4 + HeroUI Native styles — must be first

import { useEffect, useRef } from 'react';
import { LogBox } from 'react-native';
import { Stack, router, usePathname } from 'expo-router';
import * as Notifications from 'expo-notifications';
import { StatusBar } from 'expo-status-bar';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { HeroUINativeProvider } from 'heroui-native';

import { useTranslation } from 'react-i18next';
import '@/lib/i18n'; // initialise i18next before any screen renders
import { validateEnv } from '@/lib/env';
import { AuthProvider, useAuthContext } from '@/lib/context/AuthContext';
import { TenantProvider } from '@/lib/context/TenantContext';
import { RealtimeProvider } from '@/lib/context/RealtimeContext';
import ErrorBoundary from '@/components/ErrorBoundary';
import { navigateToLink } from '@/lib/utils/navigateToLink';
import * as Sentry from '@sentry/react-native';

// Validate environment variables at startup — logs warnings for missing config
validateEnv();

// Suppress known non-fatal dev-mode warnings that block the UI in Expo Go
LogBox.ignoreLogs([
  'expo-notifications',
  'expo-av',
  'VirtualizedLists should never be nested',
  'Each child in a list should have a unique',
  'Encountered two children with the same key',
  'Non-serializable values were found in the navigation state',
]);

Sentry.init({
  dsn: process.env.EXPO_PUBLIC_SENTRY_DSN ?? '',
  // Sample 10% of traces in production to reduce Sentry quota usage
  tracesSampleRate: 0.1,
  enabled: !!process.env.EXPO_PUBLIC_SENTRY_DSN,
});

/**
 * Root layout — wraps the entire app in providers and handles the
 * initial auth redirect (auth check → home or login).
 */
function RootLayout() {
  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <HeroUINativeProvider>
        <SafeAreaProvider>
          <ErrorBoundary>
            <TenantProvider>
              <AuthProvider>
                <RealtimeProvider>
                  <StatusBar style="auto" />
                  <RootNavigator />
                </RealtimeProvider>
              </AuthProvider>
            </TenantProvider>
          </ErrorBoundary>
        </SafeAreaProvider>
      </HeroUINativeProvider>
    </GestureHandlerRootView>
  );
}

export default Sentry.wrap(RootLayout);

/**
 * Handles the redirect logic after auth state resolves.
 * Separated from RootLayout so it can consume AuthContext.
 */
function RootNavigator() {
  const { t } = useTranslation();
  const { isLoading, isAuthenticated } = useAuthContext();
  const pathname = usePathname();
  const isTenantSelectionPath =
    pathname === '/select-tenant' || pathname.startsWith('/select-tenant/');
  const isPublicAuthPath =
    pathname === '/login' ||
    pathname.startsWith('/login/') ||
    pathname === '/register' ||
    pathname.startsWith('/register/') ||
    isTenantSelectionPath;

  // Queue deep link from push notification taps — only navigate once auth resolves.
  const pendingDeepLinkRef = useRef<string | null>(null);

  useEffect(() => {
    const subscription = Notifications.addNotificationResponseReceivedListener((response) => {
      const data = response.notification.request.content.data as { link?: string } | undefined;
      if (data?.link) {
        pendingDeepLinkRef.current = data.link;
      }
    });
    return () => subscription.remove();
  }, []);

  // Auth redirect — check for a pending deep link BEFORE defaulting to home.
  // This prevents the race condition where router.replace('/(tabs)/home')
  // fires before the deep link effect has a chance to navigate.
  useEffect(() => {
    if (isLoading) return;
    if (isAuthenticated) {
      const pendingLink = pendingDeepLinkRef.current;
      if (pendingLink) {
        pendingDeepLinkRef.current = null;
        navigateToLink(pendingLink);
      } else if (pathname === '/' || (isPublicAuthPath && !isTenantSelectionPath)) {
        router.replace('/(tabs)/home');
      } else {
        // Preserve direct/deep-linked routes after auth, such as /members or /messages.
        // Expo Router already has the current path in state; replacing here would
        // make every refreshed authenticated page look like Home.
      }
    } else {
      if (!isPublicAuthPath) {
        router.replace('/(auth)/login');
      }
    }
  }, [isLoading, isAuthenticated, isPublicAuthPath, pathname]);

  // Shared options for regular modal screens: slide up from bottom, swipe-to-dismiss
  const modalOptions = {
    presentation: 'modal' as const,
    headerShown: true,
    animation: 'slide_from_bottom' as const,
    gestureEnabled: true,
    gestureDirection: 'vertical' as const,
    contentStyle: { backgroundColor: 'transparent' },
  };

  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="index" />
      <Stack.Screen name="(auth)" />
      <Stack.Screen name="(tabs)" />
      <Stack.Screen
        name="(modals)/new-exchange"
        options={{ ...modalOptions, headerShown: false, title: t('exchanges:newTitle') }}
      />
      <Stack.Screen
        name="(modals)/quick-create"
        options={{ ...modalOptions, headerShown: false, title: t('common:quickCreate.title') }}
      />
      <Stack.Screen
        name="(modals)/edit-exchange"
        options={{ ...modalOptions, headerShown: false, title: t('exchanges:editTitle') }}
      />
      <Stack.Screen
        name="(modals)/new-event"
        options={{ ...modalOptions, headerShown: false, title: t('events:create.title') }}
      />
      <Stack.Screen
        name="(modals)/edit-event"
        options={{ ...modalOptions, headerShown: false, title: t('events:create.editTitle') }}
      />
      <Stack.Screen
        name="(modals)/new-group"
        options={{ ...modalOptions, headerShown: false, title: t('groups:create.title') }}
      />
      <Stack.Screen
        name="(modals)/edit-group"
        options={{ ...modalOptions, headerShown: false, title: t('groups:create.editTitle') }}
      />
      <Stack.Screen
        name="(modals)/new-volunteering"
        options={{ ...modalOptions, headerShown: false, title: t('volunteering:create.title') }}
      />
      <Stack.Screen
        name="(modals)/edit-volunteering"
        options={{ ...modalOptions, headerShown: false, title: t('volunteering:create.editTitle') }}
      />
      <Stack.Screen
        name="(modals)/new-job"
        options={{ ...modalOptions, headerShown: false, title: t('jobs:create.title') }}
      />
      <Stack.Screen
        name="(modals)/edit-job"
        options={{ ...modalOptions, headerShown: false, title: t('jobs:create.editTitle') }}
      />
      <Stack.Screen
        name="(modals)/exchange-detail"
        options={{ ...modalOptions, headerShown: false, title: t('exchanges:detailTitle') }}
      />
      <Stack.Screen
        name="(modals)/thread"
        options={{ ...modalOptions, headerShown: false, title: t('messages:threadTitle') }}
      />
      <Stack.Screen
        name="(modals)/member-profile"
        options={{ ...modalOptions, headerShown: false, title: t('members:profileTitle') }}
      />
      <Stack.Screen
        name="(modals)/notifications"
        options={{ ...modalOptions, headerShown: false, title: t('notifications:title') }}
      />
      <Stack.Screen
        name="(modals)/wallet"
        options={{ ...modalOptions, headerShown: false, title: t('wallet:title') }}
      />
      <Stack.Screen
        name="(modals)/settings"
        options={{ ...modalOptions, headerShown: false, title: t('settings:title') }}
      />
      <Stack.Screen
        name="(modals)/edit-profile"
        options={{ ...modalOptions, headerShown: false, title: t('profile:editTitle') }}
      />
      <Stack.Screen
        name="(modals)/event-detail"
        options={{ ...modalOptions, headerShown: false, title: t('events:detailTitle') }}
      />
      <Stack.Screen
        name="(modals)/members"
        options={{ ...modalOptions, headerShown: false, title: t('members:title') }}
      />
      <Stack.Screen
        name="(modals)/change-password"
        options={{ ...modalOptions, headerShown: false, title: t('settings:changePasswordTitle') }}
      />
      <Stack.Screen
        name="(modals)/verify-identity"
        options={{ ...modalOptions, headerShown: false, title: t('settings:identity.page_title') }}
      />
      <Stack.Screen
        name="(modals)/group-detail"
        options={{ ...modalOptions, headerShown: false, title: t('groups:detailTitle') }}
      />
      <Stack.Screen
        name="(modals)/blog"
        options={{ ...modalOptions, headerShown: false, title: t('blog:title') }}
      />
      <Stack.Screen
        name="(modals)/blog-post"
        options={{ ...modalOptions, headerShown: false, title: t('blog:postTitle') }}
      />
      <Stack.Screen
        name="(modals)/gamification"
        options={{ ...modalOptions, headerShown: false, title: t('gamification:title') }}
      />
      <Stack.Screen
        name="(modals)/goals"
        options={{ ...modalOptions, headerShown: false, title: t('goals:title') }}
      />
      <Stack.Screen
        name="(modals)/chat"
        options={{ ...modalOptions, headerShown: false, title: t('chat:title') }}
      />
      <Stack.Screen
        name="(modals)/volunteering"
        options={{ ...modalOptions, headerShown: false, title: t('volunteering:title') }}
      />
      <Stack.Screen
        name="(modals)/volunteering-detail"
        options={{ ...modalOptions, headerShown: false, title: t('volunteering:detailTitle') }}
      />
      <Stack.Screen
        name="(modals)/jobs"
        options={{ ...modalOptions, headerShown: false, title: t('jobs:title') }}
      />
      <Stack.Screen
        name="(modals)/marketplace"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:title') }}
      />
      <Stack.Screen
        name="(modals)/marketplace-free"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:free.title') }}
      />
      <Stack.Screen
        name="(modals)/marketplace-category"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:category.title') }}
      />
      <Stack.Screen
        name="(modals)/marketplace-detail"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:detail.title') }}
      />
      <Stack.Screen
        name="(modals)/new-marketplace-listing"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:forms.createTitle') }}
      />
      <Stack.Screen
        name="(modals)/edit-marketplace-listing"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:forms.editTitle') }}
      />
      <Stack.Screen
        name="(modals)/marketplace-my-listings"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:myListings.title') }}
      />
      <Stack.Screen
        name="(modals)/marketplace-offers"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:offers.title') }}
      />
      <Stack.Screen
        name="(modals)/marketplace-orders"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:orders.title') }}
      />
      <Stack.Screen
        name="(modals)/marketplace-pickups"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:pickup.myTitle') }}
      />
      <Stack.Screen
        name="(modals)/marketplace-coupons"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:publicCoupons.title') }}
      />
      <Stack.Screen
        name="(modals)/marketplace-coupon-detail"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:publicCoupons.details') }}
      />
      <Stack.Screen
        name="(modals)/marketplace-tools"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:tools.title') }}
      />
      <Stack.Screen
        name="(modals)/marketplace-map"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:map.title') }}
      />
      <Stack.Screen
        name="(modals)/marketplace-search"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:advancedSearch.title') }}
      />
      <Stack.Screen
        name="(modals)/marketplace-shipping-options"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:shipping.title') }}
      />
      <Stack.Screen
        name="(modals)/marketplace-collections"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:collections.title') }}
      />
      <Stack.Screen
        name="(modals)/marketplace-seller"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:seller.title') }}
      />
      <Stack.Screen
        name="(modals)/marketplace-merchant-onboarding"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:merchantOnboarding.title') }}
      />
      <Stack.Screen
        name="(modals)/marketplace-stripe-onboarding"
        options={{ ...modalOptions, headerShown: false, title: t('marketplace:stripeOnboarding.title') }}
      />
      <Stack.Screen
        name="(modals)/job-detail"
        options={{ ...modalOptions, headerShown: false, title: t('jobs:detailTitle') }}
      />
      <Stack.Screen
        name="(modals)/job-analytics"
        options={{ ...modalOptions, headerShown: false, title: t('jobs:analytics.title') }}
      />
      <Stack.Screen
        name="(modals)/job-pipeline"
        options={{ ...modalOptions, headerShown: false, title: t('jobs:kanban.pipeline_title') }}
      />
      <Stack.Screen
        name="(modals)/organisations"
        options={{ ...modalOptions, headerShown: false, title: t('organisations:title') }}
      />
      <Stack.Screen
        name="(modals)/new-organisation"
        options={{ ...modalOptions, headerShown: false, title: t('organisations:register.title') }}
      />
      <Stack.Screen
        name="(modals)/organisation-detail"
        options={{ ...modalOptions, headerShown: false, title: t('organisations:detailTitle') }}
      />
      <Stack.Screen
        name="(modals)/endorsements"
        options={{ ...modalOptions, headerShown: false, title: t('endorsements:title') }}
      />
      <Stack.Screen
        name="(modals)/federation"
        options={{ ...modalOptions, headerShown: false, title: t('federation:title') }}
      />
      <Stack.Screen
        name="(modals)/federation-partner"
        options={{ ...modalOptions, headerShown: false, title: t('federation:partnerTitle') }}
      />
      <Stack.Screen
        name="(modals)/federation-partners"
        options={{ ...modalOptions, headerShown: false, title: t('federation:directory.partners.title') }}
      />
        <Stack.Screen
          name="(modals)/federation-members"
          options={{ ...modalOptions, headerShown: false, title: t('federation:directory.members.title') }}
        />
        <Stack.Screen
          name="(modals)/federation-member"
          options={{ ...modalOptions, headerShown: false, title: t('federation:directory.members.title') }}
        />
        <Stack.Screen
          name="(modals)/federation-connections"
          options={{ ...modalOptions, headerShown: false, title: t('federation:directory.connections.title') }}
        />
        <Stack.Screen
          name="(modals)/federation-messages"
          options={{ ...modalOptions, headerShown: false, title: t('federation:directory.messages.title') }}
        />
        <Stack.Screen
          name="(modals)/federation-listings"
          options={{ ...modalOptions, headerShown: false, title: t('federation:directory.listings.title') }}
        />
        <Stack.Screen
          name="(modals)/federation-groups"
          options={{ ...modalOptions, headerShown: false, title: t('federation:directory.groups.title') }}
        />
        <Stack.Screen
          name="(modals)/federation-events"
          options={{ ...modalOptions, headerShown: false, title: t('federation:directory.events.title') }}
        />
        <Stack.Screen
          name="(modals)/federation-onboarding"
          options={{ ...modalOptions, headerShown: false, title: t('federation:directory.onboarding.title') }}
        />
        <Stack.Screen
          name="(modals)/federation-settings"
          options={{ ...modalOptions, headerShown: false, title: t('federation:directory.settings.title') }}
        />
      <Stack.Screen
        name="(modals)/groups"
        options={{ ...modalOptions, headerShown: false, title: t('groups:title') }}
      />
      <Stack.Screen
        name="(modals)/search"
        options={{ ...modalOptions, headerShown: false, title: t('search:title') }}
      />
      <Stack.Screen
        name="(modals)/image-viewer"
        options={{
          presentation: 'fullScreenModal',
          headerShown: false,
          gestureEnabled: true,
          gestureDirection: 'vertical',
          contentStyle: { backgroundColor: 'transparent' },
        }}
      />
    </Stack>
  );
}
