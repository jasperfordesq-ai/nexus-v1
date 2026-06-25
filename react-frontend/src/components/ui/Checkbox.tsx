// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  useId,
  type ComponentPropsWithoutRef,
  type ReactNode,
  type Ref,
} from 'react';
import {
  Checkbox as HeroUICheckbox,
  CheckboxGroup as HeroUICheckboxGroup,
  Description,
  Label,
} from '@heroui/react';

type HeroUICheckboxProps = ComponentPropsWithoutRef<typeof HeroUICheckbox>;
type HeroUICheckboxGroupProps = ComponentPropsWithoutRef<typeof HeroUICheckboxGroup>;
type CheckboxColor = 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'danger';
type CheckboxSize = 'sm' | 'md' | 'lg';
type CheckboxRadius = 'none' | 'sm' | 'md' | 'lg' | 'full';

export type CheckboxProps = Omit<HeroUICheckboxProps, 'children' | 'className' | 'color' | 'onChange'> & {
  children?: ReactNode | HeroUICheckboxProps['children'];
  className?: string;
  classNames?: {
    base?: string;
    wrapper?: string;
    icon?: string;
    label?: string;
  };
  color?: CheckboxColor;
  description?: ReactNode;
  disableAnimation?: boolean;
  icon?: ReactNode;
  lineThrough?: boolean;
  onChange?: (isSelected: boolean) => void;
  onValueChange?: (isSelected: boolean) => void;
  radius?: CheckboxRadius;
  size?: CheckboxSize;
};

export type CheckboxGroupProps = Omit<HeroUICheckboxGroupProps, 'children' | 'className' | 'onChange'> & {
  children?: ReactNode | HeroUICheckboxGroupProps['children'];
  className?: string;
  classNames?: {
    base?: string;
    wrapper?: string;
    label?: string;
    description?: string;
  };
  description?: ReactNode;
  label?: ReactNode;
  onChange?: (value: string[]) => void;
  onValueChange?: (value: string[]) => void;
  orientation?: 'horizontal' | 'vertical';
  size?: CheckboxSize;
};

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

function colorClass(color?: CheckboxColor) {
  switch (color) {
    case 'primary':
      return 'data-[selected=true]:border-accent data-[selected=true]:bg-accent';
    case 'secondary':
      return 'data-[selected=true]:border-accent data-[selected=true]:bg-accent-soft';
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

function sizeClass(size?: CheckboxSize) {
  switch (size) {
    case 'sm':
      return 'size-3';
    case 'lg':
      return 'size-5';
    default:
      return undefined;
  }
}

function radiusClass(radius?: CheckboxRadius) {
  switch (radius) {
    case 'none':
      return 'rounded-none before:rounded-none';
    case 'sm':
      return 'rounded-sm before:rounded-sm';
    case 'lg':
      return 'rounded-lg before:rounded-lg';
    case 'full':
      return 'rounded-full before:rounded-full';
    default:
      return undefined;
  }
}

export function Checkbox({
  children,
  className,
  classNames,
  color,
  description,
  disableAnimation: _disableAnimation,
  icon,
  id,
  lineThrough,
  onChange,
  onValueChange,
  radius,
  size,
  // Default `slot` to null so a standalone Checkbox rendered inside a Table /
  // collection cell opts OUT of React Aria's slotted CheckboxContext (the
  // selection checkbox). Without this, React Aria throws "A slot prop is
  // required. Valid slot names are 'selection'." Callers can still pass an
  // explicit slot when they genuinely want the collection's selection slot.
  slot = null,
  ref,
  ...props
}: CheckboxProps & { ref?: Ref<HTMLLabelElement> }) {
  const generatedId = useId();
  const checkboxId = id ?? generatedId;

  if (typeof children === 'function') {
    return (
      <HeroUICheckbox
        ref={ref}
        className={combineClasses(classNames?.base, className)}
        id={checkboxId}
        onChange={onChange ?? onValueChange}
        slot={slot}
        {...props}
      >
        {children}
      </HeroUICheckbox>
    );
  }

  return (
    <HeroUICheckbox
      ref={ref}
      className={combineClasses(classNames?.base, className)}
      id={checkboxId}
      onChange={onChange ?? onValueChange}
      slot={slot}
      {...props}
    >
      <HeroUICheckbox.Control
        className={combineClasses(classNames?.wrapper, colorClass(color), sizeClass(size), radiusClass(radius))}
      >
        <HeroUICheckbox.Indicator className={classNames?.icon}>
          {icon}
        </HeroUICheckbox.Indicator>
      </HeroUICheckbox.Control>
      {children || description ? (
        <HeroUICheckbox.Content>
          {children ? (
            <Label className={combineClasses(lineThrough && 'line-through', classNames?.label)} htmlFor={checkboxId}>
              {children}
            </Label>
          ) : null}
          {description ? <Description>{description}</Description> : null}
        </HeroUICheckbox.Content>
      ) : null}
    </HeroUICheckbox>
  );
}

Checkbox.displayName = 'Checkbox';

export function CheckboxGroup({
  children,
  className,
  classNames,
  description,
  label,
  onChange,
  onValueChange,
  orientation,
  size: _size,
  ref,
  ...props
}: CheckboxGroupProps & { ref?: Ref<HTMLDivElement> }) {
  if (typeof children === 'function') {
    return (
      <HeroUICheckboxGroup
        ref={ref}
        className={combineClasses(
          classNames?.base,
          classNames?.wrapper,
          orientation === 'horizontal' && 'flex-row flex-wrap',
          className,
        )}
        onChange={onChange ?? onValueChange}
        {...props}
      >
        {children}
      </HeroUICheckboxGroup>
    );
  }

  return (
    <HeroUICheckboxGroup
      ref={ref}
      className={combineClasses(
        classNames?.base,
        classNames?.wrapper,
        orientation === 'horizontal' && 'flex-row flex-wrap',
        className,
      )}
      onChange={onChange ?? onValueChange}
      {...props}
    >
      {label ? <Label className={classNames?.label}>{label}</Label> : null}
      {description ? <Description className={classNames?.description}>{description}</Description> : null}
      {children}
    </HeroUICheckboxGroup>
  );
}

CheckboxGroup.displayName = 'CheckboxGroup';
