// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, { forwardRef } from 'react';
import { TextInput, type TextInputProps } from 'react-native';
import { SearchField } from 'heroui-native';

interface SearchInputProps extends Omit<TextInputProps, 'editable' | 'onChangeText' | 'value'> {
  value: string;
  onChangeText: (value: string) => void;
  placeholder: string;
  clearLabel: string;
  disabled?: boolean;
  containerClassName?: string;
  groupClassName?: string;
  inputClassName?: string;
}

const SearchInput = forwardRef<TextInput, SearchInputProps>(function SearchInput(
  {
    value,
    onChangeText,
    placeholder,
    clearLabel,
    disabled = false,
    accessibilityLabel,
    containerClassName,
    groupClassName,
    inputClassName,
    ...rest
  },
  ref,
) {
  return (
    <SearchField
      value={value}
      onChange={onChangeText}
      isDisabled={disabled}
      className={containerClassName ?? 'mb-3'}
    >
      <SearchField.Group className={groupClassName ?? 'min-h-12 rounded-full bg-content2'}>
        <SearchField.SearchIcon className="left-4" />
        <SearchField.Input
          ref={ref}
          accessibilityLabel={accessibilityLabel ?? placeholder}
          placeholder={placeholder}
          className={inputClassName ?? 'min-h-12 flex-1 rounded-full pl-11 pr-10'}
          {...rest}
        />
        <SearchField.ClearButton accessibilityLabel={clearLabel} className="mr-2" />
      </SearchField.Group>
    </SearchField>
  );
});

export default SearchInput;
