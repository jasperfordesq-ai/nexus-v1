// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  createContext,
  useContext,
  type ComponentPropsWithoutRef,
  type ReactNode,
} from 'react';
import { Popover as HeroUIPopover } from '@heroui-v3/react';

type HeroUIPopoverProps = ComponentPropsWithoutRef<typeof HeroUIPopover>;
type HeroUIPopoverContentProps = ComponentPropsWithoutRef<typeof HeroUIPopover.Content>;
type HeroUIPopoverTriggerProps = ComponentPropsWithoutRef<typeof HeroUIPopover.Trigger>;
type HeroUIPopoverDialogProps = ComponentPropsWithoutRef<typeof HeroUIPopover.Dialog>;

type LegacyPopoverClassNames = {
  base?: string;
  trigger?: string;
  content?: string;
  arrow?: string;
  dialog?: string;
};

type PopoverContextValue = {
  classNames?: LegacyPopoverClassNames;
  containerPadding?: HeroUIPopoverContentProps['containerPadding'];
  offset?: HeroUIPopoverContentProps['offset'];
  placement?: HeroUIPopoverContentProps['placement'];
  portalContainer?: HTMLElement;
  shouldBlockScroll?: boolean;
  shouldFlip?: HeroUIPopoverContentProps['shouldFlip'];
  showArrow?: boolean;
};

const PopoverContext = createContext<PopoverContextValue>({});

export type PopoverProps = Omit<HeroUIPopoverProps, 'children'> & {
  backdrop?: 'transparent' | 'opaque' | 'blur' | string;
  children?: ReactNode;
  className?: string;
  classNames?: LegacyPopoverClassNames;
  containerPadding?: HeroUIPopoverContentProps['containerPadding'];
  motionProps?: unknown;
  offset?: HeroUIPopoverContentProps['offset'];
  placement?: HeroUIPopoverContentProps['placement'];
  portalContainer?: HTMLElement;
  radius?: string;
  shadow?: string;
  shouldBlockScroll?: boolean;
  shouldFlip?: HeroUIPopoverContentProps['shouldFlip'];
  showArrow?: boolean;
  size?: string;
};

export type PopoverTriggerProps = HeroUIPopoverTriggerProps;

export type PopoverContentProps = Omit<HeroUIPopoverContentProps, 'children' | 'className'> & {
  children?: ReactNode;
  className?: string;
  classNames?: Pick<LegacyPopoverClassNames, 'content' | 'arrow' | 'dialog'>;
  shouldBlockScroll?: boolean;
  showArrow?: boolean;
};

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

export function Popover({
  backdrop: _backdrop,
  children,
  className,
  classNames,
  containerPadding,
  motionProps: _motionProps,
  offset,
  placement,
  portalContainer,
  radius: _radius,
  shadow: _shadow,
  shouldBlockScroll,
  shouldFlip,
  showArrow,
  size: _size,
  ...props
}: PopoverProps) {
  return (
    <PopoverContext.Provider
      value={{
        classNames: {
          ...classNames,
          base: combineClasses(classNames?.base, className),
        },
        containerPadding,
        offset,
        placement,
        portalContainer,
        shouldBlockScroll,
        shouldFlip,
        showArrow,
      }}
    >
      <HeroUIPopover {...props}>
        {children}
      </HeroUIPopover>
    </PopoverContext.Provider>
  );
}

export function PopoverTrigger({ children, className, ...props }: PopoverTriggerProps) {
  const { classNames } = useContext(PopoverContext);

  return (
    <HeroUIPopover.Trigger
      className={combineClasses(classNames?.trigger, className)}
      {...props}
    >
      {children}
    </HeroUIPopover.Trigger>
  );
}

export function PopoverContent({
  children,
  className,
  classNames,
  containerPadding,
  offset,
  placement,
  shouldBlockScroll: _shouldBlockScroll,
  shouldFlip,
  showArrow,
  ...props
}: PopoverContentProps) {
  const context = useContext(PopoverContext);
  const shouldRenderArrow = showArrow ?? context.showArrow;

  return (
    <HeroUIPopover.Content
      className={combineClasses(context.classNames?.base, context.classNames?.content, classNames?.content, className)}
      containerPadding={containerPadding ?? context.containerPadding}
      offset={offset ?? context.offset}
      placement={placement ?? context.placement}
      shouldFlip={shouldFlip ?? context.shouldFlip}
      UNSTABLE_portalContainer={context.portalContainer}
      {...props}
    >
      {shouldRenderArrow ? (
        <HeroUIPopover.Arrow className={combineClasses(context.classNames?.arrow, classNames?.arrow)} />
      ) : null}
      <HeroUIPopover.Dialog className={combineClasses('p-0', context.classNames?.dialog, classNames?.dialog)}>
        {children}
      </HeroUIPopover.Dialog>
    </HeroUIPopover.Content>
  );
}

export type PopoverDialogProps = HeroUIPopoverDialogProps;
export const PopoverDialog = HeroUIPopover.Dialog;
export const PopoverHeading = HeroUIPopover.Heading;
export const PopoverArrow = HeroUIPopover.Arrow;
