// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Alert,
  FlatList,
  RefreshControl,
  SafeAreaView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
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
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';

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
            <View style={[styles.skillBadge, { backgroundColor: primary + '20' }]}>
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
            <Text style={styles.emptyText}>{t('noSkills')}</Text>
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
            <Text style={styles.emptyText}>{t('noEndorsements')}</Text>
          }
        />
      )}
    </SafeAreaView>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    tabRow: {
      flexDirection: 'row',
      gap: 8,
      padding: 16,
      paddingBottom: 8,
    },
    tabPill: {
      flex: 1,
      borderRadius: 20,
      paddingVertical: 8,
      alignItems: 'center',
      backgroundColor: theme.surface,
      borderWidth: 1,
      borderColor: theme.border,
    },
    tabLabel: {
      fontSize: 14,
      fontWeight: '600',
      color: theme.textSecondary,
    },
    tabLabelActive: {
      color: '#fff', // contrast on primary
    },
    listContent: {
      padding: 16,
      paddingTop: 8,
      paddingBottom: 48,
    },
    addSkillHeader: {
      marginBottom: 12,
    },
    addSkillButton: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 6,
      borderWidth: 1,
      borderRadius: 10,
      paddingVertical: 10,
      paddingHorizontal: 14,
      alignSelf: 'flex-start',
    },
    addSkillButtonText: {
      fontSize: 14,
      fontWeight: '600',
    },
    addSkillForm: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 8,
    },
    skillInput: {
      flex: 1,
      borderWidth: 1,
      borderRadius: 10,
      paddingHorizontal: 12,
      paddingVertical: 9,
      fontSize: 14,
      color: theme.text,
      backgroundColor: theme.surface,
    },
    addBtn: {
      borderRadius: 10,
      paddingHorizontal: 14,
      paddingVertical: 9,
    },
    addBtnText: {
      fontSize: 14,
      fontWeight: '600',
      color: '#fff', // contrast on primary
    },
    cancelBtn: {
      padding: 4,
    },
    skillRow: {
      flexDirection: 'row',
      alignItems: 'center',
      backgroundColor: theme.surface,
      borderRadius: 12,
      padding: 14,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
    },
    skillInfo: {
      flex: 1,
      gap: 2,
    },
    skillName: {
      fontSize: 15,
      fontWeight: '600',
      color: theme.text,
    },
    skillCategory: {
      fontSize: 12,
      color: theme.textSecondary,
    },
    endorseCount: {
      fontSize: 12,
      color: theme.textSecondary,
      marginTop: 2,
    },
    removeBtn: {
      padding: 6,
    },
    endorsementCard: {
      flexDirection: 'row',
      gap: 12,
      backgroundColor: theme.surface,
      borderRadius: 12,
      padding: 14,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
    },
    endorsementBody: {
      flex: 1,
      gap: 4,
    },
    endorserName: {
      fontSize: 14,
      fontWeight: '600',
      color: theme.text,
    },
    skillBadge: {
      alignSelf: 'flex-start',
      borderRadius: 6,
      paddingHorizontal: 8,
      paddingVertical: 2,
    },
    skillBadgeText: {
      fontSize: 12,
      fontWeight: '600',
    },
    endorsementMessage: {
      fontSize: 13,
      color: theme.textSecondary,
      lineHeight: 18,
    },
    endorsementDate: {
      fontSize: 11,
      color: theme.textMuted,
    },
    emptyText: {
      fontSize: 14,
      color: theme.textMuted,
      textAlign: 'center',
      marginTop: 32,
    },
  });
}
