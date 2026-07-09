// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ComponentProps, ReactNode } from 'react';
import { Description } from '@heroui/react/description';
import { FieldError } from '@heroui/react/field-error';
import { Label } from '@heroui/react/label';
import { TimeField } from '@heroui/react/time-field';
import { type TimeValue } from '@heroui/react/rac';
import type { Time as InternationalizedTime } from '@internationalized/date';
import { cn } from '@/lib/helpers';

type TimeFieldProps = ComponentProps<typeof TimeField>;

interface TimeInputClassNames {
  base?: string;
  description?: string;
  errorMessage?: string;
  input?: string;
  inputWrapper?: string;
  label?: string;
}

export type TimeInputValue = TimeValue | InternationalizedTime;

export interface TimeInputProps extends Omit<TimeFieldProps, 'children' | 'className' | 'defaultValue' | 'maxValue' | 'minValue' | 'onChange' | 'placeholderValue' | 'value'> {
  className?: string;
  classNames?: TimeInputClassNames;
  description?: ReactNode;
  endContent?: ReactNode;
  errorMessage?: ReactNode;
  label?: ReactNode;
  defaultValue?: TimeInputValue | null;
  maxValue?: TimeInputValue | null;
  minValue?: TimeInputValue | null;
  onChange?: (value: TimeInputValue | null) => void;
  placeholderValue?: TimeInputValue | null;
  size?: 'sm' | 'md' | 'lg';
  startContent?: ReactNode;
  value?: TimeInputValue | null;
  variant?: 'flat' | 'bordered' | 'faded' | 'underlined' | 'primary' | 'secondary';
}

function mapVariant(variant: TimeInputProps['variant']): ComponentProps<typeof TimeField.Group>['variant'] {
  return variant === 'faded' || variant === 'underlined' || variant === 'secondary'
    ? 'secondary'
    : 'primary';
}

export function TimeInput({
  className,
  classNames,
  defaultValue,
  description,
  endContent,
  errorMessage,
  isInvalid,
  label,
  maxValue,
  minValue,
  onChange,
  placeholderValue,
  size: _size,
  startContent,
  value,
  variant,
  ...props
}: TimeInputProps) {
  return (
    <TimeField
      {...props}
      className={cn(props.fullWidth && 'w-full', classNames?.base, className)}
      defaultValue={defaultValue as TimeFieldProps['defaultValue']}
      maxValue={maxValue as TimeFieldProps['maxValue']}
      minValue={minValue as TimeFieldProps['minValue']}
      placeholderValue={placeholderValue as TimeFieldProps['placeholderValue']}
      value={value as TimeFieldProps['value']}
      isInvalid={isInvalid}
      onChange={onChange as TimeFieldProps['onChange']}
    >
      {label && <Label className={classNames?.label}>{label}</Label>}
      <TimeField.Group
        className={cn(props.fullWidth && 'w-full', classNames?.inputWrapper)}
        variant={mapVariant(variant)}
      >
        {startContent && <TimeField.Prefix>{startContent}</TimeField.Prefix>}
        <TimeField.Input className={classNames?.input}>
          {(segment) => <TimeField.Segment segment={segment} />}
        </TimeField.Input>
        {endContent && <TimeField.Suffix>{endContent}</TimeField.Suffix>}
      </TimeField.Group>
      {isInvalid && errorMessage ? (
        <FieldError className={classNames?.errorMessage}>{errorMessage}</FieldError>
      ) : (
        description && <Description className={classNames?.description}>{description}</Description>
      )}
    </TimeField>
  );
}
