// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useMemo } from 'react';
import { useForm, Controller, type Resolver } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
  View,
  Text,
  Pressable,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
} from 'react-native';
import { useTranslation } from 'react-i18next';
import { Link, router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as Haptics from '@/lib/haptics';

import { register as apiRegister, extractToken } from '@/lib/api/auth';
import { ApiResponseError } from '@/lib/api/client';
import { STORAGE_KEYS } from '@/lib/constants';
import { storage } from '@/lib/storage';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { registerForPushNotifications } from '@/lib/notifications';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';

function makeRegisterSchema(t: (key: string) => string) {
  return z
    .object({
      firstName: z.string().min(1, t('errors.firstNameRequired')),
      lastName: z.string().default(''),
      email: z.string().email(t('errors.validEmail')),
      password: z
        .string()
        .min(8, t('errors.weakPassword'))
        .regex(/[A-Z]/, t('errors.passwordUppercase'))
        .regex(/[0-9]/, t('errors.passwordNumber')),
      passwordConfirm: z.string().min(1, t('errors.confirmPasswordRequired')),
    })
    .refine((d) => d.password === d.passwordConfirm, {
      message: t('errors.passwordMismatch'),
      path: ['passwordConfirm'],
    });
}

type RegisterFormValues = {
  firstName: string;
  lastName: string;
  email: string;
  password: string;
  passwordConfirm: string;
};

export default function RegisterScreen() {
  const { t } = useTranslation('auth');
  const { setSession } = useAuth();
  const primary = usePrimaryColor();
  const insets = useSafeAreaInsets();

  const registerSchema = useMemo(() => makeRegisterSchema(t), [t]);

  const {
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<RegisterFormValues>({
    resolver: zodResolver(registerSchema) as Resolver<RegisterFormValues>,
    defaultValues: { firstName: '', lastName: '', email: '', password: '', passwordConfirm: '' },
  });

  const [isLoading, setIsLoading] = useState(false);
  const [globalError, setGlobalError] = useState<string | null>(null);
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  async function onSubmit(data: RegisterFormValues) {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setIsLoading(true);
    setGlobalError(null);

    try {
      const response = await apiRegister({
        first_name: data.firstName.trim(),
        last_name: data.lastName?.trim() ?? '',
        email: data.email.trim().toLowerCase(),
        password: data.password,
        password_confirmation: data.passwordConfirm,
      });

      const token = extractToken(response);
      await Promise.all([
        storage.set(STORAGE_KEYS.AUTH_TOKEN, token),
        storage.set(STORAGE_KEYS.REFRESH_TOKEN, response.refresh_token),
        storage.setJson(STORAGE_KEYS.USER_DATA, response.user),
      ]);

      setSession(token, response.user);
      router.replace('/(tabs)/home');
      void registerForPushNotifications();
    } catch (err) {
      if (err instanceof ApiResponseError) {
        setGlobalError(err.message);
      } else {
        setGlobalError(t('errors.unableToRegister'));
      }
    } finally {
      setIsLoading(false);
    }
  }

  const EyeToggle = ({ show, onToggle }: { show: boolean; onToggle: () => void }) => (
    <Pressable
      onPress={onToggle}
      accessibilityLabel={t('login.togglePassword')}
      accessibilityRole="button"
      hitSlop={8}
    >
      <Ionicons
        name={show ? 'eye-off-outline' : 'eye-outline'}
        size={20}
        className="text-muted-foreground"
      />
    </Pressable>
  );

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
        <View className="px-6 py-12">
          <View className="mb-8">
            <Text className="text-2xl font-bold text-foreground mb-1">{t('register.title')}</Text>
            <Text className="text-sm text-muted-foreground">{t('register.subtitle')}</Text>
          </View>

          {globalError ? (
            <View
              className="bg-danger/10 rounded-lg p-3 mb-4"
              accessibilityRole="alert"
              accessibilityLiveRegion="polite"
            >
              <Text className="text-danger text-sm">{globalError}</Text>
            </View>
          ) : null}

          <View className="gap-1">
            <Controller
              control={control}
              name="firstName"
              render={({ field: { onChange, onBlur, value } }) => (
                <Input
                  label={t('register.firstName')}
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
                  error={errors.firstName?.message}
                  placeholder={t('register.firstNamePlaceholder')}
                  autoCapitalize="words"
                  returnKeyType="next"
                />
              )}
            />

            <Controller
              control={control}
              name="lastName"
              render={({ field: { onChange, onBlur, value } }) => (
                <Input
                  label={t('register.lastName')}
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
                  placeholder={t('register.lastNamePlaceholder')}
                  autoCapitalize="words"
                  returnKeyType="next"
                />
              )}
            />

            <Controller
              control={control}
              name="email"
              render={({ field: { onChange, onBlur, value } }) => (
                <Input
                  label={t('register.email')}
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
                  error={errors.email?.message}
                  placeholder={t('register.emailPlaceholder')}
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
                  label={t('register.password')}
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
                  error={errors.password?.message}
                  placeholder={t('register.passwordPlaceholder')}
                  secureTextEntry={!showPassword}
                  autoComplete="new-password"
                  textContentType="newPassword"
                  returnKeyType="next"
                  rightIcon={
                    <EyeToggle
                      show={showPassword}
                      onToggle={() => setShowPassword((p) => !p)}
                    />
                  }
                />
              )}
            />

            <Controller
              control={control}
              name="passwordConfirm"
              render={({ field: { onChange, onBlur, value } }) => (
                <Input
                  label={t('register.confirmPassword')}
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
                  error={errors.passwordConfirm?.message}
                  placeholder={t('register.confirmPasswordPlaceholder')}
                  secureTextEntry={!showConfirmPassword}
                  autoComplete="new-password"
                  textContentType="newPassword"
                  returnKeyType="done"
                  onSubmitEditing={handleSubmit(onSubmit)}
                  rightIcon={
                    <EyeToggle
                      show={showConfirmPassword}
                      onToggle={() => setShowConfirmPassword((p) => !p)}
                    />
                  }
                />
              )}
            />

            <View className="mt-6">
              <Button onPress={handleSubmit(onSubmit)} isLoading={isLoading} fullWidth>
                {t('register.submit')}
              </Button>
            </View>
          </View>

          <View className="flex-row justify-center mt-8">
            <Text className="text-muted-foreground text-sm">{t('register.hasAccount')} </Text>
            <Link href="/(auth)/login" style={{ color: primary }}>
              <Text className="text-sm font-semibold">{t('register.signIn')}</Text>
            </Link>
          </View>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
