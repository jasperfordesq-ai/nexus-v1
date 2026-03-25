// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useMemo } from 'react';
import {
  Image,
  ScrollView,
  Share,
  StatusBar,
  StyleSheet,
  TouchableOpacity,
  View,
} from 'react-native';
import { useLocalSearchParams, router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';

import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { SPACING, RADIUS } from '@/lib/styles/spacing';

export default function ImageViewerScreen() {
  const { t } = useTranslation('home');
  const { uri, title } = useLocalSearchParams<{ uri: string; title?: string }>();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  async function handleShare() {
    if (!uri) return;
    const message = title ? `${title}\n${uri}` : uri;
    await Share.share({ message, url: uri });
  }

  function handleClose() {
    router.back();
  }

  if (!uri) {
    handleClose();
    return null;
  }

  return (
    <View style={styles.container}>
      <StatusBar barStyle="light-content" />
      <SafeAreaView style={styles.safeArea}>
        {/* Close button */}
        <TouchableOpacity
          style={styles.closeButton}
          onPress={handleClose}
          activeOpacity={0.7}
          accessibilityLabel={t('imageViewer.close')}
          accessibilityRole="button"
        >
          <Ionicons name="close" size={28} color="#FFFFFF" />
        </TouchableOpacity>

        {/* Image with pinch-to-zoom via ScrollView */}
        <ScrollView
          style={styles.scrollView}
          contentContainerStyle={styles.scrollContent}
          maximumZoomScale={3}
          minimumZoomScale={1}
          showsHorizontalScrollIndicator={false}
          showsVerticalScrollIndicator={false}
          bouncesZoom
          centerContent
        >
          <Image
            source={{ uri }}
            style={styles.image}
            resizeMode="contain"
            accessibilityLabel={title ?? t('imageViewer.close')}
          />
        </ScrollView>

        {/* Share button */}
        <TouchableOpacity
          style={styles.shareButton}
          onPress={handleShare}
          activeOpacity={0.7}
          accessibilityLabel={t('imageViewer.share')}
          accessibilityRole="button"
        >
          <Ionicons name="share-outline" size={24} color="#FFFFFF" />
        </TouchableOpacity>
      </SafeAreaView>
    </View>
  );
}

function makeStyles(_theme: Theme) {
  return StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: '#000000',
    },
    safeArea: {
      flex: 1,
    },
    closeButton: {
      position: 'absolute',
      top: SPACING.sm,
      right: SPACING.md,
      zIndex: 10,
      width: 40,
      height: 40,
      borderRadius: RADIUS.full,
      backgroundColor: 'rgba(0,0,0,0.5)',
      alignItems: 'center',
      justifyContent: 'center',
    },
    scrollView: {
      flex: 1,
    },
    scrollContent: {
      flex: 1,
      alignItems: 'center',
      justifyContent: 'center',
    },
    image: {
      width: '100%',
      height: '100%',
    },
    shareButton: {
      alignSelf: 'center',
      marginBottom: SPACING.md,
      width: 48,
      height: 48,
      borderRadius: RADIUS.full,
      backgroundColor: 'rgba(255,255,255,0.2)',
      alignItems: 'center',
      justifyContent: 'center',
    },
  });
}
