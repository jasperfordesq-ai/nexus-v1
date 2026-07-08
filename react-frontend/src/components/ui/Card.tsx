// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, {
  type ElementType,
  type HTMLAttributes,
  type MouseEvent,
  type ReactNode,
  type Ref,
} from 'react';
import { Card as HeroUICard, type CardProps as HeroUICardProps } from '@heroui/react/card';

function heroCardBaseClass(variant?: string): string {
  return `card card--${variant ?? 'default'}`;
}

type V2CardShadow = 'none' | 'sm' | 'md' | 'lg';
type V2CardRadius = 'none' | 'sm' | 'md' | 'lg';
type V3CardVariant = NonNullable<HeroUICardProps['variant']>;

export type CardProps = Omit<
  HTMLAttributes<HTMLElement>,
  'children' | 'onClick'
> & {
  as?: ElementType;
  children?: ReactNode;
  className?: string;
  classNames?: {
    base?: string;
    header?: string;
    body?: string;
    footer?: string;
  };
  disableAnimation?: boolean;
  disableRipple?: boolean;
  fullWidth?: boolean;
  isBlurred?: boolean;
  isDisabled?: boolean;
  isFooterBlurred?: boolean;
  isHoverable?: boolean;
  isPressable?: boolean;
  onClick?: (event: MouseEvent<HTMLElement>) => void;
  onPress?: () => void;
  radius?: V2CardRadius;
  shadow?: V2CardShadow;
  to?: unknown;
  href?: string;
  variant?: V3CardVariant;
};

export type CardHeaderProps = HTMLAttributes<HTMLDivElement> & {
  as?: ElementType;
  children?: ReactNode;
};

export type CardBodyProps = HTMLAttributes<HTMLDivElement> & {
  as?: ElementType;
  children?: ReactNode;
};

export type CardFooterProps = HTMLAttributes<HTMLDivElement> & {
  as?: ElementType;
  children?: ReactNode;
};

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

function radiusClass(radius?: V2CardRadius): string | undefined {
  switch (radius) {
    case 'none':
      return 'rounded-none';
    case 'sm':
      return 'rounded-sm';
    case 'md':
      return 'rounded-md';
    case 'lg':
      return 'rounded-lg';
    default:
      return undefined;
  }
}

function shadowClass(shadow?: V2CardShadow): string | undefined {
  switch (shadow) {
    case 'none':
      return 'shadow-none';
    case 'sm':
      return 'shadow-sm';
    case 'md':
      return 'shadow-md';
    case 'lg':
      return 'shadow-lg';
    default:
      return undefined;
  }
}

function CardRoot(
  {
    as: Component,
    children,
    className,
    classNames,
    disableAnimation: _disableAnimation,
    disableRipple: _disableRipple,
    fullWidth,
    isBlurred,
    isDisabled,
    isFooterBlurred,
    isHoverable,
    isPressable,
    onClick,
    onPress,
    radius,
    shadow,
    variant,
    ref,
    ...props
  }: CardProps & { ref?: Ref<HTMLElement> },
) {
    const rootClassName = combineClasses(
      classNames?.base,
      fullWidth && 'w-full',
      radiusClass(radius),
      shadowClass(shadow),
      isBlurred && 'backdrop-blur-md',
      isFooterBlurred && 'relative overflow-hidden',
      isHoverable && 'transition-shadow hover:shadow-md',
      isPressable && 'cursor-pointer',
      isDisabled && 'pointer-events-none opacity-50',
      className,
    );

    const handleClick = (event: MouseEvent<HTMLElement>) => {
      onClick?.(event);

      if (!event.defaultPrevented) {
        onPress?.();
      }
    };

    // A click/press handler on the default (non-`as`) card renders a clickable
    // <div>. Without button semantics that div is mouse-only — invisible to
    // keyboard and screen-reader users (WCAG 2.1.1). Expose it as a button and
    // activate on Enter/Space. (`as`-rendered cards keep the element the caller
    // chose, so their semantics stay the caller's responsibility.)
    const isInteractive = Boolean(onClick || onPress) && !isDisabled;

    const handleKeyDown = (event: React.KeyboardEvent<HTMLElement>) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        event.currentTarget.click();
      }
    };

    const sharedProps = {
      className: rootClassName,
      variant,
      'aria-disabled': isDisabled || undefined,
      onClick: onClick || onPress ? handleClick : undefined,
      ...(isInteractive
        ? { role: 'button', tabIndex: 0, onKeyDown: handleKeyDown }
        : {}),
      ...props,
    };

    if (Component) {
      return (
        <Component
          ref={ref}
          {...props}
          className={combineClasses(heroCardBaseClass(variant), rootClassName)}
          aria-disabled={isDisabled || undefined}
          onClick={onClick || onPress ? handleClick : undefined}
        >
          {children}
        </Component>
      );
    }

    return (
      <HeroUICard ref={ref as React.Ref<HTMLDivElement>} {...(sharedProps as unknown as HeroUICardProps)}>
        {children}
      </HeroUICard>
    );
}

CardRoot.displayName = 'Card';

export function CardHeader({ as: Component, children, className, ...props }: CardHeaderProps) {
  if (Component) {
    return (
      <HeroUICard.Header
        className={className}
        render={(renderProps) => (
          <Component {...renderProps} {...props}>
            {renderProps.children}
          </Component>
        )}
        {...props}
      >
        {children}
      </HeroUICard.Header>
    );
  }

  return (
    <HeroUICard.Header className={className} {...props}>
      {children}
    </HeroUICard.Header>
  );
}

export function CardBody({ as: Component, children, className, ...props }: CardBodyProps) {
  if (Component) {
    return (
      <HeroUICard.Content
        className={className}
        render={(renderProps) => (
          <Component {...renderProps} {...props}>
            {renderProps.children}
          </Component>
        )}
        {...props}
      >
        {children}
      </HeroUICard.Content>
    );
  }

  return (
    <HeroUICard.Content className={className} {...props}>
      {children}
    </HeroUICard.Content>
  );
}

export function CardFooter({ as: Component, children, className, ...props }: CardFooterProps) {
  if (Component) {
    return (
      <HeroUICard.Footer
        className={className}
        render={(renderProps) => (
          <Component {...renderProps} {...props}>
            {renderProps.children}
          </Component>
        )}
        {...props}
      >
        {children}
      </HeroUICard.Footer>
    );
  }

  return (
    <HeroUICard.Footer className={className} {...props}>
      {children}
    </HeroUICard.Footer>
  );
}

export const Card = Object.assign(CardRoot, {
  Header: CardHeader,
  Body: CardBody,
  Content: CardBody,
  Footer: CardFooter,
});
