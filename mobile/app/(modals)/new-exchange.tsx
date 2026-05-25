// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useRef } from 'react';
import {
  View,
  Text,
  TextInput,
  Pressable,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useNavigation } from 'expo-router';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';
import { Spinner } from 'heroui-native';

import { createExchange, type ExchangeType } from '@/lib/api/exchanges';
import { ApiResponseError, api } from '@/lib/api/client';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import { useApi } from '@/lib/hooks/useApi';
import { API_V2 } from '@/lib/constants';
import OfflineBanner from '@/components/OfflineBanner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

interface Category {
  id: number;
  name: string;
}

function getCategories(): Promise<{ data: Category[] }> {
  return api.get<{ data: Category[] }>(`${API_V2}/categories`, { type: 'listing' });
}

interface FieldErrors {
  title?: string;
  timeCredits?: string;
}

export default function NewExchangeModal() {
  const { t } = useTranslation('exchanges');
  const navigation = useNavigation();
  const primary = usePrimaryColor();
  const theme = useTheme();

  useEffect(() => {
    navigation.setOptions({ title: t('newExchange') });
  }, [navigation, t]);

  const { data: catData } = useApi(() => getCategories());
  const categories = catData?.data ?? [];

  const [type, setType] = useState<ExchangeType>('offer');
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [timeCredits, setTimeCredits] = useState('1');
  const [categoryId, setCategoryId] = useState<number | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});

  // Refs for returnKeyType focus chaining
  const descriptionRef = useRef<TextInput>(null);
  const creditsRef = useRef<TextInput>(null);

  async function handleSubmit() {
    const errors: FieldErrors = {};

    if (!title.trim()) {
      errors.title = t('validation.titleRequired');
    }

    const credits = parseFloat(timeCredits);
    if (isNaN(credits) || credits <= 0) {
      errors.timeCredits = t('validation.invalidCredits');
    }

    if (Object.keys(errors).length > 0) {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      setFieldErrors(errors);
      return;
    }

    setFieldErrors({});
    setSubmitting(true);
    setError(null);
    try {
      await createExchange({
        title: title.trim(),
        description: description.trim(),
        type,
        hours_estimate: credits,
        category_id: categoryId ?? (categories[0]?.id ?? 1),
      });
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      router.back();
    } catch (err) {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      setError(err instanceof ApiResponseError ? err.message : t('createError'));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-surface" edges={['bottom']}>
    <KeyboardAvoidingView
      style={{ flex: 1 }}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView contentContainerStyle={{ padding: 24 }} keyboardShouldPersistTaps="handled">
        <OfflineBanner />
        {/* Type toggle */}
        <Text className="text-sm font-semibold text-foreground mb-1.5 mt-4">{t('type')}</Text>
        <View className="flex-row gap-2">
          {(['offer', 'request'] as ExchangeType[]).map((tp) => (
            <Pressable
              key={tp}
              style={[
                { flex: 1, borderWidth: 1, borderColor: theme.border, borderRadius: 8, paddingVertical: 10, alignItems: 'center' },
                type === tp && { backgroundColor: primary, borderColor: primary },
              ]}
              onPress={() => setType(tp)}
              accessibilityLabel={t('typeLabel', { type: t(tp) })}
              accessibilityRole="button"
            >
              <Text style={[{ fontSize: 14, fontWeight: '600', color: theme.textSecondary }, type === tp && { color: '#fff' }]}>
                {t(tp)}
              </Text>
            </Pressable>
          ))}
        </View>

        {error && (
          <View className="bg-danger/10 rounded-lg p-3 mt-3">
            <Text className="text-danger text-sm">{error}</Text>
          </View>
        )}

        <Text className="text-sm font-semibold text-foreground mb-1.5 mt-4">{t('titleLabel')}</Text>
        <TextInput
          style={[
            { borderWidth: 1, borderColor: fieldErrors.title ? theme.error : theme.border, borderRadius: 10, paddingHorizontal: 14, paddingVertical: 12, fontSize: 16, color: theme.text, backgroundColor: theme.surface },
          ]}
          value={title}
          onChangeText={(v) => {
            setTitle(v);
            if (fieldErrors.title) setFieldErrors((e) => ({ ...e, title: undefined }));
          }}
          placeholder={type === 'offer' ? t('offerPlaceholder') : t('requestPlaceholder')}
          placeholderTextColor={theme.textMuted}
          returnKeyType="next"
          maxLength={100}
          onSubmitEditing={() => descriptionRef.current?.focus()}
          blurOnSubmit={false}
        />
        {fieldErrors.title && (
          <Text className="text-xs text-danger mt-1">{fieldErrors.title}</Text>
        )}

        <Text className="text-sm font-semibold text-foreground mb-1.5 mt-4">{t('description')}</Text>
        <TextInput
          ref={descriptionRef}
          style={{ borderWidth: 1, borderColor: theme.border, borderRadius: 10, paddingHorizontal: 14, paddingVertical: 12, fontSize: 16, color: theme.text, backgroundColor: theme.surface, height: 100 }}
          value={description}
          onChangeText={setDescription}
          placeholder={t('descriptionPlaceholder')}
          placeholderTextColor={theme.textMuted}
          multiline
          numberOfLines={4}
          textAlignVertical="top"
          maxLength={500}
          returnKeyType="next"
          blurOnSubmit={true}
          onSubmitEditing={() => creditsRef.current?.focus()}
        />

        <Text className="text-sm font-semibold text-foreground mb-1.5 mt-4">{t('timeCredits')}</Text>
        <TextInput
          ref={creditsRef}
          style={[
            { borderWidth: 1, borderColor: fieldErrors.timeCredits ? theme.error : theme.border, borderRadius: 10, paddingHorizontal: 14, paddingVertical: 12, fontSize: 16, color: theme.text, backgroundColor: theme.surface },
          ]}
          value={timeCredits}
          onChangeText={(v) => {
            setTimeCredits(v);
            if (fieldErrors.timeCredits) setFieldErrors((e) => ({ ...e, timeCredits: undefined }));
          }}
          placeholder="1"
          placeholderTextColor={theme.textMuted}
          keyboardType="decimal-pad"
          returnKeyType="done"
        />
        {fieldErrors.timeCredits && (
          <Text className="text-xs text-danger mt-1">{fieldErrors.timeCredits}</Text>
        )}

        {/* Category picker */}
        {categories.length > 0 && (
          <>
            <Text className="text-sm font-semibold text-foreground mb-1.5 mt-4">{t('category')}</Text>
            <View className="flex-row flex-wrap gap-2">
              {categories.map((cat) => {
                const selected = categoryId === cat.id || (categoryId === null && categories[0]?.id === cat.id);
                return (
                  <Pressable
                    key={cat.id}
                    style={[
                      { borderWidth: 1, borderColor: theme.border, borderRadius: 20, paddingHorizontal: 14, paddingVertical: 7, backgroundColor: theme.surface },
                      selected && { backgroundColor: withAlpha(primary, 0.09), borderColor: primary },
                    ]}
                    onPress={() => setCategoryId(cat.id)}
                    accessibilityLabel={t('categoryLabel', { name: cat.name })}
                    accessibilityRole="button"
                  >
                    <Text style={[{ fontSize: 13, color: theme.textMuted }, selected && { color: primary, fontWeight: '600' }]}>
                      {cat.name}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          </>
        )}

        <Pressable
          style={[{ borderRadius: 10, paddingVertical: 14, alignItems: 'center', marginTop: 32, backgroundColor: primary }, submitting && { opacity: 0.6 }]}
          onPress={() => void handleSubmit()}
          disabled={submitting}
          accessibilityLabel={type === 'offer' ? t('postOffer') : t('postRequest')}
          accessibilityRole="button"
        >
          {submitting ? (
            <Spinner size="sm" />
          ) : (
            <Text style={{ color: '#fff', fontSize: 16, fontWeight: '600' }}>
              {type === 'offer' ? t('postOffer') : t('postRequest')}
            </Text>
          )}
        </Pressable>
      </ScrollView>
    </KeyboardAvoidingView>
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}
