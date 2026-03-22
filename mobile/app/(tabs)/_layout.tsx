// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect } from 'react';
import { Tabs, usePathname } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { useRealtimeContext } from '@/lib/context/RealtimeContext';

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

interface TabConfig {
  name: string;
  title: string;
  icon: IoniconName;
  iconFocused: IoniconName;
}

const TABS: TabConfig[] = [
  { name: 'home',      title: 'Home',      icon: 'home-outline',    iconFocused: 'home' },
  { name: 'exchanges', title: 'Listings',  icon: 'storefront-outline', iconFocused: 'storefront' },
  { name: 'events',    title: 'Events',    icon: 'calendar-outline', iconFocused: 'calendar' },
  { name: 'messages',  title: 'Messages',  icon: 'chatbubble-outline', iconFocused: 'chatbubble' },
  { name: 'profile',   title: 'Profile',   icon: 'person-outline',  iconFocused: 'person' },
];

export default function TabsLayout() {
  const primary = usePrimaryColor();
  const theme = useTheme();
  const { unreadMessages, resetUnread } = useRealtimeContext();
  const pathname = usePathname();
  const insets = useSafeAreaInsets();

  // Clear the badge whenever the user navigates to the Messages tab
  useEffect(() => {
    if (pathname === '/messages') {
      resetUnread();
    }
  }, [pathname, resetUnread]);

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: primary,
        tabBarInactiveTintColor: theme.textMuted,
        tabBarStyle: {
          backgroundColor: theme.surface,
          borderTopColor: theme.border,
          borderTopWidth: 1,
          paddingBottom: (insets.bottom || 0) + 4,
          paddingTop: 4,
          height: 60 + (insets.bottom || 0),
        },
        tabBarLabelStyle: {
          fontSize: 11,
          fontWeight: '500',
        },
      }}
    >
      {TABS.map(({ name, title, icon, iconFocused }) => (
        <Tabs.Screen
          key={name}
          name={name}
          options={{
            title,
            tabBarBadge: name === 'messages' && unreadMessages > 0 ? unreadMessages : undefined,
            tabBarBadgeStyle: name === 'messages' && unreadMessages > 0 ? { backgroundColor: primary } : undefined,
            tabBarIcon: ({ focused, color, size }) => (
              <Ionicons
                name={focused ? iconFocused : icon}
                size={size}
                color={color}
              />
            ),
          }}
        />
      ))}
      {/* Hide auxiliary tabs from the tab bar — navigated to programmatically */}
      <Tabs.Screen name="members" options={{ href: null }} />
      <Tabs.Screen name="groups" options={{ href: null }} />
      <Tabs.Screen name="search" options={{ href: null }} />
    </Tabs>
  );
}
