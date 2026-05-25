// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  Image,
  Pressable,
  ScrollView,
  Share,
  StatusBar,
  View,
} from 'react-native';
import { useLocalSearchParams, router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';

export default function ImageViewerScreen() {
  const { t } = useTranslation('home');
  const { uri, title } = useLocalSearchParams<{ uri: string; title?: string }>();

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
    <View className="flex-1 bg-black">
      <StatusBar barStyle="light-content" />
      <SafeAreaView className="flex-1">
        {/* Close button */}
        <Pressable
          className="absolute top-2 right-4 z-10 w-10 h-10 rounded-full bg-black/50 items-center justify-center"
          onPress={handleClose}
          accessibilityLabel={t('imageViewer.close')}
          accessibilityRole="button"
        >
          <Ionicons name="close" size={28} color="#FFFFFF" />
        </Pressable>

        {/* Image with pinch-to-zoom via ScrollView */}
        <ScrollView
          className="flex-1"
          contentContainerStyle={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}
          maximumZoomScale={3}
          minimumZoomScale={1}
          showsHorizontalScrollIndicator={false}
          showsVerticalScrollIndicator={false}
          bouncesZoom
          centerContent
        >
          <Image
            source={{ uri }}
            className="w-full h-full"
            resizeMode="contain"
            accessibilityLabel={title ?? t('imageViewer.close')}
          />
        </ScrollView>

        {/* Share button */}
        <Pressable
          className="self-center mb-4 w-12 h-12 rounded-full bg-white/20 items-center justify-center"
          onPress={handleShare}
          accessibilityLabel={t('imageViewer.share')}
          accessibilityRole="button"
        >
          <Ionicons name="share-outline" size={24} color="#FFFFFF" />
        </Pressable>
      </SafeAreaView>
    </View>
  );
}
