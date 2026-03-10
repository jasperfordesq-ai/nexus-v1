// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Input } from '@heroui/react';
import type { InputProps } from '@heroui/react';

export interface GlassInputProps extends Omit<InputProps, 'errorMessage'> {
  /** Error message to display */
  error?: string;
  /** Helper text displayed below input */
  helperText?: string;
}

/**
 * GlassInput - Glassmorphism input component
 *
 * Wraps HeroUI Input with glassmorphism styling.
 */
export function GlassInput({
  label,
  error,
  helperText,
  isDisabled,
  classNames,
  ...props
}: GlassInputProps) {
  return (
    <Input
      label={label}
      isDisabled={isDisabled}
      isInvalid={!!error}
      errorMessage={error}
      description={!error ? helperText : undefined}
      variant="bordered"
      classNames={{
        inputWrapper: [
          'bg-white/10',
          'backdrop-blur-lg',
          'border-white/20',
          'hover:bg-white/15',
          'focus-within:bg-white/15',
          classNames?.inputWrapper ?? '',
        ].filter(Boolean).join(' '),
        input: ['bg-transparent', classNames?.input ?? ''].filter(Boolean).join(' '),
        ...classNames,
      }}
      {...props}
    />
  );
}

// Named export alias kept for backwards compatibility
export const GlassInputComponent = GlassInput;

export default GlassInput;
