// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useRef, useState } from 'react';
import {
  Dimensions,
  FlatList,
  Image,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
  type ViewToken,
} from 'react-native';
import { router } from 'expo-router';

interface CarouselImage {
  uri: string;
  alt?: string;
}

interface ImageCarouselProps {
  images: CarouselImage[];
  height?: number;
  onImagePress?: (index: number) => void;
}

const screenWidth = Dimensions.get('window').width;
const HORIZONTAL_MARGIN = 16; // matches FeedItem wrapper marginHorizontal
const CARD_PADDING = 16; // matches Card padding
const IMAGE_WIDTH = screenWidth - HORIZONTAL_MARGIN * 2 - CARD_PADDING * 2;

export default function ImageCarousel({
  images,
  height = 250,
  onImagePress,
}: ImageCarouselProps) {
  const [activeIndex, setActiveIndex] = useState(0);

  const onViewableItemsChanged = useRef(
    ({ viewableItems }: { viewableItems: ViewToken[] }) => {
      if (viewableItems.length > 0 && viewableItems[0].index != null) {
        setActiveIndex(viewableItems[0].index);
      }
    },
  ).current;

  const viewabilityConfig = useRef({ viewAreaCoveragePercentThreshold: 50 }).current;

  const handleImagePress = useCallback(
    (index: number) => {
      if (onImagePress) {
        onImagePress(index);
      } else {
        router.push({
          pathname: '/(modals)/image-viewer',
          params: { uri: images[index].uri, title: images[index].alt ?? '' },
        });
      }
    },
    [onImagePress, images],
  );

  const renderItem = useCallback(
    ({ item, index }: { item: CarouselImage; index: number }) => (
      <TouchableOpacity
        activeOpacity={0.9}
        onPress={() => handleImagePress(index)}
        accessibilityLabel={item.alt ?? `Image ${index + 1} of ${images.length}`}
        accessibilityRole="imagebutton"
      >
        <Image
          source={{ uri: item.uri }}
          style={{ width: IMAGE_WIDTH, height, borderRadius: 10 }}
          resizeMode="cover"
        />
      </TouchableOpacity>
    ),
    [handleImagePress, height, images.length],
  );

  const keyExtractor = useCallback(
    (_: CarouselImage, index: number) => `carousel-${index}`,
    [],
  );

  return (
    <View style={styles.container}>
      <FlatList
        data={images}
        renderItem={renderItem}
        keyExtractor={keyExtractor}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onViewableItemsChanged={onViewableItemsChanged}
        viewabilityConfig={viewabilityConfig}
        snapToInterval={IMAGE_WIDTH}
        decelerationRate="fast"
        getItemLayout={(_, index) => ({
          length: IMAGE_WIDTH,
          offset: IMAGE_WIDTH * index,
          index,
        })}
      />

      {/* Image count badge */}
      <View style={styles.countBadge}>
        <Text style={styles.countBadgeText}>
          {activeIndex + 1}/{images.length}
        </Text>
      </View>

      {/* Page indicator dots */}
      {images.length > 1 && (
        <View style={styles.dotsContainer}>
          {images.map((_, index) => (
            <View
              key={index}
              style={[
                styles.dot,
                index === activeIndex ? styles.dotActive : styles.dotInactive,
              ]}
            />
          ))}
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    position: 'relative',
  },
  countBadge: {
    position: 'absolute',
    top: 8,
    right: 8,
    backgroundColor: 'rgba(0, 0, 0, 0.6)',
    borderRadius: 10,
    paddingHorizontal: 8,
    paddingVertical: 3,
  },
  countBadgeText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '600',
  },
  dotsContainer: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    position: 'absolute',
    bottom: 8,
    left: 0,
    right: 0,
    gap: 6,
  },
  dot: {
    width: 6,
    height: 6,
    borderRadius: 3,
  },
  dotActive: {
    backgroundColor: 'rgba(255, 255, 255, 1)',
  },
  dotInactive: {
    backgroundColor: 'rgba(255, 255, 255, 0.5)',
  },
});
