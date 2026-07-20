// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  type ComponentPropsWithoutRef,
  type ReactNode,
  type Ref,
} from 'react';
import { Description } from '@heroui/react/description';
import { Label } from '@heroui/react/label';
import { Switch as HeroUISwitch } from '@heroui/react/switch';

type HeroUISwitchProps = ComponentPropsWithoutRef<typeof HeroUISwitch>;
type SwitchColor = 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'danger';

export type SwitchProps = Omit<HeroUISwitchProps, 'children' | 'className' | 'color' | 'onChange'> & {
  children?: ReactNode | HeroUISwitchProps['children'];
  className?: string;
  classNames?: {
    base?: string;
    content?: string;
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

function selectedControlClass(color?: SwitchColor) {
  switch (color) {
    case 'primary':
      return '!bg-accent';
    case 'secondary':
      return '!bg-accent-soft';
    case 'success':
      return '!bg-success';
    case 'warning':
      return '!bg-warning';
    case 'danger':
      return '!bg-danger';
    default:
      return '!bg-accent';
  }
}

function selectedThumbClass(size: HeroUISwitchProps['size']) {
  if (size === 'sm') {
    return '!ms-[calc(100%-1.15625rem)] !bg-accent-foreground !text-accent';
  }

  if (size === 'lg') {
    return '!ms-[calc(100%-1.84375rem)] !bg-accent-foreground !text-accent';
  }

  return '!ms-[calc(100%-1.5rem)] !bg-accent-foreground !text-accent';
}

export function Switch({
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
  ref,
  ...props
}: SwitchProps & { ref?: Ref<HTMLLabelElement> }) {
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
      {({ isSelected }) => (
        <>
          <HeroUISwitch.Content className={classNames?.content}>
            <HeroUISwitch.Control
              className={combineClasses(
                classNames?.wrapper,
                isSelected && selectedControlClass(color),
              )}
              data-selected={isSelected}
            >
              {startContent ? <span className={classNames?.startContent}>{startContent}</span> : null}
              <HeroUISwitch.Thumb
                className={combineClasses(
                  classNames?.thumb,
                  isSelected && selectedThumbClass(props.size),
                )}
                data-selected={isSelected}
              >
                {thumbIcon ? (
                  <HeroUISwitch.Icon className={classNames?.thumbIcon}>
                    {thumbIcon}
                  </HeroUISwitch.Icon>
                ) : null}
              </HeroUISwitch.Thumb>
              {endContent ? <span className={classNames?.endContent}>{endContent}</span> : null}
            </HeroUISwitch.Control>
            {children ? <Label className={classNames?.label}>{children}</Label> : null}
          </HeroUISwitch.Content>
          {description ? <Description>{description}</Description> : null}
        </>
      )}
    </HeroUISwitch>
  );
}

Switch.displayName = 'Switch';
