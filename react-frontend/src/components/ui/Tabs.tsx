// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  Children,
  isValidElement,
  type ComponentPropsWithoutRef,
  type HTMLAttributes,
  type Key,
  type ReactElement,
  type ReactNode,
} from 'react';
import { Tabs as HeroUITabs } from '@heroui-v3/react';

type HeroUITabsProps = ComponentPropsWithoutRef<typeof HeroUITabs>;
type TabsVariant = 'solid' | 'bordered' | 'light' | 'underlined' | 'primary' | 'secondary';

export type TabProps = Omit<HTMLAttributes<HTMLDivElement>, 'title'> & {
  children?: ReactNode;
  isDisabled?: boolean;
  title?: ReactNode;
};

export type TabsProps = Omit<HeroUITabsProps, 'children' | 'className' | 'orientation' | 'variant'> & {
  'aria-label'?: string;
  children?: ReactNode;
  className?: string;
  classNames?: {
    base?: string;
    tabList?: string;
    tab?: string;
    tabContent?: string;
    cursor?: string;
    panel?: string;
  };
  color?: string;
  disableAnimation?: boolean;
  disableCursorAnimation?: boolean;
  fullWidth?: boolean;
  isVertical?: boolean;
  orientation?: 'horizontal' | 'vertical';
  placement?: string;
  radius?: string;
  size?: string;
  variant?: TabsVariant;
};

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

function normalizeKey(key: Key | null, fallback: number): string {
  if (key == null) {
    return String(fallback);
  }

  return String(key)
    .replace(/^\.\$/, '')
    .replace(/^\./, '')
    .replace(/^\$/, '')
    .replace(/=0/g, '=')
    .replace(/=2/g, ':');
}

function mapVariant(variant?: TabsVariant): HeroUITabsProps['variant'] {
  return variant === 'underlined' || variant === 'secondary' ? 'secondary' : 'primary';
}

export function Tab(_props: TabProps) {
  return null;
}

export function Tabs({
  'aria-label': ariaLabel,
  children,
  className,
  classNames,
  color: _color,
  disableAnimation: _disableAnimation,
  disableCursorAnimation,
  fullWidth,
  isVertical,
  orientation,
  placement: _placement,
  radius: _radius,
  size: _size,
  variant,
  ...props
}: TabsProps) {
  const tabChildren = Children.toArray(children).filter(isValidElement) as Array<ReactElement<TabProps>>;

  return (
    <HeroUITabs
      className={combineClasses(classNames?.base, className)}
      orientation={isVertical ? 'vertical' : orientation}
      variant={mapVariant(variant)}
      {...props}
    >
      <HeroUITabs.ListContainer>
        <HeroUITabs.List
          aria-label={ariaLabel}
          className={combineClasses(fullWidth && 'w-full', classNames?.tabList)}
        >
          {tabChildren.map((child, index) => {
            const id = normalizeKey(child.key, index);

            return (
              <HeroUITabs.Tab
                key={id}
                className={combineClasses(fullWidth && 'flex-1', classNames?.tab, child.props.className)}
                id={id}
                isDisabled={child.props.isDisabled}
              >
                {child.props.title ?? child.props.children}
                {disableCursorAnimation ? null : <HeroUITabs.Indicator className={classNames?.cursor} />}
              </HeroUITabs.Tab>
            );
          })}
        </HeroUITabs.List>
      </HeroUITabs.ListContainer>
      {tabChildren.map((child, index) => {
        const id = normalizeKey(child.key, index);

        return (
          <HeroUITabs.Panel key={id} className={classNames?.panel} id={id}>
            {child.props.children}
          </HeroUITabs.Panel>
        );
      })}
    </HeroUITabs>
  );
}
