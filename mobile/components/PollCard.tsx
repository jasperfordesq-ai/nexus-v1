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

  const [poll, setPoll] = useState<PollData>(pollData);
  const [isVoting, setIsVoting] = useState(false);

  // Keep local poll in sync if parent updates pollData prop
  useEffect(() => {
    setPoll(pollData);
  }, [pollData]);

  const hasVoted = poll.user_vote_option_id !== null;
  const showResults = hasVoted || !poll.is_active;

  const handleVote = useCallback(async (optionId: number) => {
    if (isVoting || hasVoted || !poll.is_active) return;

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
          theme={theme}
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
  theme: ReturnType<typeof useTheme>;
  onPress: () => void;
  disabled: boolean;
}

function PollOptionRow({ option, showResults, isUserVote, primary, theme, onPress, disabled }: PollOptionRowProps) {
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
          resultStyles(theme, primary).optionContainer,
          isUserVote && resultStyles(theme, primary).optionContainerSelected,
        ]}
      >
        <Animated.View
          style={[
            resultStyles(theme, primary).fillBar,
            isUserVote && resultStyles(theme, primary).fillBarSelected,
            { width: fillWidth },
          ]}
        />
        <View style={resultStyles(theme, primary).optionContent}>
          <Text style={[
            resultStyles(theme, primary).optionText,
            isUserVote && resultStyles(theme, primary).optionTextSelected,
          ]}>
            {option.text}
          </Text>
          <Text style={[
            resultStyles(theme, primary).percentageText,
            isUserVote && resultStyles(theme, primary).percentageTextSelected,
          ]}>
            {option.percentage}%
          </Text>
        </View>
      </View>
    );
  }

  return (
    <TouchableOpacity
      style={votableStyles(theme).optionContainer}
      onPress={onPress}
      disabled={disabled}
      activeOpacity={0.7}
      accessibilityRole="button"
      accessibilityLabel={option.text}
    >
      <Text style={votableStyles(theme).optionText}>{option.text}</Text>
    </TouchableOpacity>
  );
}

function makeStyles(theme: Theme, primary: string) {
  return StyleSheet.create({
    container: { gap: 8 },
    question: { fontSize: 15, fontWeight: '600', color: theme.text },
    footer: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 2 },
    totalVotes: { fontSize: 13, color: theme.textMuted },
    votedBadge: { flexDirection: 'row', alignItems: 'center', gap: 3 },
    votedText: { fontSize: 12, fontWeight: '600' },
    closedText: { fontSize: 12, color: theme.textMuted, fontStyle: 'italic' },
  });
}

function resultStyles(theme: Theme, primary: string) {
  return StyleSheet.create({
    optionContainer: {
      borderRadius: 10,
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
      borderRadius: 9,
    },
    fillBarSelected: {
      backgroundColor: primary + '20',
    },
    optionContent: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'center',
      paddingHorizontal: 14,
      paddingVertical: 10,
    },
    optionText: {
      fontSize: 14,
      color: theme.text,
      flex: 1,
    },
    optionTextSelected: {
      fontWeight: '600',
      color: primary,
    },
    percentageText: {
      fontSize: 13,
      fontWeight: '600',
      color: theme.textSecondary,
      marginLeft: 8,
    },
    percentageTextSelected: {
      color: primary,
    },
  });
}

function votableStyles(theme: Theme) {
  return StyleSheet.create({
    optionContainer: {
      borderRadius: 10,
      borderWidth: 1,
      borderColor: theme.border,
      paddingHorizontal: 14,
      paddingVertical: 10,
      minHeight: 44,
      justifyContent: 'center',
    },
    optionText: {
      fontSize: 14,
      color: theme.text,
    },
  });
}
