// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  type ChangeEvent,
  type CSSProperties,
  type ReactNode,
  type Ref,
  type TextareaHTMLAttributes,
} from 'react';
import {
  Description,
  FieldError,
  Label,
  TextArea as HeroUITextArea,
  TextField,
  type TextAreaProps as HeroUITextAreaProps,
  type TextFieldProps,
} from '@heroui/react';

type LegacyVariant = 'flat' | 'bordered' | 'underlined' | 'faded';
type V3Variant = NonNullable<HeroUITextAreaProps['variant']>;

export type TextareaProps = Omit<
  TextareaHTMLAttributes<HTMLTextAreaElement>,
  'color' | 'onChange' | 'size'
> & {
  className?: string;
  classNames?: {
    base?: string;
    input?: string;
    inputWrapper?: string;
    label?: string;
    description?: string;
    errorMessage?: string;
  };
  color?: 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'danger';
  description?: ReactNode;
  errorMessage?: ReactNode | ((value: string) => ReactNode);
  fullWidth?: boolean;
  isDisabled?: boolean;
  isInvalid?: boolean;
  isReadOnly?: boolean;
  isRequired?: boolean;
  label?: ReactNode;
  labelPlacement?: 'inside' | 'outside' | 'outside-left';
  maxRows?: number;
  minRows?: number;
  onChange?: (event: ChangeEvent<HTMLTextAreaElement>) => void;
  onValueChange?: (value: string) => void;
  radius?: 'none' | 'sm' | 'md' | 'lg' | 'full';
  size?: 'sm' | 'md' | 'lg';
  startContent?: ReactNode;
  endContent?: ReactNode;
  validate?: TextFieldProps['validate'];
  variant?: LegacyVariant | V3Variant;
};

function mapVariant(variant?: TextareaProps['variant']): V3Variant {
  return variant === 'bordered' || variant === 'underlined' || variant === 'faded'
    ? 'secondary'
    : 'primary';
}

function sizeClass(size?: TextareaProps['size']): string | undefined {
  switch (size) {
    case 'sm':
      return 'px-3 py-2 text-sm';
    case 'lg':
      return 'px-4 py-3 text-base';
    default:
      return undefined;
  }
}

function radiusClass(radius?: TextareaProps['radius']): string | undefined {
  switch (radius) {
    case 'none':
      return 'rounded-none';
    case 'sm':
      return 'rounded-sm';
    case 'md':
      return 'rounded-md';
    case 'lg':
      return 'rounded-lg';
    case 'full':
      return 'rounded-full';
    default:
      return undefined;
  }
}

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

export function Textarea({
  className,
  classNames,
  color: _color,
  description,
  errorMessage,
  fullWidth,
  isDisabled,
  isInvalid,
  isReadOnly,
  isRequired,
  label,
  labelPlacement: _labelPlacement,
  maxRows,
  minRows,
  onChange,
  onValueChange,
  radius,
  rows,
  size,
  startContent,
  endContent,
  style,
  validate,
  variant,
  ref,
  ...props
}: TextareaProps & { ref?: Ref<HTMLTextAreaElement> }) {
  const handleTextareaChange = (event: ChangeEvent<HTMLTextAreaElement>) => {
    onChange?.(event);
    onValueChange?.(event.target.value);
  };

  const textArea = (
    <HeroUITextArea
      className={combineClasses(
        classNames?.inputWrapper,
        classNames?.input,
        sizeClass(size),
        radiusClass(radius),
      )}
      disabled={isDisabled}
      fullWidth={fullWidth}
      readOnly={isReadOnly}
      required={isRequired}
      ref={ref}
      rows={rows ?? minRows}
      style={{
        ...(maxRows ? { maxHeight: `${maxRows * 1.5}rem` } : {}),
      }}
      variant={mapVariant(variant)}
      onChange={handleTextareaChange}
      {...props}
    />
  );
  const textareaWithContent = startContent || endContent ? (
    <div
      className={combineClasses(
        'flex items-start gap-2',
        classNames?.inputWrapper,
        sizeClass(size),
        radiusClass(radius),
      )}
    >
      {startContent ? <span className="mt-2 shrink-0">{startContent}</span> : null}
      {textArea}
      {endContent ? <span className="mt-2 shrink-0">{endContent}</span> : null}
    </div>
  ) : textArea;

  const fieldStyle = style as CSSProperties | undefined;

  if (!label && !description && !errorMessage && !isInvalid && !isRequired && !validate && !startContent && !endContent) {
    return (
      <HeroUITextArea
        className={combineClasses(
          classNames?.base,
          classNames?.inputWrapper,
          classNames?.input,
          sizeClass(size),
          radiusClass(radius),
          className,
        )}
        disabled={isDisabled}
        fullWidth={fullWidth}
        readOnly={isReadOnly}
        required={isRequired}
        ref={ref}
        rows={rows ?? minRows}
        style={{
          ...(fieldStyle ?? {}),
          ...(maxRows ? { maxHeight: `${maxRows * 1.5}rem` } : {}),
        }}
        variant={mapVariant(variant)}
        onChange={handleTextareaChange}
        {...props}
      />
    );
  }

  return (
    <TextField
      className={combineClasses('flex flex-col gap-1', classNames?.base, className)}
      fullWidth={fullWidth}
      isDisabled={isDisabled}
      isInvalid={isInvalid}
      isReadOnly={isReadOnly}
      isRequired={isRequired}
      name={props.name}
      style={fieldStyle}
      validate={validate}
      variant={mapVariant(variant)}
    >
      {label ? <Label className={classNames?.label}>{label}</Label> : null}
      {textareaWithContent}
      {description ? (
        <Description className={classNames?.description}>{description}</Description>
      ) : null}
      {errorMessage ? (
        <FieldError className={classNames?.errorMessage}>
          {typeof errorMessage === 'function'
            ? errorMessage(String(props.value ?? ''))
            : errorMessage}
        </FieldError>
      ) : null}
    </TextField>
  );
}
