// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  Children,
  createContext,
  type ComponentType,
  type HTMLAttributes,
  isValidElement,
  type ReactNode,
  type Ref,
  useCallback,
  use,
  useMemo,
} from "react";
import { useTranslation } from "react-i18next";
import { Modal as HeroUIModal } from "@heroui/react/modal";

import { cn } from "@/lib/helpers";

type ModalSize =
  "xs" | "sm" | "md" | "lg" | "xl" | "2xl" | "3xl" | "4xl" | "5xl" | "full";
type ModalPlacement =
  "auto" | "top" | "top-center" | "center" | "bottom" | "bottom-center";
type ModalBackdrop = "opaque" | "blur" | "transparent";
type ModalScrollBehavior = "inside" | "outside" | "normal";

type ModalClassNames = Partial<{
  base: string;
  body: string;
  backdrop: string;
  closeButton: string;
  footer: string;
  header: string;
  wrapper: string;
}>;

type ModalContextValue = {
  backdrop?: ModalBackdrop;
  classNames?: ModalClassNames;
  closeLabel?: string;
  dialogProps?: Omit<HTMLAttributes<HTMLDivElement>, "children" | "className">;
  hideCloseButton?: boolean;
  isDismissable?: boolean;
  isKeyboardDismissDisabled?: boolean;
  isOpen?: boolean;
  onClose?: () => void;
  onOpenChange?: (isOpen: boolean) => void;
  placement?: ModalPlacement;
  portalContainer?: HTMLElement;
  scrollBehavior?: ModalScrollBehavior;
  shouldBlockScroll?: boolean;
  size?: ModalSize;
};

export interface ModalProps
  extends
    ModalContextValue,
    Omit<
      HTMLAttributes<HTMLDivElement>,
      "children" | "className" | "onChange"
    > {
  children?: ReactNode;
  className?: string;
  defaultOpen?: boolean;
  disableAnimation?: boolean;
  motionProps?: unknown;
}

export type ModalContentRenderProp = (onClose: () => void) => ReactNode;

export interface ModalContentProps extends Omit<
  HTMLAttributes<HTMLDivElement>,
  "children"
> {
  children?: ReactNode | ModalContentRenderProp;
}

export interface ModalSectionProps extends HTMLAttributes<HTMLDivElement> {
  children?: ReactNode;
}

export interface ModalHeadingProps extends HTMLAttributes<HTMLHeadingElement> {
  children?: ReactNode;
  level?: number;
}

const ModalContext = createContext<ModalContextValue>({});

type ModalDialogRenderProps = { close: () => void };
type CompatModalDialogProps = Omit<
  HTMLAttributes<HTMLDivElement>,
  "children"
> & {
  children: (renderProps: ModalDialogRenderProps) => ReactNode;
  ref?: Ref<HTMLDivElement>;
};

const ModalDialog =
  HeroUIModal.Dialog as unknown as ComponentType<CompatModalDialogProps>;

const CONTAINER_SIZES = new Set<ModalSize>(["xs", "sm", "md", "lg", "full"]);

const sizeClassName: Partial<Record<ModalSize, string>> = {
  xl: "sm:max-w-xl",
  "2xl": "sm:max-w-2xl",
  "3xl": "sm:max-w-3xl",
  "4xl": "sm:max-w-4xl",
  "5xl": "sm:max-w-5xl",
};

const modalBackdropClassName = "z-[var(--z-modal-backdrop)]";
const modalContainerClassName = [
  "z-[var(--z-modal)] box-border",
  "ps-[calc(var(--safe-area-left)+1rem)] pe-[calc(var(--safe-area-right)+1rem)]",
  "pt-[calc(var(--safe-area-top)+1rem)] pb-[calc(var(--safe-area-bottom)+1rem)]",
  "sm:ps-[calc(var(--safe-area-left)+2.5rem)] sm:pe-[calc(var(--safe-area-right)+2.5rem)]",
  "sm:pt-[calc(var(--safe-area-top)+2.5rem)] sm:pb-[calc(var(--safe-area-bottom)+2.5rem)]",
].join(" ");

function normalizeScroll(scrollBehavior?: ModalScrollBehavior) {
  return scrollBehavior === "normal" ? "inside" : scrollBehavior;
}

function normalizeContainerSize(size?: ModalSize) {
  if (!size) {
    return undefined;
  }

  return CONTAINER_SIZES.has(size)
    ? (size as "xs" | "sm" | "md" | "lg" | "full")
    : "lg";
}

function normalizePlacement(placement?: ModalPlacement) {
  if (placement === "top-center") {
    return "top";
  }

  if (placement === "bottom-center") {
    return "bottom";
  }

  return placement;
}

export function Modal({
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
  scrollBehavior,
  shouldBlockScroll,
  size,
  ...dialogProps
}: ModalProps) {
  const value = useMemo<ModalContextValue>(
    () => ({
      backdrop,
      classNames: {
        ...classNames,
        base: cn(classNames?.base, className),
      },
      closeLabel,
      dialogProps,
      hideCloseButton,
      isDismissable,
      isKeyboardDismissDisabled,
      isOpen: isOpen ?? defaultOpen,
      onClose,
      onOpenChange,
      placement,
      portalContainer,
      scrollBehavior,
      shouldBlockScroll,
      size,
    }),
    [
      backdrop,
      className,
      classNames,
      closeLabel,
      defaultOpen,
      dialogProps,
      hideCloseButton,
      isDismissable,
      isKeyboardDismissDisabled,
      isOpen,
      onClose,
      onOpenChange,
      placement,
      portalContainer,
      scrollBehavior,
      shouldBlockScroll,
      size,
    ],
  );

  return (
    <ModalContext.Provider value={value}>{children}</ModalContext.Provider>
  );
}

export function ModalContent({
  children,
  className,
  ref,
  ...props
}: ModalContentProps & { ref?: Ref<HTMLDivElement> }) {
  const { t } = useTranslation("common");
  const {
    backdrop,
    classNames,
    closeLabel,
    dialogProps,
    hideCloseButton,
    isDismissable,
    isKeyboardDismissDisabled,
    isOpen,
    onClose,
    onOpenChange,
    placement,
    portalContainer,
    scrollBehavior,
    size,
  } = use(ModalContext);

  const handleOpenChange = useCallback(
    (nextOpen: boolean) => {
      onOpenChange?.(nextOpen);
      if (!nextOpen) {
        onClose?.();
      }
    },
    [onClose, onOpenChange],
  );

  return (
    <HeroUIModal.Backdrop
      className={cn(modalBackdropClassName, classNames?.backdrop)}
      isDismissable={isDismissable}
      isKeyboardDismissDisabled={isKeyboardDismissDisabled}
      isOpen={isOpen}
      onOpenChange={handleOpenChange}
      variant={backdrop}
      UNSTABLE_portalContainer={portalContainer}
    >
      <HeroUIModal.Container
        className={cn(
          "z-[var(--z-modal)]",
          size === "full" ? undefined : modalContainerClassName,
          classNames?.wrapper,
        )}
        placement={normalizePlacement(placement)}
        scroll={normalizeScroll(scrollBehavior)}
        size={normalizeContainerSize(size)}
      >
        <ModalDialog
          ref={ref}
          className={cn(
            size ? sizeClassName[size] : undefined,
            classNames?.base,
            className,
          )}
          {...dialogProps}
          {...props}
        >
          {(renderProps: ModalDialogRenderProps) => (
            <>
              {!hideCloseButton && (
                <HeroUIModal.CloseTrigger
                  aria-label={closeLabel ?? t("accessibility.close")}
                  className={classNames?.closeButton}
                />
              )}
              {typeof children === "function"
                ? (children as ModalContentRenderProp)(() => {
                    renderProps.close();
                  })
                : children}
            </>
          )}
        </ModalDialog>
      </HeroUIModal.Container>
    </HeroUIModal.Backdrop>
  );
}

export function ModalHeading({
  className,
  ref,
  ...props
}: ModalHeadingProps & { ref?: Ref<HTMLHeadingElement> }) {
  return <HeroUIModal.Heading ref={ref} className={className} {...props} />;
}

export function ModalHeader({
  children,
  className,
  id,
  ref,
  ...props
}: ModalSectionProps & { ref?: Ref<HTMLDivElement> }) {
  const { classNames } = use(ModalContext);
  const hasExplicitHeading = Children.toArray(children).some(
    (child) => isValidElement(child) && child.type === ModalHeading,
  );

  return (
    <HeroUIModal.Header
      ref={ref}
      className={cn(classNames?.header, className)}
      id={hasExplicitHeading ? id : undefined}
      {...props}
    >
      {hasExplicitHeading ? (
        children
      ) : (
        <ModalHeading id={id}>{children}</ModalHeading>
      )}
    </HeroUIModal.Header>
  );
}

export function ModalBody({
  className,
  ref,
  ...props
}: ModalSectionProps & { ref?: Ref<HTMLDivElement> }) {
  const { classNames } = use(ModalContext);

  return (
    <HeroUIModal.Body
      ref={ref}
      className={cn(classNames?.body, className)}
      {...props}
    />
  );
}

export function ModalFooter({
  className,
  ref,
  ...props
}: ModalSectionProps & { ref?: Ref<HTMLDivElement> }) {
  const { classNames } = use(ModalContext);

  return (
    <HeroUIModal.Footer
      ref={ref}
      className={cn(classNames?.footer, className)}
      {...props}
    />
  );
}
