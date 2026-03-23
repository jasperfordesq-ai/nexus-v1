// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useMemo, useEffect, useRef } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
  SafeAreaView,
  StyleSheet,
} from 'react-native';
import { router, useNavigation } from 'expo-router';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import { createExchange, type ExchangeType } from '@/lib/api/exchanges';
import { ApiResponseError } from '@/lib/api/client';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { useApi } from '@/lib/hooks/useApi';
import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';
import OfflineBanner from '@/components/OfflineBanner';

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
  const styles = useMemo(() => makeStyles(theme), [theme]);

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
    <SafeAreaView style={styles.flex}>
    <KeyboardAvoidingView
      style={{ flex: 1 }}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled">
        <OfflineBanner />
        {/* Type toggle */}
        <Text style={styles.label}>{t('type')}</Text>
        <View style={styles.toggle}>
          {(['offer', 'request'] as ExchangeType[]).map((tp) => (
            <TouchableOpacity
              key={tp}
              style={[
                styles.toggleOption,
                type === tp && { backgroundColor: primary, borderColor: primary },
              ]}
              onPress={() => setType(tp)}
              accessibilityLabel={t('typeLabel', { type: t(tp) })}
              accessibilityRole="button"
            >
              <Text style={[styles.toggleText, type === tp && styles.toggleTextActive]}>
                {t(tp)}
              </Text>
            </TouchableOpacity>
          ))}
        </View>

        {error && (
          <View style={styles.errorBanner}>
            <Text style={styles.errorBannerText}>{error}</Text>
          </View>
        )}

        <Text style={styles.label}>{t('titleLabel')}</Text>
        <TextInput
          style={[styles.input, !!fieldErrors.title && styles.inputError]}
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
          <Text style={styles.fieldError}>{fieldErrors.title}</Text>
        )}

        <Text style={styles.label}>{t('description')}</Text>
        <TextInput
          ref={descriptionRef}
          style={[styles.input, styles.textarea]}
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

        <Text style={styles.label}>{t('timeCredits')}</Text>
        <TextInput
          ref={creditsRef}
          style={[styles.input, !!fieldErrors.timeCredits && styles.inputError]}
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
          <Text style={styles.fieldError}>{fieldErrors.timeCredits}</Text>
        )}

        {/* Category picker */}
        {categories.length > 0 && (
          <>
            <Text style={styles.label}>{t('category')}</Text>
            <View style={styles.categoryGrid}>
              {categories.map((cat) => {
                const selected = categoryId === cat.id || (categoryId === null && categories[0]?.id === cat.id);
                return (
                  <TouchableOpacity
                    key={cat.id}
                    style={[
                      styles.categoryChip,
                      selected && { backgroundColor: primary + '18', borderColor: primary },
                    ]}
                    onPress={() => setCategoryId(cat.id)}
                    activeOpacity={0.7}
                    accessibilityLabel={t('categoryLabel', { name: cat.name })}
                    accessibilityRole="button"
                  >
                    <Text style={[styles.categoryChipText, selected && { color: primary, fontWeight: '600' }]}>
                      {cat.name}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </View>
          </>
        )}

        <TouchableOpacity
          style={[styles.button, { backgroundColor: primary }, submitting && styles.buttonDisabled]}
          onPress={() => void handleSubmit()}
          disabled={submitting}
          activeOpacity={0.8}
          accessibilityLabel={type === 'offer' ? t('postOffer') : t('postRequest')}
          accessibilityRole="button"
        >
          {submitting ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.buttonText}>
              {type === 'offer' ? t('postOffer') : t('postRequest')}
            </Text>
          )}
        </TouchableOpacity>
      </ScrollView>
    </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    flex: { flex: 1, backgroundColor: theme.surface },
    container: { padding: 24 },
    label: { fontSize: 14, fontWeight: '600', color: theme.text, marginBottom: 6, marginTop: 16 },
    toggle: { flexDirection: 'row', gap: 8 },
    toggleOption: {
      flex: 1,
      borderWidth: 1,
      borderColor: theme.border,
      borderRadius: 8,
      paddingVertical: 10,
      alignItems: 'center',
    },
    toggleText: { fontSize: 14, fontWeight: '600', color: theme.textSecondary },
    toggleTextActive: { color: '#fff' }, // contrast on primary
    input: {
      borderWidth: 1,
      borderColor: theme.border,
      borderRadius: 10,
      paddingHorizontal: 14,
      paddingVertical: 12,
      fontSize: 16,
      color: theme.text,
      backgroundColor: theme.surface,
    },
    inputError: { borderColor: theme.error },
    textarea: { height: 100 },
    fieldError: { fontSize: 12, color: theme.error, marginTop: 4 },
    categoryGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
    categoryChip: {
      borderWidth: 1,
      borderColor: theme.border,
      borderRadius: 20,
      paddingHorizontal: 14,
      paddingVertical: 7,
      backgroundColor: theme.surface,
    },
    categoryChipText: { fontSize: 13, color: theme.textMuted },
    errorBanner: { backgroundColor: theme.errorBg, borderRadius: 8, padding: 12, marginTop: 12 },
    errorBannerText: { color: theme.error, fontSize: 14 },
    button: { borderRadius: 10, paddingVertical: 14, alignItems: 'center', marginTop: 32 },
    buttonDisabled: { opacity: 0.6 },
    buttonText: { color: '#fff', fontSize: 16, fontWeight: '600' }, // contrast on primary
  });
}
