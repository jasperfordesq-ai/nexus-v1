// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useRef, useState, type RefObject } from 'react';
import {
  Alert,
  FlatList,
  RefreshControl,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { Button as HeroButton, Card as HeroCard, Chip, Surface, Tabs } from 'heroui-native';
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
import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

type Tab = 'skills' | 'endorsements';
type ListItem = Skill | Endorsement;

export default function EndorsementsScreen() {
  const { t } = useTranslation(['endorsements', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const { user } = useAuth();

  const [activeTab, setActiveTab] = useState<Tab>('skills');
  const [addingSkill, setAddingSkill] = useState(false);
  const [skillInput, setSkillInput] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const skillInputRef = useRef<TextInput>(null);

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
      Alert.alert(t('addSkillErrorTitle'), t('addSkillError'));
    } finally {
      setSubmitting(false);
    }
  }

  async function handleRemoveSkill(skillId: number) {
    Alert.alert(t('removeSkillTitle'), t('removeSkillConfirm'), [
      { text: t('common:cancel'), style: 'cancel' },
      {
        text: t('removeSkill'),
        style: 'destructive',
        onPress: async () => {
          void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
          try {
            await removeSkill(skillId);
            void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
            Alert.alert(t('skillRemovedTitle'), t('skillRemoved'));
            refreshSkills();
          } catch {
            Alert.alert(t('removeSkillErrorTitle'), t('removeSkillError'));
          }
        },
      },
    ]);
  }

  const renderSkill = useCallback(
    ({ item }: { item: Skill }) => {
      const endorseCount = endorsements.filter((e) => e.skill.id === item.id).length;
      return (
        <HeroCard variant="default" className="mx-4 my-2 overflow-hidden rounded-panel p-0">
          <View className="h-1 w-full" style={{ backgroundColor: primary }} />
          <HeroCard.Body className="flex-row items-center gap-3 p-4">
            <View className="size-11 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
              <Ionicons name="construct-outline" size={20} color={primary} />
            </View>
            <View className="min-w-0 flex-1 gap-1">
              <Text className="text-base font-semibold" style={{ color: theme.text }} numberOfLines={2}>{item.name}</Text>
            {item.category ? (
                <Text className="text-xs" style={{ color: theme.textSecondary }}>{item.category}</Text>
            ) : null}
            {endorseCount > 0 ? (
                <Chip size="sm" variant="soft" color="success" className="self-start">
                  <Ionicons name="ribbon-outline" size={12} color={theme.success} />
                  <Chip.Label>{t('endorsedBy', { count: endorseCount })}</Chip.Label>
                </Chip>
            ) : null}
            </View>
            <HeroButton
              isIconOnly
              variant="danger-soft"
            onPress={() => void handleRemoveSkill(item.id)}
            accessibilityLabel={t('removeSkill')}
          >
            <Ionicons name="trash-outline" size={18} color={theme.error} />
            </HeroButton>
          </HeroCard.Body>
        </HeroCard>
      );
    },
    [endorsements, primary, t, theme.error, theme.success, theme.text, theme.textSecondary],
  );

  const renderEndorsement = useCallback(
    ({ item }: { item: Endorsement }) => {
      const date = new Date(item.created_at).toLocaleDateString('default', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
      return (
        <HeroCard variant="default" className="mx-4 my-2 rounded-panel p-0">
          <HeroCard.Body className="flex-row gap-3 p-4">
            <Avatar uri={item.endorsed_by.avatar} name={item.endorsed_by.name} size={44} />
            <View className="min-w-0 flex-1 gap-2">
              <View className="flex-row items-start justify-between gap-2">
                <View className="min-w-0 flex-1">
                  <Text className="text-sm font-semibold" style={{ color: theme.text }} numberOfLines={1}>{item.endorsed_by.name}</Text>
                  <Text className="text-[11px]" style={{ color: theme.textMuted }}>{date}</Text>
                </View>
                <Chip size="sm" variant="secondary">
                  <Ionicons name="ribbon-outline" size={12} color={primary} />
                  <Chip.Label>{item.skill.name}</Chip.Label>
                </Chip>
              </View>
              {item.message ? (
                <Surface variant="secondary" className="rounded-panel-inner px-3 py-2">
                  <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{item.message}</Text>
                </Surface>
              ) : null}
            </View>
          </HeroCard.Body>
        </HeroCard>
      );
    },
    [primary, theme.text, theme.textMuted, theme.textSecondary],
  );

  const isLoading = activeTab === 'skills' ? skillsLoading : endorsementsLoading;
  const listData: ListItem[] = activeTab === 'skills' ? skills : endorsements;

  return (
    <ModalErrorBoundary>
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('title')} backLabel={t('common:back')} fallbackHref="/(tabs)/profile" />
        <FlatList<ListItem>
          data={listData}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => (
            activeTab === 'skills'
              ? renderSkill({ item: item as unknown as Skill })
              : renderEndorsement({ item: item as Endorsement })
          )}
          contentContainerStyle={{ paddingBottom: 40 }}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => void handleRefresh()}
              tintColor={primary}
              colors={[primary]}
            />
          }
          ListHeaderComponent={
            <EndorsementsHeader
              activeTab={activeTab}
              setActiveTab={setActiveTab}
              addingSkill={addingSkill}
              setAddingSkill={setAddingSkill}
              skillInput={skillInput}
              setSkillInput={setSkillInput}
              skillInputRef={skillInputRef}
              submitting={submitting}
              handleAddSkill={handleAddSkill}
              skillsCount={skills.length}
              endorsementsCount={endorsements.length}
              primary={primary}
              theme={theme}
              t={t}
            />
          }
          ListEmptyComponent={
            isLoading ? (
              <View className="items-center justify-center py-14">
                <LoadingSpinner />
              </View>
            ) : (
              <View className="px-4 py-8">
                <EmptyState
                  icon={activeTab === 'skills' ? 'construct-outline' : 'ribbon-outline'}
                  title={activeTab === 'skills' ? t('noSkills') : t('noEndorsements')}
                  subtitle={activeTab === 'skills' ? t('noSkillsHint') : t('noEndorsementsHint')}
                />
              </View>
            )
          }
        />
      </SafeAreaView>
    </ModalErrorBoundary>
  );
}

function EndorsementsHeader({
  activeTab,
  setActiveTab,
  addingSkill,
  setAddingSkill,
  skillInput,
  setSkillInput,
  skillInputRef,
  submitting,
  handleAddSkill,
  skillsCount,
  endorsementsCount,
  primary,
  theme,
  t,
}: {
  activeTab: Tab;
  setActiveTab: (tab: Tab) => void;
  addingSkill: boolean;
  setAddingSkill: (value: boolean) => void;
  skillInput: string;
  setSkillInput: (value: string) => void;
  skillInputRef: RefObject<TextInput | null>;
  submitting: boolean;
  handleAddSkill: () => Promise<void>;
  skillsCount: number;
  endorsementsCount: number;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string, options?: Record<string, unknown>) => string;
}) {
  function selectTab(tab: Tab) {
    if (tab !== activeTab) {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      setActiveTab(tab);
    }
  }

  return (
    <View className="gap-3 pb-2">
      <HeroCard variant="default" className="mx-4 overflow-hidden rounded-panel p-0">
        <View className="h-1 w-full" style={{ backgroundColor: primary }} />
        <HeroCard.Body className="gap-4 p-4">
          <View className="flex-row items-start gap-3">
            <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
              <Ionicons name="ribbon-outline" size={24} color={primary} />
            </View>
            <View className="min-w-0 flex-1">
              <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('heroEyebrow')}</Text>
              <Text className="mt-1 text-2xl font-bold leading-8" style={{ color: theme.text }}>{t('title')}</Text>
              <Text className="mt-1 text-sm leading-5" style={{ color: theme.textSecondary }}>{t('subtitle')}</Text>
            </View>
          </View>
          <View className="flex-row flex-wrap gap-2">
            <Chip size="sm" variant="secondary">
              <Ionicons name="construct-outline" size={12} color={primary} />
              <Chip.Label>{t('skillsCount', { count: skillsCount })}</Chip.Label>
            </Chip>
            <Chip size="sm" variant="soft" color="success">
              <Ionicons name="ribbon-outline" size={12} color={theme.success} />
              <Chip.Label>{t('endorsementsCount', { count: endorsementsCount })}</Chip.Label>
            </Chip>
          </View>
        </HeroCard.Body>
      </HeroCard>

      <Surface variant="default" className="mx-4 gap-3 rounded-panel-inner p-3">
        <Tabs value={activeTab} onValueChange={(value) => selectTab(value as Tab)} variant="secondary">
          <Tabs.List>
            <Tabs.Indicator />
            <Tabs.Trigger value="skills">
              <Ionicons name="construct-outline" size={15} color={activeTab === 'skills' ? primary : theme.textMuted} />
              <Tabs.Label>{t('mySkills')}</Tabs.Label>
            </Tabs.Trigger>
            <Tabs.Trigger value="endorsements">
              <Ionicons name="ribbon-outline" size={15} color={activeTab === 'endorsements' ? primary : theme.textMuted} />
              <Tabs.Label>{t('endorsements')}</Tabs.Label>
            </Tabs.Trigger>
          </Tabs.List>
        </Tabs>
        <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>
          {activeTab === 'skills' ? t('skillsIntro') : t('endorsementsIntro')}
        </Text>
      </Surface>

      {activeTab === 'skills' ? (
        <Surface variant="default" className="mx-4 gap-3 rounded-panel-inner p-3">
          {addingSkill ? (
            <>
              <View className="flex-row items-center gap-2 rounded-panel-inner border px-3 py-2" style={{ borderColor: withAlpha(primary, 0.34), backgroundColor: theme.surface }}>
                <Ionicons name="add-circle-outline" size={18} color={primary} />
                <TextInput
                  ref={skillInputRef}
                  className="min-h-10 flex-1 text-sm"
                  style={{ color: theme.text }}
                  placeholder={t('skillPlaceholder')}
                  placeholderTextColor={theme.textMuted}
                  value={skillInput}
                  onChangeText={setSkillInput}
                  autoFocus
                  returnKeyType="done"
                  onSubmitEditing={() => void handleAddSkill()}
                />
              </View>
              <View className="flex-row gap-2">
                <HeroButton className="flex-1" variant="secondary" onPress={() => { setAddingSkill(false); setSkillInput(''); }}>
                  <HeroButton.Label>{t('common:cancel')}</HeroButton.Label>
                </HeroButton>
                <HeroButton
                  className="flex-1"
                  variant="primary"
                  isDisabled={submitting || !skillInput.trim()}
                  style={{ backgroundColor: submitting || !skillInput.trim() ? theme.border : primary }}
                  onPress={() => void handleAddSkill()}
                  accessibilityLabel={t('addSkill')}
                >
                  <Ionicons name="checkmark-outline" size={18} color={theme.onPrimary} />
                  <HeroButton.Label>{t('addSkill')}</HeroButton.Label>
                </HeroButton>
              </View>
            </>
          ) : (
            <HeroButton
              variant="primary"
              style={{ backgroundColor: primary }}
              onPress={() => {
                setAddingSkill(true);
                setTimeout(() => skillInputRef.current?.focus(), 50);
              }}
              accessibilityLabel={t('addSkill')}
            >
              <Ionicons name="add-circle-outline" size={18} color={theme.onPrimary} />
              <HeroButton.Label>{t('addSkill')}</HeroButton.Label>
            </HeroButton>
          )}
        </Surface>
      ) : null}
    </View>
  );
}
