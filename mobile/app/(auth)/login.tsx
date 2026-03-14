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
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Linking,
} from 'react-native';
import { Link } from 'expo-router';
import * as Haptics from 'expo-haptics';

import { useTranslation } from 'react-i18next';
import { useAuth } from '@/lib/hooks/useAuth';
import { ApiResponseError } from '@/lib/api/client';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';

const loginSchema = z.object({
  email: z.string().email('Please enter a valid email address'),
  password: z.string().min(1, 'Password is required'),
});

type LoginFormValues = z.infer<typeof loginSchema>;

export default function LoginScreen() {
  const { t } = useTranslation('auth');
  const { login: authLogin } = useAuth();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

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

  async function onSubmit(data: LoginFormValues) {
    setIsLoading(true);
    setGlobalError(null);

    try {
      await authLogin({ email: data.email.trim().toLowerCase(), password: data.password });
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch (err) {
      if (err instanceof ApiResponseError) {
        setGlobalError(err.message);
      } else {
        setGlobalError('Unable to sign in. Please try again.');
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
        {/* Logo / branding */}
        <View style={styles.header}>
          <View style={[styles.logoCircle, { backgroundColor: primary }]}>
            <Text style={styles.logoText}>N</Text>
          </View>
          <Text style={styles.title}>Project NEXUS</Text>
          <Text style={styles.subtitle}>{t('login.subtitle')}</Text>
        </View>

        {/* Error banner */}
        {globalError && (
          <View style={styles.errorBanner}>
            <Text style={styles.errorText}>{globalError}</Text>
          </View>
        )}

        {/* Form */}
        <View style={styles.form}>
          <Text style={styles.label}>{t('login.email')}</Text>
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
                  placeholder={t('login.emailPlaceholder')}
                  keyboardType="email-address"
                  autoCapitalize="none"
                  autoComplete="email"
                  returnKeyType="next"
                />
                {errors.email && <Text style={styles.fieldError}>{errors.email.message}</Text>}
              </>
            )}
          />

          <Text style={styles.label}>{t('login.password')}</Text>
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
                  placeholder="••••••••"
                  secureTextEntry
                  autoComplete="password"
                  returnKeyType="done"
                  onSubmitEditing={handleSubmit(onSubmit)}
                />
                {errors.password && <Text style={styles.fieldError}>{errors.password.message}</Text>}
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
              <Text style={styles.buttonText}>{t('login.submit')}</Text>
            )}
          </TouchableOpacity>

          <TouchableOpacity
            onPress={() => Linking.openURL('https://app.project-nexus.ie/forgot-password')}
            style={styles.forgotPasswordBtn}
            activeOpacity={0.7}
          >
            <Text style={styles.forgotPassword}>Forgot password?</Text>
          </TouchableOpacity>
        </View>

        {/* Register link */}
        <View style={styles.footer}>
          <Text style={styles.footerText}>Don&apos;t have an account? </Text>
          <Link href="/(auth)/register" style={[styles.link, { color: primary }]}>
            Register
          </Link>
        </View>

        {/* Tenant switcher */}
        <View style={[styles.footer, { marginTop: 12 }]}>
          <Text style={styles.footerText}>Wrong timebank? </Text>
          <Link href="/(auth)/select-tenant" style={[styles.link, { color: primary }]}>
            Switch community
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
    header: { alignItems: 'center', marginBottom: 40 },
    logoCircle: {
      width: 72,
      height: 72,
      borderRadius: 36,
      justifyContent: 'center',
      alignItems: 'center',
      marginBottom: 16,
    },
    logoText: { color: '#fff', fontSize: 32, fontWeight: '700' },
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
    inputError: {
      borderColor: theme.error,
    },
    fieldError: {
      color: theme.error,
      fontSize: 12,
      marginTop: 4,
      marginLeft: 4,
    },
    button: {
      borderRadius: 10,
      paddingVertical: 14,
      alignItems: 'center',
      marginTop: 24,
    },
    buttonDisabled: { opacity: 0.6 },
    buttonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
    forgotPasswordBtn: { alignSelf: 'center', marginTop: 14 },
    forgotPassword: { color: theme.textSecondary, fontSize: 13, fontWeight: '500' },
    footer: { flexDirection: 'row', justifyContent: 'center', marginTop: 32 },
    footerText: { color: theme.textSecondary, fontSize: 14 },
    link: { fontSize: 14, fontWeight: '600' },
  });
}
