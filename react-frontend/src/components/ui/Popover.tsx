// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  cloneElement,
  createContext,
  isValidElement,
  use,
  type ComponentPropsWithoutRef,
  type ReactElement,
  type ReactNode,
} from 'react';
import { Pressable } from '@react-aria/interactions';
import type { DOMAttributes } from '@react-types/shared';
import { Popover as HeroUIPopover } from '@heroui/react/popover';

type HeroUIPopoverProps = ComponentPropsWithoutRef<typeof HeroUIPopover>;
type HeroUIPopoverContentProps = ComponentPropsWithoutRef<typeof HeroUIPopover.Content>;
type HeroUIPopoverTriggerProps = ComponentPropsWithoutRef<typeof HeroUIPopover.Trigger>;
type HeroUIPopoverDialogProps = ComponentPropsWithoutRef<typeof HeroUIPopover.Dialog>;
type LegacyPlacement = HeroUIPopoverContentProps['placement'] | string;

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
  disableMobileSheet?: boolean;
  offset?: HeroUIPopoverContentProps['offset'];
  placement?: LegacyPlacement;
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
  /** Opt out of the phone bottom-sheet conversion and keep an anchored popover at every width. */
  disableMobileSheet?: boolean;
  motionProps?: unknown;
  offset?: HeroUIPopoverContentProps['offset'];
  placement?: LegacyPlacement;
  portalContainer?: HTMLElement;
  radius?: string;
  shadow?: string;
  shouldBlockScroll?: boolean;
  shouldFlip?: HeroUIPopoverContentProps['shouldFlip'];
  showArrow?: boolean;
  size?: string;
};

export type PopoverTriggerProps = Omit<HeroUIPopoverTriggerProps, 'children'> & {
  children: ReactElement;
};

export type PopoverContentProps = Omit<HeroUIPopoverContentProps, 'children' | 'className'> & {
  children?: ReactNode;
  className?: string;
  classNames?: Pick<LegacyPopoverClassNames, 'content' | 'arrow' | 'dialog'>;
  disableMobileSheet?: boolean;
  shouldBlockScroll?: boolean;
  showArrow?: boolean;
};

function combineClasses(...classes: Array<string | false | undefined>): string | undefined {
  const className = classes.filter(Boolean).join(' ');

  return className || undefined;
}

function normalizePlacement(placement?: LegacyPlacement): HeroUIPopoverContentProps['placement'] | undefined {
  // admin-i18n-ignore: converts a legacy placement token to HeroUI's protocol value; never rendered
  return placement?.replace('-', ' ') as HeroUIPopoverContentProps['placement'] | undefined;
}

const NATIVE_PRESSABLE_TAGS = new Set(['a', 'area', 'button', 'input', 'select', 'summary', 'textarea']);

function isProjectButton(child: ReactElement): boolean {
  if (typeof child.type === 'string') return false;

  return (child.type as { displayName?: string }).displayName === 'Button';
}

function isNativePressable(
  child: ReactElement,
): child is ReactElement<DOMAttributes, string> {
  return typeof child.type === 'string' && NATIVE_PRESSABLE_TAGS.has(child.type);
}

export function Popover({
  backdrop: _backdrop,
  children,
  className,
  classNames,
  containerPadding,
  disableMobileSheet,
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
        disableMobileSheet,
        offset,
        placement: normalizePlacement(placement),
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
  const { classNames } = use(PopoverContext);
  const triggerClassName = combineClasses(classNames?.trigger, className);

  if (isValidElement<{ className?: string }>(children) && isProjectButton(children)) {
    return cloneElement(children, {
      ...(props as Record<string, unknown>),
      className: combineClasses(children.props.className, triggerClassName),
    } as { className?: string });
  }

  if (isValidElement(children) && isNativePressable(children)) {
    const pressableChild = cloneElement(children, {
      ...(props as Record<string, unknown>),
      className: combineClasses(children.props.className, triggerClassName),
    } as DOMAttributes) as ReactElement<DOMAttributes, string>;

    return <Pressable>{pressableChild}</Pressable>;
  }

  return (
    <HeroUIPopover.Trigger
      className={triggerClassName}
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
  disableMobileSheet,
  offset,
  placement,
  shouldBlockScroll,
  shouldFlip,
  showArrow,
  isNonModal,
  ...props
}: PopoverContentProps) {
  const context = use(PopoverContext);
  const shouldRenderArrow = showArrow ?? context.showArrow;
  const effectiveShouldBlockScroll = shouldBlockScroll ?? context.shouldBlockScroll;
  const sheetDisabled = disableMobileSheet ?? context.disableMobileSheet;

  // React Aria's modal Popover owns scroll locking and exposes the inverse
  // contract through `isNonModal`. HeroUI 3.1.0 does not consume the legacy
  // `shouldBlockScroll` prop directly, so map false explicitly instead of
  // forwarding a no-op DOM prop. An explicit v3 `isNonModal` always wins.
  const effectiveIsNonModal = isNonModal ?? (effectiveShouldBlockScroll === false ? true : undefined);

  return (
    <HeroUIPopover.Content
      className={combineClasses(
        !sheetDisabled && 'nexus-responsive-popover',
        context.classNames?.base,
        context.classNames?.content,
        classNames?.content,
        className,
      )}
      containerPadding={containerPadding ?? context.containerPadding}
      offset={offset ?? context.offset}
      placement={(normalizePlacement(placement) ?? context.placement) as HeroUIPopoverContentProps['placement']}
      isNonModal={effectiveIsNonModal}
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
