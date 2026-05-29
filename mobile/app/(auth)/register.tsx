// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useMemo, useState } from 'react';
import { Controller, useForm, type Resolver } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  Text,
  View,
} from 'react-native';
import { useTranslation } from 'react-i18next';
import { router, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Button as HeroButton, Card as HeroCard } from 'heroui-native';
import * as Haptics from '@/lib/haptics';

import { extractToken, register as apiRegister } from '@/lib/api/auth';
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
  const { t } = useTranslation(['auth', 'common']);
  const { setSession } = useAuth();
  const authRouter = useRouter();
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
    <HeroButton
      isIconOnly
      size="sm"
      variant="ghost"
      onPress={onToggle}
      accessibilityLabel={t('register.togglePassword')}
    >
      <Ionicons
        name={show ? 'eye-off-outline' : 'eye-outline'}
        size={20}
        className="text-muted-foreground"
      />
    </HeroButton>
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
        <View className="px-5 py-10">
          <HeroCard className="overflow-hidden">
            <HeroCard.Header className="items-center px-6 pt-8 pb-4">
              <View
                className="w-[72px] h-[72px] rounded-2xl items-center justify-center mb-4"
                style={{ backgroundColor: primary }}
                accessibilityRole="image"
                accessibilityLabel={t('common:appName')}
              >
                <Ionicons name="person-add-outline" size={30} color="#fff" />
              </View>
              <HeroCard.Title className="text-2xl font-bold text-center">
                {t('register.title')}
              </HeroCard.Title>
              <HeroCard.Description className="text-center mt-1">
                {t('register.subtitle')}
              </HeroCard.Description>
            </HeroCard.Header>

            <HeroCard.Body className="px-6 pb-6">
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
            </HeroCard.Body>

            <HeroCard.Footer className="px-6 pb-6 pt-0">
              <View className="w-full items-center gap-2">
                <Text className="text-muted-foreground text-sm text-center">
                  {t('register.hasAccount')}
                </Text>
                <Button
                  variant="outline"
                  fullWidth
                  onPress={() => authRouter.push('/login')}
                  accessibilityLabel={t('register.signIn')}
                >
                  {t('register.signIn')}
                </Button>
              </View>
            </HeroCard.Footer>
          </HeroCard>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
