// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useMemo, useState } from 'react';
import {
  View,
  Text,
  FlatList,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  Alert,
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  RefreshControl,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';
import {
  getGoals,
  createGoal,
  updateGoalStatus,
  type Goal,
} from '@/lib/api/goals';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

// ─── Goal Card ────────────────────────────────────────────────────────────────

function GoalCard({
  goal,
  primary,
  theme,
  styles,
  t,
  onComplete,
  onAbandon,
}: {
  goal: Goal;
  primary: string;
  theme: Theme;
  styles: ReturnType<typeof makeStyles>;
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
      style={styles.goalCard}
      accessible={true}
      accessibilityLabel={goal.title}
    >
      <View style={styles.goalCardHeader}>
        <Text style={styles.goalTitle} numberOfLines={2}>{goal.title}</Text>
        <View style={[styles.statusBadge, { backgroundColor: statusColor + '20', borderColor: statusColor }]}>
          <Text style={[styles.statusText, { color: statusColor }]}>
            {t(`goals:status.${goal.status}`)}
          </Text>
        </View>
      </View>

      {/* Progress */}
      <View style={styles.progressRow}>
        <View style={styles.progressTrack}>
          <View
            style={[
              styles.progressFill,
              {
                width: `${progressPercent}%` as `${number}%`,
                backgroundColor: goal.status === 'completed' ? theme.success : primary,
              },
            ]}
          />
        </View>
        <Text style={styles.progressLabel}>
          {goal.target_hours
            ? t('goals:progress', { current: goal.progress_hours, target: goal.target_hours })
            : t('goals:noTarget', { current: goal.progress_hours })}
        </Text>
      </View>

      {/* Due date */}
      {dueDateStr && (
        <View style={styles.dueDateRow}>
          <Ionicons name="calendar-outline" size={13} color={theme.textMuted} />
          <Text style={styles.dueDateText}>{t('goals:due', { date: dueDateStr })}</Text>
        </View>
      )}

      {/* Actions for active goals only */}
      {goal.status === 'active' && (
        <View style={styles.actionRow}>
          <TouchableOpacity
            style={[styles.actionBtn, { borderColor: theme.success }]}
            onPress={() => { void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light); onComplete(goal.id); }}
            activeOpacity={0.8}
            hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
          >
            <Ionicons name="checkmark-outline" size={14} color={theme.success} />
            <Text style={[styles.actionBtnText, { color: theme.success }]}>{t('goals:complete')}</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.actionBtn, { borderColor: theme.textMuted }]}
            onPress={handleAbandon}
            activeOpacity={0.8}
            hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
          >
            <Ionicons name="close-outline" size={14} color={theme.textMuted} />
            <Text style={[styles.actionBtnText, { color: theme.textMuted }]}>{t('goals:abandon')}</Text>
          </TouchableOpacity>
        </View>
      )}
    </View>
  );
}

// ─── Create Goal Form ─────────────────────────────────────────────────────────

function CreateGoalForm({
  primary,
  theme,
  styles,
  t,
  onCreated,
  onCancel,
}: {
  primary: string;
  theme: Theme;
  styles: ReturnType<typeof makeStyles>;
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
    <View style={styles.formCard}>
      <Text style={styles.formTitle}>{t('goals:create.title')}</Text>

      <Text style={styles.formLabel}>{t('goals:create.titleLabel')}</Text>
      <TextInput
        style={[styles.formInput, { borderColor: theme.border, color: theme.text }]}
        placeholder={t('goals:create.titlePlaceholder')}
        placeholderTextColor={theme.textMuted}
        value={title}
        onChangeText={setTitle}
        returnKeyType="next"
        autoFocus
      />

      <Text style={styles.formLabel}>{t('goals:create.targetHoursLabel')}</Text>
      <TextInput
        style={[styles.formInput, { borderColor: theme.border, color: theme.text }]}
        placeholder="e.g. 10"
        placeholderTextColor={theme.textMuted}
        value={targetHours}
        onChangeText={setTargetHours}
        keyboardType="decimal-pad"
        returnKeyType="done"
      />

      <View style={styles.formActions}>
        <TouchableOpacity style={styles.cancelBtn} onPress={onCancel} activeOpacity={0.8}>
          <Text style={[styles.cancelBtnText, { color: theme.textSecondary }]}>
            {t('common:cancel')}
          </Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.submitBtn, { backgroundColor: primary, opacity: submitting || !title.trim() ? 0.6 : 1 }]}
          onPress={() => void handleSubmit()}
          disabled={submitting || !title.trim()}
          activeOpacity={0.8}
        >
          {submitting ? (
            <ActivityIndicator size="small" color="#fff" />
          ) : (
            <Text style={styles.submitBtnText}>{t('goals:create.submit')}</Text>
          )}
        </TouchableOpacity>
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
  const styles = useMemo(() => makeStyles(theme), [theme]);

  const [showForm, setShowForm] = useState(false);
  const [goals, setGoals] = useState<Goal[]>([]);
  const [initialized, setInitialized] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    navigation.setOptions({
      title: t('goals:title'),
      headerRight: () => (
        <TouchableOpacity
          onPress={() => setShowForm((v) => !v)}
          style={{ marginRight: 16 }}
          accessibilityLabel={t('goals:addGoal')}
        >
          <Ionicons name={showForm ? 'close-outline' : 'add-outline'} size={24} color={primary} />
        </TouchableOpacity>
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
      <SafeAreaView style={styles.center}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  return (
    <ModalErrorBoundary>
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <FlatList<Goal>
          data={goals}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={styles.listContent}
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
                styles={styles}
                t={t}
                onCreated={handleGoalCreated}
                onCancel={() => setShowForm(false)}
              />
            ) : null
          }
          ListEmptyComponent={
            !isLoading && !showForm ? (
              <View style={styles.emptyWrap}>
                {error ? (
                  <Text style={styles.errorText}>{error}</Text>
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
              styles={styles}
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

// ─── Styles ───────────────────────────────────────────────────────────────────

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    flex: { flex: 1 },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    listContent: { padding: SPACING.md, paddingBottom: 40 },

    // Goal card
    goalCard: {
      backgroundColor: theme.surface,
      borderRadius: RADIUS.lg,
      padding: RADIUS.lg,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
    },
    goalCardHeader: {
      flexDirection: 'row',
      alignItems: 'flex-start',
      justifyContent: 'space-between',
      gap: SPACING.sm,
      marginBottom: RADIUS.md,
    },
    goalTitle: { flex: 1, ...TYPOGRAPHY.body, fontWeight: '600', color: theme.text },
    statusBadge: {
      borderWidth: 1,
      borderRadius: RADIUS.sm,
      paddingHorizontal: SPACING.sm,
      paddingVertical: 3,
    },
    statusText: { fontSize: 11, fontWeight: '600' },

    // Progress
    progressRow: { marginBottom: SPACING.sm },
    progressTrack: {
      height: 6,
      borderRadius: 3,
      backgroundColor: theme.borderSubtle,
      overflow: 'hidden',
      marginBottom: 4,
    },
    progressFill: { height: 6, borderRadius: 3 },
    progressLabel: { ...TYPOGRAPHY.caption, color: theme.textSecondary },

    // Due date
    dueDateRow: { flexDirection: 'row', alignItems: 'center', gap: 5, marginBottom: SPACING.sm },
    dueDateText: { ...TYPOGRAPHY.caption, color: theme.textMuted },

    // Actions
    actionRow: { flexDirection: 'row', gap: RADIUS.md, marginTop: 4 },
    actionBtn: {
      flex: 1,
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 5,
      borderWidth: 1,
      borderRadius: SPACING.sm,
      paddingVertical: 7,
    },
    actionBtnText: { ...TYPOGRAPHY.bodySmall, fontWeight: '600' },

    // Create form
    formCard: {
      backgroundColor: theme.surface,
      borderRadius: RADIUS.lg,
      padding: SPACING.md,
      marginBottom: SPACING.md,
      borderWidth: 1,
      borderColor: theme.border,
    },
    formTitle: { fontSize: 16, fontWeight: '700', color: theme.text, marginBottom: RADIUS.lg },
    formLabel: { ...TYPOGRAPHY.caption, fontWeight: '600', color: theme.textSecondary, marginBottom: 5 },
    formInput: {
      borderWidth: 1,
      borderRadius: RADIUS.md,
      paddingHorizontal: 12,
      paddingVertical: RADIUS.md,
      fontSize: TYPOGRAPHY.body.fontSize,
      backgroundColor: theme.bg,
      marginBottom: 12,
    },
    formActions: { flexDirection: 'row', gap: RADIUS.md, marginTop: 4 },
    cancelBtn: {
      flex: 1,
      alignItems: 'center',
      paddingVertical: 11,
      borderRadius: RADIUS.md,
      borderWidth: 1,
      borderColor: theme.border,
    },
    cancelBtnText: { ...TYPOGRAPHY.label, fontWeight: '600' },
    submitBtn: {
      flex: 2,
      alignItems: 'center',
      justifyContent: 'center',
      paddingVertical: 11,
      borderRadius: RADIUS.md,
    },
    submitBtnText: { ...TYPOGRAPHY.label, fontWeight: '700', color: '#fff' }, // contrast on primary

    // Empty / error
    emptyWrap: { paddingTop: SPACING.xxl, alignItems: 'center' },
    errorText: { ...TYPOGRAPHY.label, color: theme.error, textAlign: 'center' },
  });
}
