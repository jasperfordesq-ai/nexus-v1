// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  createContext,
  type ComponentProps,
  type ComponentType,
  type CSSProperties,
  type HTMLAttributes,
  type ReactNode,
  type Ref,
  useCallback,
  use,
  useMemo,
} from 'react';
import { useTranslation } from 'react-i18next';
import { Drawer as HeroUIDrawer } from '@heroui/react/drawer';

import { cn } from '@/lib/helpers';
import { useMediaQuery } from '@/hooks/useMediaQuery';

type DrawerPlacement = 'top' | 'bottom' | 'left' | 'right';
type DrawerSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl' | '5xl' | 'full';
type DrawerBackdrop = 'opaque' | 'blur' | 'transparent';

type DrawerClassNames = Partial<{
  base: string;
  body: string;
  backdrop: string;
  closeButton: string;
  footer: string;
  header: string;
  wrapper: string;
}>;

type DrawerContextValue = {
  backdrop?: DrawerBackdrop;
  classNames?: DrawerClassNames;
  closeLabel?: string;
  hideCloseButton?: boolean;
  isDismissable?: boolean;
  isKeyboardDismissDisabled?: boolean;
  isOpen?: boolean;
  onClose?: () => void;
  onOpenChange?: (isOpen: boolean) => void;
  placement?: DrawerPlacement;
  portalContainer?: HTMLElement;
  size?: DrawerSize;
};

export interface DrawerProps
  extends DrawerContextValue,
  Omit<HTMLAttributes<HTMLDivElement>, 'children' | 'className' | 'onChange'> {
  children?: ReactNode;
  className?: string;
  closeButton?: ReactNode;
  defaultOpen?: boolean;
  disableAnimation?: boolean;
  motionProps?: unknown;
  radius?: string;
  shouldBlockScroll?: boolean;
}

export type DrawerContentRenderProp = (onClose: () => void) => ReactNode;

export interface DrawerContentProps {
  'aria-describedby'?: string;
  'aria-label'?: string;
  'aria-labelledby'?: string;
  children?: ReactNode | DrawerContentRenderProp;
  className?: string;
  id?: string;
  role?: 'dialog' | 'alertdialog';
  style?: CSSProperties;
}

export interface DrawerSectionProps extends HTMLAttributes<HTMLDivElement> {
  children?: ReactNode;
}

const DrawerContext = createContext<DrawerContextValue>({});
const DrawerDialog = HeroUIDrawer.Dialog as ComponentType<ComponentProps<typeof HeroUIDrawer.Dialog> & { ref?: Ref<HTMLDivElement> }>;

const sizeClassName: Partial<Record<DrawerSize, string>> = {
  xs: 'max-w-xs',
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-xl',
  '2xl': 'max-w-2xl',
  '3xl': 'max-w-3xl',
  '4xl': 'max-w-4xl',
  '5xl': 'max-w-5xl',
  full: 'max-w-none',
};

export function Drawer({
  backdrop,
  children,
  className,
  classNames,
  closeLabel,
  defaultOpen,
  hideCloseButton,
  isDismissable,
  isKeyboardDismissDisabled,
  isOpen,
  onClose,
  onOpenChange,
  placement,
  portalContainer,
  size,
}: DrawerProps) {
  const value = useMemo<DrawerContextValue>(() => ({
    backdrop,
    classNames: {
      ...classNames,
      base: cn(classNames?.base, className),
    },
    closeLabel,
    hideCloseButton,
    isDismissable,
    isKeyboardDismissDisabled,
    isOpen: isOpen ?? defaultOpen,
    onClose,
    onOpenChange,
    placement,
    portalContainer,
    size,
  }), [
    backdrop,
    className,
    classNames,
    closeLabel,
    defaultOpen,
    hideCloseButton,
    isDismissable,
    isKeyboardDismissDisabled,
    isOpen,
    onClose,
    onOpenChange,
    placement,
    portalContainer,
    size,
  ]);

  return (
    <DrawerContext.Provider value={value}>
      {children}
    </DrawerContext.Provider>
  );
}

export function DrawerContent(
  { children, className, ref, ...props }: DrawerContentProps & { ref?: Ref<HTMLDivElement> },
) {
  const { t } = useTranslation('common');
  const isMobile = useMediaQuery('(max-width: 639px)');
  const {
    backdrop,
    classNames,
    closeLabel,
    hideCloseButton,
    isDismissable,
    isKeyboardDismissDisabled,
    isOpen,
    onClose,
    onOpenChange,
    placement,
    portalContainer,
    size,
  } = use(DrawerContext);
  const requestedPlacement = placement ?? 'right';
  const convertsSideDrawerToSheet = isMobile
    && (requestedPlacement === 'left' || requestedPlacement === 'right');
  const effectivePlacement = convertsSideDrawerToSheet ? 'bottom' : requestedPlacement;

  const handleOpenChange = useCallback((nextOpen: boolean) => {
    onOpenChange?.(nextOpen);
    if (!nextOpen) {
      onClose?.();
    }
  }, [onClose, onOpenChange]);

  const close = useCallback(() => {
    handleOpenChange(false);
  }, [handleOpenChange]);

  return (
    <HeroUIDrawer.Backdrop
      className={cn('z-[var(--z-modal-backdrop)]', classNames?.backdrop)}
      isDismissable={isDismissable}
      isKeyboardDismissDisabled={isKeyboardDismissDisabled}
      isOpen={isOpen}
      onOpenChange={handleOpenChange}
      variant={backdrop}
      UNSTABLE_portalContainer={portalContainer}
    >
      <HeroUIDrawer.Content
        className={cn('z-[var(--z-modal)]', classNames?.wrapper)}
        placement={effectivePlacement}
      >
        <DrawerDialog
          ref={ref}
          className={cn(
            size ? sizeClassName[size] : undefined,
            convertsSideDrawerToSheet ? 'nexus-responsive-side-drawer-sheet' : undefined,
            classNames?.base,
            className,
          )}
          {...props}
        >
          {convertsSideDrawerToSheet && (
            <HeroUIDrawer.Handle className="nexus-responsive-drawer-handle" />
          )}
          {!hideCloseButton && (
            <HeroUIDrawer.CloseTrigger
              aria-label={closeLabel ?? t('accessibility.close')}
              className={classNames?.closeButton}
            />
          )}
          {typeof children === 'function'
            ? (children as DrawerContentRenderProp)(close)
            : children}
        </DrawerDialog>
      </HeroUIDrawer.Content>
    </HeroUIDrawer.Backdrop>
  );
}

export function DrawerHeader(
  { children, className, ref, ...props }: DrawerSectionProps & { ref?: Ref<HTMLDivElement> },
) {
  const { classNames } = use(DrawerContext);

  return (
    <HeroUIDrawer.Header
      ref={ref}
      className={cn(classNames?.header, className)}
      {...props}
    >
      {children}
    </HeroUIDrawer.Header>
  );
}

export function DrawerHeading(props: ComponentProps<typeof HeroUIDrawer.Heading>) {
  return <HeroUIDrawer.Heading {...props} />;
}

export function DrawerHandle(props: ComponentProps<typeof HeroUIDrawer.Handle>) {
  return <HeroUIDrawer.Handle {...props} />;
}

export function DrawerBody(
  { className, ref, ...props }: DrawerSectionProps & { ref?: Ref<HTMLDivElement> },
) {
  const { classNames } = use(DrawerContext);

  return (
    <HeroUIDrawer.Body
      ref={ref}
      className={cn(classNames?.body, className)}
      {...props}
    />
  );
}

export function DrawerFooter(
  { className, ref, ...props }: DrawerSectionProps & { ref?: Ref<HTMLDivElement> },
) {
  const { classNames } = use(DrawerContext);

  return (
    <HeroUIDrawer.Footer
      ref={ref}
      className={cn(classNames?.footer, className)}
      {...props}
    />
  );
}
