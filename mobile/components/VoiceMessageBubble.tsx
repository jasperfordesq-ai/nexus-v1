// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useRef, useCallback } from 'react';
import { View, Text, TouchableOpacity, StyleSheet, ActivityIndicator } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Audio, type AVPlaybackStatus } from 'expo-av';

import { useTheme } from '@/lib/hooks/useTheme';

interface VoiceMessageBubbleProps {
  audioUrl: string;
  durationMs?: number;
  isOwn: boolean;
  primaryColor: string;
  textColor: string;
  textColorSecondary: string;
}

function formatDuration(ms: number): string {
  const totalSeconds = Math.floor(ms / 1000);
  const m = Math.floor(totalSeconds / 60);
  const s = totalSeconds % 60;
  return `${m}:${s.toString().padStart(2, '0')}`;
}

export default function VoiceMessageBubble({
  audioUrl,
  durationMs,
  isOwn,
  primaryColor,
  textColor,
  textColorSecondary,
}: VoiceMessageBubbleProps) {
  const soundRef = useRef<Audio.Sound | null>(null);
  const [isPlaying, setIsPlaying] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [hasError, setHasError] = useState(false);
  const [positionMs, setPositionMs] = useState(0);
  const [totalMs, setTotalMs] = useState(durationMs ?? 0);

  // Unload sound on unmount to free resources
  useEffect(() => {
    return () => {
      soundRef.current?.unloadAsync().catch(() => null);
    };
  }, []);

  const onPlaybackStatusUpdate = useCallback((status: AVPlaybackStatus) => {
    if (!status.isLoaded) return;
    setPositionMs(status.positionMillis);
    if (status.durationMillis) setTotalMs(status.durationMillis);
    if (status.didJustFinish) {
      setIsPlaying(false);
      setPositionMs(0);
    }
  }, []);

  const handlePlayPause = useCallback(async () => {
    setHasError(false);
    try {
      if (soundRef.current) {
        if (isPlaying) {
          await soundRef.current.pauseAsync();
          setIsPlaying(false);
        } else {
          await soundRef.current.playAsync();
          setIsPlaying(true);
        }
        return;
      }

      // First play — load the sound
      setIsLoading(true);
      await Audio.setAudioModeAsync({ playsInSilentModeIOS: true });
      const { sound } = await Audio.Sound.createAsync(
        { uri: audioUrl },
        { shouldPlay: true },
        onPlaybackStatusUpdate,
      );
      soundRef.current = sound;
      setIsPlaying(true);
    } catch {
      setHasError(true);
      setIsPlaying(false);
    } finally {
      setIsLoading(false);
    }
  }, [audioUrl, isPlaying, onPlaybackStatusUpdate]);

  const theme = useTheme();

  const iconColor = isOwn ? 'rgba(255,255,255,0.95)' : primaryColor;
  const timeColor = isOwn ? 'rgba(255,255,255,0.8)' : textColorSecondary;
  const labelColor = isOwn ? '#fff' : textColor;
  const unfilledBarColor = isOwn ? 'rgba(255,255,255,0.35)' : theme.border;

  const progress = totalMs > 0 ? positionMs / totalMs : 0;
  const displayTime = isPlaying || positionMs > 0
    ? formatDuration(totalMs - positionMs)
    : totalMs > 0 ? formatDuration(totalMs) : '0:00';

  return (
    <View style={styles.row}>
      <TouchableOpacity
        onPress={handlePlayPause}
        disabled={isLoading}
        style={[styles.playButton, { borderColor: iconColor }]}
        accessibilityLabel={isPlaying ? 'Pause voice message' : 'Play voice message'}
      >
        {isLoading ? (
          <ActivityIndicator size="small" color={iconColor} />
        ) : (
          <Ionicons
            name={isPlaying ? 'pause' : 'play'}
            size={18}
            color={iconColor}
          />
        )}
      </TouchableOpacity>

      <View style={styles.waveContainer}>
        {/* Simple waveform bar visualization */}
        {Array.from({ length: 20 }).map((_, i) => {
          const barHeight = 6 + ((i % 5) * 3) + (i % 3 === 0 ? 4 : 0);
          const filled = i / 20 <= progress;
          return (
            <View
              key={i}
              style={[
                styles.waveBar,
                {
                  height: barHeight,
                  backgroundColor: filled
                    ? (isOwn ? 'rgba(255,255,255,0.95)' : primaryColor)
                    : unfilledBarColor,
                },
              ]}
            />
          );
        })}
      </View>

      <Text style={[styles.duration, { color: timeColor }]}>{displayTime}</Text>
      <Text style={[styles.label, { color: hasError ? '#DC2626' : labelColor }]}>
        {hasError ? 'Failed' : 'Voice'}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    minWidth: 180,
  },
  playButton: {
    width: 32,
    height: 32,
    borderRadius: 16,
    borderWidth: 1.5,
    alignItems: 'center',
    justifyContent: 'center',
  },
  waveContainer: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 2,
  },
  waveBar: {
    flex: 1,
    borderRadius: 2,
  },
  duration: {
    fontSize: 11,
    fontVariant: ['tabular-nums'],
    minWidth: 30,
    textAlign: 'right',
  },
  label: {
    fontSize: 11,
    opacity: 0.7,
  },
});
