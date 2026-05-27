// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  type ChangeEvent,
  type CSSProperties,
  forwardRef,
  type InputHTMLAttributes,
  type ReactNode,
} from 'react';
import {
  Description,
  FieldError,
  Input as HeroUIInput,
  Label,
  TextField,
  type InputProps as HeroUIInputProps,
  type TextFieldProps,
} from '@heroui/react';

type LegacyVariant = 'flat' | 'bordered' | 'underlined' | 'faded';
type V3Variant = NonNullable<HeroUIInputProps['variant']>;

export type InputProps = Omit<
  InputHTMLAttributes<HTMLInputElement>,
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
  isClearable?: boolean;
  isInvalid?: boolean;
  isReadOnly?: boolean;
  isRequired?: boolean;
  label?: ReactNode;
  labelPlacement?: 'inside' | 'outside' | 'outside-left';
  onChange?: (event: ChangeEvent<HTMLInputElement>) => void;
  onClear?: () => void;
  onValueChange?: (value: string) => void;
  radius?: 'none' | 'sm' | 'md' | 'lg' | 'full';
  size?: 'sm' | 'md' | 'lg';
  startContent?: ReactNode;
  endContent?: ReactNode;
  validate?: TextFieldProps['validate'];
  variant?: LegacyVariant | V3Variant;
};

function mapVariant(variant?: InputProps['variant']): V3Variant {
  return variant === 'bordered' || variant === 'underlined' || variant === 'faded'
    ? 'secondary'
    : 'primary';
}

function sizeClass(size?: InputProps['size']): string | undefined {
  switch (size) {
    case 'sm':
      return 'min-h-8 px-3 py-1 text-sm';
    case 'lg':
      return 'min-h-12 px-4 py-3 text-base';
    default:
      return undefined;
  }
}

function radiusClass(radius?: InputProps['radius']): string | undefined {
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

function decoratedInputClass(className?: string): string | undefined {
  return combineClasses(
    'h-full min-h-7 w-full min-w-0 flex-1 self-stretch rounded-none border-0 bg-transparent px-0 py-0 leading-7 shadow-none outline-none',
    'focus-visible:border-transparent focus-visible:ring-0',
    className,
  );
}

function decoratedWrapperClass(
  className: string | undefined,
  size: InputProps['size'],
  radius: InputProps['radius'],
): string | undefined {
  return combineClasses(
    'flex min-h-10 w-full items-center gap-2 px-3 py-2',
    sizeClass(size),
    radiusClass(radius),
    className,
  );
}

export const Input = forwardRef<HTMLInputElement, InputProps>(function Input({
  className,
  classNames,
  color: _color,
  description,
  errorMessage,
  fullWidth,
  isClearable,
  isDisabled,
  isInvalid,
  isReadOnly,
  isRequired,
  label,
  labelPlacement: _labelPlacement,
  onChange,
  onClear,
  onValueChange,
  radius,
  size,
  startContent,
  endContent,
  style,
  validate,
  variant,
  ...props
}: InputProps, ref) {
  const handleInputChange = (event: ChangeEvent<HTMLInputElement>) => {
    onChange?.(event);
    onValueChange?.(event.target.value);
  };

  const clearButton = isClearable ? (
    <button
      type="button"
      className="shrink-0 px-1 text-sm text-muted hover:text-foreground"
      aria-label="Clear"
      onClick={() => {
        onClear?.();
        onValueChange?.('');
      }}
    >
      x
    </button>
  ) : null;
  const trailingContent = endContent ?? clearButton;

  const inputElement = (
    <HeroUIInput
      className={combineClasses(
        startContent || trailingContent
          ? decoratedInputClass(classNames?.input)
          : classNames?.input,
        !startContent && !endContent && classNames?.inputWrapper,
        !(startContent || trailingContent) && sizeClass(size),
        !startContent && !endContent && radiusClass(radius),
      )}
      disabled={isDisabled}
      fullWidth={fullWidth}
      readOnly={isReadOnly}
      required={isRequired}
      ref={ref}
      variant={mapVariant(variant)}
      onChange={handleInputChange}
      {...props}
    />
  );

  const input = startContent || trailingContent ? (
    <div
      className={decoratedWrapperClass(
        classNames?.inputWrapper,
        size,
        radius,
      )}
    >
      {startContent ? <span className="shrink-0">{startContent}</span> : null}
      {inputElement}
      {trailingContent ? <span className="shrink-0">{trailingContent}</span> : null}
    </div>
  ) : inputElement;

  const fieldStyle = style as CSSProperties | undefined;

  if (!label && !description && !errorMessage && !isInvalid && !isRequired && !validate) {
    if (startContent || trailingContent) {
      return (
        <div
          className={combineClasses(
            decoratedWrapperClass(
              classNames?.inputWrapper,
              size,
              radius,
            ),
            classNames?.base,
            className,
          )}
          style={fieldStyle}
        >
          {startContent ? <span className="shrink-0">{startContent}</span> : null}
          <HeroUIInput
            aria-invalid={isInvalid || undefined}
            className={decoratedInputClass(classNames?.input)}
            disabled={isDisabled}
            fullWidth={fullWidth}
            readOnly={isReadOnly}
            required={isRequired}
            ref={ref}
            variant={mapVariant(variant)}
            onChange={handleInputChange}
            {...props}
          />
          {trailingContent ? <span className="shrink-0">{trailingContent}</span> : null}
        </div>
      );
    }

    return (
      <HeroUIInput
        aria-invalid={isInvalid || undefined}
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
        style={fieldStyle}
        variant={mapVariant(variant)}
        onChange={handleInputChange}
        {...props}
      />
    );
  }

  return (
    <TextField
      className={combineClasses('flex w-full flex-col gap-1', classNames?.base, className)}
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
      {input}
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
});
