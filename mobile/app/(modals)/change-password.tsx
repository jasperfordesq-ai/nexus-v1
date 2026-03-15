// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useMemo } from 'react';
import {
  Alert,
  KeyboardAvoidingView,
  Platform,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { router } from 'expo-router';
import * as Haptics from 'expo-haptics';

import { useTranslation } from 'react-i18next';

import { updatePassword } from '@/lib/api/profile';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Input from '@/components/ui/Input';
import Button from '@/components/ui/Button';
import OfflineBanner from '@/components/OfflineBanner';

export default function ChangePasswordScreen() {
  const { t } = useTranslation('settings');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);

  async function handleSubmit() {
    if (!currentPassword.trim()) {
      Alert.alert(t('password.validation.currentRequired'));
      return;
    }
    if (!newPassword.trim()) {
      Alert.alert(t('password.validation.newRequired'));
      return;
    }
    if (newPassword !== confirmPassword) {
      Alert.alert(t('password.validation.mismatch'));
      return;
    }
    if (newPassword.length < 8) {
      Alert.alert(t('password.validation.tooShort'));
      return;
    }

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
              onChangeText={setCurrentPassword}
            />
            <Input
              label={t('password.newLabel')}
              placeholder={t('password.newPlaceholder')}
              secureTextEntry
              autoCapitalize="none"
              autoCorrect={false}
              value={newPassword}
              onChangeText={setNewPassword}
            />
            <Input
              label={t('password.confirmLabel')}
              placeholder={t('password.confirmPlaceholder')}
              secureTextEntry
              autoCapitalize="none"
              autoCorrect={false}
              value={confirmPassword}
              onChangeText={setConfirmPassword}
            />
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
    content: { padding: 24, paddingBottom: 48 },
    hint: {
      fontSize: 14,
      color: theme.textSecondary,
      marginBottom: 24,
      lineHeight: 20,
    },
    form: { marginBottom: 24 },
    saveButton: { marginTop: 8 },
  });
}
