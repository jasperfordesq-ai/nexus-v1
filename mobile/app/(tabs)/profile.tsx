// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  View,
  Text,
  TouchableOpacity,
  ScrollView,
  StyleSheet,
  SafeAreaView,
  Alert,
} from 'react-native';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import {
  launchImageLibraryAsync,
  requestMediaLibraryPermissionsAsync,
  MediaTypeOptions,
} from 'expo-image-picker';
import { useState } from 'react';

import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { type User } from '@/lib/api/auth';
import { updateAvatar } from '@/lib/api/profile';
import Avatar from '@/components/ui/Avatar';
import { ProfileSkeleton } from '@/components/ui/Skeleton';

export default function ProfileScreen() {
  const { user, displayName, logout, refreshUser } = useAuth();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = makeStyles(theme);
  const [uploading, setUploading] = useState(false);

  async function pickAndUploadAvatar() {
    const { status } = await requestMediaLibraryPermissionsAsync();
    if (status !== 'granted') {
      Alert.alert(
        'Permission needed',
        'Please allow access to your photo library to change your avatar.',
      );
      return;
    }

    const result = await launchImageLibraryAsync({
      mediaTypes: MediaTypeOptions.Images,
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.8,
    });

    if (result.canceled || !result.assets?.[0]) return;

    const uri = result.assets[0].uri;
    setUploading(true);
    try {
      const response = await updateAvatar(uri);
      if (user) {
        refreshUser({ ...user, avatar_url: response.data.avatar_url });
      }
    } catch {
      Alert.alert('Upload failed', 'Could not update your avatar. Please try again.');
    } finally {
      setUploading(false);
    }
  }

  function confirmLogout() {
    Alert.alert(
      'Sign out',
      'Are you sure you want to sign out?',
      [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Sign out', style: 'destructive', onPress: () => void logout() },
      ],
    );
  }

  if (!user) {
    return (
      <SafeAreaView style={styles.container}>
        <ProfileSkeleton />
      </SafeAreaView>
    );
  }

  // balance is only present on the full User (from /users/me), not the LoginUser
  const balance = 'balance' in user ? (user as User).balance : null;
  const bio = 'bio' in user ? (user as User).bio : null;

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView contentContainerStyle={styles.content}>
        {/* Avatar + name */}
        <View style={styles.avatarSection}>
          <TouchableOpacity
            onPress={() => void pickAndUploadAvatar()}
            activeOpacity={0.8}
            disabled={uploading}
            style={styles.avatarWrapper}
            accessibilityLabel="Change profile photo"
            accessibilityRole="button"
          >
            <Avatar uri={user.avatar_url} name={displayName} size={88} />
            <View style={styles.cameraOverlay}>
              <Ionicons name="camera-outline" size={20} color="#fff" />
            </View>
          </TouchableOpacity>
          <Text style={styles.name}>{displayName}</Text>
          <Text style={styles.email}>{user.email}</Text>
        </View>

        {/* Time balance card — only shown once full profile loads */}
        {balance !== null && (
          <View style={[styles.balanceCard, { borderColor: primary }]}>
            <Text style={styles.balanceLabel}>Time balance</Text>
            <Text style={[styles.balanceValue, { color: primary }]}>
              {balance.toFixed(1)} hrs
            </Text>
          </View>
        )}

        {/* Bio */}
        {bio && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>About</Text>
            <Text style={styles.bio}>{bio}</Text>
          </View>
        )}

        {/* Actions */}
        <View style={styles.actions}>
          <TouchableOpacity
            style={[styles.actionButton, { borderColor: primary }]}
            onPress={() => router.push('/(modals)/wallet')}
            activeOpacity={0.7}
          >
            <Text style={[styles.actionButtonText, { color: primary }]}>View wallet</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.actionButton, { borderColor: theme.border }]}
            onPress={() => router.push('/(modals)/edit-profile')}
            activeOpacity={0.7}
          >
            <Text style={styles.actionButtonText}>Edit profile</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.actionButton, { borderColor: theme.border }]}
            onPress={() => router.push('/(modals)/members')}
            activeOpacity={0.7}
          >
            <Text style={styles.actionButtonText}>Browse Members</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.actionButton, { borderColor: theme.border }]}
            onPress={() => router.push('/(modals)/settings')}
            activeOpacity={0.7}
          >
            <Text style={styles.actionButtonText}>Settings</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.actionButton, styles.logoutButton]}
            onPress={confirmLogout}
            activeOpacity={0.7}
          >
            <Text style={styles.logoutText}>Sign out</Text>
          </TouchableOpacity>
        </View>

        {/* AGPL attribution — required by Section 7(b) */}
        <Text style={styles.attribution}>
          Project NEXUS · AGPL-3.0-or-later · © 2024–2026 Jasper Ford
        </Text>
      </ScrollView>
    </SafeAreaView>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    content: { paddingHorizontal: 24, paddingTop: 32, paddingBottom: 48 },
    avatarSection: { alignItems: 'center', marginBottom: 24 },
    avatarWrapper: { position: 'relative' },
    cameraOverlay: {
      position: 'absolute',
      bottom: 0,
      right: 0,
      backgroundColor: 'rgba(0,0,0,0.55)',
      borderRadius: 12,
      padding: 4,
    },
    name: { fontSize: 22, fontWeight: '700', color: theme.text, marginTop: 12 },
    email: { fontSize: 14, color: theme.textSecondary, marginTop: 2 },
    balanceCard: {
      borderWidth: 2,
      borderRadius: 14,
      padding: 20,
      alignItems: 'center',
      backgroundColor: theme.surface,
      marginBottom: 24,
    },
    balanceLabel: { fontSize: 13, color: theme.textSecondary, marginBottom: 4 },
    balanceValue: { fontSize: 36, fontWeight: '700' },
    section: { marginBottom: 24 },
    sectionTitle: { fontSize: 13, fontWeight: '600', color: theme.textSecondary, marginBottom: 6, textTransform: 'uppercase', letterSpacing: 0.5 },
    bio: { fontSize: 15, color: theme.text, lineHeight: 22 },
    actions: { gap: 12 },
    actionButton: {
      borderWidth: 1,
      borderRadius: 10,
      paddingVertical: 13,
      alignItems: 'center',
      backgroundColor: theme.surface,
    },
    actionButtonText: { fontSize: 15, fontWeight: '600', color: theme.text },
    logoutButton: { borderColor: theme.error, backgroundColor: theme.errorBg },
    logoutText: { fontSize: 15, fontWeight: '600', color: theme.error },
    attribution: {
      fontSize: 11,
      color: theme.textMuted,
      textAlign: 'center',
      marginTop: 40,
    },
  });
}
