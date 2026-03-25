// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Share,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, useNavigation, router } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Ionicons } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';
import * as Haptics from 'expo-haptics';

import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';
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
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

const WEB_URL = 'https://app.project-nexus.ie';

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
  const { t } = useTranslation('members');
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme, primary), [theme, primary]);
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
        message: `${member.name} — ${WEB_URL}/members/${member.id}`,
      });
    } catch { /* ignore */ }
  }

  if (isNaN(memberId) || memberId <= 0) {
    return (
      <SafeAreaView style={styles.centered}>
        <Text style={styles.errorText}>{t('common:errors.notFound')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('common:buttons.back')}</Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  if (isLoading && !data) {
    return (
      <SafeAreaView style={styles.centered}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  if (error || !member) {
    return (
      <SafeAreaView style={styles.centered}>
        <Text style={styles.errorText}>{t('profile.loadError')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('common:buttons.back')}</Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  return (
    <ModalErrorBoundary>
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.scroll}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={isLoading} onRefresh={() => void refresh()} tintColor={primary} colors={[primary]} />
        }
      >

        {/* Avatar + identity */}
        <View style={styles.heroSection}>
          <Avatar uri={member.avatar_url} name={member.name} size={80} />

          <TouchableOpacity
            onPress={() => void handleShare()}
            style={{ position: 'absolute', top: 24, right: 24, padding: 4 }}
            activeOpacity={0.7}
            accessibilityLabel={t('profile.share')}
            accessibilityRole="button"
          >
            <Ionicons name="share-outline" size={22} color={primary} />
          </TouchableOpacity>

          <View style={styles.identityRow}>
            <Text style={styles.name}>{member.name}</Text>
            {member.is_verified && (
              <View style={styles.verifiedBadge}>
                <Text style={styles.verifiedText}>{t('profile.verified')}</Text>
              </View>
            )}
          </View>

          {member.rating != null && (
            <Text style={styles.rating}>{member.rating.toFixed(1)} ★</Text>
          )}

          {member.bio && (
            <Text style={styles.bio}>{member.bio}</Text>
          )}

          {member.location && (
            <Text style={styles.location}>{member.location}</Text>
          )}
        </View>

        {/* Stats row */}
        <View style={styles.statsRow}>
          <View style={styles.statItem}>
            <Text style={[styles.statValue, { color: primary }]}>
              {(member.total_hours_given ?? member.time_balance ?? 0).toFixed(0)}
            </Text>
            <Text style={styles.statLabel}>{t('profile.hoursGiven')}</Text>
          </View>
          <View style={styles.statDivider} />
          <View style={styles.statItem}>
            <Text style={[styles.statValue, { color: primary }]}>
              {(member.total_hours_received ?? 0).toFixed(0)}
            </Text>
            <Text style={styles.statLabel}>{t('profile.hoursReceived')}</Text>
          </View>
        </View>

        {/* Connection actions */}
        {!isOwnProfile && !connLoading && (
          <View style={styles.connectionRow}>
            {connStatus === 'none' && (
              <TouchableOpacity
                style={styles.connectButton}
                activeOpacity={0.85}
                disabled={connActionLoading}
                accessibilityLabel={t('profile.connect')}
                accessibilityRole="button"
                onPress={() => void handleConnect()}
              >
                {connActionLoading ? (
                  <ActivityIndicator size="small" color={primary} />
                ) : (
                  <>
                    <Ionicons name="person-add-outline" size={18} color={primary} />
                    <Text style={styles.connectButtonText}>{t('profile.connect')}</Text>
                  </>
                )}
              </TouchableOpacity>
            )}

            {connStatus === 'pending_sent' && (
              <View style={styles.pendingBadge}>
                <Ionicons name="time-outline" size={16} color={theme.textMuted} />
                <Text style={styles.pendingText}>{t('profile.pendingSent')}</Text>
              </View>
            )}

            {connStatus === 'pending_received' && (
              <View style={styles.respondRow}>
                <TouchableOpacity
                  style={styles.acceptButton}
                  activeOpacity={0.85}
                  disabled={connActionLoading}
                  accessibilityLabel={t('profile.accept')}
                  accessibilityRole="button"
                  onPress={() => void handleAccept()}
                >
                  {connActionLoading ? (
                    <ActivityIndicator size="small" color="#fff" />
                  ) : (
                    <Text style={styles.acceptButtonText}>{t('profile.accept')}</Text>
                  )}
                </TouchableOpacity>
                <TouchableOpacity
                  style={styles.declineButton}
                  activeOpacity={0.85}
                  disabled={connActionLoading}
                  accessibilityLabel={t('profile.decline')}
                  accessibilityRole="button"
                  onPress={() => void handleDecline()}
                >
                  <Text style={styles.declineButtonText}>{t('profile.decline')}</Text>
                </TouchableOpacity>
              </View>
            )}

            {connStatus === 'connected' && (
              <TouchableOpacity
                style={styles.connectedBadge}
                activeOpacity={0.85}
                accessibilityLabel={t('profile.connected')}
                accessibilityRole="button"
                onPress={handleDisconnect}
              >
                <Ionicons name="checkmark-circle" size={18} color={theme.success} />
                <Text style={styles.connectedText}>{t('profile.connected')}</Text>
              </TouchableOpacity>
            )}
          </View>
        )}

        {/* Skills */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>{t('profile.skills')}</Text>
          {(member.skills?.length ?? 0) > 0 ? (
            <View style={styles.skillsWrap}>
              {member.skills.map((skill) => (
                <View key={skill} style={[styles.skillChip, { borderColor: primary }]}>
                  <Text style={[styles.skillText, { color: primary }]}>{skill}</Text>
                </View>
              ))}
            </View>
          ) : (
            <Text style={styles.emptyStateText}>{t('profile.noSkills')}</Text>
          )}
        </View>

        {/* Member since */}
        {member.joined_at ? (
          <Text style={styles.joinedText}>
            {t('profile.memberSince', { date: formatDate(member.joined_at) })}
          </Text>
        ) : null}

      </ScrollView>

      {/* Footer actions */}
      <View style={styles.footer}>
        <View style={styles.footerRow}>
          {!isOwnProfile && connStatus !== 'connected' && connStatus !== 'pending_sent' && (
            <TouchableOpacity
              style={styles.footerConnectButton}
              activeOpacity={0.85}
              disabled={connActionLoading}
              accessibilityLabel={t('profile.connect')}
              accessibilityRole="button"
              onPress={() => void handleConnect()}
            >
              {connActionLoading ? (
                <ActivityIndicator size="small" color={primary} />
              ) : (
                <>
                  <Ionicons name="person-add-outline" size={18} color={primary} />
                  <Text style={styles.footerConnectText}>{t('profile.connect')}</Text>
                </>
              )}
            </TouchableOpacity>
          )}
          <TouchableOpacity
            style={[styles.messageButton, { backgroundColor: primary, flex: 1 }]}
            activeOpacity={0.85}
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
            <Text style={styles.messageButtonText}>{t('profile.sendMessage')}</Text>
          </TouchableOpacity>
        </View>
      </View>
    </SafeAreaView>
    </ModalErrorBoundary>
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

function makeStyles(theme: Theme, primary: string) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.surface },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: SPACING.xl },
    errorText: { ...TYPOGRAPHY.label, color: theme.error, textAlign: 'center' },
    scroll: { paddingBottom: SPACING.lg },
    heroSection: {
      alignItems: 'center',
      paddingTop: SPACING.lg,
      paddingHorizontal: SPACING.lg,
      paddingBottom: SPACING.md,
      gap: SPACING.sm,
    },
    identityRow: { flexDirection: 'row', alignItems: 'center', gap: SPACING.sm, flexWrap: 'wrap', justifyContent: 'center' },
    name: { ...TYPOGRAPHY.h2, color: theme.text, textAlign: 'center' },
    verifiedBadge: {
      backgroundColor: theme.successBg,
      borderRadius: 12,
      paddingHorizontal: 10,
      paddingVertical: 3,
    },
    verifiedText: { ...TYPOGRAPHY.caption, color: theme.success, fontWeight: '600' },
    rating: { fontSize: 16, color: theme.warning, fontWeight: '600' },
    bio: {
      ...TYPOGRAPHY.label,
      color: theme.textSecondary,
      textAlign: 'center',
    },
    location: { ...TYPOGRAPHY.bodySmall, color: theme.textMuted },
    statsRow: {
      flexDirection: 'row',
      marginHorizontal: SPACING.lg,
      marginTop: SPACING.sm,
      marginBottom: SPACING.md,
      borderWidth: 1,
      borderColor: theme.border,
      borderRadius: 12,
      overflow: 'hidden',
    },
    statItem: { flex: 1, alignItems: 'center', paddingVertical: SPACING.md },
    statDivider: { width: 1, backgroundColor: theme.border },
    statValue: { fontSize: 24, fontWeight: '700' },
    statLabel: { ...TYPOGRAPHY.caption, color: theme.textMuted, marginTop: 2 },
    /* Connection action buttons */
    connectionRow: {
      flexDirection: 'row',
      justifyContent: 'center',
      alignItems: 'center',
      paddingHorizontal: SPACING.lg,
      marginBottom: SPACING.md,
    },
    connectButton: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 8,
      borderWidth: 1.5,
      borderColor: primary,
      borderRadius: 12,
      paddingVertical: 10,
      paddingHorizontal: 24,
    },
    connectButtonText: { ...TYPOGRAPHY.body, color: primary, fontWeight: '600' },
    pendingBadge: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: RADIUS.sm,
      backgroundColor: theme.borderSubtle,
      borderRadius: 12,
      paddingVertical: RADIUS.md,
      paddingHorizontal: 20,
    },
    pendingText: { ...TYPOGRAPHY.label, color: theme.textMuted },
    respondRow: {
      flexDirection: 'row',
      gap: 12,
    },
    acceptButton: {
      backgroundColor: primary,
      borderRadius: 12,
      paddingVertical: 10,
      paddingHorizontal: 24,
      justifyContent: 'center',
      alignItems: 'center',
    },
    acceptButtonText: { ...TYPOGRAPHY.body, color: '#fff', fontWeight: '600' },
    declineButton: {
      borderWidth: 1.5,
      borderColor: theme.border,
      borderRadius: 12,
      paddingVertical: 10,
      paddingHorizontal: 24,
      justifyContent: 'center',
      alignItems: 'center',
    },
    declineButtonText: { ...TYPOGRAPHY.body, color: theme.textSecondary, fontWeight: '600' },
    connectedBadge: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 6,
      backgroundColor: theme.successBg,
      borderRadius: 12,
      paddingVertical: 10,
      paddingHorizontal: 20,
    },
    connectedText: { ...TYPOGRAPHY.label, color: theme.success, fontWeight: '600' },
    section: { paddingHorizontal: SPACING.lg, marginBottom: SPACING.md },
    sectionTitle: { ...TYPOGRAPHY.body, fontWeight: '600', color: theme.text, marginBottom: 10 },
    skillsWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: SPACING.sm },
    skillChip: {
      borderWidth: 1,
      borderRadius: 8,
      paddingHorizontal: 12,
      paddingVertical: 4,
    },
    skillText: { ...TYPOGRAPHY.bodySmall, fontWeight: '500' },
    emptyStateText: { ...TYPOGRAPHY.bodySmall, color: theme.textMuted, fontStyle: 'italic' },
    joinedText: {
      ...TYPOGRAPHY.caption,
      color: theme.textMuted,
      textAlign: 'center',
      paddingHorizontal: SPACING.lg,
    },
    footer: {
      padding: SPACING.md,
      borderTopWidth: 1,
      borderTopColor: theme.border,
      backgroundColor: theme.surface,
    },
    footerRow: {
      flexDirection: 'row',
      gap: 12,
      alignItems: 'center',
    },
    footerConnectButton: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 6,
      height: 48,
      borderWidth: 1.5,
      borderColor: primary,
      borderRadius: 12,
      paddingHorizontal: 16,
    },
    footerConnectText: { ...TYPOGRAPHY.body, color: primary, fontWeight: '600' },
    messageButton: {
      height: 48,
      borderRadius: 12,
      justifyContent: 'center',
      alignItems: 'center',
    },
    messageButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' }, // contrast on primary
  });
}
