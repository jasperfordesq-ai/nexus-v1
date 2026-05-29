// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { View, type TextInputProps } from 'react-native';
import { FieldError, Input as HeroInput, Label, TextField } from 'heroui-native';

interface InputProps extends TextInputProps {
  label?: string;
  error?: string;
  leftIcon?: React.ReactNode;
  rightIcon?: React.ReactNode;
}

export default function Input({
  label,
  error,
  leftIcon,
  rightIcon,
  style,
  editable,
  ...rest
}: InputProps) {
  const isDisabled = editable === false;

  return (
    <TextField isInvalid={!!error} isDisabled={isDisabled} className="mb-3">
      {label ? (
        <Label className="mb-1.5 text-sm font-semibold">{label}</Label>
      ) : null}
      <View className="flex-row items-center">
        {leftIcon ? (
          <View className="pl-3 absolute left-0 z-10">{leftIcon}</View>
        ) : null}
        <HeroInput
          isInvalid={!!error}
          isDisabled={isDisabled}
          style={[leftIcon ? { paddingLeft: 40 } : undefined, rightIcon ? { paddingRight: 40 } : undefined, style]}
          className="flex-1"
          {...rest}
        />
        {rightIcon ? (
          <View className="pr-3 absolute right-0 z-10">{rightIcon}</View>
        ) : null}
      </View>
      {error ? (
        <FieldError className="mt-1 text-xs">{error}</FieldError>
      ) : null}
    </TextField>
  );
}
