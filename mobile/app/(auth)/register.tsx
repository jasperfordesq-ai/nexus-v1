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
import { Alert, Button as HeroButton, Card as HeroCard } from 'heroui-native';
import * as Haptics from '@/lib/haptics';

import { extractToken, getRegistrationResult, register as apiRegister, type LoginUser, type RegisterResult } from '@/lib/api/auth';
import { ApiResponseError } from '@/lib/api/client';
import { STORAGE_KEYS } from '@/lib/constants';
import { storage } from '@/lib/storage';
import { useAuth } from '@/lib/hooks/useAuth';
import { useTheme } from '@/lib/hooks/useTheme';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { registerForPushNotifications } from '@/lib/notifications';
import Button from '@/components/ui/Button';
import Checkbox from '@/components/ui/Checkbox';
import Input from '@/components/ui/Input';

function makeRegisterSchema(t: (key: string) => string) {
  const phoneRegex = /^\+?\d{7,15}$/;

  return z
    .object({
      firstName: z.string().min(1, t('errors.firstNameRequired')),
      lastName: z.string().min(1, t('errors.lastNameRequired')),
      phone: z
        .string()
        .min(1, t('errors.phoneRequired'))
        .refine((value) => phoneRegex.test(value.replace(/[\s\-().]/g, '')), t('errors.phoneInvalid')),
      location: z.string().min(1, t('errors.locationRequired')),
      email: z.string().email(t('errors.validEmail')),
      password: z
        .string()
        .min(12, t('errors.weakPassword')),
      passwordConfirm: z.string().min(1, t('errors.confirmPasswordRequired')),
      termsAccepted: z.boolean().refine((value) => value, t('errors.termsRequired')),
    })
    .refine((d) => d.password === d.passwordConfirm, {
      message: t('errors.passwordMismatch'),
      path: ['passwordConfirm'],
    });
}

type RegisterFormValues = {
  firstName: string;
  lastName: string;
  phone: string;
  location: string;
  email: string;
  password: string;
  passwordConfirm: string;
  termsAccepted: boolean;
};

function hasAuthSession(result: RegisterResult): result is RegisterResult & {
  user: LoginUser;
  refresh_token: string;
} {
  return !!(result.access_token ?? result.token)
    && !!result.refresh_token
    && !!result.user
    && 'tenant_id' in result.user;
}

export default function RegisterScreen() {
  const { t } = useTranslation(['auth', 'common']);
  const { setSession } = useAuth();
  const authRouter = useRouter();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const [formStartedAt] = useState(() => Date.now());

  const registerSchema = useMemo(() => makeRegisterSchema(t), [t]);

  const {
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<RegisterFormValues>({
    resolver: zodResolver(registerSchema) as Resolver<RegisterFormValues>,
    defaultValues: {
      firstName: '',
      lastName: '',
      phone: '',
      location: '',
      email: '',
      password: '',
      passwordConfirm: '',
      termsAccepted: false,
    },
  });

  const [isLoading, setIsLoading] = useState(false);
  const [globalError, setGlobalError] = useState<string | null>(null);
  const [pendingMessage, setPendingMessage] = useState<string | null>(null);
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  async function onSubmit(data: RegisterFormValues) {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setIsLoading(true);
    setGlobalError(null);

    try {
      const response = await apiRegister({
        first_name: data.firstName.trim(),
        last_name: data.lastName.trim(),
        phone: data.phone.trim(),
        location: data.location.trim(),
        email: data.email.trim().toLowerCase(),
        password: data.password,
        password_confirmation: data.passwordConfirm,
        terms_accepted: data.termsAccepted,
        form_started_at: formStartedAt,
      });
      const result = getRegistrationResult(response);

      if (!hasAuthSession(result)) {
        setPendingMessage(result.message ?? t('register.successDefaultMessage'));
        return;
      }

      const token = extractToken({
        success: true,
        access_token: result.access_token ?? result.token ?? '',
        refresh_token: result.refresh_token,
        token_type: result.token_type ?? 'Bearer',
        expires_in: result.expires_in ?? 0,
        user: result.user,
      });
      await Promise.all([
        storage.set(STORAGE_KEYS.AUTH_TOKEN, token),
        storage.set(STORAGE_KEYS.REFRESH_TOKEN, result.refresh_token),
        storage.setJson(STORAGE_KEYS.USER_DATA, result.user),
      ]);

      setSession(token, result.user);
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
        color={theme.textMuted}
      />
    </HeroButton>
  );

  if (pendingMessage) {
    return (
      <ScrollView
        contentContainerStyle={{ paddingTop: insets.top, paddingBottom: insets.bottom }}
        className="flex-1 bg-background"
      >
        <View className="px-5 py-10">
          <HeroCard className="overflow-hidden">
            <HeroCard.Header className="items-center px-6 pt-8 pb-4">
              <View
                className="w-[72px] h-[72px] rounded-2xl items-center justify-center mb-4"
                style={{ backgroundColor: primary }}
                accessibilityRole="image"
                accessibilityLabel={t('register.successTitle')}
              >
                <Ionicons name="mail-unread-outline" size={30} color="#fff" />
              </View>
              <HeroCard.Title className="text-2xl font-bold text-center">
                {t('register.successTitle')}
              </HeroCard.Title>
              <HeroCard.Description className="text-center mt-1">
                {t('register.successSubtitle')}
              </HeroCard.Description>
            </HeroCard.Header>
            <HeroCard.Body className="px-6 pb-6">
              <Alert status="success" accessibilityRole="alert">
                <Alert.Indicator />
                <Alert.Content>
                  <Alert.Description>{pendingMessage}</Alert.Description>
                </Alert.Content>
              </Alert>
              <View className="mt-6">
                <Button
                  variant="outline"
                  fullWidth
                  onPress={() => authRouter.replace('/login')}
                  accessibilityLabel={t('register.signIn')}
                >
                  {t('register.signIn')}
                </Button>
              </View>
            </HeroCard.Body>
          </HeroCard>
        </View>
      </ScrollView>
    );
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
                <Alert
                  status="danger"
                  className="mb-4"
                  accessibilityRole="alert"
                  accessibilityLiveRegion="polite"
                >
                  <Alert.Indicator />
                  <Alert.Content>
                    <Alert.Description className="text-danger">{globalError}</Alert.Description>
                  </Alert.Content>
                </Alert>
              ) : null}

              <View className="gap-1">
                <Controller
                  control={control}
                  name="firstName"
                  render={({ field: { onChange, onBlur, value } }) => (
                    <Input
                      testID="register-first-name"
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
                      testID="register-last-name"
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
                  name="phone"
                  render={({ field: { onChange, onBlur, value } }) => (
                    <Input
                      testID="register-phone"
                      label={t('register.phone')}
                      value={value}
                      onChangeText={onChange}
                      onBlur={onBlur}
                      error={errors.phone?.message}
                      placeholder={t('register.phonePlaceholder')}
                      keyboardType="phone-pad"
                      autoComplete="tel"
                      returnKeyType="next"
                    />
                  )}
                />

                <Controller
                  control={control}
                  name="location"
                  render={({ field: { onChange, onBlur, value } }) => (
                    <Input
                      testID="register-location"
                      label={t('register.location')}
                      value={value}
                      onChangeText={onChange}
                      onBlur={onBlur}
                      error={errors.location?.message}
                      placeholder={t('register.locationPlaceholder')}
                      autoComplete="address-line1"
                      returnKeyType="next"
                    />
                  )}
                />

                <Controller
                  control={control}
                  name="email"
                  render={({ field: { onChange, onBlur, value } }) => (
                    <Input
                      testID="register-email"
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
                      testID="register-password"
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
                      testID="register-confirm-password"
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

                <Controller
                  control={control}
                  name="termsAccepted"
                  render={({ field: { onChange, value } }) => (
                    <View className="mt-2 gap-1.5">
                      <Checkbox
                        testID="register-terms"
                        accessibilityLabel={t('register.termsAccepted')}
                        checked={value}
                        onPress={() => onChange(!value)}
                        label={t('register.termsAccepted')}
                      />
                      {errors.termsAccepted?.message ? (
                        <Text className="text-xs text-danger">{errors.termsAccepted.message}</Text>
                      ) : null}
                    </View>
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
