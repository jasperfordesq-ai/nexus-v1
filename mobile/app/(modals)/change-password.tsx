// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect } from 'react';
import {
  Alert,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useNavigation } from 'expo-router';
import * as Haptics from 'expo-haptics';

import { useTranslation } from 'react-i18next';

import { updatePassword } from '@/lib/api/profile';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import Input from '@/components/ui/Input';
import Button from '@/components/ui/Button';
import OfflineBanner from '@/components/OfflineBanner';

export default function ChangePasswordScreen() {
  const { t } = useTranslation('settings');
  const navigation = useNavigation();
  const primary = usePrimaryColor();

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
    <SafeAreaView className="flex-1 bg-background">
      <KeyboardAvoidingView
        className="flex-1"
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 64 : 0}
      >
        <ScrollView
          contentContainerStyle={{ padding: 20, paddingBottom: 40 }}
          keyboardShouldPersistTaps="handled"
        >
          <OfflineBanner />
          <Text className="text-sm text-muted-foreground mb-5 leading-5">
            {t('password.hint')}
          </Text>

          <View className="mb-5">
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
              <Text className="text-danger text-[12px] mt-1 mb-1">{fieldErrors.currentPassword}</Text>
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
              <Text className="text-danger text-[12px] mt-1 mb-1">{fieldErrors.newPassword}</Text>
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
              <Text className="text-danger text-[12px] mt-1 mb-1">{fieldErrors.confirmPassword}</Text>
            ) : null}
          </View>

          <Button
            onPress={() => void handleSubmit()}
            isLoading={isLoading}
            disabled={isLoading}
            color={primary}
            style={{ marginTop: 4 }}
          >
            {t('password.save')}
          </Button>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}
