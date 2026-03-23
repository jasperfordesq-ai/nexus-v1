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
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
} from 'react-native';
import { useTranslation } from 'react-i18next';
import { Link, router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';

import { register as apiRegister, extractToken } from '@/lib/api/auth';
import { ApiResponseError } from '@/lib/api/client';
import { STORAGE_KEYS } from '@/lib/constants';
import { storage } from '@/lib/storage';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { registerForPushNotifications } from '@/lib/notifications';

/** White text used on primary-colored backgrounds for guaranteed contrast */
const PRIMARY_CONTRAST_TEXT = '#FFFFFF'; // contrast text on primary

function makeRegisterSchema(t: (key: string) => string) {
  return z
    .object({
      firstName: z.string().min(1, t('errors.firstNameRequired')),
      lastName: z.string().default(''),
      email: z.string().email(t('errors.validEmail')),
      password: z.string()
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
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);
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

      // Update in-memory auth state so isAuthenticated becomes true immediately
      setSession(token, response.user);

      router.replace('/(tabs)/home');

      // Register device for push notifications (non-blocking, best-effort)
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

  return (
    <KeyboardAvoidingView
      style={styles.flex}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView
        contentContainerStyle={[
          styles.container,
          { paddingTop: insets.top, paddingBottom: insets.bottom },
        ]}
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="on-drag"
      >
        <View style={styles.header}>
          <Text style={styles.title}>{t('register.title')}</Text>
          <Text style={styles.subtitle}>{t('register.subtitle')}</Text>
        </View>

        {globalError && (
          <View
            style={styles.errorBanner}
            accessibilityRole="alert"
            accessibilityLiveRegion="polite"
          >
            <Text style={styles.errorText}>{globalError}</Text>
          </View>
        )}

        <View style={styles.form}>
          <Text style={styles.label}>{t('register.firstName')}</Text>
          <Controller
            control={control}
            name="firstName"
            render={({ field: { onChange, onBlur, value } }) => (
              <>
                <TextInput
                  style={[styles.input, errors.firstName && styles.inputError]}
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
                  placeholder={t('register.firstNamePlaceholder')}
                  autoCapitalize="words"
                  returnKeyType="next"
                />
                {errors.firstName && <Text style={styles.fieldError}>{errors.firstName.message}</Text>}
              </>
            )}
          />

          <Text style={styles.label}>{t('register.lastName')}</Text>
          <Controller
            control={control}
            name="lastName"
            render={({ field: { onChange, onBlur, value } }) => (
              <TextInput
                style={styles.input}
                value={value}
                onChangeText={onChange}
                onBlur={onBlur}
                placeholder={t('register.lastNamePlaceholder')}
                autoCapitalize="words"
                returnKeyType="next"
              />
            )}
          />

          <Text style={styles.label}>{t('register.email')}</Text>
          <Controller
            control={control}
            name="email"
            render={({ field: { onChange, onBlur, value } }) => (
              <>
                <TextInput
                  style={[styles.input, errors.email && styles.inputError]}
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
                  placeholder={t('register.emailPlaceholder')}
                  keyboardType="email-address"
                  autoCapitalize="none"
                  autoComplete="email"
                  returnKeyType="next"
                />
                {errors.email && <Text style={styles.fieldError}>{errors.email.message}</Text>}
              </>
            )}
          />

          <Text style={styles.label}>{t('register.password')}</Text>
          <Controller
            control={control}
            name="password"
            render={({ field: { onChange, onBlur, value } }) => (
              <>
                <View style={styles.passwordContainer}>
                  <TextInput
                    style={[styles.passwordInput, errors.password && styles.inputError]}
                    value={value}
                    onChangeText={onChange}
                    onBlur={onBlur}
                    placeholder={t('register.passwordPlaceholder')}
                    secureTextEntry={!showPassword}
                    autoComplete="new-password"
                    textContentType="newPassword"
                    returnKeyType="next"
                  />
                  <TouchableOpacity
                    style={styles.eyeButton}
                    onPress={() => setShowPassword((prev) => !prev)}
                    activeOpacity={0.6}
                    accessibilityLabel={t('login.togglePassword')}
                    accessibilityRole="button"
                  >
                    <Ionicons
                      name={showPassword ? 'eye-off-outline' : 'eye-outline'}
                      size={20}
                      color={theme.textMuted}
                    />
                  </TouchableOpacity>
                </View>
                {errors.password && <Text style={styles.fieldError}>{errors.password.message}</Text>}
              </>
            )}
          />

          <Text style={styles.label}>{t('register.confirmPassword')}</Text>
          <Controller
            control={control}
            name="passwordConfirm"
            render={({ field: { onChange, onBlur, value } }) => (
              <>
                <View style={styles.passwordContainer}>
                  <TextInput
                    style={[styles.passwordInput, errors.passwordConfirm && styles.inputError]}
                    value={value}
                    onChangeText={onChange}
                    onBlur={onBlur}
                    placeholder={t('register.confirmPasswordPlaceholder')}
                    secureTextEntry={!showConfirmPassword}
                    autoComplete="new-password"
                    textContentType="newPassword"
                    returnKeyType="done"
                    onSubmitEditing={handleSubmit(onSubmit)}
                  />
                  <TouchableOpacity
                    style={styles.eyeButton}
                    onPress={() => setShowConfirmPassword((prev) => !prev)}
                    activeOpacity={0.6}
                    accessibilityLabel={t('login.togglePassword')}
                    accessibilityRole="button"
                  >
                    <Ionicons
                      name={showConfirmPassword ? 'eye-off-outline' : 'eye-outline'}
                      size={20}
                      color={theme.textMuted}
                    />
                  </TouchableOpacity>
                </View>
                {errors.passwordConfirm && (
                  <Text style={styles.fieldError}>{errors.passwordConfirm.message}</Text>
                )}
              </>
            )}
          />

          <TouchableOpacity
            style={[styles.button, { backgroundColor: primary }, isLoading && styles.buttonDisabled]}
            onPress={handleSubmit(onSubmit)}
            disabled={isLoading}
            activeOpacity={0.8}
          >
            {isLoading ? (
              <ActivityIndicator color={PRIMARY_CONTRAST_TEXT} />
            ) : (
              <Text style={styles.buttonText}>{t('register.submit')}</Text>
            )}
          </TouchableOpacity>
        </View>

        <View style={styles.footer}>
          <Text style={styles.footerText}>{t('register.hasAccount')} </Text>
          <Link href="/(auth)/login" style={[styles.link, { color: primary }]}>
            {t('register.signIn')}
          </Link>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    flex: { flex: 1, backgroundColor: theme.surface },
    container: {
      flexGrow: 1,
      justifyContent: 'center',
      paddingHorizontal: 24,
      paddingVertical: 48,
    },
    header: { marginBottom: 32 },
    title: { fontSize: 26, fontWeight: '700', color: theme.text, marginBottom: 4 },
    subtitle: { fontSize: 14, color: theme.textSecondary },
    errorBanner: {
      backgroundColor: theme.errorBg,
      borderRadius: 8,
      padding: 12,
      marginBottom: 16,
    },
    errorText: { color: theme.error, fontSize: 14 },
    form: { gap: 4 },
    label: { fontSize: 14, fontWeight: '600', color: theme.text, marginBottom: 6, marginTop: 12 },
    input: {
      borderWidth: 1,
      borderColor: theme.border,
      borderRadius: 10,
      paddingHorizontal: 14,
      paddingVertical: 12,
      fontSize: 16,
      color: theme.text,
      backgroundColor: theme.bg,
    },
    inputError: { borderColor: theme.error },
    passwordContainer: {
      position: 'relative',
      justifyContent: 'center',
    },
    passwordInput: {
      borderWidth: 1,
      borderColor: theme.border,
      borderRadius: 10,
      paddingHorizontal: 14,
      paddingRight: 44,
      paddingVertical: 12,
      fontSize: 16,
      color: theme.text,
      backgroundColor: theme.bg,
    },
    eyeButton: {
      position: 'absolute',
      right: 12,
      padding: 4,
    },
    fieldError: { color: theme.error, fontSize: 12, marginTop: 4, marginLeft: 4 },
    button: {
      borderRadius: 10,
      paddingVertical: 14,
      alignItems: 'center',
      marginTop: 24,
    },
    buttonDisabled: { opacity: 0.6 },
    buttonText: { color: PRIMARY_CONTRAST_TEXT, fontSize: 16, fontWeight: '600' }, // contrast text on primary
    footer: { flexDirection: 'row', justifyContent: 'center', marginTop: 32 },
    footerText: { color: theme.textSecondary, fontSize: 14 },
    link: { fontSize: 14, fontWeight: '600' },
  });
}
