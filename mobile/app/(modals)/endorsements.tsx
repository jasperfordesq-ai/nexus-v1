// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Alert,
  FlatList,
  RefreshControl,
  Text,
  TextInput,
  Pressable,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
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
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
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
        <View className="flex-row items-center bg-surface rounded-xl px-4 py-3 border border-border/50">
          <View className="flex-1 gap-0.5">
            <Text className="text-sm font-semibold text-foreground">{item.name}</Text>
            {item.category ? (
              <Text className="text-xs text-muted-foreground">{item.category}</Text>
            ) : null}
            {endorseCount > 0 ? (
              <Text className="text-xs text-muted-foreground mt-0.5">
                {t('endorsedBy', { count: endorseCount })}
              </Text>
            ) : null}
          </View>
          <Pressable
            onPress={() => void handleRemoveSkill(item.id)}
            className="p-1.5"
            hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
            accessibilityLabel={t('removeSkill')}
            accessibilityRole="button"
          >
            <Ionicons name="trash-outline" size={18} color={theme.error} />
          </Pressable>
        </View>
      );
    },
    [endorsements, t, theme.error],
  );

  const renderEndorsement = useCallback(
    ({ item }: { item: Endorsement }) => {
      const date = new Date(item.created_at).toLocaleDateString('default', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
      return (
        <View className="flex-row gap-3 bg-surface rounded-xl px-4 py-3 border border-border/50">
          <Avatar uri={item.endorsed_by.avatar} name={item.endorsed_by.name} size={40} />
          <View className="flex-1 gap-1">
            <Text className="text-xs font-semibold text-foreground">{item.endorsed_by.name}</Text>
            <View
              className="self-start rounded px-2 py-0.5"
              style={{ backgroundColor: withAlpha(primary, 0.13) }}
            >
              <Text className="text-xs font-semibold" style={{ color: primary }}>{item.skill.name}</Text>
            </View>
            {item.message ? (
              <Text className="text-xs text-muted-foreground">{item.message}</Text>
            ) : null}
            <Text className="text-[11px] text-muted-foreground">{date}</Text>
          </View>
        </View>
      );
    },
    [primary],
  );

  const isLoading = activeTab === 'skills' ? skillsLoading : endorsementsLoading;

  return (
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-background">
      {/* Tab toggle */}
      <View className="flex-row gap-2 px-4 pt-4 pb-2">
        <Pressable
          className={`flex-1 rounded-full py-2 items-center border border-border ${activeTab === 'skills' ? '' : 'bg-surface'}`}
          style={activeTab === 'skills' ? { backgroundColor: primary } : undefined}
          onPress={() => { void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light); setActiveTab('skills'); }}
          accessibilityRole="tab"
          accessibilityState={{ selected: activeTab === 'skills' }}
        >
          <Text className={`text-xs font-semibold ${activeTab === 'skills' ? 'text-white' : 'text-muted-foreground'}`}>
            {t('mySkills')}
          </Text>
        </Pressable>
        <Pressable
          className={`flex-1 rounded-full py-2 items-center border border-border ${activeTab === 'endorsements' ? '' : 'bg-surface'}`}
          style={activeTab === 'endorsements' ? { backgroundColor: primary } : undefined}
          onPress={() => { void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light); setActiveTab('endorsements'); }}
          accessibilityRole="tab"
          accessibilityState={{ selected: activeTab === 'endorsements' }}
        >
          <Text className={`text-xs font-semibold ${activeTab === 'endorsements' ? 'text-white' : 'text-muted-foreground'}`}>
            {t('endorsements')}
          </Text>
        </Pressable>
      </View>

      {isLoading ? (
        <View className="flex-1 items-center justify-center">
          <LoadingSpinner />
        </View>
      ) : activeTab === 'skills' ? (
        <FlatList
          data={skills}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderSkill}
          contentContainerStyle={{ padding: 16, paddingTop: 8, paddingBottom: 40 }}
          ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => void handleRefresh()}
              tintColor={primary}
            />
          }
          ListHeaderComponent={
            <View className="mb-3">
              {addingSkill ? (
                <View className="flex-row items-center gap-2">
                  <TextInput
                    ref={skillInputRef}
                    className="flex-1 border rounded-lg px-3 py-2 text-xs bg-surface text-foreground"
                    style={{ borderColor: primary, color: theme.text, backgroundColor: theme.surface }}
                    placeholder={t('skillPlaceholder')}
                    placeholderTextColor={theme.textMuted}
                    value={skillInput}
                    onChangeText={setSkillInput}
                    autoFocus
                    returnKeyType="done"
                    onSubmitEditing={() => void handleAddSkill()}
                  />
                  <Pressable
                    className="rounded-lg px-4 py-2"
                    style={{ backgroundColor: primary }}
                    onPress={() => void handleAddSkill()}
                    disabled={submitting || !skillInput.trim()}
                    accessibilityLabel={t('addSkill')}
                    accessibilityRole="button"
                  >
                    <Text className="text-xs font-semibold text-white">{t('addSkill')}</Text>
                  </Pressable>
                  <Pressable
                    className="p-1"
                    onPress={() => {
                      setAddingSkill(false);
                      setSkillInput('');
                    }}
                    hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
                    accessibilityRole="button"
                  >
                    <Ionicons name="close" size={20} color={theme.textSecondary} />
                  </Pressable>
                </View>
              ) : (
                <Pressable
                  className="flex-row items-center gap-1.5 border rounded-lg py-2.5 px-4 self-start"
                  style={{ borderColor: primary }}
                  onPress={() => {
                    setAddingSkill(true);
                    setTimeout(() => skillInputRef.current?.focus(), 50);
                  }}
                  accessibilityLabel={t('addSkill')}
                  accessibilityRole="button"
                >
                  <Ionicons name="add-circle-outline" size={18} color={primary} />
                  <Text className="text-xs font-semibold" style={{ color: primary }}>
                    {t('addSkill')}
                  </Text>
                </Pressable>
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
          contentContainerStyle={{ padding: 16, paddingTop: 8, paddingBottom: 40 }}
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
