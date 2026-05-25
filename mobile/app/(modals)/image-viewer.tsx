// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  Dimensions,
  Pressable,
  ScrollView,
  Share,
  StatusBar,
  View,
} from 'react-native';
import { Image } from 'expo-image';
import { useLocalSearchParams, router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';

export default function ImageViewerScreen() {
  const { t } = useTranslation('home');
  const { uri, title } = useLocalSearchParams<{ uri: string; title?: string }>();

  const { width, height } = Dimensions.get('window');

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
    <View style={{ flex: 1, backgroundColor: '#000' }}>
      <StatusBar barStyle="light-content" />
      <SafeAreaView style={{ flex: 1 }}>
        {/* Close button */}
        <Pressable
          style={{
            position: 'absolute',
            top: 8,
            right: 16,
            zIndex: 10,
            width: 40,
            height: 40,
            borderRadius: 20,
            backgroundColor: 'rgba(0,0,0,0.5)',
            alignItems: 'center',
            justifyContent: 'center',
          }}
          onPress={handleClose}
          accessibilityLabel={t('imageViewer.close')}
          accessibilityRole="button"
        >
          <Ionicons name="close" size={28} color="#FFFFFF" />
        </Pressable>

        {/*
          Pinch-to-zoom via ScrollView:
          - iOS: native pinch gesture via maximumZoomScale + pinchGestureEnabled
          - Android: ScrollView zoom works on both platforms when the content
            has an explicit pixel size driven by Dimensions (not flex/percentage).
            Using flex: 1 or width: '100%' collapses to 0px inside a zoomable
            ScrollView on Android — explicit width/height from Dimensions fixes this.
        */}
        <ScrollView
          style={{ flex: 1 }}
          contentContainerStyle={{
            width,
            height,
            alignItems: 'center',
            justifyContent: 'center',
          }}
          maximumZoomScale={5}
          minimumZoomScale={1}
          pinchGestureEnabled
          showsHorizontalScrollIndicator={false}
          showsVerticalScrollIndicator={false}
          bouncesZoom
          centerContent
        >
          <Image
            source={{ uri }}
            style={{ width, height }}
            contentFit="contain"
            accessibilityLabel={title ?? t('imageViewer.close')}
          />
        </ScrollView>

        {/* Share button */}
        <Pressable
          style={{
            alignSelf: 'center',
            marginBottom: 16,
            width: 48,
            height: 48,
            borderRadius: 24,
            backgroundColor: 'rgba(255,255,255,0.2)',
            alignItems: 'center',
            justifyContent: 'center',
          }}
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
