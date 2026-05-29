// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useCallback, useRef, useEffect } from 'react';
import { View, Text, Animated } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton } from 'heroui-native';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import { voteFeedPoll, type PollData } from '@/lib/api/feed';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { withAlpha } from '@/lib/utils/color';

interface PollCardProps {
  pollData: PollData;
  itemId: number;
  onVoted?: (updated: PollData) => void;
}

export default function PollCard({ pollData, itemId, onVoted }: PollCardProps) {
  const { t } = useTranslation('home');
  const primary = usePrimaryColor();

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
    <View className="gap-2">
      <Text className="text-base font-semibold text-foreground">{poll.question}</Text>

      {poll.options.map((option) => (
        <PollOptionRow
          key={option.id}
          option={option}
          showResults={showResults}
          isUserVote={poll.user_vote_option_id === option.id}
          primary={primary}
          onPress={() => handleVote(option.id)}
          disabled={isVoting || hasVoted || !poll.is_active}
        />
      ))}

      <View className="flex-row items-center gap-2 mt-0.5">
        <Text className="text-sm text-muted-foreground">
          {t('poll.totalVotes', { count: poll.total_votes })}
        </Text>
        {hasVoted && (
          <View className="flex-row items-center gap-[3px]">
            <Ionicons name="checkmark-circle" size={14} color={primary} />
            <Text className="text-xs font-semibold" style={{ color: primary }}>{t('poll.voted')}</Text>
          </View>
        )}
        {!poll.is_active && (
          <Text className="text-xs italic text-muted-foreground">{t('poll.closed')}</Text>
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
  onPress: () => void;
  disabled: boolean;
}

function PollOptionRow({ option, showResults, isUserVote, primary, onPress, disabled }: PollOptionRowProps) {
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
        className="rounded-xl overflow-hidden min-h-[44px] justify-center"
        style={{
          borderWidth: 1,
          borderColor: isUserVote ? primary : 'rgba(128,128,128,0.2)',
        }}
      >
        <Animated.View
          style={{
            position: 'absolute',
            top: 0,
            left: 0,
            bottom: 0,
            width: fillWidth,
            backgroundColor: isUserVote ? withAlpha(primary, 0.13) : 'rgba(128,128,128,0.1)',
            borderRadius: 11,
          }}
        />
        <View className="flex-row justify-between items-center px-3 py-2.5">
          <Text
            className="flex-1 text-sm"
            style={{ color: isUserVote ? primary : undefined, fontWeight: isUserVote ? '600' : '400' }}
          >
            {option.text}
          </Text>
          <Text
            className="text-sm font-semibold ml-2"
            style={{ color: isUserVote ? primary : undefined }}
          >
            {option.percentage}%
          </Text>
        </View>
      </View>
    );
  }

  return (
    <HeroButton
      variant="outline"
      className="min-h-[44px] w-full justify-center rounded-xl"
      onPress={onPress}
      isDisabled={disabled}
      accessibilityRole="button"
      accessibilityLabel={option.text}
    >
      <HeroButton.Label>{option.text}</HeroButton.Label>
    </HeroButton>
  );
}
