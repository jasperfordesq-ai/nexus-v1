// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  View,
  Text,
  ScrollView,
  Pressable,
  RefreshControl,
  Share,
  Alert,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, useNavigation, router } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { Ionicons } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';
import * as Haptics from '@/lib/haptics';
import { Spinner } from 'heroui-native';

import { getMember } from '@/lib/api/members';
import {
  getConnectionStatus,
  sendConnectionRequest,
  acceptConnection,
  removeConnection,
  type ConnectionStatusType,
} from '@/lib/api/connections';
import { useApi } from '@/lib/hooks/useApi';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import { APP_URL } from '@/lib/constants';

/** Extended member shape returned by the single-member endpoint */
interface MemberProfile {
  id: number;
  name: string;
  bio: string | null;
  avatar_url: string | null;
  location: string | null;
  time_balance: number;
  skills: string[];
  joined_at: string;
  last_active_at: string | null;
  total_hours_given?: number;
  total_hours_received?: number;
  rating?: number | null;
  is_verified?: boolean;
}

export default function MemberProfileScreen() {
  return (
    <ModalErrorBoundary>
      <MemberProfileScreenInner />
    </ModalErrorBoundary>
  );
}

function MemberProfileScreenInner() {
  const { t } = useTranslation('members');
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const navigation = useNavigation();
  const { user } = useAuth();

  const memberId = Number(id);
  const safeMemberId = isNaN(memberId) || memberId <= 0 ? 0 : memberId;
  const isOwnProfile = user?.id === safeMemberId;

  const { data, isLoading, error, refresh } = useApi(
    () => getMember(safeMemberId),
    [safeMemberId],
    { enabled: safeMemberId > 0 },
  );

  const member = data?.data as MemberProfile | undefined;

  // Connection status
  const [connStatus, setConnStatus] = useState<ConnectionStatusType>('none');
  const [connId, setConnId] = useState<number | null>(null);
  const [connLoading, setConnLoading] = useState(false);
  const [connActionLoading, setConnActionLoading] = useState(false);

  const loadConnectionStatus = useCallback(async () => {
    if (!safeMemberId || isOwnProfile) return;
    setConnLoading(true);
    try {
      const res = await getConnectionStatus(safeMemberId);
      setConnStatus(res.data.status);
      setConnId(res.data.connection_id);
    } catch {
      // Silently fail — connection status is non-critical
    } finally {
      setConnLoading(false);
    }
  }, [safeMemberId, isOwnProfile]);

  useEffect(() => {
    if (member) {
      void loadConnectionStatus();
    }
  }, [member, loadConnectionStatus]);

  async function handleConnect() {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setConnActionLoading(true);
    try {
      const res = await sendConnectionRequest(safeMemberId);
      setConnStatus('pending_sent');
      setConnId(res.data.connection_id);
    } catch {
      Alert.alert(t('profile.connectionError'));
    } finally {
      setConnActionLoading(false);
    }
  }

  async function handleAccept() {
    if (!connId) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setConnActionLoading(true);
    try {
      await acceptConnection(connId);
      setConnStatus('connected');
    } catch {
      Alert.alert(t('profile.connectionError'));
    } finally {
      setConnActionLoading(false);
    }
  }

  async function handleDecline() {
    if (!connId) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setConnActionLoading(true);
    try {
      await removeConnection(connId);
      setConnStatus('none');
      setConnId(null);
    } catch {
      Alert.alert(t('profile.connectionError'));
    } finally {
      setConnActionLoading(false);
    }
  }

  function handleDisconnect() {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    Alert.alert(
      t('profile.disconnectConfirm'),
      t('profile.disconnectMessage'),
      [
        { text: t('common:buttons.cancel'), style: 'cancel' },
        {
          text: t('profile.disconnect'),
          style: 'destructive',
          onPress: async () => {
            if (!connId) return;
            setConnActionLoading(true);
            try {
              await removeConnection(connId);
              setConnStatus('none');
              setConnId(null);
            } catch {
              Alert.alert(t('profile.connectionError'));
            } finally {
              setConnActionLoading(false);
            }
          },
        },
      ],
    );
  }

  useEffect(() => {
    if (member?.name) {
      navigation.setOptions({ title: member.name });
    }
  }, [member?.name, navigation]);

  async function handleShare() {
    if (!member) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    try {
      await Share.share({
        message: `${member.name} — ${APP_URL}/members/${member.id}`,
      });
    } catch { /* ignore */ }
  }

  if (isNaN(memberId) || memberId <= 0) {
    return (
      <SafeAreaView className="flex-1 justify-center items-center px-10">
        <Text className="text-sm font-medium text-danger text-center">{t('common:errors.notFound')}</Text>
        <Pressable onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('common:buttons.back')}</Text>
        </Pressable>
      </SafeAreaView>
    );
  }

  if (isLoading && !data) {
    return (
      <SafeAreaView className="flex-1 justify-center items-center px-10">
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  if (error || !member) {
    return (
      <SafeAreaView className="flex-1 justify-center items-center px-10">
        <Text className="text-sm font-medium text-danger text-center">{t('profile.loadError')}</Text>
        <Pressable onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('common:buttons.back')}</Text>
        </Pressable>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView className="flex-1 bg-surface">
      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{ paddingBottom: 24 }}
        refreshControl={
          <RefreshControl refreshing={isLoading} onRefresh={() => void refresh()} tintColor={primary} colors={[primary]} />
        }
      >

        {/* Avatar + identity */}
        <View className="items-center pt-6 px-6 pb-4 gap-2">
          <Avatar uri={member.avatar_url} name={member.name} size={80} />

          <Pressable
            onPress={() => void handleShare()}
            style={{ position: 'absolute', top: 24, right: 24, padding: 4 }}
            accessibilityLabel={t('profile.share')}
            accessibilityRole="button"
          >
            <Ionicons name="share-outline" size={22} color={primary} />
          </Pressable>

          <View className="flex-row items-center gap-2 flex-wrap justify-center">
            <Text className="text-xl font-bold text-foreground text-center">{member.name}</Text>
            {member.is_verified && (
              <View className="bg-success/10 rounded-xl px-[10px] py-[3px]">
                <Text className="text-xs text-success font-semibold">{t('profile.verified')}</Text>
              </View>
            )}
          </View>

          {member.rating != null && (
            <Text style={{ fontSize: 16, color: theme.warning, fontWeight: '600' }}>{member.rating.toFixed(1)} ★</Text>
          )}

          {member.bio && (
            <Text className="text-sm font-medium text-muted-foreground text-center">{member.bio}</Text>
          )}

          {member.location && (
            <Text className="text-xs text-muted-foreground">{member.location}</Text>
          )}
        </View>

        {/* Stats row */}
        <View className="flex-row mx-6 mt-2 mb-4 border border-border rounded-xl overflow-hidden">
          <View className="flex-1 items-center py-4">
            <Text className="text-2xl font-bold" style={{ color: primary }}>
              {(member.total_hours_given ?? member.time_balance ?? 0).toFixed(0)}
            </Text>
            <Text className="text-xs text-muted-foreground mt-0.5">{t('profile.hoursGiven')}</Text>
          </View>
          <View className="w-px bg-border" />
          <View className="flex-1 items-center py-4">
            <Text className="text-2xl font-bold" style={{ color: primary }}>
              {(member.total_hours_received ?? 0).toFixed(0)}
            </Text>
            <Text className="text-xs text-muted-foreground mt-0.5">{t('profile.hoursReceived')}</Text>
          </View>
        </View>

        {/* Connection actions */}
        {!isOwnProfile && !connLoading && (
          <View className="flex-row justify-center items-center px-6 mb-4">
            {connStatus === 'none' && (
              <Pressable
                className="flex-row items-center justify-center gap-2 border-[1.5px] rounded-xl py-[10px] px-6"
                style={{ borderColor: primary }}
                disabled={connActionLoading}
                accessibilityLabel={t('profile.connect')}
                accessibilityRole="button"
                onPress={() => void handleConnect()}
              >
                {connActionLoading ? (
                  <Spinner size="sm" />
                ) : (
                  <>
                    <Ionicons name="person-add-outline" size={18} color={primary} />
                    <Text className="text-sm font-semibold" style={{ color: primary }}>{t('profile.connect')}</Text>
                  </>
                )}
              </Pressable>
            )}

            {connStatus === 'pending_sent' && (
              <View className="flex-row items-center gap-2 bg-border/50 rounded-xl py-3 px-5">
                <Ionicons name="time-outline" size={16} color={theme.textMuted} />
                <Text className="text-sm font-medium text-muted-foreground">{t('profile.pendingSent')}</Text>
              </View>
            )}

            {connStatus === 'pending_received' && (
              <View className="flex-row gap-3">
                <Pressable
                  className="rounded-xl py-[10px] px-6 justify-center items-center"
                  style={{ backgroundColor: primary }}
                  disabled={connActionLoading}
                  accessibilityLabel={t('profile.accept')}
                  accessibilityRole="button"
                  onPress={() => void handleAccept()}
                >
                  {connActionLoading ? (
                    <Spinner size="sm" />
                  ) : (
                    <Text className="text-sm font-semibold text-white">{t('profile.accept')}</Text>
                  )}
                </Pressable>
                <Pressable
                  className="border-[1.5px] border-border rounded-xl py-[10px] px-6 justify-center items-center"
                  disabled={connActionLoading}
                  accessibilityLabel={t('profile.decline')}
                  accessibilityRole="button"
                  onPress={() => void handleDecline()}
                >
                  <Text className="text-sm font-semibold text-muted-foreground">{t('profile.decline')}</Text>
                </Pressable>
              </View>
            )}

            {connStatus === 'connected' && (
              <Pressable
                className="flex-row items-center gap-[6px] bg-success/10 rounded-xl py-[10px] px-5"
                accessibilityLabel={t('profile.connected')}
                accessibilityRole="button"
                onPress={handleDisconnect}
              >
                <Ionicons name="checkmark-circle" size={18} color={theme.success} />
                <Text className="text-sm font-semibold text-success">{t('profile.connected')}</Text>
              </Pressable>
            )}
          </View>
        )}

        {/* Skills */}
        <View className="px-6 mb-4">
          <Text className="text-sm font-semibold text-foreground mb-[10px]">{t('profile.skills')}</Text>
          {(member.skills?.length ?? 0) > 0 ? (
            <View className="flex-row flex-wrap gap-2">
              {member.skills.map((skill) => (
                <View key={skill} className="border rounded-lg px-3 py-1" style={{ borderColor: primary }}>
                  <Text className="text-xs font-medium" style={{ color: primary }}>{skill}</Text>
                </View>
              ))}
            </View>
          ) : (
            <Text className="text-xs text-muted-foreground italic">{t('profile.noSkills')}</Text>
          )}
        </View>

        {/* Member since */}
        {member.joined_at ? (
          <Text className="text-xs text-muted-foreground text-center px-6">
            {t('profile.memberSince', { date: formatDate(member.joined_at) })}
          </Text>
        ) : null}

      </ScrollView>

      {/* Footer actions */}
      <View className="p-4 border-t border-border bg-surface">
        <View className="flex-row gap-3 items-center">
          {!isOwnProfile && connStatus !== 'connected' && connStatus !== 'pending_sent' && (
            <Pressable
              className="flex-row items-center justify-center gap-[6px] h-12 border-[1.5px] rounded-xl px-4"
              style={{ borderColor: primary }}
              disabled={connActionLoading}
              accessibilityLabel={t('profile.connect')}
              accessibilityRole="button"
              onPress={() => void handleConnect()}
            >
              {connActionLoading ? (
                <Spinner size="sm" />
              ) : (
                <>
                  <Ionicons name="person-add-outline" size={18} color={primary} />
                  <Text className="text-sm font-semibold" style={{ color: primary }}>{t('profile.connect')}</Text>
                </>
              )}
            </Pressable>
          )}
          <Pressable
            className="flex-1 h-12 rounded-xl justify-center items-center"
            style={{ backgroundColor: primary }}
            accessibilityLabel={t('profile.sendMessage')}
            accessibilityRole="button"
            onPress={() => {
              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              router.push({
                pathname: '/(modals)/thread',
                params: { recipientId: String(member.id), name: member.name },
              });
            }}
          >
            <Text className="text-base font-semibold text-white">{t('profile.sendMessage')}</Text>
          </Pressable>
        </View>
      </View>
    </SafeAreaView>
  );
}

function formatDate(iso: string): string {
  try {
    const d = new Date(iso);
    if (isNaN(d.getTime())) return iso;
    return d.toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'long',
    });
  } catch {
    return iso;
  }
}
