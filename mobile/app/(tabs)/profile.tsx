// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Alert, ScrollView, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';
import {
  Card,
  Chip,
  ListGroup,
  Separator,
  Surface,
  Text,
} from 'heroui-native';

import { useAuth } from '@/lib/hooks/useAuth';
import { useTenant, usePrimaryColor } from '@/lib/hooks/useTenant';
import { ProfileSkeleton } from '@/components/ui/Skeleton';
import Avatar from '@/components/ui/Avatar';
import Button from '@/components/ui/Button';

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

interface MenuSection {
  titleKey: string;
  items: MenuItem[];
}

interface MenuItem {
  labelKey: string;
  icon: IoniconName;
  route: string;
  featureGate?: string;
}

const MY_SPACE: MenuItem[] = [
  { labelKey: 'myProfile',     icon: 'person-outline',   route: '/(modals)/member-profile' },
  { labelKey: 'achievements',  icon: 'trophy-outline',   route: '/(modals)/gamification' },
  { labelKey: 'myGoals',       icon: 'flag-outline',     route: '/(modals)/goals' },
  { labelKey: 'groups',        icon: 'people-outline',   route: '/(modals)/groups' },
];

const DISCOVER: MenuItem[] = [
  { labelKey: 'browseMembers',  icon: 'people-outline',    route: '/(modals)/members' },
  { labelKey: 'volunteering',   icon: 'heart-outline',     route: '/(modals)/volunteering' },
  { labelKey: 'organisations',  icon: 'business-outline',  route: '/(modals)/organisations' },
  { labelKey: 'aiChat',         icon: 'sparkles-outline',  route: '/(modals)/chat' },
  { labelKey: 'federation',     icon: 'globe-outline',     route: '/(modals)/federation', featureGate: 'federation' },
];

const ACCOUNT: MenuItem[] = [
  { labelKey: 'settings', icon: 'settings-outline', route: '/(modals)/settings' },
];

export default function MoreScreen() {
  const { t } = useTranslation('profile');
  const { user, displayName, logout } = useAuth();
  const { hasFeature } = useTenant();
  const primary = usePrimaryColor();

  const rawBalance = user && 'balance' in user ? (user.balance as number | null) : null;
  const balance = typeof rawBalance === 'number' && Number.isFinite(rawBalance) ? rawBalance : null;

  function navigate(route: string) {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    router.push(route as Parameters<typeof router.push>[0]);
  }

  function confirmLogout() {
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
    Alert.alert(
      t('signOutConfirmTitle'),
      t('signOutConfirmMessage'),
      [
        { text: t('common:buttons.cancel'), style: 'cancel' },
        { text: t('signOut'), style: 'destructive', onPress: () => void logout() },
      ],
    );
  }

  if (!user) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <ProfileSkeleton />
      </SafeAreaView>
    );
  }

  function renderSection(items: MenuItem[]) {
    return items
      .filter(item => !item.featureGate || hasFeature(item.featureGate))
      .map(item => (
        <ListGroup.Item
          key={item.route}
          onPress={() => navigate(item.route)}
          accessibilityRole="button"
        >
          <ListGroup.ItemPrefix>
            <View
              style={{
                width: 32,
                height: 32,
                borderRadius: 8,
                backgroundColor: `${primary}1a`,
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              <Ionicons name={item.icon} size={18} color={primary} />
            </View>
          </ListGroup.ItemPrefix>
          <ListGroup.ItemContent>
            <ListGroup.ItemTitle>{t(item.labelKey)}</ListGroup.ItemTitle>
          </ListGroup.ItemContent>
          <ListGroup.ItemSuffix>
            <Ionicons name="chevron-forward" size={16} color="#9ca3af" />
          </ListGroup.ItemSuffix>
        </ListGroup.Item>
      ));
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      <ScrollView
        contentContainerStyle={{ paddingBottom: 40 }}
        showsVerticalScrollIndicator={false}
      >
        {/* ── Profile card ── */}
        <View className="px-4 pt-6 pb-4">
          <Surface className="rounded-2xl overflow-hidden">
            <Card>
              <Card.Body>
                <View className="flex-row items-center gap-4">
                  <Avatar uri={user.avatar_url} name={displayName} size={64} />
                  <View className="flex-1 min-w-0">
                    <Text.Heading
                      level={3}
                      className="text-foreground font-bold"
                      numberOfLines={1}
                    >
                      {displayName}
                    </Text.Heading>
                    <Text.Paragraph
                      className="text-muted-foreground text-sm mt-0.5"
                      numberOfLines={1}
                    >
                      {user.email}
                    </Text.Paragraph>
                    {balance !== null ? (
                      <View className="mt-2 self-start">
                        <Chip
                          size="sm"
                          style={{ backgroundColor: `${primary}1a` }}
                        >
                          <Text className="text-xs font-semibold" style={{ color: primary }}>
                            {balance.toFixed(1)} {t('hrs')} · {t('timeBalance')}
                          </Text>
                        </Chip>
                      </View>
                    ) : null}
                  </View>
                </View>

                {/* Action buttons */}
                <View className="flex-row gap-3 mt-4">
                  <View className="flex-1">
                    <Button
                      variant="ghost"
                      size="sm"
                      fullWidth
                      onPress={() => navigate('/(modals)/edit-profile')}
                    >
                      {t('editProfile')}
                    </Button>
                  </View>
                  <View className="flex-1">
                    <Button
                      variant="outline"
                      size="sm"
                      fullWidth
                      color={primary}
                      onPress={() => navigate('/(modals)/wallet')}
                    >
                      {t('viewWallet')}
                    </Button>
                  </View>
                </View>
              </Card.Body>
            </Card>
          </Surface>
        </View>

        <Separator className="my-2" />

        {/* ── My Space ── */}
        <View className="px-4 pt-2 pb-1">
          <Text className="text-xs font-semibold text-muted-foreground uppercase tracking-widest mb-2">
            {t('mySpace')}
          </Text>
          <Surface className="rounded-2xl overflow-hidden">
            <ListGroup>
              {renderSection(MY_SPACE)}
            </ListGroup>
          </Surface>
        </View>

        <Separator className="my-2" />

        {/* ── Discover ── */}
        <View className="px-4 pt-2 pb-1">
          <Text className="text-xs font-semibold text-muted-foreground uppercase tracking-widest mb-2">
            {t('discover')}
          </Text>
          <Surface className="rounded-2xl overflow-hidden">
            <ListGroup>
              {renderSection(DISCOVER)}
            </ListGroup>
          </Surface>
        </View>

        <Separator className="my-2" />

        {/* ── Account ── */}
        <View className="px-4 pt-2 pb-1">
          <Text className="text-xs font-semibold text-muted-foreground uppercase tracking-widest mb-2">
            {t('account')}
          </Text>
          <Surface className="rounded-2xl overflow-hidden">
            <ListGroup>
              {renderSection(ACCOUNT)}
            </ListGroup>
          </Surface>
        </View>

        <Separator className="my-4" />

        {/* ── Sign out ── */}
        <View className="px-4 mb-2">
          <Button
            variant="danger"
            fullWidth
            onPress={confirmLogout}
          >
            {t('signOut')}
          </Button>
        </View>

        {/* ── AGPL attribution — required by Section 7(b) ── */}
        <Text className="text-[11px] text-muted-foreground text-center mt-6 px-6">
          {t('common:attribution')}
        </Text>
      </ScrollView>
    </SafeAreaView>
  );
}
