// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { type ElementType, type MouseEvent, type ReactNode, type Ref } from 'react';
import type { ButtonHTMLAttributes } from 'react';
import {
  Button as HeroUIButton,
  Spinner as HeroUISpinner,
  type ButtonProps as HeroUIButtonProps,
} from '@heroui/react';

function heroButtonClasses(opts: {
  variant?: string;
  size?: string;
  fullWidth?: boolean;
  isIconOnly?: boolean;
}): string {
  const v = opts.variant ?? 'primary';
  const s = opts.size ?? 'md';
  const classes = [`button`, `button--${v}`, `button--${s}`];
  if (opts.fullWidth) classes.push('button--full-width');
  if (opts.isIconOnly) classes.push('button--icon-only');
  return classes.join(' ');
}

type V2ButtonColor =
  | 'default'
  | 'primary'
  | 'secondary'
  | 'success'
  | 'warning'
  | 'danger';

type V2ButtonVariant =
  | 'solid'
  | 'bordered'
  | 'light'
  | 'flat'
  | 'faded'
  | 'shadow'
  | 'ghost';

type V3ButtonVariant = NonNullable<HeroUIButtonProps['variant']>;
type NativeButtonProps = Omit<
  ButtonHTMLAttributes<HTMLButtonElement>,
  'children' | 'color'
>;

export type ButtonProps = Omit<
  HeroUIButtonProps,
  | 'children'
  | 'color'
  | 'variant'
  | 'isPending'
> &
  NativeButtonProps & {
  as?: ElementType;
  children?: HeroUIButtonProps['children'];
  download?: unknown;
  href?: string;
  rel?: string;
  target?: string;
  to?: unknown;
  color?: V2ButtonColor;
  variant?: V2ButtonVariant | V3ButtonVariant;
  isLoading?: boolean;
  isPending?: boolean;
  startContent?: ReactNode;
  endContent?: ReactNode;
  spinner?: ReactNode;
  spinnerPlacement?: 'start' | 'end';
  disableRipple?: boolean;
  disableAnimation?: boolean;
  radius?: 'none' | 'sm' | 'md' | 'lg' | 'full';
  classNames?: { base?: string };
};

function mapVariant(
  color?: V2ButtonColor,
  variant?: ButtonProps['variant'],
): V3ButtonVariant {
  if (
    variant === 'primary' ||
    variant === 'secondary' ||
    variant === 'tertiary' ||
    variant === 'outline' ||
    variant === 'danger' ||
    variant === 'danger-soft'
  ) {
    return variant;
  }

  if (variant === 'ghost') {
    return 'ghost';
  }

  if (color === 'danger') {
    return variant === 'flat' || variant === 'light' ? 'danger-soft' : 'danger';
  }

  if (color === 'secondary') {
    return 'secondary';
  }

  if (variant === 'bordered' || variant === 'faded') {
    return 'secondary';
  }

  if (variant === 'light' || variant === 'flat') {
    return 'tertiary';
  }

  return 'primary';
}

function radiusClass(radius?: ButtonProps['radius']): string | undefined {
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

export function Button(
  {
    as: Component,
    children,
    className,
    classNames,
    color,
    disableAnimation: _disableAnimation,
    disableRipple: _disableRipple,
    endContent,
    fullWidth,
    isDisabled,
    isIconOnly,
    isLoading,
    isPending,
    onPress,
    radius,
    size,
    spinner,
    spinnerPlacement = 'start',
    startContent,
    type,
    variant,
    disabled,
    ref,
    ...props
  }: ButtonProps & { ref?: Ref<HTMLButtonElement> },
) {
    const pending = isPending ?? isLoading ?? false;
    const disabledState = isDisabled ?? disabled ?? false;
    const mappedVariant = mapVariant(color, variant);
    const pendingIndicator = spinner ?? <HeroUISpinner color="current" size="sm" />;
    const content = typeof children === 'function'
      ? children
      : ({ isPending: renderPending }: { isPending: boolean }) => (
          <>
            {renderPending && spinnerPlacement === 'start' ? pendingIndicator : null}
            {!renderPending || spinnerPlacement !== 'start' ? startContent : null}
            {children}
            {!renderPending || spinnerPlacement !== 'end' ? endContent : null}
            {renderPending && spinnerPlacement === 'end' ? pendingIndicator : null}
          </>
        );

    const sharedProps = {
      className: combineClasses(classNames?.base, radiusClass(radius), className),
      fullWidth,
      isDisabled: disabledState,
      isIconOnly,
      isPending: pending,
      size,
      type,
      variant: mappedVariant,
      onPress,
      ...props,
    } as HeroUIButtonProps;

    if (Component) {
      const directChildren = typeof content === 'function'
        ? content({
            isDisabled: disabledState,
            isPending: pending,
          } as never)
        : content;
      const directClassName = combineClasses(
        heroButtonClasses({
          fullWidth,
          isIconOnly,
          size,
          variant: mappedVariant,
        }),
        fullWidth && 'w-full',
        classNames?.base,
        radiusClass(radius),
        className,
      );
      const handleClick = (event: MouseEvent<HTMLElement>) => {
        if (disabledState || pending) {
          event.preventDefault();
          event.stopPropagation();
          return;
        }

        props.onClick?.(event as MouseEvent<HTMLButtonElement>);
        onPress?.(event as never);
      };
      const directProps = {
        ...props,
        'aria-disabled': disabledState || undefined,
        'data-disabled': disabledState || undefined,
        'data-pending': pending || undefined,
        className: directClassName,
        onClick: handleClick,
        ref,
      };

      return (
        <Component
          {...directProps}
          {...(Component === 'button'
            ? { disabled: disabledState, type: type ?? 'button' }
            : null)}
        >
          {directChildren}
        </Component>
      );
    }

    return (
      <HeroUIButton
        ref={ref}
        {...sharedProps}
      >
        {content}
      </HeroUIButton>
    );
}

Button.displayName = 'Button';
