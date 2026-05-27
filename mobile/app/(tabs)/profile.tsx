// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Alert, Pressable, ScrollView, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';
import { Button as HeroButton, Card as HeroCard, Chip, Surface, Text } from 'heroui-native';

import { useAuth } from '@/lib/hooks/useAuth';
import { useTenant, usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import { ProfileSkeleton } from '@/components/ui/Skeleton';
import Avatar from '@/components/ui/Avatar';

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

interface MenuItem {
  labelKey: string;
  descriptionKey: string;
  icon: IoniconName;
  route: Href;
  tone: string;
  featureGate?: string;
}

const MY_SPACE: MenuItem[] = [
  { labelKey: 'myProfile', descriptionKey: 'navDescriptions.myProfile', icon: 'person-outline', route: '/(modals)/member-profile', tone: '#3b82f6' },
  { labelKey: 'wallet', descriptionKey: 'navDescriptions.wallet', icon: 'wallet-outline', route: '/(modals)/wallet', tone: '#f59e0b' },
  { labelKey: 'messages', descriptionKey: 'navDescriptions.messages', icon: 'chatbubble-outline', route: '/(tabs)/messages', tone: '#0ea5e9' },
  { labelKey: 'notifications', descriptionKey: 'navDescriptions.notifications', icon: 'notifications-outline', route: '/(modals)/notifications', tone: '#ef4444' },
  { labelKey: 'achievements', descriptionKey: 'navDescriptions.achievements', icon: 'trophy-outline', route: '/(modals)/gamification', tone: '#f59e0b' },
  { labelKey: 'myGoals', descriptionKey: 'navDescriptions.myGoals', icon: 'flag-outline', route: '/(modals)/goals', tone: '#8b5cf6' },
  { labelKey: 'groups', descriptionKey: 'navDescriptions.groups', icon: 'people-outline', route: '/(modals)/groups', tone: '#06b6d4' },
];

const DISCOVER: MenuItem[] = [
  { labelKey: 'search', descriptionKey: 'navDescriptions.search', icon: 'search-outline', route: '/(modals)/search', tone: '#64748b' },
  { labelKey: 'listings', descriptionKey: 'navDescriptions.listings', icon: 'storefront-outline', route: '/(tabs)/exchanges', tone: '#0f766e' },
  { labelKey: 'marketplace', descriptionKey: 'navDescriptions.marketplace', icon: 'bag-handle-outline', route: '/(modals)/marketplace' as Href, tone: '#0ea5e9', featureGate: 'marketplace' },
  { labelKey: 'jobs', descriptionKey: 'navDescriptions.jobs', icon: 'briefcase-outline', route: '/(modals)/jobs', tone: '#2563eb', featureGate: 'job_vacancies' },
  { labelKey: 'events', descriptionKey: 'navDescriptions.events', icon: 'calendar-outline', route: '/(tabs)/events', tone: '#f43f5e' },
  { labelKey: 'browseMembers', descriptionKey: 'navDescriptions.browseMembers', icon: 'people-outline', route: '/(modals)/members', tone: '#14b8a6' },
  { labelKey: 'volunteering', descriptionKey: 'navDescriptions.volunteering', icon: 'heart-outline', route: '/(modals)/volunteering', tone: '#e11d48' },
  { labelKey: 'organisations', descriptionKey: 'navDescriptions.organisations', icon: 'business-outline', route: '/(modals)/organisations', tone: '#6366f1' },
  { labelKey: 'blog', descriptionKey: 'navDescriptions.blog', icon: 'newspaper-outline', route: '/(modals)/blog', tone: '#f97316' },
  { labelKey: 'skills', descriptionKey: 'navDescriptions.skills', icon: 'ribbon-outline', route: '/(modals)/endorsements', tone: '#10b981' },
  { labelKey: 'aiChat', descriptionKey: 'navDescriptions.aiChat', icon: 'sparkles-outline', route: '/(modals)/chat', tone: '#a855f7' },
  { labelKey: 'federation', descriptionKey: 'navDescriptions.federation', icon: 'globe-outline', route: '/(modals)/federation', tone: '#0ea5e9', featureGate: 'federation' },
];

const MARKETPLACE: MenuItem[] = [
  { labelKey: 'marketplaceBrowse', descriptionKey: 'navDescriptions.marketplaceBrowse', icon: 'bag-handle-outline', route: '/(modals)/marketplace' as Href, tone: '#0ea5e9', featureGate: 'marketplace' },
  { labelKey: 'marketplaceSell', descriptionKey: 'navDescriptions.marketplaceSell', icon: 'add-circle-outline', route: '/(modals)/new-marketplace-listing' as Href, tone: '#22c55e', featureGate: 'marketplace' },
  { labelKey: 'marketplaceMyListings', descriptionKey: 'navDescriptions.marketplaceMyListings', icon: 'albums-outline', route: '/(modals)/marketplace-my-listings' as Href, tone: '#6366f1', featureGate: 'marketplace' },
  { labelKey: 'marketplaceOrders', descriptionKey: 'navDescriptions.marketplaceOrders', icon: 'receipt-outline', route: '/(modals)/marketplace-orders' as Href, tone: '#f97316', featureGate: 'marketplace' },
  { labelKey: 'marketplaceOffers', descriptionKey: 'navDescriptions.marketplaceOffers', icon: 'pricetag-outline', route: '/(modals)/marketplace-offers' as Href, tone: '#14b8a6', featureGate: 'marketplace' },
  { labelKey: 'marketplaceSaved', descriptionKey: 'navDescriptions.marketplaceSaved', icon: 'folder-open-outline', route: '/(modals)/marketplace-collections' as Href, tone: '#8b5cf6', featureGate: 'marketplace' },
  { labelKey: 'marketplaceTools', descriptionKey: 'navDescriptions.marketplaceTools', icon: 'construct-outline', route: '/(modals)/marketplace-tools' as Href, tone: '#64748b', featureGate: 'marketplace' },
  { labelKey: 'marketplacePayments', descriptionKey: 'navDescriptions.marketplacePayments', icon: 'card-outline', route: '/(modals)/marketplace-stripe-onboarding' as Href, tone: '#0f766e', featureGate: 'marketplace' },
];

const ACCOUNT: MenuItem[] = [
  { labelKey: 'settings', descriptionKey: 'navDescriptions.settings', icon: 'settings-outline', route: '/(modals)/settings', tone: '#64748b' },
];

export default function MoreScreen() {
  const { t } = useTranslation(['profile', 'common']);
  const { user, displayName, logout } = useAuth();
  const { hasFeature } = useTenant();
  const primary = usePrimaryColor();
  const theme = useTheme();

  const rawBalance = user && 'balance' in user ? (user.balance as number | null) : null;
  const balance = typeof rawBalance === 'number' && Number.isFinite(rawBalance) ? rawBalance : null;
  const visibleDiscover = DISCOVER.filter((item) => !item.featureGate || hasFeature(item.featureGate));
  const visibleMarketplace = MARKETPLACE.filter((item) => !item.featureGate || hasFeature(item.featureGate));

  function navigate(route: Href) {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    router.push(route);
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

  return (
    <SafeAreaView className="flex-1 bg-background">
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 112 }} showsVerticalScrollIndicator={false}>
        <HeroCard className="mb-4 overflow-hidden rounded-panel p-0">
          <View className="h-1.5" style={{ backgroundColor: primary }} />
          <HeroCard.Body className="gap-5 p-4">
            <View className="flex-row items-start gap-4">
              <Avatar uri={user.avatar_url} name={displayName} size={72} showOnline />
              <View className="min-w-0 flex-1 gap-2">
                <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }}>
                  {t('hubEyebrow')}
                </Text>
                <Text className="text-2xl font-bold leading-8" style={{ color: theme.text }} numberOfLines={2}>
                  {displayName}
                </Text>
                <Text className="text-sm" style={{ color: theme.textSecondary }} numberOfLines={1}>
                  {user.email}
                </Text>
                {balance !== null ? (
                  <Chip size="sm" variant="secondary">
                    <Ionicons name="time-outline" size={12} color={primary} />
                    <Chip.Label>{t('balanceLabel', { balance: balance.toFixed(1) })}</Chip.Label>
                  </Chip>
                ) : null}
              </View>
            </View>

            <View className="flex-row gap-3">
              <HeroButton className="flex-1" variant="primary" onPress={() => navigate('/(modals)/edit-profile')} style={{ backgroundColor: primary }}>
                <Ionicons name="create-outline" size={17} color="#fff" />
                <HeroButton.Label>{t('editProfile')}</HeroButton.Label>
              </HeroButton>
              <HeroButton className="flex-1" variant="secondary" onPress={() => navigate('/(modals)/wallet')}>
                <Ionicons name="wallet-outline" size={17} color={primary} />
                <HeroButton.Label>{t('viewWallet')}</HeroButton.Label>
              </HeroButton>
            </View>
          </HeroCard.Body>
        </HeroCard>

        <View className="mb-4 flex-row gap-3">
          <MetricCard icon="star-outline" label={t('quickStats.trust')} value={t('quickStats.active')} tone="#f59e0b" theme={theme} />
          <MetricCard icon="git-network-outline" label={t('quickStats.network')} value={visibleDiscover.length.toString()} tone="#0ea5e9" theme={theme} />
          <MetricCard icon="shield-checkmark-outline" label={t('quickStats.account')} value={t('quickStats.ready')} tone="#22c55e" theme={theme} />
        </View>

        <MenuSection title={t('discover')} items={visibleDiscover} onNavigate={navigate} theme={theme} />
        <MenuSection title={t('marketplaceSection')} items={visibleMarketplace} onNavigate={navigate} theme={theme} />
        <MenuSection title={t('mySpace')} items={MY_SPACE} onNavigate={navigate} theme={theme} />
        <MenuSection title={t('account')} items={ACCOUNT} onNavigate={navigate} theme={theme} />

        <HeroButton variant="danger" onPress={confirmLogout} className="mt-1">
          <Ionicons name="log-out-outline" size={18} color="#fff" />
          <HeroButton.Label>{t('signOut')}</HeroButton.Label>
        </HeroButton>

        <Text className="mt-6 px-3 text-center text-[11px] leading-4" style={{ color: theme.textMuted }}>
          {t('common:attribution')}
        </Text>
      </ScrollView>
    </SafeAreaView>
  );
}

function MetricCard({ icon, label, value, tone, theme }: { icon: IoniconName; label: string; value: string; tone: string; theme: Theme }) {
  return (
    <HeroCard className="flex-1 rounded-panel p-0">
      <HeroCard.Body className="items-center gap-1 px-2 py-3">
        <View className="size-9 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(tone, 0.14) }}>
          <Ionicons name={icon} size={17} color={tone} />
        </View>
        <Text className="text-base font-bold" style={{ color: theme.text }} numberOfLines={1}>{value}</Text>
        <Text className="text-center text-[11px] leading-4" style={{ color: theme.textSecondary }} numberOfLines={2}>{label}</Text>
      </HeroCard.Body>
    </HeroCard>
  );
}

function MenuSection({ title, items, onNavigate, theme }: { title: string; items: MenuItem[]; onNavigate: (route: Href) => void; theme: Theme }) {
  if (items.length === 0) return null;

  return (
    <View className="mb-4">
      <Text className="mb-2 text-xs font-semibold uppercase" style={{ color: theme.textSecondary }}>
        {title}
      </Text>
      <HeroCard className="rounded-panel p-0">
        <HeroCard.Body className="gap-2 p-2">
          {items.map((item) => (
            <MenuRow key={String(item.route)} item={item} onPress={() => onNavigate(item.route)} theme={theme} />
          ))}
        </HeroCard.Body>
      </HeroCard>
    </View>
  );
}

function MenuRow({ item, onPress, theme }: { item: MenuItem; onPress: () => void; theme: Theme }) {
  const { t } = useTranslation('profile');

  return (
    <Pressable accessibilityRole="button" accessibilityLabel={t(item.labelKey)} onPress={onPress}>
      <Surface variant="secondary" className="flex-row items-center gap-3 rounded-panel-inner px-3 py-3">
        <View className="size-11 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(item.tone, 0.14) }}>
          <Ionicons name={item.icon} size={20} color={item.tone} />
        </View>
        <View className="min-w-0 flex-1 gap-0.5">
          <Text className="text-base font-semibold" style={{ color: theme.text }} numberOfLines={1}>
            {t(item.labelKey)}
          </Text>
          <Text className="text-xs leading-4" style={{ color: theme.textSecondary }} numberOfLines={2}>
            {t(item.descriptionKey)}
          </Text>
        </View>
        <Ionicons name="chevron-forward-outline" size={18} color={theme.textSecondary} />
      </Surface>
    </Pressable>
  );
}
