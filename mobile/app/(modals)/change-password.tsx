// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useMemo } from 'react';
import {
  Alert,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useNavigation } from 'expo-router';
import * as Haptics from 'expo-haptics';

import { useTranslation } from 'react-i18next';

import { updatePassword } from '@/lib/api/profile';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING } from '@/lib/styles/spacing';
import Input from '@/components/ui/Input';
import Button from '@/components/ui/Button';
import OfflineBanner from '@/components/OfflineBanner';

export default function ChangePasswordScreen() {
  const { t } = useTranslation('settings');
  const navigation = useNavigation();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  useEffect(() => {
    navigation.setOptions({ title: t('password.title') });
  }, [navigation, t]);

  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<{
    currentPassword?: string;
    newPassword?: string;
    confirmPassword?: string;
  }>({});

  async function handleSubmit() {
    const errors: typeof fieldErrors = {};
    if (!currentPassword.trim()) {
      errors.currentPassword = t('password.validation.currentRequired');
    }
    if (!newPassword.trim()) {
      errors.newPassword = t('password.validation.newRequired');
    } else if (newPassword.length < 8) {
      errors.newPassword = t('password.validation.tooShort');
    }
    if (newPassword.trim() && newPassword !== confirmPassword) {
      errors.confirmPassword = t('password.validation.mismatch');
    }
    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors);
      return;
    }
    setFieldErrors({});

    setIsLoading(true);
    try {
      await updatePassword({
        current_password: currentPassword,
        new_password: newPassword,
        new_password_confirmation: confirmPassword,
      });
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      Alert.alert(t('password.success'), t('password.successMessage'), [
        { text: t('common:buttons.done'), onPress: () => router.back() },
      ]);
    } catch (err: unknown) {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      const message =
        err instanceof Error ? err.message : t('password.changeError');
      Alert.alert(t('common:errors.generic'), message);
    } finally {
      setIsLoading(false);
    }
  }

  return (
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 64 : 0}
      >
        <ScrollView
          contentContainerStyle={styles.content}
          keyboardShouldPersistTaps="handled"
        >
          <OfflineBanner />
          <Text style={styles.hint}>
            {t('password.hint')}
          </Text>

          <View style={styles.form}>
            <Input
              label={t('password.currentLabel')}
              placeholder={t('password.currentPlaceholder')}
              secureTextEntry
              autoCapitalize="none"
              autoCorrect={false}
              value={currentPassword}
              onChangeText={(v) => { setCurrentPassword(v); setFieldErrors((e) => ({ ...e, currentPassword: undefined })); }}
            />
            {fieldErrors.currentPassword ? (
              <Text style={styles.fieldError}>{fieldErrors.currentPassword}</Text>
            ) : null}
            <Input
              label={t('password.newLabel')}
              placeholder={t('password.newPlaceholder')}
              secureTextEntry
              autoCapitalize="none"
              autoCorrect={false}
              value={newPassword}
              onChangeText={(v) => { setNewPassword(v); setFieldErrors((e) => ({ ...e, newPassword: undefined })); }}
            />
            {fieldErrors.newPassword ? (
              <Text style={styles.fieldError}>{fieldErrors.newPassword}</Text>
            ) : null}
            <Input
              label={t('password.confirmLabel')}
              placeholder={t('password.confirmPlaceholder')}
              secureTextEntry
              autoCapitalize="none"
              autoCorrect={false}
              value={confirmPassword}
              onChangeText={(v) => { setConfirmPassword(v); setFieldErrors((e) => ({ ...e, confirmPassword: undefined })); }}
            />
            {fieldErrors.confirmPassword ? (
              <Text style={styles.fieldError}>{fieldErrors.confirmPassword}</Text>
            ) : null}
          </View>

          <Button
            onPress={() => void handleSubmit()}
            isLoading={isLoading}
            disabled={isLoading}
            color={primary}
            style={styles.saveButton}
          >
            {t('password.save')}
          </Button>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    flex: { flex: 1 },
    content: { padding: SPACING.lg, paddingBottom: SPACING.xxl },
    hint: {
      ...TYPOGRAPHY.label,
      color: theme.textSecondary,
      marginBottom: SPACING.lg,
      lineHeight: 20,
    },
    form: { marginBottom: SPACING.lg },
    saveButton: { marginTop: SPACING.sm },
    fieldError: { color: theme.error, ...TYPOGRAPHY.caption, marginTop: SPACING.xs, marginBottom: SPACING.xs },
  });
}
