// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { SearchField as HeroUISearchField, type SearchFieldProps as HeroUISearchFieldProps } from '@heroui/react/search-field';
import type { ReactNode } from 'react';

type LegacyVariant = 'flat' | 'bordered' | 'underlined' | 'faded';
type V3Variant = NonNullable<HeroUISearchFieldProps['variant']>;

export type SearchFieldProps = Omit<HeroUISearchFieldProps, 'children' | 'onChange' | 'variant' | 'className'> & {
  className?: string;
  classNames?: {
    base?: string;
    input?: string;
    inputWrapper?: string;
  };
  endContent?: ReactNode;
  isClearable?: boolean;
  onChange?: (event: { target: { value: string } }) => void;
  onValueChange?: (value: string) => void;
  placeholder?: string;
  size?: 'sm' | 'md' | 'lg';
  startContent?: ReactNode;
  variant?: LegacyVariant | V3Variant;
};

function mapVariant(variant?: SearchFieldProps['variant']): V3Variant {
  return variant === 'bordered' || variant === 'underlined' || variant === 'faded'
    ? 'secondary'
    : 'primary';
}

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

function sizeClass(size?: SearchFieldProps['size']): string | undefined {
  switch (size) {
    case 'sm':
      return 'min-h-8 text-sm';
    case 'lg':
      return 'min-h-12 text-base';
    default:
      return undefined;
  }
}

export function SearchField({
  className,
  classNames,
  endContent,
  isClearable: _isClearable,
  onChange,
  onValueChange,
  placeholder,
  size,
  startContent,
  variant,
  ...props
}: SearchFieldProps) {
  return (
    <HeroUISearchField
      {...props}
      className={combineClasses(className, classNames?.base)}
      variant={mapVariant(variant)}
      onChange={(value) => {
        onValueChange?.(value);
        onChange?.({ target: { value } });
      }}
    >
      <HeroUISearchField.Group className={combineClasses(sizeClass(size), classNames?.inputWrapper)}>
        <HeroUISearchField.SearchIcon>{startContent}</HeroUISearchField.SearchIcon>
        <HeroUISearchField.Input className={classNames?.input} placeholder={placeholder} />
        {endContent}
        <HeroUISearchField.ClearButton />
      </HeroUISearchField.Group>
    </HeroUISearchField>
  );
}
