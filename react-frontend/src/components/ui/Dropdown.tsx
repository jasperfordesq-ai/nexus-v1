// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  cloneElement,
  createContext,
  Fragment,
  isValidElement,
  use,
  type ComponentProps,
  type ReactNode,
} from 'react';
import { Description } from '@heroui/react/description';
import { Dropdown as HeroDropdown, type Dropdown as HeroDropdownTypes } from '@heroui/react/dropdown';
import { Header } from '@heroui/react/header';
import { Kbd } from '@heroui/react/kbd';
import { Label } from '@heroui/react/label';
import { Separator } from '@heroui/react/separator';
import { cn } from '@/lib/helpers';

type HeroDropdownProps = ComponentProps<typeof HeroDropdown>;
type HeroDropdownTriggerProps = ComponentProps<typeof HeroDropdown.Trigger>;
type HeroDropdownPopoverProps = ComponentProps<typeof HeroDropdown.Popover>;
type HeroDropdownItemProps = ComponentProps<typeof HeroDropdown.Item>;
type HeroDropdownSectionProps = ComponentProps<typeof HeroDropdown.Section>;
type HeroDropdownMenuProps<T extends object = object> = HeroDropdownTypes<T>['MenuProps'];
type LegacyPlacement =
  | HeroDropdownPopoverProps['placement']
  | 'bottom-start'
  | 'bottom-end'
  | 'top-start'
  | 'top-end'
  | 'left-start'
  | 'left-end'
  | 'right-start'
  | 'right-end';

interface DropdownContextValue {
  placement?: LegacyPlacement;
  shouldBlockScroll?: boolean;
}

const DropdownContext = createContext<DropdownContextValue>({});

// Propagates the menu's selectionMode down to items so they only render a
// selection indicator (checkmark) when the menu is actually selectable.
// Action menus must NOT show an indicator gutter (matches v3 demos).
const DropdownSelectionContext = createContext<'none' | 'single' | 'multiple' | undefined>(undefined);

export interface DropdownProps extends Omit<HeroDropdownProps, 'children'> {
  children: ReactNode;
  placement?: LegacyPlacement;
  shouldBlockScroll?: boolean;
}

export function Dropdown({ children, placement, shouldBlockScroll, ...props }: DropdownProps) {
  return (
    <DropdownContext.Provider value={{ placement, shouldBlockScroll }}>
      <HeroDropdown {...props}>{children}</HeroDropdown>
    </DropdownContext.Provider>
  );
}

export type DropdownTriggerProps = HeroDropdownTriggerProps;

export function DropdownTrigger({ children, className, ...props }: DropdownTriggerProps) {
  if (isValidElement<{ className?: string }>(children)) {
    const childClassName = typeof children.props.className === 'string' ? children.props.className : undefined;
    const triggerClassName = typeof className === 'string' ? className : undefined;

    return cloneElement(children, {
      ...(props as Record<string, unknown>),
      className: cn(childClassName, triggerClassName),
    } as { className?: string });
  }

  return <HeroDropdown.Trigger {...props} className={className}>{children}</HeroDropdown.Trigger>;
}

interface LegacyDropdownClassNames {
  base?: string;
  list?: string;
}

export interface DropdownMenuProps<T extends object = object> extends Omit<HeroDropdownMenuProps<T>, 'className'> {
  className?: string;
  classNames?: LegacyDropdownClassNames;
  color?: string;
  itemClasses?: unknown;
  variant?: string;
}

export function DropdownMenu<T extends object = object>({
  children,
  className,
  classNames,
  color: _color,
  itemClasses: _itemClasses,
  variant: _variant,
  ...props
}: DropdownMenuProps<T>) {
  const { placement, shouldBlockScroll } = use(DropdownContext);
  const popoverProps = shouldBlockScroll === undefined
    ? {}
    : ({ shouldBlockScroll } as Partial<HeroDropdownPopoverProps>);
  const selectionMode = (props as { selectionMode?: 'none' | 'single' | 'multiple' }).selectionMode;

  return (
    <DropdownSelectionContext.Provider value={selectionMode}>
      <HeroDropdown.Popover
        className={classNames?.base}
        placement={normalizePlacement(placement)}
        {...popoverProps}
      >
        <HeroDropdown.Menu {...props} className={cn(classNames?.list, className)}>
          {children}
        </HeroDropdown.Menu>
      </HeroDropdown.Popover>
    </DropdownSelectionContext.Provider>
  );
}

export interface DropdownItemProps
  extends Omit<HeroDropdownItemProps, 'children' | 'id' | 'textValue' | 'variant'> {
  children?: ReactNode;
  color?: string;
  description?: ReactNode;
  endContent?: ReactNode;
  id?: HeroDropdownItemProps['id'];
  isReadOnly?: boolean;
  shortcut?: ReactNode;
  startContent?: ReactNode;
  textValue?: string;
  variant?: HeroDropdownItemProps['variant'] | 'light' | 'flat' | 'faded' | 'shadow' | 'bordered';
}

export function DropdownItem({
  children,
  className,
  color,
  description,
  endContent,
  isDisabled,
  isReadOnly,
  shortcut,
  startContent,
  textValue,
  variant,
  ...props
}: DropdownItemProps) {
  const danger = color === 'danger' || variant === 'danger';
  const resolvedTextValue = textValue ?? inferTextValue(children);
  const selectionMode = use(DropdownSelectionContext);
  const isSelectable = selectionMode === 'single' || selectionMode === 'multiple';

  return (
    <HeroDropdown.Item
      {...props}
      className={className}
      isDisabled={isDisabled || isReadOnly}
      textValue={resolvedTextValue}
      variant={danger ? 'danger' : 'default'}
    >
      {isSelectable && <HeroDropdown.ItemIndicator />}
      {startContent}
      {description ? (
        <div className="flex flex-col">
          {renderLabel(children)}
          <Description>{description}</Description>
        </div>
      ) : (
        renderLabel(children)
      )}
      {endContent}
      {shortcut && (
        <Kbd className="ms-auto" slot="keyboard" variant="light">
          <Kbd.Content>{shortcut}</Kbd.Content>
        </Kbd>
      )}
    </HeroDropdown.Item>
  );
}

export interface DropdownSectionProps extends Omit<HeroDropdownSectionProps, 'children'> {
  children?: ReactNode;
  showDivider?: boolean;
  title?: ReactNode;
}

export function DropdownSection({ children, showDivider, title, ...props }: DropdownSectionProps) {
  return (
    <Fragment>
      <HeroDropdown.Section {...props}>
        {title && <Header>{title}</Header>}
        {children}
      </HeroDropdown.Section>
      {showDivider && <Separator />}
    </Fragment>
  );
}

function renderLabel(children: ReactNode) {
  if (typeof children === 'string' || typeof children === 'number') {
    return <Label>{children}</Label>;
  }

  return children;
}

function inferTextValue(node: ReactNode): string | undefined {
  if (typeof node === 'string' || typeof node === 'number') {
    return String(node);
  }

  if (Array.isArray(node)) {
    const text = node.map(inferTextValue).filter(Boolean).join(' ').trim();

    return text || undefined;
  }

  if (isValidElement<{ children?: ReactNode }>(node)) {
    return inferTextValue(node.props.children);
  }

  return undefined;
}

function normalizePlacement(placement: LegacyPlacement | undefined): HeroDropdownPopoverProps['placement'] {
  return placement?.replace('-', ' ') as HeroDropdownPopoverProps['placement'] | undefined;
}
