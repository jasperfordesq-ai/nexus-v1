// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useMemo, useCallback, useRef, useEffect } from 'react';
import { View, Text, TouchableOpacity, StyleSheet, Animated } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import { voteFeedPoll, type PollData } from '@/lib/api/feed';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';

interface PollCardProps {
  pollData: PollData;
  itemId: number;
  onVoted?: (updated: PollData) => void;
}

export default function PollCard({ pollData, itemId, onVoted }: PollCardProps) {
  const { t } = useTranslation('home');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme, primary), [theme, primary]);
  const rStyles = useMemo(() => makeResultStyles(theme, primary), [theme, primary]);
  const vStyles = useMemo(() => makeVotableStyles(theme), [theme]);

  const safePollData = pollData && pollData.options ? pollData : null;
  const [poll, setPoll] = useState<PollData | null>(safePollData);
  const [isVoting, setIsVoting] = useState(false);

  // Keep local poll in sync if parent updates pollData prop
  useEffect(() => {
    if (pollData && pollData.options) {
      setPoll(pollData);
    }
  }, [pollData]);

  const hasVoted = poll ? poll.user_vote_option_id !== null : false;
  const showResults = hasVoted || (poll ? !poll.is_active : false);

  const handleVote = useCallback(async (optionId: number) => {
    if (!poll || isVoting || hasVoted || !poll.is_active) return;

    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setIsVoting(true);

    // Optimistic update
    const previousPoll = poll;
    const newTotalVotes = poll.total_votes + 1;
    const optimisticOptions = poll.options.map((opt) => {
      const newCount = opt.id === optionId ? opt.vote_count + 1 : opt.vote_count;
      return {
        ...opt,
        vote_count: newCount,
        percentage: newTotalVotes > 0 ? Math.round((newCount / newTotalVotes) * 100) : 0,
      };
    });
    const optimisticPoll: PollData = {
      ...poll,
      options: optimisticOptions,
      total_votes: newTotalVotes,
      user_vote_option_id: optionId,
    };
    setPoll(optimisticPoll);

    try {
      const result = await voteFeedPoll(itemId, optionId);
      setPoll(result.data);
      onVoted?.(result.data);
    } catch {
      // Revert on error
      setPoll(previousPoll);
    } finally {
      setIsVoting(false);
    }
  }, [isVoting, hasVoted, poll, itemId, onVoted]);

  if (!poll || !poll.options?.length) return null;

  return (
    <View style={styles.container}>
      <Text style={styles.question}>{poll.question}</Text>

      {poll.options.map((option) => (
        <PollOptionRow
          key={option.id}
          option={option}
          showResults={showResults}
          isUserVote={poll.user_vote_option_id === option.id}
          primary={primary}
          rStyles={rStyles}
          vStyles={vStyles}
          onPress={() => handleVote(option.id)}
          disabled={isVoting || hasVoted || !poll.is_active}
        />
      ))}

      <View style={styles.footer}>
        <Text style={styles.totalVotes}>
          {t('poll.totalVotes', { count: poll.total_votes })}
        </Text>
        {hasVoted && (
          <View style={styles.votedBadge}>
            <Ionicons name="checkmark-circle" size={14} color={primary} />
            <Text style={[styles.votedText, { color: primary }]}>{t('poll.voted')}</Text>
          </View>
        )}
        {!poll.is_active && (
          <Text style={styles.closedText}>{t('poll.closed')}</Text>
        )}
      </View>
    </View>
  );
}

interface PollOptionRowProps {
  option: { id: number; text: string; vote_count: number; percentage: number };
  showResults: boolean;
  isUserVote: boolean;
  primary: string;
  rStyles: ReturnType<typeof makeResultStyles>;
  vStyles: ReturnType<typeof makeVotableStyles>;
  onPress: () => void;
  disabled: boolean;
}

function PollOptionRow({ option, showResults, isUserVote, rStyles, vStyles, onPress, disabled }: PollOptionRowProps) {
  const fillAnim = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    if (showResults) {
      Animated.timing(fillAnim, {
        toValue: option.percentage,
        duration: 500,
        useNativeDriver: false,
      }).start();
    } else {
      fillAnim.setValue(0);
    }
  }, [showResults, option.percentage, fillAnim]);

  const fillWidth = fillAnim.interpolate({
    inputRange: [0, 100],
    outputRange: ['0%', '100%'],
  });

  if (showResults) {
    return (
      <View
        style={[
          rStyles.optionContainer,
          isUserVote && rStyles.optionContainerSelected,
        ]}
      >
        <Animated.View
          style={[
            rStyles.fillBar,
            isUserVote && rStyles.fillBarSelected,
            { width: fillWidth },
          ]}
        />
        <View style={rStyles.optionContent}>
          <Text style={[
            rStyles.optionText,
            isUserVote && rStyles.optionTextSelected,
          ]}>
            {option.text}
          </Text>
          <Text style={[
            rStyles.percentageText,
            isUserVote && rStyles.percentageTextSelected,
          ]}>
            {option.percentage}%
          </Text>
        </View>
      </View>
    );
  }

  return (
    <TouchableOpacity
      style={vStyles.optionContainer}
      onPress={onPress}
      disabled={disabled}
      activeOpacity={0.7}
      accessibilityRole="button"
      accessibilityLabel={option.text}
    >
      <Text style={vStyles.optionText}>{option.text}</Text>
    </TouchableOpacity>
  );
}

function makeStyles(theme: Theme, primary: string) {
  return StyleSheet.create({
    container: { gap: SPACING.sm },
    question: { ...TYPOGRAPHY.body, fontWeight: '600', color: theme.text },
    footer: { flexDirection: 'row', alignItems: 'center', gap: SPACING.sm, marginTop: SPACING.xxs },
    totalVotes: { ...TYPOGRAPHY.bodySmall, color: theme.textMuted },
    votedBadge: { flexDirection: 'row', alignItems: 'center', gap: 3 },
    votedText: { ...TYPOGRAPHY.caption, fontWeight: '600' },
    closedText: { ...TYPOGRAPHY.caption, color: theme.textMuted, fontStyle: 'italic' },
  });
}

function makeResultStyles(theme: Theme, primary: string) {
  return StyleSheet.create({
    optionContainer: {
      borderRadius: RADIUS.md,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
      overflow: 'hidden',
      minHeight: 44,
      justifyContent: 'center',
    },
    optionContainerSelected: {
      borderColor: primary,
    },
    fillBar: {
      position: 'absolute',
      top: 0,
      left: 0,
      bottom: 0,
      backgroundColor: theme.borderSubtle,
      borderRadius: RADIUS.md - 1,
    },
    fillBarSelected: {
      backgroundColor: withAlpha(primary, 0.13),
    },
    optionContent: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'center',
      paddingHorizontal: SPACING.lg - 10,
      paddingVertical: SPACING.sm + 2,
    },
    optionText: {
      ...TYPOGRAPHY.label,
      color: theme.text,
      flex: 1,
    },
    optionTextSelected: {
      fontWeight: '600',
      color: primary,
    },
    percentageText: {
      ...TYPOGRAPHY.bodySmall,
      fontWeight: '600',
      color: theme.textSecondary,
      marginLeft: SPACING.sm,
    },
    percentageTextSelected: {
      color: primary,
    },
  });
}

function makeVotableStyles(theme: Theme) {
  return StyleSheet.create({
    optionContainer: {
      borderRadius: RADIUS.md,
      borderWidth: 1,
      borderColor: theme.border,
      paddingHorizontal: SPACING.lg - 10,
      paddingVertical: SPACING.sm + 2,
      minHeight: 44,
      justifyContent: 'center',
    },
    optionText: {
      ...TYPOGRAPHY.label,
      color: theme.text,
    },
  });
}
