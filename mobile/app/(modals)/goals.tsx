// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import {
  View,
  Text,
  FlatList,
  Pressable,
  TextInput,
  Alert,
  KeyboardAvoidingView,
  Platform,
  RefreshControl,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';
import { Spinner } from 'heroui-native';

import {
  getGoals,
  createGoal,
  updateGoalStatus,
  type Goal,
} from '@/lib/api/goals';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

// ─── Goal Card ────────────────────────────────────────────────────────────────

function GoalCard({
  goal,
  primary,
  theme,
  t,
  onComplete,
  onAbandon,
}: {
  goal: Goal;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string, opts?: Record<string, unknown>) => string;
  onComplete: (id: number) => void;
  onAbandon: (id: number) => void;
}) {
  const statusColor =
    goal.status === 'active' ? primary :
    goal.status === 'completed' ? theme.success :
    theme.textMuted;

  const progressPercent =
    goal.target_hours && goal.target_hours > 0
      ? Math.min(100, Math.round((goal.progress_hours / goal.target_hours) * 100))
      : 0;

  const dueDateStr = goal.due_date
    ? new Date(goal.due_date).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
    : null;

  function handleAbandon() {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    Alert.alert(
      t('goals:abandonTitle'),
      t('goals:abandonMessage'),
      [
        { text: t('common:buttons.cancel'), style: 'cancel' },
        { text: t('goals:abandon'), style: 'destructive', onPress: () => void onAbandon(goal.id) },
      ],
    );
  }

  return (
    <View
      className="bg-surface rounded-xl px-4 py-3 border border-border/50"
      accessible={true}
      accessibilityLabel={goal.title}
    >
      <View className="flex-row items-start justify-between gap-2 mb-2">
        <Text className="flex-1 text-sm font-semibold text-foreground" numberOfLines={2}>{goal.title}</Text>
        <View
          className="border rounded px-2 py-0.5"
          style={{ backgroundColor: statusColor + '20', borderColor: statusColor }}
        >
          <Text className="text-[11px] font-semibold" style={{ color: statusColor }}>
            {t(`goals:status.${goal.status}`)}
          </Text>
        </View>
      </View>

      {/* Progress */}
      <View className="mb-2">
        <View className="h-1.5 rounded-full bg-border/50 overflow-hidden mb-1">
          <View
            className="h-1.5 rounded-full"
            style={{
              width: `${progressPercent}%`,
              backgroundColor: goal.status === 'completed' ? theme.success : primary,
            }}
          />
        </View>
        <Text className="text-xs text-muted-foreground">
          {goal.target_hours
            ? t('goals:progress', { current: goal.progress_hours, target: goal.target_hours })
            : t('goals:noTarget', { current: goal.progress_hours })}
        </Text>
      </View>

      {/* Due date */}
      {dueDateStr && (
        <View className="flex-row items-center gap-1 mb-2">
          <Ionicons name="calendar-outline" size={13} color={theme.textMuted} />
          <Text className="text-xs text-muted-foreground">{t('goals:due', { date: dueDateStr })}</Text>
        </View>
      )}

      {/* Actions for active goals only */}
      {goal.status === 'active' && (
        <View className="flex-row gap-2 mt-1">
          <Pressable
            className="flex-1 flex-row items-center justify-center gap-1 border rounded-lg py-1.5"
            style={{ borderColor: theme.success }}
            onPress={() => { void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light); onComplete(goal.id); }}
            hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
          >
            <Ionicons name="checkmark-outline" size={14} color={theme.success} />
            <Text className="text-xs font-semibold" style={{ color: theme.success }}>{t('goals:complete')}</Text>
          </Pressable>
          <Pressable
            className="flex-1 flex-row items-center justify-center gap-1 border rounded-lg py-1.5"
            style={{ borderColor: theme.textMuted }}
            onPress={handleAbandon}
            hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
          >
            <Ionicons name="close-outline" size={14} color={theme.textMuted} />
            <Text className="text-xs font-semibold" style={{ color: theme.textMuted }}>{t('goals:abandon')}</Text>
          </Pressable>
        </View>
      )}
    </View>
  );
}

// ─── Create Goal Form ─────────────────────────────────────────────────────────

function CreateGoalForm({
  primary,
  theme,
  t,
  onCreated,
  onCancel,
}: {
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string) => string;
  onCreated: (goal: Goal) => void;
  onCancel: () => void;
}) {
  const [title, setTitle] = useState('');
  const [targetHours, setTargetHours] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit() {
    const trimmed = title.trim();
    if (!trimmed) return;
    setSubmitting(true);
    try {
      const parsed = targetHours.trim() !== '' ? parseFloat(targetHours) : undefined;
      const result = await createGoal({
        title: trimmed,
        ...(parsed !== undefined && !isNaN(parsed) ? { target_hours: parsed } : {}),
      });
      onCreated(result.data);
    } catch {
      Alert.alert(t('common:errors.alertTitle'), t('goals:create.error'));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <View className="bg-surface rounded-xl p-4 mb-4 border border-border">
      <Text className="text-base font-bold text-foreground mb-3">{t('goals:create.title')}</Text>

      <Text className="text-xs font-semibold text-muted-foreground mb-1">{t('goals:create.titleLabel')}</Text>
      <TextInput
        className="border rounded-lg px-3 py-2 text-sm bg-background mb-3"
        style={{ borderColor: theme.border, color: theme.text, backgroundColor: theme.bg }}
        placeholder={t('goals:create.titlePlaceholder')}
        placeholderTextColor={theme.textMuted}
        value={title}
        onChangeText={setTitle}
        returnKeyType="next"
        autoFocus
      />

      <Text className="text-xs font-semibold text-muted-foreground mb-1">{t('goals:create.targetHoursLabel')}</Text>
      <TextInput
        className="border rounded-lg px-3 py-2 text-sm bg-background mb-3"
        style={{ borderColor: theme.border, color: theme.text, backgroundColor: theme.bg }}
        placeholder="e.g. 10"
        placeholderTextColor={theme.textMuted}
        value={targetHours}
        onChangeText={setTargetHours}
        keyboardType="decimal-pad"
        returnKeyType="done"
      />

      <View className="flex-row gap-2 mt-1">
        <Pressable
          className="flex-1 items-center py-3 rounded-lg border border-border"
          onPress={onCancel}
        >
          <Text className="text-xs font-semibold text-muted-foreground">
            {t('common:cancel')}
          </Text>
        </Pressable>
        <Pressable
          className="flex-[2] items-center justify-center py-3 rounded-lg"
          style={{ backgroundColor: primary, opacity: submitting || !title.trim() ? 0.6 : 1 }}
          onPress={() => void handleSubmit()}
          disabled={submitting || !title.trim()}
        >
          {submitting ? (
            <Spinner size="sm" />
          ) : (
            <Text className="text-xs font-bold text-white">{t('goals:create.submit')}</Text>
          )}
        </Pressable>
      </View>
    </View>
  );
}

// ─── Screen ───────────────────────────────────────────────────────────────────

export default function GoalsScreen() {
  const { t } = useTranslation(['goals', 'common']);
  const navigation = useNavigation();
  const primary = usePrimaryColor();
  const theme = useTheme();

  const [showForm, setShowForm] = useState(false);
  const [goals, setGoals] = useState<Goal[]>([]);
  const [initialized, setInitialized] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    navigation.setOptions({
      title: t('goals:title'),
      headerRight: () => (
        <Pressable
          onPress={() => setShowForm((v) => !v)}
          style={{ marginRight: 16 }}
          accessibilityLabel={t('goals:addGoal')}
        >
          <Ionicons name={showForm ? 'close-outline' : 'add-outline'} size={24} color={primary} />
        </Pressable>
      ),
    });
  }, [navigation, t, primary, showForm]);

  const { data, isLoading, error, refresh } = useApi(() => getGoals(null), []);

  useEffect(() => {
    if (data) {
      setGoals(data.data);
      setInitialized(true);
      setRefreshing(false);
    }
  }, [data]);

  function handleRefresh() {
    setRefreshing(true);
    refresh();
  }

  async function handleUpdateStatus(id: number, status: 'completed' | 'abandoned') {
    try {
      const result = await updateGoalStatus(id, status);
      setGoals((prev) => prev.map((g) => (g.id === id ? result.data : g)));
    } catch {
      Alert.alert(t('common:errors.alertTitle'), t('goals:updateError'));
    }
  }

  function handleGoalCreated(goal: Goal) {
    setGoals((prev) => [goal, ...prev]);
    setShowForm(false);
  }

  if (isLoading && !initialized) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center bg-background">
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  return (
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-background">
      <KeyboardAvoidingView
        className="flex-1"
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <FlatList<Goal>
          data={goals}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => void handleRefresh()}
              tintColor={primary}
            />
          }
          ListHeaderComponent={
            showForm ? (
              <CreateGoalForm
                primary={primary}
                theme={theme}
                t={t}
                onCreated={handleGoalCreated}
                onCancel={() => setShowForm(false)}
              />
            ) : null
          }
          ListEmptyComponent={
            !isLoading && !showForm ? (
              <View className="pt-10 items-center">
                {error ? (
                  <Text className="text-xs text-danger text-center">{error}</Text>
                ) : (
                  <EmptyState
                    icon="flag-outline"
                    title={t('goals:noGoals')}
                    subtitle={t('goals:noGoalsHint')}
                    actionLabel={t('goals:addGoal')}
                    onAction={() => setShowForm(true)}
                  />
                )}
              </View>
            ) : null
          }
          renderItem={({ item }) => (
            <GoalCard
              goal={item}
              primary={primary}
              theme={theme}
              t={t}
              onComplete={(id) => void handleUpdateStatus(id, 'completed')}
              onAbandon={(id) => void handleUpdateStatus(id, 'abandoned')}
            />
          )}
          ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
        />
      </KeyboardAvoidingView>
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}
