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

import { register as apiRegister, extractToken } from '@/lib/api/auth';
import { ApiResponseError } from '@/lib/api/client';
import { STORAGE_KEYS } from '@/lib/constants';
import { storage } from '@/lib/storage';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';

const registerSchema = z
  .object({
    firstName: z.string().min(1, 'First name is required'),
    lastName: z.string().default(''),
    email: z.string().email('Please enter a valid email address'),
    password: z.string().min(8, 'Password must be at least 8 characters'),
    passwordConfirm: z.string().min(1, 'Please confirm your password'),
  })
  .refine((d) => d.password === d.passwordConfirm, {
    message: 'Passwords do not match',
    path: ['passwordConfirm'],
  });

type RegisterFormValues = z.infer<typeof registerSchema>;

export default function RegisterScreen() {
  const { t } = useTranslation('auth');
  const { setSession } = useAuth();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

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

  async function onSubmit(data: RegisterFormValues) {
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
    } catch (err) {
      if (err instanceof ApiResponseError) {
        setGlobalError(err.message);
      } else {
        setGlobalError('Unable to register. Please try again.');
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
        contentContainerStyle={styles.container}
        keyboardShouldPersistTaps="handled"
      >
        <View style={styles.header}>
          <Text style={styles.title}>{t('register.title')}</Text>
          <Text style={styles.subtitle}>{t('register.subtitle')}</Text>
        </View>

        {globalError && (
          <View style={styles.errorBanner}>
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
                <TextInput
                  style={[styles.input, errors.password && styles.inputError]}
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
                  placeholder={t('register.passwordPlaceholder')}
                  secureTextEntry
                  returnKeyType="next"
                />
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
                <TextInput
                  style={[styles.input, errors.passwordConfirm && styles.inputError]}
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
                  placeholder={t('register.confirmPasswordPlaceholder')}
                  secureTextEntry
                  returnKeyType="done"
                  onSubmitEditing={handleSubmit(onSubmit)}
                />
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
              <ActivityIndicator color="#fff" />
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
    fieldError: { color: theme.error, fontSize: 12, marginTop: 4, marginLeft: 4 },
    button: {
      borderRadius: 10,
      paddingVertical: 14,
      alignItems: 'center',
      marginTop: 24,
    },
    buttonDisabled: { opacity: 0.6 },
    buttonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
    footer: { flexDirection: 'row', justifyContent: 'center', marginTop: 32 },
    footerText: { color: theme.textSecondary, fontSize: 14 },
    link: { fontSize: 14, fontWeight: '600' },
  });
}
