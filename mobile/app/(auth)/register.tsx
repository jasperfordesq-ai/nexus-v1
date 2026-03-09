// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
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
} from 'react-native';
import { Link, router } from 'expo-router';

import { register as apiRegister, extractToken } from '@/lib/api/auth';
import { ApiResponseError } from '@/lib/api/client';
import { STORAGE_KEYS } from '@/lib/constants';
import { storage } from '@/lib/storage';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';

const registerSchema = z
  .object({
    firstName: z.string().min(1, 'First name is required'),
    lastName: z.string().optional().default(''),
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
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = makeStyles(theme);

  const {
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<RegisterFormValues>({
    resolver: zodResolver(registerSchema),
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
          <Text style={styles.title}>Create account</Text>
          <Text style={styles.subtitle}>Join your local timebank community</Text>
        </View>

        {globalError && (
          <View style={styles.errorBanner}>
            <Text style={styles.errorText}>{globalError}</Text>
          </View>
        )}

        <View style={styles.form}>
          <Text style={styles.label}>First name</Text>
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
                  placeholder="Jane"
                  autoCapitalize="words"
                  returnKeyType="next"
                />
                {errors.firstName && <Text style={styles.fieldError}>{errors.firstName.message}</Text>}
              </>
            )}
          />

          <Text style={styles.label}>Last name</Text>
          <Controller
            control={control}
            name="lastName"
            render={({ field: { onChange, onBlur, value } }) => (
              <TextInput
                style={styles.input}
                value={value}
                onChangeText={onChange}
                onBlur={onBlur}
                placeholder="Smith"
                autoCapitalize="words"
                returnKeyType="next"
              />
            )}
          />

          <Text style={styles.label}>Email</Text>
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
                  placeholder="you@example.com"
                  keyboardType="email-address"
                  autoCapitalize="none"
                  autoComplete="email"
                  returnKeyType="next"
                />
                {errors.email && <Text style={styles.fieldError}>{errors.email.message}</Text>}
              </>
            )}
          />

          <Text style={styles.label}>Password</Text>
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
                  placeholder="Min. 8 characters"
                  secureTextEntry
                  returnKeyType="next"
                />
                {errors.password && <Text style={styles.fieldError}>{errors.password.message}</Text>}
              </>
            )}
          />

          <Text style={styles.label}>Confirm password</Text>
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
                  placeholder="Re-enter password"
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
              <Text style={styles.buttonText}>Create account</Text>
            )}
          </TouchableOpacity>
        </View>

        <View style={styles.footer}>
          <Text style={styles.footerText}>Already have an account? </Text>
          <Link href="/(auth)/login" style={[styles.link, { color: primary }]}>
            Sign in
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
      backgroundColor: '#FAFAFA',
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
