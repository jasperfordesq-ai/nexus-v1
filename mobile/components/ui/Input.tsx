// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  type TextInputProps,
  StyleSheet,
} from 'react-native';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';

interface InputProps extends TextInputProps {
  label?: string;
  error?: string;
}

export default function Input({ label, error, style, ...rest }: InputProps) {
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [focused, setFocused] = useState(false);
  const styles = makeStyles(theme);

  return (
    <View style={styles.wrapper}>
      {label && <Text style={styles.label}>{label}</Text>}
      <TextInput
        style={[
          styles.input,
          focused && { borderColor: primary },
          error && styles.inputError,
          style,
        ]}
        placeholderTextColor={theme.textMuted}
        onFocus={() => setFocused(true)}
        onBlur={() => setFocused(false)}
        {...rest}
      />
      {error && <Text style={styles.errorText}>{error}</Text>}
    </View>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    wrapper: { marginBottom: 12 },
    label: {
      fontSize: 14,
      fontWeight: '600',
      color: theme.text,
      marginBottom: 6,
    },
    input: {
      borderWidth: 1,
      borderColor: theme.border,
      borderRadius: 10,
      paddingHorizontal: 14,
      paddingVertical: 12,
      fontSize: 16,
      color: theme.text,
      backgroundColor: theme.surface,
    },
    inputError: { borderColor: theme.error },
    errorText: { fontSize: 12, color: theme.error, marginTop: 4 },
  });
}
