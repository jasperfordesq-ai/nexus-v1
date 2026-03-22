// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useMemo } from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  SafeAreaView,
  Alert,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import { router, useNavigation } from 'expo-router';
import * as Haptics from 'expo-haptics';

import { useTranslation } from 'react-i18next';

import { updateProfile, type UpdateProfilePayload } from '@/lib/api/profile';
import { type User } from '@/lib/api/auth';
import { useAuth } from '@/lib/hooks/useAuth';
import { storage } from '@/lib/storage';
import { STORAGE_KEYS } from '@/lib/constants';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Input from '@/components/ui/Input';
import Button from '@/components/ui/Button';
import OfflineBanner from '@/components/OfflineBanner';

// E.164-ish: optional + then digits, spaces, dashes — at least 7 digits total
const PHONE_RE = /^\+?[\d\s\-().]{7,20}$/;

interface FieldErrors {
  firstName?: string;
  phone?: string;
}

export default function EditProfileScreen() {
  const { t } = useTranslation('profile');
  const navigation = useNavigation();
  const { user, refreshUser } = useAuth();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  useEffect(() => {
    navigation.setOptions({ title: t('edit.title') });
  }, [navigation, t]);

  const fullUser = user as User | null;

  const [firstName, setFirstName] = useState(fullUser?.first_name ?? '');
  const [lastName, setLastName] = useState(fullUser?.last_name ?? '');
  const [bio, setBio] = useState(fullUser?.bio ?? '');
  const [location, setLocation] = useState(fullUser?.location ?? '');
  const [phone, setPhone] = useState(fullUser?.phone ?? '');
  const [saving, setSaving] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});

  // Track whether the form has unsaved changes
  const isDirty =
    firstName !== (fullUser?.first_name ?? '') ||
    lastName !== (fullUser?.last_name ?? '') ||
    bio !== (fullUser?.bio ?? '') ||
    location !== (fullUser?.location ?? '') ||
    phone !== (fullUser?.phone ?? '');

  // Warn the user before navigating away with unsaved changes
  useEffect(() => {
    const unsubscribe = navigation.addListener('beforeRemove', (e) => {
      if (!isDirty || saving) return;
      e.preventDefault();
      Alert.alert(
        t('edit.unsavedTitle'),
        t('edit.unsavedMessage'),
        [
          { text: t('common:buttons.cancel'), style: 'cancel' },
          {
            text: t('edit.discard'),
            style: 'destructive',
            onPress: () => navigation.dispatch(e.data.action),
          },
        ],
      );
    });
    return unsubscribe;
  }, [navigation, isDirty, saving, t]);

  function validate(): FieldErrors {
    const errors: FieldErrors = {};
    if (!firstName.trim()) {
      errors.firstName = t('edit.firstNameRequired');
    }
    if (phone.trim() && !PHONE_RE.test(phone.trim())) {
      errors.phone = t('edit.phoneInvalid');
    }
    return errors;
  }

  async function handleSave() {
    const errors = validate();
    if (Object.keys(errors).length > 0) {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      setFieldErrors(errors);
      return;
    }

    setFieldErrors({});
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

      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      Alert.alert(t('edit.saved'), t('edit.savedMessage'), [
        { text: t('common:buttons.done'), onPress: () => router.back() },
      ]);
    } catch (err: unknown) {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      const msg = err instanceof Error ? err.message : t('edit.saveError');
      Alert.alert(t('common:errors.generic'), msg);
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
          <OfflineBanner />
          <View style={styles.fieldGroup}>
            <Text style={styles.label}>{t('edit.firstName')}</Text>
            <Input
              value={firstName}
              onChangeText={(v) => {
                setFirstName(v);
                if (fieldErrors.firstName) setFieldErrors((e) => ({ ...e, firstName: undefined }));
              }}
              placeholder={t('edit.firstName')}
              autoCapitalize="words"
              maxLength={50}
              error={fieldErrors.firstName}
            />
          </View>

          <View style={styles.fieldGroup}>
            <Text style={styles.label}>{t('edit.lastName')}</Text>
            <Input
              value={lastName}
              onChangeText={setLastName}
              placeholder={t('edit.lastName')}
              autoCapitalize="words"
              maxLength={50}
            />
          </View>

          <View style={styles.fieldGroup}>
            <Text style={styles.label}>{t('edit.aboutYou')}</Text>
            <Input
              value={bio}
              onChangeText={setBio}
              placeholder={t('edit.aboutPlaceholder')}
              multiline
              numberOfLines={4}
              style={styles.bioInput}
              maxLength={500}
            />
          </View>

          <View style={styles.fieldGroup}>
            <Text style={styles.label}>{t('edit.location')}</Text>
            <Input
              value={location}
              onChangeText={setLocation}
              placeholder={t('edit.locationPlaceholder')}
              autoCapitalize="words"
            />
          </View>

          <View style={styles.fieldGroup}>
            <Text style={styles.label}>{t('edit.phoneOptional')}</Text>
            <Input
              value={phone}
              onChangeText={(v) => {
                setPhone(v);
                if (fieldErrors.phone) setFieldErrors((e) => ({ ...e, phone: undefined }));
              }}
              placeholder={t('edit.phonePlaceholder')}
              keyboardType="phone-pad"
              error={fieldErrors.phone}
            />
          </View>

          <Button
            onPress={() => void handleSave()}
            disabled={saving}
            isLoading={saving}
            style={styles.saveBtn}
            accessibilityLabel={t('edit.saveChanges')}
          >
            {t('edit.saveChanges')}
          </Button>
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
  });
}
