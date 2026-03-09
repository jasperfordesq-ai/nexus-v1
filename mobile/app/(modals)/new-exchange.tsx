// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
  StyleSheet,
} from 'react-native';
import { router } from 'expo-router';

import { createExchange, type ExchangeType } from '@/lib/api/exchanges';
import { ApiResponseError } from '@/lib/api/client';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { useApi } from '@/lib/hooks/useApi';
import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

interface Category {
  id: number;
  name: string;
}

function getCategories(): Promise<{ data: Category[] }> {
  return api.get<{ data: Category[] }>(`${API_V2}/categories`, { type: 'listing' });
}

export default function NewExchangeModal() {
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = makeStyles(theme);
  const { data: catData } = useApi(() => getCategories());
  const categories = catData?.data ?? [];

  const [type, setType] = useState<ExchangeType>('offer');
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [timeCredits, setTimeCredits] = useState('1');
  const [categoryId, setCategoryId] = useState<number | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit() {
    if (!title.trim()) { setError('Please enter a title.'); return; }
    const credits = parseFloat(timeCredits);
    if (isNaN(credits) || credits <= 0) { setError('Please enter a valid number of time credits.'); return; }

    setIsLoading(true);
    setError(null);
    try {
      await createExchange({
        title: title.trim(),
        description: description.trim(),
        type,
        hours_estimate: credits,
        category_id: categoryId ?? (categories[0]?.id ?? 1),
      });
      router.back();
    } catch (err) {
      setError(err instanceof ApiResponseError ? err.message : 'Failed to create exchange.');
    } finally {
      setIsLoading(false);
    }
  }

  return (
    <KeyboardAvoidingView
      style={styles.flex}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled">
        {/* Type toggle */}
        <Text style={styles.label}>Type</Text>
        <View style={styles.toggle}>
          {(['offer', 'request'] as ExchangeType[]).map((t) => (
            <TouchableOpacity
              key={t}
              style={[
                styles.toggleOption,
                type === t && { backgroundColor: primary, borderColor: primary },
              ]}
              onPress={() => setType(t)}
            >
              <Text style={[styles.toggleText, type === t && styles.toggleTextActive]}>
                {t.charAt(0).toUpperCase() + t.slice(1)}
              </Text>
            </TouchableOpacity>
          ))}
        </View>

        {error && (
          <View style={styles.errorBanner}>
            <Text style={styles.errorText}>{error}</Text>
          </View>
        )}

        <Text style={styles.label}>Title</Text>
        <TextInput
          style={styles.input}
          value={title}
          onChangeText={setTitle}
          placeholder={type === 'offer' ? 'e.g. Gardening help' : 'e.g. Need a lift to hospital'}
          placeholderTextColor={theme.textMuted}
          returnKeyType="next"
        />

        <Text style={styles.label}>Description</Text>
        <TextInput
          style={[styles.input, styles.textarea]}
          value={description}
          onChangeText={setDescription}
          placeholder="Tell your community more…"
          placeholderTextColor={theme.textMuted}
          multiline
          numberOfLines={4}
          textAlignVertical="top"
        />

        <Text style={styles.label}>Time credits (hours)</Text>
        <TextInput
          style={styles.input}
          value={timeCredits}
          onChangeText={setTimeCredits}
          placeholder="1"
          placeholderTextColor={theme.textMuted}
          keyboardType="decimal-pad"
          returnKeyType="done"
        />

        {/* Category picker */}
        {categories.length > 0 && (
          <>
            <Text style={styles.label}>Category</Text>
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
          style={[styles.button, { backgroundColor: primary }, isLoading && styles.buttonDisabled]}
          onPress={() => void handleSubmit()}
          disabled={isLoading}
          activeOpacity={0.8}
        >
          {isLoading ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.buttonText}>
              Post {type === 'offer' ? 'offer' : 'request'}
            </Text>
          )}
        </TouchableOpacity>
      </ScrollView>
    </KeyboardAvoidingView>
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
    toggleTextActive: { color: '#fff' },
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
    textarea: { height: 100 },
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
    errorText: { color: theme.error, fontSize: 14 },
    button: { borderRadius: 10, paddingVertical: 14, alignItems: 'center', marginTop: 32 },
    buttonDisabled: { opacity: 0.6 },
    buttonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
  });
}
