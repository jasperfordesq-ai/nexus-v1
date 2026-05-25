// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useMemo } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
  View,
  Text,
  Pressable,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  Linking,
} from 'react-native';
import { Link } from 'expo-router';
import * as Haptics from 'expo-haptics';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';

import { useAuth } from '@/lib/hooks/useAuth';
import { ApiResponseError } from '@/lib/api/client';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { FORGOT_PASSWORD_URL } from '@/lib/constants';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';

function makeLoginSchema(t: (key: string) => string) {
  return z.object({
    email: z.string().email(t('errors.validEmail')),
    password: z.string().min(1, t('errors.passwordRequired')),
  });
}

type LoginFormValues = { email: string; password: string };

export default function LoginScreen() {
  const { t } = useTranslation('auth');
  const { login: authLogin } = useAuth();
  const primary = usePrimaryColor();
  const insets = useSafeAreaInsets();

  const loginSchema = useMemo(() => makeLoginSchema(t), [t]);

  const {
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  });

  const [isLoading, setIsLoading] = useState(false);
  const [globalError, setGlobalError] = useState<string | null>(null);
  const [showPassword, setShowPassword] = useState(false);

  async function onSubmit(data: LoginFormValues) {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setIsLoading(true);
    setGlobalError(null);

    try {
      await authLogin({ email: data.email.trim().toLowerCase(), password: data.password });
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch (err) {
      if (err instanceof ApiResponseError) {
        setGlobalError(err.message);
      } else {
        setGlobalError(t('errors.unableToSignIn'));
      }
    } finally {
      setIsLoading(false);
    }
  }

  return (
    <KeyboardAvoidingView
      className="flex-1 bg-background"
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView
        contentContainerStyle={{ paddingTop: insets.top, paddingBottom: insets.bottom }}
        className="flex-grow"
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="on-drag"
      >
        <View className="flex-1 justify-center px-6 py-12">
          {/* Logo / branding */}
          <View className="items-center mb-10">
            <View
              className="w-[72px] h-[72px] rounded-full items-center justify-center mb-4"
              style={{ backgroundColor: primary }}
              accessibilityRole="image"
              accessibilityLabel="Project NEXUS logo"
            >
              <Text className="text-white text-[32px] font-bold">N</Text>
            </View>
            <Text className="text-2xl font-bold text-foreground mb-1">{t('common:appName')}</Text>
            <Text className="text-sm text-muted-foreground">{t('login.subtitle')}</Text>
          </View>

          {/* Error banner */}
          {globalError ? (
            <View
              className="bg-danger/10 rounded-lg p-3 mb-4"
              accessibilityRole="alert"
              accessibilityLiveRegion="polite"
            >
              <Text className="text-danger text-sm">{globalError}</Text>
            </View>
          ) : null}

          {/* Form */}
          <View className="gap-1">
            <Controller
              control={control}
              name="email"
              render={({ field: { onChange, onBlur, value } }) => (
                <Input
                  label={t('login.email')}
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
                  error={errors.email?.message}
                  placeholder={t('login.emailPlaceholder')}
                  keyboardType="email-address"
                  autoCapitalize="none"
                  autoComplete="email"
                  returnKeyType="next"
                />
              )}
            />

            <Controller
              control={control}
              name="password"
              render={({ field: { onChange, onBlur, value } }) => (
                <Input
                  label={t('login.password')}
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
                  error={errors.password?.message}
                  placeholder="••••••••"
                  secureTextEntry={!showPassword}
                  autoComplete="password"
                  textContentType="password"
                  returnKeyType="done"
                  onSubmitEditing={handleSubmit(onSubmit)}
                  rightIcon={
                    <Pressable
                      onPress={() => setShowPassword((p) => !p)}
                      accessibilityLabel={t('login.togglePassword')}
                      accessibilityRole="button"
                      hitSlop={8}
                    >
                      <Ionicons
                        name={showPassword ? 'eye-off-outline' : 'eye-outline'}
                        size={20}
                        className="text-muted-foreground"
                      />
                    </Pressable>
                  }
                />
              )}
            />

            <View className="mt-6">
              <Button onPress={handleSubmit(onSubmit)} isLoading={isLoading} fullWidth>
                {t('login.submit')}
              </Button>
            </View>

            <Pressable
              onPress={async () => {
                const supported = await Linking.canOpenURL(FORGOT_PASSWORD_URL);
                if (supported) await Linking.openURL(FORGOT_PASSWORD_URL);
              }}
              className="self-center mt-3.5"
            >
              <Text className="text-muted-foreground text-[13px] font-medium">
                {t('login.forgotPassword')}
              </Text>
            </Pressable>
          </View>

          {/* Register link */}
          <View className="flex-row justify-center mt-8">
            <Text className="text-muted-foreground text-sm">{t('login.noAccount')} </Text>
            <Link href="/(auth)/register" style={{ color: primary }}>
              <Text className="text-sm font-semibold">{t('register.submit')}</Text>
            </Link>
          </View>

          {/* Tenant switcher */}
          <View className="flex-row justify-center mt-3">
            <Text className="text-muted-foreground text-sm">{t('wrongTimebank')} </Text>
            <Link href="/(auth)/select-tenant" style={{ color: primary }}>
              <Text className="text-sm font-semibold">{t('switchCommunity')}</Text>
            </Link>
          </View>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
