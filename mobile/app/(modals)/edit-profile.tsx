// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  SafeAreaView,
  Alert,
  KeyboardAvoidingView,
  Platform,
  TouchableOpacity,
} from 'react-native';
import { router } from 'expo-router';

import { updateProfile, type UpdateProfilePayload } from '@/lib/api/profile';
import { type User } from '@/lib/api/auth';
import { useAuth } from '@/lib/hooks/useAuth';
import { storage } from '@/lib/storage';
import { STORAGE_KEYS } from '@/lib/constants';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Input from '@/components/ui/Input';
import Button from '@/components/ui/Button';

export default function EditProfileScreen() {
  const { user, refreshUser } = useAuth();
  const theme = useTheme();
  const styles = makeStyles(theme);

  const fullUser = user as User | null;

  const [firstName, setFirstName] = useState(fullUser?.first_name ?? '');
  const [lastName, setLastName] = useState(fullUser?.last_name ?? '');
  const [bio, setBio] = useState(fullUser?.bio ?? '');
  const [location, setLocation] = useState(fullUser?.location ?? '');
  const [phone, setPhone] = useState(fullUser?.phone ?? '');
  const [saving, setSaving] = useState(false);

  async function handleSave() {
    if (!firstName.trim()) {
      Alert.alert('Validation', 'First name is required.');
      return;
    }

    setSaving(true);
    try {
      const payload: UpdateProfilePayload = {
        first_name: firstName.trim(),
        last_name: lastName.trim(),
        bio: bio.trim(),
        location: location.trim(),
        phone: phone.trim() || undefined,
      };

      const response = await updateProfile(payload);

      // Update cached user data
      await storage.setJson(STORAGE_KEYS.USER_DATA, response.data);
      refreshUser(response.data);

      Alert.alert('Saved', 'Your profile has been updated.', [
        { text: 'OK', onPress: () => router.back() },
      ]);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Could not save profile.';
      Alert.alert('Error', msg);
    } finally {
      setSaving(false);
    }
  }

  return (
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      >
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          <View style={styles.fieldGroup}>
            <Text style={styles.label}>First name</Text>
            <Input
              value={firstName}
              onChangeText={setFirstName}
              placeholder="First name"
              autoCapitalize="words"
            />
          </View>

          <View style={styles.fieldGroup}>
            <Text style={styles.label}>Last name</Text>
            <Input
              value={lastName}
              onChangeText={setLastName}
              placeholder="Last name"
              autoCapitalize="words"
            />
          </View>

          <View style={styles.fieldGroup}>
            <Text style={styles.label}>About you</Text>
            <Input
              value={bio}
              onChangeText={setBio}
              placeholder="Tell the community about yourself..."
              multiline
              numberOfLines={4}
              style={styles.bioInput}
            />
          </View>

          <View style={styles.fieldGroup}>
            <Text style={styles.label}>Location</Text>
            <Input
              value={location}
              onChangeText={setLocation}
              placeholder="e.g. New York, USA"
              autoCapitalize="words"
            />
          </View>

          <View style={styles.fieldGroup}>
            <Text style={styles.label}>Phone (optional)</Text>
            <Input
              value={phone}
              onChangeText={setPhone}
              placeholder="+1 555 123 4567"
              keyboardType="phone-pad"
            />
          </View>

          <Button
            onPress={() => void handleSave()}
            disabled={saving}
            style={styles.saveBtn}
          >
            {saving ? 'Saving…' : 'Save changes'}
          </Button>

          <TouchableOpacity
            style={styles.cancelBtn}
            onPress={() => router.back()}
            disabled={saving}
            activeOpacity={0.7}
          >
            <Text style={styles.cancelText}>Cancel</Text>
          </TouchableOpacity>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    content: { padding: 20, paddingBottom: 48 },
    fieldGroup: { marginBottom: 18 },
    label: {
      fontSize: 13,
      fontWeight: '600',
      color: theme.textSecondary,
      textTransform: 'uppercase',
      letterSpacing: 0.5,
      marginBottom: 6,
    },
    bioInput: { height: 100, textAlignVertical: 'top' },
    saveBtn: { marginTop: 8, borderRadius: 10 },
    cancelBtn: { alignItems: 'center', marginTop: 16, paddingVertical: 10 },
    cancelText: { fontSize: 15, color: theme.textSecondary },
  });
}
