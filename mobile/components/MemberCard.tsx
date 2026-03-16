// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useMemo } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { router } from 'expo-router';

import { useTranslation } from 'react-i18next';

import { type Member } from '@/lib/api/members';
import Avatar from '@/components/ui/Avatar';
import Card from '@/components/ui/Card';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';

interface MemberCardProps {
  member: Member;
}

export default function MemberCard({ member }: MemberCardProps) {
  const { t } = useTranslation('members');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  const displayName = member.name?.trim() ||
    [member.first_name, member.last_name].filter(Boolean).join(' ') ||
    t('common:labels.member');

  return (
    <TouchableOpacity
      style={styles.wrapper}
      activeOpacity={0.85}
      onPress={() => router.push({ pathname: '/(modals)/member-profile', params: { id: String(member.id) } })}
      accessibilityRole="button"
      accessibilityLabel={displayName}
    >
      <Card style={styles.card}>
        <View style={styles.row}>
          <Avatar uri={member.avatar ?? member.avatar_url ?? null} name={displayName} size={52} />
          <View style={styles.info}>
            <Text style={styles.name}>{displayName}</Text>
            {member.tagline && (
              <Text style={styles.tagline} numberOfLines={2}>{member.tagline}</Text>
            )}
          </View>
          <View style={styles.stat}>
            <Text style={[styles.statValue, { color: primary }]}>
              {(member.total_hours_given ?? 0).toFixed(0)}
            </Text>
            <Text style={styles.statLabel}>{t('hrsGiven')}</Text>
          </View>
        </View>
      </Card>
    </TouchableOpacity>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    wrapper: { marginHorizontal: 16, marginVertical: 6 },
    card: {},
    row: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
    info: { flex: 1, gap: 4 },
    name: { fontSize: 16, fontWeight: '600', color: theme.text },
    tagline: { fontSize: 13, color: theme.textSecondary, lineHeight: 18 },
    stat: { alignItems: 'center', minWidth: 48 },
    statValue: { fontSize: 20, fontWeight: '700' },
    statLabel: { fontSize: 11, color: theme.textMuted },
  });
}
