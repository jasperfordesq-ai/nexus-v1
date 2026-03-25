// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Alert,
  FlatList,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import {
  addSkill,
  getMySkills,
  getUserEndorsements,
  removeSkill,
  type Endorsement,
  type Skill,
} from '@/lib/api/endorsements';
import { useApi } from '@/lib/hooks/useApi';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

type Tab = 'skills' | 'endorsements';

export default function EndorsementsScreen() {
  const { t } = useTranslation('endorsements');
  const navigation = useNavigation();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);
  const { user } = useAuth();

  const [activeTab, setActiveTab] = useState<Tab>('skills');
  const [addingSkill, setAddingSkill] = useState(false);
  const [skillInput, setSkillInput] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const skillInputRef = useRef<TextInput>(null);

  useEffect(() => {
    navigation.setOptions({ title: t('title') });
  }, [navigation, t]);

  const userId = user?.id ?? 0;

  const {
    data: skillsData,
    isLoading: skillsLoading,
    refresh: refreshSkills,
  } = useApi(() => getMySkills(), [], { enabled: userId > 0 });

  const {
    data: endorsementsData,
    isLoading: endorsementsLoading,
    refresh: refreshEndorsements,
  } = useApi(() => getUserEndorsements(userId), [userId], { enabled: userId > 0 });

  const handleRefresh = useCallback(async () => {
    setRefreshing(true);
    await Promise.allSettled([refreshSkills(), refreshEndorsements()]);
    setRefreshing(false);
  }, [refreshSkills, refreshEndorsements]);

  const skills: Skill[] = skillsData?.data?.skills ?? [];
  const endorsements: Endorsement[] = endorsementsData?.data ?? [];

  async function handleAddSkill() {
    const name = skillInput.trim();
    if (!name) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setSubmitting(true);
    try {
      await addSkill(name);
      setSkillInput('');
      setAddingSkill(false);
      refreshSkills();
    } catch {
      Alert.alert('', t('addSkillError'));
    } finally {
      setSubmitting(false);
    }
  }

  async function handleRemoveSkill(skillId: number) {
    Alert.alert('', t('removeSkillConfirm'), [
      { text: t('common:cancel', { defaultValue: 'Cancel' }), style: 'cancel' },
      {
        text: t('removeSkill'),
        style: 'destructive',
        onPress: async () => {
          void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
          try {
            await removeSkill(skillId);
            void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
            Alert.alert('', t('skillRemoved'));
            refreshSkills();
          } catch {
            Alert.alert('', t('removeSkillError'));
          }
        },
      },
    ]);
  }

  const renderSkill = useCallback(
    ({ item }: { item: Skill }) => {
      const endorseCount = endorsements.filter((e) => e.skill.id === item.id).length;
      return (
        <View style={styles.skillRow}>
          <View style={styles.skillInfo}>
            <Text style={styles.skillName}>{item.name}</Text>
            {item.category ? (
              <Text style={styles.skillCategory}>{item.category}</Text>
            ) : null}
            {endorseCount > 0 ? (
              <Text style={styles.endorseCount}>
                {t('endorsedBy', { count: endorseCount })}
              </Text>
            ) : null}
          </View>
          <TouchableOpacity
            onPress={() => void handleRemoveSkill(item.id)}
            style={styles.removeBtn}
            hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
            accessibilityLabel={t('removeSkill')}
            accessibilityRole="button"
          >
            <Ionicons name="trash-outline" size={18} color={theme.error} />
          </TouchableOpacity>
        </View>
      );
    },
    [endorsements, styles, t, theme.error],
  );

  const renderEndorsement = useCallback(
    ({ item }: { item: Endorsement }) => {
      const date = new Date(item.created_at).toLocaleDateString('default', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
      return (
        <View style={styles.endorsementCard}>
          <Avatar uri={item.endorsed_by.avatar} name={item.endorsed_by.name} size={40} />
          <View style={styles.endorsementBody}>
            <Text style={styles.endorserName}>{item.endorsed_by.name}</Text>
            <View style={[styles.skillBadge, { backgroundColor: withAlpha(primary, 0.13) }]}>
              <Text style={[styles.skillBadgeText, { color: primary }]}>{item.skill.name}</Text>
            </View>
            {item.message ? (
              <Text style={styles.endorsementMessage}>{item.message}</Text>
            ) : null}
            <Text style={styles.endorsementDate}>{date}</Text>
          </View>
        </View>
      );
    },
    [styles, primary],
  );

  const isLoading = activeTab === 'skills' ? skillsLoading : endorsementsLoading;

  return (
    <ModalErrorBoundary>
    <SafeAreaView style={styles.container}>
      {/* Tab toggle */}
      <View style={styles.tabRow}>
        <TouchableOpacity
          style={[styles.tabPill, activeTab === 'skills' && { backgroundColor: primary }]}
          onPress={() => { void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light); setActiveTab('skills'); }}
          accessibilityRole="tab"
          accessibilityState={{ selected: activeTab === 'skills' }}
        >
          <Text style={[styles.tabLabel, activeTab === 'skills' && styles.tabLabelActive]}>
            {t('mySkills')}
          </Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.tabPill, activeTab === 'endorsements' && { backgroundColor: primary }]}
          onPress={() => { void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light); setActiveTab('endorsements'); }}
          accessibilityRole="tab"
          accessibilityState={{ selected: activeTab === 'endorsements' }}
        >
          <Text
            style={[
              styles.tabLabel,
              activeTab === 'endorsements' && styles.tabLabelActive,
            ]}
          >
            {t('endorsements')}
          </Text>
        </TouchableOpacity>
      </View>

      {isLoading ? (
        <View style={styles.center}>
          <LoadingSpinner />
        </View>
      ) : activeTab === 'skills' ? (
        <FlatList
          data={skills}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderSkill}
          contentContainerStyle={styles.listContent}
          ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => void handleRefresh()}
              tintColor={primary}
            />
          }
          ListHeaderComponent={
            <View style={styles.addSkillHeader}>
              {addingSkill ? (
                <View style={styles.addSkillForm}>
                  <TextInput
                    ref={skillInputRef}
                    style={[styles.skillInput, { borderColor: primary }]}
                    placeholder={t('skillPlaceholder')}
                    placeholderTextColor={theme.textMuted}
                    value={skillInput}
                    onChangeText={setSkillInput}
                    autoFocus
                    returnKeyType="done"
                    onSubmitEditing={() => void handleAddSkill()}
                  />
                  <TouchableOpacity
                    style={[styles.addBtn, { backgroundColor: primary }]}
                    onPress={() => void handleAddSkill()}
                    disabled={submitting || !skillInput.trim()}
                    accessibilityLabel={t('addSkill')}
                    accessibilityRole="button"
                  >
                    <Text style={styles.addBtnText}>{t('addSkill')}</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={styles.cancelBtn}
                    onPress={() => {
                      setAddingSkill(false);
                      setSkillInput('');
                    }}
                    hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
                    accessibilityRole="button"
                  >
                    <Ionicons name="close" size={20} color={theme.textSecondary} />
                  </TouchableOpacity>
                </View>
              ) : (
                <TouchableOpacity
                  style={[styles.addSkillButton, { borderColor: primary }]}
                  onPress={() => {
                    setAddingSkill(true);
                    setTimeout(() => skillInputRef.current?.focus(), 50);
                  }}
                  accessibilityLabel={t('addSkill')}
                  accessibilityRole="button"
                >
                  <Ionicons name="add-circle-outline" size={18} color={primary} />
                  <Text style={[styles.addSkillButtonText, { color: primary }]}>
                    {t('addSkill')}
                  </Text>
                </TouchableOpacity>
              )}
            </View>
          }
          ListEmptyComponent={
            <EmptyState
              icon="construct-outline"
              title={t('noSkills')}
            />
          }
        />
      ) : (
        <FlatList
          data={endorsements}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderEndorsement}
          contentContainerStyle={styles.listContent}
          ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => void handleRefresh()}
              tintColor={primary}
            />
          }
          ListEmptyComponent={
            <EmptyState
              icon="ribbon-outline"
              title={t('noEndorsements')}
            />
          }
        />
      )}
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    tabRow: {
      flexDirection: 'row',
      gap: SPACING.sm,
      padding: SPACING.md,
      paddingBottom: SPACING.sm,
    },
    tabPill: {
      flex: 1,
      borderRadius: RADIUS.xl,
      paddingVertical: SPACING.sm,
      alignItems: 'center',
      backgroundColor: theme.surface,
      borderWidth: 1,
      borderColor: theme.border,
    },
    tabLabel: {
      ...TYPOGRAPHY.label,
      fontWeight: '600',
      color: theme.textSecondary,
    },
    tabLabelActive: {
      color: '#fff', // contrast on primary
    },
    listContent: {
      padding: SPACING.md,
      paddingTop: SPACING.sm,
      paddingBottom: SPACING.xxl,
    },
    addSkillHeader: {
      marginBottom: SPACING.sm + 4,
    },
    addSkillButton: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: SPACING.sm - 2,
      borderWidth: 1,
      borderRadius: RADIUS.md,
      paddingVertical: SPACING.sm + 2,
      paddingHorizontal: RADIUS.lg,
      alignSelf: 'flex-start',
    },
    addSkillButtonText: {
      ...TYPOGRAPHY.label,
      fontWeight: '600',
    },
    addSkillForm: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: SPACING.sm,
    },
    skillInput: {
      flex: 1,
      borderWidth: 1,
      borderRadius: RADIUS.md,
      paddingHorizontal: SPACING.sm + 4,
      paddingVertical: 9,
      ...TYPOGRAPHY.label,
      color: theme.text,
      backgroundColor: theme.surface,
    },
    addBtn: {
      borderRadius: RADIUS.md,
      paddingHorizontal: RADIUS.lg,
      paddingVertical: 9,
    },
    addBtnText: {
      ...TYPOGRAPHY.label,
      fontWeight: '600',
      color: '#fff', // contrast on primary
    },
    cancelBtn: {
      padding: SPACING.xs,
    },
    skillRow: {
      flexDirection: 'row',
      alignItems: 'center',
      backgroundColor: theme.surface,
      borderRadius: SPACING.sm + 4,
      padding: RADIUS.lg,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
    },
    skillInfo: {
      flex: 1,
      gap: SPACING.xxs,
    },
    skillName: {
      ...TYPOGRAPHY.body,
      fontWeight: '600',
      color: theme.text,
    },
    skillCategory: {
      ...TYPOGRAPHY.caption,
      color: theme.textSecondary,
    },
    endorseCount: {
      ...TYPOGRAPHY.caption,
      color: theme.textSecondary,
      marginTop: SPACING.xxs,
    },
    removeBtn: {
      padding: SPACING.sm - 2,
    },
    endorsementCard: {
      flexDirection: 'row',
      gap: SPACING.sm + 4,
      backgroundColor: theme.surface,
      borderRadius: SPACING.sm + 4,
      padding: RADIUS.lg,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
    },
    endorsementBody: {
      flex: 1,
      gap: SPACING.xs,
    },
    endorserName: {
      ...TYPOGRAPHY.label,
      fontWeight: '600',
      color: theme.text,
    },
    skillBadge: {
      alignSelf: 'flex-start',
      borderRadius: RADIUS.sm,
      paddingHorizontal: SPACING.sm,
      paddingVertical: SPACING.xxs,
    },
    skillBadgeText: {
      ...TYPOGRAPHY.caption,
      fontWeight: '600',
    },
    endorsementMessage: {
      ...TYPOGRAPHY.bodySmall,
      color: theme.textSecondary,
    },
    endorsementDate: {
      fontSize: 11,
      color: theme.textMuted,
    },
    // emptyText removed — now handled by EmptyState component
  });
}
