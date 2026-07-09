// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ComponentProps, ReactNode } from 'react';
import { Alert as HeroUIAlert } from '@heroui/react/alert';

type HeroUIAlertProps = ComponentProps<typeof HeroUIAlert>;

type AlertClassNames = {
  base?: string;
  content?: string;
  description?: string;
  icon?: string;
  title?: string;
};

export type AlertProps = Omit<HeroUIAlertProps, 'children' | 'status'> & {
  children?: ReactNode;
  classNames?: AlertClassNames;
  color?: HeroUIAlertProps['status'] | 'primary' | 'secondary';
  description?: ReactNode;
  endContent?: ReactNode;
  hideIcon?: boolean;
  icon?: ReactNode;
  radius?: string;
  startContent?: ReactNode;
  title?: ReactNode;
  variant?: string;
};

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

function mapStatus(color?: AlertProps['color']): HeroUIAlertProps['status'] {
  switch (color) {
    case 'primary':
      return 'accent';
    case 'secondary':
      return 'default';
    default:
      return color;
  }
}

export function Alert({
  children,
  className,
  classNames,
  color,
  description,
  endContent,
  hideIcon,
  icon,
  radius: _radius,
  startContent,
  title,
  variant: _variant,
  ...props
}: AlertProps) {
  if (children) {
    return (
      <HeroUIAlert {...props} className={combineClasses(classNames?.base, className)} status={mapStatus(color)}>
        {children}
      </HeroUIAlert>
    );
  }

  return (
    <HeroUIAlert {...props} className={combineClasses(classNames?.base, className)} status={mapStatus(color)}>
      {startContent}
      {hideIcon ? null : (
        <HeroUIAlert.Indicator className={classNames?.icon}>
          {icon}
        </HeroUIAlert.Indicator>
      )}
      <HeroUIAlert.Content className={classNames?.content}>
        {title ? <HeroUIAlert.Title className={classNames?.title}>{title}</HeroUIAlert.Title> : null}
        {description ? (
          <HeroUIAlert.Description className={classNames?.description}>
            {description}
          </HeroUIAlert.Description>
        ) : null}
      </HeroUIAlert.Content>
      {endContent}
    </HeroUIAlert>
  );
}
