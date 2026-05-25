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
  Label,
  Switch as HeroUISwitch,
} from '@heroui-v3/react';

type HeroUISwitchProps = ComponentPropsWithoutRef<typeof HeroUISwitch>;
type SwitchColor = 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'danger';

export type SwitchProps = Omit<HeroUISwitchProps, 'children' | 'className' | 'color' | 'onChange'> & {
  children?: ReactNode | HeroUISwitchProps['children'];
  className?: string;
  classNames?: {
    base?: string;
    wrapper?: string;
    thumb?: string;
    thumbIcon?: string;
    label?: string;
    startContent?: string;
    endContent?: string;
  };
  color?: SwitchColor;
  description?: ReactNode;
  disableAnimation?: boolean;
  endContent?: ReactNode;
  onChange?: (isSelected: boolean) => void;
  onValueChange?: (isSelected: boolean) => void;
  startContent?: ReactNode;
  thumbIcon?: ReactNode;
};

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

function colorClass(color?: SwitchColor) {
  switch (color) {
    case 'primary':
      return 'data-[selected=true]:bg-accent';
    case 'secondary':
      return 'data-[selected=true]:bg-default';
    case 'success':
      return 'data-[selected=true]:bg-success';
    case 'warning':
      return 'data-[selected=true]:bg-warning';
    case 'danger':
      return 'data-[selected=true]:bg-danger';
    default:
      return undefined;
  }
}

export const Switch = forwardRef<HTMLLabelElement, SwitchProps>(function Switch({
  children,
  className,
  classNames,
  color,
  description,
  disableAnimation: _disableAnimation,
  endContent,
  onChange,
  onValueChange,
  startContent,
  thumbIcon,
  ...props
}, ref) {
  if (typeof children === 'function') {
    return (
      <HeroUISwitch
        ref={ref}
        className={combineClasses(classNames?.base, className)}
        onChange={onChange ?? onValueChange}
        {...props}
      >
        {children}
      </HeroUISwitch>
    );
  }

  return (
    <HeroUISwitch
      ref={ref}
      className={combineClasses(classNames?.base, className)}
      onChange={onChange ?? onValueChange}
      {...props}
    >
      <HeroUISwitch.Control className={combineClasses(classNames?.wrapper, colorClass(color))}>
        {startContent ? <span className={classNames?.startContent}>{startContent}</span> : null}
        <HeroUISwitch.Thumb className={classNames?.thumb}>
          {thumbIcon ? (
            <HeroUISwitch.Icon className={classNames?.thumbIcon}>
              {thumbIcon}
            </HeroUISwitch.Icon>
          ) : null}
        </HeroUISwitch.Thumb>
        {endContent ? <span className={classNames?.endContent}>{endContent}</span> : null}
      </HeroUISwitch.Control>
      {children || description ? (
        <HeroUISwitch.Content>
          {children ? <Label className={classNames?.label}>{children}</Label> : null}
          {description ? <Description>{description}</Description> : null}
        </HeroUISwitch.Content>
      ) : null}
    </HeroUISwitch>
  );
});

Switch.displayName = 'Switch';
