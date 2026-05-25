// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  type ComponentType,
  forwardRef,
  type ElementType,
  type HTMLAttributes,
  type MouseEvent,
  type ReactNode,
} from 'react';
import { Card as HeroUICard, type CardProps as HeroUICardProps } from '@heroui/react';

const HeroUICardRoot = HeroUICard as ComponentType<any>;

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

export const Card = forwardRef<HTMLElement, CardProps>(
  (
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
      ...props
    },
    ref,
  ) => {
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

    const sharedProps = {
      className: rootClassName,
      variant,
      'aria-disabled': isDisabled || undefined,
      onClick: onClick || onPress ? handleClick : undefined,
      ...props,
    };

    if (Component) {
      return (
        <HeroUICardRoot
          ref={ref}
          {...sharedProps}
          render={(renderProps: { children?: ReactNode }) => (
            <Component {...renderProps} {...props}>
              {renderProps.children}
            </Component>
          )}
        >
          {children}
        </HeroUICardRoot>
      );
    }

    return (
      <HeroUICardRoot ref={ref} {...sharedProps}>
        {children}
      </HeroUICardRoot>
    );
  },
);

Card.displayName = 'Card';

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
