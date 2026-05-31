// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, { forwardRef } from 'react';
import { TextInput, View, type TextInputProps } from 'react-native';
import { FieldError, Input as HeroInput, InputGroup, Label, TextField } from 'heroui-native';

interface InputProps extends TextInputProps {
  label?: string;
  error?: string;
  leftIcon?: React.ReactNode;
  rightIcon?: React.ReactNode;
  containerClassName?: string;
  inputClassName?: string;
}

const Input = forwardRef<TextInput, InputProps>(function Input(
  {
    label,
    error,
    leftIcon,
    rightIcon,
    containerClassName,
    inputClassName,
    style,
    editable,
    ...rest
  },
  ref,
) {
  const isDisabled = editable === false;

  return (
    <TextField isInvalid={!!error} isDisabled={isDisabled} className={containerClassName ?? 'mb-3'}>
      {label ? (
        <Label className="mb-1.5 text-sm font-semibold">{label}</Label>
      ) : null}
      {leftIcon || rightIcon ? (
        // InputGroup auto-measures the prefix/suffix width and pads the input
        // to match (no hardcoded 40px). isDecorative makes the icons
        // non-interactive and hidden from screen readers, and lets taps fall
        // through to focus the input — matching the old absolute-View behaviour.
        <InputGroup isDisabled={isDisabled}>
          {leftIcon ? (
            <InputGroup.Prefix isDecorative>{leftIcon}</InputGroup.Prefix>
          ) : null}
          <InputGroup.Input
            ref={ref}
            isInvalid={!!error}
            style={style}
            className={inputClassName ?? 'flex-1'}
            {...rest}
          />
          {rightIcon ? (
            <InputGroup.Suffix isDecorative>{rightIcon}</InputGroup.Suffix>
          ) : null}
        </InputGroup>
      ) : (
        <View className="flex-row items-center">
          <HeroInput
            ref={ref}
            isInvalid={!!error}
            isDisabled={isDisabled}
            style={style}
            className={inputClassName ?? 'flex-1'}
            {...rest}
          />
        </View>
      )}
      {error ? (
        <FieldError className="mt-1 text-xs">{error}</FieldError>
      ) : null}
    </TextField>
  );
});

export default Input;
