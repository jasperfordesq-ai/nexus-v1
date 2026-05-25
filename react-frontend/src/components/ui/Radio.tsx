// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  forwardRef,
  type ComponentPropsWithoutRef,
  type ReactNode,
} from 'react';
import {
  Description,
  FieldError,
  Label,
  Radio as HeroUIRadio,
  RadioGroup as HeroUIRadioGroup,
} from '@heroui/react';

type HeroUIRadioProps = ComponentPropsWithoutRef<typeof HeroUIRadio>;
type HeroUIRadioGroupProps = ComponentPropsWithoutRef<typeof HeroUIRadioGroup>;
type RadioColor = 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'danger';
type RadioSize = 'sm' | 'md' | 'lg';

export type RadioProps = Omit<HeroUIRadioProps, 'children' | 'className' | 'color'> & {
  children?: ReactNode | HeroUIRadioProps['children'];
  className?: string;
  classNames?: {
    base?: string;
    wrapper?: string;
    control?: string;
    labelWrapper?: string;
    label?: string;
    description?: string;
  };
  color?: RadioColor;
  description?: ReactNode;
  disableAnimation?: boolean;
  size?: RadioSize;
};

export type RadioGroupProps = Omit<HeroUIRadioGroupProps, 'children' | 'className' | 'onChange'> & {
  children?: ReactNode | HeroUIRadioGroupProps['children'];
  className?: string;
  classNames?: {
    base?: string;
    wrapper?: string;
    label?: string;
    description?: string;
    errorMessage?: string;
  };
  description?: ReactNode;
  errorMessage?: ReactNode;
  label?: ReactNode;
  onChange?: (value: string) => void;
  onValueChange?: (value: string) => void;
  size?: RadioSize;
};

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

function colorClass(color?: RadioColor) {
  switch (color) {
    case 'primary':
      return 'data-[selected=true]:border-accent data-[selected=true]:bg-accent';
    case 'secondary':
      return 'data-[selected=true]:border-default data-[selected=true]:bg-default';
    case 'success':
      return 'data-[selected=true]:border-success data-[selected=true]:bg-success';
    case 'warning':
      return 'data-[selected=true]:border-warning data-[selected=true]:bg-warning';
    case 'danger':
      return 'data-[selected=true]:border-danger data-[selected=true]:bg-danger';
    default:
      return undefined;
  }
}

function sizeClass(size?: RadioSize) {
  switch (size) {
    case 'sm':
      return 'size-3';
    case 'lg':
      return 'size-5';
    default:
      return undefined;
  }
}

export const Radio = forwardRef<HTMLLabelElement, RadioProps>(function Radio({
  children,
  className,
  classNames,
  color,
  description,
  disableAnimation: _disableAnimation,
  size,
  ...props
}, ref) {
  if (typeof children === 'function') {
    return (
      <HeroUIRadio
        ref={ref}
        className={combineClasses(classNames?.base, className)}
        {...props}
      >
        {children}
      </HeroUIRadio>
    );
  }

  return (
    <HeroUIRadio
      ref={ref}
      className={combineClasses(classNames?.base, className)}
      {...props}
    >
      <HeroUIRadio.Control className={combineClasses(classNames?.wrapper, classNames?.control, colorClass(color), sizeClass(size))}>
        <HeroUIRadio.Indicator />
      </HeroUIRadio.Control>
      {children || description ? (
        <HeroUIRadio.Content className={classNames?.labelWrapper}>
          {children ? <Label className={classNames?.label}>{children}</Label> : null}
          {description ? <Description className={classNames?.description}>{description}</Description> : null}
        </HeroUIRadio.Content>
      ) : null}
    </HeroUIRadio>
  );
});

Radio.displayName = 'Radio';

export const RadioGroup = forwardRef<HTMLDivElement, RadioGroupProps>(function RadioGroup({
  children,
  className,
  classNames,
  description,
  errorMessage,
  label,
  onChange,
  onValueChange,
  size: _size,
  ...props
}, ref) {
  if (typeof children === 'function') {
    return (
      <HeroUIRadioGroup
        ref={ref}
        className={combineClasses(classNames?.base, classNames?.wrapper, className)}
        onChange={onChange ?? onValueChange}
        {...props}
      >
        {children}
      </HeroUIRadioGroup>
    );
  }

  return (
    <HeroUIRadioGroup
      ref={ref}
      className={combineClasses(classNames?.base, classNames?.wrapper, className)}
      onChange={onChange ?? onValueChange}
      {...props}
    >
      {label ? <Label className={classNames?.label}>{label}</Label> : null}
      {description ? <Description className={classNames?.description}>{description}</Description> : null}
      {children}
      {errorMessage ? <FieldError className={classNames?.errorMessage}>{errorMessage}</FieldError> : null}
    </HeroUIRadioGroup>
  );
});

RadioGroup.displayName = 'RadioGroup';
