// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  TouchableOpacity,
  Text,
  ActivityIndicator,
  StyleSheet,
  type TouchableOpacityProps,
} from 'react-native';
import { usePrimaryColor } from '@/lib/hooks/useTenant';

type ButtonVariant = 'solid' | 'outline' | 'ghost';

interface ButtonProps extends TouchableOpacityProps {
  children: string;
  variant?: ButtonVariant;
  isLoading?: boolean;
  color?: string;
}

export default function Button({
  children,
  variant = 'solid',
  isLoading = false,
  color,
  disabled,
  style,
  ...rest
}: ButtonProps) {
  const primary = usePrimaryColor();
  const resolvedColor = color ?? primary;

  const containerStyle = [
    styles.base,
    variant === 'solid' && { backgroundColor: resolvedColor },
    variant === 'outline' && { borderWidth: 1.5, borderColor: resolvedColor },
    variant === 'ghost' && styles.ghost,
    (disabled || isLoading) && styles.disabled,
    style,
  ];

  const textStyle = [
    styles.text,
    variant === 'solid' && styles.textSolid,
    (variant === 'outline' || variant === 'ghost') && { color: resolvedColor },
  ];

  return (
    <TouchableOpacity
      style={containerStyle}
      disabled={disabled || isLoading}
      activeOpacity={0.8}
      accessibilityRole="button"
      {...rest}
    >
      {isLoading ? (
        <ActivityIndicator color={variant === 'solid' ? '#fff' : resolvedColor} />
      ) : (
        <Text style={textStyle}>{children}</Text>
      )}
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  base: {
    borderRadius: 10,
    paddingVertical: 13,
    paddingHorizontal: 20,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 46,
  },
  ghost: {},
  disabled: { opacity: 0.5 },
  text: { fontSize: 15, fontWeight: '600' },
  textSolid: { color: '#fff' },
});
