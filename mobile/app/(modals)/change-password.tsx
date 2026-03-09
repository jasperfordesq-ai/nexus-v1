// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
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

import { updatePassword } from '@/lib/api/profile';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Input from '@/components/ui/Input';
import Button from '@/components/ui/Button';

export default function ChangePasswordScreen() {
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = makeStyles(theme);

  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);

  async function handleSubmit() {
    if (!currentPassword.trim()) {
      Alert.alert('Validation error', 'Please enter your current password.');
      return;
    }
    if (!newPassword.trim()) {
      Alert.alert('Validation error', 'Please enter a new password.');
      return;
    }
    if (newPassword !== confirmPassword) {
      Alert.alert('Validation error', 'New password and confirmation do not match.');
      return;
    }
    if (newPassword.length < 8) {
      Alert.alert('Validation error', 'New password must be at least 8 characters.');
      return;
    }

    setIsLoading(true);
    try {
      await updatePassword({
        current_password: currentPassword,
        new_password: newPassword,
        new_password_confirmation: confirmPassword,
      });
      Alert.alert('Password changed', 'Your password has been updated successfully.', [
        { text: 'OK', onPress: () => router.back() },
      ]);
    } catch (err: unknown) {
      const message =
        err instanceof Error ? err.message : 'Could not change password. Please try again.';
      Alert.alert('Error', message);
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
          <Text style={styles.hint}>
            Enter your current password, then choose a new one.
          </Text>

          <View style={styles.form}>
            <Input
              label="Current password"
              placeholder="Enter current password"
              secureTextEntry
              autoCapitalize="none"
              autoCorrect={false}
              value={currentPassword}
              onChangeText={setCurrentPassword}
            />
            <Input
              label="New password"
              placeholder="Enter new password"
              secureTextEntry
              autoCapitalize="none"
              autoCorrect={false}
              value={newPassword}
              onChangeText={setNewPassword}
            />
            <Input
              label="Confirm new password"
              placeholder="Re-enter new password"
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
            Save password
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
